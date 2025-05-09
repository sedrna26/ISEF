<?php
/**
 * Script de prueba para la base de datos del Instituto Superior de Educación Física
 * Este script realiza pruebas completas del sistema, incluyendo todas las tablas, 
 * procedimientos almacenados y triggers.
 */

// Configuración de conexión a la base de datos
$config = [
    'host'     => 'localhost',
    'db'       => 'isef_sistema',
    'user'     => 'root',      // Cambiar por el usuario real
    'password' => '',          // Cambiar por la contraseña real
    'charset'  => 'utf8mb4'
];

// Conectar a la base de datos
function conectarDB($config) {
    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['db']};charset={$config['charset']}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, $config['user'], $config['password'], $options);
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

// Función para ejecutar consultas y manejar errores
function ejecutarQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        echo "Error en la consulta: " . $e->getMessage() . "\n";
        echo "SQL: $sql\n";
        return false;
    }
}

// Función para imprimir resultados de forma legible
function printResults($results, $title = "") {
    if (!empty($title)) {
        echo "\n=== $title ===\n";
    }
    
    if (is_array($results) && count($results) > 0) {
        // Obtener las claves (columnas) del primer elemento
        $columns = array_keys($results[0]);
        
        // Imprimir encabezados
        foreach ($columns as $column) {
            echo str_pad($column, 20) . " | ";
        }
        echo "\n" . str_repeat("-", count($columns) * 23) . "\n";
        
        // Imprimir filas
        foreach ($results as $row) {
            foreach ($row as $value) {
                echo str_pad(substr($value ?? "NULL", 0, 18), 20) . " | ";
            }
            echo "\n";
        }
    } elseif (!$results) {
        echo "No se encontraron resultados o hubo un error.\n";
    } else {
        echo "Operación completada exitosamente.\n";
    }
}

// Función para limpiar datos de prueba anteriores (opcional)
function limpiarDatos($pdo) {
    $tables = [
        'auditoria', 'certificacion', 'inscripcion_examen', 'acta_examen',
        'evaluacion', 'asistencia', 'inscripcion_cursado', 'profesor_materia',
        'licencia_profesor', 'correlatividad', 'materia', 'curso',
        'profesor', 'alumno', 'persona', 'usuario'
    ];
    
    echo "Limpiando datos anteriores...\n";
    
    // Deshabilitar restricciones de clave foránea temporalmente
    ejecutarQuery($pdo, "SET FOREIGN_KEY_CHECKS = 0");
    
    foreach ($tables as $table) {
        ejecutarQuery($pdo, "TRUNCATE TABLE $table");
    }
    
    // Rehabilitar restricciones de clave foránea
    ejecutarQuery($pdo, "SET FOREIGN_KEY_CHECKS = 1");
    
    echo "Base de datos limpiada correctamente.\n";
}

// Función para probar la creación de usuarios y personas
function testUsuariosPersonas($pdo) {
    echo "\n== Prueba de creación de usuarios y personas ==\n";
    
    // Crear usuarios (administrador, preceptor, profesor, alumno)
    $usuarios = [
        ['admin1', password_hash('123456', PASSWORD_DEFAULT), 'administrador'],
        ['preceptor1', password_hash('123456', PASSWORD_DEFAULT), 'preceptor'],
        ['prof1', password_hash('123456', PASSWORD_DEFAULT), 'profesor'],
        ['prof2', password_hash('123456', PASSWORD_DEFAULT), 'profesor'],
        ['prof3', password_hash('123456', PASSWORD_DEFAULT), 'profesor'],
        ['alum1', password_hash('123456', PASSWORD_DEFAULT), 'alumno'],
        ['alum2', password_hash('123456', PASSWORD_DEFAULT), 'alumno'],
        ['alum3', password_hash('123456', PASSWORD_DEFAULT), 'alumno'],
        ['alum4', password_hash('123456', PASSWORD_DEFAULT), 'alumno'],
    ];
    
    foreach ($usuarios as $usuario) {
        $sql = "INSERT INTO usuario (username, password, tipo) VALUES (?, ?, ?)";
        ejecutarQuery($pdo, $sql, $usuario);
    }
    
    // Verificar usuarios creados
    $stmt = ejecutarQuery($pdo, "SELECT id, username, tipo, activo FROM usuario");
    $results = $stmt->fetchAll();
    printResults($results, "Usuarios creados");
    
    // Crear personas
    $personas = [
        // id_usuario, apellidos, nombres, dni, fecha_nacimiento, celular, domicilio, contacto
        [1, 'López', 'María', '20345678', '1980-05-15', '3511234567', 'Dirección 123', 'Contacto de emergencia'],
        [2, 'González', 'Roberto', '21456789', '1982-08-20', '3512345678', 'Dirección 456', 'Contacto de emergencia'],
        [3, 'Martínez', 'Carlos', '22567890', '1975-03-10', '3513456789', 'Dirección 789', 'Contacto de emergencia'],
        [4, 'Sánchez', 'Laura', '23678901', '1978-07-25', '3514567890', 'Dirección 101', 'Contacto de emergencia'],
        [5, 'Rodríguez', 'Juan', '24789012', '1983-11-12', '3515678901', 'Dirección 112', 'Contacto de emergencia'],
        [6, 'Fernández', 'Ana', '30123456', '2000-01-30', '3516789012', 'Dirección 213', 'Contacto de emergencia'],
        [7, 'Torres', 'Miguel', '31234567', '2001-04-05', '3517890123', 'Dirección 314', 'Contacto de emergencia'],
        [8, 'Díaz', 'Lucía', '32345678', '2002-06-18', '3518901234', 'Dirección 415', 'Contacto de emergencia'],
        [9, 'Pérez', 'Daniel', '33456789', '2003-09-22', '3519012345', 'Dirección 516', 'Contacto de emergencia'],
    ];
    
    foreach ($personas as $persona) {
        $sql = "INSERT INTO persona (usuario_id, apellidos, nombres, dni, fecha_nacimiento, celular, domicilio, contacto_emergencia) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        ejecutarQuery($pdo, $sql, $persona);
    }
    
    // Verificar personas creadas usando la vista
    $stmt = ejecutarQuery($pdo, "SELECT * FROM vista_personas");
    $results = $stmt->fetchAll();
    printResults($results, "Personas creadas");
    
    return true;
}

// Función para probar la creación de profesores
function testProfesores($pdo) {
    echo "\n== Prueba de creación de profesores ==\n";
    
    // Crear profesores
    $profesores = [
        // persona_id, titulo_profesional, fecha_ingreso, horas_consulta
        [3, 'Profesor de Educación Física', '2010-03-01', 'Lunes 14:00-16:00'],
        [4, 'Licenciado en Educación Física', '2012-08-15', 'Martes 15:00-17:00'],
        [5, 'Doctor en Ciencias del Deporte', '2015-02-10', 'Miércoles 16:00-18:00'],
    ];
    
    foreach ($profesores as $profesor) {
        $sql = "INSERT INTO profesor (persona_id, titulo_profesional, fecha_ingreso, horas_consulta) 
                VALUES (?, ?, ?, ?)";
        ejecutarQuery($pdo, $sql, $profesor);
    }
    
    // Verificar profesores creados usando la vista
    $stmt = ejecutarQuery($pdo, "SELECT * FROM vista_profesores");
    $results = $stmt->fetchAll();
    printResults($results, "Profesores creados");
    
    // Añadir una licencia para un profesor
    $sql = "INSERT INTO licencia_profesor (profesor_id, fecha_inicio, fecha_fin, tipo, observaciones) 
            VALUES (1, '2023-05-01', '2023-05-10', 'Enfermedad', 'Licencia médica por cirugía')";
    ejecutarQuery($pdo, $sql);
    
    // Verificar licencias
    $stmt = ejecutarQuery($pdo, "SELECT * FROM licencia_profesor");
    $results = $stmt->fetchAll();
    printResults($results, "Licencias de profesores");
    
    return true;
}

// Función para probar la creación de alumnos
function testAlumnos($pdo) {
    echo "\n== Prueba de creación de alumnos ==\n";
    
    // Crear alumnos
    $alumnos = [
        // persona_id, legajo, fecha_ingreso, cohorte
        [6, '12345', '2022-03-01', 2022],
        [7, '12346', '2022-03-01', 2022],
        [8, '12347', '2023-03-01', 2023],
        [9, '12348', '2023-03-01', 2023],
    ];
    
    foreach ($alumnos as $alumno) {
        $sql = "INSERT INTO alumno (persona_id, legajo, fecha_ingreso, cohorte) 
                VALUES (?, ?, ?, ?)";
        ejecutarQuery($pdo, $sql, $alumno);
    }
    
    // Verificar alumnos creados usando la vista
    $stmt = ejecutarQuery($pdo, "SELECT * FROM vista_alumnos");
    $results = $stmt->fetchAll();
    printResults($results, "Alumnos creados");
    
    return true;
}

// Función para probar la creación de cursos y materias
function testCursosMaterias($pdo) {
    echo "\n== Prueba de creación de cursos y materias ==\n";
    
    // Crear cursos
    $cursos = [
        // codigo, division, anio, turno, ciclo_lectivo
        ['1PEF', 'A', '1°', 'Mañana', 2023],
        ['1PEF', 'B', '1°', 'Tarde', 2023],
        ['2PEF', 'A', '2°', 'Mañana', 2023],
        ['2PEF', 'B', '2°', 'Tarde', 2023],
    ];
    
    foreach ($cursos as $curso) {
        $sql = "INSERT INTO curso (codigo, division, anio, turno, ciclo_lectivo) 
                VALUES (?, ?, ?, ?, ?)";
        ejecutarQuery($pdo, $sql, $curso);
    }
    
    // Verificar cursos creados
    $stmt = ejecutarQuery($pdo, "SELECT * FROM curso");
    $results = $stmt->fetchAll();
    printResults($results, "Cursos creados");
    
    // Crear materias
    $materias = [
        // nro_orden, codigo, nombre, tipo, anio, cuatrimestre
        [1, 'MAT101', 'Anatomía Funcional', 'Anual', 1, 'Anual'],
        [2, 'MAT102', 'Fisiología del Ejercicio', 'Anual', 1, 'Anual'],
        [3, 'MAT103', 'Deportes Individuales I', 'Cuatrimestral', 1, '1°'],
        [4, 'MAT104', 'Deportes de Conjunto I', 'Cuatrimestral', 1, '2°'],
        [5, 'MAT201', 'Entrenamiento Deportivo', 'Anual', 2, 'Anual'],
        [6, 'MAT202', 'Didáctica de la Educación Física', 'Anual', 2, 'Anual'],
    ];
    
    foreach ($materias as $materia) {
        $sql = "INSERT INTO materia (nro_orden, codigo, nombre, tipo, anio, cuatrimestre) 
                VALUES (?, ?, ?, ?, ?, ?)";
        ejecutarQuery($pdo, $sql, $materia);
    }
    
    // Verificar materias creadas
    $stmt = ejecutarQuery($pdo, "SELECT * FROM materia");
    $results = $stmt->fetchAll();
    printResults($results, "Materias creadas");
    
    // Definir correlatividades
    $correlatividades = [
        // materia_id, materia_correlativa_id, tipo
        [5, 1, 'Para cursar regularizada'], // Entrenamiento necesita Anatomía regularizada
        [5, 2, 'Para cursar regularizada'], // Entrenamiento necesita Fisiología regularizada
        [6, 3, 'Para cursar acreditada'],   // Didáctica necesita Deportes Individuales aprobada
        [6, 4, 'Para acreditar'],           // Didáctica necesita Deportes de Conjunto para aprobar
    ];
    
    foreach ($correlatividades as $correlatividad) {
        $sql = "INSERT INTO correlatividad (materia_id, materia_correlativa_id, tipo) 
                VALUES (?, ?, ?)";
        ejecutarQuery($pdo, $sql, $correlatividad);
    }
    
    // Verificar correlatividades usando la vista
    $stmt = ejecutarQuery($pdo, "SELECT * FROM vista_correlatividades");
    $results = $stmt->fetchAll();
    printResults($results, "Correlatividades definidas");
    
    return true;
}

// Función para probar asignación de profesores a materias
function testAsignacionProfesores($pdo) {
    echo "\n== Prueba de asignación de profesores a materias ==\n";
    
    // Asignar profesores a materias y cursos
    $asignaciones = [
        // profesor_id, materia_id, curso_id, ciclo_lectivo
        [1, 1, 1, 2023], // Prof. Martínez - Anatomía - 1PEF A - 2023
        [1, 1, 2, 2023], // Prof. Martínez - Anatomía - 1PEF B - 2023
        [2, 2, 1, 2023], // Prof. Sánchez - Fisiología - 1PEF A - 2023
        [2, 2, 2, 2023], // Prof. Sánchez - Fisiología - 1PEF B - 2023
        [3, 3, 1, 2023], // Prof. Rodríguez - Deportes Individuales - 1PEF A - 2023
        [3, 3, 2, 2023], // Prof. Rodríguez - Deportes Individuales - 1PEF B - 2023
        [1, 4, 1, 2023], // Prof. Martínez - Deportes de Conjunto - 1PEF A - 2023
        [1, 4, 2, 2023], // Prof. Martínez - Deportes de Conjunto - 1PEF B - 2023
        [2, 5, 3, 2023], // Prof. Sánchez - Entrenamiento - 2PEF A - 2023
        [2, 5, 4, 2023], // Prof. Sánchez - Entrenamiento - 2PEF B - 2023
        [3, 6, 3, 2023], // Prof. Rodríguez - Didáctica - 2PEF A - 2023
        [3, 6, 4, 2023], // Prof. Rodríguez - Didáctica - 2PEF B - 2023
    ];
    
    foreach ($asignaciones as $asignacion) {
        $sql = "INSERT INTO profesor_materia (profesor_id, materia_id, curso_id, ciclo_lectivo) 
                VALUES (?, ?, ?, ?)";
        ejecutarQuery($pdo, $sql, $asignacion);
    }
    
    // Verificar asignaciones
    $sql = "SELECT 
                pm.id, 
                CONCAT(p.apellidos, ', ', p.nombres) AS profesor, 
                m.nombre AS materia, 
                CONCAT(c.codigo, ' ', c.division) AS curso, 
                pm.ciclo_lectivo
            FROM 
                profesor_materia pm
                JOIN profesor pr ON pm.profesor_id = pr.id
                JOIN persona p ON pr.persona_id = p.id
                JOIN materia m ON pm.materia_id = m.id
                JOIN curso c ON pm.curso_id = c.id
            ORDER BY 
                m.anio, m.nro_orden, c.codigo";
    
    $stmt = ejecutarQuery($pdo, $sql);
    $results = $stmt->fetchAll();
    printResults($results, "Asignaciones de profesores a materias");
    
    return true;
}

// Función para probar inscripciones de alumnos a materias
function testInscripcionesAlumnos($pdo) {
    echo "\n== Prueba de inscripciones de alumnos a materias ==\n";
    
    // Inscribir alumnos a materias (primer año)
    $inscripciones = [
        // alumno_id, materia_id, curso_id, ciclo_lectivo, fecha_inscripcion
        [1, 1, 1, 2023, '2023-03-10'], // Alumno 1 - Anatomía - 1PEF A - 2023
        [1, 2, 1, 2023, '2023-03-10'], // Alumno 1 - Fisiología - 1PEF A - 2023
        [1, 3, 1, 2023, '2023-03-10'], // Alumno 1 - Deportes Individuales - 1PEF A - 2023
        [1, 4, 1, 2023, '2023-03-10'], // Alumno 1 - Deportes de Conjunto - 1PEF A - 2023
        
        [2, 1, 1, 2023, '2023-03-11'], // Alumno 2 - Anatomía - 1PEF A - 2023
        [2, 2, 1, 2023, '2023-03-11'], // Alumno 2 - Fisiología - 1PEF A - 2023
        [2, 3, 1, 2023, '2023-03-11'], // Alumno 2 - Deportes Individuales - 1PEF A - 2023
        [2, 4, 1, 2023, '2023-03-11'], // Alumno 2 - Deportes de Conjunto - 1PEF A - 2023
        
        [3, 1, 2, 2023, '2023-03-12'], // Alumno 3 - Anatomía - 1PEF B - 2023
        [3, 2, 2, 2023, '2023-03-12'], // Alumno 3 - Fisiología - 1PEF B - 2023
        [3, 3, 2, 2023, '2023-03-12'], // Alumno 3 - Deportes Individuales - 1PEF B - 2023
        [3, 4, 2, 2023, '2023-03-12'], // Alumno 3 - Deportes de Conjunto - 1PEF B - 2023
        
        [4, 1, 2, 2023, '2023-03-13'], // Alumno 4 - Anatomía - 1PEF B - 2023
        [4, 2, 2, 2023, '2023-03-13'], // Alumno 4 - Fisiología - 1PEF B - 2023
        [4, 3, 2, 2023, '2023-03-13'], // Alumno 4 - Deportes Individuales - 1PEF B - 2023
        [4, 4, 2, 2023, '2023-03-13'], // Alumno 4 - Deportes de Conjunto - 1PEF B - 2023
    ];
    
    foreach ($inscripciones as $inscripcion) {
        $sql = "INSERT INTO inscripcion_cursado (alumno_id, materia_id, curso_id, ciclo_lectivo, fecha_inscripcion) 
                VALUES (?, ?, ?, ?, ?)";
        ejecutarQuery($pdo, $sql, $inscripcion);
    }
    
    // Verificar inscripciones usando la vista de situación académica
    $stmt = ejecutarQuery($pdo, "SELECT * FROM vista_situacion_academica");
    $results = $stmt->fetchAll();
    printResults($results, "Inscripciones de alumnos a materias");
    
    return true;
}

// Función para probar registro de asistencias
function testAsistencias($pdo) {
    echo "\n== Prueba de registro de asistencias ==\n";
    
    // Registrar asistencias para la materia Anatomía (materia 1)
    $inscripciones = ejecutarQuery($pdo, "SELECT id, alumno_id FROM inscripcion_cursado WHERE materia_id = 1")->fetchAll();
    $fechas = ['2023-04-05', '2023-04-12', '2023-04-19'];
    
    foreach ($inscripciones as $inscripcion) {
        foreach ($fechas as $index => $fecha) {
            // Variar el estado de asistencia para prueba
            $estado = ($index == 1 && $inscripcion['alumno_id'] == 2) ? 'Ausente' : 
                     (($index == 2 && $inscripcion['alumno_id'] == 4) ? 'Justificado' : 'Presente');
            
            $sql = "INSERT INTO asistencia (inscripcion_cursado_id, fecha, estado, profesor_id) 
                    VALUES (?, ?, ?, ?)";
            ejecutarQuery($pdo, $sql, [$inscripcion['id'], $fecha, $estado, 1]);
        }
    }
    
    // Verificar asistencias usando la vista
    $stmt = ejecutarQuery($pdo, "SELECT * FROM vista_asistencia");
    $results = $stmt->fetchAll();
    printResults($results, "Registro de asistencias");
    
    return true;
}

// Función para probar registro de evaluaciones
function testEvaluaciones($pdo) {
    echo "\n== Prueba de registro de evaluaciones y auditoría ==\n";
    
    // Configurar variable de sesión para auditoría
    ejecutarQuery($pdo, "SET @usuario_id = 1");  // ID de usuario admin
    ejecutarQuery($pdo, "SET @ip_origen = '192.168.1.100'");
    
    // Registrar evaluaciones parciales para Deportes Individuales (materia 3)
    $inscripciones = ejecutarQuery($pdo, "SELECT id, alumno_id FROM inscripcion_cursado WHERE materia_id = 3")->fetchAll();
    
    foreach ($inscripciones as $inscripcion) {
        // Parcial de primer cuatrimestre
        $nota = rand(4, 10);  // Nota aleatoria entre 4 y 10
        $sql = "INSERT INTO evaluacion (inscripcion_cursado_id, tipo, instancia, fecha, nota, nota_letra, profesor_id, observaciones) 
                VALUES (?, 'Parcial', '1°Cuatrimestre', '2023-06-15', ?, ?, 3, ?)";
        $nota_letra = convertirNotaALetra($nota);
        $observacion = ($nota >= 8) ? "Excelente desempeño" : "Desempeño satisfactorio";
        ejecutarQuery($pdo, $sql, [$inscripcion['id'], $nota, $nota_letra, $observacion]);
    }
    
    // Verificar evaluaciones
    $stmt = ejecutarQuery($pdo, "SELECT 
                e.id, 
                CONCAT(p.apellidos, ', ', p.nombres) AS alumno,
                a.legajo,
                m.nombre AS materia,
                e.tipo,
                e.instancia,
                e.fecha,
                e.nota,
                e.nota_letra,
                e.observaciones
            FROM 
                evaluacion e
                JOIN inscripcion_cursado ic ON e.inscripcion_cursado_id = ic.id
                JOIN alumno a ON ic.alumno_id = a.id
                JOIN persona p ON a.persona_id = p.id
                JOIN materia m ON ic.materia_id = m.id
            ORDER BY 
                p.apellidos, p.nombres, m.nombre");
    $results = $stmt->fetchAll();
    printResults($results, "Evaluaciones registradas");
    
    // Actualizar una evaluación para verificar el trigger de auditoría
    $evaluacionId = $results[0]['id'];
    $sql = "UPDATE evaluacion SET nota = 9, nota_letra = 'Sobresaliente' WHERE id = ?";
    ejecutarQuery($pdo, $sql, [$evaluacionId]);
    
    // Verificar registros de auditoría
    $stmt = ejecutarQuery($pdo, "SELECT * FROM auditoria");
    $results = $stmt->fetchAll();
    printResults($results, "Registros de auditoría");
    
    return true;
}

// Función para probar el procedimiento de verificar correlatividades
function testVerificarCorrelatividades($pdo) {
    echo "\n== Prueba del procedimiento de verificación de correlatividades ==\n";
    
    // Primero vamos a establecer que los alumnos han regularizado algunas materias
    // Actualizamos estado de las materias de primer año para el alumno 1
    $sql = "UPDATE inscripcion_cursado SET estado = 'Regular' 
            WHERE alumno_id = 1 AND materia_id IN (1, 2, 3)";
    ejecutarQuery($pdo, $sql);
    
    // Registramos un final aprobado para el alumno 1 en materia 3 (Deportes Individuales)
    $inscripcion = ejecutarQuery($pdo, "SELECT id FROM inscripcion_cursado 
                                      WHERE alumno_id = 1 AND materia_id = 3")->fetch();
    
    $sql = "INSERT INTO evaluacion (inscripcion_cursado_id, tipo, instancia, fecha, nota, nota_letra, profesor_id, observaciones) 
            VALUES (?, 'Final', '1°Cuatrimestre', '2023-07-20', 8, 'Muy Bueno', 3, 'Aprobado')";
    ejecutarQuery($pdo, $sql, [$inscripcion['id']]);
    
    // Ahora probamos verificar correlatividades para el alumno 1 en materia 5 (Entrenamiento Deportivo)
    // Debería poder cursarla ya que tiene regularizadas Anatomía y Fisiología
    $puedeAcreditar = false;
    $mensaje = '';
    
    $stmt = $pdo->prepare("CALL verificar_correlatividades(1, 5, @puede_cursar, @mensaje)");
    $stmt->execute();
    
    $stmt = $pdo->query("SELECT @puede_cursar AS puede_cursar, @mensaje AS mensaje");
    $result = $stmt->fetch();
    
    echo "Alumno 1 - Materia 5 (Entrenamiento): \n";
    echo "¿Puede cursar? " . ($result['puede_cursar'] ? 'Sí' : 'No') . "\n";
    echo "Mensaje: " . $result['mensaje'] . "\n\n";
    
    // Probamos verificar correlatividades para el alumno 1 en materia 6 (Didáctica)
    // Debería poder cursarla ya que tiene acreditada Deportes Individuales
    $stmt = $pdo->prepare("CALL verificar_correlatividades(1, 6, @puede_cursar, @mensaje)");
    $stmt->execute();
    
    $stmt = $pdo->query("SELECT @puede_cursar AS puede_cursar, @mensaje AS mensaje");
    $result = $stmt->fetch();
    
    echo "Alumno 1 - Materia 6 (Didáctica): \n";
    echo "¿Puede cursar? " . ($result['puede_cursar'] ? 'Sí' : 'No') . "\n";
    echo "Mensaje: " . $result['mensaje'] . "\n\n";
    
    // Probamos con un alumno que no tenga las correlativas (alumno 2)
    $stmt = $pdo->prepare("CALL verificar_correlatividades(2, 5, @puede_cursar, @mensaje)");
    $stmt->execute();
    
    $stmt = $pdo->query("SELECT @puede_cursar AS puede_cursar, @mensaje AS mensaje");
    $result = $stmt->fetch();
    
    echo "Alumno 2 - Materia 5 (Entrenamiento): \n";
    echo "¿Puede cursar? " . ($result['puede_cursar'] ? 'Sí' : 'No') . "\n";
    echo "Mensaje: " . $result['mensaje'] . "\n\n";
    
    return true;
}

// Función para probar las actas de examen
function testActasExamen($pdo) {
    echo "\n== Prueba de creación de actas de examen ==\n";
    
    // Crear actas de examen para algunas materias
    $actas = [
        // materia_id, curso_id, fecha, tipo, libro, folio, profesor_id
        [1, 1, '2023-07-10', '1°Cuatrimestre', 1, 1, 1], // Anatomía - Turno Julio
        [2, 1, '2023-07-11', '1°Cuatrimestre', 1, 2, 2], // Fisiología - Turno Julio
        [3, 1, '2023-07-12', '1°Cuatrimestre', 1, 3, 3], // Deportes Individuales - Turno Julio
        [4, 1, '2023-12-05', '2°Cuatrimestre', 1, 4, 1], // Deportes de Conjunto - Turno Diciembre
    ];
    
    foreach ($actas as $acta) {
        $sql = "INSERT INTO acta_examen (materia_id, curso_id, fecha, tipo, libro, folio, profesor_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        ejecutarQuery($pdo, $sql, $acta);
    }
    
    // Verificar actas creadas
    $sql = "SELECT 
                ae.id, 
                m.nombre AS materia, 
                CONCAT(c.codigo, ' ', c.division) AS curso, 
                ae.fecha, 
                ae.tipo, 
                ae.libro, 
                ae.folio, 
                CONCAT(p.apellidos, ', ', p.nombres) AS profesor,
                ae.cerrada
            FROM 
                acta_examen ae
                JOIN materia m ON ae.materia_id = m.id
                JOIN curso c ON ae.curso_id = c.id
                JOIN profesor pr ON ae.profesor_id = pr.id
                JOIN persona p ON pr.persona_id = p.id
            ORDER BY 
                ae.fecha";
    
    $stmt = ejecutarQuery($pdo, $sql);
    $results = $stmt->fetchAll();
    printResults($results, "Actas de examen creadas");
    
    return true;
}

// Función para probar inscripciones a exámenes
function testInscripcionesExamen($pdo) {
    echo "\n== Prueba de inscripciones a exámenes ==\n";
    
    // Inscribir alumnos a exámenes
    $inscripciones = [
        // alumno_id, acta_examen_id
        [1, 1], // Alumno 1 - Anatomía
        [1, 2], // Alumno 1 - Fisiología
        [2, 1], // Alumno 2 - Anatomía
        [3, 3], // Alumno 3 - Deportes Individuales
        [4, 3], // Alumno 4 - Deportes Individuales
    ];
    
    foreach ($inscripciones as $inscripcion) {
        $sql = "INSERT INTO inscripcion_examen (alumno_id, acta_examen_id) 
                VALUES (?, ?)";
        ejecutarQuery($pdo, $sql, $inscripcion);
    }
    
    // Marcar algunos alumnos como presentes
    $sql = "UPDATE inscripcion_examen SET estado = 'Presente' WHERE alumno_id IN (1, 2, 3)";
    ejecutarQuery($pdo, $sql);
    
    // Verificar inscripciones a exámenes
    $sql = "SELECT 
                ie.id, 
                CONCAT(p.apellidos, ', ', p.nombres) AS alumno,
                a.legajo,
                m.nombre AS materia,
                ae.fecha AS fecha_examen,
                ie.fecha_inscripcion,
                ie.estado
            FROM 
                inscripcion_examen ie
                JOIN alumno a ON ie.alumno_id = a.id
                JOIN persona p ON a.persona_id = p.id
                JOIN acta_examen ae ON ie.acta_examen_id = ae.id
                JOIN materia m ON ae.materia_id = m.id
            ORDER BY 
                ae.fecha, p.apellidos, p.nombres";
    
    $stmt = ejecutarQuery($pdo, $sql);
    $results = $stmt->fetchAll();
    printResults($results, "Inscripciones a exámenes");
    
    return true;
}

// Función para probar emisión de certificaciones
function testCertificaciones($pdo) {
    echo "\n== Prueba de emisión de certificaciones ==\n";
    
    // Emitir certificaciones para algunos alumnos
    $certificaciones = [
        // alumno_id, fecha_emision, tipo, codigo_verificacion, autorizado_por
        [1, '2023-04-15', 'Alumno Regular', generateVerificationCode(), 2], // Alumno 1 - Cert. Alumno Regular
        [2, '2023-05-20', 'Analítico Parcial', generateVerificationCode(), 2], // Alumno 2 - Cert. Analítico
        [3, '2023-06-10', 'Otro', generateVerificationCode(), 1], // Alumno 3 - Otro tipo
    ];
    
    foreach ($certificaciones as $certificacion) {
        $sql = "INSERT INTO certificacion (alumno_id, fecha_emision, tipo, codigo_verificacion, autorizado_por) 
                VALUES (?, ?, ?, ?, ?)";
        ejecutarQuery($pdo, $sql, $certificacion);
    }
    
    // Verificar certificaciones
    $sql = "SELECT 
                c.id, 
                CONCAT(p.apellidos, ', ', p.nombres) AS alumno,
                a.legajo,
                c.fecha_emision,
                c.tipo,
                c.codigo_verificacion,
                CONCAT(pa.apellidos, ', ', pa.nombres) AS autorizado_por
            FROM 
                certificacion c
                JOIN alumno a ON c.alumno_id = a.id
                JOIN persona p ON a.persona_id = p.id
                JOIN usuario u ON c.autorizado_por = u.id
                JOIN persona pa ON u.id = pa.usuario_id
            ORDER BY 
                c.fecha_emision";
    
    $stmt = ejecutarQuery($pdo, $sql);
    $results = $stmt->fetchAll();
    printResults($results, "Certificaciones emitidas");
    
    return true;
}

// Función para convertir nota numérica a letra
function convertirNotaALetra($nota) {
    switch ($nota) {
        case 10:
            return "Sobresaliente";
        case 9:
            return "Distinguido";
        case 8:
            return "Muy Bueno";
        case 7:
        case 6:
            return "Bueno";
        case 5:
        case 4:
            return "Aprobado";
        default:
            return "Desaprobado";
    }
}

// Función para generar código de verificación para certificaciones
function generateVerificationCode() {
    return strtoupper(substr(md5(uniqid(rand(), true)), 0, 10));
}

// Función para realizar una prueba completa e integral del sistema
function testSistemaCompleto($pdo) {
    echo "\n=== PRUEBA INTEGRAL DEL SISTEMA ===\n";
    
    // 1. Limpiar todos los datos existentes (opcional)
    limpiarDatos($pdo);
    
    // 2. Crear usuarios y personas
    testUsuariosPersonas($pdo);
    
    // 3. Crear profesores
    testProfesores($pdo);
    
    // 4. Crear alumnos
    testAlumnos($pdo);
    
    // 5. Crear cursos y materias con correlatividades
    testCursosMaterias($pdo);
    
    // 6. Asignar profesores a materias
    testAsignacionProfesores($pdo);
    
    // 7. Inscribir alumnos a materias
    testInscripcionesAlumnos($pdo);
    
    // 8. Registrar asistencias
    testAsistencias($pdo);
    
    // 9. Registrar evaluaciones (con trigger de auditoría)
    testEvaluaciones($pdo);
    
    // 10. Verificar correlatividades
    testVerificarCorrelatividades($pdo);
    
    // 11. Crear actas de examen
    testActasExamen($pdo);
    
    // 12. Inscribir alumnos a exámenes
    testInscripcionesExamen($pdo);
    
    // 13. Emitir certificaciones
    testCertificaciones($pdo);
    
    echo "\n=== PRUEBA COMPLETA FINALIZADA ===\n";
    
    return true;
}

// Ejecución principal del script
try {
    // Conectar a la base de datos
    $pdo = conectarDB($config);
    
    // Ejecutar prueba completa del sistema
    testSistemaCompleto($pdo);
    
    echo "\nLa prueba del sistema ha finalizado exitosamente!\n";
} catch (Exception $e) {
    echo "Error durante la ejecución: " . $e->getMessage() . "\n";
}

// Función principal para ejecutar todas las pruebas
function ejecutarPruebas($pdo) {
    echo "\n=== INICIO DE PRUEBAS ===\n";

    // Limpiar datos de prueba anteriores
    limpiarDatos($pdo);

    // Pruebas individuales
    testUsuariosPersonas($pdo);
    testProfesores($pdo);
    testAlumnos($pdo);
    testCursosMaterias($pdo);
    testAsignacionProfesores($pdo);
    testInscripcionesAlumnos($pdo);
    testAsistencias($pdo);
    testEvaluaciones($pdo);
    testVerificarCorrelatividades($pdo);
    testActasExamen($pdo);
    testInscripcionesExamen($pdo);
    testCertificaciones($pdo);

    echo "\n=== PRUEBAS COMPLETADAS ===\n";
}

// Conexión a la base de datos y ejecución de pruebas
try {
    $pdo = conectarDB($config);
    ejecutarPruebas($pdo);
} catch (Exception $e) {
    echo "Error durante la ejecución de las pruebas: " . $e->getMessage() . "\n";
}