<?php
// =====================================================
// DETALLE DE SALIDA DE MERCANCÍA - SISTEMA METRU
// =====================================================

include_once '../includes/functions.php';
verificarSesion('admin');

$salida_id = $_GET['id'] ?? 0;

if (!$salida_id) {
    $_SESSION['mensaje'] = 'ID de salida no válido';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: salidas.php');
    exit();
}

// Obtener información de la salida
$sql_salida = "SELECT 
    s.*,
    r.nombre as ruta_nombre,
    u.nombre as responsable_nombre,
    u.codigo_usuario as responsable_codigo
FROM salidas_mercancia s
JOIN rutas r ON s.ruta_id = r.id
JOIN usuarios u ON s.usuario_id = u.id
WHERE s.id = ?";

$salida = obtenerRegistro($sql_salida, [$salida_id]);

if (!$salida) {
    $_SESSION['mensaje'] = 'Salida no encontrada';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: salidas.php');
    exit();
}

// Obtener productos de la salida
$productos_salida = obtenerDetalleSalida($salida_id);

// Obtener facturas de la salida
$facturas = obtenerFacturasPorSalida($salida_id);

// Obtener productos vendidos
$productos_vendidos = obtenerProductosVendidosPorSalida($salida_id);

// Calcular devoluciones esperadas
$devoluciones_esperadas = calcularDevolucionesEsperadas($salida_id);

// Obtener totales
$total_productos_salida = count($productos_salida);
$total_unidades_salida = array_sum(array_column($productos_salida, 'cantidad'));
$total_facturas = count($facturas);
$total_vendido = array_sum(array_column($facturas, 'total'));

// Procesar acciones POST
if ($_POST) {
    $accion = $_POST['accion'] ?? '';
    
    switch ($accion) {
        case 'actualizar_cantidad':
            if ($salida['estado'] == 'preparando') {
                $producto_id = $_POST['producto_id'] ?? 0;
                $nueva_cantidad = $_POST['nueva_cantidad'] ?? 0;
                
                if ($nueva_cantidad > 0) {
                    ejecutarConsulta(
                        "UPDATE detalle_salidas SET cantidad = ? WHERE salida_id = ? AND producto_id = ?",
                        [$nueva_cantidad, $salida_id, $producto_id]
                    );
                    $_SESSION['mensaje'] = 'Cantidad actualizada correctamente';
                    $_SESSION['tipo_mensaje'] = 'success';
                } else {
                    ejecutarConsulta(
                        "DELETE FROM detalle_salidas WHERE salida_id = ? AND producto_id = ?",
                        [$salida_id, $producto_id]
                    );
                    $_SESSION['mensaje'] = 'Producto eliminado de la salida';
                    $_SESSION['tipo_mensaje'] = 'success';
                }
                
                header('Location: detalle_salida.php?id=' . $salida_id);
                exit();
            }
            break;
            
        case 'cambiar_estado':
            $nuevo_estado = $_POST['nuevo_estado'] ?? '';
            if (in_array($nuevo_estado, ['preparando', 'en_ruta', 'finalizada'])) {
                actualizarEstadoSalida($salida_id, $nuevo_estado);
                $_SESSION['mensaje'] = 'Estado actualizado correctamente';
                $_SESSION['tipo_mensaje'] = 'success';
                
                header('Location: detalle_salida.php?id=' . $salida_id);
                exit();
            }
            break;
    }
}

$titulo_pagina = 'Detalle de Salida #' . $salida_id;
$icono_pagina = 'fas fa-clipboard-list';

include '../includes/header.php';
?>

<!-- Información principal -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-info-circle"></i> 
                    Información de la Salida #<?php echo $salida_id; ?>
                </h6>
                <?php
                $badges = [
                    'preparando' => ['class' => 'secondary', 'icon' => 'fa-clock'],
                    'en_ruta' => ['class' => 'primary', 'icon' => 'fa-truck'],
                    'finalizada' => ['class' => 'success', 'icon' => 'fa-check-circle']
                ];
                $badge = $badges[$salida['estado']] ?? ['class' => 'secondary', 'icon' => 'fa-question'];
                ?>
                <span class="badge bg-<?php echo $badge['class']; ?> fs-6">
                    <i class="fas <?php echo $badge['icon']; ?>"></i>
                    <?php echo ESTADOS_SALIDA[$salida['estado']] ?? $salida['estado']; ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Ruta:</strong></td>
                                <td><?php echo htmlspecialchars($salida['ruta_nombre']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Responsable:</strong></td>
                                <td>
                                    <?php echo htmlspecialchars($salida['responsable_nombre']); ?>
                                    <small class="text-muted">(<?php echo $salida['responsable_codigo']; ?>)</small>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Fecha Salida:</strong></td>
                                <td><?php echo formatearFecha($salida['fecha_salida']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Fecha Creación:</strong></td>
                                <td><?php echo formatearFechaHora($salida['fecha_creacion']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Total Productos:</strong></td>
                                <td><span class="badge bg-info"><?php echo $total_productos_salida; ?></span></td>
                            </tr>
                            <tr>
                                <td><strong>Total Unidades:</strong></td>
                                <td><strong><?php echo $total_unidades_salida; ?></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($salida['observaciones']): ?>
                <div class="alert alert-info">
                    <strong>Observaciones:</strong><br>
                    <?php echo nl2br(htmlspecialchars($salida['observaciones'])); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Panel de acciones -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-cogs"></i> Acciones
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <!-- Cambiar estado -->
                    <?php if ($salida['estado'] == 'preparando'): ?>
                        <button class="btn btn-success" onclick="cambiarEstado('en_ruta')">
                            <i class="fas fa-play"></i> Enviar a Ruta
                        </button>
                    <?php elseif ($salida['estado'] == 'en_ruta'): ?>
                        <button class="btn btn-warning" onclick="cambiarEstado('finalizada')">
                            <i class="fas fa-stop"></i> Finalizar Ruta
                        </button>
                        <button class="btn btn-secondary" onclick="cambiarEstado('preparando')">
                            <i class="fas fa-undo"></i> Regresar a Preparando
                        </button>
                    <?php endif; ?>
                    <!-- Agregar después del botón "Enviar a Ruta" -->
                    <?php if ($salida['estado'] == 'preparando'): ?>
                        <a href="cargar_camion.php?salida_id=<?php echo $salida_id; ?>" 
                        class="btn btn-warning">
                            <i class="fas fa-truck-loading"></i> Cargar Camión
                        </a>
                    <?php endif; ?>
                    
                    <!-- Imprimir -->
                    <a href="imprimir_salida.php?id=<?php echo $salida_id; ?>" 
                       target="_blank" class="btn btn-outline-primary">
                        <i class="fas fa-print"></i> Imprimir Salida
                    </a>
                
                    
                    <!-- Volver -->
                    <a href="salidas.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver a Lista
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Estadísticas -->
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-chart-pie"></i> Estadísticas
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h4 class="text-primary"><?php echo $total_facturas; ?></h4>
                            <small class="text-muted">Facturas</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success"><?php echo formatearPrecio($total_vendido); ?></h4>
                        <small class="text-muted">Total Vendido</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Productos de la salida -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-boxes"></i> Productos Cargados
        </h6>
        <span class="badge bg-primary"><?php echo count($productos_salida); ?> productos</span>
    </div>
    <div class="card-body">
        <?php if (empty($productos_salida)): ?>
            <div class="text-center text-muted py-4">
                <i class="fas fa-box-open fa-3x mb-3"></i>
                <p>No hay productos en esta salida</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Unidad</th>
                            <th>Cantidad Salida</th>
                            <th>Cantidad Vendida</th>
                            <th>Cantidad Esperada Regreso</th>
                            <th>Precio Ref.</th>
                            <?php if ($salida['estado'] == 'preparando'): ?>
                            <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_valor_referencial = 0;
                        foreach ($productos_salida as $producto): 
                            // Buscar cantidad vendida
                            $cantidad_vendida = 0;
                            foreach ($productos_vendidos as $vendido) {
                                if ($vendido['id'] == $producto['id']) {
                                    $cantidad_vendida = $vendido['cantidad_vendida'];
                                    break;
                                }
                            }
                            
                            $cantidad_esperada = $producto['cantidad'] - $cantidad_vendida;
                            $valor_linea = $producto['cantidad'] * $producto['precio_publico'];
                            $total_valor_referencial += $valor_linea;
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($producto['descripcion']); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark">
                                    <?php echo $producto['unidad_medida']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-primary">
                                    <?php echo $producto['cantidad']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-success">
                                    <?php echo $cantidad_vendida; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($cantidad_esperada > 0): ?>
                                    <span class="badge bg-warning">
                                        <?php echo $cantidad_esperada; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success">
                                        0
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo formatearPrecio($producto['precio_publico']); ?>
                                </small>
                                <br>
                                <strong><?php echo formatearPrecio($valor_linea); ?></strong>
                            </td>
                            <?php if ($salida['estado'] == 'preparando'): ?>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-warning" 
                                            onclick="editarCantidad(<?php echo $producto['id']; ?>, <?php echo $producto['cantidad']; ?>)"
                                            title="Editar cantidad">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" 
                                            onclick="eliminarProducto(<?php echo $producto['id']; ?>)"
                                            title="Eliminar producto">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-secondary">
                            <th colspan="5">Total Valor Referencial:</th>
                            <th><?php echo formatearPrecio($total_valor_referencial); ?></th>
                            <?php if ($salida['estado'] == 'preparando'): ?>
                            <th></th>
                            <?php endif; ?>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Facturas creadas -->
<?php if (!empty($facturas)): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-receipt"></i> Facturas Creadas
        </h6>
        <span class="badge bg-success"><?php echo count($facturas); ?> facturas</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th>Forma Pago</th>
                        <th>Total</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($facturas as $factura): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($factura['numero_factura']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($factura['cliente_nombre']); ?></td>
                        <td><?php echo htmlspecialchars($factura['vendedor_nombre']); ?></td>
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
                            <strong><?php echo formatearPrecio($factura['total']); ?></strong>
                        </td>
                        <td>
                            <small><?php echo formatearFechaHora($factura['fecha_venta']); ?></small>
                        </td>
                        <td>
                            <a href="detalle_factura.php?id=<?php echo $factura['id']; ?>" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Formularios ocultos -->
<form id="form-cambiar-estado" method="POST" style="display: none;">
    <input type="hidden" name="accion" value="cambiar_estado">
    <input type="hidden" name="nuevo_estado" id="nuevo-estado">
</form>

<form id="form-actualizar-cantidad" method="POST" style="display: none;">
    <input type="hidden" name="accion" value="actualizar_cantidad">
    <input type="hidden" name="producto_id" id="producto-id">
    <input type="hidden" name="nueva_cantidad" id="nueva-cantidad">
</form>

<!-- Modal para editar cantidad -->
<div class="modal fade" id="modal-editar-cantidad" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Cantidad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="cantidad-modal" class="form-label">Nueva Cantidad</label>
                    <input type="number" class="form-control" id="cantidad-modal" min="0" step="1">
                    <div class="form-text">
                        Ingrese 0 para eliminar el producto de la salida
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="confirmarCambiarCantidad()">
                    Actualizar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let productoEditando = 0;

function cambiarEstado(nuevoEstado) {
    const mensajes = {
        'en_ruta': '¿Confirma que desea enviar esta salida a ruta?',
        'finalizada': '¿Confirma que desea finalizar esta ruta?',
        'preparando': '¿Confirma que desea regresar la salida a estado de preparación?'
    };
    
    if (confirm(mensajes[nuevoEstado] || '¿Confirma el cambio de estado?')) {
        document.getElementById('nuevo-estado').value = nuevoEstado;
        document.getElementById('form-cambiar-estado').submit();
    }
}

function editarCantidad(productoId, cantidadActual) {
    productoEditando = productoId;
    document.getElementById('cantidad-modal').value = cantidadActual;
    
    const modal = new bootstrap.Modal(document.getElementById('modal-editar-cantidad'));
    modal.show();
}

function confirmarCambiarCantidad() {
    const nuevaCantidad = document.getElementById('cantidad-modal').value;
    
    if (nuevaCantidad < 0) {
        alert('La cantidad no puede ser negativa');
        return;
    }
    
    document.getElementById('producto-id').value = productoEditando;
    document.getElementById('nueva-cantidad').value = nuevaCantidad;
    document.getElementById('form-actualizar-cantidad').submit();
}

function eliminarProducto(productoId) {
    if (confirm('¿Está seguro de que desea eliminar este producto de la salida?')) {
        document.getElementById('producto-id').value = productoId;
        document.getElementById('nueva-cantidad').value = 0;
        document.getElementById('form-actualizar-cantidad').submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>