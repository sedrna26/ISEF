<?php
// Asegúrate de tener la conexión $mysqli establecida como en tu dashboard.php [cite: 2]
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

// Conectar a la base de datos
$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

function obtener_cursos_disponibles($mysqli, $materia_id, $ciclo_lectivo)
{
    // Una materia puede dictarse en varios cursos (comisiones/turnos)
    // Esta función busca los cursos disponibles para una materia en un ciclo lectivo.
    // La tabla `materia` tiene `anio` (ej: 1, 2) y `cuatrimestre`.
    // La tabla `curso` tiene `anio` (ej: "1°", "2°"), `ciclo_lectivo`, `codigo` (ej: "1PEF"), `division` (ej: "A"), `turno`.

    // Primero, obtenemos el año de la materia para buscar cursos correspondientes.
    $stmt_materia_anio = $mysqli->prepare("SELECT anio FROM materia WHERE id = ?");
    $stmt_materia_anio->bind_param("i", $materia_id);
    $stmt_materia_anio->execute();
    $result_materia_anio = $stmt_materia_anio->get_result();
    if ($result_materia_anio->num_rows === 0) {
        $stmt_materia_anio->close();
        return []; // Materia no encontrada
    }
    $materia_data = $result_materia_anio->fetch_assoc();
    $anio_cursada_materia = $materia_data['anio'] . '°'; // Convertir ej: 1 a "1°"
    $stmt_materia_anio->close();

    // Ahora buscamos los cursos
    $cursos = [];
    $stmt = $mysqli->prepare("SELECT id, codigo, division, turno FROM curso WHERE anio = ? AND ciclo_lectivo = ?");
    // El campo `curso.anio` es VARCHAR, ej: "1°", "2°". Asegúrate que el formato coincida.
    $stmt->bind_param("si", $anio_cursada_materia, $ciclo_lectivo);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $cursos[] = $row;
    }
    $stmt->close();
    return $cursos;
}

function verificar_requisitos_materia_alumno($mysqli, $alumno_id, $materia_id)
{
    // Llamada al procedimiento almacenado
    $stmt = $mysqli->prepare("CALL verificar_requisitos_inscripcion(?, ?, @p_puede_cursar_regular, @p_mensaje_cursar_regular, @p_puede_inscribir_libre, @p_mensaje_inscribir_libre)");
    $stmt->bind_param("ii", $alumno_id, $materia_id);
    $stmt->execute();
    $stmt->close(); // Importante cerrar antes de seleccionar las variables OUT

    // Obtener los resultados de las variables OUT
    $select_out_vars = $mysqli->query("SELECT @p_puede_cursar_regular AS puede_cursar_regular, @p_mensaje_cursar_regular AS mensaje_cursar_regular, @p_puede_inscribir_libre AS puede_inscribir_libre, @p_mensaje_inscribir_libre AS mensaje_inscribir_libre");
    $resultados = $select_out_vars->fetch_assoc();
    $select_out_vars->free();

    return $resultados;
}

function verificar_periodo_inscripcion_activo($mysqli, $cuatrimestre_materia, $ciclo_lectivo)
{
    $hoy = date("Y-m-d");
    $stmt = $mysqli->prepare("SELECT id FROM periodos_inscripcion WHERE ciclo_lectivo = ? AND cuatrimestre = ? AND fecha_apertura <= ? AND fecha_cierre >= ? AND activo = 1 LIMIT 1");
    $stmt->bind_param("isss", $ciclo_lectivo, $cuatrimestre_materia, $hoy, $hoy);
    $stmt->execute();
    $result = $stmt->get_result();
    $activo = $result->num_rows > 0;
    $stmt->close();
    return $activo;
}

function alumno_ya_inscripto($mysqli, $alumno_id, $materia_id, $ciclo_lectivo)
{
    $stmt = $mysqli->prepare("SELECT id FROM inscripcion_cursado WHERE alumno_id = ? AND materia_id = ? AND ciclo_lectivo = ? LIMIT 1");
    $stmt->bind_param("iii", $alumno_id, $materia_id, $ciclo_lectivo);
    $stmt->execute();
    $result = $stmt->get_result();
    $inscripto = $result->num_rows > 0;
    $stmt->close();
    return $inscripto;
}
