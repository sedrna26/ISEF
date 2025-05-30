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

// Procesar asignación, modificación o eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['accion'] === 'asignar') {
        // Obtener el ciclo lectivo del curso seleccionado
        $stmt_ciclo = $mysqli->prepare("SELECT ciclo_lectivo FROM curso WHERE id = ?"); // Renombrada variable para evitar colisión
        $stmt_ciclo->bind_param("i", $_POST['curso_id']);
        $stmt_ciclo->execute();
        $stmt_ciclo->bind_result($ciclo_lectivo);
        $stmt_ciclo->fetch();
        $stmt_ciclo->close();

        // Realizar la asignación
        $stmt = $mysqli->prepare("INSERT INTO profesor_materia (profesor_id, materia_id, curso_id, ciclo_lectivo) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiii", $_POST['profesor_id'], $_POST['materia_id'], $_POST['curso_id'], $ciclo_lectivo);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['accion'] === 'modificar') { // <<< NUEVO BLOQUE IF
        if (isset($_POST['edit_asignacion_id'], $_POST['profesor_id'], $_POST['materia_id'], $_POST['curso_id']) && !empty($_POST['edit_asignacion_id'])) {
            // Obtener el ciclo lectivo del NUEVO curso seleccionado
            $stmt_ciclo_edit = $mysqli->prepare("SELECT ciclo_lectivo FROM curso WHERE id = ?");
            $stmt_ciclo_edit->bind_param("i", $_POST['curso_id']);
            $stmt_ciclo_edit->execute();
            $stmt_ciclo_edit->bind_result($ciclo_lectivo_edit);
            $stmt_ciclo_edit->fetch();
            $stmt_ciclo_edit->close();

            $stmt = $mysqli->prepare("UPDATE profesor_materia SET profesor_id = ?, materia_id = ?, curso_id = ?, ciclo_lectivo = ? WHERE id = ?");
            $stmt->bind_param("iiiii", $_POST['profesor_id'], $_POST['materia_id'], $_POST['curso_id'], $ciclo_lectivo_edit, $_POST['edit_asignacion_id']);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($_POST['accion'] === 'eliminar') {
        $stmt = $mysqli->prepare("DELETE FROM profesor_materia WHERE id = ?");
        $stmt->bind_param("i", $_POST['asignacion_id']);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: asignaciones.php");
    exit;
}
// Obtener datos necesarios
$asignaciones = $mysqli->query("
    SELECT pm.id as asignacion_id,
           pm.profesor_id,      
           pm.materia_id,       
           pm.curso_id,         
           p.apellidos, p.nombres,
           m.nombre as materia_nombre,
           c.codigo as curso_codigo, c.division,
           pm.ciclo_lectivo
    FROM profesor_materia pm
    JOIN vista_profesores p ON pm.profesor_id = p.profesor_id
    JOIN materia m ON pm.materia_id = m.id
    JOIN curso c ON pm.curso_id = c.id
    ORDER BY p.apellidos, p.nombres, m.nombre
");
// Las consultas para $profesores, $materias, $cursos no necesitan cambios.

$profesores = $mysqli->query("SELECT profesor_id, apellidos, nombres FROM vista_profesores ORDER BY apellidos, nombres");
$materias = $mysqli->query("SELECT id, nombre FROM materia ORDER BY nombre");
$cursos = $mysqli->query("SELECT id, codigo, division, ciclo_lectivo FROM curso ORDER BY codigo, division");
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Asignaciones Profesores - ISEF</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }
    </style>
</head>

<body>
    <h1>Gestión de Asignaciones de Profesores</h1>
    <a href="dashboard.php">&laquo; Volver al menú</a>

    <h2 id="formAsignacionTitulo">Nueva Asignación</h2>
    <form method="post" id="formGestionAsignacion">
        <input type="hidden" name="accion" value="asignar">
        <input type="hidden" name="edit_asignacion_id" id="edit_asignacion_id" value="">

        <label>Profesor:
            <select name="profesor_id" required>
                <option value="">-- Seleccione Profesor --</option>
                <?php while ($p = $profesores->fetch_assoc()): ?>
                    <option value="<?= $p['profesor_id'] ?>">
                        <?= htmlspecialchars($p['apellidos'] . ', ' . $p['nombres']) ?>
                    </option>
                <?php endwhile;
                $profesores->data_seek(0); // Reiniciar para posible uso futuro 
                ?>
            </select>
        </label><br>

        <label>Materia:
            <select name="materia_id" required>
                <option value="">-- Seleccione Materia --</option>
                <?php while ($m = $materias->fetch_assoc()): ?>
                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                <?php endwhile;
                $materias->data_seek(0); ?>
            </select>
        </label><br>

        <label>Curso:
            <select name="curso_id" required>
                <option value="">-- Seleccione Curso --</option>
                <?php while ($c = $cursos->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>">
                        <?= htmlspecialchars($c['codigo'] . ' - ' . $c['division'] . ' (' . $c['ciclo_lectivo'] . ')') ?>
                    </option>
                <?php endwhile;
                $cursos->data_seek(0); ?>
            </select>
        </label><br>

        <button type="submit">Asignar Profesor</button>
        <button type="button" id="cancelarEdicionBtnAsignacion" onclick="cancelarEdicionAsignacion()" style="display:none; margin-left: 10px;">Cancelar Edición</button>
    </form>
    <h2>Asignaciones Actuales</h2>
    <table>
        <thead>
            <tr>
                <th>Profesor</th>
                <th>Materia</th>
                <th>Curso</th>
                <th>Ciclo Lectivo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($a = $asignaciones->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($a['apellidos'] . ', ' . $a['nombres']) ?></td>
                    <td><?= htmlspecialchars($a['materia_nombre']) ?></td>
                    <td><?= htmlspecialchars($a['curso_codigo'] . ' - ' . $a['division']) ?></td>
                    <td><?= htmlspecialchars($a['ciclo_lectivo']) ?></td>
                    <td>
                        <button type="button" class="btn-editar" onclick="prepararEdicionAsignacion(<?= $a['asignacion_id'] ?>, <?= $a['profesor_id'] ?>, <?= $a['materia_id'] ?>, <?= $a['curso_id'] ?>)">Editar</button>
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
<script>
    function prepararEdicionAsignacion(id, profesorId, materiaId, cursoId) {
        const form = document.getElementById('formGestionAsignacion');
        form.querySelector('input[name="accion"]').value = 'modificar';
        form.querySelector('input[name="edit_asignacion_id"]').value = id;
        form.querySelector('select[name="profesor_id"]').value = profesorId;
        form.querySelector('select[name="materia_id"]').value = materiaId;
        form.querySelector('select[name="curso_id"]').value = cursoId;
        form.querySelector('button[type="submit"]').textContent = 'Guardar Cambios';

        document.getElementById('formAsignacionTitulo').textContent = 'Modificar Asignación';
        document.getElementById('cancelarEdicionBtnAsignacion').style.display = 'inline';

        form.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    function cancelarEdicionAsignacion() {
        const form = document.getElementById('formGestionAsignacion');
        form.querySelector('input[name="accion"]').value = 'asignar'; // Acción original para crear [cite: 47]
        form.querySelector('input[name="edit_asignacion_id"]').value = '';
        form.reset();
        form.querySelector('button[type="submit"]').textContent = 'Asignar Profesor'; // Texto original del botón

        document.getElementById('formAsignacionTitulo').textContent = 'Nueva Asignación'; // Título original
        document.getElementById('cancelarEdicionBtnAsignacion').style.display = 'none';
    }
</script>

</html>