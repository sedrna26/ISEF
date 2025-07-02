<?php
// Configuración de la página
$titulo = "Sitio en Desarrollo";
$mensaje = "¡Estamos trabajando duro para traerte algo increíble!";
$submensaje = "Nuestra página web está actualmente en desarrollo. Pronto estará lista con contenido nuevo y emocionante.";
$fecha_estimada = "Estimamos que estará lista en las próximas semanas.";

// Obtener la fecha actual
$fecha_actual = date("d/m/Y");
$hora_actual = date("H:i");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }

        .container {
            text-align: center;
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 90%;
            animation: fadeIn 1s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .desarrollo-icon {
            width: 150px;
            height: 150px;
            margin: 0 auto 30px;
            background: #ff6b6b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .desarrollo-icon::before {
            content: "🔧";
            font-size: 60px;
            animation: rotate 3s linear infinite;
        }

        .desarrollo-icon::after {
            content: "";
            position: absolute;
            width: 20px;
            height: 20px;
            background: #4ecdc4;
            border-radius: 50%;
            top: 20px;
            right: 20px;
            animation: bounce 1.5s infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        h1 {
            color: #2c3e50;
            font-size: 2.5em;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .mensaje-principal {
            font-size: 1.3em;
            color: #34495e;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .submensaje {
            font-size: 1em;
            color: #7f8c8d;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .fecha-info {
            background: #ecf0f1;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #3498db;
        }

        .fecha-info p {
            margin: 5px 0;
            color: #2c3e50;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #ecf0f1;
            border-radius: 4px;
            margin: 20px 0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4ecdc4, #44a08d);
            border-radius: 4px;
            animation: loading 3s ease-in-out infinite;
            width: 65%;
        }

        @keyframes loading {
            0% { width: 65%; }
            50% { width: 75%; }
            100% { width: 65%; }
        }

        .contacto {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .contacto h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .email {
            color: #3498db;
            text-decoration: none;
            font-weight: bold;
        }

        .email:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 2em;
            }
            
            .desarrollo-icon {
                width: 120px;
                height: 120px;
            }
            
            .desarrollo-icon::before {
                font-size: 50px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="desarrollo-icon"></div>
        
        <h1><?php echo $titulo; ?></h1>
        
        <p class="mensaje-principal"><?php echo $mensaje; ?></p>
        
        <p class="submensaje"><?php echo $submensaje; ?></p>
        
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
        
        <div class="fecha-info">
            <p><strong>Estado:</strong> En desarrollo activo</p>
            <p><strong>Progreso:</strong> ~65% completado</p>
            <p><strong>Última actualización:</strong> <?php echo $fecha_actual . " a las " . $hora_actual; ?></p>
        </div>
        
        <p style="color: #27ae60; font-weight: bold; margin: 20px 0;">
            <?php echo $fecha_estimada; ?>
        </p>
        
        <div class="contacto">
            <h3>¿Necesitas contactarnos?</h3>
            <p>Para consultas urgentes, escríbenos a:</p>
            <a href="mailto:contacto@tusitio.com" class="email">contacto@tusitio.com</a>
        </div>
    </div>

    <script>
        // Añadir un poco de interactividad
        document.addEventListener('DOMContentLoaded', function() {
            // Actualizar la hora cada minuto
            setInterval(function() {
                location.reload();
            }, 60000);
            
            // Efecto de clic en el icono
            const icon = document.querySelector('.desarrollo-icon');
            icon.addEventListener('click', function() {
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
        });
    </script>
</body>
</html>