<?php
/**
 * SIPAE - Cambiar Contraseña (PHP)
 */
require_once __DIR__ . '/conexion.php';
requerirAutenticacion();

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actual = $_POST['actual'] ?? '';
    $nueva = $_POST['nueva'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    if (empty($actual) || empty($nueva) || empty($confirmar)) {
        $error = 'Todos los campos son obligatorios.';
    } elseif ($nueva !== $confirmar) {
        $error = 'La nueva contraseña y su confirmación no coinciden.';
    } elseif (strlen($nueva) < 6) {
        $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } else {
        $stmt = $pdo->prepare("SELECT contrasena FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['usuario_id']]);
        $row = $stmt->fetch();

        $valida = false;
        if ($row) {
            if (password_verify($actual, $row['contrasena']) || $row['contrasena'] === $actual) {
                $valida = true;
            }
        }

        if (!$valida) {
            $error = 'La contraseña actual no es correcta.';
        } else {
            $nuevoHash = password_hash($nueva, PASSWORD_BCRYPT);
            $stmtUpdate = $pdo->prepare("UPDATE usuarios SET contrasena = ?, debe_cambiar_contrasena = 0 WHERE id = ?");
            $stmtUpdate->execute([$nuevoHash, $_SESSION['usuario_id']]);
            $exito = 'Contraseña actualizada con éxito.';
            
            // Redireccionar al panel
            if ($_SESSION['rol'] === 'coordinador') {
                header('Location: dashboard_coordinador.php');
            } else {
                header('Location: dashboard_docente.php');
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPAE — Cambiar Contraseña</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem; }
        .card { background: #fff; width: 100%; max-width: 420px; padding: 2rem; border-radius: 12px; border: 1.5px solid #e2e8f0; }
        h1 { font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-bottom: .5rem; }
        p { font-size: .85rem; color: #64748b; margin-bottom: 1.5rem; }
        .campo { margin-bottom: 1rem; }
        label { display: block; font-size: .75rem; font-weight: 700; text-transform: uppercase; color: #475569; margin-bottom: .35rem; }
        input { width: 100%; padding: .65rem .85rem; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: .9rem; }
        .btn { width: 100%; padding: .75rem; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; margin-top: .5rem; }
        .error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: .65rem; border-radius: 8px; font-size: .85rem; margin-bottom: 1rem; }
    </style>
</head>
<body>

<div class="card">
    <h1>Cambiar Contraseña</h1>
    <p>Por seguridad debes actualizar tu clave de acceso.</p>

    <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="cambiar_password.php">
        <div class="campo">
            <label>Contraseña Actual</label>
            <input type="password" name="actual" required>
        </div>
        <div class="campo">
            <label>Nueva Contraseña</label>
            <input type="password" name="nueva" required>
        </div>
        <div class="campo">
            <label>Confirmar Nueva Contraseña</label>
            <input type="password" name="confirmar" required>
        </div>
        <button type="submit" class="btn">Guardar Nueva Contraseña</button>
    </form>
</div>

</body>
</html>
