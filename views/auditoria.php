<?php
// auditoria.php - Registro de auditorías
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: ../index.php");
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

// Obtener las auditorías más recientes
$audit_query = "
    SELECT a.id, a.fecha_hora, u.username AS usuario, a.tipo_operacion, a.tabla_afectada,
           a.registro_afectado, a.valor_anterior, a.valor_nuevo, a.ip_origen
    FROM auditoria a
    LEFT JOIN usuario u ON a.usuario_id = u.id
    ORDER BY a.fecha_hora DESC
    LIMIT 100
";
$audit_result = $mysqli->query($audit_query);

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
    <title>Auditoría - ISEF</title>
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
        .table-container {
            max-height: 600px;
            overflow-y: auto;
        }
        .styled-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
        }
        .styled-table th, .styled-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid var(--gray-border);
            vertical-align: top;
        }
        .styled-table th {
            background-color: #ffe0b2;
            color: #4e342e;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .styled-table tr:hover {
            background-color: #fff8e1;
        }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 0.95em;
            margin: 0;
        }
        @media (max-width: 900px) {
            .main-content { margin-left: 0; padding: 20px 5px; }
            .sidebar { position: fixed; left: -280px; transition: left 0.3s; }
            .sidebar.open { left: 0; }
        }
        @media (max-width: 600px) {
            .styled-table th, .styled-table td { font-size: 0.85em; }
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
                    <li class="nav-item"><a href="materias.php" class="nav-link"><i data-lucide="book-open" class="nav-icon"></i><span>Materias</span></a></li>
                    <li class="nav-item"><a href="cursos.php" class="nav-link"><i data-lucide="library" class="nav-icon"></i><span>Cursos</span></a></li>
                    <li class="nav-item"><a href="auditoria.php" class="nav-link active"><i data-lucide="clipboard-list" class="nav-icon"></i><span>Auditoría</span></a></li>
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
            <form method="post" action="logout.php">
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
                <span>Auditoría</span>
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
                    <h2 class="card-title">Registro de Auditoría</h2>
                    <p class="card-description">Últimas 100 operaciones realizadas en el sistema.</p>
                </div>
                <div class="card-content">
                    <div class="table-container">
                        <table class="styled-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha y Hora</th>
                                    <th>Usuario</th>
                                    <th>Operación</th>
                                    <th>Tabla</th>
                                    <th>Registro</th>
                                    <th>Valor Anterior</th>
                                    <th>Valor Nuevo</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $audit_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['id']) ?></td>
                                        <td><?= htmlspecialchars($row['fecha_hora']) ?></td>
                                        <td><?= htmlspecialchars($row['usuario'] ?? 'Sistema') ?></td>
                                        <td><?= htmlspecialchars($row['tipo_operacion']) ?></td>
                                        <td><?= htmlspecialchars($row['tabla_afectada']) ?></td>
                                        <td><?= htmlspecialchars($row['registro_afectado']) ?></td>
                                        <td><pre><?= htmlspecialchars($row['valor_anterior']) ?></pre></td>
                                        <td><pre><?= htmlspecialchars($row['valor_nuevo']) ?></pre></td>
                                        <td><?= htmlspecialchars($row['ip_origen']) ?></td>
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
lucide.createIcons();
</script>
</body>
</html>
<?php if ($mysqli) { $mysqli->close(); } ?>