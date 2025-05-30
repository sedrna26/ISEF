<?php

session_start();

include '../config/db.php';

if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

// 2. VERIFICACIÓN DE USUARIO ADMINISTRADOR
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: ../index.php"); // O a la página de login
    exit;
}

// Variables para mensajes
$mensaje_exito = '';
$mensaje_error = '';

if (isset($_SESSION['mensaje_exito'])) {
    $mensaje_exito = $_SESSION['mensaje_exito'];
    unset($_SESSION['mensaje_exito']);
}
if (isset($_SESSION['mensaje_error'])) {
    $mensaje_error = $_SESSION['mensaje_error'];
    unset($_SESSION['mensaje_error']);
}

// 3. LÓGICA PARA MANEJAR ACCIONES (POST)
$accion = $_POST['accion'] ?? '';
$periodo_id_editar = null; // Para precargar el formulario de edición

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ciclo_lectivo = filter_input(INPUT_POST, 'ciclo_lectivo', FILTER_VALIDATE_INT);
    $cuatrimestre = filter_input(INPUT_POST, 'cuatrimestre', FILTER_SANITIZE_STRING);
    $fecha_apertura = filter_input(INPUT_POST, 'fecha_apertura', FILTER_SANITIZE_STRING);
    $fecha_cierre = filter_input(INPUT_POST, 'fecha_cierre', FILTER_SANITIZE_STRING);
    $descripcion = filter_input(INPUT_POST, 'descripcion', FILTER_SANITIZE_STRING);
    $activo = isset($_POST['activo']) ? 1 : 0;
    $periodo_id = filter_input(INPUT_POST, 'periodo_id', FILTER_VALIDATE_INT);

    if ($accion === 'guardar_periodo') {
        if ($ciclo_lectivo && $cuatrimestre && $fecha_apertura && $fecha_cierre) {
            if ($fecha_apertura > $fecha_cierre) {
                $mensaje_error = "La fecha de apertura no puede ser posterior a la fecha de cierre.";
            } else {
                if ($periodo_id) { // Editar
                    $stmt = $mysqli->prepare("UPDATE periodos_inscripcion SET ciclo_lectivo = ?, cuatrimestre = ?, fecha_apertura = ?, fecha_cierre = ?, descripcion = ?, activo = ? WHERE id = ?");
                    $stmt->bind_param("issssii", $ciclo_lectivo, $cuatrimestre, $fecha_apertura, $fecha_cierre, $descripcion, $activo, $periodo_id);
                    if ($stmt->execute()) {
                        $mensaje_exito = "Período de inscripción actualizado correctamente.";
                    } else {
                        $mensaje_error = "Error al actualizar el período: " . $stmt->error;
                    }
                    $stmt->close();
                } else { // Agregar
                    $stmt = $mysqli->prepare("INSERT INTO periodos_inscripcion (ciclo_lectivo, cuatrimestre, fecha_apertura, fecha_cierre, descripcion, activo) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issssi", $ciclo_lectivo, $cuatrimestre, $fecha_apertura, $fecha_cierre, $descripcion, $activo);
                    if ($stmt->execute()) {
                        $mensaje_exito = "Período de inscripción agregado correctamente.";
                    } else {
                        $mensaje_error = "Error al agregar el período: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        } else {
            $mensaje_error = "Todos los campos marcados con * son obligatorios.";
        }
    }
}

// Acción para editar (GET)
if (isset($_GET['accion']) && $_GET['accion'] === 'editar' && isset($_GET['id'])) {
    $periodo_id_get = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    $stmt = $mysqli->prepare("SELECT * FROM periodos_inscripcion WHERE id = ?");
    $stmt->bind_param("i", $periodo_id_get);
    $stmt->execute();
    $result_edit = $stmt->get_result();
    if ($result_edit->num_rows === 1) {
        $periodo_id_editar = $result_edit->fetch_assoc();
    } else {
        $mensaje_error = "Período no encontrado para editar.";
    }
    $stmt->close();
}

// Acción para eliminar (GET)
if (isset($_GET['accion']) && $_GET['accion'] === 'eliminar' && isset($_GET['id'])) {
    $periodo_id_delete = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    // Podrías implementar un borrado lógico (marcar como inactivo) o físico.
    // Por seguridad, es mejor un borrado lógico o una confirmación.
    // Aquí un ejemplo de borrado físico (usar con precaución):
    $stmt = $mysqli->prepare("DELETE FROM periodos_inscripcion WHERE id = ?");
    $stmt->bind_param("i", $periodo_id_delete);
    if ($stmt->execute()) {
        $_SESSION['mensaje_exito'] = "Período de inscripción eliminado correctamente.";
    } else {
        $_SESSION['mensaje_error'] = "Error al eliminar el período: " . $stmt->error;
    }
    $stmt->close();
    header("Location: admin_periodos_inscripcion.php"); // Redirigir para refrescar y mostrar mensaje
    exit;
}


// 4. OBTENER LISTA DE PERÍODOS EXISTENTES
$lista_periodos = [];
$result_periodos = $mysqli->query("SELECT * FROM periodos_inscripcion ORDER BY ciclo_lectivo DESC, fecha_apertura DESC");
if ($result_periodos) {
    while ($row = $result_periodos->fetch_assoc()) {
        $lista_periodos[] = $row;
    }
    $result_periodos->free();
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Gestión de Períodos de Inscripción - ISEF</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
        }

        .container {
            max-width: 900px;
            margin: 20px auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .form-container,
        .table-container {
            margin-bottom: 30px;
        }

        label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        select {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border-radius: 4px;
            border: 1px solid #ddd;
            box-sizing: border-box;
        }

        input[type="checkbox"] {
            margin-top: 5px;
        }

        button[type="submit"],
        .button {
            padding: 10px 15px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        button[type="submit"]:hover,
        .button:hover {
            background-color: #218838;
        }

        .button.edit {
            background-color: #ffc107;
            color: black;
        }

        .button.edit:hover {
            background-color: #e0a800;
        }

        .button.delete {
            background-color: #dc3545;
        }

        .button.delete:hover {
            background-color: #c82333;
        }

        .button.cancel {
            background-color: #6c757d;
        }

        .button.cancel:hover {
            background-color: #5a6268;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f8f9fa;
        }

        .mensaje {
            padding: 10px;
            margin: 15px 0;
            border-radius: 4px;
            text-align: center;
        }

        .exito {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Gestión de Períodos de Inscripción</h1>
            <a href="dashboard.php" class="button">Volver al Panel</a>
        </div>

        <?php if ($mensaje_exito): ?><div class="mensaje exito"><?= htmlspecialchars($mensaje_exito) ?></div><?php endif; ?>
        <?php if ($mensaje_error): ?><div class="mensaje error"><?= htmlspecialchars($mensaje_error) ?></div><?php endif; ?>

        <div class="form-container">
            <h2><?= $periodo_id_editar ? 'Editar' : 'Agregar Nuevo' ?> Período</h2>
            <form action="admin_periodos_inscripcion.php" method="POST">
                <input type="hidden" name="accion" value="guardar_periodo">
                <?php if ($periodo_id_editar): ?>
                    <input type="hidden" name="periodo_id" value="<?= htmlspecialchars($periodo_id_editar['id']) ?>">
                <?php endif; ?>

                <label for="ciclo_lectivo">Ciclo Lectivo (*):</label>
                <input type="number" id="ciclo_lectivo" name="ciclo_lectivo" value="<?= htmlspecialchars($periodo_id_editar['ciclo_lectivo'] ?? date('Y')) ?>" required>

                <label for="cuatrimestre">Cuatrimestre (*):</label>
                <select id="cuatrimestre" name="cuatrimestre" required>
                    <option value="1°" <?= (isset($periodo_id_editar['cuatrimestre']) && $periodo_id_editar['cuatrimestre'] == '1°') ? 'selected' : '' ?>>1° Cuatrimestre</option>
                    <option value="2°" <?= (isset($periodo_id_editar['cuatrimestre']) && $periodo_id_editar['cuatrimestre'] == '2°') ? 'selected' : '' ?>>2° Cuatrimestre</option>
                    <option value="Anual" <?= (isset($periodo_id_editar['cuatrimestre']) && $periodo_id_editar['cuatrimestre'] == 'Anual') ? 'selected' : '' ?>>Anual</option>
                </select>

                <label for="fecha_apertura">Fecha de Apertura (*):</label>
                <input type="date" id="fecha_apertura" name="fecha_apertura" value="<?= htmlspecialchars($periodo_id_editar['fecha_apertura'] ?? '') ?>" required>

                <label for="fecha_cierre">Fecha de Cierre (*):</label>
                <input type="date" id="fecha_cierre" name="fecha_cierre" value="<?= htmlspecialchars($periodo_id_editar['fecha_cierre'] ?? '') ?>" required>

                <label for="descripcion">Descripción:</label>
                <input type="text" id="descripcion" name="descripcion" value="<?= htmlspecialchars($periodo_id_editar['descripcion'] ?? '') ?>">

                <label for="activo">
                    <input type="checkbox" id="activo" name="activo" value="1" <?= (isset($periodo_id_editar['activo']) && $periodo_id_editar['activo'] == 1) || !isset($periodo_id_editar) ? 'checked' : '' ?>>
                    Activo
                </label>

                <button type="submit"><?= $periodo_id_editar ? 'Actualizar Período' : 'Agregar Período' ?></button>
                <?php if ($periodo_id_editar): ?>
                    <a href="admin_periodos_inscripcion.php" class="button cancel">Cancelar Edición</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-container">
            <h2>Períodos Existentes</h2>
            <?php if (!empty($lista_periodos)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Ciclo Lectivo</th>
                            <th>Cuatrimestre</th>
                            <th>Apertura</th>
                            <th>Cierre</th>
                            <th>Descripción</th>
                            <th>Activo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista_periodos as $periodo): ?>
                            <tr>
                                <td><?= htmlspecialchars($periodo['ciclo_lectivo']) ?></td>
                                <td><?= htmlspecialchars($periodo['cuatrimestre']) ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($periodo['fecha_apertura']))) ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($periodo['fecha_cierre']))) ?></td>
                                <td><?= htmlspecialchars($periodo['descripcion']) ?></td>
                                <td><?= $periodo['activo'] ? 'Sí' : 'No' ?></td>
                                <td>
                                    <a href="admin_periodos_inscripcion.php?accion=editar&id=<?= $periodo['id'] ?>" class="button edit">Editar</a>
                                    <a href="admin_periodos_inscripcion.php?accion=eliminar&id=<?= $periodo['id'] ?>" class="button delete" onclick="return confirm('¿Estás seguro de que deseas eliminar este período?');">Eliminar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No hay períodos de inscripción definidos.</p>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>