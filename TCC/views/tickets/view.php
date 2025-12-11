<?php
/**
 * Visualizar Detalhes do Ticket - VERSÃO CORRIGIDA
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 */

// Incluir configurações na ordem correta
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Verificar autenticação
requireLogin();

// Obter ID do ticket
$ticketId = (int)($_GET['id'] ?? 0);

if (!$ticketId) {
    setFlashMessage('Ticket inválido.', 'danger');
    redirect(BASE_URL . 'views/dashboard/index.php');
}

// Conectar ao banco
$pdo = getConnection();

// Buscar ticket com informações relacionadas
$sql = "SELECT t.*, 
        c.name as company_name, c.email as company_email, c.phone as company_phone,
        u.name as assigned_user_name, u.email as assigned_user_email
        FROM tickets t
        INNER JOIN companies c ON t.company_id = c.id
        LEFT JOIN users u ON t.assigned_user_id = u.id
        WHERE t.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    setFlashMessage('Ticket não encontrado.', 'danger');
    redirect(BASE_URL . 'views/dashboard/index.php');
}

// Verificar permissão de acesso
if (isCompany() && $ticket['company_id'] != getCurrentCompanyId()) {
    setFlashMessage('Você não tem permissão para acessar este ticket.', 'danger');
    redirect(BASE_URL . 'views/tickets/my-tickets.php');
}

$pageTitle = "Ticket #$ticketId - " . SYSTEM_NAME;

// Buscar logs do ticket
$stmt = $pdo->prepare("SELECT l.*, u.name as user_name 
                       FROM ticket_logs l
                       LEFT JOIN users u ON l.user_id = u.id
                       WHERE l.ticket_id = ?
                       ORDER BY l.created_at DESC");
$stmt->execute([$ticketId]);
$logs = $stmt->fetchAll();

// Buscar anexos
$stmt = $pdo->prepare("SELECT * FROM attachments WHERE ticket_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$ticketId]);
$attachments = $stmt->fetchAll();

// Incluir header
include __DIR__ . '/../../includes/header.php';
?>

<style>
.timeline {
    position: relative;
}

.timeline-item {
    position: relative;
}

.timeline-badge {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 8px;
}

.ticket-details-card {
    transition: all 0.3s ease;
}

.ticket-details-card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.info-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-value {
    color: #212529;
}

.action-button {
    transition: all 0.2s ease;
}

.action-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.attachment-item {
    transition: all 0.2s ease;
}

.attachment-item:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}
</style>

<div class="container-fluid px-4 py-4">
    

    
    <div class="row">
        
        <!-- Coluna Principal -->
        <div class="col-lg-8">
            
            <!-- Informações do Ticket -->
            <div class="card border-0 shadow-sm mb-4 ticket-details-card">
                
                <!-- Header -->
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h4 class="mb-2">
                                <span class="badge bg-secondary me-2">#<?php echo $ticket['id']; ?></span>
                                <?php echo htmlspecialchars($ticket['title']); ?>
                            </h4>
                            <div class="d-flex gap-2 flex-wrap">
                                <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?> px-3 py-2">
                                    <i class="fas fa-circle me-1" style="font-size: 0.6rem;"></i>
                                    <?php echo getStatusLabel($ticket['status']); ?>
                                </span>
                                <span class="badge bg-<?php echo getPriorityColor($ticket['priority']); ?> px-3 py-2">
                                    <i class="fas <?php echo getPriorityIcon($ticket['priority']); ?> me-1"></i>
                                    <?php echo getPriorityLabel($ticket['priority']); ?>
                                </span>
                                <span class="badge bg-light text-dark border px-3 py-2">
                                    <i class="fas <?php echo getCategoryIcon($ticket['category']); ?> me-1"></i>
                                    <?php echo getCategoryLabel($ticket['category']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Body -->
                <div class="card-body p-4">
                    
                    <!-- Descrição -->
                    <div class="mb-4">
                        <h6 class="info-label">
                            <i class="fas fa-align-left text-primary"></i>
                            Descrição do Problema
                        </h6>
                        <div class="bg-light p-3 rounded info-value" style="white-space: pre-line;">
                            <?php echo htmlspecialchars($ticket['description']); ?>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <!-- Informações Adicionais -->
                    <div class="row g-4">
                        
                        <?php if (!empty($ticket['address'])): ?>
                        <div class="col-md-6">
                            <h6 class="info-label">
                                <i class="fas fa-map-marker-alt text-danger"></i>
                                Endereço/Local
                            </h6>
                            <p class="info-value mb-0">
                                <?php echo htmlspecialchars($ticket['address']); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-6">
                            <h6 class="info-label">
                                <i class="far fa-calendar text-info"></i>
                                Data de Criação
                            </h6>
                            <p class="info-value mb-0">
                                <?php echo formatDate($ticket['created_at'], true); ?>
                                <br>
                                <small class="text-muted">(<?php echo timeElapsed($ticket['created_at']); ?>)</small>
                            </p>
                        </div>
                        
                        <?php if ($ticket['assumed_at']): ?>
                        <div class="col-md-6">
                            <h6 class="info-label">
                                <i class="fas fa-hand-paper text-success"></i>
                                Data de Assunção
                            </h6>
                            <p class="info-value mb-0">
                                <?php echo formatDate($ticket['assumed_at'], true); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($ticket['dispatched_at']): ?>
                        <div class="col-md-6">
                            <h6 class="info-label">
                                <i class="fas fa-truck text-primary"></i>
                                Data de Despacho
                            </h6>
                            <p class="info-value mb-0">
                                <?php echo formatDate($ticket['dispatched_at'], true); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($ticket['resolved_at']): ?>
                        <div class="col-md-6">
                            <h6 class="info-label">
                                <i class="fas fa-check-circle text-success"></i>
                                Data de Resolução
                            </h6>
                            <p class="info-value mb-0">
                                <?php echo formatDate($ticket['resolved_at'], true); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($ticket['closed_at']): ?>
                        <div class="col-md-6">
                            <h6 class="info-label">
                                <i class="fas fa-times-circle text-dark"></i>
                                Data de Encerramento
                            </h6>
                            <p class="info-value mb-0">
                                <?php echo formatDate($ticket['closed_at'], true); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                    </div>
                    
                </div>
                
            </div>
            
            <!-- Anexos -->
            <?php if (!empty($attachments)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="fas fa-paperclip me-2 text-primary"></i>
                        Anexos (<?php echo count($attachments); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($attachments as $attachment): ?>
                        <div class="col-md-6">
                            <div class="border rounded p-3 attachment-item">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-file fa-2x text-primary me-3"></i>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($attachment['file_name']); ?>">
                                            <?php echo htmlspecialchars($attachment['file_name']); ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo formatBytes($attachment['file_size']); ?>
                                        </small>
                                    </div>
                                    <a href="<?php echo BASE_URL; ?>public/uploads/<?php echo $attachment['file_path']; ?>" 
                                       class="btn btn-sm btn-outline-primary"
                                       download
                                       title="Baixar arquivo">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Histórico de Ações -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2 text-primary"></i>
                        Histórico de Ações
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($logs)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">Nenhuma ação registrada ainda.</p>
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($logs as $log): ?>
                            <div class="timeline-item mb-3">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <div class="timeline-badge bg-primary">
                                            <i class="fas fa-circle"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="card border">
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <strong class="text-primary">
                                                        <?php echo LOG_ACTIONS[$log['action']] ?? $log['action']; ?>
                                                    </strong>
                                                    <small class="text-muted">
                                                        <i class="far fa-clock me-1"></i>
                                                        <?php echo timeElapsed($log['created_at']); ?>
                                                    </small>
                                                </div>
                                                <?php if (!empty($log['description'])): ?>
                                                <p class="mb-2 small">
                                                    <?php echo htmlspecialchars($log['description']); ?>
                                                </p>
                                                <?php endif; ?>
                                                <?php if ($log['user_name']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-user me-1"></i>
                                                    Por: <?php echo htmlspecialchars($log['user_name']); ?>
                                                </small>
                                                <?php endif; ?>
                                                <div class="text-muted" style="font-size: 0.7rem;">
                                                    <?php echo formatDate($log['created_at'], true); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
        
        <!-- Coluna Lateral -->
        <div class="col-lg-4">
            
            <!-- Informações da Empresa -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">
                        <i class="fas fa-building me-1 text-primary"></i>
                        Informações da Empresa
                    </h6>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        <strong><?php echo htmlspecialchars($ticket['company_name']); ?></strong>
                    </p>
                    <hr class="my-2">
                    <p class="mb-2 small">
                        <i class="fas fa-envelope me-2 text-muted"></i>
                        <a href="mailto:<?php echo $ticket['company_email']; ?>">
                            <?php echo htmlspecialchars($ticket['company_email']); ?>
                        </a>
                    </p>
                    <?php if ($ticket['company_phone']): ?>
                    <p class="mb-0 small">
                        <i class="fas fa-phone me-2 text-muted"></i>
                        <a href="tel:<?php echo preg_replace('/[^0-9]/', '', $ticket['company_phone']); ?>">
                            <?php echo formatPhone($ticket['company_phone']); ?>
                        </a>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Responsável -->
            <?php if ($ticket['assigned_user_name']): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">
                        <i class="fas fa-user-tie me-1 text-success"></i>
                        Atendente Responsável
                    </h6>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        <strong><?php echo htmlspecialchars($ticket['assigned_user_name']); ?></strong>
                    </p>
                    <hr class="my-2">
                    <p class="mb-0 small text-muted">
                        <i class="fas fa-envelope me-2"></i>
                        <a href="mailto:<?php echo $ticket['assigned_user_email']; ?>">
                            <?php echo htmlspecialchars($ticket['assigned_user_email']); ?>
                        </a>
                    </p>
                </div>
            </div>
            <?php else: ?>
            <div class="card border-0 shadow-sm mb-3 border-warning">
                <div class="card-body text-center py-4">
                    <i class="fas fa-user-slash fa-2x text-warning mb-2"></i>
                    <p class="mb-0 text-muted">
                        <strong>Aguardando atribuição</strong>
                    </p>
                    <small class="text-muted">Este ticket ainda não foi assumido por um atendente</small>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Ações (apenas para funcionários) -->
            <?php if (isUser()): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">
                        <i class="fas fa-cog me-1 text-primary"></i>
                        Ações do Ticket
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        
                        <?php if ($ticket['status'] === 'created' || $ticket['status'] === 'reopened'): ?>
                        <button class="btn btn-success action-button" id="btnAssume">
                            <i class="fas fa-hand-paper me-2"></i>
                            Assumir Ticket
                        </button>
                        <?php endif; ?>
                        
                        <?php if (in_array($ticket['status'], ['assumed', 'reopened'])): ?>
                        <button class="btn btn-primary action-button" id="btnDispatch">
                            <i class="fas fa-truck me-2"></i>
                            Despachar Equipe
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($ticket['status'] === 'dispatched'): ?>
                        <button class="btn btn-info action-button" id="btnInProgress">
                            <i class="fas fa-spinner me-2"></i>
                            Iniciar Atendimento
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($ticket['status'] === 'in_progress'): ?>
                        <button class="btn btn-warning action-button" id="btnResolve">
                            <i class="fas fa-check-circle me-2"></i>
                            Marcar como Resolvido
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($ticket['status'] === 'resolved'): ?>
                        <button class="btn btn-dark action-button" id="btnClose">
                            <i class="fas fa-times-circle me-2"></i>
                            Encerrar Ticket
                        </button>
                        <?php endif; ?>
                        
                        <?php if (in_array($ticket['status'], ['created', 'assumed', 'dispatched', 'in_progress'])): ?>
                        <hr class="my-2">
                        <a href="<?php echo BASE_URL; ?>views/tickets/index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Voltar para Lista
                        </a>
                        <?php endif; ?>
                        
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
        
    </div>
    
</div>

<script>
// Script corrigido para substituir o script existente no view.php
$(document).ready(function() {
    
    const ticketId = <?php echo $ticketId; ?>;
    const csrfToken = '<?php echo generateCSRFToken(); ?>';
    const baseUrl = '<?php echo BASE_URL; ?>';
    
    // Mapeamento correto de ações para arquivos
    const actionEndpoints = {
        'assume': 'assume_ticket.php',
        'dispatch': 'dispatch_ticket.php',
        'in_progress': 'in_progress_ticket.php',
        'resolve': 'resolve_ticket.php',
        'close': 'close_ticket.php'
    };
    
    // Assumir ticket
    $('#btnAssume').click(function() {
        handleAction('assume', 'Deseja assumir este ticket?\n\nVocê será o responsável pelo atendimento.');
    });
    
    // Despachar equipe
    $('#btnDispatch').click(function() {
        handleAction('dispatch', 'Deseja despachar a equipe para este atendimento?\n\nA empresa será notificada.');
    });
    
    // Em andamento
    $('#btnInProgress').click(function() {
        handleAction('in_progress', 'Deseja marcar este ticket como em andamento?');
    });
    
    // Resolver
    $('#btnResolve').click(function() {
        handleAction('resolve', 'Deseja marcar este ticket como resolvido?\n\nA empresa será notificada.');
    });
    
    // Encerrar
    $('#btnClose').click(function() {
        handleAction('close', 'Deseja encerrar este ticket definitivamente?\n\nEsta ação não poderá ser desfeita.');
    });
    
    // Função para lidar com ações
    function handleAction(action, confirmMessage) {
        if (!confirm(confirmMessage)) {
            return;
        }
        
        // Mapear nomes de ações para IDs de botões
        const buttonMap = {
            'assume': 'btnAssume',
            'dispatch': 'btnDispatch',
            'in_progress': 'btnInProgress',
            'resolve': 'btnResolve',
            'close': 'btnClose'
        };
        
        const button = $('#' + buttonMap[action]);
        const originalHtml = button.html();
        
        button.prop('disabled', true);
        button.html('<i class="fas fa-spinner fa-spin me-2"></i>Processando...');
        
        // Log para debug
        console.log('Action:', action);
        console.log('Endpoint:', baseUrl + 'ajax/' + actionEndpoints[action]);
        console.log('Ticket ID:', ticketId);
        
        $.ajax({
            url: baseUrl + 'ajax/' + actionEndpoints[action],
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                ticket_id: ticketId
            }),
            dataType: 'json',
            success: function(response) {
                console.log('Response:', response);
                
                if (response.success) {
                    showNotification('success', response.message || 'Ação realizada com sucesso!');
                    
                    // Recarregar página após 1.5 segundos
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification('error', 'Erro: ' + (response.message || 'Erro desconhecido'));
                    button.prop('disabled', false);
                    button.html(originalHtml);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    xhr: xhr
                });
                
                let errorMessage = 'Erro ao processar ação. Tente novamente.';
                
                // Tentar extrair mensagem de erro do servidor
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.message) {
                        errorMessage = errorResponse.message;
                    }
                } catch (e) {
                    // Se não conseguir parsear, usar mensagem padrão
                    if (xhr.responseText) {
                        console.error('Response Text:', xhr.responseText);
                    }
                }
                
                showNotification('error', errorMessage);
                button.prop('disabled', false);
                button.html(originalHtml);
            }
        });
    }
    
    // Função para mostrar notificações
    function showNotification(type, message) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        
        const notification = $(`
            <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 350px; max-width: 500px; box-shadow: 0 8px 24px rgba(0,0,0,0.2);"
                 role="alert">
                <i class="fas ${icon} me-2"></i>
                <strong>${message}</strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        
        $('body').append(notification);
        
        // Auto-remover após 5 segundos
        setTimeout(function() {
            notification.alert('close');
        }, 5000);
    }
    
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>