<?php
// profesores.php - Gestión de profesores
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: index.php");
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

// Crear o eliminar profesor
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['accion'] === 'crear') {
        $stmt = $mysqli->prepare("INSERT INTO profesor (persona_id, titulo_profesional, fecha_ingreso, horas_consulta) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $_POST['persona_id'], $_POST['titulo_profesional'], $_POST['fecha_ingreso'], $_POST['horas_consulta']);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['accion'] === 'borrar') {
        $stmt = $mysqli->prepare("DELETE FROM profesor WHERE id = ?");
        $stmt->bind_param("i", $_POST['profesor_id']);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: profesores.php");
    exit;
}

$profesores = $mysqli->query("SELECT * FROM vista_profesores ORDER BY apellidos, nombres");
$personas = $mysqli->query("SELECT id, apellidos, nombres FROM persona WHERE id NOT IN (SELECT persona_id FROM profesor) ORDER BY apellidos, nombres");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Profesores - ISEF</title>
</head>
<body>
    <h1>Gestión de Profesores</h1>
    <a href="dashboard.php">&laquo; Volver al menú</a>

    <h2>Nuevo Profesor</h2>
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
        <label>Título Profesional: <input type="text" name="titulo_profesional" required></label><br>
        <label>Fecha Ingreso: <input type="date" name="fecha_ingreso" required></label><br>
        <label>Horas de Consulta: <input type="text" name="horas_consulta"></label><br>
        <button type="submit">Crear Profesor</button>
    </form>

    <h2>Listado</h2>
    <table border="1" cellpadding="5">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>DNI</th>
                <th>Título</th>
                <th>Ingreso</th>
                <th>Consulta</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($p = $profesores->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($p['apellidos']) ?>, <?= htmlspecialchars($p['nombres']) ?></td>
                    <td><?= htmlspecialchars($p['dni']) ?></td>
                    <td><?= htmlspecialchars($p['titulo_profesional']) ?></td>
                    <td><?= htmlspecialchars($p['fecha_ingreso']) ?></td>
                    <td><?= htmlspecialchars($p['horas_consulta']) ?></td>
                    <td>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="accion" value="borrar">
                            <input type="hidden" name="profesor_id" value="<?= $p['profesor_id'] ?>">
                            <button onclick="return confirm('¿Eliminar este profesor?')">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
