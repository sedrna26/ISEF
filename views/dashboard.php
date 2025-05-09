<?php
// dashboard.php - Menú principal tras login
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$tipo = $_SESSION['tipo'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel - ISEF</title>
</head>
<body>
    <h1>Bienvenido al Sistema Académico ISEF</h1>
    <p>Usuario: <?= htmlspecialchars($_SESSION['usuario_id']) ?> | Rol: <?= htmlspecialchars($tipo) ?></p>
    <nav>
        <ul>
            <li><a href="logout.php">Cerrar sesión</a></li>
            <?php if ($tipo === 'administrador'): ?>
                <li><a href="usuarios.php">Usuarios</a></li>
                <li><a href="personas.php">Personas</a></li>
                <li><a href="materias.php">Materias</a></li>
                <li><a href="cursos.php">Cursos</a></li>
                <li><a href="correlatividades.php">Correlatividades</a></li>
                <li><a href="auditoria.php">Auditoría</a></li>
            <?php endif; ?>

            <?php if ($tipo === 'profesor' || $tipo === 'preceptor'): ?>
                <li><a href="asistencias.php">Asistencias</a></li>
                <li><a href="evaluaciones.php">Evaluaciones</a></li>
            <?php endif; ?>

            <?php if ($tipo === 'alumno'): ?>
                <li><a href="inscripciones.php">Inscripciones</a></li>
                <li><a href="situacion.php">Situación Académica</a></li>
                <li><a href="certificados.php">Certificados</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</body>
</html>
