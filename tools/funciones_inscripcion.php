<?php
// AsegÃºrate de tener la conexiÃ³n $mysqli establecida como en tu dashboard.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

// Conectar a la base de datos solo si no existe la conexión
if (!isset($mysqli)) {
    $mysqli = new mysqli("localhost", "root", "", "isef_sistema");
    if ($mysqli->connect_errno) {
        die("Fallo la conexiÃ³n: " . $mysqli->connect_error);
    }
}

// Prevenir redeclaración de funciones
if (!function_exists('obtener_cursos_disponibles')) {
    function obtener_cursos_disponibles($mysqli, $materia_id, $ciclo_lectivo)
    {
        $query = "
            SELECT DISTINCT c.* 
            FROM curso c
            JOIN profesor_materia pm ON pm.curso_id = c.id
            WHERE pm.materia_id = ?
            AND c.ciclo_lectivo = ?
            ORDER BY c.codigo, c.division
        ";

        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("is", $materia_id, $ciclo_lectivo);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        return $result;
    }
}

if (!function_exists('verificar_requisitos_materia_alumno')) {
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
}

if (!function_exists('verificar_periodo_inscripcion_activo')) {
    /**
     * Verifica si hay un perÃ­odo de inscripciÃ³n activo para un cuatrimestre especÃ­fico.
     * Para materias 'Anual', tambiÃ©n busca perÃ­odos activos del '1Â°' cuatrimestre.
     *
     * @param mysqli $mysqli ConexiÃ³n a la base de datos.
     * @param string $cuatrimestre_materia El cuatrimestre de la materia ('1Â°', '2Â°', 'Anual').
     * @param int $ciclo_lectivo El aÃ±o del ciclo lectivo.
     * @return bool Devuelve true si el perÃ­odo estÃ¡ activo, false en caso contrario.
     */
    function verificar_periodo_inscripcion_activo($mysqli, $cuatrimestre_materia, $ciclo_lectivo)
    {
        $hoy = date("Y-m-d");

        // Lógica más clara para verificar períodos activos
        $query = "SELECT id FROM periodos_inscripcion 
                 WHERE ciclo_lectivo = ? 
                 AND fecha_apertura <= ? 
                 AND fecha_cierre >= ? 
                 AND activo = 1 
                 AND (
                     cuatrimestre = ? OR 
                     (cuatrimestre = 'Anual' AND ? IN ('1°', '2°', 'Anual'))
                 )
                 LIMIT 1";

        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("issss", $ciclo_lectivo, $hoy, $hoy, $cuatrimestre_materia, $cuatrimestre_materia);
        $stmt->execute();
        $result = $stmt->get_result();
        $activo = $result->num_rows > 0;
        $stmt->close();

        return $activo;
    }
}

if (!function_exists('alumno_ya_inscripto')) {
    function alumno_ya_inscripto($mysqli, $alumno_id, $materia_id, $ciclo_lectivo)
    {
        $stmt = $mysqli->prepare("SELECT id FROM inscripcion_cursado WHERE alumno_id = ? AND materia_id = ? AND ciclo_lectivo = ? LIMIT 1");
        $stmt->bind_param("iii", $alumno_id, $materia_id, $ciclo_lectivo);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        $inscripto = $result->num_rows > 0;
        return $inscripto;
    }
}
