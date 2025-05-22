<?php
// preceptores.php - Gestión integrada de preceptores
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: index.php");
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

$mensaje = '';
$error = '';

// Función para generar nombre de usuario único basado en nombre y apellido
function generarUsername($nombre, $apellido, $mysqli) {
    // Normalizar: eliminar acentos, espacios y caracteres especiales
    setlocale(LC_ALL, 'en_US.UTF-8');
    $nombre = strtolower(trim($nombre));
    $apellido = strtolower(trim($apellido));
    
    // Eliminar caracteres especiales y tildes
    $nombre = preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $nombre));
    $apellido = preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $apellido));
    
    // Crear username base: primera letra del nombre + apellido
    $baseUsername = substr($nombre, 0, 1) . $apellido;
    $username = $baseUsername;
    
    // Verificar si ya existe
    $i = 1;
    while (true) {
        $stmt = $mysqli->prepare("SELECT id FROM usuario WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows === 0) {
            // Username disponible
            return $username;
        }
        
        // Incrementar contador y probar de nuevo
        $username = $baseUsername . $i;
        $i++;
    }
}

// Procesar formulario de creación de preceptor
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['accion'] === 'crear') {
        $mysqli->begin_transaction();
        try {
            // Generar nombre de usuario único
            $username = generarUsername($_POST['nombres'], $_POST['apellidos'], $mysqli);
            
            // 1. Crear usuario con contraseña por defecto (DNI)
            $password_hash = password_hash($_POST['dni'], PASSWORD_DEFAULT);
            $tipo = 'preceptor';
            $activo = 1;
            
            $stmt = $mysqli->prepare("INSERT INTO usuario (username, password, tipo, activo) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $username, $password_hash, $tipo, $activo);
            $stmt->execute();
            $usuario_id = $mysqli->insert_id;
            $stmt->close();
            
            // 2. Crear registro de persona
            $stmt = $mysqli->prepare("INSERT INTO persona (usuario_id, apellidos, nombres, dni, fecha_nacimiento, celular, domicilio, contacto_emergencia) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssss", $usuario_id, $_POST['apellidos'], $_POST['nombres'], $_POST['dni'], $_POST['fecha_nacimiento'], $_POST['celular'], $_POST['domicilio'], $_POST['contacto_emergencia']);
            $stmt->execute();
            $persona_id = $mysqli->insert_id;
            $stmt->close();
            
            // 3. Crear registro de preceptor
            $stmt = $mysqli->prepare("INSERT INTO preceptor (persona_id, titulo_profesional, fecha_ingreso, sector_asignado) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $persona_id, $_POST['titulo_profesional'], $_POST['fecha_ingreso'], $_POST['sector_asignado']);
            $stmt->execute();
            $stmt->close();
            
            $mysqli->commit();
            $mensaje = "Preceptor creado correctamente. Nombre de usuario: $username";
            
        } catch (Exception $e) {
            $mysqli->rollback();
            $error = "Error al crear el preceptor: " . $e->getMessage();
        }
    } elseif ($_POST['accion'] === 'editar') {
        $mysqli->begin_transaction();
        try {
            // 1. Actualizar datos de persona
            $stmt = $mysqli->prepare("UPDATE persona SET apellidos = ?, nombres = ?, dni = ?, fecha_nacimiento = ?, celular = ?, domicilio = ?, contacto_emergencia = ? WHERE id = ?");
            $stmt->bind_param("sssssssi", $_POST['apellidos'], $_POST['nombres'], $_POST['dni'], $_POST['fecha_nacimiento'], $_POST['celular'], $_POST['domicilio'], $_POST['contacto_emergencia'], $_POST['persona_id']);
            $stmt->execute();
            $stmt->close();
            
            // 2. Actualizar datos de preceptor
            $stmt = $mysqli->prepare("UPDATE preceptor SET titulo_profesional = ?, fecha_ingreso = ?, sector_asignado = ? WHERE persona_id = ?");
            $stmt->bind_param("sssi", $_POST['titulo_profesional'], $_POST['fecha_ingreso'], $_POST['sector_asignado'], $_POST['persona_id']);
            $stmt->execute();
            $stmt->close();
            
            $mysqli->commit();
            $mensaje = "Preceptor actualizado correctamente.";
            
        } catch (Exception $e) {
            $mysqli->rollback();
            $error = "Error al actualizar el preceptor: " . $e->getMessage();
        }
    } elseif ($_POST['accion'] === 'eliminar') {
        $mysqli->begin_transaction();
        try {
            // Obtener el usuario_id antes de eliminar
            $stmt = $mysqli->prepare("SELECT usuario_id FROM persona WHERE id = ?");
            $stmt->bind_param("i", $_POST['persona_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $usuario_id = $row['usuario_id'];
            $stmt->close();
            
            // La eliminación en cascada manejará las relaciones,
            // pero debemos eliminar primero el usuario manualmente
            $stmt = $mysqli->prepare("DELETE FROM usuario WHERE id = ?");
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();
            $stmt->close();
            
            $mysqli->commit();
            $mensaje = "Preceptor eliminado correctamente.";
            
        } catch (Exception $e) {
            $mysqli->rollback();
            $error = "Error al eliminar el preceptor: " . $e->getMessage();
        }
    }
    
    // Redireccionar para evitar reenvío del formulario
    header("Location: preceptores.php?mensaje=" . urlencode($mensaje) . "&error=" . urlencode($error));
    exit;
}

// Recuperar mensaje y error si existen
if (isset($_GET['mensaje']) && !empty($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
}
if (isset($_GET['error']) && !empty($_GET['error'])) {
    $error = $_GET['error'];
}

// Obtener lista de preceptores
$preceptores = $mysqli->query("
    SELECT 
        p.id AS persona_id,
        prec.id AS preceptor_id,
        p.apellidos,
        p.nombres,
        p.dni,
        p.fecha_nacimiento,
        p.celular,
        p.domicilio,
        p.contacto_emergencia,
        u.username,
        u.activo,
        prec.titulo_profesional,
        prec.fecha_ingreso,
        prec.sector_asignado
    FROM 
        preceptor prec
    JOIN 
        persona p ON prec.persona_id = p.id
    JOIN 
        usuario u ON p.usuario_id = u.id
    ORDER BY 
        p.apellidos, p.nombres
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Preceptores - ISEF</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .message { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        form { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="date"], textarea, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { padding: 10px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button.edit { background-color: #2196F3; }
        button.delete { background-color: #f44336; }
        .form-row { display: flex; gap: 20px; }
        .form-col { flex: 1; }
        .actions { display: flex; gap: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gestión de Preceptores</h1>
        <a href="dashboard.php">&laquo; Volver al menú</a>
        
        <?php if ($mensaje): ?>
            <div class="message success"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <h2>Nuevo Preceptor</h2>
        <form method="post">
            <input type="hidden" name="accion" value="crear">
            
            <div class="form-row">
                <div class="form-col">
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
                        <input type="text" id="dni" name="dni" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_nacimiento">Fecha de nacimiento:</label>
                        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="celular">Celular:</label>
                        <input type="text" id="celular" name="celular">
                    </div>
                    
                    <div class="form-group">
                        <label for="domicilio">Domicilio:</label>
                        <input type="text" id="domicilio" name="domicilio">
                    </div>
                    
                    <div class="form-group">
                        <label for="contacto_emergencia">Contacto de emergencia:</label>
                        <input type="text" id="contacto_emergencia" name="contacto_emergencia">
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="titulo_profesional">Título profesional:</label>
                        <input type="text" id="titulo_profesional" name="titulo_profesional" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_ingreso">Fecha de ingreso:</label>
                        <input type="date" id="fecha_ingreso" name="fecha_ingreso" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="sector_asignado">Sector asignado:</label>
                        <select id="sector_asignado" name="sector_asignado" required>
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
            
            <button type="submit">Crear Preceptor</button>
        </form>
        
        <h2>Lista de Preceptores</h2>
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>DNI</th>
                    <th>Título profesional</th>
                    <th>Sector asignado</th>
                    <th>Usuario</th>
                    <th>Estado</th>
                    <th>Contacto</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($prec = $preceptores->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($prec['apellidos']) ?>, <?= htmlspecialchars($prec['nombres']) ?></td>
                        <td><?= htmlspecialchars($prec['dni']) ?></td>
                        <td><?= htmlspecialchars($prec['titulo_profesional']) ?></td>
                        <td><?= htmlspecialchars($prec['sector_asignado']) ?></td>
                        <td><?= htmlspecialchars($prec['username']) ?></td>
                        <td><?= $prec['activo'] ? 'Activo' : 'Inactivo' ?></td>
                        <td><?= htmlspecialchars($prec['celular']) ?></td>
                        <td class="actions">
                            <button class="edit" onclick="cargarDatosEdicion(<?= htmlspecialchars(json_encode($prec)) ?>)">
                                Editar
                            </button>
                            <form method="post" style="display:inline;" onsubmit="return confirm('¿Está seguro de eliminar este preceptor?');">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="persona_id" value="<?= $prec['persona_id'] ?>">
                                <button type="submit" class="delete">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <!-- Modal de edición (aparece oculto) -->
        <div id="edicionForm" style="display:none;">
            <h2>Editar Preceptor</h2>
            <form method="post" id="form-editar">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="persona_id" id="edit-persona-id">
                
                <div class="form-row">
                    <div class="form-col">
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
                            <input type="text" id="edit-dni" name="dni" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit-fecha-nacimiento">Fecha de nacimiento:</label>
                            <input type="date" id="edit-fecha-nacimiento" name="fecha_nacimiento" required>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label for="edit-celular">Celular:</label>
                            <input type="text" id="edit-celular" name="celular">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit-domicilio">Domicilio:</label>
                            <input type="text" id="edit-domicilio" name="domicilio">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit-contacto-emergencia">Contacto de emergencia:</label>
                            <input type="text" id="edit-contacto-emergencia" name="contacto_emergencia">
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label for="edit-titulo-profesional">Título profesional:</label>
                            <input type="text" id="edit-titulo-profesional" name="titulo_profesional" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit-fecha-ingreso">Fecha de ingreso:</label>
                            <input type="date" id="edit-fecha-ingreso" name="fecha_ingreso" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit-sector-asignado">Sector asignado:</label>
                            <select id="edit-sector-asignado" name="sector_asignado" required>
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
                
                <button type="submit">Guardar Cambios</button>
                <button type="button" onclick="ocultarFormEdicion()">Cancelar</button>
            </form>
        </div>
    </div>
    
    <script>
        function cargarDatosEdicion(preceptor) {
            // Mostrar el formulario de edición
            document.getElementById('edicionForm').style.display = 'block';
            document.getElementById('edicionForm').scrollIntoView({ behavior: 'smooth' });
            
            // Cargar los datos en el formulario
            document.getElementById('edit-persona-id').value = preceptor.persona_id;
            document.getElementById('edit-apellidos').value = preceptor.apellidos;
            document.getElementById('edit-nombres').value = preceptor.nombres;
            document.getElementById('edit-dni').value = preceptor.dni;
            document.getElementById('edit-fecha-nacimiento').value = preceptor.fecha_nacimiento;
            document.getElementById('edit-celular').value = preceptor.celular || '';
            document.getElementById('edit-domicilio').value = preceptor.domicilio || '';
            document.getElementById('edit-contacto-emergencia').value = preceptor.contacto_emergencia || '';
            document.getElementById('edit-titulo-profesional').value = preceptor.titulo_profesional;
            document.getElementById('edit-fecha-ingreso').value = preceptor.fecha_ingreso;
            document.getElementById('edit-sector-asignado').value = preceptor.sector_asignado || '';
        }
        
        function ocultarFormEdicion() {
            document.getElementById('edicionForm').style.display = 'none';
        }
    </script>
</body>
</html>