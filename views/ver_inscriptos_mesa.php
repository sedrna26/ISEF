<?php
// ver_inscriptos_mesa.php - Visualizar inscriptos de una mesa de examen
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
$mesa_id = isset($_GET['mesa_id']) ? (int)$_GET['mesa_id'] : 0;

if (!$mesa_id) {
    header("Location: admin_periodos_examen.php");
    exit;
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cambiar_estado'])) {
        $inscripcion_id = (int)$_POST['inscripcion_id'];
        $nuevo_estado = $_POST['nuevo_estado'];
        
        $stmt = $mysqli->prepare("UPDATE inscripcion_examen SET estado = ? WHERE id = ?");
        $stmt->bind_param("si", $nuevo_estado, $inscripcion_id);
        
        if ($stmt->execute()) {
            $mensaje_feedback = "Estado actualizado exitosamente.";
        } else {
            $mensaje_feedback = "Error al actualizar el estado: " . $stmt->error;
        }
        $stmt->close();
    }
    
    if (isset($_POST['eliminar_inscripcion'])) {
        $inscripcion_id = (int)$_POST['inscripcion_id'];
        
        // Verificar que la mesa no esté cerrada
        $check_mesa = $mysqli->prepare("
            SELECT ae.cerrada 
            FROM inscripcion_examen ie 
            JOIN acta_examen ae ON ie.acta_examen_id = ae.id 
            WHERE ie.id = ?
        ");
        $check_mesa->bind_param("i", $inscripcion_id);
        $check_mesa->execute();
        $result_mesa = $check_mesa->get_result();
        $mesa_info = $result_mesa->fetch_assoc();
        $check_mesa->close();
        
        if ($mesa_info && $mesa_info['cerrada']) {
            $mensaje_feedback = "Error: No se puede eliminar inscripciones de una mesa cerrada.";
        } else {
            $stmt = $mysqli->prepare("DELETE FROM inscripcion_examen WHERE id = ?");
            $stmt->bind_param("i", $inscripcion_id);
            
            if ($stmt->execute()) {
                $mensaje_feedback = "Inscripción eliminada exitosamente.";
            } else {
                $mensaje_feedback = "Error al eliminar la inscripción: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Obtener información de la mesa
$mesa_query = "
    SELECT ae.id, ae.fecha, ae.tipo, ae.libro, ae.folio, ae.cerrada,
           m.nombre as materia_nombre,
           CONCAT(c.codigo, ' ', c.division, ' - ', c.ciclo_lectivo) as curso_nombre,
           CONCAT(p.apellidos, ', ', p.nombres) as profesor_nombre
    FROM acta_examen ae
    JOIN materia m ON ae.materia_id = m.id
    JOIN curso c ON ae.curso_id = c.id
    JOIN profesor pr ON ae.profesor_id = pr.id
    JOIN persona p ON pr.persona_id = p.id
    WHERE ae.id = ?
";

$mesa_stmt = $mysqli->prepare($mesa_query);
$mesa_stmt->bind_param("i", $mesa_id);
$mesa_stmt->execute();
$mesa_result = $mesa_stmt->get_result();
$mesa = $mesa_result->fetch_assoc();
$mesa_stmt->close();

if (!$mesa) {
    header("Location: admin_periodos_examen.php");
    exit;
}

// Obtener inscriptos de la mesa
$inscriptos_query = "
    SELECT ie.id as inscripcion_id, ie.fecha_inscripcion, ie.estado,
           a.legajo,
           CONCAT(p.apellidos, ', ', p.nombres) as alumno_nombre,
           p.dni,
           -- Obtener la mejor nota del alumno en esta materia
           (SELECT MAX(ev.nota) 
            FROM evaluacion ev 
            JOIN inscripcion_cursado ic ON ev.inscripcion_cursado_id = ic.id 
            WHERE ic.alumno_id = a.id 
            AND ic.materia_id = (SELECT materia_id FROM acta_examen WHERE id = ?)
            AND ev.nota IS NOT NULL
           ) as mejor_nota,
           -- Verificar si tiene coloquio aprobado
           (SELECT COUNT(*) 
            FROM evaluacion ev 
            JOIN inscripcion_cursado ic ON ev.inscripcion_cursado_id = ic.id 
            WHERE ic.alumno_id = a.id 
            AND ic.materia_id = (SELECT materia_id FROM acta_examen WHERE id = ?)
            AND ev.tipo = 'Coloquio' 
            AND ev.nota >= 6
           ) as tiene_coloquio_aprobado
    FROM inscripcion_examen ie
    JOIN alumno a ON ie.alumno_id = a.id
    JOIN persona p ON a.persona_id = p.id
    WHERE ie.acta_examen_id = ?
    ORDER BY p.apellidos, p.nombres
";

$inscriptos_stmt = $mysqli->prepare($inscriptos_query);
$inscriptos_stmt->bind_param("iii", $mesa_id, $mesa_id, $mesa_id);
$inscriptos_stmt->execute();
$inscriptos_result = $inscriptos_stmt->get_result();

// Contar estadísticas
$stats = [
    'total' => 0,
    'presente' => 0,
    'ausente' => 0
];

$inscriptos_array = [];
while ($inscripto = $inscriptos_result->fetch_assoc()) {
    $inscriptos_array[] = $inscripto;
    $stats['total']++;
    $stats[strtolower($inscripto['estado'])]++;
}

$inscriptos_stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inscriptos de Mesa de Examen - ISEF</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .mesa-info {
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .mesa-info h2 {
            margin-top: 0;
            color: #0066cc;
        }

        .mesa-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #ddd;
        }

        .detail-label {
            font-weight: bold;
            color: #333;
        }

        .stats-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .stat-card {
            text-align: center;
            padding: 15px;
            border-radius: 5px;
            color: white;
            font-weight: bold;
        }

        .stat-total { background-color: #6c757d; }
        .stat-inscriptos { background-color: #007bff; }
        .stat-presentes { background-color: #28a745; }
        .stat-ausentes { background-color: #dc3545; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
            position: sticky;
            top: 0;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .estado-inscripto { color: #007bff; font-weight: bold; }
        .estado-presente { color: #28a745; font-weight: bold; }
        .estado-ausente { color: #dc3545; font-weight: bold; }

        .nota-buena { color: #28a745; font-weight: bold; }
        .nota-regular { color: #ffc107; font-weight: bold; }
        .nota-mala { color: #dc3545; }

        .coloquio-aprobado {
            background-color: #d4edda;
            color: #155724;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }

        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .actions select, .actions button {
            padding: 4px 8px;
            font-size: 12px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }

        .actions button {
            background-color: #007bff;
            color: white;
            cursor: pointer;
            border: none;
        }

        .actions button:hover {
            background-color: #0056b3;
        }

        .btn-danger {
            background-color: #dc3545 !important;
        }

        .btn-danger:hover {
            background-color: #c82333 !important;
        }

        .feedback {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }

        .feedback.error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .mesa-cerrada-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .no-inscriptos {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }

        .export-section {
            margin-bottom: 20px;
            text-align: right;
        }

        .export-section button {
            background-color: #28a745;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .export-section button:hover {
            background-color: #218838;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 15px;
            }
            
            table {
                font-size: 12px;
            }
            
            th, td {
                padding: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Inscriptos de Mesa de Examen</h1>
        <p><a href="admin_periodos_examen.php">&laquo; Volver a Mesas de Examen</a></p>

        <?php if ($mensaje_feedback): ?>
            <div class="feedback <?= strpos($mensaje_feedback, 'Error') === 0 ? 'error' : '' ?>">
                <?= htmlspecialchars($mensaje_feedback) ?>
            </div>
        <?php endif; ?>

        <?php if ($mesa['cerrada']): ?>
            <div class="mesa-cerrada-warning">
                <strong>⚠️ Mesa Cerrada:</strong> Esta mesa está cerrada. No se pueden realizar modificaciones en las inscripciones.
            </div>
        <?php endif; ?>

        <div class="mesa-info">
            <h2>📋 Información de la Mesa</h2>
            <div class="mesa-details">
                <div class="detail-item">
                    <span class="detail-label">Materia:</span>
                    <span><?= htmlspecialchars($mesa['materia_nombre']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Curso:</span>
                    <span><?= htmlspecialchars($mesa['curso_nombre']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Fecha:</span>
                    <span><?= date('d/m/Y', strtotime($mesa['fecha'])) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Tipo:</span>
                    <span><?= htmlspecialchars($mesa['tipo']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Profesor:</span>
                    <span><?= htmlspecialchars($mesa['profesor_nombre']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Libro/Folio:</span>
                    <span>
                        <?php if ($mesa['libro'] || $mesa['folio']): ?>
                            <?= $mesa['libro'] ? "L: " . $mesa['libro'] : "" ?>
                            <?= $mesa['folio'] ? " F: " . $mesa['folio'] : "" ?>
                        <?php else: ?>
                            No asignado
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Estado:</span>
                    <span><?= $mesa['cerrada'] ? 'CERRADA' : 'ABIERTA' ?></span>
                </div>
            </div>
        </div>

        <div class="stats-section">
            <h3>📊 Estadísticas de Inscripción</h3>
           <div class="stats-grid">
    <div class="stat-card stat-total">
        <div style="font-size: 24px;"><?= $stats['total'] ?></div>
        <div>Total Inscriptos</div>
    </div>
    <div class="stat-card stat-presentes">
        <div style="font-size: 24px;"><?= $stats['presente'] ?></div>
        <div>Presentes</div>
    </div>
    <div class="stat-card stat-ausentes">
        <div style="font-size: 24px;"><?= $stats['ausente'] ?></div>
        <div>Ausentes</div>
    </div>
</div>
        </div>

        <?php if (count($inscriptos_array) > 0): ?>
            <div class="export-section">
                <button onclick="exportToCSV()">📊 Exportar a CSV</button>
                <button onclick="window.print()">🖨️ Imprimir</button>
            </div>

            <table id="inscriptosTable">
                <thead>
                    <tr>
                        <th>Legajo</th>
                        <th>Alumno</th>
                        <th>DNI</th>
                        <th>Fecha Inscripción</th>
                        <th>Mejor Nota</th>
                        <th>Coloquio</th>
                        <th>Estado</th>
                        <?php if (!$mesa['cerrada']): ?>
                            <th>Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inscriptos_array as $inscripto): ?>
                        <tr>
                            <td><?= htmlspecialchars($inscripto['legajo']) ?></td>
                            <td><?= htmlspecialchars($inscripto['alumno_nombre']) ?></td>
                            <td><?= htmlspecialchars($inscripto['dni']) ?></td>
                            
                            <td><?= date('d/m/Y H:i', strtotime($inscripto['fecha_inscripcion'])) ?></td>
                            <td>
                                <?php if ($inscripto['mejor_nota']): ?>
                                    <span class="<?= $inscripto['mejor_nota'] >= 8 ? 'nota-buena' : ($inscripto['mejor_nota'] >= 6 ? 'nota-regular' : 'nota-mala') ?>">
                                        <?= $inscripto['mejor_nota'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="nota-mala">Sin nota</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($inscripto['tiene_coloquio_aprobado'] > 0): ?>
                                    <span class="coloquio-aprobado">APROBADO</span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="estado-<?= strtolower($inscripto['estado']) ?>">
                                    <?= strtoupper($inscripto['estado']) ?>
                                </span>
                            </td>
                            <?php if (!$mesa['cerrada']): ?>
                                <td>
                                    <div class="actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="cambiar_estado" value="1">
                                            <input type="hidden" name="inscripcion_id" value="<?= $inscripto['inscripcion_id'] ?>">
                                            <select name="nuevo_estado" onchange="this.form.submit()">
                                                <option value="Inscripto" <?= $inscripto['estado'] === 'Inscripto' ? 'selected' : '' ?>>Inscripto</option>
                                                <option value="Presente" <?= $inscripto['estado'] === 'Presente' ? 'selected' : '' ?>>Presente</option>
                                                <option value="Ausente" <?= $inscripto['estado'] === 'Ausente' ? 'selected' : '' ?>>Ausente</option>
                                            </select>
                                        </form>
                                        
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Está seguro de eliminar esta inscripción?')">
                                            <input type="hidden" name="eliminar_inscripcion" value="1">
                                            <input type="hidden" name="inscripcion_id" value="<?= $inscripto['inscripcion_id'] ?>">
                                            <button type="submit" class="btn-danger">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-inscriptos">
                <h3>📝 No hay inscriptos</h3>
                <p>Esta mesa de examen no tiene alumnos inscriptos.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function exportToCSV() {
            const table = document.getElementById('inscriptosTable');
            const rows = Array.from(table.rows);
            
            let csvContent = '';
            
            // Headers (excluding actions column)
            const headers = rows[0].cells;
            const headerRow = [];
            for (let i = 0; i < headers.length - 1; i++) { // -1 to exclude actions
                headerRow.push('"' + headers[i].textContent.trim() + '"');
            }
            csvContent += headerRow.join(',') + '\n';
            
            // Data rows
            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].cells;
                const row = [];
                for (let j = 0; j < cells.length - 1; j++) { // -1 to exclude actions
                    let cellText = cells[j].textContent.trim();
                    cellText = cellText.replace(/"/g, '""'); // Escape quotes
                    row.push('"' + cellText + '"');
                }
                csvContent += row.join(',') + '\n';
            }
            
            // Download
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'inscriptos_mesa_<?= $mesa_id ?>_<?= date('Y-m-d') ?>.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>

<?php
$mysqli->close();
?>