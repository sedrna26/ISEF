<?php
// personas.php - Gestión de personas
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: index.php");
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

// Crear o actualizar persona
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['accion'] === 'crear') {
        $stmt = $mysqli->prepare("INSERT INTO persona (usuario_id, apellidos, nombres, dni, fecha_nacimiento, celular, domicilio, contacto_emergencia) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", $_POST['usuario_id'], $_POST['apellidos'], $_POST['nombres'], $_POST['dni'], $_POST['fecha_nacimiento'], $_POST['celular'], $_POST['domicilio'], $_POST['contacto_emergencia']);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['accion'] === 'borrar') {
        $stmt = $mysqli->prepare("DELETE FROM persona WHERE id = ?");
        $stmt->bind_param("i", $_POST['persona_id']);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: personas.php");
    exit;
}

$personas = $mysqli->query("SELECT * FROM persona ORDER BY apellidos, nombres");
$usuarios = $mysqli->query("SELECT id, username FROM usuario ORDER BY username");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Personas - ISEF</title>
</head>
<body>
    <h1>Gestión de Personas</h1>
    <a href="dashboard.php">&laquo; Volver al menú</a>

    <h2>Nueva Persona</h2>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <label>Usuario:
            <select name="usuario_id" required>
                <option value="">-- Seleccione --</option>
                <?php while ($u = $usuarios->fetch_assoc()): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
                <?php endwhile; ?>
            </select>
        </label><br>
        <label>Apellidos: <input type="text" name="apellidos" required></label><br>
        <label>Nombres: <input type="text" name="nombres" required></label><br>
        <label>DNI: <input type="text" name="dni" required></label><br>
        <label>Fecha de nacimiento: <input type="date" name="fecha_nacimiento" required></label><br>
        <label>Celular: <input type="text" name="celular"></label><br>
        <label>Domicilio: <input type="text" name="domicilio"></label><br>
        <label>Contacto de emergencia: <input type="text" name="contacto_emergencia"></label><br>
        <button type="submit">Crear Persona</button>
    </form>

    <h2>Listado</h2>
    <table border="1" cellpadding="5">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>DNI</th>
                <th>Fecha Nac.</th>
                <th>Celular</th>
                <th>Domicilio</th>
                <th>Contacto</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($p = $personas->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($p['apellidos']) ?>, <?= htmlspecialchars($p['nombres']) ?></td>
                    <td><?= htmlspecialchars($p['dni']) ?></td>
                    <td><?= htmlspecialchars($p['fecha_nacimiento']) ?></td>
                    <td><?= htmlspecialchars($p['celular']) ?></td>
                    <td><?= htmlspecialchars($p['domicilio']) ?></td>
                    <td><?= htmlspecialchars($p['contacto_emergencia']) ?></td>
                    <td>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="accion" value="borrar">
                            <input type="hidden" name="persona_id" value="<?= $p['id'] ?>">
                            <button onclick="return confirm('¿Eliminar esta persona?')">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>