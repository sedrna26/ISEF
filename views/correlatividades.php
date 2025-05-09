<?php
// correlatividades.php - Gestión de correlatividades entre materias
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: index.php");
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

// Procesar creación o eliminación de correlatividades
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['accion'] === 'crear') {
        $stmt = $mysqli->prepare("INSERT INTO correlatividad (materia_id, materia_correlativa_id, tipo) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $_POST['materia_id'], $_POST['materia_correlativa_id'], $_POST['tipo']);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['accion'] === 'eliminar') {
        $stmt = $mysqli->prepare("DELETE FROM correlatividad WHERE id = ?");
        $stmt->bind_param("i", $_POST['correlatividad_id']);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: correlatividades.php");
    exit;
}

// Obtener datos necesarios
$correlatividades = $mysqli->query("
    SELECT c.id as correlatividad_id, 
           m1.nombre as materia_nombre, 
           m2.nombre as materia_correlativa_nombre,
           c.tipo
    FROM correlatividad c
    JOIN materia m1 ON c.materia_id = m1.id
    JOIN materia m2 ON c.materia_correlativa_id = m2.id
    ORDER BY m1.nombre, m2.nombre
");

$materias = $mysqli->query("SELECT id, nombre FROM materia ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Correlatividades - ISEF</title>
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .form-group { margin-bottom: 15px; }
        select, button { padding: 8px; }
    </style>
</head>
<body>
    <h1>Gestión de Correlatividades</h1>
    <a href="dashboard.php">&laquo; Volver al menú</a>

    <h2>Nueva Correlatividad</h2>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        
        <div class="form-group">
            <label>Materia:
                <select name="materia_id" required>
                    <option value="">-- Seleccione Materia --</option>
                    <?php while ($m = $materias->fetch_assoc()): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                    <?php endwhile; ?>
                </select>
            </label>
        </div>
        
        <div class="form-group">
            <label>Materia Correlativa:
                <select name="materia_correlativa_id" required>
                    <option value="">-- Seleccione Materia Correlativa --</option>
                    <?php 
                    $materias->data_seek(0); // Reiniciar el puntero del resultado
                    while ($m = $materias->fetch_assoc()): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                    <?php endwhile; ?>
                </select>
            </label>
        </div>
        
        <div class="form-group">
            <label>Tipo de Correlatividad:
                <select name="tipo" required>
                    <option value="regular">Regular</option>
                    <option value="aprobada">Aprobada</option>
                    <option value="cursada">Cursada</option>
                </select>
            </label>
        </div>
        
        <button type="submit">Crear Correlatividad</button>
    </form>

    <h2>Correlatividades Existentes</h2>
    <table>
        <thead>
            <tr>
                <th>Materia</th>
                <th>Correlativa con</th>
                <th>Tipo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($cor = $correlatividades->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($cor['materia_nombre']) ?></td>
                    <td><?= htmlspecialchars($cor['materia_correlativa_nombre']) ?></td>
                    <td><?= htmlspecialchars(ucfirst($cor['tipo'])) ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="correlatividad_id" value="<?= $cor['correlatividad_id'] ?>">
                            <button onclick="return confirm('¿Eliminar esta correlatividad?')">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>