<?php
/**
 * SIPAE - Recuperar Contraseña (PHP)
 * Solicita el correo del usuario, genera un token de un solo uso
 * y (si el correo existe) envía un enlace de restablecimiento.
 */
require_once __DIR__ . '/conexion.php';

$error = '';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');

    if ($correo === '') {
        $error = 'Ingresa tu correo institucional.';
    } else {
        $stmt = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE correo = ? AND activo = 1");
        $stmt->execute([$correo]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            $token = bin2hex(random_bytes(32));
            $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmtUpdate = $pdo->prepare("UPDATE usuarios SET reset_token = ?, reset_token_expira = ? WHERE id = ?");
            $stmtUpdate->execute([$token, $expira, $usuario['id']]);

            $enlace = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/restablecer_contrasena.php?token=' . $token;

            $asunto = 'SIPAE - Recuperación de contraseña';
            $cuerpo = "Hola " . $usuario['nombre'] . ",\n\n"
                . "Recibimos una solicitud para restablecer tu contraseña de SIPAE.\n\n"
                . "Haz clic en el siguiente enlace (válido por 1 hora):\n" . $enlace . "\n\n"
                . "Si no solicitaste este cambio, puedes ignorar este mensaje.\n\n"
                . "SIPAE - Colegio OEA";
            $cabeceras = "From: SIPAE <noreply@sipae.live>\r\n";

            @mail($correo, $asunto, $cuerpo, $cabeceras);
        }

        // Mensaje genérico: no revela si el correo existe o no (evita enumeración de usuarios)
        $mensaje = 'Si el correo está registrado en el sistema, se ha enviado un enlace de recuperación. Revisa tu bandeja de entrada (y la carpeta de spam).';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPAE — Recuperar contraseña</title>
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
        <h1>Recuperar contraseña</h1>
        <p>Ingresa tu correo institucional para recibir un enlace de recuperación</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($mensaje)): ?>
        <div class="success-msg"><?= htmlspecialchars($mensaje) ?></div>
    <?php else: ?>
        <form method="post" action="recuperar_contrasena.php">
            <div class="campo">
                <label for="correo">Correo institucional</label>
                <input type="email" id="correo" name="correo" required placeholder="usuario@oea.edu.co" value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
            </div>

            <button type="submit" class="btn-submit">Enviar enlace de recuperación</button>
        </form>
    <?php endif; ?>

    <a href="login.php" class="back-link">&larr; Volver a inicio de sesión</a>
</div>

</body>
</html>
