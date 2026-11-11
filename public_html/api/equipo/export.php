<?php
try {
    // 1. Cargar bootstrap
    require_once __DIR__ . '/../../includes/bootstrap.php';

    // 2. Verificar autenticación y permisos
    $rol = $_SESSION['user_role'] ?? '';
    $permitidos = ['admin', 'administrador', 'superadmin', 'super admin'];

    if (!in_array($rol, $permitidos)) {
        die('No tienes permisos para realizar esta acción');
    }

    // 3. Obtener conexión
    $db = Database::getInstance()->getConnection();

    // 4. Obtener todos los usuarios
    $usuarios = [];
    $tablas = [
        'coordinador_estatal',
        'coordinador_municipal', 
        'coordinador_distrital', 
        'responsable_zona', 
        'promotor'
    ];
    
    foreach ($tablas as $tabla) {
        $sql = "SELECT id, nombre, telefono, email, colonia, calle, seccion_electoral, estatus, 
                       fecha_registro, '$tabla' as rol 
                FROM $tabla 
                ORDER BY fecha_registro DESC";
        $stmt = $db->query($sql);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $usuarios = array_merge($usuarios, $resultados);
    }

    // 5. Configurar headers para descarga CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="equipo_' . date('Y-m-d_H-i-s') . '.csv"');

    // 6. Crear el archivo CSV
    $output = fopen('php://output', 'w');

    // BOM para UTF-8 (para que Excel reconozca bien los acentos)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // 7. Encabezados del CSV
    fputcsv($output, [
        'ID',
        'Nombre',
        'Rol',
        'Teléfono',
        'Email',
        'Colonia',
        'Calle',
        'Sección Electoral',
        'Estatus',
        'Fecha Registro'
    ]);

    // 8. Mapeo de nombres de roles para display
    $rolesDisplay = [
        'coordinador_estatal' => 'Coordinador Estatal',
        'coordinador_municipal' => 'Coordinador Municipal',
        'coordinador_distrital' => 'Coordinador Distrital',
        'responsable_zona' => 'Responsable de Zona',
        'promotor' => 'Promotor'
    ];

    // 9. Datos
    foreach ($usuarios as $usuario) {
        $rolDisplay = $rolesDisplay[$usuario['rol']] ?? ucfirst(str_replace('_', ' ', $usuario['rol']));
        
        fputcsv($output, [
            $usuario['id'],
            $usuario['nombre'],
            $rolDisplay,
            $usuario['telefono'],
            $usuario['email'],
            $usuario['colonia'],
            $usuario['calle'],
            $usuario['seccion_electoral'],
            ucfirst($usuario['estatus']),
            date('Y-m-d H:i:s', strtotime($usuario['fecha_registro']))
        ]);
    }

    fclose($output);
    exit();

} catch (Exception $e) {
    die('Error al generar el archivo CSV: ' . $e->getMessage());
}