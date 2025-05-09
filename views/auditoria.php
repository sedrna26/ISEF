<?php
// auditoria.php - Registro de auditorías
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] !== 'administrador') {
    header("Location: ../index.php");
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "isef_sistema");
if ($mysqli->connect_errno) {
    die("Fallo la conexión: " . $mysqli->connect_error);
}

// Obtener las auditorías más recientes
$audit_query = "
    SELECT a.id, a.fecha_hora, u.username AS usuario, a.tipo_operacion, a.tabla_afectada,
           a.registro_afectado, a.valor_anterior, a.valor_nuevo, a.ip_origen
    FROM auditoria a
    LEFT JOIN usuario u ON a.usuario_id = u.id
    ORDER BY a.fecha_hora DESC
    LIMIT 100
";
$audit_result = $mysqli->query($audit_query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Auditoría - ISEF</title>
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background-color: #f2f2f2; }
        pre { white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
    <h1>Registro de Auditoría</h1>
    <a href="dashboard.php">&laquo; Volver al menú</a>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha y Hora</th>
                <th>Usuario</th>
                <th>Operación</th>
                <th>Tabla</th>
                <th>Registro</th>
                <th>Valor Anterior</th>
                <th>Valor Nuevo</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $audit_result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['fecha_hora']) ?></td>
                    <td><?= htmlspecialchars($row['usuario'] ?? 'Sistema') ?></td>
                    <td><?= htmlspecialchars($row['tipo_operacion']) ?></td>
                    <td><?= htmlspecialchars($row['tabla_afectada']) ?></td>
                    <td><?= htmlspecialchars($row['registro_afectado']) ?></td>
                    <td><pre><?= htmlspecialchars($row['valor_anterior']) ?></pre></td>
                    <td><pre><?= htmlspecialchars($row['valor_nuevo']) ?></pre></td>
                    <td><?= htmlspecialchars($row['ip_origen']) ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
