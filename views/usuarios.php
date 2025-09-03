<?php
// usuarios.php - Gestión unificada de usuarios del sistema 
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: index.php");
    exit;
}

// Incluir el archivo de conexión a la base de datos
// Asegúrate de que la ruta a tu archivo db.php es correcta
require_once '../config/db.php';

// --- Obtener datos del usuario para el sidebar ---
$usuario_sidebar = ['nombre_completo' => 'Admin ISEF']; // Fallback
$stmt_user_sidebar = $mysqli->prepare("
    SELECT CONCAT(p.nombres, ' ', p.apellidos) as nombre_completo 
    FROM persona p 
    JOIN usuario u ON p.usuario_id = u.id 
    WHERE u.id = ?
");
if ($stmt_user_sidebar) {
    $stmt_user_sidebar->bind_param("i", $_SESSION['usuario_id']);
    $stmt_user_sidebar->execute();
    $result_user = $stmt_user_sidebar->get_result();
    if ($result_user->num_rows > 0) {
        $usuario_sidebar = $result_user->fetch_assoc();
    }
    $stmt_user_sidebar->close();
}


// --- Función para generar la fila HTML de un usuario en la tabla ---
function generarFilaUsuarioHTML($usuario)
{
    // Usa una imagen por defecto si no hay foto
    $foto = !empty($usuario['foto'])
        ? '../uploads/' . htmlspecialchars($usuario['foto'])
        : '../sources/default-user.png'; // Ruta a una imagen por defecto

    // Prepara los datos del usuario para el botón de editar en formato JSON
    $datos_usuario_json = htmlspecialchars(json_encode($usuario), ENT_QUOTES, 'UTF-8');

    $filaHTML = '<tr>';
    $filaHTML .= '<td><img src="' . $foto . '" alt="Foto" style="width:40px;height:40px;border-radius:50%;object-fit:cover;"></td>';
    $filaHTML .= '<td>' . ucfirst(htmlspecialchars($usuario['tipo'])) . '</td>';
    $filaHTML .= '<td>' . htmlspecialchars($usuario['apellidos']) . ', ' . htmlspecialchars($usuario['nombres']) . '</td>';
    $filaHTML .= '<td>' . htmlspecialchars($usuario['dni']) . '</td>';
    $filaHTML .= '<td>' . htmlspecialchars($usuario['username']) . '</td>';
    $filaHTML .= '<td>';
    if ($usuario['activo']) {
        $filaHTML .= '<span class="badge badge-success"><i data-lucide="user-check"></i>Activo</span>';
    } else {
        $filaHTML .= '<span class="badge badge-danger"><i data-lucide="user-x"></i>Inactivo</span>';
    }
    if ($usuario['debe_cambiar_password']) {
        $filaHTML .= '<br><small style="color:#c2410c;">(Cambiar pass)</small>';
    }
    $filaHTML .= '</td>';
    $filaHTML .= '<td class="table-actions">
                     <button type="button" class="btn btn-outline btn-sm" onclick="cargarDatosEdicion(' . $datos_usuario_json . ')"><i data-lucide="edit-2"></i> Editar</button>
                    <form method="post" style="display:inline;" onsubmit="return confirm(\'¿Está seguro de eliminar este usuario? Esta acción no se puede deshacer y eliminará todos los datos asociados.\');">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="usuario_id" value="' . $usuario['usuario_id'] . '">
                        <button type="submit" class="btn btn-outline btn-danger-outline btn-sm"><i data-lucide="trash-2"></i> Eliminar</button>
                    </form>
                </td>';
    $filaHTML .= '</tr>';
    return $filaHTML;
}

// --- Búsqueda por AJAX ---
if (isset($_GET['action']) && $_GET['action'] === 'search_users') {
    $searchTerm = $mysqli->real_escape_string($_GET['term'] ?? '');
    $searchQuery = "
        SELECT 
            u.id AS usuario_id, u.username, u.tipo, u.activo, u.debe_cambiar_password,
            p.id AS persona_id, p.apellidos, p.nombres, p.dni, p.fecha_nacimiento,
            p.celular, p.domicilio, p.contacto_emergencia, p.foto_url,
            pc.titulo_profesional AS titulo_profesional_preceptor,
            pc.fecha_ingreso AS fecha_ingreso_preceptor,
            pc.sector_asignado AS sector_asignado_preceptor
        FROM usuario u
        LEFT JOIN persona p ON u.id = p.usuario_id
        LEFT JOIN preceptor pc ON p.id = pc.persona_id AND u.tipo = 'preceptor'
        WHERE u.tipo IN ('administrador', 'preceptor')
        AND (p.apellidos LIKE '%$searchTerm%' OR 
             p.nombres LIKE '%$searchTerm%' OR 
             p.dni LIKE '%$searchTerm%' OR 
             u.username LIKE '%$searchTerm%')
        ORDER BY u.tipo, p.apellidos, p.nombres
    ";
    $result = $mysqli->query($searchQuery);
    $output = '';
    if ($result && $result->num_rows > 0) {
        while ($usuario = $result->fetch_assoc()) {
            $output .= generarFilaUsuarioHTML($usuario);
        }
    } else {
        $output = '<tr id="noResultsSearchRow"><td colspan="7" style="text-align:center; padding: 2rem;">No se encontraron usuarios que coincidan con la búsqueda.</td></tr>';
    }
    echo $output;
    $mysqli->close();
    exit;
}

// --- Función para generar un nombre de usuario único ---
function generarUsername($nombre, $apellido, $mysqli)
{
    setlocale(LC_ALL, 'en_US.UTF-8');
    $nombre = strtolower(trim($nombre));
    $apellido = strtolower(trim($apellido));
    $nombre = preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $nombre));
    $apellido = preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $apellido));
    $baseUsername = substr($nombre, 0, 1) . $apellido;
    $username = $baseUsername;
    $i = 1;
    while (true) {
        $stmt_check = $mysqli->prepare("SELECT id FROM usuario WHERE username = ?");
        if (!$stmt_check) {
            error_log("Error al preparar la consulta para verificar username: " . $mysqli->error);
            return $baseUsername . time(); // Fallback
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


// --- Procesar acciones POST (crear, editar, eliminar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    // --- Acción CREAR ---
    if ($_POST['accion'] === 'crear') {
        $mysqli->begin_transaction();
        try {
            // Generar usuario y contraseña
            $username = generarUsername($_POST['nombres'], $_POST['apellidos'], $mysqli);
            $password_hash = password_hash($_POST['dni'], PASSWORD_DEFAULT);
            $tipo = $_POST['tipo'];
            $activo = 1; // Siempre activo al crear
            $debe_cambiar = 1; // Siempre forzar cambio de pass al crear

            // Manejo de la foto
            $foto_nombre = '';
            if (isset($_FILES['foto_usuario']) && $_FILES['foto_usuario']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['foto_usuario']['name'], PATHINFO_EXTENSION);
                $foto_nombre = 'user_' . $username . '_' . time() . '.' . $ext;
                $destino = realpath(__DIR__ . '/../uploads') . DIRECTORY_SEPARATOR . $foto_nombre;
                if (!move_uploaded_file($_FILES['foto_usuario']['tmp_name'], $destino)) {
                    throw new Exception("Error al mover el archivo de la foto.");
                }
            }

            // Insertar en `usuario`
            $stmt_usuario = $mysqli->prepare("INSERT INTO usuario (username, password, tipo, activo, debe_cambiar_password) VALUES (?, ?, ?, ?, ?)");
            $stmt_usuario->bind_param("sssii", $username, $password_hash, $tipo, $activo, $debe_cambiar);
            $stmt_usuario->execute();
            $usuario_id = $mysqli->insert_id;
            $stmt_usuario->close();

            // Insertar en `persona`
            $stmt_persona = $mysqli->prepare("INSERT INTO persona (usuario_id, apellidos, nombres, dni, fecha_nacimiento, celular, domicilio, contacto_emergencia, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_persona->bind_param("issssssss", $usuario_id, $_POST['apellidos'], $_POST['nombres'], $_POST['dni'], $_POST['fecha_nacimiento'], $_POST['celular'], $_POST['domicilio'], $_POST['contacto_emergencia'], $foto_nombre);
            $stmt_persona->execute();
            $persona_id = $mysqli->insert_id;
            $stmt_persona->close();

            // Insertar en la tabla del ROL específico
            $stmt_role = null;
            switch ($tipo) {
                case 'alumno':
                    $stmt_role = $mysqli->prepare("INSERT INTO alumno (persona_id, legajo, fecha_ingreso, cohorte) VALUES (?, ?, ?, ?)");
                    $stmt_role->bind_param("isss", $persona_id, $_POST['legajo_alumno'], $_POST['fecha_ingreso_alumno'], $_POST['cohorte_alumno']);
                    break;
                case 'profesor':
                    $stmt_role = $mysqli->prepare("INSERT INTO profesor (persona_id, titulo_profesional, fecha_ingreso, horas_consulta) VALUES (?, ?, ?, ?)");
                    $stmt_role->bind_param("isss", $persona_id, $_POST['titulo_profesional_profesor'], $_POST['fecha_ingreso_profesor'], $_POST['horas_consulta_profesor']);
                    break;
                case 'preceptor':
                    $stmt_role = $mysqli->prepare("INSERT INTO preceptor (persona_id, titulo_profesional, fecha_ingreso, sector_asignado) VALUES (?, ?, ?, ?)");
                    $stmt_role->bind_param("isss", $persona_id, $_POST['titulo_profesional_preceptor'], $_POST['fecha_ingreso_preceptor'], $_POST['sector_asignado_preceptor']);
                    break;
            }
            if ($stmt_role) {
                $stmt_role->execute();
                $stmt_role->close();
            }

            $mysqli->commit();
            $_SESSION['mensaje_exito'] = "Usuario creado correctamente. Nombre de usuario: $username";
        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['mensaje_error'] = "Error al crear el usuario: " . $e->getMessage();
        }
    }
    // --- Acción EDITAR ---
    elseif ($_POST['accion'] === 'editar') {
        $mysqli->begin_transaction();
        try {
            $usuario_id_edit = (int)$_POST['usuario_id_edit'];
            $tipo_edit = $_POST['tipo_edit'];
            $activo_edit = isset($_POST['activo_edit']) ? 1 : 0;
            $debe_cambiar_edit = isset($_POST['debe_cambiar_password_edit']) ? 1 : 0;

            // 1. Actualizar tabla `usuario`
            $stmt_usuario_update = $mysqli->prepare("UPDATE usuario SET tipo = ?, activo = ?, debe_cambiar_password = ? WHERE id = ?");
            $stmt_usuario_update->bind_param("siii", $tipo_edit, $activo_edit, $debe_cambiar_edit, $usuario_id_edit);
            $stmt_usuario_update->execute();
            $stmt_usuario_update->close();

            // 2. Manejar actualización de foto
            $sql_foto_update = "";
            $foto_nombre_edit = $_POST['foto_actual']; // Mantener la foto actual por defecto
            if (isset($_FILES['foto_usuario_edit']) && $_FILES['foto_usuario_edit']['error'] === UPLOAD_ERR_OK) {
                // Hay una nueva foto, procesarla
                $ext = pathinfo($_FILES['foto_usuario_edit']['name'], PATHINFO_EXTENSION);
                $foto_nombre_edit = 'user_id_' . $usuario_id_edit . '_' . time() . '.' . $ext;
                $destino = realpath(__DIR__ . '/../uploads') . DIRECTORY_SEPARATOR . $foto_nombre_edit;

                if (move_uploaded_file($_FILES['foto_usuario_edit']['tmp_name'], $destino)) {
                    // Si se movió la nueva, eliminar la antigua si existe y es diferente
                    if (!empty($_POST['foto_actual']) && file_exists(realpath(__DIR__ . '/../uploads') . DIRECTORY_SEPARATOR . $_POST['foto_actual'])) {
                        unlink(realpath(__DIR__ . '/../uploads') . DIRECTORY_SEPARATOR . $_POST['foto_actual']);
                    }
                } else {
                    throw new Exception("Error al mover el nuevo archivo de foto.");
                }
            }

            // 3. Actualizar tabla `persona`
            $stmt_persona_update = $mysqli->prepare("UPDATE persona SET apellidos = ?, nombres = ?, dni = ?, fecha_nacimiento = ?, celular = ?, domicilio = ?, contacto_emergencia = ?, foto = ? WHERE usuario_id = ?");
            $stmt_persona_update->bind_param("ssssssssi", $_POST['apellidos_edit'], $_POST['nombres_edit'], $_POST['dni_edit'], $_POST['fecha_nacimiento_edit'], $_POST['celular_edit'], $_POST['domicilio_edit'], $_POST['contacto_emergencia_edit'], $foto_nombre_edit, $usuario_id_edit);
            $stmt_persona_update->execute();
            $stmt_persona_update->close();

            // 4. Sincronizar roles (complejo pero necesario)
            $persona_id_result = $mysqli->query("SELECT id FROM persona WHERE usuario_id = $usuario_id_edit");
            if ($persona_id_result->num_rows === 0) throw new Exception("No se encontró la persona asociada.");
            $persona_id = $persona_id_result->fetch_assoc()['id'];

            // Borrar roles que NO correspondan al nuevo tipo
            if ($tipo_edit !== 'alumno') $mysqli->query("DELETE FROM alumno WHERE persona_id = $persona_id");
            if ($tipo_edit !== 'profesor') $mysqli->query("DELETE FROM profesor WHERE persona_id = $persona_id");
            if ($tipo_edit !== 'preceptor') $mysqli->query("DELETE FROM preceptor WHERE persona_id = $persona_id");

            // Insertar o actualizar el rol correcto (UPSERT)
            $stmt_role_update = null;
            if ($tipo_edit === 'alumno') {
                $stmt_role_update = $mysqli->prepare("INSERT INTO alumno (persona_id, legajo, fecha_ingreso, cohorte) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE legajo=VALUES(legajo), fecha_ingreso=VALUES(fecha_ingreso), cohorte=VALUES(cohorte)");
                $stmt_role_update->bind_param("isss", $persona_id, $_POST['legajo_alumno_edit'], $_POST['fecha_ingreso_alumno_edit'], $_POST['cohorte_alumno_edit']);
            } elseif ($tipo_edit === 'profesor') {
                $stmt_role_update = $mysqli->prepare("INSERT INTO profesor (persona_id, titulo_profesional, fecha_ingreso, horas_consulta) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE titulo_profesional=VALUES(titulo_profesional), fecha_ingreso=VALUES(fecha_ingreso), horas_consulta=VALUES(horas_consulta)");
                $stmt_role_update->bind_param("isss", $persona_id, $_POST['titulo_profesional_profesor_edit'], $_POST['fecha_ingreso_profesor_edit'], $_POST['horas_consulta_profesor_edit']);
            } elseif ($tipo_edit === 'preceptor') {
                $stmt_role_update = $mysqli->prepare("INSERT INTO preceptor (persona_id, titulo_profesional, fecha_ingreso, sector_asignado) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE titulo_profesional=VALUES(titulo_profesional), fecha_ingreso=VALUES(fecha_ingreso), sector_asignado=VALUES(sector_asignado)");
                $stmt_role_update->bind_param("isss", $persona_id, $_POST['titulo_profesional_preceptor_edit'], $_POST['fecha_ingreso_preceptor_edit'], $_POST['sector_asignado_preceptor_edit']);
            }

            if ($stmt_role_update) {
                $stmt_role_update->execute();
                $stmt_role_update->close();
            }

            $mysqli->commit();
            $_SESSION['mensaje_exito'] = "Usuario actualizado correctamente.";
        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['mensaje_error'] = "Error al actualizar el usuario: " . $e->getMessage();
        }
    }
    // --- Acción ELIMINAR ---
    elseif ($_POST['accion'] === 'eliminar') {
        // La eliminación de usuario debería tener ON DELETE CASCADE en la BD
        // para las tablas persona, alumno, profesor, etc. Si no es así, hay que borrar manualmente.
        // Asumiendo que la FK en `persona` tiene ON DELETE CASCADE...
        $mysqli->begin_transaction();
        try {
            $stmt_delete = $mysqli->prepare("DELETE FROM usuario WHERE id = ?");
            if (!$stmt_delete) throw new Exception("Error al preparar la consulta de eliminación.");

            $stmt_delete->bind_param("i", $_POST['usuario_id']);
            $stmt_delete->execute();

            if ($stmt_delete->affected_rows > 0) {
                $mysqli->commit();
                $_SESSION['mensaje_exito'] = "Usuario eliminado correctamente.";
            } else {
                $mysqli->rollback();
                $_SESSION['mensaje_error'] = "No se pudo eliminar el usuario (quizás ya fue eliminado).";
            }
            $stmt_delete->close();
        } catch (mysqli_sql_exception $e) {
            $mysqli->rollback();
            if ($e->getCode() == 1451) { // Error de Foreign Key
                $_SESSION['mensaje_error'] = "Error: No se puede eliminar este usuario porque tiene datos asociados (inscripciones, evaluaciones, etc.).";
            } else {
                $_SESSION['mensaje_error'] = "Error de base de datos al eliminar: " . $e->getMessage();
            }
        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['mensaje_error'] = "Error al eliminar el usuario: " . $e->getMessage();
        }
    }

    // Redirigir para evitar reenvío de formulario
    header("Location: usuarios.php");
    exit;
}

// Recuperar mensajes de la sesión para mostrarlos
$mensaje_exito = '';
$mensaje_error = '';
if (isset($_SESSION['mensaje_exito'])) {
    $mensaje_exito = $_SESSION['mensaje_exito'];
    unset($_SESSION['mensaje_exito']);
}
if (isset($_SESSION['mensaje_error'])) {
    $mensaje_error = $_SESSION['mensaje_error'];
    unset($_SESSION['mensaje_error']);
}


// --- Obtener la lista inicial de todos los usuarios ---
$usuarios_iniciales_sql = "
    SELECT 
        u.id AS usuario_id, u.username, u.tipo, u.activo, u.debe_cambiar_password,
        p.id AS persona_id, p.apellidos, p.nombres, p.dni, p.fecha_nacimiento, 
        p.celular, p.domicilio, p.contacto_emergencia, p.foto_url,
        pc.titulo_profesional AS titulo_profesional_preceptor, 
        pc.fecha_ingreso AS fecha_ingreso_preceptor, 
        pc.sector_asignado AS sector_asignado_preceptor
    FROM usuario u
    LEFT JOIN persona p ON u.id = p.usuario_id
    LEFT JOIN preceptor pc ON p.id = pc.persona_id AND u.tipo = 'preceptor'
    WHERE u.tipo IN ('administrador', 'preceptor')
    ORDER BY u.tipo, p.apellidos, p.nombres
";
$usuarios_result = $mysqli->query($usuarios_iniciales_sql);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Sistema ISEF</title>
    <link rel="icon" href="../sources/logo_recortado.ico" type="image/x-icon">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="../style/style.css">
</head>

<body>
    <div class="app-container">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/ISEF/views/includes/nav.php'; ?>
        <main class="main-content">
            <header class="header">
                <button class="sidebar-toggle" onclick="toggleSidebar()"><i data-lucide="menu"></i></button>
                <nav class="breadcrumb">
                    <a href="dashboard.php">Sistema de Gestión ISEF</a>
                    <span>/</span>
                    <span>Usuarios</span>
                </nav>
            </header>

            <div class="content">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Gestión de Usuarios</h1>
                        <p class="page-subtitle">Crea, edita y administra todos los usuarios del sistema.</p>
                    </div>
                    <button class="btn btn-primary" onclick="mostrarFormCreacion()">
                        <i data-lucide="plus"></i>
                        Nuevo Usuario
                    </button>
                </div>

                <?php if ($mensaje_exito): ?>
                    <div class="message-toast success" role="alert"><?= htmlspecialchars($mensaje_exito) ?></div>
                <?php endif; ?>
                <?php if ($mensaje_error): ?>
                    <div class="message-toast error" role="alert"><?= htmlspecialchars($mensaje_error) ?></div>
                <?php endif; ?>

                <!-- Formulario de creación como card desplegable -->
                <div class="card" id="creacionFormCard" style="display:none; margin-bottom: 2rem;">
                    <div class="card-header">
                        <h2 class="card-title">Registrar Nuevo Usuario</h2>
                        <p class="card-description">Completa los datos para agregar un nuevo usuario al sistema.</p>
                    </div>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="accion" value="crear">
                        <div class="card-content">
                            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
                                <div class="form-group">
                                    <label>Tipo de usuario:</label>
                                    <select name="tipo" required onchange="mostrarCamposAdicionales('creacion')">
                                        <option value="">Seleccione tipo</option>
                                        <option value="administrador">Administrador</option>
                                        <option value="preceptor">Preceptor</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Apellidos:</label>
                                    <input type="text" name="apellidos" required>
                                </div>
                                <div class="form-group">
                                    <label>Nombres:</label>
                                    <input type="text" name="nombres" required>
                                </div>
                                <div class="form-group">
                                    <label>DNI:</label>
                                    <input type="text" name="dni" required pattern="\d{7,8}" title="DNI debe ser 7 u 8 dígitos numéricos">
                                </div>
                                <div class="form-group">
                                    <label>Fecha de nacimiento:</label>
                                    <input type="date" name="fecha_nacimiento" required>
                                </div>
                                <div class="form-group">
                                    <label>Celular:</label>
                                    <input type="text" name="celular">
                                </div>
                                <div class="form-group">
                                    <label>Domicilio:</label>
                                    <input type="text" name="domicilio">
                                </div>
                                <div class="form-group">
                                    <label>Contacto de emergencia:</label>
                                    <input type="text" name="contacto_emergencia">
                                </div>
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label>Foto de perfil:</label>
                                    <div style="display:flex; align-items:center; gap:1rem;">
                                        <input type="file" name="foto_usuario" accept="image/*" onchange="mostrarPreviewFoto(this)">
                                        <div id="preview-foto" style="margin-top: 0.5rem;"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Campos específicos por tipo de usuario -->
                            <div id="campos-preceptor-creacion" class="tipo-fields">
                                <h4>Datos del Preceptor</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Título profesional:</label>
                                        <input type="text" name="titulo_profesional_preceptor">
                                    </div>
                                    <div class="form-group">
                                        <label>Fecha de ingreso:</label>
                                        <input type="date" name="fecha_ingreso_preceptor">
                                    </div>
                                    <div class="form-group">
                                        <label>Sector asignado:</label>
                                        <input type="text" name="sector_asignado_preceptor">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="button" class="btn btn-secondary" onclick="ocultarFormCreacion()">Cancelar</button>
                            <button type="submit" class="btn btn-primary" style="margin-left:0.5rem;">
                                <i data-lucide="save"></i>Crear Usuario
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Formulario de edición como card desplegable -->
                <div class="card" id="edicionFormCard" style="display:none; margin-bottom: 2rem;">
                    <div class="card-header">
                        <h2 class="card-title">Editar Usuario</h2>
                        <p class="card-description">Modifica la información del usuario seleccionado.</p>
                    </div>
                    <div class="card-content">
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="accion" value="editar">
                            <input type="hidden" name="usuario_id_edit" id="usuario_id_edit">
                            <input type="hidden" name="foto_actual" id="foto_actual_edit">

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Tipo de usuario:</label>
                                    <select name="tipo_edit" id="tipo_edit" required onchange="mostrarCamposAdicionales('edicion')">
                                        <option value="administrador">Administrador</option>
                                        <option value="preceptor">Preceptor</option>
                                    </select>
                                </div>
                                <div class="form-group"><label>Apellidos:</label><input type="text" name="apellidos_edit" id="apellidos_edit" required></div>
                                <div class="form-group"><label>Nombres:</label><input type="text" name="nombres_edit" id="nombres_edit" required></div>
                                <div class="form-group"><label>DNI:</label><input type="text" name="dni_edit" id="dni_edit" required></div>
                                <div class="form-group"><label>Fecha de nacimiento:</label><input type="date" name="fecha_nacimiento_edit" id="fecha_nacimiento_edit" required></div>
                                <div class="form-group"><label>Celular:</label><input type="text" name="celular_edit" id="celular_edit"></div>
                                <div class="form-group"><label>Domicilio:</label><input type="text" name="domicilio_edit" id="domicilio_edit"></div>
                                <div class="form-group"><label>Contacto de emergencia:</label><input type="text" name="contacto_emergencia_edit" id="contacto_emergencia_edit"></div>

                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label>Foto de perfil (dejar en blanco para no cambiar)</label>
                                    <div style="display:flex; align-items:center; gap:1rem;">
                                        <img id="preview_foto_edit" src="" alt="Foto actual" style="width:50px; height:50px; border-radius:50%; object-fit:cover;">
                                        <input type="file" name="foto_usuario_edit" accept="image/*">
                                    </div>
                                </div>

                                <div class="form-group" id="estado-form-group-edit">
                                    <label><input type="checkbox" name="activo_edit" id="activo_edit" value="1">Usuario Activo</label>
                                </div>
                                <div class="form-group" id="forzar-pass-form-group-edit">
                                    <label><input type="checkbox" name="debe_cambiar_password_edit" id="debe_cambiar_password_edit" value="1">Forzar cambio de contraseña</label>
                                </div>
                            </div>
                            <div id="campos-preceptor-edicion" class="tipo-fields">
                                <h4>Datos del Preceptor</h4>
                                <div class="form-grid">
                                    <div class="form-group"><label>Título profesional:</label><input type="text" name="titulo_profesional_preceptor_edit" id="titulo_profesional_preceptor_edit"></div>
                                    <div class="form-group"><label>Fecha de ingreso:</label><input type="date" name="fecha_ingreso_preceptor_edit" id="fecha_ingreso_preceptor_edit"></div>
                                    <div class="form-group"><label>Sector asignado:</label><input type="text" name="sector_asignado_preceptor_edit" id="sector_asignado_preceptor_edit"></div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer">
                        <button type="button" class="btn btn-secondary" onclick="ocultarFormEdicion()">Cancelar</button>
                        <button type="submit" class="btn btn-primary" style="margin-left: 0.5rem;"><i data-lucide="save"></i>Guardar Cambios</button>
                    </div>
                </div>

                <!-- Lista de usuarios -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Lista de Usuarios</h2>
                        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; margin-top: 1rem;">
                            <div class="form-group" style="flex: 1 1 300px; margin-bottom: 0;">
                                <input type="search"
                                    id="searchUserInput"
                                    onkeyup="filterTableUsers()"
                                    placeholder="Buscar por Nombre, Apellido, DNI, Usuario..."
                                    style="width: 100%; height: 2.5rem; padding: 0.625rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 0.875rem;">
                            </div>
                            <form method="get" style="margin-bottom: 0;">
                                <label for="registros" style="font-size: 0.9rem; color: #64748b; margin-right: 0.5rem;">Mostrar</label>
                                <select name="registros" id="registros" onchange="this.form.submit()"
                                    style="padding: 0.4rem 0.7rem; border-radius: 6px; border: 1px solid #cbd5e1;">
                                    <option value="10">10</option>
                                    <option value="20">20</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                            </form>
                        </div>
                    </div>
                    <div class="card-content" style="padding:0;">
                        <div class="table-container">
                            <table class="styled-table">
                                <thead>
                                    <tr>
                                        <th>Foto</th>
                                        <th>Tipo</th>
                                        <th>Nombre Completo</th>
                                        <th>DNI</th>
                                        <th>Usuario</th>
                                        <th>Estado</th>
                                        <th class="text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="userTableBody">
                                    <?php if ($usuarios_result && $usuarios_result->num_rows > 0): ?>
                                        <?php while ($usuario = $usuarios_result->fetch_assoc()): ?>
                                            <?php echo generarFilaUsuarioHTML($usuario); ?>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr id="noUsersRow">
                                            <td colspan="7" style="text-align:center; padding: 2rem;">No hay usuarios registrados.</td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr id="noResultsSearchRow" style="display: none;">
                                        <td colspan="7" style="text-align:center; padding: 2rem;">
                                            No se encontraron usuarios que coincidan con la búsqueda.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();

        // --- MANEJO DEL SIDEBAR ---
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
        }

        function confirmLogout() {
            if (confirm('¿Estás seguro de que deseas cerrar sesión?')) {
                window.location.href = 'logout.php';
            }
        }

        // --- BÚSQUEDA AJAX ---
        let searchTimeout = null;

        function buscarUsuarios() {
            clearTimeout(searchTimeout);
            const searchTerm = document.getElementById('searchUserInput').value;
            searchTimeout = setTimeout(() => {
                fetch(`usuarios.php?action=search_users&term=${encodeURIComponent(searchTerm)}`, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.text())
                    .then(html => {
                        document.getElementById('userTableBody').innerHTML = html;
                        lucide.createIcons(); // Vuelve a renderizar los iconos en los nuevos elementos
                    })
                    .catch(error => console.error('Error en la búsqueda AJAX:', error));
            }, 300);
        }

        // --- MANEJO DE MODALES ---
        const creacionModal = document.getElementById('creacionUsuarioModal');
        const edicionModal = document.getElementById('edicionUsuarioModal');

        // Funciones para mostrar/ocultar formularios
        function mostrarFormCreacion() {
            const formCard = document.getElementById('creacionFormCard');
            formCard.style.display = 'block';
            formCard.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        function ocultarFormCreacion() {
            const formCard = document.getElementById('creacionFormCard');
            formCard.style.display = 'none';
            document.querySelector('#creacionFormCard form').reset();
        }

        function cargarDatosEdicion(usuario) {
            // Ocultar formulario de creación si está visible
            ocultarFormCreacion();

            // Mostrar formulario de edición
            const formCard = document.getElementById('edicionFormCard');
            formCard.style.display = 'block';

            // Cargar datos en el formulario
            document.getElementById('usuario_id_edit').value = usuario.usuario_id;
            document.getElementById('tipo_edit').value = usuario.tipo;
            document.getElementById('apellidos_edit').value = usuario.apellidos;
            document.getElementById('nombres_edit').value = usuario.nombres;
            document.getElementById('dni_edit').value = usuario.dni;
            document.getElementById('fecha_nacimiento_edit').value = usuario.fecha_nacimiento;
            document.getElementById('celular_edit').value = usuario.celular || '';
            document.getElementById('domicilio_edit').value = usuario.domicilio || '';
            document.getElementById('contacto_emergencia_edit').value = usuario.contacto_emergencia || '';

            // Estado y cambio de pass
            document.getElementById('activo_edit').checked = (usuario.activo == '1');
            document.getElementById('debe_cambiar_password_edit').checked = (usuario.debe_cambiar_password == '1');

            // Foto
            const fotoPreview = document.getElementById('preview_foto_edit');
            document.getElementById('foto_actual_edit').value = usuario.foto || '';
            fotoPreview.src = usuario.foto ? `../uploads/${usuario.foto}` : '../sources/default-user.png';

            // Rellenar datos específicos del rol
            document.getElementById('legajo_alumno_edit').value = usuario.legajo_alumno || '';
            document.getElementById('cohorte_alumno_edit').value = usuario.cohorte_alumno || '';
            document.getElementById('fecha_ingreso_alumno_edit').value = usuario.fecha_ingreso_alumno || '';

            document.getElementById('titulo_profesional_profesor_edit').value = usuario.titulo_profesional_profesor || '';
            document.getElementById('fecha_ingreso_profesor_edit').value = usuario.fecha_ingreso_profesor || '';
            document.getElementById('horas_consulta_profesor_edit').value = usuario.horas_consulta_profesor || '';

            document.getElementById('titulo_profesional_preceptor_edit').value = usuario.titulo_profesional_preceptor || '';
            document.getElementById('fecha_ingreso_preceptor_edit').value = usuario.fecha_ingreso_preceptor || '';
            document.getElementById('sector_asignado_preceptor_edit').value = usuario.sector_asignado_preceptor || '';

            // Mostrar campos correctos y el modal
            mostrarCamposAdicionales('edicion');
            mostrarFormEdicion();
        }

        // Cierra los modales si se hace clic en el fondo oscuro
        window.onclick = function(event) {
            if (event.target == creacionModal) {
                ocultarFormCreacion();
            }
            if (event.target == edicionModal) {
                ocultarFormEdicion();
            }
        }

        // Agregar este script para la previsualización de la foto
        function mostrarPreviewFoto(input) {
            const previewDiv = document.getElementById('preview-foto');
            previewDiv.innerHTML = '';
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewDiv.innerHTML = '<img src="' + e.target.result +
                        '" alt="Foto seleccionada" style="max-width:180px;max-height:180px;border-radius:8px;border:1px solid #e2e8f0;">';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function mostrarCamposAdicionales(modo) {
            // Ocultar todos los campos específicos
            document.querySelectorAll('.tipo-fields').forEach(div => div.style.display = 'none');

            const tipo = document.getElementById(modo === 'creacion' ? 'tipo' : 'tipo_edit').value;

            // Mostrar solo los campos de preceptor si corresponde
            if (tipo === 'preceptor') {
                document.getElementById(`campos-preceptor-${modo}`).style.display = 'block';
            }
        }

        function filterTableUsers() {
            const input = document.getElementById("searchUserInput");
            const filter = input.value.toLowerCase();
            const table = document.querySelector(".styled-table");
            const tbody = table.getElementsByTagName("tbody")[0];
            const tr = tbody.getElementsByTagName("tr");
            let foundMatch = false;

            const noUsersRow = document.getElementById('noUsersRow');
            const noResultsSearchRow = document.getElementById('noResultsSearchRow');

            if (noUsersRow) noUsersRow.style.display = 'none';
            if (noResultsSearchRow) noResultsSearchRow.style.display = 'none';

            for (let i = 0; i < tr.length; i++) {
                let row = tr[i];
                if (row.id === 'noUsersRow' || row.id === 'noResultsSearchRow') {
                    continue;
                }
                let displayRow = false;
                const fotoTd = row.cells[0];
                const tipoTd = row.cells[1];
                const nombreTd = row.cells[2];
                const dniTd = row.cells[3];
                const usuarioTd = row.cells[4];
                const estadoTd = row.cells[5];

                if (tipoTd && nombreTd && dniTd && usuarioTd) {
                    const tipoText = tipoTd.textContent || tipoTd.innerText;
                    const nombreText = nombreTd.textContent || nombreTd.innerText;
                    const dniText = dniTd.textContent || dniTd.innerText;
                    const usuarioText = usuarioTd.textContent || usuarioTd.innerText;
                    const estadoText = estadoTd ? (estadoTd.textContent || estadoTd.innerText) : '';

                    if (tipoText.toLowerCase().indexOf(filter) > -1 ||
                        nombreText.toLowerCase().indexOf(filter) > -1 ||
                        dniText.toLowerCase().indexOf(filter) > -1 ||
                        usuarioText.toLowerCase().indexOf(filter) > -1 ||
                        estadoText.toLowerCase().indexOf(filter) > -1) {
                        displayRow = true;
                        foundMatch = true;
                    }
                }
                row.style.display = displayRow ? "" : "none";
            }

            // Mostrar mensaje cuando no hay resultados
            if (filter === "") {
                if (tbody.rows.length === 0 && noUsersRow) {
                    noUsersRow.style.display = '';
                }
                if (noResultsSearchRow) {
                    noResultsSearchRow.style.display = 'none';
                }
            } else {
                if (!foundMatch && noResultsSearchRow) {
                    noResultsSearchRow.style.display = '';
                }
                if (noUsersRow) {
                    noUsersRow.style.display = 'none';
                }
            }
        }
    </script>
</body>

</html>
<?php
if (isset($mysqli)) {
    $mysqli->close();
}
?>