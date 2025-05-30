<?php
// admin_mesas_examen.php - Gestión de mesas de examen para administradores
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: ../index.php");
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

$mensaje_feedback = '';

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crear_mesa'])) {
        $materia_id = (int)$_POST['materia_id'];
        $curso_id = (int)$_POST['curso_id'];
        $fecha = $_POST['fecha'];
        $tipo = $_POST['tipo'];
        $profesor_id = (int)$_POST['profesor_id'];
        $libro = $_POST['libro'] ? (int)$_POST['libro'] : null;
        $folio = $_POST['folio'] ? (int)$_POST['folio'] : null;

        $stmt = $mysqli->prepare("INSERT INTO acta_examen (materia_id, curso_id, fecha, tipo, profesor_id, libro, folio, cerrada) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->bind_param("iissiii", $materia_id, $curso_id, $fecha, $tipo, $profesor_id, $libro, $folio);

        if ($stmt->execute()) {
            $mensaje_feedback = "Mesa de examen creada exitosamente.";
        } else {
            $mensaje_feedback = "Error al crear la mesa de examen: " . $stmt->error;
        }
        $stmt->close();
    }

    if (isset($_POST['cerrar_mesa'])) {
        $mesa_id = (int)$_POST['mesa_id'];
        $stmt = $mysqli->prepare("UPDATE acta_examen SET cerrada = 1 WHERE id = ?");
        $stmt->bind_param("i", $mesa_id);

        if ($stmt->execute()) {
            $mensaje_feedback = "Mesa de examen cerrada exitosamente.";
        } else {
            $mensaje_feedback = "Error al cerrar la mesa de examen.";
        }
        $stmt->close();
    }

    if (isset($_POST['eliminar_mesa'])) {
        $mesa_id = (int)$_POST['mesa_id'];

        // Verificar si hay inscripciones
        $check_inscripciones = $mysqli->prepare("SELECT COUNT(*) as total FROM inscripcion_examen WHERE acta_examen_id = ?");
        $check_inscripciones->bind_param("i", $mesa_id);
        $check_inscripciones->execute();
        $result_check = $check_inscripciones->get_result();
        $inscripciones = $result_check->fetch_assoc();
        $check_inscripciones->close();

        if ($inscripciones['total'] > 0) {
            $mensaje_feedback = "No se puede eliminar la mesa porque tiene inscripciones registradas.";
        } else {
            $stmt = $mysqli->prepare("DELETE FROM acta_examen WHERE id = ?");
            $stmt->bind_param("i", $mesa_id);

            if ($stmt->execute()) {
                $mensaje_feedback = "Mesa de examen eliminada exitosamente.";
            } else {
                $mensaje_feedback = "Error al eliminar la mesa de examen.";
            }
            $stmt->close();
        }
    }
}

// Obtener datos para el formulario
$materias = $mysqli->query("SELECT id, nombre FROM materia ORDER BY nombre");
$cursos = $mysqli->query("SELECT id, CONCAT(codigo, ' ', division, ' - ', ciclo_lectivo) as nombre FROM curso ORDER BY ciclo_lectivo DESC, codigo");
$profesores = $mysqli->query("
    SELECT pr.id, CONCAT(p.apellidos, ', ', p.nombres) as nombre 
    FROM profesor pr 
    JOIN persona p ON pr.persona_id = p.id 
    ORDER BY p.apellidos, p.nombres
");

// Obtener mesas existentes
$mesas_query = "
    SELECT ae.id, ae.fecha, ae.tipo, ae.libro, ae.folio, ae.cerrada,
           m.nombre as materia_nombre,
           CONCAT(c.codigo, ' ', c.division, ' - ', c.ciclo_lectivo) as curso_nombre,
           CONCAT(p.apellidos, ', ', p.nombres) as profesor_nombre,
           COUNT(ie.id) as total_inscriptos
    FROM acta_examen ae
    JOIN materia m ON ae.materia_id = m.id
    JOIN curso c ON ae.curso_id = c.id
    JOIN profesor pr ON ae.profesor_id = pr.id
    JOIN persona p ON pr.persona_id = p.id
    LEFT JOIN inscripcion_examen ie ON ae.id = ie.acta_examen_id
    GROUP BY ae.id
    ORDER BY ae.fecha DESC, m.nombre
";
$mesas_result = $mysqli->query($mesas_query);

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Gestión de Mesas de Examen - ISEF</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .form-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            align-items: end;
        }

        .form-group {
            flex: 1;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        select,
        input[type="date"],
        input[type="number"],
        button {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        button {
            background-color: #007bff;
            color: white;
            cursor: pointer;
            border: none;
            font-weight: bold;
        }

        button:hover {
            background-color: #0056b3;
        }

        .btn-danger {
            background-color: #dc3545;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background-color: #e0a800;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .status-cerrada {
            color: #dc3545;
            font-weight: bold;
        }

        .status-abierta {
            color: #28a745;
            font-weight: bold;
        }

        .feedback {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }

        .actions {
            display: flex;
            gap: 5px;
        }

        .actions button {
            padding: 5px 10px;
            font-size: 12px;
            min-width: auto;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Gestión de Mesas de Examen</h1>
        <p><a href="dashboard.php">&laquo; Volver al menú</a></p>

        <?php if ($mensaje_feedback): ?>
            <div class="feedback"><?= htmlspecialchars($mensaje_feedback) ?></div>
        <?php endif; ?>

        <div class="form-section">
            <h2>Crear Nueva Mesa de Examen</h2>
            <form method="POST">
                <input type="hidden" name="crear_mesa" value="1">

                <div class="form-row">
                    <div class="form-group">
                        <label for="materia_id">Materia:</label>
                        <select name="materia_id" required>
                            <option value="">-- Seleccione Materia --</option>
                            <?php while ($materia = $materias->fetch_assoc()): ?>
                                <option value="<?= $materia['id'] ?>"><?= htmlspecialchars($materia['nombre']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="curso_id">Curso:</label>
                        <select name="curso_id" required>
                            <option value="">-- Seleccione Curso --</option>
                            <?php while ($curso = $cursos->fetch_assoc()): ?>
                                <option value="<?= $curso['id'] ?>"><?= htmlspecialchars($curso['nombre']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fecha">Fecha del Examen:</label>
                        <input type="date" name="fecha" required min="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="tipo">Tipo:</label>
                        <select name="tipo" required>
                            <option value="">-- Seleccione Tipo --</option>
                            <option value="1°Cuatrimestre">1° Cuatrimestre</option>
                            <option value="2°Cuatrimestre">2° Cuatrimestre</option>
                            <option value="Anual">Anual</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="profesor_id">Profesor:</label>
                        <select name="profesor_id" required>
                            <option value="">-- Seleccione Profesor --</option>
                            <?php while ($profesor = $profesores->fetch_assoc()): ?>
                                <option value="<?= $profesor['id'] ?>"><?= htmlspecialchars($profesor['nombre']) ?></option>
                            <?php endwhile; ?>
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

                    <div class="form-group">
                        <button type="submit">Crear Mesa</button>
                    </div>
                </div>
            </form>
        </div>

        <h2>Mesas de Examen Existentes</h2>
        <?php if ($mesas_result && $mesas_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Materia</th>
                        <th>Curso</th>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Profesor</th>
                        <th>Libro/Folio</th>
                        <th>Inscriptos</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($mesa = $mesas_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($mesa['materia_nombre']) ?></td>
                            <td><?= htmlspecialchars($mesa['curso_nombre']) ?></td>
                            <td><?= date('d/m/Y', strtotime($mesa['fecha'])) ?></td>
                            <td><?= htmlspecialchars($mesa['tipo']) ?></td>
                            <td><?= htmlspecialchars($mesa['profesor_nombre']) ?></td>
                            <td>
                                <?php if ($mesa['libro'] || $mesa['folio']): ?>
                                    <?= $mesa['libro'] ? "L: " . $mesa['libro'] : "" ?>
                                    <?= $mesa['folio'] ? " F: " . $mesa['folio'] : "" ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= $mesa['total_inscriptos'] ?></td>
                            <td>
                                <?php if ($mesa['cerrada']): ?>
                                    <span class="status-cerrada">CERRADA</span>
                                <?php else: ?>
                                    <span class="status-abierta">ABIERTA</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <?php if (!$mesa['cerrada']): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Está seguro de cerrar esta mesa?')">
                                            <input type="hidden" name="cerrar_mesa" value="1">
                                            <input type="hidden" name="mesa_id" value="<?= $mesa['id'] ?>">
                                            <button type="submit" class="btn-warning">Cerrar</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($mesa['total_inscriptos'] == 0): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Está seguro de eliminar esta mesa?')">
                                            <input type="hidden" name="eliminar_mesa" value="1">
                                            <input type="hidden" name="mesa_id" value="<?= $mesa['id'] ?>">
                                            <button type="submit" class="btn-danger">Eliminar</button>
                                        </form>
                                    <?php endif; ?>

                                    <a href="ver_inscriptos_mesa.php?mesa_id=<?= $mesa['id'] ?>" style="text-decoration: none;">
                                        <button type="button">Ver Inscriptos</button>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay mesas de examen registradas.</p>
        <?php endif; ?>
    </div>
</body>

</html>

<?php
$mysqli->close();
?>