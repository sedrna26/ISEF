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
if (isset($_GET['action']) && $_GET['action'] === 'search_users' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $searchTerm = $mysqli->real_escape_string($_GET['term'] ?? '');
    $searchQuery = "
        SELECT 
            u.id AS usuario_id, u.username, u.tipo, u.activo, u.debe_cambiar_password,
            p.id AS persona_id, p.apellidos, p.nombres, p.dni, p.fecha_nacimiento, p.celular, p.domicilio, p.contacto_emergencia, p.foto_url,
            a.legajo AS legajo_alumno, a.cohorte AS cohorte_alumno, a.fecha_ingreso AS fecha_ingreso_alumno,
            pr.titulo_profesional AS titulo_profesional_profesor, pr.fecha_ingreso AS fecha_ingreso_profesor, pr.horas_consulta AS horas_consulta_profesor,
            pc.titulo_profesional AS titulo_profesional_preceptor, pc.fecha_ingreso AS fecha_ingreso_preceptor, pc.sector_asignado AS sector_asignado_preceptor
        FROM usuario u
        LEFT JOIN persona p ON u.id = p.usuario_id
        LEFT JOIN alumno a ON p.id = a.persona_id AND u.tipo = 'alumno'
        LEFT JOIN profesor pr ON p.id = pr.persona_id AND u.tipo = 'profesor'
        LEFT JOIN preceptor pc ON p.id = pc.persona_id AND u.tipo = 'preceptor'
        WHERE (p.apellidos LIKE '%$searchTerm%' OR 
               p.nombres LIKE '%$searchTerm%' OR 
               p.dni LIKE '%$searchTerm%' OR 
               u.username LIKE '%$searchTerm%' OR 
               a.legajo LIKE '%$searchTerm%')
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
        p.id AS persona_id, p.apellidos, p.nombres, p.dni, p.fecha_nacimiento, p.celular, p.domicilio, p.contacto_emergencia, p.foto_url,
        a.legajo AS legajo_alumno, a.cohorte AS cohorte_alumno, a.fecha_ingreso AS fecha_ingreso_alumno,
        pr.titulo_profesional AS titulo_profesional_profesor, pr.fecha_ingreso AS fecha_ingreso_profesor, pr.horas_consulta AS horas_consulta_profesor,
        pc.titulo_profesional AS titulo_profesional_preceptor, pc.fecha_ingreso AS fecha_ingreso_preceptor, pc.sector_asignado AS sector_asignado_preceptor
    FROM usuario u
    LEFT JOIN persona p ON u.id = p.usuario_id
    LEFT JOIN alumno a ON p.id = a.persona_id AND u.tipo = 'alumno'
    LEFT JOIN profesor pr ON p.id = pr.persona_id AND u.tipo = 'profesor'
    LEFT JOIN preceptor pc ON p.id = pc.persona_id AND u.tipo = 'preceptor'
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
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        /* Estilos base del dashboard */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: url('../sources/fondo.png') no-repeat center center fixed;
            background-size: cover;
            color: #333;
            line-height: 1.6;
            position: relative;
        }

        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.15);
            z-index: -1;
            pointer-events: none;
        }

        :root {
            --orange-primary: rgba(230, 92, 0, 0.9);
            --orange-light: rgba(255, 140, 66, 0.8);
            --gray-dark: rgba(51, 51, 51, 0.9);
            --white: rgba(255, 255, 255, 0.9);
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: rgba(230, 92, 0, 0.85);
            backdrop-filter: blur(5px);
            border-right: 1px solid var(--orange-light);
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
            color: var(--white);
            transition: all 0.3s ease;
            z-index: 10;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: inherit;
        }

        .sidebar-brand img {
            width: 50px;
            height: 50px;
        }

        .brand-text h1 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            color: var(--white);
        }

        .brand-text p {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
        }

        .sidebar-nav {
            flex: 1;
            padding: 1rem;
        }

        .nav-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.8);
            text-transform: uppercase;
            margin-bottom: 0.5rem;
            padding: 0 0.75rem;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 0.875rem;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: var(--white);
        }

        .nav-link.active {
            background: var(--white);
            color: var(--orange-primary);
            font-weight: 500;
        }

        .nav-icon {
            width: 16px;
            height: 16px;
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: var(--white);
            color: var(--orange-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .user-details h3 {
            font-size: 0.875rem;
            margin: 0;
            color: var(--white);
        }

        .user-details p {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            color: var(--white);
            border-radius: 6px;
            border: none;
            background: rgba(255, 255, 255, 0.1);
            width: 100%;
            cursor: pointer;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: transparent;
        }

        .header {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            position: sticky;
            top: 0;
            z-index: 5;
        }

        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            padding: 0.5rem;
            cursor: pointer;
            color: var(--orange-primary);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            color: #888;
        }

        .breadcrumb a {
            color: var(--orange-primary);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .content {
            flex: 1;
            padding: 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .page-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--gray-dark);
        }

        .page-subtitle {
            color: var(--gray-dark);
            opacity: 0.9;
        }

        .message-toast {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 6px;
            border: 1px solid transparent;
        }

        .message-toast.success {
            background-color: rgba(220, 252, 231, 0.8);
            color: #166534;
            border-color: rgba(187, 247, 208, 0.6);
        }

        .message-toast.error {
            background-color: rgba(254, 226, 226, 0.8);
            color: #991b1b;
            border-color: rgba(254, 202, 202, 0.6);
        }

        .card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-dark);
        }

        .card-description {
            font-size: 0.875rem;
            color: var(--gray-dark);
            opacity: 0.8;
            margin-top: 0.25rem;
        }

        .card-content {
            padding: 1.5rem;
        }

        .card-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.3);
            background-color: rgba(255, 255, 255, 0.5);
            text-align: right;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.625rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            background-color: var(--white);
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--orange-primary);
            outline: none;
            box-shadow: 0 0 0 1px var(--orange-primary);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 6px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn i {
            margin-right: 0.5rem;
            width: 16px;
            height: 16px;
        }

        .btn-primary {
            background-color: var(--orange-primary);
            color: white;
        }

        .btn-secondary {
            background-color: #e5e7eb;
            color: #374151;
        }

        .btn-danger-outline {
            border-color: #ef4444;
            color: #ef4444;
        }

        .btn-danger-outline:hover {
            background: #fee2e2;
        }

        .btn-outline {
            border: 1px solid #d1d5db;
            color: #374151;
        }

        .btn-outline:hover {
            background-color: #f9fafb;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }

        .table-container {
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(5px);
        }

        .styled-table {
            width: 100%;
            border-collapse: collapse;
        }

        .styled-table th,
        .styled-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 0.875rem;
        }

        .styled-table th {
            background-color: rgba(255, 255, 255, 0.5);
            font-weight: 600;
        }

        .styled-table tr:hover {
            background-color: rgba(255, 255, 255, 0.5);
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25em 0.6em;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 9999px;
        }

        .badge i {
            width: 12px;
            height: 12px;
            margin-right: 0.25rem;
        }

        .badge-success {
            background-color: rgba(220, 252, 231, 0.8);
            color: #15803d;
        }

        .badge-danger {
            background-color: rgba(254, 226, 226, 0.8);
            color: #b91c1c;
        }

        .modal-container {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal-content {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            backdrop-filter: blur(5px);
        }

        .tipo-fields {
            display: none;
            padding: 1rem;
            background-color: rgba(255, 243, 224, 0.7);
            border-radius: 6px;
            margin-top: 1rem;
            border: 1px solid rgba(255, 224, 178, 0.8);
        }

        #estado-form-group-edit label,
        #forzar-pass-form-group-edit label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -280px;
            }

            .sidebar.open {
                left: 0;
            }

            .sidebar-toggle {
                display: block;
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="sidebar-brand">
                    <img src="../sources/logo_recortado.png" alt="ISEF Logo">
                    <div class="brand-text">
                        <h1>Sistema de Gestión ISEF</h1>
                        <p>Instituto Superior</p>
                    </div>
                </a>
            </div>
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li class="nav-item"><a href="dashboard.php" class="nav-link"><i data-lucide="home" class="nav-icon"></i><span>Inicio</span></a></li>
                    <?php if ($_SESSION['tipo'] === 'administrador'): ?>
                        <li class="nav-item"><a href="alumnos.php" class="nav-link"><i data-lucide="graduation-cap" class="nav-icon"></i><span>Alumnos</span></a></li>
                        <li class="nav-item"><a href="profesores.php" class="nav-link"><i data-lucide="briefcase" class="nav-icon"></i><span>Profesores</span></a></li>
                        <li class="nav-item"><a href="usuarios.php" class="nav-link active"><i data-lucide="users" class="nav-icon"></i><span>Usuarios</span></a></li>
                        <li class="nav-item"><a href="materias.php" class="nav-link"><i data-lucide="book-open" class="nav-icon"></i><span>Materias</span></a></li>
                        <li class="nav-item"><a href="cursos.php" class="nav-link"><i data-lucide="library" class="nav-icon"></i><span>Cursos</span></a></li>
                        <li class="nav-item"><a href="auditoria.php" class="nav-link"><i data-lucide="clipboard-list" class="nav-icon"></i><span>Auditoría</span></a></li>
                    <?php endif; ?>
                </ul>
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

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Lista de Usuarios</h2>
                        <div class="form-group" style="margin-top: 1rem;">
                            <input type="search" id="searchUserInput" onkeyup="buscarUsuarios()" placeholder="Buscar por Nombre, Apellido, DNI, Usuario, Legajo...">
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
                                    <?php
                                    if ($usuarios_result && $usuarios_result->num_rows > 0) {
                                        while ($usuario = $usuarios_result->fetch_assoc()) {
                                            echo generarFilaUsuarioHTML($usuario);
                                        }
                                    } else {
                                        echo '<tr id="noUsersRow"><td colspan="7" style="text-align:center; padding: 2rem;">No hay usuarios registrados.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="creacionUsuarioModal" class="modal-container">
        <div class="modal-content card">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="crear">
                <div class="card-header">
                    <h2 class="card-title">Crear Nuevo Usuario</h2>
                    <p class="card-description">El DNI se usará como contraseña inicial.</p>
                </div>
                <div class="card-content">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Tipo de usuario:</label>
                            <select name="tipo" required onchange="mostrarCamposAdicionales('creacion')">
                                <option value="">Seleccione tipo</option>
                                <option value="administrador">Administrador</option>
                                <option value="profesor">Profesor</option>
                                <option value="preceptor">Preceptor</option>
                                <option value="alumno">Alumno</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Apellidos:</label><input type="text" name="apellidos" required></div>
                        <div class="form-group"><label>Nombres:</label><input type="text" name="nombres" required></div>
                        <div class="form-group"><label>DNI:</label><input type="text" name="dni" required pattern="\d{7,8}"></div>
                        <div class="form-group"><label>Fecha de nacimiento:</label><input type="date" name="fecha_nacimiento" required></div>
                        <div class="form-group"><label>Celular:</label><input type="text" name="celular"></div>
                        <div class="form-group"><label>Domicilio:</label><input type="text" name="domicilio"></div>
                        <div class="form-group"><label>Contacto de emergencia:</label><input type="text" name="contacto_emergencia"></div>
                        <div class="form-group"><label>Foto de perfil:</label><input type="file" name="foto_usuario" accept="image/*"></div>
                    </div>
                    <div id="campos-alumno-creacion" class="tipo-fields">
                        <h4>Datos del Alumno</h4>
                        <div class="form-grid">
                            <div class="form-group"><label>Legajo:</label><input type="text" name="legajo_alumno"></div>
                            <div class="form-group"><label>Cohorte:</label><input type="number" name="cohorte_alumno"></div>
                            <div class="form-group"><label>Fecha de ingreso:</label><input type="date" name="fecha_ingreso_alumno"></div>
                        </div>
                    </div>
                    <div id="campos-profesor-creacion" class="tipo-fields">
                        <h4>Datos del Profesor</h4>
                        <div class="form-grid">
                            <div class="form-group"><label>Título profesional:</label><input type="text" name="titulo_profesional_profesor"></div>
                            <div class="form-group"><label>Fecha de ingreso:</label><input type="date" name="fecha_ingreso_profesor"></div>
                            <div class="form-group" style="grid-column: 1 / -1;"><label>Horas de consulta:</label><textarea name="horas_consulta_profesor" rows="2"></textarea></div>
                        </div>
                    </div>
                    <div id="campos-preceptor-creacion" class="tipo-fields">
                        <h4>Datos del Preceptor</h4>
                        <div class="form-grid">
                            <div class="form-group"><label>Título profesional:</label><input type="text" name="titulo_profesional_preceptor"></div>
                            <div class="form-group"><label>Fecha de ingreso:</label><input type="date" name="fecha_ingreso_preceptor"></div>
                            <div class="form-group"><label>Sector asignado:</label><input type="text" name="sector_asignado_preceptor"></div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-secondary" onclick="ocultarFormCreacion()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="margin-left: 0.5rem;"><i data-lucide="save"></i>Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>

    <div id="edicionUsuarioModal" class="modal-container">
        <div class="modal-content card">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="usuario_id_edit" id="usuario_id_edit">
                <input type="hidden" name="foto_actual" id="foto_actual_edit">

                <div class="card-header">
                    <h2 class="card-title">Editar Usuario</h2>
                    <p class="card-description">Modifica los datos del usuario seleccionado.</p>
                </div>
                <div class="card-content">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Tipo de usuario:</label>
                            <select name="tipo_edit" id="tipo_edit" required onchange="mostrarCamposAdicionales('edicion')">
                                <option value="administrador">Administrador</option>
                                <option value="profesor">Profesor</option>
                                <option value="preceptor">Preceptor</option>
                                <option value="alumno">Alumno</option>
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
                    <div id="campos-alumno-edicion" class="tipo-fields">
                        <h4>Datos del Alumno</h4>
                        <div class="form-grid">
                            <div class="form-group"><label>Legajo:</label><input type="text" name="legajo_alumno_edit" id="legajo_alumno_edit"></div>
                            <div class="form-group"><label>Cohorte:</label><input type="number" name="cohorte_alumno_edit" id="cohorte_alumno_edit"></div>
                            <div class="form-group"><label>Fecha de ingreso:</label><input type="date" name="fecha_ingreso_alumno_edit" id="fecha_ingreso_alumno_edit"></div>
                        </div>
                    </div>
                    <div id="campos-profesor-edicion" class="tipo-fields">
                        <h4>Datos del Profesor</h4>
                        <div class="form-grid">
                            <div class="form-group"><label>Título profesional:</label><input type="text" name="titulo_profesional_profesor_edit" id="titulo_profesional_profesor_edit"></div>
                            <div class="form-group"><label>Fecha de ingreso:</label><input type="date" name="fecha_ingreso_profesor_edit" id="fecha_ingreso_profesor_edit"></div>
                            <div class="form-group" style="grid-column: 1 / -1;"><label>Horas de consulta:</label><textarea name="horas_consulta_profesor_edit" id="horas_consulta_profesor_edit" rows="2"></textarea></div>
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
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-secondary" onclick="ocultarFormEdicion()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="margin-left: 0.5rem;"><i data-lucide="save"></i>Guardar Cambios</button>
                </div>
            </form>
        </div>
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

        function mostrarFormCreacion() {
            creacionModal.style.display = 'flex';
        }

        function ocultarFormCreacion() {
            creacionModal.style.display = 'none';
        }

        function mostrarFormEdicion() {
            edicionModal.style.display = 'flex';
        }

        function ocultarFormEdicion() {
            edicionModal.style.display = 'none';
        }

        // --- LÓGICA DE FORMULARIOS ---
        function mostrarCamposAdicionales(contexto) { // 'creacion' o 'edicion'
            document.querySelectorAll(`.tipo-fields`).forEach(div => div.style.display = 'none');
            const tipo = document.getElementById(`tipo${contexto === 'edicion' ? '_edit' : ''}`).value;
            if (tipo) {
                const camposDiv = document.getElementById(`campos-${tipo}-${contexto}`);
                if (camposDiv) {
                    camposDiv.style.display = 'block';
                }
            }
        }

        function cargarDatosEdicion(usuario) {
            // Rellenar datos generales
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
    </script>
</body>

</html>
<?php
if (isset($mysqli)) {
    $mysqli->close();
}
?>