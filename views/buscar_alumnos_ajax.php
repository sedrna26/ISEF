<?php
session_start();
require_once '../config/db.php';

// Verificar que sea administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

if (strlen($term) >= 2) {
    $stmt = $mysqli->prepare("
        SELECT DISTINCT a.legajo, p.apellidos, p.nombres, p.dni
        FROM alumno a
        JOIN persona p ON a.persona_id = p.id
        WHERE a.legajo LIKE ? 
        OR p.dni LIKE ? 
        OR p.apellidos LIKE ? 
        OR p.nombres LIKE ?
        LIMIT 10
    ");

    $searchTerm = "%$term%";
    $stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    $alumnos = [];
    while ($row = $result->fetch_assoc()) {
        $alumnos[] = [
            'legajo' => htmlspecialchars($row['legajo']),
            'apellidos' => htmlspecialchars($row['apellidos']),
            'nombres' => htmlspecialchars($row['nombres']),
            'dni' => htmlspecialchars($row['dni'])
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($alumnos);

    $stmt->close();
}

$mysqli->close();
