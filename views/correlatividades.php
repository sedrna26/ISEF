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

// Procesar creación, modificación o eliminación de correlatividades
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['accion'] === 'crear') {
        $stmt = $mysqli->prepare("INSERT INTO correlatividad (materia_id, materia_correlativa_id, tipo) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $_POST['materia_id'], $_POST['materia_correlativa_id'], $_POST['tipo']);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['accion'] === 'modificar') { 
        if (isset($_POST['edit_correlatividad_id'], $_POST['materia_id'], $_POST['materia_correlativa_id'], $_POST['tipo']) && !empty($_POST['edit_correlatividad_id'])) {
            $stmt = $mysqli->prepare("UPDATE correlatividad SET materia_id = ?, materia_correlativa_id = ?, tipo = ? WHERE id = ?");
            $stmt->bind_param("iisi", $_POST['materia_id'], $_POST['materia_correlativa_id'], $_POST['tipo'], $_POST['edit_correlatividad_id']);
            $stmt->execute();
            $stmt->close();
        }
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
           m1.id as materia_id_actual,
           m1.nombre as materia_nombre,
           m2.id as materia_correlativa_id_actual, 
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

    <h2 id="formCorrelatividadTitulo">Nueva Correlatividad</h2>
<form method="post" id="formGestionCorrelatividad">
    <input type="hidden" name="accion" value="crear">
    <input type="hidden" name="edit_correlatividad_id" id="edit_correlatividad_id" value=""> 

    <div class="form-group">
        <label>Materia:
            <select name="materia_id" required>
                <option value="">-- Seleccione Materia --</option>
                <?php
                // Asegurarse de que $materias se puede usar aquí de nuevo si es necesario, o clonar el resultado
                $materias->data_seek(0); // Reiniciar por si acaso
                while ($m = $materias->fetch_assoc()): ?>
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
                <option value="Para cursar regularizada">Para cursar regularizada</option> 
                <option value="Para cursar acreditada">Para cursar acreditada</option> 
                <option value="Para acreditar">Para acreditar</option> 
            </select>
        </label>
    </div>

    <button type="submit">Crear Correlatividad</button>
    <button type="button" id="cancelarEdicionBtnCorrelatividad" onclick="cancelarEdicionCorrelatividad()" style="display:none; margin-left: 10px;">Cancelar Edición</button> 
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
                        <button type="button" class="btn-editar" onclick="prepararEdicionCorrelatividad(<?= $cor['correlatividad_id'] ?>, <?= $cor['materia_id_actual'] ?>, <?= $cor['materia_correlativa_id_actual'] ?>, '<?= htmlspecialchars($cor['tipo']) ?>')">Editar</button>
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
<script>
function prepararEdicionCorrelatividad(id, materiaId, materiaCorrelativaId, tipo) {
    const form = document.getElementById('formGestionCorrelatividad');
    form.querySelector('input[name="accion"]').value = 'modificar';
    form.querySelector('input[name="edit_correlatividad_id"]').value = id;
    form.querySelector('select[name="materia_id"]').value = materiaId;
    form.querySelector('select[name="materia_correlativa_id"]').value = materiaCorrelativaId;
    form.querySelector('select[name="tipo"]').value = tipo;
    form.querySelector('button[type="submit"]').textContent = 'Guardar Cambios';

    document.getElementById('formCorrelatividadTitulo').textContent = 'Modificar Correlatividad';
    document.getElementById('cancelarEdicionBtnCorrelatividad').style.display = 'inline';
    
    // Scroll to form for better UX
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function cancelarEdicionCorrelatividad() {
    const form = document.getElementById('formGestionCorrelatividad');
    form.querySelector('input[name="accion"]').value = 'crear';
    form.querySelector('input[name="edit_correlatividad_id"]').value = '';
    form.reset(); // Limpia los campos del formulario
    form.querySelector('button[type="submit"]').textContent = 'Crear Correlatividad';

    document.getElementById('formCorrelatividadTitulo').textContent = 'Nueva Correlatividad';
    document.getElementById('cancelarEdicionBtnCorrelatividad').style.display = 'none';
}
</script>
</html>