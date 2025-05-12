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
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { color: #333; }
        nav ul { list-style-type: none; padding: 0; }
        nav ul li { margin-bottom: 10px; }
        nav ul li a { 
            display: block;
            padding: 10px 15px;
            background-color: #f8f9fa;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            border-left: 5px solid #4CAF50;
            transition: all 0.3s ease;
        }
        nav ul li a:hover {
            background-color: #e9ecef;
            border-left: 5px solid #45a049;
        }
        .user-info {
            background-color: #e9ecef;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logout-btn {
            padding: 8px 15px;
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 4px;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Sistema Académico ISEF</h1>
        
        <div class="user-info">
            <div>
                <strong>Usuario:</strong> <?= htmlspecialchars($_SESSION['usuario_id']) ?> | 
                <strong>Rol:</strong> <?= htmlspecialchars($tipo) ?>
            </div>
            <a href="logout.php" class="logout-btn">Cerrar sesión</a>
        </div>
        
        <nav>
            <ul>
                <?php if ($tipo === 'administrador'): ?>
                    <li><a href="usuarios.php">Gestión de Usuarios</a></li>
                    <li><a href="profesores.php">Gestión de Profesores</a></li>
                    <li><a href="alumnos.php">Gestión de Alumnos</a></li>
                    <li><a href="materias.php">Gestión de Materias</a></li>
                    <li><a href="cursos.php">Gestión de Cursos</a></li>
                    <li><a href="asignaciones.php">Asignaciones de Profesores</a></li>
                    <li><a href="correlatividades.php">Correlatividades</a></li>
                    <li><a href="auditoria.php">Auditoría</a></li>
                <?php endif; ?>

                <?php if ($tipo === 'profesor' || $tipo === 'preceptor'): ?>
                    <li><a href="asistencias.php">Registro de Asistencias</a></li>
                    <li><a href="evaluaciones.php">Evaluaciones</a></li>
                <?php endif; ?>

                <?php if ($tipo === 'alumno'): ?>
                    <li><a href="inscripciones.php">Inscripciones</a></li>
                    <li><a href="situacion.php">Situación Académica</a></li>
                    <li><a href="certificados.php">Certificados</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</body>
</html>