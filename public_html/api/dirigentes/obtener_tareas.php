<?php
// Archivo: /public_html/api/dirigentes/obtener_tareas.php

// 1. Configuración de errores (Desactivar en producción)
ini_set('display_errors', 0); 
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// 2. Carga del sistema
require_once __DIR__ . '/../../includes/bootstrap.php';

// Cabecera JSON
header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getInstance()->getConnection();
    
    // Si viene 'estado', filtramos. Si no viene (caso Admin dashboard global), mostramos todo o manejamos error según tu lógica.
    // Aquí asumo que si no hay estado, devolvemos array vacío o todo. Ajusta según necesites.
    $estado = $_GET['estado'] ?? null; 

    if (!$estado) {
        // Opción A: Devolver todo si eres SuperAdmin
        // Opción B: Lanzar error. Usaremos Opción A simplificada o vacío.
        // throw new Exception("Estado no especificado");
    }
    
    // --- SQL MAESTRO CORREGIDO ---
    // Clave: El JOIN se hace contra banco_actividades (ba) para sacar el nombre del creador original
    // y no contra dirigentes_actividades (da) que solo tiene al asignador.
    
    $sql = "SELECT 
                -- 1. Datos de la Ejecución (Instancia Local)
                da.id, 
                da.estatus,
                da.impacto_estimado,
                da.mes_objetivo,
                da.categoria,
                da.comentarios,
                da.fecha_asignacion,
                
                -- Archivo de EVIDENCIA (Subido por Coordinador en Pág 4)
                COALESCE(da.reporte_adjunto, da.reporte_pdf) as reporte_adjunto,
                da.fecha_reporte,

                -- 2. Datos de la Definición (Banco Global)
                ba.id AS id_actividad_banco,
                ba.codigo_referencia,
                COALESCE(ba.titulo, 'Actividad sin título') as nombre_actividad,
                ba.descripcion,
                
                -- Archivo de GUÍA/BASE (Subido por Creador en Pág 1 o 3)
                ba.documento_adjunto as guia_pdf,

                -- 3. LÓGICA DE ORIGEN (AUTORÍA)
                -- Aquí determinamos quién inventó la actividad, no quién la asignó.
                ba.creado_por_rol,
                
                CASE 
                    -- Caso 1: Creado por un Administrador (buscamos en tabla administradores)
                    WHEN ba.creado_por_rol LIKE '%admin%' OR ba.creado_por_rol = 'superadmin' THEN 
                        COALESCE(adm.nombre_completo, 'Administración Central')
                    
                    -- Caso 2: Creado por un Coordinador (buscamos en tabla coordinadores)
                    WHEN ba.creado_por_rol = 'coordinador_estatal' THEN 
                        CONCAT(COALESCE(coord.nombre, ''), ' ', COALESCE(coord.ap_paterno, ''))
                    
                    -- Fallback
                    ELSE 'Sistema'
                END AS creado_por_nombre,

                -- 4. Bandera para el Frontend (Badge Azul o Verde)
                CASE 
                    WHEN ba.creado_por_rol LIKE '%admin%' OR ba.creado_por_rol = 'superadmin' THEN 'admin'
                    ELSE 'user'
                END AS tipo_origen

            FROM dirigentes_actividades da
            
            -- UNIÓN OBLIGATORIA CON EL BANCO
            INNER JOIN banco_actividades ba ON da.id_actividad_banco = ba.id
            
            -- UNIÓN CONDICIONAL 1: Si fue Admin
            LEFT JOIN administradores adm ON ba.creado_por_id = adm.id 
                AND (ba.creado_por_rol LIKE '%admin%' OR ba.creado_por_rol = 'superadmin')
            
            -- UNIÓN CONDICIONAL 2: Si fue Coordinador
            LEFT JOIN coordinador_estatal coord ON ba.creado_por_id = coord.id 
                AND (ba.creado_por_rol = 'coordinador_estatal')
            
            WHERE 1=1";

    $params = [];

    // Si se especificó estado, filtramos (Vista Coordinador Pág 4 y Admin Pág 2)
    if ($estado) {
        $sql .= " AND da.estado = ?";
        $params[] = $estado;
    }

    $sql .= " ORDER BY da.fecha_asignacion DESC, da.id DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalizar nulls en nombres si concat falló
    foreach ($tareas as &$t) {
        if (trim($t['creado_por_nombre']) === '') {
            $t['creado_por_nombre'] = 'Usuario Desconocido';
        }
    }

    echo json_encode($tareas ?: []);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true, 
        'message' => $e->getMessage()
    ]);
}
?>