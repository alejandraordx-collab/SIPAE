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
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

<nav class="navbar">
    <a href="dashboard_coordinador.php" class="navbar__marca">
        <span>SIPAE</span>
        <span class="navbar__badge">Coordinación</span>
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
                <span class="badge-pill-green"><?= $kpiAlmuerzosHoy ?> <?= $kpiAlmuerzosHoy === 1 ? 'almuerzo' : 'almuerzos' ?></span>
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
                                <span class="badge-pill-green">
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
                                    <span class="badge-pill-green">Registrado</span>
                                <?php else: ?>
                                    <span class="badge-pill-orange">Pendiente</span>
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
