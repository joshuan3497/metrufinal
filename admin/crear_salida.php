<?php
// =====================================================
// CREAR NUEVA SALIDA DE MERCANCÍA - SISTEMA METRU
// =====================================================

include_once '../includes/functions.php';
verificarSesion('admin');

$titulo_pagina = 'Crear Nueva Salida de Mercancía';
$icono_pagina = 'fas fa-plus-circle';

// Obtener datos necesarios
$rutas = obtenerTodasLasRutas();
$trabajadores = obtenerRegistros("SELECT * FROM usuarios WHERE tipo = 'trabajador' AND activo = 1 ORDER BY nombre");

// Procesar formulario
if ($_POST && isset($_POST['accion']) && $_POST['accion'] == 'crear_salida') {
    $ruta_id = $_POST['ruta_id'] ?? 0;
    $responsable_id = $_POST['responsable_id'] ?? 0;
    $fecha_salida = $_POST['fecha_salida'] ?? date('Y-m-d');
    $observaciones = $_POST['observaciones'] ?? '';
    $productos = $_POST['productos'] ?? [];
    $trabajadores_asignados = $_POST['trabajadores'] ?? [];
    $trabajador_principal = $_POST['trabajador_principal'] ?? 0;
    
    // Validaciones
    $errores = [];
    
    if (!$ruta_id) {
        $errores[] = "Debe seleccionar una ruta";
    }
    
    
    if (empty($trabajadores_asignados)) {
    $errores[] = "Debe asignar al menos un trabajador a la ruta";
}

if (!in_array($trabajador_principal, $trabajadores_asignados)) {
    $errores[] = "El trabajador principal debe estar entre los trabajadores asignados";
}

// Validar que los trabajadores no tengan rutas activas
foreach ($trabajadores_asignados as $trabajador_id) {
    $sql_verificar = "SELECT COUNT(*) as rutas_activas 
                      FROM salida_trabajadores st
                      JOIN salidas_mercancia s ON st.salida_id = s.id
                      WHERE st.trabajador_id = ? 
                      AND s.estado IN ('preparando', 'en_ruta')";
    $resultado = obtenerRegistro($sql_verificar, [$trabajador_id]);
    
    if ($resultado['rutas_activas'] > 0) {
        $trabajador = obtenerUsuarioPorId($trabajador_id);
        $errores[] = "El trabajador {$trabajador['nombre']} ya tiene una ruta activa";
    }
}

    if (empty($productos)) {
        $errores[] = "Debe agregar al menos un producto";
    }
    
    // Validar que no exista una salida activa para la misma ruta en la misma fecha
    $salida_existente = obtenerRegistro(
        "SELECT id FROM salidas_mercancia WHERE ruta_id = ? AND DATE(fecha_salida) = ? AND estado != 'finalizada'",
        [$ruta_id, $fecha_salida]
    );
    
    if ($salida_existente) {
        $errores[] = "Ya existe una salida activa para esta ruta en la fecha seleccionada";
    }
    
    // Si no hay errores, crear la salida
    if (empty($errores)) {
        try {
            global $pdo;
            $pdo->beginTransaction();
            
            // Crear la salida
            $salida_id = crearSalidaMercancia($ruta_id, $trabajador_principal, $fecha_salida, $observaciones);
            
            // Agregar productos
            foreach ($trabajadores_asignados as $trabajador_id) {
                $es_principal = ($trabajador_id == $trabajador_principal) ? 1 : 0;
                ejecutarConsulta(
                    "INSERT INTO salida_trabajadores (salida_id, trabajador_id, es_principal) VALUES (?, ?, ?)",
                    [$salida_id, $trabajador_id, $es_principal]
                );
            }
            
                        $productos_agregados = 0;
            foreach ($_POST as $key => $value) {
                // Buscar campos de productos (formato: producto_X_id y producto_X_cantidad)
                if (preg_match('/^producto_(\d+)_id$/', $key, $matches)) {
                    $index = $matches[1];
                    $producto_id = $value;
                    $cantidad_key = "producto_{$index}_cantidad";
                    $cantidad = $_POST[$cantidad_key] ?? 0;
                    
                    if ($producto_id > 0 && $cantidad > 0) {
                        ejecutarConsulta(
                            "INSERT INTO detalle_salidas (salida_id, producto_id, cantidad, tipo_carga) 
                            VALUES (?, ?, ?, 'normal')",
                            [$salida_id, $producto_id, $cantidad]
                        );
                        $productos_agregados++;
                    }
                }
            }

            // Verificar que se agregaron productos
            if ($productos_agregados == 0) {
                throw new Exception("No se agregaron productos a la salida");
            }

            $pdo->commit();
            
            $_SESSION['mensaje'] = 'Salida creada exitosamente';
            $_SESSION['tipo_mensaje'] = 'success';
            
            header('Location: detalle_salida.php?id=' . $salida_id);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollback();
            $errores[] = "Error al crear la salida: " . $e->getMessage();
        }
    }
    
    if (!empty($errores)) {
        $_SESSION['mensaje'] = implode('<br>', $errores);
        $_SESSION['tipo_mensaje'] = 'danger';
    }
}

include '../includes/header.php';
?>

<!-- Mostrar mensajes -->
<?php if (isset($_SESSION['mensaje'])): ?>
    <div class="alert alert-<?php echo $_SESSION['tipo_mensaje'] ?? 'info'; ?> alert-dismissible fade show" role="alert">
        <?php 
        echo $_SESSION['mensaje'];
        unset($_SESSION['mensaje']);
        unset($_SESSION['tipo_mensaje']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" action="" id="form-crear-salida">
    <input type="hidden" name="accion" value="crear_salida">
    
    <div class="row">
        <!-- Información básica -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-info-circle"></i> Información de la Salida
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="ruta_id" class="form-label">Ruta <span class="text-danger">*</span></label>
                        <select class="form-select" id="ruta_id" name="ruta_id" required>
                            <option value="">Seleccione una ruta</option>
                            <?php foreach ($rutas as $ruta): ?>
                                <option value="<?php echo $ruta['id']; ?>">
                                    <?php echo htmlspecialchars($ruta['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Selección de trabajadores -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="m-0">
                                <i class="fas fa-users"></i> Asignar Trabajadores
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Seleccione los trabajadores:</label>
                                    <div class="trabajadores-lista" style="max-height: 200px; overflow-y: auto;">
                                        <?php foreach ($trabajadores as $trabajador): ?>
                                        <div class="form-check">
                                            <input class="form-check-input trabajador-check" 
                                                type="checkbox" 
                                                name="trabajadores[]" 
                                                value="<?php echo $trabajador['id']; ?>"
                                                id="trabajador_<?php echo $trabajador['id']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($trabajador['nombre']); ?>">
                                            <label class="form-check-label" for="trabajador_<?php echo $trabajador['id']; ?>">
                                                <?php echo htmlspecialchars($trabajador['nombre']); ?>
                                                <small class="text-muted">(<?php echo $trabajador['codigo_usuario']; ?>)</small>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="trabajador_principal" class="form-label fw-bold">
                                        <i class="fas fa-star"></i> Trabajador principal:
                                    </label>
                                    <select class="form-select" name="trabajador_principal" id="trabajador_principal" required>
                                        <option value="">Seleccione...</option>
                                    </select>
                                    <div class="form-text">
                                        El trabajador principal será el responsable de la ruta
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                    // Actualizar lista de trabajador principal según los seleccionados
                    $('.trabajador-check').on('change', function() {
                        actualizarTrabajadorPrincipal();
                    });

                    function actualizarTrabajadorPrincipal() {
                        const $select = $('#trabajador_principal');
                        const valorActual = $select.val();
                        
                        $select.empty();
                        $select.append('<option value="">Seleccione...</option>');
                        
                        $('.trabajador-check:checked').each(function() {
                            const id = $(this).val();
                            const nombre = $(this).data('nombre');
                            $select.append(`<option value="${id}">${nombre}</option>`);
                        });
                        
                        // Restaurar valor si aún es válido
                        if ($select.find(`option[value="${valorActual}"]`).length > 0) {
                            $select.val(valorActual);
                        }
                    }

                    // Validar antes de enviar
                    $('form').on('submit', function(e) {
                        const trabajadoresSeleccionados = $('.trabajador-check:checked').length;
                        
                        if (trabajadoresSeleccionados === 0) {
                            e.preventDefault();
                            alert('Debe seleccionar al menos un trabajador');
                            return false;
                        }
                        
                        const principal = $('#trabajador_principal').val();
                        if (!principal) {
                            e.preventDefault();
                            alert('Debe seleccionar un trabajador principal');
                            return false;
                        }
                    });
                    </script>
                    
                    <div class="mb-3">
                        <label for="fecha_salida" class="form-label">Fecha de Salida <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="fecha_salida" name="fecha_salida" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" 
                                  rows="3" placeholder="Observaciones adicionales (opcional)"></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Resumen -->
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-chart-bar"></i> Resumen
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total Productos:</span>
                        <strong id="total-productos">0</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Total Unidades:</span>
                        <strong id="total-unidades">0</strong>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save"></i> Crear Salida
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Productos -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-boxes"></i> Productos de la Salida
                    </h6>
                </div>
                <div class="card-body">
                    <!-- Buscador -->
                    <div class="mb-4">
                        <label for="buscar-producto" class="form-label">Buscar Producto</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="buscar-producto" 
                                   placeholder="Escriba el nombre o código del producto..."
                                   autocomplete="off">
                        </div>
                        <small class="text-muted">
                            Escriba al menos 2 caracteres para buscar
                        </small>
                    </div>
                    
                    <!-- Resultados de búsqueda -->
                    <div id="resultados-busqueda"></div>
                    
                    <!-- Productos seleccionados -->
                    <hr>
                    <h6 class="mb-3">
                        <i class="fas fa-clipboard-list"></i> Productos Seleccionados
                    </h6>
                    <div id="productos-seleccionados">
                        <div class="alert alert-info text-center">
                            <i class="fas fa-box-open fa-2x mb-2"></i>
                            <p class="mb-0">No hay productos agregados. Use el buscador para agregar productos.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- AGREGAR ESTE CÓDIGO JUSTO ANTES DEL CIERRE </body> EN crear_salida.php -->
    <style>
    /* Forzar visibilidad de resultados */
    #resultados-busqueda {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        background-color: white !important;
        min-height: 50px;
        margin-top: 10px;
    }

    #resultados-busqueda:not(:empty) {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 10px;
    }

    .list-group-item {
        display: block !important;
        padding: 12px !important;
        margin-bottom: 8px !important;
        background: white !important;
        border: 1px solid #ddd !important;
        border-radius: 8px !important;
        cursor: pointer !important;
    }

    .list-group-item:hover {
        background-color: #f8f9fa !important;
        border-color: #007bff !important;
    }
    </style>

    <script>
    // Variables globales
    window.productosAgregados = {};
    window.contadorProductos = 0;

    // Función para agregar producto a la salida
    function agregarProductoASalida(producto) {
        // Verificar si ya está agregado
        if (productosAgregados[producto.id]) {
            alert('Este producto ya está agregado');
            return;
        }
        
        // Agregar a la lista
        productosAgregados[producto.id] = true;
        contadorProductos++;
        
        const html = `
            <div class="producto-item card mb-2" id="producto_item_${producto.id}">
                <div class="card-body p-2">
                    <input type="hidden" name="producto_${window.indiceProducto}_id" value="${producto.id}">
                    <input type="hidden" name="producto_${window.indiceProducto}_cantidad" class="cantidad-hidden" value="1">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <strong>${producto.descripcion}</strong><br>
                            <small class="text-muted">
                                ${producto.codigo} • ${producto.unidad_medida}
                            </small>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Cantidad</span>
                                <input type="number" 
                                    class="form-control cantidad-input" 
                                    data-index="${window.indiceProducto}"
                                    value="1" 
                                    min="1" 
                                    required
                                    onchange="actualizarCantidadHidden(this)">
                            </div>
                        </div>
                        <div class="col-md-2 text-end">
                            <span class="text-success">
                                $${Number(producto.precio_publico).toLocaleString('es-CO')}
                            </span>
                        </div>
                        <div class="col-md-1 text-end">
                            <button type="button" 
                                    class="btn btn-danger btn-sm" 
                                    onclick="quitarProducto(${producto.id})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Si es el primer producto, quitar el mensaje
        if (contadorProductos === 1) {
            $('#productos-seleccionados').empty();
        }
        
        $('#productos-seleccionados').append(html);
        actualizarContador();
        
        // Limpiar búsqueda
        $('#buscar-producto').val('').focus();
        $('#resultados-busqueda').empty();
    }

    // Función para quitar producto
    function quitarProducto(productoId) {
        $(`#producto_item_${productoId}`).remove();
        delete productosAgregados[productoId];
        contadorProductos--;
        actualizarContador();
        
        if (contadorProductos === 0) {
            $('#productos-seleccionados').html(`
                <div class="alert alert-info text-center">
                    <i class="fas fa-box-open fa-2x mb-2"></i>
                    <p class="mb-0">No hay productos agregados.</p>
                </div>
            `);
        }
    }

    // Actualizar contador visual
    function actualizarContador() {
        $('#contador-productos').text(contadorProductos);
        
        // Habilitar/deshabilitar botón de crear
        if (contadorProductos > 0) {
            $('#btn-crear-salida').prop('disabled', false);
        } else {
            $('#btn-crear-salida').prop('disabled', true);
        }
    }

    // Validar antes de enviar
    $('#form-crear-salida').on('submit', function(e) {
        if (contadorProductos === 0) {
            e.preventDefault();
            alert('Debe agregar al menos un producto');
            return false;
        }
    });

    // Búsqueda de productos mejorada
    $('#buscar-producto').on('input', function() {
        const termino = $(this).val().trim();
        
        if (termino.length >= 2) {
            buscarProductosAjax(termino);
        } else {
            $('#resultados-busqueda').empty();
        }
    });

    function buscarProductosAjax(termino) {
        $('#resultados-busqueda').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>');
        
        $.ajax({
            url: '../includes/buscar_productos.php',
            method: 'POST',
            data: { termino: termino },
            dataType: 'json',
            success: function(productos) {
                mostrarResultadosBusqueda(productos);
            },
            error: function(xhr, status, error) {
                console.error('Error buscando productos:', error);
                $('#resultados-busqueda').html('<div class="alert alert-danger">Error al buscar productos</div>');
            }
        });
    }

    function mostrarResultadosBusqueda(productos) {
        if (productos.length === 0) {
            $('#resultados-busqueda').html('<div class="alert alert-warning">No se encontraron productos</div>');
            return;
        }
        
        let html = '<div class="list-group">';
        productos.forEach(producto => {
            const yaAgregado = productosAgregados[producto.id];
            const claseAdicional = yaAgregado ? 'disabled' : '';
            
            html += `
                <button type="button" 
                        class="list-group-item list-group-item-action ${claseAdicional}"
                        onclick="${yaAgregado ? '' : 'agregarProductoASalida(' + JSON.stringify(producto) + ')'}"
                        ${yaAgregado ? 'disabled' : ''}>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${producto.descripcion}</strong><br>
                            <small class="text-muted">${producto.codigo} • ${producto.unidad_medida}</small>
                        </div>
                        <div class="text-end">
                            ${yaAgregado ? 
                                '<span class="badge bg-secondary">Ya agregado</span>' : 
                                '<span class="badge bg-primary">+ Agregar</span>'
                            }
                        </div>
                    </div>
                </button>
            `;
        });
        html += '</div>';
        
        $('#resultados-busqueda').html(html);
    }

    // Inicializar
    $(document).ready(function() {
        actualizarContador();
        $('#buscar-producto').focus();
    });
    </script>

    <script>
    // Parche para asegurar que la función funcione
    $(document).ready(function() {
        console.log('🔧 Aplicando parche de visualización...');
        
        // Sobrescribir la función mostrarResultadosProductos si existe problemas
        const mostrarResultadosProductosOriginal = window.mostrarResultadosProductos;
        
        window.mostrarResultadosProductos = function(productos) {
            console.log('📋 Mostrando productos (versión parcheada):', productos.length);
            
            // Limpiar primero
            $('#resultados-busqueda').empty();
            
            if (!productos || productos.length === 0) {
                $('#resultados-busqueda').html(`
                    <div class="alert alert-warning">
                        <i class="fas fa-search"></i> No se encontraron productos
                    </div>
                `);
                return;
            }
            
            // Crear lista
            let html = '<div class="list-group">';
            
            productos.forEach(function(producto) {
                const yaAgregado = $(`.producto-item[data-producto-id="${producto.id}"]`).length > 0;
                
                html += `
                    <div class="list-group-item ${yaAgregado ? 'disabled opacity-50' : ''}" 
                        onclick="${yaAgregado ? '' : 'agregarProductoDirecto(' + producto.id + ', \'' + producto.descripcion.replace(/'/g, "\\'") + '\', \'' + producto.unidad_medida + '\', ' + producto.precio_publico + ')'}"
                        style="cursor: ${yaAgregado ? 'not-allowed' : 'pointer'};">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${producto.descripcion}</strong><br>
                                <small class="text-muted">
                                    <i class="fas fa-barcode"></i> ${producto.codigo} • 
                                    <i class="fas fa-cube"></i> ${producto.unidad_medida}
                                </small>
                            </div>
                            <div class="text-end">
                                <div class="h6 mb-0 text-success">$${Number(producto.precio_publico).toLocaleString('es-CO')}</div>
                                <small class="${yaAgregado ? 'text-muted' : 'text-primary'}">
                                    ${yaAgregado ? '✓ Agregado' : '+ Agregar'}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            
            // Insertar y mostrar
            $('#resultados-busqueda').html(html);
            
            // Verificar que se insertó
            console.log('✅ Productos mostrados en el DOM');
        };
        
        // Función auxiliar para agregar productos
        window.agregarProductoDirecto = function(id, descripcion, unidad, precio) {
            const producto = {
                id: id,
                descripcion: descripcion,
                unidad_medida: unidad,
                precio_publico: precio
            };
            
            if (typeof agregarProductoASalida !== 'undefined') {
                agregarProductoASalida(producto);
            } else {
                alert('Producto seleccionado: ' + descripcion);
            }
        };
    });
    
    function actualizarCantidadHidden(input) {
        const index = $(input).data('index');
        const cantidad = $(input).val();
        $(input).closest('.producto-item').find('.cantidad-hidden').val(cantidad);
    }
    </script>
</form>

<!-- Cargar JavaScript específico -->
<script src="../js/crear_salida.js"></script>

<?php include '../includes/footer.php'; ?>