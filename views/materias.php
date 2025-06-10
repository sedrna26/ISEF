<?php
// materias.php - Gestión integrada de Materias y Correlatividades
session_start();

// 1. Verificación de sesión y tipo de usuario
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}
if ($_SESSION['tipo'] !== 'administrador') {
    $_SESSION['mensaje_error'] = "Acceso no autorizado.";
    header("Location: dashboard.php");
    exit;
}

// 2. Incluir el archivo de conexión y funciones
require_once '../config/db.php';

// 3. Obtener el nombre del usuario para el sidebar
$stmt_user_sidebar = $mysqli->prepare("
    SELECT CONCAT(p.apellidos ,' ', p.nombres) as nombre_completo 
    FROM persona p 
    JOIN usuario u ON p.usuario_id = u.id 
    WHERE u.id = ?
");
$stmt_user_sidebar->bind_param("i", $_SESSION['usuario_id']);
$stmt_user_sidebar->execute();
$result_user_sidebar = $stmt_user_sidebar->get_result();
$usuario_sidebar = $result_user_sidebar->fetch_assoc();
$stmt_user_sidebar->close();


// 4. Lógica de procesamiento de formularios (CRUD)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    try {
        $mysqli->begin_transaction();

        if ($accion === 'crear') {
            $stmt = $mysqli->prepare("INSERT INTO materia (nro_orden, codigo, nombre, tipo, anio, cuatrimestre) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssis", $_POST['nro_orden'], $_POST['codigo'], $_POST['nombre'], $_POST['tipo'], $_POST['anio'], $_POST['cuatrimestre']);
            $stmt->execute();
            $_SESSION['mensaje_exito'] = "Materia creada correctamente.";

        } elseif ($accion === 'editar') {
            $stmt = $mysqli->prepare("UPDATE materia SET nro_orden = ?, codigo = ?, nombre = ?, tipo = ?, anio = ?, cuatrimestre = ? WHERE id = ?");
            $stmt->bind_param("isssisi", $_POST['nro_orden'], $_POST['codigo'], $_POST['nombre'], $_POST['tipo'], $_POST['anio'], $_POST['cuatrimestre'], $_POST['materia_id']);
            $stmt->execute();
            $_SESSION['mensaje_exito'] = "Materia actualizada correctamente.";

        } elseif ($accion === 'eliminar') {
            $stmt = $mysqli->prepare("DELETE FROM materia WHERE id = ?");
            $stmt->bind_param("i", $_POST['materia_id_eliminar']);
            $stmt->execute();
            $_SESSION['mensaje_exito'] = "Materia eliminada correctamente.";

        } elseif ($accion === 'editar_correlatividades') {
            $materia_id = $_POST['materia_id_correl'];
            $correlativas_ids = $_POST['correlativas_ids'] ?? [];
            $correlativas_tipos = $_POST['correlativas_tipos'] ?? [];

            // Borrar correlatividades existentes para esta materia
            $stmt_del = $mysqli->prepare("DELETE FROM correlatividad WHERE materia_id = ?");
            $stmt_del->bind_param("i", $materia_id);
            $stmt_del->execute();

            // Insertar las nuevas correlatividades
            if (!empty($correlativas_ids)) {
                $stmt_ins = $mysqli->prepare("INSERT INTO correlatividad (materia_id, materia_correlativa_id, tipo) VALUES (?, ?, ?)");
                foreach ($correlativas_ids as $correl_id) {
                    $tipo = $correlativas_tipos[$correl_id] ?? 'Para cursar regularizada'; // Fallback
                    $stmt_ins->bind_param("iis", $materia_id, $correl_id, $tipo);
                    $stmt_ins->execute();
                }
            }
            $_SESSION['mensaje_exito'] = "Correlatividades actualizadas correctamente.";
        }

        $mysqli->commit();
    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['mensaje_error'] = "Error en la operación: " . $e->getMessage();
    }

    header("Location: materias.php");
    exit;
}

// 5. Recuperar datos para la vista
$mensaje_exito = $_SESSION['mensaje_exito'] ?? null;
$mensaje_error = $_SESSION['mensaje_error'] ?? null;
unset($_SESSION['mensaje_exito'], $_SESSION['mensaje_error']);

// Obtener lista de materias
$resultado_materias = $mysqli->query("SELECT * FROM materia ORDER BY anio, nro_orden");
$lista_materias = $resultado_materias->fetch_all(MYSQLI_ASSOC);

// Obtener correlatividades y mapearlas
$resultado_correlatividades = $mysqli->query("SELECT * FROM correlatividad");
$correlatividades_map = [];
while ($corr = $resultado_correlatividades->fetch_assoc()) {
    $correlatividades_map[$corr['materia_id']][] = $corr;
}

// Stats Cards Data
$total_materias = count($lista_materias);
$total_cursos = $mysqli->query("SELECT COUNT(*) as total FROM curso")->fetch_assoc()['total'];
$total_correlatividades = $mysqli->query("SELECT COUNT(*) as total FROM correlatividad")->fetch_assoc()['total'];
$total_profesores = $mysqli->query("SELECT COUNT(*) as total FROM profesor")->fetch_assoc()['total'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Materias - Sistema ISEF</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        /* Estilos base y de dashboard (reutilizados de profesores.php) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f8fafc; color: #334155; line-height: 1.6; }
        .app-container { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: white; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 1.5rem; border-bottom: 1px solid #e2e8f0; }
        .sidebar-brand { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; color: inherit; }
        .brand-icon { width: 32px; height: 32px; background: #3b82f6; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
        .brand-text h1 { font-size: 1rem; font-weight: 600; margin: 0; }
        .brand-text p { font-size: 0.75rem; color: #64748b; margin: 0; }
        .sidebar-nav { flex: 1; padding: 1rem; }
        .nav-section { margin-bottom: 2rem; }
        .nav-label { font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; padding: 0 0.75rem; }
        .nav-menu { list-style: none; }
        .nav-item { margin-bottom: 0.25rem; }
        .nav-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; color: #64748b; text-decoration: none; border-radius: 6px; transition: all 0.2s; font-size: 0.875rem; }
        .nav-link:hover { background: #f1f5f9; color: #334155; }
        .nav-link.active { background: #dbeafe; color: #1d4ed8; font-weight: 500; }
        .nav-icon { width: 16px; height: 16px; }
        .sidebar-footer { padding: 1rem; border-top: 1px solid #e2e8f0; margin-top: auto; }
        .user-info { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: #f8fafc; border-radius: 6px; margin-bottom: 0.5rem; }
        .user-avatar { width: 32px; height: 32px; background: #3b82f6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem; }
        .user-details h3 { font-size: 0.875rem; font-weight: 500; margin: 0; }
        .user-details p { font-size: 0.75rem; color: #64748b; margin: 0; }
        .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; color: #dc2626; text-decoration: none; border-radius: 6px; transition: all 0.2s; font-size: 0.875rem; border: none; background: none; width: 100%; cursor: pointer; text-align: left; }
        .logout-btn:hover { background: #fee2e2; }
        .main-content { flex: 1; display: flex; flex-direction: column; }
        .header { background: white; border-bottom: 1px solid #e2e8f0; padding: 1rem 1.5rem; display: flex; align-items: center; gap: 1rem; position: sticky; top: 0; z-index: 900; }
        .sidebar-toggle { display: none; background: none; border: none; padding: 0.5rem; cursor: pointer; border-radius: 4px; }
        .breadcrumb { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: #64748b; }
        .breadcrumb a { color: inherit; text-decoration: none; }
        .content { flex: 1; padding: 1.5rem; max-width: 1200px; margin: 0 auto; width: 100%; }
        .page-header { margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .page-title { font-size: 1.75rem; font-weight: 600; }
        .page-subtitle { color: #64748b; font-size: 0.9rem; margin-top: 0.25rem; }
        .message-toast { padding: 1rem; margin-bottom: 1.5rem; border-radius: 6px; border: 1px solid transparent; }
        .message-toast.success { background-color: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .message-toast.error { background-color: #fee2e2; color: #991b1b; border-color: #fecaca; }
        .card { background: white; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 1.5rem; }
        .card-header { padding: 1rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: flex-start; }
        .card-header .card-header-text { flex: 1; }
        .card-title { font-size: 1.125rem; font-weight: 600; }
        .card-description { font-size: 0.875rem; color: #64748b; margin-top:0.25rem; }
        .card-content { padding: 1.5rem; }
        .card-footer { padding: 1rem 1.5rem; border-top: 1px solid #e2e8f0; background-color: #f8fafc; text-align: right;}
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.875rem; color: #334155; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.625rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.875rem; color: #334155; background-color: white; transition: border-color 0.2s, box-shadow 0.2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 1px #3b82f6; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 0.625rem 1rem; font-size: 0.875rem; font-weight: 500; border-radius: 6px; border: 1px solid transparent; cursor: pointer; transition: all 0.2s; text-decoration: none; white-space: nowrap; }
        .btn i { margin-right: 0.5rem; width:16px; height:16px; }
        .btn.btn-primary { background-color: #2563eb; color: white; }
        .btn.btn-primary:hover { background-color: #1d4ed8; }
        .btn.btn-secondary { background-color: #e2e8f0; color: #334155; }
        .btn.btn-secondary:hover { background-color: #cbd5e1; }
        .btn.btn-danger-outline { color: #dc2626; border-color: #fecaca; background:transparent; }
        .btn.btn-danger-outline:hover { background-color: #fee2e2; color: #b91c1c; border-color: #fca5a5;}
        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.75rem; }
        .table-container { border: 1px solid #e2e8f0; border-radius: 8px; overflow-x: auto; background: white; }
        table.styled-table { width: 100%; border-collapse: collapse; }
        table.styled-table th, table.styled-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 0.875rem; white-space: nowrap; }
        table.styled-table th { background-color: #f8fafc; color: #475569; font-weight: 600; }
        .table-actions { display: flex; gap: 0.5rem; justify-content: flex-end; }
        .badge { display: inline-flex; align-items: center; padding: 0.25em 0.6em; font-size: 0.75rem; font-weight: 500; border-radius: 9999px; }
        .badge-info { background-color: #e0f2fe; color: #0c4a6e; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 1rem; }
        .modal.show { display: flex; }
        .modal-content { background: white; padding: 0; border-radius: 8px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; display: flex; flex-direction: column; }
        .modal-content .card-header, .modal-content .card-footer { flex-shrink: 0; }
        .modal-content .card-content { flex-grow: 1; overflow-y: auto; }
        /* Nuevos Estilos para Materias */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; }
        .stat-card { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; }
        .stat-info .stat-title { font-size: 0.875rem; font-weight: 500; color: #64748b; }
        .stat-info .stat-value { font-size: 1.75rem; font-weight: 700; color: #1e293b; }
        .stat-icon { color: #94a3b8; }
        .tabs { display: flex; border-bottom: 1px solid #e2e8f0; margin-bottom: 1.5rem; }
        .tab-link { padding: 0.75rem 1rem; cursor: pointer; font-size: 0.875rem; font-weight: 500; color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -1px; }
        .tab-link.active { color: #2563eb; border-color: #2563eb; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .correl-map-grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
        .correl-map-grid .card-description { font-size: 0.8rem; }
        .correl-list { list-style: none; padding-left: 0; margin-top: 0.75rem; }
        .correl-list li { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; }
        .correl-list .badge { font-size: 0.7rem; }
        #correlatividadesModal .form-group { display: flex; align-items: center; justify-content: space-between; padding: 0.5rem; border-radius: 6px; }
        #correlatividadesModal .form-group:hover { background-color: #f8fafc; }
        #correlatividadesModal .form-group label { margin-bottom: 0; flex-grow: 1; cursor: pointer; }
        #correlatividadesModal .form-group .select-container { display: flex; align-items: center; gap: 0.5rem; }
        @media (max-width: 768px) { .sidebar { display:none; } .sidebar-toggle { display: block; } .stats-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="app-container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="sidebar-brand">
                    <div class="brand-icon"><i data-lucide="school"></i></div>
                    <div class="brand-text"><h1>Sistema de Gestión ISEF</h1><p>Instituto Superior</p></div>
                </a>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-label">Navegación Principal</div>
                    <ul class="nav-menu">
                        <li class="nav-item"><a href="dashboard.php" class="nav-link"><i data-lucide="home" class="nav-icon"></i><span>Inicio</span></a></li>
                        <li class="nav-item"><a href="alumnos.php" class="nav-link"><i data-lucide="graduation-cap" class="nav-icon"></i><span>Alumnos</span></a></li>
                        <li class="nav-item"><a href="profesores.php" class="nav-link"><i data-lucide="briefcase" class="nav-icon"></i><span>Profesores</span></a></li>
                        <li class="nav-item"><a href="usuarios.php" class="nav-link"><i data-lucide="users" class="nav-icon"></i><span>Usuarios</span></a></li>
                        <li class="nav-item"><a href="materias.php" class="nav-link active"><i data-lucide="book-open" class="nav-icon"></i><span>Materias</span></a></li>
                        <li class="nav-item"><a href="cursos.php" class="nav-link"><i data-lucide="library" class="nav-icon"></i><span>Cursos</span></a></li>
                        <li class="nav-item"><a href="auditoria.php" class="nav-link"><i data-lucide="clipboard-list" class="nav-icon"></i><span>Auditoría</span></a></li>
                    </ul>
                </div>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><?= strtoupper(substr($usuario_sidebar['nombre_completo'] ?? 'A', 0, 1)) ?></div>
                    <div class="user-details">
                        <h3><?= htmlspecialchars($usuario_sidebar['nombre_completo'] ?? 'Admin') ?></h3>
                        <p><?= htmlspecialchars($_SESSION['tipo']) ?>@isef.edu</p>
                    </div>
                </div>
                <a href="../index.php?logout=1" class="logout-btn" onclick="return confirm('¿Estás seguro que deseas cerrar sesión?');">
                    <i data-lucide="log-out" class="nav-icon"></i><span>Cerrar Sesión</span>
                </a>
            </div>
        </aside>

        <main class="main-content">
            <header class="header">
                <button class="sidebar-toggle"><i data-lucide="menu"></i></button>
                <nav class="breadcrumb">
                    <a href="dashboard.php">ISEF</a><span>/</span><span>Materias</span>
                </nav>
            </header>

            <div class="content">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Gestión de Materias</h1>
                        <p class="page-subtitle">Administra el plan de estudios y las correlatividades.</p>
                    </div>
                    <button class="btn btn-primary" onclick="abrirModal('creacionModal')">
                        <i data-lucide="plus"></i>
                        Nueva Materia
                    </button>
                </div>

                <?php if ($mensaje_exito): ?>
                    <div class="message-toast success" role="alert"><?= htmlspecialchars($mensaje_exito) ?></div>
                <?php endif; ?>
                <?php if ($mensaje_error): ?>
                    <div class="message-toast error" role="alert"><?= htmlspecialchars($mensaje_error) ?></div>
                <?php endif; ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-info">
                            <div class="stat-title">Total Materias</div>
                            <div class="stat-value"><?= $total_materias ?></div>
                        </div>
                        <i data-lucide="book-open" class="stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <div class="stat-title">Correlatividades</div>
                            <div class="stat-value"><?= $total_correlatividades ?></div>
                        </div>
                        <i data-lucide="link" class="stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <div class="stat-title">Total Cursos</div>
                            <div class="stat-value"><?= $total_cursos ?></div>
                        </div>
                        <i data-lucide="library" class="stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <div class="stat-title">Total Profesores</div>
                            <div class="stat-value"><?= $total_profesores ?></div>
                        </div>
                        <i data-lucide="briefcase" class="stat-icon"></i>
                    </div>
                </div>

                <div class="tabs">
                    <a href="#" class="tab-link active" data-tab="lista">Lista de Materias</a>
                    <a href="#" class="tab-link" data-tab="mapa">Mapa de Correlatividades</a>
                </div>

                <div id="tab-lista" class="tab-content active">
                    <div class="card">
                        <div class="card-header">
                           <div class="card-header-text">
                             <h2 class="card-title">Materias Registradas</h2>
                             <p class="card-description">Busca, visualiza y gestiona las materias del plan de estudios.</p>
                           </div>
                        </div>
                        <div class="card-content">
                             <div class="form-group" style="margin-bottom: 1.5rem;">
                                <input type="search" id="searchInput" onkeyup="filterTable()" placeholder="Buscar por Nombre, Código o Año..." style="width: 100%;">
                            </div>
                            <div class="table-container">
                                <table class="styled-table" id="materiasTable">
                                    <thead>
                                        <tr>
                                            <th>N° Orden</th>
                                            <th>Código</th>
                                            <th>Nombre</th>
                                            <th>Año</th>
                                            <th>Cuat./Tipo</th>
                                            <th>Correlativas</th>
                                            <th class="text-right">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lista_materias as $materia): ?>
                                        <tr data-anio="<?= htmlspecialchars($materia['anio']) ?>">
                                            <td><?= htmlspecialchars($materia['nro_orden']) ?></td>
                                            <td><?= htmlspecialchars($materia['codigo']) ?></td>
                                            <td><?= htmlspecialchars($materia['nombre']) ?></td>
                                            <td><?= htmlspecialchars($materia['anio']) ?>° Año</td>
                                            <td><?= htmlspecialchars($materia['tipo']) === 'Anual' ? 'Anual' : $materia['cuatrimestre'] . '° Cuat.' ?></td>
                                            <td>
                                                <?php 
                                                $count_corr = isset($correlatividades_map[$materia['id']]) ? count($correlatividades_map[$materia['id']]) : 0;
                                                if ($count_corr > 0) {
                                                    echo "<span class='badge badge-info'>{$count_corr}</span>";
                                                } else {
                                                    echo "Ninguna";
                                                }
                                                ?>
                                            </td>
                                            <td class="table-actions">
                                                <button class="btn btn-sm" onclick='abrirModalCorrelatividades(<?= json_encode($materia, ENT_QUOTES) ?>)' title="Gestionar Correlatividades">
                                                    <i data-lucide="link"></i>
                                                </button>
                                                <button class="btn btn-sm" onclick='abrirModalEdicion(<?= json_encode($materia, ENT_QUOTES) ?>)' title="Editar Materia">
                                                    <i data-lucide="edit-2"></i>
                                                </button>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('¿Está seguro de eliminar esta materia? Esta acción no se puede deshacer.');">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="materia_id_eliminar" value="<?= $materia['id'] ?>">
                                                    <button type="submit" class="btn btn-danger-outline btn-sm" title="Eliminar Materia">
                                                        <i data-lucide="trash-2"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <tr id="noResultsSearchRow" style="display: none;">
                                            <td colspan="7" style="text-align:center; padding: 2rem;">No se encontraron materias que coincidan con la búsqueda.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="tab-mapa" class="tab-content">
                     <div class="card">
                        <div class="card-header">
                           <div class="card-header-text">
                             <h2 class="card-title">Mapa de Correlatividades</h2>
                             <p class="card-description">Visualiza las dependencias entre materias del plan de estudios.</p>
                           </div>
                        </div>
                        <div class="card-content">
                            <div class="correl-map-grid">
                            <?php 
                            $materias_por_anio = [];
                            foreach ($lista_materias as $m) {
                                $materias_por_anio[$m['anio']][] = $m;
                            }
                            ksort($materias_por_anio);
                            ?>
                            <?php foreach ($materias_por_anio as $anio => $materias_de_anio): ?>
                                <div>
                                    <h3 class="page-title" style="font-size: 1.25rem; margin-bottom: 1rem;"><?= $anio ?>° Año</h3>
                                    <div class="correl-map-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
                                    <?php foreach ($materias_de_anio as $materia): ?>
                                        <div class="card" style="margin-bottom: 0;">
                                            <div class="card-header">
                                                <div class="card-header-text">
                                                    <h4 class="card-title" style="font-size: 1rem;"><?= htmlspecialchars($materia['nombre']) ?></h4>
                                                    <p class="card-description"><?= htmlspecialchars($materia['codigo']) ?> - <?= htmlspecialchars($materia['tipo']) === 'Anual' ? 'Anual' : $materia['cuatrimestre'] . '° Cuat.' ?></p>
                                                </div>
                                            </div>
                                            <div class="card-content">
                                                <?php if (isset($correlatividades_map[$materia['id']])): ?>
                                                    <strong style="font-size:0.875rem;">Requiere:</strong>
                                                    <ul class="correl-list">
                                                        <?php foreach ($correlatividades_map[$materia['id']] as $corr): ?>
                                                            <?php 
                                                            $materia_corr_nombre = "Materia no encontrada";
                                                            foreach($lista_materias as $m_lookup) {
                                                                if ($m_lookup['id'] == $corr['materia_correlativa_id']) {
                                                                    $materia_corr_nombre = $m_lookup['nombre'];
                                                                    break;
                                                                }
                                                            }
                                                            ?>
                                                            <li>
                                                                <i data-lucide="arrow-right" style="width:14px; height:14px;"></i>
                                                                <span><?= htmlspecialchars($materia_corr_nombre) ?></span>
                                                                <span class="badge badge-info"><?= htmlspecialchars(str_replace('Para cursar ', '', $corr['tipo'])) ?></span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    <p style="font-size:0.875rem; color: #64748b;">Sin correlatividades</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div> </main>
    </div>

    <div id="creacionModal" class="modal">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="accion" value="crear">
                <div class="card-header">
                    <div class="card-header-text">
                        <h2 class="card-title">Registrar Nueva Materia</h2>
                        <p class="card-description">Completa los datos para agregar una nueva materia.</p>
                    </div>
                </div>
                <div class="card-content">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="crear-codigo">Código</label>
                            <input type="text" id="crear-codigo" name="codigo" required>
                        </div>
                        <div class="form-group">
                            <label for="crear-nombre">Nombre de la Materia</label>
                            <input type="text" id="crear-nombre" name="nombre" required>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="crear-nro_orden">N° de Orden</label>
                            <input type="number" id="crear-nro_orden" name="nro_orden" required>
                        </div>
                        <div class="form-group">
                            <label for="crear-anio">Año de cursado</label>
                            <select id="crear-anio" name="anio" required>
                                <option value="1">1° Año</option><option value="2">2° Año</option><option value="3">3° Año</option><option value="4">4° Año</option>
                            </select>
                        </div>
                    </div>
                     <div class="form-grid">
                        <div class="form-group">
                            <label for="crear-tipo">Tipo</label>
                            <select id="crear-tipo" name="tipo" required>
                                <option value="Cuatrimestral">Cuatrimestral</option><option value="Anual">Anual</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="crear-cuatrimestre">Cuatrimestre</label>
                            <select id="crear-cuatrimestre" name="cuatrimestre" required>
                                <option value="1°">1° Cuatrimestre</option><option value="2°">2° Cuatrimestre</option><option value="Anual">Anual</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal('creacionModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="margin-left:0.5rem;">Crear Materia</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="edicionModal" class="modal">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" id="edit-materia-id" name="materia_id">
                <div class="card-header">
                     <div class="card-header-text">
                        <h2 class="card-title">Editar Materia</h2>
                        <p class="card-description">Modifica la información de la materia seleccionada.</p>
                    </div>
                </div>
                <div class="card-content">
                     <div class="form-grid">
                        <div class="form-group">
                            <label for="edit-codigo">Código</label>
                            <input type="text" id="edit-codigo" name="codigo" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-nombre">Nombre de la Materia</label>
                            <input type="text" id="edit-nombre" name="nombre" required>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit-nro_orden">N° de Orden</label>
                            <input type="number" id="edit-nro_orden" name="nro_orden" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-anio">Año de cursado</label>
                            <select id="edit-anio" name="anio" required>
                                <option value="1">1° Año</option><option value="2">2° Año</option><option value="3">3° Año</option><option value="4">4° Año</option>
                            </select>
                        </div>
                    </div>
                     <div class="form-grid">
                        <div class="form-group">
                            <label for="edit-tipo">Tipo</label>
                            <select id="edit-tipo" name="tipo" required>
                                <option value="Cuatrimestral">Cuatrimestral</option><option value="Anual">Anual</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit-cuatrimestre">Cuatrimestre</label>
                            <select id="edit-cuatrimestre" name="cuatrimestre" required>
                                <option value="1°">1° Cuatrimestre</option><option value="2°">2° Cuatrimestre</option><option value="Anual">Anual</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal('edicionModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="margin-left:0.5rem;">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="correlatividadesModal" class="modal">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="accion" value="editar_correlatividades">
                <input type="hidden" id="correl-materia-id" name="materia_id_correl">
                <div class="card-header">
                     <div class="card-header-text">
                        <h2 class="card-title">Gestionar Correlatividades</h2>
                        <p class="card-description" id="correl-modal-description">Seleccione las materias requeridas.</p>
                    </div>
                </div>
                <div class="card-content" id="correl-modal-content">
                    </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal('correlatividadesModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="margin-left:0.5rem;">Guardar Correlatividades</button>
                </div>
            </form>
        </div>
    </div>


    <script>
        lucide.createIcons();

        // Lógica de Tabs
        const tabLinks = document.querySelectorAll('.tab-link');
        const tabContents = document.querySelectorAll('.tab-content');
        tabLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const tabId = link.getAttribute('data-tab');
                
                tabLinks.forEach(l => l.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));

                link.classList.add('active');
                document.getElementById(`tab-${tabId}`).classList.add('active');
            });
        });

        // Lógica de Modales
        function abrirModal(modalId) { document.getElementById(modalId).style.display = 'flex'; }
        function cerrarModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = "none";
            }
        }

        function abrirModalEdicion(materia) {
            document.getElementById('edit-materia-id').value = materia.id;
            document.getElementById('edit-nro_orden').value = materia.nro_orden;
            document.getElementById('edit-codigo').value = materia.codigo;
            document.getElementById('edit-nombre').value = materia.nombre;
            document.getElementById('edit-anio').value = materia.anio;
            document.getElementById('edit-tipo').value = materia.tipo;
            document.getElementById('edit-cuatrimestre').value = materia.cuatrimestre;
            abrirModal('edicionModal');
        }

        // Lógica para el modal de Correlatividades
        const todasLasMaterias = <?= json_encode($lista_materias, ENT_QUOTES) ?>;
        const correlatividadesMap = <?= json_encode($correlatividades_map, ENT_QUOTES) ?> || {};

        function abrirModalCorrelatividades(materia) {
            document.getElementById('correl-materia-id').value = materia.id;
            document.getElementById('correl-modal-description').textContent = `Seleccione las materias requeridas para cursar "${materia.nombre}"`;
            
            const contentDiv = document.getElementById('correl-modal-content');
            contentDiv.innerHTML = ''; // Limpiar contenido anterior

            const materiasDisponibles = todasLasMaterias.filter(m => m.id !== materia.id && m.anio <= materia.anio);
            const correlatividadesActuales = correlatividadesMap[materia.id] || [];
            
            materiasDisponibles.forEach(mDisp => {
                const correlExistente = correlatividadesActuales.find(c => c.materia_correlativa_id == mDisp.id);
                const isChecked = !!correlExistente;
                const tipoActual = correlExistente ? correlExistente.tipo : 'Para cursar regularizada';

                const formGroup = document.createElement('div');
                formGroup.className = 'form-group';
                formGroup.innerHTML = `
                    <label for="correl-${mDisp.id}">
                        <input type="checkbox" id="correl-${mDisp.id}" name="correlativas_ids[]" value="${mDisp.id}" ${isChecked ? 'checked' : ''}>
                        <span style="margin-left: 0.5rem;">${mDisp.nombre} (${mDisp.anio}° Año)</span>
                    </label>
                    <div class="select-container">
                        <select name="correlativas_tipos[${mDisp.id}]">
                            <option value="Para cursar regularizada" ${tipoActual === 'Para cursar regularizada' ? 'selected' : ''}>Regularizada</option>
                            <option value="Para cursar acreditada" ${tipoActual === 'Para cursar acreditada' ? 'selected' : ''}>Acreditada</option>
                            <option value="Para acreditar" ${tipoActual === 'Para acreditar' ? 'selected' : ''}>Para Acreditar</option>
                        </select>
                    </div>
                `;
                contentDiv.appendChild(formGroup);
            });

            abrirModal('correlatividadesModal');
        }


        // Lógica de filtrado de tabla
        function filterTable() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toLowerCase();
            const table = document.getElementById("materiasTable");
            const tr = table.getElementsByTagName("tr");
            let foundMatch = false;

            for (let i = 1; i < tr.length; i++) { // Empezar en 1 para saltar el header
                if (tr[i].id === 'noResultsSearchRow') continue;

                const tdCodigo = tr[i].getElementsByTagName("td")[1];
                const tdNombre = tr[i].getElementsByTagName("td")[2];
                const tdAnio = tr[i].getElementsByTagName("td")[3];
                
                if (tdCodigo || tdNombre || tdAnio) {
                    const textValue = (tdCodigo.textContent || tdCodigo.innerText) + 
                                      (tdNombre.textContent || tdNombre.innerText) +
                                      (tdAnio.textContent || tdAnio.innerText);
                    
                    if (textValue.toLowerCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                        foundMatch = true;
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
            
            const noResultsRow = document.getElementById('noResultsSearchRow');
            noResultsRow.style.display = foundMatch ? 'none' : '';
        }

    </script>
</body>
</html>