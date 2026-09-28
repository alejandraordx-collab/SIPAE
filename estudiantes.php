<?php
/**
 * SIPAE - Gestión de Estudiantes (PHP)
 * Stack: PHP + MySQL
 */
require_once __DIR__ . '/conexion.php';
requerirRol('coordinador');

$error = '';
$exito = '';

// Procesar nuevo estudiante
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $nombre = trim($_POST['nombre'] ?? '');
    $documento = trim($_POST['documento'] ?? '');
    $curso = trim($_POST['curso'] ?? '');
    $nombre_acudiente = trim($_POST['nombre_acudiente'] ?? '');
    $whatsapp_acudiente = trim($_POST['whatsapp_acudiente'] ?? '');
    $correo_acudiente = trim($_POST['correo_acudiente'] ?? '');

    if (empty($nombre) || empty($documento) || empty($curso)) {
        $error = 'Nombre, documento y curso son obligatorios.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO estudiantes (nombre, documento, curso, nombre_acudiente, whatsapp_acudiente, correo_acudiente, activo) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$nombre, $documento, $curso, $nombre_acudiente, $whatsapp_acudiente, $correo_acudiente]);
            $exito = 'Estudiante registrado exitosamente.';
        } catch (PDOException $e) {
            $error = 'Error al registrar el estudiante: ' . $e->getMessage();
        }
    }
}

// Filtro por curso y búsqueda
$cursoFiltro = $_GET['curso'] ?? '';
$busqueda = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM estudiantes WHERE 1=1";
$params = [];

if (!empty($cursoFiltro)) {
    $sql .= " AND curso = ?";
    $params[] = $cursoFiltro;
}
if (!empty($busqueda)) {
    $sql .= " AND (nombre LIKE ? OR documento LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}
$sql .= " ORDER BY curso ASC, nombre ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$estudiantes = $stmt->fetchAll();

// Cursos únicos para el filtro
$cursosList = $pdo->query("SELECT DISTINCT curso FROM estudiantes ORDER BY curso ASC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPAE — Gestión de Estudiantes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        :root {
            --azul-oscuro: #0f172a; --azul-medio: #ee7374; --azul-fondo: #fbe3e1;
            --verde: #059669; --rojo: #dc2626; --gris-bg: #f8fafc;
            --gris-card: #ffffff; --gris-borde: #e2e8f0; --gris-texto: #64748b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--gris-bg); color: var(--azul-oscuro); }
        .navbar { background: #fff; border-bottom: 1.5px solid var(--gris-borde); padding: 0 1.5rem; height: 64px; display: flex; align-items: center; justify-content: space-between; }
        .nav-links { display: flex; gap: 1rem; align-items: center; }
        .nav-link { text-decoration: none; color: var(--gris-texto); font-weight: 600; font-size: .85rem; padding: .4rem .75rem; border-radius: 6px; }
        .nav-link.active { color: var(--azul-medio); background: var(--azul-fondo); }
        .contenedor { max-width: 1200px; margin: 1.75rem auto; padding: 0 1rem; }
        .card { background: #fff; border-radius: 12px; border: 1.5px solid var(--gris-borde); padding: 1.5rem; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th { background: #f8fafc; color: var(--gris-texto); font-size: .75rem; font-weight: 700; text-transform: uppercase; padding: .75rem 1rem; text-align: left; border-bottom: 1.5px solid var(--gris-borde); }
        td { padding: .75rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: .85rem; }
        .btn { padding: .5rem 1rem; border-radius: 6px; font-weight: 700; font-size: .85rem; border: none; cursor: pointer; text-decoration: none; }
        .btn-primario { background: var(--azul-medio); color: #fff; }
        .input { padding: .5rem .75rem; border: 1.5px solid var(--gris-borde); border-radius: 6px; font-size: .85rem; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="dashboard_coordinador.php" style="font-weight:800;text-decoration:none;color:inherit;font-size:1.15rem">SIPAE</a>
    <div class="nav-links">
        <a href="dashboard_coordinador.php" class="nav-link">Panel General</a>
        <a href="estudiantes.php" class="nav-link active">Estudiantes</a>
        <a href="usuarios.php" class="nav-link">Usuarios</a>
        <a href="logout.php" style="color:var(--rojo);font-size:.8rem;font-weight:600;text-decoration:none">Cerrar sesión</a>
    </div>
</nav>

<div class="contenedor">
    <div class="card">
        <h2 style="font-size:1.1rem;margin-bottom:1rem">Directorio de Estudiantes</h2>
        <form method="get" action="estudiantes.php" style="display:flex;gap:1rem;flex-wrap:wrap">
            <select name="curso" class="input" onchange="this.form.submit()">
                <option value="">Todos los cursos</option>
                <?php foreach ($cursosList as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= $c === $cursoFiltro ? 'selected' : '' ?>>Curso <?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="q" placeholder="Buscar por nombre o documento..." value="<?= htmlspecialchars($busqueda) ?>" class="input" style="flex:1;min-width:200px">
            <button type="submit" class="btn btn-primario">Filtrar</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Documento</th>
                    <th>Nombre</th>
                    <th>Curso</th>
                    <th>Acudiente</th>
                    <th>WhatsApp Acudiente</th>
                    <th>Correo Acudiente</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($estudiantes as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars($e['documento']) ?></td>
                        <td><strong><?= htmlspecialchars($e['nombre']) ?></strong></td>
                        <td>Curso <?= htmlspecialchars($e['curso']) ?></td>
                        <td><?= htmlspecialchars($e['nombre_acudiente'] ?? 'No registrado') ?></td>
                        <td><?= htmlspecialchars($e['whatsapp_acudiente'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($e['correo_acudiente'] ?? '—') ?></td>
                        <td><span style="color:var(--verde);font-weight:700">Activo</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
