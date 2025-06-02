<?php

include '../tools/funciones_inscripcion.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'alumno') {
    header("Location: ../index.php");
    exit;
}

// Obtener alumno_id desde la base de datos si no está en la sesión
if (!isset($_SESSION['alumno_id_db'])) {
    $stmt = $mysqli->prepare("SELECT a.id FROM alumno a 
                            JOIN persona p ON a.persona_id = p.id 
                            JOIN usuario u ON p.usuario_id = u.id 
                            WHERE u.id = ?");
    $stmt->bind_param("i", $_SESSION['usuario_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $_SESSION['alumno_id_db'] = $row['id'];
    } else {
        die("Error: No se encontró el registro de alumno asociado a este usuario.");
    }
    $stmt->close();
}

$alumno_id = $_SESSION['alumno_id_db'];
$ciclo_lectivo_actual = date("Y");

// Obtener materias
$result_materias = $mysqli->query("SELECT * FROM materia ORDER BY anio, nro_orden");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inscripción a Materias - ISEF</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f7f6; color: #333; }
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        h1, h2 { color: #2c3e50; margin-bottom: 20px; }
        h1 { font-size: 1.8em; }
        h2 { font-size: 1.4em; }
        a { color: #3498db; text-decoration: none; }
        a:hover { text-decoration: underline; }

        .nav-link {
            display: inline-block;
            margin-bottom: 20px;
            padding: 8px 15px;
            background-color: #6c757d;
            color: white;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .nav-link:hover {
            background-color: #5a6268;
            text-decoration: none;
        }

        .materia-card {
            margin-bottom: 25px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #fdfdfd;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .materia-header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }

        .materia-title {
            font-size: 1.2em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .materia-info {
            color: #6c757d;
            font-size: 0.9em;
        }

        .status-message {
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 0.9em;
        }

        .status-inscripto {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-no-cursos {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .status-periodo-inactivo {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .form-inscripcion {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            color: #555;
            font-size: 0.9em;
        }

        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 0.95em;
            background-color: white;
        }

        select:focus {
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.25);
            outline: none;
        }

        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        button {
            padding: 10px 18px;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.95em;
            text-align: center;
            transition: background-color 0.2s ease-in-out;
        }

        .btn-regular {
            background-color: #28a745;
        }

        .btn-regular:hover:not(:disabled) {
            background-color: #218838;
        }

        .btn-libre {
            background-color: #007bff;
        }

        .btn-libre:hover:not(:disabled) {
            background-color: #0056b3;
        }

        button:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .requisitos-info {
            margin-top: 10px;
            font-size: 0.85em;
        }

        .requisitos-cumple {
            color: #155724;
        }

        .requisitos-no-cumple {
            color: #721c24;
        }

        .divider {
            height: 1px;
            background-color: #dee2e6;
            margin: 25px 0;
        }

        .no-materias {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Inscripción a Materias - Ciclo Lectivo <?= $ciclo_lectivo_actual ?></h1>
    <a href="dashboard.php" class="nav-link">&laquo; Volver al menú</a>

    <?php if ($result_materias->num_rows > 0): ?>
        <?php while ($materia = $result_materias->fetch_assoc()): ?>
            <div class="materia-card">
                <div class="materia-header">
                    <div class="materia-title"><?= htmlspecialchars($materia['nombre']) ?></div>
                    <div class="materia-info">
                        Año: <?= $materia['anio'] ?> | Cuatrimestre: <?= $materia['cuatrimestre'] ?>
                    </div>
                </div>

                <?php if (alumno_ya_inscripto($mysqli, $alumno_id, $materia['id'], $ciclo_lectivo_actual)): ?>
                    <div class="status-message status-inscripto">
                        ✓ Ya estás inscripto/a en esta materia para el ciclo <?= $ciclo_lectivo_actual ?>.
                    </div>
                <?php else: ?>
                    <?php 
                    $periodo_activo = verificar_periodo_inscripcion_activo($mysqli, $materia['cuatrimestre'], $ciclo_lectivo_actual);
                    if ($periodo_activo): 
                        $requisitos = verificar_requisitos_materia_alumno($mysqli, $alumno_id, $materia['id']);
                        $cursos_disponibles = obtener_cursos_disponibles($mysqli, $materia['id'], $ciclo_lectivo_actual);
                    ?>
                        <?php if (empty($cursos_disponibles)): ?>
                            <div class="status-message status-no-cursos">
                                ⚠ No hay cursos (comisiones/turnos) definidos para esta materia en el ciclo lectivo actual.
                            </div>
                        <?php else: ?>
                            <div class="form-inscripcion">
                                <form action="procesar_inscripcion.php" method="POST">
                                    <input type="hidden" name="materia_id" value="<?= $materia['id'] ?>">
                                    <input type="hidden" name="ciclo_lectivo" value="<?= $ciclo_lectivo_actual ?>">

                                    <div class="form-group">
                                        <label for="curso_id_<?= $materia['id'] ?>">Seleccionar Curso/Comisión:</label>
                                        <select name="curso_id" id="curso_id_<?= $materia['id'] ?>" required>
                                            <option value="">-- Seleccione un curso --</option>
                                            <?php foreach ($cursos_disponibles as $curso): ?>
                                                <option value="<?= $curso['id'] ?>">
                                                    <?= htmlspecialchars($curso['codigo'] . ' ' . $curso['division'] . ' - ' . $curso['turno']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="button-group">
                                        <?php if ($requisitos['puede_cursar_regular']): ?>
                                            <button type="submit" name="tipo_inscripcion" value="Regular" class="btn-regular">
                                                Inscribir Regular
                                            </button>
                                        <?php else: ?>
                                            <button type="button" disabled title="<?= htmlspecialchars($requisitos['mensaje_cursar_regular']) ?>" class="btn-regular">
                                                Inscribir Regular (No cumple)
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($requisitos['puede_inscribir_libre']): ?>
                                            <button type="submit" name="tipo_inscripcion" value="Libre" class="btn-libre">
                                                Inscribir Libre
                                            </button>
                                        <?php else: ?>
                                            <button type="button" disabled title="<?= htmlspecialchars($requisitos['mensaje_inscribir_libre']) ?>" class="btn-libre">
                                                Inscribir Libre (No cumple)
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <div class="requisitos-info">
                                        <div class="<?= $requisitos['puede_cursar_regular'] ? 'requisitos-cumple' : 'requisitos-no-cumple' ?>">
                                            <strong>Regular:</strong> <?= htmlspecialchars($requisitos['mensaje_cursar_regular']) ?>
                                        </div>
                                        <div class="<?= $requisitos['puede_inscribir_libre'] ? 'requisitos-cumple' : 'requisitos-no-cumple' ?>">
                                            <strong>Libre:</strong> <?= htmlspecialchars($requisitos['mensaje_inscribir_libre']) ?>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="status-message status-periodo-inactivo">
                            ⏰ El período de inscripción para materias de este cuatrimestre no está activo.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="no-materias">
            <p>No hay materias cargadas en el sistema.</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>