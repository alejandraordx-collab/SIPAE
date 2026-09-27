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
require_once __DIR__ . '/libs/correo.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');

    if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'Debes ingresar un correo válido.';
    } else {
        $pdo = obtenerConexion();
        $stmt = $pdo->prepare('SELECT id, nombre FROM usuarios WHERE correo = :correo AND activo = 1 LIMIT 1');
        $stmt->execute([':correo' => $correo]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiraEn = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $pdo->prepare('DELETE FROM password_reset_tokens WHERE usuario_id = :id')->execute([':id' => $usuario['id']]);
            $stmtInsert = $pdo->prepare(
                'INSERT INTO password_reset_tokens (usuario_id, token_hash, expiracion, usado)
                 VALUES (:usuario_id, :token_hash, :expiracion, 0)'
            );
            $stmtInsert->execute([
                ':usuario_id' => $usuario['id'],
                ':token_hash' => $tokenHash,
                ':expiracion' => $expiraEn,
            ]);

            $baseUrl = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            $enlace = $baseUrl . dirname($_SERVER['PHP_SELF']) . '/restablecer_contrasena.php?token=' . urlencode($token);

            enviarCorreoRecuperacion($correo, $usuario['nombre'], $enlace);
            $success = 'Si el correo existe en el sistema, recibirás un enlace para restablecer la contraseña.';
        } else {
            $success = 'Si el correo existe en el sistema, recibirás un enlace para restablecer la contraseña.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPAE — Recuperar contraseña</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #fdf5f4;
            min-height: 100vh;
            display:flex; align-items:center; justify-content:center;
        }
        .card {
            background:#fff; border-radius:12px; box-shadow:0 4px 24px rgba(0,0,0,.10);
            width:100%; max-width:420px; padding:2rem;
        }
        h1 { font-size:1.5rem; margin-bottom:.5rem; color:#1e2a3a; }
        p { color:#6b7280; margin-bottom:1.25rem; font-size:.95rem; }
        .alerta { padding: .75rem 1rem; border-radius:8px; margin-bottom:1rem; font-size:.875rem; }
        .alerta--error { background:#fef2f2; border:1px solid #fca5a5; color:#b91c1c; }
        .alerta--ok { background:#f0fdf4; border:1px solid #86efac; color:#15803d; }
        .campo { margin-bottom: 1rem; }
        label { display:block; font-size:.85rem; font-weight:600; color:#374151; margin-bottom:.4rem; }
        input { width:100%; padding: .7rem .8rem; border:1.5px solid #d1d5db; border-radius:8px; font-size:.95rem; }
        button {
            width:100%; padding: .8rem; border:none; border-radius:8px; background:#ee7374; color:#fff; font-weight:700; cursor:pointer; font-size:1rem;
        }
        a { color:#ee7374; text-decoration:none; font-weight:600; }
    </style>
</head>
<body>
    <main class="card">
        <h1>Recuperar contraseña</h1>
        <p>Escribe tu correo institucional para recibir un enlace seguro para restablecer tu contraseña.</p>

        <?php if ($error !== ''): ?>
            <div class="alerta alerta--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alerta alerta--ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="recuperar_contrasena.php">
            <div class="campo">
                <label for="correo">Correo institucional</label>
                <input type="email" id="correo" name="correo" required maxlength="150" placeholder="usuario@oea.edu.co">
            </div>
            <button type="submit">Enviar enlace</button>
        </form>

        <div style="margin-top:1rem; text-align:center;">
            <a href="login.php">Volver al inicio de sesión</a>
        </div>
    </main>
</body>
</html>
