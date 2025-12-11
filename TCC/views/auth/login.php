<?php
/**
 * Página de Login
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 */

// Incluir configurações
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Se já estiver logado, redirecionar para dashboard
if (isLoggedIn()) {
    redirect(BASE_URL . 'views/dashboard/index.php');
}

$pageTitle = 'Login - ' . SYSTEM_NAME;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            max-width: 450px;
            width: 100%;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-header i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .login-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .login-header p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .user-type-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }
        
        .user-type-btn {
            flex: 1;
            padding: 12px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .user-type-btn:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }
        
        .user-type-btn.active {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .user-type-btn i {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 5px;
        }
        
        .user-type-btn span {
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .form-floating {
            margin-bottom: 20px;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            padding: 12px 15px;
            height: calc(3.5rem + 2px);
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.15);
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }
        
        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
        }
        
        .login-footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            font-size: 0.85rem;
            color: #666;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 20px;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            z-index: 10;
        }
        
        .form-floating {
            position: relative;
        }
    </style>
</head>
<body>
    
    <div class="login-container">
        <div class="login-card">
            
            <!-- Header -->
            <div class="login-header">
                <i class="fas fa-headset"></i>
                <h1><?php echo SYSTEM_SHORT_NAME; ?></h1>
                <p>Sistema de Suporte e Manutenção</p>
            </div>
            
            <!-- Body -->
            <div class="login-body">
                
                <?php displayFlashMessage(); ?>
                
                <!-- Seletor de Tipo de Usuário -->
                <div class="user-type-selector">
                    <div class="user-type-btn active" data-type="user">
                        <i class="fas fa-user-tie"></i>
                        <span>Funcionário</span>
                    </div>
                    <div class="user-type-btn" data-type="company">
                        <i class="fas fa-building"></i>
                        <span>Empresa</span>
                    </div>
                </div>
                
                <!-- Formulário de Login -->
                <form method="POST" action="<?php echo BASE_URL; ?>controllers/LoginController.php?action=login" id="loginForm">
                    
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="user_type" id="userType" value="user">
                    
                    <!-- Email -->
                    <div class="form-floating">
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               placeholder="seu@email.com" 
                               required 
                               autofocus>
                        <label for="email">
                            <i class="fas fa-envelope me-2"></i>E-mail
                        </label>
                    </div>
                    
                    <!-- Senha -->
                    <div class="form-floating">
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Senha" 
                               required>
                        <label for="password">
                            <i class="fas fa-lock me-2"></i>Senha
                        </label>
                        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                    </div>
                    
                    <!-- Lembrar-me -->
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember">
                        <label class="form-check-label" for="remember">
                            Lembrar-me
                        </label>
                    </div>
                    
                    <!-- Botão de Login -->
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Entrar
                    </button>
                    
                    <!-- Esqueceu a senha -->
                    <div class="forgot-password">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">
                            <i class="fas fa-question-circle me-1"></i>
                            Esqueceu sua senha?
                        </a>
                    </div>
                    
                </form>
                
            </div>
            
            <!-- Footer -->
            <div class="login-footer">
                <p class="mb-0">
                    &copy; <?php echo date('Y'); ?> <?php echo SYSTEM_NAME; ?><br>
                    <small>Versão <?php echo SYSTEM_VERSION; ?></small>
                </p>
            </div>
            
        </div>
    </div>
    
    <!-- Modal Esqueci a Senha -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 15px;">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-key me-2"></i>
                        Recuperar Senha
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted mb-3">
                        Entre em contato com o administrador do sistema para recuperar sua senha.
                    </p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Contato:</strong><br>
                        <i class="fas fa-envelope me-2"></i> suporte@sistema.com<br>
                        <i class="fas fa-phone me-2"></i> (14) 3333-0000
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <script>
    $(document).ready(function() {
        
        // Seletor de tipo de usuário
        $('.user-type-btn').click(function() {
            $('.user-type-btn').removeClass('active');
            $(this).addClass('active');
            
            const type = $(this).data('type');
            $('#userType').val(type);
            
            // Atualizar placeholder do email
            if (type === 'company') {
                $('#email').attr('placeholder', 'contato@empresa.com');
            } else {
                $('#email').attr('placeholder', 'seu@email.com');
            }
        });
        
        // Toggle mostrar/ocultar senha
        $('#togglePassword').click(function() {
            const passwordField = $('#password');
            const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
            passwordField.attr('type', type);
            
            // Alternar ícone
            $(this).toggleClass('fa-eye fa-eye-slash');
        });
        
        // Validação do formulário
        $('#loginForm').submit(function(e) {
            const email = $('#email').val().trim();
            const password = $('#password').val();
            
            if (!email || !password) {
                e.preventDefault();
                alert('Por favor, preencha todos os campos.');
                return false;
            }
            
            // Validar formato de email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Por favor, insira um e-mail válido.');
                return false;
            }
        });
        
        // Auto-fechar alertas após 5 segundos
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
        
    });
    </script>
    
</body>
</html>