<?php
// =====================================================
// FUNCIONES PRINCIPALES - SISTEMA METRU
// =====================================================

// Detectar la ruta correcta al archivo de base de datos


// Incluir configuración si no está incluida
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config/config.php';
}

// Incluir base de datos una sola vez
require_once __DIR__ . '/../config/database.php';

// Hacer $pdo global para que esté disponible en todas las funciones
global $pdo;

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// =====================================================
// FUNCIONES DE AUTENTICACIÓN
// =====================================================

function autenticarUsuario($codigo_usuario, $password) {
    $sql = "SELECT * FROM usuarios WHERE codigo_usuario = ? AND password = MD5(?) AND activo = 1";
    $usuario = obtenerRegistro($sql, [$codigo_usuario, $password]);
    
    if ($usuario) {
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_codigo'] = $usuario['codigo_usuario'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['usuario_tipo'] = $usuario['tipo'];
        return true;
    }
    return false;
}

function verificarSesion($tipo_requerido = null) {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: /Metru/index.php');
        exit();
    }
    
    if ($tipo_requerido && $_SESSION['usuario_tipo'] != $tipo_requerido) {
        header('Location: /Metru/index.php');
        exit();
    }
}

function cerrarSesion() {
    session_destroy();
    header('Location: /Metru/index.php');
    exit();
}

// =====================================================
// FUNCIONES DE PRODUCTOS
// =====================================================

function obtenerProductosSalida($salida_id) {
    $sql = "SELECT 
        p.*,
        ds.cantidad as cantidad_salida,
        ds.cantidad - COALESCE(
            (SELECT SUM(df.cantidad) 
             FROM detalle_facturas df 
             JOIN facturas f ON df.factura_id = f.id 
             WHERE f.salida_id = ? AND df.producto_id = p.id), 0
        ) as cantidad_disponible
    FROM productos p
    JOIN detalle_salidas ds ON p.id = ds.producto_id
    WHERE ds.salida_id = ? AND p.activo = 1
    ORDER BY p.descripcion";
    
    return obtenerRegistros($sql, [$salida_id, $salida_id]);
}

function buscarProductos($termino) {
    $sql = "SELECT * FROM productos 
            WHERE activo = 1 
            AND (descripcion LIKE ? OR codigo LIKE ?)
            ORDER BY descripcion 
            LIMIT 15";
    $termino_busqueda = '%' . $termino . '%';
    return obtenerRegistros($sql, [$termino_busqueda, $termino_busqueda]);
}
// =====================================================
// FUNCIONES DE RUTAS
// =====================================================

function obtenerTodasLasRutas() {
    return obtenerRegistros("SELECT * FROM rutas WHERE activa = 1 ORDER BY nombre");
}

function obtenerRutaPorId($ruta_id) {
    return obtenerRegistro("SELECT * FROM rutas WHERE id = ?", [$ruta_id]);
}


// =====================================================
// FUNCIONES DE CLIENTES
// =====================================================

function obtenerClientesPorRuta($ruta_id) {
    $sql = "SELECT * FROM clientes WHERE ruta_id = ? AND activo = 1 ORDER BY nombre";
    return obtenerRegistros($sql, [$ruta_id]);
}

function obtenerClientePorId($id) {
    $sql = "SELECT c.*, r.nombre as ruta_nombre 
            FROM clientes c 
            JOIN rutas r ON c.ruta_id = r.id 
            WHERE c.id = ?";
    return obtenerRegistro($sql, [$id]);
}

// =====================================================
// FUNCIONES DE SALIDAS DE MERCANCÍA
// =====================================================

function crearSalida($ruta_id, $usuario_id, $fecha_salida, $observaciones = '') {
    $sql = "INSERT INTO salidas_mercancia (ruta_id, usuario_id, fecha_salida, observaciones, estado) 
            VALUES (?, ?, ?, ?, 'preparando')";
    return insertarYObtenerID($sql, [$ruta_id, $usuario_id, $fecha_salida, $observaciones]);
}

function agregarProductoASalida($salida_id, $producto_id, $cantidad) {
    $sql = "INSERT INTO detalle_salidas (salida_id, producto_id, cantidad) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE cantidad = cantidad + VALUES(cantidad)";
    ejecutarConsulta($sql, [$salida_id, $producto_id, $cantidad]);
}

function actualizarEstadoSalida($salida_id, $nuevo_estado) {
    $sql = "UPDATE salidas_mercancia SET estado = ? WHERE id = ?";
    ejecutarConsulta($sql, [$nuevo_estado, $salida_id]);
}
// =====================================================
// FUNCIONES DE FACTURAS
// =====================================================

function crearFactura($salida_id, $cliente_id, $vendedor_id, $forma_pago, $total, $observaciones = '', $cliente_nombre = null, $cliente_ciudad = null) {
    // Generar número de factura automático
    $fecha = date('Y-m-d');
    
    // Si hay cliente_id, obtener su ruta
    if ($cliente_id) {
        $cliente = obtenerClientePorId($cliente_id);
        $ruta_id = $cliente['ruta_id'];
    } else {
        // Si no hay cliente_id, obtener la ruta de la salida
        $sql_ruta = "SELECT ruta_id FROM salidas_mercancia WHERE id = ?";
        $ruta_id = obtenerRegistro($sql_ruta, [$salida_id])['ruta_id'];
    }
    
    $numero_factura = generarNumeroFactura($ruta_id, $fecha);
    
    $sql = "INSERT INTO facturas (numero_factura, salida_id, cliente_id, vendedor_id, forma_pago, total, observaciones, cliente_nombre, cliente_ciudad) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    return insertarYObtenerID($sql, [$numero_factura, $salida_id, $cliente_id, $vendedor_id, $forma_pago, $total, $observaciones, $cliente_nombre, $cliente_ciudad]);
}

function agregarProductoAFactura($factura_id, $producto_id, $cantidad, $precio_unitario) {
    $subtotal = $cantidad * $precio_unitario;
    $sql = "INSERT INTO detalle_facturas (factura_id, producto_id, cantidad, precio_unitario, subtotal) 
            VALUES (?, ?, ?, ?, ?)";
    ejecutarConsulta($sql, [$factura_id, $producto_id, $cantidad, $precio_unitario, $subtotal]);
}

function obtenerFacturasPorSalida($salida_id) {
    $sql = "SELECT f.*, 
            COALESCE(c.nombre, f.cliente_nombre, 'Cliente General') as cliente_nombre, 
            u.nombre as vendedor_nombre 
            FROM facturas f 
            LEFT JOIN clientes c ON f.cliente_id = c.id 
            JOIN usuarios u ON f.vendedor_id = u.id 
            WHERE f.salida_id = ? 
            ORDER BY f.fecha_venta DESC";
    return obtenerRegistros($sql, [$salida_id]);
}

function obtenerFacturasPorVendedor($vendedor_id, $fecha = null) {
    if (!$fecha) $fecha = date('Y-m-d');
    
    $sql = "SELECT f.*, 
            COALESCE(c.nombre, f.cliente_nombre, 'Cliente General') as cliente_nombre 
            FROM facturas f 
            LEFT JOIN clientes c ON f.cliente_id = c.id 
            WHERE f.vendedor_id = ? AND DATE(f.fecha_venta) = ? 
            ORDER BY f.fecha_venta DESC";
    return obtenerRegistros($sql, [$vendedor_id, $fecha]);
}

function obtenerDetalleFactura($factura_id) {
    $sql = "SELECT df.*, p.descripcion, p.unidad_medida 
            FROM detalle_facturas df 
            JOIN productos p ON df.producto_id = p.id 
            WHERE df.factura_id = ? 
            ORDER BY p.descripcion";
    return obtenerRegistros($sql, [$factura_id]);
}

// =====================================================
// FUNCIONES DE REPORTES
// =====================================================

function obtenerVentasPorPeriodo($fecha_inicio, $fecha_fin, $ruta_id = null) {
    $sql = "SELECT 
        DATE(f.fecha_venta) as fecha,
        COUNT(DISTINCT f.id) as num_facturas,
        SUM(f.total) as total_ventas,
        SUM(CASE WHEN f.forma_pago = 'efectivo' THEN f.total ELSE 0 END) as total_efectivo,
        SUM(CASE WHEN f.forma_pago = 'transferencia' THEN f.total ELSE 0 END) as total_transferencia,
        SUM(CASE WHEN f.forma_pago = 'pendiente' THEN f.total ELSE 0 END) as total_pendiente
    FROM facturas f
    JOIN salidas_mercancia s ON f.salida_id = s.id
    WHERE DATE(f.fecha_venta) BETWEEN ? AND ?";
    
    $params = [$fecha_inicio, $fecha_fin];
    
    if ($ruta_id) {
        $sql .= " AND s.ruta_id = ?";
        $params[] = $ruta_id;
    }
    
    $sql .= " GROUP BY DATE(f.fecha_venta) ORDER BY fecha DESC";
    
    return obtenerRegistros($sql, $params);
}

// =====================================================
// FUNCIONES AUXILIARES
// =====================================================

function generarNumeroFactura($ruta_id, $fecha) {
    // Para facturas sin cliente_id, contar todas las facturas de la ruta
    $sql = "SELECT COUNT(*) + 1 as siguiente 
            FROM facturas f 
            JOIN salidas_mercancia s ON f.salida_id = s.id
            WHERE s.ruta_id = ? AND DATE(f.fecha_venta) = ?";
    
    $resultado = obtenerRegistro($sql, [$ruta_id, $fecha]);
    
    return 'R' . $ruta_id . '-' . date('Ymd', strtotime($fecha)) . '-' . str_pad($resultado['siguiente'], 3, '0', STR_PAD_LEFT);
}

function formatearPrecio($precio) {
    // Validar que el precio no sea null o vacío
    if ($precio === null || $precio === '') {
        $precio = 0;
    }
    return '$' . number_format((float)$precio, 0, ',', '.');
}

function formatearFecha($fecha) {
    return date('d/m/Y', strtotime($fecha));
}

function formatearFechaHora($fecha) {
    return date('d/m/Y H:i', strtotime($fecha));
}

// =====================================================
// FUNCIONES DE VALIDACIÓN
// =====================================================

function validarRutaActiva($salida_id) {
    $sql = "SELECT estado FROM salidas_mercancia WHERE id = ?";
    $salida = obtenerRegistro($sql, [$salida_id]);
    return $salida && $salida['estado'] == 'en_ruta';
}

function validarAccesoRuta($usuario_id, $salida_id) {
    // Verificar en tabla de múltiples trabajadores primero
    $sql = "SELECT COUNT(*) as tiene_acceso 
            FROM salida_trabajadores 
            WHERE salida_id = ? AND trabajador_id = ?";
    $resultado = obtenerRegistro($sql, [$salida_id, $usuario_id]);
    
    if ($resultado && $resultado['tiene_acceso'] > 0) {
        return true;
    }
    
    // Si no está en salida_trabajadores, verificar método antiguo
    $sql = "SELECT COUNT(*) as tiene_acceso 
            FROM salidas_mercancia 
            WHERE id = ? AND usuario_id = ?";
    $resultado = obtenerRegistro($sql, [$salida_id, $usuario_id]);
    
    return $resultado && $resultado['tiene_acceso'] > 0;
}

// =====================================================
// FUNCIONES DE MÚLTIPLES TRABAJADORES
// =====================================================

function asignarTrabajadorASalida($salida_id, $trabajador_id, $es_principal = false) {
    $sql = "INSERT INTO salida_trabajadores (salida_id, trabajador_id, es_principal) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE es_principal = ?";
    ejecutarConsulta($sql, [$salida_id, $trabajador_id, $es_principal ? 1 : 0, $es_principal ? 1 : 0]);
}

function obtenerTrabajadoresDeSalida($salida_id) {
    $sql = "SELECT u.*, st.es_principal 
            FROM salida_trabajadores st
            JOIN usuarios u ON st.trabajador_id = u.id
            WHERE st.salida_id = ?
            ORDER BY st.es_principal DESC, u.nombre";
    return obtenerRegistros($sql, [$salida_id]);
}

function obtenerUsuarioPorId($usuario_id) {
    $sql = "SELECT * FROM usuarios WHERE id = ?";
    return obtenerRegistro($sql, [$usuario_id]);
}
