<?php
// =====================================================
// GUARDAR DEVOLUCIONES - SISTEMA METRU
// =====================================================

include_once 'functions.php';
verificarSesion();

header('Content-Type: application/json');

$salida_id = $_POST['salida_id'] ?? 0;
$devoluciones = json_decode($_POST['devoluciones'] ?? '[]', true);

if (!$salida_id || empty($devoluciones)) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Limpiar devoluciones anteriores
    ejecutarConsulta("DELETE FROM devoluciones WHERE salida_id = ?", [$salida_id]);
    
    // Insertar nuevas devoluciones
    foreach ($devoluciones as $devolucion) {
        if ($devolucion['cantidad'] > 0) {
            ejecutarConsulta(
                "INSERT INTO devoluciones (salida_id, producto_id, cantidad_devuelta) VALUES (?, ?, ?)",
                [$salida_id, $devolucion['producto_id'], $devolucion['cantidad']]
            );
        }
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $pdo->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>