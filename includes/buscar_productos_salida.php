<?php
// =====================================================
// BUSCAR PRODUCTOS EN SALIDA - SISTEMA METRU
// =====================================================

include_once 'functions.php';
verificarSesion();

header('Content-Type: application/json');

$termino = $_POST['termino'] ?? '';
$salida_id = $_POST['salida_id'] ?? 0;

if (!$termino || !$salida_id) {
    echo json_encode([]);
    exit;
}

// Buscar productos disponibles en la salida
$sql = "SELECT p.*, 
        ds.cantidad as cantidad_disponible,
        ds.cantidad - COALESCE(
            (SELECT SUM(df.cantidad) 
             FROM detalle_facturas df 
             JOIN facturas f ON df.factura_id = f.id 
             WHERE f.salida_id = ? AND df.producto_id = p.id), 0
        ) as cantidad_restante
        FROM productos p
        JOIN detalle_salidas ds ON p.id = ds.producto_id
        WHERE ds.salida_id = ? 
        AND p.activo = 1
        AND (p.descripcion LIKE ? OR p.codigo LIKE ?)
        ORDER BY p.descripcion
        LIMIT 10";

$termino_busqueda = '%' . $termino . '%';
$productos = obtenerRegistros($sql, [$salida_id, $salida_id, $termino_busqueda, $termino_busqueda]);

// Formatear respuesta
$resultado = [];
foreach ($productos as $producto) {
    $resultado[] = [
        'id' => $producto['id'],
        'codigo' => $producto['codigo'],
        'descripcion' => $producto['descripcion'],
        'precio_publico' => $producto['precio_publico'],
        'unidad_medida' => $producto['unidad_medida'],
        'cantidad_disponible' => $producto['cantidad_disponible'],
        'cantidad_restante' => $producto['cantidad_restante'] ?: 0
    ];
}

echo json_encode($resultado);
?>