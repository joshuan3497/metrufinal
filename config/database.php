<?php
// =====================================================
// CONFIGURACIÓN DE BASE DE DATOS - SISTEMA METRU
// =====================================================

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'metru_sistema';
$username = 'root';
$password = '';

try {
    // Crear conexión PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Configurar PDO para mostrar errores
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Configurar fetch mode por defecto
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

// Función para ejecutar consultas seguras
function ejecutarConsulta($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch(PDOException $e) {
        die("Error en consulta: " . $e->getMessage());
    }
}

// Función para obtener un solo registro
function obtenerRegistro($sql, $params = []) {
    $stmt = ejecutarConsulta($sql, $params);
    return $stmt->fetch();
}

// Función para obtener múltiples registros
function obtenerRegistros($sql, $params = []) {
    $stmt = ejecutarConsulta($sql, $params);
    return $stmt->fetchAll();
}

// Función para insertar y obtener ID
function insertarYObtenerID($sql, $params = []) {
    global $pdo;
    $stmt = ejecutarConsulta($sql, $params);
    return $pdo->lastInsertId();
}
?>