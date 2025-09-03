<?php
// 1. CONEXIÓN A LA BASE DE DATOS Y CONFIGURACIÓN
include '../config/db.php';
include '../tools/funciones_inscripcion.php'; // Para obtener_cursos_disponibles y verificar_requisitos_materia_alumno

// session_start();

if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

// 2. VERIFICACIÓN DE USUARIO ADMINISTRADOR
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: ../index.php");
    exit;
}

// 3. Obtener el nombre del usuario para el sidebar
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
    $usuario_sidebar = ['nombre_completo' => 'Admin ISEF']; // Fallback
}

$mensaje_exito = '';
$mensaje_error = '';
$alumno_encontrado = null;
$search_term = '';
$materias = [];
$cursos_disponibles_form = [];

if (isset($_SESSION['mensaje_exito'])) {
    $mensaje_exito = $_SESSION['mensaje_exito'];
    unset($_SESSION['mensaje_exito']);
}
if (isset($_SESSION['mensaje_error'])) {
    $mensaje_error = $_SESSION['mensaje_error'];
    unset($_SESSION['mensaje_error']);
}

// Obtener lista de materias para el dropdown
$result_materias = $mysqli->query("SELECT id, nombre, anio, cuatrimestre FROM materia ORDER BY anio, nombre");
if ($result_materias) {
    while ($row = $result_materias->fetch_assoc()) {
        $materias[] = $row;
    }
    $result_materias->free();
}

// 3. LÓGICA PARA BUSCAR ALUMNO (GET o POST)
if (isset($_REQUEST['accion']) && $_REQUEST['accion'] === 'buscar_alumno') {
    $search_term = isset($_REQUEST['search_term']) ? trim(htmlspecialchars($_REQUEST['search_term'])) : '';
    if (!empty($search_term)) {
        // Busca por legajo, DNI, o parcialmente por apellido/nombre
        $stmt_search = $mysqli->prepare(
            "SELECT a.id AS alumno_id, p.nombres, p.apellidos, p.dni, a.legajo
             FROM alumno a
             JOIN persona p ON a.persona_id = p.id
             WHERE a.legajo = ? OR p.dni = ? OR p.apellidos LIKE ? OR p.nombres LIKE ?"
        );
        $search_like = "%" . $search_term . "%";
        $stmt_search->bind_param("ssss", $search_term, $search_term, $search_like, $search_like);
        $stmt_search->execute();
        $result_search = $stmt_search->get_result();
        if ($result_search->num_rows === 1) {
            $alumno_encontrado = $result_search->fetch_assoc();
        } elseif ($result_search->num_rows > 1) {
            $mensaje_error = "Múltiples alumnos encontrados. Por favor, sea más específico.";
            // Aquí podrías listar los alumnos encontrados para que el admin elija.
        } else {
            $mensaje_error = "Alumno no encontrado con el término '{$search_term}'.";
        }
        $stmt_search->close();
    } else {
        $mensaje_error = "Por favor, ingrese un término de búsqueda.";
    }
}

// Cargar cursos si se selecciona materia y ciclo lectivo (para AJAX o recarga de página)
$selected_materia_id_form = filter_input(INPUT_POST, 'materia_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'materia_id', FILTER_VALIDATE_INT);
$selected_ciclo_lectivo_form = filter_input(INPUT_POST, 'ciclo_lectivo', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'ciclo_lectivo', FILTER_VALIDATE_INT) ?: date('Y');
$selected_alumno_id_form = filter_input(INPUT_POST, 'alumno_id_hidden', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'alumno_id_hidden', FILTER_VALIDATE_INT);

if ($selected_materia_id_form && $selected_ciclo_lectivo_form && function_exists('obtener_cursos_disponibles')) {
    $cursos_disponibles_form = obtener_cursos_disponibles($mysqli, $selected_materia_id_form, $selected_ciclo_lectivo_form);
}
// Si se recarga la página para mostrar cursos, re-establecer $alumno_encontrado si había uno
if (!$alumno_encontrado && $selected_alumno_id_form) {
    $stmt_recheck_alumno = $mysqli->prepare("SELECT a.id AS alumno_id, p.nombres, p.apellidos, p.dni, a.legajo FROM alumno a JOIN persona p ON a.persona_id = p.id WHERE a.id = ?");
    $stmt_recheck_alumno->bind_param("i", $selected_alumno_id_form);
    $stmt_recheck_alumno->execute();
    $result_recheck = $stmt_recheck_alumno->get_result();
    if ($result_recheck->num_rows === 1) $alumno_encontrado = $result_recheck->fetch_assoc();
    $stmt_recheck_alumno->close();
}

// 4. LÓGICA PARA INSCRIBIR ALUMNO (POST)
if (isset($_POST['accion']) && $_POST['accion'] === 'inscribir_alumno') {
    $alumno_id = filter_input(INPUT_POST, 'alumno_id_hidden', FILTER_VALIDATE_INT);
    $materia_id = filter_input(INPUT_POST, 'materia_id', FILTER_VALIDATE_INT);
    $curso_id = filter_input(INPUT_POST, 'curso_id', FILTER_VALIDATE_INT);
    $ciclo_lectivo_insc = filter_input(INPUT_POST, 'ciclo_lectivo', FILTER_VALIDATE_INT);
    $estado_inscripcion = filter_input(INPUT_POST, 'estado_inscripcion', FILTER_SANITIZE_STRING);
    $fecha_inscripcion = date("Y-m-d");

    if ($alumno_id && $materia_id && $curso_id && $ciclo_lectivo_insc && $estado_inscripcion) {
        // Verificar si ya está inscripto
        if (function_exists('alumno_ya_inscripto') && alumno_ya_inscripto($mysqli, $alumno_id, $materia_id, $ciclo_lectivo_insc)) {
            $_SESSION['mensaje_error'] = "El alumno ya está inscripto en esta materia para el ciclo lectivo {$ciclo_lectivo_insc}.";
        } else {
            $stmt_insert = $mysqli->prepare("INSERT INTO inscripcion_cursado (alumno_id, materia_id, curso_id, ciclo_lectivo, fecha_inscripcion, estado) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("iiiiss", $alumno_id, $materia_id, $curso_id, $ciclo_lectivo_insc, $fecha_inscripcion, $estado_inscripcion);

            if ($stmt_insert->execute()) {
                $_SESSION['mensaje_exito'] = "Alumno inscripto correctamente en la materia.";
                // Limpiar alumno_encontrado para permitir nueva búsqueda
                $alumno_encontrado = null;
                $search_term = '';
            } else {
                $_SESSION['mensaje_error'] = "Error al inscribir al alumno: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
    } else {
        $_SESSION['mensaje_error'] = "Faltan datos para la inscripción. Asegúrese de seleccionar alumno, materia, curso, ciclo lectivo y estado.";
    }
    // Redirigir para mostrar el mensaje y limpiar el POST
    header("Location: admin_inscripcion_manual.php" . ($alumno_encontrado ? "?accion=buscar_alumno&search_term=" . urlencode($search_term) : ""));
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscripción Manual de Alumnos - Sistema ISEF</title>
    <link rel="icon" href="../sources/logo_recortado.ico" type="image/x-icon">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="../style/style.css">
</head>

<body>
    <div class="app-container">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/ISEF/views/includes/nav.php'; ?>
        <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

        <main class="main-content">
            <header class="header">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i data-lucide="menu"></i>
                </button>
                <nav class="breadcrumb">
                    <a href="dashboard.php">Sistema de Gestión ISEF</a>
                    <span>/</span>
                    <span>Inscripción Manual</span>
                </nav>
            </header>

            <div class="content">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Inscripción Manual de Alumnos</h1>
                        <p class="page-subtitle">Inscribe alumnos manualmente en materias y cursos específicos.</p>
                    </div>

                </div>

                <?php if ($mensaje_exito): ?>
                    <div class="message-toast success" role="alert"><?= htmlspecialchars($mensaje_exito) ?></div>
                <?php endif; ?>
                <?php if ($mensaje_error): ?>
                    <div class="message-toast error" role="alert"><?= htmlspecialchars($mensaje_error) ?></div>
                <?php endif; ?>

                <!-- Formulario de búsqueda de alumno -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">1. Buscar Alumno</h2>
                        <p class="card-description">Busca al alumno que deseas inscribir por legajo, DNI, apellido o nombre.</p>
                    </div>
                    <div class="card-content">
                        <form action="admin_inscripcion_manual.php" method="GET" id="searchForm">
                            <input type="hidden" name="accion" value="buscar_alumno">
                            <div class="form-group">
                                <label for="search_term">Buscar por Legajo, DNI, Apellido o Nombre:</label>
                                <div style="display: flex; gap: 0.75rem; align-items: end; position: relative;">
                                    <div style="flex: 1;">
                                        <input type="text"
                                            id="search_term"
                                            name="search_term"
                                            value="<?= htmlspecialchars($search_term) ?>"
                                            required
                                            placeholder="Ingrese término de búsqueda..."
                                            autocomplete="off"
                                            onkeyup="buscarAlumnos(this.value)">
                                        <div id="sugerencias" class="sugerencias-dropdown"></div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i data-lucide="search"></i>
                                        Buscar Alumno
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($alumno_encontrado): ?>
                    <!-- Formulario de inscripción -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">2. Inscribir Alumno</h2>
                            <p class="card-description">Completa los datos para inscribir al alumno seleccionado.</p>
                        </div>
                        <div class="card-content">
                            <!-- Información del alumno encontrado -->
                            <div style="background: rgba(255, 224, 204, 0.3); padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; border-left: 4px solid var(--orange-primary);">
                                <h3 style="margin: 0 0 0.5rem 0; color: var(--orange-primary); font-size: 1rem;">
                                    <i data-lucide="user-check" style="width: 16px; height: 16px; margin-right: 0.5rem;"></i>
                                    Alumno Seleccionado
                                </h3>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; font-size: 0.875rem;">
                                    <div><strong>Nombre:</strong> <?= htmlspecialchars($alumno_encontrado['apellidos'] . ', ' . $alumno_encontrado['nombres']) ?></div>
                                    <div><strong>Legajo:</strong> <?= htmlspecialchars($alumno_encontrado['legajo']) ?></div>
                                    <div><strong>DNI:</strong> <?= htmlspecialchars($alumno_encontrado['dni']) ?></div>
                                </div>
                            </div>

                            <form action="admin_inscripcion_manual.php" method="POST" id="formInscripcion">
                                <input type="hidden" name="accion" value="inscribir_alumno">
                                <input type="hidden" name="alumno_id_hidden" id="alumno_id_hidden_val" value="<?= htmlspecialchars($alumno_encontrado['alumno_id']) ?>">
                                <input type="hidden" id="search_term_val" value="<?= htmlspecialchars($search_term) ?>">

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="materia_id">Materia (*):</label>
                                        <select id="materia_id" name="materia_id" required onchange="actualizarCursos()">
                                            <option value="">-- Seleccionar Materia --</option>
                                            <?php foreach ($materias as $materia): ?>
                                                <option value="<?= $materia['id'] ?>" <?= ($selected_materia_id_form == $materia['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($materia['anio'] . '° - ' . $materia['nombre'] . ' (' . $materia['cuatrimestre'] . ')') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="ciclo_lectivo">Ciclo Lectivo (*):</label>
                                        <input type="number" id="ciclo_lectivo" name="ciclo_lectivo" value="<?= htmlspecialchars($selected_ciclo_lectivo_form) ?>" required onchange="actualizarCursos()" min="2020" max="2030">
                                    </div>

                                    <div class="form-group">
                                        <label for="curso_id">Curso/Comisión (*):</label>
                                        <select id="curso_id" name="curso_id" required>
                                            <option value="">-- Seleccionar Curso (primero elija materia y ciclo) --</option>
                                            <?php if (!empty($cursos_disponibles_form)): ?>
                                                <?php foreach ($cursos_disponibles_form as $curso): ?>
                                                    <option value="<?= $curso['id'] ?>">
                                                        <?= htmlspecialchars($curso['codigo'] . ' ' . $curso['division'] . ' - ' . $curso['turno']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php elseif ($selected_materia_id_form && $selected_ciclo_lectivo_form): ?>
                                                <option value="" disabled>No hay cursos disponibles para la materia y ciclo lectivo seleccionados.</option>
                                            <?php endif; ?>
                                        </select>
                                        <?php if (empty($cursos_disponibles_form) && $selected_materia_id_form && $selected_ciclo_lectivo_form): ?>
                                            <small style="color: #ef4444; font-size: 0.875rem; margin-top: 0.25rem; display: block;">
                                                <i data-lucide="alert-circle" style="width: 14px; height: 14px; margin-right: 0.25rem;"></i>
                                                No se encontraron cursos para la materia y ciclo lectivo seleccionados. Verifique la configuración de cursos.
                                            </small>
                                        <?php endif; ?>
                                    </div>

                                    <div class="form-group">
                                        <label for="estado_inscripcion">Estado de Inscripción (*):</label>
                                        <select id="estado_inscripcion" name="estado_inscripcion" required>
                                            <option value="">-- Seleccionar Estado --</option>
                                            <option value="Regular">Regular</option>
                                            <option value="Libre">Libre</option>
                                            <option value="Promocional">Promocional</option>
                                        </select>
                                    </div>
                                </div>

                                <?php
                                // Mostrar requisitos (opcional, informativo para el admin)
                                if ($selected_materia_id_form && function_exists('verificar_requisitos_materia_alumno')) {
                                    $requisitos = verificar_requisitos_materia_alumno($mysqli, $alumno_encontrado['alumno_id'], $selected_materia_id_form);
                                    echo '<div style="background: rgba(245, 245, 245, 0.8); padding: 1rem; border-radius: 6px; margin-top: 1rem; font-size: 0.875rem;">';
                                    echo '<h4 style="margin: 0 0 0.5rem 0; color: var(--orange-primary);"><i data-lucide="info" style="width: 16px; height: 16px; margin-right: 0.5rem;"></i>Información de Requisitos</h4>';
                                    echo "<div><strong>Requisitos para Cursar Regular:</strong> " . htmlspecialchars($requisitos['mensaje_cursar_regular']) . "</div>";
                                    echo "<div style='margin-top: 0.5rem;'><strong>Requisitos para Inscribir Libre:</strong> " . htmlspecialchars($requisitos['mensaje_inscribir_libre']) . "</div>";
                                    echo "</div>";
                                }
                                ?>

                                <div class="card-footer">
                                    <button type="button" class="btn btn-secondary" onclick="limpiarFormulario()">
                                        <i data-lucide="x"></i>
                                        Limpiar
                                    </button>
                                    <button type="submit" class="btn btn-primary" style="margin-left: 0.5rem;">
                                        <i data-lucide="user-plus"></i>
                                        Inscribir Alumno
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php elseif (isset($_REQUEST['accion']) && $_REQUEST['accion'] === 'buscar_alumno' && empty($mensaje_error)): ?>
                    <div class="card">
                        <div class="card-content">
                            <div style="text-align: center; padding: 2rem; color: #64748b;">
                                <i data-lucide="user-x" style="width: 48px; height: 48px; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p style="margin: 0; font-size: 1rem;">No se encontró ningún alumno con los criterios de búsqueda proporcionados.</p>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem; opacity: 0.8;">Intente con otro término de búsqueda.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <script>
        // Sidebar and general UI functions
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

        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });

        function confirmLogout() {
            if (confirm('¿Estás seguro que deseas cerrar sesión?')) {
                window.location.href = '../index.php?logout=1';
            }
        }

        // Script para recargar la página cuando se cambia la materia o el ciclo lectivo
        function actualizarCursos() {
            const materiaId = document.getElementById('materia_id').value;
            const cicloLectivo = document.getElementById('ciclo_lectivo').value;
            const alumnoIdHidden = document.getElementById('alumno_id_hidden_val') ? document.getElementById('alumno_id_hidden_val').value : '';
            const searchTermActual = document.getElementById('search_term_val') ? document.getElementById('search_term_val').value : '';

            if (materiaId && cicloLectivo) {
                // Recargar la página con los nuevos parámetros para que PHP cargue los cursos
                let url = `admin_inscripcion_manual.php?accion=buscar_alumno&search_term=${encodeURIComponent(searchTermActual)}&alumno_id_hidden=${alumnoIdHidden}&materia_id=${materiaId}&ciclo_lectivo=${cicloLectivo}`;
                window.location.href = url;
            }
        }

        // Función para limpiar el formulario
        function limpiarFormulario() {
            if (confirm('¿Está seguro de que desea limpiar el formulario? Perderá los datos ingresados.')) {
                window.location.href = 'admin_inscripcion_manual.php';
            }
        }

        // Función para buscar alumnos (sugerencias en dropdown)
        async function buscarAlumnos(termino) {
            if (termino.length < 2) {
                document.getElementById('sugerencias').style.display = 'none';
                return;
            }

            try {
                const response = await fetch(`buscar_alumnos_ajax.php?term=${encodeURIComponent(termino)}`);
                const alumnos = await response.json();

                const sugerenciasDiv = document.getElementById('sugerencias');

                if (alumnos.length > 0) {
                    sugerenciasDiv.innerHTML = alumnos.map(alumno => `
                        <div class="sugerencia-item" onclick="seleccionarAlumno('${alumno.legajo}', '${alumno.apellidos}, ${alumno.nombres}')">
                            <div class="nombre">${alumno.apellidos}, ${alumno.nombres}</div>
                            <div class="info">Legajo: ${alumno.legajo} | DNI: ${alumno.dni}</div>
                        </div>
                    `).join('');
                    sugerenciasDiv.style.display = 'block';
                } else {
                    sugerenciasDiv.innerHTML = '<div class="sugerencia-item">No se encontraron resultados</div>';
                    sugerenciasDiv.style.display = 'block';
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        function seleccionarAlumno(legajo, nombreCompleto) {
            document.getElementById('search_term').value = legajo;
            document.getElementById('sugerencias').style.display = 'none';
            document.getElementById('searchForm').submit();
        }

        // Cerrar sugerencias al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#search_term')) {
                document.getElementById('sugerencias').style.display = 'none';
            }
        });

        // Create icons after DOM is ready
        lucide.createIcons();
    </script>
</body>

</html>