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
function generarUsername($nombre, $apellido, $mysqli_conn)
{
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'asignar_materia') {
    $profesor_persona_id = intval($_POST['profesor_persona_id']);
    $materia_id = intval($_POST['materia_id']);
    $curso_id = intval($_POST['curso_id']);
    $ciclo_lectivo = trim($_POST['ciclo_lectivo']);

    if ($profesor_persona_id && $materia_id && $curso_id && $ciclo_lectivo) {
        // Obtener ID del profesor a partir del persona_id
        $stmt_prof = $mysqli->prepare("SELECT id FROM profesor WHERE persona_id = ?");
        $stmt_prof->bind_param("i", $profesor_persona_id);
        $stmt_prof->execute();
        $stmt_prof->bind_result($profesor_id);
        $stmt_prof->fetch();
        $stmt_prof->close();

        if ($profesor_id) {
            // Verificar si ya existe la asignación
            $stmt_check = $mysqli->prepare("SELECT COUNT(*) FROM profesor_materia WHERE profesor_id = ? AND materia_id = ? AND curso_id = ? AND ciclo_lectivo = ?");
            $stmt_check->bind_param("iiis", $profesor_id, $materia_id, $curso_id, $ciclo_lectivo);
            $stmt_check->execute();
            $stmt_check->bind_result($existe);
            $stmt_check->fetch();
            $stmt_check->close();

            if ($existe > 0) {
                $_SESSION['mensaje_error'] = "La asignación ya existe.";
            } else {
                // Insertar la asignación
                $stmt_insert = $mysqli->prepare("INSERT INTO profesor_materia (profesor_id, materia_id, curso_id, ciclo_lectivo) VALUES (?, ?, ?, ?)");
                $stmt_insert->bind_param("iiis", $profesor_id, $materia_id, $curso_id, $ciclo_lectivo);
                if ($stmt_insert->execute()) {
                    $_SESSION['mensaje_exito'] = "Materia y curso asignados correctamente.";
                } else {
                    $_SESSION['mensaje_error'] = "Error al asignar materia/curso.";
                }
                $stmt_insert->close();
            }
        } else {
            $_SESSION['mensaje_error'] = "No se encontró el profesor.";
        }
    } else {
        $_SESSION['mensaje_error'] = "Complete todos los campos para asignar.";
    }

    header("Location: profesores.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'editar_asignacion') {
    $id_profesor_materia = intval($_POST['id_profesor_materia'] ?? 0);
    $materia_id = intval($_POST['materia_id'] ?? 0);
    $curso_id = intval($_POST['curso_id'] ?? 0);
    $ciclo_lectivo = trim($_POST['ciclo_lectivo'] ?? '');
    if (strlen($ciclo_lectivo) === 0) {
        // falla el chequeo
    }

    var_dump($_POST);
    var_dump($id_profesor_materia, $materia_id, $curso_id, $ciclo_lectivo);
    exit;

    if ($id_profesor_materia > 0 && $materia_id > 0 && $curso_id > 0 && strlen($ciclo_lectivo) > 0) {
        $stmt_check = $mysqli->prepare("SELECT COUNT(*) FROM profesor_materia WHERE id = ?");
        $stmt_check->bind_param("i", $id_profesor_materia);
        $stmt_check->execute();
        $stmt_check->bind_result($existe);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($existe > 0) {
            $stmt_update = $mysqli->prepare("UPDATE profesor_materia SET materia_id = ?, curso_id = ?, ciclo_lectivo = ? WHERE id = ?");
            if (!$stmt_update) {
                $_SESSION['mensaje_error'] = "Error en prepare: " . $mysqli->error;
            } else {
                $stmt_update->bind_param("isis", $materia_id, $curso_id, $ciclo_lectivo, $id_profesor_materia);
                if ($stmt_update->execute()) {
                    $_SESSION['mensaje_exito'] = "Asignación actualizada correctamente.";
                } else {
                    $_SESSION['mensaje_error'] = "Error al actualizar la asignación: " . $stmt_update->error;
                }
                $stmt_update->close();
            }
        } else {
            $_SESSION['mensaje_error'] = "No se encontró la asignación a editar.";
        }
    } else {
        $_SESSION['mensaje_error'] = "Complete todos los campos para editar la asignación.";
    }

    header("Location: profesores.php");
    exit;
}

// Procesar formulario de creación o edición de profesor
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $mysqli->begin_transaction();
        try {

            // --- Manejo de la foto ---
            $foto_url = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $fotoTmp = $_FILES['foto']['tmp_name'];
                $fotoNombre = basename($_FILES['foto']['name']);
                $ext = strtolower(pathinfo($fotoNombre, PATHINFO_EXTENSION));
                $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array($ext, $permitidas)) {
                    // Ruta absoluta desde la raíz del proyecto
                    $carpeta = realpath(__DIR__ . '/../uploads/fotos_usuarios');
                    if ($carpeta === false || !is_dir($carpeta)) {
                        throw new Exception("No existe la carpeta de fotos de usuario.");
                    }
                    $nuevoNombre = 'foto_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $destino = $carpeta . DIRECTORY_SEPARATOR . $nuevoNombre;
                    if (move_uploaded_file($fotoTmp, $destino)) {
                        // Guardar la ruta relativa para la base de datos
                        $foto_url = 'uploads/fotos_usuarios/' . $nuevoNombre;
                    } else {
                        throw new Exception("No se pudo guardar la foto.");
                    }
                } else {
                    throw new Exception("Formato de foto no permitido. Solo jpg, jpeg, png, gif, webp.");
                }
            }

            $username = generarUsername($_POST['nombres'], $_POST['apellidos'], $mysqli);
            $password_hash = password_hash($_POST['dni'], PASSWORD_DEFAULT); // Contraseña por defecto es el DNI
            $tipo_usuario = 'profesor';
            $activo = 1; // Por defecto activo

            $stmt_u = $mysqli->prepare("INSERT INTO usuario (username, password, tipo, activo, debe_cambiar_password) VALUES (?, ?, ?, ?, 1)"); // debe_cambiar_password = 1 (true) por defecto
            $stmt_u->bind_param("sssi", $username, $password_hash, $tipo_usuario, $activo);
            $stmt_u->execute();
            $usuario_id_new = $mysqli->insert_id;
            $stmt_u->close();

            $stmt_p = $mysqli->prepare("INSERT INTO persona (usuario_id, apellidos, nombres, dni, fecha_nacimiento, celular, domicilio, contacto_emergencia, foto_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_p->bind_param("issssssss", $usuario_id_new, $_POST['apellidos'], $_POST['nombres'], $_POST['dni'], $_POST['fecha_nacimiento'], $_POST['celular'], $_POST['domicilio'], $_POST['contacto_emergencia'], $foto_url);
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
            // --- Manejo de la foto (edición) ---
            $foto_url = null;
            $foto_actual = null;

            // Obtener la foto actual de la persona
            $stmt_foto = $mysqli->prepare("SELECT foto_url FROM persona WHERE id = ?");
            $stmt_foto->bind_param("i", $_POST['persona_id']);
            $stmt_foto->execute();
            $stmt_foto->bind_result($foto_actual);
            $stmt_foto->fetch();
            $stmt_foto->close();

            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $fotoTmp = $_FILES['foto']['tmp_name'];
                $fotoNombre = basename($_FILES['foto']['name']);
                $ext = strtolower(pathinfo($fotoNombre, PATHINFO_EXTENSION));
                $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array($ext, $permitidas)) {
                    $carpeta = realpath(__DIR__ . '/../uploads/fotos_usuarios');
                    if ($carpeta === false || !is_dir($carpeta)) {
                        throw new Exception("No existe la carpeta de fotos de usuario.");
                    }
                    $nuevoNombre = 'foto_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $destino = $carpeta . DIRECTORY_SEPARATOR . $nuevoNombre;
                    if (move_uploaded_file($fotoTmp, $destino)) {
                        $foto_url = 'uploads/fotos_usuarios/' . $nuevoNombre;
                        // Eliminar la foto anterior si existe y es diferente
                        if ($foto_actual && file_exists(realpath(__DIR__ . '/../' . $foto_actual)) && $foto_actual !== $foto_url) {
                            @unlink(realpath(__DIR__ . '/../' . $foto_actual));
                        }
                    } else {
                        throw new Exception("No se pudo guardar la nueva foto.");
                    }
                } else {
                    throw new Exception("Formato de foto no permitido. Solo jpg, jpeg, png, gif, webp.");
                }
            } else {
                // No se subió nueva foto, mantener la existente
                $foto_url = $foto_actual;
            }

            $stmt_p_upd = $mysqli->prepare("UPDATE persona SET apellidos = ?, nombres = ?, dni = ?, fecha_nacimiento = ?, celular = ?, domicilio = ?, contacto_emergencia = ?, foto_url = ? WHERE id = ?");
            $stmt_p_upd->bind_param("ssssssssi", $_POST['apellidos'], $_POST['nombres'], $_POST['dni'], $_POST['fecha_nacimiento'], $_POST['celular'], $_POST['domicilio'], $_POST['contacto_emergencia'], $foto_url, $_POST['persona_id']);
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

// --- PAGINACIÓN Y FILTRO DE REGISTROS ---
$registros_por_pagina = isset($_GET['registros']) && in_array($_GET['registros'], [10, 20, 50, 100]) ? intval($_GET['registros']) : 10;
$pagina_actual = isset($_GET['pagina']) && intval($_GET['pagina']) > 0 ? intval($_GET['pagina']) : 1;

// Contar total de profesores
$query_total = "SELECT COUNT(*) as total FROM profesor prof JOIN persona p ON prof.persona_id = p.id JOIN usuario u ON p.usuario_id = u.id";
$result_total = $mysqli->query($query_total);
$total_registros = $result_total ? intval($result_total->fetch_assoc()['total']) : 0;
$total_paginas = max(1, ceil($total_registros / $registros_por_pagina));
$offset = ($pagina_actual - 1) * $registros_por_pagina;

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
        p.foto_url,
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
        p.id DESC
    LIMIT $registros_por_pagina OFFSET $offset
";
$resultado_profesores = $mysqli->query($query_profesores);
$lista_profesores = [];
if ($resultado_profesores) {
    while ($fila = $resultado_profesores->fetch_assoc()) {
        $profesor_id = $fila['profesor_id'];

        // Obtener todas las asignaciones de materia/curso/ciclo_lectivo para este profesor
        $sql_asig = "SELECT m.nombre AS materia, c.codigo AS curso, pm.ciclo_lectivo
                     FROM profesor_materia pm
                     JOIN materia m ON pm.materia_id = m.id
                     JOIN curso c ON pm.curso_id = c.id
                     WHERE pm.profesor_id = $profesor_id
                     ORDER BY pm.id DESC";

        $res_asig = $mysqli->query($sql_asig);

        $asignaciones = [];
        if ($res_asig) {
            while ($row_asig = $res_asig->fetch_assoc()) {
                $asignaciones[] = [
                    'materia' => $row_asig['materia'],
                    'curso' => $row_asig['curso'],
                    'ciclo_lectivo' => $row_asig['ciclo_lectivo']
                ];
            }
        }

        $fila['asignaciones'] = $asignaciones;

        // Opcional: si quieres dejar también los campos planos con la última asignación
        if (count($asignaciones) > 0) {
            $fila['materia'] = $asignaciones[0]['materia'];
            $fila['curso'] = $asignaciones[0]['curso'];
            $fila['ciclo_lectivo'] = $asignaciones[0]['ciclo_lectivo'];
        } else {
            $fila['materia'] = null;
            $fila['curso'] = null;
            $fila['ciclo_lectivo'] = null;
        }

        $lista_profesores[] = $fila;
    }
}
// Antes de renderizar el modal o la página
$materias = [];
$result = $mysqli->query("SELECT id, codigo, nombre, tipo, anio, nro_orden, cuatrimestre FROM materia ORDER BY id DESC");
while ($row = $result->fetch_assoc()) {
    $materias[] = $row;
}

$cursos = [];
$result = $mysqli->query("SELECT id, codigo, division, anio, turno, ciclo_lectivo FROM curso ORDER BY id DESC");
while ($row = $result->fetch_assoc()) {
    $cursos[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Profesores - Sistema ISEF</title>
    <link rel="icon" href="../sources/logoo.ico" type="image/x-icon">
    <link rel="stylesheet" href="../style/profesor.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        #info-profesor-foto img {
            max-width: 300px !important;
            max-height: 300px !important;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.07);
        }
    </style>
</head>

<body>
    <div class="app-container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="../views/dashboard.php" class="sidebar-brand">
                    <img src="../../ISEF/sources/logo.jpg" alt="No Logo" style="width: 50px; height: 50px; margin-bottom: 20px;">
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
                            <li class="nav-item"><a href="profesores.php" class="nav-link"><i data-lucide="briefcase" class="nav-icon" class="nav-link active"></i><span>Profesores</span></a></li>
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
                    <!-- Botón para mostrar el formulario de creación -->
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
                    <form method="post" enctype="multipart/form-data">
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
                                <div class="form-group" style="grid-column: 1 / -1; text-align: center;">
                                    <label for="foto" style="display: block; margin-bottom: 0.5rem;">Foto:</label>
                                    <input type="file" id="foto" name="foto" accept="image/*" style="display: inline-block;">
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
                        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group" style="flex: 1 1 300px; margin-bottom: 0;">
                                <input type="search" id="searchInput" onkeyup="filterTableProfesores()" placeholder="Buscar por Nombre, Apellido, DNI o Título..." style="width: 100%; height: 2.5rem; padding: 0.625rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 0.875rem;">
                            </div>
                            <form method="get" style="margin-bottom: 0;">
                                <label for="registros" style="font-size: 0.9rem; color: #64748b; margin-right: 0.5rem;">Mostrar</label>
                                <select name="registros" id="registros" onchange="this.form.submit()" style="padding: 0.4rem 0.7rem; border-radius: 6px; border: 1px solid #cbd5e1;">
                                    <option value="10" <?= $registros_por_pagina == 10 ? 'selected' : '' ?>>10</option>
                                    <option value="20" <?= $registros_por_pagina == 20 ? 'selected' : '' ?>>20</option>
                                    <option value="50" <?= $registros_por_pagina == 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= $registros_por_pagina == 100 ? 'selected' : '' ?>>100</option>
                                </select>
                                <input type="hidden" name="pagina" value="1">
                            </form>
                            <div style="font-size: 0.95rem; color: #64748b;">
                                <?php
                                $desde = $total_registros > 0 ? ($offset + 1) : 0;
                                $hasta = $offset + count($lista_profesores);
                                ?>
                                Mostrando <?= $desde ?>-<?= $hasta ?> de <?= $total_registros ?> profesores
                            </div>
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
                                        <th class="text-end">Acciones</th>
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
                                                    <button class="btn btn-outline btn-sm" onclick='cargarDatosEdicionProfesor(<?= htmlspecialchars(json_encode($prof), ENT_QUOTES, "UTF-8") ?>)' title="Editar Profesor">
                                                        <i data-lucide="edit-2"></i>
                                                    </button>
                                                    <button
                                                        class="btn btn-outline btn-sm"
                                                        data-profesor='<?php echo json_encode($prof, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>'
                                                        onclick="verInformacionProfesorDesdeBoton(this)"
                                                        title="Ver Información">
                                                        <i data-lucide="eye"></i>
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
                            <div style="display: flex; justify-content: center; align-items: center; margin: 1rem 0;">
                                <nav aria-label="Paginación de profesores">
                                    <ul style="display: flex; list-style: none; gap: 0.25rem; padding: 0; margin: 0;">
                                        <?php
                                        $url_base = $_SERVER['PHP_SELF'] . '?registros=' . $registros_por_pagina;
                                        $max_links = 5;
                                        $start = max(1, $pagina_actual - floor($max_links / 2));
                                        $end = min($total_paginas, $start + $max_links - 1);
                                        $start = max(1, $end - $max_links + 1);

                                        if ($pagina_actual > 1): ?>
                                            <li><a class="btn btn-sm btn-outline" href="<?= $url_base ?>&pagina=1">&laquo;</a></li>
                                            <li><a class="btn btn-sm btn-outline" href="<?= $url_base ?>&pagina=<?= $pagina_actual - 1 ?>">&lt;</a></li>
                                        <?php endif;
                                        for ($i = $start; $i <= $end; $i++): ?>
                                            <li>
                                                <a class="btn btn-sm <?= $i == $pagina_actual ? 'btn-primary' : 'btn-outline' ?>" href="<?= $url_base ?>&pagina=<?= $i ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor;
                                        if ($pagina_actual < $total_paginas): ?>
                                            <li><a class="btn btn-sm btn-outline" href="<?= $url_base ?>&pagina=<?= $pagina_actual + 1 ?>">&gt;</a></li>
                                            <li><a class="btn btn-sm btn-outline" href="<?= $url_base ?>&pagina=<?= $total_paginas ?>">&raquo;</a></li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal de edición de profesor -->
                <div id="edicionFormContainerProfesor" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
                    <div class="modal-content card">
                        <div class="card-header">
                            <h2 class="card-title">Editar Profesor</h2>
                            <p class="card-description">Modifica la información del docente seleccionado.</p>
                        </div>
                        <form method="post" id="form-editar-profesor" enctype="multipart/form-data">
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
                                    <div class="form-group" style="grid-column: 1 / -1; text-align: center;">
                                        <label style="display: block; margin-bottom: 0.5rem;">Foto actual:</label>
                                        <div id="edit-profesor-foto-actual" style="margin-bottom: 0.5rem;">
                                            <span style="color:#64748b;">Sin foto</span>
                                        </div>
                                        <label for="edit-profesor-foto-nueva" style="display: block; margin-bottom: 0.5rem;">Nueva foto (opcional):</label>
                                        <input type="file" id="edit-profesor-foto-nueva" name="foto" accept="image/*" onchange="mostrarPreviewFotoProfesor(this)">
                                        <div id="preview-foto-profesor" style="margin-top: 0.5rem;"></div>
                                    </div>
                                    <script>
                                        function mostrarPreviewFotoProfesor(input) {
                                            const previewDiv = document.getElementById('preview-foto-profesor');
                                            previewDiv.innerHTML = '';
                                            if (input.files && input.files[0]) {
                                                const reader = new FileReader();
                                                reader.onload = function(e) {
                                                    previewDiv.innerHTML = '<img src="' + e.target.result + '" alt="Foto seleccionada" style="max-width:180px;max-height:180px;border-radius:8px;border:1px solid #e2e8f0;">';
                                                };
                                                reader.readAsDataURL(input.files[0]);
                                            }
                                        }
                                    </script>

                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="button" class="btn btn-secondary" onclick="ocultarFormEdicionProfesor()">Cancelar</button>
                                <button type="submit" class="btn btn-primary" style="margin-left:0.5rem;"><i data-lucide="save"></i>Guardar Cambios</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- MODAL: Información del Profesor -->
                <div id="infoProfesorModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
                    <div class="modal-content card" style="max-width: 800px;">
                        <div class="card-header">
                            <h2 class="card-title">Datos del Profesor</h2>
                            <p class="card-description">Información detallada del docente seleccionado.</p>
                        </div>
                        <div class="card-content" style="padding-top: 1rem;">
                            <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                                <!-- Foto -->
                                <div class="form-group" style="grid-column: 1 / -1; text-align: center;">
                                    <label style="display: block; margin-bottom: 0.5rem;">Foto:</label>
                                    <div id="info-profesor-foto" style="display: inline-block; font-weight: 500;"></div>
                                </div>
                                <!-- ID -->
                                <div class="form-group" style="grid-column: 1 / -1; text-align: center;">
                                    <label>ID:</label>
                                    <div id="info-profesor-id" style="font-weight: 500;"></div>
                                </div>

                                <!-- Botón para mostrar el formulario -->
                                <div class="form-group" style="grid-column: 1 / -1; text-align: center; margin-top: 1rem;">
                                    <button type="button" class="btn btn-primary" onclick="abrirAsignarMateriaForm()" id="btnAsignarMateria">
                                        <i data-lucide="book-plus"></i> Asignar Materia/Curso
                                    </button>
                                </div>

                                <!-- Formulario de asignación -->
                                <div class="form-group" id="asignarMateriaForm" style="grid-column: 1 / -1; display:none; margin-top:1rem;">
                                    <form method="post" id="form-asignar-materia" action="">
                                        <input type="hidden" name="accion" value="asignar_materia">
                                        <input type="hidden" name="profesor_persona_id" id="asignar-profesor-persona-id">
                                        <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                                            <!-- Materia -->
                                            <div class="form-group">
                                                <label for="materia_id">Materia:</label>
                                                <select class="form-control" name="materia_id" id="editar-materia" required>
                                                    <option value="0" disabled selected>Seleccione una materia</option>
                                                    <?php foreach ($materias as $materias): ?>
                                                        <option value="<?= $materias['id'] ?>"><?= htmlspecialchars($materias['nombre']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <!-- Curso -->
                                            <div class="form-group">
                                                <label for="curso_id">Curso:</label>
                                                <select class="form-control" name="curso_id" id="editar-curso" required>
                                                    <option value="0" disabled selected>Seleccione un curso</option>
                                                    <?php foreach ($cursos as $c): ?>
                                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['codigo'])  ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <!-- Ciclo lectivo -->
                                            <div class="form-group">
                                                <label for="ciclo_lectivo">Ciclo lectivo:</label>
                                                <input type="text" name="ciclo_lectivo" id="ciclo_lectivo" placeholder="Ej: 2025" required>
                                            </div>
                                        </div>
                                        <!-- Botones -->
                                        <div style="text-align:right; margin-top:1rem;">
                                            <button type="button" class="btn btn-secondary" onclick="cerrarAsignarMateriaForm()">Cancelar</button>
                                            <button type="submit" class="btn btn-primary" style="margin-left:0.5rem;">
                                                <i data-lucide="save"></i> Asignar
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Datos personales del profesor -->
                                <div class="form-group"><label>Apellidos:</label>
                                    <div id="info-profesor-apellidos"></div>
                                </div>
                                <div class="form-group"><label>Nombres:</label>
                                    <div id="info-profesor-nombres"></div>
                                </div>
                                <div class="form-group"><label>DNI:</label>
                                    <div id="info-profesor-dni"></div>
                                </div>
                                <div class="form-group"><label>Fecha de nacimiento:</label>
                                    <div id="info-profesor-fecha-nacimiento"></div>
                                </div>
                                <div class="form-group"><label>Celular:</label>
                                    <div id="info-profesor-celular"></div>
                                </div>
                                <div class="form-group"><label>Domicilio:</label>
                                    <div id="info-profesor-domicilio"></div>
                                </div>
                                <div class="form-group"><label>Contacto de emergencia:</label>
                                    <div id="info-profesor-contacto-emergencia"></div>
                                </div>
                                <div class="form-group"><label>Título profesional:</label>
                                    <div id="info-profesor-titulo-profesional"></div>
                                </div>
                                <div class="form-group"><label>Fecha de ingreso:</label>
                                    <div id="info-profesor-fecha-ingreso"></div>
                                </div>
                                <div class="form-group"><label>Horas de consulta:</label>
                                    <div id="info-profesor-horas-consulta"></div>
                                </div>
                                <div class="form-group"><label>Usuario:</label>
                                    <div id="info-profesor-username"></div>
                                </div>
                                <div class="form-group"><label>Estado:</label>
                                    <div id="info-profesor-estado"></div>
                                </div>
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label style="font-weight: 640; font-size: 1.1rem;">Asignaciones:</label>
                                    <div style="overflow-x: auto; margin-top: 1rem; border: 1px solid #dee2e6; border-radius: 0.8rem; text-align: center;">
                                        <table class="table table-bordered table-striped table-hover align-middle" style="width: 100%; margin: 0;">
                                            <thead style="background-color: #f8f9fa;">
                                                <tr class="text-center">
                                                    <th class="text-center" style="padding: 0.75rem; text-align: center;">Materia</th>
                                                    <th class="text-center" style="padding: 0.75rem; text-align: center;">Curso</th>
                                                    <th class="text-center" style="padding: 0.75rem; text-align: center;">Ciclo Lectivo</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tabla-asignaciones-profesor" style="background-color: #ffffff;"></tbody>
                                        </table>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 1rem;">
                            <button type="button" class="btn btn-secondary" onclick="ocultarInfoProfesor()">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Función que toma el botón con el atributo data-profesor y llama a verInformacionProfesor
        function verInformacionProfesorDesdeBoton(boton) {
            const profesorData = boton.getAttribute('data-profesor');
            if (!profesorData) {
                alert("No se encontraron datos del profesor.");
                return;
            }

            try {
                const profesor = JSON.parse(profesorData);
                verInformacionProfesor(profesor);
            } catch (e) {
                console.error("Error al parsear los datos del profesor:", e);
                alert("Hubo un problema al mostrar los datos del profesor.");
            }
        }
        // Variable global para el profesor seleccionado
        let profesorSeleccionado = null;

        // Mostrar el formulario y setear ID
        function abrirAsignarMateriaForm() {
            if (profesorSeleccionado) {
                document.getElementById('asignar-profesor-persona-id').value = profesorSeleccionado.persona_id || '';
            }
            document.getElementById('asignarMateriaForm').style.display = 'block';
        }

        // Ocultar el formulario y limpiar
        function cerrarAsignarMateriaForm() {
            document.getElementById('asignarMateriaForm').style.display = 'none';
            document.getElementById('form-asignar-materia').reset();
        }

        function verInformacionProfesor(profesor) {
            // --- INTEGRACIÓN PARA ASIGNACIÓN ---
            profesorSeleccionado = profesor;
            const campoPersonaId = document.getElementById('asignar-profesor-persona-id');
            if (campoPersonaId) campoPersonaId.value = profesor.persona_id || '';
            // -----------------------------------

            const infoProfesorModal = document.getElementById('infoProfesorModal');
            const fotoDiv = document.getElementById('info-profesor-foto');
            if (profesor.foto_url && profesor.foto_url.trim() !== "") {
                let ruta = profesor.foto_url;
                if (!/^https?:\/\//.test(ruta) && ruta[0] !== '/') {
                    ruta = '../' + ruta;
                }
                fotoDiv.innerHTML =
                    `<img src="${ruta}" alt="Foto del profesor" style="max-width:120px;max-height:120px;border-radius:8px;border:1px solid #e2e8f0;">`;
            } else {
                fotoDiv.innerHTML = '<span style="color:#64748b;">Sin foto</span>';
            }

            document.getElementById('info-profesor-apellidos').textContent = profesor.apellidos || '';
            document.getElementById('info-profesor-id').textContent = profesor.profesor_id || '';
            document.getElementById('info-profesor-nombres').textContent = profesor.nombres || '';
            document.getElementById('info-profesor-dni').textContent = profesor.dni || '';
            document.getElementById('info-profesor-fecha-nacimiento').textContent = profesor.fecha_nacimiento || '';
            document.getElementById('info-profesor-celular').textContent = profesor.celular || '';
            document.getElementById('info-profesor-domicilio').textContent = profesor.domicilio || '';
            document.getElementById('info-profesor-contacto-emergencia').textContent = profesor.contacto_emergencia || '';
            document.getElementById('info-profesor-titulo-profesional').textContent = profesor.titulo_profesional || '';
            document.getElementById('info-profesor-fecha-ingreso').textContent = profesor.fecha_ingreso || '';
            document.getElementById('info-profesor-horas-consulta').textContent = profesor.horas_consulta || '';
            document.getElementById('info-profesor-username').textContent = profesor.username || '';

            function rellenarTablaAsignaciones(asignaciones) {
                const tbody = document.getElementById('tabla-asignaciones-profesor');
                tbody.innerHTML = '';

                if (!Array.isArray(asignaciones) || asignaciones.length === 0) {
                    const fila = document.createElement('tr');
                    fila.innerHTML = `<td colspan="4">No hay asignaciones registradas.</td>`;
                    tbody.appendChild(fila);
                    return;
                }

                asignaciones.forEach(asignacion => {
                    const fila = document.createElement('tr');

                    fila.innerHTML = `
                        <td class="font-medium text-center">${asignacion.materia}</td>
                        <td class="font-medium text-center">${asignacion.curso}</td>
                        <td class="font-medium text-center">${asignacion.ciclo_lectivo}</td>
                            `;

                    tbody.appendChild(fila);
                });

                lucide.createIcons(); // Actualiza los íconos
            }

            const asignaciones = profesor.asignaciones || [];
            rellenarTablaAsignaciones(asignaciones);
            document.getElementById('info-profesor-estado').innerHTML = profesor.activo == '1' ?
                '<span class="badge badge-success"><i data-lucide="user-check"></i>Activo</span>' :
                '<span class="badge badge-danger"><i data-lucide="user-x"></i>Inactivo</span>';

            if (infoProfesorModal) {
                infoProfesorModal.style.display = 'flex';
                const modalContent = infoProfesorModal.querySelector('.modal-content');
                if (modalContent) modalContent.scrollTop = 0;
                lucide.createIcons();
            }
        }

        // Updated editarAsignacion function
        function editarAsignacion(id) {
            if (!id) {
                alert("ID inválido para asignación");
                return;
            }

            // Find the assignment using the provided ID
            const asignacion = (profesorSeleccionado?.asignaciones || []).find(a => a.id_profesor_materia == id);
            if (!asignacion) {
                alert("Asignación no encontrada.");
                return;
            }

            // Set the hidden input for the assignment ID
            document.getElementById('editar-id-profesor-materia').value = id;

            // Set the values for materia and curso selects using their respective IDs
            // Assuming asignacion.materia_id and asignacion.curso_id contain the correct IDs
            document.getElementById('editar-materia').value = asignacion.materia_id;
            document.getElementById('editar-curso').value = asignacion.curso_id;
            document.getElementById('editar-materia').value = asignacion.materia_id || '';
            document.getElementById('editar-curso').value = asignacion.curso_id || '';
            document.getElementById('editar-ciclo').value = asignacion.ciclo_lectivo || '';

            // Display the modal
            document.getElementById('modalEditarAsignacion').style.display = 'flex';
        }


        function cerrarModalEditarAsignacion() {
            document.getElementById('modalEditarAsignacion').style.display = 'none';
        }

        document.addEventListener('click', function(event) {
            const modal = document.getElementById('modalEditarAsignacion');
            if (modal && event.target === modal) {
                cerrarModalEditarAsignacion();
            }
        });

        function eliminarAsignacion(id) {
            if (confirm('¿Estás seguro de que deseas eliminar esta asignación?')) {
                // Aquí podés hacer un fetch/AJAX a un endpoint PHP que elimine por ID
                console.log('Eliminando asignación con ID:', id);
                // Por ejemplo: fetch('eliminar_asignacion.php', { method: 'POST', body: ... });
            }
        }

        // Cierra el modal
        function ocultarInfoProfesor() {
            document.getElementById('infoProfesorModal').style.display = 'none';
        }

        const infoProfesorModal = document.getElementById('infoProfesorModal');
        if (infoProfesorModal) {
            infoProfesorModal.addEventListener('click', function(event) {
                if (event.target === infoProfesorModal) ocultarInfoProfesor();
            });
        }

        // Validación del formulario de asignación
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('form-asignar-materia');
            form.addEventListener('submit', function(e) {
                const materiaId = document.getElementById('materia_id').value;
                const cursoId = document.getElementById('curso_id').value;
                const ciclo = document.getElementById('ciclo_lectivo').value.trim();

                if (!materiaId || !cursoId || !ciclo) {
                    e.preventDefault();
                    alert('Complete todos los campos antes de asignar.');
                }
            });
        });
    </script>

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
            creacionFormCard.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
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

            // Mostrar la foto actual
            const fotoDiv = document.getElementById('edit-profesor-foto-actual');
            if (fotoDiv) {
                if (profesor.foto_url && profesor.foto_url.trim() !== "") {
                    let ruta = profesor.foto_url;
                    if (!/^https?:\/\//.test(ruta) && ruta[0] !== '/') {
                        ruta = '../' + ruta;
                    }
                    fotoDiv.innerHTML = `<img src="${ruta}" alt="Foto actual del profesor" style="max-width:220px;max-height:220px;border-radius:8px;border:1px solid #e2e8f0;">`;
                } else {
                    fotoDiv.innerHTML = '<span style="color:#64748b;">Sin foto</span>';
                }
                fotoDiv.style.display = 'inline-block';
            }

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
                const usuarioTd = row.cells[3];
                const celularTd = row.cells[4];
                const estadoTd = row.cells[5];

                if (nombreCompletoTd && dniTd && tituloTd) {
                    const nombreCompletoText = nombreCompletoTd.textContent || nombreCompletoTd.innerText;
                    const dniText = dniTd.textContent || dniTd.innerText;
                    const tituloText = tituloTd.textContent || tituloTd.innerText;
                    const usuarioText = usuarioTd ? (usuarioTd.textContent || usuarioTd.innerText) : '';
                    const celularText = celularTd ? (celularTd.textContent || celularTd.innerText) : '';
                    const estadoText = estadoTd ? (estadoTd.textContent || estadoTd.innerText) : '';

                    if (nombreCompletoText.toLowerCase().indexOf(filter) > -1 ||
                        dniText.toLowerCase().indexOf(filter) > -1 ||
                        usuarioText.toLowerCase().indexOf(filter) > -1 ||
                        celularText.toLowerCase().indexOf(filter) > -1 ||
                        estadoText.toLowerCase().indexOf(filter) > -1 ||
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
                if (noProfesoresRow) { // Should always be hidden if filter is active
                    noProfesoresRow.style.display = 'none';
                }
            }
        }

        // Create icons after DOM is ready
        lucide.createIcons();
    </script>
</body>

</html>