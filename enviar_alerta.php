<?php
/**
 * SIPAE - Enviar Notificación de Alerta (PHP)
 * Stack: PHP + MySQL
 */
require_once __DIR__ . '/conexion.php';
requerirRol('coordinador');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alerta_id = (int)($_POST['alerta_id'] ?? 0);
    $metodo = $_POST['metodo'] ?? 'correo';

    if ($alerta_id > 0) {
        $stmt = $pdo->prepare("UPDATE alertas SET estado = 'notificado', notificado_en = NOW() WHERE id = ?");
        $stmt->execute([$alerta_id]);
    }
}

header('Location: dashboard_coordinador.php?alerta_enviada=1');
exit;
?>
