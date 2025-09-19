<?php
// =====================================================
// BÚSQUEDA DE PRODUCTOS - SISTEMA METRU
// =====================================================


// Limpiar cualquier salida previa
ob_start();

// Incluir archivos necesarios usando rutas absolutas
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

// Limpiar buffer
ob_clean();

// Configurar headers JSON
header('Content-Type: application/json');

// Validar parámetros
$termino = $_POST['termino'] ?? '';

if (strlen($termino) < 2) {
    echo json_encode([]);
    exit;
}

try {
    // Buscar productos
    $sql = "SELECT 
        id,
        codigo,
        descripcion,
        unidad_medida,
        precio_publico,
        grupo_id
    FROM productos 
    WHERE activo = 1 
    AND (descripcion LIKE ? OR codigo LIKE ?)
    ORDER BY 
        CASE 
            WHEN descripcion LIKE ? THEN 1
            WHEN codigo = ? THEN 2
            ELSE 3
        END,
        descripcion
    LIMIT 15";
    
    $termino_busqueda = '%' . $termino . '%';
    $termino_inicio = $termino . '%';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $termino_busqueda, 
        $termino_busqueda,
        $termino_inicio,
        $termino
    ]);
    
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear resultados
    $resultados = array_map(function($producto) {
        return [
            'id' => intval($producto['id']),
            'codigo' => $producto['codigo'],
            'descripcion' => $producto['descripcion'],
            'unidad_medida' => $producto['unidad_medida'],
            'precio_publico' => floatval($producto['precio_publico']),
            'grupo_id' => intval($producto['grupo_id'])
        ];
    }, $productos);
    
    echo json_encode($resultados);
    
} catch (Exception $e) {
    error_log("Error en búsqueda: " . $e->getMessage());
    echo json_encode(['error' => 'Error al buscar productos']);
}

// Asegurar que no haya salida adicional
exit();
?>