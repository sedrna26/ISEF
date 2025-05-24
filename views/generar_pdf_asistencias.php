<?php
// generar_pdf_asistencias.php
require('fpdf.php'); // Asegúrate de que fpdf.php esté en la misma carpeta o en el path correcto

// ----------------------------------------------------
// Conexión a la Base de Datos
// ----------------------------------------------------
$mysqli = new mysqli("localhost", "root", "", "isef_sistema");

if ($mysqli->connect_errno) {
    die("Fallo la conexión a la base de datos: " . $mysqli->connect_error);
}

// ----------------------------------------------------
// Recuperar y validar datos POST
// ----------------------------------------------------
$materia_id = isset($_POST['materia_id']) ? intval($_POST['materia_id']) : 0;
$curso_id = isset($_POST['curso_id']) ? intval($_POST['curso_id']) : 0;
$profesor_id = isset($_POST['profesor_id']) ? intval($_POST['profesor_id']) : 0;
$periodo = isset($_POST['periodo']) ? $_POST['periodo'] : '';

if ($materia_id === 0 || $curso_id === 0 || $profesor_id === 0 || !in_array($periodo, ['abril_julio', 'septiembre_diciembre'])) {
    die("Error: Parámetros incompletos o inválidos para generar el informe.");
}

// ----------------------------------------------------
// Definir el rango de fechas según el periodo
// ----------------------------------------------------
$anio_actual = date('Y'); // Asume el año actual
$fecha_inicio = '';
$fecha_fin = '';
$titulo_periodo = '';

if ($periodo === 'abril_julio') {
    $fecha_inicio = $anio_actual . '-04-01';
    $fecha_fin = $anio_actual . '-07-31';
    $titulo_periodo = 'Abril - Julio ' . $anio_actual;
} elseif ($periodo === 'septiembre_diciembre') {
    $fecha_inicio = $anio_actual . '-09-01';
    $fecha_fin = $anio_actual . '-12-31';
    $titulo_periodo = 'Septiembre - Diciembre ' . $anio_actual;
}

// ----------------------------------------------------
// Obtener datos de la materia, curso y profesor
// ----------------------------------------------------
$materia_nombre = 'N/A';
$curso_anio = 'N/A';
$profesor_nombre = 'N/A';

$stmt_info = $mysqli->prepare("
    SELECT 
        m.nombre AS materia_nombre,
        c.anio AS curso_anio,
        CONCAT(per.nombres, ' ', per.apellidos) AS profesor_nombre
    FROM materia m
    JOIN curso c ON c.id = ? -- Unir con curso para obtener el año del curso
    JOIN profesor_materia pm ON pm.materia_id = m.id AND pm.profesor_id = ?
    JOIN profesor p ON p.id = pm.profesor_id
    JOIN persona per ON per.id = p.persona_id
    WHERE m.id = ?
");
$stmt_info->bind_param("iii", $curso_id, $profesor_id, $materia_id);
$stmt_info->execute();
$result_info = $stmt_info->get_result();
if ($info = $result_info->fetch_assoc()) {
    $materia_nombre = $info['materia_nombre'];
    $curso_anio = $info['curso_anio'];
    $profesor_nombre = $info['profesor_nombre'];
}
$stmt_info->close();

// ----------------------------------------------------
// Obtener alumnos y sus asistencias
// ----------------------------------------------------
$data_alumnos_asistencias = [];

$stmt_alumnos_asistencias = $mysqli->prepare("
    SELECT 
        a.id AS alumno_id,
        CONCAT(per.nombres, ' ', per.apellidos) AS alumno_nombre,
        GROUP_CONCAT(CONCAT(asis.fecha, ':', asis.estado) ORDER BY asis.fecha ASC SEPARATOR '|') AS asistencias_detalles
    FROM alumno al
    JOIN persona per ON al.persona_id = per.id
    JOIN inscripcion_cursado ic ON ic.alumno_id = al.id
    LEFT JOIN asistencia asis ON asis.inscripcion_cursado_id = ic.id AND asis.fecha BETWEEN ? AND ?
    WHERE ic.materia_id = ? AND ic.curso_id = ?
    GROUP BY al.id, per.nombres, per.apellidos
    ORDER BY per.apellidos, per.nombres
");
$stmt_alumnos_asistencias->bind_param("ssii", $fecha_inicio, $fecha_fin, $materia_id, $curso_id);
$stmt_alumnos_asistencias->execute();
$result_alumnos_asistencias = $stmt_alumnos_asistencias->get_result();

while ($row = $result_alumnos_asistencias->fetch_assoc()) {
    $total_asistencias = 0;
    if (!empty($row['asistencias_detalles'])) {
        $asistencias_arr = explode('|', $row['asistencias_detalles']);
        foreach ($asistencias_arr as $detalle) {
            list($fecha, $estado) = explode(':', $detalle);
            if ($estado === 'P' || $estado === 'J') { // 'P' para presente, 'J' para justificado
                $total_asistencias++;
            }
        }
    }
    $data_alumnos_asistencias[] = [
        'alumno_nombre' => $row['alumno_nombre'],
        'total_asistencias' => $total_asistencias
    ];
}
$stmt_alumnos_asistencias->close();
$mysqli->close();

// ----------------------------------------------------
// Generación del PDF con FPDF
// ----------------------------------------------------
class PDF extends FPDF
{
    protected $materia_nombre;
    protected $curso_anio;
    protected $profesor_nombre;
    protected $titulo_periodo;

    function Header()
    {
        // Logo (opcional, si tienes uno)
        // $this->Image('ruta/a/tu/logo.png', 10, 8, 30);
        $this->SetFont('Arial', 'B', 15);
        // Título
        $this->Cell(0, 10, utf8_decode('Informe de Asistencias - ISEF'), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 7, utf8_decode('Materia: ' . $this->materia_nombre . ' - Curso: ' . $this->curso_anio), 0, 1, 'C');
        $this->Cell(0, 7, utf8_decode('Profesor/a: ' . $this->profesor_nombre), 0, 1, 'C');
        $this->Cell(0, 7, utf8_decode('Periodo: ' . $this->titulo_periodo), 0, 1, 'C');
        // Salto de línea
        $this->Ln(10);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
        $this->Cell(0, 10, utf8_decode('Generado el: ' . date('d/m/Y H:i')), 0, 0, 'R');
    }

    function setInfo($materia, $curso, $profesor, $periodo)
    {
        $this->materia_nombre = $materia;
        $this->curso_anio = $curso;
        $this->profesor_nombre = $profesor;
        $this->titulo_periodo = $periodo;
    }

    function ChapterTitle($label)
    {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 6, $label, 0, 1, 'L', false);
        $this->Ln(4);
    }

    function ChapterBody($body)
    {
        $this->SetFont('Times', '', 12);
        $this->MultiCell(0, 5, $body);
        $this->Ln();
    }

    // Tabla para los datos
    function BasicTable($header, $data)
    {
        // Anchos de las columnas
        $w = array(150, 40); // Ancho para Nombre del Alumno y Total de Asistencias
        // Cabecera
        $this->SetFillColor(200, 220, 255);
        $this->SetFont('Arial', 'B', 10);
        for ($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C', true);
        }
        $this->Ln();
        // Datos
        $this->SetFont('Arial', '', 10);
        foreach ($data as $row) {
            $this->Cell($w[0], 6, utf8_decode($row['alumno_nombre']), 1);
            $this->Cell($w[1], 6, $row['total_asistencias'], 1, 0, 'C');
            $this->Ln();
        }
    }
}

$pdf = new PDF();
$pdf->AliasNbPages(); // Para el número de páginas en el footer
$pdf->setInfo($materia_nombre, $curso_anio, $profesor_nombre, $titulo_periodo);
$pdf->AddPage();

$header = array('Nombre del Alumno', 'Total Asistencias');
$pdf->BasicTable($header, $data_alumnos_asistencias);

$pdf->Output('I', utf8_decode('Informe_Asistencias_' . str_replace(' ', '_', $materia_nombre) . '_' . str_replace(' ', '_', $curso_anio) . '_' . $periodo . '.pdf'));

?>