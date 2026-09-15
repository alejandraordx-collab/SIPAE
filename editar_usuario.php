<?php
/**
 * editar_usuario.php
 * Formulario para editar una cuenta de usuario ya existente: nombre,
 * correo, rol, y opcionalmente una nueva contraseña (por ejemplo cuando
 * la persona la olvidó). El formulario envía los datos a
 * procesar_editar_usuario.php.
 *
 * Si el campo de contraseña se deja en blanco, la contraseña actual
 * NO se modifica.
 */

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'coordinador') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/conexion.php';

$pdo = obtenerConexion();

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT id, nombre, correo, rol FROM usuarios WHERE id = :id');
$stmt->execute([':id' => $id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    header('Location: usuarios.php?error=no_encontrado');
    exit;
}

$esUsuarioActual = (int) $usuario['id'] === (int) $_SESSION['usuario_id'];

// ── Mensaje de error (llega desde procesar_editar_usuario.php) ────────────
$error = '';

if (isset($_GET['error'])) {
    $textos = [
        'validacion' => 'Revisa los datos: el nombre y el correo son obligatorios, el correo debe ser válido y, si escribiste una nueva contraseña, debe tener al menos 8 caracteres.',
        'duplicado'  => 'Ya existe otro usuario con ese correo institucional.',
        'auto_rol'   => 'No puedes quitarte a ti mismo el rol de coordinador(a) mientras tienes la sesión iniciada.',
        'bd'         => 'Error al guardar los cambios. Intenta de nuevo.',
    ];
    $error = $textos[$_GET['error']] ?? 'Ocurrió un error inesperado.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPAE — Editar usuario</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --verde: #059669;
            --verde-oscuro: #047857;
            --gris-fondo: #f0f4f8;
            --gris-borde: #e2e8f0;
            --gris-texto: #6b7280;
            --texto: #1e2a3a;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: var(--gris-fondo);
            color: var(--texto);
            min-height: 100vh;
        }

        .navbar {
            background: var(--verde);
            padding: .875rem 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,.15);
            position: sticky;
            top: 0;
        }

        .navbar__marca {
            display: flex;
            align-items: center;
            gap: .625rem;
            color: #fff;
            font-size: 1.125rem;
            font-weight: 700;
            text-decoration: none;
        }

        .navbar__marca svg { width: 28px; height: 28px; fill: #fff; }

        .navbar__link {
            color: #fff;
            text-decoration: none;
            font-size: .8rem;
            font-weight: 600;
            opacity: .9;
        }
        .navbar__link:hover { opacity: 1; text-decoration: underline; }

        .contenedor {
            max-width: 640px;
            margin: 2rem auto;
            padding: 0 1.25rem;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,.07);
            padding: 1.5rem 1.75rem;
        }

        .card__titulo {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            padding-bottom: .75rem;
            border-bottom: 2px solid var(--gris-borde);
        }

        .alerta {
            padding: .875rem 1.125rem;
            border-radius: 10px;
            font-size: .9rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #b91c1c;
        }

        .campo { display: flex; flex-direction: column; gap: .375rem; margin-bottom: 1.25rem; }

        .campo label {
            font-size: .8rem;
            font-weight: 600;
            color: var(--gris-texto);
        }

        .campo input, .campo select {
            padding: .575rem .875rem;
            border: 1.5px solid var(--gris-borde);
            border-radius: 8px;
            font-size: .925rem;
            color: var(--texto);
            font-family: inherit;
        }

        .campo input:focus, .campo select:focus {
            outline: none;
            border-color: var(--verde);
            box-shadow: 0 0 0 3px rgba(5,150,105,.12);
        }

        .campo__ayuda { font-size: .75rem; color: var(--gris-texto); }

        .acciones { display: flex; gap: .875rem; align-items: center; margin-top: 1.5rem; }

        .btn {
            padding: .7rem 1.75rem;
            border: none;
            border-radius: 8px;
            font-size: .925rem;
            font-weight: 700;
            cursor: pointer;
            color: #fff;
        }
        .btn--verde { background: var(--verde); }
        .btn--verde:hover { background: var(--verde-oscuro); }

        .btn--cancelar {
            color: var(--gris-texto);
            text-decoration: none;
            font-size: .875rem;
            font-weight: 600;
        }
        .btn--cancelar:hover { text-decoration: underline; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="dashboard_coordinador.php" class="navbar__marca">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.25C17.25 22.15 21 17.25 21 12V7L12 2zm0 2.18l7 3.89V12c0 4.35-3.1 8.4-7 9.43C8.1 20.4 5 16.35 5 12V8.07l7-3.89z"/>
        </svg>
        SIPAE — Editar usuario
    </a>
    <a href="usuarios.php" class="navbar__link">← Volver a usuarios</a>
</nav>

<main class="contenedor">

    <div class="card">
        <h2 class="card__titulo">Editar a <?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?></h2>

        <?php if ($error !== ''): ?>
            <div class="alerta"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="procesar_editar_usuario.php">
            <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">

            <div class="campo">
                <label for="nombre">Nombre completo</label>
                <input type="text" id="nombre" name="nombre" maxlength="120" required
                       value="<?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="campo">
                <label for="correo">Correo institucional</label>
                <input type="email" id="correo" name="correo" maxlength="150" required
                       value="<?= htmlspecialchars($usuario['correo'], ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="campo">
                <label for="rol">Rol</label>
                <select id="rol" name="rol" required <?= $esUsuarioActual ? 'disabled' : '' ?>>
                    <option value="docente"      <?= $usuario['rol'] === 'docente'      ? 'selected' : '' ?>>Docente</option>
                    <option value="coordinador"  <?= $usuario['rol'] === 'coordinador'  ? 'selected' : '' ?>>Coordinador(a)</option>
                </select>
                <?php if ($esUsuarioActual): ?>
                    <input type="hidden" name="rol" value="<?= htmlspecialchars($usuario['rol'], ENT_QUOTES, 'UTF-8') ?>">
                    <span class="campo__ayuda">No puedes cambiar tu propio rol mientras tienes la sesión iniciada.</span>
                <?php endif; ?>
            </div>

            <div class="campo">
                <label for="contrasena">Nueva contraseña</label>
                <input type="password" id="contrasena" name="contrasena"
                       minlength="8" maxlength="128" autocomplete="new-password">
                <span class="campo__ayuda">Déjalo en blanco para no cambiar la contraseña actual. Si la escribes, mínimo 8 caracteres.</span>
            </div>

            <div class="acciones">
                <button type="submit" class="btn btn--verde">Guardar cambios</button>
                <a href="usuarios.php" class="btn--cancelar">Cancelar</a>
            </div>
        </form>
    </div>

</main>

</body>
</html>
