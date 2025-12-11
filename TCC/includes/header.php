<?php
/**
 * Header com Sistema de Notificações Completo - VERSÃO FINAL TESTADA
 * Sistema de Suporte e Manutenção
 * Arquivo: includes/header.php
 */

// Verificar se está logado
$isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['company_id']);
$userName = '';
$userType = '';
$userId = null;
$companyId = null;

if (isset($_SESSION['user_name'])) {
    $userName = $_SESSION['user_name'];
    $userType = 'Funcionário';
    $userId = $_SESSION['user_id'];
} elseif (isset($_SESSION['company_name'])) {
    $userName = $_SESSION['company_name'];
    $userType = 'Empresa';
    $companyId = $_SESSION['company_id'];
}

// Buscar notificações não lidas
$unreadCount = 0;
$notifications = [];

if ($isLoggedIn) {
    try {
        $pdo = getConnection();
        
        if ($userId) {
            // Notificações do usuário
            $stmt = $pdo->prepare("
                SELECT n.*, t.title as ticket_title, c.name as company_name
                FROM notifications n
                INNER JOIN tickets t ON n.ticket_id = t.id
                INNER JOIN companies c ON n.company_id = c.id
                WHERE n.user_id = ?
                ORDER BY n.created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$userId]);
            $notifications = $stmt->fetchAll();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$userId]);
            $unreadCount = $stmt->fetchColumn();
            
        } elseif ($companyId) {
            // Notificações da empresa
            $stmt = $pdo->prepare("
                SELECT n.*, t.title as ticket_title, u.name as user_name
                FROM notifications n
                INNER JOIN tickets t ON n.ticket_id = t.id
                LEFT JOIN users u ON n.user_id = u.id
                WHERE n.company_id = ?
                ORDER BY n.created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$companyId]);
            $notifications = $stmt->fetchAll();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE company_id = ? AND is_read = 0");
            $stmt->execute([$companyId]);
            $unreadCount = $stmt->fetchColumn();
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar notificações: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? SYSTEM_NAME); ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(ASSETS_URL); ?>images/favicon.png">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- CSS Customizado -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(ASSETS_URL); ?>css/style.css">
    
    <style>
    /* ================================================
       VARIÁVEIS DE TEMA
       ================================================ */
    :root {
        --navbar-bg: #0d6efd;
        --navbar-text: #ffffff;
        --bg-primary: #ffffff;
        --bg-secondary: #f8f9fa;
        --text-primary: #212529;
        --text-secondary: #6c757d;
        --border-color: #dee2e6;
        --gradient-start: #667eea;
        --gradient-end: #764ba2;
    }

    [data-theme="dark"] {
        --navbar-bg: #1a1d23;
        --navbar-text: #e9ecef;
        --bg-primary: #1a1d23;
        --bg-secondary: #252932;
        --text-primary: #e9ecef;
        --text-secondary: #adb5bd;
        --border-color: #343a40;
    }

    /* ================================================
       RESET E BASE
       ================================================ */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        transition: background-color 0.3s ease, color 0.3s ease;
        padding-top: 70px;
    }

    /* ================================================
       NAVBAR
       ================================================ */
    .navbar-custom {
        background-color: var(--navbar-bg) !important;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        padding: 0.5rem 0;
        height: 70px !important;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1030;
    }

    .navbar-brand {
        font-weight: 700;
        font-size: 1.25rem;
        color: var(--navbar-text) !important;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .navbar-brand i {
        font-size: 1.5rem;
    }

    .navbar-nav .nav-link {
        color: rgba(255, 255, 255, 0.85) !important;
        font-weight: 500;
        padding: 0.4rem 0.875rem;
        border-radius: 6px;
        transition: all 0.3s ease;
        margin: 0 0.2rem;
        font-size: 0.95rem;
    }

    .navbar-nav .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.1);
        color: white !important;
    }

    /* ================================================
       NOTIFICAÇÃO BELL
       ================================================ */
    .notification-bell {
        position: relative;
        background: rgba(255, 255, 255, 0.15);
        border: 2px solid rgba(255, 255, 255, 0.3);
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-left: 0.5rem;
    }

    .notification-bell:hover {
        background: rgba(255, 255, 255, 0.25);
        transform: scale(1.05);
    }

    .notification-bell.has-unread {
        animation: bellShake 2s ease-in-out infinite;
    }

    @keyframes bellShake {
        0%, 50%, 100% { transform: rotate(0deg); }
        10%, 30% { transform: rotate(-15deg); }
        20%, 40% { transform: rotate(15deg); }
    }

    .notification-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: #dc3545;
        color: white;
        border-radius: 50%;
        min-width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
        border: 2px solid var(--navbar-bg);
        animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }

    /* ================================================
       PAINEL DE NOTIFICAÇÕES
       ================================================ */
    .notification-panel {
        position: fixed;
        top: 0;
        right: -420px;
        width: 420px;
        height: 100vh;
        background: var(--bg-primary);
        box-shadow: -4px 0 20px rgba(0, 0, 0, 0.15);
        z-index: 1100;
        transition: right 0.4s cubic-bezier(0.4, 0.0, 0.2, 1);
        display: flex;
        flex-direction: column;
    }

    .notification-panel.active {
        right: 0;
    }

    .notification-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1050;
        opacity: 0;
        visibility: hidden;
        transition: all 0.4s ease;
        backdrop-filter: blur(2px);
    }

    .notification-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .notification-header {
        padding: 20px;
        background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .notification-header h5 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .notification-close {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .notification-close:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(90deg);
    }

    .notification-actions {
        padding: 15px 20px;
        background: var(--bg-secondary);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        gap: 10px;
    }

    .notification-actions button {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid var(--border-color);
        background: var(--bg-primary);
        color: var(--text-primary);
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .notification-actions button:hover {
        background: var(--gradient-start);
        color: white;
        border-color: var(--gradient-start);
        transform: translateY(-2px);
    }

    .notification-list {
        flex: 1;
        overflow-y: auto;
        padding: 10px;
    }

    .notification-list::-webkit-scrollbar {
        width: 6px;
    }

    .notification-list::-webkit-scrollbar-track {
        background: var(--bg-secondary);
    }

    .notification-list::-webkit-scrollbar-thumb {
        background: var(--border-color);
        border-radius: 3px;
    }

    .notification-item {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
    }

    .notification-item:hover {
        transform: translateX(-5px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border-color: var(--gradient-start);
    }

    .notification-item.unread {
        background: linear-gradient(to right, rgba(102, 126, 234, 0.05), var(--bg-primary));
        border-left: 4px solid var(--gradient-start);
    }

    .notification-item.unread::before {
        content: '';
        position: absolute;
        top: 15px;
        right: 15px;
        width: 10px;
        height: 10px;
        background: #dc3545;
        border-radius: 50%;
        animation: pulse 2s ease-in-out infinite;
    }

    .notification-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        margin-bottom: 10px;
    }

    .notification-icon.assumed { background: rgba(13, 202, 240, 0.1); color: #0dcaf0; }
    .notification-icon.dispatched { background: rgba(13, 110, 253, 0.1); color: #0d6efd; }
    .notification-icon.resolved { background: rgba(25, 135, 84, 0.1); color: #198754; }
    .notification-icon.closed { background: rgba(108, 117, 125, 0.1); color: #6c757d; }
    .notification-icon.reopened { background: rgba(220, 53, 69, 0.1); color: #dc3545; }

    .notification-content h6 {
        font-size: 14px;
        font-weight: 700;
        margin: 0 0 5px 0;
        color: var(--text-primary);
    }

    .notification-content p {
        font-size: 13px;
        margin: 0 0 8px 0;
        color: var(--text-secondary);
        line-height: 1.5;
    }

    .notification-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 12px;
        color: var(--text-secondary);
    }

    .notification-time {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .notification-empty {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-secondary);
    }

    .notification-empty i {
        font-size: 64px;
        opacity: 0.3;
        margin-bottom: 20px;
    }

    .notification-empty h6 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .notification-footer {
        padding: 15px 20px;
        background: var(--bg-secondary);
        border-top: 1px solid var(--border-color);
        text-align: center;
    }

    .notification-footer a {
        color: var(--gradient-start);
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .notification-footer a:hover {
        color: var(--gradient-end);
    }

    /* ================================================
       BOTÃO TEMA
       ================================================ */
    #theme-toggle {
        background: rgba(255, 255, 255, 0.15);
        border: 2px solid rgba(255, 255, 255, 0.3);
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-left: 0.5rem;
    }

    #theme-toggle:hover {
        background: rgba(255, 255, 255, 0.25);
        transform: scale(1.05) rotate(15deg);
    }

    .theme-icon-light, .theme-icon-dark {
        font-size: 16px;
        transition: all 0.3s ease;
    }

    [data-theme="light"] .theme-icon-light {
        color: #ffc107;
        display: inline-block;
    }
    
    [data-theme="light"] .theme-icon-dark {
        display: none;
    }
    
    [data-theme="dark"] .theme-icon-light {
        display: none;
    }
    
    [data-theme="dark"] .theme-icon-dark {
        color: #64b5f6;
        display: inline-block;
    }

    /* ================================================
       DROPDOWN DO USUÁRIO - 100% JAVASCRIPT PURO
       ================================================ */
    .user-dropdown {
        position: relative;
        margin-left: 1rem;
    }

    .user-dropdown-btn {
        background: rgba(255, 255, 255, 0.15);
        border: 2px solid rgba(255, 255, 255, 0.3);
        color: #ffffff;
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .user-dropdown-btn:hover {
        background: rgba(255, 255, 255, 0.25);
        transform: scale(1.02);
    }

    .user-dropdown-btn .dropdown-arrow {
        margin-left: 0.25rem;
        font-size: 0.75rem;
        transition: transform 0.3s ease;
    }

    .user-dropdown-btn.active .dropdown-arrow {
        transform: rotate(180deg);
    }

    .user-dropdown-menu {
        position: absolute;
        top: calc(100% + 0.5rem);
        right: 0;
        background-color: #ffffff;
        border: 1px solid #dee2e6;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        border-radius: 12px;
        padding: 0.5rem;
        min-width: 220px;
        z-index: 2000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px) scale(0.95);
        transform-origin: top right;
        transition: all 0.2s ease;
    }

    .user-dropdown-menu.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0) scale(1);
    }

    [data-theme="dark"] .user-dropdown-menu {
        background-color: #1a1d23;
        border-color: #343a40;
    }

    .user-dropdown-item {
        color: #212529;
        padding: 0.6rem 1rem;
        border-radius: 8px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        text-decoration: none;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    [data-theme="dark"] .user-dropdown-item {
        color: #e9ecef;
    }

    .user-dropdown-item:hover {
        background-color: #f8f9fa;
        color: #212529;
        transform: translateX(4px);
    }

    [data-theme="dark"] .user-dropdown-item:hover {
        background-color: #252932;
        color: #e9ecef;
    }

    .user-dropdown-item i {
        width: 20px;
        text-align: center;
        font-size: 16px;
    }

    .user-dropdown-text {
        padding: 0.6rem 1rem;
        color: #6c757d;
        font-size: 0.85rem;
    }

    [data-theme="dark"] .user-dropdown-text {
        color: #adb5bd;
    }

    .user-dropdown-text small {
        color: #6c757d;
        display: block;
        font-size: 0.8rem;
    }

    [data-theme="dark"] .user-dropdown-text small {
        color: #adb5bd;
    }

    .user-dropdown-divider {
        border-top: 1px solid #dee2e6;
        margin: 0.5rem 0;
    }

    [data-theme="dark"] .user-dropdown-divider {
        border-color: #343a40;
    }

    .user-dropdown-item.danger {
        color: #dc3545;
    }

    .user-dropdown-item.danger:hover {
        background-color: #dc3545;
        color: #ffffff;
    }

    /* ================================================
       RESPONSIVO
       ================================================ */
    @media (max-width: 768px) {
        .notification-panel {
            width: 100%;
            right: -100%;
        }
        
        .user-dropdown-menu {
            right: 10px;
        }
    }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
    <div class="container-fluid px-4">
        
        <!-- Logo/Brand -->
        <a class="navbar-brand" href="<?php echo BASE_URL; ?>">
            <i class="fas fa-headset"></i>
            <?php echo SYSTEM_SHORT_NAME ?? SYSTEM_NAME; ?>
        </a>

        <!-- Botão Mobile -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Menu -->
        <div class="collapse navbar-collapse" id="navbarNav">
            
            <?php if ($isLoggedIn): ?>
                
                
                <ul class="navbar-nav me-auto">
            <!-- NÃO RETIRA SO TA AQUI POR CONTA DO ESPAÇAMENTO DO HEADER NO CANTO DIREITO -->    
                </ul>

                <!-- Acoes do Usuário -->
                <div class="d-flex align-items-center">
                    
                    <!-- Notificações -->
                    <div class="notification-bell <?php echo $unreadCount > 0 ? 'has-unread' : ''; ?>" 
                         id="notificationBell"
                         title="Notificações">
                        <i class="fas fa-bell"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span class="notification-badge" id="notificationBadge"><?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Botão Tema -->
                    <button id="theme-toggle" title="Alternar tema">
                        <i class="fas fa-sun theme-icon-light"></i>
                        <i class="fas fa-moon theme-icon-dark"></i>
                    </button>

                    <!-- Dropdown Usuário -->
                    <div class="user-dropdown">
                        <button class="user-dropdown-btn" type="button" id="userDropdownBtn">
                            <i class="fas fa-user-circle"></i>
                            <span><?php echo htmlspecialchars(substr($userName, 0, 20)); ?></span>
                            <i class="fas fa-chevron-down dropdown-arrow"></i>
                        </button>
                        <div class="user-dropdown-menu" id="userDropdownMenu">
                            <div class="user-dropdown-text">
                                <small><?php echo $userType; ?></small>
                            </div>
                            <div class="user-dropdown-divider"></div>
                            <a class="user-dropdown-item" href="<?php echo BASE_URL; ?>views/users/profile.php">
                                <i class="fas fa-user"></i>
                                <span>Meu Perfil</span>
                            </a>
                            <a class="user-dropdown-item" href="<?php echo BASE_URL; ?>views/users/edit.php ">
                                <i class="fas fa-cog"></i>
                                <span>Configurações</span>
                            </a>
                            <div class="user-dropdown-divider"></div>
                            <a class="user-dropdown-item danger" href="<?php echo BASE_URL; ?>views/auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Sair</span>
                            </a>
                        </div>
                    </div>

                </div>

            <?php else: ?>
                
                <!-- Menu Não Logado -->
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>views/auth/login.php">
                            <i class="fas fa-sign-in-alt me-1"></i> Entrar
                        </a>
                    </li>
                </ul>

            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Overlay das Notificações -->
<div class="notification-overlay" id="notificationOverlay"></div>

<!-- Painel de Notificações -->
<div class="notification-panel" id="notificationPanel">
    
    <!-- Header -->
    <div class="notification-header">
        <h5>
            <i class="fas fa-bell"></i>
            Notificações
            <?php if ($unreadCount > 0): ?>
                <span class="badge bg-danger" id="panelBadge"><?php echo $unreadCount; ?></span>
            <?php endif; ?>
        </h5>
        <button class="notification-close" id="closeNotifications">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Ações -->
    <div class="notification-actions">
        <button id="markAllRead">
            <i class="fas fa-check-double me-1"></i>
            Marcar todas como lidas
        </button>
    </div>

    <!-- Lista de Notificações -->
    <div class="notification-list" id="notificationList">
        
        <?php if (empty($notifications)): ?>
            <div class="notification-empty">
                <i class="fas fa-bell-slash"></i>
                <h6>Nenhuma notificação</h6>
                <p>Você não tem notificações no momento</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <div class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>" 
                     data-id="<?php echo $notif['id']; ?>"
                     data-ticket="<?php echo $notif['ticket_id']; ?>">
                    
                    <div class="notification-icon <?php echo $notif['type']; ?>">
                        <?php
                        $icons = [
                            'assumed' => 'fa-user-check',
                            'dispatched' => 'fa-truck',
                            'resolved' => 'fa-check-circle',
                            'closed' => 'fa-times-circle',
                            'reopened' => 'fa-redo'
                        ];
                        ?>
                        <i class="fas <?php echo $icons[$notif['type']] ?? 'fa-bell'; ?>"></i>
                    </div>
                    
                    <div class="notification-content">
                        <h6>Ticket #<?php echo $notif['ticket_id']; ?> - <?php echo htmlspecialchars($notif['ticket_title']); ?></h6>
                        <p><?php echo htmlspecialchars($notif['message']); ?></p>
                        <div class="notification-meta">
                            <span class="notification-time">
                                <i class="fas fa-clock"></i>
                                <?php echo formatDate($notif['created_at'], true); ?>
                            </span>
                            <?php if (isset($notif['company_name'])): ?>
                                <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($notif['company_name']); ?></span>
                            <?php elseif (isset($notif['user_name']) && $notif['user_name']): ?>
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($notif['user_name']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
    </div>

    <!-- Footer -->
    <div class="notification-footer">
        <a href="<?php echo BASE_URL; ?>views/notifications/all.php">
            Ver todas as notificações
            <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>

</div>

<!-- Container Principal -->
<div class="container-fluid">

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ========================================
// APLICAR TEMA SALVO IMEDIATAMENTE
// ========================================
(function() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
})();

// ========================================
// AGUARDAR CARREGAMENTO DO DOM
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    
    console.log('🚀 Sistema Header Inicializado');
    console.log('📊 Status:', {
        bootstrap: typeof bootstrap !== 'undefined' ? '✅' : '❌',
        jquery: typeof jQuery !== 'undefined' ? '✅' : '❌ (não necessário)',
        tema: document.documentElement.getAttribute('data-theme')
    });

    // ========================================
    // DROPDOWN DO USUÁRIO - 100% JAVASCRIPT PURO
    // ========================================
    const userDropdownBtn = document.getElementById('userDropdownBtn');
    const userDropdownMenu = document.getElementById('userDropdownMenu');
    
    if (userDropdownBtn && userDropdownMenu) {
        console.log('✅ Dropdown do usuário inicializado');
        
        let isDropdownOpen = false;
        
        // Função para abrir dropdown
        function openDropdown() {
            userDropdownMenu.classList.add('show');
            userDropdownBtn.classList.add('active');
            isDropdownOpen = true;
            console.log('📂 Dropdown aberto');
        }
        
        // Função para fechar dropdown
        function closeDropdown() {
            userDropdownMenu.classList.remove('show');
            userDropdownBtn.classList.remove('active');
            isDropdownOpen = false;
            console.log('📁 Dropdown fechado');
        }
        
        // Função para alternar dropdown
        function toggleDropdown(e) {
            e.stopPropagation();
            if (isDropdownOpen) {
                closeDropdown();
            } else {
                openDropdown();
            }
        }
        
        // Click no botão
        userDropdownBtn.addEventListener('click', toggleDropdown);
        
        // Click fora do dropdown fecha
        document.addEventListener('click', function(e) {
            if (isDropdownOpen && 
                !userDropdownBtn.contains(e.target) && 
                !userDropdownMenu.contains(e.target)) {
                closeDropdown();
            }
        });
        
        // ESC fecha dropdown
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isDropdownOpen) {
                closeDropdown();
            }
        });
        
        // Prevenir que cliques dentro do menu o fechem
        userDropdownMenu.addEventListener('click', function(e) {
            // Permitir que links funcionem normalmente
            if (e.target.tagName === 'A' || e.target.closest('a')) {
                return; // Deixa o link funcionar
            }
            e.stopPropagation();
        });
        
    } else {
        console.error('❌ Elementos do dropdown não encontrados');
    }

    // ========================================
    // SISTEMA DE TEMA
    // ========================================
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        console.log('✅ Botão de tema inicializado');
        
        themeToggle.addEventListener('click', function() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            console.log('🎨 Tema alterado para:', newTheme);
        });
    }

    // ========================================
    // SISTEMA DE NOTIFICAÇÕES
    // ========================================
    const notificationBell = document.getElementById('notificationBell');
    const notificationPanel = document.getElementById('notificationPanel');
    const notificationOverlay = document.getElementById('notificationOverlay');
    const closeNotifications = document.getElementById('closeNotifications');
    const markAllRead = document.getElementById('markAllRead');

    if (notificationBell && notificationPanel) {
        console.log('✅ Sistema de notificações inicializado');
    }

    // Abrir/Fechar painel
    function toggleNotificationPanel() {
        const isOpen = notificationPanel.classList.contains('active');
        
        if (!isOpen) {
            // Fechar dropdown do usuário se estiver aberto
            if (userDropdownBtn && userDropdownMenu) {
                userDropdownMenu.classList.remove('show');
                userDropdownBtn.classList.remove('active');
            }
        }
        
        notificationPanel.classList.toggle('active');
        notificationOverlay.classList.toggle('active');
        document.body.style.overflow = notificationPanel.classList.contains('active') ? 'hidden' : '';
        
        console.log('🔔 Painel de notificações:', notificationPanel.classList.contains('active') ? 'Aberto' : 'Fechado');
    }

    if (notificationBell) {
        notificationBell.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleNotificationPanel();
        });
    }

    if (closeNotifications) {
        closeNotifications.addEventListener('click', toggleNotificationPanel);
    }

    if (notificationOverlay) {
        notificationOverlay.addEventListener('click', toggleNotificationPanel);
    }

    // Fechar com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (notificationPanel && notificationPanel.classList.contains('active')) {
                toggleNotificationPanel();
            }
        }
    });

    // Marcar todas como lidas
    if (markAllRead) {
        markAllRead.addEventListener('click', function() {
            const button = this;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processando...';
            
            fetch('<?php echo BASE_URL; ?>ajax/mark_all_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    
                    const badge = document.getElementById('notificationBadge');
                    const panelBadge = document.getElementById('panelBadge');
                    
                    if (badge) badge.remove();
                    if (panelBadge) panelBadge.remove();
                    
                    if (notificationBell) {
                        notificationBell.classList.remove('has-unread');
                    }
                    
                    showToast('Todas as notificações foram marcadas como lidas', 'success');
                    console.log('✅ Notificações marcadas como lidas');
                } else {
                    showToast('Erro ao marcar notificações', 'error');
                    console.error('❌ Erro ao marcar notificações');
                }
                
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-check-double me-1"></i> Marcar todas como lidas';
            })
            .catch(error => {
                console.error('❌ Erro na requisição:', error);
                showToast('Erro ao marcar notificações', 'error');
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-check-double me-1"></i> Marcar todas como lidas';
            });
        });
    }

    // Clicar em uma notificação
    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function() {
            const notifId = this.dataset.id;
            const ticketId = this.dataset.ticket;
            
            console.log('🎯 Notificação clicada:', { notifId, ticketId });
            
            if (this.classList.contains('unread')) {
                fetch('<?php echo BASE_URL; ?>ajax/mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ notification_id: notifId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.classList.remove('unread');
                        updateNotificationCount(data.unread_count);
                        console.log('✅ Notificação marcada como lida');
                    }
                })
                .catch(error => console.error('❌ Erro:', error));
            }
            
            setTimeout(() => {
                window.location.href = '<?php echo BASE_URL; ?>views/tickets/view.php?id=' + ticketId;
            }, 300);
        });
    });

    // Atualizar contador de notificações
    function updateNotificationCount(count) {
        const badge = document.getElementById('notificationBadge');
        const panelBadge = document.getElementById('panelBadge');
        
        if (count === 0) {
            if (badge) badge.remove();
            if (panelBadge) panelBadge.remove();
            if (notificationBell) {
                notificationBell.classList.remove('has-unread');
            }
        } else {
            const displayCount = count > 99 ? '99+' : count;
            
            if (badge) {
                badge.textContent = displayCount;
            } else if (notificationBell) {
                const newBadge = document.createElement('span');
                newBadge.id = 'notificationBadge';
                newBadge.className = 'notification-badge';
                newBadge.textContent = displayCount;
                notificationBell.appendChild(newBadge);
            }
            
            if (panelBadge) {
                panelBadge.textContent = count;
            }
            
            if (notificationBell) {
                notificationBell.classList.add('has-unread');
            }
        }
        
        console.log('🔢 Contador atualizado:', count);
    }

    // Toast notification
    function showToast(message, type = 'info') {
        const colors = {
            success: '#198754',
            error: '#dc3545',
            info: '#0d6efd',
            warning: '#ffc107'
        };
        
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: ${colors[type] || colors.info};
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 9999;
            animation: slideInUp 0.3s ease;
            font-size: 14px;
            font-weight: 500;
            max-width: 350px;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOutDown 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Auto-atualizar notificações a cada 30 segundos
    setInterval(function() {
        fetch('<?php echo BASE_URL; ?>ajax/get_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotificationList(data.notifications, data.unread_count);
                    
                    if (data.new_notifications && data.new_notifications.length > 0) {
                        data.new_notifications.forEach(notif => {
                            showToast(`Nova notificação: ${notif.message}`, 'info');
                        });
                        console.log('📬 Novas notificações recebidas:', data.new_notifications.length);
                    }
                }
            })
            .catch(error => console.error('❌ Erro ao atualizar notificações:', error));
    }, 30000);

    // Atualizar lista de notificações
    function updateNotificationList(notifications, unreadCount) {
        const list = document.getElementById('notificationList');
        
        if (!list) return;
        
        if (notifications.length === 0) {
            list.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <h6>Nenhuma notificação</h6>
                    <p>Você não tem notificações no momento</p>
                </div>
            `;
        } else {
            list.innerHTML = notifications.map(notif => {
                const icons = {
                    'assumed': 'fa-user-check',
                    'dispatched': 'fa-truck',
                    'resolved': 'fa-check-circle',
                    'closed': 'fa-times-circle',
                    'reopened': 'fa-redo'
                };
                
                const companyInfo = notif.company_name ? 
                    `<span><i class="fas fa-building"></i> ${notif.company_name}</span>` : '';
                const userInfo = notif.user_name ? 
                    `<span><i class="fas fa-user"></i> ${notif.user_name}</span>` : '';
                
                return `
                    <div class="notification-item ${notif.is_read ? '' : 'unread'}" 
                         data-id="${notif.id}"
                         data-ticket="${notif.ticket_id}">
                        
                        <div class="notification-icon ${notif.type}">
                            <i class="fas ${icons[notif.type] || 'fa-bell'}"></i>
                        </div>
                        
                        <div class="notification-content">
                            <h6>Ticket #${notif.ticket_id} - ${notif.ticket_title}</h6>
                            <p>${notif.message}</p>
                            <div class="notification-meta">
                                <span class="notification-time">
                                    <i class="fas fa-clock"></i>
                                    ${notif.created_at_formatted || notif.created_at}
                                </span>
                                ${companyInfo}
                                ${userInfo}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            
            // Reativar eventos de clique
            document.querySelectorAll('.notification-item').forEach(item => {
                item.addEventListener('click', function() {
                    const notifId = this.dataset.id;
                    const ticketId = this.dataset.ticket;
                    
                    if (this.classList.contains('unread')) {
                        fetch('<?php echo BASE_URL; ?>ajax/mark_notification_read.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ notification_id: notifId })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.classList.remove('unread');
                                updateNotificationCount(data.unread_count);
                            }
                        })
                        .catch(error => console.error('❌ Erro:', error));
                    }
                    
                    setTimeout(() => {
                        window.location.href = '<?php echo BASE_URL; ?>views/tickets/view.php?id=' + ticketId;
                    }, 300);
                });
            });
        }
        
        updateNotificationCount(unreadCount);
    }

    // Animações CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInUp {
            from {
                transform: translateY(100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutDown {
            from {
                transform: translateY(0);
                opacity: 1;
            }
            to {
                transform: translateY(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);

    console.log('✅ Todos os sistemas do header foram inicializados com sucesso!');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

});
</script>

</body>
</html>