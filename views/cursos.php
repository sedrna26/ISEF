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
    } elseif ($_POST['accion'] === 'modificar') { 
        if (isset($_POST['edit_curso_id'], $_POST['codigo'], $_POST['division'], $_POST['anio'], $_POST['turno'], $_POST['ciclo_lectivo']) && !empty($_POST['edit_curso_id'])) { 
            $stmt = $mysqli->prepare("UPDATE curso SET codigo = ?, division = ?, anio = ?, turno = ?, ciclo_lectivo = ? WHERE id = ?"); 
            $stmt->bind_param("ssssii", $_POST['codigo'], $_POST['division'], $_POST['anio'], $_POST['turno'], $_POST['ciclo_lectivo'], $_POST['edit_curso_id']); 
            $stmt->execute(); 
            $stmt->close(); 
        }
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
            border: 1px solid #e0e0e0;
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
    <h1>Gestión de Cursos</h1>
    <a href="dashboard.php" class="nav-link">&laquo; Volver al menú</a> <div class="form-container">
        <h2 id="formCursoTitulo">Nuevo Curso</h2>
        <form method="post" id="formGestionCurso">
            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="edit_curso_id" id="edit_curso_id" value="">
            <div class="form-group">
                <label for="codigo">Código:</label>
                <input type="text" id="codigo" name="codigo" required>
            </div>
            <div class="form-group">
                <label for="division">División:</label>
                <input type="text" id="division" name="division" required>
            </div>
            <div class="form-group">
                <label for="anio">Año:</label>
                <input type="text" id="anio" name="anio" required>
            </div>
            <div class="form-group">
                <label for="turno">Turno:</label>
                <select id="turno" name="turno" required>
                    <option value="Mañana">Mañana</option> <option value="Tarde">Tarde</option> </select>
            </div>
            <div class="form-group">
                <label for="ciclo_lectivo">Ciclo Lectivo:</label>
                <input type="number" id="ciclo_lectivo" name="ciclo_lectivo" required value="<?php echo date('Y'); ?>">
            </div>
            <button type="submit">Crear Curso</button>
            <button type="button" id="cancelarEdicionBtnCurso" class="btn-cancel" onclick="cancelarEdicionCurso()" style="display:none;">Cancelar Edición</button>
        </form>
    </div>

    <h2>Listado de Cursos</h2>
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>División</th>
                <th>Año</th>
                <th>Turno</th> <th>Ciclo Lectivo</th> <th>Acción</th> </tr>
        </thead>
        <tbody>
            <?php while ($c = $cursos->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($c['codigo']) ?></td> <td><?= htmlspecialchars($c['division']) ?></td> <td><?= htmlspecialchars($c['anio']) ?></td> <td><?= htmlspecialchars($c['turno']) ?></td> <td><?= htmlspecialchars($c['ciclo_lectivo']) ?></td> <td class="actions-cell">
                        <button type="button" class="btn-edit" onclick="prepararEdicionCurso(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['codigo'])) ?>', '<?= htmlspecialchars(addslashes($c['division'])) ?>', '<?= htmlspecialchars(addslashes($c['anio'])) ?>', '<?= $c['turno'] ?>', <?= $c['ciclo_lectivo'] ?>)">Editar</button>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="accion" value="borrar"> <input type="hidden" name="curso_id" value="<?= $c['id'] ?>"> <button type="submit" class="btn-delete" onclick="return confirm('¿Eliminar este curso?')">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
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
        form.scrollIntoView({ behavior: 'smooth', block: 'start' }); 
    }

    function cancelarEdicionCurso() {
        const form = document.getElementById('formGestionCurso');
        form.querySelector('input[name="accion"]').value = 'crear'; 
        form.querySelector('input[name="edit_curso_id"]').value = ''; 
        form.reset(); 
        form.querySelector('input[name="ciclo_lectivo"]').value = new Date().getFullYear(); 
        form.querySelector('button[type="submit"]').textContent = 'Crear Curso'; 
        document.getElementById('formCursoTitulo').textContent = 'Nuevo Curso'; 
        document.getElementById('cancelarEdicionBtnCurso').style.display = 'none'; 
    }

    document.addEventListener('DOMContentLoaded', function() { 
        const cicloLectivoInput = document.querySelector('#formGestionCurso input[name="ciclo_lectivo"]');
        if (!cicloLectivoInput.value || document.getElementById('formGestionCurso').querySelector('input[name="accion"]').value === 'crear') { 
             
            const editCursoId = document.getElementById('formGestionCurso').querySelector('input[name="edit_curso_id"]').value;
            if (!editCursoId) { 
                 cicloLectivoInput.value = new Date().getFullYear();
            }
        }
    });
</script>
</body>
</html>