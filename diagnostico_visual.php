<?php
// Diagnóstico Visual - Sistema Metru
include_once 'config/config.php';
include_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico Visual - Sistema Metru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .test-box {
            border: 2px solid #007bff;
            padding: 20px;
            margin: 10px 0;
            background-color: #f8f9fa;
        }
        .visible-test {
            background-color: #28a745;
            color: white;
            padding: 10px;
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>🔍 Diagnóstico Visual del Problema</h1>
        
        <!-- Test 1: Visibilidad básica -->
        <div class="test-box">
            <h5>Test 1: Elementos Visibles</h5>
            <div id="test1" class="visible-test">Si ves este texto en verde, los estilos funcionan</div>
        </div>
        
        <!-- Test 2: Inserción dinámica -->
        <div class="test-box">
            <h5>Test 2: Inserción Dinámica con jQuery</h5>
            <div id="test2"></div>
            <button class="btn btn-primary" onclick="insertarElemento()">Insertar Elemento</button>
        </div>
        
        <!-- Test 3: Búsqueda simulada -->
        <div class="test-box">
            <h5>Test 3: Búsqueda Simulada</h5>
            <input type="text" id="buscar-test" class="form-control mb-3" placeholder="Escriba 'posto'">
            <div id="resultados-test"></div>
        </div>
        
        <!-- Test 4: Estructura real -->
        <div class="test-box">
            <h5>Test 4: Estructura Real de Búsqueda</h5>
            <div id="buscar-producto-container">
                <input type="text" id="buscar-producto" class="form-control" placeholder="Buscar productos...">
            </div>
            <div id="resultados-busqueda"></div>
        </div>
        
        <!-- Información de Debug -->
        <div class="test-box">
            <h5>Información de Debug</h5>
            <div id="debug-info"></div>
        </div>
    </div>

    <script>
    // Test 2: Inserción dinámica
    function insertarElemento() {
        $('#test2').html('<div class="alert alert-success">✅ Elemento insertado dinámicamente</div>');
    }
    
    // Test 3: Búsqueda simulada
    $('#buscar-test').on('input', function() {
        const valor = $(this).val();
        if (valor.length >= 2) {
            $('#resultados-test').html(`
                <div class="list-group">
                    <div class="list-group-item">Resultado 1 para: ${valor}</div>
                    <div class="list-group-item">Resultado 2 para: ${valor}</div>
                    <div class="list-group-item">Resultado 3 para: ${valor}</div>
                </div>
            `);
        } else {
            $('#resultados-test').empty();
        }
    });
    
    // Test 4: Probar con la estructura real
    function probarBusquedaReal() {
        console.log('Probando búsqueda real...');
        
        // Simular respuesta exitosa
        const productosSimulados = [
            {id: 1, codigo: '1', descripcion: 'GASEOSA POSTOBON 250 X 30', unidad_medida: 'CAJA', precio_publico: 25000},
            {id: 2, codigo: '2', descripcion: 'GASEOSA POSTOBON 350X30', unidad_medida: 'CAJA', precio_publico: 45000}
        ];
        
        let html = '<div class="list-group">';
        productosSimulados.forEach(function(producto) {
            html += `
                <div class="list-group-item list-group-item-action">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">${producto.descripcion}</h6>
                            <small class="text-muted">Código: ${producto.codigo}</small>
                        </div>
                        <div class="text-end">
                            <h6 class="text-success mb-0">$${producto.precio_publico}</h6>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        
        $('#resultados-busqueda').html(html);
        console.log('HTML insertado:', $('#resultados-busqueda').html());
    }
    
    // Debug info
    function actualizarDebug() {
        const info = {
            'jQuery Version': $.fn.jquery,
            'Elementos #resultados-busqueda': $('#resultados-busqueda').length,
            'HTML en #resultados-busqueda': $('#resultados-busqueda').html() ? 'Tiene contenido' : 'Vacío',
            'Visibilidad': $('#resultados-busqueda').is(':visible') ? 'Visible' : 'Oculto',
            'Display CSS': $('#resultados-busqueda').css('display'),
            'Opacity CSS': $('#resultados-busqueda').css('opacity'),
            'Height': $('#resultados-busqueda').height() + 'px'
        };
        
        let html = '<table class="table table-sm">';
        for (let key in info) {
            html += `<tr><td>${key}:</td><td><strong>${info[key]}</strong></td></tr>`;
        }
        html += '</table>';
        
        $('#debug-info').html(html);
    }
    
    // Ejecutar al cargar
    $(document).ready(function() {
        console.log('Document ready');
        actualizarDebug();
        
        // Auto-ejecutar prueba
        setTimeout(function() {
            $('#buscar-test').val('posto').trigger('input');
            probarBusquedaReal();
            setTimeout(actualizarDebug, 500);
        }, 1000);
    });
    </script>
</body>
</html>