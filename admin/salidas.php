<?php
// =====================================================
// GESTIÓN DE SALIDAS DE MERCANCÍA - SISTEMA METRU
// =====================================================

include_once '../includes/functions.php';
verificarSesion('admin');

$titulo_pagina = 'Gestión de Salidas de Mercancía';
$icono_pagina = 'fas fa-shipping-fast';

// Filtros
$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');
$ruta_filtro = $_GET['ruta'] ?? '';
$estado_filtro = $_GET['estado'] ?? '';

// Construir consulta con filtros
$where_conditions = ["DATE(s.fecha_salida) = ?"];
$params = [$fecha_filtro];

if ($ruta_filtro) {
    $where_conditions[] = "s.ruta_id = ?";
    $params[] = $ruta_filtro;
}

if ($estado_filtro) {
    $where_conditions[] = "s.estado = ?";
    $params[] = $estado_filtro;
}

$where_clause = implode(' AND ', $where_conditions);

// 1. Modificar la consulta principal para obtener las salidas (alrededor de línea 40):
$sql_salidas = "SELECT 
    s.*,
    r.nombre as ruta_nombre,
    u.nombre as responsable_nombre,
    s.observaciones,
    COUNT(DISTINCT ds.producto_id) as total_productos,
    SUM(ds.cantidad) as total_cantidad,
    COUNT(DISTINCT f.id) as total_facturas,
    COALESCE(SUM(f.total), 0) as total_vendido,
    GROUP_CONCAT(DISTINCT CONCAT(ut.nombre, IF(st.es_principal, ' (Principal)', '')) SEPARATOR ', ') as trabajadores_asignados
FROM salidas_mercancia s
JOIN rutas r ON s.ruta_id = r.id
JOIN usuarios u ON s.usuario_id = u.id
LEFT JOIN detalle_salidas ds ON s.id = ds.salida_id
LEFT JOIN facturas f ON s.id = f.salida_id
LEFT JOIN salida_trabajadores st ON s.id = st.salida_id
LEFT JOIN usuarios ut ON st.trabajador_id = ut.id
WHERE {$where_clause}
GROUP BY s.id
ORDER BY s.fecha_creacion DESC";

$salidas = obtenerRegistros($sql_salidas, $params);

// Obtener rutas para filtro
$rutas = obtenerTodasLasRutas();

// Procesar acciones
if ($_POST) {
    $accion = $_POST['accion'] ?? '';
    $salida_id = $_POST['salida_id'] ?? 0;
    
    switch ($accion) {
        case 'cambiar_estado':
            $nuevo_estado = $_POST['nuevo_estado'] ?? '';
            if (in_array($nuevo_estado, ['preparando', 'en_ruta', 'finalizada'])) {
                actualizarEstadoSalida($salida_id, $nuevo_estado);
                $_SESSION['mensaje'] = 'Estado actualizado correctamente';
                $_SESSION['tipo_mensaje'] = 'success';
            }
            break;
            
        case 'eliminar':
            $salida_id = $_POST['salida_id'] ?? 0;
            
            // Solo se puede eliminar si está en estado 'preparando'
            $salida = obtenerRegistro("SELECT estado FROM salidas_mercancia WHERE id = ?", [$salida_id]);
            
            if ($salida && $salida['estado'] == 'preparando') {
                try {
                    global $pdo;
                    $pdo->beginTransaction();
                    
                    // IMPORTANTE: Eliminar en este orden exacto
                    
                    // 1. Primero eliminar asignaciones de trabajadores
                    $sql1 = "DELETE FROM salida_trabajadores WHERE salida_id = ?";
                    ejecutarConsulta($sql1, [$salida_id]);
                    
                    // 2. Luego eliminar detalles de productos
                    $sql2 = "DELETE FROM detalle_salidas WHERE salida_id = ?";
                    ejecutarConsulta($sql2, [$salida_id]);
                    
                    // 3. Verificar que no hay facturas (por si acaso)
                    $facturas = obtenerRegistro("SELECT COUNT(*) as total FROM facturas WHERE salida_id = ?", [$salida_id]);
                    if ($facturas['total'] > 0) {
                        throw new Exception("No se puede eliminar: hay facturas asociadas");
                    }
                    
                    // 4. Finalmente eliminar la salida
                    $sql3 = "DELETE FROM salidas_mercancia WHERE id = ?";
                    ejecutarConsulta($sql3, [$salida_id]);
                    
                    $pdo->commit();
                    
                    $_SESSION['mensaje'] = 'Salida eliminada correctamente';
                    $_SESSION['tipo_mensaje'] = 'success';
                    
                } catch (Exception $e) {
                    $pdo->rollback();
                    $_SESSION['mensaje'] = 'Error al eliminar: ' . $e->getMessage();
                    $_SESSION['tipo_mensaje'] = 'danger';
                    
                    // Log del error para debugging
                    error_log("Error eliminando salida $salida_id: " . $e->getMessage());
                }
            } else {
                $_SESSION['mensaje'] = 'Solo se pueden eliminar salidas en estado Preparando';
                $_SESSION['tipo_mensaje'] = 'warning';
            }
            
            header('Location: salidas.php');
            exit();
            break;
    }
    
    header('Location: salidas.php?' . http_build_query($_GET));
    exit();
}

include '../includes/header.php';
?>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-filter"></i> Filtros de Búsqueda
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3">
                    <label for="fecha" class="form-label">Fecha</label>
                    <input type="date" class="form-control" id="fecha" name="fecha" 
                           value="<?php echo htmlspecialchars($fecha_filtro); ?>">
                </div>
                <div class="col-md-3">
                    <label for="ruta" class="form-label">Ruta</label>
                    <select class="form-select" id="ruta" name="ruta">
                        <option value="">Todas las rutas</option>
                        <?php foreach ($rutas as $ruta): ?>
                            <option value="<?php echo $ruta['id']; ?>" 
                                    <?php echo $ruta_filtro == $ruta['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ruta['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="estado" class="form-label">Estado</label>
                    <select class="form-select" id="estado" name="estado">
                        <option value="">Todos los estados</option>
                        <option value="preparando" <?php echo $estado_filtro == 'preparando' ? 'selected' : ''; ?>>
                            Preparando
                        </option>
                        <option value="en_ruta" <?php echo $estado_filtro == 'en_ruta' ? 'selected' : ''; ?>>
                            En Ruta
                        </option>
                        <option value="finalizada" <?php echo $estado_filtro == 'finalizada' ? 'selected' : ''; ?>>
                            Finalizada
                        </option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Acciones rápidas -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h4>
                <i class="fas fa-list"></i> 
                Salidas del <?php echo formatearFecha($fecha_filtro); ?>
                <span class="badge bg-primary"><?php echo count($salidas); ?></span>
            </h4>
            <div>
                <a href="crear_salida.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Nueva Salida
                </a>
                <a href="salidas.php?fecha=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-primary">
                    <i class="fas fa-calendar-day"></i> Hoy
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Lista de salidas -->
<div class="card">
    <div class="card-body">
        <?php if (empty($salidas)): ?>
            <div class="text-center py-5">
                <i class="fas fa-truck fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No hay salidas registradas</h5>
                <p class="text-muted">Para esta fecha y filtros seleccionados</p>
                <a href="crear_salida.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Crear Primera Salida
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Ruta</th>
                            <th>Responsable</th>
                            <th>Estado</th>
                            <th>Productos</th>
                            <th>Facturas</th>
                            <th>Total Vendido</th>
                            <th>Fecha Creación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($salidas as $salida): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($salida['ruta_nombre']); ?></strong>
                                <?php if ($salida['observaciones']): ?>
                                    <br><small class="text-muted">
                                        <i class="fas fa-comment"></i>
                                        <?php echo htmlspecialchars(substr($salida['observaciones'], 0, 50)); ?>
                                        <?php echo strlen($salida['observaciones']) > 50 ? '...' : ''; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php if (!empty($salida['trabajadores_asignados'])): ?>
                                    <br><small class="text-muted">
                                        <i class="fas fa-users"></i> <?php echo $salida['trabajadores_asignados']; ?>
                                    </small>
                                <?php endif; ?>
                            <td>
                                <?php
                                $badges = [
                                    'preparando' => ['class' => 'secondary', 'icon' => 'fa-clock'],
                                    'en_ruta' => ['class' => 'primary', 'icon' => 'fa-truck'],
                                    'finalizada' => ['class' => 'success', 'icon' => 'fa-check-circle']
                                ];
                                $badge = $badges[$salida['estado']] ?? ['class' => 'secondary', 'icon' => 'fa-question'];
                                ?>
                                <span class="badge bg-<?php echo $badge['class']; ?>">
                                    <i class="fas <?php echo $badge['icon']; ?>"></i>
                                    <?php echo ESTADOS_SALIDA[$salida['estado']] ?? $salida['estado']; ?>
                                </span>
                            </td>
                            
                            <td>
                                <span class="badge bg-info">
                                    <?php echo $salida['total_productos']; ?> tipos
                                </span>
                                <br>
                                <small class="text-muted">
                                    <?php echo $salida['total_cantidad']; ?> unidades
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-success">
                                    <?php echo $salida['total_facturas']; ?>
                                </span>
                            </td>
                            <td>
                                <strong class="text-success">
                                    <?php echo formatearPrecio($salida['total_vendido']); ?>
                                </strong>
                            </td>
                            <td>
                                <small><?php echo formatearFechaHora($salida['fecha_creacion']); ?></small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <!-- Ver detalles -->
                                    <a href="detalle_salida.php?id=<?php echo $salida['id']; ?>" 
                                       class="btn btn-outline-primary" 
                                       title="Ver detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <!-- Cambiar estado -->
                                    <?php if ($salida['estado'] == 'preparando'): ?>
                                        <button class="btn btn-outline-info btn-sm" 
                                                onclick="cambiarEstadoConValidacion(<?php echo $salida['id']; ?>, 'en_ruta')"
                                                title="Enviar a ruta">
                                            <i class="fas fa-truck"></i>
                                        </button>

                                        <script>
                                        function cambiarEstadoConValidacion(salidaId, nuevoEstado) {
                                        if (nuevoEstado === 'en_ruta') {
                                            $.ajax({
                                                url: '../includes/validar_trabajadores.php',
                                                method: 'POST',
                                                data: { salida_id: salidaId },
                                                success: function(response) {
                                                    if (response.tiene_trabajadores) {
                                                        cambiarEstado(salidaId, nuevoEstado);
                                                    } else {
                                                        alert('Debe asignar al menos un trabajador antes de enviar a ruta');
                                                    }
                                                }
                                            });
                                        } else {
                                            cambiarEstado(salidaId, nuevoEstado);
                                        }
                                    }

                                        function cambiarEstado(salidaId, nuevoEstado) {
                                            if (confirm('¿Confirma el cambio de estado?')) {
                                                $('#form-cambiar-estado input[name="salida_id"]').val(salidaId);
                                                $('#form-cambiar-estado input[name="nuevo_estado"]').val(nuevoEstado);
                                                $('#form-cambiar-estado').submit();
                                            }
                                        }
                                        </script>
                                    <?php elseif ($salida['estado'] == 'en_ruta'): ?>
                                        <button class="btn btn-outline-warning" 
                                                onclick="cambiarEstado(<?php echo $salida['id']; ?>, 'finalizada')"
                                                title="Finalizar ruta">
                                            <i class="fas fa-stop"></i>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <!-- Eliminar (solo si está preparando) -->
                                    <?php if ($salida['estado'] == 'preparando'): ?>
                                        <button class="btn btn-outline-danger" 
                                                onclick="eliminarSalida(<?php echo $salida['id']; ?>)"
                                                title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <!-- Imprimir -->
                                    <a href="imprimir_salida.php?id=<?php echo $salida['id']; ?>" 
                                       target="_blank"
                                       class="btn btn-outline-secondary" 
                                       title="Imprimir">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <!-- Botón asignar trabajadores -->
                                    <?php if ($salida['estado'] == 'preparando'): ?>
                                        <a href="asignar_trabajadores.php?salida_id=<?php echo $salida['id']; ?>" 
                                        class="btn btn-warning btn-sm" 
                                        title="Asignar trabajadores">
                                            <i class="fas fa-users"></i> 
                                            <?php echo empty($salida['trabajadores_asignados']) ? 'Asignar' : 'Modificar'; ?> Trabajadores
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <!-- Añadir después del badge de estado -->
                        <?php if ($salida['estado'] == 'preparando'): ?>
                            <?php
                            // Verificar si hay productos sin cargar
                            $sql_pendientes = "SELECT COUNT(*) as pendientes FROM detalle_salidas WHERE salida_id = ? AND cargado = 0";
                            $pendientes = obtenerRegistro($sql_pendientes, [$salida['id']]);
                            if ($pendientes['pendientes'] > 0):
                            ?>
                            <a href="cargar_camion.php?salida_id=<?php echo $salida['id']; ?>" 
                            class="btn btn-warning btn-sm ms-2">
                                <i class="fas fa-truck-loading"></i> 
                                Cargar (<?php echo $pendientes['pendientes']; ?> pendientes)
                            </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Estadísticas del día -->
<?php if (!empty($salidas)): ?>
<div class="row mt-4">
    <div class="col-md-3">
        <div class="card border-left-primary">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                    Total Salidas
                </div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">
                    <?php echo count($salidas); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-left-success">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                    En Ruta
                </div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">
                    <?php echo count(array_filter($salidas, fn($s) => $s['estado'] == 'en_ruta')); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-left-info">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                    Total Vendido
                </div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">
                    <?php echo formatearPrecio(array_sum(array_column($salidas, 'total_vendido'))); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-left-warning">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                    Facturas Creadas
                </div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">
                    <?php echo array_sum(array_column($salidas, 'total_facturas')); ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Formularios ocultos para acciones -->
<form id="form-cambiar-estado" method="POST" style="display: none;">
    <input type="hidden" name="accion" value="cambiar_estado">
    <input type="hidden" name="salida_id" id="salida-id-estado">
    <input type="hidden" name="nuevo_estado" id="nuevo-estado">
</form>

<form id="form-eliminar" method="POST" style="display: none;">
    <input type="hidden" name="accion" value="eliminar">
    <input type="hidden" name="salida_id" id="salida-id-eliminar">
</form>

<script>
function cambiarEstado(salidaId, nuevoEstado) {
    const estados = {
        'en_ruta': '¿Confirma que desea enviar esta salida a ruta?',
        'finalizada': '¿Confirma que desea finalizar esta ruta?'
    };
    
    if (confirm(estados[nuevoEstado] || '¿Confirma el cambio de estado?')) {
        document.getElementById('salida-id-estado').value = salidaId;
        document.getElementById('nuevo-estado').value = nuevoEstado;
        document.getElementById('form-cambiar-estado').submit();
    }
}

function validarTrabajadoresAsignados(salidaId) {
    // Esta función se debe agregar en el JavaScript
    $.ajax({
        url: '../includes/validar_trabajadores.php',
        method: 'POST',
        data: { salida_id: salidaId },
        async: false,
        success: function(response) {
            if (!response.tiene_trabajadores) {
                alert('Debe asignar al menos un trabajador antes de enviar a ruta');
                return false;
            }
        }
    });
    return true;
}

function eliminarSalida(salidaId) {
    if (confirm('¿Está seguro de que desea eliminar esta salida?\n\nEsta acción no se puede deshacer.')) {
        document.getElementById('salida-id-eliminar').value = salidaId;
        document.getElementById('form-eliminar').submit();
    }
}

// Auto-actualizar página cada 2 minutos para ver cambios en tiempo real
setInterval(function() {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 120000);
</script>

<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df!important;
}
.border-left-success {
    border-left: 0.25rem solid #1cc88a!important;
}
.border-left-info {
    border-left: 0.25rem solid #36b9cc!important;
}
.border-left-warning {
    border-left: 0.25rem solid #f6c23e!important;
}
.text-xs {
    font-size: 0.7rem;
}
</style>

<?php include '../includes/footer.php'; ?>