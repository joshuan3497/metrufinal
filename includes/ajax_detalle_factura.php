<?php
// =====================================================
// AJAX DETALLE FACTURA - SISTEMA METRU
// =====================================================

include_once 'functions.php';
verificarSesion('admin');

$factura_id = $_POST['factura_id'] ?? 0;

if (!$factura_id) {
    echo '<div class="alert alert-danger">Factura no encontrada</div>';
    exit();
}

// Obtener información de la factura
$sql_factura = "SELECT 
    f.*,
    COALESCE(c.nombre, f.cliente_nombre, 'Cliente General') as cliente_nombre,
    COALESCE(f.cliente_ciudad, 'Sin ciudad') as cliente_ciudad,
    u.nombre as vendedor_nombre,
    r.nombre as ruta_nombre
FROM facturas f
LEFT JOIN clientes c ON f.cliente_id = c.id
JOIN usuarios u ON f.vendedor_id = u.id
JOIN salidas_mercancia s ON f.salida_id = s.id
JOIN rutas r ON s.ruta_id = r.id
WHERE f.id = ?";

$factura = obtenerRegistro($sql_factura, [$factura_id]);

if (!$factura) {
    echo '<div class="alert alert-danger">No se pudo cargar la factura</div>';
    exit();
}

// Obtener detalle de productos
$sql_detalle = "SELECT 
    df.*,
    p.descripcion,
    p.unidad_medida
FROM detalle_facturas df
JOIN productos p ON df.producto_id = p.id
WHERE df.factura_id = ?
ORDER BY p.descripcion";

$detalles = obtenerRegistros($sql_detalle, [$factura_id]);
?>

<div class="factura-detalle">
    <!-- Encabezado -->
    <div class="row mb-3">
        <div class="col-6">
            <strong>Factura #:</strong><br>
            <span class="h5"><?php echo htmlspecialchars($factura['numero_factura']); ?></span>
        </div>
        <div class="col-6 text-end">
            <strong>Fecha:</strong><br>
            <?php echo formatearFechaHora($factura['fecha_venta']); ?>
        </div>
    </div>
    
    <hr>
    
    <!-- Información -->
    <div class="row mb-3">
        <div class="col-md-6">
            <strong>Cliente:</strong><br>
            <?php echo htmlspecialchars($factura['cliente_nombre']); ?><br>
            <small class="text-muted"><?php echo htmlspecialchars($factura['cliente_ciudad']); ?></small>
        </div>
        <div class="col-md-6">
            <strong>Vendedor:</strong><br>
            <?php echo htmlspecialchars($factura['vendedor_nombre']); ?><br>
            <small class="text-muted">Ruta: <?php echo htmlspecialchars($factura['ruta_nombre']); ?></small>
        </div>
    </div>
    
    <!-- Productos -->
    <div class="mb-3">
        <strong>Productos:</strong>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="text-center">Cantidad</th>
                        <th class="text-end">Precio Unit.</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $detalle): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($detalle['descripcion']); ?><br>
                            <small class="text-muted"><?php echo $detalle['unidad_medida']; ?></small>
                        </td>
                        <td class="text-center"><?php echo $detalle['cantidad']; ?></td>
                        <td class="text-end"><?php echo formatearPrecio($detalle['precio_unitario']); ?></td>
                        <td class="text-end"><?php echo formatearPrecio($detalle['subtotal']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-end">Total:</th>
                        <th class="text-end h5"><?php echo formatearPrecio($factura['total']); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <!-- Forma de pago y observaciones -->
    <div class="row">
        <div class="col-md-6">
            <strong>Forma de Pago:</strong><br>
            <?php
            $forma_pago_badges = [
                'efectivo' => 'success',
                'transferencia' => 'info',
                'pendiente' => 'warning'
            ];
            $badge_class = $forma_pago_badges[$factura['forma_pago']] ?? 'secondary';
            ?>
            <span class="badge bg-<?php echo $badge_class; ?>">
                <?php echo ucfirst($factura['forma_pago']); ?>
            </span>
        </div>
        <div class="col-md-6">
            <?php if ($factura['observaciones']): ?>
            <strong>Observaciones:</strong><br>
            <small><?php echo nl2br(htmlspecialchars($factura['observaciones'])); ?></small>
            <?php endif; ?>
        </div>
    </div>
</div>