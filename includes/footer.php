</div> <!-- Fin main-content -->
        </div> <!-- Fin columna principal -->
    </div> <!-- Fin row -->
</div> <!-- Fin container-fluid -->



<!-- Footer -->
<footer class="bg-dark text-white text-center py-3 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-6 text-start">
                <p class="mb-0">
                    <strong><?php echo APP_NAME; ?></strong> v<?php echo APP_VERSION; ?>
                </p>
                <small class="text-muted">Sistema de Gestión de Distribución</small>
            </div>
            <div class="col-md-6 text-end">
                <p class="mb-0">
                    <i class="fas fa-clock"></i> 
                    <?php echo date('d/m/Y H:i'); ?>
                </p>
                <small class="text-muted">
                    <?php if (isset($_SESSION['usuario_nombre'])): ?>
                        Usuario: <?php echo $_SESSION['usuario_nombre']; ?>
                    <?php endif; ?>
                </small>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Custom JavaScript -->
<?php
$js_path = '';
if (file_exists('js/main.js')) {
    $js_path = 'js/main.js';
} elseif (file_exists('../js/main.js')) {
    $js_path = '../js/main.js';
}
if ($js_path): ?>
<script src="<?php echo $js_path; ?>"></script>
<?php endif; ?>

<script>
// Configuración global de JavaScript
const APP_CONFIG = {
    url_base: '<?php echo obtenerUrlBase(); ?>',
    csrf_token: '<?php echo isset($_SESSION["csrf_token"]) ? $_SESSION["csrf_token"] : ""; ?>',
    usuario_tipo: '<?php echo isset($_SESSION["usuario_tipo"]) ? $_SESSION["usuario_tipo"] : ""; ?>'
};

// Función para mostrar alertas
function mostrarAlerta(mensaje, tipo = 'info') {
    const alertas = {
        'success': '<i class="fas fa-check-circle"></i>',
        'error': '<i class="fas fa-exclamation-triangle"></i>',
        'warning': '<i class="fas fa-exclamation-circle"></i>',
        'info': '<i class="fas fa-info-circle"></i>'
    };
    
    const alerta = `
        <div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
            ${alertas[tipo] || alertas['info']} ${mensaje}
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

// Función para confirmar acciones
function confirmarAccion(mensaje, callback) {
    if (confirm(mensaje)) {
        callback();
    }
}

// Función para formatear precios
function formatearPrecio(precio) {
    return '$' + new Intl.NumberFormat('es-CO').format(precio);
}

// Función para formatear fechas
function formatearFecha(fecha) {
    return new Date(fecha).toLocaleDateString('es-CO');
}

// Auto-refresh para páginas de monitoreo
<?php if (isset($auto_refresh) && $auto_refresh): ?>
setTimeout(() => {
    location.reload();
}, 30000); // Refresh cada 30 segundos
<?php endif; ?>

// Validación de formularios
$(document).ready(function() {
    // Validar números positivos
    $('.numero-positivo').on('input', function() {
        let valor = $(this).val();
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
    
    // Confirmación para botones de eliminar
    $('.btn-eliminar').on('click', function(e) {
        e.preventDefault();
        const url = $(this).attr('href');
        confirmarAccion('¿Está seguro de que desea eliminar este elemento?', function() {
            window.location.href = url;
        });
    });
    
    // Loading en formularios
    $('form').on('submit', function() {
        $(this).find('button[type="submit"]').prop('disabled', true).html(
            '<i class="fas fa-spinner fa-spin"></i> Procesando...'
        );
    });
    
    // Tooltips de Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Función para buscar productos en tiempo real
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

// Función para calcular totales automáticamente
function calcularTotal() {
    let total = 0;
    $('.subtotal').each(function() {
        let subtotal = parseFloat($(this).text().replace(/[^0-9.-]+/g,"")) || 0;
        total += subtotal;
    });
    $('#total').text(formatearPrecio(total));
    $('#total_hidden').val(total);
}
</script>

</body>
</html>