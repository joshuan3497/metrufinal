<!-- Modal para ver detalle de factura -->
<div class="modal fade" id="modalDetalleFactura" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-invoice"></i> Detalle de Factura
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenido-detalle-factura">
                <!-- Se carga dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="imprimirFactura()">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function verDetalleFactura(facturaId) {
    // Mostrar loading
    $('#contenido-detalle-factura').html(`
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
    `);
    
    // Mostrar modal
    $('#modalDetalleFactura').modal('show');
    
    // Cargar contenido
    $.ajax({
        url: '../includes/ajax_detalle_factura.php',
        method: 'POST',
        data: { factura_id: facturaId },
        success: function(response) {
            $('#contenido-detalle-factura').html(response);
        },
        error: function() {
            $('#contenido-detalle-factura').html(`
                <div class="alert alert-danger">
                    Error al cargar el detalle de la factura
                </div>
            `);
        }
    });
}

function imprimirFactura() {
    const contenido = document.getElementById('contenido-detalle-factura').innerHTML;
    const ventana = window.open('', '_blank');
    
    ventana.document.write(`
        <html>
            <head>
                <title>Factura - Sistema Metru</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body { padding: 20px; }
                    @media print {
                        .no-print { display: none; }
                    }
                </style>
            </head>
            <body>
                ${contenido}
                <script>
                    window.onload = function() { 
                        window.print(); 
                        window.onafterprint = function() { window.close(); }
                    }
                <\/script>
            </body>
        </html>
    `);
    
    ventana.document.close();
}
</script>