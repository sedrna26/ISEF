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
    <title>Auditoría - ISEF</title>
    <link rel="icon" href="../sources/logo_recortado.ico" type="image/x-icon">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="../style/style.css">
</head>

<body class="auditoria">
    <div class="app-container">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/ISEF/views/includes/nav.php'; ?>
        <main class="main-content">
            <header class="header">

                <nav class="breadcrumb">
                    <a href="dashboard.php">Sistema de Gestión ISEF</a>
                    <span>/</span>
                    <span>Auditoría</span>
                </nav>

            </header>
            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Registro de Auditoría</h2>
                        <p class="card-description">Últimas 100 operaciones realizadas en el sistema.</p>
                    </div>
                    <div class="card-content">
                        <div class="table-container">
                            <table class="styled-table">
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
                                            <td>
                                                <pre><?= htmlspecialchars($row['valor_anterior']) ?></pre>
                                            </td>
                                            <td>
                                                <pre><?= htmlspecialchars($row['valor_nuevo']) ?></pre>
                                            </td>
                                            <td><?= htmlspecialchars($row['ip_origen']) ?></td>
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
        lucide.createIcons();
    </script>
</body>

</html>
<?php if ($mysqli) {
    $mysqli->close();
} ?>