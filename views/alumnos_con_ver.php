
<?php
// Mostrar detalle de alumno si se pasa ?ver=id
if (isset($_GET['ver'])) {
    $id = intval($_GET['ver']);
    $consulta = "SELECT * FROM alumnos WHERE id = $id";
    $resultado = $conexion->query($consulta);

    if ($resultado && $resultado->num_rows > 0) {
        $alumno = $resultado->fetch_assoc();
        ?>
        <div style="background:#fff; border:1px solid #ccc; padding:20px; border-radius:10px; max-width:700px; margin:20px auto; box-shadow:0 0 10px #aaa;">
            <h2 style="color:#f57c00; text-align:center;">📄 Datos del Alumno</h2>
            <p><strong>Nombre:</strong> <?php echo $alumno['nombre']; ?></p>
            <p><strong>Apellido:</strong> <?php echo $alumno['apellido']; ?></p>
            <p><strong>DNI:</strong> <?php echo $alumno['dni']; ?></p>
            <p><strong>Correo:</strong> <?php echo $alumno['correo']; ?></p>
            <p><strong>Teléfono:</strong> <?php echo $alumno['telefono']; ?></p>
            <!-- Agregá más campos según lo que tengas en tu base de datos -->
            <div style="margin-top:20px; text-align:center;">
                <a href="alumnos.php" style="color:#fff; background:#f57c00; padding:10px 20px; border-radius:5px; text-decoration:none;">⬅ Volver al listado</a>
            </div>
        </div>
        <?php
        exit; // Detiene la ejecución del resto del archivo
    } else {
        echo "<p style='color:red;'>Alumno no encontrado.</p>";
    }
}

// alumnos.php - Gestión integrada de alumnos (adaptado con diseño de dashboard)
session_start();
// 1. Verificación de sesión y tipo de usuario
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php"); // Asumiendo que index.php está en la raíz
    exit;
}
if ($_SESSION['tipo'] !== 'administrador') {
    // Redirigir si no es administrador, quizás a dashboard con un mensaje
    $_SESSION['mensaje_error'] = "Acceso no autorizado.";
    header("Location: dashboard.php");
    exit;
}

// 2. Incluir el archivo de conexión a la base de datos
require_once '../config/db.php'; // Ajusta la ruta si es necesario

// 3. Obtener el nombre del usuario para el sidebar
$stmt_user = $mysqli->prepare("
    SELECT CONCAT(p.apellidos ,' ', p.nombres) as nombre_completo 
    FROM persona p 
    JOIN usuario u ON p.usuario_id = u.id 
    WHERE u.id = ?
");
if ($stmt_user) {
    $stmt_user->bind_param("i", $_SESSION['usuario_id']);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    $usuario_sidebar = $result_user->fetch_assoc();
    $stmt_user->close();
} else {
    $usuario_sidebar = ['nombre_completo' => 'Admin ISEF']; // Fallback
}


$mensaje = '';
$error = '';

// Función para generar nombre de usuario único basado en nombre y apellido
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
        if (!$stmt_check) { // Manejo de error en preparación de consulta
            error_log("Error al preparar la consulta de verificación de username: " . $mysqli_conn->error);
            // Fallback: generar un username aleatorio para evitar bucle infinito
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

// Procesar formulario de creación o edición de alumno
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? ''; // Usar operador de coalescencia nula

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
                    $carpeta = realpath(__DIR__ . '/../uploads/fotos_alumnos');
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
            $password_hash = password_hash($_POST['dni'], PASSWORD_DEFAULT);
            $tipo = 'alumno';
            $activo = 1; // Por defecto activo
            
            $stmt_u = $mysqli->prepare("INSERT INTO usuario (username, password, tipo, activo, debe_cambiar_password) VALUES (?, ?, ?, ?, 0)"); // debe_cambiar_password = 0 (false) por defecto
            $stmt_u->bind_param("sssi", $username, $password_hash, $tipo, $activo);
            $stmt_u->execute();
            $usuario_id_new = $mysqli->insert_id;
            $stmt_u->close();

            $stmt_p = $mysqli->prepare("INSERT INTO persona (usuario_id, apellidos, nombres, dni, fecha_nacimiento, celular, domicilio, contacto_emergencia) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_p->bind_param("isssssss", $usuario_id_new, $_POST['apellidos'], $_POST['nombres'], $_POST['dni'], $_POST['fecha_nacimiento'], $_POST['celular'], $_POST['domicilio'], $_POST['contacto_emergencia']);
            $stmt_p->execute();
            $persona_id_new = $mysqli->insert_id;
            $stmt_p->close();

            $stmt_a = $mysqli->prepare("INSERT INTO alumno (persona_id, legajo, fecha_ingreso, cohorte) VALUES (?, ?, ?, ?)");
            $stmt_a->bind_param("issi", $persona_id_new, $_POST['legajo'], $_POST['fecha_ingreso'], $_POST['cohorte']);
            $stmt_a->execute();
            $stmt_a->close();
            
            $mysqli->commit();
            $_SESSION['mensaje_exito'] = "Alumno creado correctamente. Nombre de usuario: $username";
        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['mensaje_error'] = "Error al crear el alumno: " . $e->getMessage();
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
            $stmt_p_upd->bind_param("sssssssi", $_POST['apellidos'], $_POST['nombres'], $_POST['dni'], $_POST['fecha_nacimiento'], $_POST['celular'], $_POST['domicilio'], $_POST['contacto_emergencia'], $_POST['persona_id'], $foto_url, $_POST['persona_id']);
            $stmt_p_upd->execute();
            $stmt_p_upd->close();

            $stmt_a_upd = $mysqli->prepare("UPDATE alumno SET legajo = ?, fecha_ingreso = ?, cohorte = ? WHERE persona_id = ?");
            $stmt_a_upd->bind_param("ssii", $_POST['legajo'], $_POST['fecha_ingreso'], $_POST['cohorte'], $_POST['persona_id']);
            $stmt_a_upd->execute();
            $stmt_a_upd->close();

            // Opcional: Actualizar estado activo/inactivo del usuario
            if (isset($_POST['activo'])) {
                 $activo_user = $_POST['activo'] == '1' ? 1 : 0;
                 $stmt_user_act = $mysqli->prepare("UPDATE usuario SET activo = ? WHERE id = (SELECT usuario_id FROM persona WHERE id = ?)");
                 $stmt_user_act->bind_param("ii", $activo_user, $_POST['persona_id']);
                 $stmt_user_act->execute();
                 $stmt_user_act->close();
            }
            
            $mysqli->commit();
            $_SESSION['mensaje_exito'] = "Alumno actualizado correctamente.";
        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['mensaje_error'] = "Error al actualizar el alumno: " . $e->getMessage();
        }
    } elseif ($accion === 'eliminar') {
        $mysqli->begin_transaction();
        try {
            $stmt_get_uid = $mysqli->prepare("SELECT usuario_id FROM persona WHERE id = ?");
            $stmt_get_uid->bind_param("i", $_POST['persona_id_eliminar']);
            $stmt_get_uid->execute();
            $result_uid = $stmt_get_uid->get_result();
            $row_uid = $result_uid->fetch_assoc();
            $usuario_id_del = $row_uid['usuario_id'] ?? null;
            $stmt_get_uid->close();

            if ($usuario_id_del) {
                // Primero eliminar de alumno, luego persona, luego usuario (o configurar ON DELETE CASCADE en la BD)
                // Asumiendo que ON DELETE CASCADE está configurado para alumno -> persona y persona -> usuario (en usuario_id)
                // O eliminamos explícitamente si no hay CASCADE o queremos ser específicos.
                // Si no hay cascade desde persona a alumno, primero alumno:
                $stmt_del_a = $mysqli->prepare("DELETE FROM alumno WHERE persona_id = ?");
                $stmt_del_a->bind_param("i", $_POST['persona_id_eliminar']);
                $stmt_del_a->execute();
                $stmt_del_a->close();

                $stmt_del_p = $mysqli->prepare("DELETE FROM persona WHERE id = ?");
                $stmt_del_p->bind_param("i", $_POST['persona_id_eliminar']);
                $stmt_del_p->execute();
                $stmt_del_p->close();
                
                $stmt_del_u = $mysqli->prepare("DELETE FROM usuario WHERE id = ?");
                $stmt_del_u->bind_param("i", $usuario_id_del);
                $stmt_del_u->execute();
                $stmt_del_u->close();
            } else {
                 throw new Exception("No se encontró el usuario asociado a la persona.");
            }
            
            $mysqli->commit();
            $_SESSION['mensaje_exito'] = "Alumno eliminado correctamente.";
        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['mensaje_error'] = "Error al eliminar el alumno: " . $e->getMessage();
        }
    }
    
    header("Location: alumnos.php");
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

// Obtener lista de alumnos
$query_alumnos = "
    SELECT 
        p.id AS persona_id,
        a.id AS alumno_id,
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
        a.legajo,
        a.fecha_ingreso,
        a.cohorte
    FROM 
        alumno a
    JOIN 
        persona p ON a.persona_id = p.id
    JOIN 
        usuario u ON p.usuario_id = u.id
    ORDER BY 
        p.apellidos, p.nombres
";
$resultado_alumnos = $mysqli->query($query_alumnos);
$lista_alumnos = [];
if ($resultado_alumnos) {
    while ($fila = $resultado_alumnos->fetch_assoc()) {
        $lista_alumnos[] = $fila;
    }
}

// Para los selectores de filtros (si se implementan más adelante)
// $cursos_existentes = array_unique(array_column($lista_alumnos, 'cohorte')); // Ejemplo
?>
<?php
// [Código PHP permanece exactamente igual hasta la parte del HTML]
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Alumnos - Sistema ISEF</title>
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
            background: url('fondo.png') no-repeat center center fixed;
            background-size: cover;
            color: var(--gray-dark);
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
        
        /* Paleta de colores naranja */
        :root {
            --orange-primary: rgba(230, 92, 0, 0.9);
            --orange-light: rgba(255, 140, 66, 0.8);
            --orange-lighter: rgba(255, 165, 102, 0.7);
            --orange-lightest: rgba(255, 224, 204, 0.6);
            --white: rgba(255, 255, 255, 0.9);
            --white-70: rgba(255, 255, 255, 0.7);
            --white-50: rgba(255, 255, 255, 0.5);
            --gray-light: rgba(245, 245, 245, 0.7);
            --gray-medium: rgba(224, 224, 224, 0.6);
            --gray-dark: rgba(51, 51, 51, 0.9);
        }
        
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
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
            overflow-y: auto;
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
        
        .brand-icon {
            width: 32px;
            height: 32px;
            background: var(--white);
            color: var(--orange-primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
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
        
        .nav-section {
            margin-bottom: 2rem;
        }
        
        .nav-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.8);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
            padding: 0 0.75rem;
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-item {
            margin-bottom: 0.25rem;
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
        
        .nav-link:hover {
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
            transition: all 0.3s;
        }
        
        .user-info:hover {
            background: rgba(255, 255, 255, 0.2);
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
            font-weight: 500;
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
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 0.875rem;
            border: none;
            background: rgba(255, 255, 255, 0.1);
            width: 100%;
            cursor: pointer;
        }
        
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
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
            transition: all 0.3s;
        }
        
        .header:hover {
            background: rgba(255, 255, 255, 0.85);
        }
        
        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            padding: 0.5rem;
            cursor: pointer;
            border-radius: 4px;
            color: var(--orange-primary);
        }
        
        .sidebar-toggle:hover {
            background: var(--orange-lightest);
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray-dark);
        }
        
        .breadcrumb a {
            color: inherit;
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .breadcrumb a:hover {
            color: var(--orange-primary);
        }
        
        .content {
            flex: 1;
            padding: 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            position: relative;
            z-index: 1;
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
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .page-subtitle {
            color: var(--gray-dark);
            opacity: 0.9;
            font-size: 1rem;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        
        /* Mensajes */
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
            backdrop-filter: blur(2px);
        }
        
        .message-toast.error {
            background-color: rgba(254, 226, 226, 0.8);
            color: #991b1b;
            border-color: rgba(254, 202, 202, 0.6);
            backdrop-filter: blur(2px);
        }
        
        /* Cards */
        .card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            margin-bottom: 1.5rem;
            transition: all 0.3s;
        }
        
        .card:hover {
            background: rgba(255, 255, 255, 0.85);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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
        
        /* Formularios */
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
            color: var(--gray-dark);
        }
        
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="number"],
        .form-group input[type="email"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.625rem 0.75rem;
            border: 1px solid var(--gray-medium);
            border-radius: 6px;
            font-size: 0.875rem;
            color: var(--gray-dark);
            background-color: var(--white);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--orange-primary);
            outline: none;
            box-shadow: 0 0 0 1px var(--orange-primary);
        }
        
        .form-group textarea {
            min-height: 80px;
        }
        
        /* Botones */
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
            white-space: nowrap;
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
        
        .btn-primary:hover {
            background-color: rgba(230, 92, 0, 1);
        }
        
        .btn-secondary {
            background-color: var(--gray-light);
            color: var(--gray-dark);
            border-color: var(--gray-medium);
        }
        
        .btn-secondary:hover {
            background-color: var(--gray-medium);
        }
        
        .btn-danger {
            background-color: #dc2626;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #b91c1c;
        }
        
        .btn-outline {
            background-color: transparent;
            color: var(--gray-dark);
            border: 1px solid var(--gray-medium);
        }
        
        .btn-outline:hover {
            background-color: var(--white-70);
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }
        
        .btn-sm i {
            margin-right: 0.25rem;
            width: 14px;
            height: 14px;
        }
        
        /* Tablas */
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
            color: var(--gray-dark);
            font-weight: 600;
        }
        
        .styled-table tr:last-child td {
            border-bottom: none;
        }
        
        .styled-table tr:hover {
            background-color: rgba(255, 255, 255, 0.5);
        }
        
        .table-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }
        
        /* Badges */
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
        
        /* Modal */
        .modal-content {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            backdrop-filter: blur(5px);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -280px;
                transition: left 0.3s;
                z-index: 1000;
            }
            
            .sidebar.open {
                left: 0;
            }
            
            .sidebar-toggle {
                display: block;
            }
            
            .content {
                padding: 1rem;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                max-width: calc(100% - 2rem);
            }
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        .overlay.show {
            display: block;
        }
    </style>
</head>
<body>
    <!-- [El resto del HTML permanece exactamente igual] -->
</body>
</html>
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
                        <li class="nav-item">
                            <a href="inscripciones.php" class="nav-link">
                                <i data-lucide="user-plus" class="nav-icon"></i>
                                <span>Inscripciones</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="situacion.php" class="nav-link">
                                <i data-lucide="bar-chart-3" class="nav-icon"></i>
                                <span>Situación Académica</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="certificados.php" class="nav-link">
                                <i data-lucide="file-text" class="nav-icon"></i>
                                <span>Certificados</span>
                            </a>
                        </li>
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
                    <span>Alumnos</span>
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
                        <h1 class="page-title">Gestión de Alumnos</h1>
                        <p class="page-subtitle">Administra la información de los estudiantes del instituto.</p>
                    </div>
                    <button class="btn btn-primary" onclick="mostrarFormCreacion()">
                        <i data-lucide="plus"></i>
                        Nuevo Alumno
                    </button>
                </div>

                <?php if ($mensaje): ?>
                    <div class="message-toast success" role="alert"><?= htmlspecialchars($mensaje) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="message-toast error" role="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="card" id="creacionFormCard" style="display:none;"> <div class="card-header">
                        <h2 class="card-title">Registrar Nuevo Alumno</h2>
                        <p class="card-description">Completa los datos para agregar un nuevo estudiante.</p>
                    </div>
                    <form method="post">
                        <input type="hidden" name="accion" value="crear">
                        <div class="card-content">
                            <div class="form-grid">
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
                                    <label for="legajo">Legajo:</label>
                                    <input type="text" id="legajo" name="legajo" required>
                                </div>
                                <div class="form-group">
                                    <label for="fecha_ingreso">Fecha de ingreso:</label>
                                    <input type="date" id="fecha_ingreso" name="fecha_ingreso" required>
                                </div>
                                <div class="form-group">
                                    <label for="cohorte">Cohorte (Año):</label>
                                    <input type="number" id="cohorte" name="cohorte" required min="1900" max="<?= date('Y')+1 ?>">
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="button" class="btn btn-secondary" onclick="ocultarFormCreacion()">Cancelar</button>
                            <button type="submit" class="btn btn-primary" style="margin-left:0.5rem;"><i data-lucide="save"></i>Crear Alumno</button>
                        </div>
                    </form>
                </div>
                

               <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Lista de Alumnos Registrados</h2>
                        <p class="card-description">Visualiza y gestiona los alumnos existentes.</p>
                    </div>
                    <div class="card-content" style="padding-top: 1rem; padding-bottom: 0;">
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <input type="search" id="searchInput" class="form-control" onkeyup="filterTable()" placeholder="Buscar por Legajo, Nombre, Apellido o DNI..." style="width: 100%; padding: 0.625rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 0.875rem;">
                        </div>
                        <div class="table-container">
                            <table class="styled-table">
                                <thead>
                                    <tr>
                                        <th>Legajo</th>
                                        <th>Nombre Completo</th>
                                        <th>DNI</th>
                                        <th>Cohorte</th>
                                        <th>Usuario</th>
                                        <th>Estado</th>
                                        <th class="text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($lista_alumnos)): ?>
                                        <tr id="noAlumnosRow">
                                            <td colspan="7" style="text-align:center; padding: 2rem;">No hay alumnos registrados.</td> 
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($lista_alumnos as $alu): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($alu['legajo']) ?></td>
                                            <td><?= htmlspecialchars($alu['apellidos']) ?>, <?= htmlspecialchars($alu['nombres']) ?></td>
                                            <td><?= htmlspecialchars($alu['dni']) ?></td>
                                            <td><?= htmlspecialchars($alu['cohorte']) ?></td> 
                                            <td><?= htmlspecialchars($alu['username']) ?></td> 
                                            <td>
                                                <?php if ($alu['activo']): ?> 
                                                    <span class="badge badge-success"><i data-lucide="user-check"></i>Activo</span> 
                                                <?php else: ?>
                                                    <span class="badge badge-danger"><i data-lucide="user-x"></i>Inactivo</span> 
                                                <?php endif; ?>
                                            </td>
                                            <td class="table-actions">
                                                <button class="btn btn-outline btn-sm" onclick='cargarDatosEdicion(<?= htmlspecialchars(json_encode($alu), ENT_QUOTES, 'UTF-8') ?>)' title="Editar Alumno"> 
                                                    <i data-lucide="edit-2"></i>
                                                </button>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('¿Está seguro de eliminar este alumno?\nEsta acción no se puede deshacer.');">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="persona_id_eliminar" value="<?= $alu['persona_id'] ?>"> 
                                                    <button type="submit" class="btn btn-outline btn-danger-outline btn-sm" title="Eliminar Alumno">
                                                        <i data-lucide="trash-2"></i> 
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    
                                    <tr id="noResultsSearchRow" style="display: none;">
                                        <td colspan="7" style="text-align:center; padding: 2rem;">No se encontraron alumnos que coincidan con la búsqueda.</td>
                                    </tr>
                            
                                </tbody>
                            </table>
                        </div>
                    </div> 
                </div>
           <div id="edicionFormContainer" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div class="modal-content card"> <div class="card-header">
                <h2 class="card-title">Editar Alumno</h2>
                <p class="card-description">Modifica la información del estudiante seleccionado.</p>
            </div>
            <form method="post" id="form-editar">
                 <input type="hidden" name="accion" value="editar">
                 <input type="hidden" name="persona_id" id="edit-persona-id">
                <div class="card-content">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit-apellidos">Apellidos:</label>
                            <input type="text" id="edit-apellidos" name="apellidos" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-nombres">Nombres:</label>
                            <input type="text" id="edit-nombres" name="nombres" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-dni">DNI:</label>
                            <input type="text" id="edit-dni" name="dni" required pattern="\d{7,8}" title="DNI debe ser 7 u 8 dígitos numéricos.">
                        </div>
                        <div class="form-group">
                            <label for="edit-fecha-nacimiento">Fecha de nacimiento:</label>
                            <input type="date" id="edit-fecha-nacimiento" name="fecha_nacimiento" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-celular">Celular:</label>
                            <input type="text" id="edit-celular" name="celular">
                        </div>
                        <div class="form-group">
                            <label for="edit-domicilio">Domicilio:</label>
                            <input type="text" id="edit-domicilio" name="domicilio">
                        </div>
                        <div class="form-group">
                            <label for="edit-contacto-emergencia">Contacto de emergencia (Teléfono):</label>
                            <input type="text" id="edit-contacto-emergencia" name="contacto_emergencia">
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
                                    
                        <div class="form-group">

                            <label for="edit-legajo">Legajo:</label>
                            <input type="text" id="edit-legajo" name="legajo" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-fecha-ingreso">Fecha de ingreso:</label>
                            <input type="date" id="edit-fecha-ingreso" name="fecha_ingreso" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-cohorte">Cohorte (Año):</label>
                            <input type="number" id="edit-cohorte" name="cohorte" required min="1900" max="<?= date('Y')+1 ?>">
                        </div>
                         <div class="form-group">
                            <label for="edit-activo">Estado del Usuario:</label>
                            <select id="edit-activo" name="activo">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-secondary" onclick="ocultarFormEdicion()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="margin-left:0.5rem;"><i data-lucide="save"></i>Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

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
                window.location.href = '../index.php?logout=1'; // Ajusta la ruta si es necesario
            }
        }
        function filterTable() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toLowerCase();
            const table = document.querySelector(".styled-table");
            const tbody = table.getElementsByTagName("tbody")[0];
            const tr = tbody.getElementsByTagName("tr");
            let foundMatch = false;

            const noAlumnosRow = document.getElementById('noAlumnosRow');
            const noResultsSearchRow = document.getElementById('noResultsSearchRow');

            // Hide specific message rows initially during filtering
            if (noAlumnosRow) noAlumnosRow.style.display = 'none';
            if (noResultsSearchRow) noResultsSearchRow.style.display = 'none';

            for (let i = 0; i < tr.length; i++) {
                let row = tr[i];

                // Skip the predefined message rows from the filtering logic itself
                if (row.id === 'noAlumnosRow' || row.id === 'noResultsSearchRow') {
                    continue;
                }

                let displayRow = false;
                // Ensure cells exist before trying to access textContent
                const legajoTd = row.cells[0];
                const nombreCompletoTd = row.cells[1];
                const dniTd = row.cells[2];
                // You can also add other cells like cohorte (row.cells[3]) or username (row.cells[4]) if needed

                if (legajoTd && nombreCompletoTd && dniTd) {
                    const legajoText = legajoTd.textContent || legajoTd.innerText;
                    const nombreCompletoText = nombreCompletoTd.textContent || nombreCompletoTd.innerText;
                    const dniText = dniTd.textContent || dniTd.innerText;

                    if (legajoText.toLowerCase().indexOf(filter) > -1 ||
                        nombreCompletoText.toLowerCase().indexOf(filter) > -1 ||
                        dniText.toLowerCase().indexOf(filter) > -1) {
                        displayRow = true;
                        foundMatch = true;
                    }
                }
                row.style.display = displayRow ? "" : "none";
            }

            // Logic to display the correct "no results" message
            const isListaAlumnosEmpty = <?php echo empty($lista_alumnos) ? 'true' : 'false'; ?>;

            if (filter === "") { // Search is cleared
                if (isListaAlumnosEmpty && noAlumnosRow) {
                    noAlumnosRow.style.display = ''; // Show "No hay alumnos registrados"
                }
                if (noResultsSearchRow) {
                     noResultsSearchRow.style.display = 'none'; // Hide "No se encontraron..."
                }
                // All actual data rows were already set to display="" if they exist
            } else { // Search has text
                if (!foundMatch && noResultsSearchRow) {
                    noResultsSearchRow.style.display = ''; // Show "No se encontraron alumnos que coincidan..."
                }
                if (noAlumnosRow){
                    noAlumnosRow.style.display = 'none'; // Hide "No hay alumnos" (if it was somehow visible)
                }
            }
        }

        const creacionFormCard = document.getElementById('creacionFormCard');
        function mostrarFormCreacion() {
            creacionFormCard.style.display = 'block';
            creacionFormCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        function ocultarFormCreacion() {
            creacionFormCard.style.display = 'none';
        }
        
        const edicionFormContainer = document.getElementById('edicionFormContainer');
        
        // Asegurarse de que esté oculto al cargar la página
        if (edicionFormContainer) {
            edicionFormContainer.style.display = 'none';
        }

        function cargarDatosEdicion(alumno) {
            document.getElementById('edit-persona-id').value = alumno.persona_id;
            document.getElementById('edit-apellidos').value = alumno.apellidos;
            document.getElementById('edit-nombres').value = alumno.nombres;
            document.getElementById('edit-dni').value = alumno.dni;
            document.getElementById('edit-fecha-nacimiento').value = alumno.fecha_nacimiento;
            document.getElementById('edit-celular').value = alumno.celular || '';
            document.getElementById('edit-domicilio').value = alumno.domicilio || '';
            document.getElementById('edit-contacto-emergencia').value = alumno.contacto_emergencia || '';
            document.getElementById('edit-legajo').value = alumno.legajo;
            document.getElementById('edit-fecha-ingreso').value = alumno.fecha_ingreso;
            document.getElementById('edit-cohorte').value = alumno.cohorte;
            document.getElementById('edit-activo').value = alumno.activo == '1' ? '1' : '0';

            if (edicionFormContainer) {
                edicionFormContainer.style.display = 'flex'; // Cambiar a flex para mostrarlo y centrarlo
            }
            
            // Opcional: si el contenido del modal es muy largo, hacer scroll a su inicio
            const modalContent = edicionFormContainer.querySelector('.modal-content');
            if (modalContent) {
                modalContent.scrollTop = 0; 
            }
        }
        
        function ocultarFormEdicion() {
            if (edicionFormContainer) {
                edicionFormContainer.style.display = 'none';
            }
        }

        // Cerrar modal de edición si se hace clic fuera del contenido del modal (en el overlay)
        if (edicionFormContainer) {
            edicionFormContainer.addEventListener('click', function(event) {
                if (event.target === edicionFormContainer) { // Si el clic fue directamente en el overlay
                    ocultarFormEdicion();
                }
            });
        }

    </script>
</body>
</html>