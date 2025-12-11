<?php
/**
 * INDEX PRINCIPAL - ROTEADOR DO SISTEMA (VERSÃO CORRIGIDA)
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 */

// =====================================================
// INCLUIR CONFIGURAÇÕES
// =====================================================
require_once __DIR__ . '/config/paths.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// =====================================================
// OBTER PÁGINA DA URL
// =====================================================
$page = $_GET['page'] ?? 'home';

// Limpar a página para segurança
$page = preg_replace('/[^a-zA-Z0-9_-]/', '', $page);

// =====================================================
// ROTAS PÚBLICAS (sem login necessário)
// =====================================================
$publicRoutes = ['login', 'login-company', 'logout', 'home'];

// =====================================================
// REDIRECIONAR HOME
// =====================================================
if ($page === 'home' || $page === '') {
    if (isLoggedIn()) {
        header('Location: ' . BASE_URL . 'views/dashboard/index.php');
        exit;
    } else {
        header('Location: ' . BASE_URL . 'views/auth/login.php');
        exit;
    }
}

// =====================================================
// VERIFICAR AUTENTICAÇÃO
// =====================================================
if (!in_array($page, $publicRoutes) && !isLoggedIn()) {
    header('Location: ' . BASE_URL . 'views/auth/login.php');
    exit;
}

// =====================================================
// ROTEAMENTO POR SWITCH
// =====================================================
switch ($page) {
    
    // ==================== AUTENTICAÇÃO ====================
    case 'login':
        if (isLoggedIn()) {
            redirect(BASE_URL . 'views/dashboard/index.php');
        }
        require_once VIEWS_PATH . 'auth/login.php';
        break;
    
    case 'login-company':
        if (isLoggedIn()) {
            redirect(BASE_URL . 'views/dashboard/index.php');
        }
        require_once VIEWS_PATH . 'auth/login.php';
        break;
    
    case 'logout':
        require_once VIEWS_PATH . 'auth/logout.php';
        break;
    
    // ==================== DASHBOARD ====================
    case 'dashboard':
        if (!isLoggedIn()) {
            redirect(BASE_URL . 'views/auth/login.php');
        }
        
        // Direcionar para o dashboard correto baseado no tipo de usuário
        if (isUser()) {
            require_once VIEWS_PATH . 'dashboard/index.php';
        } elseif (isCompany()) {
            require_once VIEWS_PATH . 'dashboard/index.php'; // Mesmo arquivo, diferencia por isUser/isCompany
        } else {
            redirect(BASE_URL . 'views/auth/login.php');
        }
        break;
    
    // ==================== TICKETS ====================
    case 'tickets':
        requireLogin();
        requireUser();
        require_once VIEWS_PATH . 'tickets/index.php';
        break;
    
    case 'ticket-view':
        requireLogin();
        require_once VIEWS_PATH . 'tickets/view.php';
        break;
    
    case 'ticket-create':
        requireLogin();
        requireCompany();
        require_once VIEWS_PATH . 'tickets/create.php';
        break;
    
    case 'my-tickets':
        requireLogin();
        requireCompany();
        require_once VIEWS_PATH . 'tickets/my-tickets.php';
        break;
    
    // ==================== USUÁRIOS ====================
    case 'users':
        requireLogin();
        requireUser();
        requireAdmin();
        require_once VIEWS_PATH . 'users/index.php';
        break;
    
    case 'user-create':
        requireLogin();
        requireAdmin();
        require_once VIEWS_PATH . 'users/create.php';
        break;
    
    case 'user-edit':
        requireLogin();
        requireAdmin();
        require_once VIEWS_PATH . 'users/edit.php';
        break;
    
    case 'profile':
        requireLogin();
        require_once VIEWS_PATH . 'users/profile.php';
        break;
    
    // ==================== EMPRESAS ====================
    case 'companies':
        requireLogin();
        requireAdmin();
        require_once VIEWS_PATH . 'companies/index.php';
        break;
    
    case 'company-create':
        requireLogin();
        requireAdmin();
        require_once VIEWS_PATH . 'companies/create.php';
        break;
    
    case 'company-edit':
        requireLogin();
        requireAdmin();
        require_once VIEWS_PATH . 'companies/edit.php';
        break;
    
    // ==================== RELATÓRIOS ====================
    case 'reports':
        requireLogin();
        requireAdmin();
        require_once VIEWS_PATH . 'admin/reports.php';
        break;
    
    case 'settings':
        requireLogin();
        requireAdmin();
        require_once VIEWS_PATH . 'admin/settings.php';
        break;
    
    // ==================== PÁGINA NÃO ENCONTRADA ====================
    default:
        http_response_code(404);
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>404 - Página não encontrada</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        </head>
        <body>
            <div class="container">
                <div class="row justify-content-center align-items-center" style="min-height: 100vh;">
                    <div class="col-md-6 text-center">
                        <i class="fas fa-exclamation-triangle fa-5x text-warning mb-4"></i>
                        <h1 class="display-1">404</h1>
                        <h3 class="mb-3">Página não encontrada</h3>
                        <p class="text-muted mb-4">
                            A página que você está procurando não existe ou foi movida.
                        </p>
                        <a href="<?php echo BASE_URL; ?>" class="btn btn-primary">
                            <i class="fas fa-home me-2"></i>
                            Voltar ao Início
                        </a>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        break;
}
?>