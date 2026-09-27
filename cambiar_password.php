<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nueva = trim($_POST['nueva_contrasena'] ?? '');
    $confirmar = trim($_POST['confirmar_contrasena'] ?? '');

    if ($nueva === '' || $confirmar === '') {
        $error = 'Debes ingresar y confirmar la nueva contraseña.';
    } elseif (strlen($nueva) < 8) {
        $error = 'La nueva contraseña debe tener al menos 8 caracteres.';
    } elseif ($nueva !== $confirmar) {
        $error = 'La nueva contraseña y la confirmación no coinciden.';
    } else {
        $pdo = obtenerConexion();
        $hash = password_hash($nueva, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            'UPDATE usuarios
                SET contrasena = :contrasena,
                    debe_cambiar_contrasena = 0
              WHERE id = :id'
        );
        $stmt->execute([
            ':contrasena' => $hash,
            ':id' => $_SESSION['usuario_id'],
        ]);

        unset($_SESSION['debe_cambiar_contrasena']);

        if ($_SESSION['rol'] === 'coordinador') {
            header('Location: dashboard_coordinador.php');
        } else {
            header('Location: dashboard_docente.php');
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPAE — Cambiar contraseña</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #fdf5f4;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 420px;
            padding: 2rem;
        }
        h1 { font-size: 1.5rem; margin-bottom: .5rem; color: #1e2a3a; }
        p { color: #6b7280; margin-bottom: 1.5rem; font-size: .95rem; }
        .alerta {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #b91c1c;
            border-radius: 8px;
            padding: .75rem 1rem;
            margin-bottom: 1rem;
            font-size: .875rem;
        }
        .campo { margin-bottom: 1rem; }
        label {
            display: block;
            font-size: .85rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: .4rem;
        }
        input {
            width: 100%;
            padding: .7rem .8rem;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: .95rem;
        }
        button {
            width: 100%;
            padding: .8rem;
            border: none;
            border-radius: 8px;
            background: #ee7374;
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>Cambia tu contraseña</h1>
        <p>Por seguridad, esta es la primera vez que ingresas. Debes establecer una nueva contraseña antes de continuar.</p>

        <?php if (!empty($error)): ?>
            <div class="alerta"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="cambiar_password.php">
            <div class="campo">
                <label for="nueva_contrasena">Nueva contraseña</label>
                <input type="password" id="nueva_contrasena" name="nueva_contrasena" minlength="8" maxlength="128" required autocomplete="new-password">
            </div>

            <div class="campo">
                <label for="confirmar_contrasena">Confirmar contraseña</label>
                <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" minlength="8" maxlength="128" required autocomplete="new-password">
            </div>

            <button type="submit">Guardar contraseña</button>
        </form>
    </main>
</body>
</html>
