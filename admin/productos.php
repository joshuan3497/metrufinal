<?php
// =====================================================
// GESTIÓN DE PRODUCTOS - SISTEMA METRU
// =====================================================

include_once '../includes/functions.php';
verificarSesion('admin');

$titulo_pagina = 'Gestión de Productos';
$icono_pagina = 'fas fa-boxes';

// Filtros
$busqueda = $_GET['buscar'] ?? '';
$grupo_filtro = $_GET['grupo'] ?? '';
$activo_filtro = $_GET['activo'] ?? '1';

// Procesar acciones
if ($_POST) {
    $accion = $_POST['accion'] ?? '';
    
    switch ($accion) {
        case 'crear':
            $codigo = limpiarDatos($_POST['codigo'] ?? '');
            $descripcion = limpiarDatos($_POST['descripcion'] ?? '');
            $grupo_id = $_POST['grupo_id'] ?? 0;
            $unidad_medida = $_POST['unidad_medida'] ?? '';
            $precio_publico = $_POST['precio_publico'] ?? 0;
            $iva = $_POST['iva'] ?? 19;
            
            // Validaciones
            $errores = [];
            if (!$codigo) $errores[] = "Código es obligatorio";
            if (!$descripcion) $errores[] = "Descripción es obligatoria";
            if (!$grupo_id) $errores[] = "Grupo es obligatorio";
            if (!$unidad_medida) $errores[] = "Unidad de medida es obligatoria";
            if ($precio_publico <= 0) $errores[] = "Precio debe ser mayor a 0";
            
            // Verificar código único
            $codigo_existente = obtenerRegistro("SELECT id FROM productos WHERE codigo = ?", [$codigo]);
            if ($codigo_existente) $errores[] = "El código ya existe";
            
            if (empty($errores)) {
                try {
                    ejecutarConsulta(
                        "INSERT INTO productos (codigo, descripcion, grupo_id, unidad_medida, precio_publico, iva) VALUES (?, ?, ?, ?, ?, ?)",
                        [$codigo, $descripcion, $grupo_id, $unidad_medida, $precio_publico, $iva]
                    );
                    $_SESSION['mensaje'] = 'Producto creado exitosamente';
                    $_SESSION['tipo_mensaje'] = 'success';
                } catch (Exception $e) {
                    $_SESSION['mensaje'] = 'Error al crear producto: ' . $e->getMessage();
                    $_SESSION['tipo_mensaje'] = 'danger';
                }
            } else {
                $_SESSION['mensaje'] = implode('<br>', $errores);
                $_SESSION['tipo_mensaje'] = 'danger';
            }
            
            header('Location: productos.php');
            exit();
            break;
            
        case 'editar':
            $id = $_POST['id'] ?? 0;
            $codigo = limpiarDatos($_POST['codigo'] ?? '');
            $descripcion = limpiarDatos($_POST['descripcion'] ?? '');
            $grupo_id = $_POST['grupo_id'] ?? 0;
            $unidad_medida = $_POST['unidad_medida'] ?? '';
            $precio_publico = $_POST['precio_publico'] ?? 0;
            $iva = $_POST['iva'] ?? 19;
            
            // Validaciones
            $errores = [];
            if (!$codigo) $errores[] = "Código es obligatorio";
            if (!$descripcion) $errores[] = "Descripción es obligatoria";
            if (!$grupo_id) $errores[] = "Grupo es obligatorio";
            if (!$unidad_medida) $errores[] = "Unidad de medida es obligatoria";
            if ($precio_publico <= 0) $errores[] = "Precio debe ser mayor a 0";
            
            // Verificar código único (excluyendo el producto actual)
            $codigo_existente = obtenerRegistro("SELECT id FROM productos WHERE codigo = ? AND id != ?", [$codigo, $id]);
            if ($codigo_existente) $errores[] = "El código ya existe";
            
            if (empty($errores)) {
                try {
                    ejecutarConsulta(
                        "UPDATE productos SET codigo = ?, descripcion = ?, grupo_id = ?, unidad_medida = ?, precio_publico = ?, iva = ? WHERE id = ?",
                        [$codigo, $descripcion, $grupo_id, $unidad_medida, $precio_publico, $iva, $id]
                    );
                    $_SESSION['mensaje'] = 'Producto actualizado exitosamente';
                    $_SESSION['tipo_mensaje'] = 'success';
                } catch (Exception $e) {
                    $_SESSION['mensaje'] = 'Error al actualizar producto: ' . $e->getMessage();
                    $_SESSION['tipo_mensaje'] = 'danger';
                }
            } else {
                $_SESSION['mensaje'] = implode('<br>', $errores);
                $_SESSION['tipo_mensaje'] = 'danger';
            }
            
            header('Location: productos.php');
            exit();
            break;
            
        case 'cambiar_estado':
            $id = $_POST['id'] ?? 0;
            $nuevo_estado = $_POST['activo'] == '1' ? 0 : 1;
            
            ejecutarConsulta("UPDATE productos SET activo = ? WHERE id = ?", [$nuevo_estado, $id]);
            
            $mensaje = $nuevo_estado ? 'Producto activado' : 'Producto desactivado';
            $_SESSION['mensaje'] = $mensaje;
            $_SESSION['tipo_mensaje'] = 'success';
            
            header('Location: productos.php');
            exit();
            break;
    }
}

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if ($busqueda) {
    $where_conditions[] = "(p.descripcion LIKE ? OR p.codigo LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

if ($grupo_filtro) {
    $where_conditions[] = "p.grupo_id = ?";
    $params[] = $grupo_filtro;
}

if ($activo_filtro !== '') {
    $where_conditions[] = "p.activo = ?";
    $params[] = $activo_filtro;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Obtener productos
$sql_productos = "SELECT p.*, 
                  CASE 
                    WHEN p.grupo_id = 1 THEN 'Gaseosas'
                    WHEN p.grupo_id = 2 THEN 'Cervezas'
                    WHEN p.grupo_id = 3 THEN 'Jugos'
                    WHEN p.grupo_id = 4 THEN 'Aguas'
                    WHEN p.grupo_id = 5 THEN 'Bebidas Deportivas'
                    WHEN p.grupo_id = 6 THEN 'Té'
                    WHEN p.grupo_id = 7 THEN 'Bebidas Energéticas'
                    WHEN p.grupo_id = 8 THEN 'Lácteos'
                    WHEN p.grupo_id = 9 THEN 'Embutidos'
                    WHEN p.grupo_id = 10 THEN 'Accesorios'
                    WHEN p.grupo_id = 11 THEN 'Cervezas Premium'
                    ELSE 'Otros'
                  END as grupo_nombre
                  FROM productos p 
                  $where_clause 
                  ORDER BY p.activo DESC, p.descripcion ASC";

$productos = obtenerRegistros($sql_productos, $params);

include '../includes/header.php';
?>

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
                    <label for="buscar" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="buscar" name="buscar" 
                           value="<?php echo htmlspecialchars($busqueda); ?>" 
                           placeholder="Código o descripción...">
                </div>
                <div class="col-md-3">
                    <label for="grupo" class="form-label">Grupo</label>
                    <select class="form-select" id="grupo" name="grupo">
                        <option value="">Todos los grupos</option>
                        <?php foreach (GRUPOS_PRODUCTOS as $id => $nombre): ?>
                            <option value="<?php echo $id; ?>" <?php echo $grupo_filtro == $id ? 'selected' : ''; ?>>
                                <?php echo $nombre; ?>
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
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modal-crear-producto">
                            <i class="fas fa-plus"></i> Nuevo
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Lista de productos -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-list"></i> Lista de Productos
        </h6>
        <span class="badge bg-primary"><?php echo count($productos); ?> productos</span>
    </div>
    <div class="card-body">
        <?php if (empty($productos)): ?>
            <div class="text-center py-5">
                <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No se encontraron productos</h5>
                <p class="text-muted">Intente con otros filtros o cree un nuevo producto</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-crear-producto">
                    <i class="fas fa-plus"></i> Crear Primer Producto
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th>Grupo</th>
                            <th>Unidad</th>
                            <th>Precio</th>
                            <th>IVA</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $producto): ?>
                        <tr class="<?php echo !$producto['activo'] ? 'table-secondary' : ''; ?>">
                            <td>
                                <strong class="text-primary"><?php echo htmlspecialchars($producto['codigo']); ?></strong>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($producto['descripcion']); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-info">
                                    <?php echo htmlspecialchars($producto['grupo_nombre']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark">
                                    <?php echo $producto['unidad_medida']; ?>
                                </span>
                            </td>
                            <td>
                                <strong class="text-success">
                                    <?php echo formatearPrecio($producto['precio_publico']); ?>
                                </strong>
                            </td>
                            <td>
                                <?php echo $producto['iva']; ?>%
                            </td>
                            <td>
                                <?php if ($producto['activo']): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-warning" 
                                            onclick="editarProducto(<?php echo htmlspecialchars(json_encode($producto)); ?>)"
                                            title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-<?php echo $producto['activo'] ? 'secondary' : 'success'; ?>" 
                                            onclick="cambiarEstadoProducto(<?php echo $producto['id']; ?>, <?php echo $producto['activo']; ?>)"
                                            title="<?php echo $producto['activo'] ? 'Desactivar' : 'Activar'; ?>">
                                        <i class="fas fa-<?php echo $producto['activo'] ? 'eye-slash' : 'eye'; ?>"></i>
                                    </button>
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

<!-- Modal Crear Producto -->
<div class="modal fade" id="modal-crear-producto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus"></i> Crear Nuevo Producto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="codigo" class="form-label">Código *</label>
                                <input type="text" class="form-control" id="codigo" name="codigo" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="grupo_id" class="form-label">Grupo *</label>
                                <select class="form-select" id="grupo_id" name="grupo_id" required>
                                    <option value="">Seleccionar grupo...</option>
                                    <?php foreach (GRUPOS_PRODUCTOS as $id => $nombre): ?>
                                        <option value="<?php echo $id; ?>"><?php echo $nombre; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción *</label>
                                <input type="text" class="form-control" id="descripcion" name="descripcion" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="unidad_medida" class="form-label">Unidad de Medida *</label>
                                <select class="form-select" id="unidad_medida" name="unidad_medida" required>
                                    <option value="">Seleccionar...</option>
                                    <option value="CAJA">CAJA</option>
                                    <option value="SIX PAK">SIX PAK</option>
                                    <option value="UNIDAD">UNIDAD</option>
                                    <option value="PAQ">PAQ</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="iva" class="form-label">IVA (%)</label>
                                <select class="form-select" id="iva" name="iva">
                                    <option value="0">0%</option>
                                    <option value="19" selected>19%</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="precio_publico" class="form-label">Precio Público *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="precio_publico" name="precio_publico" 
                                           min="0" step="0.01" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Crear Producto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Producto -->
<div class="modal fade" id="modal-editar-producto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit"></i> Editar Producto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id" id="edit-id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit-codigo" class="form-label">Código *</label>
                                <input type="text" class="form-control" id="edit-codigo" name="codigo" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit-grupo_id" class="form-label">Grupo *</label>
                                <select class="form-select" id="edit-grupo_id" name="grupo_id" required>
                                    <option value="">Seleccionar grupo...</option>
                                    <?php foreach (GRUPOS_PRODUCTOS as $id => $nombre): ?>
                                        <option value="<?php echo $id; ?>"><?php echo $nombre; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="edit-descripcion" class="form-label">Descripción *</label>
                                <input type="text" class="form-control" id="edit-descripcion" name="descripcion" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit-unidad_medida" class="form-label">Unidad de Medida *</label>
                                <select class="form-select" id="edit-unidad_medida" name="unidad_medida" required>
                                    <option value="">Seleccionar...</option>
                                    <option value="CAJA">CAJA</option>
                                    <option value="SIX PAK">SIX PAK</option>
                                    <option value="UNIDAD">UNIDAD</option>
                                    <option value="PAQ">PAQ</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit-iva" class="form-label">IVA (%)</label>
                                <select class="form-select" id="edit-iva" name="iva">
                                    <option value="0">0%</option>
                                    <option value="19">19%</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="edit-precio_publico" class="form-label">Precio Público *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="edit-precio_publico" name="precio_publico" 
                                           min="0" step="0.01" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save"></i> Actualizar Producto
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
function editarProducto(producto) {
    document.getElementById('edit-id').value = producto.id;
    document.getElementById('edit-codigo').value = producto.codigo;
    document.getElementById('edit-descripcion').value = producto.descripcion;
    document.getElementById('edit-grupo_id').value = producto.grupo_id;
    document.getElementById('edit-unidad_medida').value = producto.unidad_medida;
    document.getElementById('edit-precio_publico').value = producto.precio_publico;
    document.getElementById('edit-iva').value = producto.iva;
    
    const modal = new bootstrap.Modal(document.getElementById('modal-editar-producto'));
    modal.show();
}

function cambiarEstadoProducto(id, estadoActual) {
    const accion = estadoActual ? 'desactivar' : 'activar';
    const mensaje = `¿Está seguro de que desea ${accion} este producto?`;
    
    if (confirm(mensaje)) {
        document.getElementById('estado-id').value = id;
        document.getElementById('estado-activo').value = estadoActual;
        document.getElementById('form-cambiar-estado').submit();
    }
}

// Auto-completar código basado en grupo y descripción
$('#grupo_id, #descripcion').on('change input', function() {
    const grupo = $('#grupo_id').val();
    const descripcion = $('#descripcion').val();
    
    if (grupo && descripcion) {
        // Generar código automático si está vacío
        const codigoInput = $('#codigo');
        if (!codigoInput.val()) {
            // Usar timestamp simple o contador
            const timestamp = Date.now().toString().slice(-4);
            codigoInput.val(grupo + timestamp);
        }
    }
});

// Validaciones en tiempo real
$('#precio_publico, #edit-precio_publico').on('input', function() {
    const valor = parseFloat($(this).val());
    if (valor <= 0) {
        $(this).addClass('is-invalid');
    } else {
        $(this).removeClass('is-invalid');
    }
});

// Búsqueda automática
$('#buscar').on('input', function() {
    const form = $(this).closest('form');
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => {
        form.submit();
    }, 1000);
});
</script>

<style>
.table tbody tr.table-secondary {
    opacity: 0.7;
}

.modal-body {
    max-height: 70vh;
    overflow-y: auto;
}

.badge {
    font-size: 0.8rem;
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