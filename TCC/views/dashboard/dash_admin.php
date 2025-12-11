<?php
/**
 * Dashboard Administrativo Completo - Versão 2.0 FINALIZADA
 * Sistema de Análise e Gestão Centralizada
 * Autor: Nicolas Clayton Parpinelli + Claude
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Dashboard Administrativo - ' . SYSTEM_NAME;
$pdo = getConnection();

// ============================================
// ESTATÍSTICAS GERAIS DO SISTEMA
// ============================================

$stats = [
    'total_companies' => $pdo->query("SELECT COUNT(*) FROM companies WHERE is_active = 1")->fetchColumn(),
    'total_employees' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1 AND role IN ('attendant', 'technician')")->fetchColumn(),
    'total_attendants' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1 AND role = 'attendant'")->fetchColumn(),
    'total_technicians' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1 AND role = 'technician'")->fetchColumn(),
    'total_admins' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1 AND role = 'admin'")->fetchColumn(),
    'total_tickets' => $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn(),
    'tickets_open' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status NOT IN ('closed', 'resolved')")->fetchColumn(),
    'tickets_resolved' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('resolved', 'closed')")->fetchColumn(),
    'tickets_high_priority' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE priority = 'high' AND status NOT IN ('closed', 'resolved')")->fetchColumn(),
];

// Taxa de resolução
$stats['resolution_rate'] = $stats['total_tickets'] > 0 
    ? round(($stats['tickets_resolved'] / $stats['total_tickets']) * 100, 1) 
    : 0;

// Tempo médio de resolução
$avgResolutionTime = $pdo->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) 
    FROM tickets 
    WHERE resolved_at IS NOT NULL
")->fetchColumn();
$stats['avg_resolution_time'] = $avgResolutionTime ? round($avgResolutionTime, 1) : 0;

// ============================================
// PERFORMANCE DOS ATENDENTES
// ============================================

$attendantsPerformance = $pdo->query("
    SELECT 
        u.id,
        u.name,
        u.email,
        COUNT(DISTINCT t.id) as total_tickets,
        SUM(CASE WHEN t.status = 'assumed' THEN 1 ELSE 0 END) as tickets_assumed,
        SUM(CASE WHEN t.status IN ('dispatched', 'in_progress', 'resolved', 'closed') THEN 1 ELSE 0 END) as tickets_processed,
        AVG(CASE 
            WHEN t.assumed_at IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.assumed_at)
        END) as avg_response_time
    FROM users u
    LEFT JOIN tickets t ON u.id = t.assigned_user_id
    WHERE u.is_active = 1 AND u.role = 'attendant'
    GROUP BY u.id, u.name, u.email
    ORDER BY tickets_processed DESC
    LIMIT 10
")->fetchAll();

// ============================================
// PERFORMANCE DOS TÉCNICOS - QUERY CORRIGIDA
// ============================================

$techniciansPerformance = $pdo->query("
    SELECT 
        u.id,
        u.name,
        u.email,
        COUNT(t.id) as total_tickets,
        SUM(CASE WHEN t.status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as tickets_resolved,
        SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as tickets_in_progress,
        SUM(CASE WHEN t.status = 'dispatched' THEN 1 ELSE 0 END) as tickets_dispatched,
        AVG(CASE 
            WHEN t.resolved_at IS NOT NULL AND t.dispatched_at IS NOT NULL
            THEN TIMESTAMPDIFF(HOUR, t.dispatched_at, t.resolved_at)
        END) as avg_resolution_time
    FROM users u
    LEFT JOIN tickets t ON u.id = t.assigned_user_id
    WHERE u.is_active = 1 AND u.role = 'technician'
    GROUP BY u.id, u.name, u.email
    ORDER BY tickets_resolved DESC, total_tickets DESC
    LIMIT 10
")->fetchAll();

// ============================================
// TICKETS POR EMPRESA
// ============================================

$companiesStats = $pdo->query("
    SELECT 
        c.id,
        c.name,
        COUNT(t.id) as total_tickets,
        SUM(CASE WHEN t.status NOT IN ('closed', 'resolved') THEN 1 ELSE 0 END) as tickets_open,
        SUM(CASE WHEN t.priority = 'high' THEN 1 ELSE 0 END) as tickets_high_priority
    FROM companies c
    LEFT JOIN tickets t ON c.id = t.company_id
    WHERE c.is_active = 1
    GROUP BY c.id, c.name
    ORDER BY total_tickets DESC
    LIMIT 10
")->fetchAll();

// ============================================
// ATIVIDADES RECENTES
// ============================================

$recentActivities = $pdo->query("
    SELECT 
        t.id,
        t.title,
        t.status,
        t.priority,
        t.created_at,
        t.updated_at,
        c.name as company_name,
        u.name as assigned_user_name
    FROM tickets t
    INNER JOIN companies c ON t.company_id = c.id
    LEFT JOIN users u ON t.assigned_user_id = u.id
    ORDER BY t.updated_at DESC
    LIMIT 15
")->fetchAll();

// ============================================
// DADOS PARA GRÁFICOS
// ============================================

// Tickets por status
$chartStatus = [
    'created' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'created'")->fetchColumn(),
    'assumed' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'assumed'")->fetchColumn(),
    'dispatched' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'dispatched'")->fetchColumn(),
    'in_progress' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'in_progress'")->fetchColumn(),
    'resolved' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('resolved', 'closed')")->fetchColumn(),
];

// Tickets por prioridade
$chartPriority = [
    'high' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE priority = 'high'")->fetchColumn(),
    'medium' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE priority = 'medium'")->fetchColumn(),
    'low' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE priority = 'low'")->fetchColumn(),
];

// Tickets dos últimos 7 dias
$last7Days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $count = $pdo->query("SELECT COUNT(*) FROM tickets WHERE DATE(created_at) = '$date'")->fetchColumn();
    $last7Days[] = [
        'date' => date('d/m', strtotime($date)),
        'count' => $count
    ];
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
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

.admin-header {
    background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.2);
}

.admin-title {
    color: white;
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.admin-title i {
    width: 50px;
    height: 50px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.admin-subtitle {
    color: rgba(255, 255, 255, 0.9);
    font-size: 15px;
    margin-bottom: 1.5rem;
}

.admin-welcome {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 1rem 1.5rem;
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 1rem;
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
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin-bottom: 1rem;
}

.stat-card .stat-value {
    font-size: 36px;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.5rem;
}

.stat-card .stat-label {
    font-size: 14px;
    color: var(--text-secondary);
    font-weight: 500;
}

.stat-card .stat-detail {
    font-size: 13px;
    color: var(--text-secondary);
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid var(--border-color);
}

.quick-actions-panel {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    position: sticky;
    top: 100px;
}

.quick-actions-title {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 1.5rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    margin-bottom: 0.75rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.3s ease;
    font-weight: 500;
}

.quick-action-btn:hover {
    background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
    color: white;
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.quick-action-btn i {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(102, 126, 234, 0.1);
    border-radius: 10px;
    font-size: 18px;
}

.quick-action-btn:hover i {
    background: rgba(255, 255, 255, 0.2);
}

.performance-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    margin-bottom: 1.5rem;
}

.performance-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.performance-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.performance-badge {
    background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
    color: white;
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.performance-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    margin-bottom: 0.75rem;
    background: var(--bg-secondary);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.performance-item:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.performance-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 700;
    margin-right: 1rem;
    flex-shrink: 0;
}

/* ===================================
   TABELA DE EMPRESAS - FIX FINAL
   =================================== */

/* Container da tabela */
.performance-card .table-responsive {
    background: var(--bg-primary) !important;
    border-radius: 12px;
    overflow: hidden;
}

/* A PRÓPRIA TABELA */
.performance-card .table {
    background: var(--bg-primary) !important;
    color: var(--text-primary) !important;
    margin-bottom: 0;
}

/* CABEÇALHO - força as cores */
.performance-card .table thead {
    background: var(--bg-secondary) !important;
}

.performance-card .table thead th {
    color: var(--text-primary) !important;
    background: var(--bg-secondary) !important;
    border-color: var(--border-color) !important;
    font-weight: 600;
}

/* CORPO DA TABELA */
.performance-card .table tbody {
    background: var(--bg-primary) !important;
}

.performance-card .table tbody tr {
    background: var(--bg-primary) !important;
    border-color: var(--border-color) !important;
}

.performance-card .table tbody tr:hover {
    background: var(--bg-secondary) !important;
}

.performance-card .table tbody td {
    background: transparent !important;
    color: var(--text-primary) !important;
    border-color: var(--border-color) !important;
}

/* TEXTO DENTRO DAS CÉLULAS */
.performance-card .table tbody td strong {
    color: var(--text-primary) !important;
}

.performance-card .table tbody td .text-muted {
    color: var(--text-secondary) !important;
}

/* BADGES - NÃO FORÇAR NADA, deixar o Bootstrap cuidar */
.performance-card .table .badge {
    /* Mantém as cores originais do Bootstrap */
}

/* Avatar das empresas */
.performance-card .table tbody td > div {
    color: var(--text-primary) !important;
}

.performance-card .table tbody td > div > div {
    color: white !important; /* Avatar sempre branco */
}

.performance-info {
    flex: 1;
    min-width: 0;
}

.performance-name {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.performance-email {
    font-size: 13px;
    color: var(--text-secondary);
}

.performance-stats {
    display: flex;
    gap: 1.5rem;
    align-items: center;
}

.performance-stat {
    text-align: center;
}

.performance-stat-value {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-primary);
    display: block;
}

.performance-stat-label {
    font-size: 11px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.timeline-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
}

.timeline-header {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.timeline-item {
    display: flex;
    gap: 1rem;
    padding: 1rem 0;
    border-left: 2px solid var(--border-color);
    padding-left: 1.5rem;
    position: relative;
    margin-left: 1rem;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 1.5rem;
    width: 10px;
    height: 10px;
    background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
    border-radius: 50%;
    border: 2px solid var(--bg-primary);
}

.timeline-item:last-child {
    border-left-color: transparent;
}

.timeline-content {
    flex: 1;
}

.timeline-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.timeline-text {
    font-size: 13px;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.timeline-meta {
    font-size: 12px;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.chart-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    margin-bottom: 1.5rem;
}

.chart-header {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.chart-card canvas {
    max-height: 300px !important;
    height: 300px !important;
}

/* Ajuste específico para mobile */
@media (max-width: 768px) {
    .chart-card canvas {
        max-height: 250px !important;
        height: 250px !important;
    }
}

@media (max-width: 992px) {
    .quick-actions-panel {
        position: relative;
        top: 0;
        margin-bottom: 1.5rem;
    }
    
    .performance-stats {
        flex-direction: column;
        gap: 0.5rem;
        align-items: flex-start;
    }
}

@media (max-width: 768px) {
    .admin-header {
        padding: 1.5rem 0;
    }
    
    .admin-title {
        font-size: 22px;
    }
    
    .stat-card .stat-value {
        font-size: 28px;
    }
    
    .performance-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .performance-stats {
        width: 100%;
        justify-content: space-between;
    }
}
</style>

<div class="container-fluid px-4">
    
    <div class="admin-header">
        <div class="container">
            <div class="admin-title">
                <i class="fas fa-crown"></i>
                Dashboard Administrativo
            </div>
            <p class="admin-subtitle">Visão completa do sistema - Análise e gestão centralizada</p>
            <div class="admin-welcome">
                <i class="fas fa-user-shield"></i>
                <div>
                    <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>
                    <small style="display: block; opacity: 0.8;">Administrador do Sistema</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="row">
            
            <div class="col-lg-3">
                <div class="quick-actions-panel">
                    <div class="quick-actions-title">
                        <i class="fas fa-bolt"></i>
                        Ações Rápidas
                    </div>
                    
                    <a href="<?php echo BASE_URL; ?>views/companies/create.php" class="quick-action-btn">
                        <i class="fas fa-building"></i>
                        <div>
                            <strong>Nova Empresa</strong>
                            <small style="display: block; font-size: 12px; opacity: 0.7;">Cadastrar cliente</small>
                        </div>
                    </a>
                    
                    <a href="<?php echo BASE_URL; ?>views/users/create.php?role=attendant" class="quick-action-btn">
                        <i class="fas fa-headset"></i>
                        <div>
                            <strong>Novo Atendente</strong>
                            <small style="display: block; font-size: 12px; opacity: 0.7;">Cadastrar funcionário</small>
                        </div>
                    </a>
                    
                    <a href="<?php echo BASE_URL; ?>views/users/create.php?role=technician" class="quick-action-btn">
                        <i class="fas fa-tools"></i>
                        <div>
                            <strong>Novo Técnico</strong>
                            <small style="display: block; font-size: 12px; opacity: 0.7;">Cadastrar técnico</small>
                        </div>
                    </a>
                    
                    <a href="<?php echo BASE_URL; ?>views/users/create.php?role=admin" class="quick-action-btn">
                        <i class="fas fa-user-shield"></i>
                        <div>
                            <strong>Novo Admin</strong>
                            <small style="display: block; font-size: 12px; opacity: 0.7;">Cadastrar administrador</small>
                        </div>
                    </a>
                    
                    <div style="border-top: 1px solid var(--border-color); margin: 1rem 0;"></div>
                    
                    <a href="<?php echo BASE_URL; ?>views/users/index.php" class="quick-action-btn">
                        <i class="fas fa-users"></i>
                        <span>Gerenciar Usuários</span>
                    </a>
                    
                    <a href="<?php echo BASE_URL; ?>views/companies/index.php" class="quick-action-btn">
                        <i class="fas fa-building"></i>
                        <span>Gerenciar Empresas</span>
                    </a>
                    
                    <a href="<?php echo BASE_URL; ?>views/tickets/index.php" class="quick-action-btn">
                        <i class="fas fa-ticket-alt"></i>
                        <span>Todos os Tickets</span>
                    </a>
                    
                    <a href="<?php echo BASE_URL; ?>views/admin/reports.php" class="quick-action-btn">
                        <i class="fas fa-chart-line"></i>
                        <span>Relatórios Completos</span>
                    </a>
                </div>
            </div>
            
            <div class="col-lg-9">
                
                <div class="row g-4 mb-4">
                    
                    <div class="col-md-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(13, 110, 253, 0.1); color: #0d6efd;">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="stat-value" style="color: #0d6efd;"><?php echo $stats['total_companies']; ?></div>
                            <div class="stat-label">Empresas Ativas</div>
                            <div class="stat-detail">
                                <i class="fas fa-info-circle me-1"></i>
                                Clientes cadastrados
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(102, 126, 234, 0.1); color: #667eea;">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-value" style="color: #667eea;"><?php echo $stats['total_employees']; ?></div>
                            <div class="stat-label">Funcionários</div>
                            <div class="stat-detail">
                                <i class="fas fa-headset me-1"></i>
                                <?php echo $stats['total_attendants']; ?> Atendentes • 
                                <i class="fas fa-tools me-1"></i>
                                <?php echo $stats['total_technicians']; ?> Técnicos
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                            <div class="stat-value" style="color: #ffc107;"><?php echo $stats['total_tickets']; ?></div>
                            <div class="stat-label">Total de Tickets</div>
                            <div class="stat-detail">
                                <i class="fas fa-folder-open me-1"></i>
                                <?php echo $stats['tickets_open']; ?> em aberto
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(25, 135, 84, 0.1); color: #198754;">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-value" style="color: #198754;"><?php echo $stats['resolution_rate']; ?>%</div>
                            <div class="stat-label">Taxa de Resolução</div>
                            <div class="stat-detail">
                                <i class="fas fa-clock me-1"></i>
                                Média: <?php echo $stats['avg_resolution_time']; ?>h
                            </div>
                        </div>
                    </div>
                    
                </div>
                
                <div class="row mb-4">
                    
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <div class="chart-header">
                                <i class="fas fa-chart-pie"></i>
                                Distribuição por Status
                            </div>
                            <canvas id="statusChart" height="250"></canvas>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <div class="chart-header">
                                <i class="fas fa-chart-bar"></i>
                                Tickets - Últimos 7 Dias
                            </div>
                            <canvas id="weekChart" height="250"></canvas>
                        </div>
                    </div>
                    
                </div>
                
                <div class="performance-card">
                    <div class="performance-header">
                        <div class="performance-title">
                            <i class="fas fa-headset"></i>
                            Performance dos Atendentes
                        </div>
                        <span class="performance-badge">Top 10</span>
                    </div>
                    
                    <?php if (empty($attendantsPerformance)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users-slash fa-3x mb-3 opacity-25"></i>
                            <p class="text-muted">Nenhum atendente encontrado</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($attendantsPerformance as $index => $attendant): ?>
                            <?php
                            $initials = '';
                            $nameParts = explode(' ', $attendant['name']);
                            $initials = strtoupper(substr($nameParts[0], 0, 1));
                            if (isset($nameParts[1])) {
                                $initials .= strtoupper(substr($nameParts[1], 0, 1));
                            }
                            ?>
                            <div class="performance-item">
                                <div class="performance-avatar"><?php echo $initials; ?></div>
                                <div class="performance-info">
                                    <div class="performance-name">
                                        <?php if ($index < 3): ?>
                                            <i class="fas fa-trophy" style="color: <?php echo $index == 0 ? '#ffd700' : ($index == 1 ? '#c0c0c0' : '#cd7f32'); ?>"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($attendant['name']); ?>
                                    </div>
                                    <div class="performance-email"><?php echo htmlspecialchars($attendant['email']); ?></div>
                                </div>
                                <div class="performance-stats">
                                    <div class="performance-stat">
                                        <span class="performance-stat-value"><?php echo $attendant['total_tickets']; ?></span>
                                        <span class="performance-stat-label">Total</span>
                                    </div>
                                    <div class="performance-stat">
                                        <span class="performance-stat-value"><?php echo $attendant['tickets_assumed']; ?></span>
                                        <span class="performance-stat-label">Assumidos</span>
                                    </div>
                                    <div class="performance-stat">
                                        <span class="performance-stat-value"><?php echo $attendant['tickets_processed']; ?></span>
                                        <span class="performance-stat-label">Processados</span>
                                    </div>
                                    <div class="performance-stat">
                                        <span class="performance-stat-value"><?php echo round($attendant['avg_response_time'] ?? 0); ?>m</span>
                                        <span class="performance-stat-label">Resp. Média</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="performance-card">
                    <div class="performance-header">
                        <div class="performance-title">
                            <i class="fas fa-tools"></i>
                            Performance dos Técnicos
                        </div>
                        <span class="performance-badge">Top 10</span>
                    </div>
                    
                    <?php if (empty($techniciansPerformance)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-user-slash fa-3x mb-3 opacity-25"></i>
                            <p class="text-muted">Nenhum técnico encontrado</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($techniciansPerformance as $index => $technician): ?>
                            <?php
                            $initials = '';
                            $nameParts = explode(' ', $technician['name']);
                            $initials = strtoupper(substr($nameParts[0], 0, 1));
                            if (isset($nameParts[1])) {
                                $initials .= strtoupper(substr($nameParts[1], 0, 1));
                            }
                            $successRate = $technician['total_tickets'] > 0 
                                ? round(($technician['tickets_resolved'] / $technician['total_tickets']) * 100) 
                                : 0;
                            ?>
                            <div class="performance-item">
                                <div class="performance-avatar"><?php echo $initials; ?></div>
                                <div class="performance-info">
                                    <div class="performance-name">
                                        <?php if ($index < 3): ?>
                                            <i class="fas fa-trophy" style="color: <?php echo $index == 0 ? '#ffd700' : ($index == 1 ? '#c0c0c0' : '#cd7f32'); ?>"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($technician['name']); ?>
                                    </div>
                                    <div class="performance-email"><?php echo htmlspecialchars($technician['email']); ?></div>
                                </div>
                                <div class="performance-stats">
                                    <div class="performance-stat">
                                        <span class="performance-stat-value"><?php echo $technician['total_tickets']; ?></span>
                                        <span class="performance-stat-label">Total</span>
                                    </div>
                                    <div class="performance-stat">
                                        <span class="performance-stat-value"><?php echo $technician['tickets_resolved']; ?></span>
                                        <span class="performance-stat-label">Resolvidos</span>
                                    </div>
                                    <div class="performance-stat">
                                        <span class="performance-stat-value"><?php echo $technician['tickets_in_progress']; ?></span>
                                        <span class="performance-stat-label">Em Progresso</span>
                                    </div>
                                    <div class="performance-stat">
                                        <span class="performance-stat-value"><?php echo $successRate; ?>%</span>
                                        <span class="performance-stat-label">Taxa Sucesso</span>
                                    </div>
                                    <div class="performance-stat">
                                        <span class="performance-stat-value"><?php echo round($technician['avg_resolution_time'] ?? 0); ?>h</span>
                                        <span class="performance-stat-label">Tempo Médio</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="performance-card">
                    <div class="performance-header">
                        <div class="performance-title">
                            <i class="fas fa-building"></i>
                            Tickets por Empresa
                        </div>
                        <span class="performance-badge">Top 10</span>
                    </div>
                    
                    <?php if (empty($companiesStats)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-building fa-3x mb-3 opacity-25"></i>
                            <p class="text-muted">Nenhuma empresa encontrada</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead style="background: var(--bg-secondary);">
                                    <tr>
                                        <th style="border: none;">Empresa</th>
                                        <th class="text-center" style="border: none; width: 120px;">Total</th>
                                        <th class="text-center" style="border: none; width: 120px;">Em Aberto</th>
                                        <th class="text-center" style="border: none; width: 120px;">Prioridade Alta</th>
                                        <th class="text-center" style="border: none; width: 100px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($companiesStats as $company): ?>
                                        <?php
                                        $statusColor = 'success';
                                        if ($company['tickets_high_priority'] > 0) {
                                            $statusColor = 'danger';
                                        } elseif ($company['tickets_open'] > 5) {
                                            $statusColor = 'warning';
                                        }
                                        ?>
                                        <tr>
                                            <td style="border-color: var(--border-color);">
                                                <div class="d-flex align-items-center">
                                                    <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); color: white; display: flex; align-items: center; justify-content: center; margin-right: 0.75rem; font-weight: 700;">
                                                        <?php echo strtoupper(substr($company['name'], 0, 1)); ?>
                                                    </div>
                                                    <strong><?php echo htmlspecialchars($company['name']); ?></strong>
                                                </div>
                                            </td>
                                            <td class="text-center" style="border-color: var(--border-color);">
                                                <span class="badge bg-primary" style="font-size: 14px; padding: 0.5rem 1rem;">
                                                    <?php echo $company['total_tickets']; ?>
                                                </span>
                                            </td>
                                            <td class="text-center" style="border-color: var(--border-color);">
                                                <span class="badge bg-info" style="font-size: 14px; padding: 0.5rem 1rem;">
                                                    <?php echo $company['tickets_open']; ?>
                                                </span>
                                            </td>
                                            <td class="text-center" style="border-color: var(--border-color);">
                                                <?php if ($company['tickets_high_priority'] > 0): ?>
                                                    <span class="badge bg-danger" style="font-size: 14px; padding: 0.5rem 1rem;">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                                        <?php echo $company['tickets_high_priority']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center" style="border-color: var(--border-color);">
                                                <span class="badge bg-<?php echo $statusColor; ?>" style="font-size: 12px;">
                                                    <?php 
                                                    echo $statusColor == 'success' ? 'OK' : 
                                                         ($statusColor == 'warning' ? 'ATENÇÃO' : 'CRÍTICO');
                                                    ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="timeline-card">
                    <div class="timeline-header">
                        <i class="fas fa-history"></i>
                        Atividades Recentes do Sistema
                    </div>
                    
                    <?php if (empty($recentActivities)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                            <p class="text-muted">Nenhuma atividade recente</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="timeline-item">
                                <div class="timeline-content">
                                    <div class="timeline-title">
                                        <span class="badge bg-secondary me-2">#<?php echo $activity['id']; ?></span>
                                        <?php echo htmlspecialchars($activity['title']); ?>
                                    </div>
                                    <div class="timeline-text">
                                        <i class="fas fa-building me-1"></i>
                                        <?php echo htmlspecialchars($activity['company_name']); ?>
                                        <?php if ($activity['assigned_user_name']): ?>
                                            • <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($activity['assigned_user_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timeline-meta">
                                        <span>
                                            <span class="badge bg-<?php echo getStatusColor($activity['status']); ?> me-2">
                                                <?php echo getStatusLabel($activity['status']); ?>
                                            </span>
                                            <span class="badge bg-<?php echo getPriorityColor($activity['priority']); ?>">
                                                <?php echo getPriorityLabel($activity['priority']); ?>
                                            </span>
                                        </span>
                                        <span>
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo formatDate($activity['updated_at'], true); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
            </div>
            
        </div>
    </div>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
function getThemeColors() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    return {
        text: isDark ? '#e9ecef' : '#212529',
        grid: isDark ? '#343a40' : '#dee2e6',
        background: isDark ? '#1a1d23' : '#ffffff'
    };
}

const themeToggle = document.getElementById('theme-toggle');
if (themeToggle) {
    themeToggle.addEventListener('click', function() {
        setTimeout(() => {
            updateChartColors();
        }, 100);
    });
}

const statusChart = new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Criados', 'Assumidos', 'Despachados', 'Em Progresso', 'Resolvidos'],
        datasets: [{
            data: [
                <?php echo $chartStatus['created']; ?>,
                <?php echo $chartStatus['assumed']; ?>,
                <?php echo $chartStatus['dispatched']; ?>,
                <?php echo $chartStatus['in_progress']; ?>,
                <?php echo $chartStatus['resolved']; ?>
            ],
            backgroundColor: [
                'rgba(255, 193, 7, 0.8)',
                'rgba(13, 202, 240, 0.8)',
                'rgba(13, 110, 253, 0.8)',
                'rgba(255, 159, 64, 0.8)',
                'rgba(25, 135, 84, 0.8)'
            ],
            borderWidth: 3,
            borderColor: getThemeColors().background
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: getThemeColors().text,
                    padding: 15,
                    font: {
                        size: 12,
                        weight: 500
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                titleFont: {
                    size: 14,
                    weight: 600
                },
                bodyFont: {
                    size: 13
                },
                cornerRadius: 8
            }
        }
    }
});

const weekChart = new Chart(document.getElementById('weekChart'), {
    type: 'line',
    data: {
        labels: [<?php echo '"' . implode('","', array_column($last7Days, 'date')) . '"'; ?>],
        datasets: [{
            label: 'Tickets Criados',
            data: [<?php echo implode(',', array_column($last7Days, 'count')); ?>],
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            borderColor: 'rgba(102, 126, 234, 1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: 'rgba(102, 126, 234, 1)',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                titleFont: {
                    size: 14,
                    weight: 600
                },
                bodyFont: {
                    size: 13
                },
                cornerRadius: 8
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    color: getThemeColors().text,
                    stepSize: 1
                },
                grid: {
                    color: getThemeColors().grid
                }
            },
            x: {
                ticks: {
                    color: getThemeColors().text
                },
                grid: {
                    display: false
                }
            }
        }
    }
});

function updateChartColors() {
    const colors = getThemeColors();
    
    statusChart.options.plugins.legend.labels.color = colors.text;
    statusChart.data.datasets[0].borderColor = colors.background;
    statusChart.update();
    
    weekChart.options.scales.y.ticks.color = colors.text;
    weekChart.options.scales.y.grid.color = colors.grid;
    weekChart.options.scales.x.ticks.color = colors.text;
    weekChart.update();
}

document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.stat-card, .performance-card, .chart-card, .timeline-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 50);
    });
    
    console.log('✅ Dashboard Admin carregado com sucesso!');
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>