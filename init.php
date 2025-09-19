<?php
// =====================================================
// INICIALIZACIÓN DEL SISTEMA - SISTEMA METRU
// =====================================================

echo "<h2>Inicializando Sistema Metru...</h2>";

// Crear carpetas necesarias
$carpetas = [
    'logs',
    'uploads',
    'temp',
    'backups'
];

foreach ($carpetas as $carpeta) {
    if (!is_dir($carpeta)) {
        if (mkdir($carpeta, 0755, true)) {
            echo "<p>✅ Carpeta '$carpeta' creada exitosamente</p>";
        } else {
            echo "<p>❌ Error creando carpeta '$carpeta'</p>";
        }
    } else {
        echo "<p>ℹ️ Carpeta '$carpeta' ya existe</p>";
    }
}

// Crear archivo de log vacío
$archivo_log = 'logs/error.log';
if (!file_exists($archivo_log)) {
    if (file_put_contents($archivo_log, '') !== false) {
        echo "<p>✅ Archivo de log creado: $archivo_log</p>";
    } else {
        echo "<p>❌ Error creando archivo de log</p>";
    }
}

// Verificar permisos de escritura
$carpetas_permisos = ['logs', 'uploads', 'temp'];
foreach ($carpetas_permisos as $carpeta) {
    if (is_writable($carpeta)) {
        echo "<p>✅ Permisos de escritura OK: $carpeta</p>";
    } else {
        echo "<p>⚠️ Sin permisos de escritura: $carpeta</p>";
    }
}

// Probar conexión a base de datos
echo "<h3>Probando conexión a base de datos...</h3>";

try {
    include 'config/database.php';
    echo "<p>✅ Conexión a base de datos exitosa</p>";
    
    // Verificar que las tablas existan
    $stmt = $pdo->query("SHOW TABLES");
    $tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p>📊 Tablas encontradas: " . count($tablas) . "</p>";
    
    $tablas_requeridas = ['usuarios', 'productos', 'rutas', 'clientes', 'salidas_mercancia'];
    foreach ($tablas_requeridas as $tabla) {
        if (in_array($tabla, $tablas)) {
            echo "<p>✅ Tabla '$tabla' encontrada</p>";
        } else {
            echo "<p>❌ Tabla '$tabla' NO encontrada</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error de conexión: " . $e->getMessage() . "</p>";
    echo "<p>🔧 Verificar configuración en config/database.php</p>";
}

echo "<h3>Sistema inicializado</h3>";
echo "<p><a href='index.php' class='btn btn-primary'>Ir al Login</a></p>";
?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; }
.btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
</style>