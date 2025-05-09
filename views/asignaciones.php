<?php
// asignaciones.php - Gestión de asignación de profesores a materias y cursos
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: index.php");
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

// Procesar asignación/eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['accion'] === 'asignar') {
        $stmt = $mysqli->prepare("INSERT INTO profesor_materia_curso (profesor_id, materia_id, curso_id, fecha_desde) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $_POST['profesor_id'], $_POST['materia_id'], $_POST['curso_id'], $_POST['fecha_desde']);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['accion'] === 'eliminar') {
        $stmt = $mysqli->prepare("DELETE FROM profesor_materia_curso WHERE id = ?");
        $stmt->bind_param("i", $_POST['asignacion_id']);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: asignaciones.php");
    exit;
}

// Obtener datos necesarios
$asignaciones = $mysqli->query("
    SELECT pmc.id as asignacion_id, 
           p.apellidos, p.nombres, 
           m.nombre as materia_nombre, 
           c.codigo as curso_codigo, c.division, 
           pmc.fecha_desde
    FROM profesor_materia_curso pmc
    JOIN vista_profesores p ON pmc.profesor_id = p.profesor_id
    JOIN materia m ON pmc.materia_id = m.id
    JOIN curso c ON pmc.curso_id = c.id
    ORDER BY p.apellidos, p.nombres, m.nombre
");

$profesores = $mysqli->query("SELECT profesor_id, apellidos, nombres FROM vista_profesores ORDER BY apellidos, nombres");
$materias = $mysqli->query("SELECT id, nombre FROM materia ORDER BY nombre");
$cursos = $mysqli->query("SELECT id, codigo, division FROM curso ORDER BY codigo, division");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignaciones Profesores - ISEF</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Gestión de Asignaciones de Profesores</h1>
    <a href="dashboard.php">&laquo; Volver al menú</a>

    <h2>Nueva Asignación</h2>
    <form method="post">
        <input type="hidden" name="accion" value="asignar">
        
        <label>Profesor:
            <select name="profesor_id" required>
                <option value="">-- Seleccione Profesor --</option>
                <?php while ($p = $profesores->fetch_assoc()): ?>
                    <option value="<?= $p['profesor_id'] ?>">
                        <?= htmlspecialchars($p['apellidos'] . ', ' . $p['nombres']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </label><br>
        
        <label>Materia:
            <select name="materia_id" required>
                <option value="">-- Seleccione Materia --</option>
                <?php while ($m = $materias->fetch_assoc()): ?>
                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                <?php endwhile; ?>
            </select>
        </label><br>
        
        <label>Curso:
            <select name="curso_id" required>
                <option value="">-- Seleccione Curso --</option>
                <?php while ($c = $cursos->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>">
                        <?= htmlspecialchars($c['codigo'] . ' - ' . $c['division']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </label><br>
        
        <label>Fecha de inicio: <input type="date" name="fecha_desde" required></label><br>
        
        <button type="submit">Asignar Profesor</button>
    </form>

    <h2>Asignaciones Actuales</h2>
    <table>
        <thead>
            <tr>
                <th>Profesor</th>
                <th>Materia</th>
                <th>Curso</th>
                <th>Desde</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($a = $asignaciones->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($a['apellidos'] . ', ' . $a['nombres']) ?></td>
                    <td><?= htmlspecialchars($a['materia_nombre']) ?></td>
                    <td><?= htmlspecialchars($a['curso_codigo'] . ' - ' . $a['division']) ?></td>
                    <td><?= htmlspecialchars($a['fecha_desde']) ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="asignacion_id" value="<?= $a['asignacion_id'] ?>">
                            <button onclick="return confirm('¿Eliminar esta asignación?')">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>