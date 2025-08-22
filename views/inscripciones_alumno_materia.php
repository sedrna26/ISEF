<?php
// Iniciar sesión al principio de todo
session_start();

// 1. Incluir funciones y conexión a la base de datos
include_once '../tools/funciones_inscripcion.php';
require_once '../config/db.php'; // Es mejor usar require_once para la conexión

// 2. Verificación de sesión y tipo de usuario
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'alumno') {
    header("Location: ../index.php");
    exit;
}

// 3. Obtener el nombre del usuario para el sidebar (como en profesores.php)
$usuario_sidebar = ['nombre_completo' => 'Alumno ISEF']; // Valor por defecto
$stmt_user_sidebar = $mysqli->prepare("
    SELECT CONCAT(p.apellidos, ' ', p.nombres) as nombre_completo 
    FROM persona p 
    JOIN usuario u ON p.usuario_id = u.id 
    WHERE u.id = ?
");
if ($stmt_user_sidebar) {
    $stmt_user_sidebar->bind_param("i", $_SESSION['usuario_id']);
    $stmt_user_sidebar->execute();
    $result_user_sidebar = $stmt_user_sidebar->get_result();
    if ($user_data = $result_user_sidebar->fetch_assoc()) {
        $usuario_sidebar = $user_data;
    }
    $stmt_user_sidebar->close();
}

// 4. Obtener alumno_id (lógica original)
if (!isset($_SESSION['alumno_id_db'])) {
    $stmt_alumno = $mysqli->prepare("SELECT a.id FROM alumno a 
                            JOIN persona p ON a.persona_id = p.id 
                            JOIN usuario u ON p.usuario_id = u.id 
                            WHERE u.id = ?");
    $stmt_alumno->bind_param("i", $_SESSION['usuario_id']);
    $stmt_alumno->execute();
    $result_alumno = $stmt_alumno->get_result();
    if ($row_alumno = $result_alumno->fetch_assoc()) {
        $_SESSION['alumno_id_db'] = $row_alumno['id'];
    } else {
        // Manejar el error de forma más elegante
        $_SESSION['mensaje_error'] = "Error crítico: No se encontró el registro de alumno asociado a este usuario.";
        header("Location: dashboard.php");
        exit;
    }
    $stmt_alumno->close();
}
$alumno_id = $_SESSION['alumno_id_db'];
$ciclo_lectivo_actual = date("Y");


// 5. Procesar formulario de inscripción (ANTES de enviar cualquier HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $materia_id = isset($_POST['materia_id']) ? (int)$_POST['materia_id'] : 0;
    $curso_id = isset($_POST['curso_id']) ? (int)$_POST['curso_id'] : 0;
    $tipo_inscripcion = isset($_POST['tipo_inscripcion']) ? $_POST['tipo_inscripcion'] : '';

    $stmt_materia = $mysqli->prepare("SELECT cuatrimestre, tipo, nombre FROM materia WHERE id = ?");
    $stmt_materia->bind_param("i", $materia_id);
    $stmt_materia->execute();
    $materia_info = $stmt_materia->get_result()->fetch_assoc();
    $stmt_materia->close();

    if ($materia_id && $curso_id && $tipo_inscripcion && $materia_info) {
        $periodo_activo = verificar_periodo_inscripcion_activo(
            $mysqli,
            $materia_info['cuatrimestre'],
            $ciclo_lectivo_actual
        );

        if ($periodo_activo) {
            $ya_inscripto = alumno_ya_inscripto($mysqli, $alumno_id, $materia_id, $ciclo_lectivo_actual);

            if (!$ya_inscripto) {
                $estado_inscripcion = ($tipo_inscripcion === 'regular') ? 'Regular' : 'Libre';

                $stmt_insert = $mysqli->prepare("
                    INSERT INTO inscripcion_cursado 
                    (alumno_id, materia_id, curso_id, ciclo_lectivo, estado, fecha_inscripcion) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");

                try {
                    $stmt_insert->bind_param("iiiis", $alumno_id, $materia_id, $curso_id, $ciclo_lectivo_actual, $estado_inscripcion);
                    if ($stmt_insert->execute()) {
                        $_SESSION['mensaje_exito'] = "Inscripción realizada con éxito en " . htmlspecialchars($materia_info['nombre']) . " como " . htmlspecialchars($estado_inscripcion);
                    } else {
                        throw new Exception("Error al ejecutar la consulta: " . $stmt_insert->error);
                    }
                } catch (Exception $e) {
                    $_SESSION['mensaje_error'] = "Error al procesar la inscripción: " . $e->getMessage();
                }
                $stmt_insert->close();
            } else {
                $_SESSION['mensaje_error'] = "Ya estás inscripto en esta materia.";
            }
        } else {
            $_SESSION['mensaje_error'] = "El período de inscripción no está activo para materias de este " . htmlspecialchars($materia_info['cuatrimestre']) . ".";
        }
    } else {
        $_SESSION['mensaje_error'] = "Datos de inscripción incompletos o inválidos.";
    }

    // Redirigir para evitar reenvío del formulario
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 6. Obtener materias para mostrar en la página
$result_materias = $mysqli->query("SELECT * FROM materia ORDER BY anio, nro_orden");

// 7. Recuperar mensajes de la sesión para mostrarlos
$mensaje = '';
$error = '';
if (isset($_SESSION['mensaje_exito'])) {
    $mensaje = $_SESSION['mensaje_exito'];
    unset($_SESSION['mensaje_exito']);
}
if (isset($_SESSION['mensaje_error'])) {
    $error = $_SESSION['mensaje_error'];
    unset($_SESSION['mensaje_error']);
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscripción a Materias - Sistema ISEF</title>
    <link rel="icon" href="../sources/logo_recortado.ico" type="image/x-icon">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="../style/style.css">
</head>

<body>
    <div class="app-container">
        <?php include_once 'includes/nav.php'; ?>
        <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

        <main class="main-content">
            <header class="header">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i data-lucide="menu"></i>
                </button>
                <nav class="breadcrumb">
                    <a href="dashboard.php">Sistema de Gestión ISEF</a>
                    <span>/</span>
                    <span>Inscripciones</span>
                </nav>
            </header>

            <div class="content">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Inscripción a Materias</h1>
                        <p class="page-subtitle">Ciclo Lectivo <?= htmlspecialchars($ciclo_lectivo_actual) ?></p>
                    </div>
                </div>

                <?php if ($mensaje): ?>
                    <div class="message-toast success" role="alert"><?= htmlspecialchars($mensaje) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="message-toast error" role="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($result_materias && $result_materias->num_rows > 0): ?>
                    <?php while ($materia = $result_materias->fetch_assoc()): ?>
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title"><?= htmlspecialchars($materia['nombre']) ?></h2>
                                <p class="card-description">
                                    Año: <?= htmlspecialchars($materia['anio']) ?> |
                                    Tipo: <?= htmlspecialchars($materia['tipo']) ?> |
                                    Período: <?= htmlspecialchars($materia['cuatrimestre']) ?>
                                </p>
                            </div>
                            <div class="card-content">
                                <?php
                                $ya_inscripto = alumno_ya_inscripto($mysqli, $alumno_id, $materia['id'], $ciclo_lectivo_actual);
                                if ($ya_inscripto):
                                ?>
                                    <div class="message-toast success">
                                        Ya estás inscripto/a en esta materia para el ciclo lectivo actual.
                                    </div>
                                    <?php else:
                                    $periodo_activo = verificar_periodo_inscripcion_activo($mysqli, $materia['cuatrimestre'], $ciclo_lectivo_actual);
                                    if (!$periodo_activo):
                                    ?>
                                        <div class="message-toast error">
                                            El período de inscripción para materias del <?= htmlspecialchars($materia['cuatrimestre']) ?> no se encuentra activo.
                                        </div>
                                        <?php else:
                                        $cursos_disponibles = obtener_cursos_disponibles($mysqli, $materia['id'], $ciclo_lectivo_actual);
                                        if ($cursos_disponibles && $cursos_disponibles->num_rows > 0):
                                        ?>
                                            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                                                <input type="hidden" name="materia_id" value="<?= $materia['id'] ?>">
                                                <input type="hidden" name="tipo_inscripcion" id="tipo_inscripcion_<?= $materia['id'] ?>" value="">

                                                <div class="form-group">
                                                    <label for="curso_<?= $materia['id'] ?>">Seleccionar Comisión:</label>
                                                    <select name="curso_id" id="curso_<?= $materia['id'] ?>" required>
                                                        <option value="" disabled selected>Elige una opción...</option>
                                                        <?php while ($curso = $cursos_disponibles->fetch_assoc()): ?>
                                                            <option value="<?= $curso['id'] ?>">
                                                                <?= htmlspecialchars($curso['codigo'] . ' ' . $curso['division'] . ' - ' . $curso['turno']) ?>
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>

                                                <div style="display: flex; gap: 0.75rem; margin-top: 1rem; flex-wrap: wrap;">
                                                    <button type="submit" class="btn btn-primary" onclick="document.getElementById('tipo_inscripcion_<?= $materia['id'] ?>').value='regular';">
                                                        <i data-lucide="check-circle"></i> Inscribirse Regular
                                                    </button>
                                                    <button type="submit" class="btn btn-secondary" onclick="document.getElementById('tipo_inscripcion_<?= $materia['id'] ?>').value='libre';">
                                                        <i data-lucide="book-open"></i> Inscribirse Libre
                                                    </button>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <div class="message-toast error">
                                                No hay comisiones disponibles para esta materia en este momento.
                                            </div>
                                <?php
                                        endif;
                                        // Liberar resultado de cursos
                                        if ($cursos_disponibles) $cursos_disponibles->free();
                                    endif;
                                endif;
                                ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="card">
                        <div class="card-content" style="text-align: center;">
                            <p>No hay materias disponibles para inscripción en este momento.</p>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <script>
        // Funciones para la UI del dashboard (sidebar, logout, etc.)
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

        // Crear los íconos de Lucide
        lucide.createIcons();
    </script>
</body>

</html>