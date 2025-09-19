<?php
// =====================================================
// CIERRES DE RUTA - SISTEMA METRU
// =====================================================

include_once '../includes/functions.php';
verificarSesion('admin');

$titulo_pagina = 'Cierres de Ruta';
$icono_pagina = 'fas fa-calculator';

$salida_id = $_GET['salida_id'] ?? 0;
$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');

// Si se especifica una salida, trabajar con ella
if ($salida_id) {
    // Verificar que la salida existe
    $salida = obtenerRegistro("SELECT s.*, r.nombre as ruta_nombre, u.nombre as responsable 
                               FROM salidas_mercancia s 
                               JOIN rutas r ON s.ruta_id = r.id 
                               JOIN usuarios u ON s.usuario_id = u.id 
                               WHERE s.id = ?", [$salida_id]);
    
    if (!$salida) {
        $_SESSION['mensaje'] = 'Salida no encontrada';
        $_SESSION['tipo_mensaje'] = 'danger';
        header('Location: cierres.php');
        exit();
    }
    
    $fecha_filtro = date('Y-m-d', strtotime($salida['fecha_salida']));
}

// Obtener salidas del día que pueden ser cerradas
$sql_salidas_activas = "SELECT 
    s.id,
    s.estado,
    s.fecha_salida,
    s.observaciones,
    r.nombre as ruta_nombre,
    u.nombre as responsable,
    COUNT(DISTINCT ds.producto_id) as productos_salida,
    SUM(ds.cantidad) as unidades_salida,
    COUNT(DISTINCT f.id) as total_facturas,
    COALESCE(SUM(f.total), 0) as total_vendido
FROM salidas_mercancia s
JOIN rutas r ON s.ruta_id = r.id
JOIN usuarios u ON s.usuario_id = u.id
LEFT JOIN detalle_salidas ds ON s.id = ds.salida_id
LEFT JOIN facturas f ON s.id = f.salida_id
WHERE DATE(s.fecha_salida) = ? AND s.estado IN ('en_ruta', 'finalizada')
GROUP BY s.id
ORDER BY s.estado ASC, s.fecha_creacion DESC";

$salidas_activas = obtenerRegistros($sql_salidas_activas, [$fecha_filtro]);

// Procesar acciones
if ($_POST) {
    $accion = $_POST['accion'] ?? '';
    $salida_post_id = $_POST['salida_id'] ?? 0;
    
    switch ($accion) {
        case 'cerrar_ruta':
            try {
                // Ejecutar procedimiento de cierre automático
                ejecutarConsulta("CALL cerrar_ruta(?)", [$salida_post_id]);
                
                $_SESSION['mensaje'] = 'Ruta cerrada exitosamente';
                $_SESSION['tipo_mensaje'] = 'success';
            } catch (Exception $e) {
                $_SESSION['mensaje'] = 'Error al cerrar la ruta: ' . $e->getMessage();
                $_SESSION['tipo_mensaje'] = 'danger';
            }
            
            header('Location: cierres.php?fecha=' . $fecha_filtro);
            exit();
            break;
            
        case 'registrar_devolucion':
            $producto_id = $_POST['producto_id'] ?? 0;
            $cantidad_devuelta = $_POST['cantidad_devuelta'] ?? 0;
            
            if ($producto_id && $cantidad_devuelta >= 0) {
                // Verificar si ya existe una devolución para este producto
                $devolucion_existente = obtenerRegistro(
                    "SELECT id FROM devoluciones WHERE salida_id = ? AND producto_id = ?",
                    [$salida_post_id, $producto_id]
                );
                
                if ($devolucion_existente) {
                    // Actualizar
                    ejecutarConsulta(
                        "UPDATE devoluciones SET cantidad_devuelta = ? WHERE salida_id = ? AND producto_id = ?",
                        [$cantidad_devuelta, $salida_post_id, $producto_id]
                    );
                } else {
                    // Insertar nueva
                    ejecutarConsulta(
                        "INSERT INTO devoluciones (salida_id, producto_id, cantidad_devuelta) VALUES (?, ?, ?)",
                        [$salida_post_id, $producto_id, $cantidad_devuelta]
                    );
                }
                
                $_SESSION['mensaje'] = 'Devolución registrada correctamente';
                $_SESSION['tipo_mensaje'] = 'success';
            }
            
            header('Location: cierres.php?salida_id=' . $salida_post_id);
            exit();
            break;
        
        case 'agregar_gasto':
            $concepto = $_POST['concepto'] ?? '';
            $monto = $_POST['monto'] ?? 0;
            $fecha_gasto = $_POST['fecha_gasto'] ?? date('Y-m-d');
            $observaciones = $_POST['observaciones'] ?? '';
            
            if ($concepto && $monto > 0) {
                try {
                    ejecutarConsulta(
                        "INSERT INTO gastos_ruta (salida_id, concepto, monto, fecha_gasto, observaciones) 
                        VALUES (?, ?, ?, ?, ?)",
                        [$salida_post_id, $concepto, $monto, $fecha_gasto, $observaciones]
                    );
                    
                    $_SESSION['mensaje'] = 'Gasto registrado correctamente';
                    $_SESSION['tipo_mensaje'] = 'success';
                } catch (Exception $e) {
                    $_SESSION['mensaje'] = 'Error al registrar el gasto';
                    $_SESSION['tipo_mensaje'] = 'danger';
                }
            }
            
            header('Location: cierres.php?salida_id=' . $salida_post_id);
            exit();
            break;
        
    }    
}


include '../includes/header.php';
?>

<!-- Filtros y navegación -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body py-2">
                <form method="GET" action="">
                    <div class="row align-items-end">
                        <div class="col-8">
                            <label for="fecha" class="form-label">Fecha de Salidas</label>
                            <input type="date" class="form-control" id="fecha" name="fecha" 
                                   value="<?php echo htmlspecialchars($fecha_filtro); ?>">
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Salidas del <?php echo formatearFecha($fecha_filtro); ?></h6>
                        <small class="text-muted"><?php echo count($salidas_activas); ?> salidas</small>
                    </div>
                    <div>
                        <a href="salidas.php?fecha=<?php echo $fecha_filtro; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-truck"></i> Ver Salidas
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($salida_id && $salida): ?>
<!-- Detalle de cierre específico -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-clipboard-check"></i> 
            Cierre de Ruta: <?php echo htmlspecialchars($salida['ruta_nombre']); ?>
        </h6>
    </div>
    <div class="card-body">
        <!-- Información de la salida -->
        <div class="row mb-3">
            <div class="col-md-6">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td><strong>Responsable:</strong></td>
                        <td><?php echo htmlspecialchars($salida['responsable']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Estado:</strong></td>
                        <td>
                            <?php
                            $badge_class = $salida['estado'] == 'finalizada' ? 'success' : 'primary';
                            ?>
                            <span class="badge bg-<?php echo $badge_class; ?>">
                                <?php echo ESTADOS_SALIDA[$salida['estado']] ?? $salida['estado']; ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <?php if ($salida['observaciones']): ?>
                <div class="alert alert-info">
                    <strong>Observaciones:</strong><br>
                    <?php echo nl2br(htmlspecialchars($salida['observaciones'])); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Conciliación automática -->
        <?php
        // Obtener productos de la salida
        $productos_salida = obtenerDetalleSalida($salida_id);
        
        // Obtener resumen de ventas y devoluciones
        $sql_resumen = "SELECT 
            p.id,
            p.descripcion,
            ds.cantidad as salieron,
            COALESCE(SUM(df.cantidad), 0) as vendidos,
            ds.cantidad - COALESCE(SUM(df.cantidad), 0) as deben_regresar,
            COALESCE(dev.cantidad_devuelta, 0) as regresaron,
            (ds.cantidad - COALESCE(SUM(df.cantidad), 0)) - COALESCE(dev.cantidad_devuelta, 0) as diferencia
        FROM detalle_salidas ds
        JOIN productos p ON ds.producto_id = p.id
        LEFT JOIN detalle_facturas df ON df.producto_id = p.id 
            AND df.factura_id IN (SELECT id FROM facturas WHERE salida_id = ?)
        LEFT JOIN devoluciones dev ON dev.producto_id = p.id AND dev.salida_id = ?
        WHERE ds.salida_id = ?
        GROUP BY p.id, p.descripcion, ds.cantidad, dev.cantidad_devuelta
        ORDER BY p.descripcion";

        $productos_resumen = obtenerRegistros($sql_resumen, [$salida_id, $salida_id, $salida_id]);
        $hay_diferencias = false;
        foreach ($productos_resumen as $producto) {
            if ($producto['diferencia'] != 0) {
                $hay_diferencias = true;
                break;
            }
        }

        // Obtener detalle de facturas
        $sql_facturas = "SELECT f.*, c.nombre as cliente_nombre, 
                                COALESCE(f.cliente_nombre, 'Sin información') as cliente_libre,
                                COALESCE(f.cliente_ciudad, 'Sin información') as ciudad
                        FROM facturas f 
                        LEFT JOIN clientes c ON f.cliente_id = c.id
                        WHERE f.salida_id = ?
                        ORDER BY f.fecha_venta DESC";
        $facturas_detalle = obtenerRegistros($sql_facturas, [$salida_id]);
        // Calcular totales de efectivo que debe entregar el trabajador
        $sql_efectivo_esperado = "SELECT 
            SUM(CASE WHEN forma_pago = 'efectivo' THEN total ELSE 0 END) as efectivo_total,
            SUM(CASE WHEN forma_pago = 'transferencia' THEN total ELSE 0 END) as transferencia_total,
            COUNT(CASE WHEN forma_pago = 'efectivo' THEN 1 END) as facturas_efectivo,
            COUNT(CASE WHEN forma_pago = 'transferencia' THEN 1 END) as facturas_transferencia
        FROM facturas 
        WHERE salida_id = ?";
        $efectivo_esperado = obtenerRegistro($sql_efectivo_esperado, [$salida_id]);
        $productos_vendidos = obtenerProductosVendidosPorSalida($salida_id);

        $sql_facturas_detalle = "SELECT f.*, 
                                COALESCE(c.nombre, f.cliente_nombre, 'Cliente General') as cliente_nombre,
                                COALESCE(f.cliente_ciudad, 'Sin ciudad') as cliente_ciudad,
                                u.nombre as vendedor_nombre
                                FROM facturas f 
                                LEFT JOIN clientes c ON f.cliente_id = c.id
                                LEFT JOIN usuarios u ON f.vendedor_id = u.id
                                WHERE f.salida_id = ?
                                ORDER BY f.fecha_venta DESC";
        $facturas_detalle = obtenerRegistros($sql_facturas_detalle, [$salida_id]);
        
        // Obtener devoluciones registradas
        //no tiene la variable
        $sql_devoluciones = "SELECT producto_id, cantidad_devuelta 
                             FROM devoluciones 
                             WHERE salida_id = ?";
        $devoluciones_registradas = obtenerRegistros($sql_devoluciones, [$salida_id]);
        $devoluciones_array = [];
        foreach ($devoluciones_registradas as $dev) {
            $devoluciones_array[$dev['producto_id']] = $dev['cantidad_devuelta'];
        }
        
        // Obtener totales de facturas 
        $sql_totales = "SELECT 
            COUNT(id) as total_facturas,
            SUM(total) as total_vendido,
            SUM(CASE WHEN forma_pago = 'efectivo' THEN total ELSE 0 END) as total_efectivo,
            SUM(CASE WHEN forma_pago = 'transferencia' THEN total ELSE 0 END) as total_transferencia,
            SUM(CASE WHEN forma_pago = 'pendiente' THEN total ELSE 0 END) as total_pendiente
        FROM facturas WHERE salida_id = ?";
        $totales = obtenerRegistro($sql_totales, [$salida_id]) ?: [
            'total_facturas' => 0, 'total_vendido' => 0, 
            'total_efectivo' => 0, 'total_transferencia' => 0, 'total_pendiente' => 0
        ];
        ?>
        
        <!-- Resumen financiero -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-success text-center">
                    <div class="card-body py-2">
                        <h6 class="text-success mb-0"><?php echo formatearPrecio($totales['total_vendido']); ?></h6>
                        <small class="text-muted"><?php echo $totales['total_facturas']; ?> facturas</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-primary text-center">
                    <div class="card-body py-2">
                        <h6 class="text-primary mb-0"><?php echo formatearPrecio($totales['total_efectivo']); ?></h6>
                        <small class="text-muted">Efectivo</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info text-center">
                    <div class="card-body py-2">
                        <h6 class="text-info mb-0"><?php echo formatearPrecio($totales['total_transferencia']); ?></h6>
                        <small class="text-muted">Transferencias</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning text-center">
                    <div class="card-body py-2">
                        <h6 class="text-warning mb-0"><?php echo formatearPrecio($totales['total_pendiente']); ?></h6>
                        <small class="text-muted">Pendientes</small>
                    </div>
                </div>
            </div>
        </div>
        
       <!-- Conciliación de Productos -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-balance-scale"></i> Conciliación de Productos
        </h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Producto</th>
                        <th class="text-center">Salió</th>
                        <th class="text-center">Vendido</th>
                        <th class="text-center">Debe Regresar</th>
                        <th class="text-center">Registrado</th>
                        <th class="text-center">Diferencia</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos_resumen as $producto): 
                        $diferencia = $producto['diferencia'];
                        $clase_diferencia = $diferencia == 0 ? 'text-success' : 
                                           ($diferencia > 0 ? 'text-danger' : 'text-warning');
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($producto['descripcion']); ?></strong>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary"><?php echo $producto['salieron']; ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success"><?php echo $producto['vendidos']; ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info"><?php echo $producto['deben_regresar']; ?></span>
                        </td>   
                        <td class="text-center">
                            <input type="number" 
                                class="form-control form-control-sm cantidad-regresada" 
                                value="<?php echo $producto['regresaron'] ?? 0; ?>"
                                min="0"
                                max="<?php echo $producto['deben_regresar']; ?>"
                                data-producto-id="<?php echo $producto['id']; ?>"
                                style="width: 80px; margin: 0 auto;" />
                        </td>
                        <td class="text-center">
                            <strong class="<?php echo $clase_diferencia; ?>">
                                <?php echo $diferencia > 0 ? '+' : ''; ?><?php echo $diferencia; ?>
                            </strong>
                        </td>
                        <td>
                            <?php if ($diferencia != 0): ?>
                            <button class="btn btn-warning btn-sm" 
                                    onclick="registrarIncidencia(<?php echo $producto['id']; ?>);">
                                <i class="fas fa-exclamation-triangle"></i>
                            </button>
                            <?php else: ?>
                            <span class="text-success"><i class="fas fa-check"></i></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Botón para guardar devoluciones -->
        <div class="text-end mt-3">
            <button class="btn btn-primary" onclick="guardarDevoluciones()">
                <i class="fas fa-save"></i> Guardar Devoluciones
            </button>
        </div>
    </div>
</div>

<!-- Lista de Facturas Detalladas -->
<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-file-invoice"></i> Facturas de la Ruta
        </h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cliente</th>
                        <th>Ciudad</th>
                        <th>Vendedor</th>
                        <th>Total</th>
                        <th>Forma Pago</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($facturas_detalle as $factura): ?>
                    <tr>
                        <td><?php echo $factura['numero_factura']; ?></td>
                        <td>
                            <?php 
                            echo $factura['cliente_id'] ? 
                                htmlspecialchars($factura['cliente_nombre']) : 
                                htmlspecialchars($factura['cliente_libre']);
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($factura['cliente_ciudad'] ?? 'Sin especificar'); ?></td>
                        <td><?php echo obtenerUsuarioPorId($factura['vendedor_id'])['nombre']; ?></td>
                        <td><?php echo formatearPrecio($factura['total']); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $factura['forma_pago'] == 'efectivo' ? 'success' : 
                                    ($factura['forma_pago'] == 'transferencia' ? 'info' : 'warning'); 
                            ?>">
                                <?php echo ucfirst($factura['forma_pago']); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" 
                                    onclick="verDetalleFactura(<?php echo $factura['id']; ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Incluir modal de detalle -->
<?php include '../includes/modal_detalle_factura.php'; ?>

<!-- Incluir sección de gastos -->
<?php include '../includes/seccion_gastos_ruta.php'; ?>

<script>
function guardarDevoluciones() {
    const devoluciones = [];
    
    $('.cantidad-regresada').each(function() {
        devoluciones.push({
            producto_id: $(this).data('producto-id'),
            cantidad: $(this).val()
        });
    });
    
    $.ajax({
        url: '../includes/guardar_devoluciones.php',
        method: 'POST',
        data: {
            salida_id: <?php echo $salida_id; ?>,
            devoluciones: JSON.stringify(devoluciones)
        },
        success: function(response) {
            if (response.success) {
                mostrarAlerta('Devoluciones guardadas correctamente', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                mostrarAlerta('Error al guardar devoluciones', 'danger');
            }
        }
    });
}

function registrarIncidencia(productoId) {
    const observacion = prompt('Describa la incidencia:');
    if (observacion) {
        // Implementar registro de incidencias
        console.log('Incidencia registrada:', productoId, observacion);
    }
}
</script>
        
        <!-- Acciones de cierre -->
        <div class="row mt-4">
            <div class="col-md-8">
                <?php if ($hay_diferencias): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Atención:</strong> Hay diferencias en la conciliación. 
                        Ajuste las devoluciones antes de cerrar.
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <strong>Perfecto:</strong> La conciliación está correcta. 
                        Puede cerrar la ruta.
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-4 text-end">
                <div class="d-grid gap-2">
                    <?php if ($salida['estado'] != 'finalizada'): ?>
                        <button class="btn btn-success" 
                                onclick="cerrarRuta(<?php echo $salida_id; ?>)"
                                <?php echo $hay_diferencias ? 'disabled' : ''; ?>>
                            <i class="fas fa-check"></i> Cerrar Ruta
                        </button>
                    <?php else: ?>
                        <span class="badge bg-success fs-6">Ruta Cerrada</span>
                    <?php endif; ?>
                    <a href="cierres.php?fecha=<?php echo $fecha_filtro; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Lista de salidas para cierre -->
<div class="card">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-list"></i> Salidas Disponibles para Cierre
        </h6>
    </div>
    <div class="card-body">
        <?php if (empty($salidas_activas)): ?>
            <div class="text-center py-5">
                <i class="fas fa-truck fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No hay salidas para cerrar</h5>
                <p class="text-muted">No se encontraron salidas activas para esta fecha</p>
                <a href="salidas.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Gestionar Salidas
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
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
                        <?php foreach ($salidas_activas as $salida_item): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($salida_item['ruta_nombre']); ?></strong></td>
                            <td><?php echo htmlspecialchars($salida_item['responsable']); ?></td>
                            <td>
                                <?php
                                $badge_class = $salida_item['estado'] == 'finalizada' ? 'success' : 'primary';
                                ?>
                                <span class="badge bg-<?php echo $badge_class; ?>">
                                    <?php echo ESTADOS_SALIDA[$salida_item['estado']] ?? $salida_item['estado']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo $salida_item['productos_salida']; ?></span>
                                <br><small class="text-muted"><?php echo $salida_item['unidades_salida']; ?> und.</small>
                            </td>
                            <td>
                                <span class="badge bg-success"><?php echo $salida_item['total_facturas']; ?></span>
                            </td>
                            <td>
                                <strong class="text-success"><?php echo formatearPrecio($salida_item['total_vendido']); ?></strong>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="cierres.php?salida_id=<?php echo $salida_item['id']; ?>" 
                                       class="btn btn-outline-primary" title="Cerrar ruta">
                                        <i class="fas fa-calculator"></i>
                                    </a>
                                    <a href="detalle_salida.php?id=<?php echo $salida_item['id']; ?>" 
                                       class="btn btn-outline-info" title="Ver detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
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
<?php endif; ?>

<!-- Formularios ocultos -->
<form id="form-cerrar-ruta" method="POST" style="display: none;">
    <input type="hidden" name="accion" value="cerrar_ruta">
    <input type="hidden" name="salida_id" id="salida-id-cerrar">
</form>

<form id="form-devolucion" method="POST" style="display: none;">
    <input type="hidden" name="accion" value="registrar_devolucion">
    <input type="hidden" name="salida_id" value="<?php echo $salida_id; ?>">
    <input type="hidden" name="producto_id" id="producto-id-devolucion">
    <input type="hidden" name="cantidad_devuelta" id="cantidad-devolucion">
</form>

<!-- Modal para ajustar devolución -->
<div class="modal fade" id="modal-ajustar-devolucion" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajustar Devolución</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Producto:</label>
                    <div id="producto-nombre-modal" class="fw-bold"></div>
                </div>
                <div class="mb-3">
                    <label for="cantidad-modal" class="form-label">Cantidad que regresa:</label>
                    <input type="number" class="form-control" id="cantidad-modal" min="0" step="1">
                    <div class="form-text">
                        Cantidad de unidades que físicamente regresaron
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="confirmarAjusteDevolucion()">
                    Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let productoEditando = 0;

function cerrarRuta(salidaId) {
    if (confirm('¿Está seguro de que desea cerrar esta ruta?\n\nEsta acción calculará automáticamente las devoluciones y finalizará la ruta.')) {
        document.getElementById('salida-id-cerrar').value = salidaId;
        document.getElementById('form-cerrar-ruta').submit();
    }
}

function ajustarDevolucion(productoId, cantidadSugerida, nombreProducto) {
    productoEditando = productoId;
    document.getElementById('producto-nombre-modal').textContent = nombreProducto;
    document.getElementById('cantidad-modal').value = cantidadSugerida;
    
    const modal = new bootstrap.Modal(document.getElementById('modal-ajustar-devolucion'));
    modal.show();
}

function confirmarAjusteDevolucion() {
    const cantidad = document.getElementById('cantidad-modal').value;
    
    if (cantidad < 0) {
        alert('La cantidad no puede ser negativa');
        return;
    }
    
    document.getElementById('producto-id-devolucion').value = productoEditando;
    document.getElementById('cantidad-devolucion').value = cantidad;
    document.getElementById('form-devolucion').submit();
}

// Auto-refresh cada 3 minutos
setInterval(function() {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 180000);
</script>

<?php include '../includes/footer.php'; ?>