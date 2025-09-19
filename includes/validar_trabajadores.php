<?php
// =====================================================
// VALIDAR TRABAJADORES ASIGNADOS - SISTEMA METRU
// =====================================================

include_once 'functions.php';
verificarSesion('admin');

header('Content-Type: application/json');

$salida_id = $_POST['salida_id'] ?? 0;

if (!$salida_id) {
    echo json_encode(['error' => true, 'mensaje' => 'ID de salida no válido']);
    exit();
}

// Verificar si hay trabajadores asignados
$sql = "SELECT COUNT(*) as total FROM salida_trabajadores WHERE salida_id = ?";
$resultado = obtenerRegistro($sql, [$salida_id]);

$tiene_trabajadores = ($resultado['total'] > 0);

// Si no hay trabajadores en la nueva tabla, verificar el método antiguo
if (!$tiene_trabajadores) {
    $sql = "SELECT usuario_id FROM salidas_mercancia WHERE id = ?";
    $salida = obtenerRegistro($sql, [$salida_id]);
    $tiene_trabajadores = ($salida && $salida['usuario_id'] > 0);
}

echo json_encode([
    'tiene_trabajadores' => $tiene_trabajadores,
    'total_trabajadores' => $resultado['total']
]);
?>