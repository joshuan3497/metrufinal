<?php
// =====================================================
// DASHBOARD ADMINISTRADOR - SISTEMA METRU
// =====================================================

include_once '../includes/functions.php';
verificarSesion('admin');

$titulo_pagina = 'Dashboard Administrador';
$icono_pagina = 'fas fa-tachometer-alt';

// Obtener estadísticas del día
$fecha_hoy = date('Y-m-d');

// Salidas del día
$salidas_hoy = obtenerSalidasDelDia($fecha_hoy);
$total_salidas = count($salidas_hoy);

// Calcular estadísticas de ventas del día
$sql_ventas_hoy = "SELECT 
    COUNT(f.id) as total_facturas,
    SUM(f.total) as total_ventas,
    SUM(CASE WHEN f.forma_pago = 'efectivo' THEN f.total ELSE 0 END) as total_efectivo,
    SUM(CASE WHEN f.forma_pago = 'transferencia' THEN f.total ELSE 0 END) as total_transferencia,
    SUM(CASE WHEN f.forma_pago = 'pendiente' THEN f.total ELSE 0 END) as total_pendiente
FROM facturas f 
WHERE DATE(f.fecha_venta) = ?";
$ventas_hoy = obtenerRegistro($sql_ventas_hoy, [$fecha_hoy]) ?: [
    'total_facturas' => 0,
    'total_ventas' => 0,
    'total_efectivo' => 0,
    'total_transferencia' => 0,
    'total_pendiente' => 0
];

// Asegurar que los valores no sean null
$ventas_hoy = $ventas_hoy ?: [
    'total_facturas' => 0,
    'total_ventas' => 0,
    'total_efectivo' => 0,
    'total_transferencia' => 0,
    'total_pendiente' => 0
];

// Rutas activas
$sql_rutas_activas = "SELECT COUNT(DISTINCT s.ruta_id) as rutas_activas 
                      FROM salidas_mercancia s 
                      WHERE DATE(s.fecha_salida) = ? AND s.estado = 'en_ruta'";
$rutas_activas = obtenerRegistro($sql_rutas_activas, [$fecha_hoy])['rutas_activas'] ?? 0;

// Obtener actividad reciente
$sql_actividad = "SELECT 
    'factura' as tipo,
    f.numero_factura as detalle,
    f.total as valor,
    f.fecha_venta as fecha,
    u.nombre as usuario,
    c.nombre as cliente
FROM facturas f
JOIN usuarios u ON f.vendedor_id = u.id
LEFT JOIN clientes c ON f.cliente_id = c.id
WHERE DATE(f.fecha_venta) = ?
ORDER BY f.fecha_venta DESC
LIMIT 10";
$actividad_reciente = obtenerRegistros($sql_actividad, [$fecha_hoy]);

// Obtener rutas del día
$sql_rutas_activas_detalle = "SELECT 
    r.nombre as ruta_nombre,
    s.estado,
    u.nombre as responsable,
    s.fecha_salida,
    s.id as salida_id,
    COUNT(DISTINCT ds.id) as productos_salida,
    COALESCE(SUM(f.total), 0) as total_vendido,
    COUNT(DISTINCT f.id) as facturas_creadas,
    DATEDIFF(CURDATE(), s.fecha_salida) as dias_activa,
    GROUP_CONCAT(DISTINCT ut.nombre SEPARATOR ', ') as trabajadores_asignados
FROM salidas_mercancia s
JOIN rutas r ON s.ruta_id = r.id
JOIN usuarios u ON s.usuario_id = u.id
LEFT JOIN detalle_salidas ds ON s.id = ds.salida_id
LEFT JOIN facturas f ON s.id = f.salida_id
LEFT JOIN salida_trabajadores st ON s.id = st.salida_id
LEFT JOIN usuarios ut ON st.trabajador_id = ut.id
WHERE s.estado IN ('preparando', 'en_ruta')
  AND s.fecha_salida >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY s.id
ORDER BY s.estado DESC, s.fecha_salida DESC";
$rutas_activas_detalle = obtenerRegistros($sql_rutas_activas_detalle);

include '../includes/header.php';
?>

<!-- Dashboard Cards -->
<div class="row mb-4">
    <!-- Total Ventas -->
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Ventas del Día
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo formatearPrecio($ventas_hoy['total_ventas']); ?>
                        </div>
                        <small class="text-muted">
                            <?php echo $ventas_hoy['total_facturas']; ?> facturas
                        </small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rutas Activas -->
        <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-truck"></i> Rutas Activas
            </h6>
            <span class="badge bg-primary"><?php echo count($rutas_activas_detalle); ?> rutas activas</span>
        </div>
        <div class="card-body">
            <?php if (empty($rutas_activas_detalle)): ?>
                <div class="text-center text-muted">
                    <i class="fas fa-truck fa-3x mb-3"></i>
                    <p>No hay rutas activas en este momento</p>
                    <a href="salidas.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Crear Nueva Salida
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Ruta</th>
                                <th>Fecha Inicio</th>
                                <th>Días Activa</th>
                                <th>Trabajadores</th>
                                <th>Estado</th>
                                <th>Facturas</th>
                                <th>Total Vendido</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rutas_activas_detalle as $ruta): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($ruta['ruta_nombre']); ?></strong>
                                </td>
                                <td><?php echo formatearFecha($ruta['fecha_salida']); ?></td>
                                <td>
                                    <?php if ($ruta['dias_activa'] == 0): ?>
                                        <span class="badge bg-success">Hoy</span>
                                    <?php elseif ($ruta['dias_activa'] == 1): ?>
                                        <span class="badge bg-info">1 día</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning"><?php echo $ruta['dias_activa']; ?> días</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small>
                                        <?php echo !empty($ruta['trabajadores_asignados']) 
                                            ? htmlspecialchars($ruta['trabajadores_asignados']) 
                                            : htmlspecialchars($ruta['responsable']); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php
                                    $badges = [
                                        'preparando' => 'secondary',
                                        'en_ruta' => 'primary',
                                        'finalizada' => 'success'
                                    ];
                                    $badge_class = $badges[$ruta['estado']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <?php echo ESTADOS_SALIDA[$ruta['estado']] ?? $ruta['estado']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $ruta['facturas_creadas']; ?></span>
                                </td>
                                <td>
                                    <strong><?php echo formatearPrecio($ruta['total_vendido']); ?></strong>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="detalle_salida.php?id=<?php echo $ruta['salida_id']; ?>" 
                                        class="btn btn-outline-info" 
                                        title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($ruta['estado'] == 'en_ruta'): ?>
                                        <a href="cierres.php?salida_id=<?php echo $ruta['salida_id']; ?>" 
                                        class="btn btn-outline-success" 
                                        title="Cerrar ruta">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Efectivo Recaudado -->
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Efectivo Recaudado
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo formatearPrecio($ventas_hoy['total_efectivo']); ?>
                        </div>
                        <small class="text-muted">
                            Transferencias: <?php echo formatearPrecio($ventas_hoy['total_transferencia']); ?>
                        </small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pendientes de Cobro -->
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Pendientes de Cobro
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo formatearPrecio($ventas_hoy['total_pendiente']); ?>
                        </div>
                        <small class="text-muted">
                            Requiere seguimiento
                        </small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Acciones Rápidas -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-bolt"></i> Acciones Rápidas
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="salidas.php" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-plus-circle"></i><br>
                            Nueva Salida
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="salidas.php?mostrar=preparando" class="btn btn-warning btn-lg w-100">
                            <i class="fas fa-truck-loading"></i><br>
                            Cargar Camión
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="reportes.php" class="btn btn-info btn-lg w-100">
                            <i class="fas fa-chart-bar"></i><br>
                            Ver Reportes
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="cierres.php" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-calculator"></i><br>
                            Cerrar Rutas
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Rutas del Día -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-truck"></i> Rutas del Día
                </h6>
               <span class="badge bg-primary"><?php echo count($rutas_activas_detalle); ?> rutas activas</span>
            </div>
            <div class="card-body">
                <?php if (empty($rutas_activas_detalle)): ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-truck fa-3x mb-3"></i>
                        <p>No hay rutas programadas para hoy</p>
                        <a href="salidas.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Crear Primera Salida
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Ruta</th>
                                    <th>Responsable</th>
                                    <th>Estado</th>
                                    <th>Productos</th>
                                    <th>Facturas</th>
                                    <th>Total Vendido</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rutas_activas_detalle as $ruta): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($ruta['ruta_nombre']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($ruta['responsable']); ?></td>
                                    <td>
                                        <?php
                                        $badges = [
                                            'preparando' => 'secondary',
                                            'en_ruta' => 'primary',
                                            'finalizada' => 'success'
                                        ];
                                        $badge_class = $badges[$ruta['estado']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                            <?php echo ESTADOS_SALIDA[$ruta['estado']] ?? $ruta['estado']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $ruta['productos_salida']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo $ruta['facturas_creadas']; ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo formatearPrecio($ruta['total_vendido']); ?></strong>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="detalle_salida.php?id=<?php echo $ruta['salida_id']; ?>" 
                                               class="btn btn-outline-primary btn-sm" 
                                               title="Ver detalles">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($ruta['estado'] == 'en_ruta'): ?>
                                            <a href="cierres.php?salida_id=<?php echo $ruta['salida_id']; ?>" 
                                               class="btn btn-outline-success btn-sm" 
                                               title="Cerrar ruta">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Actividad Reciente -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-history"></i> Actividad Reciente
                </h6>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($actividad_reciente)): ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-clock fa-2x mb-2"></i>
                        <p>No hay actividad reciente</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($actividad_reciente as $actividad): ?>
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0">
                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                                 style="width: 40px; height: 40px;">
                                <i class="fas fa-receipt text-white"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="small">
                                <strong><?php echo htmlspecialchars($actividad['detalle']); ?></strong>
                                <div class="text-muted">
                                    <?php echo htmlspecialchars($actividad['cliente']); ?>
                                </div>
                                <div class="text-success">
                                    <?php echo formatearPrecio($actividad['valor']); ?>
                                </div>
                                <div class="text-muted small">
                                    <?php echo formatearFechaHora($actividad['fecha']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Auto-refresh cada 30 segundos -->
<script>
    // Auto-refresh del dashboard cada 30 segundos
    setInterval(function() {
        location.reload();
    }, 30000);
</script>

<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df!important;
}
.border-left-success {
    border-left: 0.25rem solid #1cc88a!important;
}
.border-left-info {
    border-left: 0.25rem solid #36b9cc!important;
}
.border-left-warning {
    border-left: 0.25rem solid #f6c23e!important;
}
.text-xs {
    font-size: 0.7rem;
}
</style>

<?php include '../includes/footer.php'; ?>