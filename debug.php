<?php
// =====================================================
// DEBUG - SISTEMA METRU
// =====================================================

echo "<h2>🔧 Debug del Sistema Metru</h2>";
echo "<hr>";

// Información del servidor
echo "<h3>📊 Información del Servidor</h3>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>Server:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p><strong>Current Directory:</strong> " . __DIR__ . "</p>";
echo "<p><strong>Request URI:</strong> " . $_SERVER['REQUEST_URI'] . "</p>";

// Verificar archivos principales
echo "<h3>📁 Verificación de Archivos</h3>";
$archivos_criticos = [
    'config/database.php',
    'config/config.php',
    'includes/functions.php',
    'includes/header.php',
    'includes/footer.php',
    'admin/index.php',
    'css/style.css',
    'js/main.js',
    'index.php'
];

foreach ($archivos_criticos as $archivo) {
    if (file_exists($archivo)) {
        echo "<p>✅ <strong>$archivo</strong> - Existe</p>";
    } else {
        echo "<p>❌ <strong>$archivo</strong> - NO EXISTE</p>";
    }
}

// Verificar conexión a base de datos
echo "<h3>🗄️ Conexión a Base de Datos</h3>";
try {
    include_once 'config/database.php';
    echo "<p>✅ Conexión exitosa</p>";
    
    // Verificar tablas
    $stmt = $pdo->query("SHOW TABLES");
    $tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>📊 Tablas encontradas: " . count($tablas) . "</p>";
    
    foreach ($tablas as $tabla) {
        echo "<p>📋 Tabla: $tabla</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error de conexión: " . $e->getMessage() . "</p>";
}

// Verificar sesiones
echo "<h3>🔐 Estado de Sesiones</h3>";
session_start();
echo "<p><strong>Session Status:</strong> " . session_status() . "</p>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";

if (isset($_SESSION['usuario_id'])) {
    echo "<p>✅ Usuario logueado:</p>";
    echo "<p>- ID: " . $_SESSION['usuario_id'] . "</p>";
    echo "<p>- Código: " . $_SESSION['usuario_codigo'] . "</p>";
    echo "<p>- Nombre: " . $_SESSION['usuario_nombre'] . "</p>";
    echo "<p>- Tipo: " . $_SESSION['usuario_tipo'] . "</p>";
} else {
    echo "<p>❌ No hay usuario logueado</p>";
}

// Verificar permisos de carpetas
echo "<h3>📂 Permisos de Carpetas</h3>";
$carpetas = ['config', 'includes', 'admin', 'css', 'js', 'logs'];
foreach ($carpetas as $carpeta) {
    if (is_dir($carpeta)) {
        if (is_readable($carpeta)) {
            echo "<p>✅ <strong>$carpeta</strong> - Legible</p>";
        } else {
            echo "<p>❌ <strong>$carpeta</strong> - NO legible</p>";
        }
    } else {
        echo "<p>⚠️ <strong>$carpeta</strong> - Carpeta no existe</p>";
    }
}

// Enlaces de prueba
echo "<h3>🔗 Enlaces de Prueba</h3>";
echo "<p><a href='index.php' style='color: blue;'>🏠 Ir al Login</a></p>";
echo "<p><a href='admin/index.php' style='color: blue;'>👨‍💼 Panel Admin (requiere login)</a></p>";
echo "<p><a href='init.php' style='color: blue;'>⚙️ Inicializador</a></p>";

// Información adicional
echo "<h3>ℹ️ Información Adicional</h3>";
echo "<p><strong>Zona horaria:</strong> " . date_default_timezone_get() . "</p>";
echo "<p><strong>Fecha/Hora actual:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Memory Limit:</strong> " . ini_get('memory_limit') . "</p>";
echo "<p><strong>Max Execution Time:</strong> " . ini_get('max_execution_time') . " segundos</p>";

?>

<style>
body { 
    font-family: Arial, sans-serif; 
    padding: 20px; 
    background: #f5f5f5; 
    line-height: 1.6;
}
h2, h3 { 
    color: #333; 
    border-bottom: 2px solid #007bff; 
    padding-bottom: 10px;
}
p { 
    margin: 8px 0; 
    padding: 5px;
    background: white;
    border-radius: 5px;
}
a { 
    text-decoration: none; 
    padding: 10px 15px; 
    background: #007bff; 
    color: white; 
    border-radius: 5px; 
    display: inline-block;
    margin: 5px;
}
a:hover { 
    background: #0056b3; 
}
</style>