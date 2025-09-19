<?php
// =====================================================
// CONFIGURACIÓN DE PRODUCTOS - SISTEMA METRU
// =====================================================

include_once '../includes/functions.php';
verificarSesion('admin');

$titulo_pagina = 'Configuración de Productos';
$icono_pagina = 'fas fa-cog';

// Procesar actualización de precios y ganancias
if ($_POST && isset($_POST['accion'])) {
    if ($_POST['accion'] == 'actualizar_precio') {
        $producto_id = $_POST['producto_id'] ?? 0;
        $costo = $_POST['costo'] ?? 0;
        $precio_publico = $_POST['precio_publico'] ?? 0;
        
        // Calcular ganancia
        $ganancia = $precio_publico - $costo;
        $porcentaje_ganancia = $costo > 0 ? (($ganancia / $costo) * 100) : 0;
        
        // Actualizar en la base de datos
        ejecutarConsulta(
            "UPDATE productos SET costo = ?, precio_publico = ?, ganancia = ?, porcentaje_ganancia = ? WHERE id = ?",
            [$costo, $precio_publico, $ganancia, $porcentaje_ganancia, $producto_id]
        );
        
        $_SESSION['mensaje'] = 'Precio actualizado correctamente';
        $_SESSION['tipo_mensaje'] = 'success';
        
        header('Location: configuracion_productos.php');
        exit();
    }
}

// Obtener productos con información de precios
$sql = "SELECT p.*, 
        COALESCE(p.costo, 0) as costo,
        COALESCE(p.precio_publico, 0) as precio_publico,
        COALESCE(p.precio_publico - p.costo, 0) as ganancia,
        CASE 
            WHEN p.costo > 0 THEN ((p.precio_publico - p.costo) / p.costo * 100)
            ELSE 0
        END as porcentaje_ganancia
        FROM productos p 
        WHERE p.activo = 1 
        ORDER BY p.descripcion";

$productos = obtenerRegistros($sql);

include '../includes/header.php';
?>

<style>
.tabla-precios td {
    vertical-align: middle !important;
}
.input-precio {
    width: 120px;
}
.ganancia-positiva {
    color: #28a745;
    font-weight: bold;
}
.ganancia-negativa {
    color: #dc3545;
    font-weight: bold;
}
.porcentaje-badge {
    font-size: 0.9rem;
    padding: 0.4rem 0.6rem;
}
</style>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" id="buscar-producto" 
                           placeholder="Buscar producto por nombre o código...">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="filtro-grupo">
                    <option value="">Todos los grupos</option>
                    <?php foreach (GRUPOS_PRODUCTOS as $id => $nombre): ?>
                        <option value="<?php echo $id; ?>"><?php echo $nombre; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100" onclick="exportarExcel()">
                    <i class="fas fa-download"></i> Exportar Excel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Resumen de ganancias -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h6 class="text-primary">Productos Configurados</h6>
                <h4><?php echo count(array_filter($productos, fn($p) => $p['costo'] > 0)); ?></h4>
                <small>de <?php echo count($productos); ?> total</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <h6 class="text-success">Ganancia Promedio</h6>
                <h4><?php 
                    $promedios = array_filter($productos, fn($p) => $p['porcentaje_ganancia'] > 0);
                    echo count($promedios) > 0 ? 
                        number_format(array_sum(array_column($promedios, 'porcentaje_ganancia')) / count($promedios), 1) . '%' 
                        : '0%';
                ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h6 class="text-warning">Mayor Ganancia</h6>
                <h4><?php 
                    $max_ganancia = max(array_column($productos, 'porcentaje_ganancia'));
                    echo number_format($max_ganancia, 1) . '%';
                ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <h6 class="text-info">Sin Configurar</h6>
                <h4 class="text-danger"><?php echo count(array_filter($productos, fn($p) => $p['costo'] == 0)); ?></h4>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de productos -->
<div class="card">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-dollar-sign"></i> Configuración de Precios y Ganancias
        </h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover tabla-precios" id="tabla-productos">
                <thead class="table-dark">
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Unidad</th>
                        <th>Costo</th>
                        <th>Precio Venta</th>
                        <th>Ganancia</th>
                        <th>% Ganancia</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $producto): ?>
                    <tr data-grupo="<?php echo $producto['grupo_id']; ?>" 
                        data-nombre="<?php echo strtolower($producto['descripcion']); ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($producto['codigo']); ?></strong>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($producto['descripcion']); ?>
                            <br>
                            <small class="text-muted">
                                <?php echo GRUPOS_PRODUCTOS[$producto['grupo_id']] ?? 'Sin grupo'; ?>
                            </small>
                        </td>
                        <td>
                            <span class="badge bg-secondary">
                                <?php echo $producto['unidad_medida']; ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" class="form-inline form-precio" 
                                  data-producto-id="<?php echo $producto['id']; ?>">
                                <input type="hidden" name="accion" value="actualizar_precio">
                                <input type="hidden" name="producto_id" value="<?php echo $producto['id']; ?>">
                                
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="number" 
                                           name="costo" 
                                           class="form-control input-precio input-costo" 
                                           value="<?php echo $producto['costo']; ?>"
                                           step="0.01"
                                           min="0"
                                           placeholder="0.00">
                                </div>
                        </td>
                        <td>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="number" 
                                           name="precio_publico" 
                                           class="form-control input-precio input-venta" 
                                           value="<?php echo $producto['precio_publico']; ?>"
                                           step="0.01"
                                           min="0"
                                           placeholder="0.00">
                                </div>
                        </td>
                        <td class="ganancia-celda">
                            <span class="<?php echo $producto['ganancia'] >= 0 ? 'ganancia-positiva' : 'ganancia-negativa'; ?>">
                                $<?php echo number_format($producto['ganancia'], 0); ?>
                            </span>
                        </td>
                        <td class="porcentaje-celda">
                            <?php if ($producto['costo'] > 0): ?>
                                <span class="badge porcentaje-badge bg-<?php echo $producto['porcentaje_ganancia'] >= 30 ? 'success' : ($producto['porcentaje_ganancia'] >= 15 ? 'warning' : 'danger'); ?>">
                                    <?php echo number_format($producto['porcentaje_ganancia'], 1); ?>%
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="fas fa-save"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Búsqueda en tiempo real
$('#buscar-producto').on('input', function() {
    const busqueda = $(this).val().toLowerCase();
    
    $('#tabla-productos tbody tr').each(function() {
        const nombre = $(this).data('nombre');
        if (nombre.includes(busqueda)) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
});

// Filtro por grupo
$('#filtro-grupo').on('change', function() {
    const grupo = $(this).val();
    
    $('#tabla-productos tbody tr').each(function() {
        if (!grupo || $(this).data('grupo') == grupo) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
});

// Calcular ganancia en tiempo real
$('.input-costo, .input-venta').on('input', function() {
    const fila = $(this).closest('tr');
    const costo = parseFloat(fila.find('.input-costo').val()) || 0;
    const venta = parseFloat(fila.find('.input-venta').val()) || 0;
    
    const ganancia = venta - costo;
    const porcentaje = costo > 0 ? ((ganancia / costo) * 100) : 0;
    
    // Actualizar visualización
    const gananciaSpan = fila.find('.ganancia-celda span');
    gananciaSpan.text('$' + ganancia.toLocaleString('es-CO'));
    gananciaSpan.removeClass('ganancia-positiva ganancia-negativa');
    gananciaSpan.addClass(ganancia >= 0 ? 'ganancia-positiva' : 'ganancia-negativa');
    
    // Actualizar porcentaje
    const porcentajeBadge = fila.find('.porcentaje-celda .badge');
    if (costo > 0) {
        porcentajeBadge.text(porcentaje.toFixed(1) + '%');
        porcentajeBadge.removeClass('bg-success bg-warning bg-danger bg-secondary');
        if (porcentaje >= 30) {
            porcentajeBadge.addClass('bg-success');
        } else if (porcentaje >= 15) {
            porcentajeBadge.addClass('bg-warning');
        } else {
            porcentajeBadge.addClass('bg-danger');
        }
    } else {
        porcentajeBadge.text('N/A');
        porcentajeBadge.removeClass('bg-success bg-warning bg-danger').addClass('bg-secondary');
    }
});

// Guardar cambios automáticamente
let timeoutId;
$('.input-costo, .input-venta').on('input', function() {
    const form = $(this).closest('form');
    const boton = form.find('button[type="submit"]');
    
    // Cambiar icono a reloj
    boton.html('<i class="fas fa-clock"></i>');
    
    clearTimeout(timeoutId);
    timeoutId = setTimeout(function() {
        // Cambiar icono de vuelta a guardar
        boton.html('<i class="fas fa-save"></i>');
    }, 2000);
});

// Exportar a Excel
function exportarExcel() {
    // Implementar exportación
    alert('Función de exportación en desarrollo');
}

// Validación de formularios
$('.form-precio').on('submit', function(e) {
    const costo = parseFloat($(this).find('.input-costo').val()) || 0;
    const venta = parseFloat($(this).find('.input-venta').val()) || 0;
    
    if (venta < costo) {
        if (!confirm('El precio de venta es menor al costo. ¿Desea continuar?')) {
            e.preventDefault();
            return false;
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>