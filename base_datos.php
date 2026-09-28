<?php
require_once __DIR__ . '/conexion.php';
requerirRol('coordinador');

// Columnas que nunca deben mostrarse, sin importar la tabla
$columnasOcultas = ['contrasena', 'reset_token', 'reset_token_expira'];

// Listado real de tablas de la base de datos (nunca se escribe a mano)
$tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

// La tabla elegida se valida contra el listado real antes de usarse en SQL
$tablaSel = $_GET['tabla'] ?? '';
if ($tablaSel !== '' && !in_array($tablaSel, $tablas, true)) {
    $tablaSel = '';
}

$columnas = [];
$filas = [];
$totalFilas = 0;

if ($tablaSel !== '') {
    $descripcion = $pdo->query("DESCRIBE `{$tablaSel}`")->fetchAll(PDO::FETCH_COLUMN);
    $columnas = array_values(array_diff($descripcion, $columnasOcultas));

    $totalFilas = (int) $pdo->query("SELECT COUNT(*) FROM `{$tablaSel}`")->fetchColumn();

    $stmt = $pdo->query("SELECT * FROM `{$tablaSel}` LIMIT 200");
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPAE — Base de Datos</title>
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
        th { background: #f8fafc; color: var(--gris-texto); font-size: .75rem; font-weight: 700; text-transform: uppercase; padding: .75rem 1rem; text-align: left; border-bottom: 1.5px solid var(--gris-borde); white-space: nowrap; }
        td { padding: .75rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: .85rem; white-space: nowrap; }
        .btn { padding: .5rem 1rem; border-radius: 6px; font-weight: 700; font-size: .85rem; border: none; cursor: pointer; text-decoration: none; }
        .btn-primario { background: var(--azul-medio); color: #fff; }
        .input { padding: .5rem .75rem; border: 1.5px solid var(--gris-borde); border-radius: 6px; font-size: .85rem; }
        .tabla-wrapper { overflow-x: auto; }
        .aviso { background: var(--azul-fondo); color: var(--azul-medio); border-radius: 8px; padding: .75rem 1rem; font-size: .8rem; font-weight: 600; margin-bottom: 1rem; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="dashboard_coordinador.php" style="font-weight:800;text-decoration:none;color:inherit;font-size:1.15rem">SIPAE</a>
    <div class="nav-links">
        <a href="dashboard_coordinador.php" class="nav-link">Panel General</a>
        <a href="estudiantes.php" class="nav-link">Estudiantes</a>
        <a href="usuarios.php" class="nav-link">Usuarios</a>
        <a href="base_datos.php" class="nav-link active">Base de Datos</a>
        <a href="logout.php" style="color:var(--rojo);font-size:.8rem;font-weight:600;text-decoration:none">Cerrar sesión</a>
    </div>
</nav>

<div class="contenedor">
    <div class="card">
        <h2 style="font-size:1.1rem;margin-bottom:1rem">Base de Datos</h2>
        <p class="aviso">Vista de solo lectura. Las contraseñas y tokens de recuperación nunca se muestran aquí.</p>

        <form method="get" action="base_datos.php" style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
            <div>
                <label for="tabla" style="display:block;font-size:.75rem;font-weight:700;color:var(--gris-texto);text-transform:uppercase;margin-bottom:.35rem">Tabla</label>
                <select id="tabla" name="tabla" class="input" onchange="this.form.submit()">
                    <option value="">Selecciona una tabla</option>
                    <?php foreach ($tablas as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= $t === $tablaSel ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <noscript><button type="submit" class="btn btn-primario" style="align-self:flex-end">Ver tabla</button></noscript>
        </form>
    </div>

    <?php if ($tablaSel !== ''): ?>
    <div class="card">
        <h2 style="font-size:1.05rem;font-weight:700;margin-bottom:.25rem">Tabla: <?= htmlspecialchars($tablaSel) ?></h2>
        <p style="color:var(--gris-texto);font-size:.8rem;margin-bottom:1rem">
            Mostrando <?= count($filas) ?> de <?= $totalFilas ?> fila(s) <?= $totalFilas > 200 ? '(limitado a 200)' : '' ?>
        </p>
        <div class="tabla-wrapper">
            <table>
                <thead>
                    <tr>
                        <?php foreach ($columnas as $c): ?>
                            <th><?= htmlspecialchars($c) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filas)): ?>
                        <tr><td colspan="<?= max(count($columnas), 1) ?>" style="text-align:center;color:var(--gris-texto)">Esta tabla no tiene registros.</td></tr>
                    <?php else: ?>
                        <?php foreach ($filas as $fila): ?>
                            <tr>
                                <?php foreach ($columnas as $c): ?>
                                    <td><?= htmlspecialchars((string) ($fila[$c] ?? '')) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
