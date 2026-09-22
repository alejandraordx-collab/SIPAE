<?php
/**
 * login.php
 * Formulario de inicio de sesión de SIPAE.
 *
 * Cómo funciona:
 *   1. Si la página se abre normal (GET), solo se muestra el formulario.
 *   2. Si el usuario envía el formulario (POST), buscamos su correo en
 *      la base de datos y comparamos la contraseña.
 *   3. Si todo coincide, guardamos sus datos en la sesión y lo mandamos
 *      a su panel según su rol (docente o coordinador).
 */

session_start();

// Si ya inició sesión antes, lo mandamos directo a su panel
if (isset($_SESSION['usuario_id'])) {
    redirigirSegunRol($_SESSION['rol']);
}

require_once __DIR__ . '/conexion.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $correo     = trim($_POST['correo']     ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');

    if ($correo === '' || $contrasena === '') {
        $error = 'Por favor completa todos los campos.';

    } else {
        // Buscamos en la tabla "usuarios" el correo que escribió la persona.
        // Usamos un "prepared statement" (con :correo) en vez de meter el
        // valor directo en la consulta, para que nadie pueda hacer trampa
        // escribiendo código SQL en el campo de correo.
        $pdo  = obtenerConexion();
        $stmt = $pdo->prepare(
            'SELECT id, nombre, contrasena, rol
               FROM usuarios
              WHERE correo = :correo
                AND activo = 1'
        );
        $stmt->execute([':correo' => $correo]);
        $usuario = $stmt->fetch();

        // Comparamos la contraseña escrita con la que está guardada
        // en la base de datos.
        if ($usuario && password_verify($contrasena, $usuario['contrasena'])) {

            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nombre']     = $usuario['nombre'];
            $_SESSION['rol']        = $usuario['rol'];

            redirigirSegunRol($usuario['rol']);

        } else {
            $error = 'Correo o contraseña incorrectos.';
        }
    }
}

// Manda a cada usuario a la página que le corresponde según su rol.
function redirigirSegunRol(string $rol): void
{
    if ($rol === 'coordinador') {
        header('Location: dashboard_coordinador.php');
    } else {
        header('Location: dashboard_docente.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPAE — Iniciar sesión</title>
    <style>
        /* ---- Reset y base ---- */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #fdf5f4;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        body::before {
            content: "";
            position: fixed;
            top: 50%;
            left: 50%;
            width: 560px;
            height: 560px;
            transform: translate(-50%, -50%);
            background: url('img/marca_agua.png') no-repeat center center;
            background-size: contain;
            opacity: .05;
            pointer-events: none;
            z-index: -1;
        }

        /* ---- Tarjeta principal ---- */
        .card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .10);
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 400px;
        }

        /* ---- Encabezado / logo ---- */
        .card__header {
            text-align: center;
            margin-bottom: 2rem;
        }

.card__logo {
            width: 110px;
            height: 110px;
            border-radius: 16px;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: .75rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .10);
        }

        /* Logo de SIPAE */
        .card__logo img { width: 100%; height: 100%; object-fit: contain; display: block; }

        .card__title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e2a3a;
        }

        .card__subtitle {
            font-size: .85rem;
            color: #6b7280;
            margin-top: .25rem;
        }

        /* ---- Alerta de error ---- */
        .alert {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #b91c1c;
            border-radius: 8px;
            padding: .75rem 1rem;
            font-size: .875rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .alert svg { flex-shrink: 0; width: 18px; height: 18px; fill: #b91c1c; }

        /* ---- Formulario ---- */
        .form__group { margin-bottom: 1.25rem; }

        .form__label {
            display: block;
            font-size: .875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: .375rem;
        }

        .form__input {
            width: 100%;
            padding: .625rem .875rem;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: .95rem;
            color: #1e2a3a;
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }

        .form__input:focus {
            border-color: #ee7374;
            box-shadow: 0 0 0 3px rgba(238, 115, 116, .15);
        }

        /* ---- Botón de submit ---- */
        .btn-primary {
            width: 100%;
            padding: .75rem;
            background: #ee7374;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s, transform .1s;
            margin-top: .5rem;
        }

        .btn-primary:hover  { background: #ca6263; }
        .btn-primary:active { transform: scale(.98); }

        /* ---- Pie de tarjeta ---- */
        .card__footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: .8rem;
            color: #9ca3af;
        }
    </style>
</head>
<body>

<main class="card" role="main">

    <!-- Encabezado -->
    <header class="card__header">
        <div class="card__logo">
            <img src="img/logo.jpg" alt="Logo de SIPAE - Colegio OEA">
        </div>
        <h1 class="card__title">SIPAE</h1>
        <p class="card__subtitle">Colegio OEA — Inicio de sesión</p>
    </header>

    <!-- Mensaje de error (solo si existe) -->
    <?php if ($error !== ''): ?>
        <div class="alert" role="alert">
            <svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
            </svg>
            <!--
                htmlspecialchars() convierte caracteres especiales a entidades HTML.
                Previene XSS si el mensaje de error llegara a contener caracteres peligrosos.
            -->
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- Formulario de login -->
    <!--
        action="" → envía al mismo archivo (login.php).
        method="post" → los datos NO van en la URL (evita que queden en el historial del navegador).
        autocomplete="off" en la contraseña previene que el navegador la sugiera en equipos compartidos.
    -->
    <form method="post" action="" novalidate>

        <div class="form__group">
            <label class="form__label" for="correo">Correo institucional</label>
            <input
                class="form__input"
                type="email"
                id="correo"
                name="correo"
                placeholder="usuario@oea.edu.co"
                required
                maxlength="150"
                autocomplete="username"
                value="<?= htmlspecialchars($_POST['correo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            >
                        <!-- htmlspecialchars repopula el campo conservando el valor si hay error, sin riesgo de XSS aunque el usuario haya escrito código HTML. -->
        </div>

        <div class="form__group">
            <label class="form__label" for="contrasena">Contraseña</label>
            <input
                class="form__input"
                type="password"
                id="contrasena"
                name="contrasena"
                placeholder="••••••••"
                required
                maxlength="128"
                autocomplete="current-password"
            >
            <!-- La contraseña nunca se repopula tras un error por seguridad -->
        </div>

        <button class="btn-primary" type="submit">Ingresar al sistema</button>

    </form>

    <footer class="card__footer">
        <p>SIPAE &copy; <?= date('Y') ?> — Colegio OEA</p>
    </footer>

</main>

</body>
</html>
