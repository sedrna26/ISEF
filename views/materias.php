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
            $stmt = $mysqli->prepare("UPDATE materia SET nro_orden=?, codigo=?, nombre=?, tipo=?, anio=?, cuatrimestre=? WHERE id=?");
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

// Obtener nombre de usuario para el sidebar
$stmt_user_sidebar = $mysqli->prepare("
    SELECT CONCAT(p.apellidos ,' ', p.nombres) as nombre_completo 
    FROM persona p 
    JOIN usuario u ON p.usuario_id = u.id 
    WHERE u.id = ?
");
if ($stmt_user_sidebar) {
    $stmt_user_sidebar->bind_param("i", $_SESSION['usuario_id']);
    $stmt_user_sidebar->execute();
    $result_user_sidebar = $stmt_user_sidebar->get_result();
    $usuario_sidebar = $result_user_sidebar->fetch_assoc();
    $stmt_user_sidebar->close();
} else {
    $usuario_sidebar = ['nombre_completo' => 'Admin ISEF'];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Materias - ISEF</title>
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
                    <span>Materias</span>
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
                            <input type="hidden" name="accion" value="crear">
                            <input type="hidden" name="edit_materia_id" value="">
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="nro_orden">N° Orden</label>
                                        <input type="number" name="nro_orden" id="nro_orden" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="codigo">Código</label>
                                        <input type="text" name="codigo" id="codigo" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="nombre">Nombre</label>
                                        <input type="text" name="nombre" id="nombre" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="tipo">Tipo</label>
                                        <select name="tipo" id="tipo" required>
                                            <option value="">Seleccione</option>
                                            <option value="Troncal">Troncal</option>
                                            <option value="Especialidad">Especialidad</option>
                                            <option value="Electiva">Electiva</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="anio">Año</label>
                                        <input type="number" name="anio" id="anio" min="1" max="6" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="cuatrimestre">Cuatrimestre</label>
                                        <select name="cuatrimestre" id="cuatrimestre" required>
                                            <option value="">Seleccione</option>
                                            <option value="1">1°</option>
                                            <option value="2">2°</option>
                                        </select>
                                    </div>
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
                                    <?php if ($materias && $materias->num_rows > 0): ?>
                                        <?php while ($m = $materias->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($m['nro_orden']) ?></td>
                                                <td><?= htmlspecialchars($m['codigo']) ?></td>
                                                <td><?= htmlspecialchars($m['nombre']) ?></td>
                                                <td><?= htmlspecialchars($m['tipo']) ?></td>
                                                <td><?= htmlspecialchars($m['anio']) ?></td>
                                                <td><?= htmlspecialchars($m['cuatrimestre']) ?></td>
                                                <td class="actions-cell">
                                                    <button type="button" class="edit"
                                                        onclick="prepararEdicionMateria(
                                                        '<?= $m['id'] ?>',
                                                        '<?= htmlspecialchars($m['nro_orden']) ?>',
                                                        '<?= htmlspecialchars($m['codigo']) ?>',
                                                        '<?= htmlspecialchars($m['nombre']) ?>',
                                                        '<?= htmlspecialchars($m['tipo']) ?>',
                                                        '<?= htmlspecialchars($m['anio']) ?>',
                                                        '<?= htmlspecialchars($m['cuatrimestre']) ?>'
                                                    )">
                                                        <i data-lucide="edit-2"></i> Editar
                                                    </button>
                                                    <form method="post" style="display:inline;" onsubmit="return confirm('¿Está seguro de eliminar esta materia?');">
                                                        <input type="hidden" name="accion" value="borrar">
                                                        <input type="hidden" name="materia_id" value="<?= $m['id'] ?>">
                                                        <button type="submit" class="delete"><i data-lucide="trash-2"></i> Eliminar</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" style="text-align:center;">No hay materias registradas.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
        }

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
            form.querySelector('input[name="accion"]').value = 'crear';
            form.querySelector('input[name="edit_materia_id"]').value = '';
            form.reset();
            form.querySelector('button[type="submit"]').innerHTML = '<i data-lucide="plus"></i> Crear Materia';
            document.getElementById('formMateriaTitulo').textContent = 'Nueva Materia';
            document.getElementById('cancelarEdicionBtnMateria').style.display = 'none';
            lucide.createIcons();
        }
        lucide.createIcons();
    </script>
</body>

</html>
<?php if ($mysqli) {
    $mysqli->close();
} ?>