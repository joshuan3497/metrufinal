<?php
// =====================================================
// CREAR FACTURA MÓVIL - SISTEMA METRU
// =====================================================

include_once '../includes/functions.php';
verificarSesion('trabajador');

$titulo_pagina = 'Nueva Factura';
$icono_pagina = 'fas fa-plus-circle';
$sin_sidebar = true;

$usuario_id = $_SESSION['usuario_id'];
$salida_id = $_GET['salida_id'] ?? 0;

// Verificar que la salida existe y pertenece al trabajador
if (!$salida_id) {
    $_SESSION['mensaje'] = 'ID de salida no válido';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: index.php');
    exit();
}

// Verificar acceso a la salida
if (!validarAccesoRuta($usuario_id, $salida_id)) {
    $_SESSION['mensaje'] = 'No tiene acceso a esta salida';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: index.php');
    exit();
}

// Verificar que la ruta esté activa
if (!validarRutaActiva($salida_id)) {
    $_SESSION['mensaje'] = 'La ruta no está activa';
    $_SESSION['tipo_mensaje'] = 'warning';
    header('Location: index.php');
    exit();
}

// Obtener información de la salida
$sql_salida = "SELECT s.*, r.nombre as ruta_nombre 
               FROM salidas_mercancia s 
               JOIN rutas r ON s.ruta_id = r.id 
               WHERE s.id = ?";
$salida = obtenerRegistro($sql_salida, [$salida_id]);

// Obtener clientes de la ruta
$clientes = obtenerRegistros("SELECT c.* FROM clientes c 
                              JOIN rutas r ON c.ruta_id = r.id 
                              JOIN salidas_mercancia s ON r.id = s.ruta_id 
                              WHERE s.id = ? AND c.activo = 1 
                              ORDER BY c.nombre", [$salida_id]);

// Obtener productos disponibles en esta salida con cantidades
$sql_productos = "SELECT p.*, sd.cantidad as cantidad_disponible,
                  sd.cantidad - COALESCE(
                      (SELECT SUM(fd.cantidad) 
                       FROM detalle_facturas fd 
                       JOIN facturas f ON fd.factura_id = f.id 
                       WHERE f.salida_id = ? AND fd.producto_id = p.id), 0
                  ) as cantidad_restante
                  FROM productos p
                  JOIN detalle_salidas sd ON p.id = sd.producto_id
                  WHERE sd.salida_id = ? AND p.activo = 1
                  ORDER BY p.descripcion";
$productos_disponibles = obtenerRegistros($sql_productos, [$salida_id, $salida_id]);

// Procesar formulario
if ($_POST && isset($_POST['accion']) && $_POST['accion'] == 'crear_factura') {
    $forma_pago = $_POST['forma_pago'] ?? '';
    $observaciones = $_POST['observaciones'] ?? '';
    $productos_factura = $_POST['productos'] ?? [];
    $tipo_cliente = $_POST['tipo_cliente'] ?? 'libre';
    
    // Manejo del cliente
    $cliente_id = null;
    $cliente_nombre = null;
    $cliente_ciudad = null;
    
    if ($tipo_cliente == 'existente' && !empty($_POST['cliente_id'])) {
        $cliente_id = $_POST['cliente_id'];
    } else {
        // Cliente libre
        $cliente_nombre = trim($_POST['cliente_nombre'] ?? '');
        $cliente_ciudad = trim($_POST['cliente_ciudad'] ?? '');
        
        if (empty($cliente_nombre)) {
            $cliente_nombre = 'Cliente General';
        }
        if (empty($cliente_ciudad)) {
            $cliente_ciudad = 'Sin especificar';
        }
    }
    
    // Validaciones básicas
    $errores = [];
    
    if (empty($productos_factura)) {
        $errores[] = "Debe agregar al menos un producto";
    }
    
    if (!in_array($forma_pago, ['efectivo', 'transferencia', 'pendiente'])) {
        $errores[] = "Forma de pago inválida";
    }
    
    // Si no hay errores, crear la factura
    if (empty($errores)) {
        try {
            global $pdo;
            $pdo->beginTransaction();
            
            // Calcular total
            $total = 0;
            foreach ($productos_factura as $prod) {
                $total += $prod['cantidad'] * $prod['precio_unitario'];
            }
            
            // Crear la factura
            $factura_id = crearFactura(
                $salida_id, 
                $cliente_id, 
                $usuario_id, 
                $forma_pago, 
                $total, 
                $observaciones, 
                $cliente_nombre, 
                $cliente_ciudad
            );
            
            // Agregar productos
            foreach ($productos_factura as $prod) {
                agregarProductoAFactura(
                    $factura_id, 
                    $prod['producto_id'], 
                    $prod['cantidad'], 
                    $prod['precio_unitario']
                );
            }
            
            $pdo->commit();
            
            $_SESSION['mensaje'] = 'Factura creada exitosamente';
            $_SESSION['tipo_mensaje'] = 'success';
            header('Location: index.php');
            exit();
            
        } catch (Exception $e) {
            $pdo->rollback();
            $errores[] = "Error al crear la factura: " . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<style>
/* Estilos para móvil */
.buscador-container {
    position: sticky;
    top: 0;
    z-index: 100;
    background: white;
    padding-bottom: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.producto-busqueda {
    cursor: pointer;
    transition: all 0.3s ease;
}

.producto-busqueda:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.cantidad-disponible {
    font-size: 0.8rem;
    padding: 2px 8px;
    border-radius: 12px;
}

.cantidad-alta { background-color: #d4edda; color: #155724; }
.cantidad-media { background-color: #fff3cd; color: #856404; }
.cantidad-baja { background-color: #f8d7da; color: #721c24; }

.producto-item {
    background: #f8f9fa;
    border-left: 4px solid #007bff !important;
}

#productos-seleccionados {
    min-height: 200px;
}

.precio-editable {
    font-weight: bold;
}

.subtotal {
    color: #28a745;
}

/* Optimización móvil */
@media (max-width: 768px) {
    .producto-item {
        font-size: 0.9rem;
    }
    
    .producto-item .row > div {
        margin-bottom: 0.5rem;
    }
    
    .producto-busqueda .card-body {
        padding: 0.75rem !important;
    }
    
    #buscar-producto-rapido {
        font-size: 1.1rem;
        padding: 0.75rem;
    }
    
    .btn-lg {
        padding: 0.75rem 1.5rem;
        font-size: 1.1rem;
    }
}

/* Indicador de disponibilidad */
.badge.bg-warning {
    background-color: #ffc107 !important;
    color: #000;
}

/* Producto seleccionado */
.producto-item {
    background-color: #f8f9fa;
    transition: all 0.3s ease;
}

.producto-item:hover {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>

<!-- Errores -->
<?php if (!empty($errores)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <ul class="mb-0">
        <?php foreach ($errores as $error): ?>
            <li><?php echo $error; ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" id="form-factura">
    <input type="hidden" name="accion" value="crear_factura">
    <input type="hidden" id="total-hidden" name="total" value="0">
    
    <!-- Información de la ruta -->
    <div class="alert alert-info py-2 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-route"></i> <strong><?php echo $salida['ruta_nombre']; ?></strong>
                <br>
                <small><?php echo date('d/m/Y', strtotime($salida['fecha_salida'])); ?></small>
            </div>
            <div class="text-end">
                <div id="contador-productos" class="badge bg-primary">0 productos</div>
            </div>
        </div>
    </div>
    
    <!-- Cliente y Ciudad -->
    <div class="card mb-3">
    <div class="card-header">
        <h6 class="m-0">
            <i class="fas fa-user"></i> Información del Cliente
        </h6>
    </div>
    <div class="card-body">
        <!-- Tipo de cliente -->
        <div class="mb-3">
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="tipo_cliente" 
                       id="cliente_libre" value="libre" checked>
                <label class="form-check-label" for="cliente_libre">
                    <i class="fas fa-user-plus"></i> Cliente Nuevo/General
                </label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="tipo_cliente" 
                       id="cliente_existente" value="existente">
                <label class="form-check-label" for="cliente_existente">
                    <i class="fas fa-address-book"></i> Cliente Registrado
                </label>
            </div>
            </div>
            
            <!-- Datos cliente libre -->
            <div id="datos_cliente_libre">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="cliente_nombre" class="form-label">Nombre del Cliente</label>
                        <input type="text" class="form-control" id="cliente_nombre" 
                            name="cliente_nombre" placeholder="Ej: Tienda Don Pedro">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="cliente_ciudad" class="form-label">Ciudad/Ubicación</label>
                        <input type="text" class="form-control" id="cliente_ciudad" 
                            name="cliente_ciudad" placeholder="Ej: Centro">
                    </div>
                </div>
            </div>
            
            <!-- Selección cliente existente -->
            <div id="datos_cliente_existente" style="display: none;">
                <select class="form-select" id="cliente_id" name="cliente_id" disabled>
                    <option value="">Seleccionar cliente...</option>
                    <?php foreach ($clientes as $cliente): ?>
                        <option value="<?php echo $cliente['id']; ?>">
                            <?php echo htmlspecialchars($cliente['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <script>
    // Toggle entre cliente libre y existente
    $('input[name="tipo_cliente"]').change(function() {
        if ($(this).val() == 'libre') {
            $('#datos_cliente_libre').show();
            $('#datos_cliente_existente').hide();
            $('#cliente_id').prop('disabled', true);
            $('#cliente_nombre, #cliente_ciudad').prop('disabled', false);
        } else {
            $('#datos_cliente_libre').hide();
            $('#datos_cliente_existente').show();
            $('#cliente_id').prop('disabled', false);
            $('#cliente_nombre, #cliente_ciudad').prop('disabled', true);
        }
    });
    </script>
    
    <!-- Productos -->
    <div class="card mb-3">
        <div class="card-header py-2">
            <h6 class="m-0">
                <i class="fas fa-boxes"></i> Productos
            </h6>
        </div>
        <div class="card-body p-0">
            <!-- Buscador mejorado -->
            <div class="buscador-container p-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" 
                           class="form-control" 
                           id="buscar-producto-rapido" 
                           placeholder="Buscar producto por nombre o código..."
                           autocomplete="off">
                </div>
                <div id="resultados-busqueda-rapida" class="mt-2"></div>
            </div>
            
            <!-- Productos seleccionados -->
            <div id="productos-seleccionados" class="p-3">
                <div class="alert alert-info text-center">
                    <i class="fas fa-search fa-2x mb-2"></i>
                    <p class="mb-0">Use el buscador para agregar productos</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Forma de pago -->
    <div class="card mb-3">
        <div class="card-header py-2">
            <h6 class="m-0">
                <i class="fas fa-credit-card"></i> Forma de Pago
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-4">
                    <input type="radio" class="btn-check" name="forma_pago" id="efectivo" value="efectivo" required>
                    <label class="btn btn-outline-success w-100" for="efectivo">
                        <i class="fas fa-money-bill"></i><br>
                        <small>Efectivo</small>
                    </label>
                </div>
                <div class="col-4">
                    <input type="radio" class="btn-check" name="forma_pago" id="transferencia" value="transferencia">
                    <label class="btn btn-outline-primary w-100" for="transferencia">
                        <i class="fas fa-exchange-alt"></i><br>
                        <small>Transfer.</small>
                    </label>
                </div>
                <div class="col-4">
                    <input type="radio" class="btn-check" name="forma_pago" id="pendiente" value="pendiente">
                    <label class="btn btn-outline-warning w-100" for="pendiente">
                        <i class="fas fa-clock"></i><br>
                        <small>Pendiente</small>
                    </label>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Observaciones -->
    <div class="card mb-3">
        <div class="card-header py-2">
            <h6 class="m-0">
                <i class="fas fa-comment"></i> Observaciones (Opcional)
            </h6>
        </div>
        <div class="card-body">
            <textarea name="observaciones" 
                      class="form-control" 
                      rows="2" 
                      placeholder="Notas adicionales..."></textarea>
        </div>
    </div>
    
    <!-- Total -->
    <div class="card bg-primary text-white mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Total:</h5>
                <h3 class="mb-0" id="total-factura">$0</h3>
            </div>
        </div>
    </div>
    
    <!-- Botones -->
    <div class="row mb-4">
        <div class="col-6">
            <a href="index.php" class="btn btn-secondary btn-lg w-100">
                <i class="fas fa-arrow-left"></i> Cancelar
            </a>
        </div>
        <div class="col-6">
            <button type="submit" class="btn btn-success btn-lg w-100" id="btn-guardar" disabled>
                <i class="fas fa-save"></i> Guardar
            </button>
        </div>
    </div>
    
    <!-- Template para producto seleccionado -->
    <template id="template-producto-seleccionado">
        <div class="producto-item border rounded p-3 mb-2" data-producto-id="">
            <div class="row align-items-center">
                <div class="col-12 col-md-5">
                    <strong class="producto-nombre d-block"></strong>
                    <small class="text-muted producto-unidad"></small>
                    <input type="hidden" class="producto-id-input">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Cantidad:</label>
                    <input type="number" class="form-control form-control-sm cantidad-input" 
                        min="1" value="1" required>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-1">Precio/caja:</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control precio-editable" 
                            step="100" min="0" placeholder="0">
                    </div>
                    <input type="hidden" class="precio-input">
                </div>
                <div class="col-9 col-md-1 text-end">
                    <small class="text-muted d-block">Subtotal:</small>
                    <strong class="subtotal">$0</strong>
                </div>
                <div class="col-3 col-md-1">
                    <button type="button" class="btn btn-danger btn-sm" 
                            onclick="eliminarProducto(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </template>
</form>

<script>
// Variables globales
let contadorProductos = 0;
let indiceProducto = 0;
let productos_encontrados = [];
let productos_disponibles = <?php echo json_encode($productos_disponibles); ?>;

$(document).ready(function() {
    console.log('✅ jQuery cargado correctamente, versión:', $.fn.jquery);
    
    // Activar efectivo por defecto
    $('#efectivo').prop('checked', true);
    
    // Búsqueda en tiempo real
    $('#buscar-producto-rapido').on('input', function() {
        const termino = $(this).val().trim();
        
        if (termino.length >= 2) {
            buscarProductosRapido(termino);
        } else {
            $('#resultados-busqueda-rapida').empty();
        }
    });
    
    // Validación en tiempo real
    configurarValidacionTiempoReal();
    
    // Actualizar total inicial
    actualizarTotal();
});

function buscarProductosRapido(termino) {
    const salida_id = <?php echo $salida_id; ?>;
    
    $.ajax({
        url: '../includes/buscar_productos_salida.php',
        method: 'POST',
        data: { 
            termino: termino,
            salida_id: salida_id
        },
        dataType: 'json',
        success: function(productos) {
            mostrarResultadosBusquedaRapida(productos);
        },
        error: function(xhr, status, error) {
            console.error('Error en búsqueda:', error);
            $('#resultados-busqueda-rapida').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Error al buscar productos. Intente nuevamente.
                </div>
            `);
        }
    });
}

function mostrarResultadosBusquedaRapida(productos) {
    let html = '';
    
    if (productos.length === 0) {
        html = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                No se encontraron productos con ese término
            </div>
        `;
    } else {
        html = '<div class="row">';
        
        productos.forEach(function(producto) {
            const yaAgregado = $(`.producto-item[data-producto-id="${producto.id}"]`).length > 0;
            const disponibleClass = producto.cantidad_disponible > 0 ? 'success' : 'warning';
            
            html += `
                <div class="col-12 mb-2">
                    <div class="card producto-busqueda ${yaAgregado ? 'border-success' : ''}" 
                         style="cursor: ${yaAgregado ? 'default' : 'pointer'}"
                         ${!yaAgregado ? `onclick='agregarProductoDesdeQBusqueda(${JSON.stringify(producto).replace(/'/g, "&apos;")})'` : ''}>
                        <div class="card-body py-3">
                            <div class="row align-items-center">
                                <div class="col-8">
                                    <h6 class="mb-1">${producto.descripcion}</h6>
                                    <small class="text-muted">
                                        Código: ${producto.codigo} | 
                                        ${producto.unidad_medida}
                                    </small>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="mb-1">
                                        <span class="badge bg-${disponibleClass}">
                                            ${producto.cantidad_disponible} en camión
                                        </span>
                                    </div>
                                    <strong class="text-primary">
                                        ${formatearPrecio(producto.precio_publico)}
                                    </strong>
                                    <br>
                                    <small class="text-${yaAgregado ? 'success' : 'muted'}">
                                        ${yaAgregado ? 
                                            '<i class="fas fa-check"></i> Agregado' : 
                                            '<i class="fas fa-plus"></i> Agregar'
                                        }
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
    }
    
    $('#resultados-busqueda-rapida').html(html);
}

function agregarProductoDesdeQBusqueda(producto) {
    const productoParaAgregar = {
        id: producto.id,
        descripcion: producto.descripcion,
        precio: producto.precio_publico,
        unidad: producto.unidad_medida,
        disponible: producto.cantidad_disponible
    };
    
    agregarProducto(productoParaAgregar);
}

function mostrarResultadosBusquedaRapida(productos) {
    let html = '';
    
    if (productos.length === 0) {
        html = `
            <div class="alert alert-warning">
                <i class="fas fa-search"></i> No se encontraron productos
            </div>
        `;
    } else {
        productos.forEach(function(producto) {
            // Verificar si ya está agregado
            const yaAgregado = $(`.producto-item[data-producto-id="${producto.id}"]`).length > 0;
            
            // Determinar clase de cantidad
            let clasesCantidad = 'cantidad-disponible ';
            if (producto.cantidad_restante > 10) {
                clasesCantidad += 'cantidad-alta';
            } else if (producto.cantidad_restante > 5) {
                clasesCantidad += 'cantidad-media';
            } else {
                clasesCantidad += 'cantidad-baja';
            }
            
            html += `
                <div class="card mb-2 producto-busqueda ${yaAgregado ? 'border-success' : ''}" 
                     onclick="${yaAgregado ? '' : 'agregarProductoDesdeQBusqueda(' + JSON.stringify(producto).replace(/"/g, '&quot;') + ')'}">
                    <div class="card-body py-2 ${yaAgregado ? 'bg-light' : ''}">
                        <div class="row align-items-center">
                            <div class="col-7">
                                <strong class="text-primary">${producto.descripcion}</strong>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-tag"></i> ${producto.unidad_medida} • 
                                    <i class="fas fa-barcode"></i> ${producto.codigo}
                                </small>
                            </div>
                            <div class="col-5 text-end">
                                <h6 class="text-success mb-1">${formatearPrecio(producto.precio_publico)}</h6>
                                <span class="${clasesCantidad}">
                                    <i class="fas fa-truck"></i> ${producto.cantidad_restante || 0} disponibles                                </span>
                                <br>
                                ${yaAgregado ? 
                                    '<small class="text-success"><i class="fas fa-check"></i> Agregado</small>' : 
                                    '<small class="text-primary">Toque para agregar</small>'
                                }
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    $('#resultados-busqueda-rapida').html(html);
}

function agregarProductoDesdeQBusqueda(producto) {
    // Verificar si ya está agregado
    if ($(`.producto-item[data-producto-id="${producto.id}"]`).length > 0) {
        mostrarAlerta('El producto ya está en la factura', 'warning');
        return;
    }
    
    agregarProducto(producto);
    
    // Actualizar visualmente el resultado de búsqueda
    mostrarResultadosBusquedaRapida(productos_encontrados);
}

function agregarProducto(producto) {
    // Verificar si ya está agregado
    if ($(`.producto-item[data-producto-id="${producto.id}"]`).length > 0) {
        mostrarAlerta('El producto ya está en la factura', 'warning');
        return;
    }
    
    // Obtener template
    const template = document.getElementById('template-producto-seleccionado');
    if (!template) {
        console.error('Template no encontrado');
        return;
    }
    
    const clone = template.content.cloneNode(true);
    
    // Configurar el producto
    const productoDiv = clone.querySelector('.producto-item');
    productoDiv.setAttribute('data-producto-id', producto.id);
    
    // Llenar datos básicos
    clone.querySelector('.producto-nombre').textContent = producto.descripcion;
    clone.querySelector('.producto-unidad').textContent = producto.unidad;
    
    // Configurar inputs
    const inputId = clone.querySelector('.producto-id-input');
    const inputCantidad = clone.querySelector('.cantidad-input');
    const inputPrecio = clone.querySelector('.precio-input');
    const precioEditable = clone.querySelector('.precio-editable');
    
    inputId.name = `productos[${indiceProducto}][producto_id]`;
    inputId.value = producto.id;
    
    inputCantidad.name = `productos[${indiceProducto}][cantidad]`;
    
    inputPrecio.name = `productos[${indiceProducto}][precio]`;
    
    inputPrecio.value = producto.precio_publico || 0;
    precioEditable.value = producto.precio_publico || 0;
    // Eventos para actualizar totales
    inputCantidad.addEventListener('input', function() {
        actualizarSubtotalProducto(productoDiv);
        actualizarTotal();
    });
    
    precioEditable.addEventListener('input', function() {
        inputPrecio.value = this.value;
        actualizarSubtotalProducto(productoDiv);
        actualizarTotal();
    });
    
    indiceProducto++;
    
    // Limpiar mensaje inicial si existe
    if ($('#productos-seleccionados .alert').length > 0) {
        $('#productos-seleccionados').empty();
    }
    
    // Agregar al contenedor
    $('#productos-seleccionados').append(clone);
    
    // Actualizar subtotal inicial
    actualizarSubtotalProducto(productoDiv);
    actualizarTotal();
    
    // Marcar visualmente en resultados de búsqueda
    $(`.producto-busqueda[onclick*="${producto.id}"]`)
        .addClass('border-success bg-light')
        .find('.badge').text('Agregado');
    
    mostrarAlerta('Producto agregado correctamente', 'success');
}

// Nueva función para actualizar subtotales
function actualizarSubtotalProducto(productoElement) {
    const cantidad = parseInt($(productoElement).find('.cantidad-input').val()) || 0;
    const precio = parseFloat($(productoElement).find('.precio-editable').val()) || 0;
    const subtotal = cantidad * precio;
    
    $(productoElement).find('.subtotal').text(formatearPrecio(subtotal));
}

function eliminarProducto(boton) {
    const productoItem = $(boton).closest('.producto-item');
    
    // Eliminar del formulario
    productoItem.remove();
    
    // Actualizar contador y total
    actualizarTotal();
    
    // Si no quedan productos, mostrar mensaje
    if ($('.producto-item').length === 0) {
        $('#productos-seleccionados').html(`
            <div class="alert alert-info text-center">
                <i class="fas fa-search fa-2x mb-2"></i>
                <p class="mb-0">Use el buscador para agregar productos</p>
            </div>
        `);
    }
    
    // Actualizar vista de búsqueda si está visible
    if (productos_encontrados.length > 0) {
        mostrarResultadosBusquedaRapida(productos_encontrados);
    }
}

function actualizarSubtotalProducto(productoDiv) {
    const cantidad = parseInt($(productoDiv).find('.cantidad-input').val()) || 0;
    const precio = parseFloat($(productoDiv).find('.precio-input').val()) || 0;
    const subtotal = cantidad * precio;
    
    $(productoDiv).find('.subtotal').text(formatearPrecio(subtotal));
}

function actualizarTotal() {
    let total = 0;
    contadorProductos = 0;
    
    $('.producto-item').each(function() {
        const cantidad = parseInt($(this).find('.cantidad-input').val()) || 0;
        const precio = parseFloat($(this).find('.precio-input').val()) || 0;
        
        total += cantidad * precio;
        contadorProductos++;
        
        // Actualizar subtotal de este producto
        actualizarSubtotalProducto(this);
    });
    
    $('#total-factura').text(formatearPrecio(total));
    $('#total-hidden').val(total);
    $('#contador-productos').text(contadorProductos + ' productos');
    
    // Habilitar/deshabilitar botón guardar
    if (contadorProductos > 0 && total > 0 && $('#cliente_id').val()) {
        $('#btn-guardar').prop('disabled', false);
    } else {
        $('#btn-guardar').prop('disabled', true);
    }
}

function configurarValidacionTiempoReal() {
    // Validar selección de cliente
    $('#cliente_id').on('change', function() {
        if ($(this).val()) {
            $(this).removeClass('is-invalid').addClass('is-valid');
            // Actualizar botón guardar
            actualizarTotal();
        } else {
            $(this).removeClass('is-valid').addClass('is-invalid');
            $('#btn-guardar').prop('disabled', true);
        }
    });
    
    // Validar forma de pago
    $('input[name="forma_pago"]').on('change', function() {
        $('.btn-check').each(function() {
            if ($(this).is(':checked')) {
                $(this).next('label').addClass('active');
            } else {
                $(this).next('label').removeClass('active');
            }
        });
    });
}

function mostrarAlerta(mensaje, tipo) {
    // Crear alerta temporal
    const alerta = $(`
        <div class="alert alert-${tipo} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999;">
            ${mensaje}
        </div>
    `);
    
    $('body').append(alerta);
    
    // Remover después de 3 segundos
    setTimeout(function() {
        alerta.fadeOut(function() {
            $(this).remove();
        });
    }, 3000);
}

function formatearPrecio(precio) {
    return '$' + Number(precio).toLocaleString('es-CO');
}

// Prevenir envío accidental del formulario
$('#form-factura').on('submit', function(e) {
    if (!confirm('¿Está seguro de guardar esta factura?')) {
        e.preventDefault();
        return false;
    }
});

// Al final del script de crear factura
$('#form-crear-factura').on('submit', async function(e) {
    e.preventDefault();
    
    const formData = {
        salida_id: <?php echo $salida_id; ?>,
        vendedor_id: <?php echo $usuario_id; ?>,
        auth_token: '<?php echo session_id(); ?>',
        // ... resto de datos del formulario
    };
    
    if (!navigator.onLine) {
        // Guardar offline
        await offlineStorage.saveFacturaOffline(formData);
        window.location.href = 'index.php?mensaje=Factura guardada localmente';
    } else {
        // Enviar normalmente
        this.submit();
    }
});

</script>
<!-- Sistema offline -->
<script src="/Metru/js/offline-handler.js"></script>

<script>
// Inicializar sistema offline al cargar
$(document).ready(function() {
    // Cachear datos si hay conexión
    if (navigator.onLine && window.sistemaOffline) {
        sistemaOffline.cachearDatosSalida(<?php echo $salida_id; ?>);
    }
});
</script>

<?php include '../includes/footer.php'; ?>