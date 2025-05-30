-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 27-05-2025 a las 08:16:18
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `isef_sistema`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `verificar_correlatividades` (IN `p_alumno_id` INT, IN `p_materia_id` INT, OUT `p_puede_cursar` BOOLEAN, OUT `p_mensaje` VARCHAR(255))   BEGIN
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
        -- Eliminar este SELECT que podría causar problemas
        -- SELECT p_puede_cursar, p_mensaje;
        -- No usar RETURN aquí
    ELSE
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
            -- Eliminar este SELECT
            -- SELECT p_puede_cursar, p_mensaje;
        ELSE
            SET p_puede_cursar = TRUE;
            SET p_mensaje = 'Cumple con todas las correlatividades';
        END IF;
    END IF;
    
    -- Esta línea es opcional: mostrar el resultado dentro del procedimiento
    -- pero puede causar problemas si se usa como subquery
    -- SELECT p_puede_cursar AS puede_cursar, p_mensaje AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `verificar_requisitos_inscripcion` (IN `p_alumno_id` INT, IN `p_materia_id` INT, OUT `p_puede_cursar_regular` BOOLEAN, OUT `p_mensaje_cursar_regular` VARCHAR(1000), OUT `p_puede_inscribir_libre` BOOLEAN, OUT `p_mensaje_inscribir_libre` VARCHAR(1000))   BEGIN
    DECLARE v_correlativas_faltantes_reg_reg VARCHAR(500) DEFAULT '';
    DECLARE v_correlativas_faltantes_reg_acr VARCHAR(500) DEFAULT '';
    DECLARE v_correlativas_faltantes_libre VARCHAR(500) DEFAULT '';
    DECLARE v_cumple_reg_reg BOOLEAN DEFAULT TRUE;
    DECLARE v_cumple_reg_acr BOOLEAN DEFAULT TRUE;
    DECLARE v_cumple_libre BOOLEAN DEFAULT TRUE;
    DECLARE v_count_para_acreditar INT DEFAULT 0; -- Declaración movida aquí

    -- Inicializar mensajes
    SET p_mensaje_cursar_regular = '';
    SET p_mensaje_inscribir_libre = '';

    -- >> LÓGICA PARA CURSAR REGULAR <<

    -- 1. Verificar correlatividades 'Para cursar regularizada'
    SELECT GROUP_CONCAT(m_corr.nombre SEPARATOR ', ')
    INTO v_correlativas_faltantes_reg_reg
    FROM correlatividad c
    JOIN materia m_corr ON c.materia_correlativa_id = m_corr.id
    WHERE c.materia_id = p_materia_id
      AND c.tipo = 'Para cursar regularizada'
      AND NOT EXISTS (
          SELECT 1
          FROM inscripcion_cursado ic
          WHERE ic.alumno_id = p_alumno_id
            AND ic.materia_id = c.materia_correlativa_id
            AND (ic.estado = 'Regular' OR ic.estado = 'Promocional')
      );

    IF v_correlativas_faltantes_reg_reg IS NOT NULL AND v_correlativas_faltantes_reg_reg != '' THEN
        SET v_cumple_reg_reg = FALSE;
        SET p_mensaje_cursar_regular = CONCAT('Falta regularizar: ', v_correlativas_faltantes_reg_reg);
    END IF;

    -- 2. Verificar correlatividades 'Para cursar acreditada'
    SELECT GROUP_CONCAT(m_corr.nombre SEPARATOR ', ')
    INTO v_correlativas_faltantes_reg_acr
    FROM correlatividad c
    JOIN materia m_corr ON c.materia_correlativa_id = m_corr.id
    WHERE c.materia_id = p_materia_id
      AND c.tipo = 'Para cursar acreditada'
      AND NOT EXISTS (
          SELECT 1
          FROM evaluacion e
          JOIN inscripcion_cursado ic ON e.inscripcion_cursado_id = ic.id
          WHERE ic.alumno_id = p_alumno_id
            AND ic.materia_id = c.materia_correlativa_id
            AND e.tipo = 'Final'
            AND e.nota >= 4 -- Asumiendo que 4 es la nota mínima de aprobación
      );

    IF v_correlativas_faltantes_reg_acr IS NOT NULL AND v_correlativas_faltantes_reg_acr != '' THEN
        SET v_cumple_reg_acr = FALSE;
        IF p_mensaje_cursar_regular != '' THEN
            SET p_mensaje_cursar_regular = CONCAT(p_mensaje_cursar_regular, '; ');
        END IF;
        SET p_mensaje_cursar_regular = CONCAT(p_mensaje_cursar_regular, 'Falta acreditar para cursar: ', v_correlativas_faltantes_reg_acr);
    END IF;

    -- Determinar si puede cursar regular
    IF v_cumple_reg_reg AND v_cumple_reg_acr THEN
        SET p_puede_cursar_regular = TRUE;
        SET p_mensaje_cursar_regular = 'Cumple con los requisitos para cursar regular.';
    ELSE
        SET p_puede_cursar_regular = FALSE;
        IF p_mensaje_cursar_regular = '' THEN -- En caso de que no haya ninguna correlativa de estos tipos
             SET p_puede_cursar_regular = TRUE; -- Si no hay correlativas para cursar, puede cursar.
             SET p_mensaje_cursar_regular = 'No se requieren correlativas para cursar regular.';
        END IF;
    END IF;


    -- >> LÓGICA PARA INSCRIBIR LIBRE <<
    -- (Basado en "Para acreditar tener acreditado" del PDF)
    SELECT GROUP_CONCAT(m_corr.nombre SEPARATOR ', ')
    INTO v_correlativas_faltantes_libre
    FROM correlatividad c
    JOIN materia m_corr ON c.materia_correlativa_id = m_corr.id
    WHERE c.materia_id = p_materia_id
      AND c.tipo = 'Para acreditar' -- Este es el tipo clave para "libre"
      AND NOT EXISTS (
          SELECT 1
          FROM evaluacion e
          JOIN inscripcion_cursado ic ON e.inscripcion_cursado_id = ic.id
          WHERE ic.alumno_id = p_alumno_id
            AND ic.materia_id = c.materia_correlativa_id
            AND e.tipo = 'Final'
            AND e.nota >= 4 -- Asumiendo que 4 es la nota mínima de aprobación
      );

    IF v_correlativas_faltantes_libre IS NOT NULL AND v_correlativas_faltantes_libre != '' THEN
        SET v_cumple_libre = FALSE;
        SET p_mensaje_inscribir_libre = CONCAT('Para inscribir libre, falta acreditar: ', v_correlativas_faltantes_libre);
    ELSE
        -- Resetear v_count_para_acreditar para este chequeo específico.
        SET v_count_para_acreditar = 0;
        SELECT COUNT(*)
        INTO v_count_para_acreditar
        FROM correlatividad c_check
        WHERE c_check.materia_id = p_materia_id AND c_check.tipo = 'Para acreditar';

        IF v_count_para_acreditar = 0 THEN
            SET p_mensaje_inscribir_libre = 'No se requieren correlativas específicas para inscribir libre.';
        ELSE
            SET p_mensaje_inscribir_libre = 'Cumple con los requisitos para inscribir libre.';
        END IF;
        -- Si no hay correlativas faltantes (v_correlativas_faltantes_libre es NULL o vacío)
        -- y SÍ existen correlativas de tipo 'Para acreditar' (v_count_para_acreditar > 0),
        -- o NO existen correlativas de tipo 'Para acreditar' (v_count_para_acreditar = 0),
        -- entonces se cumple.
        SET v_cumple_libre = TRUE;
    END IF;
    
    SET p_puede_inscribir_libre = v_cumple_libre;

END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `acta_examen`
--

CREATE TABLE `acta_examen` (
  `id` int(11) NOT NULL,
  `materia_id` int(11) NOT NULL,
  `curso_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `tipo` enum('1°Cuatrimestre','2°Cuatrimestre','Anual') NOT NULL,
  `libro` int(11) DEFAULT NULL,
  `folio` int(11) DEFAULT NULL,
  `profesor_id` int(11) NOT NULL,
  `cerrada` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `acta_examen`
--

INSERT INTO `acta_examen` (`id`, `materia_id`, `curso_id`, `fecha`, `tipo`, `libro`, `folio`, `profesor_id`, `cerrada`) VALUES
(1, 1, 1, '2023-07-10', '1°Cuatrimestre', 1, 1, 1, 0),
(2, 2, 1, '2023-07-11', '1°Cuatrimestre', 1, 2, 2, 0),
(3, 3, 1, '2023-07-12', '1°Cuatrimestre', 1, 3, 3, 0),
(4, 4, 1, '2023-12-05', '2°Cuatrimestre', 1, 4, 1, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno`
--

CREATE TABLE `alumno` (
  `id` int(11) NOT NULL,
  `persona_id` int(11) NOT NULL,
  `legajo` varchar(20) NOT NULL,
  `fecha_ingreso` date NOT NULL,
  `cohorte` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `alumno`
--

INSERT INTO `alumno` (`id`, `persona_id`, `legajo`, `fecha_ingreso`, `cohorte`) VALUES
(1, 6, '12345', '2022-03-01', 2022),
(2, 7, '12346', '2022-03-01', 2022),
(3, 8, '12347', '2023-03-01', 2023),
(4, 9, '12348', '2023-03-01', 2023),
(5, 12, '', '0000-00-00', 2025),
(6, 14, '2113', '0000-00-00', 2024),
(8, 17, '2114', '0000-00-00', 2025);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencia`
--

CREATE TABLE `asistencia` (
  `id` int(11) NOT NULL,
  `inscripcion_cursado_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `estado` enum('Presente','Ausente','Justificado') NOT NULL,
  `profesor_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `asistencia`
--

INSERT INTO `asistencia` (`id`, `inscripcion_cursado_id`, `fecha`, `estado`, `profesor_id`) VALUES
(1, 1, '2023-04-05', 'Presente', 1),
(2, 1, '2023-04-12', 'Presente', 1),
(3, 1, '2023-04-19', 'Presente', 1),
(4, 5, '2023-04-05', 'Presente', 1),
(5, 5, '2023-04-12', 'Ausente', 1),
(6, 5, '2023-04-19', 'Presente', 1),
(7, 9, '2023-04-05', 'Presente', 1),
(8, 9, '2023-04-12', 'Presente', 1),
(9, 9, '2023-04-19', 'Presente', 1),
(10, 13, '2023-04-05', 'Presente', 1),
(11, 13, '2023-04-12', 'Presente', 1),
(12, 13, '2023-04-19', 'Justificado', 1),
(13, 1, '2025-05-11', 'Presente', 1),
(14, 5, '2025-05-11', 'Presente', 1),
(15, 11, '2025-05-12', 'Presente', 1),
(16, 15, '2025-05-12', 'Ausente', 1),
(17, 1, '2025-05-15', 'Ausente', 1),
(18, 5, '2025-05-15', 'Justificado', 1),
(19, 11, '2025-05-22', 'Presente', 1),
(20, 15, '2025-05-22', 'Presente', 1),
(21, 4, '2025-05-22', 'Presente', 1),
(22, 8, '2025-05-22', 'Justificado', 1),
(23, 2, '2025-05-22', 'Presente', 1),
(24, 6, '2025-05-22', 'Presente', 1),
(26, 12, '2025-05-24', 'Presente', 1),
(29, 4, '2025-05-01', 'Presente', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria`
--

CREATE TABLE `auditoria` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha_hora` datetime DEFAULT current_timestamp(),
  `tipo_operacion` varchar(20) NOT NULL,
  `tabla_afectada` varchar(50) NOT NULL,
  `registro_afectado` varchar(50) NOT NULL,
  `valor_anterior` text DEFAULT NULL,
  `valor_nuevo` text DEFAULT NULL,
  `ip_origen` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `auditoria`
--

INSERT INTO `auditoria` (`id`, `usuario_id`, `fecha_hora`, `tipo_operacion`, `tabla_afectada`, `registro_afectado`, `valor_anterior`, `valor_nuevo`, `ip_origen`) VALUES
(1, 1, '2025-05-09 10:57:02', 'INSERT', 'evaluacion', '1', NULL, '{\"nota\":9,\"nota_letra\":\"Distinguido\"}', '192.168.1.100'),
(2, 1, '2025-05-09 10:57:02', 'INSERT', 'evaluacion', '2', NULL, '{\"nota\":4,\"nota_letra\":\"Aprobado\"}', '192.168.1.100'),
(3, 1, '2025-05-09 10:57:02', 'INSERT', 'evaluacion', '3', NULL, '{\"nota\":9,\"nota_letra\":\"Distinguido\"}', '192.168.1.100'),
(4, 1, '2025-05-09 10:57:02', 'INSERT', 'evaluacion', '4', NULL, '{\"nota\":7,\"nota_letra\":\"Bueno\"}', '192.168.1.100'),
(5, 1, '2025-05-09 10:57:02', 'UPDATE', 'evaluacion', '3', '{\"nota\":9,\"nota_letra\":\"Distinguido\"}', '{\"nota\":9,\"nota_letra\":\"Sobresaliente\"}', '192.168.1.100'),
(6, 1, '2025-05-09 10:57:02', 'INSERT', 'evaluacion', '5', NULL, '{\"nota\":8,\"nota_letra\":\"Muy Bueno\"}', '192.168.1.100'),
(7, NULL, '2025-05-24 13:43:04', 'INSERT', 'evaluacion', '6', NULL, '{\"nota\":10,\"nota_letra\":\"\"}', NULL),
(8, NULL, '2025-05-24 13:43:04', 'INSERT', 'evaluacion', '7', NULL, '{\"nota\":10,\"nota_letra\":\"\"}', NULL),
(9, NULL, '2025-05-24 13:43:04', 'INSERT', 'evaluacion', '8', NULL, '{\"nota\":8,\"nota_letra\":\"\"}', NULL),
(10, NULL, '2025-05-24 13:43:04', 'UPDATE', 'evaluacion', '6', '{\"nota\":10,\"nota_letra\":\"\"}', '{\"nota\":6,\"nota_letra\":\"\"}', NULL),
(11, NULL, '2025-05-24 13:43:04', 'INSERT', 'evaluacion', '9', NULL, '{\"nota\":5,\"nota_letra\":\"\"}', NULL),
(12, NULL, '2025-05-24 13:43:04', 'INSERT', 'evaluacion', '10', NULL, '{\"nota\":10,\"nota_letra\":\"\"}', NULL),
(13, NULL, '2025-05-24 13:43:39', 'INSERT', 'evaluacion', '11', NULL, '{\"nota\":10,\"nota_letra\":\"\"}', NULL),
(14, NULL, '2025-05-24 13:43:39', 'UPDATE', 'evaluacion', '7', '{\"nota\":10,\"nota_letra\":\"\"}', '{\"nota\":10,\"nota_letra\":\"\"}', NULL),
(15, NULL, '2025-05-24 13:43:39', 'UPDATE', 'evaluacion', '8', '{\"nota\":8,\"nota_letra\":\"\"}', '{\"nota\":8,\"nota_letra\":\"\"}', NULL),
(16, NULL, '2025-05-24 13:43:39', 'UPDATE', 'evaluacion', '6', '{\"nota\":6,\"nota_letra\":\"\"}', '{\"nota\":6,\"nota_letra\":\"\"}', NULL),
(17, NULL, '2025-05-24 13:43:39', 'UPDATE', 'evaluacion', '9', '{\"nota\":5,\"nota_letra\":\"\"}', '{\"nota\":5,\"nota_letra\":\"\"}', NULL),
(18, NULL, '2025-05-24 13:43:39', 'UPDATE', 'evaluacion', '10', '{\"nota\":10,\"nota_letra\":\"\"}', '{\"nota\":10,\"nota_letra\":\"\"}', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `certificacion`
--

CREATE TABLE `certificacion` (
  `id` int(11) NOT NULL,
  `alumno_id` int(11) NOT NULL,
  `fecha_emision` date NOT NULL,
  `tipo` enum('Alumno Regular','Analítico Parcial','Otro') NOT NULL,
  `codigo_verificacion` varchar(50) DEFAULT NULL,
  `autorizado_por` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `certificacion`
--

INSERT INTO `certificacion` (`id`, `alumno_id`, `fecha_emision`, `tipo`, `codigo_verificacion`, `autorizado_por`) VALUES
(1, 1, '2023-04-15', 'Alumno Regular', '85EF5F16FC', 2),
(2, 2, '2023-05-20', 'Analítico Parcial', '2DB06D73AC', 2),
(3, 3, '2023-06-10', 'Otro', '6F69639A47', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `correlatividad`
--

CREATE TABLE `correlatividad` (
  `id` int(11) NOT NULL,
  `materia_id` int(11) NOT NULL,
  `materia_correlativa_id` int(11) NOT NULL,
  `tipo` enum('Para cursar regularizada','Para cursar acreditada','Para acreditar') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `correlatividad`
--

INSERT INTO `correlatividad` (`id`, `materia_id`, `materia_correlativa_id`, `tipo`) VALUES
(1, 5, 1, 'Para cursar regularizada'),
(2, 5, 2, 'Para cursar regularizada'),
(3, 6, 3, 'Para cursar acreditada'),
(4, 6, 4, 'Para acreditar');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `curso`
--

CREATE TABLE `curso` (
  `id` int(11) NOT NULL,
  `codigo` varchar(10) NOT NULL,
  `division` varchar(5) NOT NULL,
  `anio` varchar(5) NOT NULL,
  `turno` enum('Mañana','Tarde') NOT NULL,
  `ciclo_lectivo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `curso`
--

INSERT INTO `curso` (`id`, `codigo`, `division`, `anio`, `turno`, `ciclo_lectivo`) VALUES
(1, '1PEF', 'A', '1°', 'Mañana', 2023),
(2, '1PEF', 'B', '1°', 'Tarde', 2023),
(3, '2PEF', 'A', '2°', 'Mañana', 2023),
(4, '2PEF', 'B', '2°', 'Tarde', 2023);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evaluacion`
--

CREATE TABLE `evaluacion` (
  `id` int(11) NOT NULL,
  `inscripcion_cursado_id` int(11) NOT NULL,
  `tipo` enum('Parcial','Final','Coloquio') NOT NULL,
  `instancia` enum('1°Cuatrimestre','2°Cuatrimestre','Anual') NOT NULL,
  `fecha` date NOT NULL,
  `nota` int(11) DEFAULT NULL,
  `nota_letra` varchar(50) DEFAULT NULL,
  `profesor_id` int(11) NOT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `evaluacion`
--

INSERT INTO `evaluacion` (`id`, `inscripcion_cursado_id`, `tipo`, `instancia`, `fecha`, `nota`, `nota_letra`, `profesor_id`, `observaciones`) VALUES
(1, 3, 'Parcial', '1°Cuatrimestre', '2023-06-15', 9, 'Distinguido', 3, 'Excelente desempeño'),
(2, 7, 'Parcial', '1°Cuatrimestre', '2023-06-15', 4, 'Aprobado', 3, 'Desempeño satisfactorio'),
(3, 11, 'Parcial', '1°Cuatrimestre', '2023-06-15', 9, 'Sobresaliente', 3, 'Excelente desempeño'),
(4, 15, 'Parcial', '1°Cuatrimestre', '2023-06-15', 7, 'Bueno', 3, 'Desempeño satisfactorio'),
(5, 3, 'Final', '1°Cuatrimestre', '2023-07-20', 8, 'Muy Bueno', 3, 'Aprobado'),
(6, 1, 'Parcial', '1°Cuatrimestre', '2025-05-24', 6, '', 1, ''),
(7, 1, 'Parcial', '1°Cuatrimestre', '2025-05-24', 10, '', 1, 'Práctico 2. '),
(8, 1, 'Parcial', '1°Cuatrimestre', '2025-05-24', 8, '', 1, 'Práctico 3. '),
(9, 1, 'Parcial', '2°Cuatrimestre', '2025-05-24', 5, '', 1, ''),
(10, 1, 'Coloquio', 'Anual', '2025-05-24', 10, '', 1, 'Trabajo de Campo. '),
(11, 1, 'Parcial', '1°Cuatrimestre', '2025-05-24', 10, '', 1, 'Práctico 1. ');

--
-- Disparadores `evaluacion`
--
DELIMITER $$
CREATE TRIGGER `audit_evaluacion_insert` AFTER INSERT ON `evaluacion` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `audit_evaluacion_update` AFTER UPDATE ON `evaluacion` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripcion_cursado`
--

CREATE TABLE `inscripcion_cursado` (
  `id` int(11) NOT NULL,
  `alumno_id` int(11) NOT NULL,
  `materia_id` int(11) NOT NULL,
  `curso_id` int(11) NOT NULL,
  `ciclo_lectivo` int(11) NOT NULL,
  `fecha_inscripcion` date NOT NULL,
  `estado` enum('Regular','Libre','Promocional') DEFAULT 'Regular'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `inscripcion_cursado`
--

INSERT INTO `inscripcion_cursado` (`id`, `alumno_id`, `materia_id`, `curso_id`, `ciclo_lectivo`, `fecha_inscripcion`, `estado`) VALUES
(1, 1, 1, 1, 2023, '2023-03-10', 'Regular'),
(2, 1, 2, 1, 2023, '2023-03-10', 'Regular'),
(3, 1, 3, 1, 2023, '2023-03-10', 'Regular'),
(4, 1, 4, 1, 2023, '2023-03-10', 'Regular'),
(5, 2, 1, 1, 2023, '2023-03-11', 'Regular'),
(6, 2, 2, 1, 2023, '2023-03-11', 'Regular'),
(7, 2, 3, 1, 2023, '2023-03-11', 'Regular'),
(8, 2, 4, 1, 2023, '2023-03-11', 'Regular'),
(9, 3, 1, 2, 2023, '2023-03-12', 'Regular'),
(10, 3, 2, 2, 2023, '2023-03-12', 'Regular'),
(11, 3, 3, 2, 2023, '2023-03-12', 'Regular'),
(12, 3, 4, 2, 2023, '2023-03-12', 'Regular'),
(13, 4, 1, 2, 2023, '2023-03-13', 'Regular'),
(14, 4, 2, 2, 2023, '2023-03-13', 'Regular'),
(15, 4, 3, 2, 2023, '2023-03-13', 'Regular'),
(16, 4, 4, 2, 2023, '2023-03-13', 'Regular');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripcion_examen`
--

CREATE TABLE `inscripcion_examen` (
  `id` int(11) NOT NULL,
  `alumno_id` int(11) NOT NULL,
  `acta_examen_id` int(11) NOT NULL,
  `fecha_inscripcion` datetime NOT NULL DEFAULT current_timestamp(),
  `estado` enum('Inscripto','Presente','Ausente') DEFAULT 'Inscripto'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `inscripcion_examen`
--

INSERT INTO `inscripcion_examen` (`id`, `alumno_id`, `acta_examen_id`, `fecha_inscripcion`, `estado`) VALUES
(1, 1, 1, '2025-05-09 10:57:02', 'Presente'),
(2, 1, 2, '2025-05-09 10:57:02', 'Presente'),
(3, 2, 1, '2025-05-09 10:57:02', 'Presente'),
(4, 3, 3, '2025-05-09 10:57:02', 'Presente'),
(5, 4, 3, '2025-05-09 10:57:02', 'Inscripto');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `licencia_profesor`
--

CREATE TABLE `licencia_profesor` (
  `id` int(11) NOT NULL,
  `profesor_id` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `tipo` enum('Enfermedad','Particular','Otro') NOT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `licencia_profesor`
--

INSERT INTO `licencia_profesor` (`id`, `profesor_id`, `fecha_inicio`, `fecha_fin`, `tipo`, `observaciones`) VALUES
(1, 1, '2023-05-01', '2023-05-10', 'Enfermedad', 'Licencia médica por cirugía');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materia`
--

CREATE TABLE `materia` (
  `id` int(11) NOT NULL,
  `nro_orden` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `tipo` enum('Cuatrimestral','Anual') NOT NULL,
  `anio` int(11) NOT NULL,
  `cuatrimestre` enum('1°','2°','Anual') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `materia`
--

INSERT INTO `materia` (`id`, `nro_orden`, `codigo`, `nombre`, `tipo`, `anio`, `cuatrimestre`) VALUES
(1, 1, 'MAT101', 'Anatomía Funcional', 'Anual', 1, 'Anual'),
(2, 2, 'MAT102', 'Fisiología del Ejercicio', 'Anual', 1, 'Anual'),
(3, 3, 'MAT103', 'Deportes Individuales I', 'Cuatrimestral', 1, '1°'),
(4, 4, 'MAT104', 'Deportes de Conjunto I', 'Cuatrimestral', 1, '2°'),
(5, 5, 'MAT201', 'Entrenamiento Deportivo', 'Anual', 2, 'Anual'),
(6, 6, 'MAT202', 'Didáctica de la Educación Física', 'Anual', 2, 'Anual');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `periodos_inscripcion`
--

CREATE TABLE `periodos_inscripcion` (
  `id` int(11) NOT NULL,
  `ciclo_lectivo` int(11) NOT NULL,
  `cuatrimestre` enum('1°','2°','Anual') NOT NULL,
  `fecha_apertura` date NOT NULL,
  `fecha_cierre` date NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `persona`
--

CREATE TABLE `persona` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `dni` varchar(15) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `celular` varchar(20) DEFAULT NULL,
  `domicilio` varchar(255) DEFAULT NULL,
  `contacto_emergencia` varchar(255) DEFAULT NULL,
  `foto_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `persona`
--

INSERT INTO `persona` (`id`, `usuario_id`, `apellidos`, `nombres`, `dni`, `fecha_nacimiento`, `celular`, `domicilio`, `contacto_emergencia`, `foto_url`) VALUES
(1, 1, 'López', 'María', '20345678', '1980-05-15', '3511234567', 'Dirección 123', 'Contacto de emergencia', NULL),
(2, 2, 'González', 'Roberto', '21456789', '1982-08-20', '3512345678', 'Dirección 456', 'Contacto de emergencia', NULL),
(3, 3, 'Martínez', 'Carlos', '22567890', '1975-03-10', '3513456789', 'Dirección 789', 'Contacto de emergencia', NULL),
(4, 4, 'Sánchez', 'Laura', '23678901', '1978-07-25', '3514567890', 'Dirección 101', 'Contacto de emergencia', NULL),
(5, 5, 'Rodríguez', 'Juan', '24789012', '1983-11-12', '3515678901', 'Dirección 112', 'Contacto de emergencia', NULL),
(6, 6, 'Fernández', 'Ana', '30123456', '2000-01-30', '3516789012', 'Dirección 213', 'Contacto de emergencia', NULL),
(7, 7, 'Torres', 'Miguel', '31234567', '2001-04-05', '3517890123', 'Dirección 314', 'Contacto de emergencia', NULL),
(8, 8, 'Díaz', 'Lucía', '32345678', '2002-06-18', '3518901234', 'Dirección 415', 'Contacto de emergencia', NULL),
(9, 9, 'Pérez', 'Daniel', '33456789', '2003-09-22', '3519012345', 'Dirección 516', 'Contacto de emergencia', NULL),
(10, 11, 'Rodriguez ', 'Horacio Andres', '42154777', '2000-11-26', '2644748596', 'Tucuman Sur ', 'Marcos Acuña - 264452512', NULL),
(11, 12, 'Juan', 'Lopez', '20412541', '1966-05-22', '2641254125', 'Alvear Sur', '2644748512', NULL),
(12, 13, 'Alvaro', 'Lopez', '38874658', '1990-11-16', '1111111', '1111111', '', NULL),
(14, 15, 'Estebanez', 'Dario', '38874659', '1994-11-11', '2641254125', 'Alvear Sur', '264452512', NULL),
(15, 16, 'Romero Ruiz', 'Lucas Ariel', '42154776', '1989-05-23', '2641254125', 'Alvear Sur', '2644748512', NULL),
(17, 18, 'Rodriguez ', 'Dario', '38874660', '2001-12-12', '2641254125', 'Alvear Sur', 'Marcos Acuña - 264452512', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `preceptor`
--

CREATE TABLE `preceptor` (
  `id` int(11) NOT NULL,
  `persona_id` int(11) NOT NULL,
  `titulo_profesional` varchar(255) NOT NULL,
  `fecha_ingreso` date NOT NULL,
  `sector_asignado` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Disparadores `preceptor`
--
DELIMITER $$
CREATE TRIGGER `audit_preceptor_delete` BEFORE DELETE ON `preceptor` FOR EACH ROW BEGIN
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
        'DELETE',
        'preceptor',
        OLD.id,
        CONCAT('{"titulo_profesional":"', IFNULL(OLD.titulo_profesional, ''), '","sector_asignado":"', IFNULL(OLD.sector_asignado, ''), '"}'),
        NULL,
        @ip_origen
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `audit_preceptor_insert` AFTER INSERT ON `preceptor` FOR EACH ROW BEGIN
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
        'preceptor',
        NEW.id,
        NULL,
        CONCAT('{"titulo_profesional":"', IFNULL(NEW.titulo_profesional, ''), '","sector_asignado":"', IFNULL(NEW.sector_asignado, ''), '"}'),
        @ip_origen
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `audit_preceptor_update` AFTER UPDATE ON `preceptor` FOR EACH ROW BEGIN
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
        'preceptor',
        NEW.id,
        CONCAT('{"titulo_profesional":"', IFNULL(OLD.titulo_profesional, ''), '","sector_asignado":"', IFNULL(OLD.sector_asignado, ''), '"}'),
        CONCAT('{"titulo_profesional":"', IFNULL(NEW.titulo_profesional, ''), '","sector_asignado":"', IFNULL(NEW.sector_asignado, ''), '"}'),
        @ip_origen
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesor`
--

CREATE TABLE `profesor` (
  `id` int(11) NOT NULL,
  `persona_id` int(11) NOT NULL,
  `titulo_profesional` varchar(255) NOT NULL,
  `fecha_ingreso` date NOT NULL,
  `horas_consulta` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `profesor`
--

INSERT INTO `profesor` (`id`, `persona_id`, `titulo_profesional`, `fecha_ingreso`, `horas_consulta`) VALUES
(1, 3, 'Profesor de Educación Física', '2010-03-01', 'Lunes 14:00-16:00'),
(2, 4, 'Licenciado en Educación Física', '2012-08-15', 'Martes 15:00-17:00'),
(3, 5, 'Doctor en Ciencias del Deporte', '2015-02-10', 'Miércoles 16:00-18:00'),
(4, 10, 'Tec. Sup. Desarrollo de Software', '2025-05-11', '3hs Lunes a Viernes 17hs a 19hs'),
(5, 11, 'Profesorado en Literatura', '2055-05-22', 'Martes 13 a 16hs'),
(6, 15, '', '0000-00-00', 'Martes 8 - 12hs');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesor_materia`
--

CREATE TABLE `profesor_materia` (
  `id` int(11) NOT NULL,
  `profesor_id` int(11) NOT NULL,
  `materia_id` int(11) NOT NULL,
  `curso_id` int(11) NOT NULL,
  `ciclo_lectivo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `profesor_materia`
--

INSERT INTO `profesor_materia` (`id`, `profesor_id`, `materia_id`, `curso_id`, `ciclo_lectivo`) VALUES
(1, 1, 1, 1, 2023),
(8, 1, 4, 2, 2023),
(4, 2, 2, 2, 2023),
(9, 2, 5, 3, 2023),
(5, 3, 3, 1, 2023),
(6, 3, 3, 2, 2023),
(11, 3, 6, 3, 2023),
(12, 3, 6, 4, 2023);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `tipo` enum('administrador','preceptor','profesor','alumno') NOT NULL,
  `ultimo_acceso` datetime DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `primer_acceso` tinyint(1) DEFAULT 1,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `debe_cambiar_password` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id`, `username`, `password`, `tipo`, `ultimo_acceso`, `activo`, `primer_acceso`, `fecha_creacion`, `debe_cambiar_password`) VALUES
(1, 'admin1', '$2y$10$2sAVzqlFTRicsYIz/GRHS.fNkV936nDflh8o2a2eBlK2ciip6bz6.', 'administrador', '2025-05-26 11:57:10', 1, 0, '2025-05-09 10:57:02', 0),
(2, 'preceptor1', '$2y$10$Tw2MO3cYlcpTSi3ECVSvWOim0HU.CiGyZWH5q1uUWFJug0P9s4yzW', 'preceptor', '2025-05-26 07:42:14', 1, 1, '2025-05-09 10:57:02', 0),
(3, 'prof1', '$2y$10$W8FsIhGw50N92w7ZRjJl0O.i7RTGR2c8RHvVKUyZmISXxfUe3oqkC', 'profesor', '2025-05-26 11:10:19', 1, 1, '2025-05-09 10:57:02', 0),
(4, 'prof2', '$2y$10$DTJHj83S5SoYtCtlYcmybeID8bDgh4mOlUKECdC/TDUfmzL5TKjn.', 'profesor', NULL, 1, 1, '2025-05-09 10:57:02', 1),
(5, 'prof3', '$2y$10$ZiXBSX1IHrJauSMUOc8g2e/LRMGCRO//tG77XAlEefWMmjb8E3E5O', 'profesor', NULL, 1, 1, '2025-05-09 10:57:02', 1),
(6, 'alum1', '$2y$10$DuuMqTy0sT369LQ6sd9e3ufOLwC12R.4SpHw2Xkwc6CFJwADTiQKa', 'alumno', '2025-05-22 23:32:28', 1, 1, '2025-05-09 10:57:02', 0),
(7, 'alum2', '$2y$10$uKGDnS8s2kb1iH8pjsUHjeytHYtdTspKEeQam24fBBxkXfngwq03G', 'alumno', NULL, 1, 1, '2025-05-09 10:57:02', 1),
(8, 'alum3', '$2y$10$2fKYu1IIvwX/vdiFSgbB.e9rAfKshNNuY0dopEj9u05Fk0uJrtmOe', 'alumno', NULL, 1, 1, '2025-05-09 10:57:02', 1),
(9, 'alum4', '$2y$10$TJnMP0KNK6UmeocPIs9Vr.Nb37wT1QuoPf0iMJnKlwnuWrGk9bcQq', 'alumno', NULL, 1, 1, '2025-05-09 10:57:02', 1),
(11, 'hrodriguez', '$2y$10$GBWiOb185hHBXmYPrZn7geS3jq/gc9zbtI/FgAOFzmMYhCj3QCmUO', 'profesor', '2025-05-24 12:46:33', 1, 1, '2025-05-12 00:15:53', 0),
(12, 'ljuan', '$2y$10$p9KYNsIZMDdVR9xASpWRIepaDYR8i6HnxhhHZc80SCyx6KPfvV.Hq', 'profesor', NULL, 1, 1, '2025-05-22 11:26:43', 1),
(13, 'lalvaro', '$2y$10$.OqBPWdYsHDEASpgl5LyM.SKTTvfIcPWDPrHt0ieJd438IhDiYa52', 'alumno', '2025-05-22 23:27:39', 1, 1, '2025-05-22 21:36:20', 0),
(15, 'destebanez', '$2y$10$nI0hYAe9IkV/TPrcGL3Svu9kwRKzII3PXK7Wh1DBB7l.YfMOlGV56', 'alumno', '2025-05-23 00:57:29', 1, 1, '2025-05-22 23:34:49', 0),
(16, 'lromeroruiz', '$2y$10$Cvoufvp8STq4yt9xNRQ7/uTrS6OYzqmlTdhYFXZYksxRhbzIBO.NG', 'profesor', '2025-05-23 08:35:13', 1, 1, '2025-05-23 08:34:59', 0),
(18, 'drodriguez', '$2y$10$A7aD4OghzhJZs2VhEc710OeIfMnIzb6LLtkDowGd67aX.5pciBiOq', 'alumno', '2025-05-23 08:44:21', 1, 1, '2025-05-23 08:43:40', 1);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_alumnos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_alumnos` (
`alumno_id` int(11)
,`apellidos` varchar(100)
,`nombres` varchar(100)
,`dni` varchar(15)
,`legajo` varchar(20)
,`fecha_ingreso` date
,`cohorte` int(11)
,`fecha_nacimiento` date
,`celular` varchar(20)
,`domicilio` varchar(255)
,`contacto_emergencia` varchar(255)
,`username` varchar(50)
,`activo` tinyint(1)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_asistencia`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_asistencia` (
`legajo` varchar(20)
,`alumno_nombre` varchar(202)
,`materia_codigo` varchar(20)
,`materia_nombre` varchar(255)
,`curso_codigo` varchar(10)
,`ciclo_lectivo` int(11)
,`fecha` date
,`estado` enum('Presente','Ausente','Justificado')
,`profesor_nombre` varchar(202)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_correlatividades`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_correlatividades` (
`materia_id` int(11)
,`nro_orden` int(11)
,`codigo` varchar(20)
,`materia_nombre` varchar(255)
,`tipo` enum('Cuatrimestral','Anual')
,`anio` int(11)
,`cuatrimestre` enum('1°','2°','Anual')
,`correlativa_codigo` varchar(20)
,`correlativa_nombre` varchar(255)
,`tipo_correlatividad` enum('Para cursar regularizada','Para cursar acreditada','Para acreditar')
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_personas`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_personas` (
`id` int(11)
,`dni` varchar(15)
,`apellidos` varchar(100)
,`nombres` varchar(100)
,`fecha_nacimiento` date
,`celular` varchar(20)
,`domicilio` varchar(255)
,`contacto_emergencia` varchar(255)
,`username` varchar(50)
,`tipo` enum('administrador','preceptor','profesor','alumno')
,`activo` tinyint(1)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_preceptores`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_preceptores` (
`preceptor_id` int(11)
,`apellidos` varchar(100)
,`nombres` varchar(100)
,`dni` varchar(15)
,`titulo_profesional` varchar(255)
,`fecha_ingreso` date
,`sector_asignado` varchar(100)
,`celular` varchar(20)
,`domicilio` varchar(255)
,`contacto_emergencia` varchar(255)
,`username` varchar(50)
,`activo` tinyint(1)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_profesores`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_profesores` (
`profesor_id` int(11)
,`apellidos` varchar(100)
,`nombres` varchar(100)
,`dni` varchar(15)
,`titulo_profesional` varchar(255)
,`fecha_ingreso` date
,`horas_consulta` varchar(255)
,`celular` varchar(20)
,`domicilio` varchar(255)
,`contacto_emergencia` varchar(255)
,`username` varchar(50)
,`activo` tinyint(1)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_situacion_academica`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_situacion_academica` (
`alumno_id` int(11)
,`legajo` varchar(20)
,`alumno_nombre` varchar(202)
,`nro_orden` int(11)
,`materia_codigo` varchar(20)
,`materia_nombre` varchar(255)
,`estado` enum('Regular','Libre','Promocional')
,`curso_codigo` varchar(10)
,`ciclo_lectivo` int(11)
,`nota_final` int(11)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_alumnos`
--
DROP TABLE IF EXISTS `vista_alumnos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_alumnos`  AS SELECT `a`.`id` AS `alumno_id`, `pers`.`apellidos` AS `apellidos`, `pers`.`nombres` AS `nombres`, `pers`.`dni` AS `dni`, `a`.`legajo` AS `legajo`, `a`.`fecha_ingreso` AS `fecha_ingreso`, `a`.`cohorte` AS `cohorte`, `pers`.`fecha_nacimiento` AS `fecha_nacimiento`, `pers`.`celular` AS `celular`, `pers`.`domicilio` AS `domicilio`, `pers`.`contacto_emergencia` AS `contacto_emergencia`, `u`.`username` AS `username`, `u`.`activo` AS `activo` FROM ((`alumno` `a` join `persona` `pers` on(`a`.`persona_id` = `pers`.`id`)) join `usuario` `u` on(`pers`.`usuario_id` = `u`.`id`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_asistencia`
--
DROP TABLE IF EXISTS `vista_asistencia`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_asistencia`  AS SELECT `a`.`legajo` AS `legajo`, concat(`p`.`apellidos`,', ',`p`.`nombres`) AS `alumno_nombre`, `m`.`codigo` AS `materia_codigo`, `m`.`nombre` AS `materia_nombre`, `c`.`codigo` AS `curso_codigo`, `c`.`ciclo_lectivo` AS `ciclo_lectivo`, `asist`.`fecha` AS `fecha`, `asist`.`estado` AS `estado`, concat(`pp`.`apellidos`,', ',`pp`.`nombres`) AS `profesor_nombre` FROM (((((((`asistencia` `asist` join `inscripcion_cursado` `ic` on(`asist`.`inscripcion_cursado_id` = `ic`.`id`)) join `alumno` `a` on(`ic`.`alumno_id` = `a`.`id`)) join `persona` `p` on(`a`.`persona_id` = `p`.`id`)) join `materia` `m` on(`ic`.`materia_id` = `m`.`id`)) join `curso` `c` on(`ic`.`curso_id` = `c`.`id`)) join `profesor` `prof` on(`asist`.`profesor_id` = `prof`.`id`)) join `persona` `pp` on(`prof`.`persona_id` = `pp`.`id`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_correlatividades`
--
DROP TABLE IF EXISTS `vista_correlatividades`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_correlatividades`  AS SELECT `m`.`id` AS `materia_id`, `m`.`nro_orden` AS `nro_orden`, `m`.`codigo` AS `codigo`, `m`.`nombre` AS `materia_nombre`, `m`.`tipo` AS `tipo`, `m`.`anio` AS `anio`, `m`.`cuatrimestre` AS `cuatrimestre`, `mc`.`codigo` AS `correlativa_codigo`, `mc`.`nombre` AS `correlativa_nombre`, `c`.`tipo` AS `tipo_correlatividad` FROM ((`materia` `m` left join `correlatividad` `c` on(`m`.`id` = `c`.`materia_id`)) left join `materia` `mc` on(`c`.`materia_correlativa_id` = `mc`.`id`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_personas`
--
DROP TABLE IF EXISTS `vista_personas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_personas`  AS SELECT `p`.`id` AS `id`, `p`.`dni` AS `dni`, `p`.`apellidos` AS `apellidos`, `p`.`nombres` AS `nombres`, `p`.`fecha_nacimiento` AS `fecha_nacimiento`, `p`.`celular` AS `celular`, `p`.`domicilio` AS `domicilio`, `p`.`contacto_emergencia` AS `contacto_emergencia`, `u`.`username` AS `username`, `u`.`tipo` AS `tipo`, `u`.`activo` AS `activo` FROM (`persona` `p` join `usuario` `u` on(`p`.`usuario_id` = `u`.`id`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_preceptores`
--
DROP TABLE IF EXISTS `vista_preceptores`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_preceptores`  AS SELECT `p`.`id` AS `preceptor_id`, `pers`.`apellidos` AS `apellidos`, `pers`.`nombres` AS `nombres`, `pers`.`dni` AS `dni`, `p`.`titulo_profesional` AS `titulo_profesional`, `p`.`fecha_ingreso` AS `fecha_ingreso`, `p`.`sector_asignado` AS `sector_asignado`, `pers`.`celular` AS `celular`, `pers`.`domicilio` AS `domicilio`, `pers`.`contacto_emergencia` AS `contacto_emergencia`, `u`.`username` AS `username`, `u`.`activo` AS `activo` FROM ((`preceptor` `p` join `persona` `pers` on(`p`.`persona_id` = `pers`.`id`)) join `usuario` `u` on(`pers`.`usuario_id` = `u`.`id`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_profesores`
--
DROP TABLE IF EXISTS `vista_profesores`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_profesores`  AS SELECT `p`.`id` AS `profesor_id`, `pers`.`apellidos` AS `apellidos`, `pers`.`nombres` AS `nombres`, `pers`.`dni` AS `dni`, `p`.`titulo_profesional` AS `titulo_profesional`, `p`.`fecha_ingreso` AS `fecha_ingreso`, `p`.`horas_consulta` AS `horas_consulta`, `pers`.`celular` AS `celular`, `pers`.`domicilio` AS `domicilio`, `pers`.`contacto_emergencia` AS `contacto_emergencia`, `u`.`username` AS `username`, `u`.`activo` AS `activo` FROM ((`profesor` `p` join `persona` `pers` on(`p`.`persona_id` = `pers`.`id`)) join `usuario` `u` on(`pers`.`usuario_id` = `u`.`id`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_situacion_academica`
--
DROP TABLE IF EXISTS `vista_situacion_academica`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_situacion_academica`  AS SELECT `a`.`id` AS `alumno_id`, `a`.`legajo` AS `legajo`, concat(`p`.`apellidos`,', ',`p`.`nombres`) AS `alumno_nombre`, `m`.`nro_orden` AS `nro_orden`, `m`.`codigo` AS `materia_codigo`, `m`.`nombre` AS `materia_nombre`, `ic`.`estado` AS `estado`, `c`.`codigo` AS `curso_codigo`, `c`.`ciclo_lectivo` AS `ciclo_lectivo`, (select max(`e`.`nota`) from `evaluacion` `e` where `e`.`inscripcion_cursado_id` = `ic`.`id` and `e`.`tipo` = 'Final') AS `nota_final` FROM ((((`alumno` `a` join `persona` `p` on(`a`.`persona_id` = `p`.`id`)) join `inscripcion_cursado` `ic` on(`a`.`id` = `ic`.`alumno_id`)) join `materia` `m` on(`ic`.`materia_id` = `m`.`id`)) join `curso` `c` on(`ic`.`curso_id` = `c`.`id`)) ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `acta_examen`
--
ALTER TABLE `acta_examen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `materia_id` (`materia_id`),
  ADD KEY `curso_id` (`curso_id`),
  ADD KEY `profesor_id` (`profesor_id`),
  ADD KEY `idx_fecha` (`fecha`),
  ADD KEY `idx_libro_folio` (`libro`,`folio`),
  ADD KEY `idx_cerrada` (`cerrada`);

--
-- Indices de la tabla `alumno`
--
ALTER TABLE `alumno`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `persona_id` (`persona_id`),
  ADD UNIQUE KEY `legajo` (`legajo`),
  ADD KEY `idx_legajo` (`legajo`),
  ADD KEY `idx_cohorte` (`cohorte`);

--
-- Indices de la tabla `asistencia`
--
ALTER TABLE `asistencia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_asistencia` (`inscripcion_cursado_id`,`fecha`),
  ADD KEY `profesor_id` (`profesor_id`),
  ADD KEY `idx_fecha` (`fecha`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indices de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_fecha_hora` (`fecha_hora`),
  ADD KEY `idx_tipo_operacion` (`tipo_operacion`),
  ADD KEY `idx_tabla_afectada` (`tabla_afectada`);

--
-- Indices de la tabla `certificacion`
--
ALTER TABLE `certificacion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_verificacion` (`codigo_verificacion`),
  ADD KEY `alumno_id` (`alumno_id`),
  ADD KEY `autorizado_por` (`autorizado_por`),
  ADD KEY `idx_fecha_emision` (`fecha_emision`),
  ADD KEY `idx_tipo` (`tipo`);

--
-- Indices de la tabla `correlatividad`
--
ALTER TABLE `correlatividad`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_correlatividad` (`materia_id`,`materia_correlativa_id`,`tipo`),
  ADD KEY `materia_correlativa_id` (`materia_correlativa_id`),
  ADD KEY `idx_tipo_correlatividad` (`tipo`);

--
-- Indices de la tabla `curso`
--
ALTER TABLE `curso`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_curso_completo` (`codigo`,`division`,`ciclo_lectivo`),
  ADD KEY `idx_turno` (`turno`),
  ADD KEY `idx_ciclo_lectivo` (`ciclo_lectivo`);

--
-- Indices de la tabla `evaluacion`
--
ALTER TABLE `evaluacion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inscripcion_cursado_id` (`inscripcion_cursado_id`),
  ADD KEY `profesor_id` (`profesor_id`),
  ADD KEY `idx_tipo_instancia` (`tipo`,`instancia`),
  ADD KEY `idx_fecha` (`fecha`);

--
-- Indices de la tabla `inscripcion_cursado`
--
ALTER TABLE `inscripcion_cursado`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_inscripcion_cursado` (`alumno_id`,`materia_id`,`curso_id`,`ciclo_lectivo`),
  ADD KEY `materia_id` (`materia_id`),
  ADD KEY `curso_id` (`curso_id`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_ciclo_lectivo` (`ciclo_lectivo`);

--
-- Indices de la tabla `inscripcion_examen`
--
ALTER TABLE `inscripcion_examen`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_inscripcion_examen` (`alumno_id`,`acta_examen_id`),
  ADD KEY `acta_examen_id` (`acta_examen_id`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_fecha_inscripcion` (`fecha_inscripcion`);

--
-- Indices de la tabla `licencia_profesor`
--
ALTER TABLE `licencia_profesor`
  ADD PRIMARY KEY (`id`),
  ADD KEY `profesor_id` (`profesor_id`),
  ADD KEY `idx_fecha_inicio_fin` (`fecha_inicio`,`fecha_fin`),
  ADD KEY `idx_tipo` (`tipo`);

--
-- Indices de la tabla `materia`
--
ALTER TABLE `materia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_materia_codigo` (`codigo`),
  ADD KEY `idx_nro_orden` (`nro_orden`),
  ADD KEY `idx_anio_cuatrimestre` (`anio`,`cuatrimestre`);

--
-- Indices de la tabla `periodos_inscripcion`
--
ALTER TABLE `periodos_inscripcion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ciclo_cuatri_activo` (`ciclo_lectivo`,`cuatrimestre`,`activo`);

--
-- Indices de la tabla `persona`
--
ALTER TABLE `persona`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_dni` (`dni`),
  ADD KEY `idx_apellidos_nombres` (`apellidos`,`nombres`);

--
-- Indices de la tabla `preceptor`
--
ALTER TABLE `preceptor`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `persona_id` (`persona_id`),
  ADD KEY `idx_sector_asignado` (`sector_asignado`);

--
-- Indices de la tabla `profesor`
--
ALTER TABLE `profesor`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `persona_id` (`persona_id`);

--
-- Indices de la tabla `profesor_materia`
--
ALTER TABLE `profesor_materia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_profesor_materia_curso` (`profesor_id`,`materia_id`,`curso_id`,`ciclo_lectivo`),
  ADD KEY `materia_id` (`materia_id`),
  ADD KEY `curso_id` (`curso_id`),
  ADD KEY `idx_ciclo_lectivo` (`ciclo_lectivo`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `username_2` (`username`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `acta_examen`
--
ALTER TABLE `acta_examen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `alumno`
--
ALTER TABLE `alumno`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `asistencia`
--
ALTER TABLE `asistencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `certificacion`
--
ALTER TABLE `certificacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `correlatividad`
--
ALTER TABLE `correlatividad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `curso`
--
ALTER TABLE `curso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `evaluacion`
--
ALTER TABLE `evaluacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `inscripcion_cursado`
--
ALTER TABLE `inscripcion_cursado`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `inscripcion_examen`
--
ALTER TABLE `inscripcion_examen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `licencia_profesor`
--
ALTER TABLE `licencia_profesor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `materia`
--
ALTER TABLE `materia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `periodos_inscripcion`
--
ALTER TABLE `periodos_inscripcion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `persona`
--
ALTER TABLE `persona`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `preceptor`
--
ALTER TABLE `preceptor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `profesor`
--
ALTER TABLE `profesor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `profesor_materia`
--
ALTER TABLE `profesor_materia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `acta_examen`
--
ALTER TABLE `acta_examen`
  ADD CONSTRAINT `acta_examen_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materia` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `acta_examen_ibfk_2` FOREIGN KEY (`curso_id`) REFERENCES `curso` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `acta_examen_ibfk_3` FOREIGN KEY (`profesor_id`) REFERENCES `profesor` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `alumno`
--
ALTER TABLE `alumno`
  ADD CONSTRAINT `alumno_ibfk_1` FOREIGN KEY (`persona_id`) REFERENCES `persona` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `asistencia`
--
ALTER TABLE `asistencia`
  ADD CONSTRAINT `asistencia_ibfk_1` FOREIGN KEY (`inscripcion_cursado_id`) REFERENCES `inscripcion_cursado` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `asistencia_ibfk_2` FOREIGN KEY (`profesor_id`) REFERENCES `profesor` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD CONSTRAINT `auditoria_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `certificacion`
--
ALTER TABLE `certificacion`
  ADD CONSTRAINT `certificacion_ibfk_1` FOREIGN KEY (`alumno_id`) REFERENCES `alumno` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `certificacion_ibfk_2` FOREIGN KEY (`autorizado_por`) REFERENCES `usuario` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `correlatividad`
--
ALTER TABLE `correlatividad`
  ADD CONSTRAINT `correlatividad_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materia` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `correlatividad_ibfk_2` FOREIGN KEY (`materia_correlativa_id`) REFERENCES `materia` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `evaluacion`
--
ALTER TABLE `evaluacion`
  ADD CONSTRAINT `evaluacion_ibfk_1` FOREIGN KEY (`inscripcion_cursado_id`) REFERENCES `inscripcion_cursado` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluacion_ibfk_2` FOREIGN KEY (`profesor_id`) REFERENCES `profesor` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `inscripcion_cursado`
--
ALTER TABLE `inscripcion_cursado`
  ADD CONSTRAINT `inscripcion_cursado_ibfk_1` FOREIGN KEY (`alumno_id`) REFERENCES `alumno` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inscripcion_cursado_ibfk_2` FOREIGN KEY (`materia_id`) REFERENCES `materia` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inscripcion_cursado_ibfk_3` FOREIGN KEY (`curso_id`) REFERENCES `curso` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `inscripcion_examen`
--
ALTER TABLE `inscripcion_examen`
  ADD CONSTRAINT `inscripcion_examen_ibfk_1` FOREIGN KEY (`alumno_id`) REFERENCES `alumno` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inscripcion_examen_ibfk_2` FOREIGN KEY (`acta_examen_id`) REFERENCES `acta_examen` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `licencia_profesor`
--
ALTER TABLE `licencia_profesor`
  ADD CONSTRAINT `licencia_profesor_ibfk_1` FOREIGN KEY (`profesor_id`) REFERENCES `profesor` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `persona`
--
ALTER TABLE `persona`
  ADD CONSTRAINT `persona_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `preceptor`
--
ALTER TABLE `preceptor`
  ADD CONSTRAINT `preceptor_ibfk_1` FOREIGN KEY (`persona_id`) REFERENCES `persona` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `profesor`
--
ALTER TABLE `profesor`
  ADD CONSTRAINT `profesor_ibfk_1` FOREIGN KEY (`persona_id`) REFERENCES `persona` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `profesor_materia`
--
ALTER TABLE `profesor_materia`
  ADD CONSTRAINT `profesor_materia_ibfk_1` FOREIGN KEY (`profesor_id`) REFERENCES `profesor` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `profesor_materia_ibfk_2` FOREIGN KEY (`materia_id`) REFERENCES `materia` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `profesor_materia_ibfk_3` FOREIGN KEY (`curso_id`) REFERENCES `curso` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
