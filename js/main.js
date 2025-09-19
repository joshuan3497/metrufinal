// =====================================================
// JAVASCRIPT PRINCIPAL - SISTEMA METRU
// =====================================================

$(document).ready(function() {
    // Inicializar componentes
    inicializarComponentes();
    configurarEventos();
    configurarValidaciones();
});

// =====================================================
// INICIALIZACIÓN DE COMPONENTES
// =====================================================

function inicializarComponentes() {
    // Tooltips de Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Popovers de Bootstrap
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Auto-hide alerts después de 5 segundos
    $('.alert').delay(5000).fadeOut();
    
    // Animaciones de entrada
    $('.card').addClass('fade-in');
    
    // Focus en primer input de formularios
    $('form input:visible:first').focus();
}

// =====================================================
// CONFIGURACIÓN DE EVENTOS
// =====================================================

function configurarEventos() {
    // Confirmación para botones de eliminar
    $('.btn-eliminar, .btn-danger[href]').on('click', function(e) {
        e.preventDefault();
        const url = $(this).attr('href');
        const mensaje = $(this).data('mensaje') || '¿Está seguro de que desea eliminar este elemento?';
        
        confirmarAccion(mensaje, function() {
            window.location.href = url;
        });
    });
    
    // Loading state en formularios
    $('form').on('submit', function() {
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        
        submitBtn.prop('disabled', true).html(
            '<i class="fas fa-spinner fa-spin"></i> Procesando...'
        );
        
        // Restaurar botón después de 10 segundos (por seguridad)
        setTimeout(function() {
            submitBtn.prop('disabled', false).html(originalText);
        }, 10000);
    });
    
    // Búsqueda en tiempo real
    $('.buscar-productos').on('input', function() {
        const termino = $(this).val();
        const contenedor = $(this).data('target') || '#resultados-busqueda';
        
        if (termino.length >= 2) {
            buscarProductos(termino, function(productos) {
                mostrarResultadosProductos(productos, contenedor);
            });
        } else {
            $(contenedor).empty();
        }
    });
    
    // Selección de productos
    $(document).on('click', '.producto-seleccionable', function() {
        const producto = {
            id: $(this).data('id'),
            descripcion: $(this).data('descripcion'),
            precio: $(this).data('precio'),
            unidad: $(this).data('unidad')
        };
        
        agregarProductoALista(producto);
    });
    
    // Eliminar producto de lista
    $(document).on('click', '.eliminar-producto', function() {
        $(this).closest('.producto-item').remove();
        recalcularTotal();
    });
    
    // Cambios en cantidad o precio
    $(document).on('input', '.cantidad, .precio-unitario', function() {
        actualizarSubtotal($(this).closest('.producto-item'));
        recalcularTotal();
    });
    
    // Auto-save de formularios (borrador)
    $('form[data-auto-save]').on('input', function() {
        const formId = $(this).attr('id') || 'form-auto-save';
        const formData = $(this).serialize();
        localStorage.setItem('metru_' + formId, formData);
    });
    
    // Restaurar datos guardados
    $('form[data-auto-save]').each(function() {
        const formId = $(this).attr('id') || 'form-auto-save';
        const savedData = localStorage.getItem('metru_' + formId);
        
        if (savedData) {
            const datos = new URLSearchParams(savedData);
            for (let [campo, valor] of datos) {
                $(this).find('[name="' + campo + '"]').val(valor);
            }
        }
    });
}

// =====================================================
// VALIDACIONES
// =====================================================

function configurarValidaciones() {
    // Validar números positivos
    $('.numero-positivo').on('input', function() {
        let valor = parseFloat($(this).val()) || 0;
        if (valor < 0) {
            $(this).val(0);
        }
    });
    
    // Validar precios
    $('.precio').on('input', function() {
        let valor = $(this).val();
        // Permitir solo números y punto decimal
        valor = valor.replace(/[^0-9.]/g, '');
        // Asegurar solo un punto decimal
        let partes = valor.split('.');
        if (partes.length > 2) {
            valor = partes[0] + '.' + partes[1];
        }
        $(this).val(valor);
    });
    
    // Validar formularios antes de enviar
    $('form').on('submit', function(e) {
        if (!validarFormulario($(this))) {
            e.preventDefault();
            mostrarAlerta('Por favor corrija los errores en el formulario', 'error');
            return false;
        }
    });
}

// =====================================================
// FUNCIONES DE UTILIDAD
// =====================================================

function mostrarAlerta(mensaje, tipo = 'info') {
    const alertas = {
        'success': { icon: 'fa-check-circle', class: 'alert-success' },
        'error': { icon: 'fa-exclamation-triangle', class: 'alert-danger' },
        'warning': { icon: 'fa-exclamation-circle', class: 'alert-warning' },
        'info': { icon: 'fa-info-circle', class: 'alert-info' }
    };
    
    const config = alertas[tipo] || alertas['info'];
    
    const alerta = `
        <div class="alert ${config.class} alert-dismissible fade show" role="alert">
            <i class="fas ${config.icon}"></i> ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Insertar la alerta al inicio del contenido principal
    $('.main-content').prepend(alerta);
    
    // Auto-ocultar después de 5 segundos
    setTimeout(() => {
        $('.alert').fadeOut();
    }, 5000);
}

function confirmarAccion(mensaje, callback) {
    if (confirm(mensaje)) {
        callback();
    }
}

function formatearPrecio(precio) {
    return '$' + new Intl.NumberFormat('es-CO').format(precio);
}

function formatearFecha(fecha) {
    return new Date(fecha).toLocaleDateString('es-CO');
}

function formatearFechaHora(fecha) {
    return new Date(fecha).toLocaleString('es-CO');
}

// =====================================================
// FUNCIONES DE PRODUCTOS
// =====================================================

function buscarProductos(termino, callback) {
    if (termino.length >= 2) {
        $.ajax({
            url: '../includes/buscar_productos.php',
            method: 'POST',
            data: {
                termino: termino,
                csrf_token: APP_CONFIG.csrf_token
            },
            dataType: 'json',
            success: function(productos) {
                callback(productos);
            },
            error: function() {
                mostrarAlerta('Error al buscar productos', 'error');
            }
        });
    }
}

function mostrarResultadosProductos(productos, contenedor) {
    let html = '';
    
    if (productos.length === 0) {
        html = '<div class="alert alert-info">No se encontraron productos</div>';
    } else {
        productos.forEach(function(producto) {
            html += `
                <div class="producto-card producto-seleccionable" 
                     data-id="${producto.id}"
                     data-descripcion="${producto.descripcion}"
                     data-precio="${producto.precio_publico}"
                     data-unidad="${producto.unidad_medida}">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${producto.descripcion}</strong>
                            <br>
                            <small class="text-muted">${producto.unidad_medida}</small>
                        </div>
                        <div class="text-end">
                            <div class="h6 mb-0">${formatearPrecio(producto.precio_publico)}</div>
                            <small class="text-muted">Click para agregar</small>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    $(contenedor).html(html);
}

function agregarProductoALista(producto) {
    // Verificar si el producto ya está en la lista
    if ($('.producto-item[data-id="' + producto.id + '"]').length > 0) {
        mostrarAlerta('El producto ya está en la lista', 'warning');
        return;
    }
    
    const html = `
        <div class="producto-item" data-id="${producto.id}">
            <div class="row align-items-center">
                <div class="col-md-5">
                    <strong>${producto.descripcion}</strong>
                    <br>
                    <small class="text-muted">${producto.unidad}</small>
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control cantidad" 
                           name="productos[${producto.id}][cantidad]" 
                           value="1" min="1" required>
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control precio-unitario" 
                           name="productos[${producto.id}][precio]" 
                           value="${producto.precio}" 
                           step="0.01" min="0" required>
                </div>
                <div class="col-md-2">
                    <span class="subtotal">${formatearPrecio(producto.precio)}</span>
                    <input type="hidden" name="productos[${producto.id}][producto_id]" value="${producto.id}">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-danger btn-sm eliminar-producto">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    $('#productos-lista').append(html);
    recalcularTotal();
}

function actualizarSubtotal(item) {
    const cantidad = parseFloat(item.find('.cantidad').val()) || 0;
    const precio = parseFloat(item.find('.precio-unitario').val()) || 0;
    const subtotal = cantidad * precio;
    
    item.find('.subtotal').text(formatearPrecio(subtotal));
}

function recalcularTotal() {
    let total = 0;
    
    $('.producto-item').each(function() {
        const cantidad = parseFloat($(this).find('.cantidad').val()) || 0;
        const precio = parseFloat($(this).find('.precio-unitario').val()) || 0;
        total += cantidad * precio;
    });
    
    $('#total-factura').text(formatearPrecio(total));
    $('input[name="total"]').val(total);
}

// =====================================================
// VALIDACIONES DE FORMULARIOS
// =====================================================

function validarFormulario(form) {
    let valido = true;
    
    // Limpiar errores previos
    form.find('.is-invalid').removeClass('is-invalid');
    form.find('.error-message').remove();
    
    // Validar campos requeridos
    form.find('[required]').each(function() {
        if (!$(this).val()) {
            $(this).addClass('is-invalid');
            $(this).after('<div class="error-message text-danger">Este campo es obligatorio</div>');
            valido = false;
        }
    });
    
    // Validar emails
    form.find('input[type="email"]').each(function() {
        const email = $(this).val();
        if (email && !validarEmail(email)) {
            $(this).addClass('is-invalid');
            $(this).after('<div class="error-message text-danger">Email inválido</div>');
            valido = false;
        }
    });
    
    // Validar números
    form.find('input[type="number"]').each(function() {
        const valor = parseFloat($(this).val());
        const min = parseFloat($(this).attr('min'));
        const max = parseFloat($(this).attr('max'));
        
        if (!isNaN(min) && valor < min) {
            $(this).addClass('is-invalid');
            $(this).after(`<div class="error-message text-danger">Valor mínimo: ${min}</div>`);
            valido = false;
        }
        
        if (!isNaN(max) && valor > max) {
            $(this).addClass('is-invalid');
            $(this).after(`<div class="error-message text-danger">Valor máximo: ${max}</div>`);
            valido = false;
        }
    });
    
    // Validaciones específicas
    if (form.attr('id') === 'form-factura') {
        valido = validarFormularioFactura(form) && valido;
    }
    
    return valido;
}

function validarFormularioFactura(form) {
    let valido = true;
    
    // Verificar que haya al menos un producto
    if ($('.producto-item').length === 0) {
        mostrarAlerta('Debe agregar al menos un producto a la factura', 'error');
        valido = false;
    }
    
    // Verificar cantidades válidas
    $('.cantidad').each(function() {
        const cantidad = parseFloat($(this).val()) || 0;
        if (cantidad <= 0) {
            $(this).addClass('is-invalid');
            valido = false;
        }
    });
    
    // Verificar precios válidos
    $('.precio-unitario').each(function() {
        const precio = parseFloat($(this).val()) || 0;
        if (precio <= 0) {
            $(this).addClass('is-invalid');
            valido = false;
        }
    });
    
    return valido;
}

function validarEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// =====================================================
// FUNCIONES DE STORAGE
// =====================================================

function guardarEnStorage(clave, datos) {
    try {
        localStorage.setItem('metru_' + clave, JSON.stringify(datos));
    } catch (e) {
        console.error('Error guardando en localStorage:', e);
    }
}

function obtenerDeStorage(clave) {
    try {
        const datos = localStorage.getItem('metru_' + clave);
        return datos ? JSON.parse(datos) : null;
    } catch (e) {
        console.error('Error obteniendo de localStorage:', e);
        return null;
    }
}

function limpiarStorage(clave) {
    try {
        localStorage.removeItem('metru_' + clave);
    } catch (e) {
        console.error('Error limpiando localStorage:', e);
    }
}

// =====================================================
// FUNCIONES DE IMPRESIÓN
// =====================================================

function imprimirElemento(elementoId) {
    const elemento = document.getElementById(elementoId);
    if (!elemento) {
        mostrarAlerta('Elemento no encontrado para imprimir', 'error');
        return;
    }
    
    const ventanaImpresion = window.open('', '_blank');
    ventanaImpresion.document.write(`
        <html>
        <head>
            <title>Impresión - Sistema Metru</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { font-family: Arial, sans-serif; }
                .no-print { display: none; }
                @media print {
                    body { -webkit-print-color-adjust: exact; }
                }
            </style>
        </head>
        <body>
            ${elemento.innerHTML}
            <script>
                window.onload = function() {
                    window.print();
                    window.close();
                };
            </script>
        </body>
        </html>
    `);
    ventanaImpresion.document.close();
}

// =====================================================
// FUNCIONES DE DEPURACIÓN Y TESTING
// =====================================================

// Función para probar la búsqueda desde consola
function probarBusqueda(termino) {
    console.log('Probando búsqueda con término:', termino);
    
    buscarProductos(termino, function(productos) {
        console.log('Resultados encontrados:', productos.length);
        console.table(productos);
        
        if (productos.length > 0) {
            mostrarAlerta(`Encontrados ${productos.length} productos para "${termino}"`, 'success');
        } else {
            mostrarAlerta(`No se encontraron productos para "${termino}"`, 'info');
        }
    });
}

// Función para verificar conectividad
function verificarConexion() {
    $.ajax({
        url: '../includes/buscar_productos.php',
        method: 'POST',
        data: { termino: 'test' },
        timeout: 5000,
        success: function(response) {
            console.log('✅ Conexión OK:', response);
            mostrarAlerta('Conexión con servidor OK', 'success');
        },
        error: function(xhr, status, error) {
            console.error('❌ Error de conexión:', {
                status: status,
                error: error,
                responseText: xhr.responseText
            });
            mostrarAlerta('Error de conexión con servidor', 'error');
        }
    });
}

// Función para debug en consola
function debugSistema() {
    console.log('🔧 Sistema Metru - Información de Debug');
    console.log('URL actual:', window.location.href);
    console.log('jQuery cargado:', typeof $ !== 'undefined');
    console.log('Bootstrap cargado:', typeof bootstrap !== 'undefined');
    console.log('Usuario logueado:', APP_CONFIG?.usuario_tipo || 'No definido');
    
    // Probar búsqueda
    console.log('\n🔍 Probando búsqueda...');
    probarBusqueda('coca');
    
    // Verificar archivos críticos
    console.log('\n📁 Verificando archivos...');
    verificarConexion();
}

// Hacer funciones disponibles globalmente para debugging
window.probarBusqueda = probarBusqueda;
window.verificarConexion = verificarConexion;
window.debugSistema = debugSistema;

function exportarTablaCSV(tablaId, nombreArchivo = 'export.csv') {
    const tabla = document.getElementById(tablaId);
    if (!tabla) {
        mostrarAlerta('Tabla no encontrada para exportar', 'error');
        return;
    }
    
    let csv = '';
    const filas = tabla.querySelectorAll('tr');
    
    filas.forEach(function(fila) {
        const celdas = fila.querySelectorAll('td, th');
        const valores = Array.from(celdas).map(celda => 
            '"' + celda.textContent.replace(/"/g, '""') + '"'
        );
        csv += valores.join(',') + '\n';
    });
    
    descargarCSV(csv, nombreArchivo);
}

function descargarCSV(contenidoCSV, nombreArchivo) {
    const blob = new Blob([contenidoCSV], { type: 'text/csv;charset=utf-8;' });
    const enlace = document.createElement('a');
    
    if (enlace.download !== undefined) {
        const url = URL.createObjectURL(blob);
        enlace.setAttribute('href', url);
        enlace.setAttribute('download', nombreArchivo);
        enlace.style.visibility = 'hidden';
        document.body.appendChild(enlace);
        enlace.click();
        document.body.removeChild(enlace);
    }
}

// =====================================================
// INICIALIZACIÓN DE PÁGINA
// =====================================================

// Ejecutar cuando la página esté completamente cargada
window.addEventListener('load', function() {
    // Ocultar loader si existe
    const loader = document.getElementById('page-loader');
    if (loader) {
        loader.style.display = 'none';
    }
    
    // Mostrar contenido principal
    const contenido = document.querySelector('.main-content');
    if (contenido) {
        contenido.style.opacity = '1';
    }
});