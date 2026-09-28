<?php
/**
 * SIPAE - Restablecer Contraseña (PHP)
 * Valida el token recibido por correo y permite definir una nueva contraseña.
 */
require_once __DIR__ . '/conexion.php';

$error = '';
$exito = '';
$tokenValido = false;
$usuario = null;

$token = $_GET['token'] ?? ($_POST['token'] ?? '');

if ($token === '') {
    $error = 'Enlace de recuperación no válido.';
} else {
    $stmt = $pdo->prepare("SELECT id, reset_token_expira FROM usuarios WHERE reset_token = ?");
    $stmt->execute([$token]);
    $usuario = $stmt->fetch();

    if (!$usuario || strtotime($usuario['reset_token_expira']) < time()) {
        $error = 'El enlace de recuperación no es válido o ya expiró. Solicita uno nuevo.';
    } else {
        $tokenValido = true;
    }
}

if ($tokenValido && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nueva = $_POST['nueva'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    if ($nueva === '' || $confirmar === '') {
        $error = 'Todos los campos son obligatorios.';
    } elseif ($nueva !== $confirmar) {
        $error = 'La nueva contraseña y su confirmación no coinciden.';
    } elseif (strlen($nueva) < 6) {
        $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } else {
        $hash = password_hash($nueva, PASSWORD_BCRYPT);
        $stmtUpdate = $pdo->prepare("UPDATE usuarios SET contrasena = ?, debe_cambiar_contrasena = 0, reset_token = NULL, reset_token_expira = NULL WHERE id = ?");
        $stmtUpdate->execute([$hash, $usuario['id']]);

        $exito = 'Tu contraseña se actualizó con éxito. Ya puedes iniciar sesión.';
        $tokenValido = false;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPAE — Restablecer contraseña</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem; }
        .login-card { background: #fff; width: 100%; max-width: 400px; padding: 2.25rem; border-radius: 14px; border: 1.5px solid #e2e8f0; box-shadow: 0 10px 25px -5px rgb(0 0 0 / .05); }
        .login-header { text-align: center; margin-bottom: 2rem; }
        .login-header h1 { font-size: 1.5rem; font-weight: 800; color: #0f172a; letter-spacing: -.03em; }
        .login-header p { color: #64748b; font-size: .88rem; margin-top: .35rem; }
        .login-logo { width: 72px; height: 72px; border-radius: 16px; overflow: hidden; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto .75rem; box-shadow: 0 2px 10px rgba(0,0,0,.10); }
        .login-logo img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .campo { margin-bottom: 1.25rem; }
        .campo label { display: block; font-size: .8rem; font-weight: 700; color: #475569; margin-bottom: .4rem; }
        .campo input { width: 100%; padding: .75rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: .95rem; font-family: inherit; }
        .campo input:focus { outline: none; border-color: #ee7374; }
        .btn-submit { width: 100%; padding: .85rem; background: #ee7374; color: #fff; border: none; border-radius: 8px; font-weight: 700; font-size: .95rem; cursor: pointer; margin-top: .5rem; }
        .btn-submit:hover { background: #ca6263; }
        .error-msg { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: .75rem; border-radius: 8px; font-size: .85rem; margin-bottom: 1.25rem; }
        .success-msg { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: .75rem; border-radius: 8px; font-size: .85rem; margin-bottom: 1.25rem; }
        .back-link { display: block; text-align: center; color: #ee7374; font-size: .85rem; font-weight: 600; text-decoration: none; margin-top: 1.25rem; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="login-logo"><img src="img/logo.jpg" alt="Logo de SIPAE - Colegio OEA"></div>
        <h1>Restablecer contraseña</h1>
        <p>Define tu nueva contraseña de acceso a SIPAE</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($exito)): ?>
        <div class="success-msg"><?= htmlspecialchars($exito) ?></div>
    <?php endif; ?>

    <?php if ($tokenValido): ?>
        <form method="post" action="restablecer_contrasena.php">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="campo">
                <label for="nueva">Nueva contraseña</label>
                <input type="password" id="nueva" name="nueva" required minlength="6" placeholder="••••••••••">
            </div>

            <div class="campo">
                <label for="confirmar">Confirmar contraseña</label>
                <input type="password" id="confirmar" name="confirmar" required minlength="6" placeholder="••••••••••">
            </div>

            <button type="submit" class="btn-submit">Actualizar contraseña</button>
        </form>
    <?php endif; ?>

    <a href="login.php" class="back-link">&larr; Volver a inicio de sesión</a>
</div>

</body>
</html>
