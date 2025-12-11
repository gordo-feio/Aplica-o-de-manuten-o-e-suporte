<?php
/**
 * Página: Minhas Ordens de Serviço
 * Técnico vê todas as OS que aceitou (como primary ou support)
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(__DIR__)) . '/');
}

require_once ROOT_PATH . 'config/config.php';
require_once ROOT_PATH . 'config/paths.php';
require_once ROOT_PATH . 'includes/functions.php';

requireUser();

$pageTitle = 'Minhas OS - ' . SYSTEM_NAME;

include ROOT_PATH . 'includes/header.php';
?>

<style>
.stats-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    text-align: center;
    transition: transform 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-4px);
}

.stats-value {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0.5rem 0;
}

.stats-label {
    color: #6c757d;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.os-table-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
}

.table-header {
    background: #f8f9fa;
    padding: 1.5rem;
    border-bottom: 2px solid #dee2e6;
}

.filter-tabs {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 0.5rem 1rem;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
}

.filter-tab:hover {
    border-color: #0d6efd;
}

.filter-tab.active {
    background: #0d6efd;
    color: white;
    border-color: #0d6efd;
}

.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
</style>

<div class="container-fluid py-4">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">
                <i class="fas fa-clipboard-list text-primary me-2"></i>
                Minhas Ordens de Serviço
            </h2>
            <p class="text-muted mb-0">
                Gerencie as OS que você aceitou
            </p>
        </div>
        <div>
            <a href="<?php echo BASE_URL; ?>views/work_orders/available.php" class="btn btn-primary">
                <i class="fas fa-search me-2"></i>Buscar OS Disponíveis
            </a>
        </div>
    </div>
    
    <!-- Estatísticas -->
    <div class="row g-4 mb-4" id="statsContainer">
        <div class="col-md-6 col-lg-3">
            <div class="stats-card">
                <i class="fas fa-list-check" style="font-size: 2rem; color: #0d6efd;"></i>
                <div class="stats-value text-primary" id="statTotal">-</div>
                <div class="stats-label">Total</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stats-card">
                <i class="fas fa-cog fa-spin" style="font-size: 2rem; color: #ffc107;"></i>
                <div class="stats-value text-warning" id="statInProgress">-</div>
                <div class="stats-label">Em Andamento</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stats-card">
                <i class="fas fa-check-circle" style="font-size: 2rem; color: #198754;"></i>
                <div class="stats-value text-success" id="statCompleted">-</div>
                <div class="stats-label">Concluídas</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stats-card">
                <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #dc3545;"></i>
                <div class="stats-value text-danger" id="statOverdue">-</div>
                <div class="stats-label">Atrasadas</div>
            </div>
        </div>
    </div>
    
    <!-- Tabela de OS -->
    <div class="os-table-card">
        
        <!-- Header com Filtros -->
        <div class="table-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h5 class="mb-0">Suas Ordens de Serviço</h5>
                <div class="filter-tabs">
                    <button class="filter-tab active" data-status="">Todas</button>
                    <button class="filter-tab" data-status="in_progress">Em Andamento</button>
                    <button class="filter-tab" data-status="completed">Concluídas</button>
                </div>
            </div>
        </div>
        
        <!-- Loading -->
        <div id="loadingState" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-3 text-muted">Carregando suas OS...</p>
        </div>
        
        <!-- Tabela -->
        <div id="tableContainer" style="display: none;">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 100px;">Código</th>
                            <th>Ticket</th>
                            <th style="width: 180px;">Empresa</th>
                            <th style="width: 120px;">Status</th>
                            <th style="width: 120px;">Meu Papel</th>
                            <th style="width: 140px;">Prazo</th>
                            <th class="text-center" style="width: 100px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="osTableBody">
                        <!-- Preenchido via JS -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Empty State -->
        <div id="emptyState" class="text-center py-5" style="display: none;">
            <i class="fas fa-clipboard-check" style="font-size: 64px; color: #dee2e6;"></i>
            <h4 class="mt-3">Nenhuma OS encontrada</h4>
            <p class="text-muted">
                Você ainda não aceitou nenhuma ordem de serviço.<br>
                Busque por OS disponíveis para começar.
            </p>
            <a href="<?php echo BASE_URL; ?>views/work_orders/available.php" class="btn btn-primary mt-3">
                <i class="fas fa-search me-2"></i>Buscar OS Disponíveis
            </a>
        </div>
        
    </div>
    
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let currentStatus = '';
let allOrders = [];

$(document).ready(function() {
    loadMyOrders();
    setupFilters();
});

// Carregar Minhas OS
function loadMyOrders(status = '') {
    $('#loadingState').show();
    $('#tableContainer').hide();
    $('#emptyState').hide();
    
    const url = status ? 
        `<?php echo BASE_URL; ?>ajax/get_my_work_orders.php?status=${status}` : 
        '<?php echo BASE_URL; ?>ajax/get_my_work_orders.php';
    
    $.ajax({
        url: url,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            $('#loadingState').hide();
            
            if (response.success) {
                allOrders = response.work_orders;
                
                if (response.count > 0) {
                    renderTable(response.work_orders);
                    updateStats(response.stats);
                    $('#tableContainer').show();
                } else {
                    $('#emptyState').show();
                }
            } else {
                alert('Erro ao carregar suas OS');
            }
        },
        error: function(xhr) {
            $('#loadingState').hide();
            alert('Erro ao carregar OS');
            console.error(xhr);
        }
    });
}

// Renderizar Tabela
function renderTable(orders) {
    const tbody = $('#osTableBody');
    tbody.empty();
    
    if (orders.length === 0) {
        $('#tableContainer').hide();
        $('#emptyState').show();
        return;
    }
    
    orders.forEach(function(os) {
        const overdueClass = os.is_overdue ? 'table-danger' : '';
        const overdueLabel = os.is_overdue ? '<span class="badge bg-danger ms-1">ATRASADA</span>' : '';
        
        const row = `
            <tr class="${overdueClass}">
                <td>
                    <strong>${os.code}</strong>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-${os.priority_color}">${os.priority_label}</span>
                        <span>${os.ticket_title}</span>
                    </div>
                </td>
                <td>
                    <small>
                        <i class="fas fa-building me-1"></i>
                        ${os.company_name}
                    </small>
                </td>
                <td>
                    <span class="badge bg-${os.status_color}">
                        <i class="fas ${os.status_icon} me-1"></i>
                        ${os.status_label}
                    </span>
                </td>
                <td>
                    <span class="role-badge">
                        ${os.is_primary ? 
                            '<span class="badge bg-warning"><i class="fas fa-star me-1"></i>Principal</span>' : 
                            '<span class="badge bg-secondary"><i class="fas fa-user-friends me-1"></i>Suporte</span>'}
                    </span>
                    ${os.is_completed ? '<br><small class="text-success"><i class="fas fa-check me-1"></i>Finalizado</small>' : ''}
                </td>
                <td>
                    <small>
                        ${os.time_remaining}
                        ${overdueLabel}
                    </small>
                </td>
                <td class="text-center">
                    <a href="<?php echo BASE_URL; ?>views/work_orders/view.php?id=${os.id}" 
                       class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye"></i>
                    </a>
                </td>
            </tr>
        `;
        
        tbody.append(row);
    });
}

// Atualizar Estatísticas
function updateStats(stats) {
    $('#statTotal').text(stats.total);
    $('#statInProgress').text(stats.in_progress);
    $('#statCompleted').text(stats.completed);
    $('#statOverdue').text(stats.overdue);
}

// Setup de Filtros
function setupFilters() {
    $('.filter-tab').click(function() {
        $('.filter-tab').removeClass('active');
        $(this).addClass('active');
        
        const status = $(this).data('status');
        currentStatus = status;
        loadMyOrders(status);
    });
}
</script>

<?php include ROOT_PATH . 'includes/footer.php'; ?>