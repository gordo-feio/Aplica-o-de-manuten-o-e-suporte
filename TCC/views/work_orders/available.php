<?php
/**
 * Página: Ordens de Serviço Disponíveis
 * Técnicos veem OS que podem aceitar (primeiro a clicar leva)
 */

// Define ROOT_PATH se não estiver definido
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(__DIR__)) . '/');
}

require_once ROOT_PATH . 'config/config.php';
require_once ROOT_PATH . 'config/paths.php';
require_once ROOT_PATH . 'includes/functions.php';

// Apenas técnicos e admins
requireUser();

$pageTitle = 'OS Disponíveis - ' . SYSTEM_NAME;

// Incluir header
include ROOT_PATH . 'includes/header.php';
?>

<style>
.os-card {
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
    height: 100%;
}

.os-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}

.os-card.priority-high { border-left-color: #dc3545; }
.os-card.priority-medium { border-left-color: #ffc107; }
.os-card.priority-low { border-left-color: #198754; }

.os-card.overdue {
    border: 2px solid #dc3545;
    background: #fff5f5;
}

.deadline-badge {
    font-size: 0.875rem;
    padding: 0.5rem 0.75rem;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state i {
    font-size: 64px;
    opacity: 0.3;
    margin-bottom: 1.5rem;
}

.filter-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.badge-pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.auto-refresh-badge {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
    padding: 0.75rem 1rem;
    background: #28a745;
    color: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
</style>

<div class="container-fluid py-4">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">
                <i class="fas fa-clipboard-list text-primary me-2"></i>
                Ordens de Serviço Disponíveis
            </h2>
            <p class="text-muted mb-0">
                Aceite uma OS para se tornar o técnico principal
            </p>
        </div>
        <div>
            <a href="<?php echo BASE_URL; ?>views/work_orders/my_orders.php" class="btn btn-outline-primary">
                <i class="fas fa-user-check me-2"></i>Minhas OS
            </a>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="filter-card">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label small fw-bold">Prioridade</label>
                <select id="filterPriority" class="form-select">
                    <option value="">Todas as prioridades</option>
                    <option value="high">Alta</option>
                    <option value="medium">Média</option>
                    <option value="low">Baixa</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold">Buscar</label>
                <input type="text" id="searchOS" class="form-control" placeholder="Buscar por título ou empresa...">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold">Atualização Automática</label>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" id="autoRefresh" checked>
                    <label class="form-check-label" for="autoRefresh">
                        A cada 30 segundos
                    </label>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contador de OS -->
    <div class="alert alert-info d-flex align-items-center mb-4" id="osCounter" style="display: none !important;">
        <i class="fas fa-info-circle me-2"></i>
        <span id="counterText">Carregando...</span>
    </div>
    
    <!-- Loading -->
    <div id="loadingState" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Carregando...</span>
        </div>
        <p class="mt-3 text-muted">Buscando OS disponíveis...</p>
    </div>
    
    <!-- Lista de OS -->
    <div id="osList" class="row g-4" style="display: none;">
        <!-- Cards serão inseridos aqui via JavaScript -->
    </div>
    
    <!-- Empty State -->
    <div id="emptyState" class="empty-state" style="display: none;">
        <i class="fas fa-clipboard-check text-success"></i>
        <h4>Nenhuma OS disponível</h4>
        <p class="text-muted">
            Todas as ordens de serviço foram aceitas.<br>
            Aguarde novas OS ou verifique suas OS em andamento.
        </p>
        <a href="<?php echo BASE_URL; ?>views/work_orders/my_orders.php" class="btn btn-primary mt-3">
            <i class="fas fa-list me-2"></i>Ver Minhas OS
        </a>
    </div>
    
</div>

<!-- Badge de Auto-Refresh -->
<div id="autoRefreshBadge" class="auto-refresh-badge" style="display: none;">
    <i class="fas fa-sync-alt fa-spin me-2"></i>
    Atualizando...
</div>

<!-- Modal de Confirmação -->
<div class="modal fade" id="acceptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-hand-paper text-warning me-2"></i>
                    Aceitar Ordem de Serviço
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Atenção!</strong> Ao aceitar esta OS, você será o técnico principal responsável.
                </div>
                <div id="osDetails">
                    <!-- Detalhes da OS -->
                </div>
                <p class="text-muted mb-0 small">
                    <i class="fas fa-info-circle me-1"></i>
                    Você poderá adicionar técnicos de suporte depois.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="confirmAccept">
                    <i class="fas fa-check me-2"></i>Confirmar Aceitação
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let selectedWorkOrderId = null;
let autoRefreshInterval = null;

$(document).ready(function() {
    loadAvailableOrders();
    setupAutoRefresh();
    setupFilters();
});

// Carregar OS Disponíveis
function loadAvailableOrders() {
    $('#loadingState').show();
    $('#osList').hide();
    $('#emptyState').hide();
    $('#osCounter').hide();
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>ajax/get_available_work_orders.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            $('#loadingState').hide();
            
            if (response.success && response.count > 0) {
                renderOrders(response.work_orders);
                $('#osCounter').show();
                $('#counterText').html(`<strong>${response.count}</strong> OS disponível(is) para aceitar`);
            } else {
                $('#emptyState').show();
            }
        },
        error: function(xhr) {
            $('#loadingState').hide();
            alert('Erro ao carregar OS disponíveis');
            console.error(xhr);
        }
    });
}

// Renderizar Cards de OS
function renderOrders(orders) {
    const container = $('#osList');
    container.empty();
    
    orders.forEach(function(os) {
        const overdueClass = os.is_overdue ? 'overdue' : '';
        const overdueLabel = os.is_overdue ? '<span class="badge bg-danger ms-2">ATRASADA</span>' : '';
        
        const card = `
            <div class="col-md-6 col-lg-4 os-item" 
                 data-priority="${os.priority}" 
                 data-title="${os.ticket_title.toLowerCase()}"
                 data-company="${os.company_name.toLowerCase()}">
                <div class="card os-card priority-${os.priority} ${overdueClass}">
                    <div class="card-body">
                        
                        <!-- Header -->
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="badge bg-secondary">${os.code}</span>
                            <span class="badge bg-${os.priority_color}">
                                <i class="fas fa-arrow-up me-1"></i>${os.priority_label}
                            </span>
                        </div>
                        
                        <!-- Título -->
                        <h5 class="card-title mb-2">${os.ticket_title}</h5>
                        
                        <!-- Empresa -->
                        <p class="text-muted mb-3">
                            <i class="fas fa-building me-1"></i>
                            <strong>${os.company_name}</strong>
                        </p>
                        
                        <!-- Endereço -->
                        ${os.company_address ? `
                        <div class="alert alert-light py-2 px-3 mb-3">
                            <small>
                                <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                ${os.company_address}
                            </small>
                        </div>
                        ` : ''}
                        
                        <!-- Prazo -->
                        <div class="deadline-badge alert ${os.is_overdue ? 'alert-danger' : 'alert-warning'} mb-3">
                            <i class="fas fa-clock me-1"></i>
                            <strong>Prazo:</strong> ${os.time_remaining}
                            ${overdueLabel}
                        </div>
                        
                        <!-- Info adicional -->
                        <div class="small text-muted mb-3">
                            <i class="fas fa-calendar me-1"></i>
                            Criada ${os.elapsed_time}
                        </div>
                        
                        <!-- Botões -->
                        <div class="d-flex gap-2">
                            <button class="btn btn-success flex-grow-1" onclick="showAcceptModal(${os.id})">
                                <i class="fas fa-hand-paper me-1"></i>Aceitar OS
                            </button>
                            <a href="<?php echo BASE_URL; ?>views/tickets/view.php?id=${os.ticket_id}" 
                               class="btn btn-outline-primary" target="_blank">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                        
                    </div>
                </div>
            </div>
        `;
        
        container.append(card);
    });
    
    container.show();
}

// Mostrar Modal de Aceitar
function showAcceptModal(workOrderId) {
    selectedWorkOrderId = workOrderId;
    
    // Buscar detalhes
    $.ajax({
        url: '<?php echo BASE_URL; ?>ajax/get_work_order_details.php',
        method: 'GET',
        data: { id: workOrderId },
        success: function(response) {
            if (response.success) {
                const os = response.work_order;
                $('#osDetails').html(`
                    <div class="mb-3">
                        <strong>OS:</strong> ${os.code}<br>
                        <strong>Ticket:</strong> ${os.ticket_title}<br>
                        <strong>Empresa:</strong> ${os.company_name}<br>
                        <strong>Prioridade:</strong> 
                        <span class="badge bg-${os.priority_color}">${os.priority_label}</span><br>
                        <strong>Prazo:</strong> ${os.deadline_formatted}
                    </div>
                `);
                
                $('#acceptModal').modal('show');
            }
        }
    });
}

// Confirmar Aceitação
$('#confirmAccept').click(function() {
    if (!selectedWorkOrderId) return;
    
    const btn = $(this);
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Aceitando...');
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>ajax/accept_work_order.php',
        method: 'POST',
        data: {
            work_order_id: selectedWorkOrderId,
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        },
        success: function(response) {
            if (response.success) {
                $('#acceptModal').modal('hide');
                
                // Mostrar sucesso e redirecionar
                Swal.fire({
                    icon: 'success',
                    title: 'OS Aceita!',
                    text: response.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(function() {
                    window.location.href = '<?php echo BASE_URL; ?>views/work_orders/view.php?id=' + selectedWorkOrderId;
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: response.message
                });
                btn.prop('disabled', false).html('<i class="fas fa-check me-2"></i>Confirmar Aceitação');
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Erro ao aceitar OS. Tente novamente.'
            });
            btn.prop('disabled', false).html('<i class="fas fa-check me-2"></i>Confirmar Aceitação');
        }
    });
});

// Auto Refresh
function setupAutoRefresh() {
    $('#autoRefresh').change(function() {
        if ($(this).is(':checked')) {
            autoRefreshInterval = setInterval(function() {
                $('#autoRefreshBadge').fadeIn();
                loadAvailableOrders();
                setTimeout(function() {
                    $('#autoRefreshBadge').fadeOut();
                }, 1000);
            }, 30000); // 30 segundos
        } else {
            clearInterval(autoRefreshInterval);
        }
    });
    
    // Iniciar automaticamente
    if ($('#autoRefresh').is(':checked')) {
        autoRefreshInterval = setInterval(function() {
            $('#autoRefreshBadge').fadeIn();
            loadAvailableOrders();
            setTimeout(function() {
                $('#autoRefreshBadge').fadeOut();
            }, 1000);
        }, 30000);
    }
}

// Filtros
function setupFilters() {
    $('#filterPriority, #searchOS').on('change keyup', function() {
        filterOrders();
    });
}

function filterOrders() {
    const priority = $('#filterPriority').val().toLowerCase();
    const search = $('#searchOS').val().toLowerCase();
    
    $('.os-item').each(function() {
        const item = $(this);
        const itemPriority = item.data('priority');
        const itemTitle = item.data('title');
        const itemCompany = item.data('company');
        
        let showPriority = !priority || itemPriority === priority;
        let showSearch = !search || itemTitle.includes(search) || itemCompany.includes(search);
        
        if (showPriority && showSearch) {
            item.show();
        } else {
            item.hide();
        }
    });
    
    // Verificar se há itens visíveis
    const visibleCount = $('.os-item:visible').length;
    if (visibleCount === 0) {
        $('#emptyState').show();
        $('#osCounter').hide();
    } else {
        $('#emptyState').hide();
        $('#osCounter').show();
        $('#counterText').html(`<strong>${visibleCount}</strong> OS disponível(is)`);
    }
}
</script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php include ROOT_PATH . 'includes/footer.php'; ?>