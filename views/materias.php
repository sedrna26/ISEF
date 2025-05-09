<?php
// materias.php - Gestión de materias
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: index.php");
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

// Crear o eliminar materia
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['accion'] === 'crear') {
        $stmt = $mysqli->prepare("INSERT INTO materia (nro_orden, codigo, nombre, tipo, anio, cuatrimestre) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssis", $_POST['nro_orden'], $_POST['codigo'], $_POST['nombre'], $_POST['tipo'], $_POST['anio'], $_POST['cuatrimestre']);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['accion'] === 'borrar') {
        $stmt = $mysqli->prepare("DELETE FROM materia WHERE id = ?");
        $stmt->bind_param("i", $_POST['materia_id']);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: materias.php");
    exit;
}

$materias = $mysqli->query("SELECT * FROM materia ORDER BY anio, nro_orden");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Materias - ISEF</title>
</head>
<body>
    <h1>Gestión de Materias</h1>
    <a href="dashboard.php">&laquo; Volver al menú</a>

    <h2>Nueva Materia</h2>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <label>Nro. Orden: <input type="number" name="nro_orden" required></label><br>
        <label>Código: <input type="text" name="codigo" required></label><br>
        <label>Nombre: <input type="text" name="nombre" required></label><br>
        <label>Tipo:
            <select name="tipo" required>
                <option value="Cuatrimestral">Cuatrimestral</option>
                <option value="Anual">Anual</option>
            </select>
        </label><br>
        <label>Año: <input type="number" name="anio" required></label><br>
        <label>Cuatrimestre:
            <select name="cuatrimestre" required>
                <option value="1°">1°</option>
                <option value="2°">2°</option>
                <option value="Anual">Anual</option>
            </select>
        </label><br>
        <button type="submit">Crear Materia</button>
    </form>

    <h2>Listado de Materias</h2>
    <table border="1" cellpadding="5">
        <thead>
            <tr>
                <th>N°</th>
                <th>Código</th>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Año</th>
                <th>Cuatrimestre</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($m = $materias->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($m['nro_orden']) ?></td>
                    <td><?= htmlspecialchars($m['codigo']) ?></td>
                    <td><?= htmlspecialchars($m['nombre']) ?></td>
                    <td><?= htmlspecialchars($m['tipo']) ?></td>
                    <td><?= htmlspecialchars($m['anio']) ?></td>
                    <td><?= htmlspecialchars($m['cuatrimestre']) ?></td>
                    <td>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="accion" value="borrar">
                            <input type="hidden" name="materia_id" value="<?= $m['id'] ?>">
                            <button onclick="return confirm('¿Eliminar esta materia?')">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>