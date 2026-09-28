<?php
/**
 * SIPAE - Inicio de Sesión (PHP)
 * Stack: PHP + MySQL
 */
require_once __DIR__ . '/conexion.php';

if (estaAutenticado()) {
    if ($_SESSION['rol'] === 'coordinador') {
        header('Location: dashboard_coordinador.php');
    } else {
        header('Location: dashboard_docente.php');
    }
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');

    if (empty($correo) || empty($contrasena)) {
        $error = 'Por favor completa todos los campos.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE correo = ? AND activo = 1");
        $stmt->execute([$correo]);
        $usuario = $stmt->fetch();

        // Verificación con password_verify o fallback para hash/plano de pruebas
        $passwordValida = false;
        if ($usuario) {
            if (password_verify($contrasena, $usuario['contrasena'])) {
                $passwordValida = true;
            } elseif ($usuario['contrasena'] === $contrasena) {
                $passwordValida = true;
            }
        }

        if ($usuario && $passwordValida) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = $usuario['rol'];
            $_SESSION['correo'] = $usuario['correo'];

            if (!empty($usuario['debe_cambiar_contrasena'])) {
                header('Location: cambiar_password.php');
                exit;
            }

            if ($usuario['rol'] === 'coordinador') {
                header('Location: dashboard_coordinador.php');
            } else {
                header('Location: dashboard_docente.php');
            }
            exit;
        } else {
            $error = 'Credenciales inválidas o usuario inactivo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPAE — Inicio de Sesión</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem; }
        .login-card { background: #fff; width: 100%; max-width: 400px; padding: 2.25rem; border-radius: 14px; border: 1.5px solid #e2e8f0; box-shadow: 0 10px 25px -5px rgb(0 0 0 / .05); }
        .login-header { text-align: center; margin-bottom: 2rem; }
        .login-header h1 { font-size: 1.75rem; font-weight: 800; color: #0f172a; letter-spacing: -.03em; }
        .login-header p { color: #64748b; font-size: .88rem; margin-top: .35rem; }
        .campo { margin-bottom: 1.25rem; }
        .campo label { display: block; font-size: .8rem; font-weight: 700; color: #475569; margin-bottom: .4rem; }
        .campo input { width: 100%; padding: .75rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: .95rem; font-family: inherit; }
        .campo input:focus { outline: none; border-color: #ee7374; ring: 2px solid #bfdbfe; }
        .btn-submit { width: 100%; padding: .85rem; background: #ee7374; color: #fff; border: none; border-radius: 8px; font-weight: 700; font-size: .95rem; cursor: pointer; margin-top: .5rem; }
        .btn-submit:hover { background: #ca6263; }
        .error-msg { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: .75rem; border-radius: 8px; font-size: .85rem; margin-bottom: 1.25rem; }
        .credenciales-demo { margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; font-size: .78rem; color: #64748b; }
        .login-logo { width: 88px; height: 88px; border-radius: 16px; overflow: hidden; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto .75rem; box-shadow: 0 2px 10px rgba(0,0,0,.10); }
        .login-logo img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .forgot-link { display: block; text-align: center; color: #ee7374; font-size: .85rem; font-weight: 600; text-decoration: none; margin-top: .1rem; }
        .forgot-link:hover { text-decoration: underline; }
        .quick-access { margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; }
        .quick-access__title { font-size: .78rem; color: #64748b; margin-bottom: .6rem; }
        .quick-access__title code { background: #f1f5f9; padding: .1rem .35rem; border-radius: 4px; font-size: .75rem; }
        .quick-access__buttons { display: flex; gap: .6rem; }
        .quick-btn { flex: 1; display: flex; align-items: center; justify-content: center; gap: .4rem; padding: .6rem .5rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; color: #334155; font-size: .82rem; font-weight: 600; cursor: pointer; font-family: inherit; }
        .quick-btn:hover { background: #f1f5f9; border-color: #cbd5e1; }
        .quick-btn svg { width: 14px; height: 14px; flex-shrink: 0; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
            <div class="login-logo"><img src="img/logo.jpg" alt="Logo de SIPAE - Colegio OEA"></div>
        <h1>SIPAE</h1>
        <p>Colegio OEA — Inicio de sesión</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php">
        <div class="campo">
            <label for="correo">Correo institucional o usuario</label>
            <input type="email" id="correo" name="correo" required placeholder="usuario@oea.edu.co" value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
        </div>

        <div class="campo">
            <label for="contrasena">Contraseña</label>
            <input type="password" id="contrasena" name="contrasena" required placeholder="••••••••">
        </div>

        <button type="submit" class="btn-submit">Ingresar al sistema</button>
    </form>

    <a href="recuperar_contrasena.php" class="forgot-link">¿Olvidaste tu contraseña?</a>

    <div class="quick-access">
        <p class="quick-access__title">Acceso de prueba rápido: (Contraseña: <code>Test1234!</code>)</p>
        <div class="quick-access__buttons">
            <button type="button" class="quick-btn" onclick="loginRapido('laura.martinez@oea.edu.co')"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>Coordinador (Laura)</button>
            <button type="button" class="quick-btn" onclick="loginRapido('carlos.herrera@oea.edu.co')"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>Docente (Carlos)</button>
        </div>
    </div>
    <script>
    function loginRapido(correo) {
        document.getElementById('correo').value = correo;
        document.getElementById('contrasena').value = 'Test1234!';
    }
    </script>
</div>

</body>
</html>
