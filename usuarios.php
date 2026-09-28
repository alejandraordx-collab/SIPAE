<?php
/**
 * SIPAE - Gestión de Usuarios (PHP)
 * Stack: PHP + MySQL
 */
require_once __DIR__ . '/conexion.php';
requerirRol('coordinador');

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_usuario'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');
    $rol = $_POST['rol'] ?? 'docente';
    $curso_dirigido = !empty($_POST['curso_dirigido']) ? trim($_POST['curso_dirigido']) : null;

    if (empty($nombre) || empty($correo) || empty($contrasena)) {
        $error = 'Todos los campos obligatorios deben ser diligenciados.';
    } else {
        try {
            $hash = password_hash($contrasena, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, correo, contrasena, rol, curso_dirigido, activo, debe_cambiar_contrasena) VALUES (?, ?, ?, ?, ?, 1, 1)");
            $stmt->execute([$nombre, $correo, $hash, $rol, $curso_dirigido]);
            $exito = "Usuario $nombre registrado correctamente.";
        } catch (PDOException $e) {
            $error = 'Error al registrar el usuario: ' . $e->getMessage();
        }
    }
}

$usuarios = $pdo->query("SELECT id, nombre, correo, rol, curso_dirigido, activo, creado_en FROM usuarios ORDER BY nombre ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPAE — Gestión de Usuarios</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        :root { --azul-oscuro: #0f172a; --azul-medio: #ee7374; --gris-bg: #f8fafc; --gris-borde: #e2e8f0; --gris-texto: #64748b; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--gris-bg); color: var(--azul-oscuro); }
        .navbar { background: #fff; border-bottom: 1.5px solid var(--gris-borde); padding: 0 1.5rem; height: 64px; display: flex; align-items: center; justify-content: space-between; }
        .nav-links { display: flex; gap: 1rem; align-items: center; }
        .nav-link { text-decoration: none; color: var(--gris-texto); font-weight: 600; font-size: .85rem; padding: .4rem .75rem; border-radius: 6px; }
        .nav-link.active { color: var(--azul-medio); background: #fbe3e1; }
        .contenedor { max-width: 1200px; margin: 1.75rem auto; padding: 0 1rem; }
        .card { background: #fff; border-radius: 12px; border: 1.5px solid var(--gris-borde); padding: 1.5rem; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th { background: #f8fafc; color: var(--gris-texto); font-size: .75rem; font-weight: 700; text-transform: uppercase; padding: .75rem 1rem; text-align: left; border-bottom: 1.5px solid var(--gris-borde); }
        td { padding: .75rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: .85rem; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="dashboard_coordinador.php" style="font-weight:800;text-decoration:none;color:inherit;font-size:1.15rem">SIPAE</a>
    <div class="nav-links">
        <a href="dashboard_coordinador.php" class="nav-link">Panel General</a>
        <a href="estudiantes.php" class="nav-link">Estudiantes</a>
        <a href="usuarios.php" class="nav-link active">Usuarios</a>
        <a href="logout.php" style="color:#dc2626;font-size:.8rem;font-weight:600;text-decoration:none">Cerrar sesión</a>
    </div>
</nav>

<div class="contenedor">
    <div class="card">
        <h2 style="font-size:1.1rem;margin-bottom:1rem">Usuarios del Sistema</h2>
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Curso Asignado</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($u['nombre']) ?></strong></td>
                        <td><?= htmlspecialchars($u['correo']) ?></td>
                        <td><span style="text-transform:capitalize;font-weight:600"><?= htmlspecialchars($u['rol']) ?></span></td>
                        <td><?= $u['curso_dirigido'] ? 'Curso ' . htmlspecialchars($u['curso_dirigido']) : '—' ?></td>
                        <td><span style="color:#059669;font-weight:700"><?= $u['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
