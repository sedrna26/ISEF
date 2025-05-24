<?php
// asistencias.php - Gestión de asistencias para profesores y preceptores
session_start();
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['tipo'], ['profesor', 'preceptor'])) {
    header("Location: ../index.php");
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

$usuario_id = $_SESSION['usuario_id'];
$tipo_usuario = $_SESSION['tipo'];
$profesor_id = null; // Para la carga de asistencia

// 1️⃣ Obtener profesor_id (necesario para la tabla asistencia)
if ($tipo_usuario === 'profesor') {
    $profesor_res = $mysqli->query("
        SELECT p.id AS profesor_id
        FROM profesor p
        JOIN persona per ON p.persona_id = per.id
        WHERE per.usuario_id = $usuario_id
    ");
    $profesor_data = $profesor_res->fetch_assoc();
    $profesor_id = $profesor_data ? $profesor_data['profesor_id'] : null;
} else { // Si es preceptor, obtener un profesor_id válido (el primero disponible)
    $profesor_res = $mysqli->query("SELECT id FROM profesor LIMIT 1");
    $profesor_data = $profesor_res->fetch_assoc();
    $profesor_id = $profesor_data ? $profesor_data['id'] : null;
    if (!$profesor_id) {
        // Considerar un manejo de error más robusto si no hay profesores en el sistema,
        // ya que la tabla asistencia parece requerir un profesor_id.
        // Por ahora, si no hay profesores, la inserción podría fallar más adelante.
    }
}

$mensaje_feedback = '';
$redirect_url_params = '';

// --- Variables para la planilla del Preceptor ---
$curso_id_seleccionado_preceptor = isset($_REQUEST['curso_id']) ? (int)$_REQUEST['curso_id'] : null;
$materia_id_seleccionada_preceptor = isset($_REQUEST['materia_id']) ? (int)$_REQUEST['materia_id'] : null;
$mes_seleccionado_preceptor = isset($_REQUEST['mes']) ? (int)$_REQUEST['mes'] : date('m');
$anio_seleccionado_preceptor = isset($_REQUEST['anio']) ? (int)$_REQUEST['anio'] : date('Y');
$mostrar_planilla = isset($_REQUEST['mostrar_planilla']);


// 2️⃣ Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Registro individual (profesor) - Sin cambios respecto al original
    if (isset($_POST['inscripcion_cursado_id'], $_POST['fecha'], $_POST['estado']) && $tipo_usuario === 'profesor') {
        $stmt = $mysqli->prepare("
    INSERT INTO asistencia (inscripcion_cursado_id, fecha, estado, profesor_id)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        estado = VALUES(estado),
        profesor_id = VALUES(profesor_id)
");
        $stmt->bind_param("isss", $inscripcion_cursado_id, $fecha_asistencia, $estado_asistencia, $profesor_id);
        $stmt->execute();
        $stmt->close();
        $mensaje_feedback = "Asistencia individual registrada.";
        // header("Location: asistencias.php"); // Evitar múltiples headers
        // exit;
    }

    // Guardar Planilla de Asistencia (Preceptor)
    if (isset($_POST['guardar_planilla_asistencia']) && $tipo_usuario === 'preceptor') {
        $asistencias_planilla = $_POST['asistencias_planilla'] ?? [];
        $curso_id_post = (int)$_POST['curso_id'];
        $materia_id_post = (int)$_POST['materia_id'];
        $mes_post = (int)$_POST['mes'];
        $anio_post = (int)$_POST['anio'];
        $redirect_url_params = "&curso_id=$curso_id_post&materia_id=$materia_id_post&mes=$mes_post&anio=$anio_post&mostrar_planilla=1";

        if (!$profesor_id) {
            $mensaje_feedback = "Error: No se pudo determinar un profesor para asignar la asistencia.";
        } else {
            foreach ($asistencias_planilla as $ic_id => $fechas) {
                foreach ($fechas as $fecha_str => $estado) {
                    $ic_id_int = (int)$ic_id;
                    
                    // Verificar si ya existe una asistencia para este alumno y fecha
                    $stmt_check = $mysqli->prepare("SELECT id FROM asistencia WHERE inscripcion_cursado_id = ? AND fecha = ?");
                    $stmt_check->bind_param("is", $ic_id_int, $fecha_str);
                    $stmt_check->execute();
                    $res_check = $stmt_check->get_result();
                    $asistencia_existente = $res_check->fetch_assoc();
                    $stmt_check->close();

                    if (!empty($estado)) { // Si se seleccionó Presente, Ausente o Justificado
                        if ($asistencia_existente) { // Actualizar
                            $stmt_update = $mysqli->prepare("UPDATE asistencia SET estado = ?, profesor_id = ? WHERE id = ?");
                            $stmt_update->bind_param("sii", $estado, $profesor_id, $asistencia_existente['id']);
                            $stmt_update->execute();
                            $stmt_update->close();
                        } else { // Insertar
                            $stmt_insert = $mysqli->prepare("INSERT INTO asistencia (inscripcion_cursado_id, fecha, estado, profesor_id) VALUES (?, ?, ?, ?)");
                            $stmt_insert->bind_param("issi", $ic_id_int, $fecha_str, $estado, $profesor_id);
                            $stmt_insert->execute();
                            $stmt_insert->close();
                        }
                    } else { // Si el estado es vacío (ej. "Sin marcar") y existe, se borra
                        if ($asistencia_existente) {
                            $stmt_delete = $mysqli->prepare("DELETE FROM asistencia WHERE id = ?");
                            $stmt_delete->bind_param("i", $asistencia_existente['id']);
                            $stmt_delete->execute();
                            $stmt_delete->close();
                        }
                    }
                }
            }
            $mensaje_feedback = "Planilla de asistencias guardada correctamente.";
        }
        header("Location: asistencias.php?feedback=" . urlencode($mensaje_feedback) . $redirect_url_params);
        exit;
    }
}


// 3️⃣ Cargar datos según el rol
$inscripciones_profesor = false; // Para el profesor
$cursos_preceptor_res = null;
$materias_preceptor_res = null;
$alumnos_planilla_preceptor = [];
$asistencias_cargadas_planilla = [];

if ($tipo_usuario === 'profesor') { // Lógica para profesor (sin cambios importantes respecto al original)
    if ($profesor_id) {
        $inscripciones_profesor = $mysqli->query("
            SELECT ic.id AS inscripcion_cursado_id, a.legajo, CONCAT(p.apellidos, ', ', p.nombres) AS alumno_nombre,
                   m.nombre AS materia_nombre, c.codigo AS curso_codigo, c.division
            FROM inscripcion_cursado ic
            JOIN alumno a ON ic.alumno_id = a.id
            JOIN persona p ON a.persona_id = p.id
            JOIN materia m ON ic.materia_id = m.id
            JOIN curso c ON ic.curso_id = c.id
            JOIN profesor_materia pm ON pm.materia_id = m.id AND pm.curso_id = c.id
            WHERE pm.profesor_id = $profesor_id
            ORDER BY p.apellidos, p.nombres
        ");
    }
} else { // Lógica para Preceptor (modificada para la planilla)
    $cursos_preceptor_res = $mysqli->query("
        SELECT c.id, CONCAT(c.codigo, ' ', c.division, ' - ', c.ciclo_lectivo) AS curso_nombre
        FROM curso c
        ORDER BY c.ciclo_lectivo DESC, c.codigo, c.division
    ");

    if ($curso_id_seleccionado_preceptor) {
        $materias_preceptor_res = $mysqli->query("
            SELECT DISTINCT m.id, m.nombre
            FROM inscripcion_cursado ic
            JOIN materia m ON ic.materia_id = m.id
            WHERE ic.curso_id = $curso_id_seleccionado_preceptor
            ORDER BY m.nombre
        ");
    }

    if ($mostrar_planilla && $curso_id_seleccionado_preceptor && $materia_id_seleccionada_preceptor && $mes_seleccionado_preceptor && $anio_seleccionado_preceptor) {
        $query_alumnos = $mysqli->query("
            SELECT ic.id AS inscripcion_cursado_id, a.legajo, CONCAT(p.apellidos, ', ', p.nombres) AS alumno_nombre
            FROM inscripcion_cursado ic
            JOIN alumno a ON ic.alumno_id = a.id
            JOIN persona p ON a.persona_id = p.id
            WHERE ic.curso_id = $curso_id_seleccionado_preceptor AND ic.materia_id = $materia_id_seleccionada_preceptor
            ORDER BY p.apellidos, p.nombres
        ");
        while ($alumno = $query_alumnos->fetch_assoc()) {
            $alumnos_planilla_preceptor[] = $alumno;
            // Cargar asistencias existentes para este alumno en el mes/año seleccionado
            $fecha_inicio_mes = $anio_seleccionado_preceptor . "-" . str_pad($mes_seleccionado_preceptor, 2, '0', STR_PAD_LEFT) . "-01";
            $fecha_fin_mes = date("Y-m-t", strtotime($fecha_inicio_mes));

            $stmt_asist = $mysqli->prepare("SELECT fecha, estado FROM asistencia WHERE inscripcion_cursado_id = ? AND fecha BETWEEN ? AND ?");
            $stmt_asist->bind_param("iss", $alumno['inscripcion_cursado_id'], $fecha_inicio_mes, $fecha_fin_mes);
            $stmt_asist->execute();
            $res_asist = $stmt_asist->get_result();
            while ($asist = $res_asist->fetch_assoc()) {
                $asistencias_cargadas_planilla[$alumno['inscripcion_cursado_id']][$asist['fecha']] = $asist['estado'];
            }
            $stmt_asist->close();
        }
    }
}

// 4️⃣ Obtener las últimas asistencias registradas con paginación (sin cambios respecto al original)
$registros_por_pagina = 10; // Reducido para ejemplo
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

$query_total_registros = "";
if ($tipo_usuario === 'profesor' && $profesor_id) {
    $query_total_registros = "SELECT COUNT(*) as total FROM asistencia a WHERE a.profesor_id = $profesor_id";
} else if ($tipo_usuario === 'preceptor') { // Para preceptor, mostrar todas o filtrar por curso/materia si se desea
     $query_total_registros = "SELECT COUNT(DISTINCT a.id) 
                              FROM asistencia a 
                              JOIN inscripcion_cursado ic ON a.inscripcion_cursado_id = ic.id
                              WHERE 1=1";
    if ($curso_id_seleccionado_preceptor) $query_total_registros .= " AND ic.curso_id = $curso_id_seleccionado_preceptor";
    if ($materia_id_seleccionada_preceptor) $query_total_registros .= " AND ic.materia_id = $materia_id_seleccionada_preceptor";

} else { // Por si un profesor no tiene $profesor_id (no debería pasar)
    $query_total_registros = "SELECT COUNT(*) as total FROM asistencia";
}
$total_registros_res = $mysqli->query($query_total_registros);
$total_registros = $total_registros_res ? $total_registros_res->fetch_assoc()['total'] : 0;
$total_paginas = ceil($total_registros / $registros_por_pagina);

$query_asistencias_listado = "
    SELECT a.id, a.fecha, a.estado, 
           CONCAT(p_alumno.apellidos, ', ', p_alumno.nombres) as alumno_nombre,
           m.nombre as materia_nombre, 
           c.codigo as curso_codigo,
           CONCAT(p_prof.apellidos, ', ', p_prof.nombres) as profesor_nombre
    FROM asistencia a
    JOIN inscripcion_cursado ic ON a.inscripcion_cursado_id = ic.id
    JOIN alumno al ON ic.alumno_id = al.id
    JOIN persona p_alumno ON al.persona_id = p_alumno.id
    JOIN materia m ON ic.materia_id = m.id
    JOIN curso c ON ic.curso_id = c.id
    JOIN profesor prof ON a.profesor_id = prof.id
    JOIN persona p_prof ON prof.persona_id = p_prof.id
    WHERE 1=1 ";

if ($tipo_usuario === 'profesor' && $profesor_id) {
    $query_asistencias_listado .= " AND a.profesor_id = $profesor_id ";
} else if ($tipo_usuario === 'preceptor') {
    if ($curso_id_seleccionado_preceptor) $query_asistencias_listado .= " AND ic.curso_id = $curso_id_seleccionado_preceptor";
    if ($materia_id_seleccionada_preceptor) $query_asistencias_listado .= " AND ic.materia_id = $materia_id_seleccionada_preceptor";
}
$query_asistencias_listado .= " ORDER BY a.fecha DESC, alumno_nombre ASC LIMIT $registros_por_pagina OFFSET $offset";
$asistencias_listado = $mysqli->query($query_asistencias_listado);

if (isset($_GET['feedback'])) {
    $mensaje_feedback = htmlspecialchars($_GET['feedback']);
}

// Obtener todas las materias para el select
$materias_query = "SELECT id, nombre FROM materia ORDER BY nombre";
$materias_result = $mysqli->query($materias_query);
$materias = [];
while ($row = $materias_result->fetch_assoc()) {
    $materias[] = $row;
}

// Obtener todos los cursos para el select
$cursos_query = "SELECT id, anio FROM curso ORDER BY anio";
$cursos_result = $mysqli->query($cursos_query);
$cursos = [];
while ($row = $cursos_result->fetch_assoc()) {
    $cursos[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias - ISEF</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; font-size: 0.9em; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f2f2f2; text-align: center; }
        td.day-cell { min-width: 50px; text-align: center; }
        td.day-cell select { padding: 3px; font-size:0.9em; width:auto; }
        .form-section { margin-bottom: 30px; padding: 15px; border: 1px solid #e0e0e0; border-radius: 5px; background-color: #f9f9f9;}
        .form-section h2 { margin-top: 0; }
        label { display: inline-block; margin-bottom: 5px; margin-right: 10px; }
        select, input[type="date"], input[type="number"], button { padding: 8px; margin-bottom:10px; border-radius: 3px; border: 1px solid #ccc; }
        button { background-color: #007bff; color: white; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .feedback { padding: 10px; margin-bottom: 15px; border-radius: 3px; color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb;}
        .error { padding: 10px; margin-bottom: 15px; border-radius: 3px; color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb;}
        .paginacion { margin: 20px 0; text-align: center; }
        .paginacion a, .paginacion span { color: #0066cc; padding: 8px 12px; text-decoration: none; border: 1px solid #ddd; margin: 0 2px; }
        .paginacion a:hover { background-color: #f2f2f2; }
        .paginacion span { background-color: #e9ecef; color: #495057; }
        .sticky-header th { position: sticky; top: 0; z-index: 2; } /* Para que el encabezado de la tabla quede fijo al hacer scroll */
    </style>
</head>
<body>
    <h1>Gestión de Asistencias</h1>
    <a href="dashboard.php">&laquo; Volver al menú</a>

    <?php if ($mensaje_feedback): ?>
        <div class="feedback"><?= $mensaje_feedback ?></div>
    <?php endif; ?>
    <?php if (!$profesor_id && $tipo_usuario === 'preceptor'): ?>
        <div class="error">Advertencia: No se pudo determinar un profesor por defecto. Las asistencias podrían no guardarse correctamente si no hay profesores en el sistema.</div>
    <?php endif; ?>


    <?php if ($tipo_usuario === 'profesor'): ?>
        <div class="form-section">
            <h2>Registrar Asistencia (Profesor)</h2>
            <form method="post" action="asistencias.php">
                <label>Alumno:
                    <select name="inscripcion_cursado_id" required>
                        <option value="">-- Seleccione Alumno --</option>
                        <?php if ($inscripciones_profesor && $inscripciones_profesor->num_rows > 0): ?>
                            <?php while ($i = $inscripciones_profesor->fetch_assoc()): ?>
                                <option value="<?= $i['inscripcion_cursado_id'] ?>">
                                    <?= htmlspecialchars($i['alumno_nombre']) ?> | <?= htmlspecialchars($i['materia_nombre']) ?> | <?= htmlspecialchars($i['curso_codigo'] . ' - ' . $i['division']) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                             <option value="" disabled>No hay alumnos asignados o usted no es profesor.</option>
                        <?php endif; ?>
                    </select>
                </label><br><br>
                <label>Fecha: <input type="date" name="fecha" required value="<?= date('Y-m-d') ?>"></label><br><br>
                <label>Estado:
                    <select name="estado" required>
                        <option value="Presente">Presente</option>
                        <option value="Ausente">Ausente</option>
                        <option value="Justificado">Justificado</option>
                    </select>
                </label><br><br>
                <button type="submit">Registrar Asistencia</button>
            </form>
        </div>
    <?php elseif ($tipo_usuario === 'preceptor'): ?>
        <div class="form-section">
            <h2>Planilla de Asistencia (Preceptor)</h2>
            <form method="GET" action="asistencias.php">
                <input type="hidden" name="mostrar_planilla" value="1">
                <label>Curso:
                    <select name="curso_id" required onchange="this.form.submit()">
                        <option value="">-- Seleccione Curso --</option>
                        <?php if($cursos_preceptor_res) : ?>
                            <?php while ($c = $cursos_preceptor_res->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>" <?= ($curso_id_seleccionado_preceptor == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['curso_nombre']) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </label>

                <?php if ($curso_id_seleccionado_preceptor): ?>
                    <label>Materia:
                        <select name="materia_id" required onchange="this.form.submit()">
                            <option value="">-- Seleccione Materia --</option>
                            <?php if($materias_preceptor_res) : ?>
                                <?php while ($m = $materias_preceptor_res->fetch_assoc()): ?>
                                    <option value="<?= $m['id'] ?>" <?= ($materia_id_seleccionada_preceptor == $m['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($m['nombre']) ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </label>
                <?php endif; ?>

                <?php if ($curso_id_seleccionado_preceptor && $materia_id_seleccionada_preceptor): ?>
                    <label>Mes:
                        <select name="mes" required onchange="this.form.submit()">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= ($mes_seleccionado_preceptor == $m) ? 'selected' : '' ?>>
                                    <?= DateTime::createFromFormat('!m', $m)->format('F') ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <label>Año:
                        <select name="anio" required onchange="this.form.submit()">
                            <?php for ($a = date('Y'); $a >= date('Y') - 5; $a--): // Últimos 5 años ?>
                                <option value="<?= $a ?>" <?= ($anio_seleccionado_preceptor == $a) ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endfor; ?>
                        </select>
                    </label>
                     <button type="submit" style="display:inline-block; vertical-align:bottom;">Mostrar Planilla</button>
                <?php endif; ?>
            </form>

            <?php if ($mostrar_planilla && $curso_id_seleccionado_preceptor && $materia_id_seleccionada_preceptor && $mes_seleccionado_preceptor && $anio_seleccionado_preceptor): ?>
                <?php if (!empty($alumnos_planilla_preceptor)):
                    $dias_en_mes = cal_days_in_month(CAL_GREGORIAN, $mes_seleccionado_preceptor, $anio_seleccionado_preceptor);
                ?>
                    <h3>Planilla: <?= htmlspecialchars($materias_preceptor_res->fetch_assoc()['nombre'] ?? 'Materia') ?> - Curso: <?= htmlspecialchars($cursos_preceptor_res->fetch_assoc()['curso_nombre'] ?? 'Curso') ?> - Mes: <?= DateTime::createFromFormat('!m', $mes_seleccionado_preceptor)->format('F') ?> <?= $anio_seleccionado_preceptor ?></h3>
                    <form method="POST" action="asistencias.php">
                        <input type="hidden" name="guardar_planilla_asistencia" value="1">
                        <input type="hidden" name="curso_id" value="<?= $curso_id_seleccionado_preceptor ?>">
                        <input type="hidden" name="materia_id" value="<?= $materia_id_seleccionada_preceptor ?>">
                        <input type="hidden" name="mes" value="<?= $mes_seleccionado_preceptor ?>">
                        <input type="hidden" name="anio" value="<?= $anio_seleccionado_preceptor ?>">
                        <div style="overflow-x:auto;"> <table>
                            <thead class="sticky-header">
                                <tr>
                                    <th>N°</th>
                                    <th>Legajo</th>
                                    <th>Alumno</th>
                                    <?php for ($dia = 1; $dia <= $dias_en_mes; $dia++): ?>
                                        <?php
                                            $fecha_actual_dia = new DateTime("$anio_seleccionado_preceptor-$mes_seleccionado_preceptor-$dia");
                                            $nombre_dia_semana = $fecha_actual_dia->format('D'); // Dom, Lun, Mar...
                                            $es_finde = ($nombre_dia_semana == 'Sat' || $nombre_dia_semana == 'Sun');
                                        ?>
                                        <th class="day-cell <?= $es_finde ? 'bg-light' : '' ?>" title="<?= $nombre_dia_semana ?>">
                                            <?= $dia ?>
                                        </th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alumnos_planilla_preceptor as $index => $alumno): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($alumno['legajo']) ?></td>
                                        <td><?= htmlspecialchars($alumno['alumno_nombre']) ?></td>
                                        <?php for ($dia = 1; $dia <= $dias_en_mes; $dia++):
                                            $fecha_completa = $anio_seleccionado_preceptor . '-' . str_pad($mes_seleccionado_preceptor, 2, '0', STR_PAD_LEFT) . '-' . str_pad($dia, 2, '0', STR_PAD_LEFT);
                                            $estado_actual = $asistencias_cargadas_planilla[$alumno['inscripcion_cursado_id']][$fecha_completa] ?? '';
                                        ?>
                                            <td class="day-cell">
                                                <select name="asistencias_planilla[<?= $alumno['inscripcion_cursado_id'] ?>][<?= $fecha_completa ?>]">
                                                    <option value="" <?= ($estado_actual == '') ? 'selected' : '' ?>>-</option>
                                                    <option value="Presente" <?= ($estado_actual == 'Presente') ? 'selected' : '' ?>>P</option>
                                                    <option value="Ausente" <?= ($estado_actual == 'Ausente') ? 'selected' : '' ?>>A</option>
                                                    <option value="Justificado" <?= ($estado_actual == 'Justificado') ? 'selected' : '' ?>>J</option>
                                                </select>
                                            </td>
                                        <?php endfor; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <br>
                        <button type="submit">Guardar Asistencias de la Planilla</button>
                    </form>
                <?php else: ?>
                    <p>No hay alumnos inscritos para el curso y materia seleccionados, o no se pudo generar la planilla.</p>
                <?php endif; ?>
            <?php elseif ($mostrar_planilla): ?>
                 <p>Por favor, seleccione Curso, Materia, Mes y Año y presione "Mostrar Planilla".</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div class="card mt-4">
    <div class="card-header">
        <h5>Generar Informes de Asistencias (PDF)</h5>
    </div>
    <div class="card-body">
        <form id="formGenerarPDF" action="generar_pdf_asistencias.php" method="POST" target="_blank">
            <div class="form-group">
                <label for="materia_pdf">Seleccionar Espacio Curricular:</label>
                <select class="form-control" id="materia_pdf" name="materia_id" required>
                    <option value="">-- Seleccione una materia --</option>
                    <?php foreach ($materias as $materia): ?>
                        <option value="<?= $materia['id'] ?>"><?= htmlspecialchars($materia['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group mt-3">
                <label for="curso_pdf">Seleccionar Curso:</label>
                <select class="form-control" id="curso_pdf" name="curso_id" required>
                    <option value="">-- Seleccione un curso --</option>
                    <?php foreach ($cursos as $curso): ?>
                        <option value="<?= $curso['id'] ?>"><?= htmlspecialchars($curso['anio']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <input type="hidden" id="profesor_id_pdf" name="profesor_id">
            <p class="mt-2" id="profesor_nombre_display">Profesor/a: No seleccionado</p>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary me-2" name="periodo" value="abril_julio">
                    Generar PDF (Abril-Julio)
                </button>
                <button type="submit" class="btn btn-primary" name="periodo" value="septiembre_diciembre">
                    Generar PDF (Septiembre-Diciembre)
                </button>
            </div>
        </form>
    </div>
</div>

    <!-- Sección de últimas asistencias registradas -->
    <div class="form-section">
        <h2>Últimas Asistencias Registradas <?= ($tipo_usuario === 'preceptor' && $curso_id_seleccionado_preceptor) ? '(Filtrado por selección actual si aplica)' : '' ?></h2>
        <?php if ($asistencias_listado && $asistencias_listado->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Alumno</th>
                    <th>Materia</th>
                    <th>Curso</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th>Profesor que registró</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = $asistencias_listado->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['alumno_nombre']) ?></td>
                        <td><?= htmlspecialchars($a['materia_nombre']) ?></td>
                        <td><?= htmlspecialchars($a['curso_codigo']) ?></td>
                        <td><?= htmlspecialchars(date("d/m/Y", strtotime($a['fecha']))) ?></td>
                        <td><?= htmlspecialchars($a['estado']) ?></td>
                        <td><?= htmlspecialchars($a['profesor_nombre']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <div class="paginacion">
            <?php
                $params_paginacion = '';
                if ($curso_id_seleccionado_preceptor) $params_paginacion .= "&curso_id=$curso_id_seleccionado_preceptor";
                if ($materia_id_seleccionada_preceptor) $params_paginacion .= "&materia_id=$materia_id_seleccionada_preceptor";
                if ($mes_seleccionado_preceptor) $params_paginacion .= "&mes=$mes_seleccionado_preceptor";
                if ($anio_seleccionado_preceptor) $params_paginacion .= "&anio=$anio_seleccionado_preceptor";
                if ($mostrar_planilla) $params_paginacion .= "&mostrar_planilla=1";
            ?>
            <?php if ($total_paginas > 1): ?>
                <?php if ($pagina_actual > 1): ?>
                    <a href="?pagina=1<?= $params_paginacion ?>">&laquo; Primera</a>
                    <a href="?pagina=<?= $pagina_actual - 1 ?><?= $params_paginacion ?>">&lsaquo; Anterior</a>
                <?php endif; ?>

                <span>Página <?= $pagina_actual ?> de <?= $total_paginas ?></span>

                <?php if ($pagina_actual < $total_paginas): ?>
                    <a href="?pagina=<?= $pagina_actual + 1 ?><?= $params_paginacion ?>">Siguiente &rsaquo;</a>
                    <a href="?pagina=<?= $total_paginas ?><?= $params_paginacion ?>">Última &raquo;</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php else: ?>
            <p>No hay asistencias registradas recientemente o para el filtro actual.</p>
        <?php endif; ?>
    </div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const materiaSelect = document.getElementById('materia_pdf');
        const profesorIdInput = document.getElementById('profesor_id_pdf');
        const profesorNombreDisplay = document.getElementById('profesor_nombre_display');
        const cursoSelect = document.getElementById('curso_pdf');

        // Función para obtener y mostrar el profesor de la materia seleccionada
        function getProfesorForMateria(materiaId) {
            if (!materiaId) {
                profesorIdInput.value = '';
                profesorNombreDisplay.textContent = 'Profesor/a: No seleccionado';
                return;
            }

            // Realizar una solicitud AJAX para obtener el profesor de la materia
            fetch('get_profesor_materia.php?materia_id=' + materiaId)
                .then(response => response.json())
                .then(data => {
                    if (data.profesor_id) {
                        profesorIdInput.value = data.profesor_id;
                        profesorNombreDisplay.textContent = 'Profesor/a: ' + data.profesor_nombre;
                    } else {
                        profesorIdInput.value = '';
                        profesorNombreDisplay.textContent = 'Profesor/a: No encontrado para esta materia';
                    }
                })
                .catch(error => {
                    console.error('Error al obtener el profesor:', error);
                    profesorIdInput.value = '';
                    profesorNombreDisplay.textContent = 'Profesor/a: Error al cargar';
                });
        }

        // Listener para cuando cambia la materia seleccionada
        materiaSelect.addEventListener('change', function() {
            getProfesorForMateria(this.value);
        });

        // Asegurarse de que si hay una materia pre-seleccionada, se cargue el profesor
        if (materiaSelect.value) {
            getProfesorForMateria(materiaSelect.value);
        }
    });
</script>
</body>
</html>
<?php
$mysqli->close();
?>