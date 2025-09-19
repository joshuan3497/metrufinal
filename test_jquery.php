<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test jQuery - Sistema Metru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- jQuery PRIMERO -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h1>Test de jQuery y Búsqueda</h1>
        
        <div class="card">
            <div class="card-body">
                <h5>Estado de jQuery:</h5>
                <p id="jquery-status" class="alert alert-info">Verificando...</p>
                
                <h5>Búsqueda de Productos:</h5>
                <input type="text" id="buscar" class="form-control mb-3" placeholder="Escriba: coca, posto, agua...">
                <div id="resultados"></div>
                
                <button class="btn btn-primary mt-3" onclick="testBusqueda()">Probar Búsqueda Manual</button>
            </div>
        </div>
    </div>

    <script>
    // Verificar jQuery
    if (typeof jQuery !== 'undefined') {
        $('#jquery-status').removeClass('alert-info').addClass('alert-success').text('✅ jQuery cargado correctamente! Versión: ' + $.fn.jquery);
        console.log('✅ jQuery OK');
    } else {
        document.getElementById('jquery-status').className = 'alert alert-danger';
        document.getElementById('jquery-status').textContent = '❌ jQuery NO está cargado';
    }
    
    // Búsqueda en tiempo real
    $('#buscar').on('input', function() {
        const termino = $(this).val();
        
        if (termino.length >= 2) {
            $('#resultados').html('<div class="alert alert-info">Buscando...</div>');
            
            $.ajax({
                url: 'includes/buscar_productos.php',
                method: 'POST',
                data: { termino: termino },
                dataType: 'json',
                success: function(productos) {
                    console.log('Productos:', productos);
                    
                    if (productos.length > 0) {
                        let html = '<ul class="list-group">';
                        productos.forEach(function(p) {
                            html += '<li class="list-group-item">' + p.descripcion + ' - $' + p.precio_publico + '</li>';
                        });
                        html += '</ul>';
                        $('#resultados').html(html);
                    } else {
                        $('#resultados').html('<div class="alert alert-warning">No se encontraron productos</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#resultados').html('<div class="alert alert-danger">Error: ' + error + '</div>');
                    console.error('Error:', xhr.responseText);
                }
            });
        } else {
            $('#resultados').empty();
        }
    });
    
    function testBusqueda() {
        $('#buscar').val('coca').trigger('input');
    }
    </script>
</body>
</html>