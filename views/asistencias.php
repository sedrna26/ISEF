<?php
// asistencias.php - Gestión de asistencias con el nuevo diseño del dashboard.
session_start();
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['tipo'], ['profesor', 'preceptor'])) {
    header("Location: ../index.php");
    exit;
}

// 1. Usar la conexión centralizada a la base de datos
require_once '../config/db.php';

// 2. Obtener el nombre del usuario para el sidebar (como en profesores.php)
$stmt_user_sidebar = $mysqli->prepare("
    SELECT CONCAT(p.apellidos ,' ', p.nombres) as nombre_completo
    FROM persona p
    JOIN usuario u ON p.usuario_id = u.id
    WHERE u.id = ?
");
if ($stmt_user_sidebar) {
    $stmt_user_sidebar->bind_param("i", $_SESSION['usuario_id']);
    $stmt_user_sidebar->execute();
    $result_user_sidebar = $stmt_user_sidebar->get_result();
    $usuario_sidebar = $result_user_sidebar->fetch_assoc();
    $stmt_user_sidebar->close();
} else {
    // Valor por defecto si la consulta falla
    $usuario_sidebar = ['nombre_completo' => 'Usuario'];
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
} else { // preceptor
    $profesor_res = $mysqli->query("SELECT id FROM profesor LIMIT 1");
    $profesor_data = $profesor_res->fetch_assoc();
    $profesor_id_preceptor_default = $profesor_data ? $profesor_data['id'] : null;
}

$mensaje_feedback = '';
if (isset($_GET['feedback'])) {
    $mensaje_feedback = htmlspecialchars($_GET['feedback']);
}
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
$mes_seleccionado_profesor = (int)date('m');
$anio_seleccionado_profesor = date('Y');
$mostrar_planilla_profesor = false;

if ($tipo_usuario === 'profesor') {
    $materia_id_seleccionada_profesor = isset($_REQUEST['materia_id_prof']) ? (int)$_REQUEST['materia_id_prof'] : null;
    $curso_id_seleccionado_profesor = isset($_REQUEST['curso_id_prof']) ? (int)$_REQUEST['curso_id_prof'] : null;
    $mes_seleccionado_profesor = isset($_REQUEST['mes_prof']) ? (int)$_REQUEST['mes_prof'] : (int)date('m');
    $anio_seleccionado_profesor = isset($_REQUEST['anio_prof']) ? (int)$_REQUEST['anio_prof'] : date('Y');
    $mostrar_planilla_profesor = isset($_REQUEST['mostrar_planilla_profesor']);
}

// --- LÓGICA POST PARA GUARDAR ASISTENCIAS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // (La lógica POST permanece idéntica a tu archivo original)
    // Guardar Planilla de Asistencia (Preceptor)
    if (isset($_POST['guardar_planilla_asistencia_preceptor']) && $tipo_usuario === 'preceptor') {
        $asistencias_planilla = $_POST['asistencias_planilla_pre'] ?? [];
        $curso_id_post = (int)$_POST['curso_id_pre_hidden'];
        $materia_id_post = (int)$_POST['materia_id_pre_hidden'];
        $mes_post = (int)$_POST['mes_pre_hidden'];
        $anio_post = (int)$_POST['anio_pre_hidden'];
        $redirect_url_params = "&curso_id_pre=$curso_id_post&materia_id_pre=$materia_id_post&mes_pre=$mes_post&anio_pre=$anio_post&mostrar_planilla_preceptor=1";

        $profesor_a_usar_preceptor = $profesor_id_preceptor_default;

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
        exit;
    }

    // Guardar Planilla de Asistencia (Profesor)
    if (isset($_POST['guardar_planilla_asistencia_profesor']) && $tipo_usuario === 'profesor') {
        $asistencias_planilla = $_POST['asistencias_planilla_prof'] ?? [];
        $materia_id_post = (int)$_POST['materia_id_prof_hidden'];
        $curso_id_post = (int)$_POST['curso_id_prof_hidden'];
        $mes_post = (int)$_POST['mes_prof_hidden'];
        $anio_post = (int)$_POST['anio_prof_hidden'];
        $redirect_url_params = "&materia_id_prof=$materia_id_post&curso_id_prof=$curso_id_post&mes_prof=$mes_post&anio_prof=$anio_post&mostrar_planilla_profesor=1";

        if (!$profesor_id) {
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
                            $stmt_update->bind_param("sii", $estado, $profesor_id, $asistencia_existente['id']);
                            $stmt_update->execute();
                            $stmt_update->close();
                        } else {
                            $stmt_insert = $mysqli->prepare("INSERT INTO asistencia (inscripcion_cursado_id, fecha, estado, profesor_id) VALUES (?, ?, ?, ?)");
                            $stmt_insert->bind_param("issi", $ic_id_int, $fecha_str, $estado, $profesor_id);
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


// --- LÓGICA GET PARA CARGAR DATOS ---
// (La lógica GET permanece idéntica a tu archivo original)
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
            ");
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

    if ($mostrar_planilla_preceptor && $curso_id_seleccionado_preceptor && $materia_id_seleccionada_preceptor && $mes_seleccionado_preceptor && $anio_seleccionado_preceptor) {
        $query_alumnos = $mysqli->query("
            SELECT ic.id AS inscripcion_cursado_id, a.legajo, CONCAT(p.apellidos, ', ', p.nombres) AS alumno_nombre
            FROM inscripcion_cursado ic
            JOIN alumno a ON ic.alumno_id = a.id
            JOIN persona p ON a.persona_id = p.id
            WHERE ic.curso_id = $curso_id_seleccionado_preceptor AND ic.materia_id = $materia_id_seleccionada_preceptor
            ORDER BY p.apellidos, p.nombres
        ");
        if ($query_alumnos) {
            while ($alumno = $query_alumnos->fetch_assoc()) {
                $alumnos_planilla_preceptor[] = $alumno;
                $fecha_inicio_mes = $anio_seleccionado_preceptor . "-" . str_pad($mes_seleccionado_preceptor, 2, '0', STR_PAD_LEFT) . "-01";
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
    WHERE 1=1 ";

$filtros_listado = "";
$params_paginacion = '';

if ($tipo_usuario === 'profesor') {
    if ($profesor_id) {
        $filtros_listado .= " AND a.profesor_id = $profesor_id ";
    }
    // Mantener filtros en paginación
    if ($materia_id_seleccionada_profesor) $params_paginacion .= "&materia_id_prof=$materia_id_seleccionada_profesor";
    if ($curso_id_seleccionado_profesor) $params_paginacion .= "&curso_id_prof=$curso_id_seleccionado_profesor";
    if ($mostrar_planilla_profesor) $params_paginacion .= "&mes_prof=$mes_seleccionado_profesor&anio_prof=$anio_seleccionado_profesor&mostrar_planilla_profesor=1";
} else if ($tipo_usuario === 'preceptor') {
    if ($curso_id_seleccionado_preceptor) {
        $filtros_listado .= " AND ic.curso_id = $curso_id_seleccionado_preceptor";
        $params_paginacion .= "&curso_id_pre=$curso_id_seleccionado_preceptor";
    }
    if ($materia_id_seleccionada_preceptor) {
        $filtros_listado .= " AND ic.materia_id = $materia_id_seleccionada_preceptor";
        $params_paginacion .= "&materia_id_pre=$materia_id_seleccionada_preceptor";
    }
    if ($mostrar_planilla_preceptor) $params_paginacion .= "&mes_pre=$mes_seleccionado_preceptor&anio_pre=$anio_seleccionado_preceptor&mostrar_planilla_preceptor=1";
}

$query_total_registros = $query_total_registros_base . $filtros_listado;
$total_registros_res = $mysqli->query($query_total_registros);
$total_registros = $total_registros_res ? $total_registros_res->fetch_assoc()['total'] : 0;
$total_paginas = ceil($total_registros / $registros_por_pagina);

$query_asistencias_listado = $query_asistencias_listado_base . $filtros_listado . " ORDER BY a.fecha DESC, alumno_nombre ASC LIMIT $registros_por_pagina OFFSET $offset";
$asistencias_listado = $mysqli->query($query_asistencias_listado);


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
if ($materia_id_seleccionada_profesor) {
    $stmt_m = $mysqli->prepare("SELECT nombre FROM materia WHERE id = ?");
    $stmt_m->bind_param("i", $materia_id_seleccionada_profesor);
    $stmt_m->execute();
    $res_m = $stmt_m->get_result();
    if ($r = $res_m->fetch_assoc()) $nombre_materia_planilla_prof = $r['nombre'];
    $stmt_m->close();
}
if ($curso_id_seleccionado_profesor) {
    $stmt_c = $mysqli->prepare("SELECT CONCAT(codigo, ' ', division, ' - ', anio, ' (', ciclo_lectivo, ')') AS curso_nombre_completo FROM curso WHERE id = ?");
    $stmt_c->bind_param("i", $curso_id_seleccionado_profesor);
    $stmt_c->execute();
    $res_c = $stmt_c->get_result();
    if ($r = $res_c->fetch_assoc()) $nombre_curso_planilla_prof = $r['curso_nombre_completo'];
    $stmt_c->close();
}

$nombre_materia_planilla_pre = 'Materia';
$nombre_curso_planilla_pre = 'Curso';
if ($materia_id_seleccionada_preceptor) {
    $stmt_m = $mysqli->prepare("SELECT nombre FROM materia WHERE id = ?");
    $stmt_m->bind_param("i", $materia_id_seleccionada_preceptor);
    $stmt_m->execute();
    $res_m = $stmt_m->get_result();
    if ($r = $res_m->fetch_assoc()) $nombre_materia_planilla_pre = $r['nombre'];
    $stmt_m->close();
}
if ($curso_id_seleccionado_preceptor) {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Asistencias - Sistema ISEF</title>
    <link rel="icon" href="../sources/logoo.ico" type="image/x-icon">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        :root {
            --orange-primary: rgba(230, 92, 0, 0.9);
            --orange-light: rgba(255, 140, 66, 0.8);
            --orange-lighter: rgba(255, 165, 102, 0.7);
            --orange-lightest: rgba(255, 224, 204, 0.6);
            --white: rgba(255, 255, 255, 0.9);
            --white-70: rgba(255, 255, 255, 0.7);
            --white-50: rgba(255, 255, 255, 0.5);
            --gray-light: rgba(245, 245, 245, 0.7);
            --gray-medium: rgba(224, 224, 224, 0.6);
            --gray-dark: rgba(51, 51, 51, 0.9);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: url('fondo.png') no-repeat center center fixed;
            background-size: cover;
            color: var(--gray-dark);
            line-height: 1.6;
            position: relative;
        }

        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.15);
            z-index: -1;
            pointer-events: none;
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: rgba(230, 92, 0, 0.85);
            backdrop-filter: blur(5px);
            border-right: 1px solid var(--orange-light);
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            color: var(--white);
            transition: all 0.3s ease;
            z-index: 10;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: inherit;
        }

        .brand-text h1 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            color: var(--white);
        }

        .brand-text p {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
        }

        .sidebar-nav {
            flex: 1;
            padding: 1rem;
        }

        .nav-section {
            margin-bottom: 2rem;
        }

        .nav-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.8);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
            padding: 0 0.75rem;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 0.875rem;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            color: var(--white);
        }

        .nav-link.active {
            background: var(--white);
            color: var(--orange-primary);
            font-weight: 500;
        }

        .nav-icon {
            width: 16px;
            height: 16px;
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: var(--white);
            color: var(--orange-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .user-details h3 {
            font-size: 0.875rem;
            font-weight: 500;
            margin: 0;
            color: var(--white);
        }

        .user-details p {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            color: var(--white);
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 0.875rem;
            border: none;
            background: rgba(255, 255, 255, 0.1);
            width: 100%;
            cursor: pointer;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: transparent;
            min-width: 0;
        }

        .header {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            position: sticky;
            top: 0;
            z-index: 5;
        }

        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            padding: 0.5rem;
            cursor: pointer;
            border-radius: 4px;
            color: var(--orange-primary);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            color: #888;
        }

        .breadcrumb a {
            color: var(--orange-primary);
            text-decoration: none;
        }

        .content {
            flex: 1;
            padding: 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            position: relative;
            z-index: 1;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--gray-dark);
        }

        .page-subtitle {
            color: var(--gray-dark);
            opacity: 0.9;
            font-size: 1rem;
        }

        .message-toast {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 6px;
            border: 1px solid transparent;
            backdrop-filter: blur(2px);
        }

        .message-toast.success {
            background-color: rgba(220, 252, 231, 0.8);
            color: #166534;
            border-color: rgba(187, 247, 208, 0.6);
        }

        .message-toast.error {
            background-color: rgba(254, 226, 226, 0.8);
            color: #991b1b;
            border-color: rgba(254, 202, 202, 0.6);
        }

        .card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-dark);
        }

        .card-description {
            font-size: 0.875rem;
            color: var(--gray-dark);
            opacity: 0.8;
            margin-top: 0.25rem;
        }

        .card-content {
            padding: 1.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .form-group select {
            width: 100%;
            padding: 0.625rem 0.75rem;
            border: 1px solid var(--gray-medium);
            border-radius: 6px;
            font-size: 0.875rem;
            background-color: var(--white);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 6px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--orange-primary);
            color: white;
        }

        .btn-success {
            background-color: #16a34a;
            color: white;
        }

        .btn-info {
            background-color: #0ea5e9;
            color: white;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }

        .table-container {
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            overflow-x: auto;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(5px);
        }

        .styled-table {
            width: 100%;
            border-collapse: collapse;
        }

        .styled-table th,
        .styled-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 0.875rem;
        }

        .styled-table th {
            background-color: rgba(255, 255, 255, 0.5);
            font-weight: 600;
        }

        .styled-table tr:hover {
            background-color: rgba(255, 255, 255, 0.5);
        }

        .pagination {
            margin: 1.5rem 0;
            text-align: center;
        }

        .pagination a,
        .pagination span {
            margin: 0 0.2rem;
        }

        .pagination span.current {
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            background-color: var(--orange-primary);
            color: white;
        }

        .sticky-header th {
            position: sticky;
            top: 0;
            z-index: 2;
            background-color: #f2f2f2;
        }

        .day-cell {
            min-width: 45px;
            text-align: center;
            padding: 2px;
        }

        .day-cell select {
            padding: 2px;
            font-size: 0.8em;
            width: 45px;
            /* Establece un ancho fijo para el select */
            max-width: 45px;
            /* Asegura que no crezca más allá de este ancho */
            border-radius: 3px;
            border: 1px solid #ccc;
            text-align: center;
            /* Centra el texto */
            text-align-last: center;
            /* Asegura que la opción seleccionada también se centre en algunos navegadores */
            -moz-appearance: none;
            /* Elimina estilos por defecto en Firefox */
            -webkit-appearance: none;
            /* Elimina estilos por defecto en Webkit (Chrome, Safari) */
            appearance: none;
            /* Elimina estilos por defecto */
            background-color: var(--white);
            /* Asegura un fondo blanco */
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20256%20256%22%3E%3Cpath%20fill%3D%22%23333%22%20d%3D%22M218.8%2093.2L128%20184.2L37.2%2093.2L45.2%2085.2L128%20168L210.8%2085.2Z%22%2F%3E%3C%2Fsvg%3E');
            /* Icono de flecha personalizado */
            background-repeat: no-repeat;
            background-position: right 5px center;
            /* Posiciona la flecha a la derecha */
            background-size: 10px;
            /* Ajusta el tamaño de la flecha */
        }

        /* Ajuste para las opciones dentro del select para centrar también el texto */
        .day-cell select option {
            text-align: center;
        }

        .bg-light-weekend {
            background-color: #f8f9fa !important;
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -280px;
                z-index: 1000;
            }

            .sidebar.open {
                left: 0;
            }

            .sidebar-toggle {
                display: block;
            }

            .content {
                padding: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .overlay.show {
            display: block;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="sidebar-brand">
                    <img src="../sources/logo_recortado.png" alt="Logo ISEF" style="width: 50px; height: 50px;">
                    <div class="brand-text">
                        <h1>Sistema de Gestión ISEF</h1>
                        <p>Instituto Superior</p>
                    </div>
                </a>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-label">Navegación Principal</div>
                    <ul class="nav-menu">
                        <li class="nav-item"><a href="dashboard.php" class="nav-link"><i data-lucide="home" class="nav-icon"></i><span>Inicio</span></a></li>
                        <?php if ($_SESSION['tipo'] === 'administrador'): ?>
                            <li class="nav-item"><a href="alumnos.php" class="nav-link"><i data-lucide="graduation-cap" class="nav-icon"></i><span>Alumnos</span></a></li>
                            <li class="nav-item"><a href="profesores.php" class="nav-link"><i data-lucide="briefcase" class="nav-icon"></i><span>Profesores</span></a></li>
                            <li class="nav-item"><a href="usuarios.php" class="nav-link"><i data-lucide="users" class="nav-icon"></i><span>Usuarios</span></a></li>
                            <li class="nav-item"><a href="materias.php" class="nav-link"><i data-lucide="book-open" class="nav-icon"></i><span>Materias</span></a></li>
                            <li class="nav-item"><a href="cursos.php" class="nav-link"><i data-lucide="library" class="nav-icon"></i><span>Cursos</span></a></li>
                            <li class="nav-item"><a href="auditoria.php" class="nav-link"><i data-lucide="clipboard-list" class="nav-icon"></i><span>Auditoría</span></a></li>
                        <?php endif; ?>
                        <?php if (in_array($_SESSION['tipo'], ['profesor', 'preceptor'])): ?>
                            <li class="nav-item"><a href="asistencias.php" class="nav-link active"><i data-lucide="user-check" class="nav-icon"></i><span>Asistencias</span></a></li>
                            <li class="nav-item"><a href="evaluaciones.php" class="nav-link"><i data-lucide="clipboard-check" class="nav-icon"></i><span>Evaluaciones</span></a></li>
                        <?php endif; ?>
                        <?php if ($_SESSION['tipo'] === 'alumno'): ?>
                            <li class="nav-item"><a href="inscripciones.php" class="nav-link"><i data-lucide="user-plus" class="nav-icon"></i><span>Inscripciones</span></a></li>
                            <li class="nav-item"><a href="situacion.php" class="nav-link"><i data-lucide="bar-chart-3" class="nav-icon"></i><span>Situación Académica</span></a></li>
                            <li class="nav-item"><a href="certificados.php" class="nav-link"><i data-lucide="file-text" class="nav-icon"></i><span>Certificados</span></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><?= strtoupper(substr($usuario_sidebar['nombre_completo'] ?? 'U', 0, 1)) ?></div>
                    <div class="user-details">
                        <h3><?= htmlspecialchars($usuario_sidebar['nombre_completo'] ?? 'Usuario') ?></h3>
                        <p><?= htmlspecialchars($_SESSION['tipo']) ?>@isef.edu</p>
                    </div>
                </div>
                <button onclick="confirmLogout()" class="logout-btn"><i data-lucide="log-out" class="nav-icon"></i><span>Cerrar Sesión</span></button>
            </div>
        </aside>

        <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

        <main class="main-content">
            <header class="header">
                <button class="sidebar-toggle" onclick="toggleSidebar()"><i data-lucide="menu"></i></button>
                <nav class="breadcrumb">
                    <a href="dashboard.php">Sistema de Gestión ISEF</a>
                    <span>/</span>
                    <span>Asistencias</span>
                </nav>
            </header>

            <div class="content">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Gestión de Asistencias</h1>
                        <p class="page-subtitle">Seleccione los filtros para registrar o consultar las asistencias.</p>
                    </div>

                </div>

                <?php if ($mensaje_feedback): ?>
                    <div class="message-toast <?= strpos(strtolower($mensaje_feedback), 'error') !== false ? 'error' : 'success' ?>" role="alert">
                        <?= $mensaje_feedback ?>
                    </div>
                <?php endif; ?>
                <?php if ($tipo_usuario === 'preceptor' && !$profesor_id_preceptor_default): ?>
                    <div class="message-toast error">Advertencia: No se pudo determinar un profesor por defecto para el Preceptor. Las asistencias podrían no guardarse correctamente.</div>
                <?php endif; ?>
                <?php if ($tipo_usuario === 'profesor' && !$profesor_id): ?>
                    <div class="message-toast error">Advertencia: Su ID de profesor no pudo ser determinado. No podrá cargar asistencias.</div>
                <?php endif; ?>


                <?php if ($tipo_usuario === 'profesor' && $profesor_id): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Planilla de Asistencia (Profesor)</h2>
                            <p class="card-description">Seleccione materia, curso, mes y año para ver la planilla.</p>
                        </div>
                        <div class="card-content">
                            <form method="GET" action="asistencias.php" class="form-grid">
                                <input type="hidden" name="mostrar_planilla_profesor" value="1">
                                <div class="form-group">
                                    <label for="materia_id_prof_select">Materia:</label>
                                    <select name="materia_id_prof" id="materia_id_prof_select" required onchange="this.form.submit()">
                                        <option value="">-- Seleccione Materia --</option>
                                        <?php if ($materias_profesor_res) : while ($m = $materias_profesor_res->fetch_assoc()): ?>
                                                <option value="<?= $m['id'] ?>" <?= ($materia_id_seleccionada_profesor == $m['id']) ? 'selected' : '' ?>><?= htmlspecialchars($m['nombre']) ?></option>
                                        <?php endwhile;
                                            $materias_profesor_res->data_seek(0);
                                        endif; ?>
                                    </select>
                                </div>

                                <?php if ($materia_id_seleccionada_profesor): ?>
                                    <div class="form-group">
                                        <label for="curso_id_prof_select">Curso:</label>
                                        <select name="curso_id_prof" id="curso_id_prof_select" required onchange="this.form.submit()">
                                            <option value="">-- Seleccione Curso --</option>
                                            <?php if ($cursos_profesor_res) : while ($c = $cursos_profesor_res->fetch_assoc()): ?>
                                                    <option value="<?= $c['id'] ?>" <?= ($curso_id_seleccionado_profesor == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['curso_nombre_completo']) ?></option>
                                            <?php endwhile;
                                                $cursos_profesor_res->data_seek(0);
                                            endif; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <?php if ($materia_id_seleccionada_profesor && $curso_id_seleccionado_profesor): ?>
                                    <div class="form-group">
                                        <label for="mes_prof_select">Mes:</label>
                                        <select name="mes_prof" id="mes_prof_select" required onchange="this.form.submit()">
                                            <?php for ($m_idx = 1; $m_idx <= 12; $m_idx++): ?>
                                                <option value="<?= $m_idx ?>" <?= ($mes_seleccionado_profesor == $m_idx) ? 'selected' : '' ?>><?= $meses_en_espanol[$m_idx] ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="anio_prof_select">Año:</label>
                                        <select name="anio_prof" id="anio_prof_select" required onchange="this.form.submit()">
                                            <?php for ($a = date('Y'); $a >= date('Y') - 2; $a--): ?>
                                                <option value="<?= $a ?>" <?= ($anio_seleccionado_profesor == $a) ? 'selected' : '' ?>><?= $a ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-info w-100">Mostrar Planilla</button>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <?php if ($mostrar_planilla_profesor && !empty($alumnos_planilla_profesor)):
                        $dias_en_mes_prof = cal_days_in_month(CAL_GREGORIAN, $mes_seleccionado_profesor, $anio_seleccionado_profesor);
                    ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Planilla: <?= htmlspecialchars($nombre_materia_planilla_prof) ?> - <?= htmlspecialchars($nombre_curso_planilla_prof) ?></h3>
                                <p class="card-description">Mes: <?= $meses_en_espanol[$mes_seleccionado_profesor] ?> <?= $anio_seleccionado_profesor ?></p>
                            </div>
                            <div class="card-content">
                                <form method="POST" action="asistencias.php">
                                    <input type="hidden" name="guardar_planilla_asistencia_profesor" value="1">
                                    <input type="hidden" name="materia_id_prof_hidden" value="<?= $materia_id_seleccionada_profesor ?>">
                                    <input type="hidden" name="curso_id_prof_hidden" value="<?= $curso_id_seleccionado_profesor ?>">
                                    <input type="hidden" name="mes_prof_hidden" value="<?= $mes_seleccionado_profesor ?>">
                                    <input type="hidden" name="anio_prof_hidden" value="<?= $anio_seleccionado_profesor ?>">
                                    <div class="table-container">
                                        <table class="styled-table">
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
                                                        <th class="day-cell <?= $es_finde ? 'bg-light-weekend' : '' ?>" title="<?= ucfirst(strftime('%A', $fecha_actual_dia->getTimestamp())) ?>"> <?= $dia ?></th>
                                                    <?php endfor; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($alumnos_planilla_profesor as $index => $alumno): ?>
                                                    <tr>
                                                        <td><?= $index + 1 ?></td>
                                                        <td><?= htmlspecialchars($alumno['legajo']) ?></td>
                                                        <td><?= htmlspecialchars($alumno['alumno_nombre']) ?></td>
                                                        <?php for ($dia = 1; $dia <= $dias_en_mes_prof; $dia++):
                                                            $fecha_completa = $anio_seleccionado_profesor . '-' . str_pad($mes_seleccionado_profesor, 2, '0', STR_PAD_LEFT) . '-' . str_pad($dia, 2, '0', STR_PAD_LEFT);
                                                            $estado_actual = $asistencias_cargadas_planilla_profesor[$alumno['inscripcion_cursado_id']][$fecha_completa] ?? '';
                                                        ?>
                                                            <td class="day-cell">
                                                                <select name="asistencias_planilla_prof[<?= $alumno['inscripcion_cursado_id'] ?>][<?= $fecha_completa ?>]">
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
                                    <button type="submit" class="btn btn-success" style="margin-top: 1.5rem;"><i data-lucide="save" class="nav-icon"></i> Guardar Asistencias</button>
                                </form>
                            </div>
                        </div>
                    <?php elseif ($mostrar_planilla_profesor): ?>
                        <div class="card">
                            <div class="card-content">
                                <p>No hay alumnos inscritos para la materia y curso seleccionados.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($tipo_usuario === 'preceptor'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Planilla de Asistencia (Preceptor)</h2>
                            <p class="card-description">Seleccione curso, materia, mes y año para ver la planilla.</p>
                        </div>
                        <div class="card-content">
                            <form method="GET" action="asistencias.php" class="form-grid">
                                <input type="hidden" name="mostrar_planilla_preceptor" value="1">
                                <div class="form-group">
                                    <label for="curso_id_pre_select">Curso:</label>
                                    <select name="curso_id_pre" id="curso_id_pre_select" required onchange="this.form.submit()">
                                        <option value="">-- Seleccione Curso --</option>
                                        <?php if ($cursos_preceptor_res) : while ($c = $cursos_preceptor_res->fetch_assoc()): ?>
                                                <option value="<?= $c['id'] ?>" <?= ($curso_id_seleccionado_preceptor == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['curso_nombre']) ?></option>
                                        <?php endwhile;
                                            $cursos_preceptor_res->data_seek(0);
                                        endif; ?>
                                    </select>
                                </div>

                                <?php if ($curso_id_seleccionado_preceptor): ?>
                                    <div class="form-group">
                                        <label for="materia_id_pre_select">Materia:</label>
                                        <select name="materia_id_pre" id="materia_id_pre_select" required onchange="this.form.submit()">
                                            <option value="">-- Seleccione Materia --</option>
                                            <?php if ($materias_preceptor_res) : while ($m = $materias_preceptor_res->fetch_assoc()): ?>
                                                    <option value="<?= $m['id'] ?>" <?= ($materia_id_seleccionada_preceptor == $m['id']) ? 'selected' : '' ?>><?= htmlspecialchars($m['nombre']) ?></option>
                                            <?php endwhile;
                                                $materias_preceptor_res->data_seek(0);
                                            endif; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <?php if ($curso_id_seleccionado_preceptor && $materia_id_seleccionada_preceptor): ?>
                                    <div class="form-group">
                                        <label for="mes_pre_select">Mes:</label>
                                        <select name="mes_pre" id="mes_pre_select" required onchange="this.form.submit()">
                                            <?php for ($m_idx = 1; $m_idx <= 12; $m_idx++): ?>
                                                <option value="<?= $m_idx ?>" <?= ($mes_seleccionado_preceptor == $m_idx) ? 'selected' : '' ?>><?= $meses_en_espanol[$m_idx] ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="anio_pre_select">Año:</label>
                                        <select name="anio_pre" id="anio_pre_select" required onchange="this.form.submit()">
                                            <?php for ($a = date('Y'); $a >= date('Y') - 2; $a--): ?>
                                                <option value="<?= $a ?>" <?= ($anio_seleccionado_preceptor == $a) ? 'selected' : '' ?>><?= $a ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-info w-100">Mostrar Planilla</button>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <?php if ($mostrar_planilla_preceptor && !empty($alumnos_planilla_preceptor)):
                        $dias_en_mes_pre = cal_days_in_month(CAL_GREGORIAN, $mes_seleccionado_preceptor, $anio_seleccionado_preceptor);
                    ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Planilla: <?= htmlspecialchars($nombre_materia_planilla_pre) ?> - <?= htmlspecialchars($nombre_curso_planilla_pre) ?></h3>
                                <p class="card-description">Mes: <?= $meses_en_espanol[$mes_seleccionado_preceptor] ?> <?= $anio_seleccionado_preceptor ?></p>
                            </div>
                            <div class="card-content">
                                <form method="POST" action="asistencias.php">
                                    <input type="hidden" name="guardar_planilla_asistencia_preceptor" value="1">
                                    <input type="hidden" name="curso_id_pre_hidden" value="<?= $curso_id_seleccionado_preceptor ?>">
                                    <input type="hidden" name="materia_id_pre_hidden" value="<?= $materia_id_seleccionada_preceptor ?>">
                                    <input type="hidden" name="mes_pre_hidden" value="<?= $mes_seleccionado_preceptor ?>">
                                    <input type="hidden" name="anio_pre_hidden" value="<?= $anio_seleccionado_preceptor ?>">
                                    <div class="table-container">
                                        <table class="styled-table">
                                            <thead class="sticky-header">
                                                <tr>
                                                    <th style="width: 40px;">N°</th>
                                                    <th style="width: 80px;">Legajo</th>
                                                    <th>Alumno</th>
                                                    <?php for ($dia = 1; $dia <= $dias_en_mes_pre; $dia++):
                                                        $fecha_actual_dia = new DateTime("$anio_seleccionado_preceptor-$mes_seleccionado_preceptor-$dia");
                                                        $nombre_dia_semana = strftime('%a', $fecha_actual_dia->getTimestamp());
                                                        $es_finde = (in_array($nombre_dia_semana, ['Sat', 'Sun', 'Sáb', 'Dom']));
                                                    ?>
                                                        <th class="day-cell <?= $es_finde ? 'bg-light-weekend' : '' ?>" title="<?= ucfirst(strftime('%A', $fecha_actual_dia->getTimestamp())) ?>"> <?= $dia ?></th>
                                                    <?php endfor; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($alumnos_planilla_preceptor as $index => $alumno): ?>
                                                    <tr>
                                                        <td><?= $index + 1 ?></td>
                                                        <td><?= htmlspecialchars($alumno['legajo']) ?></td>
                                                        <td><?= htmlspecialchars($alumno['alumno_nombre']) ?></td>
                                                        <?php for ($dia = 1; $dia <= $dias_en_mes_pre; $dia++):
                                                            $fecha_completa = $anio_seleccionado_preceptor . '-' . str_pad($mes_seleccionado_preceptor, 2, '0', STR_PAD_LEFT) . '-' . str_pad($dia, 2, '0', STR_PAD_LEFT);
                                                            $estado_actual = $asistencias_cargadas_planilla_preceptor[$alumno['inscripcion_cursado_id']][$fecha_completa] ?? '';
                                                        ?>
                                                            <td class="day-cell">
                                                                <select name="asistencias_planilla_pre[<?= $alumno['inscripcion_cursado_id'] ?>][<?= $fecha_completa ?>]">
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
                                    <button type="submit" class="btn btn-success" style="margin-top: 1.5rem;"><i data-lucide="save" class="nav-icon"></i> Guardar Asistencias</button>
                                </form>
                            </div>
                        </div>
                    <?php elseif ($mostrar_planilla_preceptor): ?>
                        <div class="card">
                            <div class="card-content">
                                <p>No hay alumnos inscritos para el curso y materia seleccionados.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Generar Informes de Asistencias (PDF)</h2>
                        <p class="card-description">Seleccione materia y curso para generar el informe en PDF.</p>
                    </div>
                    <div class="card-content">
                        <form id="formGenerarPDF" action="generar_pdf_asistencias.php" method="POST" target="_blank" class="form-grid">
                            <div class="form-group">
                                <label for="materia_pdf">Espacio Curricular:</label>
                                <select id="materia_pdf" name="materia_id" required>
                                    <option value="">-- Seleccione una materia --</option>
                                    <?php foreach ($materias_para_pdf as $materia): ?>
                                        <option value="<?= $materia['id'] ?>"><?= htmlspecialchars($materia['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="curso_pdf">Curso:</label>
                                <select id="curso_pdf" name="curso_id" required>
                                    <option value="">-- Seleccione un curso --</option>
                                    <?php foreach ($cursos_para_pdf as $curso): ?>
                                        <option value="<?= $curso['id'] ?>"><?= htmlspecialchars($curso['curso_completo']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <input type="hidden" id="profesor_id_pdf" name="profesor_id">
                                <p id="profesor_nombre_display" style="font-size:0.9rem; margin-top: 0.5rem;">Profesor/a: No seleccionado</p>
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <button type="submit" class="btn btn-primary" name="periodo" value="abril_julio"><i data-lucide="file-down" class="nav-icon"></i>Generar PDF (Abril-Julio)</button>
                                <button type="submit" class="btn btn-primary" name="periodo" value="septiembre_diciembre" style="margin-left: 0.5rem;"><i data-lucide="file-down" class="nav-icon"></i>Generar PDF (Sept-Dic)</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Últimas Asistencias Registradas</h2>
                        <p class="card-description">
                            <?php
                            if ($tipo_usuario === 'preceptor' && ($curso_id_seleccionado_preceptor || $materia_id_seleccionada_preceptor)) {
                                echo 'Listado filtrado por la selección actual.';
                            } elseif ($tipo_usuario === 'profesor') {
                                echo 'Listado de sus registros.';
                            } else {
                                echo 'Un resumen de los últimos registros de asistencia en el sistema.';
                            }
                            ?>
                        </p>
                    </div>
                    <div class="card-content">
                        <?php if ($asistencias_listado && $asistencias_listado->num_rows > 0): ?>
                            <div class="table-container">
                                <table class="styled-table">
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
                                                <td><?= htmlspecialchars($a['curso_codigo'] . ' ' . $a['curso_division'] . ' - ' . $a['curso_anio_desc']) ?></td>
                                                <td><?= htmlspecialchars(date("d/m/Y", strtotime($a['fecha']))) ?></td>
                                                <td><?= htmlspecialchars($a['estado']) ?></td>
                                                <td><?= htmlspecialchars($a['profesor_nombre']) ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="pagination">
                                <?php if ($total_paginas > 1): ?>
                                    <?php if ($pagina_actual > 1): ?>
                                        <a href="?pagina=1<?= $params_paginacion ?>" class="btn btn-sm btn-outline">&laquo; Primera</a>
                                        <a href="?pagina=<?= $pagina_actual - 1 ?><?= $params_paginacion ?>" class="btn btn-sm btn-outline">&lsaquo; Anterior</a>
                                    <?php endif; ?>
                                    <span class="current">Página <?= $pagina_actual ?> de <?= $total_paginas ?></span>
                                    <?php if ($pagina_actual < $total_paginas): ?>
                                        <a href="?pagina=<?= $pagina_actual + 1 ?><?= $params_paginacion ?>" class="btn btn-sm btn-outline">Siguiente &rsaquo;</a>
                                        <a href="?pagina=<?= $total_paginas ?><?= $params_paginacion ?>" class="btn btn-sm btn-outline">Última &raquo;</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p>No hay asistencias registradas recientemente o para el filtro actual.</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>
    <script>
        // Funcionalidad del Sidebar
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('overlay').classList.toggle('show');
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('overlay').classList.remove('show');
        }
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) closeSidebar();
        });

        // Confirmación de cierre de sesión
        function confirmLogout() {
            if (confirm('¿Estás seguro que deseas cerrar sesión?')) window.location.href = '../index.php?logout=1';
        }

        // Script específico de la página de asistencias (para el PDF)
        document.addEventListener('DOMContentLoaded', function() {
            const materiaSelect = document.getElementById('materia_pdf');
            const profesorIdInput = document.getElementById('profesor_id_pdf');
            const profesorNombreDisplay = document.getElementById('profesor_nombre_display');

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

        // Crear los íconos de Lucide
        lucide.createIcons();
    </script>
</body>

</html>
<?php
$mysqli->close();
?>