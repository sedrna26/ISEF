<?php
// cursos.php - Gestión de cursos
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: index.php");
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['accion'] === 'crear') {
        $stmt = $mysqli->prepare("INSERT INTO curso (codigo, division, anio, turno, ciclo_lectivo) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $_POST['codigo'], $_POST['division'], $_POST['anio'], $_POST['turno'], $_POST['ciclo_lectivo']);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['accion'] === 'borrar') {
        $stmt = $mysqli->prepare("DELETE FROM curso WHERE id = ?");
        $stmt->bind_param("i", $_POST['curso_id']);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: cursos.php");
    exit;
}

$cursos = $mysqli->query("SELECT * FROM curso ORDER BY ciclo_lectivo DESC, codigo, division");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cursos - ISEF</title>
</head>
<body>
    <h1>Gestión de Cursos</h1>
    <a href="dashboard.php">&laquo; Volver al menú</a>

    <h2>Nuevo Curso</h2>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <label>Código: <input type="text" name="codigo" required></label><br>
        <label>División: <input type="text" name="division" required></label><br>
        <label>Año: <input type="text" name="anio" required></label><br>
        <label>Turno:
            <select name="turno" required>
                <option value="Mañana">Mañana</option>
                <option value="Tarde">Tarde</option>
            </select>
        </label><br>
        <label>Ciclo Lectivo: <input type="number" name="ciclo_lectivo" required></label><br>
        <button type="submit">Crear Curso</button>
    </form>

    <h2>Listado de Cursos</h2>
    <table border="1" cellpadding="5">
        <thead>
            <tr>
                <th>Código</th>
                <th>División</th>
                <th>Año</th>
                <th>Turno</th>
                <th>Ciclo Lectivo</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($c = $cursos->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($c['codigo']) ?></td>
                    <td><?= htmlspecialchars($c['division']) ?></td>
                    <td><?= htmlspecialchars($c['anio']) ?></td>
                    <td><?= htmlspecialchars($c['turno']) ?></td>
                    <td><?= htmlspecialchars($c['ciclo_lectivo']) ?></td>
                    <td>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="accion" value="borrar">
                            <input type="hidden" name="curso_id" value="<?= $c['id'] ?>">
                            <button onclick="return confirm('¿Eliminar este curso?')">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
