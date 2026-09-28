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
        .campo label { display: block; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #475569; margin-bottom: .4rem; }
        .campo input { width: 100%; padding: .75rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: .95rem; font-family: inherit; }
        .campo input:focus { outline: none; border-color: #2563eb; ring: 2px solid #bfdbfe; }
        .btn-submit { width: 100%; padding: .85rem; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-weight: 700; font-size: .95rem; cursor: pointer; margin-top: .5rem; }
        .btn-submit:hover { background: #1d4ed8; }
        .error-msg { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: .75rem; border-radius: 8px; font-size: .85rem; margin-bottom: 1.25rem; }
        .credenciales-demo { margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; font-size: .78rem; color: #64748b; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <h1>SIPAE</h1>
        <p>Sistema de Asistencia y Alimentación Escolar (Colegio OEA)</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php">
        <div class="campo">
            <label for="correo">Correo Institucional</label>
            <input type="email" id="correo" name="correo" required placeholder="nombre@oea.edu.co" value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
        </div>

        <div class="campo">
            <label for="contrasena">Contraseña</label>
            <input type="password" id="contrasena" name="contrasena" required placeholder="••••••••">
        </div>

        <button type="submit" class="btn-submit">Ingresar al Sistema</button>
    </form>

    <div class="credenciales-demo">
        <p style="font-weight:700;margin-bottom:.25rem">Cuentas de prueba:</p>
        <p>Docente: <code>carlos.herrera@oea.edu.co</code></p>
        <p>Coordinador: <code>laura.martinez@oea.edu.co</code></p>
        <p>Clave: <code>Test1234!</code></p>
    </div>
</div>

</body>
</html>
