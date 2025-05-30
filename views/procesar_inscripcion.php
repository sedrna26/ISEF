<?php

include '../tools/funciones_inscripcion.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'alumno' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirigir o mostrar error
    exit;
}

$alumno_id = $_SESSION['alumno_id_db']; // ID de la tabla alumno
$materia_id = filter_input(INPUT_POST, 'materia_id', FILTER_VALIDATE_INT);
$curso_id = filter_input(INPUT_POST, 'curso_id', FILTER_VALIDATE_INT); // ¡Importante!
$ciclo_lectivo = filter_input(INPUT_POST, 'ciclo_lectivo', FILTER_VALIDATE_INT);
$tipo_inscripcion = filter_input(INPUT_POST, 'tipo_inscripcion', FILTER_SANITIZE_STRING); // 'Regular' o 'Libre'

if (!$materia_id || !$curso_id || !$ciclo_lectivo || !in_array($tipo_inscripcion, ['Regular', 'Libre'])) {
    $_SESSION['mensaje_error'] = "Datos de inscripción inválidos.";
    header("Location: inscripciones.php");
    exit;
}

// Doble verificación de requisitos y período antes de inscribir
$materia_info = $mysqli->query("SELECT cuatrimestre FROM materia WHERE id = $materia_id")->fetch_assoc();
if (!$materia_info) {
    $_SESSION['mensaje_error'] = "Materia no encontrada.";
    header("Location: inscripciones.php");
    exit;
}

if (!verificar_periodo_inscripcion_activo($mysqli, $materia_info['cuatrimestre'], $ciclo_lectivo)) {
    $_SESSION['mensaje_error'] = "El período de inscripción no está activo para esta materia.";
    header("Location: inscripciones.php");
    exit;
}

$requisitos = verificar_requisitos_materia_alumno($mysqli, $alumno_id, $materia_id);
$puede_inscribir = false;
if ($tipo_inscripcion === 'Regular' && $requisitos['puede_cursar_regular']) {
    $puede_inscribir = true;
} elseif ($tipo_inscripcion === 'Libre' && $requisitos['puede_inscribir_libre']) {
    $puede_inscribir = true;
}

if (!$puede_inscribir) {
    $_SESSION['mensaje_error'] = "No cumples los requisitos para inscribirte en la modalidad seleccionada.";
    header("Location: inscripciones.php");
    exit;
}

if (alumno_ya_inscripto($mysqli, $alumno_id, $materia_id, $ciclo_lectivo)) {
    $_SESSION['mensaje_error'] = "Ya estás inscripto/a en esta materia para el ciclo lectivo actual.";
    header("Location: inscripciones.php");
    exit;
}

// Insertar en la tabla inscripcion_cursado
$fecha_inscripcion = date("Y-m-d");
$estado_db = $tipo_inscripcion; // 'Regular' o 'Libre'

$stmt_insert = $mysqli->prepare("INSERT INTO inscripcion_cursado (alumno_id, materia_id, curso_id, ciclo_lectivo, fecha_inscripcion, estado) VALUES (?, ?, ?, ?, ?, ?)");
$stmt_insert->bind_param("iiiiss", $alumno_id, $materia_id, $curso_id, $ciclo_lectivo, $fecha_inscripcion, $estado_db);

if ($stmt_insert->execute()) {
    $_SESSION['mensaje_exito'] = "Inscripción realizada con éxito a la materia en modalidad {$tipo_inscripcion}.";
} else {
    $_SESSION['mensaje_error'] = "Error al procesar la inscripción: " . $stmt_insert->error;
}
$stmt_insert->close();
header("Location: inscripciones.php");
exit;
