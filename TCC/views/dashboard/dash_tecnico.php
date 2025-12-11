<?php
/**
 * Dashboard Técnico - COMPLETO COM SISTEMA DE OS
 * Versão atualizada com todas as funcionalidades de Ordem de Serviço
 */

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

$pageTitle = 'Dashboard Técnico - ' . SYSTEM_NAME;
$pdo = getConnection();
$userId = $_SESSION['user_id'];

// =====================================================
// ESTATÍSTICAS DO TÉCNICO
// =====================================================

$stats = [
    'available_os' => 0,
    'my_os' => 0,
    'completed_today' => 0,
    'urgent_os' => 0,
    'as_primary' => 0,
    'as_support' => 0,
    'overdue_os' => 0,
];

// OS disponíveis para aceitar
$stmt = $pdo->query("SELECT COUNT(*) FROM work_orders WHERE status = 'available'");
$stats['available_os'] = $stmt->fetchColumn();

// Minhas OS em andamento
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT wo.id) 
    FROM work_orders wo
    INNER JOIN work_order_technicians wot ON wo.id = wot.work_order_id
    WHERE wot.technician_id = ?
    AND wo.status = 'in_progress'
");
$stmt->execute([$userId]);
$stats['my_os'] = $stmt->fetchColumn();

// OS finalizadas hoje por mim
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT wo.id) 
    FROM work_orders wo
    INNER JOIN work_order_technicians wot ON wo.id = wot.work_order_id
    WHERE wot.technician_id = ?
    AND wot.status = 'completed'
    AND DATE(wot.completed_at) = CURDATE()
");
$stmt->execute([$userId]);
$stats['completed_today'] = $stmt->fetchColumn();

// OS urgentes disponíveis
$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM work_orders wo
    INNER JOIN tickets t ON wo.ticket_id = t.id
    WHERE wo.status = 'available'
    AND t.priority = 'high'
");
$stats['urgent_os'] = $stmt->fetchColumn();

// Como técnico principal
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM work_order_technicians wot
    INNER JOIN work_orders wo ON wot.work_order_id = wo.id
    WHERE wot.technician_id = ?
    AND wot.role = 'primary'
    AND wo.status = 'in_progress'
");
$stmt->execute([$userId]);
$stats['as_primary'] = $stmt->fetchColumn();

// Como suporte
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM work_order_technicians wot
    INNER JOIN work_orders wo ON wot.work_order_id = wo.id
    WHERE wot.technician_id = ?
    AND wot.role = 'support'
    AND wo.status = 'in_progress'
");
$stmt->execute([$userId]);
$stats['as_support'] = $stmt->fetchColumn();

// OS atrasadas
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT wo.id) 
    FROM work_orders wo
    INNER JOIN work_order_technicians wot ON wo.id = wot.work_order_id
    WHERE wot.technician_id = ?
    AND wo.status = 'in_progress'
    AND wo.deadline < NOW()
");
$stmt->execute([$userId]);
$stats['overdue_os'] = $stmt->fetchColumn();

// =====================================================
// OS DISPONÍVEIS PARA ACEITAR
// =====================================================

$stmt = $pdo->query("
    SELECT 
        wo.*,
        t.title,
        t.description,
        t.priority,
        c.name as company_name,
        c.address as company_address,
        c.phone as company_phone
    FROM work_orders wo
    INNER JOIN tickets t ON wo.ticket_id = t.id
    INNER JOIN companies c ON t.company_id = c.id
    WHERE wo.status = 'available'
    ORDER BY 
        CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
        wo.created_at ASC
    LIMIT 6
");
$availableOS = $stmt->fetchAll();

// =====================================================
// MINHAS OS EM ANDAMENTO
// =====================================================

$stmt = $pdo->prepare("
    SELECT 
        wo.*,
        t.title,
        t.description,
        t.priority,
        c.name as company_name,
        c.address as company_address,
        c.phone as company_phone,
        wot.role as my_role,
        wot.status as my_status,
        wot.notes as my_notes,
        (SELECT COUNT(*) FROM work_order_technicians wot2 
         WHERE wot2.work_order_id = wo.id) as team_size
    FROM work_orders wo
    INNER JOIN work_order_technicians wot ON wo.id = wot.work_order_id
    INNER JOIN tickets t ON wo.ticket_id = t.id
    INNER JOIN companies c ON t.company_id = c.id
    WHERE wot.technician_id = ?
    AND wo.status = 'in_progress'
    ORDER BY 
        CASE WHEN wo.deadline < NOW() THEN 0 ELSE 1 END,
        CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
        wo.created_at ASC
    LIMIT 10
");
$stmt->execute([$userId]);
$myOS = $stmt->fetchAll();

// =====================================================
// PERFORMANCE DO MÊS
// =====================================================

$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT wo.id) as total,
        SUM(CASE WHEN wo.status = 'completed' THEN 1 ELSE 0 END) as completed,
        AVG(CASE WHEN wo.completed_at IS NOT NULL 
            THEN TIMESTAMPDIFF(HOUR, wo.created_at, wo.completed_at) END) as avg_hours,
        SUM(CASE WHEN wo.deadline >= wo.completed_at THEN 1 ELSE 0 END) as on_time
    FROM work_orders wo
    INNER JOIN work_order_technicians wot ON wo.id = wot.work_order_id
    WHERE wot.technician_id = ?
    AND MONTH(wo.created_at) = MONTH(CURRENT_DATE())
    AND YEAR(wo.created_at) = YEAR(CURRENT_DATE())
");
$stmt->execute([$userId]);
$performance = $stmt->fetch();

// Informações do usuário
$stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userInfo = $stmt->fetch();

include ROOT_PATH . 'includes/header.php';
?>

<style>
/* Tema Claro/Escuro */
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
}

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

.tech-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.tech-avatar {
    width: 70px;
    height: 70px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: white;
    border: 3px solid rgba(255, 255, 255, 0.3);
}

.tech-title h1 {
    font-size: 32px;
    font-weight: 700;
    color: white;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tech-subtitle {
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
}

.btn-hero.primary {
    background: white;
    color: var(--gradient-start);
}

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

.os-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-left: 4px solid transparent;
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    box-shadow: var(--card-shadow);
    margin-bottom: 1rem;
    height: 100%;
}

.os-card:hover {
    transform: translateX(5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

.os-card.high { border-left-color: #dc3545; }
.os-card.medium { border-left-color: #ffc107; }
.os-card.low { border-left-color: #198754; }

.os-card.overdue {
    border: 2px solid #dc3545;
    background: linear-gradient(135deg, rgba(220, 53, 69, 0.05) 0%, var(--bg-primary) 100%);
}

.location-info {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    padding: 1rem;
    border-radius: 8px;
    margin-top: 1rem;
}

.section-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: var(--card-shadow);
    overflow: hidden;
    margin-bottom: 2rem;
}

.section-header {
    padding: 1.5rem;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.section-body {
    padding: 1.5rem;
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
}

.empty-state i {
    font-size: 48px;
    opacity: 0.3;
    margin-bottom: 1rem;
    color: var(--text-secondary);
}

.performance-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: var(--card-shadow);
    overflow: hidden;
}

.performance-header {
    background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
    padding: 1.5rem;
    color: white;
}

.performance-body {
    padding: 2rem;
}

.performance-stat {
    text-align: center;
    padding: 1rem;
}

.performance-stat h3 {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.badge-overdue {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

@media (max-width: 768px) {
    .dashboard-hero {
        padding: 2rem 0 3rem 0;
    }
    
    .tech-header {
        flex-direction: column;
        text-align: center;
    }
    
    .tech-title h1 {
        font-size: 24px;
    }
    
    .action-buttons {
        justify-content: center;
    }
}
</style>

<div class="container-fluid">
    
    <!-- HERO SECTION -->
    <div class="dashboard-hero">
        <div class="hero-content">
            
            <div class="tech-header">
                <div class="tech-avatar">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="tech-title">
                    <h1><?php echo htmlspecialchars($userInfo['name']); ?></h1>
                    <p class="tech-subtitle">
                        <i class="fas fa-wrench me-2"></i>
                        Dashboard Técnico - Campo e Atendimento
                    </p>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="<?php echo BASE_URL; ?>views/work_orders/available.php" class="btn-hero primary">
                    <i class="fas fa-clipboard-list"></i>
                    OS Disponíveis (<?php echo $stats['available_os']; ?>)
                </a>
                <a href="<?php echo BASE_URL; ?>views/work_orders/my_orders.php" class="btn-hero">
                    <i class="fas fa-tasks"></i>
                    Minhas OS (<?php echo $stats['my_os']; ?>)
                </a>
                <a href="<?php echo BASE_URL; ?>views/tickets/index.php?assigned=me" class="btn-hero">
                    <i class="fas fa-list"></i>
                    Meus Tickets
                </a>
            </div>
            
        </div>
    </div>
    
    <div class="container" style="max-width: 1200px;">
        
        <!-- ESTATÍSTICAS PRINCIPAIS -->
        <div class="row g-4 mb-4">
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(13, 110, 253, 0.1); color: #0d6efd;">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-value" style="color: #0d6efd;"><?php echo $stats['available_os']; ?></div>
                    <div class="stat-label">OS Disponíveis</div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(13, 202, 240, 0.1); color: #0dcaf0;">
                        <i class="fas fa-cog fa-spin"></i>
                    </div>
                    <div class="stat-value" style="color: #0dcaf0;"><?php echo $stats['my_os']; ?></div>
                    <div class="stat-label">Minhas OS Ativas</div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-value" style="color: #dc3545;"><?php echo $stats['urgent_os']; ?></div>
                    <div class="stat-label">OS Urgentes</div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(25, 135, 84, 0.1); color: #198754;">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div class="stat-value" style="color: #198754;"><?php echo $stats['completed_today']; ?></div>
                    <div class="stat-label">Finalizadas Hoje</div>
                </div>
            </div>
            
        </div>

        <!-- ESTATÍSTICAS SECUNDÁRIAS -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-value" style="color: #ffc107;"><?php echo $stats['as_primary']; ?></div>
                    <div class="stat-label">Como Principal</div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(13, 202, 240, 0.1); color: #0dcaf0;">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="stat-value" style="color: #0dcaf0;"><?php echo $stats['as_support']; ?></div>
                    <div class="stat-label">Como Suporte</div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value" style="color: #dc3545;"><?php echo $stats['overdue_os']; ?></div>
                    <div class="stat-label">OS Atrasadas</div>
                </div>
            </div>
        </div>
        
        <!-- PERFORMANCE DO MÊS -->
        <?php if ($performance && $performance['total'] > 0): ?>
        <div class="performance-card mb-4">
            <div class="performance-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Sua Performance Este Mês
                </h5>
            </div>
            <div class="performance-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="performance-stat">
                            <h3 style="color: #0d6efd;"><?php echo $performance['total']; ?></h3>
                            <small class="text-muted">Total de OS</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="performance-stat">
                            <h3 style="color: #198754;"><?php echo $performance['completed']; ?></h3>
                            <small class="text-muted">Concluídas</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="performance-stat">
                            <h3 style="color: #0dcaf0;">
                                <?php echo $performance['avg_hours'] ? round($performance['avg_hours'], 1) : '0'; ?>h
                            </h3>
                            <small class="text-muted">Tempo Médio</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="performance-stat">
                            <h3 style="color: #ffc107;">
                                <?php 
                                $onTimePercent = $performance['completed'] > 0 ? 
                                    round(($performance['on_time'] / $performance['completed']) * 100) : 0;
                                echo $onTimePercent;
                                ?>%
                            </h3>
                            <small class="text-muted">No Prazo</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ALERTA DE OS ATRASADAS -->
        <?php if ($stats['overdue_os'] > 0): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
            <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
            <div>
                <h5 class="alert-heading mb-1">Atenção! Você tem OS atrasadas</h5>
                <p class="mb-0">
                    <strong><?php echo $stats['overdue_os']; ?></strong> OS <?php echo $stats['overdue_os'] > 1 ? 'estão' : 'está'; ?> com o prazo vencido. 
                    <a href="<?php echo BASE_URL; ?>views/work_orders/my_orders.php" class="alert-link">Ver OS atrasadas</a>
                </p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- OS DISPONÍVEIS PARA ACEITAR -->
        <div class="section-card">
            <div class="section-header">
                <h5 class="mb-0">
                    <i class="fas fa-clipboard-list text-primary me-2"></i>
                    Ordens de Serviço Disponíveis
                    <?php if ($stats['available_os'] > 0): ?>
                        <span class="badge bg-primary ms-2"><?php echo $stats['available_os']; ?></span>
                    <?php endif; ?>
                </h5>
                <a href="<?php echo BASE_URL; ?>views/work_orders/available.php" 
                   class="btn btn-sm btn-primary">
                    Ver Todas
                </a>
            </div>
            <div class="section-body">
                <?php if (empty($availableOS)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-check text-success"></i>
                        <h5>Nenhuma OS disponível!</h5>
                        <p class="text-muted">Todas as ordens de serviço foram aceitas</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($availableOS as $os): ?>
                        <div class="col-lg-6">
                            <div class="os-card <?php echo $os['priority']; ?>">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="badge bg-secondary">OS-<?php echo str_pad($os['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                    <span class="badge bg-<?php echo getPriorityColor($os['priority']); ?>">
                                        <i class="fas <?php echo getPriorityIcon($os['priority']); ?> me-1"></i>
                                        <?php echo getPriorityLabel($os['priority']); ?>
                                    </span>
                                </div>
                                
                                <h6 class="mb-2" style="color: var(--text-primary); font-weight: 600;">
                                    <?php echo htmlspecialchars($os['title']); ?>
                                </h6>
                                
                                <p class="small mb-3" style="color: var(--text-secondary);">
                                    <i class="fas fa-building me-1"></i>
                                    <?php echo htmlspecialchars($os['company_name']); ?>
                                </p>
                                
                                <div class="location-info">
                                    <div style="color: var(--text-secondary); font-size: 12px; font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
                                        <i class="fas fa-map-marker-alt me-1 text-danger"></i>
                                        Local do Atendimento
                                    </div>
                                    <div style="color: var(--text-primary); font-size: 14px;">
                                        <?php echo htmlspecialchars($os['company_address'] ?: 'Não informado'); ?>
                                    </div>
                                    <?php if ($os['company_phone']): ?>
                                    <div style="color: var(--text-primary); font-size: 14px; margin-top: 0.5rem;">
                                        <i class="fas fa-phone me-1 text-success"></i>
                                        <?php echo htmlspecialchars($os['company_phone']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex gap-2 mt-3">
                                    <button onclick="acceptOS(<?php echo $os['id']; ?>)" 
                                            class="btn btn-success flex-grow-1">
                                        <i class="fas fa-hand-paper me-1"></i>Aceitar OS
                                    </button>
                                    <a href="<?php echo BASE_URL; ?>views/work_orders/view.php?id=<?php echo $os['id']; ?>" 
                                       class="btn btn-outline-secondary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- MINHAS OS EM ANDAMENTO -->
        <div class="section-card">
            <div class="section-header">
                <h5 class="mb-0">
                    <i class="fas fa-cog fa-spin text-warning me-2"></i>
                    Minhas Ordens de Serviço em Andamento
                    <?php if ($stats['my_os'] > 0): ?>
                        <span class="badge bg-warning text-dark ms-2"><?php echo $stats['my_os']; ?></span>
                    <?php endif; ?>
                </h5>
                <a href="<?php echo BASE_URL; ?>views/work_orders/my_orders.php" 
                   class="btn btn-sm btn-primary">
                    Ver Todas
                </a>
            </div>
            <div class="section-body">
                <?php if (empty($myOS)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-check text-info"></i>
                        <h5>Nenhuma OS em andamento</h5>
                        <p class="text-muted">Aceite uma OS disponível para começar</p>
                        <a href="<?php echo BASE_URL; ?>views/work_orders/available.php" 
                           class="btn btn-primary mt-3">
                            <i class="fas fa-search me-2"></i>Buscar OS Disponíveis
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($myOS as $os): ?>
                        <?php 
                        $isOverdue = strtotime($os['deadline']) < time();
                        $overdueClass = $isOverdue ? 'overdue' : '';
                        ?>
                        <div class="col-lg-6">
                            <div class="os-card <?php echo $os['priority']; ?> <?php echo $overdueClass; ?>">
                                
                                <!-- Header -->
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="badge bg-secondary">OS-<?php echo str_pad($os['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                    <div>
                                        <span class="badge bg-<?php echo getPriorityColor($os['priority']); ?>">
                                            <?php echo getPriorityLabel($os['priority']); ?>
                                        </span>
                                        <?php if ($os['my_role'] === 'primary'): ?>
                                            <span class="badge bg-warning text-dark ms-1">
                                                <i class="fas fa-star me-1"></i>Principal
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary ms-1">
                                                <i class="fas fa-user-friends me-1"></i>Suporte
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($isOverdue): ?>
                                            <span class="badge bg-danger ms-1 badge-overdue">
                                                <i class="fas fa-exclamation-triangle me-1"></i>ATRASADA
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Título -->
                                <h6 class="mb-2" style="color: var(--text-primary); font-weight: 600;">
                                    <?php echo htmlspecialchars($os['title']); ?>
                                </h6>
                                
                                <!-- Empresa -->
                                <p class="small mb-2" style="color: var(--text-secondary);">
                                    <i class="fas fa-building me-1"></i>
                                    <?php echo htmlspecialchars($os['company_name']); ?>
                                </p>
                                
                                <!-- Equipe -->
                                <p class="small mb-3" style="color: var(--text-secondary);">
                                    <i class="fas fa-users me-1"></i>
                                    Equipe: <?php echo $os['team_size']; ?> técnico(s)
                                </p>
                                
                                <!-- Prazo -->
                                <div class="alert alert-<?php echo $isOverdue ? 'danger' : 'warning'; ?> py-2 px-3 mb-3">
                                    <small>
                                        <i class="fas fa-clock me-1"></i>
                                        <strong>Prazo:</strong> <?php echo formatDate($os['deadline'], true); ?>
                                        <?php if ($isOverdue): ?>
                                            <br><strong class="text-danger">⚠️ Prazo vencido!</strong>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                
                                <!-- Local -->
                                <?php if ($os['company_address']): ?>
                                <div class="location-info mb-3">
                                    <div style="color: var(--text-secondary); font-size: 12px; font-weight: 600; margin-bottom: 0.25rem;">
                                        <i class="fas fa-map-marker-alt me-1 text-danger"></i>
                                        Local
                                    </div>
                                    <div style="color: var(--text-primary); font-size: 13px;">
                                        <?php echo htmlspecialchars($os['company_address']); ?>
                                    </div>
                                    <?php if ($os['company_phone']): ?>
                                    <div style="color: var(--text-primary); font-size: 13px; margin-top: 0.5rem;">
                                        <i class="fas fa-phone me-1 text-success"></i>
                                        <?php echo htmlspecialchars($os['company_phone']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Status do técnico -->
                                <?php if ($os['my_status'] === 'completed'): ?>
                                <div class="alert alert-success py-2 px-3 mb-3">
                                    <small>
                                        <i class="fas fa-check-circle me-1"></i>
                                        Sua parte foi finalizada
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Ações -->
                                <div class="d-flex gap-2">
                                    <a href="<?php echo BASE_URL; ?>views/work_orders/view.php?id=<?php echo $os['id']; ?>" 
                                       class="btn btn-primary flex-grow-1">
                                        <i class="fas fa-eye me-1"></i>Ver Detalhes
                                    </a>
                                    <?php if ($os['my_status'] !== 'completed'): ?>
                                    <button onclick="completeMyPart(<?php echo $os['id']; ?>)" 
                                            class="btn btn-success">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
    
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Aceitar OS
function acceptOS(osId) {
    Swal.fire({
        title: 'Aceitar Ordem de Serviço',
        html: `
            <p>Ao aceitar esta OS, você será o <strong>técnico principal responsável</strong>.</p>
            <p class="text-muted small">Você poderá adicionar técnicos de suporte depois.</p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, aceitar OS',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#198754'
    }).then((result) => {
        if (result.isConfirmed) {
            const button = document.querySelector(`button[onclick*="acceptOS(${osId})"]`);
            if (button) {
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Aceitando...';
            }
            
            fetch('<?php echo BASE_URL; ?>ajax/accept_work_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `work_order_id=${osId}&csrf_token=<?php echo generateCSRFToken(); ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'OS Aceita!',
                        text: data.message,
                        confirmButtonText: 'Ver OS'
                    }).then(() => {
                        window.location.href = '<?php echo BASE_URL; ?>views/work_orders/view.php?id=' + osId;
                    });
                } else {
                    Swal.fire('Erro', data.message, 'error');
                    if (button) {
                        button.disabled = false;
                        button.innerHTML = '<i class="fas fa-hand-paper me-1"></i>Aceitar OS';
                    }
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro', 'Erro de comunicação com o servidor', 'error');
                if (button) {
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-hand-paper me-1"></i>Aceitar OS';
                }
            });
        }
    });
}

// Concluir minha parte
function completeMyPart(osId) {
    Swal.fire({
        title: 'Concluir Sua Parte',
        html: `
            <p>Marcar sua parte desta OS como concluída?</p>
            <textarea id="completionNotes" class="form-control mt-3" rows="3" 
                      placeholder="Observações finais (opcional)..."></textarea>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, concluir',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#198754',
        preConfirm: () => {
            return document.getElementById('completionNotes').value;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const notes = result.value;
            
            Swal.fire({
                title: 'Finalizando...',
                text: 'Aguarde enquanto processamos',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('<?php echo BASE_URL; ?>ajax/complete_work_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `work_order_id=${osId}&notes=${encodeURIComponent(notes)}&csrf_token=<?php echo generateCSRFToken(); ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.all_completed) {
                        Swal.fire({
                            icon: 'success',
                            title: 'OS Totalmente Concluída!',
                            text: 'Todos os técnicos finalizaram. A OS foi marcada como concluída.',
                            confirmButtonText: 'Ver Ticket'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sua Parte Concluída!',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    }
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro', 'Erro de comunicação com o servidor', 'error');
            });
        }
    });
}

// Auto-refresh a cada 5 minutos (para pegar novas OS)
setInterval(() => {
    const availableOSSection = document.querySelector('.section-card:first-of-type');
    if (availableOSSection && !document.hidden) {
        // Apenas recarrega se não houver modais abertos
        if (!document.querySelector('.swal2-container')) {
            console.log('Auto-refresh: verificando novas OS...');
            // Poderia fazer uma requisição AJAX aqui para verificar novas OS
            // Por enquanto, vamos apenas logar
        }
    }
}, 300000); // 5 minutos

// Notificação de visibilidade
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        console.log('Página ficou visível novamente');
    }
});

// Log de inicialização
console.log('Dashboard técnico carregado');
console.log('User ID:', <?php echo $userId; ?>);
console.log('Estatísticas:', <?php echo json_encode($stats); ?>);
</script>

<?php include ROOT_PATH . 'includes/footer.php'; ?>