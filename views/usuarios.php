<?php
// usuarios.php - Gestión de usuarios del sistema
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: index.php");
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['accion'] === 'crear') {
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("INSERT INTO usuario (username, password, tipo, activo) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $_POST['username'], $password_hash, $_POST['tipo'], $_POST['activo']);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['accion'] === 'editar') {
        // Actualizar usuario (con o sin cambio de contraseña)
        if (!empty($_POST['password'])) {
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("UPDATE usuario SET username = ?, password = ?, tipo = ?, activo = ? WHERE id = ?");
            $stmt->bind_param("sssii", $_POST['username'], $password_hash, $_POST['tipo'], $_POST['activo'], $_POST['usuario_id']);
        } else {
            $stmt = $mysqli->prepare("UPDATE usuario SET username = ?, tipo = ?, activo = ? WHERE id = ?");
            $stmt->bind_param("ssii", $_POST['username'], $_POST['tipo'], $_POST['activo'], $_POST['usuario_id']);
        }
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['accion'] === 'eliminar') {
        $stmt = $mysqli->prepare("DELETE FROM usuario WHERE id = ?");
        $stmt->bind_param("i", $_POST['usuario_id']);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: usuarios.php");
    exit;
}

// Obtener lista de usuarios (sin el campo email que no existe)
$usuarios = $mysqli->query("SELECT id, username, tipo, activo FROM usuario ORDER BY username");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios - ISEF</title>
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .form-container { display: flex; gap: 20px; }
        .form-section { flex: 1; }
        .form-group { margin-bottom: 15px; }
        input, select, button { padding: 8px; width: 100%; }
        .active-yes { color: green; }
        .active-no { color: red; }
    </style>
</head>
<body>
    <h1>Gestión de Usuarios</h1>
    <a href="dashboard.php">&laquo; Volver al menú</a>

    <div class="form-container">
        <div class="form-section">
            <h2>Nuevo Usuario</h2>
            <form method="post">
                <input type="hidden" name="accion" value="crear">
                
                <div class="form-group">
                    <label>Nombre de usuario:</label>
                    <input type="text" name="username" required>
                </div>
                
                <div class="form-group">
                    <label>Contraseña:</label>
                    <input type="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label>Tipo de usuario:</label>
                    <select name="tipo" required>
                        <option value="administrador">Administrador</option>
                        <option value="profesor">Profesor</option>
                        <option value="preceptor">Preceptor</option>
                        <option value="alumno">Alumno</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Estado:</label>
                    <select name="activo" required>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
                
                <button type="submit">Crear Usuario</button>
            </form>
        </div>

        <div class="form-section">
            <h2>Editar Usuario</h2>
            <form method="post" id="form-editar">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="usuario_id" id="usuario_id">
                
                <div class="form-group">
                    <label>Nombre de usuario:</label>
                    <input type="text" name="username" id="edit-username" required>
                </div>
                
                <div class="form-group">
                    <label>Nueva contraseña (dejar vacío para no cambiar):</label>
                    <input type="password" name="password" id="edit-password">
                </div>
                
                <div class="form-group">
                    <label>Tipo de usuario:</label>
                    <select name="tipo" id="edit-tipo" required>
                        <option value="administrador">Administrador</option>
                        <option value="profesor">Profesor</option>
                        <option value="preceptor">Preceptor</option>
                        <option value="alumno">Alumno</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Estado:</label>
                    <select name="activo" id="edit-activo" required>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
                
                <button type="submit">Guardar Cambios</button>
            </form>
        </div>
    </div>

    <h2>Lista de Usuarios</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($usuario = $usuarios->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($usuario['id']) ?></td>
                    <td><?= htmlspecialchars($usuario['username']) ?></td>
                    <td><?= htmlspecialchars($usuario['tipo']) ?></td>
                    <td class="<?= $usuario['activo'] ? 'active-yes' : 'active-no' ?>">
                        <?= $usuario['activo'] ? 'Activo' : 'Inactivo' ?>
                    </td>
                    <td>
                        <button onclick="cargarDatosEdicion(<?= $usuario['id'] ?>, '<?= htmlspecialchars($usuario['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($usuario['tipo'], ENT_QUOTES) ?>', <?= $usuario['activo'] ?>)">
                            Editar
                        </button>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="usuario_id" value="<?= $usuario['id'] ?>">
                            <button onclick="return confirm('¿Eliminar este usuario?')">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <script>
        function cargarDatosEdicion(id, username, tipo, activo) {
            document.getElementById('usuario_id').value = id;
            document.getElementById('edit-username').value = username;
            document.getElementById('edit-tipo').value = tipo;
            document.getElementById('edit-activo').value = activo ? '1' : '0';
            document.getElementById('edit-password').value = '';
            
            // Desplazarse al formulario de edición
            document.getElementById('form-editar').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>