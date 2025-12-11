<?php
/**
 * Dashboard Atendente - COMPLETO COM SISTEMA DE OS
 * Versão atualizada com todas as funcionalidades de Ordem de Serviço
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Dashboard Atendente - ' . SYSTEM_NAME;
$pdo = getConnection();
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

// =====================================================
// ESTATÍSTICAS DO ATENDENTE
// =====================================================

$stats = [
    'new_tickets' => 0,
    'my_tickets' => 0,
    'high_priority' => 0,
    'resolved_today' => 0,
    'pending_os' => 0,
    'active_os' => 0,
];

// Tickets novos (não assumidos)
$stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'created'");
$stats['new_tickets'] = $stmt->fetchColumn();

// Meus tickets em andamento
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM tickets 
    WHERE assigned_user_id = ? 
    AND status NOT IN ('closed', 'resolved')
");
$stmt->execute([$userId]);
$stats['my_tickets'] = $stmt->fetchColumn();

// Alta prioridade não assumidos
$stmt = $pdo->query("
    SELECT COUNT(*) FROM tickets 
    WHERE priority = 'high' AND status = 'created'
");
$stats['high_priority'] = $stmt->fetchColumn();

// Resolvidos hoje
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM tickets 
    WHERE assigned_user_id = ? 
    AND status IN ('resolved', 'closed') 
    AND DATE(resolved_at) = CURDATE()
");
$stmt->execute([$userId]);
$stats['resolved_today'] = $stmt->fetchColumn();

// OS pendentes de criação (tickets assumidos sem OS)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM tickets 
    WHERE assigned_user_id = ? 
    AND status IN ('assumed', 'dispatched')
    AND has_work_order = FALSE
");
$stmt->execute([$userId]);
$stats['pending_os'] = $stmt->fetchColumn();

// OS ativas que eu criei
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM work_orders 
    WHERE created_by = ? 
    AND status IN ('available', 'in_progress')
");
$stmt->execute([$userId]);
$stats['active_os'] = $stmt->fetchColumn();

// =====================================================
// TICKETS NOVOS AGUARDANDO
// =====================================================

$stmt = $pdo->query("
    SELECT t.*, c.name as company_name
    FROM tickets t
    INNER JOIN companies c ON t.company_id = c.id
    WHERE t.status = 'created'
    ORDER BY 
        CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
        t.created_at ASC
    LIMIT 10
");
$newTickets = $stmt->fetchAll();

// =====================================================
// TICKETS ASSUMIDOS PENDENTES DE OS
// =====================================================

$stmt = $pdo->prepare("
    SELECT t.*, c.name as company_name
    FROM tickets t
    INNER JOIN companies c ON t.company_id = c.id
    WHERE t.assigned_user_id = ? 
    AND t.status IN ('assumed', 'dispatched')
    AND t.has_work_order = FALSE
    ORDER BY 
        CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
        t.created_at ASC
    LIMIT 5
");
$stmt->execute([$userId]);
$pendingOSTickets = $stmt->fetchAll();

// =====================================================
// MINHAS OS ATIVAS
// =====================================================

$stmt = $pdo->prepare("
    SELECT 
        wo.*,
        t.title as ticket_title,
        t.priority,
        c.name as company_name,
        (SELECT COUNT(*) FROM work_order_technicians wot 
         WHERE wot.work_order_id = wo.id) as tech_count,
        (SELECT u.name FROM work_order_technicians wot 
         INNER JOIN users u ON wot.technician_id = u.id 
         WHERE wot.work_order_id = wo.id AND wot.role = 'primary' 
         LIMIT 1) as primary_tech
    FROM work_orders wo
    INNER JOIN tickets t ON wo.ticket_id = t.id
    INNER JOIN companies c ON t.company_id = c.id
    WHERE wo.created_by = ? 
    AND wo.status IN ('available', 'in_progress')
    ORDER BY 
        CASE wo.status WHEN 'available' THEN 1 ELSE 2 END,
        CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
        wo.created_at DESC
    LIMIT 10
");
$stmt->execute([$userId]);
$myActiveOS = $stmt->fetchAll();

// =====================================================
// PERFORMANCE DO MÊS
// =====================================================

$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved,
        AVG(CASE WHEN resolved_at IS NOT NULL 
            THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END) as avg_time
    FROM tickets 
    WHERE assigned_user_id = ? 
    AND MONTH(created_at) = MONTH(CURRENT_DATE())
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
");
$stmt->execute([$userId]);
$performance = $stmt->fetch();

include __DIR__ . '/../../includes/header.php';
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

.user-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.user-avatar {
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

.user-title h1 {
    font-size: 32px;
    font-weight: 700;
    color: white;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.user-subtitle {
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
    justify-content: between;
    align-items: center;
}

.section-body {
    padding: 1.5rem;
}

.ticket-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-left: 4px solid transparent;
    border-radius: 12px;
    padding: 1.25rem;
    transition: all 0.3s ease;
    margin-bottom: 1rem;
}

.ticket-card:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.ticket-card.high { border-left-color: #dc3545; }
.ticket-card.medium { border-left-color: #ffc107; }
.ticket-card.low { border-left-color: #198754; }

.os-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-left: 4px solid transparent;
    border-radius: 12px;
    padding: 1.25rem;
    transition: all 0.3s ease;
    margin-bottom: 1rem;
}

.os-card.available { border-left-color: #0dcaf0; }
.os-card.in_progress { border-left-color: #ffc107; }

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
</style>

<div class="container-fluid">
    
    <!-- HERO SECTION -->
    <div class="dashboard-hero">
        <div class="hero-content">
            
            <div class="user-header">
                <div class="user-avatar">
                    <?php 
                    $initials = '';
                    $nameParts = explode(' ', $userName);
                    $initials = strtoupper(substr($nameParts[0], 0, 1));
                    if (isset($nameParts[1])) {
                        $initials .= strtoupper(substr($nameParts[1], 0, 1));
                    }
                    echo $initials;
                    ?>
                </div>
                <div class="user-title">
                    <h1><?php echo htmlspecialchars($userName); ?></h1>
                    <p class="user-subtitle">
                        <i class="fas fa-headset me-2"></i>
                        Dashboard de Atendimento
                    </p>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="<?php echo BASE_URL; ?>views/tickets/index.php" class="btn-hero primary">
                    <i class="fas fa-list"></i>
                    Todos os Tickets
                </a>
                <a href="<?php echo BASE_URL; ?>views/tickets/index.php?status=created" class="btn-hero">
                    <i class="fas fa-inbox"></i>
                    Novos Tickets
                    <?php if ($stats['new_tickets'] > 0): ?>
                        <span class="badge bg-danger"><?php echo $stats['new_tickets']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo BASE_URL; ?>views/work_orders/manage.php" class="btn-hero">
                    <i class="fas fa-tasks"></i>
                    Gerenciar OS
                </a>
            </div>
            
        </div>
    </div>
    
    <div class="container" style="max-width: 1200px;">
        
        <!-- ESTATÍSTICAS -->
        <div class="row g-4 mb-4">
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <div class="stat-value" style="color: #ffc107;"><?php echo $stats['new_tickets']; ?></div>
                    <div class="stat-label">Novos Tickets</div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(13, 202, 240, 0.1); color: #0dcaf0;">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-value" style="color: #0dcaf0;"><?php echo $stats['my_tickets']; ?></div>
                    <div class="stat-label">Meus Tickets</div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-value" style="color: #dc3545;"><?php echo $stats['high_priority']; ?></div>
                    <div class="stat-label">Alta Prioridade</div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(25, 135, 84, 0.1); color: #198754;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value" style="color: #198754;"><?php echo $stats['resolved_today']; ?></div>
                    <div class="stat-label">Resolvidos Hoje</div>
                </div>
            </div>
            
        </div>

        <!-- ESTATÍSTICAS DE OS -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(13, 110, 253, 0.1); color: #0d6efd;">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-value" style="color: #0d6efd;"><?php echo $stats['pending_os']; ?></div>
                    <div class="stat-label">Tickets Pendentes de OS</div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="stat-value" style="color: #ffc107;"><?php echo $stats['active_os']; ?></div>
                    <div class="stat-label">Minhas OS Ativas</div>
                </div>
            </div>
        </div>
        
        <!-- PERFORMANCE DO MÊS -->
        <?php if ($performance && $performance['total'] > 0): ?>
        <div class="section-card">
            <div class="section-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line text-primary me-2"></i>
                    Sua Performance Este Mês
                </h5>
            </div>
            <div class="section-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="p-3">
                            <h3 class="text-primary mb-1"><?php echo $performance['total']; ?></h3>
                            <small class="text-muted">Total Atendidos</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3">
                            <h3 class="text-success mb-1"><?php echo $performance['resolved']; ?></h3>
                            <small class="text-muted">Resolvidos</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3">
                            <h3 class="text-info mb-1">
                                <?php echo $performance['avg_time'] ? round($performance['avg_time'], 1) : '0'; ?>h
                            </h3>
                            <small class="text-muted">Tempo Médio</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- TICKETS PENDENTES DE OS -->
        <?php if (!empty($pendingOSTickets)): ?>
        <div class="section-card">
            <div class="section-header">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-circle text-danger me-2"></i>
                    Tickets Assumidos - Criar Ordem de Serviço
                    <span class="badge bg-danger ms-2"><?php echo count($pendingOSTickets); ?></span>
                </h5>
            </div>
            <div class="section-body">
                <?php foreach ($pendingOSTickets as $ticket): ?>
                <div class="ticket-card <?php echo $ticket['priority']; ?>">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-secondary">#<?php echo $ticket['id']; ?></span>
                        <span class="badge bg-<?php echo getPriorityColor($ticket['priority']); ?>">
                            <?php echo getPriorityLabel($ticket['priority']); ?>
                        </span>
                    </div>
                    <h6 class="mb-2"><?php echo htmlspecialchars($ticket['title']); ?></h6>
                    <p class="text-muted small mb-3">
                        <i class="fas fa-building me-1"></i>
                        <?php echo htmlspecialchars($ticket['company_name']); ?>
                    </p>
                    <div class="d-flex gap-2">
                        <button onclick="createWorkOrder(<?php echo $ticket['id']; ?>)" 
                                class="btn btn-primary btn-sm flex-grow-1">
                            <i class="fas fa-plus me-1"></i>Criar OS
                        </button>
                        <a href="<?php echo BASE_URL; ?>views/tickets/view.php?id=<?php echo $ticket['id']; ?>" 
                           class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- MINHAS OS ATIVAS -->
        <?php if (!empty($myActiveOS)): ?>
        <div class="section-card">
            <div class="section-header">
                <h5 class="mb-0">
                    <i class="fas fa-cog text-warning me-2"></i>
                    Minhas Ordens de Serviço Ativas
                    <span class="badge bg-warning text-dark ms-2"><?php echo count($myActiveOS); ?></span>
                </h5>
                <a href="<?php echo BASE_URL; ?>views/work_orders/manage.php" class="btn btn-sm btn-primary">
                    Ver Todas
                </a>
            </div>
            <div class="section-body">
                <?php foreach ($myActiveOS as $os): ?>
                <div class="os-card <?php echo $os['status']; ?>">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-secondary">OS-<?php echo str_pad($os['id'], 6, '0', STR_PAD_LEFT); ?></span>
                        <div>
                            <span class="badge bg-<?php echo getPriorityColor($os['priority']); ?>">
                                <?php echo getPriorityLabel($os['priority']); ?>
                            </span>
                            <span class="badge bg-<?php echo $os['status'] === 'available' ? 'info' : 'warning'; ?> ms-1">
                                <?php echo $os['status'] === 'available' ? 'Disponível' : 'Em Andamento'; ?>
                            </span>
                        </div>
                    </div>
                    <h6 class="mb-2"><?php echo htmlspecialchars($os['ticket_title']); ?></h6>
                    <p class="text-muted small mb-2">
                        <i class="fas fa-building me-1"></i>
                        <?php echo htmlspecialchars($os['company_name']); ?>
                    </p>
                    <p class="text-muted small mb-3">
                        <i class="fas fa-users me-1"></i>
                        <?php echo $os['tech_count']; ?> técnico(s)
                        <?php if ($os['primary_tech']): ?>
                            - Principal: <?php echo htmlspecialchars($os['primary_tech']); ?>
                        <?php endif; ?>
                    </p>
                    <a href="<?php echo BASE_URL; ?>views/work_orders/view.php?id=<?php echo $os['id']; ?>" 
                       class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-eye me-1"></i>Ver Detalhes
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- TICKETS NOVOS AGUARDANDO -->
        <div class="section-card">
            <div class="section-header">
                <h5 class="mb-0">
                    <i class="fas fa-inbox text-warning me-2"></i>
                    Tickets Novos Aguardando Atendimento
                    <?php if ($stats['new_tickets'] > 0): ?>
                        <span class="badge bg-warning text-dark ms-2"><?php echo $stats['new_tickets']; ?></span>
                    <?php endif; ?>
                </h5>
                <a href="<?php echo BASE_URL; ?>views/tickets/index.php?status=created" 
                   class="btn btn-sm btn-primary">
                    Ver Todos
                </a>
            </div>
            <div class="section-body">
                <?php if (empty($newTickets)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle text-success"></i>
                        <h5>Nenhum ticket aguardando!</h5>
                        <p class="text-muted">Todos os tickets foram assumidos 🎉</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($newTickets as $ticket): ?>
                    <div class="ticket-card <?php echo $ticket['priority']; ?>">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-secondary">#<?php echo $ticket['id']; ?></span>
                            <span class="badge bg-<?php echo getPriorityColor($ticket['priority']); ?>">
                                <i class="fas <?php echo getPriorityIcon($ticket['priority']); ?> me-1"></i>
                                <?php echo getPriorityLabel($ticket['priority']); ?>
                            </span>
                        </div>
                        <h6 class="mb-2"><?php echo htmlspecialchars($ticket['title']); ?></h6>
                        <p class="text-muted small mb-2">
                            <i class="fas fa-building me-1"></i>
                            <?php echo htmlspecialchars($ticket['company_name']); ?>
                        </p>
                        <p class="text-muted small mb-3">
                            <i class="far fa-clock me-1"></i>
                            <?php echo formatDate($ticket['created_at'], true); ?>
                        </p>
                        <div class="d-flex gap-2">
                            <button onclick="assumeTicket(<?php echo $ticket['id']; ?>)" 
                                    class="btn btn-primary btn-sm flex-grow-1">
                                <i class="fas fa-hand-paper me-1"></i>Assumir
                            </button>
                            <a href="<?php echo BASE_URL; ?>views/tickets/view.php?id=<?php echo $ticket['id']; ?>" 
                               class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
    
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Assumir Ticket
function assumeTicket(ticketId) {
    if (!confirm('Deseja assumir este ticket?\n\nVocê será o responsável pelo atendimento.')) {
        return;
    }
    
    const buttons = document.querySelectorAll(`button[onclick*="assumeTicket(${ticketId})"]`);
    buttons.forEach(btn => {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assumindo...';
    });
    
    fetch('<?php echo BASE_URL; ?>ajax/assume_ticket.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ ticket_id: ticketId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Ticket Assumido!',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire('Erro', data.message, 'error');
            buttons.forEach(btn => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-hand-paper me-1"></i>Assumir';
            });
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        Swal.fire('Erro', 'Erro de comunicação com o servidor', 'error');
        buttons.forEach(btn => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-hand-paper me-1"></i>Assumir';
        });
    });
}

// Criar Ordem de Serviço
function createWorkOrder(ticketId) {
    Swal.fire({
        title: 'Criar Ordem de Serviço',
        text: 'Deseja criar uma OS para este ticket?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, criar OS',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0d6efd'
    }).then((result) => {
        if (result.isConfirmed) {
            const button = document.querySelector(`button[onclick*="createWorkOrder(${ticketId})"]`);
            if (button) {
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Criando...';
            }
            
            fetch('<?php echo BASE_URL; ?>ajax/create_work_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ticket_id=${ticketId}&csrf_token=<?php echo generateCSRFToken(); ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'OS Criada!',
                        text: data.message,
                        confirmButtonText: 'Ver OS'
                    }).then(() => {
                        window.location.href = '<?php echo BASE_URL; ?>views/work_orders/view.php?id=' + data.work_order_id;
                    });
                } else {
                    Swal.fire('Erro', data.message, 'error');
                    if (button) {
                        button.disabled = false;
                        button.innerHTML = '<i class="fas fa-plus me-1"></i>Criar OS';
                    }
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro', 'Erro de comunicação com o servidor', 'error');
                if (button) {
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-plus me-1"></i>Criar OS';
                }
            });
        }
    });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>