<?php
// evaluaciones.php - Gestión de evaluaciones
session_start();
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['tipo'], ['profesor', 'preceptor'])) {
    header("Location: ../index.php");
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

$usuario_id = $_SESSION['usuario_id'];
$tipo_usuario = $_SESSION['tipo'];
$profesor_id = null;

// Obtener profesor_id si aplica
if ($tipo_usuario === 'profesor') {
    $res = $mysqli->query("
        SELECT p.id FROM profesor p
        JOIN persona per ON p.persona_id = per.id
        WHERE per.usuario_id = $usuario_id
    ");
    $data = $res->fetch_assoc();
    $profesor_id = $data ? $data['id'] : null;
}

// Procesamiento de POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Registro individual (profesor)
    if (isset($_POST['inscripcion_cursado_id'], $_POST['fecha'], $_POST['tipo'], $_POST['instancia']) && $tipo_usuario === 'profesor') {
        $stmt = $mysqli->prepare("INSERT INTO evaluacion (inscripcion_cursado_id, tipo, instancia, fecha, nota, nota_letra, profesor_id, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssisss", $_POST['inscripcion_cursado_id'], $_POST['tipo'], $_POST['instancia'], $_POST['fecha'], $_POST['nota'], $_POST['nota_letra'], $profesor_id, $_POST['observaciones']);
        $stmt->execute();
        $stmt->close();
        header("Location: evaluaciones.php");
        exit;
    }

    // Registro en lote (preceptor)
    if ($tipo_usuario === 'preceptor' && isset($_POST['registro_lote'], $_POST['fecha'], $_POST['tipo'], $_POST['instancia'])) {
        $fecha = $_POST['fecha'];
        $tipo = $_POST['tipo'];
        $instancia = $_POST['instancia'];

        foreach ($_POST['nota'] as $ic_id => $nota) {
            if ($nota !== '') {
                $nota_letra = $_POST['nota_letra'][$ic_id] ?? '';
                $obs = $_POST['observaciones'][$ic_id] ?? '';

                $stmt = $mysqli->prepare("INSERT INTO evaluacion (inscripcion_cursado_id, tipo, instancia, fecha, nota, nota_letra, profesor_id, observaciones) VALUES (?, ?, ?, ?, ?, ?, NULL, ?)");
                $stmt->bind_param("isssiss", $ic_id, $tipo, $instancia, $fecha, $nota, $nota_letra, $obs);
                $stmt->execute();
                $stmt->close();
            }
        }

        header("Location: evaluaciones.php?curso_id={$_GET['curso_id']}&materia_id={$_GET['materia_id']}");
        exit;
    }
}

// Datos para profesores
$inscripciones = [];
if ($tipo_usuario === 'profesor' && $profesor_id) {
    $inscripciones = $mysqli->query("
        SELECT ic.id AS inscripcion_cursado_id, a.legajo, CONCAT(p.apellidos, ', ', p.nombres) AS alumno_nombre,
               m.nombre AS materia_nombre, c.codigo AS curso_codigo, c.division
        FROM inscripcion_cursado ic
        JOIN alumno a ON ic.alumno_id = a.id
        JOIN persona p ON a.persona_id = p.id
        JOIN materia m ON ic.materia_id = m.id
        JOIN curso c ON ic.curso_id = c.id
        JOIN profesor_materia pm ON pm.materia_id = m.id AND pm.curso_id = c.id
        WHERE pm.profesor_id = $profesor_id
        ORDER BY p.apellidos, p.nombres
    ");
}

// Datos para preceptores
if ($tipo_usuario === 'preceptor') {
    $cursos_res = $mysqli->query("SELECT id, CONCAT(codigo, ' ', division, ' - ', ciclo_lectivo) AS nombre FROM curso ORDER BY ciclo_lectivo DESC, codigo");

    $materias_res = null;
    if (isset($_GET['curso_id'])) {
        $curso_id = (int)$_GET['curso_id'];
        $materias_res = $mysqli->query("
            SELECT DISTINCT m.id, m.nombre
            FROM inscripcion_cursado ic
            JOIN materia m ON ic.materia_id = m.id
            WHERE ic.curso_id = $curso_id
            ORDER BY m.nombre
        ");
    }

    $alumnos_res = null;
    if (isset($_GET['curso_id'], $_GET['materia_id'])) {
        $curso_id = (int)$_GET['curso_id'];
        $materia_id = (int)$_GET['materia_id'];
        $alumnos_res = $mysqli->query("
            SELECT ic.id AS inscripcion_cursado_id, CONCAT(p.apellidos, ', ', p.nombres) AS alumno_nombre
            FROM inscripcion_cursado ic
            JOIN alumno a ON ic.alumno_id = a.id
            JOIN persona p ON a.persona_id = p.id
            WHERE ic.curso_id = $curso_id AND ic.materia_id = $materia_id
            ORDER BY p.apellidos, p.nombres
        ");
    }
}

// Últimas evaluaciones
$evaluaciones = $mysqli->query("
    SELECT e.fecha, e.tipo, e.instancia, e.nota, e.nota_letra, e.observaciones,
           CONCAT(p.apellidos, ', ', p.nombres) AS alumno_nombre,
           m.nombre AS materia_nombre, c.codigo AS curso_codigo
    FROM evaluacion e
    JOIN inscripcion_cursado ic ON e.inscripcion_cursado_id = ic.id
    JOIN alumno a ON ic.alumno_id = a.id
    JOIN persona p ON a.persona_id = p.id
    JOIN materia m ON ic.materia_id = m.id
    JOIN curso c ON ic.curso_id = c.id
    ORDER BY e.fecha DESC
    LIMIT 50
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Evaluaciones - ISEF</title>
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 6px; }
    </style>
</head>
<body>
    <h1>Gestión de Evaluaciones</h1>
    <a href="dashboard.php">&laquo; Volver al menú</a>

    <?php if ($tipo_usuario === 'profesor'): ?>
        <h2>Registro Individual (Profesor)</h2>
        <form method="post">
            <label>Alumno:
                <select name="inscripcion_cursado_id" required>
                    <?php while ($i = $inscripciones->fetch_assoc()): ?>
                        <option value="<?= $i['inscripcion_cursado_id'] ?>">
                            <?= htmlspecialchars($i['alumno_nombre']) ?> | <?= $i['materia_nombre'] ?> | <?= $i['curso_codigo'] . ' ' . $i['division'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </label><br><br>
            <label>Fecha: <input type="date" name="fecha" required></label><br>
            <label>Tipo:
                <select name="tipo" required>
                    <option value="Parcial">Parcial</option>
                    <option value="Final">Final</option>
                    <option value="Coloquio">Coloquio</option>
                </select>
            </label><br>
            <label>Instancia:
                <select name="instancia" required>
                    <option value="1°Cuatrimestre">1° Cuatrimestre</option>
                    <option value="2°Cuatrimestre">2° Cuatrimestre</option>
                    <option value="Anual">Anual</option>
                </select>
            </label><br>
            <label>Nota: <input type="number" name="nota" min="1" max="10"></label><br>
            <label>Nota Letra: <input type="text" name="nota_letra"></label><br>
            <label>Observaciones: <textarea name="observaciones"></textarea></label><br><br>
            <button type="submit">Registrar</button>
        </form>
    <?php elseif ($tipo_usuario === 'preceptor'): ?>
        <h2>Registro (Preceptor)</h2>
        <form method="get">
            <label>Curso:
                <select name="curso_id" required onchange="this.form.submit()">
                    <option value="">-- Seleccione --</option>
                    <?php while ($c = $cursos_res->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" <?= ($_GET['curso_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nombre']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </label>
            <?php if ($materias_res): ?>
                <label>Materia:
                    <select name="materia_id" required onchange="this.form.submit()">
                        <option value="">-- Seleccione --</option>
                        <?php while ($m = $materias_res->fetch_assoc()): ?>
                            <option value="<?= $m['id'] ?>" <?= ($_GET['materia_id'] ?? '') == $m['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['nombre']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </label>
            <?php endif; ?>
        </form>

        <?php if ($alumnos_res): ?>
            <form method="post">
                <input type="hidden" name="registro_lote" value="1">
                <label>Fecha: <input type="date" name="fecha" required></label><br>
                <label>Tipo:
                    <select name="tipo" required>
                        <option value="Parcial">Parcial</option>
                        <option value="Final">Final</option>
                        <option value="Coloquio">Coloquio</option>
                    </select>
                </label>
                <label>Instancia:
                    <select name="instancia" required>
                        <option value="1°Cuatrimestre">1° Cuatrimestre</option>
                        <option value="2°Cuatrimestre">2° Cuatrimestre</option>
                        <option value="Anual">Anual</option>
                    </select>
                </label><br><br>
                <table>
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <th>Nota</th>
                            <th>Letra</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($a = $alumnos_res->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($a['alumno_nombre']) ?></td>
                                <td><input type="number" name="nota[<?= $a['inscripcion_cursado_id'] ?>]" min="1" max="10"></td>
                                <td><input type="text" name="nota_letra[<?= $a['inscripcion_cursado_id'] ?>]"></td>
                                <td><input type="text" name="observaciones[<?= $a['inscripcion_cursado_id'] ?>]"></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table><br>
                <button type="submit">Registrar Evaluaciones</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <h2>Últimas Evaluaciones Registradas</h2>
    <table>
        <thead>
            <tr>
                <th>Alumno</th>
                <th>Materia</th>
                <th>Curso</th>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Instancia</th>
                <th>Nota</th>
                <th>Letra</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($e = $evaluaciones->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($e['alumno_nombre']) ?></td>
                    <td><?= htmlspecialchars($e['materia_nombre']) ?></td>
                    <td><?= htmlspecialchars($e['curso_codigo']) ?></td>
                    <td><?= htmlspecialchars($e['fecha']) ?></td>
                    <td><?= htmlspecialchars($e['tipo']) ?></td>
                    <td><?= htmlspecialchars($e['instancia']) ?></td>
                    <td><?= htmlspecialchars($e['nota']) ?></td>
                    <td><?= htmlspecialchars($e['nota_letra']) ?></td>
                    <td><?= htmlspecialchars($e['observaciones']) ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
