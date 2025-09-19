<?php
// =====================================================
// PANEL TRABAJADOR - SISTEMA METRU
// =====================================================

include_once '../includes/functions.php';
verificarSesion('trabajador');

$titulo_pagina = 'Panel de Trabajador';
$icono_pagina = 'fas fa-user-tie';
$sin_sidebar = true; // Para trabajadores no mostrar sidebar

$usuario_id = $_SESSION['usuario_id'];
$fecha_hoy = date('Y-m-d');

$sql_salida_activa = "SELECT 
    s.id,
    s.estado,
    s.fecha_salida,
    r.nombre as ruta_nombre,
    COUNT(DISTINCT ds.producto_id) as total_productos,
    SUM(ds.cantidad) as total_unidades
FROM salidas_mercancia s
JOIN rutas r ON s.ruta_id = r.id
LEFT JOIN detalle_salidas ds ON s.id = ds.salida_id
WHERE s.estado IN ('preparando', 'en_ruta')
  AND (
    s.usuario_id = ? 
    OR 
    s.id IN (SELECT salida_id FROM salida_trabajadores WHERE trabajador_id = ?)
  )
  AND s.fecha_salida >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)
GROUP BY s.id
ORDER BY s.fecha_salida DESC, s.fecha_creacion DESC
LIMIT 1";

$salida_activa = obtenerRegistro($sql_salida_activa, [$usuario_id, $usuario_id]);

// Obtener facturas del día
$facturas_hoy = obtenerFacturasPorVendedor($usuario_id, $fecha_hoy);

// Calcular estadísticas del día
$total_facturas = count($facturas_hoy);
$total_vendido = array_sum(array_column($facturas_hoy, 'total'));
$total_efectivo = 0;
$total_transferencia = 0;
$total_pendiente = 0;

foreach ($facturas_hoy as $factura) {
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

// Obtener clientes de la ruta actual (si hay salida activa)
$clientes_ruta = [];
if ($salida_activa) {
    $sql_clientes = "SELECT c.* FROM clientes c 
                     JOIN rutas r ON c.ruta_id = r.id 
                     JOIN salidas_mercancia s ON r.id = s.ruta_id 
                     WHERE s.id = ? AND c.activo = 1 
                     ORDER BY c.nombre";
    $clientes_ruta = obtenerRegistros($sql_clientes, [$salida_activa['id']]);
}

include '../includes/header.php';
?>

<!-- Mensaje de bienvenida -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h4 class="text-primary">
                    <i class="fas fa-sun"></i> ¡Buenos días, <?php echo $_SESSION['usuario_nombre']; ?>!
                </h4>
                <p class="text-muted mb-0">
                    Hoy es <?php echo formatearFecha($fecha_hoy); ?> • Usuario: <?php echo $_SESSION['usuario_codigo']; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Estado de la ruta -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-route"></i> Estado de Mi Ruta
                </h6>
            </div>
            <div class="card-body">
                <?php if (!$salida_activa): ?>
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-info-circle fa-2x mb-2"></i>
                        <h5>No hay ruta asignada para hoy</h5>
                        <p class="mb-0">Contacte al administrador para que le asigne una ruta</p>
                    </div>
                <?php else: ?>
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-1">
                                <i class="fas fa-map-marker-alt text-primary"></i>
                                <?php echo htmlspecialchars($salida_activa['ruta_nombre']); ?>
                            </h5>
                            <div class="row text-center mt-3">
                                <div class="col-4">
                                    <h6 class="text-primary"><?php echo $salida_activa['total_productos']; ?></h6>
                                    <small class="text-muted">Productos</small>
                                </div>
                                <div class="col-4">
                                    <h6 class="text-info"><?php echo $salida_activa['total_unidades']; ?></h6>
                                    <small class="text-muted">Unidades</small>
                                </div>
                                <div class="col-4">
                                    <h6 class="text-success"><?php echo count($clientes_ruta); ?></h6>
                                    <small class="text-muted">Clientes</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <?php
                            $badges = [
                                'preparando' => ['class' => 'warning', 'icon' => 'fa-clock', 'texto' => 'Preparando'],
                                'en_ruta' => ['class' => 'success', 'icon' => 'fa-truck', 'texto' => 'En Ruta']
                            ];
                            $badge = $badges[$salida_activa['estado']] ?? ['class' => 'secondary', 'icon' => 'fa-question', 'texto' => 'Desconocido'];
                            ?>
                            <span class="badge bg-<?php echo $badge['class']; ?> fs-6 p-3">
                                <i class="fas <?php echo $badge['icon']; ?>"></i><br>
                                <?php echo $badge['texto']; ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Estadísticas del día -->
<div class="row mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-success text-center">
            <div class="card-body">
                <i class="fas fa-receipt fa-2x text-success mb-2"></i>
                <h4 class="text-success"><?php echo $total_facturas; ?></h4>
                <small class="text-muted">Facturas</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-primary text-center">
            <div class="card-body">
                <i class="fas fa-dollar-sign fa-2x text-primary mb-2"></i>
                <h6 class="text-primary"><?php echo formatearPrecio($total_vendido); ?></h6>
                <small class="text-muted">Total Vendido</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-info text-center">
            <div class="card-body">
                <i class="fas fa-money-bill-wave fa-2x text-info mb-2"></i>
                <h6 class="text-info"><?php echo formatearPrecio($total_efectivo); ?></h6>
                <small class="text-muted">Efectivo</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-warning text-center">
            <div class="card-body">
                <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                <h6 class="text-warning"><?php echo formatearPrecio($total_pendiente); ?></h6>
                <small class="text-muted">Pendiente</small>
            </div>
        </div>
    </div>
</div>

<!-- Acciones principales -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-bolt"></i> Acciones Rápidas
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <?php if ($salida_activa && $salida_activa['estado'] == 'en_ruta'): ?>
                            <a href="crear_factura.php?salida_id=<?php echo $salida_activa['id']; ?>" 
                            class="btn btn-success btn-lg btn-block">
                                <i class="fas fa-plus-circle"></i> Crear Nueva Factura
                            </a>
                        <?php elseif ($salida_activa && $salida_activa['estado'] == 'preparando'): ?>
                            <button class="btn btn-warning btn-lg btn-block" disabled>
                                <i class="fas fa-clock"></i> Esperando que la ruta sea enviada
                            </button>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No tiene rutas activas asignadas
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="mis_facturas.php" class="btn btn-primary btn-lg w-100 py-3">
                            <i class="fas fa-list fa-2x d-block"></i>
                            <strong>Mis Facturas</strong>
                            <br><small>Ver ventas del día</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Facturas recientes -->
<?php if (!empty($facturas_hoy)): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-history"></i> Facturas de Hoy
                </h6>
                <a href="mis_facturas.php" class="btn btn-outline-primary btn-sm">
                    Ver Todas
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Factura</th>
                                <th>Cliente</th>
                                <th>Total</th>
                                <th>Pago</th>
                                <th>Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $facturas_mostrar = array_slice($facturas_hoy, 0, 5); // Solo las últimas 5
                            foreach ($facturas_mostrar as $factura): 
                            ?>
                            <tr>
                                <td>
                                    <small><strong><?php echo htmlspecialchars($factura['numero_factura']); ?></strong></small>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($factura['cliente_nombre']); ?></small>
                                </td>
                                <td>
                                    <strong class="text-success"><?php echo formatearPrecio($factura['total']); ?></strong>
                                </td>
                                <td>
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
                                </td>
                                <td>
                                    <small><?php echo date('H:i', strtotime($factura['fecha_venta'])); ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Auto-refresh cada 5 minutos -->
<script>
// Auto-refresh para mantener datos actualizados
setInterval(function() {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 300000); // 5 minutos

// Función para mostrar hora actual
function actualizarHora() {
    const ahora = new Date();
    const hora = ahora.toLocaleTimeString('es-CO');
    document.title = '<?php echo $titulo_pagina; ?> - ' + hora;
}

// Actualizar hora cada segundo
setInterval(actualizarHora, 1000);
actualizarHora();

// Notificación de bienvenida (solo la primera vez)
if (!localStorage.getItem('bienvenida_mostrada_' + '<?php echo date('Y-m-d'); ?>')) {
    setTimeout(function() {
        mostrarAlerta('¡Bienvenido! Listo para comenzar las ventas del día', 'success');
        localStorage.setItem('bienvenida_mostrada_' + '<?php echo date('Y-m-d'); ?>', 'true');
    }, 1000);
}
</script>

<style>
/* Estilos específicos para trabajador móvil */
@media (max-width: 768px) {
    .card {
        margin-bottom: 1rem;
    }
    
    .btn-lg {
        font-size: 1rem;
        padding: 1rem;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    h4, h5, h6 {
        font-size: 1.1rem;
    }
}

.card {
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.btn-lg {
    border-radius: 12px;
    transition: all 0.3s;
}

.btn-lg:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.badge.fs-6 {
    font-size: 1rem !important;
}
</style>

<?php include '../includes/footer.php'; ?>