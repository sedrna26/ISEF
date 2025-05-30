<?php

// 1. CONEXIÓN A LA BASE DE DATOS Y CONFIGURACIÓN
include '../config/db.php';
include '../tools/funciones_inscripcion.php'; // Para obtener_cursos_disponibles y verificar_requisitos_materia_alumno

if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}


// 2. VERIFICACIÓN DE USUARIO ADMINISTRADOR
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: ../index.php");
    exit;
}

$mensaje_exito = '';
$mensaje_error = '';
$alumno_encontrado = null;
$search_term = '';
$materias = [];
$cursos_disponibles_form = [];

if (isset($_SESSION['mensaje_exito'])) {
    $mensaje_exito = $_SESSION['mensaje_exito'];
    unset($_SESSION['mensaje_exito']);
}
if (isset($_SESSION['mensaje_error'])) {
    $mensaje_error = $_SESSION['mensaje_error'];
    unset($_SESSION['mensaje_error']);
}

// Obtener lista de materias para el dropdown
$result_materias = $mysqli->query("SELECT id, nombre, anio, cuatrimestre FROM materia ORDER BY anio, nombre");
if ($result_materias) {
    while ($row = $result_materias->fetch_assoc()) {
        $materias[] = $row;
    }
    $result_materias->free();
}


// 3. LÓGICA PARA BUSCAR ALUMNO (GET o POST)
if (isset($_REQUEST['accion']) && $_REQUEST['accion'] === 'buscar_alumno') {
    $search_term = isset($_REQUEST['search_term']) ? trim(htmlspecialchars($_REQUEST['search_term'])) : '';
    if (!empty($search_term)) {
        // Busca por legajo, DNI, o parcialmente por apellido/nombre
        $stmt_search = $mysqli->prepare(
            "SELECT a.id AS alumno_id, p.nombres, p.apellidos, p.dni, a.legajo
             FROM alumno a
             JOIN persona p ON a.persona_id = p.id
             WHERE a.legajo = ? OR p.dni = ? OR p.apellidos LIKE ? OR p.nombres LIKE ?"
        );
        $search_like = "%" . $search_term . "%";
        $stmt_search->bind_param("ssss", $search_term, $search_term, $search_like, $search_like);
        $stmt_search->execute();
        $result_search = $stmt_search->get_result();
        if ($result_search->num_rows === 1) {
            $alumno_encontrado = $result_search->fetch_assoc();
        } elseif ($result_search->num_rows > 1) {
            $mensaje_error = "Múltiples alumnos encontrados. Por favor, sea más específico.";
            // Aquí podrías listar los alumnos encontrados para que el admin elija.
        } else {
            $mensaje_error = "Alumno no encontrado con el término '{$search_term}'.";
        }
        $stmt_search->close();
    } else {
        $mensaje_error = "Por favor, ingrese un término de búsqueda.";
    }
}

// Cargar cursos si se selecciona materia y ciclo lectivo (para AJAX o recarga de página)
$selected_materia_id_form = filter_input(INPUT_POST, 'materia_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'materia_id', FILTER_VALIDATE_INT);
$selected_ciclo_lectivo_form = filter_input(INPUT_POST, 'ciclo_lectivo', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'ciclo_lectivo', FILTER_VALIDATE_INT) ?: date('Y');
$selected_alumno_id_form = filter_input(INPUT_POST, 'alumno_id_hidden', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'alumno_id_hidden', FILTER_VALIDATE_INT);


if ($selected_materia_id_form && $selected_ciclo_lectivo_form && function_exists('obtener_cursos_disponibles')) {
    $cursos_disponibles_form = obtener_cursos_disponibles($mysqli, $selected_materia_id_form, $selected_ciclo_lectivo_form);
}
// Si se recarga la página para mostrar cursos, re-establecer $alumno_encontrado si había uno
if (!$alumno_encontrado && $selected_alumno_id_form) {
    $stmt_recheck_alumno = $mysqli->prepare("SELECT a.id AS alumno_id, p.nombres, p.apellidos, p.dni, a.legajo FROM alumno a JOIN persona p ON a.persona_id = p.id WHERE a.id = ?");
    $stmt_recheck_alumno->bind_param("i", $selected_alumno_id_form);
    $stmt_recheck_alumno->execute();
    $result_recheck = $stmt_recheck_alumno->get_result();
    if ($result_recheck->num_rows === 1) $alumno_encontrado = $result_recheck->fetch_assoc();
    $stmt_recheck_alumno->close();
}


// 4. LÓGICA PARA INSCRIBIR ALUMNO (POST)
if (isset($_POST['accion']) && $_POST['accion'] === 'inscribir_alumno') {
    $alumno_id = filter_input(INPUT_POST, 'alumno_id_hidden', FILTER_VALIDATE_INT);
    $materia_id = filter_input(INPUT_POST, 'materia_id', FILTER_VALIDATE_INT);
    $curso_id = filter_input(INPUT_POST, 'curso_id', FILTER_VALIDATE_INT);
    $ciclo_lectivo_insc = filter_input(INPUT_POST, 'ciclo_lectivo', FILTER_VALIDATE_INT);
    $estado_inscripcion = filter_input(INPUT_POST, 'estado_inscripcion', FILTER_SANITIZE_STRING);
    $fecha_inscripcion = date("Y-m-d");

    if ($alumno_id && $materia_id && $curso_id && $ciclo_lectivo_insc && $estado_inscripcion) {
        // Verificar si ya está inscripto
        if (function_exists('alumno_ya_inscripto') && alumno_ya_inscripto($mysqli, $alumno_id, $materia_id, $ciclo_lectivo_insc)) {
            $_SESSION['mensaje_error'] = "El alumno ya está inscripto en esta materia para el ciclo lectivo {$ciclo_lectivo_insc}.";
        } else {
            $stmt_insert = $mysqli->prepare("INSERT INTO inscripcion_cursado (alumno_id, materia_id, curso_id, ciclo_lectivo, fecha_inscripcion, estado) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("iiiiss", $alumno_id, $materia_id, $curso_id, $ciclo_lectivo_insc, $fecha_inscripcion, $estado_inscripcion);

            if ($stmt_insert->execute()) {
                $_SESSION['mensaje_exito'] = "Alumno inscripto correctamente en la materia.";
                // Limpiar alumno_encontrado para permitir nueva búsqueda
                $alumno_encontrado = null;
                $search_term = '';
            } else {
                $_SESSION['mensaje_error'] = "Error al inscribir al alumno: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
    } else {
        $_SESSION['mensaje_error'] = "Faltan datos para la inscripción. Asegúrese de seleccionar alumno, materia, curso, ciclo lectivo y estado.";
    }
    // Redirigir para mostrar el mensaje y limpiar el POST
    header("Location: admin_inscripcion_manual.php" . ($alumno_encontrado ? "?accion=buscar_alumno&search_term=" . urlencode($search_term) : ""));
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Inscripción Manual de Alumnos - ISEF</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
        }

        .container {
            max-width: 900px;
            margin: 20px auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .form-section {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 5px;
        }

        label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border-radius: 4px;
            border: 1px solid #ddd;
            box-sizing: border-box;
        }

        button[type="submit"],
        .button {
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }

        button[type="submit"]:hover,
        .button:hover {
            background-color: #0056b3;
        }

        .button.search {
            background-color: #17a2b8;
        }

        .button.search:hover {
            background-color: #138496;
        }

        .mensaje {
            padding: 10px;
            margin: 15px 0;
            border-radius: 4px;
            text-align: center;
        }

        .exito {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alumno-info {
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .prereq-info {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
    </style>
    <script>
        // Script para recargar la página o hacer AJAX para obtener cursos
        // cuando se cambia la materia o el ciclo lectivo.
        function actualizarCursos() {
            const materiaId = document.getElementById('materia_id').value;
            const cicloLectivo = document.getElementById('ciclo_lectivo').value;
            const alumnoIdHidden = document.getElementById('alumno_id_hidden_val') ? document.getElementById('alumno_id_hidden_val').value : '';
            const searchTermActual = document.getElementById('search_term_val') ? document.getElementById('search_term_val').value : '';

            if (materiaId && cicloLectivo) {
                // Recargar la página con los nuevos parámetros para que PHP cargue los cursos
                let url = `admin_inscripcion_manual.php?accion=buscar_alumno&search_term=${encodeURIComponent(searchTermActual)}&alumno_id_hidden=${alumnoIdHidden}&materia_id=${materiaId}&ciclo_lectivo=${cicloLectivo}`;
                window.location.href = url;
            }
        }
    </script>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Inscripción Manual de Alumnos</h1>
            <a href="dashboard.php" class="button">Volver al Panel</a>
        </div>

        <?php if ($mensaje_exito): ?><div class="mensaje exito"><?= htmlspecialchars($mensaje_exito) ?></div><?php endif; ?>
        <?php if ($mensaje_error): ?><div class="mensaje error"><?= htmlspecialchars($mensaje_error) ?></div><?php endif; ?>

        <div class="form-section">
            <h2>1. Buscar Alumno</h2>
            <form action="admin_inscripcion_manual.php" method="GET">
                <input type="hidden" name="accion" value="buscar_alumno">
                <label for="search_term">Buscar por Legajo, DNI, Apellido o Nombre:</label>
                <input type="text" id="search_term" name="search_term" value="<?= htmlspecialchars($search_term) ?>" required>
                <button type="submit" class="button search" style="margin-top:10px;">Buscar Alumno</button>
            </form>
        </div>

        <?php if ($alumno_encontrado): ?>
            <div class="form-section">
                <h2>2. Inscribir Alumno</h2>
                <div class="alumno-info">
                    <strong>Alumno:</strong> <?= htmlspecialchars($alumno_encontrado['apellidos'] . ', ' . $alumno_encontrado['nombres']) ?><br>
                    <strong>Legajo:</strong> <?= htmlspecialchars($alumno_encontrado['legajo']) ?> | <strong>DNI:</strong> <?= htmlspecialchars($alumno_encontrado['dni']) ?>
                </div>

                <form action="admin_inscripcion_manual.php" method="POST" id="formInscripcion">
                    <input type="hidden" name="accion" value="inscribir_alumno">
                    <input type="hidden" name="alumno_id_hidden" id="alumno_id_hidden_val" value="<?= htmlspecialchars($alumno_encontrado['alumno_id']) ?>">
                    <input type="hidden" id="search_term_val" value="<?= htmlspecialchars($search_term) ?>">


                    <label for="materia_id">Materia (*):</label>
                    <select id="materia_id" name="materia_id" required onchange="actualizarCursos()">
                        <option value="">-- Seleccionar Materia --</option>
                        <?php foreach ($materias as $materia): ?>
                            <option value="<?= $materia['id'] ?>" <?= ($selected_materia_id_form == $materia['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($materia['anio'] . '° - ' . $materia['nombre'] . ' (' . $materia['cuatrimestre'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="ciclo_lectivo">Ciclo Lectivo (*):</label>
                    <input type="number" id="ciclo_lectivo" name="ciclo_lectivo" value="<?= htmlspecialchars($selected_ciclo_lectivo_form) ?>" required onchange="actualizarCursos()">

                    <?php
                    // Mostrar requisitos (opcional, informativo para el admin)
                    if ($selected_materia_id_form && function_exists('verificar_requisitos_materia_alumno')) {
                        $requisitos = verificar_requisitos_materia_alumno($mysqli, $alumno_encontrado['alumno_id'], $selected_materia_id_form);
                        echo "<div class='prereq-info'>";
                        echo "<strong>Requisitos para Cursar Regular:</strong> " . htmlspecialchars($requisitos['mensaje_cursar_regular']) . "<br>";
                        echo "<strong>Requisitos para Inscribir Libre:</strong> " . htmlspecialchars($requisitos['mensaje_inscribir_libre']);
                        echo "</div>";
                    }
                    ?>

                    <label for="curso_id">Curso/Comisión (*):</label>
                    <select id="curso_id" name="curso_id" required>
                        <option value="">-- Seleccionar Curso (primero elija materia y ciclo) --</option>
                        <?php if (!empty($cursos_disponibles_form)): ?>
                            <?php foreach ($cursos_disponibles_form as $curso): ?>
                                <option value="<?= $curso['id'] ?>">
                                    <?= htmlspecialchars($curso['codigo'] . ' ' . $curso['division'] . ' - ' . $curso['turno']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php elseif ($selected_materia_id_form && $selected_ciclo_lectivo_form): ?>
                            <option value="" disabled>No hay cursos disponibles para la materia y ciclo lectivo seleccionados.</option>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($cursos_disponibles_form) && $selected_materia_id_form && $selected_ciclo_lectivo_form): ?>
                        <small style="color:red;">No se encontraron cursos para la materia y ciclo lectivo seleccionados. Verifique la configuración de cursos.</small>
                    <?php endif; ?>


                    <label for="estado_inscripcion">Estado de Inscripción (*):</label>
                    <select id="estado_inscripcion" name="estado_inscripcion" required>
                        <option value="Regular">Regular</option>
                        <option value="Libre">Libre</option>
                        <option value="Promocional">Promocional</option>
                    </select>

                    <button type="submit" style="margin-top:15px;">Inscribir Alumno</button>
                </form>
            </div>
        <?php elseif (isset($_REQUEST['accion']) && $_REQUEST['accion'] === 'buscar_alumno' && empty($mensaje_error)): ?>
            <p>No se encontró ningún alumno con los criterios de búsqueda proporcionados.</p>
        <?php endif; ?>

    </div>
</body>

</html>