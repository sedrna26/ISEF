<?php
// Incluir funciones solo una vez
include_once '../tools/funciones_inscripcion.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'alumno') {
    header("Location: ../index.php");
    exit;
}

// Obtener alumno_id desde la base de datos si no está en la sesión
if (!isset($_SESSION['alumno_id_db'])) {
    $stmt = $mysqli->prepare("SELECT a.id FROM alumno a 
                            JOIN persona p ON a.persona_id = p.id 
                            JOIN usuario u ON p.usuario_id = u.id 
                            WHERE u.id = ?");
    $stmt->bind_param("i", $_SESSION['usuario_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $_SESSION['alumno_id_db'] = $row['id'];
    } else {
        die("Error: No se encontró el registro de alumno asociado a este usuario.");
    }
    $stmt->close();
}

$alumno_id = $_SESSION['alumno_id_db'];
$ciclo_lectivo_actual = date("Y");

// Verificar si se envió el formulario de inscripción ANTES de mostrar el HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $materia_id = isset($_POST['materia_id']) ? (int)$_POST['materia_id'] : 0;
    $curso_id = isset($_POST['curso_id']) ? (int)$_POST['curso_id'] : 0;
    $tipo_inscripcion = isset($_POST['tipo_inscripcion']) ? $_POST['tipo_inscripcion'] : '';

    // DEBUG: Agregar estas líneas para verificar qué se está enviando
    error_log("POST recibido - Materia ID: $materia_id, Curso ID: $curso_id, Tipo: $tipo_inscripcion");

    // Obtener información de la materia
    $stmt_materia = $mysqli->prepare("SELECT cuatrimestre, tipo, nombre FROM materia WHERE id = ?");
    $stmt_materia->bind_param("i", $materia_id);
    $stmt_materia->execute();
    $materia_info = $stmt_materia->get_result()->fetch_assoc();
    $stmt_materia->close();

    if ($materia_id && $curso_id && $tipo_inscripcion && $materia_info) {
        error_log("Procesando inscripción para materia: " . $materia_info['nombre']);

        // Verificar período activo
        $periodo_activo = verificar_periodo_inscripcion_activo(
            $mysqli,
            $materia_info['cuatrimestre'],
            $ciclo_lectivo_actual
        );

        error_log("Período activo: " . ($periodo_activo ? 'SI' : 'NO'));

        if ($periodo_activo) {
            // Verificar si ya está inscripto
            $ya_inscripto = alumno_ya_inscripto($mysqli, $alumno_id, $materia_id, $ciclo_lectivo_actual);

            if (!$ya_inscripto) {
                // CORRECCIÓN CRÍTICA: Usar 'estado' en lugar de 'condicion'
                $estado_inscripcion = ($tipo_inscripcion === 'regular') ? 'Regular' : 'Libre';

                $stmt = $mysqli->prepare("
                    INSERT INTO inscripcion_cursado 
                    (alumno_id, materia_id, curso_id, ciclo_lectivo, estado, fecha_inscripcion) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");

                try {
                    $stmt->bind_param("iiiis", $alumno_id, $materia_id, $curso_id, $ciclo_lectivo_actual, $estado_inscripcion);

                    error_log("Ejecutando query de inscripción con valores: $alumno_id, $materia_id, $curso_id, $ciclo_lectivo_actual, $estado_inscripcion");

                    if ($stmt->execute()) {
                        $_SESSION['mensaje'] = "Inscripción realizada con éxito en " . $materia_info['nombre'] . " como " . $estado_inscripcion;
                        error_log("Inscripción exitosa");
                    } else {
                        throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
                    }
                } catch (Exception $e) {
                    $_SESSION['error'] = "Error al procesar la inscripción: " . $e->getMessage();
                    error_log("Error en inscripción: " . $e->getMessage());
                }
                $stmt->close();
            } else {
                $_SESSION['error'] = "Ya estás inscripto en esta materia.";
                error_log("Alumno ya inscripto");
            }
        } else {
            $_SESSION['error'] = "El período de inscripción no está activo para esta materia (" . $materia_info['cuatrimestre'] . ").";
            error_log("Período no activo para cuatrimestre: " . $materia_info['cuatrimestre']);
        }
    } else {
        $_SESSION['error'] = "Datos de inscripción incompletos.";
        error_log("Datos incompletos - Materia info: " . print_r($materia_info, true));
    }

    // Redirigir para evitar reenvío del formulario
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Obtener materias DESPUÉS del procesamiento
$result_materias = $mysqli->query("SELECT * FROM materia ORDER BY anio, nro_orden");
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Inscripción a Materias - ISEF</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f7f6;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }

        h1,
        h2 {
            color: #2c3e50;
            margin-bottom: 20px;
        }

        h1 {
            font-size: 1.8em;
        }

        h2 {
            font-size: 1.4em;
        }

        a {
            color: #3498db;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .nav-link {
            display: inline-block;
            margin-bottom: 20px;
            padding: 8px 15px;
            background-color: #6c757d;
            color: white;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .nav-link:hover {
            background-color: #5a6268;
            text-decoration: none;
        }

        .materia-card {
            margin-bottom: 25px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #fdfdfd;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .materia-header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }

        .materia-title {
            font-size: 1.2em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .materia-info {
            color: #6c757d;
            font-size: 0.9em;
        }

        .status-message {
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 0.9em;
        }

        .status-inscripto {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-no-cursos {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .status-periodo-inactivo {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .form-inscripcion {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            color: #555;
            font-size: 0.9em;
        }

        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 0.95em;
            background-color: white;
        }

        select:focus {
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.25);
            outline: none;
        }

        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        button {
            padding: 10px 18px;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.95em;
            text-align: center;
            transition: background-color 0.2s ease-in-out;
        }

        .btn-regular {
            background-color: #28a745;
        }

        .btn-regular:hover:not(:disabled) {
            background-color: #218838;
        }

        .btn-libre {
            background-color: #007bff;
        }

        .btn-libre:hover:not(:disabled) {
            background-color: #0056b3;
        }

        button:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .requisitos-info {
            margin-top: 10px;
            font-size: 0.85em;
        }

        .requisitos-cumple {
            color: #155724;
        }

        .requisitos-no-cumple {
            color: #721c24;
        }

        .divider {
            height: 1px;
            background-color: #dee2e6;
            margin: 25px 0;
        }

        .no-materias {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-size: 1.1em;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Inscripción a Materias - Ciclo Lectivo <?= $ciclo_lectivo_actual ?></h1>
        <a href="dashboard.php" class="nav-link">&laquo; Volver al menú</a>

        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="status-message status-inscripto">
                <?= htmlspecialchars($_SESSION['mensaje']) ?>
            </div>
            <?php unset($_SESSION['mensaje']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="status-message status-no-cursos">
                <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if ($result_materias->num_rows > 0): ?>
            <?php while ($materia = $result_materias->fetch_assoc()): ?>
                <div class="materia-card">
                    <div class="materia-header">
                        <div class="materia-title">
                            <?= htmlspecialchars($materia['nombre']) ?>
                        </div>
                        <div class="materia-info">
                            Año: <?= htmlspecialchars($materia['anio']) ?> |
                            <?= htmlspecialchars($materia['tipo']) ?> |
                            <?= htmlspecialchars($materia['cuatrimestre']) ?>
                        </div>
                    </div>

                    <?php
                    // Verificar si el alumno ya está inscripto
                    $ya_inscripto = alumno_ya_inscripto($mysqli, $alumno_id, $materia['id'], $ciclo_lectivo_actual);

                    if ($ya_inscripto): ?>
                        <div class="status-message status-inscripto">
                            Ya estás inscripto/a en esta materia para el ciclo <?= $ciclo_lectivo_actual ?>.
                        </div>
                        <?php else:
                        // Verificar perÃ­odo de inscripción
                        $periodo_activo = verificar_periodo_inscripcion_activo($mysqli, $materia['cuatrimestre'], $ciclo_lectivo_actual);

                        if (!$periodo_activo): ?>
                            <div class="status-message status-periodo-inactivo">
                                El perÃ­odo de inscripción para materias del <?= htmlspecialchars($materia['cuatrimestre']) ?> no está activo.
                            </div>
                            <?php else:
                            // Obtener cursos disponibles
                            $cursos_disponibles = obtener_cursos_disponibles($mysqli, $materia['id'], $ciclo_lectivo_actual);

                            if ($cursos_disponibles && $cursos_disponibles->num_rows > 0): ?>
                                <form method="POST" class="form-inscripcion">
                                    <input type="hidden" name="materia_id" value="<?= $materia['id'] ?>">
                                    <input type="hidden" name="tipo_inscripcion" id="tipo_inscripcion_<?= $materia['id'] ?>" value="regular">

                                    <div class="form-group">
                                        <label for="curso_<?= $materia['id'] ?>">Seleccionar Comisión:</label>
                                        <select name="curso_id" id="curso_<?= $materia['id'] ?>" required>
                                            <option value="">Seleccione una comisión...</option>
                                            <?php
                                            // Reset del cursor para esta consulta específica
                                            $cursos_disponibles = obtener_cursos_disponibles($mysqli, $materia['id'], $ciclo_lectivo_actual);
                                            if ($cursos_disponibles && $cursos_disponibles->num_rows > 0):
                                                while ($curso = $cursos_disponibles->fetch_assoc()):
                                            ?>
                                                    <option value="<?= $curso['id'] ?>">
                                                        <?= htmlspecialchars($curso['codigo'] . ' ' . $curso['division'] . ' - ' . $curso['turno']) ?>
                                                    </option>
                                            <?php
                                                endwhile;
                                                $cursos_disponibles->free();
                                            endif;
                                            ?>
                                        </select>
                                    </div>

                                    <div class="button-group">
                                        <button type="submit" class="btn-regular" onclick="document.getElementById('tipo_inscripcion_<?= $materia['id'] ?>').value='regular';">
                                            Inscribirse como Regular
                                        </button>
                                        <button type="submit" class="btn-libre" onclick="document.getElementById('tipo_inscripcion_<?= $materia['id'] ?>').value='libre';">
                                            Inscribirse como Libre
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="status-message status-no-cursos">
                                    No hay comisiones disponibles para esta materia en este momento.
                                </div>
                    <?php endif;
                        endif;
                    endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-materias">
                <p>No hay materias disponibles para mostrar.</p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>