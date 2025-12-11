<?php
/**
 * Dashboard Empresa - PROFISSIONAL COM TEMA CLARO/ESCURO
 * Visão limpa e moderna dos tickets da empresa cliente
 */

// Define ROOT_PATH se não estiver definido
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(__DIR__)) . '/');
}

require_once ROOT_PATH . 'config/config.php';
require_once ROOT_PATH . 'config/database.php';
require_once ROOT_PATH . 'config/paths.php';
require_once ROOT_PATH . 'config/constants.php';
require_once ROOT_PATH . 'classes/User.php';
require_once ROOT_PATH . 'classes/Database.php';
require_once ROOT_PATH . 'includes/functions.php';

$pageTitle = 'Dashboard - ' . SYSTEM_NAME;
$pdo = getConnection();
$companyId = $_SESSION['company_id'];

// Estatísticas da empresa - APENAS SEUS TICKETS
$stats = [
    'total_tickets' => 0,
    'open_tickets' => 0,
    'in_progress' => 0,
    'resolved' => 0,
];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE company_id = ?");
$stmt->execute([$companyId]);
$stats['total_tickets'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE company_id = ? AND status NOT IN ('closed', 'resolved')");
$stmt->execute([$companyId]);
$stats['open_tickets'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE company_id = ? AND status IN ('assumed', 'dispatched', 'in_progress')");
$stmt->execute([$companyId]);
$stats['in_progress'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE company_id = ? AND status IN ('resolved', 'closed')");
$stmt->execute([$companyId]);
$stats['resolved'] = $stmt->fetchColumn();

// Tickets recentes da empresa - APENAS SEUS TICKETS
$stmt = $pdo->prepare("
    SELECT t.*, u.name as assigned_user_name
    FROM tickets t
    LEFT JOIN users u ON t.assigned_user_id = u.id
    WHERE t.company_id = ?
    ORDER BY t.created_at DESC
    LIMIT 10
");
$stmt->execute([$companyId]);
$recentTickets = $stmt->fetchAll();

// Distribuição por categoria - APENAS SEUS TICKETS
$stmt = $pdo->prepare("
    SELECT category, COUNT(*) as total
    FROM tickets
    WHERE company_id = ?
    GROUP BY category
    ORDER BY total DESC
");
$stmt->execute([$companyId]);
$categoryData = $stmt->fetchAll();

// Informações da empresa
$stmt = $pdo->prepare("SELECT name, email, phone FROM companies WHERE id = ?");
$stmt->execute([$companyId]);
$companyInfo = $stmt->fetch();

// Incluir header (apenas HTML/estrutura)
include ROOT_PATH . 'includes/header.php';
?>

<style>
/* ================================================
   SISTEMA DE TEMA CLARO/ESCURO
   ================================================ */
:root {
    --bg-primary: #ffffff;
    --bg-secondary: #f8f9fa;
    --text-primary: #212529;
    --text-secondary: #6c757d;
    --border-color: #dee2e6;
    --card-shadow: 0 2px 8px rgba(0,0,0,0.08);
    --gradient-start: #667eea;
    --gradient-end: #764ba2;
}

[data-theme="dark"] {
    --bg-primary: #1a1d23;
    --bg-secondary: #252932;
    --text-primary: #e9ecef;
    --text-secondary: #adb5bd;
    --border-color: #343a40;
    --card-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

body {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    transition: background-color 0.3s ease, color 0.3s ease;
}

/* ================================================
   HEADER CLEAN E PROFISSIONAL
   ================================================ */
.dashboard-hero {
    background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
    padding: 3rem 0 4rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 24px 24px;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.2);
}

.hero-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
}

.company-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.company-avatar {
    width: 70px;
    height: 70px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 700;
    color: white;
    border: 3px solid rgba(255, 255, 255, 0.3);
}

.company-title h1 {
    font-size: 32px;
    font-weight: 700;
    color: white;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.company-subtitle {
    color: rgba(255, 255, 255, 0.9);
    font-size: 16px;
    margin: 0.5rem 0 0 0;
}

.action-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.btn-hero {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.2);
    color: white;
    padding: 0.875rem 1.75rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.625rem;
}

.btn-hero:hover {
    background: white;
    color: var(--gradient-start);
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.2);
}

.btn-hero.primary {
    background: white;
    color: var(--gradient-start);
}

.btn-hero i {
    font-size: 18px;
}

/* ================================================
   CARDS MODERNOS - CORRIGIDO
   ================================================ */
.stat-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    box-shadow: var(--card-shadow);
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

.stat-card .stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 1rem;
}

.stat-card .stat-value {
    font-size: 32px;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.5rem;
}

.stat-card .stat-label {
    font-size: 14px;
    color: var(--text-secondary);
    font-weight: 500;
}

/* ================================================
   QUICK ACTIONS
   ================================================ */
.quick-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: var(--card-shadow);
    text-decoration: none;
    color: var(--text-primary);
    display: block;
    height: 100%;
}

.quick-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    color: var(--text-primary);
}

.quick-card i {
    font-size: 48px;
    margin-bottom: 1rem;
}

.quick-card h5 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.quick-card p {
    font-size: 14px;
    color: var(--text-secondary);
    margin-bottom: 1rem;
}

/* ================================================
   TABLE MODERNA
   ================================================ */
.modern-table {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--card-shadow);
}

.modern-table .table {
    margin: 0;
    color: var(--text-primary);
}

.modern-table .table thead {
    background: var(--bg-secondary);
    border-bottom: 2px solid var(--border-color);
}

.modern-table .table tbody tr {
    border-bottom: 1px solid var(--border-color);
    transition: background-color 0.2s ease;
}

.modern-table .table tbody tr:hover {
    background: var(--bg-secondary);
}

/* ================================================
   RESPONSIVO
   ================================================ */
@media (max-width: 768px) {
    .dashboard-hero {
        padding: 2rem 0 3rem 0;
    }
    
    .company-header {
        flex-direction: column;
        text-align: center;
    }
    
    .company-title h1 {
        font-size: 24px;
    }
    
    .action-buttons {
        justify-content: center;
    }
    
    .btn-hero {
        padding: 0.75rem 1.5rem;
        font-size: 14px;
    }
}
</style>

<div class="container-fluid">
    
    <!-- HERO SECTION -->
    <div class="dashboard-hero">
        <div class="hero-content">
            
            <div class="company-header">
                <div class="company-avatar">
                    <?php 
                    $initials = '';
                    $nameParts = explode(' ', $companyInfo['name']);
                    $initials = strtoupper(substr($nameParts[0], 0, 1));
                    if (isset($nameParts[1])) {
                        $initials .= strtoupper(substr($nameParts[1], 0, 1));
                    }
                    echo $initials;
                    ?>
                </div>
                <div class="company-title">
                    <h1><?php echo htmlspecialchars($companyInfo['name']); ?></h1>
                    <p class="company-subtitle">Bem-vindo ao seu painel de suporte</p>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="<?php echo BASE_URL; ?>views/tickets/create.php" class="btn-hero primary">
                    <i class="fas fa-plus-circle"></i>
                    Criar Novo Ticket
                </a>
                <a href="<?php echo BASE_URL; ?>views/tickets/my-tickets.php" class="btn-hero">
                    <i class="fas fa-list"></i>
                    Ver Todos os Tickets
                </a>
                <a href="<?php echo BASE_URL; ?>views/tickets/my-tickets.php?status=created" class="btn-hero">
                    <i class="fas fa-clock"></i>
                    Aguardando Atendimento
                </a>
            </div>
            
        </div>
    </div>
    
    <div class="container" style="max-width: 1200px;">
        
        <!-- ESTATÍSTICAS -->
        <div class="row g-4 mb-4">
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(13, 110, 253, 0.1); color: #0d6efd;">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div class="stat-value" style="color: #0d6efd;"><?php echo $stats['total_tickets']; ?></div>
                    <div class="stat-label">Total de Tickets</div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <div class="stat-value" style="color: #ffc107;"><?php echo $stats['open_tickets']; ?></div>
                    <div class="stat-label">Tickets Abertos</div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(13, 202, 240, 0.1); color: #0dcaf0;">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="stat-value" style="color: #0dcaf0;"><?php echo $stats['in_progress']; ?></div>
                    <div class="stat-label">Em Progresso</div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(25, 135, 84, 0.1); color: #198754;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value" style="color: #198754;"><?php echo $stats['resolved']; ?></div>
                    <div class="stat-label">Resolvidos</div>
                </div>
            </div>
            
        </div>
        
        <!-- AÇÕES RÁPIDAS -->
        <div class="row g-4 mb-4">
            
            <div class="col-md-4">
                <a href="<?php echo BASE_URL; ?>views/tickets/my-tickets.php" class="quick-card">
                    <i class="fas fa-list-ul text-primary"></i>
                    <h5>Meus Tickets</h5>
                    <p>Visualize todos os seus chamados</p>
                    <span class="badge bg-primary" style="font-size: 14px;"><?php echo $stats['total_tickets']; ?> tickets</span>
                </a>
            </div>
            
            <div class="col-md-4">
                <a href="<?php echo BASE_URL; ?>views/tickets/create.php" class="quick-card">
                    <i class="fas fa-plus-circle text-success"></i>
                    <h5>Novo Ticket</h5>
                    <p>Abra um novo chamado de suporte</p>
                    <span class="badge bg-success" style="font-size: 14px;">Criar agora</span>
                </a>
            </div>
            
            <div class="col-md-4">
                <a href="<?php echo BASE_URL; ?>views/tickets/my-tickets.php?status=open" class="quick-card">
                    <i class="fas fa-folder-open text-warning"></i>
                    <h5>Tickets Abertos</h5>
                    <p>Acompanhe tickets em andamento</p>
                    <span class="badge bg-warning" style="font-size: 14px;"><?php echo $stats['open_tickets']; ?> abertos</span>
                </a>
            </div>
            
        </div>
        
        <!-- DISTRIBUIÇÃO POR CATEGORIA -->
        <?php if (!empty($categoryData)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="stat-card">
                    <h5 class="mb-4">
                        <i class="fas fa-chart-pie text-primary me-2"></i>
                        Distribuição por Categoria
                    </h5>
                    <?php foreach ($categoryData as $cat): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span style="color: var(--text-primary);">
                                <i class="fas <?php echo getCategoryIcon($cat['category']); ?> me-2"></i>
                                <?php echo getCategoryLabel($cat['category']); ?>
                            </span>
                            <strong style="color: var(--text-primary);"><?php echo $cat['total']; ?></strong>
                        </div>
                        <div class="progress" style="height: 8px; background: var(--bg-secondary);">
                            <div class="progress-bar" 
                                 style="width: <?php echo ($cat['total'] / $stats['total_tickets']) * 100; ?>%; 
                                        background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- TICKETS RECENTES -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="modern-table">
                    <div class="p-3 d-flex justify-content-between align-items-center" style="background: var(--bg-secondary);">
                        <h5 class="mb-0">
                            <i class="fas fa-history text-primary me-2"></i>
                            Seus Tickets Recentes
                        </h5>
                        <a href="<?php echo BASE_URL; ?>views/tickets/my-tickets.php" 
                           class="btn btn-sm btn-primary">
                            Ver Todos
                        </a>
                    </div>
                    
                    <?php if (empty($recentTickets)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-4x mb-3 opacity-25" style="color: var(--text-secondary);"></i>
                            <h5 style="color: var(--text-secondary);">Nenhum ticket encontrado</h5>
                            <a href="<?php echo BASE_URL; ?>views/tickets/create.php" class="btn btn-primary mt-3">
                                <i class="fas fa-plus me-2"></i>
                                Criar Primeiro Ticket
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">ID</th>
                                        <th>Título</th>
                                        <th style="width: 130px;">Categoria</th>
                                        <th style="width: 120px;">Prioridade</th>
                                        <th style="width: 130px;">Status</th>
                                        <th style="width: 180px;">Atendente</th>
                                        <th style="width: 140px;">Data</th>
                                        <th class="text-center" style="width: 100px;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTickets as $ticket): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary">#<?php echo $ticket['id']; ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-truncate" style="max-width: 250px;" 
                                                 title="<?php echo htmlspecialchars($ticket['title']); ?>">
                                                <?php echo htmlspecialchars($ticket['title']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <i class="fas <?php echo getCategoryIcon($ticket['category']); ?> me-1"></i>
                                                <?php echo getCategoryLabel($ticket['category']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getPriorityColor($ticket['priority']); ?>">
                                                <?php echo getPriorityLabel($ticket['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?>">
                                                <?php echo getStatusLabel($ticket['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($ticket['assigned_user_name']): ?>
                                                <small style="color: var(--text-secondary);">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($ticket['assigned_user_name']); ?>
                                                </small>
                                            <?php else: ?>
                                                <small style="color: var(--text-secondary);">
                                                    <i class="fas fa-clock me-1"></i>
                                                    Aguardando
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small style="color: var(--text-secondary);">
                                                <?php echo formatDate($ticket['created_at'], true); ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?php echo BASE_URL; ?>views/tickets/view.php?id=<?php echo $ticket['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    </div>
    
</div>

<?php include ROOT_PATH . 'includes/footer.php'; ?>