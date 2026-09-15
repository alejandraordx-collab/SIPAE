<?php
/**
 * usuarios.php
 * Pantalla del coordinador para crear y administrar las cuentas de acceso
 * al sistema (tabla `usuarios`): docentes y coordinadores.
 *
 * El formulario envía los datos a procesar_usuario.php, que valida,
 * genera el hash de la contraseña con password_hash() y guarda el
 * registro. Esta misma página también permite activar/desactivar una
 * cuenta sin necesidad de borrarla (borrado lógico, igual que en
 * estudiantes), editar una cuenta ya existente (nombre, correo, rol y,
 * opcionalmente, una nueva contraseña — por ejemplo cuando la persona la
 * olvidó, ver editar_usuario.php), y lista las cuentas ya existentes.
 */

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

// ── Protección: solo coordinadores autenticados ────────────────────────────
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'coordinador') {
    header('Location: login.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/conexion.php';

$pdo = obtenerConexion();

// ── Activar / desactivar una cuenta (borrado lógico) ───────────────────────
// Se maneja aquí mismo con POST, para no necesitar un archivo aparte.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    $toggleId = (int) $_POST['toggle_id'];

    // Evita que un coordinador desactive su propia cuenta por accidente
    // mientras tiene la sesión abierta.
    if ($toggleId === (int) $_SESSION['usuario_id']) {
        header('Location: usuarios.php?error=auto_desactivar');
        exit;
    }

    $stmt = $pdo->prepare('UPDATE usuarios SET activo = 1 - activo WHERE id = :id');
    $stmt->execute([':id' => $toggleId]);

    header('Location: usuarios.php?estado_actualizado=1');
    exit;
}

// ── Mensaje de resultado (llega desde procesar_usuario.php, el toggle, o
//    procesar_editar_usuario.php) ──────────────────────────────────────────
$alerta = null;

if (isset($_GET['guardado'])) {
    $alerta = ['tipo' => 'exito', 'texto' => 'Usuario creado correctamente.'];

} elseif (isset($_GET['editado'])) {
    $alerta = ['tipo' => 'exito', 'texto' => 'Usuario actualizado correctamente.'];

} elseif (isset($_GET['estado_actualizado'])) {
    $alerta = ['tipo' => 'exito', 'texto' => 'Estado del usuario actualizado.'];

} elseif (isset($_GET['error'])) {
    $textos = [
        'validacion'      => 'Revisa los datos: todos los campos son obligatorios, el correo debe ser válido y la contraseña debe tener al menos 8 caracteres.',
        'duplicado'       => 'Ya existe un usuario con ese correo institucional.',
        'bd'              => 'Error al guardar en la base de datos. Intenta de nuevo.',
        'auto_desactivar' => 'No puedes desactivar tu propia cuenta mientras tienes la sesión iniciada.',
        'no_encontrado'   => 'El usuario que intentas editar no existe.',
    ];
    $alerta = [
        'tipo'  => 'error',
        'texto' => $textos[$_GET['error']] ?? 'Ocurrió un error inesperado.',
    ];
}

// ── Listado de usuarios: coordinadores primero, luego docentes ────────────
$usuarios = $pdo->query(
    'SELECT id, nombre, correo, rol, activo, creado_en
       FROM usuarios
      ORDER BY rol ASC, nombre ASC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPAE — Usuarios</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --verde: #059669;
            --verde-oscuro: #047857;
            --verde-claro: #d1fae5;
            --azul: #1a56db;
            --azul-claro: #eff6ff;
            --rojo: #dc2626;
            --rojo-claro: #fef2f2;
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

        .navbar__derecha { display: flex; align-items: center; gap: 1.25rem; }

        .navbar__link {
            color: #fff;
            text-decoration: none;
            font-size: .8rem;
            font-weight: 600;
            opacity: .9;
        }
        .navbar__link:hover { opacity: 1; text-decoration: underline; }

        .navbar__logout {
            color: #fff;
            text-decoration: none;
            background: rgba(255,255,255,.18);
            padding: .35rem .875rem;
            border-radius: 6px;
            font-size: .8rem;
            font-weight: 600;
        }
        .navbar__logout:hover { background: rgba(255,255,255,.30); }

        .contenedor {
            max-width: 1100px;
            margin: 2rem auto;
            padding: 0 1.25rem;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,.07);
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.5rem;
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
        }
        .alerta--exito { background: #f0fdf4; border: 1px solid #86efac; color: #15803d; }
        .alerta--error { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; }

        /* ── Formulario ────────────────────────────────────────────────────── */
        .grid-form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem 1.25rem;
        }

        .campo { display: flex; flex-direction: column; gap: .375rem; }

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

        .btn {
            padding: .7rem 1.75rem;
            border: none;
            border-radius: 8px;
            font-size: .925rem;
            font-weight: 700;
            cursor: pointer;
            color: #fff;
            margin-top: 1.25rem;
        }
        .btn--verde { background: var(--verde); }
        .btn--verde:hover { background: var(--verde-oscuro); }

        /* ── Tabla de usuarios ───────────────────────────────────────────────── */
        .tabla-wrapper { overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; font-size: .875rem; }

        thead th {
            background: #f8fafc;
            padding: .625rem .875rem;
            text-align: left;
            font-size: .72rem;
            font-weight: 700;
            color: var(--gris-texto);
            text-transform: uppercase;
            letter-spacing: .04em;
            border-bottom: 2px solid var(--gris-borde);
        }

        tbody td {
            padding: .625rem .875rem;
            border-bottom: 1px solid var(--gris-borde);
            vertical-align: middle;
        }

        tbody tr:hover { background: #fafafa; }

        .badge {
            display: inline-block;
            border-radius: 20px;
            padding: .25rem .75rem;
            font-size: .75rem;
            font-weight: 700;
        }
        .badge--azul { background: var(--azul-claro); color: var(--azul); }
        .badge--verde { background: var(--verde-claro); color: var(--verde-oscuro); }
        .badge--rojo { background: var(--rojo-claro); color: var(--rojo); }
        .badge--gris { background: #f1f5f9; color: var(--gris-texto); }

        .acciones-fila { display: flex; gap: .5rem; align-items: center; justify-content: center; }

        .btn-editar {
            padding: .35rem .85rem;
            border: 1.5px solid var(--azul);
            border-radius: 6px;
            font-size: .78rem;
            font-weight: 700;
            cursor: pointer;
            color: var(--azul);
            background: #fff;
            text-decoration: none;
        }
        .btn-editar:hover { background: var(--azul-claro); }

        .btn-toggle {
            padding: .35rem .85rem;
            border: none;
            border-radius: 6px;
            font-size: .78rem;
            font-weight: 700;
            cursor: pointer;
            color: #fff;
        }
        .btn-toggle--desactivar { background: var(--rojo); }
        .btn-toggle--desactivar:hover { background: #b91c1c; }
        .btn-toggle--activar { background: var(--verde); }
        .btn-toggle--activar:hover { background: var(--verde-oscuro); }

        .vacio { text-align: center; padding: 2rem; color: var(--gris-texto); }

        @media (max-width: 640px) {
            .grid-form { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="dashboard_coordinador.php" class="navbar__marca">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.25C17.25 22.15 21 17.25 21 12V7L12 2zm0 2.18l7 3.89V12c0 4.35-3.1 8.4-7 9.43C8.1 20.4 5 16.35 5 12V8.07l7-3.89z"/>
        </svg>
        SIPAE — Usuarios
    </a>
    <div class="navbar__derecha">
        <a href="dashboard_coordinador.php" class="navbar__link">← Volver al panel</a>
        <a href="estudiantes.php" class="navbar__link">Estudiantes</a>
        <a href="?logout=1" class="navbar__logout">Cerrar sesión</a>
    </div>
</nav>

<main class="contenedor">

    <?php if ($alerta): ?>
        <div class="alerta alerta--<?= $alerta['tipo'] ?>">
            <?= htmlspecialchars($alerta['texto'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- ── Crear un nuevo usuario ───────────────────────────────────────────── -->
    <div class="card">
        <h2 class="card__titulo">Crear nuevo usuario</h2>

        <form method="post" action="procesar_usuario.php">
            <div class="grid-form">
                <div class="campo">
                    <label for="nombre">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre" maxlength="120" required>
                </div>

                <div class="campo">
                    <label for="correo">Correo institucional</label>
                    <input type="email" id="correo" name="correo" maxlength="150"
                           placeholder="usuario@oea.edu.co" required>
                </div>

                <div class="campo">
                    <label for="contrasena">Contraseña</label>
                    <input type="password" id="contrasena" name="contrasena"
                           minlength="8" maxlength="128" autocomplete="new-password" required>
                    <span class="campo__ayuda">Mínimo 8 caracteres.</span>
                </div>

                <div class="campo">
                    <label for="rol">Rol</label>
                    <select id="rol" name="rol" required>
                        <option value="">— Selecciona —</option>
                        <option value="docente">Docente</option>
                        <option value="coordinador">Coordinador(a)</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn--verde">Crear usuario</button>
        </form>
    </div>

    <!-- ── Listado de usuarios ──────────────────────────────────────────────── -->
    <div class="card">
        <h2 class="card__titulo">Usuarios registrados (<?= count($usuarios) ?>)</h2>

        <?php if (empty($usuarios)): ?>
            <div class="vacio">Aún no hay usuarios registrados.</div>
        <?php else: ?>
            <div class="tabla-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th style="text-align:center">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u):
                            $esUsuarioActual = (int) $u['id'] === (int) $_SESSION['usuario_id'];
                            $fechaCreado = date('d/m/Y', strtotime($u['creado_en']));
                        ?>
                        <tr>
                            <td style="font-weight:600">
                                <?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($esUsuarioActual): ?>
                                    <span style="color:var(--gris-texto);font-weight:400;font-size:.78rem"> (tú)</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="badge <?= $u['rol'] === 'coordinador' ? 'badge--azul' : 'badge--gris' ?>">
                                    <?= $u['rol'] === 'coordinador' ? 'Coordinador(a)' : 'Docente' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $u['activo'] ? 'badge--verde' : 'badge--rojo' ?>">
                                    <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td style="color:var(--gris-texto)"><?= $fechaCreado ?></td>
                            <td style="text-align:center">
                                <div class="acciones-fila">
                                    <a href="editar_usuario.php?id=<?= (int) $u['id'] ?>" class="btn-editar">Editar</a>

                                    <?php if ($esUsuarioActual): ?>
                                        <span style="color:var(--gris-texto);font-size:.78rem">—</span>
                                    <?php else: ?>
                                        <form method="post" action="usuarios.php" style="display:inline"
                                              onsubmit="return confirm('¿Seguro que deseas <?= $u['activo'] ? 'desactivar' : 'activar' ?> a <?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>?');">
                                            <input type="hidden" name="toggle_id" value="<?= (int) $u['id'] ?>">
                                            <button type="submit"
                                                    class="btn-toggle <?= $u['activo'] ? 'btn-toggle--desactivar' : 'btn-toggle--activar' ?>">
                                                <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</main>

</body>
</html>
