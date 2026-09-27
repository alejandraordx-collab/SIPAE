<?php
/**
 * SIPAE - Procesar Asistencia (PHP)
 * Guarda o actualiza el estado de asistencia y almuerzo escolar
 * Stack: PHP + MySQL
 */
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
                registrado_en = NOW()";

    $stmt = $pdo->prepare($sql);

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
?>
