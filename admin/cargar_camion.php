<?php
// =====================================================
// INTERFAZ DE CARGA DE CAMIÓN - SISTEMA METRU
// =====================================================

include_once '../includes/functions.php';
include_once '../config/config.php'; 
verificarSesion('admin');

$titulo_pagina = 'Cargar Camión';
$icono_pagina = 'fas fa-truck-loading';

$salida_id = $_GET['salida_id'] ?? 0;

if (!$salida_id) {
    $_SESSION['mensaje'] = 'Debe seleccionar una salida';
    $_SESSION['tipo_mensaje'] = 'warning';
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
WHERE s.id = ? AND s.estado = 'preparando'";

$salida = obtenerRegistro($sql_salida, [$salida_id]);

if (!$salida) {
    $_SESSION['mensaje'] = 'Salida no encontrada o ya fue enviada';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: salidas.php');
    exit();
}

// Obtener productos de la salida
$sql_productos = "SELECT 
    ds.*,
    p.codigo,
    p.descripcion,
    p.unidad_medida,
    p.grupo_id,
    COALESCE(ds.cargado, 0) as cargado
FROM detalle_salidas ds
JOIN productos p ON ds.producto_id = p.id
WHERE ds.salida_id = ?
ORDER BY p.grupo_id, p.descripcion";

$productos = obtenerRegistros($sql_productos, [$salida_id]);

// Agrupar productos por grupo
$productos_por_grupo = [];
foreach ($productos as $producto) {
    $grupo = GRUPOS_PRODUCTOS[$producto['grupo_id']] ?? 'Otros';
    if (!isset($productos_por_grupo[$grupo])) {
        $productos_por_grupo[$grupo] = [];
    }
    $productos_por_grupo[$grupo][] = $producto;
}

// Calcular estadísticas
$total_productos = count($productos);
$productos_cargados = count(array_filter($productos, fn($p) => $p['cargado'] == 1));
$porcentaje_carga = $total_productos > 0 ? round(($productos_cargados / $total_productos) * 100) : 0;

// Procesar acciones
if ($_POST) {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion == 'marcar_cargado') {
        $producto_id = $_POST['producto_id'] ?? 0;
        $cargado = $_POST['cargado'] ?? 0;
        
        // Actualizar estado de carga
        ejecutarConsulta(
            "UPDATE detalle_salidas SET cargado = ? WHERE salida_id = ? AND producto_id = ?",
            [$cargado, $salida_id, $producto_id]
        );
        
        // Respuesta JSON para AJAX
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    }
    
    if ($accion == 'finalizar_carga') {
        // Verificar que todo esté cargado
        $sql_verificar = "SELECT COUNT(*) as pendientes FROM detalle_salidas WHERE salida_id = ? AND cargado = 0";
        $verificacion = obtenerRegistro($sql_verificar, [$salida_id]);
        
        if ($verificacion['pendientes'] > 0) {
            $_SESSION['mensaje'] = 'Aún hay productos pendientes de cargar';
            $_SESSION['tipo_mensaje'] = 'warning';
        } else {
            // Cambiar estado a en_ruta
            actualizarEstadoSalida($salida_id, 'en_ruta');
            $_SESSION['mensaje'] = 'Carga completada. La ruta está lista para salir';
            $_SESSION['tipo_mensaje'] = 'success';
            header('Location: detalle_salida.php?id=' . $salida_id);
            exit();
        }
    }
}

include '../includes/header.php';
?>

<style>
.producto-row {
    transition: all 0.3s ease;
    cursor: pointer;
}

.producto-row:hover {
    background-color: #f8f9fa;
}

.producto-cargado {
    background-color: #d4edda !important;
    opacity: 0.8;
}

.grupo-header {
    background-color: #e9ecef;
    font-weight: bold;
    padding: 10px;
    margin-top: 20px;
    margin-bottom: 10px;
    border-radius: 8px;
}

.check-grande {
    width: 25px;
    height: 25px;
    cursor: pointer;
}

.progress {
    height: 30px;
}

.tabla-carga {
    font-size: 1.1rem;
}

@media print {
    .no-print {
        display: none !important;
    }
    
    .producto-cargado {
        background-color: #ddd !important;
        -webkit-print-color-adjust: exact;
    }
}

.sticky-top {
    top: 70px;
    z-index: 100;
}
</style>

<!-- Información de la salida -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h6 class="m-0">
                    <i class="fas fa-route"></i> 
                    Información de la Ruta
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Ruta:</strong> <?php echo htmlspecialchars($salida['ruta_nombre']); ?></p>
                        <p><strong>Responsable:</strong> <?php echo htmlspecialchars($salida['responsable_nombre']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Fecha:</strong> <?php echo formatearFecha($salida['fecha_salida']); ?></p>
                        <p><strong>Código:</strong> #<?php echo str_pad($salida_id, 6, '0', STR_PAD_LEFT); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card sticky-top">
            <div class="card-header bg-success text-white">
                <h6 class="m-0">
                    <i class="fas fa-clipboard-check"></i> 
                    Progreso de Carga
                </h6>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <h2 class="text-success"><?php echo $productos_cargados; ?> / <?php echo $total_productos; ?></h2>
                    <small class="text-muted">Productos cargados</small>
                </div>
                
                <div class="progress mb-3">
                    <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                         role="progressbar" 
                         style="width: <?php echo $porcentaje_carga; ?>%">
                        <?php echo $porcentaje_carga; ?>%
                    </div>
                </div>
                
                <div class="d-grid gap-2 no-print">
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Imprimir Lista
                    </button>
                    
                    <?php if ($porcentaje_carga == 100): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="accion" value="finalizar_carga">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-check-circle"></i> Finalizar Carga
                        </button>
                    </form>
                    <?php else: ?>
                    <button class="btn btn-secondary" disabled>
                        <i class="fas fa-lock"></i> Complete la carga
                    </button>
                    <?php endif; ?>
                    
                    <a href="detalle_salida.php?id=<?php echo $salida_id; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lista de productos para cargar -->
<div class="card">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-boxes"></i> 
            Lista de Productos para Cargar
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table tabla-carga mb-0">
                <?php foreach ($productos_por_grupo as $grupo => $productos_grupo): ?>
                <thead>
                    <tr>
                        <th colspan="5" class="grupo-header">
                            <i class="fas fa-layer-group"></i> <?php echo $grupo; ?>
                            <span class="badge bg-secondary float-end">
                                <?php echo count($productos_grupo); ?> productos
                            </span>
                        </th>
                    </tr>
                    <tr class="table-secondary">
                        <th width="50">✓</th>
                        <th width="100">Código</th>
                        <th>Producto</th>
                        <th width="120">Cantidad</th>
                        <th width="100">Unidad</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos_grupo as $producto): ?>
                    <tr class="producto-row <?php echo $producto['cargado'] ? 'producto-cargado' : ''; ?>" 
                        data-producto-id="<?php echo $producto['producto_id']; ?>"
                        onclick="toggleProducto(<?php echo $producto['producto_id']; ?>)">
                        <td class="text-center">
                            <input type="checkbox" 
                                   class="form-check-input check-grande" 
                                   data-producto-id="<?php echo $producto['producto_id']; ?>"
                                   <?php echo $producto['cargado'] ? 'checked' : ''; ?>
                                   onclick="event.stopPropagation();">
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($producto['codigo']); ?></strong>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($producto['descripcion']); ?>
                            <?php if ($producto['cargado']): ?>
                                <span class="badge bg-success float-end">
                                    <i class="fas fa-check"></i> Cargado
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <h5 class="mb-0">
                                <span class="badge bg-primary">
                                    <?php echo $producto['cantidad']; ?>
                                </span>
                            </h5>
                        </td>
                        <td>
                            <span class="badge bg-secondary">
                                <?php echo $producto['unidad_medida']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>

<!-- Modal de confirmación -->
<div class="modal fade" id="modalConfirmacion" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Carga</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="mensaje-confirmacion"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-confirmar-carga">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<script>
let productoActual = 0;

function toggleProducto(productoId) {
    const checkbox = $(`.check-grande[data-producto-id="${productoId}"]`);
    checkbox.prop('checked', !checkbox.prop('checked'));
    marcarProducto(productoId, checkbox.prop('checked'));
}

function marcarProducto(productoId, cargado) {
    // Actualizar visualmente
    const fila = $(`.producto-row[data-producto-id="${productoId}"]`);
    
    if (cargado) {
        fila.addClass('producto-cargado');
        fila.find('.badge').remove();
        fila.find('td:nth-child(3)').append('<span class="badge bg-success float-end"><i class="fas fa-check"></i> Cargado</span>');
    } else {
        fila.removeClass('producto-cargado');
        fila.find('.badge').remove();
    }
    
    // Guardar en base de datos
    $.ajax({
        url: '',
        method: 'POST',
        data: {
            accion: 'marcar_cargado',
            producto_id: productoId,
            cargado: cargado ? 1 : 0
        },
        success: function(response) {
            actualizarProgreso();
        }
    });
}

// Manejar cambios en checkboxes
$('.check-grande').on('change', function(e) {
    e.stopPropagation();
    const productoId = $(this).data('producto-id');
    const cargado = $(this).prop('checked');
    marcarProducto(productoId, cargado);
});

function actualizarProgreso() {
    const total = $('.check-grande').length;
    const cargados = $('.check-grande:checked').length;
    const porcentaje = Math.round((cargados / total) * 100);
    
    // Actualizar contador
    $('.text-success h2').text(`${cargados} / ${total}`);
    
    // Actualizar barra de progreso
    $('.progress-bar')
        .css('width', porcentaje + '%')
        .text(porcentaje + '%');
    
    // Habilitar/deshabilitar botón de finalizar
    if (porcentaje === 100) {
        $('button:contains("Complete la carga")')
            .removeClass('btn-secondary')
            .addClass('btn-success')
            .prop('disabled', false)
            .html('<i class="fas fa-check-circle"></i> Finalizar Carga');
    }
}

// Atajos de teclado
$(document).keydown(function(e) {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        // Marcar el siguiente producto no cargado
        const siguiente = $('.producto-row:not(.producto-cargado)').first();
        if (siguiente.length) {
            siguiente.click();
            // Hacer scroll hasta el producto
            $('html, body').animate({
                scrollTop: siguiente.offset().top - 100
            }, 300);
        }
    }
});

// Auto-guardar cada 30 segundos
setInterval(function() {
    // Aquí podrías implementar auto-guardado si lo necesitas
}, 30000);

// Resaltar fila al pasar el mouse
$('.producto-row').hover(
    function() { $(this).addClass('table-active'); },
    function() { $(this).removeClass('table-active'); }
);

// Confirmación antes de finalizar
$('form').on('submit', function(e) {
    if (!confirm('¿Está seguro de que todos los productos han sido cargados correctamente?')) {
        e.preventDefault();
        return false;
    }
});
</script>

<?php include '../includes/footer.php'; ?>          