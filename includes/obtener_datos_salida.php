<?php
// =====================================================
// OBTENER DATOS DE SALIDA PARA CACHE OFFLINE
// =====================================================

include_once 'functions.php';
verificarSesion('trabajador');

header('Content-Type: application/json');

$salida_id = $_GET['salida_id'] ?? 0;
$usuario_id = $_SESSION['usuario_id'];

if (!$salida_id) {
    echo json_encode(['success' => false, 'error' => 'ID de salida no válido']);
    exit();
}

// Verificar acceso
if (!validarAccesoRuta($usuario_id, $salida_id)) {
    echo json_encode(['success' => false, 'error' => 'Sin acceso a esta salida']);
    exit();
}

try {
    // Obtener información de la salida
    $sql_salida = "SELECT s.*, r.nombre as ruta_nombre 
                   FROM salidas_mercancia s 
                   JOIN rutas r ON s.ruta_id = r.id 
                   WHERE s.id = ?";
    $salida = obtenerRegistro($sql_salida, [$salida_id]);
    
    // Obtener productos disponibles con cantidades
    $sql_productos = "SELECT 
        p.id,
        p.codigo,
        p.descripcion,
        p.unidad_medida,
        p.precio_publico,
        p.iva,
        ds.cantidad as cantidad_disponible,
        ds.cantidad - COALESCE(
            (SELECT SUM(df.cantidad) 
             FROM detalle_facturas df 
             JOIN facturas f ON df.factura_id = f.id 
             WHERE f.salida_id = ? AND df.producto_id = p.id), 0
        ) as cantidad_restante
    FROM productos p
    JOIN detalle_salidas ds ON p.id = ds.producto_id
    WHERE ds.salida_id = ? AND p.activo = 1
    ORDER BY p.descripcion";
    
    $productos = obtenerRegistros($sql_productos, [$salida_id, $salida_id]);
    
    // Obtener clientes de la ruta
    $sql_clientes = "SELECT c.* 
                     FROM clientes c 
                     JOIN rutas r ON c.ruta_id = r.id 
                     JOIN salidas_mercancia s ON r.id = s.ruta_id 
                     WHERE s.id = ? AND c.activo = 1 
                     ORDER BY c.nombre";
    $clientes = obtenerRegistros($sql_clientes, [$salida_id]);
    
    // Preparar respuesta
    $respuesta = [
        'success' => true,
        'salida' => [
            'id' => $salida['id'],
            'ruta_nombre' => $salida['ruta_nombre'],
            'fecha_salida' => $salida['fecha_salida'],
            'estado' => $salida['estado']
        ],
        'productos' => $productos,
        'clientes' => $clientes,
        'fecha_cache' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($respuesta);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener datos: ' . $e->getMessage()
    ]);
}
?>