<?php
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
    <title>Gestión de Alumnos - ISEF</title>
    <link rel="icon" href="../sources/logo_recortado.ico" type="image/x-icon">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="../style/style.css">
</head>

<body>
    <div class="app-container">

        <?php include $_SERVER['DOCUMENT_ROOT'] . '/ISEF/views/includes/nav.php'; ?>

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

                <div class="card" id="creacionFormCard" style="display:none;">
                    <div class="card-header">
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
                                    <input type="number" id="cohorte" name="cohorte" required min="1900" max="<?= date('Y') + 1 ?>">
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
                                                    <button class="btn btn-outline btn-sm" onclick='mostrarVerAlumno(<?= htmlspecialchars(json_encode($alu), ENT_QUOTES, 'UTF-8') ?>)' title="Ver Alumno">
                                                        <i data-lucide="eye"></i>
                                                    </button>
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
                    <div class="modal-content card">
                        <div class="card-header">
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
                                        <input type="number" id="edit-cohorte" name="cohorte" required min="1900" max="<?= date('Y') + 1 ?>">
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
                <div id="verAlumnoContainer" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
                    <div class="modal-content card">
                        <div class="card-header">
                            <h2 class="card-title">Datos del Alumno</h2>
                            <p class="card-description">Visualización de la información del estudiante.</p>
                        </div>
                        <div class="card-content">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Apellidos:</label>
                                    <input type="text" id="ver-apellidos" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Nombres:</label>
                                    <input type="text" id="ver-nombres" readonly>
                                </div>
                                <div class="form-group">
                                    <label>DNI:</label>
                                    <input type="text" id="ver-dni" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Fecha de nacimiento:</label>
                                    <input type="text" id="ver-fecha-nacimiento" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Celular:</label>
                                    <input type="text" id="ver-celular" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Domicilio:</label>
                                    <input type="text" id="ver-domicilio" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Contacto de emergencia:</label>
                                    <input type="text" id="ver-contacto-emergencia" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Legajo:</label>
                                    <input type="text" id="ver-legajo" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Fecha de ingreso:</label>
                                    <input type="text" id="ver-fecha-ingreso" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Cohorte:</label>
                                    <input type="text" id="ver-cohorte" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Usuario:</label>
                                    <input type="text" id="ver-username" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Estado:</label>
                                    <input type="text" id="ver-estado" readonly>
                                </div>
                                <div class="form-group" style="grid-column: 1 / -1; text-align: center;">
                                    <label>Foto:</label>
                                    <div id="ver-foto-alumno" style="margin-top: 0.5rem;">
                                        <span style="color:#64748b;">Sin foto</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="button" class="btn btn-secondary" onclick="ocultarVerAlumno()">Cerrar</button>
                        </div>
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
                            if (noAlumnosRow) {
                                noAlumnosRow.style.display = 'none'; // Hide "No hay alumnos" (if it was somehow visible)
                            }
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

                    const verAlumnoContainer = document.getElementById('verAlumnoContainer');

                    function mostrarVerAlumno(datosAlumno) {
                        document.getElementById('ver-apellidos').value = datosAlumno.apellidos;
                        document.getElementById('ver-nombres').value = datosAlumno.nombres;
                        document.getElementById('ver-dni').value = datosAlumno.dni;
                        document.getElementById('ver-fecha-nacimiento').value = datosAlumno.fecha_nacimiento;
                        document.getElementById('ver-celular').value = datosAlumno.celular || '';
                        document.getElementById('ver-domicilio').value = datosAlumno.domicilio || '';
                        document.getElementById('ver-contacto-emergencia').value = datosAlumno.contacto_emergencia || '';
                        document.getElementById('ver-legajo').value = datosAlumno.legajo;
                        document.getElementById('ver-fecha-ingreso').value = datosAlumno.fecha_ingreso;
                        document.getElementById('ver-cohorte').value = datosAlumno.cohorte;
                        document.getElementById('ver-username').value = datosAlumno.username;
                        document.getElementById('ver-estado').value = datosAlumno.activo == '1' ? 'Activo' : 'Inactivo';

                        const fotoUrl = datosAlumno.foto_url ? '../' + datosAlumno.foto_url : '';
                        const verFotoAlumnoDiv = document.getElementById('ver-foto-alumno');
                        verFotoAlumnoDiv.innerHTML = ''; // Limpiar contenido anterior
                        if (fotoUrl) {
                            verFotoAlumnoDiv.innerHTML = '<img src="' + fotoUrl + '" alt="Foto del alumno" style="max-width:180px;max-height:180px;border-radius:8px;border:1px solid #e2e8f0;">';
                        } else {
                            verFotoAlumnoDiv.innerHTML = '<span style="color:#64748b;">Sin foto</span>';
                        }

                        if (verAlumnoContainer) {
                            verAlumnoContainer.style.display = 'flex'; // Cambiar a flex para mostrarlo y centrarlo
                        }
                    }

                    function ocultarVerAlumno() {
                        if (verAlumnoContainer) {
                            verAlumnoContainer.style.display = 'none';
                        }
                    }

                    // Cerrar modal de ver alumno si se hace clic fuera del contenido del modal (en el overlay)
                    if (verAlumnoContainer) {
                        verAlumnoContainer.addEventListener('click', function(event) {
                            if (event.target === verAlumnoContainer) { // Si el clic fue directamente en el overlay
                                ocultarVerAlumno();
                            }
                        });
                    }
                </script>
</body>

</html>