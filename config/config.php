<?php
// =====================================================
// CONFIGURACIÓN GENERAL - SISTEMA METRU
// =====================================================

// Configuración de la aplicación
define('APP_NAME', 'Sistema Metru');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/Metru/');

// Configuración de zona horaria
date_default_timezone_set('America/Bogota');

// Configuración de errores (cambiar a false en producción)
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Configuración de sesiones (solo si no hay sesión activa)
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 86400); // 24 horas
    ini_set('session.gc_maxlifetime', 86400);
}

// Configuración de archivos
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_PATH', 'uploads/');

// Configuración de paginación
define('REGISTROS_POR_PAGINA', 20);

// Estados del sistema
define('ESTADOS_SALIDA', [
    'preparando' => 'Preparando',
    'en_ruta' => 'En Ruta',
    'finalizada' => 'Finalizada'
]);

define('FORMAS_PAGO', [
    'efectivo' => 'Efectivo',
    'transferencia' => 'Transferencia',
    'pendiente' => 'Pendiente'
]);

define('TIPOS_USUARIO', [
    'admin' => 'Administrador',
    'trabajador' => 'Trabajador'
]);

// Grupos de productos
define('GRUPOS_PRODUCTOS', [
    1 => 'Gaseosas',
    2 => 'Cervezas',
    3 => 'Jugos',
    4 => 'Aguas',
    5 => 'Bebidas Deportivas',
    6 => 'Té',
    7 => 'Bebidas Energéticas',
    8 => 'Lácteos',
    9 => 'Embutidos',
    10 => 'Accesorios',
    11 => 'Cervezas Premium'
]);

// Funciones auxiliares de configuración
function obtenerUrlBase() {
    return APP_URL;
}

function obtenerFechaActual() {
    return date('Y-m-d');
}

function obtenerFechaHoraActual() {
    return date('Y-m-d H:i:s');
}

function log_error($mensaje, $archivo = '') {
    if (DEBUG_MODE) {
        $timestamp = date('Y-m-d H:i:s');
        $log = "[$timestamp] $mensaje";
        if ($archivo) {
            $log .= " en archivo: $archivo";
        }
        error_log($log . "\n", 3, "logs/error.log");
    }
}

// Función para limpiar datos de entrada
function limpiarDatos($data) {
    if (is_array($data)) {
        return array_map('limpiarDatos', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Función para validar CSRF token
function generarCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>