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

// Crear, modificar o eliminar materia
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['accion'] === 'crear') {
        $stmt = $mysqli->prepare("INSERT INTO materia (nro_orden, codigo, nombre, tipo, anio, cuatrimestre) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssis", $_POST['nro_orden'], $_POST['codigo'], $_POST['nombre'], $_POST['tipo'], $_POST['anio'], $_POST['cuatrimestre']);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['accion'] === 'modificar') { // <<< NUEVO BLOQUE IF
        if (isset($_POST['edit_materia_id'], $_POST['nro_orden'], $_POST['codigo'], $_POST['nombre'], $_POST['tipo'], $_POST['anio'], $_POST['cuatrimestre']) && !empty($_POST['edit_materia_id'])) {
            $stmt = $mysqli->prepare("UPDATE materia SET nro_orden = ?, codigo = ?, nombre = ?, tipo = ?, anio = ?, cuatrimestre = ? WHERE id = ?");
            $stmt->bind_param("isssisi", $_POST['nro_orden'], $_POST['codigo'], $_POST['nombre'], $_POST['tipo'], $_POST['anio'], $_POST['cuatrimestre'], $_POST['edit_materia_id']);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($_POST['accion'] === 'borrar') { // 'borrar' es la acción original para eliminar [cite: 30]
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
    <h2 id="formMateriaTitulo">Nueva Materia</h2>
<form method="post" id="formGestionMateria">
    <input type="hidden" name="accion" value="crear">
    <input type="hidden" name="edit_materia_id" id="edit_materia_id" value=""> {/* <<< AÑADIDO */}
    <label>Nro. Orden: <input type="number" name="nro_orden" required></label><br>
    <label>Código: <input type="text" name="codigo" required></label><br>
    <label>Nombre: <input type="text" name="nombre" required></label><br>
    <label>Tipo:
        <select name="tipo" required>
            <option value="Cuatrimestral">Cuatrimestral</option>
            <option value="Anual">Anual</option>
        </select>
    </label><br>
    <label>Año: <input type="number" name="anio" min="1" required></label><br> {/* Añadido min="1" para mejor validación */}
    <label>Cuatrimestre:
        <select name="cuatrimestre" required>
            <option value="1°">1°</option>
            <option value="2°">2°</option>
            <option value="Anual">Anual</option>
        </select>
    </label><br>
    <button type="submit">Crear Materia</button>
    <button type="button" id="cancelarEdicionBtnMateria" onclick="cancelarEdicionMateria()" style="display:none; margin-left: 10px;">Cancelar Edición</button> {/* <<< AÑADIDO */}
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
                        <button type="button" class="btn-editar" onclick="prepararEdicionMateria(<?= $m['id'] ?>, <?= $m['nro_orden'] ?>, '<?= htmlspecialchars(addslashes($m['codigo'])) ?>', '<?= htmlspecialchars(addslashes($m['nombre'])) ?>', '<?= $m['tipo'] ?>', <?= $m['anio'] ?>, '<?= $m['cuatrimestre'] ?>')">Editar</button>
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
<script>
function prepararEdicionMateria(id, nroOrden, codigo, nombre, tipo, anio, cuatrimestre) {
    const form = document.getElementById('formGestionMateria');
    form.querySelector('input[name="accion"]').value = 'modificar';
    form.querySelector('input[name="edit_materia_id"]').value = id;
    form.querySelector('input[name="nro_orden"]').value = nroOrden;
    form.querySelector('input[name="codigo"]').value = codigo;
    form.querySelector('input[name="nombre"]').value = nombre;
    form.querySelector('select[name="tipo"]').value = tipo;
    form.querySelector('input[name="anio"]').value = anio;
    form.querySelector('select[name="cuatrimestre"]').value = cuatrimestre;
    form.querySelector('button[type="submit"]').textContent = 'Guardar Cambios';

    document.getElementById('formMateriaTitulo').textContent = 'Modificar Materia';
    document.getElementById('cancelarEdicionBtnMateria').style.display = 'inline';
    
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function cancelarEdicionMateria() {
    const form = document.getElementById('formGestionMateria');
    form.querySelector('input[name="accion"]').value = 'crear';
    form.querySelector('input[name="edit_materia_id"]').value = '';
    form.reset();
    form.querySelector('button[type="submit"]').textContent = 'Crear Materia';

    document.getElementById('formMateriaTitulo').textContent = 'Nueva Materia';
    document.getElementById('cancelarEdicionBtnMateria').style.display = 'none';
}
</script>
</html>