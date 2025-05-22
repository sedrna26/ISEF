<?php
// asistencias.php - Gestión de asistencias para profesores y preceptores
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

// 1️⃣ Obtener profesor_id solo si es profesor
$profesor_id = null;
if ($tipo_usuario === 'profesor') {
    $profesor_res = $mysqli->query("
        SELECT p.id AS profesor_id
        FROM profesor p
        JOIN persona per ON p.persona_id = per.id
        WHERE per.usuario_id = $usuario_id
    ");
    $profesor = $profesor_res->fetch_assoc();
    $profesor_id = $profesor ? $profesor['profesor_id'] : null;
} else {
    // Si es preceptor, obtener un profesor_id válido (el primero disponible)
    // Esto es una solución provisional para evitar errores de restricción de clave foránea
    $profesor_res = $mysqli->query("SELECT id FROM profesor LIMIT 1");
    $profesor = $profesor_res->fetch_assoc();
    $profesor_id = $profesor ? $profesor['id'] : null;
}

// 2️⃣ Procesar POST (registro individual y en lote)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Registro individual (profesor)
    if (isset($_POST['inscripcion_cursado_id'], $_POST['fecha'], $_POST['estado']) && $tipo_usuario === 'profesor') {
        $stmt = $mysqli->prepare("INSERT INTO asistencia (inscripcion_cursado_id, fecha, estado, profesor_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $_POST['inscripcion_cursado_id'], $_POST['fecha'], $_POST['estado'], $profesor_id);
        $stmt->execute();
        $stmt->close();
        header("Location: asistencias.php");
        exit;
    }

    // Registro en lote (preceptor)
    if (isset($_POST['registro_lote']) && isset($_POST['fecha']) && isset($_POST['estado']) && $tipo_usuario === 'preceptor') {
        $fecha = $_POST['fecha'];
        foreach ($_POST['estado'] as $inscripcion_cursado_id => $estado) {
            if ($estado) {  // solo si seleccionaron un estado
                $stmt = $mysqli->prepare("INSERT INTO asistencia (inscripcion_cursado_id, fecha, estado, profesor_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("issi", $inscripcion_cursado_id, $fecha, $estado, $profesor_id);
                $stmt->execute();
                $stmt->close();
            }
        }
        $redirect_url = "asistencias.php";
        if (isset($_GET['curso_id'])) {
            $redirect_url .= "?curso_id=" . (int)$_GET['curso_id'];
            if (isset($_GET['materia_id'])) {
                $redirect_url .= "&materia_id=" . (int)$_GET['materia_id'];
            }
        }
        header("Location: $redirect_url");
        exit;
    }
}

// 3️⃣ Cargar datos según el rol
if ($tipo_usuario === 'profesor') {
    // Obtener lista de inscripciones para el profesor
    if ($profesor_id) {
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
    } else {
        $inscripciones = false;
    }
} else {
    // Preceptor: obtener cursos y materias
    $cursos_res = $mysqli->query("
        SELECT c.id, CONCAT(c.codigo, ' ', c.division, ' - ', c.ciclo_lectivo) AS curso_nombre
        FROM curso c
        ORDER BY c.ciclo_lectivo DESC, c.codigo, c.division
    ");

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
            SELECT ic.id AS inscripcion_cursado_id, a.legajo, CONCAT(p.apellidos, ', ', p.nombres) AS alumno_nombre, m.nombre AS materia_nombre
            FROM inscripcion_cursado ic
            JOIN alumno a ON ic.alumno_id = a.id
            JOIN persona p ON a.persona_id = p.id
            JOIN materia m ON ic.materia_id = m.id
            WHERE ic.curso_id = $curso_id AND ic.materia_id = $materia_id
            ORDER BY p.apellidos, p.nombres
        ");
    }
}

// Configuración de paginación
$registros_por_pagina = 20;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Obtener total de registros para la paginación
if ($tipo_usuario === 'profesor') {
    $total_registros = $mysqli->query("
        SELECT COUNT(*) as total FROM asistencia a 
        WHERE a.profesor_id = $profesor_id
    ")->fetch_assoc()['total'];
} else {
    $total_registros = $mysqli->query("
        SELECT COUNT(*) as total FROM asistencia
    ")->fetch_assoc()['total'];
}

$total_paginas = ceil($total_registros / $registros_por_pagina);

// 4️⃣ Obtener las asistencias registradas con paginación
if ($tipo_usuario === 'profesor') {
    // Para profesores: solo sus registros
    $asistencias = $mysqli->query("
        SELECT a.id, a.fecha, a.estado, 
               CONCAT(p.apellidos, ', ', p.nombres) as alumno_nombre,
               m.nombre as materia_nombre, 
               c.codigo as curso_codigo,
               CONCAT(pp.apellidos, ', ', pp.nombres) as profesor_nombre
        FROM asistencia a
        JOIN inscripcion_cursado ic ON a.inscripcion_cursado_id = ic.id
        JOIN alumno al ON ic.alumno_id = al.id
        JOIN persona p ON al.persona_id = p.id
        JOIN materia m ON ic.materia_id = m.id
        JOIN curso c ON ic.curso_id = c.id
        JOIN profesor prof ON a.profesor_id = prof.id
        JOIN persona pp ON prof.persona_id = pp.id
        WHERE a.profesor_id = $profesor_id
        ORDER BY a.fecha DESC
        LIMIT $registros_por_pagina OFFSET $offset
    ");
} else {
    // Para preceptores: mostrar todas las asistencias
    $asistencias = $mysqli->query("
        SELECT a.id, a.fecha, a.estado, 
               CONCAT(p.apellidos, ', ', p.nombres) as alumno_nombre,
               m.nombre as materia_nombre, 
               c.codigo as curso_codigo,
               CONCAT(pp.apellidos, ', ', pp.nombres) as profesor_nombre
        FROM asistencia a
        JOIN inscripcion_cursado ic ON a.inscripcion_cursado_id = ic.id
        JOIN alumno al ON ic.alumno_id = al.id
        JOIN persona p ON al.persona_id = p.id
        JOIN materia m ON ic.materia_id = m.id
        JOIN curso c ON ic.curso_id = c.id
        JOIN profesor prof ON a.profesor_id = prof.id
        JOIN persona pp ON prof.persona_id = pp.id
        ORDER BY a.fecha DESC
        LIMIT $registros_por_pagina OFFSET $offset
    ");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias - ISEF</title>
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Gestión de Asistencias</h1>
    <a href="dashboard.php">&laquo; Volver al menú</a>

    <?php if ($tipo_usuario === 'profesor'): ?>
        <h2>Registrar Asistencia (Profesor)</h2>
        <form method="post">
            <label>Alumno:
                <select name="inscripcion_cursado_id" required>
                    <option value="">-- Seleccione Alumno --</option>
                    <?php if ($inscripciones): ?>
                        <?php while ($i = $inscripciones->fetch_assoc()): ?>
                            <option value="<?= $i['inscripcion_cursado_id'] ?>">
                                <?= htmlspecialchars($i['alumno_nombre']) ?> | <?= htmlspecialchars($i['materia_nombre']) ?> | <?= htmlspecialchars($i['curso_codigo'] . ' - ' . $i['division']) ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </label><br><br>
            <label>Fecha: <input type="date" name="fecha" required></label><br><br>
            <label>Estado:
                <select name="estado" required>
                    <option value="Presente">Presente</option>
                    <option value="Ausente">Ausente</option>
                    <option value="Justificado">Justificado</option>
                </select>
            </label><br><br>
            <button type="submit">Registrar Asistencia</button>
        </form>
    <?php elseif ($tipo_usuario === 'preceptor'): ?>
        <h2>Seleccionar Curso y Materia (Preceptor)</h2>
        <form method="get">
            <label>Curso:
                <select name="curso_id" required onchange="this.form.submit()">
                    <option value="">-- Seleccione un curso --</option>
                    <?php while ($c = $cursos_res->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" <?= (isset($_GET['curso_id']) && $_GET['curso_id'] == $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['curso_nombre']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </label><br><br>

            <?php if ($materias_res && $materias_res->num_rows > 0): ?>
                <label>Materia:
                    <select name="materia_id" required>
                        <option value="">-- Seleccione una materia --</option>
                        <?php while ($m = $materias_res->fetch_assoc()): ?>
                            <option value="<?= $m['id'] ?>" <?= (isset($_GET['materia_id']) && $_GET['materia_id'] == $m['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['nombre']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </label>
                <button type="submit">Ver Alumnos</button>
            <?php endif; ?>
        </form>

        <?php if ($alumnos_res && $alumnos_res->num_rows > 0): ?>
            <h2>Registrar Asistencia para el Curso y Materia Seleccionados</h2>
            <form method="post">
                <input type="hidden" name="registro_lote" value="1">
                <label>Fecha: <input type="date" name="fecha" required></label><br><br>
                <table>
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($al = $alumnos_res->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($al['alumno_nombre']) ?></td>
                                <td>
                                    <select name="estado[<?= $al['inscripcion_cursado_id'] ?>]">
                                        <option value="">-- Sin marcar --</option>
                                        <option value="Presente">Presente</option>
                                        <option value="Ausente">Ausente</option>
                                        <option value="Justificado">Justificado</option>
                                    </select>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <br>
                <button type="submit">Registrar Asistencias</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <h2>Últimas Asistencias Registradas</h2>
    <table>
        <thead>
            <tr>
                <th>Alumno</th>
                <th>Materia</th>
                <th>Curso</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Profesor</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($a = $asistencias->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($a['alumno_nombre']) ?></td>
                    <td><?= htmlspecialchars($a['materia_nombre']) ?></td>
                    <td><?= htmlspecialchars($a['curso_codigo']) ?></td>
                    <td><?= htmlspecialchars($a['fecha']) ?></td>
                    <td><?= htmlspecialchars($a['estado']) ?></td>
                    <td><?= htmlspecialchars($a['profesor_nombre']) ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Controles de paginación -->
    <div style="margin-top: 20px; text-align: center;">
        <?php if ($total_paginas > 1): ?>
            <div class="paginacion">
                <?php if ($pagina_actual > 1): ?>
                    <a href="?pagina=1<?= isset($_GET['curso_id']) ? '&curso_id=' . $_GET['curso_id'] : '' ?><?= isset($_GET['materia_id']) ? '&materia_id=' . $_GET['materia_id'] : '' ?>">&laquo; Primera</a>
                    <a href="?pagina=<?= $pagina_actual - 1 ?><?= isset($_GET['curso_id']) ? '&curso_id=' . $_GET['curso_id'] : '' ?><?= isset($_GET['materia_id']) ? '&materia_id=' . $_GET['materia_id'] : '' ?>">&lsaquo; Anterior</a>
                <?php endif; ?>

                <span>Página <?= $pagina_actual ?> de <?= $total_paginas ?></span>

                <?php if ($pagina_actual < $total_paginas): ?>
                    <a href="?pagina=<?= $pagina_actual + 1 ?><?= isset($_GET['curso_id']) ? '&curso_id=' . $_GET['curso_id'] : '' ?><?= isset($_GET['materia_id']) ? '&materia_id=' . $_GET['materia_id'] : '' ?>">Siguiente &rsaquo;</a>
                    <a href="?pagina=<?= $total_paginas ?><?= isset($_GET['curso_id']) ? '&curso_id=' . $_GET['curso_id'] : '' ?><?= isset($_GET['materia_id']) ? '&materia_id=' . $_GET['materia_id'] : '' ?>">Última &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .paginacion {
            margin: 20px 0;
            text-align: center;
        }
        .paginacion a {
            color: #0066cc;
            padding: 8px 16px;
            text-decoration: none;
            border: 1px solid #ddd;
            margin: 0 4px;
        }
        .paginacion a:hover {
            background-color: #f2f2f2;
        }
        .paginacion span {
            padding: 8px 16px;
            margin: 0 4px;
        }
    </style>
</body>
</html>