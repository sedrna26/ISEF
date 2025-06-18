<?php
// index.php - Interfaz inicial de prueba para la BD del ISEF
session_start();

// Incluir el archivo de conexión a la base de datos
require_once 'config/db.php';

$conn = $mysqli;

$error = '';
$logout_message = '';

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
    $logout_message = "Sesión cerrada correctamente.";
}

// Cerrar la conexión al final
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>ISEF - Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        html,
        body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            min-width: 100vw;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            position: relative;
            background: #222;
        }

        .portada-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: url('../ISEF-programadores-2/portada.png') no-repeat center center;
            background-size: cover;
            z-index: 1;
            transition: opacity 0.7s;
        }

        .login-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 3;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.5s;
        }

        .login-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 32px 20px 24px 20px;
            border-radius: 16px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.18), 0 1.5px 8px 0 rgba(255, 152, 0, 0.12);
            width: 100%;
            max-width: 370px;
            position: relative;
            border: 1.5px solid #ffe0b2;
            animation: fadeInUp 0.7s cubic-bezier(.39, .575, .565, 1) both;
        }

        @keyframes fadeInUp {
            0% {
                opacity: 0;
                transform: translateY(40px);
            }

            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            text-align: center;
            margin-bottom: 18px;
        }

        .header h1 {
            color: #333;
            margin-bottom: 8px;
            letter-spacing: 1px;
            font-weight: 700;
            font-size: 1.3em;
        }

        .header p {
            color: #666;
            margin: 0;
            font-size: 15px;
        }

        .form-group {
            margin-bottom: 18px;
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
            border: 1.5px solid #ffd54f;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
            background: #fffde7;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #ff9800;
            outline: none;
            box-shadow: 0 0 0 2px #ffe0b2;
        }

        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(90deg, #ff9800 0%, #ffb300 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
            font-weight: 600;
            box-shadow: 0 2px 8px 0 rgba(255, 152, 0, 0.10);
            transition: background 0.2s, box-shadow 0.2s;
        }

        button:hover {
            background: linear-gradient(90deg, #ffa726 0%, #ffb300 100%);
            box-shadow: 0 4px 16px 0 rgba(255, 152, 0, 0.16);
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 18px;
            text-align: center;
            border: 1px solid #f5c6cb;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 18px;
            text-align: center;
            border: 1px solid #c3e6cb;
        }

        .info {
            background-color: #fff8e1;
            color: #8d6e63;
            padding: 13px;
            border-radius: 4px;
            margin-top: 16px;
            font-size: 14px;
            border: 1px solid #ffe0b2;
        }

        .info strong {
            display: block;
            margin-bottom: 5px;
            color: #ff9800;
        }

        .fade-out {
            opacity: 0;
            transition: opacity 0.5s;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .login-container {
                max-width: 98vw;
                padding: 18px 6vw 16px 6vw;
            }

            .header h1 {
                font-size: 1.1em;
            }
        }

        /* Oculta scroll en móvil */
        body,
        html {
            overflow: hidden;
        }
    </style>
</head>

<body>
    <div class="portada-bg" id="portada"></div>
    <div class="login-overlay" id="loginOverlay">
        <div class="login-container">
            <div class="header">
                <h1>Sistema Académico ISEF</h1>
                <p>Ingrese sus credenciales para acceder</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!empty($logout_message)): ?>
                <div class="success" id="logout-message"><?= htmlspecialchars($logout_message) ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="success"><?= htmlspecialchars($_SESSION['mensaje']) ?></div>
                <?php unset($_SESSION['mensaje']); ?>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="username">Usuario:</label>
                    <input type="text" id="username" name="username" required autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>

                <button type="submit">Ingresar</button>
            </form>

            <div class="info">
                <strong>Primer ingreso:</strong>
                Si es su primer acceso al sistema, su contraseña inicial es su número de DNI.
                El sistema le solicitará cambiarla por seguridad.
            </div>
        </div>
    </div>

    <script>
        // Mostrar login al hacer click en la portada
        let loginShown = false;
        document.getElementById('portada').addEventListener('click', function () {
            if (!loginShown) {
                document.getElementById('loginOverlay').classList.add('active');
                document.getElementById('portada').style.opacity = '0.25';
                loginShown = true;
            }
        });

        // Auto-ocultar mensaje de logout después de 3 segundos
        document.addEventListener('DOMContentLoaded', function () {
            const logoutMessage = document.getElementById('logout-message');
            if (logoutMessage) {
                setTimeout(function () {
                    logoutMessage.classList.add('fade-out');
                    setTimeout(function () {
                        logoutMessage.style.display = 'none';
                    }, 500);
                }, 3000);
            }
        });

        // Permite abrir login con Enter en móvil
        document.body.addEventListener('keydown', function (e) {
            if (!loginShown && (e.key === "Enter" || e.key === " " || e.key === "Spacebar")) {
                document.getElementById('loginOverlay').classList.add('active');
                document.getElementById('portada').style.opacity = '0.25';
                loginShown = true;
            }
        });
    </script>
</body>

</html>