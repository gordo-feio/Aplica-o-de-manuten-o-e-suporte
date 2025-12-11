<?php
/**
 * Página: Detalhes da Ordem de Serviço
 * Visualização completa com equipe, logs e ações
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(__DIR__)) . '/');
}

require_once ROOT_PATH . 'config/config.php';
require_once ROOT_PATH . 'config/paths.php';
require_once ROOT_PATH . 'includes/functions.php';

requireLogin();

$workOrderId = (int)($_GET['id'] ?? 0);

if (!$workOrderId) {
    setFlashMessage('OS inválida', 'danger');
    redirect(BASE_URL . 'views/dashboard/index.php');
}

$pageTitle = 'OS #' . $workOrderId . ' - ' . SYSTEM_NAME;

include ROOT_PATH . 'includes/header.php';
?>

<style>
.os-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
}

.info-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    height: 100%;
}

.team-member {
    background: #f8f9fa;
    border-left: 4px solid #0d6efd;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.team-member.primary {
    border-left-color: #ffc107;
    background: #fffbf0;
}

.log-item {
    border-left: 3px solid #dee2e6;
    padding: 1rem;
    margin-bottom: 1rem;
    background: #f8f9fa;
    border-radius: 0 8px 8px 0;
}

.log-item:hover {
    background: #e9ecef;
}

.action-btn {
    margin-bottom: 0.5rem;
}
</style>

<div class="container-fluid py-4">
    
    <!-- Loading inicial -->
    <div id="loadingState" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-3 text-muted">Carregando detalhes da OS...</p>
    </div>
    
    <!-- Conteúdo principal -->
    <div id="osContent" style="display: none;">
        
        <!-- Header da OS -->
        <div class="os-header" id="osHeader">
            <!-- Será preenchido via JS -->
        </div>
        
        <div class="row g-4">
            
            <!-- Coluna Esquerda: Informações e Equipe -->
            <div class="col-lg-8">
                
                <!-- Informações do Ticket -->
                <div class="info-card mb-4">
                    <h5 class="mb-3">
                        <i class="fas fa-ticket-alt text-primary me-2"></i>
                        Informações do Ticket
                    </h5>
                    <div id="ticketInfo">
                        <!-- Preenchido via JS -->
                    </div>
                </div>
                
                <!-- Equipe -->
                <div class="info-card mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-users text-primary me-2"></i>
                            Equipe
                        </h5>
                        <button class="btn btn-sm btn-outline-primary" id="btnAddTechnician" style="display: none;">
                            <i class="fas fa-user-plus me-1"></i>Adicionar Técnico
                        </button>
                    </div>
                    <div id="teamList">
                        <!-- Preenchido via JS -->
                    </div>
                </div>
                
                <!-- Histórico/Logs -->
                <div class="info-card">
                    <h5 class="mb-3">
                        <i class="fas fa-history text-primary me-2"></i>
                        Histórico de Atividades
                    </h5>
                    <div id="logsList">
                        <!-- Preenchido via JS -->
                    </div>
                </div>
                
            </div>
            
            <!-- Coluna Direita: Ações -->
            <div class="col-lg-4">
                
                <div class="info-card">
                    <h5 class="mb-3">
                        <i class="fas fa-cogs text-primary me-2"></i>
                        Ações
                    </h5>
                    
                    <div id="actionButtons">
                        <!-- Botões serão inseridos via JS -->
                    </div>
                    
                    <!-- Informações Adicionais -->
                    <hr>
                    <div id="additionalInfo">
                        <!-- Info adicional -->
                    </div>
                </div>
                
            </div>
            
        </div>
        
    </div>
    
</div>

<!-- Modal: Adicionar Técnico -->
<div class="modal fade" id="addTechnicianModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adicionar Técnico de Suporte</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Selecione o Técnico</label>
                    <select class="form-select" id="selectTechnician">
                        <option value="">Carregando...</option>
                    </select>
                </div>
                <div class="alert alert-info small">
                    <i class="fas fa-info-circle me-1"></i>
                    O técnico será adicionado como <strong>suporte</strong>.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confirmAddTechnician">
                    <i class="fas fa-plus me-1"></i>Adicionar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Concluir OS -->
<div class="modal fade" id="completeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Concluir Ordem de Serviço</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Observações Finais (opcional)</label>
                    <textarea class="form-control" id="completionNotes" rows="4" 
                              placeholder="Descreva o que foi realizado..."></textarea>
                </div>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    Ao confirmar, sua parte na OS será marcada como concluída.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="confirmComplete">
                    <i class="fas fa-check me-1"></i>Confirmar Conclusão
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Cancelar OS -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Cancelar Ordem de Serviço</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Esta ação não pode ser desfeita!
                </div>
                <div class="mb-3">
                    <label class="form-label">Motivo do Cancelamento *</label>
                    <textarea class="form-control" id="cancelReason" rows="3" required
                              placeholder="Informe o motivo do cancelamento..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Voltar</button>
                <button type="button" class="btn btn-danger" id="confirmCancel">
                    <i class="fas fa-times me-1"></i>Confirmar Cancelamento
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const workOrderId = <?php echo $workOrderId; ?>;
let osData = null;

$(document).ready(function() {
    loadWorkOrderDetails();
    loadTechnicians();
});

// Carregar Detalhes
function loadWorkOrderDetails() {
    $.ajax({
        url: '<?php echo BASE_URL; ?>ajax/get_work_order_details.php',
        method: 'GET',
        data: { id: workOrderId },
        success: function(response) {
            if (response.success) {
                osData = response;
                renderWorkOrder(response);
                $('#loadingState').hide();
                $('#osContent').fadeIn();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: response.message
                }).then(() => {
                    window.location.href = '<?php echo BASE_URL; ?>views/dashboard/index.php';
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Erro ao carregar OS'
            });
        }
    });
}

// Renderizar OS
function renderWorkOrder(data) {
    const os = data.work_order;
    const team = data.team;
    const logs = data.logs;
    
    // Header
    const overdueLabel = os.is_overdue ? '<span class="badge bg-danger ms-2">ATRASADA</span>' : '';
    $('#osHeader').html(`
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-2">${os.code} ${overdueLabel}</h2>
                <h4 class="mb-3">${os.ticket_title}</h4>
                <div class="d-flex gap-3 flex-wrap">
                    <span class="badge bg-light text-dark">
                        <i class="fas ${os.status_icon} me-1"></i>${os.status_label}
                    </span>
                    <span class="badge bg-${os.priority_color}">
                        ${os.priority_label}
                    </span>
                    <span class="badge bg-warning text-dark">
                        <i class="fas fa-clock me-1"></i>${os.time_remaining}
                    </span>
                </div>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="<?php echo BASE_URL; ?>views/tickets/view.php?id=${os.ticket_id}" 
                   class="btn btn-light" target="_blank">
                    <i class="fas fa-ticket-alt me-1"></i>Ver Ticket
                </a>
            </div>
        </div>
    `);
    
    // Ticket Info
    $('#ticketInfo').html(`
        <div class="row g-3">
            <div class="col-md-6">
                <strong>Empresa:</strong><br>
                <i class="fas fa-building text-muted me-1"></i>${os.company_name}
            </div>
            <div class="col-md-6">
                <strong>Endereço:</strong><br>
                <i class="fas fa-map-marker-alt text-danger me-1"></i>${os.company_address || 'Não informado'}
            </div>
            <div class="col-md-6">
                <strong>Telefone:</strong><br>
                <i class="fas fa-phone text-success me-1"></i>${os.company_phone || 'Não informado'}
            </div>
            <div class="col-md-6">
                <strong>Prazo:</strong><br>
                <i class="fas fa-calendar text-warning me-1"></i>${os.deadline_formatted}
            </div>
            <div class="col-12">
                <strong>Descrição:</strong><br>
                <p class="text-muted mb-0">${os.ticket_description}</p>
            </div>
        </div>
    `);
    
    // Equipe
    renderTeam(team, data.can_add_technician);
    
    // Logs
    renderLogs(logs);
    
    // Botões de Ação
    renderActionButtons(os, team);
}

// Renderizar Equipe
function renderTeam(team, canAdd) {
    let html = '';
    
    if (team.length === 0) {
        html = '<p class="text-muted">Nenhum técnico na equipe ainda.</p>';
    } else {
        team.forEach(member => {
            const primaryClass = member.role === 'primary' ? 'primary' : '';
            const statusBadge = `<span class="badge bg-${member.status_color}">${member.status_label}</span>`;
            
            html += `
                <div class="team-member ${primaryClass}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <strong>${member.technician_name}</strong>
                                <span class="badge bg-${member.role_color}">
                                    <i class="fas ${member.role_icon} me-1"></i>${member.role_label}
                                </span>
                                ${statusBadge}
                            </div>
                            <div class="small text-muted">
                                <i class="fas fa-envelope me-1"></i>${member.technician_email}<br>
                                ${member.technician_phone ? `<i class="fas fa-phone me-1"></i>${member.technician_phone}<br>` : ''}
                                <i class="fas fa-clock me-1"></i>Aceitou ${member.accepted_at_formatted}
                            </div>
                            ${member.notes ? `
                                <div class="mt-2 p-2 bg-light rounded">
                                    <small><strong>Observações:</strong> ${member.notes}</small>
                                </div>
                            ` : ''}
                        </div>
                        ${member.role === 'support' ? `
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="removeTechnician(${member.technician_id})">
                                <i class="fas fa-times"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        });
    }
    
    $('#teamList').html(html);
    
    // Botão adicionar técnico
    if (canAdd && osData.work_order.status === 'in_progress') {
        $('#btnAddTechnician').show();
    }
}

// Renderizar Logs
function renderLogs(logs) {
    let html = '';
    
    if (logs.length === 0) {
        html = '<p class="text-muted">Nenhuma atividade registrada ainda.</p>';
    } else {
        logs.forEach(log => {
            html += `
                <div class="log-item">
                    <div class="d-flex justify-content-between mb-1">
                        <strong>${log.action_label}</strong>
                        <small class="text-muted">${log.elapsed_time}</small>
                    </div>
                    <small class="text-muted">
                        ${log.user_name} - ${log.description}
                    </small>
                </div>
            `;
        });
    }
    
    $('#logsList').html(html);
}

// Renderizar Botões de Ação
function renderActionButtons(os, team) {
    let buttons = '';
    const userId = <?php echo getCurrentUserId(); ?>;
    const isTechnician = team.some(t => t.technician_id === userId);
    const myData = team.find(t => t.technician_id === userId);
    
    if (isTechnician && os.status === 'in_progress') {
        // Técnico pode concluir sua parte
        if (!myData || myData.status !== 'completed') {
            buttons += `
                <button class="btn btn-success w-100 action-btn" onclick="showCompleteModal()">
                    <i class="fas fa-check me-2"></i>Concluir Minha Parte
                </button>
            `;
        }
    }
    
    // Admin ou técnico principal pode cancelar
    <?php if (isAdmin()): ?>
    if (os.status !== 'completed' && os.status !== 'cancelled') {
        buttons += `
            <button class="btn btn-danger w-100 action-btn" onclick="showCancelModal()">
                <i class="fas fa-times me-2"></i>Cancelar OS
            </button>
        `;
    }
    <?php endif; ?>
    
    // Info adicional
    $('#additionalInfo').html(`
        <small class="text-muted">
            <strong>Criada por:</strong> ${os.created_by_name}<br>
            <strong>Data:</strong> ${os.created_at_formatted}<br>
            ${os.completed_at ? `<strong>Concluída em:</strong> ${os.completed_at_formatted}` : ''}
        </small>
    `);
    
    $('#actionButtons').html(buttons || '<p class="text-muted">Nenhuma ação disponível</p>');
}

// Carregar Lista de Técnicos
function loadTechnicians() {
    $.ajax({
        url: '<?php echo BASE_URL; ?>ajax/get_available_technicians.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                let options = '<option value="">Selecione...</option>';
                response.technicians.forEach(tech => {
                    options += `<option value="${tech.id}">${tech.name}</option>`;
                });
                $('#selectTechnician').html(options);
            }
        }
    });
}

// Modal Adicionar Técnico
$('#btnAddTechnician').click(function() {
    $('#addTechnicianModal').modal('show');
});

$('#confirmAddTechnician').click(function() {
    const techId = $('#selectTechnician').val();
    
    if (!techId) {
        Swal.fire('Erro', 'Selecione um técnico', 'warning');
        return;
    }
    
    $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>ajax/add_technician_to_os.php',
        method: 'POST',
        data: {
            work_order_id: workOrderId,
            technician_id: techId,
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        },
        success: function(response) {
            if (response.success) {
                $('#addTechnicianModal').modal('hide');
                Swal.fire('Sucesso!', response.message, 'success');
                loadWorkOrderDetails();
            } else {
                Swal.fire('Erro', response.message, 'error');
            }
        },
        complete: function() {
            $('#confirmAddTechnician').prop('disabled', false).html('<i class="fas fa-plus me-1"></i>Adicionar');
        }
    });
});

// Remover Técnico
function removeTechnician(techId) {
    Swal.fire({
        title: 'Remover Técnico?',
        text: 'Tem certeza que deseja remover este técnico da OS?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, remover',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '<?php echo BASE_URL; ?>ajax/remove_technician_from_os.php',
                method: 'POST',
                data: {
                    work_order_id: workOrderId,
                    technician_id: techId,
                    csrf_token: '<?php echo generateCSRFToken(); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Removido!', response.message, 'success');
                        loadWorkOrderDetails();
                    } else {
                        Swal.fire('Erro', response.message, 'error');
                    }
                }
            });
        }
    });
}

// Modal Concluir
function showCompleteModal() {
    $('#completeModal').modal('show');
}

$('#confirmComplete').click(function() {
    const notes = $('#completionNotes').val();
    
    $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>ajax/complete_work_order.php',
        method: 'POST',
        data: {
            work_order_id: workOrderId,
            notes: notes,
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        },
        success: function(response) {
            if (response.success) {
                $('#completeModal').modal('hide');
                
                if (response.all_completed) {
                    Swal.fire({
                        icon: 'success',
                        title: 'OS Concluída!',
                        text: 'Todos os técnicos finalizaram. A OS foi marcada como concluída.',
                        confirmButtonText: 'Ver Ticket'
                    }).then(() => {
                        window.location.href = '<?php echo BASE_URL; ?>views/tickets/view.php?id=' + osData.work_order.ticket_id;
                    });
                } else {
                    Swal.fire('Sucesso!', response.message, 'success');
                    loadWorkOrderDetails();
                }
            } else {
                Swal.fire('Erro', response.message, 'error');
            }
        },
        complete: function() {
            $('#confirmComplete').prop('disabled', false).html('<i class="fas fa-check me-1"></i>Confirmar Conclusão');
        }
    });
});

// Modal Cancelar
function showCancelModal() {
    $('#cancelModal').modal('show');
}

$('#confirmCancel').click(function() {
    const reason = $('#cancelReason').val().trim();
    
    if (!reason) {
        Swal.fire('Atenção', 'Informe o motivo do cancelamento', 'warning');
        return;
    }
    
    $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>ajax/cancel_work_order.php',
        method: 'POST',
        data: {
            work_order_id: workOrderId,
            reason: reason,
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        },
        success: function(response) {
            if (response.success) {
                $('#cancelModal').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: 'OS Cancelada',
                    text: response.message
                }).then(() => {
                    window.location.href = '<?php echo BASE_URL; ?>views/dashboard/index.php';
                });
            } else {
                Swal.fire('Erro', response.message, 'error');
            }
        },
        complete: function() {
            $('#confirmCancel').prop('disabled', false).html('<i class="fas fa-times me-1"></i>Confirmar Cancelamento');
        }
    });
});
</script>

<?php include ROOT_PATH . 'includes/footer.php'; ?>