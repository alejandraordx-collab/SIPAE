<?php
/**
 * SIPAE - Dashboard Coordinador (PHP)
 * Vista ejecutiva con KPIs, Control PAE (Almuerzos), Alertas y Asistencia Docente
 * Stack: PHP + MySQL
 */
require_once __DIR__ . '/conexion.php';
requerirRol('coordinador');

$hoy = date('Y-m-d');
$hace7Dias = date('Y-m-d', strtotime('-7 days'));

// 1. KPI Registrados Hoy
$stmtReg = $pdo->prepare("SELECT COUNT(DISTINCT estudiante_id) FROM asistencia WHERE fecha = ?");
$stmtReg->execute([$hoy]);
$kpiRegistradosHoy = (int)$stmtReg->fetchColumn();

// 2. KPI Almuerzos PAE Hoy (estudiantes que asistieron o tienen almuerzo = 1)
$stmtAlm = $pdo->prepare("SELECT COUNT(DISTINCT estudiante_id) FROM asistencia WHERE fecha = ? AND (almuerzo = 1 OR estado = 'asistió')");
$stmtAlm->execute([$hoy]);
$kpiAlmuerzosHoy = (int)$stmtAlm->fetchColumn();

// 3. KPI Inasistencias Hoy
$stmtFal = $pdo->prepare("SELECT COUNT(DISTINCT estudiante_id) FROM asistencia WHERE fecha = ? AND estado = 'falla'");
$stmtFal->execute([$hoy]);
$kpiFallasHoy = (int)$stmtFal->fetchColumn();

// 4. KPI Alertas Pendientes
$stmtAlert = $pdo->query("SELECT COUNT(*) FROM alertas WHERE estado = 'pendiente'");
$kpiAlertasPendientes = (int)$stmtAlert->fetchColumn();

// Bloque PAE: Desglose por curso
$stmtPae = $pdo->prepare("
    SELECT e.curso, COUNT(DISTINCT a.estudiante_id) AS total_almuerzos
    FROM estudiantes e
    LEFT JOIN asistencia a ON a.estudiante_id = e.id AND a.fecha = ? AND (a.almuerzo = 1 OR a.estado = 'asistió')
    WHERE e.activo = 1
    GROUP BY e.curso
    ORDER BY e.curso ASC
");
$stmtPae->execute([$hoy]);
$paePorCurso = $stmtPae->fetchAll();

// Bloque Alertas de inasistencia reiterada (últimos 7 días)
$stmtFallasRecientes = $pdo->prepare("
    SELECT e.id, e.nombre, e.curso, e.nombre_acudiente, e.whatsapp_acudiente, e.correo_acudiente,
           COUNT(a.id) AS total_fallas
    FROM estudiantes e
    JOIN asistencia a ON a.estudiante_id = e.id
    WHERE a.estado = 'falla' AND a.fecha BETWEEN ? AND ?
    GROUP BY e.id, e.nombre, e.curso, e.nombre_acudiente, e.whatsapp_acudiente, e.correo_acudiente
    ORDER BY total_fallas DESC
");
$stmtFallasRecientes->execute([$hace7Dias, $hoy]);
$alertasEstudiantes = $stmtFallasRecientes->fetchAll();

// Control de registro docente hoy
$stmtDocentes = $pdo->query("
    SELECT u.id, u.nombre, u.curso_dirigido,
           (SELECT COUNT(*) FROM asistencia WHERE docente_id = u.id AND fecha = CURDATE()) AS registros_hoy
    FROM usuarios u
    WHERE u.rol = 'docente' AND u.activo = 1
    ORDER BY u.nombre ASC
");
$docentesControl = $stmtDocentes->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPAE — Panel de Coordinación</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --azul-oscuro: #0f172a;
            --azul-medio: #2563eb;
            --azul-fondo: #eff6ff;
            --verde: #059669;
            --verde-fondo: #ecfdf5;
            --rojo: #dc2626;
            --rojo-fondo: #fef2f2;
            --naranja: #ea580c;
            --naranja-fondo: #fff7ed;
            --gris-bg: #f8fafc;
            --gris-card: #ffffff;
            --gris-borde: #e2e8f0;
            --gris-texto: #64748b;
            --gris-oscuro: #1e293b;
            --sombra: 0 4px 6px -1px rgb(0 0 0 / .07), 0 2px 4px -2px rgb(0 0 0 / .05);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--gris-bg); color: var(--gris-oscuro); min-height: 100vh; }
        
        .navbar { background: #fff; border-bottom: 1.5px solid var(--gris-borde); padding: 0 1.5rem; height: 64px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 50; }
        .navbar__marca { font-weight: 800; font-size: 1.15rem; color: var(--azul-oscuro); text-decoration: none; display: flex; align-items: center; gap: .5rem; }
        .nav-links { display: flex; gap: 1rem; align-items: center; }
        .nav-link { text-decoration: none; color: var(--gris-texto); font-weight: 600; font-size: .85rem; padding: .4rem .75rem; border-radius: 6px; }
        .nav-link.active { color: var(--azul-medio); background: var(--azul-fondo); }
        .btn-logout { background: none; border: 1.5px solid var(--gris-borde); color: var(--rojo); padding: .375rem .75rem; border-radius: 6px; font-weight: 600; font-size: .8rem; cursor: pointer; text-decoration: none; }
        
        .contenedor { max-width: 1200px; margin: 1.75rem auto; padding: 0 1rem; }
        
        .grid-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .kpi { background: #fff; border-radius: 12px; border: 1.5px solid var(--gris-borde); box-shadow: var(--sombra); padding: 1.25rem; display: flex; flex-direction: column; }
        .kpi__valor { font-size: 2rem; font-weight: 800; color: var(--azul-oscuro); margin-bottom: .25rem; }
        .kpi__etiqueta { font-size: .85rem; font-weight: 700; color: var(--gris-texto); text-transform: uppercase; letter-spacing: .04em; }
        
        .grid-2col { display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; }
        .card { background: #fff; border-radius: 12px; border: 1.5px solid var(--gris-borde); box-shadow: var(--sombra); padding: 1.5rem; }
        .card__header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
        .card__titulo { font-size: 1rem; font-weight: 700; }
        .badge { padding: .25rem .75rem; border-radius: 20px; font-size: .75rem; font-weight: 700; }
        .badge--verde { background: var(--verde-fondo); color: var(--verde); }
        
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; color: var(--gris-texto); font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: .65rem .75rem; text-align: left; border-bottom: 1.5px solid var(--gris-borde); }
        td { padding: .65rem .75rem; border-bottom: 1px solid #f1f5f9; font-size: .85rem; }
        .chip-almuerzo { background: #d1fae5; color: #047857; padding: .2rem .5rem; border-radius: 6px; font-weight: 700; font-size: .78rem; display: inline-block; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="dashboard_coordinador.php" class="navbar__marca">
        <span>SIPAE</span>
        <span style="font-size:.8rem;color:var(--azul-medio);background:var(--azul-fondo);padding:2px 8px;border-radius:12px">Coordinación</span>
    </a>
    <div class="nav-links">
        <a href="dashboard_coordinador.php" class="nav-link active">Panel General</a>
        <a href="estudiantes.php" class="nav-link">Estudiantes</a>
        <a href="usuarios.php" class="nav-link">Usuarios</a>
        <a href="logout.php" class="btn-logout">Cerrar sesión</a>
    </div>
</nav>

<div class="contenedor">
    <div class="grid-kpis">
        <div class="kpi">
            <div class="kpi__valor"><?= $kpiRegistradosHoy ?></div>
            <div class="kpi__etiqueta">Registrados Hoy</div>
        </div>
        <div class="kpi">
            <div class="kpi__valor"><?= $kpiAlmuerzosHoy ?></div>
            <div class="kpi__etiqueta">Almuerzos PAE</div>
        </div>
        <div class="kpi">
            <div class="kpi__valor"><?= $kpiFallasHoy ?></div>
            <div class="kpi__etiqueta">Inasistencias Hoy</div>
        </div>
        <div class="kpi">
            <div class="kpi__valor"><?= $kpiAlertasPendientes ?></div>
            <div class="kpi__etiqueta">Alertas Pendientes</div>
        </div>
    </div>

    <div class="grid-2col">
        <!-- Tarjeta de Alimentación Escolar (PAE) -->
        <div class="card">
            <div class="card__header">
                <div class="card__titulo">Alimentación Escolar (PAE) — Hoy</div>
                <span class="badge badge--verde"><?= $kpiAlmuerzosHoy ?> <?= $kpiAlmuerzosHoy === 1 ? 'almuerzo' : 'almuerzos' ?></span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Curso</th>
                        <th style="text-align:right">Almuerzos requeridos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paePorCurso as $p): 
                        $total = (int)$p['total_almuerzos'];
                    ?>
                        <tr>
                            <td><strong>Curso <?= htmlspecialchars($p['curso']) ?></strong></td>
                            <td style="text-align:right">
                                <span class="chip-almuerzo">
                                    <?= $total ?> <?= $total === 1 ? 'almuerzo' : 'almuerzos' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Control de Docentes -->
        <div class="card">
            <div class="card__header">
                <div class="card__titulo">Control de Asistencia — Docentes</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Docente</th>
                        <th>Curso</th>
                        <th>Estado hoy</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($docentesControl as $d): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($d['nombre']) ?></strong></td>
                            <td>Curso <?= htmlspecialchars($d['curso_dirigido'] ?? 'Sin curso') ?></td>
                            <td>
                                <?php if ($d['registros_hoy'] > 0): ?>
                                    <span class="badge badge--verde">Registrado</span>
                                <?php else: ?>
                                    <span style="color:var(--naranja);font-weight:700">Pendiente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
