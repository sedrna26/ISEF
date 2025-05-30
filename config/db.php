<?php
// config/db.php - Configuración de base de datos

// Constantes de configuración
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'isef_sistema');

// Función para obtener conexión MySQLi
function getDBConnection()
{
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Verificar la conexión
    if ($mysqli->connect_errno) {
        // Registrar el error y mostrar mensaje genérico
        error_log("Fallo la conexión a MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
        die("Fallo la conexión a la base de datos. Por favor, intente más tarde.");
    }

    // Establecer el juego de caracteres a UTF-8
    if (!$mysqli->set_charset("utf8mb4")) {
        error_log("Error estableciendo charset utf8mb4: " . $mysqli->error);
    }

    return $mysqli;
}

// Crear conexión global (opcional, para compatibilidad)
$mysqli = getDBConnection();
