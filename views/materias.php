<?php
// materias.php - Gestión unificada de materias y correlatividades
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: index.php");
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

// --- PROCESAR ACCIONES DEL FORMULARIO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    // Acciones para Materias
    if ($accion === 'crear_materia') {
        $stmt = $mysqli->prepare("INSERT INTO materia (nro_orden, codigo, nombre, tipo, anio, cuatrimestre) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssis", $_POST['nro_orden'], $_POST['codigo'], $_POST['nombre'], $_POST['tipo'], $_POST['anio'], $_POST['cuatrimestre']);
        $stmt->execute();
        $stmt->close();
    } elseif ($accion === 'modificar_materia') {
        $stmt = $mysqli->prepare("UPDATE materia SET nro_orden=?, codigo=?, nombre=?, tipo=?, anio=?, cuatrimestre=? WHERE id=?");
        $stmt->bind_param("isssisi", $_POST['nro_orden'], $_POST['codigo'], $_POST['nombre'], $_POST['tipo'], $_POST['anio'], $_POST['cuatrimestre'], $_POST['edit_materia_id']);
        $stmt->execute();
        $stmt->close();
    } elseif ($accion === 'borrar_materia') {
        $stmt = $mysqli->prepare("DELETE FROM materia WHERE id = ?");
        $stmt->bind_param("i", $_POST['materia_id']);
        $stmt->execute();
        $stmt->close();
    }

    // Acciones para Correlatividades
    elseif ($accion === 'crear_correlatividad') {
        $stmt = $mysqli->prepare("INSERT INTO correlatividad (materia_id, materia_correlativa_id, tipo) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $_POST['materia_id'], $_POST['materia_correlativa_id'], $_POST['tipo']);
        $stmt->execute();
        $stmt->close();
    } elseif ($accion === 'modificar_correlatividad') {
        $stmt = $mysqli->prepare("UPDATE correlatividad SET materia_id = ?, materia_correlativa_id = ?, tipo = ? WHERE id = ?");
        $stmt->bind_param("iisi", $_POST['materia_id'], $_POST['materia_correlativa_id'], $_POST['tipo'], $_POST['edit_correlatividad_id']);
        $stmt->execute();
        $stmt->close();
    } elseif ($accion === 'eliminar_correlatividad') {
        $stmt = $mysqli->prepare("DELETE FROM correlatividad WHERE id = ?");
        $stmt->bind_param("i", $_POST['correlatividad_id']);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: materias.php"); // Redirigir para evitar reenvío de formulario
    exit;
}

// --- OBTENER DATOS DE LA BASE DE DATOS ---

// Obtener listado de materias para la tabla principal
$materias = $mysqli->query("SELECT * FROM materia ORDER BY anio, nro_orden");

// Obtener listado de materias para los menús desplegables de correlatividades
$materias_para_select = $mysqli->query("SELECT id, nombre FROM materia ORDER BY nombre");

// Obtener listado de correlatividades existentes
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

// Obtener nombre de usuario para el sidebar
$usuario_sidebar = ['nombre_completo' => 'Admin ISEF'];
$stmt_user_sidebar = $mysqli->prepare("
    SELECT CONCAT(p.apellidos ,' ', p.nombres) as nombre_completo 
    FROM persona p 
    JOIN usuario u ON p.usuario_id = u.id 
    WHERE u.id = ?
");
if ($stmt_user_sidebar) {
    $stmt_user_sidebar->bind_param("i", $_SESSION['usuario_id']);
    $stmt_user_sidebar->execute();
    $result_user = $stmt_user_sidebar->get_result();
    if ($result_user->num_rows > 0) {
        $usuario_sidebar = $result_user->fetch_assoc();
    }
    $stmt_user_sidebar->close();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Gestión de Materias y Correlatividades - ISEF</title>
    <link rel="icon" href="../sources/logo_recortado.ico" type="image/x-icon">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="../style/style.css">
</head>

<body class="materias">
    <div class="app-container">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/ISEF/views/includes/nav.php'; ?>
        <main class="main-content">
            <header class="header">
                <nav class="breadcrumb">
                    <a href="dashboard.php">Sistema de Gestión ISEF</a>
                    <span>/</span>
                    <span>Materias y Correlatividades</span>
                </nav>
            </header>
            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title" id="formMateriaTitulo">Nueva Materia</h2>
                        <p class="card-description">Complete los datos para agregar o modificar una materia.</p>
                    </div>
                    <div class="card-content">
                        <form id="formGestionMateria" method="post" autocomplete="off">
                            <input type="hidden" name="accion" value="crear_materia">
                            <input type="hidden" name="edit_materia_id" value="">
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group"><label for="nro_orden">N° Orden</label><input type="number" name="nro_orden" required></div>
                                    <div class="form-group"><label for="codigo">Código</label><input type="text" name="codigo" required></div>
                                    <div class="form-group"><label for="nombre">Nombre</label><input type="text" name="nombre" required></div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group"><label for="tipo">Tipo</label><select name="tipo" required>
                                            <option value="">Seleccione</option>
                                            <option value="Formación General">Formación General</option>
                                            <option value="Formación Específica">Formación Específica</option>
                                        </select></div>
                                    <div class="form-group"><label for="anio">Año</label><input type="number" name="anio" required></div>
                                    <div class="form-group"><label for="cuatrimestre">Cuatrimestre</label><select name="cuatrimestre" required>
                                            <option value="">Seleccione</option>
                                            <option value="Anual">Anual</option>
                                            <option value="Cuatrimestral">Cuatrimestral</option>
                                        </select></div>
                                </div>
                            </div>
                            <div style="margin-top: 10px;">
                                <button type="submit" class="save"><i data-lucide="plus"></i> <span id="submitButtonText">Crear Materia</span></button>
                                <button type="button" class="cancel" id="cancelarEdicionBtnMateria" style="display:none;" onclick="cancelarEdicionMateria()"><i data-lucide="x"></i> Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Listado de Materias</h2>
                    </div>
                    <div class="card-content">
                        <div class="table-container">
                            <table class="styled-table">
                                <thead>
                                    <tr>
                                        <th>N° Orden</th>
                                        <th>Código</th>
                                        <th>Nombre</th>
                                        <th>Tipo</th>
                                        <th>Año</th>
                                        <th>Cuatrimestre</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($materias && $materias->num_rows > 0) {
                                        $materias->data_seek(0);
                                        while ($m = $materias->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($m['nro_orden']) ?></td>
                                                <td><?= htmlspecialchars($m['codigo']) ?></td>
                                                <td><?= htmlspecialchars($m['nombre']) ?></td>
                                                <td><?= htmlspecialchars($m['tipo']) ?></td>
                                                <td><?= htmlspecialchars($m['anio']) ?></td>
                                                <td><?= htmlspecialchars($m['cuatrimestre']) ?></td>
                                                <td class="actions-cell">
                                                    <button type="button" class="edit" onclick="prepararEdicionMateria('<?= $m['id'] ?>', '<?= htmlspecialchars($m['nro_orden']) ?>', '<?= htmlspecialchars($m['codigo']) ?>', '<?= htmlspecialchars($m['nombre']) ?>', '<?= htmlspecialchars($m['tipo']) ?>', '<?= htmlspecialchars($m['anio']) ?>', '<?= htmlspecialchars($m['cuatrimestre']) ?>')"><i data-lucide="edit-2"></i> Editar</button>
                                                    <form method="post" style="display:inline;" onsubmit="return confirm('¿Está seguro de eliminar esta materia?');"><input type="hidden" name="accion" value="borrar_materia"><input type="hidden" name="materia_id" value="<?= $m['id'] ?>"><button type="submit" class="delete"><i data-lucide="trash-2"></i> Eliminar</button></form>
                                                </td>
                                            </tr>
                                        <?php endwhile;
                                    } else { ?>
                                        <tr>
                                            <td colspan="7" style="text-align:center;">No hay materias registradas.</td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <hr style="margin: 2rem 0; border: 1px solid #eee;">

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title" id="formCorrelatividadTitulo">Nueva Correlatividad</h2>
                        <p class="card-description">Establece las dependencias entre materias.</p>
                    </div>
                    <div class="card-content">
                        <form id="formGestionCorrelatividad" method="post">
                            <input type="hidden" name="accion" value="crear_correlatividad">
                            <input type="hidden" name="edit_correlatividad_id" value="">
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label>Materia (la que necesita el requisito)</label>
                                        <select name="materia_id" required>
                                            <option value="">-- Seleccione Materia --</option>
                                            <?php $materias_para_select->data_seek(0);
                                            while ($m = $materias_para_select->fetch_assoc()): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option><?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Materia Correlativa (el requisito)</label>
                                        <select name="materia_correlativa_id" required>
                                            <option value="">-- Seleccione Materia Correlativa --</option>
                                            <?php $materias_para_select->data_seek(0);
                                            while ($m = $materias_para_select->fetch_assoc()): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option><?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label>Tipo de Requisito</label>
                                        <select name="tipo" required>
                                            <option value="regular">Regular</option>
                                            <option value="aprobada">Aprobada</option>
                                            <option value="cursada">Cursada</option>
                                            <option value="Para cursar regularizada">Para cursar regularizada</option>
                                            <option value="Para cursar acreditada">Para cursar acreditada</option>
                                            <option value="Para acreditar">Para acreditar</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div style="margin-top: 10px;">
                                <button type="submit" class="save"><i data-lucide="plus"></i> <span>Crear Correlatividad</span></button>
                                <button type="button" class="cancel" id="cancelarEdicionBtnCorrelatividad" style="display:none;" onclick="cancelarEdicionCorrelatividad()"><i data-lucide="x"></i> Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Correlatividades Existentes</h2>
                    </div>
                    <div class="card-content">
                        <div class="table-container">
                            <table class="styled-table">
                                <thead>
                                    <tr>
                                        <th>Materia</th>
                                        <th>Es correlativa con</th>
                                        <th>Tipo de Requisito</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($correlatividades && $correlatividades->num_rows > 0) {
                                        while ($cor = $correlatividades->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($cor['materia_nombre']) ?></td>
                                                <td><?= htmlspecialchars($cor['materia_correlativa_nombre']) ?></td>
                                                <td><?= htmlspecialchars(ucfirst($cor['tipo'])) ?></td>
                                                <td class="actions-cell">
                                                    <button type="button" class="edit" onclick="prepararEdicionCorrelatividad(<?= $cor['correlatividad_id'] ?>, <?= $cor['materia_id_actual'] ?>, <?= $cor['materia_correlativa_id_actual'] ?>, '<?= htmlspecialchars($cor['tipo']) ?>')"><i data-lucide="edit-2"></i> Editar</button>
                                                    <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar esta correlatividad?')"><input type="hidden" name="accion" value="eliminar_correlatividad"><input type="hidden" name="correlatividad_id" value="<?= $cor['correlatividad_id'] ?>"><button type="submit" class="delete"><i data-lucide="trash-2"></i> Eliminar</button></form>
                                                </td>
                                            </tr>
                                        <?php endwhile;
                                    } else { ?>
                                        <tr>
                                            <td colspan="4" style="text-align:center;">No hay correlatividades registradas.</td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        lucide.createIcons();

        // --- SCRIPTS PARA GESTIÓN DE MATERIAS ---
        function prepararEdicionMateria(id, nroOrden, codigo, nombre, tipo, anio, cuatrimestre) {
            const form = document.getElementById('formGestionMateria');
            form.querySelector('input[name="accion"]').value = 'modificar_materia';
            form.querySelector('input[name="edit_materia_id"]').value = id;
            form.querySelector('input[name="nro_orden"]').value = nroOrden;
            form.querySelector('input[name="codigo"]').value = codigo;
            form.querySelector('input[name="nombre"]').value = nombre;
            form.querySelector('select[name="tipo"]').value = tipo;
            form.querySelector('input[name="anio"]').value = anio;
            form.querySelector('select[name="cuatrimestre"]').value = cuatrimestre;
            form.querySelector('button[type="submit"]').innerHTML = '<i data-lucide="save"></i> Guardar Cambios';
            document.getElementById('formMateriaTitulo').textContent = 'Modificar Materia';
            document.getElementById('cancelarEdicionBtnMateria').style.display = 'inline-block';
            lucide.createIcons();
            form.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        function cancelarEdicionMateria() {
            const form = document.getElementById('formGestionMateria');
            form.querySelector('input[name="accion"]').value = 'crear_materia';
            form.querySelector('input[name="edit_materia_id"]').value = '';
            form.reset();
            form.querySelector('button[type="submit"]').innerHTML = '<i data-lucide="plus"></i> Crear Materia';
            document.getElementById('formMateriaTitulo').textContent = 'Nueva Materia';
            document.getElementById('cancelarEdicionBtnMateria').style.display = 'none';
            lucide.createIcons();
        }

        // --- SCRIPTS PARA GESTIÓN DE CORRELATIVIDADES ---
        function prepararEdicionCorrelatividad(id, materiaId, materiaCorrelativaId, tipo) {
            const form = document.getElementById('formGestionCorrelatividad');
            form.querySelector('input[name="accion"]').value = 'modificar_correlatividad';
            form.querySelector('input[name="edit_correlatividad_id"]').value = id;
            form.querySelector('select[name="materia_id"]').value = materiaId;
            form.querySelector('select[name="materia_correlativa_id"]').value = materiaCorrelativaId;
            form.querySelector('select[name="tipo"]').value = tipo;
            form.querySelector('button[type="submit"] span').textContent = 'Guardar Cambios';
            const icon = form.querySelector('button[type="submit"] i');
            icon.setAttribute('data-lucide', 'save');

            document.getElementById('formCorrelatividadTitulo').textContent = 'Modificar Correlatividad';
            document.getElementById('cancelarEdicionBtnCorrelatividad').style.display = 'inline';
            lucide.createIcons();
            form.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        function cancelarEdicionCorrelatividad() {
            const form = document.getElementById('formGestionCorrelatividad');
            form.querySelector('input[name="accion"]').value = 'crear_correlatividad';
            form.querySelector('input[name="edit_correlatividad_id"]').value = '';
            form.reset();
            form.querySelector('button[type="submit"] span').textContent = 'Crear Correlatividad';
            const icon = form.querySelector('button[type="submit"] i');
            icon.setAttribute('data-lucide', 'plus');

            document.getElementById('formCorrelatividadTitulo').textContent = 'Nueva Correlatividad';
            document.getElementById('cancelarEdicionBtnCorrelatividad').style.display = 'none';
            lucide.createIcons();
        }
    </script>
</body>

</html>
<?php
if (isset($mysqli)) {
    $mysqli->close();
}
?>