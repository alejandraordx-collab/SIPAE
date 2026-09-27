<?php
session_start();

if (isset($_SESSION['usuario_id'])) {
    if (!empty($_SESSION['debe_cambiar_contrasena'])) {
        header('Location: cambiar_password.php');
        exit;
    }
    header('Location: dashboard_' . ($_SESSION['rol'] === 'coordinador' ? 'coordinador' : 'docente') . '.php');
    exit;
}

require_once __DIR__ . '/conexion.php';

$error = '';
$success = '';
$token = trim($_GET['token'] ?? '');

if ($token === '') {
    $error = 'El enlace es inválido o ha expirado.';
} else {
    $pdo = obtenerConexion();
    $tokenHash = hash('sha256', $token);
    $ahora = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        'SELECT id, usuario_id, expiracion, usado
           FROM password_reset_tokens
          WHERE token_hash = :token_hash
            AND expiracion > :ahora
            AND usado = 0
          LIMIT 1'
    );
    $stmt->execute([':token_hash' => $tokenHash, ':ahora' => $ahora]);
    $reset = $stmt->fetch();

    if (!$reset) {
        $error = 'El enlace es inválido, ya fue usado o expiró.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nueva = trim($_POST['nueva_contrasena'] ?? '');
        $confirmar = trim($_POST['confirmar_contrasena'] ?? '');

        if ($nueva === '' || $confirmar === '') {
            $error = 'Debes ingresar y confirmar la nueva contraseña.';
        } elseif (strlen($nueva) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($nueva !== $confirmar) {
            $error = 'La contraseña y la confirmación no coinciden.';
        } else {
            $hash = password_hash($nueva, PASSWORD_DEFAULT);

            $pdo->beginTransaction();
            $pdo->prepare('UPDATE usuarios SET contrasena = :hash, debe_cambiar_contrasena = 0 WHERE id = :id')->execute([
                ':hash' => $hash,
                ':id' => $reset['usuario_id'],
            ]);
            $pdo->prepare('UPDATE password_reset_tokens SET usado = 1 WHERE id = :id')->execute([
                ':id' => $reset['id'],
            ]);
            $pdo->commit();

            $success = 'La contraseña fue restablecida correctamente. Ya puedes iniciar sesión.';
            $token = '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPAE — Restablecer contraseña</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }
        body { font-family:'Segoe UI', Arial, sans-serif; background:#fdf5f4; min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .card { background:#fff; border-radius:12px; box-shadow:0 4px 24px rgba(0,0,0,.10); width:100%; max-width:420px; padding:2rem; }
        h1 { font-size:1.5rem; margin-bottom:.5rem; color:#1e2a3a; }
        p { color:#6b7280; margin-bottom:1.25rem; font-size:.95rem; }
        .alerta { padding: .75rem 1rem; border-radius:8px; margin-bottom:1rem; font-size:.875rem; }
        .alerta--error { background:#fef2f2; border:1px solid #fca5a5; color:#b91c1c; }
        .alerta--ok { background:#f0fdf4; border:1px solid #86efac; color:#15803d; }
        .campo { margin-bottom:1rem; }
        label { display:block; font-size:.85rem; font-weight:600; color:#374151; margin-bottom:.4rem; }
        input { width:100%; padding:.7rem .8rem; border:1.5px solid #d1d5db; border-radius:8px; font-size:.95rem; }
        button { width:100%; padding:.8rem; border:none; border-radius:8px; background:#ee7374; color:#fff; font-weight:700; cursor:pointer; font-size:1rem; }
        a { color:#ee7374; text-decoration:none; font-weight:600; }
    </style>
</head>
<body>
    <main class="card">
        <h1>Establecer nueva contraseña</h1>
        <p>Define una nueva contraseña para tu acceso a SIPAE.</p>

        <?php if ($error !== ''): ?>
            <div class="alerta alerta--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alerta alerta--ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($token !== '' && $error === ''): ?>
            <form method="post" action="restablecer_contrasena.php?token=<?= urlencode($token) ?>">
                <div class="campo">
                    <label for="nueva_contrasena">Nueva contraseña</label>
                    <input type="password" id="nueva_contrasena" name="nueva_contrasena" minlength="8" maxlength="128" required autocomplete="new-password">
                </div>
                <div class="campo">
                    <label for="confirmar_contrasena">Confirmar contraseña</label>
                    <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" minlength="8" maxlength="128" required autocomplete="new-password">
                </div>
                <button type="submit">Guardar nueva contraseña</button>
            </form>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div style="margin-top:1rem; text-align:center;">
                <a href="login.php">Ir al inicio</a>
            </div>
        <?php elseif ($error !== ''): ?>
            <div style="margin-top:1rem; text-align:center;">
                <a href="login.php">Volver al inicio</a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
