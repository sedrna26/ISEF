<?php
// dashboard.php - Menú principal tras login
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

// Incluir el archivo de conexión a la base de datos
require_once '../config/db.php';

// Usar la conexión desde db.php
// Verificar si el usuario debe cambiar contraseña
$stmt = $mysqli->prepare("SELECT debe_cambiar_password FROM usuario WHERE id = ?");
$stmt->bind_param("i", $_SESSION['usuario_id']);
$stmt->execute();
$result = $stmt->get_result();
$usuario_data = $result->fetch_assoc();
$stmt->close();

// Si debe cambiar contraseña, redirigir
if ($usuario_data['debe_cambiar_password']) {
    header("Location: cambiar_password.php");
    exit;
}

// Obtener el nombre del usuario
$stmt = $mysqli->prepare("
    SELECT CONCAT(p.apellidos ,' ', p.nombres) as nombre_completo 
    FROM persona p 
    JOIN usuario u ON p.usuario_id = u.id 
    WHERE u.id = ?
");
$stmt->bind_param("i", $_SESSION['usuario_id']);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();
$stmt->close();

// Cerrar la conexión
$mysqli->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Gestión ISEF</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
            color: #334155;
            line-height: 1.6;
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: inherit;
        }

        .brand-icon {
            width: 32px;
            height: 32px;
            background: #3b82f6;
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand-text h1 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
        }

        .brand-text p {
            font-size: 0.75rem;
            color: #64748b;
            margin: 0;
        }

        .sidebar-nav {
            flex: 1;
            padding: 1rem;
        }

        .nav-section {
            margin-bottom: 2rem;
        }

        .nav-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
            padding: 0 0.75rem;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            color: #64748b;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s;
            font-size: 0.875rem;
        }

        .nav-link:hover {
            background: #f1f5f9;
            color: #334155;
        }

        .nav-link.active {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .nav-icon {
            width: 16px;
            height: 16px;
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: #3b82f6;
            color: white;
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
        }

        .user-details p {
            font-size: 0.75rem;
            color: #64748b;
            margin: 0;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            color: #dc2626;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s;
            font-size: 0.875rem;
            border: none;
            background: none;
            width: 100%;
            cursor: pointer;
        }

        .logout-btn:hover {
            background: #fee2e2;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            padding: 0.5rem;
            cursor: pointer;
            border-radius: 4px;
        }

        .sidebar-toggle:hover {
            background: #f1f5f9;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #64748b;
        }

        .breadcrumb a {
            color: inherit;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color: #334155;
        }

        .content {
            flex: 1;
            padding: 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #64748b;
            font-size: 1rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .stat-info h3 {
            font-size: 0.875rem;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 0.25rem;
        }

        .stat-change {
            font-size: 0.75rem;
            color: #64748b;
        }

        .stat-icon {
            width: 16px;
            height: 16px;
            color: #64748b;
        }

        /* Menu Cards */
        .menu-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .menu-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
            display: block;
        }

        .menu-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .menu-card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .menu-icon-wrapper {
            width: 48px;
            height: 48px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .menu-icon {
            width: 24px;
            height: 24px;
            color: #3b82f6;
        }

        .menu-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .menu-description {
            color: #64748b;
            font-size: 0.875rem;
            margin: 0;
        }

        .menu-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .menu-count {
            font-size: 1.5rem;
            font-weight: 700;
            color: #3b82f6;
        }

        .menu-btn {
            background: none;
            border: 1px solid #e2e8f0;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
        }

        .menu-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        /* Activity Card */
        .activity-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
        }

        .activity-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .activity-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            background: rgba(248, 250, 252, 0.5);
            border-radius: 6px;
        }

        .activity-icon-wrapper {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .activity-icon {
            width: 16px;
            height: 16px;
        }

        .activity-icon.blue { color: #3b82f6; }
        .activity-icon.green { color: #10b981; }
        .activity-icon.purple { color: #8b5cf6; }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .activity-subtitle {
            font-size: 0.75rem;
            color: #64748b;
        }

        .activity-time {
            font-size: 0.75rem;
            color: #64748b;
        }

        .success-message {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -280px;
                transition: left 0.3s;
                z-index: 1000;
            }

            .sidebar.open {
                left: 0;
            }

            .sidebar-toggle {
                display: block;
            }

            .content {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .menu-grid {
                grid-template-columns: 1fr;
            }
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .overlay.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <!-- Header -->
            <div class="sidebar-header">
                <a href="/" class="sidebar-brand">
                    <div class="brand-icon">
                        <i data-lucide="school"></i>
                    </div>
                    <div class="brand-text">
                        <h1>Sistema de Gestión ISEF</h1>
                        <p>Instituto Superior</p>
                    </div>
                </a>
            </div>

            <!-- Navigation -->
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-label">Navegación Principal</div>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link active">
                                <i data-lucide="home" class="nav-icon"></i>
                                <span>Inicio</span>
                            </a>
                        </li>
                        
                        <?php if ($_SESSION['tipo'] === 'administrador'): ?>
                            <li class="nav-item"><a href="alumnos.php" class="nav-link"><i data-lucide="graduation-cap" class="nav-icon"></i><span>Alumnos</span></a></li>
                            <li class="nav-item"><a href="profesores.php" class="nav-link"><i data-lucide="briefcase" class="nav-icon"></i><span>Profesores</span></a></li>
                            <li class="nav-item"><a href="usuarios.php" class="nav-link"><i data-lucide="users" class="nav-icon"></i><span>Usuarios</span></a></li>
                            <li class="nav-item"><a href="materias.php" class="nav-link"><i data-lucide="book-open" class="nav-icon"></i><span>Materias</span></a></li>
                            <li class="nav-item"><a href="cursos.php" class="nav-link"><i data-lucide="library" class="nav-icon"></i><span>Cursos</span></a></li>
                            <li class="nav-item"><a href="auditoria.php" class="nav-link"><i data-lucide="clipboard-list" class="nav-icon"></i><span>Auditoría</span></a></li>
                        <?php endif; ?>

                        <?php if ($_SESSION['tipo'] === 'alumno'): ?>
                        <li class="nav-item">
                            <a href="inscripciones.php" class="nav-link">
                                <i data-lucide="user-plus" class="nav-icon"></i>
                                <span>Inscripciones</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="situacion.php" class="nav-link">
                                <i data-lucide="bar-chart-3" class="nav-icon"></i>
                                <span>Situación Académica</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="certificados.php" class="nav-link">
                                <i data-lucide="file-text" class="nav-icon"></i>
                                <span>Certificados</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>

            <!-- Footer -->
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?= strtoupper(substr($usuario['nombre_completo'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div class="user-details">
                        <h3><?= htmlspecialchars($usuario['nombre_completo'] ?? 'Admin Usuario') ?></h3>
                        <p><?= htmlspecialchars($_SESSION['tipo']) ?>@isef.edu</p>
                    </div>
                </div>
                <button onclick="confirmLogout()" class="logout-btn">
                    <i data-lucide="log-out" class="nav-icon"></i>
                    <span>Cerrar Sesión</span>
                </button>
            </div>
        </aside>

        <!-- Overlay for mobile -->
        <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i data-lucide="menu"></i>
                </button>
                <nav class="breadcrumb">
                    <a href="dashboard.php">Sistema de Gestión ISEF</a>
                    <span>/</span>
                    <span>Dashboard</span>
                </nav>
                <div style="margin-left: auto;">
                    <button style="background: none; border: 1px solid #e2e8f0; padding: 0.5rem; border-radius: 6px; cursor: pointer;">
                        <i data-lucide="bell" style="width: 16px; height: 16px;"></i>
                    </button>
                </div>
            </header>

            <!-- Content -->
            <div class="content">
                <?php if (isset($_SESSION['mensaje'])): ?>
                    <div class="success-message">
                        <?= htmlspecialchars($_SESSION['mensaje']) ?>
                    </div>
                    <?php unset($_SESSION['mensaje']); ?>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="page-header">
                    <h1 class="page-title">Dashboard</h1>
                    <p class="page-subtitle">Bienvenido al Sistema de Gestión ISEF</p>
                </div>

                <!-- Quick Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Total Estudiantes</h3>
                            <div class="stat-value">1,234</div>
                            <div class="stat-change">+12% desde el mes pasado</div>
                        </div>
                        <i data-lucide="graduation-cap" class="stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Profesores Activos</h3>
                            <div class="stat-value">89</div>
                            <div class="stat-change">+3% desde el mes pasado</div>
                        </div>
                        <i data-lucide="user-check" class="stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Materias Activas</h3>
                            <div class="stat-value">45</div>
                            <div class="stat-change">+5% desde el mes pasado</div>
                        </div>
                        <i data-lucide="book-open" class="stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Eventos Hoy</h3>
                            <div class="stat-value">8</div>
                            <div class="stat-change">+2 desde el mes pasado</div>
                        </div>
                        <i data-lucide="calendar" class="stat-icon"></i>
                    </div>
                </div>

                <!-- Main Sections -->
                <?php if ($_SESSION['tipo'] === 'administrador'): ?>
                <div class="menu-section">
                    <h2 class="section-title">Secciones Principales</h2>
                    <div class="menu-grid">
                        <a href="usuarios.php" class="menu-card">
                            <div class="menu-card-header">
                                <div class="menu-icon-wrapper">
                                    <i data-lucide="users" class="menu-icon"></i>
                                </div>
                                <div>
                                    <h3 class="menu-title">Gestión de Usuarios</h3>
                                    <p class="menu-description">Crear, editar y administrar usuarios del sistema</p>
                                </div>
                            </div>
                            <div class="menu-card-footer">
                                <div class="menu-count">156</div>
                                <button class="menu-btn">Ver más</button>
                            </div>
                        </a>

                        <a href="materias.php" class="menu-card">
                            <div class="menu-card-header">
                                <div class="menu-icon-wrapper">
                                    <i data-lucide="book-open" class="menu-icon"></i>
                                </div>
                                <div>
                                    <h3 class="menu-title">Gestión de Materias</h3>
                                    <p class="menu-description">Administrar materias y asignaturas</p>
                                </div>
                            </div>
                            <div class="menu-card-footer">
                                <div class="menu-count">45</div>
                                <button class="menu-btn">Ver más</button>
                            </div>
                        </a>

                        <a href="cursos.php" class="menu-card">
                            <div class="menu-card-header">
                                <div class="menu-icon-wrapper">
                                    <i data-lucide="graduation-cap" class="menu-icon"></i>
                                </div>
                                <div>
                                    <h3 class="menu-title">Gestión de Cursos</h3>
                                    <p class="menu-description">Crear y administrar cursos académicos</p>
                                </div>
                            </div>
                            <div class="menu-card-footer">
                                <div class="menu-count">24</div>
                                <button class="menu-btn">Ver más</button>
                            </div>
                        </a>

                        <a href="asignaciones.php" class="menu-card">
                            <div class="menu-card-header">
                                <div class="menu-icon-wrapper">
                                    <i data-lucide="user-cog" class="menu-icon"></i>
                                </div>
                                <div>
                                    <h3 class="menu-title">Asignaciones de Profesores</h3>
                                    <p class="menu-description">Asignar profesores a materias y cursos</p>
                                </div>
                            </div>
                            <div class="menu-card-footer">
                                <div class="menu-count">89</div>
                                <button class="menu-btn">Ver más</button>
                            </div>
                        </a>

                        <a href="correlatividades.php" class="menu-card">
                            <div class="menu-card-header">
                                <div class="menu-icon-wrapper">
                                    <i data-lucide="git-branch" class="menu-icon"></i>
                                </div>
                                <div>
                                    <h3 class="menu-title">Correlatividades</h3>
                                    <p class="menu-description">Gestionar correlatividades entre materias</p>
                                </div>
                            </div>
                            <div class="menu-card-footer">
                                <div class="menu-count">32</div>
                                <button class="menu-btn">Ver más</button>
                            </div>
                        </a>

                        <a href="auditoria.php" class="menu-card">
                            <div class="menu-card-header">
                                <div class="menu-icon-wrapper">
                                    <i data-lucide="clipboard-list" class="menu-icon"></i>
                                </div>
                                <div>
                                    <h3 class="menu-title">Auditoría</h3>
                                    <p class="menu-description">Revisar logs y actividad del sistema</p>
                                </div>
                            </div>
                            <div class="menu-card-footer">
                                <div class="menu-count">2,847</div>
                                <button class="menu-btn">Ver más</button>
                            </div>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($_SESSION['tipo'] === 'profesor' || $_SESSION['tipo'] === 'preceptor'): ?>
                <div class="menu-section">
                    <h2 class="section-title">Herramientas Docentes</h2>
                    <div class="menu-grid">
                        <a href="asistencias.php" class="menu-card">
                            <div class="menu-card-header">
                                <div class="menu-icon-wrapper">
                                    <i data-lucide="user-check" class="menu-icon"></i>
                                </div>
                                <div>
                                    <h3 class="menu-title">Registro de Asistencias</h3>
                                    <p class="menu-description">Registrar y consultar asistencias de estudiantes</p>
                                </div>
                            </div>
                            <div class="menu-card-footer">
                                <div class="menu-count">1,234</div>
                                <button class="menu-btn">Ver más</button>
                            </div>
                        </a>

                        <a href="evaluaciones.php" class="menu-card">
                            <div class="menu-card-header">
                                <div class="menu-icon-wrapper">
                                    <i data-lucide="clipboard-check" class="menu-icon"></i>
                                </div>
                                <div>
                                    <h3 class="menu-title">Evaluaciones</h3>
                                    <p class="menu-description">Gestionar evaluaciones y calificaciones</p>
                                </div>
                            </div>
                            <div class="menu-card-footer">
                                <div class="menu-count">156</div>
                                <button class="menu-btn">Ver más</button>
                            </div>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($_SESSION['tipo'] === 'alumno'): ?>
                <div class="menu-section">
                    <h2 class="section-title">Portal del Estudiante</h2>
                    <div class="menu-grid">
                        <a href="inscripciones.php" class="menu-card">
                            <div class="menu-card-header">
                                <div class="menu-icon-wrapper">
                                    <i data-lucide="user-plus" class="menu-icon"></i>
                                </div>
                                <div>
                                    <h3 class="menu-title">Inscripciones</h3>
                                    <p class="menu-description">Inscribirse a materias y cursos</p>
                                </div>
                            </div>
                            <div class="menu-card-footer">
                                <div class="menu-count">12</div>
                                <button class="menu-btn">Ver más</button>
                            </div>
                        </a>

                        <a href="situacion.php" class="menu-card">
                            <div class="menu-card-header">
                                <div class="menu-icon-wrapper">
                                    <i data-lucide="bar-chart-3" class="menu-icon"></i>
                                </div>
                                <div>
                                    <h3 class="menu-title">Situación Académica</h3>
                                    <p class="menu-description">Consultar estado académico y calificaciones</p>
                                </div>
                            </div>
                            <div class="menu-card-footer">
                                <div class="menu-count">8</div>
                                <button class="menu-btn">Ver más</button>
                            </div>
                        </a>

                        <a href="certificados.php" class="menu-card">
                            <div class="menu-card-header">
                                <div class="menu-icon-wrapper">
                                    <i data-lucide="file-text" class="menu-icon"></i>
                                </div>
                                <div>
                                    <h3 class="menu-title">Certificados</h3>
                                    <p class="menu-description">Solicitar y descargar certificados</p>
                                </div>
                            </div>
                            <div class="menu-card-footer">
                                <div class="menu-count">3</div>
                                <button class="menu-btn">Ver más</button>
                            </div>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Activity -->
                <div class="activity-card">
                    <div class="activity-header">
                        <i data-lucide="bar-chart-3" style="width: 20px; height: 20px;"></i>
                        <h2>Actividad Reciente</h2>
                    </div>
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-icon-wrapper">
                                <i data-lucide="graduation-cap" class="activity-icon blue"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">Nuevo alumno registrado</div>
                                <div class="activity-subtitle">Juan Pérez - 2do Año</div>
                            </div>
                            <div class="activity-time">Hace 5 min</div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon-wrapper">
                                <i data-lucide="user-check" class="activity-icon green"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">Profesor asignado a materia</div>
                                <div class="activity-subtitle">Prof. García - Matemáticas</div>
                            </div>
                            <div class="activity-time">Hace 15 min</div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon-wrapper">
                                <i data-lucide="book-open" class="activity-icon purple"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">Nueva materia creada</div>
                                <div class="activity-subtitle">Educación Física Avanzada</div>
                            </div>
                            <div class="activity-time">Hace 1 hora</div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        }

        // Logout confirmation
        function confirmLogout() {
            if (confirm('¿Estás seguro que deseas cerrar sesión?\n\nSe cerrará tu sesión actual y serás redirigido a la página de inicio de sesión.')) {
                window.location.href = '../index.php?logout=1';
            }
        }

        // Close sidebar when clicking on nav links (mobile)
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            });
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });

        // Add click handlers for menu cards
        document.querySelectorAll('.menu-card').forEach(card => {
            card.addEventListener('click', (e) => {
                // Prevent navigation if clicking the button
                if (e.target.classList.contains('menu-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    // Handle button click if needed
                    console.log('Button clicked for:', card.href);
                }
            });
        });

        // Add hover effect for interactive elements
        document.querySelectorAll('.menu-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                // Get the parent link href
                const parentCard = btn.closest('.menu-card');
                if (parentCard && parentCard.href) {
                    window.location.href = parentCard.href;
                }
            });
        });
    </script>
</body>
</html>