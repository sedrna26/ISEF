<?php
// cursos.php - Gestión de cursos
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: index.php");
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['accion'] === 'crear') {
        $stmt = $mysqli->prepare("INSERT INTO curso (codigo, division, anio, turno, ciclo_lectivo) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $_POST['codigo'], $_POST['division'], $_POST['anio'], $_POST['turno'], $_POST['ciclo_lectivo']);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['accion'] === 'modificar') {
        if (isset($_POST['edit_curso_id'], $_POST['codigo'], $_POST['division'], $_POST['anio'], $_POST['turno'], $_POST['ciclo_lectivo']) && !empty($_POST['edit_curso_id'])) {
            $stmt = $mysqli->prepare("UPDATE curso SET codigo = ?, division = ?, anio = ?, turno = ?, ciclo_lectivo = ? WHERE id = ?");
            $stmt->bind_param("ssssii", $_POST['codigo'], $_POST['division'], $_POST['anio'], $_POST['turno'], $_POST['ciclo_lectivo'], $_POST['edit_curso_id']);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($_POST['accion'] === 'borrar') {
        $stmt = $mysqli->prepare("DELETE FROM curso WHERE id = ?");
        $stmt->bind_param("i", $_POST['curso_id']);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: cursos.php");
    exit;
}

$cursos = $mysqli->query("SELECT * FROM curso ORDER BY ciclo_lectivo DESC, codigo, division");

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cursos - Sistema ISEF</title>
    <link rel="icon" href="../sources/logo_recortado.ico" type="image/x-icon">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="../style/style.css">
</head>

<body class="cursos">
    <div class="app-container">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/ISEF/views/includes/nav.php'; ?>
        <main class="main-content">
            <header class="header">

                <nav class="breadcrumb">
                    <a href="dashboard.php">Sistema de Gestión ISEF</a>
                    <span>/</span>
                    <span>Cursos</span>
                </nav>

            </header>
            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title" id="formCursoTitulo">Nuevo Curso</h2>
                        <p class="card-description">Complete los datos para agregar o modificar un curso.</p>
                    </div>
                    <div class="card-content">
                        <form method="post" id="formGestionCurso" autocomplete="off">
                            <input type="hidden" name="accion" value="crear">
                            <input type="hidden" name="edit_curso_id" id="edit_curso_id" value="">
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="codigo">Código:</label>
                                        <input type="text" id="codigo" name="codigo" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="division">División:</label>
                                        <input type="text" id="division" name="division" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="anio">Año:</label>
                                        <input type="text" id="anio" name="anio" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="turno">Turno:</label>
                                        <select id="turno" name="turno" required>
                                            <option value="Mañana">Mañana</option>
                                            <option value="Tarde">Tarde</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="ciclo_lectivo">Ciclo Lectivo:</label>
                                        <input type="number" id="ciclo_lectivo" name="ciclo_lectivo" required value="<?php echo date('Y'); ?>">
                                    </div>
                                </div>
                            </div>
                            <div style="margin-top: 10px;">
                                <button type="submit" class="save"><i data-lucide="plus"></i> <span id="submitButtonText">Crear Curso</span></button>
                                <button type="button" class="cancel" id="cancelarEdicionBtnCurso" onclick="cancelarEdicionCurso()" style="display:none;"><i data-lucide="x"></i> Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Listado de Cursos</h2>
                    </div>
                    <div class="card-content">
                        <div class="table-container">
                            <table class="styled-table">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>División</th>
                                        <th>Año</th>
                                        <th>Turno</th>
                                        <th>Ciclo Lectivo</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($c = $cursos->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($c['codigo']) ?></td>
                                            <td><?= htmlspecialchars($c['division']) ?></td>
                                            <td><?= htmlspecialchars($c['anio']) ?></td>
                                            <td><?= htmlspecialchars($c['turno']) ?></td>
                                            <td><?= htmlspecialchars($c['ciclo_lectivo']) ?></td>
                                            <td class="actions-cell">
                                                <button type="button" class="edit" onclick="prepararEdicionCurso(
                                                <?= $c['id'] ?>,
                                                '<?= htmlspecialchars(addslashes($c['codigo'])) ?>',
                                                '<?= htmlspecialchars(addslashes($c['division'])) ?>',
                                                '<?= htmlspecialchars(addslashes($c['anio'])) ?>',
                                                '<?= $c['turno'] ?>',
                                                <?= $c['ciclo_lectivo'] ?>
                                            )"><i data-lucide="edit-2"></i> Editar</button>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar este curso?')">
                                                    <input type="hidden" name="accion" value="borrar">
                                                    <input type="hidden" name="curso_id" value="<?= $c['id'] ?>">
                                                    <button type="submit" class="delete"><i data-lucide="trash-2"></i> Eliminar</button>
                                                </form>
                                            </td>
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

        function prepararEdicionCurso(id, codigo, division, anio, turno, cicloLectivo) {
            const form = document.getElementById('formGestionCurso');
            form.querySelector('input[name="accion"]').value = 'modificar';
            form.querySelector('input[name="edit_curso_id"]').value = id;
            form.querySelector('input[name="codigo"]').value = codigo;
            form.querySelector('input[name="division"]').value = division;
            form.querySelector('input[name="anio"]').value = anio;
            form.querySelector('select[name="turno"]').value = turno;
            form.querySelector('input[name="ciclo_lectivo"]').value = cicloLectivo;
            form.querySelector('button[type="submit"]').innerHTML = '<i data-lucide="save"></i> Guardar Cambios';
            document.getElementById('formCursoTitulo').textContent = 'Modificar Curso';
            document.getElementById('cancelarEdicionBtnCurso').style.display = 'inline-block';
            lucide.createIcons();
            form.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        function cancelarEdicionCurso() {
            const form = document.getElementById('formGestionCurso');
            form.querySelector('input[name="accion"]').value = 'crear';
            form.querySelector('input[name="edit_curso_id"]').value = '';
            form.reset();
            form.querySelector('input[name="ciclo_lectivo"]').value = new Date().getFullYear();
            form.querySelector('button[type="submit"]').innerHTML = '<i data-lucide="plus"></i> Crear Curso';
            document.getElementById('formCursoTitulo').textContent = 'Nuevo Curso';
            document.getElementById('cancelarEdicionBtnCurso').style.display = 'none';
            lucide.createIcons();
        }
        document.addEventListener('DOMContentLoaded', function() {
            const cicloLectivoInput = document.querySelector('#formGestionCurso input[name="ciclo_lectivo"]');
            if (!cicloLectivoInput.value || document.getElementById('formGestionCurso').querySelector('input[name="accion"]').value === 'crear') {
                const editCursoId = document.getElementById('formGestionCurso').querySelector('input[name="edit_curso_id"]').value;
                if (!editCursoId) {
                    cicloLectivoInput.value = new Date().getFullYear();
                }
            }
            lucide.createIcons();
        });
    </script>
</body>

</html>
<?php if ($mysqli) {
    $mysqli->close();
} ?>