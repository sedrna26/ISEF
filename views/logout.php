<?php
// logout.php - Cerrar sesión y redirigir al login
session_start();

// Destruir todas las variables de sesión
$_SESSION = [];

// Destruir la sesión
session_destroy();

// Redirigir al login
header("Location: ../index.php");
exit;
?>
