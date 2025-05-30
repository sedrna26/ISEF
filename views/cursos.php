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
        $stmt->bind_param("ssssi", $_POST['codigo'], $_POST['division'], $_POST['anio'], $_POST['turno'], $_POST['ciclo_lectivo']); // [cite: 4]
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['accion'] === 'modificar') { // <<< NUEVO BLOQUE IF PARA MODIFICAR
        if (isset($_POST['edit_curso_id'], $_POST['codigo'], $_POST['division'], $_POST['anio'], $_POST['turno'], $_POST['ciclo_lectivo']) && !empty($_POST['edit_curso_id'])) {
            $stmt = $mysqli->prepare("UPDATE curso SET codigo = ?, division = ?, anio = ?, turno = ?, ciclo_lectivo = ? WHERE id = ?");
            $stmt->bind_param("ssssii", $_POST['codigo'], $_POST['division'], $_POST['anio'], $_POST['turno'], $_POST['ciclo_lectivo'], $_POST['edit_curso_id']);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($_POST['accion'] === 'borrar') {
        $stmt = $mysqli->prepare("DELETE FROM curso WHERE id = ?"); // [cite: 4]
        $stmt->bind_param("i", $_POST['curso_id']); // [cite: 5]
        $stmt->execute();
        $stmt->close();
    }
    header("Location: cursos.php");
    exit;
}

$cursos = $mysqli->query("SELECT * FROM curso ORDER BY ciclo_lectivo DESC, codigo, division"); // [cite: 6]
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

    <h2 id="formCursoTitulo">Nuevo Curso</h2>
    <form method="post" id="formGestionCurso">
        <input type="hidden" name="accion" value="crear">
        <input type="hidden" name="edit_curso_id" id="edit_curso_id" value="">
        <label>Código: <input type="text" name="codigo" required></label><br>
        <label>División: <input type="text" name="division" required></label><br>
        <label>Año: <input type="text" name="anio" required></label><br>
        <label>Turno:
            <select name="turno" required>
                <option value="Mañana">Mañana</option>
                <option value="Tarde">Tarde</option>
            </select>
        </label><br>
        <label>Ciclo Lectivo: <input type="number" name="ciclo_lectivo" required value="<?php echo date('Y'); ?>"></label><br>
        <button type="submit">Crear Curso</button>
        <button type="button" id="cancelarEdicionBtnCurso" onclick="cancelarEdicionCurso()" style="display:none; margin-left: 10px;">Cancelar Edición</button>
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
                        <button type="button" class="btn-editar" onclick="prepararEdicionCurso(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['codigo'])) ?>', '<?= htmlspecialchars(addslashes($c['division'])) ?>', '<?= htmlspecialchars(addslashes($c['anio'])) ?>', '<?= $c['turno'] ?>', <?= $c['ciclo_lectivo'] ?>)">Editar</button>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="accion" value="borrar">
                            <input type="hidden" name="curso_id" value="<?= $c['id'] ?>">
                            <button type="submit" onclick="return confirm('¿Eliminar este curso?')">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
<script>
    function prepararEdicionCurso(id, codigo, division, anio, turno, cicloLectivo) {
        const form = document.getElementById('formGestionCurso');
        form.querySelector('input[name="accion"]').value = 'modificar';
        form.querySelector('input[name="edit_curso_id"]').value = id;
        form.querySelector('input[name="codigo"]').value = codigo;
        form.querySelector('input[name="division"]').value = division;
        form.querySelector('input[name="anio"]').value = anio;
        form.querySelector('select[name="turno"]').value = turno;
        form.querySelector('input[name="ciclo_lectivo"]').value = cicloLectivo;
        form.querySelector('button[type="submit"]').textContent = 'Guardar Cambios';

        document.getElementById('formCursoTitulo').textContent = 'Modificar Curso';
        document.getElementById('cancelarEdicionBtnCurso').style.display = 'inline';

        // Scroll al formulario para una mejor experiencia de usuario
        form.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    function cancelarEdicionCurso() {
        const form = document.getElementById('formGestionCurso');
        form.querySelector('input[name="accion"]').value = 'crear';
        form.querySelector('input[name="edit_curso_id"]').value = '';
        form.reset(); // Limpia todos los campos del formulario
        form.querySelector('input[name="ciclo_lectivo"]').value = new Date().getFullYear(); // Restablecer ciclo lectivo al año actual
        form.querySelector('button[type="submit"]').textContent = 'Crear Curso';

        document.getElementById('formCursoTitulo').textContent = 'Nuevo Curso';
        document.getElementById('cancelarEdicionBtnCurso').style.display = 'none';
    }

    // Para establecer el año actual en el campo ciclo_lectivo al cargar la página para nuevos cursos.
    document.addEventListener('DOMContentLoaded', function() {
        const cicloLectivoInput = document.querySelector('#formGestionCurso input[name="ciclo_lectivo"]');
        if (!cicloLectivoInput.value) { // Solo si está vacío (para no sobrescribir en edición)
            cicloLectivoInput.value = new Date().getFullYear();
        }
    });
</script>

</html>