<?php
// =====================================================
// ASIGNAR TRABAJADORES A SALIDA - SISTEMA METRU
// =====================================================

include_once '../includes/functions.php';
verificarSesion('admin');

$titulo_pagina = 'Asignar Trabajadores';
$icono_pagina = 'fas fa-users';

$salida_id = $_GET['salida_id'] ?? 0;

if (!$salida_id) {
    $_SESSION['mensaje'] = 'ID de salida no válido';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: salidas.php');
    exit();
}

// Obtener información de la salida
$sql_salida = "SELECT s.*, r.nombre as ruta_nombre 
               FROM salidas_mercancia s 
               JOIN rutas r ON s.ruta_id = r.id 
               WHERE s.id = ?";
$salida = obtenerRegistro($sql_salida, [$salida_id]);

if (!$salida) {
    $_SESSION['mensaje'] = 'Salida no encontrada';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: salidas.php');
    exit();
}

// Procesar asignación
if ($_POST && isset($_POST['accion']) && $_POST['accion'] == 'asignar_trabajadores') {
    $trabajadores = $_POST['trabajadores'] ?? [];
    $principal = $_POST['principal'] ?? 0;
    
    if (empty($trabajadores)) {
        $_SESSION['mensaje'] = 'Debe seleccionar al menos un trabajador';
        $_SESSION['tipo_mensaje'] = 'warning';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Limpiar asignaciones anteriores
            ejecutarConsulta("DELETE FROM salida_trabajadores WHERE salida_id = ?", [$salida_id]);
            
            // Asignar nuevos trabajadores
            foreach ($trabajadores as $trabajador_id) {
                $es_principal = ($trabajador_id == $principal) ? 1 : 0;
                
                ejecutarConsulta(
                    "INSERT INTO salida_trabajadores (salida_id, trabajador_id, es_principal) VALUES (?, ?, ?)",
                    [$salida_id, $trabajador_id, $es_principal]
                );
            }
            
            // Actualizar estado de la salida
            ejecutarConsulta(
                "UPDATE salidas_mercancia SET estado = 'en_ruta' WHERE id = ?",
                [$salida_id]
            );
            
            $pdo->commit();
            
            $_SESSION['mensaje'] = 'Trabajadores asignados correctamente';
            $_SESSION['tipo_mensaje'] = 'success';
            header('Location: salidas.php');
            exit();
            
        } catch (Exception $e) {
            $pdo->rollback();
            $_SESSION['mensaje'] = 'Error al asignar trabajadores: ' . $e->getMessage();
            $_SESSION['tipo_mensaje'] = 'danger';
        }
    }
}

// Obtener trabajadores disponibles
$trabajadores = obtenerRegistros(
    "SELECT * FROM usuarios WHERE tipo = 'trabajador' AND activo = 1 ORDER BY nombre"
);

// Obtener trabajadores ya asignados
$asignados = obtenerRegistros(
    "SELECT * FROM salida_trabajadores WHERE salida_id = ?",
    [$salida_id]
);

$trabajadores_asignados = array_column($asignados, 'trabajador_id');
$principal_id = 0;
foreach ($asignados as $asignado) {
    if ($asignado['es_principal']) {
        $principal_id = $asignado['trabajador_id'];
        break;
    }
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-users"></i> Asignar Trabajadores a la Ruta
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Información de la salida -->
                    <div class="alert alert-info">
                        <strong>Ruta:</strong> <?php echo $salida['ruta_nombre']; ?><br>
                        <strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($salida['fecha_salida'])); ?><br>
                        <strong>Estado:</strong> 
                        <span class="badge bg-<?php 
                            echo $salida['estado'] == 'preparando' ? 'warning' : 
                                ($salida['estado'] == 'en_ruta' ? 'info' : 'success'); 
                        ?>">
                            <?php echo ucfirst($salida['estado']); ?>
                        </span>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="accion" value="asignar_trabajadores">
                        
                        <!-- Selección de trabajadores -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-check-square"></i> Seleccione los trabajadores:
                            </label>
                            
                            <div class="row">
                                <?php foreach ($trabajadores as $trabajador): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               name="trabajadores[]" 
                                               value="<?php echo $trabajador['id']; ?>"
                                               id="trabajador_<?php echo $trabajador['id']; ?>"
                                               <?php echo in_array($trabajador['id'], $trabajadores_asignados) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="trabajador_<?php echo $trabajador['id']; ?>">
                                            <?php echo htmlspecialchars($trabajador['nombre']); ?>
                                            <small class="text-muted">(<?php echo $trabajador['codigo_usuario']; ?>)</small>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Selección del principal -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-star"></i> Trabajador principal (responsable):
                            </label>
                            
                            <select class="form-select" name="principal" required>
                                <option value="">Seleccione el responsable...</option>
                                <?php foreach ($trabajadores as $trabajador): ?>
                                <option value="<?php echo $trabajador['id']; ?>"
                                        <?php echo $trabajador['id'] == $principal_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($trabajador['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">
                                El trabajador principal será el responsable de la ruta
                            </small>
                        </div>
                        
                        <!-- Botones -->
                        <div class="d-flex justify-content-between">
                            <a href="salidas.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Volver
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Asignar Trabajadores
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Información adicional -->
            <div class="card mt-3">
                <div class="card-body">
                    <h6><i class="fas fa-info-circle"></i> Información importante:</h6>
                    <ul>
                        <li>Todos los trabajadores asignados podrán crear facturas en esta ruta</li>
                        <li>El trabajador principal será el responsable de la ruta</li>
                        <li>Las facturas mostrarán qué trabajador las creó</li>
                        <li>En el cierre se podrá ver el rendimiento individual de cada trabajador</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validar que al menos un trabajador esté seleccionado
$('form').on('submit', function(e) {
    const trabajadoresSeleccionados = $('input[name="trabajadores[]"]:checked').length;
    const principal = $('select[name="principal"]').val();
    
    if (trabajadoresSeleccionados === 0) {
        e.preventDefault();
        alert('Debe seleccionar al menos un trabajador');
        return false;
    }
    
    // Verificar que el principal esté entre los seleccionados
    if (principal && !$('#trabajador_' + principal).is(':checked')) {
        e.preventDefault();
        alert('El trabajador principal debe estar entre los seleccionados');
        return false;
    }
});

// Auto-seleccionar cuando se elige como principal
$('select[name="principal"]').on('change', function() {
    const principalId = $(this).val();
    if (principalId) {
        $('#trabajador_' + principalId).prop('checked', true);
    }
});
</script>

<?php include '../includes/footer.php'; ?>