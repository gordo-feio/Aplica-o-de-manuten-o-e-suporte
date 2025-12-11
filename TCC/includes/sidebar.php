<?php
/**
 * Sidebar de Navegação Moderna com Animação + TEMA ESCURO FUNCIONANDO
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 */

// Incluir configurações
if (!defined('SYSTEM_NAME')) {
    require_once __DIR__ . '/../config/config.php';
}

// Verificar tipo de usuário
$isUserType = isUser();
$isCompanyType = isCompany();
$isAdminRole = isAdmin();

// Informações do usuário
$userName = '';
$userEmail = '';
$userInitials = 'U';

if ($isUserType && isset($_SESSION['user_name'])) {
    $userName = $_SESSION['user_name'];
    $userEmail = $_SESSION['user_email'] ?? '';
    $nameParts = explode(' ', $userName);
    $userInitials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
} elseif ($isCompanyType && isset($_SESSION['company_name'])) {
    $userName = $_SESSION['company_name'];
    $userEmail = $_SESSION['company_email'] ?? '';
    $nameParts = explode(' ', $userName);
    $userInitials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
}

// Página atual para destacar menu ativo
$currentPage = basename($_SERVER['PHP_SELF']);
$currentUri = $_SERVER['REQUEST_URI'];

// Função para verificar se menu está ativo
function isMenuActive($page, $uri = '') {
    global $currentPage, $currentUri;
    
    if ($uri && strpos($currentUri, $uri) !== false) {
        return true;
    }
    
    return $currentPage == $page;
}

// Contar tickets
$newTicketsCount = 0;
$highPriorityCount = 0;
$myTicketsCount = 0;

try {
    $pdo = getConnection();
    
    if ($isUserType) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'created'");
        $newTicketsCount = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE priority = 'high' AND status NOT IN ('closed', 'resolved')");
        $highPriorityCount = $stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE company_id = ?");
        $stmt->execute([getCurrentCompanyId()]);
        $myTicketsCount = $stmt->fetchColumn();
    }
} catch (Exception $e) {
    // Silenciar erro
}
?>

<style>
/* ============================================
   VARIÁVEIS DO TEMA PARA SIDEBAR - CORRIGIDO
   ============================================ */
:root {
    /* TEMA CLARO (Gradiente Roxo/Azul) */
    --sidebar-bg: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
    --sidebar-text: #ffffff;
    --sidebar-text-secondary: rgba(255, 255, 255, 0.8);
    --sidebar-hover: rgba(255, 255, 255, 0.15);
    --sidebar-active: rgba(255, 255, 255, 0.25);
    --sidebar-border: rgba(255, 255, 255, 0.2);
    --sidebar-footer-bg: rgba(0, 0, 0, 0.2);
    --sidebar-logo-bg: #ffffff;
    --sidebar-logo-icon: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --sidebar-avatar-bg: #ffffff;
    --sidebar-avatar-text: #667eea;
    --sidebar-badge-bg: rgba(255, 255, 255, 0.3);
    --sidebar-badge-text: #ffffff;
    --sidebar-scrollbar-track: rgba(255, 255, 255, 0.1);
    --sidebar-scrollbar-thumb: rgba(255, 255, 255, 0.3);
}

[data-theme="dark"] {
    /* TEMA ESCURO (Cinza Escuro) */
    --sidebar-bg: linear-gradient(180deg, #1a1d23 0%, #252932 100%);
    --sidebar-text: #e9ecef;
    --sidebar-text-secondary: #adb5bd;
    --sidebar-hover: rgba(102, 126, 234, 0.15);
    --sidebar-active: rgba(102, 126, 234, 0.25);
    --sidebar-border: #343a40;
    --sidebar-footer-bg: rgba(0, 0, 0, 0.3);
    --sidebar-logo-bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --sidebar-logo-icon: #ffffff;
    --sidebar-avatar-bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --sidebar-avatar-text: #ffffff;
    --sidebar-badge-bg: rgba(102, 126, 234, 0.3);
    --sidebar-badge-text: #e9ecef;
    --sidebar-scrollbar-track: rgba(0, 0, 0, 0.2);
    --sidebar-scrollbar-thumb: rgba(102, 126, 234, 0.5);
}

/* ============================================
   ESTILOS DA SIDEBAR COM TEMA
   ============================================ */

/* Botão Menu Hamburguer */
.menu-toggle {
    position: fixed;
    top: 80px;
    left: 20px;
    z-index: 1100;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 12px;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.menu-toggle:hover {
    transform: scale(1.05) rotate(5deg);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.6);
}

.menu-toggle span {
    width: 25px;
    height: 3px;
    background: white;
    border-radius: 2px;
    transition: all 0.3s ease;
}

.menu-toggle.active span:nth-child(1) {
    transform: rotate(45deg) translate(8px, 8px);
}

.menu-toggle.active span:nth-child(2) {
    opacity: 0;
}

.menu-toggle.active span:nth-child(3) {
    transform: rotate(-45deg) translate(8px, -8px);
}

/* Overlay */
.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.4s ease;
    backdrop-filter: blur(2px);
}

.sidebar-overlay.active {
    opacity: 1;
    visibility: visible;
}

/* Sidebar */
.sidebar-modern {
    position: fixed;
    top: 0;
    left: -300px;
    width: 300px;
    height: 100vh;
    background: var(--sidebar-bg);
    z-index: 1050;
    transition: left 0.4s cubic-bezier(0.4, 0.0, 0.2, 1);
    box-shadow: 4px 0 15px rgba(0, 0, 0, 0.2);
    overflow-y: auto;
    overflow-x: hidden;
}

.sidebar-modern.active {
    left: 0;
}

/* Scrollbar customizada */
.sidebar-modern::-webkit-scrollbar {
    width: 6px;
}

.sidebar-modern::-webkit-scrollbar-track {
    background: var(--sidebar-scrollbar-track);
}

.sidebar-modern::-webkit-scrollbar-thumb {
    background: var(--sidebar-scrollbar-thumb);
    border-radius: 3px;
}

.sidebar-modern::-webkit-scrollbar-thumb:hover {
    background: rgba(102, 126, 234, 0.7);
}

/* Header da Sidebar */
.sidebar-header {
    padding: 30px 20px;
    text-align: center;
    border-bottom: 1px solid var(--sidebar-border);
}

.sidebar-header .logo-icon {
    width: 60px;
    height: 60px;
    background: var(--sidebar-logo-bg);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

/* Ícone do logo - TEMA CLARO */
[data-theme="light"] .sidebar-header .logo-icon i {
    font-size: 28px;
    background: var(--sidebar-logo-icon);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Ícone do logo - TEMA ESCURO */
[data-theme="dark"] .sidebar-header .logo-icon i {
    font-size: 28px;
    color: var(--sidebar-logo-icon);
}

.sidebar-header h3 {
    color: var(--sidebar-text);
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 5px;
    transition: color 0.3s ease;
}

.sidebar-header p {
    color: var(--sidebar-text-secondary);
    font-size: 13px;
    margin: 0;
    transition: color 0.3s ease;
}

/* Menu da Sidebar */
.sidebar-menu {
    padding: 20px 0;
}

.menu-section {
    margin-bottom: 25px;
}

.menu-section-title {
    padding: 10px 20px;
    color: var(--sidebar-text-secondary);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    margin-bottom: 5px;
    transition: color 0.3s ease;
}

.menu-item {
    display: flex;
    align-items: center;
    padding: 14px 20px;
    color: var(--sidebar-text);
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.menu-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 4px;
    height: 100%;
    background: currentColor;
    transform: translateX(-100%);
    transition: transform 0.3s ease;
}

.menu-item:hover {
    background: var(--sidebar-hover);
    padding-left: 25px;
    color: var(--sidebar-text);
}

.menu-item:hover::before {
    transform: translateX(0);
}

.menu-item.active {
    background: var(--sidebar-active);
    font-weight: 600;
}

.menu-item.active::before {
    transform: translateX(0);
}

.menu-item i {
    width: 24px;
    font-size: 18px;
    margin-right: 15px;
    color: inherit;
}

.menu-item span {
    font-size: 15px;
    font-weight: 500;
    flex: 1;
    color: inherit;
}

.menu-item .badge {
    margin-left: auto;
    background: var(--sidebar-badge-bg);
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    color: var(--sidebar-badge-text);
    transition: all 0.3s ease;
}

.menu-item.danger {
    color: #ff6b6b;
}

[data-theme="dark"] .menu-item.danger {
    color: #ff8787;
}

/* Footer da Sidebar */
.sidebar-footer {
    position: sticky;
    bottom: 0;
    left: 0;
    width: 100%;
    padding: 20px;
    background: var(--sidebar-footer-bg);
    border-top: 1px solid var(--sidebar-border);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.user-info {
    display: flex;
    align-items: center;
    color: var(--sidebar-text);
}

.user-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: var(--sidebar-avatar-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    font-size: 16px;
    color: var(--sidebar-avatar-text);
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.user-details h6 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: var(--sidebar-text);
    transition: color 0.3s ease;
}

.user-details p {
    margin: 0;
    font-size: 12px;
    color: var(--sidebar-text-secondary);
    transition: color 0.3s ease;
}

/* Animação de entrada dos itens do menu */
.sidebar-modern.active .menu-item {
    animation: slideInLeft 0.3s ease forwards;
    opacity: 0;
}

.sidebar-modern.active .menu-item:nth-child(1) { animation-delay: 0.1s; }
.sidebar-modern.active .menu-item:nth-child(2) { animation-delay: 0.15s; }
.sidebar-modern.active .menu-item:nth-child(3) { animation-delay: 0.2s; }
.sidebar-modern.active .menu-item:nth-child(4) { animation-delay: 0.25s; }
.sidebar-modern.active .menu-item:nth-child(5) { animation-delay: 0.3s; }
.sidebar-modern.active .menu-item:nth-child(6) { animation-delay: 0.35s; }
.sidebar-modern.active .menu-item:nth-child(7) { animation-delay: 0.4s; }
.sidebar-modern.active .menu-item:nth-child(8) { animation-delay: 0.45s; }
.sidebar-modern.active .menu-item:nth-child(9) { animation-delay: 0.5s; }
.sidebar-modern.active .menu-item:nth-child(10) { animation-delay: 0.55s; }

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Responsivo */
@media (max-width: 768px) {
    .menu-toggle {
        width: 45px;
        height: 45px;
        top: 75px;
        left: 15px;
    }

    .sidebar-modern {
        width: 280px;
        left: -280px;
    }
}
</style>

<!-- Botão Menu Hamburguer -->
<button class="menu-toggle" id="menuToggle">
    <span></span>
    <span></span>
    <span></span>
</button>

<!-- Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar Moderna -->
<aside class="sidebar-modern" id="sidebarModern">
    
    <!-- Header -->
    <div class="sidebar-header">
        <div class="logo-icon">
            <i class="fas fa-headset"></i>
        </div>
        <h3><?php echo SYSTEM_SHORT_NAME; ?></h3>
        <p>Suporte & Manutenção</p>
    </div>

    <!-- Menu -->
    <nav class="sidebar-menu">
        
        <!-- Dashboard -->
        <div class="menu-section">
            <div class="menu-section-title">Principal</div>
            <a href="<?php echo BASE_URL; ?>views/dashboard/index.php" 
               class="menu-item <?php echo isMenuActive('index.php', 'dashboard') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </div>

        <?php if ($isUserType): ?>
            <!-- Menu para Funcionários -->
            
            <div class="menu-section">
                <div class="menu-section-title">Atendimento</div>
                
                <a href="<?php echo BASE_URL; ?>views/tickets/index.php" 
                   class="menu-item <?php echo isMenuActive('index.php', 'tickets') ? 'active' : ''; ?>">
                    <i class="fas fa-ticket-alt"></i>
                    <span>Todos os Tickets</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>views/tickets/index.php?status=created" 
                   class="menu-item">
                    <i class="fas fa-inbox"></i>
                    <span>Novos</span>
                    <?php if ($newTicketsCount > 0): ?>
                        <span class="badge"><?php echo $newTicketsCount; ?></span>
                    <?php endif; ?>
                </a>
                
                <a href="<?php echo BASE_URL; ?>views/tickets/index.php?priority=high" 
                   class="menu-item">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Alta Prioridade</span>
                    <?php if ($highPriorityCount > 0): ?>
                        <span class="badge"><?php echo $highPriorityCount; ?></span>
                    <?php endif; ?>
                </a>
                
                <a href="<?php echo BASE_URL; ?>views/tickets/index.php?status=in_progress" 
                   class="menu-item">
                    <i class="fas fa-spinner"></i>
                    <span>Em Andamento</span>
                </a>
            </div>

            <?php if ($isAdminRole): ?>
                <!-- Menu Administrativo -->
                <div class="menu-section">
                    <div class="menu-section-title">Gerenciamento</div>
                    
                    <a href="<?php echo BASE_URL; ?>views/users/index.php" 
                       class="menu-item <?php echo isMenuActive('', 'users') ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Usuários</span>
                    </a>
                    
                    <a href="<?php echo BASE_URL; ?>views/companies/index.php" 
                       class="menu-item <?php echo isMenuActive('', 'companies') ? 'active' : ''; ?>">
                        <i class="fas fa-building"></i>
                        <span>Empresas</span>
                    </a>
                    
                    <a href="<?php echo BASE_URL; ?>views/admin/reports.php" 
                       class="menu-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Relatórios</span>
                    </a>
                    
                    <a href="<?php echo BASE_URL; ?>views/admin/settings.php" 
                       class="menu-item">
                        <i class="fas fa-cog"></i>
                        <span>Configurações</span>
                    </a>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Menu para Empresas -->
            
            <div class="menu-section">
                <div class="menu-section-title">Meus Tickets</div>
                
                <a href="<?php echo BASE_URL; ?>views/tickets/create.php" 
                   class="menu-item <?php echo isMenuActive('create.php') ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle"></i>
                    <span>Novo Ticket</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>views/tickets/my-tickets.php" 
                   class="menu-item <?php echo isMenuActive('my-tickets.php') ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>
                    <span>Todos</span>
                    <?php if ($myTicketsCount > 0): ?>
                        <span class="badge"><?php echo $myTicketsCount; ?></span>
                    <?php endif; ?>
                </a>
                
                <a href="<?php echo BASE_URL; ?>views/tickets/my-tickets.php?status=created" 
                   class="menu-item">
                    <i class="fas fa-clock"></i>
                    <span>Aguardando</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>views/tickets/my-tickets.php?status=in_progress" 
                   class="menu-item">
                    <i class="fas fa-tools"></i>
                    <span>Em Andamento</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>views/tickets/my-tickets.php?status=resolved" 
                   class="menu-item">
                    <i class="fas fa-check-circle"></i>
                    <span>Resolvidos</span>
                </a>
            </div>

        <?php endif; ?>

        <!-- Seção Comum -->
        <div class="menu-section">
            <div class="menu-section-title">Conta</div>
            
            <a href="<?php echo BASE_URL; ?>views/users/profile.php" 
               class="menu-item <?php echo isMenuActive('profile.php') ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>
                <span>Meu Perfil</span>
            </a>
            
            <a href="<?php echo BASE_URL; ?>views/auth/logout.php" 
               class="menu-item danger">
                <i class="fas fa-sign-out-alt"></i>
                <span>Sair</span>
            </a>
        </div>

    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?php echo $userInitials; ?></div>
            <div class="user-details">
                <h6><?php echo htmlspecialchars(strlen($userName) > 18 ? substr($userName, 0, 18) . '...' : $userName); ?></h6>
                <p><?php echo $isUserType ? getRoleLabel($_SESSION['user_role'] ?? '') : 'Empresa'; ?></p>
            </div>
        </div>
    </div>

</aside>

<script>
// ============================================
// JAVASCRIPT DA SIDEBAR MODERNA
// ============================================

(function() {
    // Elementos
    const menuToggle = document.getElementById('menuToggle');
    const sidebarModern = document.getElementById('sidebarModern');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (!menuToggle || !sidebarModern || !sidebarOverlay) {
        console.warn('Elementos da sidebar não encontrados');
        return;
    }

    // Função para abrir/fechar sidebar
    function toggleSidebar() {
        menuToggle.classList.toggle('active');
        sidebarModern.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
        
        // Prevenir scroll do body quando sidebar está aberta
        if (sidebarModern.classList.contains('active')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }

    // Event Listeners
    menuToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleSidebar();
    });

    sidebarOverlay.addEventListener('click', toggleSidebar);

    // Fechar sidebar com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebarModern.classList.contains('active')) {
            toggleSidebar();
        }
    });

    // Prevenir propagação de cliques dentro da sidebar
    sidebarModern.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Fechar sidebar ao clicar em um link (opcional, para mobile)
    const menuItems = sidebarModern.querySelectorAll('.menu-item');
    menuItems.forEach(function(item) {
        item.addEventListener('click', function() {
            // Em mobile, fechar sidebar após clicar
            if (window.innerWidth < 992) {
                setTimeout(toggleSidebar, 300);
            }
        });
    });

    // Adicionar efeito de ripple nos itens do menu
    menuItems.forEach(function(item) {
        item.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            ripple.style.cssText = `
                position: absolute;
                left: ${x}px;
                top: ${y}px;
                width: 0;
                height: 0;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.5);
                transform: translate(-50%, -50%);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
            `;
            
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });

    // Adicionar animação de ripple ao CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                width: 300px;
                height: 300px;
                opacity: 0;
            }
        }
        
        .menu-item {
            position: relative;
            overflow: hidden;
        }
    `;
    document.head.appendChild(style);

   

})();
</script>