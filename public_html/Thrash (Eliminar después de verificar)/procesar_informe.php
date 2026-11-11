<?php
require_once 'db.php'; // Asegúrate de que esta ruta sea correcta

// Solo enviamos encabezado JSON si no vamos a servir un archivo HTML para impresión
if (!isset($_GET['action']) || $_GET['action'] !== 'generar_pdf') {
    header('Content-Type: application/json');
}

$db = getDB();
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

try {
    switch ($action) {
        case 'crear_informe':
            // 1. CREAR INFORME MANUALMENTE
            $titulo = trim($_POST['title']);
            $tipo = trim($_POST['type']);
            $estado = trim($_POST['status']);
            $autor = trim($_POST['author']);
            $contenido = $_POST['content']; 

            if (empty($titulo) || empty($tipo) || empty($estado) || empty($autor) || empty($contenido)) {
                echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos.']);
                exit;
            }

            $stmt = $db->prepare("INSERT INTO informes (titulo, creado_por, fecha_creacion, tipo, estado, contenido, archivo_url) 
                                 VALUES (:titulo, :autor, CURDATE(), :tipo, :estado, :contenido, NULL)");
            
            $stmt->bindParam(':titulo', $titulo);
            $stmt->bindParam(':autor', $autor);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':contenido', $contenido);
            $stmt->execute();

            $lastId = $db->lastInsertId();

            echo json_encode(['success' => true, 'message' => 'Informe creado con éxito.', 'id' => $lastId, 'date' => date('d/M/Y')]);
            break;
            
        case 'actualizar_informe':
            // 2. ACTUALIZAR INFORME
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $titulo = trim($_POST['title']);
            $tipo = trim($_POST['type']);
            $estado = trim($_POST['status']);
            $contenido = $_POST['content']; 
            $autor = trim($_POST['author']); 

            if ($id <= 0 || empty($titulo) || empty($tipo) || empty($estado) || empty($autor) || empty($contenido)) {
                echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos o ID no válido.']);
                exit;
            }

            $stmt = $db->prepare("UPDATE informes SET titulo = :titulo, tipo = :tipo, estado = :estado, contenido = :contenido, creado_por = :autor WHERE id = :id AND archivo_url IS NULL");
            
            $stmt->bindParam(':titulo', $titulo);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':contenido', $contenido);
            $stmt->bindParam(':autor', $autor);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Informe actualizado con éxito.', 'id' => $id, 'date' => date('d/M/Y')]);
            } else {
                echo json_encode(['success' => false, 'message' => 'El informe no pudo ser actualizado. Puede que no exista o que sea un PDF (no editable).']);
            }
            break;

        case 'subir_pdf':
            // 3. SUBIR INFORME PDF
            if (!isset($_FILES['pdfFile'])) {
                echo json_encode(['success' => false, 'message' => 'No se ha subido ningún archivo.']);
                exit;
            }

            $pdfFile = $_FILES['pdfFile'];
            $tituloPdf = trim($_POST['pdfTitle']);
            $tipoPdf = trim($_POST['pdfType']);
            $estadoPdf = trim($_POST['pdfStatus']);
            $autorPdf = trim($_POST['pdfAuthor']);
            
            // 🔴 CORRECCIÓN: Ajuste de la ruta a 'informes/' ya que el script está en la raíz.
            $uploadDir = 'informes/'; 

            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    error_log("Fallo al intentar crear el directorio de subida: " . $uploadDir);
                    echo json_encode(['success' => false, 'message' => 'Fallo al crear el directorio de subida. Verifique permisos de carpeta padre.']);
                    exit;
                }
            }

            if (empty($tituloPdf) || empty($tipoPdf) || empty($estadoPdf) || empty($autorPdf)) {
                 echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos para el PDF.']);
                 exit;
            }
            
            if ($pdfFile['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'Error al subir el archivo: ' . $pdfFile['error']]);
                exit;
            }

            $fileExtension = pathinfo($pdfFile['name'], PATHINFO_EXTENSION);
            if (strtolower($fileExtension) !== 'pdf') {
                echo json_encode(['success' => false, 'message' => 'Solo se permiten archivos PDF.']);
                exit;
            }

            $fileName = uniqid('informe_') . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;
            
            // La URL pública sigue siendo '/informes/...'
            $publicUrl = '/informes/' . $fileName; 

            if (move_uploaded_file($pdfFile['tmp_name'], $filePath)) {
                $stmt = $db->prepare("INSERT INTO informes (titulo, creado_por, fecha_creacion, tipo, estado, contenido, archivo_url) 
                                     VALUES (:titulo, :autor, CURDATE(), :tipo, :estado, NULL, :archivo_url)");
                
                $stmt->bindParam(':titulo', $tituloPdf);
                $stmt->bindParam(':autor', $autorPdf);
                $stmt->bindParam(':tipo', $tipoPdf);
                $stmt->bindParam(':estado', $estadoPdf);
                $stmt->bindParam(':archivo_url', $publicUrl);
                $stmt->execute();
                
                $lastId = $db->lastInsertId();

                echo json_encode(['success' => true, 'message' => 'PDF subido y registrado con éxito.', 'id' => $lastId, 'date' => date('d/M/Y'), 'url' => $publicUrl]);
            } else {
                error_log("Fallo al mover archivo subido. Ruta: " . $filePath . ". Comprobar permisos.");
                echo json_encode(['success' => false, 'message' => 'Fallo al mover el archivo subido. Verifique permisos de carpeta.']);
            }
            break;
            
        case 'obtener_informe_por_id':
            // 4. OBTENER UN INFORME
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de informe no válido.']);
                exit;
            }

            $stmt = $db->prepare("SELECT id, titulo, creado_por, tipo, estado, contenido, archivo_url FROM informes WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $report = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($report) {
                if ($report['archivo_url'] !== NULL) {
                    echo json_encode(['success' => false, 'message' => 'Este informe es un archivo PDF, no tiene contenido editable.', 'is_pdf' => true]);
                    exit;
                }
                echo json_encode(['success' => true, 'report' => $report]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Informe no encontrado.']);
            }
            break;
            
        case 'obtener_informes':
            // 5. LISTAR INFORMES
            $stmt = $db->query("SELECT id, titulo, creado_por, DATE_FORMAT(fecha_creacion, '%d/%b/%Y') AS fecha_creacion, tipo, estado, archivo_url FROM informes ORDER BY id DESC");
            $informes = $stmt->fetchAll();

            echo json_encode(['success' => true, 'informes' => $informes]);
            break;
            
        case 'generar_pdf':
            // CORRECCIÓN 2: Generar una vista HTML imprimible
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            
            if ($id <= 0) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'ID de informe no válido.']);
                exit;
            }
            
            $stmt = $db->prepare("SELECT titulo, contenido, archivo_url, creado_por, fecha_creacion, tipo FROM informes WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $report = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$report) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Informe no encontrado.']);
                exit;
            }
            
            // Si ya es un PDF subido, redirigimos
            if ($report['archivo_url']) {
                header('Location: ' . $report['archivo_url']);
                exit;
            }
            
            // Generar vista HTML limpia para imprimir como PDF
            header('Content-Type: text/html; charset=utf-8');
            ?>
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <title>Informe: <?php echo htmlspecialchars($report['titulo']); ?></title>
                <style>
                    body { font-family: 'Arial', sans-serif; padding: 40px; color: #333; line-height: 1.6; }
                    .header { border-bottom: 2px solid #2d7a3e; padding-bottom: 20px; margin-bottom: 30px; }
                    .header h1 { margin: 0; color: #1a1a1a; font-size: 28px; }
                    .meta { margin-top: 10px; color: #666; font-size: 14px; }
                    .meta span { margin-right: 20px; }
                    .content { font-size: 14px; }
                    .footer { margin-top: 50px; font-size: 12px; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 20px; }
                    @media print {
                        body { padding: 0; }
                        .no-print { display: none; }
                    }
                </style>
            </head>
            <body> <div class="header">
                    <h1><?php echo htmlspecialchars($report['titulo']); ?></h1>
                    <div class="meta">
                        <span><strong>Autor:</strong> <?php echo htmlspecialchars($report['creado_por']); ?></span>
                        <span><strong>Tipo:</strong> <?php echo htmlspecialchars($report['tipo']); ?></span>
                        <span><strong>Fecha:</strong> <?php echo htmlspecialchars($report['fecha_creacion']); ?></span>
                    </div>
                </div>
                
                <div class="content">
                    <?php echo $report['contenido']; ?>
                </div>

                <div class="footer">
                    Generado por Sistema de Gestión - Proyecto Verde
                </div>

                <script>
                    // Abrir diálogo de impresión automáticamente
                    window.onload = function() {
                        setTimeout(function(){ window.print(); }, 500);
                    };
                </script>
            </body>
            </html>
            <?php
            exit; 
            
        case 'eliminar_informe':
            // 7. ELIMINAR INFORME
            $id = (int)$_POST['id'];
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID de informe no válido.']);
                exit;
            }

            $stmtFile = $db->prepare("SELECT archivo_url FROM informes WHERE id = :id");
            $stmtFile->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtFile->execute();
            $fileResult = $stmtFile->fetch();

            if ($fileResult && $fileResult['archivo_url']) {
                // CORRECCIÓN DE RUTA: Eliminar el archivo físico. Asumimos que la URL pública es /informes/nombre.pdf
                // y el script está en la raíz, por lo que usamos la ruta relativa 'informes/nombre.pdf'
                $filePath = ltrim($fileResult['archivo_url'], '/'); 
                
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $stmt = $db->prepare("DELETE FROM informes WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                 echo json_encode(['success' => true, 'message' => 'Informe eliminado correctamente.']);
            } else {
                 echo json_encode(['success' => false, 'message' => 'Informe no encontrado.']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
            break;
    }
} catch (PDOException $e) {
    error_log("Error de DB en procesar_informe: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos.', 'detail' => $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error general en procesar_informe: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor.', 'detail' => $e->getMessage()]);
}
?>