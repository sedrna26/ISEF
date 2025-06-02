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

setlocale(LC_TIME, 'es_AR.UTF-8', 'es_ES.UTF-8', 'es_ES', 'esp', 'spanish'); 
$meses_en_espanol = [ 
    1 => "Enero",
    2 => "Febrero",
    3 => "Marzo",
    4 => "Abril",
    5 => "Mayo",
    6 => "Junio",
    7 => "Julio",
    8 => "Agosto",
    9 => "Septiembre",
    10 => "Octubre",
    11 => "Noviembre",
    12 => "Diciembre"
];
$usuario_id = $_SESSION['usuario_id']; 
$tipo_usuario = $_SESSION['tipo']; 
$profesor_id = null; 

if ($tipo_usuario === 'profesor') {
    $profesor_res = $mysqli->query("
        SELECT p.id AS profesor_id
        FROM profesor p
        JOIN persona per ON p.persona_id = per.id
        WHERE per.usuario_id = $usuario_id
    "); 
    $profesor_data = $profesor_res->fetch_assoc(); 
    $profesor_id = $profesor_data ? $profesor_data['profesor_id'] : null; 
} else {
    $profesor_res = $mysqli->query("SELECT id FROM profesor LIMIT 1"); 
    $profesor_data = $profesor_res->fetch_assoc(); 
    $profesor_id_preceptor_default = $profesor_data ? $profesor_data['id'] : null;
}

$mensaje_feedback = ''; 
$redirect_url_params = ''; 

// --- Variables para la planilla del Preceptor ---
$curso_id_seleccionado_preceptor = isset($_REQUEST['curso_id_pre']) ? (int)$_REQUEST['curso_id_pre'] : null;
$materia_id_seleccionada_preceptor = isset($_REQUEST['materia_id_pre']) ? (int)$_REQUEST['materia_id_pre'] : null;
$mes_seleccionado_preceptor = isset($_REQUEST['mes_pre']) ? (int)$_REQUEST['mes_pre'] : (int)date('m');
$anio_seleccionado_preceptor = isset($_REQUEST['anio_pre']) ? (int)$_REQUEST['anio_pre'] : date('Y');
$mostrar_planilla_preceptor = isset($_REQUEST['mostrar_planilla_preceptor']);

// --- Variables para la planilla del Profesor ---
$materia_id_seleccionada_profesor = null;
$curso_id_seleccionado_profesor = null;
// Estas se inicializan correctamente dentro del if ($tipo_usuario === 'profesor')
$mes_seleccionado_profesor = (int)date('m'); // Valor por defecto
$anio_seleccionado_profesor = date('Y');   // Valor por defecto
$mostrar_planilla_profesor = false;

if ($tipo_usuario === 'profesor') {
    $materia_id_seleccionada_profesor = isset($_REQUEST['materia_id_prof']) ? (int)$_REQUEST['materia_id_prof'] : null;
    $curso_id_seleccionado_profesor = isset($_REQUEST['curso_id_prof']) ? (int)$_REQUEST['curso_id_prof'] : null;
    // MODIFICADO: Asegurar que (int) se aplique a date('m') también
    $mes_seleccionado_profesor = isset($_REQUEST['mes_prof']) ? (int)$_REQUEST['mes_prof'] : (int)date('m');
    $anio_seleccionado_profesor = isset($_REQUEST['anio_prof']) ? (int)$_REQUEST['anio_prof'] : date('Y');
    $mostrar_planilla_profesor = isset($_REQUEST['mostrar_planilla_profesor']);
}





if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
    // Guardar Planilla de Asistencia (Preceptor)
    if (isset($_POST['guardar_planilla_asistencia_preceptor']) && $tipo_usuario === 'preceptor') { 
        $asistencias_planilla = $_POST['asistencias_planilla_pre'] ?? []; 
        $curso_id_post = (int)$_POST['curso_id_pre_hidden']; 
        $materia_id_post = (int)$_POST['materia_id_pre_hidden']; 
        $mes_post = (int)$_POST['mes_pre_hidden']; 
        $anio_post = (int)$_POST['anio_pre_hidden']; 
        $redirect_url_params = "&curso_id_pre=$curso_id_post&materia_id_pre=$materia_id_post&mes_pre=$mes_post&anio_pre=$anio_post&mostrar_planilla_preceptor=1"; 

        $profesor_a_usar_preceptor = $profesor_id_preceptor_default; // El preceptor usa el profesor por defecto
        // Opcional: si el preceptor debe seleccionar un profesor específico para la materia,
        // ese ID vendría del formulario. Por ahora, se usa el default.

        if (!$profesor_a_usar_preceptor) { 
            $mensaje_feedback = "Error: No se pudo determinar un profesor para asignar la asistencia (Preceptor)."; 
        } else {
            foreach ($asistencias_planilla as $ic_id => $fechas) { 
                foreach ($fechas as $fecha_str => $estado) { 
                    $ic_id_int = (int)$ic_id; 
                    $stmt_check = $mysqli->prepare("SELECT id FROM asistencia WHERE inscripcion_cursado_id = ? AND fecha = ?"); 
                    $stmt_check->bind_param("is", $ic_id_int, $fecha_str); 
                    $stmt_check->execute(); 
                    $res_check = $stmt_check->get_result(); 
                    $asistencia_existente = $res_check->fetch_assoc(); 
                    $stmt_check->close(); 

                    if (!empty($estado)) { 
                        if ($asistencia_existente) { 
                            $stmt_update = $mysqli->prepare("UPDATE asistencia SET estado = ?, profesor_id = ? WHERE id = ?"); 
                            $stmt_update->bind_param("sii", $estado, $profesor_a_usar_preceptor, $asistencia_existente['id']); 
                            $stmt_update->execute(); 
                            $stmt_update->close(); 
                        } else { 
                            $stmt_insert = $mysqli->prepare("INSERT INTO asistencia (inscripcion_cursado_id, fecha, estado, profesor_id) VALUES (?, ?, ?, ?)"); 
                            $stmt_insert->bind_param("issi", $ic_id_int, $fecha_str, $estado, $profesor_a_usar_preceptor); 
                            $stmt_insert->execute(); 
                            $stmt_insert->close(); 
                        }
                    } else { 
                        if ($asistencia_existente) { 
                            $stmt_delete = $mysqli->prepare("DELETE FROM asistencia WHERE id = ?"); 
                            $stmt_delete->bind_param("i", $asistencia_existente['id']); 
                            $stmt_delete->execute(); 
                            $stmt_delete->close(); 
                        }
                    }
                }
            }
            $mensaje_feedback = "Planilla de asistencias (Preceptor) guardada correctamente."; 
        }
        header("Location: asistencias.php?feedback=" . urlencode($mensaje_feedback) . $redirect_url_params); 
        exit; // [cite: 257]
    }

    // Guardar Planilla de Asistencia (Profesor)
    if (isset($_POST['guardar_planilla_asistencia_profesor']) && $tipo_usuario === 'profesor') {
        $asistencias_planilla = $_POST['asistencias_planilla_prof'] ?? [];
        $materia_id_post = (int)$_POST['materia_id_prof_hidden'];
        $curso_id_post = (int)$_POST['curso_id_prof_hidden'];
        $mes_post = (int)$_POST['mes_prof_hidden'];
        $anio_post = (int)$_POST['anio_prof_hidden'];
        $redirect_url_params = "&materia_id_prof=$materia_id_post&curso_id_prof=$curso_id_post&mes_prof=$mes_post&anio_prof=$anio_post&mostrar_planilla_profesor=1";

        if (!$profesor_id) { // $profesor_id del profesor logueado
            $mensaje_feedback = "Error: No se pudo determinar su identificación de profesor.";
        } else {
            foreach ($asistencias_planilla as $ic_id => $fechas) {
                foreach ($fechas as $fecha_str => $estado) {
                    $ic_id_int = (int)$ic_id;
                    $stmt_check = $mysqli->prepare("SELECT id FROM asistencia WHERE inscripcion_cursado_id = ? AND fecha = ?"); 
                    $stmt_check->bind_param("is", $ic_id_int, $fecha_str); 
                    $stmt_check->execute();
                    $res_check = $stmt_check->get_result();
                    $asistencia_existente = $res_check->fetch_assoc();
                    $stmt_check->close(); 

                    if (!empty($estado)) {
                        if ($asistencia_existente) {
                            $stmt_update = $mysqli->prepare("UPDATE asistencia SET estado = ?, profesor_id = ? WHERE id = ?"); 
                            $stmt_update->bind_param("sii", $estado, $profesor_id, $asistencia_existente['id']); // Usa $profesor_id del profesor logueado 
                            $stmt_update->execute();
                            $stmt_update->close();
                        } else {
                            $stmt_insert = $mysqli->prepare("INSERT INTO asistencia (inscripcion_cursado_id, fecha, estado, profesor_id) VALUES (?, ?, ?, ?)"); 
                            $stmt_insert->bind_param("issi", $ic_id_int, $fecha_str, $estado, $profesor_id); // Usa $profesor_id del profesor logueado 
                            $stmt_insert->execute();
                            $stmt_insert->close();
                        }
                    } else {
                        if ($asistencia_existente) {
                            $stmt_delete = $mysqli->prepare("DELETE FROM asistencia WHERE id = ?"); 
                            $stmt_delete->bind_param("i", $asistencia_existente['id']); 
                            $stmt_delete->execute();
                            $stmt_delete->close();
                        }
                    }
                }
            }
            $mensaje_feedback = "Planilla de asistencias (Profesor) guardada correctamente.";
        }
        header("Location: asistencias.php?feedback=" . urlencode($mensaje_feedback) . $redirect_url_params);
        exit;
    }
}


// Cargar datos según el rol
$cursos_preceptor_res = null; 
$materias_preceptor_res = null; 
$alumnos_planilla_preceptor = []; 
$asistencias_cargadas_planilla_preceptor = []; 

$materias_profesor_res = null;
$cursos_profesor_res = null;
$alumnos_planilla_profesor = [];
$asistencias_cargadas_planilla_profesor = [];


if ($tipo_usuario === 'profesor') {
    if ($profesor_id) {
        $materias_profesor_res = $mysqli->query("
            SELECT DISTINCT m.id, m.nombre
            FROM materia m
            JOIN profesor_materia pm ON m.id = pm.materia_id
            WHERE pm.profesor_id = $profesor_id
            ORDER BY m.nombre
        ");

        if ($materia_id_seleccionada_profesor) {
            $cursos_profesor_res = $mysqli->query("
                SELECT DISTINCT c.id, CONCAT(c.codigo, ' ', c.division, ' - ', c.anio, ' (', c.ciclo_lectivo, ')') AS curso_nombre_completo
                FROM curso c
                JOIN profesor_materia pm ON c.id = pm.curso_id
                WHERE pm.profesor_id = $profesor_id AND pm.materia_id = $materia_id_seleccionada_profesor
                ORDER BY c.ciclo_lectivo DESC, c.anio, c.codigo, c.division
            ");
        }

        if ($mostrar_planilla_profesor && $materia_id_seleccionada_profesor && $curso_id_seleccionado_profesor && $mes_seleccionado_profesor && $anio_seleccionado_profesor) {
            $query_alumnos_prof = $mysqli->query("
                SELECT ic.id AS inscripcion_cursado_id, a.legajo, CONCAT(p.apellidos, ', ', p.nombres) AS alumno_nombre
                FROM inscripcion_cursado ic
                JOIN alumno a ON ic.alumno_id = a.id
                JOIN persona p ON a.persona_id = p.id
                WHERE ic.curso_id = $curso_id_seleccionado_profesor AND ic.materia_id = $materia_id_seleccionada_profesor
                ORDER BY p.apellidos, p.nombres
            "); // [cite: 264]
            if ($query_alumnos_prof) {
                while ($alumno_prof = $query_alumnos_prof->fetch_assoc()) { 
                    $alumnos_planilla_profesor[] = $alumno_prof;
                    $fecha_inicio_mes_prof = $anio_seleccionado_profesor . "-" . str_pad($mes_seleccionado_profesor, 2, '0', STR_PAD_LEFT) . "-01"; 
                    $fecha_fin_mes_prof = date("Y-m-t", strtotime($fecha_inicio_mes_prof)); 

                    $stmt_asist_prof = $mysqli->prepare("SELECT fecha, estado FROM asistencia WHERE inscripcion_cursado_id = ? AND fecha BETWEEN ? AND ?"); 
                    $stmt_asist_prof->bind_param("iss", $alumno_prof['inscripcion_cursado_id'], $fecha_inicio_mes_prof, $fecha_fin_mes_prof); 
                    $stmt_asist_prof->execute(); 
                    $res_asist_prof = $stmt_asist_prof->get_result(); 
                    while ($asist_prof = $res_asist_prof->fetch_assoc()) { 
                        $asistencias_cargadas_planilla_profesor[$alumno_prof['inscripcion_cursado_id']][$asist_prof['fecha']] = $asist_prof['estado']; 
                    }
                    $stmt_asist_prof->close(); 
                }
            }
        }
    }
} else { // Lógica para Preceptor
    $cursos_preceptor_res = $mysqli->query("
        SELECT c.id, CONCAT(c.codigo, ' ', c.division, ' - ', c.ciclo_lectivo) AS curso_nombre
        FROM curso c
        ORDER BY c.ciclo_lectivo DESC, c.codigo, c.division
    "); // [cite: 262]
    if ($curso_id_seleccionado_preceptor) { 
        $materias_preceptor_res = $mysqli->query("
            SELECT DISTINCT m.id, m.nombre
            FROM inscripcion_cursado ic
            JOIN materia m ON ic.materia_id = m.id
            WHERE ic.curso_id = $curso_id_seleccionado_preceptor
            ORDER BY m.nombre
        "); // [cite: 263]
    }

    if ($mostrar_planilla_preceptor && $curso_id_seleccionado_preceptor && $materia_id_seleccionada_preceptor && $mes_seleccionado_preceptor && $anio_seleccionado_preceptor) { 
        $query_alumnos = $mysqli->query("
            SELECT ic.id AS inscripcion_cursado_id, a.legajo, CONCAT(p.apellidos, ', ', p.nombres) AS alumno_nombre
            FROM inscripcion_cursado ic
            JOIN alumno a ON ic.alumno_id = a.id
            JOIN persona p ON a.persona_id = p.id
            WHERE ic.curso_id = $curso_id_seleccionado_preceptor AND ic.materia_id = $materia_id_seleccionada_preceptor
            ORDER BY p.apellidos, p.nombres
        "); // [c
        if ($query_alumnos) {
            while ($alumno = $query_alumnos->fetch_assoc()) { 
                $alumnos_planilla_preceptor[] = $alumno; 
                $fecha_inicio_mes = $anio_seleccionado_preceptor . "-" . str_pad($mes_seleccionado_preceptor, 2, '0', STR_PAD_LEFT) . "-01"; // [c
                $fecha_fin_mes = date("Y-m-t", strtotime($fecha_inicio_mes)); 
                $stmt_asist = $mysqli->prepare("SELECT fecha, estado FROM asistencia WHERE inscripcion_cursado_id = ? AND fecha BETWEEN ? AND ?"); 
                $stmt_asist->bind_param("iss", $alumno['inscripcion_cursado_id'], $fecha_inicio_mes, $fecha_fin_mes); 
                $stmt_asist->execute(); 
                $res_asist = $stmt_asist->get_result(); 
                while ($asist = $res_asist->fetch_assoc()) { 
                    $asistencias_cargadas_planilla_preceptor[$alumno['inscripcion_cursado_id']][$asist['fecha']] = $asist['estado']; 
                }
                $stmt_asist->close(); 
            }
        }
    }
}

// Obtener las últimas asistencias registradas con paginación
$registros_por_pagina = 10; 
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1; 
$offset = ($pagina_actual - 1) * $registros_por_pagina; 

$query_total_registros_base = "SELECT COUNT(DISTINCT a.id) AS total 
                               FROM asistencia a 
                               JOIN inscripcion_cursado ic ON a.inscripcion_cursado_id = ic.id"; 
$query_asistencias_listado_base = "
    SELECT a.id, a.fecha, a.estado, 
           CONCAT(p_alumno.apellidos, ', ', p_alumno.nombres) as alumno_nombre,
           m.nombre as materia_nombre, 
           c.codigo as curso_codigo, c.division as curso_division, c.anio as curso_anio_desc,
           CONCAT(p_prof.apellidos, ', ', p_prof.nombres) as profesor_nombre
    FROM asistencia a
    JOIN inscripcion_cursado ic ON a.inscripcion_cursado_id = ic.id
    JOIN alumno al ON ic.alumno_id = al.id
    JOIN persona p_alumno ON al.persona_id = p_alumno.id
    JOIN materia m ON ic.materia_id = m.id
    JOIN curso c ON ic.curso_id = c.id
    JOIN profesor prof ON a.profesor_id = prof.id
    JOIN persona p_prof ON prof.persona_id = p_prof.id
    WHERE 1=1 "; // [cite: 280, 281]

$filtros_listado = "";
$params_paginacion = '';

if ($tipo_usuario === 'profesor') {
    if ($profesor_id) {
        $filtros_listado .= " AND a.profesor_id = $profesor_id "; 
    }
    // Mantener los filtros de la planilla del profesor en la paginación del listado si están activos
    if ($materia_id_seleccionada_profesor) $params_paginacion .= "&materia_id_prof=$materia_id_seleccionada_profesor";
    if ($curso_id_seleccionado_profesor) $params_paginacion .= "&curso_id_prof=$curso_id_seleccionado_profesor";
    if ($mes_seleccionado_profesor) $params_paginacion .= "&mes_prof=$mes_seleccionado_profesor";
    if ($anio_seleccionado_profesor) $params_paginacion .= "&anio_prof=$anio_seleccionado_profesor";
    if ($mostrar_planilla_profesor) $params_paginacion .= "&mostrar_planilla_profesor=1";
} else if ($tipo_usuario === 'preceptor') {
    if ($curso_id_seleccionado_preceptor) {
        $filtros_listado .= " AND ic.curso_id = $curso_id_seleccionado_preceptor"; 
        $params_paginacion .= "&curso_id_pre=$curso_id_seleccionado_preceptor"; 
    }
    if ($materia_id_seleccionada_preceptor) {
        $filtros_listado .= " AND ic.materia_id = $materia_id_seleccionada_preceptor"; 
        $params_paginacion .= "&materia_id_pre=$materia_id_seleccionada_preceptor"; 
    }
    if ($mes_seleccionado_preceptor) $params_paginacion .= "&mes_pre=$mes_seleccionado_preceptor"; 
    if ($anio_seleccionado_preceptor) $params_paginacion .= "&anio_pre=$anio_seleccionado_preceptor"; 
    if ($mostrar_planilla_preceptor) $params_paginacion .= "&mostrar_planilla_preceptor=1"; 
}

$query_total_registros = $query_total_registros_base . $filtros_listado;
$total_registros_res = $mysqli->query($query_total_registros);
$total_registros = $total_registros_res ? $total_registros_res->fetch_assoc()['total'] : 0; 
$total_paginas = ceil($total_registros / $registros_por_pagina); 

$query_asistencias_listado = $query_asistencias_listado_base . $filtros_listado . " ORDER BY a.fecha DESC, alumno_nombre ASC LIMIT $registros_por_pagina OFFSET $offset"; 
$asistencias_listado = $mysqli->query($query_asistencias_listado); 

if (isset($_GET['feedback'])) { 
    $mensaje_feedback = htmlspecialchars($_GET['feedback']); 
}

// Obtener todas las materias y cursos para los selectores de PDF
$materias_pdf_query = "SELECT id, nombre FROM materia ORDER BY nombre"; 
$materias_pdf_result = $mysqli->query($materias_pdf_query); 
$materias_para_pdf = []; 
while ($row = $materias_pdf_result->fetch_assoc()) { 
    $materias_para_pdf[] = $row; 
}

$cursos_pdf_query = "SELECT id, CONCAT(anio, '° ', division, ' (', ciclo_lectivo, ')') AS curso_completo FROM curso ORDER BY ciclo_lectivo DESC, anio, division"; 
$cursos_pdf_result = $mysqli->query($cursos_pdf_query); 
$cursos_para_pdf = []; 
while ($row = $cursos_pdf_result->fetch_assoc()) { 
    $cursos_para_pdf[] = $row; 
}

// Para los títulos de las planillas
$nombre_materia_planilla_prof = 'Materia';
$nombre_curso_planilla_prof = 'Curso';
if ($materia_id_seleccionada_profesor && $materias_profesor_res) {
    // Resetear el puntero si ya se usó $materias_profesor_res para el select
    // O, mejor, buscar en una copia o hacer una query específica
    $stmt_m = $mysqli->prepare("SELECT nombre FROM materia WHERE id = ?");
    $stmt_m->bind_param("i", $materia_id_seleccionada_profesor);
    $stmt_m->execute();
    $res_m = $stmt_m->get_result();
    if ($r = $res_m->fetch_assoc()) $nombre_materia_planilla_prof = $r['nombre'];
    $stmt_m->close();
}
if ($curso_id_seleccionado_profesor && $cursos_profesor_res) {
    $stmt_c = $mysqli->prepare("SELECT CONCAT(codigo, ' ', division, ' - ', anio, ' (', ciclo_lectivo, ')') AS curso_nombre_completo FROM curso WHERE id = ?");
    $stmt_c->bind_param("i", $curso_id_seleccionado_profesor);
    $stmt_c->execute();
    $res_c = $stmt_c->get_result();
    if ($r = $res_c->fetch_assoc()) $nombre_curso_planilla_prof = $r['curso_nombre_completo'];
    $stmt_c->close();
}

$nombre_materia_planilla_pre = 'Materia';
$nombre_curso_planilla_pre = 'Curso';
if ($materia_id_seleccionada_preceptor && $materias_preceptor_res) {
    $stmt_m = $mysqli->prepare("SELECT nombre FROM materia WHERE id = ?");
    $stmt_m->bind_param("i", $materia_id_seleccionada_preceptor);
    $stmt_m->execute();
    $res_m = $stmt_m->get_result();
    if ($r = $res_m->fetch_assoc()) $nombre_materia_planilla_pre = $r['nombre'];
    $stmt_m->close();
}
if ($curso_id_seleccionado_preceptor && $cursos_preceptor_res) {
    $stmt_c = $mysqli->prepare("SELECT CONCAT(codigo, ' ', division, ' - ', ciclo_lectivo) AS curso_nombre FROM curso WHERE id = ?");
    $stmt_c->bind_param("i", $curso_id_seleccionado_preceptor);
    $stmt_c->execute();
    $res_c = $stmt_c->get_result();
    if ($r = $res_c->fetch_assoc()) $nombre_curso_planilla_pre = $r['curso_nombre'];
    $stmt_c->close();
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Asistencias - ISEF</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            font-size: 0.9rem;
        }

        
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 15px;
            font-size: 0.85em;
        }

        
        th,
        td {
            border: 1px solid #ddd;
            padding: 5px;
            text-align: left;
        }

        
        th {
            background-color: #f2f2f2;
            text-align: center;
            vertical-align: middle;
        }

        
        td.day-cell {
            min-width: 45px;
            text-align: center;
            padding: 2px;
        }

        
        td.day-cell select {
            padding: 3px;
            font-size: 0.9em;
            width: 100%;
            max-width: 50px;
            border-radius: 3px;
            border: 1px solid #ccc;
        }

        
        .form-section {
            margin-bottom: 25px;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background-color: #f9f9f9;
        }

        
        .form-section h2 {
            margin-top: 0;
            font-size: 1.5em;
        }

        
        label {
            display: inline-block;
            margin-bottom: 5px;
            margin-right: 8px;
            font-weight: 500;
        }

        
        select,
        input[type="date"],
        input[type="number"],
        .btn {
            padding: 0.375rem 0.75rem;
            margin-bottom: 10px;
            border-radius: 0.25rem;
            border: 1px solid #ced4da;
            font-size: 0.9rem;
        }

        
        .btn-primary {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }

        
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        
        .feedback {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 3px;
            color: #0f5132;
            background-color: #d1e7dd;
            border: 1px solid #badbcc;
        }

        
        .error {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 3px;
            color: #842029;
            background-color: #f8d7da;
            border: 1px solid #f5c2c7;
        }

        
        .paginacion {
            margin: 20px 0;
            text-align: center;
        }

        
        .paginacion a,
        .paginacion span {
            color: #0d6efd;
            padding: 0.375rem 0.75rem;
            text-decoration: none;
            border: 1px solid #dee2e6;
            margin: 0 2px;
            border-radius: 0.25rem;
        }

        
        .paginacion a:hover {
            background-color: #e9ecef;
        }

        
        .paginacion span.current {
            background-color: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }

        
        .sticky-header th {
            position: sticky;
            top: 0;
            z-index: 2;
            background-color: #e9ecef;
        }

        
        .table-responsive {
            overflow-x: auto;
        }

        
        .bg-light-weekend {
            background-color: #f8f9fa !important;
        }

        
    </style>
</head>

<body>
    <div class="container-fluid">
        <h1>Gestión de Asistencias</h1>
        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary mb-3">&laquo; Volver al menú</a> <?php if ($mensaje_feedback): ?>
            <div class="<?= strpos(strtolower($mensaje_feedback), 'error') !== false || strpos(strtolower($mensaje_feedback), 'advertencia') !== false ? 'error' : 'feedback' ?>"><?= $mensaje_feedback ?></div> <?php endif; ?>
        <?php if ($tipo_usuario === 'preceptor' && !$profesor_id_preceptor_default): ?> <div class="error">Advertencia: No se pudo determinar un profesor por defecto para el Preceptor. Las asistencias podrían no guardarse correctamente si no hay profesores en el sistema.</div> <?php endif; ?>
        <?php if ($tipo_usuario === 'profesor' && !$profesor_id): ?>
            <div class="error">Advertencia: Su ID de profesor no pudo ser determinado. No podrá cargar asistencias.</div>
        <?php endif; ?>

        <?php if ($tipo_usuario === 'profesor' && $profesor_id): ?>
            <div class="form-section">
                <h2>Planilla de Asistencia (Profesor)</h2>
                <form method="GET" action="asistencias.php" class="row g-3 align-items-end">
                    <input type="hidden" name="mostrar_planilla_profesor" value="1">
                    <div class="col-md-3">
                        <label for="materia_id_prof_select" class="form-label">Materia:</label>
                        <select name="materia_id_prof" id="materia_id_prof_select" class="form-select form-select-sm" required onchange="this.form.submit()">
                            <option value="">-- Seleccione Materia --</option>
                            <?php if ($materias_profesor_res) : ?>
                                <?php while ($m = $materias_profesor_res->fetch_assoc()): ?>
                                    <option value="<?= $m['id'] ?>" <?= ($materia_id_seleccionada_profesor == $m['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($m['nombre']) ?>
                                    </option>
                                <?php endwhile;
                                $materias_profesor_res->data_seek(0); // Reset pointer if needed elsewhere 
                                ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <?php if ($materia_id_seleccionada_profesor): ?>
                        <div class="col-md-3">
                            <label for="curso_id_prof_select" class="form-label">Curso:</label>
                            <select name="curso_id_prof" id="curso_id_prof_select" class="form-select form-select-sm" required onchange="this.form.submit()">
                                <option value="">-- Seleccione Curso --</option>
                                <?php if ($cursos_profesor_res) : ?>
                                    <?php while ($c = $cursos_profesor_res->fetch_assoc()): ?>
                                        <option value="<?= $c['id'] ?>" <?= ($curso_id_seleccionado_profesor == $c['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['curso_nombre_completo']) ?>
                                        </option>
                                    <?php endwhile;
                                    $cursos_profesor_res->data_seek(0); ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($materia_id_seleccionada_profesor && $curso_id_seleccionado_profesor): ?>
                        <div class="col-md-2">
                            <label for="mes_prof_select" class="form-label">Mes:</label>
                            <select name="mes_prof" id="mes_prof_select" class="form-select form-select-sm" required onchange="this.form.submit()">
                                <?php for ($m_idx = 1; $m_idx <= 12; $m_idx++): ?>
                                    <option value="<?= $m_idx ?>" <?= ($mes_seleccionado_profesor == $m_idx) ? 'selected' : '' ?>>
                                        <?= $meses_en_espanol[$m_idx] ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="anio_prof_select" class="form-label">Año:</label>
                            <select name="anio_prof" id="anio_prof_select" class="form-select form-select-sm" required onchange="this.form.submit()">
                                <?php for ($a = date('Y'); $a >= date('Y') - 2; $a--): ?>
                                    <option value="<?= $a ?>" <?= ($anio_seleccionado_profesor == $a) ? 'selected' : '' ?>><?= $a ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-sm btn-info w-100">Mostrar Planilla</button>
                        </div>
                    <?php endif; ?>
                </form>

                <?php if ($mostrar_planilla_profesor && $materia_id_seleccionada_profesor && $curso_id_seleccionado_profesor && $mes_seleccionado_profesor && $anio_seleccionado_profesor): ?>
                    <?php if (!empty($alumnos_planilla_profesor)):
                        $dias_en_mes_prof = cal_days_in_month(CAL_GREGORIAN, $mes_seleccionado_profesor, $anio_seleccionado_profesor);
                    ?>
                        <h3 class="mt-4">Planilla Profesor: <?= htmlspecialchars($nombre_materia_planilla_prof) ?> - <?= htmlspecialchars($nombre_curso_planilla_prof) ?> - Mes: <?= $meses_en_espanol[$mes_seleccionado_profesor] ?> <?= $anio_seleccionado_profesor ?></h3>
                        <form method="POST" action="asistencias.php">
                            <input type="hidden" name="guardar_planilla_asistencia_profesor" value="1">
                            <input type="hidden" name="materia_id_prof_hidden" value="<?= $materia_id_seleccionada_profesor ?>">
                            <input type="hidden" name="curso_id_prof_hidden" value="<?= $curso_id_seleccionado_profesor ?>">
                            <input type="hidden" name="mes_prof_hidden" value="<?= $mes_seleccionado_profesor ?>">
                            <input type="hidden" name="anio_prof_hidden" value="<?= $anio_seleccionado_profesor ?>">
                            <div class="table-responsive mt-3">
                                <table class="table table-bordered table-sm table-hover">
                                    <thead class="sticky-header">
                                        <tr>
                                            <th style="width: 40px;">N°</th>
                                            <th style="width: 80px;">Legajo</th>
                                            <th>Alumno</th>
                                            <?php for ($dia = 1; $dia <= $dias_en_mes_prof; $dia++):
                                                $fecha_actual_dia = new DateTime("$anio_seleccionado_profesor-$mes_seleccionado_profesor-$dia"); 
                                                $nombre_dia_semana = strftime('%a', $fecha_actual_dia->getTimestamp()); 
                                                $es_finde = (in_array($nombre_dia_semana, ['Sat', 'Sun', 'Sáb', 'Dom'])); 
                                            ?>
                                                <th class="day-cell <?= $es_finde ? 'bg-light-weekend' : '' ?>" title="<?= ucfirst($nombre_dia_semana) ?>"> <?= $dia ?>
                                                </th>
                                            <?php endfor; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($alumnos_planilla_profesor as $index => $alumno): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td><?= htmlspecialchars($alumno['legajo']) ?></td>
                                                <td><?= htmlspecialchars($alumno['alumno_nombre']) ?></td> <?php for ($dia = 1; $dia <= $dias_en_mes_prof; $dia++):
                                                                                                                $fecha_completa = $anio_seleccionado_profesor . '-' . str_pad($mes_seleccionado_profesor, 2, '0', STR_PAD_LEFT) . '-' . str_pad($dia, 2, '0', STR_PAD_LEFT);
                                                                                                                $estado_actual = $asistencias_cargadas_planilla_profesor[$alumno['inscripcion_cursado_id']][$fecha_completa] ?? '';
                                                                                                            ?>
                                                    <td class="day-cell">
                                                        <select name="asistencias_planilla_prof[<?= $alumno['inscripcion_cursado_id'] ?>][<?= $fecha_completa ?>]" class="form-select form-select-sm p-1">
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
                            <button type="submit" class="btn btn-success mt-3">Guardar Asistencias (Profesor)</button>
                        </form>
                    <?php else: ?>
                        <p class="mt-3">No hay alumnos inscritos para la materia y curso seleccionados.</p> <?php endif; ?>
                <?php elseif ($mostrar_planilla_profesor): ?>
                    <p class="mt-3">Por favor, seleccione Materia, Curso, Mes y Año y presione "Mostrar Planilla".</p>
                <?php endif; ?>
            </div>

        <?php elseif ($tipo_usuario === 'preceptor'): ?>
            <div class="form-section">
                <h2>Planilla de Asistencia (Preceptor)</h2>
                <form method="GET" action="asistencias.php" class="row g-3 align-items-end"> <input type="hidden" name="mostrar_planilla_preceptor" value="1">
                    <div class="col-md-3">
                        <label for="curso_id_pre_select" class="form-label">Curso:</label>
                        <select name="curso_id_pre" id="curso_id_pre_select" class="form-select form-select-sm" required onchange="this.form.submit()">
                            <option value="">-- Seleccione Curso --</option> <?php if ($cursos_preceptor_res) : ?> <?php while ($c = $cursos_preceptor_res->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($curso_id_seleccionado_preceptor == $c['id']) ? 'selected' : '' ?>> <?= htmlspecialchars($c['curso_nombre']) ?>
                                    </option>
                                <?php endwhile;
                                                                                                                    $cursos_preceptor_res->data_seek(0); ?> <?php endif; ?>
                        </select>
                    </div>

                    <?php if ($curso_id_seleccionado_preceptor): ?> <div class="col-md-3">
                            <label for="materia_id_pre_select" class="form-label">Materia:</label>
                            <select name="materia_id_pre" id="materia_id_pre_select" class="form-select form-select-sm" required onchange="this.form.submit()">
                                <option value="">-- Seleccione Materia --</option> <?php if ($materias_preceptor_res) : ?> <?php while ($m = $materias_preceptor_res->fetch_assoc()): ?> <option value="<?= $m['id'] ?>" <?= ($materia_id_seleccionada_preceptor == $m['id']) ? 'selected' : '' ?>> <?= htmlspecialchars($m['nombre']) ?>
                                        </option>
                                    <?php endwhile;
                                                                                                                            $materias_preceptor_res->data_seek(0); ?> <?php endif; ?>
                            </select>
                        </div>
                    <?php endif; ?> <?php if ($curso_id_seleccionado_preceptor && $materia_id_seleccionada_preceptor): ?> <div class="col-md-2">
                            <label for="mes_pre_select" class="form-label">Mes:</label>
                            <select name="mes_pre" id="mes_pre_select" class="form-select form-select-sm" required onchange="this.form.submit()"> <?php for ($m_idx = 1; $m_idx <= 12; $m_idx++): ?> <option value="<?= $m_idx ?>" <?= ($mes_seleccionado_preceptor == $m_idx) ? 'selected' : '' ?>> <?= $meses_en_espanol[$m_idx] ?>
                                    </option>
                                <?php endfor; ?> </select>
                        </div>
                        <div class="col-md-2">
                            <label for="anio_pre_select" class="form-label">Año:</label>
                            <select name="anio_pre" id="anio_pre_select" class="form-select form-select-sm" required onchange="this.form.submit()"> <?php for ($a = date('Y'); $a >= date('Y') - 2; $a--): ?> <option value="<?= $a ?>" <?= ($anio_seleccionado_preceptor == $a) ? 'selected' : '' ?>><?= $a ?></option> <?php endfor; ?> </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-sm btn-info w-100">Mostrar Planilla</button>
                        </div>
                    <?php endif; ?>
                </form>

                <?php if ($mostrar_planilla_preceptor && $curso_id_seleccionado_preceptor && $materia_id_seleccionada_preceptor && $mes_seleccionado_preceptor && $anio_seleccionado_preceptor): ?> <?php if (!empty($alumnos_planilla_preceptor)): 
                                                                                                                                                                                                        $dias_en_mes_pre = cal_days_in_month(CAL_GREGORIAN, $mes_seleccionado_preceptor, $anio_seleccionado_preceptor); 
                                                                                                                                                                                                    ?>
                        <h3 class="mt-4">Planilla Preceptor: <?= htmlspecialchars($nombre_materia_planilla_pre) ?> - <?= htmlspecialchars($nombre_curso_planilla_pre) ?> - Mes: <?= $meses_en_espanol[$mes_seleccionado_preceptor] ?> <?= $anio_seleccionado_preceptor ?></h3>
                        <form method="POST" action="asistencias.php">
                            <input type="hidden" name="guardar_planilla_asistencia_preceptor" value="1">
                            <input type="hidden" name="curso_id_pre_hidden" value="<?= $curso_id_seleccionado_preceptor ?>"> <input type="hidden" name="materia_id_pre_hidden" value="<?= $materia_id_seleccionada_preceptor ?>"> <input type="hidden" name="mes_pre_hidden" value="<?= $mes_seleccionado_preceptor ?>"> <input type="hidden" name="anio_pre_hidden" value="<?= $anio_seleccionado_preceptor ?>">
                            <div class="table-responsive mt-3">
                                <table class="table table-bordered table-sm table-hover">
                                    <thead class="sticky-header">
                                        <tr>
                                            <th style="width: 40px;">N°</th>
                                            <th style="width: 80px;">Legajo</th>
                                            <th>Alumno</th> <?php for ($dia = 1; $dia <= $dias_en_mes_pre; $dia++): 
                                                                                                                                                                                                            $fecha_actual_dia = new DateTime("$anio_seleccionado_preceptor-$mes_seleccionado_preceptor-$dia"); 
                                                                                                                                                                                                            $nombre_dia_semana = strftime('%a', $fecha_actual_dia->getTimestamp()); 
                                                                                                                                                                                                            $es_finde = (in_array($nombre_dia_semana, ['Sat', 'Sun', 'Sáb', 'Dom'])); 
                                                            ?>
                                                <th class="day-cell <?= $es_finde ? 'bg-light-weekend' : '' ?>" title="<?= ucfirst($nombre_dia_semana) ?>"> <?= $dia ?>
                                                </th>
                                            <?php endfor; ?>
                                        </tr>
                                    </thead>
                                    <tbody> <?php foreach ($alumnos_planilla_preceptor as $index => $alumno): ?> <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td><?= htmlspecialchars($alumno['legajo']) ?></td>
                                                <td><?= htmlspecialchars($alumno['alumno_nombre']) ?></td> <?php for ($dia = 1; $dia <= $dias_en_mes_pre; $dia++): 
                                                                                                                                                                                                                $fecha_completa = $anio_seleccionado_preceptor . '-' . str_pad($mes_seleccionado_preceptor, 2, '0', STR_PAD_LEFT) . '-' . str_pad($dia, 2, '0', STR_PAD_LEFT); 
                                                                                                                                                                                                                $estado_actual = $asistencias_cargadas_planilla_preceptor[$alumno['inscripcion_cursado_id']][$fecha_completa] ?? ''; 
                                                                                                            ?>
                                                    <td class="day-cell"> <select name="asistencias_planilla_pre[<?= $alumno['inscripcion_cursado_id'] ?>][<?= $fecha_completa ?>]" class="form-select form-select-sm p-1">
                                                            <option value="" <?= ($estado_actual == '') ? 'selected' : '' ?>>-</option>
                                                            <option value="Presente" <?= ($estado_actual == 'Presente') ? 'selected' : '' ?>>P</option>
                                                            <option value="Ausente" <?= ($estado_actual == 'Ausente') ? 'selected' : '' ?>>A</option>
                                                            <option value="Justificado" <?= ($estado_actual == 'Justificado') ? 'selected' : '' ?>>J</option>
                                                        </select>
                                                    </td>
                                                <?php endfor; ?>
                                            </tr>
                                        <?php endforeach; ?> </tbody>
                                </table>
                            </div>
                            <button type="submit" class="btn btn-success mt-3">Guardar Asistencias (Preceptor)</button>
                        </form>
                    <?php else: ?>
                        <p class="mt-3">No hay alumnos inscritos para el curso y materia seleccionados, o no se pudo generar la planilla.</p> <?php endif; ?> <?php elseif ($mostrar_planilla_preceptor): ?> <p class="mt-3">Por favor, seleccione Curso, Materia, Mes y Año y presione "Mostrar Planilla".</p> <?php endif; ?>
            </div>
        <?php endif; ?> <div class="card mt-4">
            <div class="card-header">
                <h5>Generar Informes de Asistencias (PDF)</h5>
            </div>
            <div class="card-body">
                <form id="formGenerarPDF" action="generar_pdf_asistencias.php" method="POST" target="_blank" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="materia_pdf" class="form-label">Espacio Curricular:</label> <select class="form-select form-select-sm" id="materia_pdf" name="materia_id" required>
                            <option value="">-- Seleccione una materia --</option> <?php foreach ($materias_para_pdf as $materia): ?> <option value="<?= $materia['id'] ?>"><?= htmlspecialchars($materia['nombre']) ?></option> <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="curso_pdf" class="form-label">Curso:</label> <select class="form-select form-select-sm" id="curso_pdf" name="curso_id" required>
                            <option value="">-- Seleccione un curso --</option> <?php foreach ($cursos_para_pdf as $curso): ?> <option value="<?= $curso['id'] ?>"><?= htmlspecialchars($curso['curso_completo']) ?></option> <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="hidden" id="profesor_id_pdf" name="profesor_id">
                    <div class="col-md-4">
                        <p class="mt-2 mb-0" id="profesor_nombre_display" style="font-size:0.85rem;">Profesor/a: No seleccionado</p>
                    </div>

                    <div class="col-12 mt-3"> <button type="submit" class="btn btn-sm btn-primary me-2" name="periodo" value="abril_julio"> Generar PDF (Abril-Julio)
                        </button>
                        <button type="submit" class="btn btn-sm btn-primary" name="periodo" value="septiembre_diciembre"> Generar PDF (Septiembre-Diciembre)
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="form-section mt-4">
            <h2>Últimas Asistencias Registradas <?= ($tipo_usuario === 'preceptor' && ($curso_id_seleccionado_preceptor || $materia_id_seleccionada_preceptor)) ? '(Filtrado por selección actual)' : (($tipo_usuario === 'profesor') ? '(Sus registros)' : '') ?></h2> <?php if ($asistencias_listado && $asistencias_listado->num_rows > 0): ?> <div class="table-responsive">
                    <table class="table table-striped table-sm table-hover">
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
                        <tbody> <?php while ($a = $asistencias_listado->fetch_assoc()): ?> <tr>
                                    <td><?= htmlspecialchars($a['alumno_nombre']) ?></td>
                                    <td><?= htmlspecialchars($a['materia_nombre']) ?></td>
                                    <td><?= htmlspecialchars($a['curso_codigo'] . ' ' . $a['curso_division'] . ' - ' . $a['curso_anio_desc']) ?></td>
                                    <td><?= htmlspecialchars(date("d/m/Y", strtotime($a['fecha']))) ?></td>
                                    <td><?= htmlspecialchars($a['estado']) ?></td>
                                    <td><?= htmlspecialchars($a['profesor_nombre']) ?></td>
                                </tr>
                            <?php endwhile; ?> </tbody>
                    </table>
                </div>
                <div class="paginacion"> <?php if ($total_paginas > 1): ?> <?php if ($pagina_actual > 1): ?> <a href="?pagina=1<?= $params_paginacion ?>">&laquo; Primera</a> <a href="?pagina=<?= $pagina_actual - 1 ?><?= $params_paginacion ?>">&lsaquo; Anterior</a> <?php endif; ?> <span class="current">Página <?= $pagina_actual ?> de <?= $total_paginas ?></span> <?php if ($pagina_actual < $total_paginas): ?> <a href="?pagina=<?= $pagina_actual + 1 ?><?= $params_paginacion ?>">Siguiente &rsaquo;</a> <a href="?pagina=<?= $total_paginas ?><?= $params_paginacion ?>">Última &raquo;</a> <?php endif; ?> <?php endif; ?> </div> <?php else: ?>
                <p>No hay asistencias registradas recientemente o para el filtro actual.</p> <?php endif; ?>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const materiaSelect = document.getElementById('materia_pdf'); 
            const profesorIdInput = document.getElementById('profesor_id_pdf'); 
            const profesorNombreDisplay = document.getElementById('profesor_nombre_display'); 
            // const cursoSelect = document.getElementById('curso_pdf'); // No se usa directamente aquí 

            function getProfesorForMateria(materiaId) { 
                if (!materiaId) { 
                    profesorIdInput.value = ''; 
                    profesorNombreDisplay.textContent = 'Profesor/a: No seleccionado'; 
                    return; 
                }

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

            if (materiaSelect) { 
                materiaSelect.addEventListener('change', function() {
                    getProfesorForMateria(this.value); 
                });
                if (materiaSelect.value) {
                    getProfesorForMateria(materiaSelect.value);
                }
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php
$mysqli->close(); 
?>