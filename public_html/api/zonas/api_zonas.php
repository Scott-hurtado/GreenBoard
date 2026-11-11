<?php
// /api/zonas/api_zonas.php

// Asegúrate de que esta ruta apunte correctamente a donde tienes tu conexión PDO
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}

// Verifica que el usuario tenga sesión iniciada y permisos (opcional, pero recomendado)
session_start();
$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin','administrador','superadmin','super admin', 'coordinador_estatal'];
if (!in_array($rol, $permitidos)) { 
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No tienes permisos para realizar esta acción.']);
    exit(); 
}

header('Content-Type: application/json');
$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET: CARGAR TODOS LOS DATOS ─────────────────────────────────────────────
if ($metodo === 'GET') {
    $estado = $_GET['estado'] ?? 'Sonora';
    
    try {
        // 1. Obtener regiones del estado
        $stmt = $pdo->prepare("SELECT id, nombre, descripcion as `desc`, meta_miembros as meta FROM geo_regiones WHERE estado = ?");
        $stmt->execute([$estado]);
        $regiones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($regiones as &$r) {
            // Obtener municipios de la tabla pivot
            $stmtM = $pdo->prepare("SELECT municipio FROM region_municipios WHERE region_id = ?");
            $stmtM->execute([$r['id']]);
            $r['municipios'] = $stmtM->fetchAll(PDO::FETCH_COLUMN);
            
            // 2. Obtener zonas de esta región
            $stmtZ = $pdo->prepare("SELECT id, nombre, clave, foco, colonias, meta FROM geo_zonas WHERE region_id = ?");
            $stmtZ->execute([$r['id']]);
            $r['zonas'] = $stmtZ->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($r['zonas'] as &$z) {
                // Obtener secciones de la tabla pivot
                $stmtS = $pdo->prepare("SELECT seccion FROM zona_secciones WHERE zona_id = ?");
                $stmtS->execute([$z['id']]);
                $z['secciones'] = $stmtS->fetchAll(PDO::FETCH_COLUMN);
                
                // Mantenemos miembros en 0 temporalmente (luego lo conectarás con la tabla de afiliados/miembros)
                $z['miembros'] = 0; 
            }
        }
        
        echo json_encode($regiones);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error de base de datos al cargar: ' . $e->getMessage()]);
    }
    exit;
}

// ── POST: GUARDAR, EDITAR Y ELIMINAR ────────────────────────────────────────
if ($metodo === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $accion = $data['accion'] ?? '';
    $estado = $data['estado'] ?? 'Sonora';
    $payload = $data['datos'] ?? [];
    
    try {
        // Iniciamos la transacción para asegurar la integridad de los datos
        $pdo->beginTransaction();
        
        // ── ACCIÓN: GUARDAR REGIÓN ──
        if ($accion === 'guardar_region') {
            if (empty($payload['id'])) {
                // INSERT nueva región
                $stmt = $pdo->prepare("INSERT INTO geo_regiones (nombre, descripcion, meta_miembros, estado) VALUES (?, ?, ?, ?)");
                $stmt->execute([$payload['nombre'], $payload['desc'], $payload['meta'], $estado]);
                $region_id = $pdo->lastInsertId();
            } else {
                // UPDATE región existente
                $stmt = $pdo->prepare("UPDATE geo_regiones SET nombre = ?, descripcion = ?, meta_miembros = ? WHERE id = ?");
                $stmt->execute([$payload['nombre'], $payload['desc'], $payload['meta'], $payload['id']]);
                $region_id = $payload['id'];
                
                // Limpiamos los municipios viejos para insertar los nuevos
                $pdo->prepare("DELETE FROM region_municipios WHERE region_id = ?")->execute([$region_id]);
            }
            
            // Insertar los municipios en la tabla pivot
            $stmtM = $pdo->prepare("INSERT INTO region_municipios (region_id, municipio) VALUES (?, ?)");
            foreach ($payload['municipios'] as $muni) {
                $stmtM->execute([$region_id, $muni]);
            }
        }
        
        // ── ACCIÓN: ELIMINAR REGIÓN ──
        elseif ($accion === 'eliminar_region') {
            // Si configuraste ON DELETE CASCADE en tus tablas, esto borrará las zonas, municipios y secciones asociados automáticamente.
            $stmt = $pdo->prepare("DELETE FROM geo_regiones WHERE id = ?");
            $stmt->execute([$payload['id']]);
        }
        
        // ── ACCIÓN: GUARDAR ZONA ──
        elseif ($accion === 'guardar_zona') {
            if (empty($payload['id'])) {
                // INSERT nueva zona
                $stmt = $pdo->prepare("INSERT INTO geo_zonas (region_id, nombre, clave, foco, colonias, meta) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $payload['region_id'], $payload['nombre'], $payload['clave'], 
                    $payload['foco'], $payload['colonias'], $payload['meta']
                ]);
                $zona_id = $pdo->lastInsertId();
            } else {
                // UPDATE zona existente
                $stmt = $pdo->prepare("UPDATE geo_zonas SET nombre = ?, clave = ?, foco = ?, colonias = ?, meta = ? WHERE id = ?");
                $stmt->execute([
                    $payload['nombre'], $payload['clave'], $payload['foco'], 
                    $payload['colonias'], $payload['meta'], $payload['id']
                ]);
                $zona_id = $payload['id'];
                
                // Limpiamos secciones viejas
                $pdo->prepare("DELETE FROM zona_secciones WHERE zona_id = ?")->execute([$zona_id]);
            }
            
            // Insertar las secciones en la tabla pivot
            $stmtS = $pdo->prepare("INSERT INTO zona_secciones (zona_id, seccion) VALUES (?, ?)");
            foreach ($payload['secciones'] as $sec) {
                $stmtS->execute([$zona_id, $sec]);
            }
        }
        
        // ── ACCIÓN: ELIMINAR ZONA ──
        elseif ($accion === 'eliminar_zona') {
            $stmt = $pdo->prepare("DELETE FROM geo_zonas WHERE id = ?");
            $stmt->execute([$payload['id']]);
        }
        
        // ── ACCIÓN: GUARDAR SECCIONES (Desde el Modal de la columna 3) ──
        elseif ($accion === 'guardar_secciones') {
            $zona_id = $payload['zona_id'];
            
            // Borramos asignaciones anteriores
            $pdo->prepare("DELETE FROM zona_secciones WHERE zona_id = ?")->execute([$zona_id]);
            
            // Insertamos las nuevas selecciones
            $stmtS = $pdo->prepare("INSERT INTO zona_secciones (zona_id, seccion) VALUES (?, ?)");
            foreach ($payload['secciones'] as $sec) {
                $stmtS->execute([$zona_id, $sec]);
            }
        }
        
        // Confirmamos todos los cambios en la BD
        $pdo->commit();
        echo json_encode(['success' => true]);
        
    } catch (PDOException $e) {
        // Si algo falla, revertimos todos los cambios para no dejar basura
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Error SQL: ' . $e->getMessage()]);
    }
    exit;
}