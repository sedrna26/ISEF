<?php
// profesores.php - Gestión integrada de profesores (adaptado con diseño de dashboard)
session_start();
// 1. Verificación de sesión y tipo de usuario
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php"); // Asumiendo que index.php está en la raíz
    exit;
}
if ($_SESSION['tipo'] !== 'administrador') {
    $_SESSION['mensaje_error'] = "Acceso no autorizado.";
    header("Location: dashboard.php");
    exit;
}

// 2. Incluir el archivo de conexión a la base de datos
require_once '../config/db.php'; // Ajusta la ruta si es necesario

// 3. Obtener el nombre del usuario para el sidebar
$stmt_user_sidebar = $mysqli->prepare("
    SELECT CONCAT(p.apellidos ,' ', p.nombres) as nombre_completo 
    FROM persona p 
    JOIN usuario u ON p.usuario_id = u.id 
    WHERE u.id = ?
");
if ($stmt_user_sidebar) {
    $stmt_user_sidebar->bind_param("i", $_SESSION['usuario_id']);
    $stmt_user_sidebar->execute();
    $result_user_sidebar = $stmt_user_sidebar->get_result();
    $usuario_sidebar = $result_user_sidebar->fetch_assoc();
    $stmt_user_sidebar->close();
} else {
    $usuario_sidebar = ['nombre_completo' => 'Admin ISEF']; // Fallback
}

$mensaje = '';
$error = '';

// Función para generar nombre de usuario único basado en nombre y apellido (tomada de alumnos.php)
function generarUsername($nombre, $apellido, $mysqli_conn) {
    setlocale(LC_ALL, 'en_US.UTF-8');
    $nombre_norm = strtolower(trim($nombre));
    $apellido_norm = strtolower(trim($apellido));
    
    $nombre_norm = preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $nombre_norm));
    $apellido_norm = preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $apellido_norm));
    
    $baseUsername = substr($nombre_norm, 0, 1) . $apellido_norm;
    if (empty($baseUsername)) { // En caso de nombres/apellidos muy cortos o con solo caracteres especiales
        $baseUsername = 'user';
    }
    $username = $baseUsername;
    $i = 1;
    while (true) {
        $stmt_check = $mysqli_conn->prepare("SELECT id FROM usuario WHERE username = ?");
        if (!$stmt_check) { 
            error_log("Error al preparar la consulta de verificación de username: " . $mysqli_conn->error);
            return "user" . uniqid(); 
        }
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $stmt_check->close();
        
        if ($result_check->num_rows === 0) {
            return $username;
        }
        $username = $baseUsername . $i;
        $i++;
    }
}

// Procesar formulario de creación o edición de profesor
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $mysqli->begin_transaction();
        try {
            $username = generarUsername($_POST['nombres'], $_POST['apellidos'], $mysqli);
            $password_hash = password_hash($_POST['dni'], PASSWORD_DEFAULT); // Contraseña por defecto es el DNI
            $tipo_usuario = 'profesor';
            $activo = 1; // Por defecto activo
            
            $stmt_u = $mysqli->prepare("INSERT INTO usuario (username, password, tipo, activo, debe_cambiar_password) VALUES (?, ?, ?, ?, 1)"); // debe_cambiar_password = 1 (true) por defecto
            $stmt_u->bind_param("sssi", $username, $password_hash, $tipo_usuario, $activo);
            $stmt_u->execute();
            $usuario_id_new = $mysqli->insert_id;
            $stmt_u->close();

            $stmt_p = $mysqli->prepare("INSERT INTO persona (usuario_id, apellidos, nombres, dni, fecha_nacimiento, celular, domicilio, contacto_emergencia) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_p->bind_param("isssssss", $usuario_id_new, $_POST['apellidos'], $_POST['nombres'], $_POST['dni'], $_POST['fecha_nacimiento'], $_POST['celular'], $_POST['domicilio'], $_POST['contacto_emergencia']);
            $stmt_p->execute();
            $persona_id_new = $mysqli->insert_id;
            $stmt_p->close();

            $stmt_prof = $mysqli->prepare("INSERT INTO profesor (persona_id, titulo_profesional, fecha_ingreso, horas_consulta) VALUES (?, ?, ?, ?)");
            $stmt_prof->bind_param("isss", $persona_id_new, $_POST['titulo_profesional'], $_POST['fecha_ingreso'], $_POST['horas_consulta']);
            $stmt_prof->execute();
            $stmt_prof->close();
            
            $mysqli->commit();
            $_SESSION['mensaje_exito'] = "Profesor creado correctamente. Nombre de usuario: $username";
        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['mensaje_error'] = "Error al crear el profesor: " . $e->getMessage();
        }
    } elseif ($accion === 'editar') {
        $mysqli->begin_transaction();
        try {
            $stmt_p_upd = $mysqli->prepare("UPDATE persona SET apellidos = ?, nombres = ?, dni = ?, fecha_nacimiento = ?, celular = ?, domicilio = ?, contacto_emergencia = ? WHERE id = ?");
            $stmt_p_upd->bind_param("sssssssi", $_POST['apellidos'], $_POST['nombres'], $_POST['dni'], $_POST['fecha_nacimiento'], $_POST['celular'], $_POST['domicilio'], $_POST['contacto_emergencia'], $_POST['persona_id']);
            $stmt_p_upd->execute();
            $stmt_p_upd->close();

            $stmt_prof_upd = $mysqli->prepare("UPDATE profesor SET titulo_profesional = ?, fecha_ingreso = ?, horas_consulta = ? WHERE persona_id = ?");
            $stmt_prof_upd->bind_param("sssi", $_POST['titulo_profesional'], $_POST['fecha_ingreso'], $_POST['horas_consulta'], $_POST['persona_id']);
            $stmt_prof_upd->execute();
            $stmt_prof_upd->close();

            if (isset($_POST['activo'])) {
                 $activo_user = $_POST['activo'] == '1' ? 1 : 0;
                 $stmt_user_act = $mysqli->prepare("UPDATE usuario SET activo = ? WHERE id = (SELECT usuario_id FROM persona WHERE id = ?)");
                 $stmt_user_act->bind_param("ii", $activo_user, $_POST['persona_id']);
                 $stmt_user_act->execute();
                 $stmt_user_act->close();
            }
            
            $mysqli->commit();
            $_SESSION['mensaje_exito'] = "Profesor actualizado correctamente.";
        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['mensaje_error'] = "Error al actualizar el profesor: " . $e->getMessage();
        }
    } elseif ($accion === 'eliminar') {
        $mysqli->begin_transaction();
        try {
            // Obtener usuario_id para eliminarlo. ON DELETE CASCADE debería manejar persona y profesor.
            // Si no está configurado ON DELETE CASCADE para profesor->persona, y persona->usuario, se necesita borrado manual.
            // Asumimos que se quiere borrar el usuario también.
            $stmt_get_uid = $mysqli->prepare("SELECT usuario_id FROM persona WHERE id = ?");
            $stmt_get_uid->bind_param("i", $_POST['persona_id_eliminar']);
            $stmt_get_uid->execute();
            $result_uid = $stmt_get_uid->get_result();
            $row_uid = $result_uid->fetch_assoc();
            $usuario_id_del = $row_uid['usuario_id'] ?? null;
            $stmt_get_uid->close();

            if ($usuario_id_del) {
                // 1. Eliminar de profesor (si no hay ON DELETE CASCADE desde persona)
                $stmt_del_prof = $mysqli->prepare("DELETE FROM profesor WHERE persona_id = ?");
                $stmt_del_prof->bind_param("i", $_POST['persona_id_eliminar']);
                $stmt_del_prof->execute();
                $stmt_del_prof->close();

                // 2. Eliminar de persona (si no hay ON DELETE CASCADE desde usuario)
                $stmt_del_p = $mysqli->prepare("DELETE FROM persona WHERE id = ?");
                $stmt_del_p->bind_param("i", $_POST['persona_id_eliminar']);
                $stmt_del_p->execute();
                $stmt_del_p->close();
                
                // 3. Eliminar de usuario
                $stmt_del_u = $mysqli->prepare("DELETE FROM usuario WHERE id = ?");
                $stmt_del_u->bind_param("i", $usuario_id_del);
                $stmt_del_u->execute();
                $stmt_del_u->close();
            } else {
                 throw new Exception("No se encontró el usuario asociado a la persona.");
            }
            
            $mysqli->commit();
            $_SESSION['mensaje_exito'] = "Profesor eliminado correctamente.";
        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['mensaje_error'] = "Error al eliminar el profesor: " . $e->getMessage();
        }
    }
    
    header("Location: profesores.php"); // Redirigir para limpiar POST y mostrar mensajes
    exit;
}

// Recuperar mensajes de la sesión
if (isset($_SESSION['mensaje_exito'])) {
    $mensaje = $_SESSION['mensaje_exito'];
    unset($_SESSION['mensaje_exito']);
}
if (isset($_SESSION['mensaje_error'])) {
    $error = $_SESSION['mensaje_error'];
    unset($_SESSION['mensaje_error']);
}

// Obtener lista de profesores
$query_profesores = "
    SELECT 
        p.id AS persona_id,
        prof.id AS profesor_id,
        p.apellidos,
        p.nombres,
        p.dni,
        p.fecha_nacimiento,
        p.celular,
        p.domicilio,
        p.contacto_emergencia,
        u.username,
        u.activo,
        prof.titulo_profesional,
        prof.fecha_ingreso,
        prof.horas_consulta
    FROM 
        profesor prof
    JOIN 
        persona p ON prof.persona_id = p.id
    JOIN 
        usuario u ON p.usuario_id = u.id
    ORDER BY 
        p.apellidos, p.nombres
";
$resultado_profesores = $mysqli->query($query_profesores);
$lista_profesores = [];
if ($resultado_profesores) {
    while ($fila = $resultado_profesores->fetch_assoc()) {
        $lista_profesores[] = $fila;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Profesores - Sistema ISEF</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        /* Estilos base y de dashboard.php (COPIADOS DE ALUMNOS.PHP) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f8fafc; color: #334155; line-height: 1.6; }
        .app-container { display: flex; min-height: 100vh; }

        /* Sidebar Styles */
        .sidebar { width: 280px; background: white; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 1.5rem; border-bottom: 1px solid #e2e8f0; }
        .sidebar-brand { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; color: inherit; }
        .brand-icon { width: 32px; height: 32px; background: #3b82f6; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
        .brand-text h1 { font-size: 1rem; font-weight: 600; margin: 0; }
        .brand-text p { font-size: 0.75rem; color: #64748b; margin: 0; }
        .sidebar-nav { flex: 1; padding: 1rem; }
        .nav-section { margin-bottom: 2rem; }
        .nav-label { font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; padding: 0 0.75rem; }
        .nav-menu { list-style: none; }
        .nav-item { margin-bottom: 0.25rem; }
        .nav-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; color: #64748b; text-decoration: none; border-radius: 6px; transition: all 0.2s; font-size: 0.875rem; }
        .nav-link:hover { background: #f1f5f9; color: #334155; }
        .nav-link.active { background: #dbeafe; color: #1d4ed8; font-weight: 500; }
        .nav-icon { width: 16px; height: 16px; }
        .sidebar-footer { padding: 1rem; border-top: 1px solid #e2e8f0; margin-top: auto; }
        .user-info { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: #f8fafc; border-radius: 6px; margin-bottom: 0.5rem; }
        .user-avatar { width: 32px; height: 32px; background: #3b82f6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem; }
        .user-details h3 { font-size: 0.875rem; font-weight: 500; margin: 0; }
        .user-details p { font-size: 0.75rem; color: #64748b; margin: 0; }
        .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; color: #dc2626; text-decoration: none; border-radius: 6px; transition: all 0.2s; font-size: 0.875rem; border: none; background: none; width: 100%; cursor: pointer; text-align: left; }
        .logout-btn:hover { background: #fee2e2; }

        /* Main Content & Header */
        .main-content { flex: 1; display: flex; flex-direction: column; }
        .header { background: white; border-bottom: 1px solid #e2e8f0; padding: 1rem 1.5rem; display: flex; align-items: center; gap: 1rem; position: sticky; top: 0; z-index: 900; }
        .sidebar-toggle { display: none; background: none; border: none; padding: 0.5rem; cursor: pointer; border-radius: 4px; }
        .sidebar-toggle:hover { background: #f1f5f9; }
        .breadcrumb { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: #64748b; }
        .breadcrumb a { color: inherit; text-decoration: none; }
        .breadcrumb a:hover { color: #334155; }
        .header-actions { margin-left: auto; display: flex; align-items: center; gap: 1rem; }
        .header-actions .icon-btn { background: none; border: 1px solid #e2e8f0; padding: 0.5rem; border-radius: 6px; cursor: pointer; display:flex; align-items:center; justify-content:center; }
        .header-actions .icon-btn i { width: 16px; height: 16px; color: #64748b; }
        .header-actions .icon-btn:hover { background: #f1f5f9; }

        /* Content Area */
        .content { flex: 1; padding: 1.5rem; max-width: 1200px; margin: 0 auto; width: 100%; }
        .page-header { margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .page-title { font-size: 1.75rem; font-weight: 600; }
        .page-subtitle { color: #64748b; font-size: 0.9rem; margin-top: 0.25rem; }

        /* Messages */
        .message-toast { padding: 1rem; margin-bottom: 1.5rem; border-radius: 6px; border: 1px solid transparent; }
        .message-toast.success { background-color: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .message-toast.error { background-color: #fee2e2; color: #991b1b; border-color: #fecaca; }

        /* Cards */
        .card { background: white; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 1.5rem; }
        .card-header { padding: 1rem 1.5rem; border-bottom: 1px solid #e2e8f0; }
        .card-title { font-size: 1.125rem; font-weight: 600; }
        .card-description { font-size: 0.875rem; color: #64748b; margin-top:0.25rem; }
        .card-content { padding: 1.5rem; }
        .card-footer { padding: 1rem 1.5rem; border-top: 1px solid #e2e8f0; background-color: #f8fafc; text-align: right;}

        /* Forms */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.875rem; color: #334155; }
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="number"],
        .form-group input[type="search"], /* Added for search input */
        .form-group select,
        .form-group textarea {
            width: 100%; padding: 0.625rem 0.75rem; 
            border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;
            font-size: 0.875rem; color: #334155;
            background-color: white;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus, .form-group input[type="search"]:focus {
            border-color: #3b82f6; outline: none; box-shadow: 0 0 0 1px #3b82f6;
        }
        .form-group textarea { min-height: 80px; resize: vertical; }

        /* Buttons */
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 0.625rem 1rem; font-size: 0.875rem; font-weight: 500; border-radius: 6px; border: 1px solid transparent; cursor: pointer; transition: all 0.2s; text-decoration: none; white-space: nowrap; }
        .btn i { margin-right: 0.5rem; width:16px; height:16px; }
        .btn.btn-primary { background-color: #2563eb; color: white; border-color: #2563eb; }
        .btn.btn-primary:hover { background-color: #1d4ed8; border-color: #1d4ed8; }
        .btn.btn-secondary { background-color: #e2e8f0; color: #334155; border-color: #e2e8f0; }
        .btn.btn-secondary:hover { background-color: #cbd5e1; border-color: #cbd5e1; }
        .btn.btn-danger { background-color: #dc2626; color: white; border-color: #dc2626; }
        .btn.btn-danger:hover { background-color: #b91c1c; border-color: #b91c1c; }
        .btn.btn-outline { background-color: transparent; color: #475569; border: 1px solid #cbd5e1; }
        .btn.btn-outline:hover { background-color: #f8fafc; border-color: #94a3b8; }
        .btn.btn-outline.btn-danger-outline { color: #dc2626; border-color: #fecaca; }
        .btn.btn-outline.btn-danger-outline:hover { background-color: #fee2e2; color: #b91c1c; border-color: #fca5a5;}
        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.75rem; }
        .btn-sm i { margin-right: 0.25rem; width:14px; height:14px;}

        /* Tables */
        .table-container { border: 1px solid #e2e8f0; border-radius: 8px; overflow-x: auto; background: white; } /* overflow-x for responsiveness */
        table.styled-table { width: 100%; border-collapse: collapse; }
        table.styled-table th, table.styled-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 0.875rem; white-space: nowrap; /* Prevent text wrapping in cells initially */ }
        table.styled-table th { background-color: #f8fafc; color: #475569; font-weight: 600; }
        table.styled-table tr:last-child td { border-bottom: none; }
        table.styled-table tr:hover { background-color: #f1f5f9; }
        .table-actions { display: flex; gap: 0.5rem; justify-content: flex-end; }
        
        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 0.25em 0.6em; font-size: 0.75rem; font-weight: 500; border-radius: 9999px; }
        .badge i { width: 12px; height: 12px; margin-right: 0.25rem; }
        .badge-success { background-color: #dcfce7; color: #15803d; }
        .badge-danger { background-color: #fee2e2; color: #b91c1c; }
        
        /* Modal */
        .modal-content { background: white; padding: 0; border-radius: 8px; width: 100%; max-width: 800px; /* Wider modal for professor form */ max-height: 90vh; overflow-y: auto; }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -280px; transition: left 0.3s; z-index: 1000; }
            .sidebar.open { left: 0; }
            .sidebar-toggle { display: block; }
            .content { padding: 1rem; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
            .page-header .btn { margin-top:0.5rem; width:100%;}
            .form-grid { grid-template-columns: 1fr; } 
            .header-actions { display: none; }
            .modal-content { max-width: calc(100% - 2rem); }
            table.styled-table th, table.styled-table td { white-space: normal; /* Allow text wrapping on small screens */ }
        }
        .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 999; }
        .overlay.show { display: block; }
    </style>
</head>
<body>
    <div class="app-container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="sidebar-brand">
                    <div class="brand-icon"><i data-lucide="school"></i></div>
                    <div class="brand-text">
                        <h1>Sistema de Gestión ISEF</h1>
                        <p>Instituto Superior</p>
                    </div>
                </a>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-label">Navegación Principal</div>
                    <ul class="nav-menu">
                        <li class="nav-item"><a href="dashboard.php" class="nav-link"><i data-lucide="home" class="nav-icon"></i><span>Inicio</span></a></li>
                        <?php if ($_SESSION['tipo'] === 'administrador'): ?>
                            <li class="nav-item"><a href="alumnos.php" class="nav-link"><i data-lucide="graduation-cap" class="nav-icon"></i><span>Alumnos</span></a></li>
                            <li class="nav-item"><a href="profesores.php" class="nav-link"><i data-lucide="briefcase" class="nav-icon"></i><span>Profesores</span></a></li>
                            <li class="nav-item"><a href="usuarios.php" class="nav-link"><i data-lucide="users" class="nav-icon"></i><span>Usuarios</span></a></li>
                            <li class="nav-item"><a href="materias.php" class="nav-link"><i data-lucide="book-open" class="nav-icon"></i><span>Materias</span></a></li>
                            <li class="nav-item"><a href="cursos.php" class="nav-link"><i data-lucide="library" class="nav-icon"></i><span>Cursos</span></a></li>
                            <li class="nav-item"><a href="auditoria.php" class="nav-link"><i data-lucide="clipboard-list" class="nav-icon"></i><span>Auditoría</span></a></li>
                        <?php endif; ?>
                         <?php if ($_SESSION['tipo'] === 'profesor' || $_SESSION['tipo'] === 'preceptor'): ?>
                        <li class="nav-item">
                            <a href="asistencias.php" class="nav-link">
                                <i data-lucide="user-check" class="nav-icon"></i>
                                <span>Asistencias</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="evaluaciones.php" class="nav-link">
                                <i data-lucide="clipboard-check" class="nav-icon"></i>
                                <span>Evaluaciones</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($_SESSION['tipo'] === 'alumno'): ?>
                        <li class="nav-item"><a href="inscripciones.php" class="nav-link"><i data-lucide="user-plus" class="nav-icon"></i><span>Inscripciones</span></a></li>
                        <li class="nav-item"><a href="situacion.php" class="nav-link"><i data-lucide="bar-chart-3" class="nav-icon"></i><span>Situación Académica</span></a></li>
                        <li class="nav-item"><a href="certificados.php" class="nav-link"><i data-lucide="file-text" class="nav-icon"></i><span>Certificados</span></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><?= strtoupper(substr($usuario_sidebar['nombre_completo'] ?? 'A', 0, 1)) ?></div>
                    <div class="user-details">
                        <h3><?= htmlspecialchars($usuario_sidebar['nombre_completo'] ?? 'Admin Usuario') ?></h3>
                        <p><?= htmlspecialchars($_SESSION['tipo']) ?>@isef.edu</p>
                    </div>
                </div>
                <button onclick="confirmLogout()" class="logout-btn">
                    <i data-lucide="log-out" class="nav-icon"></i>
                    <span>Cerrar Sesión</span>
                </button>
            </div>
        </aside>
        <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

        <main class="main-content">
            <header class="header">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i data-lucide="menu"></i>
                </button>
                <nav class="breadcrumb">
                    <a href="dashboard.php">Sistema de Gestión ISEF</a>
                    <span>/</span>
                    <span>Profesores</span>
                </nav>
                <div class="header-actions">
                    <button class="icon-btn" title="Notificaciones">
                        <i data-lucide="bell"></i>
                    </button>
                </div>
            </header>

            <div class="content">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Gestión de Profesores</h1>
                        <p class="page-subtitle">Administra la información de los docentes del instituto.</p>
                    </div>
                    <button class="btn btn-primary" onclick="mostrarFormCreacion()">
                        <i data-lucide="plus"></i>
                        Nuevo Profesor
                    </button>
                </div>

                <?php if ($mensaje): ?>
                    <div class="message-toast success" role="alert"><?= htmlspecialchars($mensaje) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="message-toast error" role="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="card" id="creacionFormCard" style="display:none;">
                    <div class="card-header">
                        <h2 class="card-title">Registrar Nuevo Profesor</h2>
                        <p class="card-description">Completa los datos para agregar un nuevo docente.</p>
                    </div>
                    <form method="post">
                        <input type="hidden" name="accion" value="crear">
                        <div class="card-content">
                            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));"> 
                                <div class="form-group">
                                    <label for="apellidos">Apellidos:</label>
                                    <input type="text" id="apellidos" name="apellidos" required>
                                </div>
                                <div class="form-group">
                                    <label for="nombres">Nombres:</label>
                                    <input type="text" id="nombres" name="nombres" required>
                                </div>
                                <div class="form-group">
                                    <label for="dni">DNI:</label>
                                    <input type="text" id="dni" name="dni" required pattern="\d{7,8}" title="DNI debe ser 7 u 8 dígitos numéricos.">
                                </div>
                                <div class="form-group">
                                    <label for="fecha_nacimiento">Fecha de nacimiento:</label>
                                    <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required>
                                </div>
                                <div class="form-group">
                                    <label for="celular">Celular:</label>
                                    <input type="text" id="celular" name="celular">
                                </div>
                                <div class="form-group">
                                    <label for="domicilio">Domicilio:</label>
                                    <input type="text" id="domicilio" name="domicilio">
                                </div>
                                <div class="form-group">
                                    <label for="contacto_emergencia">Contacto de emergencia (Teléfono):</label>
                                    <input type="text" id="contacto_emergencia" name="contacto_emergencia">
                                </div>
                                <div class="form-group">
                                    <label for="titulo_profesional">Título profesional:</label>
                                    <input type="text" id="titulo_profesional" name="titulo_profesional" required>
                                </div>
                                <div class="form-group">
                                    <label for="fecha_ingreso">Fecha de ingreso:</label>
                                    <input type="date" id="fecha_ingreso" name="fecha_ingreso" required>
                                </div>
                                <div class="form-group" style="grid-column: span 1 / auto;"> 
                                    <label for="horas_consulta">Horas de consulta:</label>
                                    <textarea id="horas_consulta" name="horas_consulta" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="button" class="btn btn-secondary" onclick="ocultarFormCreacion()">Cancelar</button>
                            <button type="submit" class="btn btn-primary" style="margin-left:0.5rem;"><i data-lucide="save"></i>Crear Profesor</button>
                        </div>
                    </form>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Lista de Profesores Registrados</h2>
                        <p class="card-description">Visualiza y gestiona los docentes existentes.</p>
                    </div>
                     <div class="card-content" style="padding-top: 1rem; padding-bottom: 0;">
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <input type="search" id="searchInput" onkeyup="filterTableProfesores()" placeholder="Buscar por Nombre, Apellido, DNI o Título..." style="width: 100%;">
                        </div>
                        <div class="table-container">
                            <table class="styled-table">
                                <thead>
                                    <tr>
                                        <th>Nombre Completo</th>
                                        <th>DNI</th>
                                        <th>Título Profesional</th>
                                        <th>Usuario</th>
                                        <th>Celular</th>
                                        <th>Estado</th>
                                        <th class="text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($lista_profesores)): ?>
                                        <tr id="noProfesoresRow">
                                            <td colspan="7" style="text-align:center; padding: 2rem;">No hay profesores registrados.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($lista_profesores as $prof): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($prof['apellidos']) ?>, <?= htmlspecialchars($prof['nombres']) ?></td>
                                            <td><?= htmlspecialchars($prof['dni']) ?></td>
                                            <td><?= htmlspecialchars($prof['titulo_profesional']) ?></td>
                                            <td><?= htmlspecialchars($prof['username']) ?></td>
                                            <td><?= htmlspecialchars($prof['celular']) ?></td>
                                            <td>
                                                <?php if ($prof['activo']): ?>
                                                    <span class="badge badge-success"><i data-lucide="user-check"></i>Activo</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger"><i data-lucide="user-x"></i>Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="table-actions">
                                                <button class="btn btn-outline btn-sm" onclick='cargarDatosEdicionProfesor(<?= htmlspecialchars(json_encode($prof), ENT_QUOTES, 'UTF-8') ?>)' title="Editar Profesor">
                                                    <i data-lucide="edit-2"></i>
                                                </button>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('¿Está seguro de eliminar este profesor?\nEsta acción no se puede deshacer.');">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="persona_id_eliminar" value="<?= $prof['persona_id'] ?>">
                                                    <button type="submit" class="btn btn-outline btn-danger-outline btn-sm" title="Eliminar Profesor">
                                                        <i data-lucide="trash-2"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <tr id="noResultsSearchRowProfesores" style="display: none;">
                                        <td colspan="7" style="text-align:center; padding: 2rem;">No se encontraron profesores que coincidan con la búsqueda.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

           <div id="edicionFormContainerProfesor" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; display:flex; align-items: center; justify-content: center; padding: 1rem;">
                <div class="modal-content card">
                    <div class="card-header">
                        <h2 class="card-title">Editar Profesor</h2>
                        <p class="card-description">Modifica la información del docente seleccionado.</p>
                    </div>
                    <form method="post" id="form-editar-profesor">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="persona_id" id="edit-profesor-persona-id">
                        <div class="card-content">
                            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
                                <div class="form-group">
                                    <label for="edit-profesor-apellidos">Apellidos:</label>
                                    <input type="text" id="edit-profesor-apellidos" name="apellidos" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit-profesor-nombres">Nombres:</label>
                                    <input type="text" id="edit-profesor-nombres" name="nombres" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit-profesor-dni">DNI:</label>
                                    <input type="text" id="edit-profesor-dni" name="dni" required pattern="\d{7,8}" title="DNI debe ser 7 u 8 dígitos numéricos.">
                                </div>
                                <div class="form-group">
                                    <label for="edit-profesor-fecha-nacimiento">Fecha de nacimiento:</label>
                                    <input type="date" id="edit-profesor-fecha-nacimiento" name="fecha_nacimiento" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit-profesor-celular">Celular:</label>
                                    <input type="text" id="edit-profesor-celular" name="celular">
                                </div>
                                <div class="form-group">
                                    <label for="edit-profesor-domicilio">Domicilio:</label>
                                    <input type="text" id="edit-profesor-domicilio" name="domicilio">
                                </div>
                                <div class="form-group">
                                    <label for="edit-profesor-contacto-emergencia">Contacto de emergencia (Teléfono):</label>
                                    <input type="text" id="edit-profesor-contacto-emergencia" name="contacto_emergencia">
                                </div>
                                <div class="form-group">
                                    <label for="edit-profesor-titulo-profesional">Título profesional:</label>
                                    <input type="text" id="edit-profesor-titulo-profesional" name="titulo_profesional" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit-profesor-fecha-ingreso">Fecha de ingreso:</label>
                                    <input type="date" id="edit-profesor-fecha-ingreso" name="fecha_ingreso" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit-profesor-activo">Estado del Usuario:</label>
                                    <select id="edit-profesor-activo" name="activo">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                                <div class="form-group" style="grid-column: span 1 / auto;">
                                    <label for="edit-profesor-horas-consulta">Horas de consulta:</label>
                                    <textarea id="edit-profesor-horas-consulta" name="horas_consulta" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="button" class="btn btn-secondary" onclick="ocultarFormEdicionProfesor()">Cancelar</button>
                            <button type="submit" class="btn btn-primary" style="margin-left:0.5rem;"><i data-lucide="save"></i>Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>

            </div>
        </main>
    </div>

    <script>
        // Sidebar and general UI functions (copied from alumnos.php)
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        }
        
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });

        function confirmLogout() {
            if (confirm('¿Estás seguro que deseas cerrar sesión?')) {
                window.location.href = '../index.php?logout=1';
            }
        }

        const creacionFormCard = document.getElementById('creacionFormCard');
        function mostrarFormCreacion() {
            creacionFormCard.style.display = 'block';
            creacionFormCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        function ocultarFormCreacion() {
            creacionFormCard.style.display = 'none';
            document.querySelector('#creacionFormCard form').reset(); // Reset form on cancel
        }
        
        // Edit form functions specific to Profesor
        const edicionFormContainerProfesor = document.getElementById('edicionFormContainerProfesor');
        if (edicionFormContainerProfesor) { // Ensure it's hidden on load
            edicionFormContainerProfesor.style.display = 'none';
        }

        function cargarDatosEdicionProfesor(profesor) {
            document.getElementById('edit-profesor-persona-id').value = profesor.persona_id;
            document.getElementById('edit-profesor-apellidos').value = profesor.apellidos;
            document.getElementById('edit-profesor-nombres').value = profesor.nombres;
            document.getElementById('edit-profesor-dni').value = profesor.dni;
            document.getElementById('edit-profesor-fecha-nacimiento').value = profesor.fecha_nacimiento;
            document.getElementById('edit-profesor-celular').value = profesor.celular || '';
            document.getElementById('edit-profesor-domicilio').value = profesor.domicilio || '';
            document.getElementById('edit-profesor-contacto-emergencia').value = profesor.contacto_emergencia || '';
            document.getElementById('edit-profesor-titulo-profesional').value = profesor.titulo_profesional;
            document.getElementById('edit-profesor-fecha-ingreso').value = profesor.fecha_ingreso;
            document.getElementById('edit-profesor-horas-consulta').value = profesor.horas_consulta || '';
            document.getElementById('edit-profesor-activo').value = profesor.activo == '1' ? '1' : '0';

            if (edicionFormContainerProfesor) {
                edicionFormContainerProfesor.style.display = 'flex'; 
            }
            const modalContent = edicionFormContainerProfesor.querySelector('.modal-content');
            if (modalContent) {
                modalContent.scrollTop = 0; 
            }
        }
        
        function ocultarFormEdicionProfesor() {
            if (edicionFormContainerProfesor) {
                edicionFormContainerProfesor.style.display = 'none';
                document.getElementById('form-editar-profesor').reset(); // Reset form on cancel
            }
        }

        if (edicionFormContainerProfesor) {
            edicionFormContainerProfesor.addEventListener('click', function(event) {
                if (event.target === edicionFormContainerProfesor) { 
                    ocultarFormEdicionProfesor();
                }
            });
        }

        // Search filter for profesores table
        function filterTableProfesores() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toLowerCase();
            const table = document.querySelector(".styled-table"); // Assuming only one styled-table for profesores list
            const tbody = table.getElementsByTagName("tbody")[0];
            const tr = tbody.getElementsByTagName("tr");
            let foundMatch = false;

            const noProfesoresRow = document.getElementById('noProfesoresRow');
            const noResultsSearchRow = document.getElementById('noResultsSearchRowProfesores');

            if (noProfesoresRow) noProfesoresRow.style.display = 'none';
            if (noResultsSearchRow) noResultsSearchRow.style.display = 'none';

            for (let i = 0; i < tr.length; i++) {
                let row = tr[i];
                if (row.id === 'noProfesoresRow' || row.id === 'noResultsSearchRowProfesores') {
                    continue;
                }
                let displayRow = false;
                const nombreCompletoTd = row.cells[0];
                const dniTd = row.cells[1];
                const tituloTd = row.cells[2];

                if (nombreCompletoTd && dniTd && tituloTd) {
                    const nombreCompletoText = nombreCompletoTd.textContent || nombreCompletoTd.innerText;
                    const dniText = dniTd.textContent || dniTd.innerText;
                    const tituloText = tituloTd.textContent || tituloTd.innerText;

                    if (nombreCompletoText.toLowerCase().indexOf(filter) > -1 ||
                        dniText.toLowerCase().indexOf(filter) > -1 ||
                        tituloText.toLowerCase().indexOf(filter) > -1) {
                        displayRow = true;
                        foundMatch = true;
                    }
                }
                row.style.display = displayRow ? "" : "none";
            }

            const isListaProfesoresEmpty = <?php echo empty($lista_profesores) ? 'true' : 'false'; ?>;

            if (filter === "") {
                if (isListaProfesoresEmpty && noProfesoresRow) {
                    noProfesoresRow.style.display = '';
                }
                if (noResultsSearchRow) {
                     noResultsSearchRow.style.display = 'none';
                }
            } else {
                if (!foundMatch && noResultsSearchRow) {
                    noResultsSearchRow.style.display = '';
                }
                if (noProfesoresRow){ // Should always be hidden if filter is active
                    noProfesoresRow.style.display = 'none';
                }
            }
        }
        
        // Create icons after DOM is ready
        lucide.createIcons();
    </script>
</body>
</html>