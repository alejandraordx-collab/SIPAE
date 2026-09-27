<?php
/**
 * SIPAE - Dashboard Docente
 * Registro de Asistencia y Almuerzo PAE por Curso y Hora
 * Stack: PHP + MySQL
 */
require_once __DIR__ . '/conexion.php';
requerirRol('docente');

$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre'];

// Obtener curso dirigido del docente
$stmtDoc = $pdo->prepare("SELECT curso_dirigido FROM usuarios WHERE id = ?");
$stmtDoc->execute([$usuario_id]);
$docenteData = $stmtDoc->fetch();
$cursoDirigido = $docenteData['curso_dirigido'] ?? null;

// Obtener lista de cursos disponibles
$cursosStmt = $pdo->query("SELECT DISTINCT curso FROM estudiantes WHERE activo = 1 ORDER BY curso ASC");
$cursos = $cursosStmt->fetchAll(PDO::FETCH_COLUMN);

// Horarios Militares hora a hora (06:00 a 18:00) sin paréntesis
$horariosMilitares = [
    ['b' => 1,  'hora' => '06:00 - 07:00'],
    ['b' => 2,  'hora' => '07:00 - 08:00'],
    ['b' => 3,  'hora' => '08:00 - 09:00'],
    ['b' => 4,  'hora' => '09:00 - 10:00'],
    ['b' => 5,  'hora' => '10:00 - 11:00'],
    ['b' => 6,  'hora' => '11:00 - 12:00'],
    ['b' => 7,  'hora' => '12:00 - 13:00'],
    ['b' => 8,  'hora' => '13:00 - 14:00'],
    ['b' => 9,  'hora' => '14:00 - 15:00'],
    ['b' => 10, 'hora' => '15:00 - 16:00'],
    ['b' => 11, 'hora' => '16:00 - 17:00'],
    ['b' => 12, 'hora' => '17:00 - 18:00'],
];

// Parámetros de selección
$cursoSel = $_GET['curso'] ?? ($cursoDirigido ?? ($cursos[0] ?? ''));
$fechaSel = $_GET['fecha'] ?? date('Y-m-d');
$bloqueSel = isset($_GET['bloque']) ? (int)$_GET['bloque'] : 1;

// Buscar etiqueta de horario
$etiquetaHorario = "Hora $bloqueSel";
foreach ($horariosMilitares as $h) {
    if ($h['b'] === $bloqueSel) {
        $etiquetaHorario = $h['hora'];
        break;
    }
}

// Cargar estudiantes del curso seleccionado
$estudiantes = [];
$asistenciaExistente = [];
$almuerzoExistente = [];

if ($cursoSel !== '') {
    $stmtEst = $pdo->prepare("SELECT id, nombre, curso FROM estudiantes WHERE curso = ? AND activo = 1 ORDER BY nombre ASC");
    $stmtEst->execute([$cursoSel]);
    $estudiantes = $stmtEst->fetchAll();

    if (!empty($estudiantes)) {
        $stmtAsist = $pdo->prepare("SELECT estudiante_id, estado, almuerzo FROM asistencia WHERE fecha = ? AND bloque_clase = ?");
        $stmtAsist->execute([$fechaSel, $bloqueSel]);
        while ($row = $stmtAsist->fetch()) {
            $asistenciaExistente[$row['estudiante_id']] = $row['estado'];
            $almuerzoExistente[$row['estudiante_id']] = (bool)$row['almuerzo'];
        }
    }
}

// Alertas de feedback
$alerta = null;
if (isset($_GET['guardado'])) {
    $alerta = ['tipo' => 'exito', 'texto' => 'Asistencia y registro de almuerzo guardados correctamente.'];
} elseif (isset($_GET['error'])) {
    $alerta = ['tipo' => 'error', 'texto' => 'Error al guardar los datos o información incompleta.'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPAE — Registro de Asistencia y Almuerzo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --azul-oscuro: #0f172a;
            --azul-marino: #1e3a8a;
            --azul-medio: #2563eb;
            --azul-claro: #3b82f6;
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
        .navbar__marca { display: flex; align-items: center; gap: .75rem; text-decoration: none; color: var(--azul-oscuro); font-weight: 800; font-size: 1.15rem; }
        .navbar__user { display: flex; align-items: center; gap: 1rem; }
        .btn-logout { background: none; border: 1.5px solid var(--gris-borde); color: var(--rojo); padding: .375rem .75rem; border-radius: 6px; font-weight: 600; font-size: .8rem; cursor: pointer; text-decoration: none; }
        
        main { max-width: 1100px; margin: 1.75rem auto; padding: 0 1rem; }
        .card { background: var(--gris-card); border-radius: 12px; border: 1.5px solid var(--gris-borde); box-shadow: var(--sombra); padding: 1.5rem; margin-bottom: 1.5rem; }
        
        .filtros-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) auto; gap: 1rem; align-items: flex-end; }
        .filtro__grupo { display: flex; flex-direction: column; gap: .375rem; }
        .filtro__label { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--gris-texto); }
        .filtro__select, .filtro__input { padding: .6rem .85rem; border: 1.5px solid var(--gris-borde); border-radius: 8px; font-size: .9rem; background: #fff; color: var(--gris-oscuro); }
        .btn-cargar { background: var(--azul-medio); color: #fff; border: none; border-radius: 8px; padding: .65rem 1.25rem; font-weight: 700; cursor: pointer; }

        .tabla-wrapper { overflow-x: auto; margin-top: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; color: var(--gris-texto); font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: .75rem 1rem; text-align: left; border-bottom: 1.5px solid var(--gris-borde); }
        th.th-almuerzo { text-align: center; width: 140px; }
        td { padding: .75rem 1rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        td.num { width: 40px; color: var(--gris-texto); font-size: .8rem; }
        td.nombre { font-weight: 600; font-size: .9rem; }
        td.col-almuerzo { text-align: center; }

        .radio-grupo { display: flex; gap: .375rem; }
        .radio-grupo input[type="radio"] { display: none; }
        .radio-grupo label { display: inline-block; padding: .35rem .75rem; border-radius: 6px; font-size: .78rem; font-weight: 600; cursor: pointer; border: 1.5px solid transparent; transition: all .15s; user-select: none; }
        
        .radio-grupo input[type="radio"] + label.est-asistio { border-color: var(--verde); color: var(--verde); }
        .radio-grupo input[type="radio"]:checked + label.est-asistio { background: var(--verde); color: #fff; }
        
        .radio-grupo input[type="radio"] + label.est-falla { border-color: var(--rojo); color: var(--rojo); }
        .radio-grupo input[type="radio"]:checked + label.est-falla { background: var(--rojo); color: #fff; }

        .radio-grupo input[type="radio"] + label.est-justificado { border-color: var(--azul-claro); color: var(--azul-claro); }
        .radio-grupo input[type="radio"]:checked + label.est-justificado { background: var(--azul-claro); color: #fff; }

        .radio-grupo input[type="radio"] + label.est-novedad { border-color: var(--naranja); color: var(--naranja); }
        .radio-grupo input[type="radio"]:checked + label.est-novedad { background: var(--naranja); color: #fff; }

        .radio-almuerzo { justify-content: center; }
        .radio-almuerzo label { padding: .32rem .65rem; }
        .radio-almuerzo input[type="radio"] + label.alm-si { border-color: #059669; color: #059669; background: #f0fdf4; }
        .radio-almuerzo input[type="radio"]:checked + label.alm-si { background: #059669; color: #fff; }
        .radio-almuerzo input[type="radio"] + label.alm-no { border-color: #cbd5e1; color: #64748b; background: #f8fafc; }
        .radio-almuerzo input[type="radio"]:checked + label.alm-no { background: #64748b; color: #fff; }

        .btn-guardar { padding: .75rem 2rem; background: var(--verde); color: #fff; border: none; border-radius: 8px; font-size: .95rem; font-weight: 700; cursor: pointer; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="dashboard_docente.php" class="navbar__marca">
        <span>SIPAE</span>
        <span style="font-size:.8rem;color:var(--azul-medio);background:var(--azul-fondo);padding:2px 8px;border-radius:12px">Docente</span>
    </a>
    <div class="navbar__user">
        <span style="font-size:.85rem;font-weight:600"><?= htmlspecialchars($nombre_usuario) ?></span>
        <a href="logout.php" class="btn-logout">Cerrar sesión</a>
    </div>
</nav>

<main>
    <div class="card">
        <form method="get" action="dashboard_docente.php">
            <div class="filtros-grid">
                <div class="filtro__grupo">
                    <label class="filtro__label" for="curso">Curso</label>
                    <select class="filtro__select" id="curso" name="curso">
                        <?php foreach ($cursos as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= $c === $cursoSel ? 'selected' : '' ?>>
                                Curso <?= htmlspecialchars($c) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtro__grupo">
                    <label class="filtro__label" for="fecha">Fecha</label>
                    <input class="filtro__input" type="date" id="fecha" name="fecha" value="<?= htmlspecialchars($fechaSel) ?>" required>
                </div>

                <div class="filtro__grupo">
                    <label class="filtro__label" for="bloque">Hora</label>
                    <select class="filtro__select" id="bloque" name="bloque">
                        <?php foreach ($horariosMilitares as $h): ?>
                            <option value="<?= $h['b'] ?>" <?= $h['b'] === $bloqueSel ? 'selected' : '' ?>>
                                <?= htmlspecialchars($h['hora']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button class="btn-cargar" type="submit">Cargar estudiantes</button>
            </div>
        </form>
    </div>

    <?php if ($cursoSel !== ''): ?>
        <div class="card">
            <h2 style="font-size:1.05rem;font-weight:700;margin-bottom:1rem">
                Asistencia — Curso <?= htmlspecialchars($cursoSel) ?> / <?= htmlspecialchars($fechaSel) ?> / <?= htmlspecialchars($etiquetaHorario) ?>
            </h2>

            <form id="form-asistencia" method="post" action="procesar_asistencia.php">
                <input type="hidden" name="curso" value="<?= htmlspecialchars($cursoSel) ?>">
                <input type="hidden" name="fecha" value="<?= htmlspecialchars($fechaSel) ?>">
                <input type="hidden" name="bloque" value="<?= $bloqueSel ?>">

                <div class="tabla-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Estudiante</th>
                                <th>Estado de asistencia</th>
                                <th class="th-almuerzo">Almuerzo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estudiantes as $i => $est): 
                                $id = $est['id'];
                                $estadoActual = $asistenciaExistente[$id] ?? 'asistió';
                                $tieneAlmuerzo = isset($almuerzoExistente[$id]) ? $almuerzoExistente[$id] : ($estadoActual === 'asistió');
                            ?>
                                <tr>
                                    <td class="num"><?= $i + 1 ?></td>
                                    <td class="nombre"><?= htmlspecialchars($est['nombre']) ?></td>
                                    <td>
                                        <div class="radio-grupo" role="group">
                                            <input type="radio" id="est_<?= $id ?>_asistio" name="asistencia[<?= $id ?>]" value="asistió" <?= $estadoActual === 'asistió' ? 'checked' : '' ?>>
                                            <label for="est_<?= $id ?>_asistio" class="est-asistio">Asistió</label>

                                            <input type="radio" id="est_<?= $id ?>_falla" name="asistencia[<?= $id ?>]" value="falla" <?= $estadoActual === 'falla' ? 'checked' : '' ?>>
                                            <label for="est_<?= $id ?>_falla" class="est-falla">Falla</label>

                                            <input type="radio" id="est_<?= $id ?>_justificado" name="asistencia[<?= $id ?>]" value="justificado" <?= $estadoActual === 'justificado' ? 'checked' : '' ?>>
                                            <label for="est_<?= $id ?>_justificado" class="est-justificado">Justificado</label>

                                            <input type="radio" id="est_<?= $id ?>_novedad" name="asistencia[<?= $id ?>]" value="novedad" <?= $estadoActual === 'novedad' ? 'checked' : '' ?>>
                                            <label for="est_<?= $id ?>_novedad" class="est-novedad">Novedad</label>
                                        </div>
                                    </td>
                                    <td class="col-almuerzo">
                                        <div class="radio-grupo radio-almuerzo" role="group">
                                            <input type="radio" id="alm_<?= $id ?>_si" name="almuerzo[<?= $id ?>]" value="1" <?= $tieneAlmuerzo ? 'checked' : '' ?>>
                                            <label for="alm_<?= $id ?>_si" class="alm-si" title="Recibe almuerzo escolar">🍽️ Sí</label>

                                            <input type="radio" id="alm_<?= $id ?>_no" name="almuerzo[<?= $id ?>]" value="0" <?= !$tieneAlmuerzo ? 'checked' : '' ?>>
                                            <label for="alm_<?= $id ?>_no" class="alm-no" title="No recibe almuerzo escolar">No</label>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:1.5rem;display:flex;justify-content:flex-end">
                    <button class="btn-guardar" type="submit">Guardar asistencia</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('form-asistencia');
    if (form) {
        form.addEventListener('change', function (e) {
            const target = e.target;
            if (target && target.name && target.name.startsWith('asistencia[')) {
                const match = target.name.match(/asistencia\[(\d+)\]/);
                if (match) {
                    const estId = match[1];
                    const almSi = document.getElementById('alm_' + estId + '_si');
                    const almNo = document.getElementById('alm_' + estId + '_no');
                    if (target.value === 'falla' || target.value === 'justificado') {
                        if (almNo) almNo.checked = true;
                    } else if (target.value === 'asistió') {
                        if (almSi) almSi.checked = true;
                    }
                }
            }
        });
    }
});
</script>
</body>
</html>
