<?php

// Verificar si el usuario está autenticado
$rol = $_SESSION['rol'] ?? 'invitado';

// Obtener el nombre del archivo de la página actual para marcar el enlace activo
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="../views/dashboard.php" class="sidebar-brand">
            <img src="../sources/logo_recortado.png" alt="No Logo" style="width: 50px; height: 50px; margin-bottom: 20px;">
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
                <li class="nav-item">
                    <a href="../views/dashboard.php" class="nav-link <?php if ($currentPage == 'dashboard.php') echo 'active'; ?>">
                        <i data-lucide="home" class="nav-icon"></i>
                        <span>Inicio</span>
                    </a>
                </li>

                <?php if ($_SESSION['tipo'] === 'administrador'): ?>
                    <li class="nav-item"><a href="../views/alumnos.php" class="nav-link <?php if ($currentPage == 'alumnos.php') echo 'active'; ?>"><i data-lucide="graduation-cap" class="nav-icon"></i><span>Alumnos</span></a></li>
                    <li class="nav-item"><a href="../views/profesores.php" class="nav-link <?php if ($currentPage == 'profesores.php') echo 'active'; ?>"><i data-lucide="briefcase" class="nav-icon"></i><span>Profesores</span></a></li>
                    <li class="nav-item"><a href="../views/usuarios.php" class="nav-link <?php if ($currentPage == 'usuarios.php') echo 'active'; ?>"><i data-lucide="users" class="nav-icon"></i><span>Usuarios</span></a></li>
                    <li class="nav-item"><a href="../views/materias.php" class="nav-link <?php if ($currentPage == 'materias.php') echo 'active'; ?>"><i data-lucide="book-open" class="nav-icon"></i><span>Materias</span></a></li>
                    <li class="nav-item"><a href="../views/cursos.php" class="nav-link <?php if ($currentPage == 'cursos.php') echo 'active'; ?>"><i data-lucide="library" class="nav-icon"></i><span>Cursos</span></a></li>
                    
                    <li class="nav-item">
                        <a href="../views/admin_periodos_inscripcion.php" class="nav-link <?php if ($currentPage == 'admin_periodos_inscripcion.php') echo 'active'; ?>">
                            <i data-lucide="calendar-plus" class="nav-icon"></i>
                            <span>Períodos de Inscripción</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../views/admin_periodos_examen.php" class="nav-link <?php if ($currentPage == 'admin_periodos_examen.php') echo 'active'; ?>">
                            <i data-lucide="calendar-check" class="nav-icon"></i>
                            <span>Períodos de Examen</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../views/admin_inscripcion_manual.php" class="nav-link <?php if ($currentPage == 'admin_inscripcion_manual.php') echo 'active'; ?>">
                            <i data-lucide="edit-3" class="nav-icon"></i>
                            <span>Inscripción Manual</span>
                        </a>
                    </li>

                    <li class="nav-item"><a href="../views/auditoria.php" class="nav-link <?php if ($currentPage == 'auditoria.php') echo 'active'; ?>"><i data-lucide="clipboard-list" class="nav-icon"></i><span>Auditoría</span></a></li>
                <?php endif; ?>

                <?php if ($_SESSION['tipo'] === 'profesor'): ?>
                    <li class="nav-item">
                        <a href="../views/asistencias.php" class="nav-link <?php if ($currentPage == 'asistencias.php') echo 'active'; ?>">
                            <i data-lucide="user-check" class="nav-icon"></i>
                            <span>Asistencias</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../views/evaluaciones.php" class="nav-link <?php if ($currentPage == 'evaluaciones.php') echo 'active'; ?>">
                            <i data-lucide="clipboard-check" class="nav-icon"></i>
                            <span>Evaluaciones</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($_SESSION['tipo'] === 'alumno'): ?>
                    <li class="nav-item">
                        <a href="../views/inscripciones_alumno_materia.php"
                            class="nav-link <?php if ($currentPage == 'inscripciones_alumno_materia.php') echo 'active'; ?>">
                            <i data-lucide="user-plus" class="nav-icon"></i>
                            <span>Inscripciones</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../views/historial.php"
                            class="nav-link <?php if ($currentPage == 'historial.php') echo 'active'; ?>">
                            <i data-lucide="bar-chart-3" class="nav-icon"></i>
                            <span>Situación Académica</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../views/desarrollo.php"
                            class="nav-link <?php if ($currentPage == 'desarrollo.php') echo 'active'; ?>">
                            <i data-lucide="file-text" class="nav-icon"></i>
                            <span>Certificados</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?= strtoupper(substr($usuario_sidebar['nombre_completo'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="user-details">
                <h3><?= htmlspecialchars($usuario_sidebar['nombre_completo'] ?? 'Admin Usuario') ?></h3>
                <p><?= htmlspecialchars($_SESSION['tipo']) ?>@isef.edu</p>
            </div>
        </div>
        <button onclick="confirmLogout()" class="logout-btn">
            <i data-lucide="log-out" class="nav-icon"></i>
            <span>Cerrar Sesión</span>
        </button>
    </div>
</aside>