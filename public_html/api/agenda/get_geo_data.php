<?php
// api/agenda/get_geo_data.php

// 1. Configuración de cabeceras para responder siempre en formato JSON
header('Content-Type: application/json; charset=utf-8');

// Evitar que errores o advertencias de PHP ensucien la respuesta JSON
error_reporting(0);
ini_set('display_errors', 0);
ob_start(); // Iniciamos el buffer de salida para atrapar cualquier texto basura

try {
    // 2. Intentar cargar el bootstrap o iniciar sesión
    if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
        require_once __DIR__ . '/../../includes/bootstrap.php';
    } else {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // 3. Bloqueo de seguridad básico
    if (!isset($_SESSION['user_id'])) {
        ob_clean();
        echo json_encode([]); // Si no hay sesión, devolvemos un arreglo vacío
        exit;
    }

    // 4. Conexión a la base de datos (Usando el método de tu sistema)
    $dbConnection = Database::getInstance()->getConnection();

    $type = $_GET['type'] ?? '';
    $data = [];

    // ── OBTENER MUNICIPIOS DE UN ESTADO (Solución Estable) ────────
    if ($type === 'municipios') {
        $estado = trim($_GET['estado'] ?? '');
        
        $stmt = $dbConnection->prepare("
            SELECT DISTINCT municipio as nombre 
            FROM secciones_electorales 
            WHERE TRIM(estado) = ? AND activo = 1 
            ORDER BY nombre ASC
        ");
        $stmt->execute([$estado]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formateamos para devolver un arreglo plano: ["Hermosillo", "Cajeme"]
        $data = array_column($result, 'nombre');
    } 
    // ── OBTENER SECCIONES DE UN MUNICIPIO Y ESTADO ─────────────────
    elseif ($type === 'secciones') {
        $municipio = trim($_GET['municipio'] ?? '');
        $estado = trim($_GET['estado'] ?? '');
        
        $stmt = $dbConnection->prepare("
            SELECT nombre_seccion 
            FROM secciones_electorales 
            WHERE TRIM(municipio) = ? AND TRIM(estado) = ? AND activo = 1 
            ORDER BY CAST(nombre_seccion AS UNSIGNED) ASC
        ");
        $stmt->execute([$municipio, $estado]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formateamos para devolver un arreglo plano: ["0001", "0002"]
        $data = array_column($result, 'nombre_seccion');
    }
    // ── OBTENER REGIONES (Mantenido por compatibilidad) ────────────
    elseif ($type === 'regiones') {
        $estado = trim($_GET['estado'] ?? '');
        
        $stmt = $dbConnection->prepare("
            SELECT DISTINCT region_asignada as nombre 
            FROM secciones_electorales 
            WHERE TRIM(estado) = ? AND activo = 1 
            ORDER BY nombre ASC
        ");
        $stmt->execute([$estado]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // ── OBTENER ZONAS (Mantenido por compatibilidad) ───────────────
    elseif ($type === 'zonas') {
        $region = trim($_GET['region'] ?? '');
        
        $stmt = $dbConnection->prepare("
            SELECT DISTINCT zona_asignada as nombre 
            FROM secciones_electorales 
            WHERE TRIM(region_asignada) = ? AND activo = 1 
            ORDER BY nombre ASC
        ");
        $stmt->execute([$region]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 

    // 5. Imprimir la respuesta asegurando que sea JSON válido
    ob_clean(); // Limpia cualquier espacio en blanco o caracter residual previo
    echo json_encode($data);

} catch (Exception $e) {
    http_response_code(500);
    ob_clean();
    echo json_encode(['error' => 'Error de servidor', 'detalle' => $e->getMessage()]);
}