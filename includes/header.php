<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Detectar la ruta correcta al archivo de configuración
$config_path = '';
if (file_exists('config/config.php')) {
    $config_path = 'config/config.php';
} elseif (file_exists('../config/config.php')) {
    $config_path = '../config/config.php';
} elseif (file_exists('../../config/config.php')) {
    $config_path = '../../config/config.php';
}

if ($config_path) {
    include_once $config_path;
} else {
    // Definir constantes básicas si no se encuentra el archivo
    define('APP_NAME', 'Sistema Metru');
    define('APP_VERSION', '1.0.0');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($titulo_pagina) ? $titulo_pagina . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- IMPORTANTE: jQuery DEBE cargarse AQUÍ, ANTES de cualquier otro script -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom CSS -->
    <?php
    $css_path = '';
    if (file_exists('css/style.css')) {
        $css_path = 'css/style.css';
    } elseif (file_exists('../css/style.css')) {
        $css_path = '../css/style.css';
    }
    if ($css_path): ?>
    <link rel="stylesheet" href="<?php echo $css_path; ?>">
    <?php endif; ?>

    <script src="/Metru/js/offline-handler.js"></script>
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-brand {
            font-weight: bold;
            color: #007bff !important;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            font-weight: bold;
        }
        .btn-primary {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
            border-radius: 10px;
        }
        .btn-success {
            background: linear-gradient(45deg, #28a745, #1e7e34);
            border: none;
            border-radius: 10px;
        }
        .btn-warning {
            background: linear-gradient(45deg, #ffc107, #d39e00);
            border: none;
            border-radius: 10px;
        }
        .btn-danger {
            background: linear-gradient(45deg, #dc3545, #a71d2a);
            border: none;
            border-radius: 10px;
        }
        .table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
        }
        .form-control:focus, .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .sidebar {
            background: linear-gradient(180deg, #2c3e50, #34495e);
            min-height: 100vh;
            padding-top: 20px;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 12px 20px;
            margin: 5px 10px;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }
        .sidebar .nav-link.active {
            background-color: #007bff;
            color: white;
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .badge {
            border-radius: 8px;
        }

        /* Prevenir parpadeo de modales */
        .modal {
            display: none;
        }

        .modal.show {
            display: block !important;
        }

        .modal-backdrop {
            opacity: 0;
            transition: opacity 0.15s linear;
        }

        .modal-backdrop.show {
            opacity: 0.5;
        }

        /* Prevenir saltos de scroll */
        body.modal-open {
            overflow: hidden;
            padding-right: 0 !important;
        }
    </style>
    
    <!-- Script de verificación -->
    <script>
    // Verificar que jQuery está cargado
    if (typeof jQuery === 'undefined') {
        console.error('❌ jQuery NO está cargado!');
    } else {
        console.log('✅ jQuery cargado correctamente, versión:', jQuery.fn.jquery);
    }
    </script>
    <style>
    /* Arreglar menú responsive */
    @media (max-width: 768px) {
        .sidebar {
            position: fixed;
            top: 56px;
            left: -250px;
            width: 250px;
            height: calc(100% - 56px);
            background: #2c3e50;
            transition: left 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar.active {
            left: 0;
        }
        
        .content-wrapper {
            margin-left: 0 !important;
            padding: 15px;
        }
        
        /* Botón toggle para móvil */
        .menu-toggle {
            display: block !important;
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            background: #007bff;
            color: white;
            border-radius: 50%;
            border: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            z-index: 1001;
        }
    }

    /* Ocultar botón en desktop */
    .menu-toggle {
        display: none;
    }
    </style>


</head>
<body>

<!-- Botón toggle para móvil -->
<button class="menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
}


// Cerrar menú al hacer clic fuera
document.addEventListener('click', function(e) {
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.querySelector('.menu-toggle');
    
    // Verificar que los elementos existen antes de usar contains
    if (sidebar && toggle && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
        sidebar.classList.remove('active');
    }
});
</script>   

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo file_exists('index.php') ? 'index.php' : '../index.php'; ?>">
            <i class="fas fa-truck" style="color: white;"></i> 
            <span style="color: white; font-weight: bold;"><?php echo APP_NAME; ?></span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div id="connection-status" class="ms-2"></div>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if (isset($_SESSION['usuario_tipo'])): ?>
                    <?php if ($_SESSION['usuario_tipo'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo file_exists('admin/index.php') ? 'admin/index.php' : 'index.php'; ?>">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo file_exists('admin/salidas.php') ? 'admin/salidas.php' : 'salidas.php'; ?>">
                                <i class="fas fa-box"></i> Salidas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo file_exists('admin/reportes.php') ? 'admin/reportes.php' : 'reportes.php'; ?>">
                                <i class="fas fa-chart-bar"></i> Reportes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo file_exists('admin/cierres.php') ? 'admin/cierres.php' : 'cierres.php'; ?>">
                                <i class="fas fa-check-circle"></i> Cierres
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo file_exists('trabajador/index.php') ? 'trabajador/index.php' : 'index.php'; ?>">
                                <i class="fas fa-home"></i> Inicio
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo file_exists('trabajador/crear_factura.php') ? 'trabajador/crear_factura.php' : 'crear_factura.php'; ?>">
                                <i class="fas fa-plus"></i> Nueva Factura
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo file_exists('trabajador/mis_facturas.php') ? 'trabajador/mis_facturas.php' : 'mis_facturas.php'; ?>">
                                <i class="fas fa-list"></i> Mis Facturas
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['usuario_nombre'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['usuario_nombre']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo file_exists('includes/logout.php') ? 'includes/logout.php' : '../includes/logout.php'; ?>">
                                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                            </a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Contenido principal -->
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar para admin (opcional) -->
        <?php if (isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] == 'admin' && !isset($sin_sidebar)): ?>
        <div class="col-md-2 sidebar">
            <nav class="nav flex-column">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a class="nav-link" href="salidas.php">
                    <i class="fas fa-shipping-fast"></i> Gestionar Salidas
                </a>
                <a class="nav-link" href="reportes.php">
                    <i class="fas fa-chart-line"></i> Reportes
                </a>
                <a class="nav-link" href="cierres.php">
                    <i class="fas fa-calculator"></i> Cierres de Ruta
                </a>
                <a class="nav-link" href="configuracion.php">
                    <i class="fas fa-cog"></i> Configuración
                </a>
                <a class="nav-link" href="productos.php">
                    <i class="fas fa-box"></i> Productos
                </a>
                <a class="nav-link" href="rutas.php">
                    <i class="fas fa-route"></i> Rutas
                </a>
                <a class="nav-link" href="clientes.php">
                    <i class="fas fa-store"></i> Clientes
                </a>
                <a class="nav-link" href="configuracion_productos.php">
                    <i class="fas fa-dollar-sign"></i> Precios y Ganancias
                </a>

                <a class="nav-link" href="verificar_integridad.php">
                    <i class="fas fa-shield-alt"></i> Integridad de Datos
                </a>
            </nav>
        </div>
        <div class="col-md-10">
        <?php else: ?>
        <div class="col-12">
        <?php endif; ?>
            
            <div class="main-content p-4">
                
                <!-- Mostrar mensajes de alerta -->
                <?php if (isset($_SESSION['mensaje'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['tipo_mensaje']; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-info-circle"></i> <?php echo $_SESSION['mensaje']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php 
                    unset($_SESSION['mensaje']);
                    unset($_SESSION['tipo_mensaje']);
                    ?>
                <?php endif; ?>
                
                <!-- Mostrar título de página si está definido -->
                <?php if (isset($titulo_pagina)): ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="text-primary">
                            <?php if (isset($icono_pagina)): ?>
                                <i class="<?php echo $icono_pagina; ?>"></i>
                            <?php endif; ?>
                            <?php echo $titulo_pagina; ?>
                        </h2>
                        <small class="text-muted">
                            <i class="fas fa-calendar"></i> <?php echo date('d/m/Y'); ?>
                        </small>
                    </div>
                <?php endif; ?>