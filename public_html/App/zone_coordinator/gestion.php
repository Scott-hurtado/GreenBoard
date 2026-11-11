<?php
// ==========================================
// 1. CONFIGURACIÓN Y SEGURIDAD
// ==========================================
require_once __DIR__ . '/../../includes/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$rol = $_SESSION['user_role'] ?? '';
$usuario_id = $_SESSION['user_id'] ?? 0;
$user_zona = $_SESSION['user_zona_nombre'] ?? 'Zona Asignada';
$zona_id = $_SESSION['user_zona_id'] ?? 0; // Nuevo: Necesitamos el ID para las consultas
$estado_actual = $_SESSION['user_estado'] ?? '';

// Permitimos acceso a los perfiles operativos (restringimos si es necesario)
$permitidos = ['coordinador_zona', 'coordinador_regional', 'coordinador_estatal', 'admin', 'superadmin'];

if (!in_array($rol, $permitidos)) {
    header('Location: /public/index.php'); 
    exit();
}

$db = Database::getInstance()->getConnection();
$nombre_usuario = $_SESSION['user_name'] ?? 'Coordinador';

// Recuperar zona desde la tabla correcta: coordinador_zona
if ((empty($zona_id) || $user_zona === 'Zona Asignada') && $usuario_id > 0) {
    try {
        $stmt_z = $db->prepare("
            SELECT z.id, z.nombre
            FROM coordinador_zona cz
            INNER JOIN zonas_estatales z ON cz.zona = z.id
            WHERE cz.id = ?
        ");
        $stmt_z->execute([$usuario_id]);
        $res_z = $stmt_z->fetch(PDO::FETCH_ASSOC);

        if ($res_z) {
            $zona_id   = $res_z['id'];
            $user_zona = $res_z['nombre'];
            $_SESSION['user_zona_id']     = $zona_id;
            $_SESSION['user_zona_nombre'] = $user_zona;
        }
    } catch (Exception $e) {
        // Silencioso en producción
    }
}

if (empty($estado_actual)) {
    die("Error: Tu perfil no tiene un estado asignado.");
}
if (empty($zona_id) && $rol === 'coordinador_zona') {
    die("Error: Tu perfil no tiene una zona asignada para operar.");
}

// ==========================================
// 2. CONSULTAS DE DATOS (LECTURA Y FILTRADO)
// ==========================================
$data = [
    'vision_mision' => ['vision' => '', 'mision' => ''], 
    'objetivos' => [], 
    'estrategias' => [], 
    'metas' => [], 
    'cronograma' => [],
    'diagnostico' => null,     
    'diag_electoral' => [],
    'archivos' => []
];

try {
    // --- DATOS DE ALINEACIÓN (ESTATALES) ---
    // Visión/Misión (Se mantiene a nivel estatal para alineación)
    $stmt = $db->prepare("SELECT vision, mision FROM geo_vision_mision WHERE estado = ? LIMIT 1");
    $stmt->execute([$estado_actual]);
    $vm = $stmt->fetch(PDO::FETCH_ASSOC);
    if($vm) $data['vision_mision'] = $vm;
    else $data['vision_mision'] = ['vision' => "Aún no definida por la coordinación estatal.", 'mision' => "Aún no definida por la coordinación estatal."];

    // Objetivos (Se mantienen a nivel estatal para que la zona sepa a qué apuntar)
    $stmt = $db->prepare("SELECT * FROM geo_objetivos WHERE estado = ? AND activo = 1 ORDER BY id DESC");
    $stmt->execute([$estado_actual]);
    $data['objetivos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estrategias (Se mantienen a nivel estatal)
    $stmt = $db->prepare("SELECT * FROM geo_estrategias WHERE estado = ? AND activo = 1 ORDER BY id DESC");
    $stmt->execute([$estado_actual]);
    $data['estrategias'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- DATOS OPERATIVOS (FILTRADOS POR ZONA) ---
    // Metas (Específicas de la zona)
    $stmt = $db->prepare("SELECT * FROM geo_metas WHERE estado = ? AND zona = ? AND activo = 1 ORDER BY id ASC");
    $stmt->execute([$estado_actual, $zona_id]);
    $data['metas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cronograma (Agenda específica de la zona)
    $stmt = $db->prepare("SELECT * FROM geo_cronograma WHERE estado = ? AND zona = ? AND activo = 1 ORDER BY fecha_orden ASC");
    $stmt->execute([$estado_actual, $zona_id]);
    $data['cronograma'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- DIAGNÓSTICO (FILTRADO POR ZONA) ---
    $stmt = $db->prepare("SELECT * FROM geo_diagnostico WHERE estado = ? AND zona = ? LIMIT 1");
    $stmt->execute([$estado_actual, $zona_id]);
    $data['diagnostico'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- CARGAR DATOS ELECTORALES (ZONA) ---
    $stmt = $db->prepare("SELECT * FROM geo_diag_electoral WHERE estado = ? AND zona = ? ORDER BY anio ASC");
    $stmt->execute([$estado_actual, $zona_id]);
    $electoral = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($electoral as $row) {
        $data['diag_electoral'][$row['anio']] = $row;
    }

    // --- CARGAR ARCHIVOS ADJUNTOS (ZONA) ---
    $stmt = $db->prepare("SELECT * FROM geo_diag_archivos WHERE estado = ? AND zona = ? ORDER BY fecha_subida DESC");
    $stmt->execute([$estado_actual, $zona_id]);
    $data['archivos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) { $error_msg = "Error DB: " . $e->getMessage(); }

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Operativa - <?php echo htmlspecialchars($user_zona); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #1976d2; --primary-dark: #1565c0; --dark: #1a1a1a; --light: #f4f6f8; --white: #ffffff; --gray-100: #f8f9fa; --gray-200: #e9ecef; }
        /* Se cambió el color primario a azul para diferenciarlo visualmente del estatal (verde) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: var(--light); color: var(--dark); overflow-x: hidden; }
        .container { max-width: 100%; margin: 0; background: var(--white); min-height: 100vh; padding-left: 330px; padding-right: 50px; padding-bottom: 40px; transition: padding-left 0.3s ease; }
        
        .page-header { padding: 40px 0 20px 0; }
        .ai-badge { 
            display: inline-block; 
            color: var(--primary); 
            font-size: 13px; 
            font-weight: 800; 
            letter-spacing: 4px; 
            background: rgba(25,118,210,0.1); 
            padding: 5px 15px; 
            border-radius: 20px; 
            text-transform: uppercase; 
            margin-bottom: 10px; 
        }
        .main-title { font-size: 42px; font-weight: 900; margin: 10px 0; color: var(--dark); }
        .subtitle { color: #666; font-size: 16px; margin-bottom: 30px; line-height: 1.6; }

        .coord-card { background: #e3f2fd; border-left: 5px solid var(--primary); padding: 20px; border-radius: 10px; margin-bottom: 30px; display: flex; align-items: center; gap: 20px; }
        .coord-avatar { width: 60px; height: 60px; background: var(--white); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: var(--primary); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .coord-info h4 { margin: 0; font-size: 18px; font-weight: 800; color: var(--dark); }
        .coord-info p { margin: 5px 0 0 0; font-size: 13px; color: #555; }

        .tabs-container { background: var(--gray-100); border-radius: 16px; padding: 5px; display: inline-flex; gap: 5px; margin-bottom: 30px; flex-wrap: wrap; }
        .tab-btn { padding: 10px 20px; border: none; background: transparent; border-radius: 12px; font-weight: 600; color: #666; cursor: pointer; transition: 0.2s;}
        .tab-btn.active { background: var(--white); color: var(--primary); box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .tab-btn:hover { color: var(--primary); }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.5s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .content-card { background: var(--white); border-radius: 16px; padding: 25px; border: 1px solid var(--gray-200); margin-bottom: 20px; position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: 0.3s; }
        .content-card:hover { transform: translateY(-3px); border-color: var(--primary); }
        .card-tag { position: absolute; top: 20px; left: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; color: var(--white); background: var(--primary); padding: 4px 10px; border-radius: 20px; }
        .content-card h4 { margin-top: 25px; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        
        .table-actions-container { background: #fff; border-radius: 12px; border: 1px solid var(--gray-200); overflow: visible; margin-top: 15px; }
        .custom-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .custom-table th { background: #f4f6f8; color: #555; font-weight: 700; text-align: left; padding: 15px; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        .custom-table td { padding: 15px; border-bottom: 1px solid #eee; vertical-align: top; color: #333; }
        .custom-table tr:hover { background: #fafafa; }
        .date-badge { background: #e8f5e9; color: #2e7d32; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 11px; display: inline-block; white-space: nowrap; }

        .kpi-block { background: #f8f9fa; padding: 20px; border-radius: 12px; border-left: 4px solid var(--primary); margin-bottom: 20px; }
        .kpi-block-title { font-size: 14px; font-weight: 800; color: var(--dark); margin-bottom: 10px; }

        .readonly-val { font-size: 16px; font-weight: 600; color: var(--dark); padding: 12px 15px; background: var(--gray-100); border-radius: 10px; min-height: 45px; display: flex; align-items: center; border: 1px solid var(--gray-200); margin-top: 5px;}
        .readonly-area { font-size: 14px; color: var(--dark); padding: 15px; background: var(--gray-100); border-radius: 10px; border: 1px solid var(--gray-200); line-height: 1.5; margin-top: 5px; min-height: 80px;}

        @media (max-width: 1024px) { .container { padding-left: 20px; padding-right: 20px; } .grid-2 { grid-template-columns: 1fr; } .grid-3 { grid-template-columns: 1fr; } .table-actions-container { overflow-x: auto; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/components/sidebar_cz.php'; ?>

    <div class="container">
        
        <div class="page-header">
            <div class="ai-badge">ZONA <?php echo strtoupper($user_zona); ?> · OPERACIÓN</div>
            <h1 class="main-title">Gestión Operativa de Zona</h1>
            <p class="subtitle">Administra tu agenda, consulta las metas asignadas a tu área y mantén la alineación con los objetivos de la coordinación estatal de <strong><?php echo htmlspecialchars($estado_actual); ?></strong>.</p>
        </div>

        <?php if(isset($error_msg)): ?>
            <div style="background: #ffebee; color: #c62828; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <div class="coord-card">
            <div class="coord-avatar"><i class="fas fa-map-pin"></i></div>
            <div class="coord-info">
                <h4>Zona Asignada: <?php echo htmlspecialchars($user_zona); ?></h4>
                <p>Estructura Territorial - <strong><?php echo htmlspecialchars($estado_actual); ?></strong></p>
            </div>
        </div>

        <div class="tabs-container">
            <button class="tab-btn active" data-tab="cronograma" onclick="showTab('cronograma')">Mi Agenda</button>
            <button class="tab-btn" data-tab="metas" onclick="showTab('metas')">Mis Metas</button>
            <button class="tab-btn" data-tab="diagnostico" onclick="showTab('diagnostico')">Diagnóstico Local</button>
            <button class="tab-btn" data-tab="objetivos" onclick="showTab('objetivos')">Alineación Estatal</button>
        </div>

        <div id="tab-cronograma" class="tab-content active">
            <h3 style="margin:0; color: var(--dark); font-size: 18px;"><i class="fas fa-calendar-alt"></i> Tareas y Eventos de Zona</h3>
            <?php if (empty($data['cronograma'])): ?>
                <div class="content-card" style="text-align: center; color: #999; margin-top: 15px;">No hay acciones registradas en tu cronograma local.</div>
            <?php else: ?>
                <div class="table-actions-container">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Fecha Programada</th>
                                <th style="width: 30%;">Acción Táctica</th>
                                <th style="width: 50%;">Instrucciones / Detalles</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['cronograma'] as $crono): ?>
                                <tr>
                                    <td><span class="date-badge"><?php echo htmlspecialchars($crono['fecha_visual'] ?? 'Sin fecha'); ?></span></td>
                                    <td><div style="font-weight: 700; color: #333;"><?php echo htmlspecialchars($crono['titulo']); ?></div></td>
                                    <td><div style="font-size: 13px; color: #666;"><?php echo htmlspecialchars($crono['descripcion']); ?></div></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-metas" class="tab-content">
            <h3 style="margin-bottom: 15px; color: var(--dark); font-size: 18px;"><i class="fas fa-crosshairs"></i> Metas Operativas de Zona</h3>
            <?php if (empty($data['metas'])): ?>
                <div class="content-card" style="text-align: center; color: #999;">Aún no se te han asignado metas operativas locales.</div>
            <?php else: ?>
                <div class="grid-3">
                    <?php $contador = 1; foreach ($data['metas'] as $meta): ?>
                        <div class="content-card" style="border-left: 4px solid var(--primary); padding: 15px 20px; min-height: 80px; display: flex; align-items: center;">
                            <p style="font-weight: 600; font-size: 14px; margin: 0; color: #1a1a1a; line-height: 1.4; width: 100%;">
                                <span style="color:var(--primary); font-weight:800; display:block; margin-bottom:5px; font-size:11px;">META LOCAL #<?php echo $contador; ?></span>
                                <?php echo nl2br(htmlspecialchars($meta['titulo'])); ?>
                            </p>
                        </div>
                    <?php $contador++; endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-diagnostico" class="tab-content">
            <h3 style="margin-bottom: 15px; color: var(--dark); font-size: 18px;"><i class="fas fa-file-signature"></i> Diagnóstico de la Zona</h3>
            
            <?php if (empty($data['diagnostico'])): ?>
                <div class="content-card" style="text-align: center; color: #999;">El diagnóstico de tu zona aún no ha sido completado.</div>
            <?php else: ?>
                <div class="content-card">
                    <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--primary);">Ficha Identificativa</h4>
                    <div class="grid-3">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: bold;">Zona de Responsabilidad</label>
                            <div class="readonly-val"><?php echo htmlspecialchars($user_zona); ?></div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: bold;">Responsable</label>
                            <div class="readonly-val"><?php echo htmlspecialchars($data['diagnostico']['responsable'] ?? '—'); ?></div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: bold;">Cargo</label>
                            <div class="readonly-val"><?php echo htmlspecialchars($data['diagnostico']['cargo'] ?? '—'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--primary);">Estructura Local</h4>
                    <div class="grid-2">
                        <div><label style="font-size: 12px; font-weight: bold;">Municipios correspondientes:</label> <div class="readonly-val" style="min-height: 35px;"><?php echo number_format($data['diagnostico']['mun_totales'] ?? 0); ?></div></div>
                        <div><label style="font-size: 12px; font-weight: bold;">Municipios cubiertos:</label> <div class="readonly-val" style="min-height: 35px;"><?php echo number_format($data['diagnostico']['mun_presencia'] ?? 0); ?></div></div>
                        <div><label style="font-size: 12px; font-weight: bold;">Afiliados activos (Zona):</label> <div class="readonly-val" style="min-height: 35px;"><?php echo number_format($data['diagnostico']['afil_activos'] ?? 0); ?></div></div>
                        <div><label style="font-size: 12px; font-weight: bold;">Promotores en campo:</label> <div class="readonly-val" style="min-height: 35px;"><?php echo number_format($data['diagnostico']['promo_confirmados'] ?? 0); ?></div></div>
                    </div>
                </div>

                <div class="content-card">
                    <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--primary);">Análisis FODA Local</h4>
                    <div class="grid-2">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: bold;">Fortalezas de la Zona:</label>
                            <div class="readonly-area"><?php echo nl2br(htmlspecialchars($data['diagnostico']['foda_fortalezas'] ?? '—')); ?></div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: bold;">Debilidades en la Zona:</label>
                            <div class="readonly-area"><?php echo nl2br(htmlspecialchars($data['diagnostico']['foda_debilidades'] ?? '—')); ?></div>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--primary);">Documentos de la Zona</h4>
                    <?php if(!empty($data['archivos'])): ?>
                        <div style="overflow-x: auto; border-radius: 8px; border: 1px solid #eee;">
                            <table class="custom-table" style="font-size: 13px; margin: 0;">
                                <thead style="background: #f4f6f8;">
                                    <tr>
                                        <th style="padding: 15px;">Documento</th>
                                        <th style="padding: 15px; text-align: center;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($data['archivos'] as $archivo): ?>
                                        <tr>
                                            <td style="padding: 15px;">
                                                <i class="fas fa-file-pdf" style="color: #d32f2f; margin-right: 8px;"></i> 
                                                <?php echo htmlspecialchars($archivo['nombre_original']); ?>
                                            </td>
                                            <td style="padding: 15px; text-align: center;">
                                                <a href="<?php echo htmlspecialchars($archivo['ruta_archivo']); ?>" target="_blank" style="color: var(--primary); text-decoration: none; font-weight: bold; background: #e3f2fd; padding: 6px 12px; border-radius: 6px;">
                                                    <i class="fas fa-download"></i> Descargar
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color: #999; font-size: 13px; text-align: center;">No hay documentos adjuntos para esta zona.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-objetivos" class="tab-content">
            <h3 style="margin-bottom: 15px; color: var(--dark); font-size: 18px;"><i class="fas fa-landmark"></i> Alineación: Estrategia Estatal</h3>
            <p style="font-size: 14px; color: #666; margin-bottom: 20px;">Estos son los lineamientos macros definidos por el estado. Tu labor en la zona ayuda a cumplir estos objetivos.</p>
            
            <div class="grid-2">
                <div class="content-card">
                    <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--dark);"><i class="fas fa-eye"></i> Visión</h4>
                    <p style="color: #666; font-size: 14px; line-height: 1.6; white-space: pre-line;">
                        <?php echo nl2br(htmlspecialchars($data['vision_mision']['vision'])); ?>
                    </p>
                </div>
                <div class="content-card">
                    <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--dark);"><i class="fas fa-bullseye"></i> Misión</h4>
                    <p style="color: #666; font-size: 14px; line-height: 1.6; white-space: pre-line;">
                        <?php echo nl2br(htmlspecialchars($data['vision_mision']['mision'])); ?>
                    </p>
                </div>
            </div>

            <h4 style="margin-top: 20px; margin-bottom: 15px; color: var(--dark);">Objetivos Generales</h4>
            <?php if (empty($data['objetivos'])): ?>
                <div class="content-card" style="text-align: center; color: #999;">Aún no se han publicado objetivos para este estado.</div>
            <?php else: ?>
                <div class="grid-2">
                    <?php foreach ($data['objetivos'] as $obj): ?>
                        <div class="content-card">
                            <span class="card-tag" style="background: #2d7a3e;">Estatal</span>
                            <h4 style="font-weight: 800; margin-bottom: 5px;"><?php echo htmlspecialchars($obj['titulo']); ?></h4>
                            <p style="font-size: 14px; color: #666;"><?php echo htmlspecialchars($obj['descripcion']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
    
    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            
            const activeContent = document.getElementById('tab-' + tabName);
            if(activeContent) activeContent.classList.add('active');
            
            const activeBtn = document.querySelector(`.tab-btn[data-tab="${tabName}"]`);
            if(activeBtn) activeBtn.classList.add('active');
        }
    </script>
</body>
</html>