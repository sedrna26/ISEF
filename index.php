<?php
// index.php - Interfaz inicial de prueba para la BD del ISEF
session_start();

// Incluir el archivo de conexión a la base de datos
require_once 'config/db.php';


$conn = $mysqli;

$error = '';

// Si se envió el formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    $stmt = $conn->prepare("SELECT id, password, tipo, debe_cambiar_password FROM usuario WHERE username = ? AND activo = 1");
    $stmt->bind_param("s", $_POST['username']);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hash, $tipo, $debe_cambiar_password);
        $stmt->fetch();

        if (password_verify($_POST['password'], $hash)) {
            $_SESSION['usuario_id'] = $id;
            $_SESSION['tipo'] = $tipo;

            // Actualizar último acceso
            $stmt_update = $conn->prepare("UPDATE usuario SET ultimo_acceso = NOW() WHERE id = ?");
            $stmt_update->bind_param("i", $id);
            $stmt_update->execute();
            $stmt_update->close();

            // Verificar si debe cambiar contraseña
            if ($debe_cambiar_password) {
                header("Location: views/cambiar_password.php");
                exit;
            } else {
                header("Location: views/dashboard.php");
                exit;
            }
        } else {
            $error = "Usuario o contraseña incorrectos.";
        }
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }
    $stmt->close();
}

// Cerrar sesión si se viene del logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    $error = "Sesión cerrada correctamente.";
}

// Cerrar la conexión al final
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>ISEF - Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .login-container {
            background-color: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            margin: 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #4CAF50;
            outline: none;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }

        button:hover {
            background-color: #45a049;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }

        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
            font-size: 14px;
        }

        .info strong {
            display: block;
            margin-bottom: 5px;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="header">
            <h1>Sistema Académico ISEF</h1>
            <p>Ingrese sus credenciales para acceder</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="success"><?= htmlspecialchars($_SESSION['mensaje']) ?></div>
            <?php unset($_SESSION['mensaje']); ?>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Usuario:</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit">Ingresar</button>
        </form>

        <div class="info">
            <strong>Primer ingreso:</strong>
            Si es su primer acceso al sistema, su contraseña inicial es su número de DNI.
            El sistema le solicitará cambiarla por seguridad.
        </div>
    </div>
</body>

</html>