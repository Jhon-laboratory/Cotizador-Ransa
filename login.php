<?php
session_start();
require_once 'conexion.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($login) || empty($password)) {
        $error = "Por favor ingrese usuario y contraseña";
    } else {
        // Determinar si es email o nombre de usuario
        if (strpos($login, '@') !== false) {
            // Es un correo
            $sql = "SELECT * FROM IT.usuarios_pt WHERE correo = ? AND email_verificado_en IS NOT NULL";
        } else {
            // Es un nombre de usuario (buscamos por nombre exacto)
            $sql = "SELECT * FROM IT.usuarios_pt WHERE nombre = ? AND email_verificado_en IS NOT NULL";
        }
        
        $params = array($login);
        $options = array("Scrollable" => SQLSRV_CURSOR_KEYSET);
        $stmt = sqlsrv_query($conn, $sql, $params, $options);
        
        if ($stmt === false) {
            die(print_r(sqlsrv_errors(), true));
        }
        
        $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        
        if ($user) {
            // Verificar contraseña (usando password_verify para bcrypt)
            if (password_verify($password, $user['contrasena'])) {
                // Login exitoso
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nombre'];
                $_SESSION['user_email'] = $user['correo'];
                $_SESSION['user_type'] = $user['tipo_usuario'];
                $_SESSION['id_perfil'] = $user['id_perfil'];
                $_SESSION['user_area'] = $user['area'];
                $_SESSION['user_subarea'] = $user['subarea'];
                $_SESSION['user_ciudad'] = $user['ciudad'];
                $_SESSION['user_pais'] = $user['pais'];
                $_SESSION['user_color'] = $user['color'] ?? '#009A3F';
                
                // Redirigir al dashboard
                header('Location: index.php');
                exit;
            } else {
                $error = "Contraseña incorrecta";
            }
        } else {
            $error = "Usuario no encontrado o no verificado";
        }
        
        sqlsrv_free_stmt($stmt);
    }
}

// Mostrar formulario de login
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LOGIRAN S.A. - Sistema de Cotización</title>
  
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    body {
       background: linear-gradient(rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.06)), 
                    url('img/imglogin.jpg') center/cover no-repeat fixed;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .login-container {
      width: 100%;
      max-width: 420px;
    }

    .login-card {
      background: white;
      border-radius: 15px;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
      overflow: hidden;
      border: none;
    }

    .login-header {
      background: linear-gradient(135deg, #009A3F 0%, #00C851 100%);
      color: white;
      padding: 30px 20px;
      text-align: center;
      position: relative;
    }

    .login-header h1 {
      font-size: 26px;
      font-weight: 700;
      margin: 0;
      letter-spacing: 0.5px;
    }

    .login-header p {
      font-size: 14px;
      opacity: 0.9;
      margin-top: 5px;
    }

    .login-body {
      padding: 35px 30px;
    }

    .form-group {
      margin-bottom: 25px;
      position: relative;
    }

    .form-label {
      color: #009A3F;
      font-weight: 600;
      font-size: 14px;
      margin-bottom: 8px;
      display: block;
    }

    .input-group {
      position: relative;
    }

    .input-group .form-control {
      height: 52px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      padding-left: 50px;
      font-size: 15px;
      transition: all 0.3s ease;
    }

    .input-group .form-control:focus {
      border-color: #009A3F;
      box-shadow: 0 0 0 0.2rem rgba(0, 154, 63, 0.25);
    }

    .input-group-icon {
      position: absolute;
      left: 0;
      top: 0;
      height: 52px;
      width: 52px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #009A3F;
      font-size: 18px;
      z-index: 4;
    }

    .btn-login {
      background: linear-gradient(135deg, #009A3F 0%, #00C851 100%);
      border: none;
      color: white;
      height: 52px;
      font-size: 16px;
      font-weight: 600;
      border-radius: 8px;
      width: 100%;
      transition: all 0.3s ease;
      letter-spacing: 0.5px;
    }

    .btn-login:hover {
      background: linear-gradient(135deg, #008a35 0%, #00b848 100%);
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0, 154, 63, 0.3);
    }

    .login-footer {
      text-align: center;
      padding-top: 20px;
      border-top: 1px solid #eee;
      margin-top: 25px;
      color: #666;
      font-size: 14px;
    }

    .login-footer a {
      color: #009A3F;
      text-decoration: none;
      font-weight: 600;
    }

    .login-footer a:hover {
      text-decoration: underline;
    }

    .alert-danger {
      background-color: #f8d7da;
      border: 2px solid #f5c6cb;
      border-radius: 8px;
      color: #721c24;
      font-size: 14px;
      padding: 12px 15px;
      margin-bottom: 20px;
    }

    .logo-container {
      display: flex;
      justify-content: center;
      margin-bottom: 15px;
    }

    .logo-circle {
      width: 80px;
      height: 80px;
      background: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .logo-circle i {
      font-size: 36px;
      color: #009A3F;
    }

    .password-toggle {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #666;
      cursor: pointer;
      z-index: 5;
    }

    .password-toggle:hover {
      color: #009A3F;
    }
  </style>
</head>

<body>
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        
        <h1>LOGIRAN S.A.</h1>
        <p>Sistema de Cotización de Tarifas</p>
      </div>
      
      <div class="login-body">
        
        
        <?php if(isset($error)): ?>
          <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>
        
        <form action="login.php" method="post" autocomplete="off">
          <div class="form-group">
            <label class="form-label" for="login">
              <i class="fas fa-user me-2"></i>Usuario o Correo
            </label>
            <div class="input-group">
              <div class="input-group-icon">
                <i class="fas fa-envelope"></i>
              </div>
              <input type="text" 
                     class="form-control" 
                     id="login" 
                     name="login" 
                     placeholder="ejemplo@ransa.net o nombre de usuario" 
                     required
                     value="<?php echo isset($_POST['login']) ? htmlspecialchars($_POST['login']) : ''; ?>">
            </div>
          </div>
          
          <div class="form-group">
            <label class="form-label" for="password">
              <i class="fas fa-key me-2"></i>Contraseña
            </label>
            <div class="input-group">
              <div class="input-group-icon">
                <i class="fas fa-lock"></i>
              </div>
              <input type="password" 
                     class="form-control" 
                     id="password" 
                     name="password" 
                     placeholder="Ingresa tu contraseña" 
                     required>
              <button type="button" class="password-toggle" id="togglePassword">
                <i class="far fa-eye"></i>
              </button>
            </div>
          </div>
          
          <div class="form-group mt-4">
            <button type="submit" name="enviar" class="btn btn-login">
              <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
            </button>
          </div>
        </form>
        
        
      </div>
    </div>
  </div>

  <!-- Bootstrap JS Bundle with Popper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Toggle password visibility
    document.getElementById('togglePassword').addEventListener('click', function() {
      const passwordInput = document.getElementById('password');
      const icon = this.querySelector('i');
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    });

    // Auto-focus on login input
    document.addEventListener('DOMContentLoaded', function() {
      document.getElementById('login').focus();
    });

    // Add animation on load
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelector('.login-card').style.opacity = '0';
      document.querySelector('.login-card').style.transform = 'translateY(20px)';
      
      setTimeout(function() {
        document.querySelector('.login-card').style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        document.querySelector('.login-card').style.opacity = '1';
        document.querySelector('.login-card').style.transform = 'translateY(0)';
      }, 100);
    });
  </script>
</body>
</html>

<?php
// Cerrar conexión si está abierta
if (isset($conn)) {
    sqlsrv_close($conn);
}
?>