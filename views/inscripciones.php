<?php

include '../tools/funciones_inscripcion.php';

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

echo "<h1>Inscripción a Materias - Ciclo Lectivo {$ciclo_lectivo_actual}</h1>";

// Obtener materias
$result_materias = $mysqli->query("SELECT * FROM materia ORDER BY anio, nro_orden");

if ($result_materias->num_rows > 0) {
    echo "<ul>";
    while ($materia = $result_materias->fetch_assoc()) {
        echo "<li>";
        echo "<strong>{$materia['nombre']}</strong> (Año: {$materia['anio']}, Cuat: {$materia['cuatrimestre']})<br>";

        if (alumno_ya_inscripto($mysqli, $alumno_id, $materia['id'], $ciclo_lectivo_actual)) {
            echo "<small>Ya estás inscripto/a en esta materia para el ciclo {$ciclo_lectivo_actual}.</small><br>";
        } else {
            $periodo_activo = verificar_periodo_inscripcion_activo($mysqli, $materia['cuatrimestre'], $ciclo_lectivo_actual);
            if ($periodo_activo) {
                $requisitos = verificar_requisitos_materia_alumno($mysqli, $alumno_id, $materia['id']);

                $cursos_disponibles = obtener_cursos_disponibles($mysqli, $materia['id'], $ciclo_lectivo_actual);

                if (empty($cursos_disponibles)) {
                    echo "<small>No hay cursos (comisiones/turnos) definidos para esta materia en el ciclo lectivo actual.</small><br>";
                } else {
                    // Formulario para inscripción
                    echo "<form action='procesar_inscripcion.php' method='POST' style='display:inline-block; margin-right:10px;'>";
                    echo "<input type='hidden' name='materia_id' value='{$materia['id']}'>";
                    echo "<input type='hidden' name='ciclo_lectivo' value='{$ciclo_lectivo_actual}'>";

                    echo "<label for='curso_id_{$materia['id']}'>Seleccionar Curso/Comisión: </label>";
                    echo "<select name='curso_id' id='curso_id_{$materia['id']}' required>";
                    foreach ($cursos_disponibles as $curso) {
                        echo "<option value='{$curso['id']}'>{$curso['codigo']} {$curso['division']} - {$curso['turno']}</option>";
                    }
                    echo "</select><br>";

                    if ($requisitos['puede_cursar_regular']) {
                        echo "<button type='submit' name='tipo_inscripcion' value='Regular'>Inscribir Regular</button>";
                    } else {
                        echo "<button type='button' disabled title=\"" . htmlspecialchars($requisitos['mensaje_cursar_regular']) . "\">Inscribir Regular (No cumple)</button>";
                    }
                    echo "<small> " . htmlspecialchars($requisitos['mensaje_cursar_regular']) . "</small><br>";

                    if ($requisitos['puede_inscribir_libre']) {
                        echo "<button type='submit' name='tipo_inscripcion' value='Libre'>Inscribir Libre</button>";
                    } else {
                        echo "<button type='button' disabled title=\"" . htmlspecialchars($requisitos['mensaje_inscribir_libre']) . "\">Inscribir Libre (No cumple)</button>";
                    }
                    echo "<small> " . htmlspecialchars($requisitos['mensaje_inscribir_libre']) . "</small><br>";
                    echo "</form>";
                }
            } else {
                echo "<small>El período de inscripción para materias de este cuatrimestre no está activo.</small><br>";
            }
        }
        echo "</li><hr>";
    }
    echo "</ul>";
} else {
    echo "<p>No hay materias cargadas en el sistema.</p>";
}
