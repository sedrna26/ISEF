-- Script de creación de Base de Datos para el Sistema de Gestión Académica
-- Instituto Superior de Educación Física

-- Eliminar base de datos si existe (para desarrollo)
-- DROP DATABASE IF EXISTS isef_sistema;

-- Crear base de datos
CREATE DATABASE isef_sistema CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE isef_sistema;

-- Tabla de usuarios
CREATE TABLE usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Almacenado con hash
    tipo ENUM('administrador', 'preceptor', 'profesor', 'alumno') NOT NULL,
    ultimo_acceso DATETIME,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_tipo (tipo)
);

-- Tabla de personas (información común)
CREATE TABLE persona (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    nombres VARCHAR(100) NOT NULL,
    dni VARCHAR(15) NOT NULL UNIQUE,
    fecha_nacimiento DATE NOT NULL,
    celular VARCHAR(20),
    domicilio VARCHAR(255),
    contacto_emergencia VARCHAR(255),
    foto_url VARCHAR(255),
    FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE CASCADE,
    INDEX idx_dni (dni),
    INDEX idx_apellidos_nombres (apellidos, nombres)
);

-- Tabla de profesores
CREATE TABLE profesor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    persona_id INT NOT NULL UNIQUE,
    titulo_profesional VARCHAR(255) NOT NULL,
    fecha_ingreso DATE NOT NULL,
    horas_consulta VARCHAR(255),
    FOREIGN KEY (persona_id) REFERENCES persona(id) ON DELETE CASCADE
);

-- Tabla de alumnos
CREATE TABLE alumno (
    id INT AUTO_INCREMENT PRIMARY KEY,
    persona_id INT NOT NULL UNIQUE,
    legajo VARCHAR(20) NOT NULL UNIQUE,
    fecha_ingreso DATE NOT NULL,
    cohorte INT NOT NULL,
    FOREIGN KEY (persona_id) REFERENCES persona(id) ON DELETE CASCADE,
    INDEX idx_legajo (legajo),
    INDEX idx_cohorte (cohorte)
);

-- Tabla de cursos
CREATE TABLE curso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(10) NOT NULL,
    division VARCHAR(5) NOT NULL,
    anio VARCHAR(5) NOT NULL,
    turno ENUM('Mañana', 'Tarde') NOT NULL,
    ciclo_lectivo INT NOT NULL,
    UNIQUE KEY uk_curso_completo (codigo, division, ciclo_lectivo),
    INDEX idx_turno (turno),
    INDEX idx_ciclo_lectivo (ciclo_lectivo)
);

-- Tabla de materias
CREATE TABLE materia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nro_orden INT NOT NULL,
    codigo VARCHAR(20) NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    tipo ENUM('Cuatrimestral', 'Anual') NOT NULL,
    anio INT NOT NULL,
    cuatrimestre ENUM('1°', '2°', 'Anual') NOT NULL,
    UNIQUE KEY uk_materia_codigo (codigo),
    INDEX idx_nro_orden (nro_orden),
    INDEX idx_anio_cuatrimestre (anio, cuatrimestre)
);

-- Tabla de correlatividades
CREATE TABLE correlatividad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    materia_id INT NOT NULL,
    materia_correlativa_id INT NOT NULL,
    tipo ENUM('Para cursar regularizada', 'Para cursar acreditada', 'Para acreditar') NOT NULL,
    FOREIGN KEY (materia_id) REFERENCES materia(id) ON DELETE CASCADE,
    FOREIGN KEY (materia_correlativa_id) REFERENCES materia(id) ON DELETE CASCADE,
    UNIQUE KEY uk_correlatividad (materia_id, materia_correlativa_id, tipo),
    INDEX idx_tipo_correlatividad (tipo)
);

-- Tabla de asignación profesor-materia-curso
CREATE TABLE profesor_materia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profesor_id INT NOT NULL,
    materia_id INT NOT NULL,
    curso_id INT NOT NULL,
    ciclo_lectivo INT NOT NULL,
    FOREIGN KEY (profesor_id) REFERENCES profesor(id) ON DELETE CASCADE,
    FOREIGN KEY (materia_id) REFERENCES materia(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES curso(id) ON DELETE CASCADE,
    UNIQUE KEY uk_profesor_materia_curso (profesor_id, materia_id, curso_id, ciclo_lectivo),
    INDEX idx_ciclo_lectivo (ciclo_lectivo)
);

-- Tabla de inscripciones a cursado
CREATE TABLE inscripcion_cursado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alumno_id INT NOT NULL,
    materia_id INT NOT NULL,
    curso_id INT NOT NULL,
    ciclo_lectivo INT NOT NULL,
    fecha_inscripcion DATE NOT NULL,
    estado ENUM('Regular', 'Libre', 'Promocional') DEFAULT 'Regular',
    FOREIGN KEY (alumno_id) REFERENCES alumno(id) ON DELETE CASCADE,
    FOREIGN KEY (materia_id) REFERENCES materia(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES curso(id) ON DELETE CASCADE,
    UNIQUE KEY uk_inscripcion_cursado (alumno_id, materia_id, curso_id, ciclo_lectivo),
    INDEX idx_estado (estado),
    INDEX idx_ciclo_lectivo (ciclo_lectivo)
);

-- Tabla de asistencias
CREATE TABLE asistencia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inscripcion_cursado_id INT NOT NULL,
    fecha DATE NOT NULL,
    estado ENUM('Presente', 'Ausente', 'Justificado') NOT NULL,
    profesor_id INT NOT NULL,
    FOREIGN KEY (inscripcion_cursado_id) REFERENCES inscripcion_cursado(id) ON DELETE CASCADE,
    FOREIGN KEY (profesor_id) REFERENCES profesor(id) ON DELETE CASCADE,
    UNIQUE KEY uk_asistencia (inscripcion_cursado_id, fecha),
    INDEX idx_fecha (fecha),
    INDEX idx_estado (estado)
);

-- Tabla de evaluaciones
CREATE TABLE evaluacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inscripcion_cursado_id INT NOT NULL,
    tipo ENUM('Parcial', 'Final', 'Coloquio') NOT NULL,
    instancia ENUM('1°Cuatrimestre', '2°Cuatrimestre', 'Anual') NOT NULL,
    fecha DATE NOT NULL,
    nota INT,
    nota_letra VARCHAR(50),
    profesor_id INT NOT NULL,
    observaciones TEXT,
    FOREIGN KEY (inscripcion_cursado_id) REFERENCES inscripcion_cursado(id) ON DELETE CASCADE,
    FOREIGN KEY (profesor_id) REFERENCES profesor(id) ON DELETE CASCADE,
    INDEX idx_tipo_instancia (tipo, instancia),
    INDEX idx_fecha (fecha)
);

-- Tabla de actas de examen
CREATE TABLE acta_examen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    materia_id INT NOT NULL,
    curso_id INT NOT NULL,
    fecha DATE NOT NULL,
    tipo ENUM('1°Cuatrimestre', '2°Cuatrimestre', 'Anual') NOT NULL,
    libro INT,
    folio INT,
    profesor_id INT NOT NULL,
    cerrada BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (materia_id) REFERENCES materia(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES curso(id) ON DELETE CASCADE,
    FOREIGN KEY (profesor_id) REFERENCES profesor(id) ON DELETE CASCADE,
    INDEX idx_fecha (fecha),
    INDEX idx_libro_folio (libro, folio),
    INDEX idx_cerrada (cerrada)
);

-- Tabla de inscripciones a exámenes
CREATE TABLE inscripcion_examen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alumno_id INT NOT NULL,
    acta_examen_id INT NOT NULL,
    fecha_inscripcion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('Inscripto', 'Presente', 'Ausente') DEFAULT 'Inscripto',
    FOREIGN KEY (alumno_id) REFERENCES alumno(id) ON DELETE CASCADE,
    FOREIGN KEY (acta_examen_id) REFERENCES acta_examen(id) ON DELETE CASCADE,
    UNIQUE KEY uk_inscripcion_examen (alumno_id, acta_examen_id),
    INDEX idx_estado (estado),
    INDEX idx_fecha_inscripcion (fecha_inscripcion)
);

-- Tabla de certificaciones
CREATE TABLE certificacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alumno_id INT NOT NULL,
    fecha_emision DATE NOT NULL,
    tipo ENUM('Alumno Regular', 'Analítico Parcial', 'Otro') NOT NULL,
    codigo_verificacion VARCHAR(50) UNIQUE,
    autorizado_por INT NOT NULL,
    FOREIGN KEY (alumno_id) REFERENCES alumno(id) ON DELETE CASCADE,
    FOREIGN KEY (autorizado_por) REFERENCES usuario(id) ON DELETE CASCADE,
    INDEX idx_fecha_emision (fecha_emision),
    INDEX idx_tipo (tipo)
);

-- Tabla de auditoría
CREATE TABLE auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    tipo_operacion VARCHAR(20) NOT NULL,
    tabla_afectada VARCHAR(50) NOT NULL,
    registro_afectado VARCHAR(50) NOT NULL,
    valor_anterior TEXT,
    valor_nuevo TEXT,
    ip_origen VARCHAR(50),
    FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE SET NULL,
    INDEX idx_fecha_hora (fecha_hora),
    INDEX idx_tipo_operacion (tipo_operacion),
    INDEX idx_tabla_afectada (tabla_afectada)
);

-- Tabla de licencias de profesores
CREATE TABLE licencia_profesor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profesor_id INT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    tipo ENUM('Enfermedad', 'Particular', 'Otro') NOT NULL,
    observaciones TEXT,
    FOREIGN KEY (profesor_id) REFERENCES profesor(id) ON DELETE CASCADE,
    INDEX idx_fecha_inicio_fin (fecha_inicio, fecha_fin),
    INDEX idx_tipo (tipo)
);

-- Insertar usuarios de prueba (sólo para desarrollo)
INSERT INTO usuario (username, password, tipo) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrador'), -- password: password
('preceptor', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'preceptor'),
('profesor', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'profesor'),
('alumno', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumno');

-- Creación de vistas útiles

-- Vista para mostrar información completa de personas
CREATE VIEW vista_personas AS
SELECT 
    p.id,
    p.dni,
    p.apellidos,
    p.nombres,
    p.fecha_nacimiento,
    p.celular,
    p.domicilio,
    p.contacto_emergencia,
    u.username,
    u.tipo,
    u.activo
FROM 
    persona p
JOIN 
    usuario u ON p.usuario_id = u.id;

-- Vista para mostrar información completa de profesores
CREATE VIEW vista_profesores AS
SELECT 
    p.id AS profesor_id,
    pers.apellidos,
    pers.nombres,
    pers.dni,
    p.titulo_profesional,
    p.fecha_ingreso,
    p.horas_consulta,
    pers.celular,
    pers.domicilio,
    pers.contacto_emergencia,
    u.username,
    u.activo
FROM 
    profesor p
JOIN 
    persona pers ON p.persona_id = pers.id
JOIN 
    usuario u ON pers.usuario_id = u.id;

-- Vista para mostrar información completa de alumnos
CREATE VIEW vista_alumnos AS
SELECT 
    a.id AS alumno_id,
    pers.apellidos,
    pers.nombres,
    pers.dni,
    a.legajo,
    a.fecha_ingreso,
    a.cohorte,
    pers.fecha_nacimiento,
    pers.celular,
    pers.domicilio,
    pers.contacto_emergencia,
    u.username,
    u.activo
FROM 
    alumno a
JOIN 
    persona pers ON a.persona_id = pers.id
JOIN 
    usuario u ON pers.usuario_id = u.id;

-- Vista para mostrar asignaturas con sus correlatividades
CREATE VIEW vista_correlatividades AS
SELECT 
    m.id AS materia_id,
    m.nro_orden,
    m.codigo,
    m.nombre AS materia_nombre,
    m.tipo,
    m.anio,
    m.cuatrimestre,
    mc.codigo AS correlativa_codigo,
    mc.nombre AS correlativa_nombre,
    c.tipo AS tipo_correlatividad
FROM 
    materia m
LEFT JOIN 
    correlatividad c ON m.id = c.materia_id
LEFT JOIN 
    materia mc ON c.materia_correlativa_id = mc.id;

-- Vista para mostrar situación académica del alumno
CREATE VIEW vista_situacion_academica AS
SELECT 
    a.id AS alumno_id,
    a.legajo,
    CONCAT(p.apellidos, ', ', p.nombres) AS alumno_nombre,
    m.nro_orden,
    m.codigo AS materia_codigo,
    m.nombre AS materia_nombre,
    ic.estado,
    c.codigo AS curso_codigo,
    c.ciclo_lectivo,
    (SELECT MAX(nota) FROM evaluacion e WHERE e.inscripcion_cursado_id = ic.id AND e.tipo = 'Final') AS nota_final
FROM 
    alumno a
JOIN 
    persona p ON a.persona_id = p.id
JOIN 
    inscripcion_cursado ic ON a.id = ic.alumno_id
JOIN 
    materia m ON ic.materia_id = m.id
JOIN 
    curso c ON ic.curso_id = c.id;

-- Vista para asistencia de alumnos
CREATE VIEW vista_asistencia AS
SELECT 
    a.legajo,
    CONCAT(p.apellidos, ', ', p.nombres) AS alumno_nombre,
    m.codigo AS materia_codigo,
    m.nombre AS materia_nombre,
    c.codigo AS curso_codigo,
    c.ciclo_lectivo,
    asist.fecha,
    asist.estado,
    CONCAT(pp.apellidos, ', ', pp.nombres) AS profesor_nombre
FROM 
    asistencia asist
JOIN 
    inscripcion_cursado ic ON asist.inscripcion_cursado_id = ic.id
JOIN 
    alumno a ON ic.alumno_id = a.id
JOIN 
    persona p ON a.persona_id = p.id
JOIN 
    materia m ON ic.materia_id = m.id
JOIN 
    curso c ON ic.curso_id = c.id
JOIN 
    profesor prof ON asist.profesor_id = prof.id
JOIN 
    persona pp ON prof.persona_id = pp.id;

-- Procedimiento almacenado para verificar correlatividades
DELIMITER //
CREATE PROCEDURE verificar_correlatividades(
    IN p_alumno_id INT,
    IN p_materia_id INT,
    OUT p_puede_cursar BOOLEAN,
    OUT p_mensaje VARCHAR(255)
)
BEGIN
    DECLARE v_cumple_requisitos BOOLEAN DEFAULT TRUE;
    DECLARE v_correlativas_faltantes VARCHAR(255) DEFAULT '';
    
    -- Verificar correlatividades para cursar regularizadas
    SELECT 
        NOT EXISTS (
            SELECT 1
            FROM correlatividad c
            JOIN materia m ON c.materia_correlativa_id = m.id
            LEFT JOIN (
                SELECT 
                    ic.materia_id,
                    ic.estado
                FROM 
                    inscripcion_cursado ic
                WHERE 
                    ic.alumno_id = p_alumno_id
                    AND (ic.estado = 'Regular' OR ic.estado = 'Promocional')
            ) icr ON c.materia_correlativa_id = icr.materia_id
            WHERE 
                c.materia_id = p_materia_id
                AND c.tipo = 'Para cursar regularizada'
                AND icr.materia_id IS NULL
        ) INTO v_cumple_requisitos;
    
    IF NOT v_cumple_requisitos THEN
        SELECT GROUP_CONCAT(m.nombre SEPARATOR ', ')
        INTO v_correlativas_faltantes
        FROM correlatividad c
        JOIN materia m ON c.materia_correlativa_id = m.id
        LEFT JOIN (
            SELECT 
                ic.materia_id,
                ic.estado
            FROM 
                inscripcion_cursado ic
            WHERE 
                ic.alumno_id = p_alumno_id
                AND (ic.estado = 'Regular' OR ic.estado = 'Promocional')
        ) icr ON c.materia_correlativa_id = icr.materia_id
        WHERE 
            c.materia_id = p_materia_id
            AND c.tipo = 'Para cursar regularizada'
            AND icr.materia_id IS NULL;
            
        SET p_puede_cursar = FALSE;
        SET p_mensaje = CONCAT('Falta regularizar: ', v_correlativas_faltantes);
        SELECT p_puede_cursar, p_mensaje;
        RETURN;
    END IF;
    
    -- Verificar correlatividades para cursar acreditadas
    SELECT 
        NOT EXISTS (
            SELECT 1
            FROM correlatividad c
            JOIN materia m ON c.materia_correlativa_id = m.id
            LEFT JOIN (
                SELECT 
                    e.inscripcion_cursado_id,
                    ic.materia_id
                FROM 
                    evaluacion e
                JOIN 
                    inscripcion_cursado ic ON e.inscripcion_cursado_id = ic.id
                WHERE 
                    ic.alumno_id = p_alumno_id
                    AND e.tipo = 'Final'
                    AND e.nota >= 4
            ) ea ON c.materia_correlativa_id = ea.materia_id
            WHERE 
                c.materia_id = p_materia_id
                AND c.tipo = 'Para cursar acreditada'
                AND ea.materia_id IS NULL
        ) INTO v_cumple_requisitos;
    
    IF NOT v_cumple_requisitos THEN
        SELECT GROUP_CONCAT(m.nombre SEPARATOR ', ')
        INTO v_correlativas_faltantes
        FROM correlatividad c
        JOIN materia m ON c.materia_correlativa_id = m.id
        LEFT JOIN (
            SELECT 
                e.inscripcion_cursado_id,
                ic.materia_id
            FROM 
                evaluacion e
            JOIN 
                inscripcion_cursado ic ON e.inscripcion_cursado_id = ic.id
            WHERE 
                ic.alumno_id = p_alumno_id
                AND e.tipo = 'Final'
                AND e.nota >= 4
        ) ea ON c.materia_correlativa_id = ea.materia_id
        WHERE 
            c.materia_id = p_materia_id
            AND c.tipo = 'Para cursar acreditada'
            AND ea.materia_id IS NULL;
            
        SET p_puede_cursar = FALSE;
        SET p_mensaje = CONCAT('Falta acreditar: ', v_correlativas_faltantes);
        SELECT p_puede_cursar, p_mensaje;
        RETURN;
    END IF;
    
    SET p_puede_cursar = TRUE;
    SET p_mensaje = 'Cumple con todas las correlatividades';
    SELECT p_puede_cursar, p_mensaje;
END //
DELIMITER ;

-- Trigger para auditoría de cambios en evaluaciones
DELIMITER //
CREATE TRIGGER audit_evaluacion_update 
AFTER UPDATE ON evaluacion
FOR EACH ROW
BEGIN
    INSERT INTO auditoria (
        usuario_id,
        tipo_operacion,
        tabla_afectada,
        registro_afectado,
        valor_anterior,
        valor_nuevo,
        ip_origen
    ) VALUES (
        @usuario_id,
        'UPDATE',
        'evaluacion',
        NEW.id,
        CONCAT('{"nota":', IFNULL(OLD.nota, 'null'), ',"nota_letra":"', IFNULL(OLD.nota_letra, ''), '"}'),
        CONCAT('{"nota":', IFNULL(NEW.nota, 'null'), ',"nota_letra":"', IFNULL(NEW.nota_letra, ''), '"}'),
        @ip_origen
    );
END //
DELIMITER ;

-- Trigger para auditoría de inserciones en evaluaciones
DELIMITER //
CREATE TRIGGER audit_evaluacion_insert
AFTER INSERT ON evaluacion
FOR EACH ROW
BEGIN
    INSERT INTO auditoria (
        usuario_id,
        tipo_operacion,
        tabla_afectada,
        registro_afectado,
        valor_anterior,
        valor_nuevo,
        ip_origen
    ) VALUES (
        @usuario_id,
        'INSERT',
        'evaluacion',
        NEW.id,
        NULL,
        CONCAT('{"nota":', IFNULL(NEW.nota, 'null'), ',"nota_letra":"', IFNULL(NEW.nota_letra, ''), '"}'),
        @ip_origen
    );
END //
DELIMITER ;