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
    <style>
        :root {
            --orange-primary: #ff7f32;
            --orange-light: #ffb066;
            --orange-lighter: #ffd6b3;
            --white: #fff;
            --gray-bg: #f5f5f5;
            --gray-border: #eee;
            --gray-dark: #333;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: var(--gray-bg);
            color: var(--gray-dark);
        }
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 280px;
            background: var(--orange-primary);
            color: var(--white);
            display: flex;
            flex-direction: column;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            z-index: 10;
            box-shadow: 2px 0 8px rgba(0,0,0,0.04);
            border-right: 1px solid var(--orange-light);
            transition: all 0.3s;
        }
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: inherit;
        }
        .sidebar-brand img {
            width: 50px;
            height: 50px;
            border-radius: 8px;
        }
        .brand-text h1 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            color: var(--white);
        }
        .brand-text p {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.8);
            margin: 0;
        }
        .sidebar-nav {
            flex: 1;
            padding: 1rem 0.5rem 1rem 1rem;
        }
        .nav-section {
            margin-bottom: 2rem;
        }
        .nav-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: rgba(255,255,255,0.8);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
            padding: 0 0.75rem;
        }
        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .nav-item {
            margin-bottom: 0.25rem;
        }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: var(--white);
            font-weight: 500;
        }
        .nav-icon {
            width: 16px;
            height: 16px;
        }
        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: rgba(255,255,255,0.1);
            border-radius: 6px;
            margin-bottom: 0.5rem;
            transition: all 0.3s;
        }
        .user-info:hover {
            background: rgba(255,255,255,0.2);
        }
        .user-avatar {
            width: 32px;
            height: 32px;
            background: var(--white);
            color: var(--orange-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .user-details h3 {
            font-size: 0.875rem;
            font-weight: 500;
            margin: 0;
            color: var(--white);
        }
        .user-details p {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.8);
            margin: 0;
        }
        .logout-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            color: var(--white);
            border-radius: 6px;
            font-size: 0.875rem;
            border: none;
            background: rgba(255,255,255,0.1);
            width: 100%;
            cursor: pointer;
            transition: all 0.3s;
        }
        .logout-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 40px 30px;
            background: var(--gray-bg);
            min-height: 100vh;
            overflow-x: auto;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        .sidebar-toggle {
            background: none;
            border: none;
            color: var(--orange-primary);
            font-size: 1.5rem;
            cursor: pointer;
        }
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            color: #888;
        }
        .breadcrumb a {
            color: var(--orange-primary);
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .header-actions .icon-btn {
            background: none;
            border: none;
            color: var(--orange-primary);
            font-size: 1.2rem;
            cursor: pointer;
        }
        .content {
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .card-header {
            background: #f5f5f5;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .card-title {
            font-size: 1.25rem;
            margin: 0;
            color: #333;
        }
        .card-description {
            font-size: 0.95rem;
            color: #666;
            margin: 0;
        }
        .card-content {
            padding: 15px;
        }
        .form-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .form-col {
            flex: 1;
            min-width: 200px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--orange-primary);
        }
        input[type="text"], input[type="number"], select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            background-color: #fff;
        }
        button {
            padding: 10px 15px;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-right: 5px;
            font-weight: bold;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        button.save { background-color: var(--orange-primary); }
        button.edit { background-color: #ffa726; }
        button.delete { background-color: #ef5350; }
        button.cancel { background-color: #9e9e9e; }
        button:hover { opacity: 0.9; }
        .actions-cell {
            display: flex;
            gap: 6px;
            align-items: center;
            justify-content: center;
        }
        .actions-cell form { display: inline; }
        .styled-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
        }
        .styled-table th, .styled-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid var(--gray-border);
        }
        .styled-table th {
            background-color: #ffe0b2;
            color: #4e342e;
        }
        .styled-table tr:hover {
            background-color: #fff8e1;
        }
        @media (max-width: 900px) {
            .main-content { margin-left: 0; padding: 20px 5px; }
            .sidebar { position: fixed; left: -280px; transition: left 0.3s; }
            .sidebar.open { left: 0; }
        }
        @media (max-width: 600px) {
            .form-row { flex-direction: column; gap: 0; }
            .form-col { min-width: 100%; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <img src="../../ISEF/sources/logo.jpg" alt="No Logo">
                <div class="brand-text">
                    <h1>Sistema de Gestión ISEF</h1>
                    <p>Instituto Superior</p>
                </div>
            </a>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-label">Navegación Principal</div>
                <ul class="nav-menu">
                    <li class="nav-item"><a href="dashboard.php" class="nav-link"><i data-lucide="home" class="nav-icon"></i><span>Inicio</span></a></li>
                    <li class="nav-item"><a href="alumnos.php" class="nav-link"><i data-lucide="graduation-cap" class="nav-icon"></i><span>Alumnos</span></a></li>
                    <li class="nav-item"><a href="profesores.php" class="nav-link"><i data-lucide="briefcase" class="nav-icon"></i><span>Profesores</span></a></li>
                    <li class="nav-item"><a href="usuarios.php" class="nav-link"><i data-lucide="users" class="nav-icon"></i><span>Usuarios</span></a></li>
                    <li class="nav-item"><a href="materias.php" class="nav-link active"><i data-lucide="book-open" class="nav-icon"></i><span>Materias</span></a></li>
                    <li class="nav-item"><a href="cursos.php" class="nav-link"><i data-lucide="library" class="nav-icon"></i><span>Cursos</span></a></li>
                    <li class="nav-item"><a href="auditoria.php" class="nav-link"><i data-lucide="clipboard-list" class="nav-icon"></i><span>Auditoría</span></a></li>
                </ul>
            </div>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($usuario_sidebar['nombre_completo'] ?? 'A', 0, 1)) ?></div>
                <div class="user-details">
                    <h3><?= htmlspecialchars($usuario_sidebar['nombre_completo'] ?? 'Admin Usuario') ?></h3>
                    <p><?= htmlspecialchars($_SESSION['tipo']) ?>@isef.edu</p>
                </div>
            </div>
            <form method="post" action="../logout.php">
                <button type="submit" class="logout-btn">
                    <i data-lucide="log-out" class="nav-icon"></i>
                    <span>Cerrar Sesión</span>
                </button>
            </form>
        </div>
    </aside>
    <main class="main-content">
        <header class="header">
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <i data-lucide="menu"></i>
            </button>
            <nav class="breadcrumb">
                <a href="dashboard.php">Sistema de Gestión ISEF</a>
                <span>/</span>
                <span>Materias</span>
            </nav>
            <div class="header-actions">
                <button class="icon-btn" title="Notificaciones">
                    <i data-lucide="bell"></i>
                </button>
            </div>
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
                                    <?php while($m = $materias->fetch_assoc()): ?>
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
                                    <tr><td colspan="7" style="text-align:center;">No hay materias registradas.</td></tr>
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
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
<?php if ($mysqli) { $mysqli->close(); } ?>