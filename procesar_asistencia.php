<?php
require_once __DIR__ . '/conexion.php';
requerirRol('docente');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard_docente.php');
    exit;
}

$docente_id = (int)$_SESSION['usuario_id'];
$curso = trim($_POST['curso'] ?? '');
$fecha = trim($_POST['fecha'] ?? '');
$bloque = (int)($_POST['bloque'] ?? 1);
$asistencia = $_POST['asistencia'] ?? [];
$almuerzo = $_POST['almuerzo'] ?? [];
$tipoNovedadPost = $_POST['tipo_novedad'] ?? [];

// Whitelist de tipos de novedad válidos (debe coincidir con dashboard_docente.php)
$tiposNovedadValidos = [
    'Cita médica',
    'Problema de salud',
    'Calamidad familiar',
    'Problemas de transporte',
    'Incapacidad médica',
    'Situación disciplinaria',
    'Permiso previamente solicitado',
    'Inasistencia sin justificar',
    'Otra novedad',
];

$paramsRetorno = http_build_query(['curso' => $curso, 'fecha' => $fecha, 'bloque' => $bloque]);

if (empty($curso) || empty($fecha) || empty($asistencia)) {
    header("Location: dashboard_docente.php?error=validacion&{$paramsRetorno}");
    exit;
}

try {
    $pdo->beginTransaction();

    $sql = "INSERT INTO asistencia (estudiante_id, docente_id, fecha, bloque_clase, estado, almuerzo, registrado_en)
            VALUES (:estudiante_id, :docente_id, :fecha, :bloque_clase, :estado, :almuerzo, NOW())
            ON DUPLICATE KEY UPDATE
                estado = VALUES(estado),
                almuerzo = VALUES(almuerzo),
                docente_id = VALUES(docente_id),
                registrado_en = NOW(),
                id = LAST_INSERT_ID(id)";

    $stmt = $pdo->prepare($sql);

    $sqlNov = "INSERT INTO novedades (asistencia_id, estudiante_id, docente_id, tipo, fecha, registrado_en)
               VALUES (:asistencia_id, :estudiante_id, :docente_id, :tipo, :fecha, NOW())
               ON DUPLICATE KEY UPDATE
                   tipo = VALUES(tipo),
                   docente_id = VALUES(docente_id),
                   fecha = VALUES(fecha),
                   registrado_en = NOW()";
    $stmtNov = $pdo->prepare($sqlNov);

    $stmtNovDel = $pdo->prepare("DELETE FROM novedades WHERE asistencia_id = ?");

    foreach ($asistencia as $estIdStr => $estadoStr) {
        $estudianteId = (int)$estIdStr;
        $estado = in_array($estadoStr, ['asistió', 'falla', 'justificado', 'novedad']) ? $estadoStr : 'asistió';
        $tomaAlmuerzo = isset($almuerzo[$estIdStr]) ? (int)$almuerzo[$estIdStr] : ($estado === 'asistió' ? 1 : 0);

        $stmt->execute([
            ':estudiante_id' => $estudianteId,
            ':docente_id'    => $docente_id,
            ':fecha'         => $fecha,
            ':bloque_clase'  => $bloque,
            ':estado'        => $estado,
            ':almuerzo'      => $tomaAlmuerzo,
        ]);

        $asistenciaId = (int)$pdo->lastInsertId();

        $tipoNovedadRaw = trim($tipoNovedadPost[$estIdStr] ?? '');
        $tipoNovedad = in_array($tipoNovedadRaw, $tiposNovedadValidos, true) ? $tipoNovedadRaw : '';

        if ($estado === 'novedad' && $tipoNovedad !== '') {
            $stmtNov->execute([
                ':asistencia_id' => $asistenciaId,
                ':estudiante_id' => $estudianteId,
                ':docente_id'    => $docente_id,
                ':tipo'          => $tipoNovedad,
                ':fecha'         => $fecha,
            ]);
        } else {
            $stmtNovDel->execute([$asistenciaId]);
        }
    }

    $pdo->commit();
    header("Location: dashboard_docente.php?guardado=1&{$paramsRetorno}");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: dashboard_docente.php?error=bd&{$paramsRetorno}");
    exit;
}
