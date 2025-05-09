<?php
// alumnos.php - Gestión de alumnos
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: index.php");
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

// Alta de alumno
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'crear') {
        $stmt = $mysqli->prepare("INSERT INTO alumno (persona_id, legajo, fecha_ingreso, cohorte) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $_POST['persona_id'], $_POST['legajo'], $_POST['fecha_ingreso'], $_POST['cohorte']);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['accion'] === 'borrar') {
        $stmt = $mysqli->prepare("DELETE FROM alumno WHERE id = ?");
        $stmt->bind_param("i", $_POST['alumno_id']);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['accion'] === 'editar') {
        $stmt = $mysqli->prepare("UPDATE alumno SET legajo = ?, fecha_ingreso = ?, cohorte = ? WHERE id = ?");
        $stmt->bind_param("ssii", $_POST['legajo'], $_POST['fecha_ingreso'], $_POST['cohorte'], $_POST['alumno_id']);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: alumnos.php");
    exit;
}

// Obtener alumnos desde vista_alumnos
$result = $mysqli->query("SELECT * FROM vista_alumnos ORDER BY apellidos, nombres");
$personas = $mysqli->query("SELECT id, apellidos, nombres FROM persona ORDER BY apellidos, nombres");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Alumnos - ISEF</title>
</head>
<body>
    <h1>Listado de Alumnos</h1>
    <a href="dashboard.php">&laquo; Volver al menú</a>

    <h2>Nuevo Alumno</h2>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <label>Persona:
            <select name="persona_id" required>
                <option value="">-- Seleccione --</option>
                <?php while ($p = $personas->fetch_assoc()): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['apellidos'] . ', ' . $p['nombres']) ?></option>
                <?php endwhile; ?>
            </select>
        </label><br>
        <label>Legajo: <input type="text" name="legajo" required></label><br>
        <label>Fecha Ingreso: <input type="date" name="fecha_ingreso" required></label><br>
        <label>Cohorte: <input type="number" name="cohorte" required></label><br>
        <button type="submit">Crear</button>
    </form>

    <h2>Alumnos Registrados</h2>
    <table border="1" cellpadding="5">
        <thead>
            <tr>
                <th>Legajo</th>
                <th>Nombre</th>
                <th>DNI</th>
                <th>Cohorte</th>
                <th>Fecha de Ingreso</th>
                <th>Activo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($alumno = $result->fetch_assoc()): ?>
                <tr>
                    <form method="post">
                        <td><input type="text" name="legajo" value="<?= htmlspecialchars($alumno['legajo']) ?>"></td>
                        <td><?= htmlspecialchars($alumno['apellidos']) ?>, <?= htmlspecialchars($alumno['nombres']) ?></td>
                        <td><?= htmlspecialchars($alumno['dni']) ?></td>
                        <td><input type="number" name="cohorte" value="<?= htmlspecialchars($alumno['cohorte']) ?>"></td>
                        <td><input type="date" name="fecha_ingreso" value="<?= htmlspecialchars($alumno['fecha_ingreso']) ?>"></td>
                        <td><?= $alumno['activo'] ? 'Sí' : 'No' ?></td>
                        <td>
                            <input type="hidden" name="alumno_id" value="<?= $alumno['alumno_id'] ?>">
                            <button name="accion" value="editar">Editar</button>
                            <button name="accion" value="borrar" onclick="return confirm('¿Está seguro?')">Eliminar</button>
                        </td>
                    </form>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
