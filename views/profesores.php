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
        $lista_profesores[] = $fila;
    }
}
?>
<?php
// [Todo el código PHP permanece exactamente igual hasta la parte del HTML]
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Profesores - Sistema ISEF</title>
    <link rel="icon" href="../sources/logoo.ico" type="image/x-icon">
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
                    <span>Profesores</span>
                </nav>
                <!-- <div class="header-actions">
                    <button class="icon-btn" title="Notificaciones">
                        <i data-lucide="bell"></i>
                    </button>
                </div> -->
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

                <div id="edicionFormContainerProfesor" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; display:flex; align-items: center; justify-content: center; padding: 1rem;">
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
                    <div class="modal-content card" style="max-width: 600px;">
                        <div class="card-header">
                            <h2 class="card-title">Datos del Profesor</h2>
                            <p class="card-description">Información detallada del docente seleccionado.</p>
                        </div>
                        <div class="card-content" style="padding-top: 1rem;">
                            <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                                <div class="form-group" style="grid-column: 1 / -1; text-align: center;">
                                    <label style="display: block; margin-bottom: 0.5rem;">Foto:</label>
                                    <div id="info-profesor-foto" style="display: inline-block; font-weight: 500 !important;">
                                        <!-- Aquí se mostrará la foto -->
                                    </div>
                                </div>
                                <style>
                                    #info-profesor-foto img {
                                        max-width: 300px !important;
                                        max-height: 300px !important;
                                        border-radius: 12px;
                                        border: 2px solid #e2e8f0;
                                        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.07);
                                    }
                                </style>
                                <div class="form-group" style="grid-column: 1 / -1; text-align: center;">
                                    <label style="font-weight: 500; font-size: 1rem; margin-bottom: 0.25rem; display: block;">ID:</label>
                                    <div id="info-profesor-id" style="font-weight: 500; display: inline-block;"></div>
                                </div>
                                <div class="form-group">
                                    <label>Apellidos:</label>
                                    <div id="info-profesor-apellidos" style="font-weight: 500;"></div>
                                </div>
                                <div class="form-group">
                                    <label>Nombres:</label>
                                    <div id="info-profesor-nombres" style="font-weight: 500;"></div>
                                </div>
                                <div class="form-group">
                                    <label>DNI:</label>
                                    <div id="info-profesor-dni"></div>
                                </div>
                                <div class="form-group">
                                    <label>Fecha de nacimiento:</label>
                                    <div id="info-profesor-fecha-nacimiento"></div>
                                </div>
                                <div class="form-group">
                                    <label>Celular:</label>
                                    <div id="info-profesor-celular"></div>
                                </div>
                                <div class="form-group">
                                    <label>Domicilio:</label>
                                    <div id="info-profesor-domicilio"></div>
                                </div>
                                <div class="form-group">
                                    <label>Contacto de emergencia:</label>
                                    <div id="info-profesor-contacto-emergencia"></div>
                                </div>
                                <div class="form-group">
                                    <label>Título profesional:</label>
                                    <div id="info-profesor-titulo-profesional"></div>
                                </div>
                                <div class="form-group">
                                    <label>Fecha de ingreso:</label>
                                    <div id="info-profesor-fecha-ingreso"></div>
                                </div>
                                <div class="form-group">
                                    <label>Horas de consulta:</label>
                                    <div id="info-profesor-horas-consulta"></div>
                                </div>
                                <div class="form-group">
                                    <label>Usuario:</label>
                                    <div id="info-profesor-username"></div>
                                </div>
                                <div class="form-group">
                                    <label>Estado:</label>
                                    <div id="info-profesor-estado"></div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="button" class="btn btn-secondary" onclick="ocultarInfoProfesor()">Cerrar</button>
                        </div>
                    </div>
                </div>


            </div>
        </main>
    </div>

    <script>
        function verInformacionProfesorDesdeBoton(btn) {
            try {
                const profesor = JSON.parse(btn.getAttribute('data-profesor'));
                verInformacionProfesor(profesor);
            } catch (e) {
                alert("Error al leer los datos del profesor.");
            }
        }

        function verInformacionProfesor(profesor) {
            const infoProfesorModal = document.getElementById('infoProfesorModal');
            const fotoDiv = document.getElementById('info-profesor-foto');
            // Mostrar la foto del profesor correctamente desde uploads/fotos_usuarios
            if (profesor.foto_url && profesor.foto_url.trim() !== "") {
                let ruta = profesor.foto_url;
                // Si la ruta no empieza con http ni con /, agrégale /
                if (!/^https?:\/\//.test(ruta) && ruta[0] !== '/') {
                    ruta = '../' + ruta;
                }
                document.getElementById('info-profesor-foto').innerHTML =
                    `<img src="${ruta}" alt="Foto del profesor" style="max-width:120px;max-height:120px;border-radius:8px;border:1px solid #e2e8f0;">`;
            } else {
                document.getElementById('info-profesor-foto').innerHTML =
                    '<span style="color:#64748b;">Sin foto</span>';
            }

            document.getElementById('info-profesor-apellidos').textContent = profesor.apellidos || '';
            document.getElementById('info-profesor-id').textContent = profesor.persona_id || '';
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

        function ocultarInfoProfesor() {
            if (infoProfesorModal) infoProfesorModal.style.display = 'none';
        }
        if (infoProfesorModal) {
            infoProfesorModal.addEventListener('click', function(event) {
                if (event.target === infoProfesorModal) ocultarInfoProfesor();
            });
        }
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