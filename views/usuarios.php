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
        $stmt = $mysqli->prepare("SELECT id FROM usuario WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows === 0) {
            return $username;
        }
        
        $username = $baseUsername . $i;
        $i++;
    }
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['accion'] === 'crear') {
        $mysqli->begin_transaction();
        try {
            $username = generarUsername($_POST['nombres'], $_POST['apellidos'], $mysqli);
            $password_hash = password_hash($_POST['dni'], PASSWORD_DEFAULT);
            $tipo = $_POST['tipo'];
            $activo = 1;
            
           // 1. Crear usuario
            // Agregamos debe_cambiar_password al INSERT y un nuevo placeholder
            $stmt = $mysqli->prepare("INSERT INTO usuario (username, password, tipo, activo, debe_cambiar_password) VALUES (?, ?, ?, ?, ?)");
            $debe_cambiar = 1; // Establecemos que el nuevo usuario SÍ debe cambiar la contraseña
            // Agregamos "i" para el nuevo entero y la variable $debe_cambiar
            $stmt->bind_param("sssii", $username, $password_hash, $tipo, $activo, $debe_cambiar);
            $stmt->execute();
            $usuario_id = $mysqli->insert_id;
            $stmt->close();
            
            // 2. Crear persona
            $stmt = $mysqli->prepare("INSERT INTO persona (usuario_id, apellidos, nombres, dni, fecha_nacimiento, celular, domicilio, contacto_emergencia) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssss", $usuario_id, $_POST['apellidos'], $_POST['nombres'], $_POST['dni'], $_POST['fecha_nacimiento'], $_POST['celular'], $_POST['domicilio'], $_POST['contacto_emergencia']);
            $stmt->execute();
            $persona_id = $mysqli->insert_id;
            $stmt->close();
            
            // 3. Crear registro específico según el tipo
            switch ($tipo) {
                case 'alumno':
                    $stmt = $mysqli->prepare("INSERT INTO alumno (persona_id, legajo, fecha_ingreso, cohorte) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("issi", $persona_id, $_POST['legajo'], $_POST['fecha_ingreso'], $_POST['cohorte']);
                    break;
                    
                case 'profesor':
                    $stmt = $mysqli->prepare("INSERT INTO profesor (persona_id, titulo_profesional, fecha_ingreso, horas_consulta) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("isss", $persona_id, $_POST['titulo_profesional'], $_POST['fecha_ingreso'], $_POST['horas_consulta']);
                    break;
                    
                case 'preceptor':
                    $stmt = $mysqli->prepare("INSERT INTO preceptor (persona_id, titulo_profesional, fecha_ingreso, sector_asignado) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("isss", $persona_id, $_POST['titulo_profesional'], $_POST['fecha_ingreso'], $_POST['sector_asignado']);
                    break;
            }
            
            if (isset($stmt)) {
                $stmt->execute();
                $stmt->close();
            }
            
            $mysqli->commit();
            $mensaje = "Usuario creado correctamente. Nombre de usuario: $username";
            
        } catch (Exception $e) {
            $mysqli->rollback();
            $error = "Error al crear el usuario: " . $e->getMessage();
        }
    } elseif ($_POST['accion'] === 'eliminar') {
        try {
            $stmt = $mysqli->prepare("DELETE FROM usuario WHERE id = ?");
            $stmt->bind_param("i", $_POST['usuario_id']);
            $stmt->execute();
            $stmt->close();
            $mensaje = "Usuario eliminado correctamente.";
        } catch (Exception $e) {
            $error = "Error al eliminar el usuario: " . $e->getMessage();
        }
    }
    
    header("Location: usuarios.php?mensaje=" . urlencode($mensaje) . "&error=" . urlencode($error));
    exit;
}

// Recuperar mensajes
if (isset($_GET['mensaje'])) $mensaje = $_GET['mensaje'];
if (isset($_GET['error'])) $error = $_GET['error'];

// Obtener lista de usuarios con información detallada
$usuarios = $mysqli->query("
    SELECT 
        u.id AS usuario_id,
        u.username,
        u.tipo,
        u.activo,
        p.id AS persona_id,
        p.apellidos,
        p.nombres,
        p.dni,
        p.fecha_nacimiento,
        p.celular,
        p.domicilio,
        p.contacto_emergencia,
        CASE 
            WHEN u.tipo = 'alumno' THEN a.legajo
            ELSE NULL 
        END AS legajo,
        CASE 
            WHEN u.tipo = 'alumno' THEN a.cohorte
            ELSE NULL 
        END AS cohorte,
        CASE 
            WHEN u.tipo = 'profesor' THEN pr.titulo_profesional
            WHEN u.tipo = 'preceptor' THEN pc.titulo_profesional
            ELSE NULL 
        END AS titulo_profesional,
        CASE 
            WHEN u.tipo = 'profesor' THEN pr.horas_consulta
            ELSE NULL 
        END AS horas_consulta,
        CASE 
            WHEN u.tipo = 'preceptor' THEN pc.sector_asignado
            ELSE NULL 
        END AS sector_asignado
    FROM usuario u
    LEFT JOIN persona p ON u.id = p.usuario_id
    LEFT JOIN alumno a ON p.id = a.persona_id
    LEFT JOIN profesor pr ON p.id = pr.persona_id
    LEFT JOIN preceptor pc ON p.id = pc.persona_id
    ORDER BY u.tipo, p.apellidos, p.nombres
");
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
        .form-container { margin: 20px 0; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .form-row { display: flex; gap: 20px; }
        .form-col { flex: 1; }
        button { padding: 10px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button.edit { background-color: #2196F3; }
        button.delete { background-color: #f44336; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .tipo-fields { display: none; }
        .active-yes { color: green; }
        .active-no { color: red; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gestión de Usuarios</h1>
        <a href="dashboard.php">&laquo; Volver al menú</a>

        <?php if ($mensaje): ?>
            <div class="message success"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-container">
            <h2>Nuevo Usuario</h2>
            <form method="post" id="userForm">
                <input type="hidden" name="accion" value="crear">
                
                <div class="form-row">
                    <div class="form-col">
                        <h3>Datos Básicos</h3>
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
                            <label>Apellidos:</label>
                            <input type="text" name="apellidos" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Nombres:</label>
                            <input type="text" name="nombres" required>
                        </div>
                        
                        <div class="form-group">
                            <label>DNI:</label>
                            <input type="text" name="dni" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Fecha de nacimiento:</label>
                            <input type="date" name="fecha_nacimiento" required>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <h3>Datos de Contacto</h3>
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
                    </div>
                    
                    <div class="form-col">
                        <!-- Campos específicos para Alumno -->
                        <div id="campos-alumno" class="tipo-fields">
                            <h3>Datos del Alumno</h3>
                            <div class="form-group">
                                <label>Legajo:</label>
                                <input type="text" name="legajo">
                            </div>
                            
                            <div class="form-group">
                                <label>Cohorte:</label>
                                <input type="number" name="cohorte">
                            </div>
                            
                            <div class="form-group">
                                <label>Fecha de ingreso:</label>
                                <input type="date" name="fecha_ingreso">
                            </div>
                        </div>

                        <!-- Campos específicos para Profesor -->
                        <div id="campos-profesor" class="tipo-fields">
                            <h3>Datos del Profesor</h3>
                            <div class="form-group">
                                <label>Título profesional:</label>
                                <input type="text" name="titulo_profesional">
                            </div>
                            
                            <div class="form-group">
                                <label>Fecha de ingreso:</label>
                                <input type="date" name="fecha_ingreso">
                            </div>
                            
                            <div class="form-group">
                                <label>Horas de consulta:</label>
                                <textarea name="horas_consulta" rows="3"></textarea>
                            </div>
                        </div>

                        <!-- Campos específicos para Preceptor -->
                        <div id="campos-preceptor" class="tipo-fields">
                            <h3>Datos del Preceptor</h3>
                            <div class="form-group">
                                <label>Título profesional:</label>
                                <input type="text" name="titulo_profesional">
                            </div>
                            
                            <div class="form-group">
                                <label>Fecha de ingreso:</label>
                                <input type="date" name="fecha_ingreso">
                            </div>
                            
                            <div class="form-group">
                                <label>Sector asignado:</label>
                                <select name="sector_asignado">
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
                
                <button type="submit">Crear Usuario</button>
            </form>
        </div>

        <h2>Lista de Usuarios</h2>
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Nombre</th>
                    <th>DNI</th>
                    <th>Usuario</th>
                    <th>Estado</th>
                    <th>Información Adicional</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($usuario = $usuarios->fetch_assoc()): ?>
                    <tr>
                        <td><?= ucfirst(htmlspecialchars($usuario['tipo'])) ?></td>
                        <td><?= htmlspecialchars($usuario['apellidos']) ?>, <?= htmlspecialchars($usuario['nombres']) ?></td>
                        <td><?= htmlspecialchars($usuario['dni']) ?></td>
                        <td><?= htmlspecialchars($usuario['username']) ?></td>
                        <td class="<?= $usuario['activo'] ? 'active-yes' : 'active-no' ?>">
                            <?= $usuario['activo'] ? 'Activo' : 'Inactivo' ?>
                        </td>
                        <td>
                            <?php
                            switch ($usuario['tipo']) {
                                case 'alumno':
                                    echo "Legajo: " . htmlspecialchars($usuario['legajo']) . 
                                         "<br>Cohorte: " . htmlspecialchars($usuario['cohorte']);
                                    break;
                                case 'profesor':
                                    echo "Título: " . htmlspecialchars($usuario['titulo_profesional']) . 
                                         "<br>Consulta: " . htmlspecialchars($usuario['horas_consulta']);
                                    break;
                                case 'preceptor':
                                    echo "Título: " . htmlspecialchars($usuario['titulo_profesional']) . 
                                         "<br>Sector: " . htmlspecialchars($usuario['sector_asignado']);
                                    break;
                            }
                            ?>
                        </td>
                        <td>
                            <form method="post" style="display:inline;" onsubmit="return confirm('¿Está seguro de eliminar este usuario?');">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="usuario_id" value="<?= $usuario['usuario_id'] ?>">
                                <button type="submit" class="delete">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script>
        function mostrarCamposAdicionales() {
            // Ocultar todos los campos específicos
            document.querySelectorAll('.tipo-fields').forEach(div => div.style.display = 'none');
            
            // Mostrar los campos según el tipo seleccionado
            const tipo = document.getElementById('tipo').value;
            if (tipo) {
                const camposEspecificos = document.getElementById('campos-' + tipo);
                if (camposEspecificos) {
                    camposEspecificos.style.display = 'block';
                }
            }
        }

        // Ejecutar al cargar la página
        mostrarCamposAdicionales();
    </script>
</body>
</html>