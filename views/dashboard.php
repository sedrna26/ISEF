<?php
// dashboard.php - Menú principal tras login
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

// Conectar a la base de datos
$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

// Verificar si el usuario debe cambiar contraseña
$stmt = $mysqli->prepare("SELECT debe_cambiar_password FROM usuario WHERE id = ?");
$stmt->bind_param("i", $_SESSION['usuario_id']);
$stmt->execute();
$result = $stmt->get_result();
$usuario_data = $result->fetch_assoc();
$stmt->close();

// Si debe cambiar contraseña, redirigir
if ($usuario_data['debe_cambiar_password']) {
    header("Location: cambiar_password.php");
    exit;
}

// Obtener el nombre del usuario
$stmt = $mysqli->prepare("
    SELECT CONCAT(p.apellidos ,' ', p.nombres) as nombre_completo 
    FROM persona p 
    JOIN usuario u ON p.usuario_id = u.id 
    WHERE u.id = ?
");
$stmt->bind_param("i", $_SESSION['usuario_id']);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();
$stmt->close();

$mysqli->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Administración - ISEF</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background-color: #f4f4f4;
        }
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 20px;
        }
        .menu-item { 
            display: block; 
            padding: 15px;
            margin: 10px 0;
            background-color: #f8f9fa;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
            border: 1px solid #ddd;
            transition: background-color 0.3s;
        }
        .menu-item:hover {
            background-color: #e9ecef;
        }
        .logout {
            color: #dc3545;
            text-decoration: none;
            padding: 10px 15px;
            border: 1px solid #dc3545;
            border-radius: 4px;
            transition: all 0.3s;
        }
        .logout:hover {
            background-color: #dc3545;
            color: white;
        }
        .message {
            padding: 10px;
            margin: 15px 0;
            border-radius: 4px;
            text-align: center;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .welcome {
            color: #28a745;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Panel de Administración</h1>
            <a href="../index.php?logout=1" class="logout">Cerrar sesión</a>
        </div>
        
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="message success"><?= htmlspecialchars($_SESSION['mensaje']) ?></div>
            <?php unset($_SESSION['mensaje']); // Limpiar el mensaje después de mostrarlo ?>
        <?php endif; ?>
        
        <h2 class="welcome">Bienvenido, <?= htmlspecialchars($usuario['nombre_completo'] ?? $_SESSION['usuario_id']) ?></h2>

        <?php if ($_SESSION['tipo'] === 'administrador'): ?>
        <div class="menu">
            <a href="usuarios.php" class="menu-item">
                <strong>Gestión de Usuarios</strong><br>
                <small>Crear, editar y administrar usuarios del sistema</small>
            </a>
            <a href="materias.php" class="menu-item">
                <strong>Gestión de Materias</strong><br>
                <small>Administrar materias y asignaturas</small>
            </a>
            <a href="cursos.php" class="menu-item">
                <strong>Gestión de Cursos</strong><br>
                <small>Crear y administrar cursos académicos</small>
            </a>
            <a href="asignaciones.php" class="menu-item">
                <strong>Asignaciones de Profesores</strong><br>
                <small>Asignar profesores a materias y cursos</small>
            </a>
            <a href="correlatividades.php" class="menu-item">
                <strong>Correlatividades</strong><br>
                <small>Gestionar correlatividades entre materias</small>
            </a>
            <a href="auditoria.php" class="menu-item">
                <strong>Auditoría</strong><br>
                <small>Revisar logs y actividad del sistema</small>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($_SESSION['tipo'] === 'profesor' || $_SESSION['tipo'] === 'preceptor'): ?>
        <div class="menu">
            <a href="asistencias.php" class="menu-item">
                <strong>Registro de Asistencias</strong><br>
                <small>Registrar y consultar asistencias de estudiantes</small>
            </a>
            <a href="evaluaciones.php" class="menu-item">
                <strong>Evaluaciones</strong><br>
                <small>Gestionar evaluaciones y calificaciones</small>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($_SESSION['tipo'] === 'alumno'): ?>
        <div class="menu">
            <a href="inscripciones.php" class="menu-item">
                <strong>Inscripciones</strong><br>
                <small>Inscribirse a materias y cursos</small>
            </a>
            <a href="situacion.php" class="menu-item">
                <strong>Situación Académica</strong><br>
                <small>Consultar estado académico y calificaciones</small>
            </a>
            <a href="certificados.php" class="menu-item">
                <strong>Certificados</strong><br>
                <small>Solicitar y descargar certificados</small>
            </a>
        </div>
        
        <?php endif; ?>
    </div>
</body>
</html>