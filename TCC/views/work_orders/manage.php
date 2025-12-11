<?php
/**
 * Página: Gerenciar Ordens de Serviço
 * Admin e Atendentes veem todas as OS do sistema
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(__DIR__)) . '/');
}

require_once ROOT_PATH . 'config/config.php';
require_once ROOT_PATH . 'config/paths.php';
require_once ROOT_PATH . 'includes/functions.php';

// Apenas admin e atendentes
requireUser();
if (!isAdmin() && !isAttendant()) {
    setFlashMessage('Acesso negado', 'danger');
    redirect(BASE_URL . 'views/dashboard/index.php');
}

$pageTitle = 'Gerenciar OS - ' . SYSTEM_NAME;
$pdo = getConnection();

// Buscar estatísticas gerais
$stats = [
    'available' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'overdue' => 0,
];

$stmt = $pdo->query("SELECT COUNT(*) FROM work_orders WHERE status = 'available'");
$stats['available'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM work_orders WHERE status = 'in_progress'");
$stats['in_progress'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM work_orders WHERE status = 'completed'");
$stats['completed'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM work_orders WHERE status = 'in_progress' AND deadline < NOW()");
$stats['overdue'] = $stmt->fetchColumn();

include ROOT_PATH . 'includes/header.php';
?>

<style>
.admin-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-box {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    text-align: center;
}

.stat-box i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0.5rem 0;
}

.management-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
}

.card-header-custom {
    background: #f8f9fa;
    padding: 1.5rem;
    border-bottom: 2px solid #dee2e6;
}

.filter-section {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.os-row {
    transition: all 0.2s ease;
}

.os-row:hover {
    background: #f8f9fa;
}

.os-row.overdue {
    background: #fff5f5;
    border-left: 4px solid #dc3545;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.quick-stats {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.quick-stat {
    padding: 0.5rem 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    font-weight: 500;
}
</style>

<div class="container-fluid py-4">
    
    <!-- Header Admin -->
    <div class="admin-header">
        <h2 class="mb-2">
            <i class="fas fa-tasks me-2"></i>
            Gerenciamento de Ordens de Serviço
        </h2>
        <p class="mb-0 opacity-75">
            Painel completo de controle e monitoramento de OS
        </p>
    </div>
    
    <!-- Estatísticas -->
    <div class="stats-grid">
        <div class="stat-box">
            <i class="fas fa-clipboard-list text-info"></i>
            <div class="stat-value text-info"><?php echo $stats['available']; ?></div>
            <div class="text-muted">Disponíveis</div>
        </div>
        <div class="stat-box">
            <i class="fas fa-cog fa-spin text-warning"></i>
            <div class="stat-value text-warning"><?php echo $stats['in_progress']; ?></div>
            <div class="text-muted">Em Andamento</div>
        </div>
        <div class="stat-box">
            <i class="fas fa-check-circle text-success"></i>
            <div class="stat-value text-success"><?php echo $stats['completed']; ?></div>
            <div class="text-muted">Concluídas</div>
        </div>
        <div class="stat-box">
            <i class="fas fa-exclamation-triangle text-danger"></i>
            <div class="stat-value text-danger"><?php echo $stats['overdue']; ?></div>
            <div class="text-muted">Atrasadas</div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="filter-section">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label small fw-bold">Status</label>
                <select id="filterStatus" class="form-select">
                    <option value="">Todos os Status</option>
                    <option value="available">Disponível</option>
                    <option value="in_progress">Em Andamento</option>
                    <option value="completed">Concluída</option>
                    <option value="cancelled">Cancelada</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Prioridade</label>
                <select id="filterPriority" class="form-select">
                    <option value="">Todas</option>
                    <option value="high">Alta</option>
                    <option value="medium">Média</option>
                    <option value="low">Baixa</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold">Buscar</label>
                <input type="text" id="searchBox" class="form-control" placeholder="Ticket, empresa, técnico...">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">&nbsp;</label>
                <button id="btnClearFilters" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-redo me-1"></i>Limpar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Tabela de OS -->
    <div class="management-card">
        
        <div class="card-header-custom">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Todas as Ordens de Serviço</h5>
                <div class="quick-stats">
                    <span class="quick-stat" id="displayCount">0 OS</span>
                    <button class="btn btn-sm btn-primary" onclick="loadOrders()">
                        <i class="fas fa-sync-alt me-1"></i>Atualizar
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Loading -->
        <div id="loadingState" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-3 text-muted">Carregando OS...</p>
        </div>
        
        <!-- Tabela -->
        <div id="tableContainer" style="display: none;">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th>Ticket / Empresa</th>
                            <th style="width: 150px;">Técnico(s)</th>
                            <th style="width: 100px;">Status</th>
                            <th style="width: 100px;">Prioridade</th>
                            <th style="width: 140px;">Prazo</th>
                            <th style="width: 120px;">Criada</th>
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
            <i class="fas fa-inbox" style="font-size: 64px; color: #dee2e6;"></i>
            <h4 class="mt-3">Nenhuma OS encontrada</h4>
            <p class="text-muted">Não há ordens de serviço com os filtros selecionados.</p>
        </div>
        
    </div>
    
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let allOrders = [];
let filteredOrders = [];

$(document).ready(function() {
    loadOrders();
    setupFilters();
});

// Carregar Todas as OS
function loadOrders() {
    $('#loadingState').show();
    $('#tableContainer').hide();
    $('#emptyState').hide();
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>ajax/get_all_work_orders.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            $('#loadingState').hide();
            
            if (response.success) {
                allOrders = response.work_orders || [];
                filteredOrders = allOrders;
                
                if (allOrders.length > 0) {
                    renderTable(allOrders);
                    $('#tableContainer').show();
                } else {
                    $('#emptyState').show();
                }
                
                updateDisplayCount(allOrders.length);
            } else {
                $('#emptyState').show();
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
        const overdueClass = os.is_overdue ? 'os-row overdue' : 'os-row';
        const overdueLabel = os.is_overdue ? '<span class="badge bg-danger ms-1">ATRASADA</span>' : '';
        
        // Montar lista de técnicos (simplificado)
        const techList = os.technician_count > 0 ? 
            `<small><i class="fas fa-users me-1"></i>${os.technician_count} técnico(s)</small>` : 
            '<small class="text-muted">Nenhum</small>';
        
        const row = `
            <tr class="${overdueClass}">
                <td>
                    <strong>${os.code}</strong>
                </td>
                <td>
                    <div>
                        <strong>${os.ticket_title}</strong>
                    </div>
                    <small class="text-muted">
                        <i class="fas fa-building me-1"></i>${os.company_name}
                    </small>
                </td>
                <td>${techList}</td>
                <td>
                    <span class="badge bg-${os.status_color}">
                        <i class="fas ${os.status_icon} me-1"></i>
                        ${os.status_label}
                    </span>
                </td>
                <td>
                    <span class="badge bg-${os.priority_color}">
                        ${os.priority_label}
                    </span>
                </td>
                <td>
                    <small>
                        ${os.time_remaining}
                        ${overdueLabel}
                    </small>
                </td>
                <td>
                    <small class="text-muted">
                        ${os.elapsed_time}
                    </small>
                </td>
                <td class="text-center">
                    <div class="action-buttons justify-content-center">
                        <a href="<?php echo BASE_URL; ?>views/work_orders/view.php?id=${os.id}" 
                           class="btn btn-sm btn-outline-primary" title="Ver Detalhes">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>views/tickets/view.php?id=${os.ticket_id}" 
                           class="btn btn-sm btn-outline-secondary" title="Ver Ticket" target="_blank">
                            <i class="fas fa-ticket-alt"></i>
                        </a>
                    </div>
                </td>
            </tr>
        `;
        
        tbody.append(row);
    });
}

// Atualizar Contador
function updateDisplayCount(count) {
    $('#displayCount').text(count + ' OS');
}

// Configurar Filtros
function setupFilters() {
    $('#filterStatus, #filterPriority, #searchBox').on('change keyup', function() {
        applyFilters();
    });
    
    $('#btnClearFilters').click(function() {
        $('#filterStatus').val('');
        $('#filterPriority').val('');
        $('#searchBox').val('');
        applyFilters();
    });
}

// Aplicar Filtros
function applyFilters() {
    const status = $('#filterStatus').val().toLowerCase();
    const priority = $('#filterPriority').val().toLowerCase();
    const search = $('#searchBox').val().toLowerCase();
    
    filteredOrders = allOrders.filter(function(os) {
        const matchStatus = !status || os.status === status;
        const matchPriority = !priority || os.priority === priority;
        const matchSearch = !search || 
            os.ticket_title.toLowerCase().includes(search) ||
            os.company_name.toLowerCase().includes(search) ||
            os.code.toLowerCase().includes(search);
        
        return matchStatus && matchPriority && matchSearch;
    });
    
    renderTable(filteredOrders);
    updateDisplayCount(filteredOrders.length);
    
    if (filteredOrders.length > 0) {
        $('#tableContainer').show();
        $('#emptyState').hide();
    } else {
        $('#tableContainer').hide();
        $('#emptyState').show();
    }
}
</script>

<?php include ROOT_PATH . 'includes/footer.php'; ?>