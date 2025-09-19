<?php
// =====================================================
// GESTIÓN DE RUTAS - SISTEMA METRU
// =====================================================

include_once '../includes/functions.php';
verificarSesion('admin');

$titulo_pagina = 'Gestión de Rutas';
$icono_pagina = 'fas fa-route';

// Procesar acciones
if ($_POST) {
    $accion = $_POST['accion'] ?? '';
    
    switch ($accion) {
        case 'crear':
            $nombre = limpiarDatos($_POST['nombre'] ?? '');
            $descripcion = limpiarDatos($_POST['descripcion'] ?? '');
            
            // Validaciones
            $errores = [];
            if (!$nombre) $errores[] = "Nombre de ruta es obligatorio";
            
            // Verificar nombre único
            $nombre_existente = obtenerRegistro("SELECT id FROM rutas WHERE nombre = ?", [$nombre]);
            if ($nombre_existente) $errores[] = "El nombre de ruta ya existe";
            
            if (empty($errores)) {
                try {
                    ejecutarConsulta(
                        "INSERT INTO rutas (nombre, descripcion) VALUES (?, ?)",
                        [$nombre, $descripcion]
                    );
                    $_SESSION['mensaje'] = 'Ruta creada exitosamente';
                    $_SESSION['tipo_mensaje'] = 'success';
                } catch (Exception $e) {
                    $_SESSION['mensaje'] = 'Error al crear ruta: ' . $e->getMessage();
                    $_SESSION['tipo_mensaje'] = 'danger';
                }
            } else {
                $_SESSION['mensaje'] = implode('<br>', $errores);
                $_SESSION['tipo_mensaje'] = 'danger';
            }
            
            header('Location: rutas.php');
            exit();
            break;
            
        case 'editar':
            $id = $_POST['id'] ?? 0;
            $nombre = limpiarDatos($_POST['nombre'] ?? '');
            $descripcion = limpiarDatos($_POST['descripcion'] ?? '');
            
            // Validaciones
            $errores = [];
            if (!$nombre) $errores[] = "Nombre de ruta es obligatorio";
            
            // Verificar nombre único (excluyendo la ruta actual)
            $nombre_existente = obtenerRegistro("SELECT id FROM rutas WHERE nombre = ? AND id != ?", [$nombre, $id]);
            if ($nombre_existente) $errores[] = "El nombre de ruta ya existe";
            
            if (empty($errores)) {
                try {
                    ejecutarConsulta(
                        "UPDATE rutas SET nombre = ?, descripcion = ? WHERE id = ?",
                        [$nombre, $descripcion, $id]
                    );
                    $_SESSION['mensaje'] = 'Ruta actualizada exitosamente';
                    $_SESSION['tipo_mensaje'] = 'success';
                } catch (Exception $e) {
                    $_SESSION['mensaje'] = 'Error al actualizar ruta: ' . $e->getMessage();
                    $_SESSION['tipo_mensaje'] = 'danger';
                }
            } else {
                $_SESSION['mensaje'] = implode('<br>', $errores);
                $_SESSION['tipo_mensaje'] = 'danger';
            }
            
            header('Location: rutas.php');
            exit();
            break;
            
        case 'cambiar_estado':
            $id = $_POST['id'] ?? 0;
            $nuevo_estado = $_POST['activa'] == '1' ? 0 : 1;
            
            ejecutarConsulta("UPDATE rutas SET activa = ? WHERE id = ?", [$nuevo_estado, $id]);
            
            $mensaje = $nuevo_estado ? 'Ruta activada' : 'Ruta desactivada';
            $_SESSION['mensaje'] = $mensaje;
            $_SESSION['tipo_mensaje'] = 'success';
            
            header('Location: rutas.php');
            exit();
            break;
    }
}

// Obtener rutas con estadísticas
$sql_rutas = "SELECT 
    r.*,
    COUNT(DISTINCT c.id) as total_clientes,
    COUNT(DISTINCT s.id) as total_salidas,
    COUNT(DISTINCT CASE WHEN s.estado = 'en_ruta' THEN s.id END) as salidas_activas,
    COALESCE(SUM(f.total), 0) as total_vendido_historico
FROM rutas r
LEFT JOIN clientes c ON r.id = c.ruta_id AND c.activo = 1
LEFT JOIN salidas_mercancia s ON r.id = s.ruta_id
LEFT JOIN facturas f ON s.id = f.salida_id
GROUP BY r.id
ORDER BY r.activa DESC, r.nombre ASC";

$rutas = obtenerRegistros($sql_rutas);

// Estadísticas generales
$total_rutas = count($rutas);
$rutas_activas = count(array_filter($rutas, fn($r) => $r['activa']));
$total_clientes = array_sum(array_column($rutas, 'total_clientes'));
$total_salidas = array_sum(array_column($rutas, 'total_salidas'));

include '../includes/header.php';
?>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-primary text-center">
            <div class="card-body">
                <i class="fas fa-route fa-2x text-primary mb-2"></i>
                <h4 class="text-primary"><?php echo $total_rutas; ?></h4>
                <small class="text-muted">Total Rutas</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-success text-center">
            <div class="card-body">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h4 class="text-success"><?php echo $rutas_activas; ?></h4>
                <small class="text-muted">Rutas Activas</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info text-center">
            <div class="card-body">
                <i class="fas fa-store fa-2x text-info mb-2"></i>
                <h4 class="text-info"><?php echo $total_clientes; ?></h4>
                <small class="text-muted">Total Clientes</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning text-center">
            <div class="card-body">
                <i class="fas fa-truck fa-2x text-warning mb-2"></i>
                <h4 class="text-warning"><?php echo $total_salidas; ?></h4>
                <small class="text-muted">Total Salidas</small>
            </div>
        </div>
    </div>
</div>

<!-- Acciones principales -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h4>
                <i class="fas fa-list"></i> Lista de Rutas
                <span class="badge bg-primary"><?php echo count($rutas); ?></span>
            </h4>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modal-crear-ruta">
                <i class="fas fa-plus"></i> Nueva Ruta
            </button>
        </div>
    </div>
</div>

<!-- Lista de rutas -->
<div class="row">
    <?php if (empty($rutas)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-route fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No hay rutas registradas</h5>
                    <p class="text-muted">Cree la primera ruta para comenzar</p>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modal-crear-ruta">
                        <i class="fas fa-plus"></i> Crear Primera Ruta
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($rutas as $ruta): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 <?php echo !$ruta['activa'] ? 'border-secondary' : 'border-primary'; ?>">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($ruta['nombre']); ?>
                    </h6>
                    <?php if ($ruta['activa']): ?>
                        <span class="badge bg-success">Activa</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactiva</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($ruta['descripcion']): ?>
                        <p class="text-muted mb-3">
                            <?php echo nl2br(htmlspecialchars($ruta['descripcion'])); ?>
                        </p>
                    <?php endif; ?>
                    
                    <!-- Estadísticas de la ruta -->
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <div class="border-end">
                                <h6 class="text-info mb-0"><?php echo $ruta['total_clientes']; ?></h6>
                                <small class="text-muted">Clientes</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border-end">
                                <h6 class="text-primary mb-0"><?php echo $ruta['total_salidas']; ?></h6>
                                <small class="text-muted">Salidas</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <h6 class="text-warning mb-0"><?php echo $ruta['salidas_activas']; ?></h6>
                            <small class="text-muted">Activas</small>
                        </div>
                    </div>
                    
                    <!-- Total vendido histórico -->
                    <div class="text-center mb-3">
                        <div class="bg-light rounded p-2">
                            <small class="text-muted">Total Histórico:</small><br>
                            <strong class="text-success">
                                <?php echo formatearPrecio($ruta['total_vendido_historico']); ?>
                            </strong>
                        </div>
                    </div>
                    
                    <!-- Fecha de creación -->
                    <small class="text-muted">
                        <i class="fas fa-calendar"></i>
                        Creada: <?php echo formatearFecha($ruta['fecha_creacion']); ?>
                    </small>
                </div>
                <div class="card-footer">
                    <div class="btn-group w-100">
                        <a href="clientes.php?ruta=<?php echo $ruta['id']; ?>" 
                           class="btn btn-outline-info btn-sm" 
                           title="Ver clientes">
                            <i class="fas fa-store"></i> Clientes
                        </a>
                        <button class="btn btn-outline-warning btn-sm" 
                                onclick="editarRuta(<?php echo htmlspecialchars(json_encode($ruta)); ?>)"
                                title="Editar ruta">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-<?php echo $ruta['activa'] ? 'secondary' : 'success'; ?> btn-sm" 
                                onclick="cambiarEstadoRuta(<?php echo $ruta['id']; ?>, <?php echo $ruta['activa']; ?>)"
                                title="<?php echo $ruta['activa'] ? 'Desactivar' : 'Activar'; ?>">
                            <i class="fas fa-<?php echo $ruta['activa'] ? 'eye-slash' : 'eye'; ?>"></i>
                        </button>
                        <a href="salidas.php?ruta=<?php echo $ruta['id']; ?>" 
                           class="btn btn-outline-primary btn-sm" 
                           title="Ver salidas">
                            <i class="fas fa-truck"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal Crear Ruta -->
<div class="modal fade" id="modal-crear-ruta" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus"></i> Crear Nueva Ruta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre de la Ruta *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" 
                               placeholder="Ej: Ruta Centro, Ruta Norte..." required>
                    </div>
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" 
                                  rows="3" placeholder="Descripción opcional de la ruta..."></textarea>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Nota:</strong> Después de crear la ruta, podrá agregar clientes específicos para esta zona.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Crear Ruta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Ruta -->
<div class="modal fade" id="modal-editar-ruta" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit"></i> Editar Ruta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id" id="edit-id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-nombre" class="form-label">Nombre de la Ruta *</label>
                        <input type="text" class="form-control" id="edit-nombre" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="edit-descripcion" name="descripcion" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save"></i> Actualizar Ruta
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
    <input type="hidden" name="activa" id="estado-activa">
</form>

<script>
function editarRuta(ruta) {
    document.getElementById('edit-id').value = ruta.id;
    document.getElementById('edit-nombre').value = ruta.nombre;
    document.getElementById('edit-descripcion').value = ruta.descripcion || '';
    
    const modal = new bootstrap.Modal(document.getElementById('modal-editar-ruta'));
    modal.show();
}

function cambiarEstadoRuta(id, estadoActual) {
    const accion = estadoActual ? 'desactivar' : 'activar';
    const mensaje = `¿Está seguro de que desea ${accion} esta ruta?`;
    
    if (estadoActual) {
        // Verificar si tiene salidas activas antes de desactivar
        if (confirm(mensaje + '\n\nNota: Asegúrese de que no tenga salidas activas.')) {
            document.getElementById('estado-id').value = id;
            document.getElementById('estado-activa').value = estadoActual;
            document.getElementById('form-cambiar-estado').submit();
        }
    } else {
        if (confirm(mensaje)) {
            document.getElementById('estado-id').value = id;
            document.getElementById('estado-activa').value = estadoActual;
            document.getElementById('form-cambiar-estado').submit();
        }
    }
}

// Generar nombres de ruta automáticamente
let contadorRutas = <?php echo $total_rutas + 1; ?>;

$('#modal-crear-ruta').on('shown.bs.modal', function() {
    const nombreInput = $('#nombre');
    if (!nombreInput.val()) {
        nombreInput.val('Ruta ' + contadorRutas);
        contadorRutas++;
    }
    nombreInput.focus();
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
</script>

<style>
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 15px rgba(0,0,0,0.15);
}

.card-header {
    background: linear-gradient(45deg, #007bff, #0056b3);
    color: white;
}

.border-end {
    border-right: 1px solid #dee2e6 !important;
}

.btn-group .btn {
    flex: 1;
}

@media (max-width: 768px) {
    .col-md-6 {
        margin-bottom: 1rem;
    }
    
    .btn-group .btn {
        padding: 0.375rem 0.25rem;
        font-size: 0.8rem;
    }
}

.is-invalid {
    border-color: #dc3545;
}
</style>

<?php include '../includes/footer.php'; ?>