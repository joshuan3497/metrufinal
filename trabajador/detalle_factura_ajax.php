<?php
// =====================================================
// DETALLE DE FACTURA AJAX - SISTEMA METRU
// =====================================================

include_once '../includes/functions.php';
verificarSesion('trabajador');

$factura_id = $_GET['id'] ?? 0;

if (!$factura_id) {
    echo '<div class="alert alert-danger">Factura no encontrada</div>';
    exit();
}

// Obtener información de la factura
$sql_factura = "SELECT 
    f.*,
    c.nombre as cliente_nombre,
    u.nombre as vendedor_nombre,
    r.nombre as ruta_nombre
FROM facturas f
JOIN clientes c ON f.cliente_id = c.id
JOIN usuarios u ON f.vendedor_id = u.id
JOIN rutas r ON c.ruta_id = r.id
WHERE f.id = ? AND f.vendedor_id = ?";

$factura = obtenerRegistro($sql_factura, [$factura_id, $_SESSION['usuario_id']]);

if (!$factura) {
    echo '<div class="alert alert-danger">No tiene acceso a esta factura</div>';
    exit();
}

// Obtener detalle de productos
$detalles = obtenerDetalleFactura($factura_id);
?>

<div class="factura-detalle">
    <!-- Encabezado -->
    <div class="row mb-3">
        <div class="col-6">
            <strong>Factura:</strong><br>
            <?php echo htmlspecialchars($factura['numero_factura']); ?>
        </div>
        <div class="col-6 text-end">
            <strong>Fecha:</strong><br>
            <?php echo formatearFechaHora($factura['fecha_venta']); ?>
        </div>
    </div>
    
    <!-- Cliente -->
    <div class="mb-3">
        <strong>Cliente:</strong><br>
        <?php echo htmlspecialchars($factura['cliente_nombre']); ?><br>
        <small class="text-muted">Ruta: <?php echo htmlspecialchars($factura['ruta_nombre']); ?></small>
    </div>
    
    <!-- Productos -->
    <div class="mb-3">
        <strong>Productos:</strong>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cant.</th>
                        <th>Precio</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $detalle): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($detalle['descripcion']); ?><br>
                            <small class="text-muted"><?php echo $detalle['unidad_medida']; ?></small>
                        </td>
                        <td><?php echo $detalle['cantidad']; ?></td>
                        <td><?php echo formatearPrecio($detalle['precio_unitario']); ?></td>
                        <td><?php echo formatearPrecio($detalle['subtotal']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3">Total:</th>
                        <th><?php echo formatearPrecio($factura['total']); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <!-- Forma de pago -->
    <div class="row">
        <div class="col-6">
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
                <?php echo FORMAS_PAGO[$factura['forma_pago']] ?? $factura['forma_pago']; ?>
            </span>
        </div>
        <div class="col-6 text-end">
            <strong>Total:</strong><br>
            <h5 class="text-success mb-0"><?php echo formatearPrecio($factura['total']); ?></h5>
        </div>
    </div>
    
    <?php if ($factura['observaciones']): ?>
    <div class="mt-3">
        <strong>Observaciones:</strong><br>
        <?php echo nl2br(htmlspecialchars($factura['observaciones'])); ?>
    </div>
    <?php endif; ?>
</div>