<?php
// evaluaciones.php - Gestión de evaluaciones
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
$profesor_id = null;
$mensaje_feedback = ''; // Para mostrar mensajes al usuario

// Obtener profesor_id si aplica
if ($tipo_usuario === 'profesor') {
    $res_prof_id = $mysqli->query("
        SELECT p.id FROM profesor p
        JOIN persona per ON p.persona_id = per.id
        WHERE per.usuario_id = $usuario_id
    ");
    $data_prof_id = $res_prof_id->fetch_assoc();
    $profesor_id = $data_prof_id ? $data_prof_id['id'] : null;
    if (!$profesor_id) {
        die("Error: No se pudo encontrar el ID de profesor asociado a este usuario.");
    }
}

// Definición de las columnas de evaluación para la grilla del profesor
// Cada elemento: ['key_form' => clave para el form, 'label' => etiqueta visible, 'db_tipo' => ENUM tipo en BD, 'db_instancia' => ENUM instancia en BD, 'db_obs_detalle' => Detalle para campo observaciones si es necesario diferenciar]
$columnas_evaluacion_grilla = [
    ['key_form' => 'prac_1', 'label' => '1° Práctico',       'db_tipo' => 'Parcial',   'db_instancia' => '1°Cuatrimestre', 'db_obs_detalle' => 'Práctico 1'],
    ['key_form' => 'prac_2', 'label' => '2° Práctico',       'db_tipo' => 'Parcial',   'db_instancia' => '1°Cuatrimestre', 'db_obs_detalle' => 'Práctico 2'],
    ['key_form' => 'prac_3', 'label' => '3° Práctico',       'db_tipo' => 'Parcial',   'db_instancia' => '1°Cuatrimestre', 'db_obs_detalle' => 'Práctico 3'],
    ['key_form' => 'parc_1', 'label' => '1° Parcial',        'db_tipo' => 'Parcial',   'db_instancia' => '1°Cuatrimestre', 'db_obs_detalle' => null],
    ['key_form' => 'rec_p1', 'label' => 'Recup. 1P',       'db_tipo' => 'Coloquio',  'db_instancia' => '1°Cuatrimestre', 'db_obs_detalle' => 'Recuperatorio 1er Parcial'], // Usamos Coloquio para diferenciar recuperatorios
    ['key_form' => 'parc_2', 'label' => '2° Parcial',        'db_tipo' => 'Parcial',   'db_instancia' => '2°Cuatrimestre', 'db_obs_detalle' => null],
    ['key_form' => 'rec_p2', 'label' => 'Recup. 2P',       'db_tipo' => 'Coloquio',  'db_instancia' => '2°Cuatrimestre', 'db_obs_detalle' => 'Recuperatorio 2do Parcial'],
    ['key_form' => 'extra',  'label' => 'Extraordinario',    'db_tipo' => 'Final',     'db_instancia' => 'Anual',          'db_obs_detalle' => 'Extraordinario'],
    ['key_form' => 'trab_c', 'label' => 'Trabajo de Campo',  'db_tipo' => 'Coloquio',  'db_instancia' => 'Anual',          'db_obs_detalle' => 'Trabajo de Campo'],
];


// Procesamiento de POST para profesores (nueva grilla)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tipo_usuario === 'profesor' && isset($_POST['guardar_evaluaciones_profesor'])) {
    $materia_id_post = isset($_POST['materia_id_seleccionada']) ? (int)$_POST['materia_id_seleccionada'] : null;
    $curso_id_post = isset($_POST['curso_id_seleccionado']) ? (int)$_POST['curso_id_seleccionado'] : null;
    $fecha_carga = $_POST['fecha_carga_general'];

    if ($materia_id_post && $curso_id_post && $fecha_carga) {
        foreach ($_POST['notas'] as $ic_id => $evaluaciones_alumno) {
            $observaciones_generales_alumno = $_POST['observaciones_generales_alumno'][$ic_id] ?? '';

            foreach ($evaluaciones_alumno as $col_key => $nota) {
                $col_config = null;
                foreach ($columnas_evaluacion_grilla as $c) {
                    if ($c['key_form'] === $col_key) {
                        $col_config = $c;
                        break;
                    }
                }

                if ($col_config && ($nota !== '' || isset($_POST['notas_originales'][$ic_id][$col_key]))) { // Procesar si hay nota nueva o había una original (para borrar)
                    $nota_val = ($nota === '') ? null : (int)$nota;
                    $observaciones_columna = $_POST['observaciones_columna'][$ic_id][$col_key] ?? '';

                    // Combinar observaciones: detalle de columna + observación específica de celda + observación general del alumno (decidir prioridad o concatenar)
                    $obs_final = $col_config['db_obs_detalle'] ? $col_config['db_obs_detalle'] . ". " : "";
                    $obs_final .= $observaciones_columna;
                    // Aquí podrías decidir si la observación general del alumno se añade a cada evaluación o se maneja de otra forma.
                    // Por ahora, la observación de la celda tiene prioridad para esta evaluación específica.

                    // Verificar si existe una evaluación para este ic_id, tipo, instancia (y detalle en obs)
                    $sql_check = "SELECT id, observaciones FROM evaluacion 
                                  WHERE inscripcion_cursado_id = ? 
                                  AND tipo = ? 
                                  AND instancia = ?";
                    if ($col_config['db_obs_detalle']) {
                        $sql_check .= " AND observaciones LIKE '" . $mysqli->real_escape_string($col_config['db_obs_detalle']) . "%'";
                    } else {
                        $sql_check .= " AND (observaciones IS NULL OR observaciones NOT LIKE 'Práctico %' OR observaciones NOT LIKE 'Recuperatorio %' OR observaciones NOT LIKE 'Extraordinario%' OR observaciones NOT LIKE 'Trabajo de Campo%')";
                    }

                    $stmt_check = $mysqli->prepare($sql_check);
                    $stmt_check->bind_param("iss", $ic_id, $col_config['db_tipo'], $col_config['db_instancia']);
                    $stmt_check->execute();
                    $res_check = $stmt_check->get_result();
                    $eval_existente = $res_check->fetch_assoc();
                    $stmt_check->close();

                    if ($nota_val === null && $eval_existente) { // Borrar nota existente
                        $stmt_delete = $mysqli->prepare("DELETE FROM evaluacion WHERE id = ?");
                        $stmt_delete->bind_param("i", $eval_existente['id']);
                        $stmt_delete->execute();
                        $stmt_delete->close();
                    } elseif ($nota_val !== null) {
                        if ($eval_existente) { // Actualizar
                            // Mantener la parte de la observación que identifica el tipo de evaluación, y añadir la nueva observación de la columna
                            $obs_actual = $eval_existente['observaciones'];
                            $obs_para_actualizar = $col_config['db_obs_detalle'] ? $col_config['db_obs_detalle'] . ". " : "";
                            $obs_para_actualizar .= $observaciones_columna;

                            $stmt_update = $mysqli->prepare("UPDATE evaluacion SET nota = ?, fecha = ?, profesor_id = ?, observaciones = ?, nota_letra = '' WHERE id = ?"); // nota_letra se puede añadir si es necesario
                            $stmt_update->bind_param("isssi", $nota_val, $fecha_carga, $profesor_id, $obs_para_actualizar, $eval_existente['id']);
                            $stmt_update->execute();
                            $stmt_update->close();
                        } else { // Insertar
                            $stmt_insert = $mysqli->prepare("INSERT INTO evaluacion (inscripcion_cursado_id, tipo, instancia, fecha, nota, profesor_id, observaciones, nota_letra) VALUES (?, ?, ?, ?, ?, ?, ?, '')");
                            $stmt_insert->bind_param("isssiss", $ic_id, $col_config['db_tipo'], $col_config['db_instancia'], $fecha_carga, $nota_val, $profesor_id, $obs_final);
                            $stmt_insert->execute();
                            $stmt_insert->close();
                        }
                    }
                }
            }
            // Guardar observaciones generales del alumno (¿dónde? Podría ser en la última evaluación relevante o en una tabla separada si es necesario)
            // Por ahora, no se guardan las observaciones generales de forma separada, se incluyen en cada celda si es relevante.
        }
        $mensaje_feedback = "Evaluaciones guardadas correctamente.";
        // Redirigir para mantener la selección
        header("Location: evaluaciones.php?materia_id=" . $materia_id_post . "&curso_id=" . $curso_id_post . "&feedback=" . urlencode($mensaje_feedback));
        exit;
    } else {
        $mensaje_feedback = "Error: Faltan datos para guardar (materia, curso o fecha).";
    }
}


// Procesamiento de POST individual (legado, se puede remover o mantener si hay dos formas de carga)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tipo_usuario === 'profesor' && isset($_POST['inscripcion_cursado_id'], $_POST['fecha'], $_POST['tipo'], $_POST['instancia']) && !isset($_POST['guardar_evaluaciones_profesor'])) {
    $stmt = $mysqli->prepare("INSERT INTO evaluacion (inscripcion_cursado_id, tipo, instancia, fecha, nota, nota_letra, profesor_id, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssisss", $_POST['inscripcion_cursado_id'], $_POST['tipo'], $_POST['instancia'], $_POST['fecha'], $_POST['nota'], $_POST['nota_letra'], $profesor_id, $_POST['observaciones']);
    $stmt->execute();
    $stmt->close();
    header("Location: evaluaciones.php?feedback=" . urlencode("Evaluación individual registrada."));
    exit;
}

// Procesamiento de POST para Preceptores (sin cambios)
if ($tipo_usuario === 'preceptor' && isset($_POST['registro_lote'], $_POST['fecha'], $_POST['tipo'], $_POST['instancia'])) {
    $fecha = $_POST['fecha'];
    $tipo = $_POST['tipo'];
    $instancia = $_POST['instancia'];
    $curso_id_get = $_GET['curso_id'] ?? null;
    $materia_id_get = $_GET['materia_id'] ?? null;

    // IMPORTANTE: Determinar el profesor_id para las evaluaciones cargadas por preceptor.
    // Podría ser el profesor asignado a esa materia/curso, o NULL si es carga administrativa.
    // La tabla 'evaluacion' tiene profesor_id NOT NULL. Esto necesita definirse.
    // Por ahora, se intentará buscar UN profesor para esa materia/curso. Si no, se pondrá el del preceptor (si tuviera uno asociado) o un ID placeholder (requiere que exista).
    // Esta parte es una simplificación y debería revisarse la lógica de negocio para profesor_id en cargas de preceptor.
    $profesor_id_para_preceptor = $profesor_id; // Temporalmente, usar el ID del preceptor si fuera profesor, o buscar uno.

    if ($curso_id_get && $materia_id_get) {
        $q_prof_materia = "SELECT profesor_id FROM profesor_materia WHERE materia_id = $materia_id_get AND curso_id = $curso_id_get LIMIT 1";
        $r_prof_materia = $mysqli->query($q_prof_materia);
        if ($d_prof_materia = $r_prof_materia->fetch_assoc()) {
            $profesor_id_para_preceptor = $d_prof_materia['profesor_id'];
        } else {
            // Si no hay profesor para la materia/curso, ¿qué profesor_id usar?
            // Por ahora, si no se encuentra, y si el preceptor tiene un user_id que casualmente es de un profesor (poco probable), se usa ese.
            // Idealmente, la BD permitiría NULL o habría un profesor "sistema".
            // COMO SOLUCIÓN TEMPORAL, Y ASUMIENDO QUE DEBE HABER UN PROFESOR, si no se encuentra, la inserción fallará o usará un ID inválido si no se maneja.
            // Para evitar error, usamos el profesor_id del preceptor (si es también profesor) o el primer profesor del sistema.
            if (!$profesor_id_para_preceptor) {
                $q_any_prof = $mysqli->query("SELECT id FROM profesor LIMIT 1");
                if ($d_any_prof = $q_any_prof->fetch_assoc()) {
                    $profesor_id_para_preceptor = $d_any_prof['id']; // ¡Esto es un placeholder!
                } else {
                    die("Error crítico: No hay profesores en el sistema para asignar la evaluación y el campo profesor_id es NOT NULL.");
                }
            }
        }
    }


    foreach ($_POST['nota'] as $ic_id => $nota) {
        if ($nota !== '') {
            $nota_letra = $_POST['nota_letra'][$ic_id] ?? '';
            $obs = $_POST['observaciones'][$ic_id] ?? '';

            $stmt = $mysqli->prepare("INSERT INTO evaluacion (inscripcion_cursado_id, tipo, instancia, fecha, nota, nota_letra, profesor_id, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            // Asegurar que profesor_id_para_preceptor tenga un valor válido antes de hacer bind.
            if (!$profesor_id_para_preceptor) $profesor_id_para_preceptor = 1; // Fallback muy básico, ajustar.

            $stmt->bind_param("isssisis", $ic_id, $tipo, $instancia, $fecha, $nota, $nota_letra, $profesor_id_para_preceptor, $obs);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: evaluaciones.php?curso_id={$curso_id_get}&materia_id={$materia_id_get}&feedback=" . urlencode("Evaluaciones en lote registradas."));
    exit;
}

// --- Datos para la interfaz ---

// Para PROFESORES: selección de materia y curso
$materias_profesor = [];
$cursos_profesor = [];
$alumnos_grilla = [];
$evaluaciones_cargadas_grilla = [];

$materia_id_seleccionada = isset($_GET['materia_id']) ? (int)$_GET['materia_id'] : null;
$curso_id_seleccionado = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : null;
$nombre_materia_seleccionada = '';
$nombre_curso_seleccionado = '';

if ($tipo_usuario === 'profesor' && $profesor_id) {
    // Obtener materias y cursos asignados al profesor
    $query_materias_cursos_prof = "
        SELECT DISTINCT m.id AS materia_id, m.nombre AS materia_nombre, c.id AS curso_id, CONCAT(c.codigo, ' ', c.division, ' - ', c.ciclo_lectivo) AS curso_nombre
        FROM profesor_materia pm
        JOIN materia m ON pm.materia_id = m.id
        JOIN curso c ON pm.curso_id = c.id
        WHERE pm.profesor_id = $profesor_id
        ORDER BY m.nombre, c.ciclo_lectivo DESC, c.codigo
    ";
    $res_materias_cursos_prof = $mysqli->query($query_materias_cursos_prof);
    $asignaciones_prof = [];
    while ($row = $res_materias_cursos_prof->fetch_assoc()) {
        $asignaciones_prof[] = $row;
    }

    if (count($asignaciones_prof) === 1 && !$materia_id_seleccionada && !$curso_id_seleccionado) {
        $materia_id_seleccionada = $asignaciones_prof[0]['materia_id'];
        $curso_id_seleccionado = $asignaciones_prof[0]['curso_id'];
    }

    if ($materia_id_seleccionada && $curso_id_seleccionado) {
        // Obtener nombre de materia y curso para mostrar
        foreach ($asignaciones_prof as $asig) {
            if ($asig['materia_id'] == $materia_id_seleccionada) $nombre_materia_seleccionada = $asig['materia_nombre'];
            if ($asig['curso_id'] == $curso_id_seleccionado) $nombre_curso_seleccionado = $asig['curso_nombre'];
        }


        // Obtener alumnos para la grilla
        $query_alumnos_grilla = "
            SELECT ic.id AS inscripcion_cursado_id, a.legajo, p.apellidos, p.nombres, ic.estado AS condicion_cursado
            FROM inscripcion_cursado ic
            JOIN alumno a ON ic.alumno_id = a.id
            JOIN persona p ON a.persona_id = p.id
            WHERE ic.materia_id = $materia_id_seleccionada AND ic.curso_id = $curso_id_seleccionado
            AND ic.id IN (SELECT DISTINCT ic_inner.id FROM inscripcion_cursado ic_inner
                          JOIN profesor_materia pm_inner ON ic_inner.materia_id = pm_inner.materia_id AND ic_inner.curso_id = pm_inner.curso_id
                          WHERE pm_inner.profesor_id = $profesor_id)
            ORDER BY p.apellidos, p.nombres
        ";
        $res_alumnos_grilla = $mysqli->query($query_alumnos_grilla);
        while ($alumno = $res_alumnos_grilla->fetch_assoc()) {
            $alumnos_grilla[] = $alumno;
            // Para cada alumno, buscar sus evaluaciones existentes para las columnas definidas
            $evaluaciones_cargadas_grilla[$alumno['inscripcion_cursado_id']] = [];
            foreach ($columnas_evaluacion_grilla as $col) {
                $sql_eval = "SELECT id, nota, observaciones FROM evaluacion 
                             WHERE inscripcion_cursado_id = ? AND tipo = ? AND instancia = ?";
                $params_eval = [$alumno['inscripcion_cursado_id'], $col['db_tipo'], $col['db_instancia']];

                if ($col['db_obs_detalle']) {
                    $sql_eval .= " AND observaciones LIKE ?";
                    $params_eval[] = $col['db_obs_detalle'] . "%";
                } else {
                    // Evitar que coincida con los que SÍ tienen detalle identificador en observaciones
                    $sql_eval .= " AND (observaciones IS NULL OR (observaciones NOT LIKE 'Práctico %' AND observaciones NOT LIKE 'Recuperatorio %' AND observaciones NOT LIKE 'Extraordinario%' AND observaciones NOT LIKE 'Trabajo de Campo%'))";
                }
                $sql_eval .= " LIMIT 1";

                $stmt_eval = $mysqli->prepare($sql_eval);
                $types = str_repeat('s', count($params_eval)); // generar la cadena de tipos
                $stmt_eval->bind_param($types, ...$params_eval); // desempaquetar params

                $stmt_eval->execute();
                $res_eval = $stmt_eval->get_result();
                if ($data_eval = $res_eval->fetch_assoc()) {
                    $evaluaciones_cargadas_grilla[$alumno['inscripcion_cursado_id']][$col['key_form']] = [
                        'nota' => $data_eval['nota'],
                        'observaciones' => $data_eval['observaciones']
                    ];
                }
                $stmt_eval->close();
            }
        }
    }
}


// Datos para PRECEPTORES (sin cambios en la lógica de obtención, solo para la interfaz)
$cursos_res = null;
$materias_res = null;
$alumnos_res_preceptor = null;

if ($tipo_usuario === 'preceptor') {
    $cursos_res = $mysqli->query("SELECT id, CONCAT(codigo, ' ', division, ' - ', ciclo_lectivo) AS nombre FROM curso ORDER BY ciclo_lectivo DESC, codigo");
    if (isset($_GET['curso_id'])) {
        $curso_id_preceptor = (int)$_GET['curso_id'];
        $materias_res = $mysqli->query("
            SELECT DISTINCT m.id, m.nombre
            FROM inscripcion_cursado ic
            JOIN materia m ON ic.materia_id = m.id
            WHERE ic.curso_id = $curso_id_preceptor
            ORDER BY m.nombre
        ");
    }
    if (isset($_GET['curso_id'], $_GET['materia_id'])) {
        $curso_id_preceptor = (int)$_GET['curso_id'];
        $materia_id_preceptor = (int)$_GET['materia_id'];
        $alumnos_res_preceptor = $mysqli->query("
            SELECT ic.id AS inscripcion_cursado_id, CONCAT(p.apellidos, ', ', p.nombres) AS alumno_nombre
            FROM inscripcion_cursado ic
            JOIN alumno a ON ic.alumno_id = a.id
            JOIN persona p ON a.persona_id = p.id
            WHERE ic.curso_id = $curso_id_preceptor AND ic.materia_id = $materia_id_preceptor
            ORDER BY p.apellidos, p.nombres
        ");
    }
}

// Últimas evaluaciones (sin cambios)
$ultimas_evaluaciones = $mysqli->query("
    SELECT e.fecha, e.tipo, e.instancia, e.nota, e.nota_letra, e.observaciones,
           CONCAT(p.apellidos, ', ', p.nombres) AS alumno_nombre,
           m.nombre AS materia_nombre, c.codigo AS curso_codigo
    FROM evaluacion e
    JOIN inscripcion_cursado ic ON e.inscripcion_cursado_id = ic.id
    JOIN alumno a ON ic.alumno_id = a.id
    JOIN persona p ON a.persona_id = p.id
    JOIN materia m ON ic.materia_id = m.id
    JOIN curso c ON ic.curso_id = c.id
    ORDER BY e.id DESC
    LIMIT 50
");

if (isset($_GET['feedback'])) {
    $mensaje_feedback = htmlspecialchars($_GET['feedback']);
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Evaluaciones - ISEF</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 15px;
            font-size: 0.9em;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 5px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        td input[type="text"],
        td input[type="number"] {
            width: 50px;
            padding: 3px;
            box-sizing: border-box;
        }

        td input.obs-col {
            width: 100px;
        }

        td input.obs-gen {
            width: 150px;
        }

        .form-section {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
        }

        .form-section h2 {
            margin-top: 0;
        }

        label {
            display: block;
            margin-bottom: 5px;
        }

        select,
        input[type="date"],
        button {
            padding: 8px;
            margin-bottom: 10px;
            border-radius: 3px;
            border: 1px solid #ccc;
        }

        button {
            background-color: #007bff;
            color: white;
            cursor: pointer;
        }

        button:hover {
            background-color: #0056b3;
        }

        .feedback {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 3px;
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }

        .small-text {
            font-size: 0.8em;
            color: #666;
        }
    </style>
    <script>
        function cambiarMateriaCursoProfesor() {
            const materiaId = document.getElementById('materia_id_profesor_select').value;
            const cursoId = document.getElementById('curso_id_profesor_select').value;
            if (materiaId && cursoId) {
                window.location.href = `evaluaciones.php?materia_id=${materiaId}&curso_id=${cursoId}`;
            } else if (materiaId) { // Si solo selecciona materia, buscar cursos para esa materia
                window.location.href = `evaluaciones.php?materia_id=${materiaId}`;
            }
        }

        function submitPreceptorForm() {
            const cursoId = document.getElementById('preceptor_curso_id').value;
            const materiaId = document.getElementById('preceptor_materia_id') ? document.getElementById('preceptor_materia_id').value : null;

            let url = 'evaluaciones.php?';
            if (cursoId) url += 'curso_id=' + cursoId;
            if (materiaId) url += (cursoId ? '&' : '') + 'materia_id=' + materiaId;

            window.location.href = url;
        }
    </script>
</head>

<body>
    <h1>Gestión de Evaluaciones</h1>
    <p><a href="dashboard.php">&laquo; Volver al menú</a></p>

    <?php if ($mensaje_feedback): ?>
        <div class="feedback"><?= $mensaje_feedback ?></div>
    <?php endif; ?>

    <?php if ($tipo_usuario === 'profesor'): ?>
        <div class="form-section">
            <h2>Carga de Notas por Grilla (Profesor)</h2>
            <?php if ($profesor_id): ?>
                <form method="GET" action="evaluaciones.php">
                    <label for="materia_id_profesor_select">Materia:</label>
                    <select name="materia_id" id="materia_id_profesor_select" onchange="this.form.submit()">
                        <option value="">-- Seleccione Materia --</option>
                        <?php
                        $materias_unicas_prof = [];
                        foreach ($asignaciones_prof as $asig) {
                            if (!isset($materias_unicas_prof[$asig['materia_id']])) {
                                $materias_unicas_prof[$asig['materia_id']] = $asig['materia_nombre'];
                                $selected = ($asig['materia_id'] == $materia_id_seleccionada) ? 'selected' : '';
                                echo "<option value='{$asig['materia_id']}' {$selected}>" . htmlspecialchars($asig['materia_nombre']) . "</option>";
                            }
                        }
                        ?>
                    </select>

                    <?php if ($materia_id_seleccionada): ?>
                        <label for="curso_id_profesor_select">Curso:</label>
                        <select name="curso_id" id="curso_id_profesor_select" onchange="this.form.submit()">
                            <option value="">-- Seleccione Curso --</option>
                            <?php
                            foreach ($asignaciones_prof as $asig) {
                                if ($asig['materia_id'] == $materia_id_seleccionada) {
                                    $selected = ($asig['curso_id'] == $curso_id_seleccionado) ? 'selected' : '';
                                    echo "<option value='{$asig['curso_id']}' {$selected}>" . htmlspecialchars($asig['curso_nombre']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    <?php endif; ?>
                </form>
                <hr>
                <?php if ($materia_id_seleccionada && $curso_id_seleccionado && !empty($alumnos_grilla)): ?>
                    <h3>Planilla de Evaluación: <?= htmlspecialchars($nombre_materia_seleccionada) ?> - <?= htmlspecialchars($nombre_curso_seleccionado) ?></h3>
                    <form method="POST" action="evaluaciones.php?materia_id=<?= $materia_id_seleccionada ?>&curso_id=<?= $curso_id_seleccionado ?>">
                        <input type="hidden" name="guardar_evaluaciones_profesor" value="1">
                        <input type="hidden" name="materia_id_seleccionada" value="<?= $materia_id_seleccionada ?>">
                        <input type="hidden" name="curso_id_seleccionado" value="<?= $curso_id_seleccionado ?>">

                        <label for="fecha_carga_general">Fecha General para esta Carga:</label>
                        <input type="date" name="fecha_carga_general" required value="<?= date('Y-m-d') ?>"><br><br>

                        <table>
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Apellido y Nombres</th>
                                    <?php foreach ($columnas_evaluacion_grilla as $col): ?>
                                        <th><?= htmlspecialchars($col['label']) ?></th>
                                    <?php endforeach; ?>
                                    <th>Condición</th>
                                    <th>Observaciones (por columna)</th>
                                    <th>Obs. Generales (Alumno)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alumnos_grilla as $index => $alumno): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($alumno['apellidos'] . ', ' . $alumno['nombres']) ?> <span class="small-text">(Leg: <?= htmlspecialchars($alumno['legajo']) ?>)</span></td>
                                        <?php foreach ($columnas_evaluacion_grilla as $col):
                                            $nota_actual = $evaluaciones_cargadas_grilla[$alumno['inscripcion_cursado_id']][$col['key_form']]['nota'] ?? '';
                                            $obs_actual_col = $evaluaciones_cargadas_grilla[$alumno['inscripcion_cursado_id']][$col['key_form']]['observaciones'] ?? '';
                                            // Limpiar la observación de la columna si contiene el detalle identificador para no mostrarlo en el input
                                            if ($col['db_obs_detalle'] && strpos($obs_actual_col, $col['db_obs_detalle']) === 0) {
                                                $obs_input_col = trim(substr($obs_actual_col, strlen($col['db_obs_detalle'])));
                                                if (strpos($obs_input_col, ". ") === 0) $obs_input_col = trim(substr($obs_input_col, 2));
                                            } else {
                                                $obs_input_col = $obs_actual_col;
                                            }
                                        ?>
                                            <td>
                                                <input type="number" min="1" max="10" name="notas[<?= $alumno['inscripcion_cursado_id'] ?>][<?= $col['key_form'] ?>]" value="<?= htmlspecialchars($nota_actual) ?>" title="<?= htmlspecialchars($col['label']) ?>">
                                                <input type="hidden" name="notas_originales[<?= $alumno['inscripcion_cursado_id'] ?>][<?= $col['key_form'] ?>]" value="<?= htmlspecialchars($nota_actual) ?>">
                                            </td>
                                        <?php endforeach; ?>
                                        <td><?= htmlspecialchars($alumno['condicion_cursado']) ?></td>
                                        <td>
                                            <?php foreach ($columnas_evaluacion_grilla as $col): ?>
                                            <?php endforeach; ?>
                                            (Ver observaciones al guardar)
                                        </td>
                                        <td><input type="text" class="obs-gen" name="observaciones_generales_alumno[<?= $alumno['inscripcion_cursado_id'] ?>]" placeholder="Generales"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <br>
                        <button type="submit">Guardar Planilla</button>
                    </form>
                <?php elseif ($materia_id_seleccionada && $curso_id_seleccionado): ?>
                    <p>No hay alumnos inscritos para la materia y curso seleccionados, o usted no tiene permisos sobre ellos.</p>
                <?php else: ?>
                    <p>Por favor, seleccione una materia y un curso para ver la planilla de evaluación.</p>
                <?php endif; ?>
            <?php else: ?>
                <p>Error: No se pudo identificar al profesor. Contacte al administrador.</p>
            <?php endif; ?>
        </div>
        <hr>
    <?php elseif ($tipo_usuario === 'preceptor'): ?>
        <div class="form-section">
            <h2>Registro en Lote (Preceptor)</h2>
            <form method="get" id="preceptorForm" action="evaluaciones.php">
                <label for="preceptor_curso_id">Curso:</label>
                <select name="curso_id" id="preceptor_curso_id" required onchange="document.getElementById('preceptor_materia_id').value=''; submitPreceptorForm();">
                    <option value="">-- Seleccione Curso --</option>
                    <?php while ($c = $cursos_res->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" <?= (isset($_GET['curso_id']) && $_GET['curso_id'] == $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nombre']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <?php if ($materias_res): ?>
                    <label for="preceptor_materia_id">Materia:</label>
                    <select name="materia_id" id="preceptor_materia_id" required onchange="submitPreceptorForm();">
                        <option value="">-- Seleccione Materia --</option>
                        <?php while ($m = $materias_res->fetch_assoc()): ?>
                            <option value="<?= $m['id'] ?>" <?= (isset($_GET['materia_id']) && $_GET['materia_id'] == $m['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['nombre']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                <?php endif; ?>
            </form>

            <?php if ($alumnos_res_preceptor && $alumnos_res_preceptor->num_rows > 0): ?>
                <form method="post" action="evaluaciones.php?curso_id=<?= $_GET['curso_id'] ?? '' ?>&materia_id=<?= $_GET['materia_id'] ?? '' ?>">
                    <input type="hidden" name="registro_lote" value="1">
                    <label>Fecha: <input type="date" name="fecha" required value="<?= date('Y-m-d') ?>"></label><br>
                    <label>Tipo:
                        <select name="tipo" required>
                            <option value="Parcial">Parcial</option>
                            <option value="Final">Final</option>
                            <option value="Coloquio">Coloquio</option>
                        </select>
                    </label>
                    <label>Instancia:
                        <select name="instancia" required>
                            <option value="1°Cuatrimestre">1° Cuatrimestre</option>
                            <option value="2°Cuatrimestre">2° Cuatrimestre</option>
                            <option value="Anual">Anual</option>
                        </select>
                    </label><br><br>
                    <table>
                        <thead>
                            <tr>
                                <th>Alumno</th>
                                <th>Nota (1-10)</th>
                                <th>Nota Letra</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($a = $alumnos_res_preceptor->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($a['alumno_nombre']) ?></td>
                                    <td><input type="number" name="nota[<?= $a['inscripcion_cursado_id'] ?>]" min="1" max="10"></td>
                                    <td><input type="text" name="nota_letra[<?= $a['inscripcion_cursado_id'] ?>]"></td>
                                    <td><input type="text" name="observaciones[<?= $a['inscripcion_cursado_id'] ?>]"></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table><br>
                    <button type="submit">Registrar Evaluaciones (Preceptor)</button>
                </form>
            <?php elseif (isset($_GET['curso_id']) && isset($_GET['materia_id'])): ?>
                <p>No hay alumnos para la selección actual o ya se procesó el formulario.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="form-section">
        <h2>Últimas 50 Evaluaciones Registradas</h2>
        <?php if ($ultimas_evaluaciones && $ultimas_evaluaciones->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>Materia</th>
                        <th>Curso</th>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Instancia</th>
                        <th>Nota</th>
                        <th>Letra</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($e = $ultimas_evaluaciones->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($e['alumno_nombre']) ?></td>
                            <td><?= htmlspecialchars($e['materia_nombre']) ?></td>
                            <td><?= htmlspecialchars($e['curso_codigo']) ?></td>
                            <td><?= htmlspecialchars(date("d/m/Y", strtotime($e['fecha']))) ?></td>
                            <td><?= htmlspecialchars($e['tipo']) ?></td>
                            <td><?= htmlspecialchars($e['instancia']) ?></td>
                            <td><?= htmlspecialchars($e['nota']) ?></td>
                            <td><?= htmlspecialchars($e['nota_letra']) ?></td>
                            <td><?= htmlspecialchars($e['observaciones']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay evaluaciones registradas recientemente.</p>
        <?php endif; ?>
    </div>

</body>

</html>

<?php
$mysqli->close();
?>