<?php
// admin_periodos_examen.php - Gestión de Períodos de Examen para administradores (Estilo unificado)
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: ../index.php");
    exit;
}

// 1. Incluir el archivo de conexión a la base de datos
require_once '../config/db.php'; // Usamos la conexión centralizada

// 2. Obtener el nombre del usuario para el sidebar (como en profesores.php)
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


// 3. Sistema de mensajes con Sesión (unificado)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $should_redirect = true; // Variable para controlar la redirección

    if (isset($_POST['crear_mesa'])) {
        $materia_id = (int)$_POST['materia_id'];
        $curso_id = (int)$_POST['curso_id'];
        $fecha = $_POST['fecha'];
        $tipo = $_POST['tipo'];
        $profesor_id = (int)$_POST['profesor_id'];
        $libro = $_POST['libro'] ? (int)$_POST['libro'] : null;
        $folio = $_POST['folio'] ? (int)$_POST['folio'] : null;

        // Validar duplicados
        $check_duplicate = $mysqli->prepare("SELECT id FROM acta_examen WHERE materia_id = ? AND curso_id = ? AND fecha = ? AND tipo = ?");
        $check_duplicate->bind_param("iiss", $materia_id, $curso_id, $fecha, $tipo);
        $check_duplicate->execute();
        $check_duplicate->store_result();

        if ($check_duplicate->num_rows > 0) {
            $_SESSION['mensaje_error'] = "Error: Ya existe un período de examen para esta materia, curso y fecha.";
        } else {
            $stmt = $mysqli->prepare("INSERT INTO acta_examen (materia_id, curso_id, fecha, tipo, profesor_id, libro, folio, cerrada) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
            $stmt->bind_param("iissiii", $materia_id, $curso_id, $fecha, $tipo, $profesor_id, $libro, $folio);
            if ($stmt->execute()) {
                $_SESSION['mensaje_exito'] = "Período de examen creado exitosamente.";
            } else {
                $_SESSION['mensaje_error'] = "Error al crear el período: " . $stmt->error;
            }
            $stmt->close();
        }
        $check_duplicate->close();
    }

    if (isset($_POST['cerrar_mesa'])) {
        $mesa_id = (int)$_POST['mesa_id'];
        $stmt = $mysqli->prepare("UPDATE acta_examen SET cerrada = 1 WHERE id = ?");
        $stmt->bind_param("i", $mesa_id);
        if ($stmt->execute()) {
            $_SESSION['mensaje_exito'] = "El período se ha cerrado correctamente.";
        } else {
            $_SESSION['mensaje_error'] = "Error al cerrar el período.";
        }
        $stmt->close();
    }

    if (isset($_POST['eliminar_mesa'])) {
        $mesa_id = (int)$_POST['mesa_id'];
        $check_inscripciones = $mysqli->prepare("SELECT COUNT(*) as total FROM inscripcion_examen WHERE acta_examen_id = ?");
        $check_inscripciones->bind_param("i", $mesa_id);
        $check_inscripciones->execute();
        $inscripciones = $check_inscripciones->get_result()->fetch_assoc();
        $check_inscripciones->close();

        if ($inscripciones['total'] > 0) {
            $_SESSION['mensaje_error'] = "No se puede eliminar porque tiene inscripciones registradas.";
        } else {
            $stmt = $mysqli->prepare("DELETE FROM acta_examen WHERE id = ?");
            $stmt->bind_param("i", $mesa_id);
            if ($stmt->execute()) {
                $_SESSION['mensaje_exito'] = "Período eliminado exitosamente.";
            } else {
                $_SESSION['mensaje_error'] = "Error al eliminar el período.";
            }
            $stmt->close();
        }
    }

    if ($should_redirect) {
        header("Location: admin_periodos_examen.php");
        exit;
    }
}

// 4. Recuperar mensajes de la sesión
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


// Obtener parámetros de filtrado
$filtro_materia = isset($_GET['filtro_materia']) ? (int)$_GET['filtro_materia'] : null;
$filtro_curso = isset($_GET['filtro_curso']) ? (int)$_GET['filtro_curso'] : null;
$filtro_estado = isset($_GET['filtro_estado']) ? $_GET['filtro_estado'] : '';
$filtro_fecha_desde = isset($_GET['filtro_fecha_desde']) ? $_GET['filtro_fecha_desde'] : '';
$filtro_fecha_hasta = isset($_GET['filtro_fecha_hasta']) ? $_GET['filtro_fecha_hasta'] : '';

// Construir consulta con filtros
$where_conditions = [];
$params = [];
$types = '';
if ($filtro_materia) {
    $where_conditions[] = "ae.materia_id = ?";
    $params[] = $filtro_materia;
    $types .= 'i';
}
if ($filtro_curso) {
    $where_conditions[] = "ae.curso_id = ?";
    $params[] = $filtro_curso;
    $types .= 'i';
}
if ($filtro_estado === 'abierta') {
    $where_conditions[] = "ae.cerrada = 0";
} elseif ($filtro_estado === 'cerrada') {
    $where_conditions[] = "ae.cerrada = 1";
}
if ($filtro_fecha_desde) {
    $where_conditions[] = "ae.fecha >= ?";
    $params[] = $filtro_fecha_desde;
    $types .= 's';
}
if ($filtro_fecha_hasta) {
    $where_conditions[] = "ae.fecha <= ?";
    $params[] = $filtro_fecha_hasta;
    $types .= 's';
}
$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Obtener datos para el formulario y filtros
$materias = $mysqli->query("SELECT id, nombre FROM materia ORDER BY nombre");
$cursos = $mysqli->query("SELECT id, CONCAT(codigo, ' ', division, ' - ', ciclo_lectivo) as nombre FROM curso ORDER BY ciclo_lectivo DESC, codigo");
$profesores = $mysqli->query("SELECT pr.id, CONCAT(p.apellidos, ', ', p.nombres) as nombre FROM profesor pr JOIN persona p ON pr.persona_id = p.id ORDER BY p.apellidos, p.nombres");

// Obtener mesas existentes con filtros
$mesas_query = "
    SELECT ae.id, ae.fecha, ae.tipo, ae.libro, ae.folio, ae.cerrada,
           m.nombre as materia_nombre, m.id as materia_id,
           c.id as curso_id, CONCAT(c.codigo, ' ', c.division, ' - ', c.ciclo_lectivo) as curso_nombre,
           CONCAT(p.apellidos, ', ', p.nombres) as profesor_nombre,
           COUNT(ie.id) as total_inscriptos
    FROM acta_examen ae
    JOIN materia m ON ae.materia_id = m.id
    JOIN curso c ON ae.curso_id = c.id
    JOIN profesor pr ON ae.profesor_id = pr.id
    JOIN persona p ON pr.persona_id = p.id
    LEFT JOIN inscripcion_examen ie ON ae.id = ie.acta_examen_id
    $where_clause
    GROUP BY ae.id
    ORDER BY ae.fecha DESC, m.nombre
";
$mesas_stmt = $mysqli->prepare($mesas_query);
if ($params) {
    $mesas_stmt->bind_param($types, ...$params);
}
$mesas_stmt->execute();
$mesas_result = $mesas_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Períodos de Examen - Sistema ISEF</title>
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
                    <span>Períodos de Examen</span>
                </nav>
            </header>

            <div class="content">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Gestión de Períodos de Examen</h1>
                        <p class="page-subtitle">Crea, administra y filtra los períodos de exámenes finales.</p>
                    </div>
                    <button class="btn btn-primary" onclick="mostrarFormCreacion()">
                        <i data-lucide="plus"></i>
                        Nuevo Período
                    </button>
                </div>

                <?php if ($mensaje): ?>
                    <div class="message-toast success" role="alert"><?= htmlspecialchars($mensaje) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="message-toast error" role="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="card" id="creacionFormCard" style="display:none;">
                    <div class="card-header">
                        <h2 class="card-title">Crear Nuevo Período de Examen</h2>
                        <p class="card-description">Completa los datos para habilitar una nueva fecha de examen.</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="crear_mesa" value="1">
                        <div class="card-content">
                            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                                <div class="form-group">
                                    <label for="materia_id">Materia:</label>
                                    <select name="materia_id" required>
                                        <option value="">-- Seleccione --</option>
                                        <?php while ($materia = $materias->fetch_assoc()): ?>
                                            <option value="<?= $materia['id'] ?>"><?= htmlspecialchars($materia['nombre']) ?></option>
                                        <?php endwhile;
                                        $materias->data_seek(0); ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="curso_id">Curso:</label>
                                    <select name="curso_id" required>
                                        <option value="">-- Seleccione --</option>
                                        <?php while ($curso = $cursos->fetch_assoc()): ?>
                                            <option value="<?= $curso['id'] ?>"><?= htmlspecialchars($curso['nombre']) ?></option>
                                        <?php endwhile;
                                        $cursos->data_seek(0); ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="fecha">Fecha del Examen:</label>
                                    <input type="date" name="fecha" required min="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="tipo">Tipo:</label>
                                    <select name="tipo" required>
                                        <option value="">-- Seleccione --</option>
                                        <option value="1°Cuatrimestre">1° Cuatrimestre</option>
                                        <option value="2°Cuatrimestre">2° Cuatrimestre</option>
                                        <option value="Anual">Anual</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="profesor_id">Profesor:</label>
                                    <select name="profesor_id" required>
                                        <option value="">-- Seleccione --</option>
                                        <?php while ($profesor = $profesores->fetch_assoc()): ?>
                                            <option value="<?= $profesor['id'] ?>"><?= htmlspecialchars($profesor['nombre']) ?></option>
                                        <?php endwhile;
                                        $profesores->data_seek(0); ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="libro">Libro (opcional):</label>
                                    <input type="number" name="libro" min="1">
                                </div>
                                <div class="form-group">
                                    <label for="folio">Folio (opcional):</label>
                                    <input type="number" name="folio" min="1">
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="button" class="btn btn-secondary" onclick="ocultarFormCreacion()">Cancelar</button>
                            <button type="submit" class="btn btn-primary" style="margin-left:0.5rem;"><i data-lucide="save"></i> Crear Período</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Filtrar Períodos de Examen</h2>
                        <a href="admin_periodos_examen.php">[Limpiar filtros]</a>
                    </div>
                    <div class="card-content">
                        <form method="GET">
                            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                                <div class="form-group">
                                    <label for="filtro_materia">Materia:</label>
                                    <select name="filtro_materia">
                                        <option value="">-- Todas --</option>
                                        <?php while ($materia = $materias->fetch_assoc()): ?>
                                            <option value="<?= $materia['id'] ?>" <?= $filtro_materia == $materia['id'] ? 'selected' : '' ?>><?= htmlspecialchars($materia['nombre']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="filtro_curso">Curso:</label>
                                    <select name="filtro_curso">
                                        <option value="">-- Todos --</option>
                                        <?php while ($curso = $cursos->fetch_assoc()): ?>
                                            <option value="<?= $curso['id'] ?>" <?= $filtro_curso == $curso['id'] ? 'selected' : '' ?>><?= htmlspecialchars($curso['nombre']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="filtro_estado">Estado:</label>
                                    <select name="filtro_estado">
                                        <option value="">-- Todos --</option>
                                        <option value="abierta" <?= $filtro_estado === 'abierta' ? 'selected' : '' ?>>Abiertas</option>
                                        <option value="cerrada" <?= $filtro_estado === 'cerrada' ? 'selected' : '' ?>>Cerradas</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="filtro_fecha_desde">Fecha desde:</label>
                                    <input type="date" name="filtro_fecha_desde" value="<?= htmlspecialchars($filtro_fecha_desde) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="filtro_fecha_hasta">Fecha hasta:</label>
                                    <input type="date" name="filtro_fecha_hasta" value="<?= htmlspecialchars($filtro_fecha_hasta) ?>">
                                </div>
                                <div class="form-group" style="align-self: end;">
                                    <button type="submit" class="btn btn-primary"><i data-lucide="search"></i> Aplicar Filtros</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Períodos de Examen Existentes</h2>
                        <p class="card-description">Hoy es: <?= date('d/m/Y') ?></p>
                    </div>
                    <div class="card-content">
                        <div class="table-container">
                            <table class="styled-table">
                                <thead>
                                    <tr>
                                        <th>Materia</th>
                                        <th>Curso</th>
                                        <th>Fecha</th>
                                        <th>Profesor</th>
                                        <th>Inscriptos</th>
                                        <th>Estado</th>
                                        <th class="text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($mesas_result && $mesas_result->num_rows > 0): ?>
                                        <?php while ($mesa = $mesas_result->fetch_assoc()):
                                            $hoy = date('Y-m-d');
                                            $fecha_mesa = $mesa['fecha'];
                                            $es_pasada = $fecha_mesa < $hoy;
                                            $es_hoy = $fecha_mesa === $hoy;
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($mesa['materia_nombre']) ?><br><small><?= htmlspecialchars($mesa['tipo']) ?></small></td>
                                                <td><?= htmlspecialchars($mesa['curso_nombre']) ?></td>
                                                <td>
                                                    <?= date('d/m/Y', strtotime($fecha_mesa)) ?>
                                                    <?php if ($es_hoy): ?><span class="badge badge-info">HOY</span><?php endif; ?>
                                                    <?php if ($es_pasada): ?><span class="badge badge-secondary">PASADA</span><?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($mesa['profesor_nombre']) ?></td>
                                                <td>
                                                    <span class="badge badge-primary"><?= $mesa['total_inscriptos'] ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($mesa['cerrada']): ?>
                                                        <span class="badge badge-danger"><i data-lucide="lock"></i> Cerrada</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success"><i data-lucide="unlock"></i> Abierta</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="table-actions">
                                                    <a href="ver_inscriptos_mesa.php?mesa_id=<?= $mesa['id'] ?>" class="btn btn-outline btn-sm" title="Ver Inscriptos">
                                                        <i data-lucide="users"></i>
                                                    </a>
                                                    <?php if (!$mesa['cerrada']): ?>
                                                        <a href="editar_mesa_examen.php?mesa_id=<?= $mesa['id'] ?>" class="btn btn-outline btn-sm" title="Editar">
                                                            <i data-lucide="edit-2"></i>
                                                        </a>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Está seguro de CERRAR este período? No podrá revertir esta acción.')">
                                                            <input type="hidden" name="cerrar_mesa" value="1">
                                                            <input type="hidden" name="mesa_id" value="<?= $mesa['id'] ?>">
                                                            <button type="submit" class="btn btn-outline btn-warning-outline btn-sm" title="Cerrar"><i data-lucide="lock"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if ($mesa['total_inscriptos'] == 0): ?>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Está seguro de ELIMINAR este período?')">
                                                            <input type="hidden" name="eliminar_mesa" value="1">
                                                            <input type="hidden" name="mesa_id" value="<?= $mesa['id'] ?>">
                                                            <button type="submit" class="btn btn-outline btn-danger-outline btn-sm" title="Eliminar"><i data-lucide="trash-2"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" style="text-align:center; padding: 2rem;">No se encontraron períodos de examen con los filtros seleccionados.</td>
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
        // Funciones del Sidebar y UI (como en profesores.php)
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('overlay').classList.toggle('show');
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('overlay').classList.remove('show');
        }

        function confirmLogout() {
            if (confirm('¿Estás seguro que deseas cerrar sesión?')) {
                window.location.href = '../index.php?logout=1';
            }
        }

        // Funciones para el formulario de creación
        const creacionFormCard = document.getElementById('creacionFormCard');

        function mostrarFormCreacion() {
            creacionFormCard.style.display = 'block';
            creacionFormCard.scrollIntoView({
                behavior: 'smooth'
            });
        }

        function ocultarFormCreacion() {
            creacionFormCard.style.display = 'none';
        }

        // Script para la fecha
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const fechaInput = document.querySelector('input[name="fecha"]');
            if (fechaInput && !fechaInput.value) {
                fechaInput.value = today;
            }
        });

        // Renderizar iconos
        lucide.createIcons();
    </script>
</body>

</html>
<?php
$mysqli->close();
?>