<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
// generar_pdf_asistencias.php
require('../tools/fpdf.php'); 

// ----------------------------------------------------
// Conexión a la Base de Datos
// ----------------------------------------------------
$mysqli = new mysqli("localhost", "root", "", "isef_sistema"); //
if ($mysqli->connect_errno) { //
    die("Fallo la conexión a la base de datos: " . $mysqli->connect_error); //
}

// ----------------------------------------------------
// Recuperar y validar datos POST
// ----------------------------------------------------
$materia_id = isset($_POST['materia_id']) ? intval($_POST['materia_id']) : 0; //
$curso_id = isset($_POST['curso_id']) ? intval($_POST['curso_id']) : 0; //
$profesor_id = isset($_POST['profesor_id']) ? intval($_POST['profesor_id']) : 0; //
$periodo = isset($_POST['periodo']) ? $_POST['periodo'] : ''; //

if ($materia_id === 0 || $curso_id === 0 || $profesor_id === 0 || !in_array($periodo, ['abril_julio', 'septiembre_diciembre'])) { //
    die("Error: Parámetros incompletos o inválidos para generar el informe."); //
}

// ----------------------------------------------------



// Definir el rango de fechas y generar días de clase específicos por mes
// ----------------------------------------------------
$anio_actual = date('Y'); //
$fecha_inicio_str = ''; //
$fecha_fin_str = ''; //
$titulo_periodo = ''; //
$meses_periodo = []; //

if ($periodo === 'abril_julio') {
    $fecha_inicio_str = $anio_actual . '-04-01'; //
    $fecha_fin_str = $anio_actual . '-07-31'; //
    $titulo_periodo = 'Abril - Julio ' . $anio_actual; //
    $meses_periodo = [4, 5, 6, 7]; //
} elseif ($periodo === 'septiembre_diciembre') {
    $fecha_inicio_str = $anio_actual . '-09-01'; //
    $fecha_fin_str = $anio_actual . '-12-31'; //
    $titulo_periodo = 'Septiembre - Diciembre ' . $anio_actual; //
    $meses_periodo = [9, 10, 11, 12]; //
}

$fechas_rango = []; //
$dias_por_mes_config = [ //
    4 => 26,  // Abril
    5 => 26,  // Mayo
    6 => 26,  // Junio
    7 => 5,   // Julio
    9 => 26,  // Septiembre
    10 => 26, // Octubre
    11 => 26, // Noviembre
    12 => 5   // Diciembre
];
foreach ($meses_periodo as $mes) { //
    $dias_a_incluir = $dias_por_mes_config[$mes]; //
    for ($dia = 1; $dia <= $dias_a_incluir; $dia++) { //
        $fecha = sprintf('%04d-%02d-%02d', $anio_actual, $mes, $dia); //
        $fechas_rango[] = $fecha; //
    }
}


// ----------------------------------------------------
// Obtener datos de la materia, curso y profesor
// ----------------------------------------------------
$materia_nombre = 'N/A'; //
$curso_anio = 'N/A'; //
$profesor_nombre = 'N/A'; //

$stmt_info = $mysqli->prepare("
    SELECT
        m.nombre AS materia_nombre,
        c.anio AS curso_anio,
        CONCAT(per.nombres, ' ', per.apellidos) AS profesor_nombre
    FROM materia m
    JOIN curso c ON c.id = ?
    JOIN profesor_materia pm ON pm.materia_id = m.id AND pm.profesor_id = ?
    JOIN profesor p ON p.id = pm.profesor_id
    JOIN persona per ON per.id = p.persona_id
    WHERE m.id = ?
"); //
$stmt_info->bind_param("iii", $curso_id, $profesor_id, $materia_id); //
$stmt_info->execute(); //
$result_info = $stmt_info->get_result(); //
if ($info = $result_info->fetch_assoc()) { //
    $materia_nombre = $info['materia_nombre']; //
    $curso_anio = $info['curso_anio']; //
    $profesor_nombre = $info['profesor_nombre']; //
}
$stmt_info->close(); //

// ----------------------------------------------------
// Obtener alumnos y sus asistencias detalladas
// ----------------------------------------------------
$alumnos_asistencias_detalles = []; //

$db_fecha_inicio = min($fechas_rango); //
$db_fecha_fin = max($fechas_rango); //

// MODIFICACIÓN AQUÍ: Cambiar el formato de alumno_nombre
$stmt_alumnos_asistencias = $mysqli->prepare("
    SELECT
        al.id AS alumno_id,
        CONCAT(per.apellidos, ', ', per.nombres) AS alumno_nombre, -- MODIFICADO para Apellidos, Nombres
        asis.fecha,
        asis.estado
    FROM alumno al
    JOIN persona per ON al.persona_id = per.id
    JOIN inscripcion_cursado ic ON ic.alumno_id = al.id
    LEFT JOIN asistencia asis ON asis.inscripcion_cursado_id = ic.id AND asis.fecha BETWEEN ? AND ?
    WHERE ic.materia_id = ? AND ic.curso_id = ?
    ORDER BY per.apellidos, per.nombres, asis.fecha ASC
"); //
$stmt_alumnos_asistencias->bind_param("ssii", $db_fecha_inicio, $db_fecha_fin, $materia_id, $curso_id); //
$stmt_alumnos_asistencias->execute(); //
$result_alumnos_asistencias = $stmt_alumnos_asistencias->get_result(); //

while ($row = $result_alumnos_asistencias->fetch_assoc()) { //
    $alumno_id = $row['alumno_id']; //
    $alumno_nombre = $row['alumno_nombre']; //
    if (!isset($alumnos_asistencias_detalles[$alumno_id])) { //
        $alumnos_asistencias_detalles[$alumno_id] = [ //
            'alumno_nombre' => $alumno_nombre, //
            'asistencias' => [], 
            'total_asistencias' => 0 //
        ];
    }
    if ($row['fecha']) { 
        $alumnos_asistencias_detalles[$alumno_id]['asistencias'][$row['fecha']] = $row['estado']; 
        if (in_array($row['fecha'], $fechas_rango) && ($row['estado'] === 'Presente' || $row['estado'] === 'Justificado')) { 
            $alumnos_asistencias_detalles[$alumno_id]['total_asistencias']++; 
        }
    }
}
$stmt_alumnos_asistencias->close(); //
$mysqli->close(); //

$data_for_pdf_table = []; 

foreach ($alumnos_asistencias_detalles as $alumno_data) { 
    $row_data = [ 
        'alumno_nombre' => $alumno_data['alumno_nombre'], 
        'asistencias_por_fecha' => [], 
        'total_asistencias' => $alumno_data['total_asistencias'] 
    ];
    foreach ($fechas_rango as $fecha) { 
        $row_data['asistencias_por_fecha'][$fecha] = $alumno_data['asistencias'][$fecha] ?? '-'; 
    }
    $data_for_pdf_table[] = $row_data; 
}

// ... (El resto de la clase PDF y la generación del PDF permanece igual que en la respuesta anterior) ...
// Asegúrate de que el resto del archivo (la definición de la clase PDF y la lógica de generación)
// sea el que te proporcioné en la respuesta anterior, ya que contenía las correcciones para 'P', 'A', 'J'
// y el cálculo del total.

class PDF extends FPDF
{
    protected $materia_nombre; //
    protected $curso_anio; //
    protected $profesor_nombre; //
    protected $titulo_periodo; //
    protected $fechas_rango_dias; //
    protected $meses_en_rango_con_fechas; //

    function Header()
    {
        $this->SetFont('Arial', 'B', 14); //
        $this->SetY(10); //
        $this->Cell(0, 7, utf8_decode('INSTITUTO SUPERIOR DE EDUCACIÓN FÍSICA'), 0, 1, 'C'); //
        $this->SetFont('Arial', 'B', 12); //
        $this->Cell(0, 6, utf8_decode('SECRETARÍA'), 0, 1, 'C'); //
        $this->Ln(5); //

        $this->SetFont('Arial', 'B', 10); //
        $this->SetX(10); //
        $this->Cell(90, 6, utf8_decode('ESPACIO CURRICULAR: ' . $this->materia_nombre), 0, 0, 'L'); //
        $this->Cell(0, 6, utf8_decode('PROFESOR/A: ' . $this->profesor_nombre), 0, 1, 'L'); // Ajustado el ancho para el profesor

        $this->SetX(10); //
        $this->Cell(90, 6, utf8_decode('CURSO: ' . $this->curso_anio), 0, 0, 'L'); //
        $this->Cell(0, 6, utf8_decode('PERÍODO: ' . $this->titulo_periodo), 0, 1, 'L'); // Ajustado el ancho para el período
        $this->Ln(8); //

        $this->SetFillColor(220, 220, 220); //
        $this->SetFont('Arial', 'B', 8); //
        $w_num = 10; //
        $w_nombre = 70; // Ajustado para Apellidos, Nombres
        $w_total_asist = 15; //
        
        $total_dias = count($this->fechas_rango_dias); //
        
        $ancho_pagina_util = $this->GetPageWidth() - $this->lMargin - $this->rMargin; // Ancho útil de la página
        $ancho_dias_disponible = $ancho_pagina_util - $w_num - $w_nombre - $w_total_asist;

        $w_dia = $total_dias > 0 ? floor($ancho_dias_disponible / $total_dias) : 0; // Usar floor para evitar decimales

        // Asegurar un ancho mínimo para los días para legibilidad
        $min_w_dia = 5; // mm
        if ($w_dia < $min_w_dia) {
            $w_dia = $min_w_dia;
            // Si esto hace que la tabla sea más ancha que la página, se necesitaría ajustar A2 o reducir el número de días por página.
        }
        
        // Primera fila de la cabecera (N°, Apellido y Nombre, Meses, celda vacía para Total)
        $this->Cell($w_num, 7, utf8_decode('N°'), 1, 0, 'C', true); //
        $this->Cell($w_nombre, 7, utf8_decode('Apellido y Nombre'), 1, 0, 'C', true); //

        $meses_en_espanol = [ //
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
            '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciciembre'
        ];
        
        $current_x_for_months = $this->GetX(); // Guardar X antes de los meses

        foreach ($this->meses_en_rango_con_fechas as $mes_num => $fechas_del_mes) { //
            $nombre_mes = $meses_en_espanol[sprintf('%02d', $mes_num)] ?? 'Desconocido'; //
            $ancho_celda_mes = count($fechas_del_mes) * $w_dia; //
            $this->Cell($ancho_celda_mes, 7, utf8_decode($nombre_mes), 1, 0, 'C', true); //
        }
        // La celda para "Total" en la primera fila (debajo de la cual irá "Total" en la segunda)
        $this->Cell($w_total_asist, 7, '', 'LTR', 1, 'C', true); // Borde LTR, salto de línea


        // --- Segunda fila de cabecera: (vacío para N°) | (vacío para Nombre) | DÍAS | Total ---
        $this->SetX($this->lMargin); // Volver al margen izquierdo
        $this->Cell($w_num, 7, '', 'LBR', 0, 'C', false); // Solo bordes inferiores y laterales
        $this->Cell($w_nombre, 7, '', 'LBR', 0, 'C', false); // Solo bordes inferiores y laterales
        
        $this->SetX($current_x_for_months); // Alinea los días con los meses

        foreach ($this->fechas_rango_dias as $fecha_str) { //
            $dia_num = date('d', strtotime($fecha_str)); //
            $this->Cell($w_dia, 7, $dia_num, 1, 0, 'C', true); //
        }
        $this->Cell($w_total_asist, 7, utf8_decode('Total'), 1, 1, 'C', true); //
    }

    function Footer()
    {
        $this->SetY(-15); //
        $this->SetFont('Arial', 'I', 8); //
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C'); //
        $this->Cell(0, 10, utf8_decode('Generado el: ' . date('d/m/Y H:i')), 0, 0, 'R'); //
    }

    function setInfo($materia, $curso, $profesor, $periodo, $fechas_rango)
    {
        $this->materia_nombre = $materia; //
        $this->curso_anio = $curso; //
        $this->profesor_nombre = $profesor; //
        $this->titulo_periodo = $periodo; //
        $this->fechas_rango_dias = $fechas_rango; //
        
        $meses_con_fechas = []; //
        foreach ($fechas_rango as $fecha) { //
            $mes = date('n', strtotime($fecha)); //
            if (!isset($meses_con_fechas[$mes])) { //
                $meses_con_fechas[$mes] = []; //
            }
            $meses_con_fechas[$mes][] = $fecha; //
        }
        ksort($meses_con_fechas);
        $this->meses_en_rango_con_fechas = $meses_con_fechas; //
    }

    function AsistenciaTable($data)
    {
        $w_num = 10; //
        $w_nombre = 70; // Ajustado para Apellidos, Nombres
        $w_total_asist = 15; //

        $total_dias = count($this->fechas_rango_dias); //
        $ancho_pagina_util = $this->GetPageWidth() - $this->lMargin - $this->rMargin;
        $ancho_dias_disponible = $ancho_pagina_util - $w_num - $w_nombre - $w_total_asist; 
        
        $w_dia = $total_dias > 0 ? floor($ancho_dias_disponible / $total_dias) : 0;
        $min_w_dia = 5;
        if ($w_dia < $min_w_dia) { $w_dia = $min_w_dia; }

        $this->SetFont('Arial', '', 7); 
        $i = 1; 
        foreach ($data as $row) { 
            $this->SetX($this->lMargin); 
            
            $altura_celda = 6; // Altura estándar de la celda
            $current_y_start_row = $this->GetY();

            // Celda para N°
            $this->Cell($w_num, $altura_celda, $i++, 1, 0, 'C'); 
            
            // Celda para Apellido y Nombre (con MultiCell para manejo de saltos de línea)
            $x_before_name = $this->GetX();
            $this->MultiCell($w_nombre, $altura_celda, utf8_decode($row['alumno_nombre']), 1, 'L');
            $y_after_name = $this->GetY(); // Y después de que MultiCell haya dibujado
            
            // Calcular la altura real que usó MultiCell
            // Si MultiCell hizo un salto de línea, $y_after_name será mayor que $current_y_start_row + $altura_celda
            // Esto es un poco más complejo de manejar perfectamente para alinear las celdas de asistencia
            // Por simplicidad, asumiremos que la altura de la fila es $altura_celda, 
            // pero si los nombres son muy largos y causan múltiples saltos, esta parte podría necesitar más ajustes
            // para que todas las celdas de la fila tengan la misma altura.

            // Restaurar Y a la posición inicial de la fila y X después de la celda del nombre
            $this->SetXY($x_before_name + $w_nombre, $current_y_start_row);

            foreach ($this->fechas_rango_dias as $fecha_str) { 
                $estado_completo = $row['asistencias_por_fecha'][$fecha_str] ?? '-'; 
                $estado_corto = '-';
                if ($estado_completo === 'Presente') {
                    $estado_corto = 'P';
                } elseif ($estado_completo === 'Ausente') {
                    $estado_corto = 'A';
                } elseif ($estado_completo === 'Justificado') {
                    $estado_corto = 'J';
                }
                $this->Cell($w_dia, $altura_celda, utf8_decode($estado_corto), 1, 0, 'C'); 
            }
            $this->Cell($w_total_asist, $altura_celda, $row['total_asistencias'], 1, 1, 'C'); 

            // Si MultiCell usó más altura, asegurar que la siguiente fila comience después
             if ($y_after_name > $this->GetY()) {
                 $this->SetY($y_after_name);
             }
        }
    }
}

$pdf = new PDF('L', 'mm', array(594,420)); // [cite: 227] 'L' para Landscape, 'mm', 'A2'
$pdf->AliasNbPages(); 
$pdf->setInfo($materia_nombre, $curso_anio, $profesor_nombre, $titulo_periodo, $fechas_rango); 
$pdf->AddPage(); 
$pdf->AsistenciaTable($data_for_pdf_table); 

$pdf->Output('I', utf8_decode('Informe_Asistencias_' . str_replace(' ', '_', $materia_nombre) . '_' . str_replace(['°',' '], ['', '_'], $curso_anio) . '_' . $periodo . '.pdf')); 
?>