<?php
// get_profesor_materia.php
header('Content-Type: application/json');

$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    echo json_encode(['error' => "Fallo la conexión: " . $mysqli->connect_error]);
    exit();
}

$materia_id = isset($_GET['materia_id']) ? intval($_GET['materia_id']) : 0;

$response = ['profesor_id' => null, 'profesor_nombre' => ''];

if ($materia_id > 0) {
    // Consulta para obtener el profesor asociado a la materia
    // Se asume que una materia está impartida por un profesor (o se toma el primero si hay varios)
    $stmt = $mysqli->prepare("
        SELECT 
            p.id AS profesor_id, 
            CONCAT(per.nombres, ' ', per.apellidos) AS profesor_nombre
        FROM profesor_materia pm
        JOIN profesor p ON pm.profesor_id = p.id
        JOIN persona per ON p.persona_id = per.id
        WHERE pm.materia_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $materia_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $response['profesor_id'] = $row['profesor_id'];
        $response['profesor_nombre'] = $row['profesor_nombre'];
    }
    $stmt->close();
}

$mysqli->close();

echo json_encode($response);
?>