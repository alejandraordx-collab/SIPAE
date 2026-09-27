<?php
/**
 * procesar_asistencia.php
 * Recibe el formulario POST de dashboard_docente.php y persiste
 * los registros de asistencia en la base de datos.
 *
 * Este archivo NO genera salida HTML: solo procesa datos y redirige.
 *
 * Seguridad aplicada:
 * - Solo acepta peticiones POST autenticadas con rol 'docente'.
 * - Valida cada campo antes de tocar la base de datos.
 * - Verifica que los IDs de estudiantes recibidos pertenezcan
 *   realmente al curso indicado (previene manipulación del formulario).
 * - Usa prepared statements (PDO) para toda inserción.
 * - INSERT ... ON DUPLICATE KEY UPDATE permite corregir registros ya
 *   guardados sin duplicarlos (la clave única es estudiante+fecha+bloque).
 * - Toda la operación está envuelta en una transacción: si falla un
 *   registro, se revierten todos.
 *
 * Novedades (estado = 'novedad'):
 * - Guarda un detalle en la tabla novedades: tipo, hora, observación y,
 *   opcionalmente, un documento de soporte (PDF/JPG/PNG, máx. 5 MB).
 * - El documento se guarda en soportes_novedades/ con un nombre aleatorio
 *   (nunca el nombre original ni datos del estudiante en la ruta), y esa
 *   carpeta tiene un .htaccess que bloquea el acceso web directo.
 * - Si un estudiante deja de estar marcado como 'novedad', se borra el
 *   detalle y el archivo asociado (evita dejar documentos sensibles
 *   huérfanos de una corrección).
 */

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

//  Verificar autenticación y rol 
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'docente') {
    header('Location: login.php');
    exit;
}

//  Solo se acepta POST 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard_docente.php');
    exit;
}

require_once __DIR__ . '/conexion.php';

$tiposNovedad = require __DIR__ . '/tipos_novedad.php';

//  Recoger datos del formulario 
$docenteId  = (int)$_SESSION['usuario_id'];
$curso      = trim($_POST['curso'] ?? '');
$fecha      = trim($_POST['fecha'] ?? '');
$bloque     = (int)($_POST['bloque'] ?? 0);
$asistencia = $_POST['asistencia'] ?? []; // array [ estudiante_id => estado ]

//  Construir URL de retorno con los filtros actuales 
$queryRetorno = http_build_query([
    'curso'  => $curso,
    'fecha'  => $fecha,
    'bloque' => $bloque,
]);

//  Funcin auxiliar de redireccin 
function redirigir(string $estado, string $queryRetorno): never
{
    header("Location: dashboard_docente.php?{$estado}&{$queryRetorno}");
    exit;
}

/**
 * Procesa (si viene) el archivo de soporte de una novedad para un
 * estudiante puntual. Devuelve:
 * - null si no se envió ningún archivo para ese estudiante.
 * - un array ['ruta' => .., 'nombre' => .., 'mime' => ..] si se guardó bien.
 * - una Exception (sin lanzarla) si el archivo llegó pero no pasó las
 *   validaciones; el llamador decide qué hacer (aquí: no bloquear el
 *   guardado de asistencia, solo registrar el motivo en el log).
 */
function manejarSoporteNovedad(string $campo, int $estudianteId)
{
    if (!isset($_FILES[$campo]['error'][$estudianteId])) {
        return null;
    }

    $error = $_FILES[$campo]['error'][$estudianteId];

    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        return new Exception("error de subida (código {$error})");
    }

    $tmpPath        = $_FILES[$campo]['tmp_name'][$estudianteId];
    $size           = (int)$_FILES[$campo]['size'][$estudianteId];
    $nombreOriginal = (string)$_FILES[$campo]['name'][$estudianteId];

    $maxBytes = 5 * 1024 * 1024; // 5 MB
    if ($size <= 0 || $size > $maxBytes) {
        return new Exception("tamaño no permitido ({$size} bytes)");
    }
    if (!is_uploaded_file($tmpPath)) {
        return new Exception('archivo temporal inválido');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmpPath);

    // Lista blanca de tipos MIME reales (se ignora la extensión declarada
    // por el navegador; se valida el contenido real del archivo).
    $extensionesPermitidas = [
        'application/pdf' => 'pdf',
        'image/jpeg'       => 'jpg',
        'image/png'        => 'png',
    ];

    if (!isset($extensionesPermitidas[$mime])) {
        return new Exception("tipo de archivo no permitido ({$mime})");
    }

    $directorio = __DIR__ . '/soportes_novedades';
    if (!is_dir($directorio)) {
        mkdir($directorio, 0750, true);
    }

    // Nombre aleatorio: nunca se usa el nombre original ni datos del
    // estudiante en la ruta del archivo guardado.
    $nombreDestino = bin2hex(random_bytes(16)) . '.' . $extensionesPermitidas[$mime];
    $rutaDestino   = $directorio . '/' . $nombreDestino;

    if (!move_uploaded_file($tmpPath, $rutaDestino)) {
        return new Exception('no se pudo guardar el archivo en el servidor');
    }
    chmod($rutaDestino, 0640);

    return [
        'ruta'   => 'soportes_novedades/' . $nombreDestino,
        'nombre' => mb_substr($nombreOriginal, 0, 255),
        'mime'   => $mime,
    ];
}

/**
 * Elimina el detalle de novedad (y su archivo de soporte, si tiene) de
 * un registro de asistencia. Se usa cuando el estado deja de ser
 * 'novedad', o cuando se envía un tipo de novedad inválido.
 */
function limpiarNovedad(PDO $pdo, int $asistenciaId): void
{
    $stmt = $pdo->prepare('SELECT soporte_ruta FROM novedades WHERE asistencia_id = :id');
    $stmt->execute([':id' => $asistenciaId]);
    $rutaAnterior = $stmt->fetchColumn();

    $stmtDel = $pdo->prepare('DELETE FROM novedades WHERE asistencia_id = :id');
    $stmtDel->execute([':id' => $asistenciaId]);

    if ($rutaAnterior) {
        $rutaAbsoluta = __DIR__ . '/' . $rutaAnterior;
        if (is_file($rutaAbsoluta)) {
            @unlink($rutaAbsoluta);
        }
    }
}

//  Validación de campos obligatorios 

if ($curso === '') {
    redirigir('error=validacion', $queryRetorno);
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha > date('Y-m-d')) {
    redirigir('error=validacion', $queryRetorno);
}

if ($bloque < 1 || $bloque > 8) {
    redirigir('error=validacion', $queryRetorno);
}

if (!is_array($asistencia) || empty($asistencia)) {
    redirigir('error=validacion', $queryRetorno);
}

//  Valores de estado permitidos (lista blanca) 
$estadosPermitidos = ['asistió', 'falla', 'justificado', 'novedad'];

//  Extraer y sanear los IDs recibidos 
$idsRecibidos = array_filter(
    array_map('intval', array_keys($asistencia)),
    fn($id) => $id > 0
);

if (empty($idsRecibidos)) {
    redirigir('error=validacion', $queryRetorno);
}

//  Conectar a la base de datos 
$pdo = obtenerConexion();

//  Verificación de integridad: los IDs deben pertenecer al curso 
$placeholders = implode(',', array_fill(0, count($idsRecibidos), '?'));
$stmtVerif = $pdo->prepare(
    "SELECT id FROM estudiantes
     WHERE id IN ($placeholders)
       AND curso = ?
       AND activo = 1"
);
$stmtVerif->execute([...$idsRecibidos, $curso]);
$idsValidos = array_column($stmtVerif->fetchAll(), 'id');

if (empty($idsValidos)) {
    redirigir('error=validacion', $queryRetorno);
}

//  Preparar los statements de inserción 
$stmt = $pdo->prepare(
    'INSERT INTO asistencia (estudiante_id, docente_id, fecha, bloque_clase, estado)
     VALUES (:estudiante_id, :docente_id, :fecha, :bloque, :estado)
     ON DUPLICATE KEY UPDATE
         estado         = VALUES(estado),
         docente_id      = VALUES(docente_id),
         registrado_en   = NOW()'
);

// Novedad con archivo de soporte nuevo: actualiza también los campos de soporte.
$stmtNovConSoporte = $pdo->prepare(
    'INSERT INTO novedades
        (asistencia_id, estudiante_id, docente_id, tipo, fecha, hora, observacion,
         soporte_ruta, soporte_nombre_original, soporte_tipo_mime, estado_revision)
     VALUES
        (:asistencia_id, :estudiante_id, :docente_id, :tipo, :fecha, :hora, :observacion,
         :soporte_ruta, :soporte_nombre_original, :soporte_tipo_mime, "pendiente")
     ON DUPLICATE KEY UPDATE
         tipo                     = VALUES(tipo),
         hora                     = VALUES(hora),
         observacion              = VALUES(observacion),
         soporte_ruta             = VALUES(soporte_ruta),
         soporte_nombre_original  = VALUES(soporte_nombre_original),
         soporte_tipo_mime        = VALUES(soporte_tipo_mime),
         estado_revision          = "pendiente"'
);

// Novedad sin archivo nuevo: conserva el soporte que ya hubiera (si lo hay).
$stmtNovSinSoporte = $pdo->prepare(
    'INSERT INTO novedades (asistencia_id, estudiante_id, docente_id, tipo, fecha, hora, observacion, estado_revision)
     VALUES (:asistencia_id, :estudiante_id, :docente_id, :tipo, :fecha, :hora, :observacion, "pendiente")
     ON DUPLICATE KEY UPDATE
         tipo            = VALUES(tipo),
         hora            = VALUES(hora),
         observacion     = VALUES(observacion),
         estado_revision = "pendiente"'
);

//  Ejecutar dentro de una transacción 
try {
    $pdo->beginTransaction();

    foreach ($asistencia as $estudianteId => $estado) {

        $estudianteId = (int)$estudianteId;

        if (!in_array($estudianteId, $idsValidos, true)) {
            continue;
        }
        if (!in_array($estado, $estadosPermitidos, true)) {
            continue;
        }

        $stmt->execute([
            ':estudiante_id' => $estudianteId,
            ':docente_id'    => $docenteId,
            ':fecha'         => $fecha,
            ':bloque'        => $bloque,
            ':estado'        => $estado,
        ]);

        // Con AUTO_INCREMENT + ON DUPLICATE KEY UPDATE, lastInsertId()
        // siempre devuelve el id de la fila afectada (nueva o existente).
        $asistenciaId = (int)$pdo->lastInsertId();

        if ($estado !== 'novedad') {
            limpiarNovedad($pdo, $asistenciaId);
            continue;
        }

        //  Estado = novedad: recoger y validar el detalle 
        $tipo = trim((string)($_POST['novedad_tipo'][$estudianteId] ?? ''));
        $hora = trim((string)($_POST['novedad_hora'][$estudianteId] ?? ''));
        $obs  = trim((string)($_POST['novedad_obs'][$estudianteId] ?? ''));

        if (!array_key_exists($tipo, $tiposNovedad)) {
            // Tipo ausente o inválido: no guardamos detalle a medias.
            limpiarNovedad($pdo, $asistenciaId);
            continue;
        }

        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
            $hora = null;
        }

        $obs = mb_substr($obs, 0, 1000);
        if ($obs === '') {
            $obs = null;
        }

        $archivo = manejarSoporteNovedad('novedad_soporte', $estudianteId);

        if ($archivo instanceof Exception) {
            error_log(
                '[SIPAE] Soporte de novedad rechazado (estudiante ' . $estudianteId .
                ', asistencia ' . $asistenciaId . '): ' . $archivo->getMessage()
            );
            $archivo = null;
        }

        if (is_array($archivo)) {
            $stmtNovConSoporte->execute([
                ':asistencia_id'           => $asistenciaId,
                ':estudiante_id'           => $estudianteId,
                ':docente_id'              => $docenteId,
                ':tipo'                    => $tipo,
                ':fecha'                   => $fecha,
                ':hora'                    => $hora,
                ':observacion'             => $obs,
                ':soporte_ruta'            => $archivo['ruta'],
                ':soporte_nombre_original' => $archivo['nombre'],
                ':soporte_tipo_mime'       => $archivo['mime'],
            ]);
        } else {
            $stmtNovSinSoporte->execute([
                ':asistencia_id' => $asistenciaId,
                ':estudiante_id' => $estudianteId,
                ':docente_id'    => $docenteId,
                ':tipo'          => $tipo,
                ':fecha'         => $fecha,
                ':hora'          => $hora,
                ':observacion'   => $obs,
            ]);
        }
    }

    $pdo->commit();

    redirigir('guardado=1', $queryRetorno);

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[SIPAE] Error al guardar asistencia: ' . $e->getMessage());

    redirigir('error=bd', $queryRetorno);
}
