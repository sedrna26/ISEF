<?php
// admin_periodos_inscripcion.php - Gestión de Períodos de Inscripción (Estilo Unificado)
session_start();
require_once '../config/db.php';

// VERIFICACIÓN DE USUARIO ADMINISTRADOR
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: ../index.php");
    exit;
}

// OBTENER NOMBRE DE USUARIO PARA SIDEBAR (COMO EN PROFESORES.PHP)
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
    $usuario_sidebar = $result_user_sidebar->fetch_assoc();
    $stmt_user_sidebar->close();
} else {
    $usuario_sidebar = ['nombre_completo' => 'Admin ISEF']; // Fallback
}

// LÓGICA PARA MANEJAR ACCIONES (POST Y GET)
$periodo_id_editar = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_periodo') {
        $ciclo_lectivo = filter_input(INPUT_POST, 'ciclo_lectivo', FILTER_VALIDATE_INT);
        $cuatrimestre = filter_input(INPUT_POST, 'cuatrimestre', FILTER_SANITIZE_STRING);
        $fecha_apertura = filter_input(INPUT_POST, 'fecha_apertura', FILTER_SANITIZE_STRING);
        $fecha_cierre = filter_input(INPUT_POST, 'fecha_cierre', FILTER_SANITIZE_STRING);
        $descripcion = filter_input(INPUT_POST, 'descripcion', FILTER_SANITIZE_STRING);
        $activo = isset($_POST['activo']) ? 1 : 0;
        $periodo_id = filter_input(INPUT_POST, 'periodo_id', FILTER_VALIDATE_INT);

        if ($ciclo_lectivo && $cuatrimestre && $fecha_apertura && $fecha_cierre) {
            if ($fecha_apertura > $fecha_cierre) {
                $_SESSION['mensaje_error'] = "Error: La fecha de apertura no puede ser posterior a la de cierre.";
            } else {
                if ($periodo_id) { // Editar
                    $stmt = $mysqli->prepare("UPDATE periodos_inscripcion SET ciclo_lectivo = ?, cuatrimestre = ?, fecha_apertura = ?, fecha_cierre = ?, descripcion = ?, activo = ? WHERE id = ?");
                    $stmt->bind_param("issssii", $ciclo_lectivo, $cuatrimestre, $fecha_apertura, $fecha_cierre, $descripcion, $activo, $periodo_id);
                    if ($stmt->execute()) {
                        $_SESSION['mensaje_exito'] = "Período actualizado correctamente.";
                    } else {
                        $_SESSION['mensaje_error'] = "Error al actualizar el período.";
                    }
                    $stmt->close();
                } else { // Agregar
                    $stmt = $mysqli->prepare("INSERT INTO periodos_inscripcion (ciclo_lectivo, cuatrimestre, fecha_apertura, fecha_cierre, descripcion, activo) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issssi", $ciclo_lectivo, $cuatrimestre, $fecha_apertura, $fecha_cierre, $descripcion, $activo);
                    if ($stmt->execute()) {
                        $_SESSION['mensaje_exito'] = "Período agregado correctamente.";
                    } else {
                        $_SESSION['mensaje_error'] = "Error al agregar el período.";
                    }
                    $stmt->close();
                }
            }
        } else {
            $_SESSION['mensaje_error'] = "Todos los campos obligatorios deben ser completados.";
        }
    }
    header("Location: admin_periodos_inscripcion.php");
    exit;
}

// Lógica para acciones GET (editar, eliminar)
if (isset($_GET['accion'])) {
    $accion_get = $_GET['accion'];
    $id_get = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

    if ($id_get) {
        if ($accion_get === 'editar') {
            $stmt = $mysqli->prepare("SELECT * FROM periodos_inscripcion WHERE id = ?");
            $stmt->bind_param("i", $id_get);
            $stmt->execute();
            $result_edit = $stmt->get_result();
            if ($result_edit->num_rows === 1) {
                $periodo_id_editar = $result_edit->fetch_assoc();
            } else {
                $_SESSION['mensaje_error'] = "Período no encontrado.";
                header("Location: admin_periodos_inscripcion.php");
                exit;
            }
            $stmt->close();
        } elseif ($accion_get === 'eliminar') {
            $stmt = $mysqli->prepare("DELETE FROM periodos_inscripcion WHERE id = ?");
            $stmt->bind_param("i", $id_get);
            if ($stmt->execute()) {
                $_SESSION['mensaje_exito'] = "Período eliminado correctamente.";
            } else {
                $_SESSION['mensaje_error'] = "Error al eliminar el período.";
            }
            $stmt->close();
            header("Location: admin_periodos_inscripcion.php");
            exit;
        }
    }
}

// RECUPERAR MENSAJES DE LA SESIÓN
$mensaje = $_SESSION['mensaje_exito'] ?? '';
$error = $_SESSION['mensaje_error'] ?? '';
unset($_SESSION['mensaje_exito'], $_SESSION['mensaje_error']);

// OBTENER LISTA DE PERÍODOS EXISTENTES
$lista_periodos = $mysqli->query("SELECT * FROM periodos_inscripcion ORDER BY ciclo_lectivo DESC, fecha_apertura DESC")->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Períodos de Inscripción - Sistema ISEF</title>
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
                    <span>Períodos de Inscripción</span>
                </nav>
            </header>

            <div class="content">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Gestión de Períodos de Inscripción</h1>
                        <p class="page-subtitle">Crea y administra los períodos para inscripciones a materias.</p>
                    </div>
                </div>

                <?php if ($mensaje): ?><div class="message-toast success" role="alert"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="message-toast error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><?= $periodo_id_editar ? 'Editar Período de Inscripción' : 'Crear Nuevo Período' ?></h2>
                        <p class="card-description">Completa los datos para habilitar un nuevo período.</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="accion" value="guardar_periodo">
                        <?php if ($periodo_id_editar): ?>
                            <input type="hidden" name="periodo_id" value="<?= htmlspecialchars($periodo_id_editar['id']) ?>">
                        <?php endif; ?>

                        <div class="card-content">
                            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                                <div class="form-group">
                                    <label for="ciclo_lectivo">Ciclo Lectivo (*):</label>
                                    <input type="number" id="ciclo_lectivo" name="ciclo_lectivo" value="<?= htmlspecialchars($periodo_id_editar['ciclo_lectivo'] ?? date('Y')) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="cuatrimestre">Cuatrimestre (*):</label>
                                    <select id="cuatrimestre" name="cuatrimestre" required>
                                        <option value="1°" <?= (isset($periodo_id_editar['cuatrimestre']) && $periodo_id_editar['cuatrimestre'] == '1°') ? 'selected' : '' ?>>1° Cuatrimestre</option>
                                        <option value="2°" <?= (isset($periodo_id_editar['cuatrimestre']) && $periodo_id_editar['cuatrimestre'] == '2°') ? 'selected' : '' ?>>2° Cuatrimestre</option>
                                        <option value="Anual" <?= (isset($periodo_id_editar['cuatrimestre']) && $periodo_id_editar['cuatrimestre'] == 'Anual') ? 'selected' : '' ?>>Anual</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="fecha_apertura">Fecha de Apertura (*):</label>
                                    <input type="date" id="fecha_apertura" name="fecha_apertura" value="<?= htmlspecialchars($periodo_id_editar['fecha_apertura'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="fecha_cierre">Fecha de Cierre (*):</label>
                                    <input type="date" id="fecha_cierre" name="fecha_cierre" value="<?= htmlspecialchars($periodo_id_editar['fecha_cierre'] ?? '') ?>" required>
                                </div>
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label for="descripcion">Descripción (opcional):</label>
                                    <input type="text" id="descripcion" name="descripcion" value="<?= htmlspecialchars($periodo_id_editar['descripcion'] ?? '') ?>">
                                </div>
                                <div class="form-group" style="align-self: center;">
                                    <label for="activo" style="display: flex; align-items: center; gap: 0.5rem;">
                                        <input type="checkbox" id="activo" name="activo" value="1" <?= (isset($periodo_id_editar['activo']) && $periodo_id_editar['activo'] == 1) || !$periodo_id_editar ? 'checked' : '' ?> style="width: auto;">
                                        Período Activo
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <?php if ($periodo_id_editar): ?>
                                <a href="admin_periodos_inscripcion.php" class="btn btn-secondary">Cancelar Edición</a>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary" style="margin-left:0.5rem;">
                                <i data-lucide="save"></i>
                                <?= $periodo_id_editar ? 'Actualizar Período' : 'Guardar Período' ?>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Períodos de Inscripción Existentes</h2>
                    </div>
                    <div class="card-content">
                        <div class="table-container">
                            <table class="styled-table">
                                <thead>
                                    <tr>
                                        <th>Ciclo Lectivo</th>
                                        <th>Cuatrimestre</th>
                                        <th>Apertura</th>
                                        <th>Cierre</th>
                                        <th>Descripción</th>
                                        <th>Estado</th>
                                        <th class="text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($lista_periodos)): ?>
                                        <?php foreach ($lista_periodos as $periodo): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($periodo['ciclo_lectivo']) ?></td>
                                                <td><?= htmlspecialchars($periodo['cuatrimestre']) ?></td>
                                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($periodo['fecha_apertura']))) ?></td>
                                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($periodo['fecha_cierre']))) ?></td>
                                                <td><?= htmlspecialchars($periodo['descripcion']) ?></td>
                                                <td>
                                                    <?php if ($periodo['activo']): ?>
                                                        <span class="badge badge-success"><i data-lucide="check-circle"></i> Activo</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger"><i data-lucide="x-circle"></i> Inactivo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="table-actions">
                                                    <a href="?accion=editar&id=<?= $periodo['id'] ?>" class="btn btn-outline btn-sm" title="Editar">
                                                        <i data-lucide="edit-2"></i>
                                                    </a>
                                                    <a href="?accion=eliminar&id=<?= $periodo['id'] ?>" class="btn btn-outline btn-danger-outline btn-sm" title="Eliminar" onclick="return confirm('¿Está seguro de que desea eliminar este período?');">
                                                        <i data-lucide="trash-2"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" style="text-align:center; padding: 2rem;">No hay períodos de inscripción definidos.</td>
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
        // Funciones del Sidebar y UI
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

        // Renderizar iconos
        lucide.createIcons();

        // Si se está editando, hacer scroll hasta el formulario
        <?php if ($periodo_id_editar): ?>
            window.addEventListener('DOMContentLoaded', (event) => {
                const formCard = document.querySelector('.card form');
                if (formCard) {
                    formCard.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>
<?php
$mysqli->close();
?>