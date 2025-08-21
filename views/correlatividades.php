<?php
// correlatividades.php - Gestión de correlatividades entre materias
session_start();
// 1. Verificación de sesión y tipo de usuario
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: ../index.php");
    exit;
}

// 2. Incluir el archivo de conexión a la base de datos
require_once '../config/db.php';

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

$mensaje = '';
$error = '';

// Procesar creación, modificación o eliminación de correlatividades
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['accion'] === 'crear') {
        $stmt = $mysqli->prepare("INSERT INTO correlatividad (materia_id, materia_correlativa_id, tipo) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $_POST['materia_id'], $_POST['materia_correlativa_id'], $_POST['tipo']);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['accion'] === 'modificar') {
        if (isset($_POST['edit_correlatividad_id'], $_POST['materia_id'], $_POST['materia_correlativa_id'], $_POST['tipo'])) {
            $stmt = $mysqli->prepare("UPDATE correlatividad SET materia_id = ?, materia_correlativa_id = ?, tipo = ? WHERE id = ?");
            $stmt->bind_param(
                "iisi",
                $_POST['materia_id'],
                $_POST['materia_correlativa_id'],
                $_POST['tipo'],
                $_POST['edit_correlatividad_id']
            );

            if ($stmt->execute()) {
                $_SESSION['mensaje_exito'] = "Correlatividad actualizada correctamente.";
            } else {
                $_SESSION['mensaje_error'] = "Error al actualizar la correlatividad.";
            }
            $stmt->close();
        }
    } elseif ($_POST['accion'] === 'eliminar') {
        $stmt = $mysqli->prepare("DELETE FROM correlatividad WHERE id = ?");
        $stmt->bind_param("i", $_POST['correlatividad_id']);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: correlatividades.php");
    exit;
}

// Recuperar mensajes de la sesión
if (isset($_SESSION['mensaje_exito'])) {
    $mensaje = $_SESSION['mensaje_exito'];
    unset($_SESSION['mensaje_exito']);
}
if (isset($_SESSION['mensaje_error'])) {
    $error = $_SESSION['mensaje_error'];
    unset($_SESSION['mensaje_error']);
}

// Obtener datos necesarios
$correlatividades = $mysqli->query("
    SELECT c.id as correlatividad_id,
           m1.id as materia_id_actual,
           m1.nombre as materia_nombre,
           m2.id as materia_correlativa_id_actual, 
           m2.nombre as materia_correlativa_nombre,
           c.tipo
    FROM correlatividad c
    JOIN materia m1 ON c.materia_id = m1.id
    JOIN materia m2 ON c.materia_correlativa_id = m2.id
    ORDER BY m1.nombre, m2.nombre
");

$materias = $mysqli->query("SELECT id, nombre FROM materia ORDER BY nombre");

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Correlatividades - Sistema ISEF</title>
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
                    <span>Correlatividades</span>
                </nav>
            </header>

            <div class="content">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Gestión de Correlatividades</h1>
                        <p class="page-subtitle">Administra las correlatividades entre materias.</p>
                    </div>
                    <button class="btn btn-primary" onclick="mostrarFormCreacion()">
                        <i data-lucide="plus"></i> Nueva Correlatividad
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
                        <h2 class="card-title">Registrar Nueva Correlatividad</h2>
                        <p class="card-description">Completa los datos para agregar una nueva correlatividad.</p>
                    </div>
                    <form method="post">
                        <input type="hidden" name="accion" value="crear">
                        <div class="card-content">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="materia_id">Materia:</label>
                                    <select id="materia_id" name="materia_id" required>
                                        <?php while ($materia = $materias->fetch_assoc()): ?>
                                            <option value="<?= $materia['id'] ?>"><?= htmlspecialchars($materia['nombre']) ?></option>
                                        <?php endwhile; ?>
                                        <?php $materias->data_seek(0); // Reiniciar el puntero para el otro select 
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="materia_correlativa_id">Correlativa:</label>
                                    <select id="materia_correlativa_id" name="materia_correlativa_id" required>
                                        <?php while ($materia = $materias->fetch_assoc()): ?>
                                            <option value="<?= $materia['id'] ?>"><?= htmlspecialchars($materia['nombre']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="tipo">Tipo:</label>
                                    <select id="tipo" name="tipo" required>
                                        <option value="cursada">Cursada</option>
                                        <option value="final">Final</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Guardar Correlatividad</button>
                            <button type="button" class="btn btn-secondary" onclick="ocultarFormCreacion()">Cancelar</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Lista de Correlatividades</h2>
                        <p class="card-description">Tabla de correlatividades existentes.</p>
                    </div>
                    <div class="card-content table-responsive">
                        <table class="styled-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Materia</th>
                                    <th>Materia Correlativa</th>
                                    <th>Tipo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($correlatividades && $correlatividades->num_rows > 0): ?>
                                    <?php while ($corr = $correlatividades->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($corr['correlatividad_id']) ?></td>
                                            <td><?= htmlspecialchars($corr['materia_nombre']) ?></td>
                                            <td><?= htmlspecialchars($corr['materia_correlativa_nombre']) ?></td>
                                            <td>
                                                <span class="badge <?= $corr['tipo'] === 'cursada' ? 'badge-success' : 'badge-danger' ?>">
                                                    <i data-lucide="<?= $corr['tipo'] === 'cursada' ? 'check-circle' : 'book-open' ?>"></i>
                                                    <?= ucfirst(htmlspecialchars($corr['tipo'])) ?>
                                                </span>
                                            </td>
                                            <td class="table-actions">
                                                <button class="btn btn-outline btn-sm"
                                                    onclick="mostrarFormEdicion('<?= $corr['correlatividad_id'] ?>', '<?= $corr['materia_id_actual'] ?>', '<?= $corr['materia_correlativa_id_actual'] ?>', '<?= $corr['tipo'] ?>')"
                                                    title="Editar Correlatividad">
                                                    <i data-lucide="edit-2"></i>
                                                </button>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('¿Está seguro de eliminar esta correlatividad?');">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="correlatividad_id" value="<?= $corr['correlatividad_id'] ?>">
                                                    <button type="submit" class="btn btn-outline btn-danger-outline btn-sm" title="Eliminar Correlatividad">
                                                        <i data-lucide="trash-2"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center;">No hay correlatividades registradas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <!-- Modal de Edición -->
    <div id="edicionFormContainer" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div class="modal-content card">
            <div class="card-header">
                <h2 class="card-title">Editar Correlatividad</h2>
                <p class="card-description">Modifica la información de la correlatividad seleccionada.</p>
            </div>
            <form method="post" id="form-editar">
                <input type="hidden" name="accion" value="modificar">
                <input type="hidden" name="edit_correlatividad_id" id="edit_correlatividad_id">
                <div class="card-content">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit_materia_id">Materia:</label>
                            <select id="edit_materia_id" name="materia_id" required>
                                <?php
                                $materias->data_seek(0);
                                while ($materia = $materias->fetch_assoc()): ?>
                                    <option value="<?= $materia['id'] ?>"><?= htmlspecialchars($materia['nombre']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_materia_correlativa_id">Correlativa:</label>
                            <select id="edit_materia_correlativa_id" name="materia_correlativa_id" required>
                                <?php
                                $materias->data_seek(0);
                                while ($materia = $materias->fetch_assoc()): ?>
                                    <option value="<?= $materia['id'] ?>"><?= htmlspecialchars($materia['nombre']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_tipo">Tipo:</label>
                            <select id="edit_tipo" name="tipo" required>
                                <option value="cursada">Cursada</option>
                                <option value="final">Final</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-secondary" onclick="ocultarFormEdicion()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="margin-left:0.5rem;">
                        <i data-lucide="save"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }

        function mostrarFormCreacion() {
            const formCard = document.getElementById('creacionFormCard');
            formCard.style.display = 'block';
        }

        function ocultarFormCreacion() {
            const formCard = document.getElementById('creacionFormCard');
            formCard.style.display = 'none';
        }

        function mostrarFormEdicion(correlatividad_id, materia_id, materia_correlativa_id, tipo) {
            document.getElementById('edit_correlatividad_id').value = correlatividad_id;
            document.getElementById('edit_materia_id').value = materia_id;
            document.getElementById('edit_materia_correlativa_id').value = materia_correlativa_id;
            document.getElementById('edit_tipo').value = tipo;

            document.getElementById('edicionFormContainer').style.display = 'flex';
        }

        function ocultarFormEdicion() {
            document.getElementById('edicionFormContainer').style.display = 'none';
            document.getElementById('form-editar').reset();
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('edicionFormContainer').addEventListener('click', function(event) {
            if (event.target === this) {
                ocultarFormEdicion();
            }
        });

        // Asegurarse que el modal esté cerrado al cargar la página
        window.onload = function() {
            document.getElementById('edicionFormContainer').style.display = 'none';
            lucide.createIcons();
        };

        // Manejar el cierre de sesión
        document.querySelector('.logout-btn').addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = '../views/logout.php';
        });
    </script>
</body>

</html>