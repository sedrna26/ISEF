<?php
session_start();
require_once '../config/db.php';

// Verificar la sesión del alumno
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
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Historial Académico</title>
    <link rel="icon" href="../sources/logo_recortado.ico" type="image/x-icon">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="../style/style.css">
    <style>
        /* Estilos específicos para la tabla del historial académico */
        .historial-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid var(--orange-lighter);
        }

        .historial-table th,
        .historial-table td {
            border: 1px solid var(--orange-lighter);
            padding: 12px;
            text-align: left;
        }

        .historial-table th {
            background-color: var(--orange-lightest);
            color: var(--gray-dark);
            font-weight: 600;
        }

        .historial-table tr:hover {
            background-color: rgba(255, 224, 204, 0.3);
        }

        .historial-table .notas-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .historial-table .notas-list li {
            padding: 4px 0;
            border-bottom: 1px dashed var(--orange-lighter);
        }

        .historial-table .notas-list li:last-child {
            border-bottom: none;
        }

        .historial-table .fecha-nota {
            font-size: 0.8em;
            color: var(--gray-dark);
            opacity: 0.7;
        }

        .historial-table .text-muted {
            color: var(--gray-dark);
            opacity: 0.6;
            font-style: italic;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/nav.php'; ?>
        <main class="main-content">
            <header class="header">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i data-lucide="menu"></i>
                </button>
                <nav class="breadcrumb">
                    <a href="dashboard.php">Sistema de Gestión ISEF</a>
                    <span>/</span>
                    <span>Historial Académico</span>
                </nav>
            </header>

            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Mi Historial Académico</h2>
                    </div>
                    <div class="card-content">
                        <?php
                        // Consulta para obtener el historial académico del alumno
                        $sql = "SELECT
                                m.nombre AS nombre_materia,
                                c.anio AS anio_curso,
                                c.division AS division_curso,
                                ic.estado AS estado_cursado,
                                e.nota AS nota_final,
                                e.tipo AS tipo_evaluacion,
                                e.fecha AS fecha_evaluacion
                            FROM
                                inscripcion_cursado ic
                            JOIN
                                materia m ON ic.materia_id = m.id
                            JOIN
                                curso c ON ic.curso_id = c.id
                            LEFT JOIN
                                evaluacion e ON ic.id = e.inscripcion_cursado_id
                            WHERE
                                ic.alumno_id = ?
                            ORDER BY
                                c.anio, m.nombre, e.fecha DESC";

                        $stmt = $mysqli->prepare($sql);
                        if (!$stmt) {
                            die("Error en la preparación de la consulta: " . $mysqli->error);
                        }
                        $stmt->bind_param("i", $alumno_id);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            $materias = [];
                            while ($row = $result->fetch_assoc()) {
                                $materia_id = $row['nombre_materia'];
                                if (!isset($materias[$materia_id])) {
                                    $materias[$materia_id] = [
                                        'nombre_materia' => $row['nombre_materia'],
                                        'anio_curso' => $row['anio_curso'],
                                        'division_curso' => $row['division_curso'],
                                        'estado_cursado' => $row['estado_cursado'],
                                        'notas' => []
                                    ];
                                }
                                if ($row['nota_final'] !== null) {
                                    $materias[$materia_id]['notas'][] = [
                                        'nota' => $row['nota_final'],
                                        'tipo' => $row['tipo_evaluacion'],
                                        'fecha' => $row['fecha_evaluacion']
                                    ];
                                }
                            }
                        ?>
                            <div class="table-responsive">
                                <table class="styled-table historial-table">
                                    <thead>
                                        <tr>
                                            <th>Materia</th>
                                            <th>Curso</th>
                                            <th>Estado</th>
                                            <th>Notas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($materias as $materia) { ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($materia['nombre_materia']); ?></td>
                                                <td><?php echo htmlspecialchars($materia['anio_curso'] . ' ' . $materia['division_curso']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo strtolower($materia['estado_cursado']); ?>">
                                                        <?php echo htmlspecialchars($materia['estado_cursado']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($materia['notas'])) { ?>
                                                        <ul class="notas-list">
                                                            <?php foreach ($materia['notas'] as $nota) { ?>
                                                                <li>
                                                                    <strong><?php echo htmlspecialchars($nota['tipo']); ?>:</strong>
                                                                    <?php echo htmlspecialchars($nota['nota']); ?>
                                                                    <span class="fecha-nota">
                                                                        (<?php echo date('d/m/Y', strtotime($nota['fecha'])); ?>)
                                                                    </span>
                                                                </li>
                                                            <?php } ?>
                                                        </ul>
                                                    <?php } else { ?>
                                                        <span class="text-muted">Sin notas registradas</span>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="empty-state">
                                <i data-lucide="book-x" class="empty-state-icon"></i>
                                <p>No hay historial académico disponible.</p>
                            </div>
                        <?php }
                        $stmt->close();
                        ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Inicializar iconos
        lucide.createIcons();

        // Función para alternar la barra lateral
        function toggleSidebar() {
            document.querySelector('.app-container').classList.toggle('collapsed');
        }
    </script>
</body>

</html>

<?php $mysqli->close(); ?>