<?php
// =====================================================
// REPORTES Y ANALYTICS - SISTEMA METRU
// =====================================================

include_once '../includes/functions.php';
verificarSesion('admin');

$titulo_pagina = 'Reportes y Analytics';
$icono_pagina = 'fas fa-chart-line';

// Parámetros de filtro
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-7 days'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$ruta_filtro = $_GET['ruta'] ?? '';
$tipo_reporte = $_GET['tipo'] ?? 'resumen';

// Obtener rutas para filtros
$rutas = obtenerTodasLasRutas();

// Función para obtener datos según el tipo de reporte
function obtenerDatosReporte($tipo, $fecha_inicio, $fecha_fin, $ruta_filtro = '') {
    global $pdo;
    
    $where_ruta = $ruta_filtro ? "AND c.ruta_id = ?" : "";
    $params = [$fecha_inicio, $fecha_fin];
    if ($ruta_filtro) $params[] = $ruta_filtro;
    
    switch ($tipo) {
        case 'resumen':
            return obtenerRegistros("
                SELECT 
                    DATE(f.fecha_venta) as fecha,
                    COUNT(f.id) as total_facturas,
                    SUM(f.total) as total_ventas,
                    SUM(CASE WHEN f.forma_pago = 'efectivo' THEN f.total ELSE 0 END) as total_efectivo,
                    SUM(CASE WHEN f.forma_pago = 'transferencia' THEN f.total ELSE 0 END) as total_transferencia,
                    SUM(CASE WHEN f.forma_pago = 'pendiente' THEN f.total ELSE 0 END) as total_pendiente
                FROM facturas f 
                JOIN clientes c ON f.cliente_id = c.id 
                WHERE DATE(f.fecha_venta) BETWEEN ? AND ? $where_ruta
                GROUP BY DATE(f.fecha_venta) 
                ORDER BY fecha DESC
            ", $params);
            
        case 'productos':
            return obtenerRegistros("
                SELECT 
                    p.descripcion,
                    p.unidad_medida,
                    SUM(df.cantidad) as cantidad_vendida,
                    AVG(df.precio_unitario) as precio_promedio,
                    SUM(df.subtotal) as total_vendido,
                    COUNT(DISTINCT f.id) as facturas_count
                FROM detalle_facturas df
                JOIN facturas f ON df.factura_id = f.id
                JOIN productos p ON df.producto_id = p.id
                JOIN clientes c ON f.cliente_id = c.id
                WHERE DATE(f.fecha_venta) BETWEEN ? AND ? $where_ruta
                GROUP BY p.id, p.descripcion, p.unidad_medida
                ORDER BY cantidad_vendida DESC
                LIMIT 20
            ", $params);
            
        case 'rutas':
            return obtenerRegistros("
                SELECT 
                    r.nombre as ruta_nombre,
                    COUNT(DISTINCT f.id) as total_facturas,
                    SUM(f.total) as total_ventas,
                    COUNT(DISTINCT f.vendedor_id) as vendedores_activos,
                    COUNT(DISTINCT f.cliente_id) as clientes_atendidos,
                    AVG(f.total) as promedio_factura
                FROM facturas f
                JOIN clientes c ON f.cliente_id = c.id
                JOIN rutas r ON c.ruta_id = r.id
                WHERE DATE(f.fecha_venta) BETWEEN ? AND ? $where_ruta
                GROUP BY r.id, r.nombre
                ORDER BY total_ventas DESC
            ", $params);
            
        case 'vendedores':
            return obtenerRegistros("
                SELECT 
                    u.nombre as vendedor_nombre,
                    u.codigo_usuario,
                    COUNT(f.id) as total_facturas,
                    SUM(f.total) as total_ventas,
                    AVG(f.total) as promedio_factura,
                    COUNT(DISTINCT f.cliente_id) as clientes_atendidos,
                    COUNT(DISTINCT DATE(f.fecha_venta)) as dias_trabajados
                FROM facturas f
                JOIN usuarios u ON f.vendedor_id = u.id
                JOIN clientes c ON f.cliente_id = c.id
                WHERE DATE(f.fecha_venta) BETWEEN ? AND ? $where_ruta
                GROUP BY u.id, u.nombre, u.codigo_usuario
                ORDER BY total_ventas DESC
            ", $params);
            
        default:
            return [];
    }
}

// Obtener datos según el tipo de reporte seleccionado
$datos_reporte = obtenerDatosReporte($tipo_reporte, $fecha_inicio, $fecha_fin, $ruta_filtro);

// Obtener estadísticas generales del período
$sql_estadisticas = "
    SELECT 
        COUNT(f.id) as total_facturas,
        SUM(f.total) as total_ventas,
        AVG(f.total) as promedio_factura,
        COUNT(DISTINCT f.vendedor_id) as vendedores_activos,
        COUNT(DISTINCT f.cliente_id) as clientes_atendidos,
        COUNT(DISTINCT c.ruta_id) as rutas_activas
    FROM facturas f
    JOIN clientes c ON f.cliente_id = c.id
    WHERE DATE(f.fecha_venta) BETWEEN ? AND ?
";

$params_stats = [$fecha_inicio, $fecha_fin];
if ($ruta_filtro) {
    $sql_estadisticas .= " AND c.ruta_id = ?";
    $params_stats[] = $ruta_filtro;
}

$estadisticas = obtenerRegistro($sql_estadisticas, $params_stats) ?: [
    'total_facturas' => 0,
    'total_ventas' => 0,
    'promedio_factura' => 0,
    'vendedores_activos' => 0,
    'clientes_atendidos' => 0,
    'rutas_activas' => 0
];

include '../includes/header.php';
?>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-filter"></i> Filtros de Reporte
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-2">
                    <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                           value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                </div>
                <div class="col-md-2">
                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                           value="<?php echo htmlspecialchars($fecha_fin); ?>">
                </div>
                <div class="col-md-2">
                    <label for="ruta" class="form-label">Ruta</label>
                    <select class="form-select" id="ruta" name="ruta">
                        <option value="">Todas las rutas</option>
                        <?php foreach ($rutas as $ruta): ?>
                            <option value="<?php echo $ruta['id']; ?>" 
                                    <?php echo $ruta_filtro == $ruta['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ruta['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="tipo" class="form-label">Tipo de Reporte</label>
                    <select class="form-select" id="tipo" name="tipo">
                        <option value="resumen" <?php echo $tipo_reporte == 'resumen' ? 'selected' : ''; ?>>
                            Resumen por Fecha
                        </option>
                        <option value="productos" <?php echo $tipo_reporte == 'productos' ? 'selected' : ''; ?>>
                            Productos Más Vendidos
                        </option>
                        <option value="rutas" <?php echo $tipo_reporte == 'rutas' ? 'selected' : ''; ?>>
                            Rendimiento por Rutas
                        </option>
                        <option value="vendedores" <?php echo $tipo_reporte == 'vendedores' ? 'selected' : ''; ?>>
                            Rendimiento Vendedores
                        </option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid gap-2 d-md-flex">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Generar
                        </button>
                        <button type="button" class="btn btn-success" onclick="exportarReporte()">
                            <i class="fas fa-download"></i> Excel
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Estadísticas generales -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card border-primary text-center h-100">
            <div class="card-body">
                <i class="fas fa-receipt fa-2x text-primary mb-2"></i>
                <h4 class="text-primary"><?php echo number_format($estadisticas['total_facturas']); ?></h4>
                <small class="text-muted">Total Facturas</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card border-success text-center h-100">
            <div class="card-body">
                <i class="fas fa-dollar-sign fa-2x text-success mb-2"></i>
                <h5 class="text-success"><?php echo formatearPrecio($estadisticas['total_ventas']); ?></h5>
                <small class="text-muted">Total Ventas</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card border-info text-center h-100">
            <div class="card-body">
                <i class="fas fa-calculator fa-2x text-info mb-2"></i>
                <h6 class="text-info"><?php echo formatearPrecio($estadisticas['promedio_factura']); ?></h6>
                <small class="text-muted">Promedio Factura</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card border-warning text-center h-100">
            <div class="card-body">
                <i class="fas fa-users fa-2x text-warning mb-2"></i>
                <h4 class="text-warning"><?php echo $estadisticas['vendedores_activos']; ?></h4>
                <small class="text-muted">Vendedores</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card border-secondary text-center h-100">
            <div class="card-body">
                <i class="fas fa-store fa-2x text-secondary mb-2"></i>
                <h4 class="text-secondary"><?php echo $estadisticas['clientes_atendidos']; ?></h4>
                <small class="text-muted">Clientes</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card border-dark text-center h-100">
            <div class="card-body">
                <i class="fas fa-route fa-2x text-dark mb-2"></i>
                <h4 class="text-dark"><?php echo $estadisticas['rutas_activas']; ?></h4>
                <small class="text-muted">Rutas</small>
            </div>
        </div>
    </div>
</div>

<!-- Datos del reporte -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-chart-bar"></i> 
            <?php
            $titulos_reporte = [
                'resumen' => 'Resumen de Ventas por Fecha',
                'productos' => 'Productos Más Vendidos',
                'rutas' => 'Rendimiento por Rutas',
                'vendedores' => 'Rendimiento de Vendedores'
            ];
            echo $titulos_reporte[$tipo_reporte] ?? 'Reporte';
            ?>
        </h6>
        <span class="badge bg-primary"><?php echo count($datos_reporte); ?> registros</span>
    </div>
    <div class="card-body">
        <?php if (empty($datos_reporte)): ?>
            <div class="text-center py-5">
                <i class="fas fa-chart-line fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No hay datos para mostrar</h5>
                <p class="text-muted">Intente con diferentes filtros o fechas</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="tabla-reporte">
                    <thead class="table-dark">
                        <?php if ($tipo_reporte == 'resumen'): ?>
                        <tr>
                            <th>Fecha</th>
                            <th>Facturas</th>
                            <th>Total Ventas</th>
                            <th>Efectivo</th>
                            <th>Transferencia</th>
                            <th>Pendiente</th>
                        </tr>
                        <?php elseif ($tipo_reporte == 'productos'): ?>
                        <tr>
                            <th>Producto</th>
                            <th>Unidad</th>
                            <th>Cantidad</th>
                            <th>Precio Prom.</th>
                            <th>Total Vendido</th>
                            <th>Facturas</th>
                        </tr>
                        <?php elseif ($tipo_reporte == 'rutas'): ?>
                        <tr>
                            <th>Ruta</th>
                            <th>Facturas</th>
                            <th>Total Ventas</th>
                            <th>Vendedores</th>
                            <th>Clientes</th>
                            <th>Prom. Factura</th>
                        </tr>
                        <?php elseif ($tipo_reporte == 'vendedores'): ?>
                        <tr>
                            <th>Vendedor</th>
                            <th>Código</th>
                            <th>Facturas</th>
                            <th>Total Ventas</th>
                            <th>Prom. Factura</th>
                            <th>Clientes</th>
                            <th>Días</th>
                        </tr>
                        <?php endif; ?>
                    </thead>
                    <tbody>
                        <?php foreach ($datos_reporte as $fila): ?>
                        <tr>
                            <?php if ($tipo_reporte == 'resumen'): ?>
                                <td><strong><?php echo formatearFecha($fila['fecha']); ?></strong></td>
                                <td><span class="badge bg-primary"><?php echo $fila['total_facturas']; ?></span></td>
                                <td><strong class="text-success"><?php echo formatearPrecio($fila['total_ventas']); ?></strong></td>
                                <td><?php echo formatearPrecio($fila['total_efectivo']); ?></td>
                                <td><?php echo formatearPrecio($fila['total_transferencia']); ?></td>
                                <td><?php echo formatearPrecio($fila['total_pendiente']); ?></td>
                            <?php elseif ($tipo_reporte == 'productos'): ?>
                                <td><strong><?php echo htmlspecialchars($fila['descripcion']); ?></strong></td>
                                <td><span class="badge bg-light text-dark"><?php echo $fila['unidad_medida']; ?></span></td>
                                <td><span class="badge bg-primary"><?php echo $fila['cantidad_vendida']; ?></span></td>
                                <td><?php echo formatearPrecio($fila['precio_promedio']); ?></td>
                                <td><strong class="text-success"><?php echo formatearPrecio($fila['total_vendido']); ?></strong></td>
                                <td><span class="badge bg-info"><?php echo $fila['facturas_count']; ?></span></td>
                            <?php elseif ($tipo_reporte == 'rutas'): ?>
                                <td><strong><?php echo htmlspecialchars($fila['ruta_nombre']); ?></strong></td>
                                <td><span class="badge bg-primary"><?php echo $fila['total_facturas']; ?></span></td>
                                <td><strong class="text-success"><?php echo formatearPrecio($fila['total_ventas']); ?></strong></td>
                                <td><span class="badge bg-info"><?php echo $fila['vendedores_activos']; ?></span></td>
                                <td><span class="badge bg-warning"><?php echo $fila['clientes_atendidos']; ?></span></td>
                                <td><?php echo formatearPrecio($fila['promedio_factura']); ?></td>
                            <?php elseif ($tipo_reporte == 'vendedores'): ?>
                                <td><strong><?php echo htmlspecialchars($fila['vendedor_nombre']); ?></strong></td>
                                <td><span class="badge bg-secondary"><?php echo $fila['codigo_usuario']; ?></span></td>
                                <td><span class="badge bg-primary"><?php echo $fila['total_facturas']; ?></span></td>
                                <td><strong class="text-success"><?php echo formatearPrecio($fila['total_ventas']); ?></strong></td>
                                <td><?php echo formatearPrecio($fila['promedio_factura']); ?></td>
                                <td><span class="badge bg-info"><?php echo $fila['clientes_atendidos']; ?></span></td>
                                <td><span class="badge bg-warning"><?php echo $fila['dias_trabajados']; ?></span></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function exportarReporte() {
    // Función para exportar la tabla a Excel
    exportarTablaCSV('tabla-reporte', 'reporte_metru_' + new Date().toISOString().split('T')[0] + '.csv');
    mostrarAlerta('Reporte exportado correctamente', 'success');
}

// Configurar fechas rápidas
$(document).ready(function() {
    // Botones para rangos rápidos
    const botonesRango = `
        <div class="btn-group btn-group-sm mt-2" role="group">
            <button type="button" class="btn btn-outline-secondary" onclick="setRangoFecha('hoy')">Hoy</button>
            <button type="button" class="btn btn-outline-secondary" onclick="setRangoFecha('ayer')">Ayer</button>
            <button type="button" class="btn btn-outline-secondary" onclick="setRangoFecha('semana')">Esta Semana</button>
            <button type="button" class="btn btn-outline-secondary" onclick="setRangoFecha('mes')">Este Mes</button>
        </div>
    `;
    
    $('#fecha_inicio').closest('.col-md-2').append(botonesRango);
});

function setRangoFecha(rango) {
    const hoy = new Date();
    let fechaInicio, fechaFin;
    
    switch(rango) {
        case 'hoy':
            fechaInicio = fechaFin = hoy.toISOString().split('T')[0];
            break;
        case 'ayer':
            const ayer = new Date(hoy);
            ayer.setDate(ayer.getDate() - 1);
            fechaInicio = fechaFin = ayer.toISOString().split('T')[0];
            break;
        case 'semana':
            const inicioSemana = new Date(hoy);
            inicioSemana.setDate(hoy.getDate() - hoy.getDay());
            fechaInicio = inicioSemana.toISOString().split('T')[0];
            fechaFin = hoy.toISOString().split('T')[0];
            break;
        case 'mes':
            fechaInicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1).toISOString().split('T')[0];
            fechaFin = hoy.toISOString().split('T')[0];
            break;
    }
    
    $('#fecha_inicio').val(fechaInicio);
    $('#fecha_fin').val(fechaFin);
}

// Auto-submit al cambiar tipo de reporte
$('#tipo').on('change', function() {
    $(this).closest('form').submit();
});
</script>

<style>
.card {
    border-radius: 15px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.table th {
    border-top: none;
    font-weight: 600;
    font-size: 0.9rem;
}

.badge {
    font-size: 0.8rem;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.85rem;
    }
    
    .card-body {
        padding: 1rem 0.5rem;
    }
}
</style>

<?php include '../includes/footer.php'; ?>