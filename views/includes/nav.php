<?php


// Verificar si el usuario está autenticado
$rol = $_SESSION['rol'] ?? 'invitado';
?>

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
                    <a href="../views/dashboard.php" class="nav-link active">
                        <i data-lucide="home" class="nav-icon"></i>
                        <span>Inicio</span>
                    </a>
                </li>

                <?php if ($_SESSION['tipo'] === 'administrador'): ?>
                    <li class="nav-item"><a href="../views/alumnos.php" class="nav-link"><i data-lucide="graduation-cap" class="nav-icon"></i><span>Alumnos</span></a></li>
                    <li class="nav-item"><a href="../views/profesores.php" class="nav-link"><i data-lucide="briefcase" class="nav-icon"></i><span>Profesores</span></a></li>
                    <!-- <li class="nav-item"><a href="../views/inscripciones_alumno_materia.php" class="nav-link"><i data-lucide="user-plus" class="nav-icon"></i><span>Inscripciones</span></a></li> -->
                    <li class="nav-item"><a href="../views/usuarios.php" class="nav-link"><i data-lucide="users" class="nav-icon"></i><span>Usuarios</span></a></li>
                    <li class="nav-item"><a href="../views/materias.php" class="nav-link"><i data-lucide="book-open" class="nav-icon"></i><span>Materias</span></a></li>
                    <li class="nav-item"><a href="../views/cursos.php" class="nav-link"><i data-lucide="library" class="nav-icon"></i><span>Cursos</span></a></li>
                    <li class="nav-item"><a href="../views/auditoria.php" class="nav-link"><i data-lucide="clipboard-list" class="nav-icon"></i><span>Auditoría</span></a></li>
                <?php endif; ?>

                <?php if ($_SESSION['tipo'] === 'profesor'): ?>
                    <li class="nav-item">
                        <a href="../views/asistencias.php" class="nav-link">
                            <i data-lucide="user-check" class="nav-icon"></i>
                            <span>Asistencias</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../views/evaluaciones.php" class="nav-link">
                            <i data-lucide="clipboard-check" class="nav-icon"></i>
                            <span>Evaluaciones</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($_SESSION['tipo'] === 'alumno'): ?>
                    <li class="nav-item">
                        <a href="../views/inscripciones_alumno_materia.php" class="nav-link">
                            <i data-lucide="user-plus" class="nav-icon"></i>
                            <span>Inscripciones</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../views/desarrollo.php" class="nav-link">
                            <i data-lucide="bar-chart-3" class="nav-icon"></i>
                            <span>Situación Académica</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../views/desarrollo.php" class="nav-link">
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