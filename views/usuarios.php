<?php
// usuarios.php - Gestión unificada de usuarios del sistema
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: index.php");
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

// --- Helper function to generate user row HTML ---
function generarFilaUsuarioHTML($usuario, $mysqli) {
    $filaHTML = '<tr>';
    $filaHTML .= '<td>' . ucfirst(htmlspecialchars($usuario['tipo'])) . '</td>';
    $filaHTML .= '<td>' . htmlspecialchars($usuario['apellidos']) . ', ' . htmlspecialchars($usuario['nombres']) . '</td>';
    $filaHTML .= '<td>' . htmlspecialchars($usuario['dni']) . '</td>';
    $filaHTML .= '<td>' . htmlspecialchars($usuario['username']) . '</td>';
    $filaHTML .= '<td>
                    <span class="' . ($usuario['activo'] ? 'active-yes' : 'active-no') . '">'
                    . ($usuario['activo'] ? 'Activo' : 'Inactivo') .
                    '</span><br>'
                    . ($usuario['debe_cambiar_password'] ? '<small>(Cambiar pass)</small>' : '') .
                 '</td>';
    
    $info_especifica = '';
    switch ($usuario['tipo']) {
        case 'alumno':
            $info_especifica .= "Legajo: " . htmlspecialchars($usuario['legajo_alumno'] ?? '');
            $info_especifica .= "<br>Cohorte: " . htmlspecialchars($usuario['cohorte_alumno'] ?? '');
            $info_especifica .= "<br>F. Ingreso: " . htmlspecialchars($usuario['fecha_ingreso_alumno'] ?? '');
            break;
        case 'profesor':
            $info_especifica .= "Título: " . htmlspecialchars($usuario['titulo_profesional_profesor'] ?? '');
            $info_especifica .= "<br>F. Ingreso: " . htmlspecialchars($usuario['fecha_ingreso_profesor'] ?? '');
            $info_especifica .= "<br>Consulta: " . nl2br(htmlspecialchars($usuario['horas_consulta_profesor'] ?? ''));
            break;
        case 'preceptor':
            $info_especifica .= "Título: " . htmlspecialchars($usuario['titulo_profesional_preceptor'] ?? '');
            $info_especifica .= "<br>F. Ingreso: " . htmlspecialchars($usuario['fecha_ingreso_preceptor'] ?? '');
            $info_especifica .= "<br>Sector: " . htmlspecialchars($usuario['sector_asignado_preceptor'] ?? '');
            break;
    }
    $filaHTML .= '<td>' . $info_especifica . '</td>';

    $filaHTML .= '<td class="actions-cell">
                     <button type="button" class="edit" 
                        data-usuario_id="' . $usuario['usuario_id'] . '"
                        data-tipo="' . htmlspecialchars($usuario['tipo']) . '"
                        data-activo="' . $usuario['activo'] . '"
                        data-debe_cambiar_password="' . $usuario['debe_cambiar_password'] . '"
                        data-apellidos="' . htmlspecialchars($usuario['apellidos']) . '"
                        data-nombres="' . htmlspecialchars($usuario['nombres']) . '"
                        data-dni="' . htmlspecialchars($usuario['dni']) . '"
                        data-fecha_nacimiento="' . htmlspecialchars($usuario['fecha_nacimiento']) . '"
                        data-celular="' . htmlspecialchars($usuario['celular'] ?? '') . '"
                        data-domicilio="' . htmlspecialchars($usuario['domicilio'] ?? '') . '"
                        data-contacto_emergencia="' . htmlspecialchars($usuario['contacto_emergencia'] ?? '') . '"
                        data-legajo_alumno="' . htmlspecialchars($usuario['legajo_alumno'] ?? '') . '"
                        data-cohorte_alumno="' . htmlspecialchars($usuario['cohorte_alumno'] ?? '') . '"
                        data-fecha_ingreso_alumno="' . htmlspecialchars($usuario['fecha_ingreso_alumno'] ?? '') . '"
                        data-titulo_profesional_profesor="' . htmlspecialchars($usuario['titulo_profesional_profesor'] ?? '') . '"
                        data-fecha_ingreso_profesor="' . htmlspecialchars($usuario['fecha_ingreso_profesor'] ?? '') . '"
                        data-horas_consulta_profesor="' . htmlspecialchars($usuario['horas_consulta_profesor'] ?? '') . '"
                        data-titulo_profesional_preceptor="' . htmlspecialchars($usuario['titulo_profesional_preceptor'] ?? '') . '"
                        data-fecha_ingreso_preceptor="' . htmlspecialchars($usuario['fecha_ingreso_preceptor'] ?? '') . '"
                        data-sector_asignado_preceptor="' . htmlspecialchars($usuario['sector_asignado_preceptor'] ?? '') . '"
                        onclick="populateEditForm(this)">Editar</button>
                        
                    <form method="post" style="display:inline;" onsubmit="return confirm(\'¿Está seguro de eliminar este usuario? Esta acción no se puede deshacer y eliminará todos los datos asociados.\');">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="usuario_id" value="' . $usuario['usuario_id'] . '">
                        <button type="submit" class="delete">Eliminar</button>
                    </form>
                </td>';
    $filaHTML .= '</tr>';
    return $filaHTML;
}
// --- End Helper function ---


// --- AJAX Search Handling ---
if (isset($_GET['action']) && $_GET['action'] === 'search_users' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $searchTerm = $mysqli->real_escape_string($_GET['term'] ?? '');
    $searchQuery = "
        SELECT 
            u.id AS usuario_id, u.username, u.tipo, u.activo, u.debe_cambiar_password,
            p.id AS persona_id, p.apellidos, p.nombres, p.dni, p.fecha_nacimiento, p.celular, p.domicilio, p.contacto_emergencia,
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
            $output .= generarFilaUsuarioHTML($usuario, $mysqli);
        }
    } else {
        $output = '<tr><td colspan="7">No se encontraron usuarios con ese criterio.</td></tr>';
    }
    echo $output;
    $mysqli->close();
    exit;
}
// --- End AJAX Search Handling ---


$mensaje = '';
$error = '';

// Función para generar nombre de usuario único
function generarUsername($nombre, $apellido, $mysqli) {
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
        if (!$stmt_check) { // Falló la preparación del statement
            error_log("Error al preparar la consulta para verificar username: " . $mysqli->error);
            // Podrías lanzar una excepción o retornar un username que probablemente falle luego,
            // o manejarlo de otra forma dependiendo de tu lógica de errores.
            // Por ahora, para evitar bucle infinito si $mysqli->prepare siempre falla:
            return $baseUsername . time(); // Retorna algo único para intentar evitar colisión
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


// Procesar acciones POST (crear, editar, eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        if ($_POST['accion'] === 'crear') {
            $mysqli->begin_transaction();
            try {
                $username = generarUsername($_POST['nombres'], $_POST['apellidos'], $mysqli); 
                $password_hash = password_hash($_POST['dni'], PASSWORD_DEFAULT); 
                $tipo = $_POST['tipo'];
                
                $activo = 1; // Usuario siempre activo al crear
                $debe_cambiar = 1; // Siempre forzar cambio de contraseña al crear

                // Statement para la tabla 'usuario'
                $stmt_usuario = $mysqli->prepare("INSERT INTO usuario (username, password, tipo, activo, debe_cambiar_password) VALUES (?, ?, ?, ?, ?)");
                if (!$stmt_usuario) {
                    throw new Exception("Error al preparar la consulta para 'usuario': " . $mysqli->error);
                }
                $stmt_usuario->bind_param("sssii", $username, $password_hash, $tipo, $activo, $debe_cambiar);
                if (!$stmt_usuario->execute()) {
                    throw new Exception("Error al ejecutar la consulta para 'usuario': " . $stmt_usuario->error);
                }
                $usuario_id = $mysqli->insert_id; 
                $stmt_usuario->close(); 
                
                // Statement para la tabla 'persona'
                $stmt_persona = $mysqli->prepare("INSERT INTO persona (usuario_id, apellidos, nombres, dni, fecha_nacimiento, celular, domicilio, contacto_emergencia) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt_persona) {
                    throw new Exception("Error al preparar la consulta para 'persona': " . $mysqli->error);
                }
                $stmt_persona->bind_param("isssssss", $usuario_id, $_POST['apellidos'], $_POST['nombres'], $_POST['dni'], $_POST['fecha_nacimiento'], $_POST['celular'], $_POST['domicilio'], $_POST['contacto_emergencia']);
                if (!$stmt_persona->execute()) {
                    throw new Exception("Error al ejecutar la consulta para 'persona': " . $stmt_persona->error);
                }
                $persona_id = $mysqli->insert_id; 
                $stmt_persona->close(); 
                
                // Statement para la tabla específica del rol (alumno, profesor, preceptor)
                $stmt_role = null; 
                
                switch ($tipo) { 
                    case 'alumno':
                        $stmt_role = $mysqli->prepare("INSERT INTO alumno (persona_id, legajo, fecha_ingreso, cohorte) VALUES (?, ?, ?, ?)"); 
                        if (!$stmt_role) {
                             throw new Exception("Error al preparar la consulta para 'alumno': " . $mysqli->error);
                        }
                        $stmt_role->bind_param("issi", $persona_id, $_POST['legajo_alumno'], $_POST['fecha_ingreso_alumno'], $_POST['cohorte_alumno']); 
                        break;
                    case 'profesor':
                        $stmt_role = $mysqli->prepare("INSERT INTO profesor (persona_id, titulo_profesional, fecha_ingreso, horas_consulta) VALUES (?, ?, ?, ?)"); 
                        if (!$stmt_role) {
                             throw new Exception("Error al preparar la consulta para 'profesor': " . $mysqli->error);
                        }
                        $stmt_role->bind_param("isss", $persona_id, $_POST['titulo_profesional_profesor'], $_POST['fecha_ingreso_profesor'], $_POST['horas_consulta_profesor']); 
                        break;
                    case 'preceptor':
                        $stmt_role = $mysqli->prepare("INSERT INTO preceptor (persona_id, titulo_profesional, fecha_ingreso, sector_asignado) VALUES (?, ?, ?, ?)"); 
                        if (!$stmt_role) {
                             throw new Exception("Error al preparar la consulta para 'preceptor': " . $mysqli->error);
                        }
                        $stmt_role->bind_param("isss", $persona_id, $_POST['titulo_profesional_preceptor'], $_POST['fecha_ingreso_preceptor'], $_POST['sector_asignado_preceptor']); 
                        break;
                    // No se necesita 'case' para 'administrador' aquí, ya que no tienen tabla de rol adicional.
                }
                
                // Ejecutar y cerrar $stmt_role SOLO si fue preparado (es decir, no es 'administrador')
                if ($stmt_role) {
                    if (!$stmt_role->execute()) {
                        throw new Exception("Error al ejecutar la consulta para el rol específico '$tipo': " . $stmt_role->error);
                    }
                    $stmt_role->close(); 
                }
                
                $mysqli->commit(); 
                $mensaje = "Usuario creado correctamente. Nombre de usuario: $username"; 
                
            } catch (Exception $e) {
                $mysqli->rollback(); 
                $error = "Error al crear el usuario: " . $e->getMessage(); 
            }
        } elseif ($_POST['accion'] === 'editar') { // Asegúrate que esta lógica también esté correcta y no reutilice $stmt incorrectamente
            $mysqli->begin_transaction();
            try {
                $usuario_id_edit = (int)$_POST['usuario_id_edit'];
                $tipo = $_POST['tipo'];
                $activo = isset($_POST['activo_estado']) ? (int)$_POST['activo_estado'] : 0;
                $debe_cambiar = isset($_POST['debe_cambiar_password_edit']) && $_POST['debe_cambiar_password_edit'] == '1' ? 1 : 0;

                $stmt_usuario_update = $mysqli->prepare("UPDATE usuario SET tipo = ?, activo = ?, debe_cambiar_password = ? WHERE id = ?");
                if(!$stmt_usuario_update) throw new Exception("Error al preparar update para 'usuario': ".$mysqli->error);
                $stmt_usuario_update->bind_param("siii", $tipo, $activo, $debe_cambiar, $usuario_id_edit);
                if(!$stmt_usuario_update->execute()) throw new Exception("Error al ejecutar update para 'usuario': ".$stmt_usuario_update->error);
                $stmt_usuario_update->close();

                $stmt_persona_update = $mysqli->prepare("UPDATE persona SET apellidos = ?, nombres = ?, dni = ?, fecha_nacimiento = ?, celular = ?, domicilio = ?, contacto_emergencia = ? WHERE usuario_id = ?");
                if(!$stmt_persona_update) throw new Exception("Error al preparar update para 'persona': ".$mysqli->error);
                $stmt_persona_update->bind_param("sssssssi", $_POST['apellidos'], $_POST['nombres'], $_POST['dni'], $_POST['fecha_nacimiento'], $_POST['celular'], $_POST['domicilio'], $_POST['contacto_emergencia'], $usuario_id_edit);
                if(!$stmt_persona_update->execute()) throw new Exception("Error al ejecutar update para 'persona': ".$stmt_persona_update->error);
                $stmt_persona_update->close();

                $persona_id_result = $mysqli->query("SELECT id FROM persona WHERE usuario_id = $usuario_id_edit");
                if ($persona_id_result->num_rows > 0) {
                    $persona_id = $persona_id_result->fetch_assoc()['id'];

                    // Limpiar datos de roles anteriores (Considera si esta lógica de borrado es la deseada al cambiar de tipo)
                    if ($tipo !== 'alumno') $mysqli->query("DELETE FROM alumno WHERE persona_id = $persona_id");
                    if ($tipo !== 'profesor') $mysqli->query("DELETE FROM profesor WHERE persona_id = $persona_id");
                    if ($tipo !== 'preceptor') $mysqli->query("DELETE FROM preceptor WHERE persona_id = $persona_id");

                    $stmt_role_update = null;
                    if ($tipo === 'alumno') {
                        $stmt_role_update = $mysqli->prepare("INSERT INTO alumno (persona_id, legajo, fecha_ingreso, cohorte) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE legajo=VALUES(legajo), fecha_ingreso=VALUES(fecha_ingreso), cohorte=VALUES(cohorte)");
                        if(!$stmt_role_update) throw new Exception("Error al preparar insert/update para 'alumno': ".$mysqli->error);
                        $stmt_role_update->bind_param("isss", $persona_id, $_POST['legajo_alumno'], $_POST['fecha_ingreso_alumno'], $_POST['cohorte_alumno']);
                    } elseif ($tipo === 'profesor') {
                        $stmt_role_update = $mysqli->prepare("INSERT INTO profesor (persona_id, titulo_profesional, fecha_ingreso, horas_consulta) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE titulo_profesional=VALUES(titulo_profesional), fecha_ingreso=VALUES(fecha_ingreso), horas_consulta=VALUES(horas_consulta)");
                        if(!$stmt_role_update) throw new Exception("Error al preparar insert/update para 'profesor': ".$mysqli->error);
                        $stmt_role_update->bind_param("isss", $persona_id, $_POST['titulo_profesional_profesor'], $_POST['fecha_ingreso_profesor'], $_POST['horas_consulta_profesor']);
                    } elseif ($tipo === 'preceptor') {
                        $stmt_role_update = $mysqli->prepare("INSERT INTO preceptor (persona_id, titulo_profesional, fecha_ingreso, sector_asignado) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE titulo_profesional=VALUES(titulo_profesional), fecha_ingreso=VALUES(fecha_ingreso), sector_asignado=VALUES(sector_asignado)");
                        if(!$stmt_role_update) throw new Exception("Error al preparar insert/update para 'preceptor': ".$mysqli->error);
                        $stmt_role_update->bind_param("isss", $persona_id, $_POST['titulo_profesional_preceptor'], $_POST['fecha_ingreso_preceptor'], $_POST['sector_asignado_preceptor']);
                    }
                    
                    if (isset($stmt_role_update)) {
                        if(!$stmt_role_update->execute()) throw new Exception("Error al ejecutar insert/update para rol '$tipo': ".$stmt_role_update->error);
                        $stmt_role_update->close();
                    }
                } else {
                    // Esto podría pasar si el usuario_id existe en 'usuario' pero no hay una 'persona' asociada (inconsistencia de datos)
                     // O si el $usuario_id_edit no es válido.
                    throw new Exception("No se encontró la persona asociada al usuario ID: $usuario_id_edit.");
                }
                
                $mysqli->commit();
                $mensaje = "Usuario actualizado correctamente.";

            } catch (Exception $e) {
                $mysqli->rollback();
                $error = "Error al actualizar el usuario: " . $e->getMessage();
            }

        } elseif ($_POST['accion'] === 'eliminar') { 
             $mysqli->begin_transaction();
            try {
                
                $stmt_delete = $mysqli->prepare("DELETE FROM usuario WHERE id = ?"); 
                if(!$stmt_delete) throw new Exception("Error al preparar delete para 'usuario': ".$mysqli->error);
                $stmt_delete->bind_param("i", $_POST['usuario_id']); 
                if(!$stmt_delete->execute()) throw new Exception("Error al ejecutar delete para 'usuario': ".$stmt_delete->error);
                
                if ($stmt_delete->affected_rows > 0) {
                    $mysqli->commit();
                    $mensaje = "Usuario eliminado correctamente."; 
                } else {
                    $mysqli->rollback(); // Si no afectó filas, puede que el usuario no existiera.
                    $error = "No se pudo eliminar el usuario (puede que ya haya sido eliminado o no exista).";
                }
                $stmt_delete->close(); 

            } catch (mysqli_sql_exception $e) { // Captura errores específicos de SQL (como violaciones de FK si no hay cascada)
                $mysqli->rollback();
                // El código 1451 es "Cannot delete or update a parent row: a foreign key constraint fails"
                if ($e->getCode() == 1451) {
                     $error = "Error al eliminar el usuario: No se puede eliminar porque tiene datos asociados en otras tablas (e.g., inscripciones, evaluaciones). Por favor, elimine primero esos registros.";
                } else {
                    $error = "Error de base de datos al eliminar el usuario: " . $e->getMessage();
                }
            } catch (Exception $e) { // Captura otras excepciones generales
                $mysqli->rollback();
                $error = "Error general al eliminar el usuario: " . $e->getMessage(); 
            }
        }
    } 

    // SOLO redirigir si NO es una petición AJAX de búsqueda
    if (!(isset($_GET['action']) && $_GET['action'] === 'search_users')) {
         // Prevenir redirección si hay un error fatal que ya envió salida.
        if (!headers_sent()) {
            header("Location: usuarios.php?mensaje=" . urlencode($mensaje) . "&error=" . urlencode($error));
            exit;
        }
    }
} 


// Recuperar mensajes de GET para mostrar después de la redirección
if (isset($_GET['mensaje'])) $mensaje = htmlspecialchars($_GET['mensaje']); // Sanitizar para mostrar en HTML
if (isset($_GET['error'])) $error = htmlspecialchars($_GET['error']);     // Sanitizar para mostrar en HTML

// Obtener lista de usuarios para la carga inicial de la página
$usuarios_iniciales_sql = "
    SELECT 
        u.id AS usuario_id, u.username, u.tipo, u.activo, u.debe_cambiar_password,
        p.id AS persona_id, p.apellidos, p.nombres, p.dni, p.fecha_nacimiento, p.celular, p.domicilio, p.contacto_emergencia,
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
    <title>Gestión de Usuarios - ISEF</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; } 
        .container { max-width: 1200px; margin: 0 auto; } 
        .message { padding: 10px; margin: 10px 0; border-radius: 5px; } 
        .success { background-color: #d4edda; color: #155724; } 
        .error { background-color: #f8d7da; color: #721c24; } 
        .form-container { margin: 20px 0; padding:20px; border: 1px solid #ccc; border-radius: 5px;} 
        .form-group { margin-bottom: 15px; } 
        label { display: block; margin-bottom: 5px; font-weight: bold; } 
        input[type="text"], input[type="date"], input[type="number"], input[type="email"], input[type="search"], select, textarea { 
            width: 100%; padding: 8px; margin-bottom: 10px; 
            border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;  
        }
        .form-row { display: flex; gap: 20px; flex-wrap: wrap;} 
        .form-col { flex: 1; min-width: 300px; } 
        button { padding: 10px 15px; color: white; border: none; 
                 border-radius: 4px; cursor: pointer; margin-right: 5px; } 
        button.save { background-color: #4CAF50; } 
        button.edit { background-color: #2196F3; } 
        button.delete { background-color: #f44336; } 
        button.cancel { background-color: #777; } 
        table { width: 100%; border-collapse: collapse; margin-top: 20px; } 
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top;} 
        th { background-color: #f2f2f2; } 
        .tipo-fields { display: none; padding: 10px; background-color: #f9f9f9; border-radius: 4px; margin-top:10px;} 
        .active-yes { color: green; } 
        .active-no { color: red; } 
        .form-title { margin-top: 0; } 
        .actions-cell button { margin-bottom: 5px; display: block; width: 100px;}
        #searchUserInput { margin-bottom: 15px; padding: 10px; width: 50%; }

    </style>
</head>
<body>
    <div class="container">
        <h1>Gestión de Usuarios</h1>
        <a href="dashboard.php">&laquo; Volver al menú</a> 
        <?php if ($mensaje): ?>
            <div class="message success"><?= $mensaje ?></div> 
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= $error ?></div> 
        <?php endif; ?>

        <div class="form-container">
             <h2 id="formTitle" class="form-title">Nuevo Usuario</h2>
            <form method="post" id="userForm">
                <input type="hidden" name="accion" id="accion" value="crear"> 
                <input type="hidden" name="usuario_id_edit" id="usuario_id_edit" value="">
                
                <div class="form-row">
                    <div class="form-col">
                        <h3>Datos de Usuario y Personales</h3>
                        <div class="form-group">
                            <label>Tipo de usuario:</label> 
                            <select name="tipo" id="tipo" required onchange="mostrarCamposAdicionales()"> 
                                <option value="">Seleccione tipo</option>
                                <option value="administrador">Administrador</option> 
                                <option value="profesor">Profesor</option> 
                                <option value="preceptor">Preceptor</option> 
                                <option value="alumno">Alumno</option> 
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Apellidos:</label> <input type="text" name="apellidos" id="apellidos" required> 
                        </div>
                        <div class="form-group">
                            <label>Nombres:</label> <input type="text" name="nombres" id="nombres" required> 
                        </div>
                        <div class="form-group">
                            <label>DNI (usado como contraseña inicial):</label> <input type="text" name="dni" id="dni" required> 
                        </div>
                         
                         <div class="form-group" id="estadoFormGroup">
                            <label>Estado:</label>
                            <select name="activo_estado" id="activo_estado">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <div class="form-group" id="forzarCambioPassFormGroup">
                            <label>
                                <input type="checkbox" name="debe_cambiar_password_edit" id="debe_cambiar_password_edit" value="1">
                                Forzar cambio de contraseña en próximo inicio de sesión
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <h3>Datos Adicionales</h3>
                         <div class="form-group">
                            <label>Fecha de nacimiento:</label> <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" required> 
                         </div>
                        <div class="form-group">
                            <label>Celular:</label> <input type="text" name="celular" id="celular"> 
                        </div>
                        <div class="form-group">
                            <label>Domicilio:</label> <input type="text" name="domicilio" id="domicilio"> 
                        </div>
                        <div class="form-group">
                            <label>Contacto de emergencia:</label> <input type="text" name="contacto_emergencia" id="contacto_emergencia"> 
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col" style="flex-basis: 100%;">
                        <div id="campos-alumno" class="tipo-fields"> 
                            <h4>Datos del Alumno</h4>
                            <div class="form-group">
                                <label>Legajo:</label> <input type="text" name="legajo_alumno" id="legajo_alumno"> 
                            </div>
                            <div class="form-group">
                                <label>Cohorte:</label> <input type="number" name="cohorte_alumno" id="cohorte_alumno"> 
                            </div>
                            <div class="form-group">
                                <label>Fecha de ingreso (Alumno):</label> <input type="date" name="fecha_ingreso_alumno" id="fecha_ingreso_alumno"> 
                            </div>
                        </div>

                        <div id="campos-profesor" class="tipo-fields"> 
                            <h4>Datos del Profesor</h4>
                            <div class="form-group">
                                <label>Título profesional:</label> <input type="text" name="titulo_profesional_profesor" id="titulo_profesional_profesor"> 
                            </div>
                            <div class="form-group">
                                <label>Fecha de ingreso (Profesor):</label> <input type="date" name="fecha_ingreso_profesor" id="fecha_ingreso_profesor"> 
                            </div>
                            <div class="form-group">
                                <label>Horas de consulta:</label> <textarea name="horas_consulta_profesor" id="horas_consulta_profesor" rows="3"></textarea> 
                            </div>
                        </div>

                        <div id="campos-preceptor" class="tipo-fields"> 
                            <h4>Datos del Preceptor</h4>
                            <div class="form-group">
                                <label>Título profesional:</label> <input type="text" name="titulo_profesional_preceptor" id="titulo_profesional_preceptor"> 
                            </div>
                             <div class="form-group">
                                <label>Fecha de ingreso (Preceptor):</label> <input type="date" name="fecha_ingreso_preceptor" id="fecha_ingreso_preceptor"> 
                            </div>
                            <div class="form-group">
                                <label>Sector asignado:</label> 
                                <select name="sector_asignado_preceptor" id="sector_asignado_preceptor"> 
                                    <option value="">Seleccionar sector</option>
                                    <option value="Administración">Administración</option> 
                                    <option value="Biblioteca">Biblioteca</option> 
                                    <option value="Laboratorio">Laboratorio</option> 
                                    <option value="Gimnasio">Gimnasio</option> 
                                    <option value="Aulas">Aulas</option> 
                                    <option value="Secretaría">Secretaría</option> 
                                    <option value="Coordinación Académica">Coordinación Académica</option> 
                                    <option value="Deportes">Deportes</option> 
                                    <option value="General">General</option> 
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="submit" id="submitButton" class="save">Crear Usuario</button> 
                <button type="button" id="cancelEditButton" class="cancel" style="display:none;" onclick="resetForm()">Cancelar Edición</button>
            </form>
        </div>

        <h2>Lista de Usuarios</h2>
        <input type="search" id="searchUserInput" placeholder="Buscar por nombre, apellido, DNI, usuario, legajo...">
        
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Nombre</th>
                    <th>DNI</th>
                    <th>Usuario</th>
                    <th>Estado</th>
                    <th>Info Específica</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="userTableBody">
                <?php 
                if ($usuarios_result && $usuarios_result->num_rows > 0) {
                    while ($usuario = $usuarios_result->fetch_assoc()) { 
                        echo generarFilaUsuarioHTML($usuario, $mysqli);
                    }
                } else {
                    echo '<tr><td colspan="7">No hay usuarios registrados.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <script>
        let searchTimeout = null;

        document.getElementById('searchUserInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value;
            
            clearTimeout(searchTimeout); 
            searchTimeout = setTimeout(() => {
                fetch(`usuarios.php?action=search_users&term=${encodeURIComponent(searchTerm)}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest', 
                    }
                })
                .then(response => response.text())
                .then(html => {
                    document.getElementById('userTableBody').innerHTML = html;
                })
                .catch(error => console.error('Error en la búsqueda AJAX:', error));
            }, 300); 
        });

        function mostrarCamposAdicionales() {
            document.querySelectorAll('.tipo-fields').forEach(div => div.style.display = 'none'); 
            const tipo = document.getElementById('tipo').value; 
            if (tipo) { 
                const camposEspecificos = document.getElementById('campos-' + tipo); 
                if (camposEspecificos) { 
                    camposEspecificos.style.display = 'block'; 
                }
            }
        }

        function populateEditForm(button) {
            document.getElementById('formTitle').innerText = 'Editar Usuario';
            document.getElementById('accion').value = 'editar';
            document.getElementById('usuario_id_edit').value = button.dataset.usuario_id;
            
            document.getElementById('tipo').value = button.dataset.tipo; 
            document.getElementById('apellidos').value = button.dataset.apellidos; 
            document.getElementById('nombres').value = button.dataset.nombres; 
            document.getElementById('dni').value = button.dataset.dni; 
            document.getElementById('fecha_nacimiento').value = button.dataset.fecha_nacimiento; 
            document.getElementById('celular').value = button.dataset.celular; 
            document.getElementById('domicilio').value = button.dataset.domicilio; 
            document.getElementById('contacto_emergencia').value = button.dataset.contacto_emergencia; 
            
            document.getElementById('estadoFormGroup').style.display = 'block';
            document.getElementById('forzarCambioPassFormGroup').style.display = 'block';
            document.getElementById('activo_estado').value = button.dataset.activo;
            document.getElementById('debe_cambiar_password_edit').checked = (button.dataset.debe_cambiar_password == '1');

            document.getElementById('legajo_alumno').value = button.dataset.legajo_alumno || ''; 
            document.getElementById('cohorte_alumno').value = button.dataset.cohorte_alumno || ''; 
            document.getElementById('fecha_ingreso_alumno').value = button.dataset.fecha_ingreso_alumno || ''; 
            
            document.getElementById('titulo_profesional_profesor').value = button.dataset.titulo_profesional_profesor || ''; 
            document.getElementById('fecha_ingreso_profesor').value = button.dataset.fecha_ingreso_profesor || ''; 
            document.getElementById('horas_consulta_profesor').value = button.dataset.horas_consulta_profesor || ''; 

            document.getElementById('titulo_profesional_preceptor').value = button.dataset.titulo_profesional_preceptor || ''; 
            document.getElementById('fecha_ingreso_preceptor').value = button.dataset.fecha_ingreso_preceptor || ''; 
            document.getElementById('sector_asignado_preceptor').value = button.dataset.sector_asignado_preceptor || ''; 

            mostrarCamposAdicionales(); 
            
            document.getElementById('submitButton').innerText = 'Guardar Cambios';
            document.getElementById('submitButton').className = 'save'; 
            document.getElementById('cancelEditButton').style.display = 'inline-block';
            window.scrollTo(0, document.getElementById('formTitle').offsetTop);
        }

        function resetForm() {
            document.getElementById('formTitle').innerText = 'Nuevo Usuario';
            document.getElementById('userForm').reset(); 
            document.getElementById('accion').value = 'crear';
            document.getElementById('usuario_id_edit').value = '';
            
            document.getElementById('activo_estado').value = '1'; 
            document.getElementById('debe_cambiar_password_edit').checked = true; 

            document.getElementById('estadoFormGroup').style.display = 'none';
            document.getElementById('forzarCambioPassFormGroup').style.display = 'none';
            
            mostrarCamposAdicionales(); 
            
            document.getElementById('submitButton').innerText = 'Crear Usuario';
            document.getElementById('submitButton').className = 'save';
            document.getElementById('cancelEditButton').style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            mostrarCamposAdicionales(); 
            
            if (document.getElementById('accion').value === 'crear') {
                document.getElementById('estadoFormGroup').style.display = 'none';
                document.getElementById('forzarCambioPassFormGroup').style.display = 'none';
                document.getElementById('debe_cambiar_password_edit').checked = true; 
            }
        });
    </script>
</body>
</html>
<?php
if ($mysqli) { // Cierra la conexión solo si está abierta
    $mysqli->close(); 
}
?>