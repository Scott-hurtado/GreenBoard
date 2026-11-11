<?php
// api/encuestas.php

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin', 'administrador', 'superadmin', 'super admin'];

if (!in_array($rol, $permitidos)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Conexión a la base de datos
try {
    $dbConfigPath = __DIR__ . '/../includes/database.php';
    if (file_exists($dbConfigPath)) {
        $dbConfig = require $dbConfigPath;
    } else {
        $dbConfig = [
            'host' => 'localhost',
            'database' => 'u881824383_pverde',
            'username' => 'u881824383_proyverde',
            'password' => 'PProyectoverde8#',
            'charset' => 'utf8mb4',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        ];
    }

    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión: ' . $e->getMessage()]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        handleGet($pdo);
        break;
    case 'POST':
        handlePost($pdo);
        break;
    case 'DELETE':
        handleDelete($pdo);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        break;
}

function handleGet($pdo) {
    if(isset($_GET['id'])) {
        // Obtener una encuesta específica con sus preguntas
        $id = $_GET['id'];
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM encuestas WHERE id = ?");
            $stmt->execute([$id]);
            $encuesta = $stmt->fetch();

            if(!$encuesta) {
                echo json_encode(['success' => false, 'message' => 'Encuesta no encontrada']);
                return;
            }

            // Obtener preguntas
            $stmt = $pdo->prepare("SELECT * FROM preguntas WHERE id_encuesta = ? ORDER BY orden ASC");
            $stmt->execute([$id]);
            $preguntas = $stmt->fetchAll();

            // Para cada pregunta, obtener opciones si es de tipo múltiple
            foreach($preguntas as &$pregunta) {
                if($pregunta['tipo'] === 'multiple') {
                    $stmt = $pdo->prepare("SELECT opcion FROM opciones WHERE id_pregunta = ? ORDER BY orden ASC");
                    $stmt->execute([$pregunta['id']]);
                    $opciones = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $pregunta['opciones'] = $opciones;
                } else {
                    $pregunta['opciones'] = [];
                }
            }

            $encuesta['preguntas'] = $preguntas;

            echo json_encode(['success' => true, 'encuesta' => $encuesta]);

        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    } else {
        // Obtener todas las encuestas
        try {
            $stmt = $pdo->query("SELECT * FROM encuestas ORDER BY fecha_creacion DESC");
            $encuestas = $stmt->fetchAll();

            echo json_encode(['success' => true, 'encuestas' => $encuestas]);

        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
}

function handlePost($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);

    if(!$input) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        return;
    }

    $id = $input['id'] ?? null;
    $titulo = $input['titulo'] ?? '';
    $descripcion = $input['descripcion'] ?? '';
    $preguntas = $input['preguntas'] ?? [];

    if(empty($titulo)) {
        echo json_encode(['success' => false, 'message' => 'El título es requerido']);
        return;
    }

    if(empty($preguntas)) {
        echo json_encode(['success' => false, 'message' => 'Debes agregar al menos una pregunta']);
        return;
    }

    try {
        $pdo->beginTransaction();

        if($id) {
            // Actualizar encuesta existente
            $stmt = $pdo->prepare("UPDATE encuestas SET titulo = ?, descripcion = ?, fecha_actualizacion = NOW() WHERE id = ?");
            $stmt->execute([$titulo, $descripcion, $id]);

            // Eliminar preguntas y opciones anteriores
            $stmt = $pdo->prepare("DELETE FROM opciones WHERE id_pregunta IN (SELECT id FROM preguntas WHERE id_encuesta = ?)");
            $stmt->execute([$id]);

            $stmt = $pdo->prepare("DELETE FROM preguntas WHERE id_encuesta = ?");
            $stmt->execute([$id]);

            $encuestaId = $id;
        } else {
            // Crear nueva encuesta
            $stmt = $pdo->prepare("INSERT INTO encuestas (titulo, descripcion, creado_por) VALUES (?, ?, ?)");
            $stmt->execute([$titulo, $descripcion, $_SESSION['user_id'] ?? 1]);
            $encuestaId = $pdo->lastInsertId();
        }

        // Insertar preguntas
        foreach($preguntas as $pregunta) {
            $stmt = $pdo->prepare("INSERT INTO preguntas (id_encuesta, pregunta, tipo, orden) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $encuestaId,
                $pregunta['pregunta'],
                $pregunta['tipo'],
                $pregunta['orden']
            ]);

            $preguntaId = $pdo->lastInsertId();

            // Si es de opción múltiple, insertar opciones
            if($pregunta['tipo'] === 'multiple' && !empty($pregunta['opciones'])) {
                foreach($pregunta['opciones'] as $index => $opcion) {
                    $stmt = $pdo->prepare("INSERT INTO opciones (id_pregunta, opcion, orden) VALUES (?, ?, ?)");
                    $stmt->execute([$preguntaId, $opcion, $index + 1]);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Encuesta guardada exitosamente', 'id' => $encuestaId]);

    } catch(PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
    }
}

function handleDelete($pdo) {
    $id = $_GET['id'] ?? null;

    if(!$id) {
        echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
        return;
    }

    try {
        $pdo->beginTransaction();

        // Eliminar opciones
        $stmt = $pdo->prepare("DELETE FROM opciones WHERE id_pregunta IN (SELECT id FROM preguntas WHERE id_encuesta = ?)");
        $stmt->execute([$id]);

        // Eliminar preguntas
        $stmt = $pdo->prepare("DELETE FROM preguntas WHERE id_encuesta = ?");
        $stmt->execute([$id]);

        // Eliminar encuesta
        $stmt = $pdo->prepare("DELETE FROM encuestas WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Encuesta eliminada exitosamente']);

    } catch(PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
    }
}
?>