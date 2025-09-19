// =====================================================
// JAVASCRIPT PARA CREAR SALIDA - SISTEMA METRU
// =====================================================

$(document).ready(function() {
    console.log('✅ crear_salida.js cargado');
    
    // Inicializar contador
    window.contadorProductos = 0;
    window.indiceProducto = 0;
    
    // Evento de búsqueda
    $('#buscar-producto').on('input', function() {
        const termino = $(this).val().trim();
        console.log('🔍 Buscando:', termino);
        
        if (termino.length >= 2) {
            buscarProductosParaSalida(termino);
        } else {
            $('#resultados-busqueda').empty();
        }
    });
    
    // Validar formulario al enviar
    $('#form-crear-salida').on('submit', function(e) {
        if ($('.producto-item').length === 0) {
            e.preventDefault();
            alert('Debe agregar al menos un producto');
            return false;
        }
    });
    
    // Actualizar resumen inicial
    actualizarResumen();
});

function buscarProductosParaSalida(termino) {
    console.log('📡 Iniciando búsqueda AJAX para:', termino);
    
    // Mostrar spinner
    $('#resultados-busqueda').html(`
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Buscando...</span>
            </div>
            <p class="mt-2 text-muted">Buscando productos...</p>
        </div>
    `);
    
    $.ajax({
        url: '../includes/buscar_productos.php',
        method: 'POST',
        data: { termino: termino },
        dataType: 'json',
        timeout: 10000,
        success: function(productos) {
            console.log('✅ Respuesta exitosa:', productos);
            mostrarResultadosProductos(productos);
        },
        error: function(xhr, status, error) {
            console.error('❌ Error AJAX:', {
                status: status,
                error: error,
                responseText: xhr.responseText
            });
            
            $('#resultados-busqueda').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> Error al buscar productos
                    <br><small>Por favor, intente nuevamente</small>
                </div>
            `);
        }
    });
}

function mostrarResultadosProductos(productos) {
    console.log('📋 Mostrando', productos.length, 'productos');
    
    let html = '';
    
    if (!productos || productos.length === 0) {
        html = `
            <div class="alert alert-warning">
                <i class="fas fa-search"></i> 
                No se encontraron productos para "<strong>${$('#buscar-producto').val()}</strong>"
            </div>
        `;
    } else {
        html = '<div class="list-group">';
        
        productos.forEach(function(producto) {
            const yaAgregado = $(`.producto-item[data-producto-id="${producto.id}"]`).length > 0;
            
            html += `
                <div class="list-group-item list-group-item-action ${yaAgregado ? 'disabled' : ''}" 
                     style="cursor: pointer;"
                     data-producto='${JSON.stringify(producto)}'
                     onclick="agregarProductoDesdeClick(this)">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">${producto.descripcion}</h6>
                            <small class="text-muted">
                                <i class="fas fa-barcode"></i> ${producto.codigo} • 
                                <i class="fas fa-cube"></i> ${producto.unidad_medida}
                            </small>
                        </div>
                        <div class="text-end">
                            <h6 class="text-success mb-0">$${Number(producto.precio_publico).toLocaleString('es-CO')}</h6>
                            <small class="${yaAgregado ? 'text-success' : 'text-primary'}">
                                ${yaAgregado ? '<i class="fas fa-check"></i> Agregado' : '<i class="fas fa-plus"></i> Agregar'}
                            </small>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
    }
    
    // IMPORTANTE: Actualizar el DOM
    $('#resultados-busqueda').html(html);
    console.log('✅ Resultados actualizados en el DOM');
}

// Función para manejar el click en un producto
function agregarProductoDesdeClick(elemento) {
    const producto = JSON.parse($(elemento).attr('data-producto'));
    if (!$(elemento).hasClass('disabled')) {
        agregarProductoASalida(producto);
    }
}

function agregarProductoASalida(producto) {
    console.log('➕ Agregando producto:', producto);
    
    // Verificar duplicados
    if ($(`.producto-item[data-producto-id="${producto.id}"]`).length > 0) {
        alert('Este producto ya está en la lista');
        return;
    }
    
    const nuevoProducto = `
        <div class="producto-item card mb-2" data-producto-id="${producto.id}">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h6 class="mb-1">${producto.descripcion}</h6>
                        <small class="text-muted">
                            <i class="fas fa-cube"></i> ${producto.unidad_medida} • 
                            <i class="fas fa-tag"></i> $${Number(producto.precio_publico).toLocaleString('es-CO')}
                        </small>
                        <input type="hidden" name="productos[${window.indiceProducto}][producto_id]" value="${producto.id}">
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text">Cantidad</span>
                            <input type="number" 
                                   class="form-control cantidad-input" 
                                   name="productos[${window.indiceProducto}][cantidad]" 
                                   value="1" 
                                   min="1" 
                                   required
                                   onchange="actualizarResumen()">
                        </div>
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="button" 
                                class="btn btn-danger btn-sm" 
                                onclick="eliminarProducto(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Limpiar mensaje inicial si existe
    if ($('#productos-seleccionados .alert-info').length > 0) {
        $('#productos-seleccionados').empty();
    }
    
    // Agregar producto
    $('#productos-seleccionados').append(nuevoProducto);
    
    // Incrementar índice
    window.indiceProducto++;
    window.contadorProductos++;
    
    // Actualizar el item en la lista de búsqueda para marcarlo como agregado
    $(`.list-group-item[data-producto='${JSON.stringify(producto)}']`)
        .addClass('disabled')
        .find('.text-primary')
        .removeClass('text-primary')
        .addClass('text-success')
        .html('<i class="fas fa-check"></i> Agregado');
    
    // Actualizar resumen
    actualizarResumen();
    
    // Mostrar confirmación
    console.log('✅ Producto agregado correctamente');
}

function eliminarProducto(boton) {
    if (confirm('¿Está seguro de eliminar este producto?')) {
        const productoItem = $(boton).closest('.producto-item');
        const productoId = productoItem.attr('data-producto-id');
        
        productoItem.remove();
        window.contadorProductos--;
        
        // Actualizar el item en la lista de búsqueda si todavía está visible
        $(`.list-group-item`).each(function() {
            try {
                const producto = JSON.parse($(this).attr('data-producto'));
                if (producto.id == productoId) {
                    $(this)
                        .removeClass('disabled')
                        .find('.text-success')
                        .removeClass('text-success')
                        .addClass('text-primary')
                        .html('<i class="fas fa-plus"></i> Agregar');
                }
            } catch (e) {
                // Ignorar errores de parsing
            }
        });
        
        actualizarResumen();
        
        // Si no quedan productos, mostrar mensaje
        if ($('.producto-item').length === 0) {
            $('#productos-seleccionados').html(`
                <div class="alert alert-info text-center">
                    <i class="fas fa-box-open fa-2x mb-2"></i>
                    <p class="mb-0">No hay productos agregados. Use el buscador para agregar productos.</p>
                </div>
            `);
        }
    }
}

function actualizarResumen() {
    const totalProductos = $('.producto-item').length;
    let totalUnidades = 0;
    
    $('.cantidad-input').each(function() {
        totalUnidades += parseInt($(this).val()) || 0;
    });
    
    $('#total-productos').text(totalProductos);
    $('#total-unidades').text(totalUnidades);
    
    console.log('📊 Resumen actualizado:', {totalProductos, totalUnidades});
}

// Función para limpiar búsqueda (si hay un botón de limpiar)
function limpiarBusqueda() {
    $('#buscar-producto').val('');
    $('#resultados-busqueda').empty();
}

// Hacer funciones globales para que puedan ser llamadas desde onclick
window.agregarProductoDesdeClick = agregarProductoDesdeClick;
window.eliminarProducto = eliminarProducto;
window.actualizarResumen = actualizarResumen;
window.limpiarBusqueda = limpiarBusqueda;