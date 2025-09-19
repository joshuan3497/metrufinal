<!-- Sección de Gastos de Ruta -->
<div class="card mb-4">
    <div class="card-header bg-warning text-dark">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-money-bill-wave"></i> Gastos de Ruta
        </h6>
    </div>
    <div class="card-body">
        <?php
        // Obtener gastos de la ruta
        $sql_gastos = "SELECT * FROM gastos_ruta WHERE salida_id = ? ORDER BY fecha_gasto DESC";
        $gastos = obtenerRegistros($sql_gastos, [$salida_id]);
        $total_gastos = array_sum(array_column($gastos, 'monto'));
        ?>
        
        <?php if (empty($gastos)): ?>
            <p class="text-center text-muted">No hay gastos registrados para esta ruta</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Concepto</th>
                            <th>Monto</th>
                            <th>Fecha</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gastos as $gasto): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($gasto['concepto']); ?></td>
                            <td><?php echo formatearPrecio($gasto['monto']); ?></td>
                            <td><?php echo formatearFecha($gasto['fecha_gasto']); ?></td>
                            <td><small><?php echo htmlspecialchars($gasto['observaciones'] ?? '-'); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total Gastos:</th>
                            <th><?php echo formatearPrecio($total_gastos); ?></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
        
        <!-- Botón para agregar gasto -->
<!-- Sección de Gastos de Ruta CORREGIDA -->
<div class="card mb-4">
    <div class="card-header bg-warning text-dark">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-money-bill-wave"></i> Gastos de Ruta
        </h6>
    </div>
    <div class="card-body">
        <?php
        // Obtener gastos de la ruta
        $sql_gastos = "SELECT * FROM gastos_ruta WHERE salida_id = ? ORDER BY fecha_gasto DESC";
        $gastos = obtenerRegistros($sql_gastos, [$salida_id]);
        $total_gastos = array_sum(array_column($gastos, 'monto'));
        ?>
        
        <?php if (empty($gastos)): ?>
            <p class="text-center text-muted">No hay gastos registrados para esta ruta</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Concepto</th>
                            <th>Monto</th>
                            <th>Fecha</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gastos as $gasto): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($gasto['concepto']); ?></td>
                            <td><?php echo formatearPrecio($gasto['monto']); ?></td>
                            <td><?php echo formatearFecha($gasto['fecha_gasto']); ?></td>
                            <td><small><?php echo htmlspecialchars($gasto['observaciones'] ?? '-'); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total Gastos:</th>
                            <th><?php echo formatearPrecio($total_gastos); ?></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
        
        <!-- Botón para agregar gasto -->
        <div class="text-end mt-3">
            <button type="button" class="btn btn-warning btn-sm" id="btnAgregarGasto">
                <i class="fas fa-plus"></i> Agregar Gasto
            </button>
        </div>
    </div>
</div>

<!-- Modal para agregar gasto CORREGIDO -->
<div class="modal fade" id="modalAgregarGasto" tabindex="-1" aria-labelledby="modalAgregarGastoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAgregarGastoLabel">Agregar Gasto de Ruta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="agregar_gasto">
                <input type="hidden" name="salida_id" value="<?php echo $salida_id; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="concepto" class="form-label">Concepto *</label>
                        <input type="text" class="form-control" id="concepto" name="concepto" required>
                    </div>
                    <div class="mb-3">
                        <label for="monto" class="form-label">Monto *</label>
                        <input type="number" class="form-control" id="monto" name="monto" min="0" step="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="fecha_gasto" class="form-label">Fecha *</label>
                        <input type="date" class="form-control" id="fecha_gasto" name="fecha_gasto" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Guardar Gasto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Script CORREGIDO para el modal
$(document).ready(function() {
    // Variable global para el modal
    let modalGastos = null;
    
    // Evento para mostrar el modal
    $('#btnAgregarGasto').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Crear nueva instancia solo si no existe
        if (!modalGastos) {
            modalGastos = new bootstrap.Modal(document.getElementById('modalAgregarGasto'), {
                backdrop: 'static',
                keyboard: true
            });
        }
        
        modalGastos.show();
    });
    
    // Limpiar formulario cuando se cierre
    $('#modalAgregarGasto').on('hidden.bs.modal', function () {
        $(this).find('form')[0].reset();
    });
});
</script>