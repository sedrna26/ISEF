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
    <link rel="stylesheet" href="../style/style.css">
    <style>
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
    </style>
</head>

<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <!-- Header -->
            <div class="sidebar-header">
                <a href="../views/dashboard.php" class="sidebar-brand">
                    <img src="../sources/logo_recortado.png" alt="No Logo" style="width: 50px; height: 50px; margin-bottom: 20px;">
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
                            <!-- <li class="nav-item"><a href="inscripciones_alumno_materia.php" class="nav-link"><i data-lucide="user-plus" class="nav-icon"></i><span>Inscripciones</span></a></li> -->
                            <li class="nav-item"><a href="usuarios.php" class="nav-link"><i data-lucide="users" class="nav-icon"></i><span>Usuarios</span></a></li>
                            <li class="nav-item"><a href="materias.php" class="nav-link"><i data-lucide="book-open" class="nav-icon"></i><span>Materias</span></a></li>
                            <li class="nav-item"><a href="cursos.php" class="nav-link"><i data-lucide="library" class="nav-icon"></i><span>Cursos</span></a></li>
                            <li class="nav-item"><a href="auditoria.php" class="nav-link"><i data-lucide="clipboard-list" class="nav-icon"></i><span>Auditoría</span></a></li>
                        <?php endif; ?>

                        <?php if ($_SESSION['tipo'] === 'profesor'): ?>
                            <li class="nav-item">
                                <a href="asistencias.php" class="nav-link">
                                    <i data-lucide="user-check" class="nav-icon"></i>
                                    <span>Asistencias</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="evaluaciones.php" class="nav-link">
                                    <i data-lucide="clipboard-check" class="nav-icon"></i>
                                    <span>Evaluaciones</span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if ($_SESSION['tipo'] === 'alumno'): ?>
                            <li class="nav-item">
                                <a href="inscripciones_alumno_materia.php" class="nav-link">
                                    <i data-lucide="user-plus" class="nav-icon"></i>
                                    <span>Inscripciones</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="desarrollo.php" class="nav-link">
                                    <i data-lucide="bar-chart-3" class="nav-icon"></i>
                                    <span>Situación Académica</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="desarrollo.php" class="nav-link">
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

            </header>

            <!-- Content -->
            <div class="content">
                <?php if (isset($_SESSION['mensaje'])): ?>
                    <div class="success-message">
                        <?= htmlspecialchars($_SESSION['mensaje']) ?>
                    </div>
                    <?php unset($_SESSION['mensaje']); ?>
                <?php endif; ?>



                <!-- Quick Stats -->

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
                                    <!-- <div class="menu-count">156</div> -->
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
                                    <!-- <div class="menu-count">45</div> -->
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
                                    <!-- <div class="menu-count">24</div> -->
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
                                    <!-- <div class="menu-count">89</div> -->
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
                                    <!-- <div class="menu-count">32</div> -->
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
                                    <!-- <div class="menu-count">2,847</div> -->
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
                                    <!-- <div class="menu-count">1,234</div> -->
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
                                    <!-- <div class="menu-count">156</div> -->
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
                            <a href="inscripciones_alumno_materia.php" class="menu-card">
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
                                    <!-- <div class="menu-count">12</div> -->
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
                                    <!-- <div class="menu-count">8</div> -->
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
                                    <!-- <div class="menu-count">3</div> -->
                                    <button class="menu-btn">Ver más</button>
                                </div>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Recent Activity -->
                <!-- <div class="activity-card">
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
                </div> -->
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