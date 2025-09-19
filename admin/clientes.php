<?php
// =====================================================
// GESTIÓN DE CLIENTES - SISTEMA METRU
// =====================================================

include_once '../includes/functions.php';
verificarSesion('admin');

$titulo_pagina = 'Gestión de Clientes';
$icono_pagina = 'fas fa-store';

// Filtros
$ruta_filtro = $_GET['ruta'] ?? '';
$activo_filtro = $_GET['activo'] ?? '1';
$busqueda = $_GET['buscar'] ?? '';

// Procesar acciones
if ($_POST) {
    $accion = $_POST['accion'] ?? '';
    
    switch ($accion) {
        case 'crear':
            $nombre = limpiarDatos($_POST['nombre'] ?? '');
            $ruta_id = $_POST['ruta_id'] ?? 0;
            
            // Validaciones
            $errores = [];
            if (!$nombre) $errores[] = "Nombre del cliente es obligatorio";
            if (!$ruta_id) $errores[] = "Ruta es obligatoria";
            
            // Verificar que la ruta existe y está activa
            $ruta_valida = obtenerRegistro("SELECT id FROM rutas WHERE id = ? AND activa = 1", [$ruta_id]);
            if (!$ruta_valida) $errores[] = "La ruta seleccionada no es válida";
            
            if (empty($errores)) {
                try {
                    ejecutarConsulta(
                        "INSERT INTO clientes (nombre, ruta_id) VALUES (?, ?)",
                        [$nombre, $ruta_id]
                    );
                    $_SESSION['mensaje'] = 'Cliente creado exitosamente';
                    $_SESSION['tipo_mensaje'] = 'success';
                } catch (Exception $e) {
                    $_SESSION['mensaje'] = 'Error al crear cliente: ' . $e->getMessage();
                    $_SESSION['tipo_mensaje'] = 'danger';
                }
            } else {
                $_SESSION['mensaje'] = implode('<br>', $errores);
                $_SESSION['tipo_mensaje'] = 'danger';
            }
            
            header('Location: clientes.php' . ($ruta_filtro ? '?ruta=' . $ruta_filtro : ''));
            exit();
            break;
            
        case 'editar':
            $id = $_POST['id'] ?? 0;
            $nombre = limpiarDatos($_POST['nombre'] ?? '');
            $ruta_id = $_POST['ruta_id'] ?? 0;
            
            // Validaciones
            $errores = [];
            if (!$nombre) $errores[] = "Nombre del cliente es obligatorio";
            if (!$ruta_id) $errores[] = "Ruta es obligatoria";
            
            // Verificar que la ruta existe y está activa
            $ruta_valida = obtenerRegistro("SELECT id FROM rutas WHERE id = ? AND activa = 1", [$ruta_id]);
            if (!$ruta_valida) $errores[] = "La ruta seleccionada no es válida";
            
            if (empty($errores)) {
                try {
                    ejecutarConsulta(
                        "UPDATE clientes SET nombre = ?, ruta_id = ? WHERE id = ?",
                        [$nombre, $ruta_id, $id]
                    );
                    $_SESSION['mensaje'] = 'Cliente actualizado exitosamente';
                    $_SESSION['tipo_mensaje'] = 'success';
                } catch (Exception $e) {
                    $_SESSION['mensaje'] = 'Error al actualizar cliente: ' . $e->getMessage();
                    $_SESSION['tipo_mensaje'] = 'danger';
                }
            } else {
                $_SESSION['mensaje'] = implode('<br>', $errores);
                $_SESSION['tipo_mensaje'] = 'danger';
            }
            
            header('Location: clientes.php' . ($ruta_filtro ? '?ruta=' . $ruta_filtro : ''));
            exit();
            break;
            
        case 'cambiar_estado':
            $id = $_POST['id'] ?? 0;
            $nuevo_estado = $_POST['activo'] == '1' ? 0 : 1;
            
            ejecutarConsulta("UPDATE clientes SET activo = ? WHERE id = ?", [$nuevo_estado, $id]);
            
            $mensaje = $nuevo_estado ? 'Cliente activado' : 'Cliente desactivado';
            $_SESSION['mensaje'] = $mensaje;
            $_SESSION['tipo_mensaje'] = 'success';
            
            header('Location: clientes.php' . ($ruta_filtro ? '?ruta=' . $ruta_filtro : ''));
            exit();
            break;
    }
}

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if ($busqueda) {
    $where_conditions[] = "c.nombre LIKE ?";
    $params[] = "%$busqueda%";
}

if ($ruta_filtro) {
    $where_conditions[] = "c.ruta_id = ?";
    $params[] = $ruta_filtro;
}

if ($activo_filtro !== '') {
    $where_conditions[] = "c.activo = ?";
    $params[] = $activo_filtro;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Obtener clientes con estadísticas
$sql_clientes = "SELECT 
    c.*,
    r.nombre as ruta_nombre,
    r.activa as ruta_activa,
    COUNT(DISTINCT f.id) as total_facturas,
    COALESCE(SUM(f.total), 0) as total_comprado,
    MAX(f.fecha_venta) as ultima_compra
FROM clientes c
JOIN rutas r ON c.ruta_id = r.id
LEFT JOIN facturas f ON c.id = f.cliente_id
$where_clause
GROUP BY c.id
ORDER BY c.activo DESC, r.nombre ASC, c.nombre ASC";

$clientes = obtenerRegistros($sql_clientes, $params);

// Obtener rutas para filtros y formularios
$rutas = obtenerTodasLasRutas();

// Estadísticas generales
$total_clientes = count($clientes);
$clientes_activos = count(array_filter($clientes, fn($c) => $c['activo']));
$total_facturas = array_sum(array_column($clientes, 'total_facturas'));
$total_vendido = array_sum(array_column($clientes, 'total_comprado'));

include '../includes/header.php';
?>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-primary text-center">
            <div class="card-body">
                <i class="fas fa-store fa-2x text-primary mb-2"></i>
                <h4 class="text-primary"><?php echo $total_clientes; ?></h4>
                <small class="text-muted">Total Clientes</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-success text-center">
            <div class="card-body">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h4 class="text-success"><?php echo $clientes_activos; ?></h4>
                <small class="text-muted">Clientes Activos</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info text-center">
            <div class="card-body">
                <i class="fas fa-receipt fa-2x text-info mb-2"></i>
                <h4 class="text-info"><?php echo number_format($total_facturas); ?></h4>
                <small class="text-muted">Total Facturas</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning text-center">
            <div class="card-body">
                <i class="fas fa-dollar-sign fa-2x text-warning mb-2"></i>
                <h6 class="text-warning"><?php echo formatearPrecio($total_vendido); ?></h6>
                <small class="text-muted">Total Vendido</small>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-filter"></i> Filtros y Búsqueda
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-4">
                    <label for="buscar" class="form-label">Buscar Cliente</label>
                    <input type="text" class="form-control" id="buscar" name="buscar" 
                           value="<?php echo htmlspecialchars($busqueda); ?>" 
                           placeholder="Nombre del cliente...">
                </div>
                <div class="col-md-3">
                    <label for="ruta" class="form-label">Ruta</label>
                    <select class="form-select" id="ruta" name="ruta">
                        <option value="">Todas las rutas</option>
                        <?php foreach ($rutas as $ruta): ?>
                            <option value="<?php echo $ruta['id']; ?>" <?php echo $ruta_filtro == $ruta['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ruta['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="activo" class="form-label">Estado</label>
                    <select class="form-select" id="activo" name="activo">
                        <option value="">Todos</option>
                        <option value="1" <?php echo $activo_filtro === '1' ? 'selected' : ''; ?>>Activos</option>
                        <option value="0" <?php echo $activo_filtro === '0' ? 'selected' : ''; ?>>Inactivos</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid gap-2 d-md-flex">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modal-crear-cliente">
                            <i class="fas fa-plus"></i> Nuevo
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Lista de clientes -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-list"></i> Lista de Clientes
            <?php if ($ruta_filtro): ?>
                <?php 
                $ruta_seleccionada = array_filter($rutas, fn($r) => $r['id'] == $ruta_filtro);
                if (!empty($ruta_seleccionada)) {
                    $ruta_seleccionada = array_values($ruta_seleccionada)[0];
                    echo ' - ' . htmlspecialchars($ruta_seleccionada['nombre']);
                }
                ?>
            <?php endif; ?>
        </h6>
        <span class="badge bg-primary"><?php echo count($clientes); ?> clientes</span>
    </div>
    <div class="card-body">
        <?php if (empty($clientes)): ?>
            <div class="text-center py-5">
                <i class="fas fa-store fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No se encontraron clientes</h5>
                <p class="text-muted">
                    <?php if ($ruta_filtro): ?>
                        No hay clientes registrados para esta ruta
                    <?php else: ?>
                        Intente con otros filtros o cree un nuevo cliente
                    <?php endif; ?>
                </p>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modal-crear-cliente">
                    <i class="fas fa-plus"></i> Crear Primer Cliente
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Ruta</th>
                            <th>Estado</th>
                            <th>Total Facturas</th>
                            <th>Total Comprado</th>
                            <th>Última Compra</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                        <tr class="<?php echo !$cliente['activo'] ? 'table-secondary' : ''; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($cliente['nombre']); ?></strong>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i>
                                    Registrado: <?php echo formatearFecha($cliente['fecha_creacion']); ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $cliente['ruta_activa'] ? 'info' : 'secondary'; ?>">
                                    <?php echo htmlspecialchars($cliente['ruta_nombre']); ?>
                                </span>
                                <?php if (!$cliente['ruta_activa']): ?>
                                    <br><small class="text-danger">Ruta inactiva</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($cliente['activo']): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?php echo $cliente['total_facturas']; ?></span>
                            </td>
                            <td>
                                <strong class="text-success">
                                    <?php echo formatearPrecio($cliente['total_comprado']); ?>
                                </strong>
                            </td>
                            <td>
                                <?php if ($cliente['ultima_compra']): ?>
                                    <small><?php echo formatearFecha($cliente['ultima_compra']); ?></small>
                                <?php else: ?>
                                    <small class="text-muted">Sin compras</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-warning" 
                                            onclick="editarCliente(<?php echo htmlspecialchars(json_encode($cliente)); ?>)"
                                            title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-<?php echo $cliente['activo'] ? 'secondary' : 'success'; ?>" 
                                            onclick="cambiarEstadoCliente(<?php echo $cliente['id']; ?>, <?php echo $cliente['activo']; ?>)"
                                            title="<?php echo $cliente['activo'] ? 'Desactivar' : 'Activar'; ?>">
                                        <i class="fas fa-<?php echo $cliente['activo'] ? 'eye-slash' : 'eye'; ?>"></i>
                                    </button>
                                    <?php if ($cliente['total_facturas'] > 0): ?>
                                    <a href="reportes.php?tipo=vendedores&fecha_inicio=<?php echo date('Y-m-01'); ?>&fecha_fin=<?php echo date('Y-m-d'); ?>&cliente=<?php echo $cliente['id']; ?>" 
                                       class="btn btn-outline-info" 
                                       title="Ver historial">
                                        <i class="fas fa-chart-line"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Crear Cliente -->
<div class="modal fade" id="modal-crear-cliente" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus"></i> Crear Nuevo Cliente
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre del Cliente *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" 
                               placeholder="Nombre de la tienda o establecimiento" required>
                    </div>
                    <div class="mb-3">
                        <label for="ruta_id" class="form-label">Ruta *</label>
                        <select class="form-select" id="ruta_id" name="ruta_id" required>
                            <option value="">Seleccionar ruta...</option>
                            <?php foreach ($rutas as $ruta): ?>
                                <option value="<?php echo $ruta['id']; ?>" 
                                        <?php echo $ruta_filtro == $ruta['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ruta['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Nota:</strong> El cliente aparecerá en la lista de la ruta seleccionada para que los trabajadores puedan registrar ventas.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Crear Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Cliente -->
<div class="modal fade" id="modal-editar-cliente" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit"></i> Editar Cliente
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id" id="edit-id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-nombre" class="form-label">Nombre del Cliente *</label>
                        <input type="text" class="form-control" id="edit-nombre" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-ruta_id" class="form-label">Ruta *</label>
                        <select class="form-select" id="edit-ruta_id" name="ruta_id" required>
                            <option value="">Seleccionar ruta...</option>
                            <?php foreach ($rutas as $ruta): ?>
                                <option value="<?php echo $ruta['id']; ?>">
                                    <?php echo htmlspecialchars($ruta['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save"></i> Actualizar Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Formularios ocultos -->
<form id="form-cambiar-estado" method="POST" style="display: none;">
    <input type="hidden" name="accion" value="cambiar_estado">
    <input type="hidden" name="id" id="estado-id">
    <input type="hidden" name="activo" id="estado-activo">
</form>

<script>
function editarCliente(cliente) {
    document.getElementById('edit-id').value = cliente.id;
    document.getElementById('edit-nombre').value = cliente.nombre;
    document.getElementById('edit-ruta_id').value = cliente.ruta_id;
    
    const modal = new bootstrap.Modal(document.getElementById('modal-editar-cliente'));
    modal.show();
}

function cambiarEstadoCliente(id, estadoActual) {
    const accion = estadoActual ? 'desactivar' : 'activar';
    const mensaje = `¿Está seguro de que desea ${accion} este cliente?`;
    
    if (confirm(mensaje)) {
        document.getElementById('estado-id').value = id;
        document.getElementById('estado-activo').value = estadoActual;
        document.getElementById('form-cambiar-estado').submit();
    }
}

// Auto-submit en cambio de ruta
$('#ruta').on('change', function() {
    $(this).closest('form').submit();
});

// Búsqueda automática
$('#buscar').on('input', function() {
    const form = $(this).closest('form');
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => {
        form.submit();
    }, 1000);
});

// Validaciones en tiempo real
$('#nombre, #edit-nombre').on('input', function() {
    const valor = $(this).val().trim();
    if (valor.length < 3) {
        $(this).addClass('is-invalid');
    } else {
        $(this).removeClass('is-invalid');
    }
});

// Enfocar nombre al abrir modal crear
$('#modal-crear-cliente').on('shown.bs.modal', function() {
    $('#nombre').focus();
});
</script>

<style>
.table tbody tr.table-secondary {
    opacity: 0.7;
}

.badge {
    font-size: 0.8rem;
}

.is-invalid {
    border-color: #dc3545;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.9rem;
    }
    
    .btn-group-sm .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
}
</style>

<?php include '../includes/footer.php'; ?>