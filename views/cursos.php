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
    <title>Cursos - ISEF</title>
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
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.04);
            border-right: 1px solid var(--orange-light);
            transition: all 0.3s;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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
            color: rgba(255, 255, 255, 0.8);
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
            color: rgba(255, 255, 255, 0.8);
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
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 0.95rem;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: var(--white);
            font-weight: 500;
        }

        .nav-icon {
            width: 16px;
            height: 16px;
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            margin-bottom: 0.5rem;
            transition: all 0.3s;
        }

        .user-info:hover {
            background: rgba(255, 255, 255, 0.2);
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
            color: rgba(255, 255, 255, 0.8);
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
            background: rgba(255, 255, 255, 0.1);
            width: 100%;
            cursor: pointer;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
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
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
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

        input[type="text"],
        input[type="number"],
        select {
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

        button.save {
            background-color: var(--orange-primary);
        }

        button.edit {
            background-color: #ffa726;
        }

        button.delete {
            background-color: #ef5350;
        }

        button.cancel {
            background-color: #9e9e9e;
        }

        button:hover {
            opacity: 0.9;
        }

        .actions-cell {
            display: flex;
            gap: 6px;
            align-items: center;
            justify-content: center;
        }

        .actions-cell form {
            display: inline;
        }

        .styled-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
        }

        .styled-table th,
        .styled-table td {
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
            .main-content {
                margin-left: 0;
                padding: 20px 5px;
            }

            .sidebar {
                position: fixed;
                left: -280px;
                transition: left 0.3s;
            }

            .sidebar.open {
                left: 0;
            }
        }

        @media (max-width: 600px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .form-col {
                min-width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="sidebar-brand">
                    <img src="../sources/logo_recortado.png" alt="No Logo">
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
                        <li class="nav-item"><a href="materias.php" class="nav-link"><i data-lucide="book-open" class="nav-icon"></i><span>Materias</span></a></li>
                        <li class="nav-item"><a href="cursos.php" class="nav-link active"><i data-lucide="library" class="nav-icon"></i><span>Cursos</span></a></li>
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

                <nav class="breadcrumb">
                    <a href="dashboard.php">Sistema de Gestión ISEF</a>
                    <span>/</span>
                    <span>Cursos</span>
                </nav>

            </header>
            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title" id="formCursoTitulo">Nuevo Curso</h2>
                        <p class="card-description">Complete los datos para agregar o modificar un curso.</p>
                    </div>
                    <div class="card-content">
                        <form method="post" id="formGestionCurso" autocomplete="off">
                            <input type="hidden" name="accion" value="crear">
                            <input type="hidden" name="edit_curso_id" id="edit_curso_id" value="">
                            <div class="form-row">
                                <div class="form-col">
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
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="turno">Turno:</label>
                                        <select id="turno" name="turno" required>
                                            <option value="Mañana">Mañana</option>
                                            <option value="Tarde">Tarde</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="ciclo_lectivo">Ciclo Lectivo:</label>
                                        <input type="number" id="ciclo_lectivo" name="ciclo_lectivo" required value="<?php echo date('Y'); ?>">
                                    </div>
                                </div>
                            </div>
                            <div style="margin-top: 10px;">
                                <button type="submit" class="save"><i data-lucide="plus"></i> <span id="submitButtonText">Crear Curso</span></button>
                                <button type="button" class="cancel" id="cancelarEdicionBtnCurso" onclick="cancelarEdicionCurso()" style="display:none;"><i data-lucide="x"></i> Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Listado de Cursos</h2>
                    </div>
                    <div class="card-content">
                        <div class="table-container">
                            <table class="styled-table">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>División</th>
                                        <th>Año</th>
                                        <th>Turno</th>
                                        <th>Ciclo Lectivo</th>
                                        <th>Acciones</th>
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
                                            <td class="actions-cell">
                                                <button type="button" class="edit" onclick="prepararEdicionCurso(
                                                <?= $c['id'] ?>,
                                                '<?= htmlspecialchars(addslashes($c['codigo'])) ?>',
                                                '<?= htmlspecialchars(addslashes($c['division'])) ?>',
                                                '<?= htmlspecialchars(addslashes($c['anio'])) ?>',
                                                '<?= $c['turno'] ?>',
                                                <?= $c['ciclo_lectivo'] ?>
                                            )"><i data-lucide="edit-2"></i> Editar</button>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar este curso?')">
                                                    <input type="hidden" name="accion" value="borrar">
                                                    <input type="hidden" name="curso_id" value="<?= $c['id'] ?>">
                                                    <button type="submit" class="delete"><i data-lucide="trash-2"></i> Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
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

        function prepararEdicionCurso(id, codigo, division, anio, turno, cicloLectivo) {
            const form = document.getElementById('formGestionCurso');
            form.querySelector('input[name="accion"]').value = 'modificar';
            form.querySelector('input[name="edit_curso_id"]').value = id;
            form.querySelector('input[name="codigo"]').value = codigo;
            form.querySelector('input[name="division"]').value = division;
            form.querySelector('input[name="anio"]').value = anio;
            form.querySelector('select[name="turno"]').value = turno;
            form.querySelector('input[name="ciclo_lectivo"]').value = cicloLectivo;
            form.querySelector('button[type="submit"]').innerHTML = '<i data-lucide="save"></i> Guardar Cambios';
            document.getElementById('formCursoTitulo').textContent = 'Modificar Curso';
            document.getElementById('cancelarEdicionBtnCurso').style.display = 'inline-block';
            lucide.createIcons();
            form.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        function cancelarEdicionCurso() {
            const form = document.getElementById('formGestionCurso');
            form.querySelector('input[name="accion"]').value = 'crear';
            form.querySelector('input[name="edit_curso_id"]').value = '';
            form.reset();
            form.querySelector('input[name="ciclo_lectivo"]').value = new Date().getFullYear();
            form.querySelector('button[type="submit"]').innerHTML = '<i data-lucide="plus"></i> Crear Curso';
            document.getElementById('formCursoTitulo').textContent = 'Nuevo Curso';
            document.getElementById('cancelarEdicionBtnCurso').style.display = 'none';
            lucide.createIcons();
        }
        document.addEventListener('DOMContentLoaded', function() {
            const cicloLectivoInput = document.querySelector('#formGestionCurso input[name="ciclo_lectivo"]');
            if (!cicloLectivoInput.value || document.getElementById('formGestionCurso').querySelector('input[name="accion"]').value === 'crear') {
                const editCursoId = document.getElementById('formGestionCurso').querySelector('input[name="edit_curso_id"]').value;
                if (!editCursoId) {
                    cicloLectivoInput.value = new Date().getFullYear();
                }
            }
            lucide.createIcons();
        });
    </script>
</body>

</html>
<?php if ($mysqli) {
    $mysqli->close();
} ?>