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
        $stmt_ciclo = $mysqli->prepare("SELECT ciclo_lectivo FROM curso WHERE id = ?"); 
        $stmt_ciclo->bind_param("i", $_POST['curso_id']); 
        $stmt_ciclo->execute(); 
        $stmt_ciclo->bind_result($ciclo_lectivo); 
        $stmt_ciclo->fetch(); 
        $stmt_ciclo->close(); 
        $stmt = $mysqli->prepare("INSERT INTO profesor_materia (profesor_id, materia_id, curso_id, ciclo_lectivo) VALUES (?, ?, ?, ?)"); 
        $stmt->bind_param("iiii", $_POST['profesor_id'], $_POST['materia_id'], $_POST['curso_id'], $ciclo_lectivo); 
        $stmt->execute(); 
        $stmt->close(); 
    } elseif ($_POST['accion'] === 'modificar') { 
        if (isset($_POST['edit_asignacion_id'], $_POST['profesor_id'], $_POST['materia_id'], $_POST['curso_id']) && !empty($_POST['edit_asignacion_id'])) { 
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
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f7f6; color: #333; }
        .container { max-width: 1000px; margin: 20px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        h1, h2 { color: #2c3e50; margin-bottom: 20px; }
        h1 { font-size: 1.8em; }
        h2 { font-size: 1.4em; }
        a { color: #3498db; text-decoration: none; }
        a:hover { text-decoration: underline; }

        .nav-link {
            display: inline-block;
            margin-bottom: 20px;
            padding: 8px 15px;
            background-color: #6c757d;
            color: white;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .nav-link:hover {
            background-color: #5a6268;
            text-decoration: none;
        }

        .form-container { margin-bottom: 30px; padding: 25px; border: 1px solid #ddd; border-radius: 8px; background-color: #fdfdfd; }
        .form-container h2 { margin-top: 0; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: bold; color: #555; font-size: 0.9em; }
        input[type="text"], input[type="date"], input[type="number"], input[type="email"], input[type="search"], select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 0.95em;
        }
        input[type="text"]:focus, input[type="date"]:focus, input[type="number"]:focus, input[type="email"]:focus, input[type="search"]:focus, select:focus, textarea:focus {
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.25);
            outline: none;
        }

        button, .button-link {
            padding: 10px 18px;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.95em;
            text-align: center;
            display: inline-block;
            margin-right: 8px;
            margin-top: 5px; 
            margin-bottom: 5px;
            transition: background-color 0.2s ease-in-out;
        }
        button[type="submit"] { background-color: #28a745; }
        button[type="submit"]:hover { background-color: #218838; }

        .btn-edit { background-color: #007bff; color: white; }
        .btn-edit:hover { background-color: #0056b3; }

        .btn-delete { background-color: #dc3545; color: white; }
        .btn-delete:hover { background-color: #c82333; }

        .btn-cancel { background-color: #6c757d; color: white; }
        .btn-cancel:hover { background-color: #5a6268; }

        table {
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 25px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        th, td {
            border: 1px solid #ddd; 
            padding: 10px 12px; 
            text-align: left; 
            vertical-align: middle;
        }
        th {
            background-color: #f2f5f7; 
            font-weight: 600;
            color: #333;
        }
        tbody tr:nth-child(odd) {
            background-color: #fdfdfd;
        }
        tbody tr:hover {
            background-color: #f0f8ff;
        }
        .actions-cell form {
            display: inline-block;
            margin-right: 5px;
        }
        .actions-cell button { 
            padding: 6px 10px;
            font-size: 0.85em;
        }
         .actions-cell .btn-edit { 
             padding: 6px 10px;
             font-size: 0.85em;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Gestión de Asignaciones de Profesores</h1>
    <a href="dashboard.php" class="nav-link">&laquo; Volver al menú</a> <div class="form-container">
        <h2 id="formAsignacionTitulo">Nueva Asignación</h2>
        <form method="post" id="formGestionAsignacion">
            <input type="hidden" name="accion" value="asignar">
            <input type="hidden" name="edit_asignacion_id" id="edit_asignacion_id" value="">

            <div class="form-group">
                <label for="profesor_id">Profesor:</label>
                <select id="profesor_id" name="profesor_id" required>
                    <option value="">-- Seleccione Profesor --</option>
                    <?php while ($p = $profesores->fetch_assoc()): ?>
                        <option value="<?= $p['profesor_id'] ?>">
                            <?= htmlspecialchars($p['apellidos'] . ', ' . $p['nombres']) ?>
                        </option>
                    <?php endwhile; $profesores->data_seek(0); ?> 
                </select>
            </div>

            <div class="form-group">
                <label for="materia_id">Materia:</label>
                <select id="materia_id" name="materia_id" required>
                    <option value="">-- Seleccione Materia --</option>
                    <?php while ($m = $materias->fetch_assoc()): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                    <?php endwhile; $materias->data_seek(0); ?>
                </select>
            </div>

            <div class="form-group">
                <label for="curso_id">Curso:</label>
                <select id="curso_id" name="curso_id" required>
                    <option value="">-- Seleccione Curso --</option>
                    <?php while ($c = $cursos->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= htmlspecialchars($c['codigo'] . ' - ' . $c['division'] . ' (' . $c['ciclo_lectivo'] . ')') ?>
                        </option>
                    <?php endwhile; $cursos->data_seek(0); ?> 
                </select>
            </div>

            <button type="submit">Asignar Profesor</button>
            <button type="button" id="cancelarEdicionBtnAsignacion" class="btn-cancel" onclick="cancelarEdicionAsignacion()" style="display:none;">Cancelar Edición</button>
        </form>
    </div>
    
    <h2>Asignaciones Actuales</h2>
    <table>
        <thead>
            <tr>
                <th>Profesor</th>
                <th>Materia</th> <th>Curso</th> <th>Ciclo Lectivo</th> <th>Acciones</th> </tr>
        </thead>
        <tbody>
            <?php while ($a = $asignaciones->fetch_assoc()): ?> 
                <tr>
                    <td><?= htmlspecialchars($a['apellidos'] . ', ' . $a['nombres']) ?></td>
                    <td><?= htmlspecialchars($a['materia_nombre']) ?></td>
                    <td><?= htmlspecialchars($a['curso_codigo'] . ' - ' . $a['division']) ?></td>
                    <td><?= htmlspecialchars($a['ciclo_lectivo']) ?></td> <td class="actions-cell">
                        <button type="button" class="btn-edit" onclick="prepararEdicionAsignacion(<?= $a['asignacion_id'] ?>, <?= $a['profesor_id'] ?>, <?= $a['materia_id'] ?>, <?= $a['curso_id'] ?>)">Editar</button>
                        <form method="post" style="display:inline;"> <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="asignacion_id" value="<?= $a['asignacion_id'] ?>">
                            <button type="submit" class="btn-delete" onclick="return confirm('¿Eliminar esta asignación?')">Eliminar</button> 
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
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

        form.scrollIntoView({ behavior: 'smooth', block: 'start' }); 
    }

    function cancelarEdicionAsignacion() {
        const form = document.getElementById('formGestionAsignacion');
        form.querySelector('input[name="accion"]').value = 'asignar'; 
        form.querySelector('input[name="edit_asignacion_id"]').value = ''; 
        form.reset(); 
        form.querySelector('button[type="submit"]').textContent = 'Asignar Profesor'; 
        document.getElementById('formAsignacionTitulo').textContent = 'Nueva Asignación'; 
        document.getElementById('cancelarEdicionBtnAsignacion').style.display = 'none'; 
    }
</script>
</body>
</html>