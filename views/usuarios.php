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
    $foto = !empty($usuario['foto']) 
        ? '/ISEF-programadores-2/uploads/' . htmlspecialchars($usuario['foto']) 
        : '/ISEF-programadores-2/uploads/default.png';
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
    $filaHTML .= '<td><img src="' . $foto . '" alt="Foto" style="width:50px;height:50px;border-radius:50%;object-fit:cover;"></td>';
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
                        data-foto="' . htmlspecialchars($usuario['foto'] ?? '') . '"
                        onclick="populateEditForm(this)"><i data-lucide="edit-2"></i> Editar</button>
                    <form method="post" style="display:inline;" onsubmit="return confirm(\'¿Está seguro de eliminar este usuario? Esta acción no se puede deshacer y eliminará todos los datos asociados.\');">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="usuario_id" value="' . $usuario['usuario_id'] . '">
                        <button type="submit" class="delete"><i data-lucide="trash-2"></i> Eliminar</button>
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
            $output .= generarFilaUsuarioHTML($usuario, $mysqli);
        }
    } else {
        $output = '<tr><td colspan="8">No se encontraron usuarios con ese criterio.</td></tr>';
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
        if (!$stmt_check) {
            error_log("Error al preparar la consulta para verificar username: " . $mysqli->error);
            return $baseUsername . time();
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
                $activo = 1;
                $debe_cambiar = 1;

                // --- FOTO ---
                $foto_nombre = '';
                if (isset($_FILES['foto_usuario']) && $_FILES['foto_usuario']['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['foto_usuario']['name'], PATHINFO_EXTENSION);
                    $foto_nombre = uniqid('foto_') . '.' . $ext;
                    move_uploaded_file($_FILES['foto_usuario']['tmp_name'], __DIR__ . '/../uploads/' . $foto_nombre);
                }

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

                $stmt_persona = $mysqli->prepare("INSERT INTO persona (usuario_id, apellidos, nombres, dni, fecha_nacimiento, celular, domicilio, contacto_emergencia, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt_persona) {
                    throw new Exception("Error al preparar la consulta para 'persona': " . $mysqli->error);
                }
                $stmt_persona->bind_param("issssssss", $usuario_id, $_POST['apellidos'], $_POST['nombres'], $_POST['dni'], $_POST['fecha_nacimiento'], $_POST['celular'], $_POST['domicilio'], $_POST['contacto_emergencia'], $foto_nombre);
                if (!$stmt_persona->execute()) {
                    throw new Exception("Error al ejecutar la consulta para 'persona': " . $stmt_persona->error);
                }
                $persona_id = $mysqli->insert_id; 
                $stmt_persona->close(); 

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
                }
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
        } elseif ($_POST['accion'] === 'editar') {
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

                // --- FOTO ---
                $foto_nombre = '';
                $sql_foto = '';
                if (isset($_FILES['foto_usuario']) && $_FILES['foto_usuario']['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['foto_usuario']['name'], PATHINFO_EXTENSION);
                    $foto_nombre = uniqid('foto_') . '.' . $ext;
                    move_uploaded_file($_FILES['foto_usuario']['tmp_name'], __DIR__ . '/../uploads/' . $foto_nombre);
                    $sql_foto = ", foto = ?";
                }

                if ($sql_foto) {
                    $stmt_persona_update = $mysqli->prepare("UPDATE persona SET apellidos = ?, nombres = ?, dni = ?, fecha_nacimiento = ?, celular = ?, domicilio = ?, contacto_emergencia = ? $sql_foto WHERE usuario_id = ?");
                    if(!$stmt_persona_update) throw new Exception("Error al preparar update para 'persona': ".$mysqli->error);
                    $stmt_persona_update->bind_param("ssssssssi", $_POST['apellidos'], $_POST['nombres'], $_POST['dni'], $_POST['fecha_nacimiento'], $_POST['celular'], $_POST['domicilio'], $_POST['contacto_emergencia'], $foto_nombre, $usuario_id_edit);
                } else {
                    $stmt_persona_update = $mysqli->prepare("UPDATE persona SET apellidos = ?, nombres = ?, dni = ?, fecha_nacimiento = ?, celular = ?, domicilio = ?, contacto_emergencia = ? WHERE usuario_id = ?");
                    if(!$stmt_persona_update) throw new Exception("Error al preparar update para 'persona': ".$mysqli->error);
                    $stmt_persona_update->bind_param("sssssssi", $_POST['apellidos'], $_POST['nombres'], $_POST['dni'], $_POST['fecha_nacimiento'], $_POST['celular'], $_POST['domicilio'], $_POST['contacto_emergencia'], $usuario_id_edit);
                }
                if(!$stmt_persona_update->execute()) throw new Exception("Error al ejecutar update para 'persona': ".$stmt_persona_update->error);
                $stmt_persona_update->close();

                $persona_id_result = $mysqli->query("SELECT id FROM persona WHERE usuario_id = $usuario_id_edit");
                if ($persona_id_result->num_rows > 0) {
                    $persona_id = $persona_id_result->fetch_assoc()['id'];
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
                    $mysqli->rollback();
                    $error = "No se pudo eliminar el usuario (puede que ya haya sido eliminado o no exista).";
                }
                $stmt_delete->close(); 
            } catch (mysqli_sql_exception $e) {
                $mysqli->rollback();
                if ($e->getCode() == 1451) {
                     $error = "Error al eliminar el usuario: No se puede eliminar porque tiene datos asociados en otras tablas (e.g., inscripciones, evaluaciones). Por favor, elimine primero esos registros.";
                } else {
                    $error = "Error de base de datos al eliminar el usuario: " . $e->getMessage();
                }
            } catch (Exception $e) {
                $mysqli->rollback();
                $error = "Error general al eliminar el usuario: " . $e->getMessage(); 
            }
        }
    }
    if (!(isset($_GET['action']) && $_GET['action'] === 'search_users')) {
        if (!headers_sent()) {
            header("Location: usuarios.php?mensaje=" . urlencode($mensaje) . "&error=" . urlencode($error));
            exit;
        }
    }
} 

if (isset($_GET['mensaje'])) $mensaje = htmlspecialchars($_GET['mensaje']);
if (isset($_GET['error'])) $error = htmlspecialchars($_GET['error']);

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
    <title>Gestión de Usuarios - ISEF</title>
   <style>
    /* Paleta y variables */
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

.sidebar {
    width: 280px;
    background: var(--orange-primary);
    backdrop-filter: blur(5px);
    border-right: 1px solid var(--orange-light);
    display: flex;
    flex-direction: column;
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    z-index: 10;
    color: var(--white);
    box-shadow: 2px 0 8px rgba(0,0,0,0.04);
    transition: all 0.3s;
}

/* Layout principal */
body {
    font-family: Arial, sans-serif;
    margin: 0;
    background: var(--gray-bg);
    color: var(--gray-dark);
}

.app-container {
    display: flex;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    width: 280px;
    background: var(--orange-primary);
    color: var(--white);
    display: flex;
    flex-direction: column;
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    z-index: 10;
    box-shadow: 2px 0 8px rgba(0,0,0,0.04);
}

.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
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
    border-radius: 8px;
}

.brand-text h1 {
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
    color: var(--white);
}

.brand-text p {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.8);
    margin: 0;
}

.sidebar-nav {
    flex: 1;
    padding: 1rem 0.5rem 1rem 1rem;
}

.nav-section {
    margin-bottom: 2rem;
}

.nav-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: rgba(255,255,255,0.8);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.5rem;
    padding: 0 0.75rem;
}

.nav-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-item {
    margin-bottom: 0.25rem;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    color: rgba(255,255,255,0.9);
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.3s;
    font-size: 0.95rem;
}

.nav-link:hover, .nav-link.active {
    background: rgba(255,255,255,0.15);
    color: var(--white);
    font-weight: 500;
}

.nav-icon {
    width: 16px;
    height: 16px;
}

.sidebar-footer {
    padding: 1rem;
    border-top: 1px solid rgba(255,255,255,0.1);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: rgba(255,255,255,0.1);
    border-radius: 6px;
    margin-bottom: 0.5rem;
    transition: all 0.3s;
}

.user-info:hover {
    background: rgba(255,255,255,0.2);
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
    color: rgba(255,255,255,0.8);
    margin: 0;
}

.logout-btn {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    color: var(--white);
    border-radius: 6px;
    font-size: 0.875rem;
    border: none;
    background: rgba(255,255,255,0.1);
    width: 100%;
    cursor: pointer;
    transition: all 0.3s;
}

.logout-btn:hover {
    background: rgba(255,255,255,0.2);
}

/* Main content */
.main-content {
    flex: 1;
    margin-left: 280px;
    padding: 40px 30px;
    background: var(--gray-bg);
    min-height: 100vh;
    overflow-x: auto;
}

.header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
}

.sidebar-toggle {
    background: none;
    border: none;
    color: var(--orange-primary);
    font-size: 1.5rem;
    cursor: pointer;
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

.header-actions .icon-btn {
    background: none;
    border: none;
    color: var(--orange-primary);
    font-size: 1.2rem;
    cursor: pointer;
}

.content {
    max-width: 1200px;
    margin: 0 auto;
}

/* Cards */
.card {
    background: var(--white);
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}

.card-header {
    background: #f5f5f5;
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.card-title {
    font-size: 1.25rem;
    margin: 0;
    color: #333;
}

.card-description {
    font-size: 0.95rem;
    color: #666;
    margin: 0;
}

.card-content {
    padding: 15px;
}

/* Tabla */
.table-container {
    max-height: 400px;
    overflow-y: auto;
}

.styled-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--white);
}

.styled-table th, .styled-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid var(--gray-border);
}

.styled-table th {
    background-color: #ffe0b2;
    color: #4e342e;
}

.styled-table tr:hover {
    background-color: #fff8e1;
}

/* Formularios */
.form-group {
    margin-bottom: 15px;
}

label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: var(--orange-primary);
}

input[type="text"], input[type="date"], input[type="number"],
input[type="email"], input[type="search"], select, textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    box-sizing: border-box;
    background-color: #fff;
}

.form-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.form-col {
    flex: 1;
    min-width: 300px;
}

.tipo-fields {
    display: none;
    padding: 10px;
    background-color: #fff3e0;
    border-radius: 4px;
    margin-top: 10px;
}

/* Botones */
button {
    padding: 10px 15px;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    margin-right: 5px;
    font-weight: bold;
    transition: background 0.2s;
}

button.save {
    background-color: var(--orange-primary);
}

button.edit {
    background-color: #ffa726;
}

button.delete {
    background-color: #ef5350;
}

button.cancel {
    background-color: #9e9e9e;
}

button:hover {
    opacity: 0.9;
}

.actions-cell {
    display: flex;
    gap: 6px;
    align-items: center;
    justify-content: center;
}
.actions-cell button {
    margin-bottom: 0;
    width: auto;
    min-width: 32px;
    padding: 6px 10px;
    font-size: 0.95em;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.actions-cell form {
    display: inline;
}

/* Estado usuario */
.active-yes {
    color: #388e3c;
    font-weight: bold;
}

.active-no {
    color: #d32f2f;
    font-weight: bold;
}

/* Foto */
.foto-preview {
    margin-top: 10px;
    border: 1px solid #ccc;
    padding: 5px;
    border-radius: 4px;
    max-width: 150px;
}

/* Mensajes tipo toast */
.message-toast {
    padding: 10px 15px;
    border-radius: 6px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.message-toast.success {
    background-color: var(--success-bg);
    color: var(--success-text);
}

.message-toast.error {
    background-color: var(--error-bg);
    color: var(--error-text);
}

/* Responsive */
@media (max-width: 900px) {
    .main-content {
        margin-left: 0;
        padding: 20px 5px;
    }
    .sidebar {
        position: fixed;
        left: -280px;
        transition: left 0.3s;
    }
    .sidebar.open {
        left: 0;
    }
}

@media (max-width: 600px) {
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    .form-col {
        min-width: 100%;
    }
}

button.save:hover, button.edit:hover, button.delete:hover, button.cancel:hover {
    filter: brightness(1.1);
    opacity: 1;
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
                <form method="post" action="logout.php">
                <button type="submit" class="logout-btn">
                    <i data-lucide="log-out" class="nav-icon"></i>
                    <span>Cerrar Sesión</span>
                </button>
            </form>
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
                        <h1 class="page-title">Gestión de Usuarios</h1>
                        <p class="page-subtitle">Administra los usuarios del sistema.</p>
                    </div>
                    <!-- Puedes agregar un botón de "Nuevo Usuario" aquí si lo deseas -->
                </div>
             
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
            <form method="post" id="userForm" enctype="multipart/form-data">
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
                        <div class="form-group">
                            <label>Foto de perfil:</label>
                            <input type="file" name="foto_usuario" id="foto_usuario" accept="image/*">
                            <div id="previewFoto" class="foto-preview"></div>
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
                <div style="margin-top: 10px;">
                    <button type="submit" id="submitButton" class="save">
                        <i data-lucide="save"></i> <span id="submitButtonText">Crear Usuario</span>
                    </button>
                    <button type="button" id="cancelEditButton" class="cancel" style="display:none; margin-left: 10px;" onclick="resetForm()">
                        <i data-lucide="x"></i> Cancelar Edición
                    </button>
                </div>
            </form>
        </div>
        <div class="card">
    <div class="card-header">
        <h2 class="card-title">Lista de Usuarios</h2>
        <p class="card-description">Visualiza y gestiona los usuarios existentes.</p>
    </div>
    <div class="card-content">
        <input type="search" id="searchUserInput" placeholder="Buscar por nombre, apellido, DNI, usuario, legajo..." style="width: 100%; margin-bottom: 1rem;">
        <div class="table-container">
            <table class="styled-table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Nombre</th>
                        <th>DNI</th>
                        <th>Usuario</th>
                        <th>Estado</th>
                        <th>Foto</th>
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
                        echo '<tr><td colspan="8">No hay usuarios registrados.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
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
            // Previsualizar foto actual
            let foto = button.dataset.foto;
            let src = foto ? 'uploads/' + foto : 'uploads/default.png';
            document.getElementById('previewFoto').innerHTML = `<img src="${src}" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">`;
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
            document.getElementById('previewFoto').innerHTML = '';
        }

        document.getElementById('foto_usuario').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    document.getElementById('previewFoto').innerHTML = `<img src="${ev.target.result}" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">`;
                }
                reader.readAsDataURL(file);
            } else {
                document.getElementById('previewFoto').innerHTML = '';
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            mostrarCamposAdicionales(); 
            if (document.getElementById('accion').value === 'crear') {
                document.getElementById('estadoFormGroup').style.display = 'none';
                document.getElementById('forzarCambioPassFormGroup').style.display = 'none';
                document.getElementById('debe_cambiar_password_edit').checked = true; 
            }
        });
    </script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
<?php
if ($mysqli) { $mysqli->close(); }
?>