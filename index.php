<?php
// index.php - Interfaz inicial de prueba para la BD del ISEF
session_start();

// Configuración básica
$host = 'localhost';
$user = 'root';
$password = '';
$db = 'isef_sistema';

$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Si se envió el formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    $stmt = $conn->prepare("SELECT id, password, tipo FROM usuario WHERE username = ? AND activo = 1");
    $stmt->bind_param("s", $_POST['username']);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hash, $tipo);
        $stmt->fetch();

        if (password_verify($_POST['password'], $hash)) {
            $_SESSION['usuario_id'] = $id;
            $_SESSION['tipo'] = $tipo;
            header("Location: views/dashboard.php");

            exit;
        } else {
            $error = "Contraseña incorrecta.";
        }
    } else {
        $error = "Usuario no encontrado o inactivo.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>ISEF - Login</title>
</head>
<body>
    <h1>Ingreso al Sistema Académico ISEF</h1>
    <?php if (!empty($error)): ?><p style="color:red;"><?= $error ?></p><?php endif; ?>
    <form method="POST">
        <label>Usuario: <input type="text" name="username" required></label><br>
        <label>Contraseña: <input type="password" name="password" required></label><br>
        <button type="submit">Ingresar</button>
    </form>
</body>
</html>
