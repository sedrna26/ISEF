<?php
// logout.php - Cerrar sesión y redirigir al login
session_start();

// Destruir todas las variables de sesión
$_SESSION = [];

// Destruir la sesión
session_destroy();

// Redirigir al login con parámetro para mostrar mensaje
header("Location: ../index.php?logout=1");
exit;
?>