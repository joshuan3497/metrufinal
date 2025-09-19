<?php
// =====================================================
// PÁGINA PRINCIPAL - LOGIN SISTEMA METRU
// =====================================================

session_start();
include 'config/config.php';
include 'includes/functions.php';

// Si ya está logueado, redirigir según el tipo de usuario
if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['usuario_tipo'] == 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: trabajador/index.php');
    }
    exit();
}

$error = '';
$mensaje = '';

// Verificar si viene de logout
if (isset($_GET['logout'])) {
    $mensaje = 'Sesión cerrada correctamente';
}

// Procesar login
if ($_POST) {
    $codigo_usuario = limpiarDatos($_POST['codigo_usuario'] ?? '');
    $password = limpiarDatos($_POST['password'] ?? '');
    
    if (empty($codigo_usuario) || empty($password)) {
        $error = 'Por favor complete todos los campos';
    } else {
        if (autenticarUsuario($codigo_usuario, $password)) {
            // Redirigir según tipo de usuario
            if ($_SESSION['usuario_tipo'] == 'admin') {
                header('Location: admin/index.php');
            } else {
                header('Location: trabajador/index.php');
            }
            exit();
        } else {
            $error = 'Código de usuario o contraseña incorrectos';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo i {
            font-size: 4rem;
            background: linear-gradient(45deg, #007bff, #0056b3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .logo h2 {
            color: #333;
            font-weight: bold;
            margin-top: 0.5rem;
        }
        
        .form-control {
            border-radius: 15px;
            border: 2px solid #e9ecef;
            padding: 12px 20px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        .input-group-text {
            border-radius: 15px 0 0 15px;
            border: 2px solid #e9ecef;
            background: #f8f9fa;
            color: #6c757d;
        }
        
        .btn-login {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
            border-radius: 15px;
            padding: 12px;
            font-size: 18px;
            font-weight: bold;
            color: white;
            width: 100%;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.4);
        }
        
        .alert {
            border-radius: 15px;
            border: none;
        }
        
        .usuarios-demo {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1rem;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }
        
        .usuario-demo {
            display: flex;
            justify-content: space-between;
            margin: 0.25rem 0;
            padding: 0.25rem 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .usuario-demo:last-child {
            border-bottom: none;
        }
        
        .badge-tipo {
            font-size: 0.7rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Logo y título -->
        <div class="logo">
            <i class="fas fa-truck"></i>
            <h2><?php echo APP_NAME; ?></h2>
            <p class="text-muted">Sistema de Gestión de Distribución</p>
        </div>
        
        <!-- Mensajes -->
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <!-- Formulario de login -->
        <form method="POST" action="">
            <div class="mb-3">
                <label for="codigo_usuario" class="form-label">
                    <i class="fas fa-user"></i> Código de Usuario
                </label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-user"></i>
                    </span>
                    <input type="text" 
                           class="form-control" 
                           id="codigo_usuario" 
                           name="codigo_usuario"
                           placeholder="Ingrese su código de usuario"
                           value="<?php echo isset($_POST['codigo_usuario']) ? htmlspecialchars($_POST['codigo_usuario']) : ''; ?>"
                           required 
                           autofocus>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="password" class="form-label">
                    <i class="fas fa-lock"></i> Contraseña
                </label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input type="password" 
                           class="form-control" 
                           id="password" 
                           name="password"
                           placeholder="Ingrese su contraseña"
                           required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-login">
                <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
            </button>
        </form>
        
        <!-- Usuarios de demostración -->
        <div class="usuarios-demo">
            <h6 class="text-muted mb-2">
                <i class="fas fa-info-circle"></i> Usuarios de Prueba:
            </h6>
            
            <div class="usuario-demo">
                <span>
                    <strong>ADMIN001</strong><br>
                    <small class="text-muted">admin123</small>
                </span>
                <span class="badge bg-primary badge-tipo">Administrador</span>
            </div>
            
            <div class="usuario-demo">
                <span>
                    <strong>VEND001</strong><br>
                    <small class="text-muted">vend123</small>
                </span>
                <span class="badge bg-success badge-tipo">Vendedor</span>
            </div>
            
            <div class="usuario-demo">
                <span>
                    <strong>VEND002</strong><br>
                    <small class="text-muted">vend123</small>
                </span>
                <span class="badge bg-success badge-tipo">Vendedor</span>
            </div>
        </div>
        
        <!-- Información del sistema -->
        <div class="text-center mt-3">
            <small class="text-muted">
                Versión <?php echo APP_VERSION; ?> | 
                <i class="fas fa-calendar"></i> <?php echo date('Y'); ?>
            </small>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-focus en el primer campo vacío
        document.addEventListener('DOMContentLoaded', function() {
            const codigoUsuario = document.getElementById('codigo_usuario');
            const password = document.getElementById('password');
            
            if (codigoUsuario.value === '') {
                codigoUsuario.focus();
            } else {
                password.focus();
            }
        });
        
        // Función para llenar automáticamente con usuarios de prueba
        function llenarUsuario(codigo, pass) {
            document.getElementById('codigo_usuario').value = codigo;
            document.getElementById('password').value = pass;
        }
        
        // Agregar clicks a los usuarios de demostración
        document.querySelectorAll('.usuario-demo').forEach(function(elemento) {
            elemento.style.cursor = 'pointer';
            elemento.addEventListener('click', function() {
                const codigo = this.querySelector('strong').textContent;
                const pass = codigo.startsWith('ADMIN') ? 'admin123' : 'vend123';
                llenarUsuario(codigo, pass);
            });
        });
        
        // Efecto de typing en el título
        let titulo = "Sistema de Gestión de Distribución";
        let subtitulo = document.querySelector('.text-muted');
        let index = 0;
        
        function typeWriter() {
            if (index < titulo.length) {
                subtitulo.innerHTML = titulo.substr(0, index + 1) + '<span class="cursor">|</span>';
                index++;
                setTimeout(typeWriter, 100);
            }
        }
        
        // Iniciar el efecto después de un pequeño delay
        setTimeout(typeWriter, 1000);
    </script>
    
    <style>
        .cursor {
            animation: blink 1s infinite;
        }
        
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0; }
        }
    </style>
</body>
</html>