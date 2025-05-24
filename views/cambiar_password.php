<?php
// cambiar_password.php - Página para cambio obligatorio de contraseña
session_start();

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

$host = 'localhost';
$user = 'root';
$password = '';
$db = 'isef_sistema';

$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

$mensaje = '';
$error = '';

// Verificar si el usuario debe cambiar la contraseña
$stmt = $conn->prepare("SELECT debe_cambiar_password, username FROM usuario WHERE id = ?");
$stmt->bind_param("i", $_SESSION['usuario_id']);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();
if (!$usuario['debe_cambiar_password']) { // Si es FALSE (o sea, 0, no debe cambiar)
    header("Location: dashboard.php"); // Redirige al dashboard dentro de la carpeta views/ [cite: 149, 150]
    exit;
}
$stmt->close();

// Si no debe cambiar contraseña, redirigir al dashboard
if (!$usuario['debe_cambiar_password']) {
    header("Location: dashboard.php");
    exit;
}

// Procesar el cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_actual = $_POST['password_actual'];
    $nueva_password = $_POST['nueva_password'];
    $confirmar_password = $_POST['confirmar_password'];
    
    // Validar que las contraseñas coincidan
    if ($nueva_password !== $confirmar_password) {
        $error = "Las contraseñas nuevas no coinciden.";
    } elseif (strlen($nueva_password) < 6) {
        $error = "La nueva contraseña debe tener al menos 6 caracteres.";
    } else {
        // Verificar contraseña actual
        $stmt = $conn->prepare("SELECT password FROM usuario WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['usuario_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();
        
        if (password_verify($password_actual, $user_data['password'])) {
            // Cambiar la contraseña
            $nueva_password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuario SET password = ?, debe_cambiar_password = 0 WHERE id = ?");
            $stmt->bind_param("si", $nueva_password_hash, $_SESSION['usuario_id']);
            
            if ($stmt->execute()) {
                $stmt->close();
                
                // Registrar cambio exitoso en sesión para mostrar en dashboard
                $_SESSION['mensaje'] = "Contraseña cambiada exitosamente.";
                
                // Redirigir al dashboard
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Error al cambiar la contraseña: " . $conn->error;
                $stmt->close();
            }
        } else {
            $error = "La contraseña actual es incorrecta.";
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cambiar Contraseña - ISEF</title>
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
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
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
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }
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
        .message {
            padding: 10px;
            margin: 15px 0;
            border-radius: 4px;
            text-align: center;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            margin-bottom: 20px;
        }
        .requirements {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        .logout-link {
            text-align: center;
            margin-top: 20px;
        }
        .logout-link a {
            color: #dc3545;
            text-decoration: none;
        }
        .logout-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Cambio Obligatorio de Contraseña</h1>
            <p>Bienvenido/a, <?= htmlspecialchars($usuario['username']) ?></p>
        </div>
        
        <div class="warning">
            <strong>¡Atención!</strong> Por seguridad, debe cambiar su contraseña antes de continuar usando el sistema.
        </div>
        
        <?php if ($mensaje): ?>
            <div class="message success"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="password_actual">Contraseña actual:</label>
                <input type="password" id="password_actual" name="password_actual" required>
                <div class="requirements">Su contraseña actual es su número de DNI</div>
            </div>
            
            <div class="form-group">
                <label for="nueva_password">Nueva contraseña:</label>
                <input type="password" id="nueva_password" name="nueva_password" required>
                <div class="requirements">Debe tener al menos 6 caracteres</div>
            </div>
            
            <div class="form-group">
                <label for="confirmar_password">Confirmar nueva contraseña:</label>
                <input type="password" id="confirmar_password" name="confirmar_password" required>
            </div>
            
            <button type="submit">Cambiar Contraseña</button>
        </form>
        
        <div class="logout-link">
            <a href="../index.php?logout=1">Cerrar sesión</a>
        </div>
    </div>
    
    <script>
        // Validación en tiempo real
        document.getElementById('confirmar_password').addEventListener('input', function() {
            const nueva = document.getElementById('nueva_password').value;
            const confirmar = this.value;
            
            if (nueva !== confirmar && confirmar.length > 0) {
                this.style.borderColor = '#f44336';
            } else {
                this.style.borderColor = '#ddd';
            }
        });
        
        document.getElementById('nueva_password').addEventListener('input', function() {
            const confirmar = document.getElementById('confirmar_password');
            if (this.value !== confirmar.value && confirmar.value.length > 0) {
                confirmar.style.borderColor = '#f44336';
            } else {
                confirmar.style.borderColor = '#ddd';
            }
        });
    </script>
</body>
</html>