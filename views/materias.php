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
    } elseif ($_POST['accion'] === 'modificar') { 
        if (isset($_POST['edit_materia_id'], $_POST['nro_orden'], $_POST['codigo'], $_POST['nombre'], $_POST['tipo'], $_POST['anio'], $_POST['cuatrimestre']) && !empty($_POST['edit_materia_id'])) { 
            $stmt = $mysqli->prepare("UPDATE materia SET nro_orden = ?, codigo = ?, nombre = ?, tipo = ?, anio = ?, cuatrimestre = ? WHERE id = ?"); 
            $stmt->bind_param("isssisi", $_POST['nro_orden'], $_POST['codigo'], $_POST['nombre'], $_POST['tipo'], $_POST['anio'], $_POST['cuatrimestre'], $_POST['edit_materia_id']); 
            $stmt->execute(); 
            $stmt->close(); 
        }
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
    <h1>Gestión de Materias</h1>
    <a href="dashboard.php" class="nav-link">&laquo; Volver al menú</a>

    <div class="form-container">
        <h2 id="formMateriaTitulo">Nueva Materia</h2>
        <form method="post" id="formGestionMateria">
            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="edit_materia_id" id="edit_materia_id" value="">
            
            <div class="form-group">
                <label for="nro_orden">Nro. Orden:</label>
                <input type="number" id="nro_orden" name="nro_orden" required>
            </div>
            <div class="form-group">
                <label for="codigo">Código:</label>
                <input type="text" id="codigo" name="codigo" required>
            </div>
            <div class="form-group">
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" required>
            </div>
            <div class="form-group">
                <label for="tipo">Tipo:</label>
                <select id="tipo" name="tipo" required>
                    <option value="Cuatrimestral">Cuatrimestral</option>
                    <option value="Anual">Anual</option>
                </select>
            </div>
            <div class="form-group">
                <label for="anio">Año:</label>
                <input type="number" id="anio" name="anio" min="1" required>
            </div>
            <div class="form-group">
                <label for="cuatrimestre">Cuatrimestre:</label>
                <select id="cuatrimestre" name="cuatrimestre" required>
                    <option value="1°">1°</option> <option value="2°">2°</option> <option value="Anual">Anual</option> </select>
            </div>
            <button type="submit">Crear Materia</button>
            <button type="button" id="cancelarEdicionBtnMateria" class="btn-cancel" onclick="cancelarEdicionMateria()" style="display:none;">Cancelar Edición</button>
        </form>
    </div>

    <h2>Listado de Materias</h2>
    <table>
        <thead>
            <tr>
                <th>N°</th> <th>Código</th> <th>Nombre</th> <th>Tipo</th> <th>Año</th> <th>Cuatrimestre</th> <th>Acción</th> </tr>
        </thead>
        <tbody>
            <?php while ($m = $materias->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($m['nro_orden']) ?></td>
                    <td><?= htmlspecialchars($m['codigo']) ?></td> <td><?= htmlspecialchars($m['nombre']) ?></td> <td><?= htmlspecialchars($m['tipo']) ?></td> <td><?= htmlspecialchars($m['anio']) ?></td> <td><?= htmlspecialchars($m['cuatrimestre']) ?></td> <td class="actions-cell">
                        <button type="button" class="btn-edit" onclick="prepararEdicionMateria(<?= $m['id'] ?>, <?= $m['nro_orden'] ?>, '<?= htmlspecialchars(addslashes($m['codigo'])) ?>', '<?= htmlspecialchars(addslashes($m['nombre'])) ?>', '<?= $m['tipo'] ?>', <?= $m['anio'] ?>, '<?= $m['cuatrimestre'] ?>')">Editar</button>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="accion" value="borrar"> <input type="hidden" name="materia_id" value="<?= $m['id'] ?>"> <button type="submit" class="btn-delete" onclick="return confirm('¿Eliminar esta materia?')">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
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
</body>
</html>