<?php
// =====================================================
// MIS FACTURAS - TRABAJADOR - SISTEMA METRU
// =====================================================

include_once '../includes/functions.php';
verificarSesion('trabajador');

$titulo_pagina = 'Mis Facturas';
$icono_pagina = 'fas fa-receipt';
$sin_sidebar = true;

$usuario_id = $_SESSION['usuario_id'];

// Filtros
$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');
$forma_pago_filtro = $_GET['forma_pago'] ?? '';

// Obtener facturas del vendedor
$sql_facturas = "SELECT 
    f.id,
    f.numero_factura,
    f.total,
    f.forma_pago,
    f.fecha_venta,
    f.observaciones,
    COALESCE(c.nombre, f.cliente_nombre, 'Cliente General') as cliente_nombre,
    COALESCE(f.cliente_ciudad, 'Sin especificar') as cliente_ciudad,
    r.nombre as ruta_nombre
FROM facturas f
LEFT JOIN clientes c ON f.cliente_id = c.id
JOIN salidas_mercancia s ON f.salida_id = s.id
JOIN rutas r ON s.ruta_id = r.id
WHERE f.vendedor_id = ? AND DATE(f.fecha_venta) = ?";

$params = [$usuario_id, $fecha_filtro];

if ($forma_pago_filtro) {
    $sql_facturas .= " AND f.forma_pago = ?";
    $params[] = $forma_pago_filtro;
}

$sql_facturas .= " ORDER BY f.fecha_venta DESC";

$facturas = obtenerRegistros($sql_facturas, $params);

// Calcular estadísticas
$total_facturas = count($facturas);
$total_vendido = array_sum(array_column($facturas, 'total'));
$total_efectivo = 0;
$total_transferencia = 0;
$total_pendiente = 0;

foreach ($facturas as $factura) {
    switch ($factura['forma_pago']) {
        case 'efectivo':
            $total_efectivo += $factura['total'];
            break;
        case 'transferencia':
            $total_transferencia += $factura['total'];
            break;
        case 'pendiente':
            $total_pendiente += $factura['total'];
            break;
    }
}

include '../includes/header.php';
?>

<!-- Filtros -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="">
            <div class="row align-items-end">
                <div class="col-6">
                    <label for="fecha" class="form-label small">Fecha</label>
                    <input type="date" class="form-control form-control-sm" id="fecha" name="fecha" 
                           value="<?php echo htmlspecialchars($fecha_filtro); ?>">
                </div>
                <div class="col-4">
                    <label for="forma_pago" class="form-label small">Pago</label>
                    <select class="form-select form-select-sm" id="forma_pago" name="forma_pago">
                        <option value="">Todos</option>
                        <option value="efectivo" <?php echo $forma_pago_filtro == 'efectivo' ? 'selected' : ''; ?>>
                            Efectivo
                        </option>
                        <option value="transferencia" <?php echo $forma_pago_filtro == 'transferencia' ? 'selected' : ''; ?>>
                            Transferencia
                        </option>
                        <option value="pendiente" <?php echo $forma_pago_filtro == 'pendiente' ? 'selected' : ''; ?>>
                            Pendiente
                        </option>
                    </select>
                </div>
                <div class="col-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Estadísticas del día -->
<div class="row mb-3">
    <div class="col-3">
        <div class="card text-center border-primary">
            <div class="card-body py-2">
                <h6 class="text-primary mb-1"><?php echo $total_facturas; ?></h6>
                <small class="text-muted">Facturas</small>
            </div>
        </div>
    </div>
    <div class="col-3">
        <div class="card text-center border-success">
            <div class="card-body py-2">
                <h6 class="text-success mb-1"><?php echo formatearPrecio($total_vendido); ?></h6>
                <small class="text-muted">Total</small>
            </div>
        </div>
    </div>
    <div class="col-3">
        <div class="card text-center border-info">
            <div class="card-body py-2">
                <h6 class="text-info mb-1"><?php echo formatearPrecio($total_efectivo); ?></h6>
                <small class="text-muted">Efectivo</small>
            </div>
        </div>
    </div>
    <div class="col-3">
        <div class="card text-center border-warning">
            <div class="card-body py-2">
                <h6 class="text-warning mb-1"><?php echo formatearPrecio($total_pendiente); ?></h6>
                <small class="text-muted">Pendiente</small>
            </div>
        </div>
    </div>
</div>

<!-- Acciones rápidas -->
<div class="row mb-3">
    <div class="col-6">
        <a href="index.php" class="btn btn-outline-primary btn-sm w-100">
            <i class="fas fa-home"></i> Inicio
        </a>
    </div>
    <div class="col-6">
        <?php
        // Verificar si hay salida activa para crear nueva factura
        $salida_activa = obtenerRegistro(
            "SELECT id FROM salidas_mercancia WHERE usuario_id = ? AND DATE(fecha_salida) = ? AND estado = 'en_ruta'",
            [$usuario_id, $fecha_filtro]
        );
        ?>
        <?php if ($salida_activa): ?>
            <a href="crear_factura.php?salida_id=<?php echo $salida_activa['id']; ?>" 
               class="btn btn-success btn-sm w-100">
                <i class="fas fa-plus"></i> Nueva Factura
            </a>
        <?php else: ?>
            <button class="btn btn-secondary btn-sm w-100" disabled>
                <i class="fas fa-lock"></i> Sin Ruta Activa
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Lista de facturas -->
<div class="card">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h6 class="m-0">
            <i class="fas fa-list"></i> 
            Facturas del <?php echo formatearFecha($fecha_filtro); ?>
        </h6>
        <span class="badge bg-primary"><?php echo $total_facturas; ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($facturas)): ?>
            <div class="text-center py-4">
                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No hay facturas</h6>
                <p class="text-muted mb-0">Para esta fecha</p>
            </div>
        <?php else: ?>
            <!-- Lista optimizada para móvil -->
            <div class="list-group list-group-flush">
                <?php foreach ($facturas as $factura): ?>
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">
                                <strong><?php echo htmlspecialchars($factura['numero_factura']); ?></strong>
                                <?php
                                $forma_pago_badges = [
                                    'efectivo' => 'success',
                                    'transferencia' => 'info',
                                    'pendiente' => 'warning'
                                ];
                                $badge_class = $forma_pago_badges[$factura['forma_pago']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $badge_class; ?> ms-2">
                                    <?php echo FORMAS_PAGO[$factura['forma_pago']] ?? $factura['forma_pago']; ?>
                                </span>
                            </h6>
                            <p class="mb-1">
                                <i class="fas fa-store text-muted"></i>
                                <?php echo htmlspecialchars($factura['cliente_nombre']); ?>
                            </p>
                            <small class="text-muted">
                                <i class="fas fa-clock"></i>
                                <?php echo date('H:i', strtotime($factura['fecha_venta'])); ?>
                                
                                <?php if ($factura['observaciones']): ?>
                                    <br>
                                    <i class="fas fa-comment"></i>
                                    <?php echo htmlspecialchars(substr($factura['observaciones'], 0, 30)); ?>
                                    <?php echo strlen($factura['observaciones']) > 30 ? '...' : ''; ?>
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="text-end">
                            <h5 class="mb-1 text-success">
                                <?php echo formatearPrecio($factura['total']); ?>
                            </h5>
                            <button class="btn btn-outline-primary btn-sm" 
                                    onclick="verDetalle(<?php echo $factura['id']; ?>)">
                                <i class="fas fa-eye"></i> Ver
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Resumen del día -->
<?php if (!empty($facturas)): ?>
<div class="card mt-3">
    <div class="card-header py-2">
        <h6 class="m-0">
            <i class="fas fa-chart-pie"></i> Resumen del Día
        </h6>
    </div>
    <div class="card-body py-2">
        <div class="row text-center">
            <div class="col-4">
                <div class="border-end">
                    <h6 class="text-success mb-0"><?php echo formatearPrecio($total_efectivo); ?></h6>
                    <small class="text-muted">Efectivo</small>
                </div>
            </div>
            <div class="col-4">
                <div class="border-end">
                    <h6 class="text-info mb-0"><?php echo formatearPrecio($total_transferencia); ?></h6>
                    <small class="text-muted">Transferencias</small>
                </div>
            </div>
            <div class="col-4">
                <h6 class="text-warning mb-0"><?php echo formatearPrecio($total_pendiente); ?></h6>
                <small class="text-muted">Pendientes</small>
            </div>
        </div>
        
        <hr class="my-2">
        
        <div class="d-flex justify-content-between align-items-center">
            <strong>Total del Día:</strong>
            <strong class="text-success h5 mb-0"><?php echo formatearPrecio($total_vendido); ?></strong>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal para ver detalle de factura -->
<div class="modal fade" id="modal-detalle-factura" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de Factura</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenido-detalle-factura">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p>Cargando...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
function verDetalle(facturaId) {
    const modal = new bootstrap.Modal(document.getElementById('modal-detalle-factura'));
    modal.show();
    
    // Cargar detalle via AJAX
    $.ajax({
        url: 'detalle_factura_ajax.php',
        method: 'GET',
        data: { id: facturaId },
        success: function(response) {
            $('#contenido-detalle-factura').html(response);
        },
        error: function() {
            $('#contenido-detalle-factura').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    Error al cargar el detalle de la factura
                </div>
            `);
        }
    });
}

// Auto-refresh cada 2 minutos
setInterval(function() {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 120000);

// Atajos de teclado para navegación rápida
$(document).keydown(function(e) {
    // F5 = Refresh
    if (e.key === 'F5') {
        location.reload();
    }
    
    // Ctrl + N = Nueva factura
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        const nuevaFacturaBtn = $('a[href*="crear_factura"]');
        if (nuevaFacturaBtn.length && !nuevaFacturaBtn.prop('disabled')) {
            window.location.href = nuevaFacturaBtn.attr('href');
        }
    }
});

// Marcar día actual en el filtro de fecha
$('#fecha').on('change', function() {
    const fechaSeleccionada = $(this).val();
    const fechaHoy = '<?php echo date('Y-m-d'); ?>';
    
    if (fechaSeleccionada === fechaHoy) {
        $(this).addClass('border-primary');
    } else {
        $(this).removeClass('border-primary');
    }
});

// Inicializar
$(document).ready(function() {
    const fechaActual = $('#fecha').val();
    const fechaHoy = '<?php echo date('Y-m-d'); ?>';
    
    if (fechaActual === fechaHoy) {
        $('#fecha').addClass('border-primary');
    }
});
</script>

<style>
/* Optimización para móvil */
.list-group-item {
    border-left: none;
    border-right: none;
    padding: 0.75rem;
}

.list-group-item:first-child {
    border-top: none;
}

.list-group-item:last-child {
    border-bottom: none;
}

.card {
    border-radius: 12px;
}

.badge {
    font-size: 0.7rem;
}

@media (max-width: 768px) {
    .modal-dialog {
        margin: 0.5rem;
    }
    
    .card-body {
        padding: 0.75rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }
}

/* Efectos visuales */
.list-group-item:hover {
    background-color: #f8f9fa;
}

.border-primary {
    border-color: #007bff !important;
    border-width: 2px !important;
}

.btn:disabled {
    opacity: 0.6;
}
</style>

<?php include '../includes/footer.php'; ?>