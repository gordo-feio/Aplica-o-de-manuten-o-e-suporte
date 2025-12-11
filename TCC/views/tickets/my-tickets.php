<?php
/**
 * Meus Tickets - Visualização para Empresas
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 */

// Incluir configurações
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Verificar se é empresa
requireLogin();
requireCompany();

$pageTitle = 'Meus Tickets - ' . SYSTEM_NAME;
$companyId = getCurrentCompanyId();

// Conectar ao banco
$pdo = getConnection();

// Filtros
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$search = $_GET['search'] ?? '';

// Construir SQL com filtros
$sql = "SELECT t.*, u.name as assigned_user_name
        FROM tickets t
        LEFT JOIN users u ON t.assigned_user_id = u.id
        WHERE t.company_id = ?";

$params = [$companyId];

if (!empty($status)) {
    $sql .= " AND t.status = ?";
    $params[] = $status;
}

if (!empty($priority)) {
    $sql .= " AND t.priority = ?";
    $params[] = $priority;
}

if (!empty($search)) {
    $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// Estatísticas
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status NOT IN ('closed', 'resolved') THEN 1 ELSE 0 END) as open,
    SUM(CASE WHEN status IN ('assumed', 'dispatched', 'in_progress') THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status IN ('closed', 'resolved') THEN 1 ELSE 0 END) as resolved
    FROM tickets WHERE company_id = ?");
$stmt->execute([$companyId]);
$stats = $stmt->fetch();

// Incluir header
include __DIR__ . '/../../includes/header.php';
?>

<style>
/* ================================================
   ESTILOS MODERNOS E LIMPOS
   ================================================ */
.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
    color: white;
}

.page-header h1 {
    color: white;
    margin: 0;
    font-size: 28px;
    font-weight: 700;
}

.page-header p {
    color: rgba(255, 255, 255, 0.9);
    margin: 0.5rem 0 0 0;
}

.stats-card {
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

.ticket-card {
    transition: all 0.3s ease;
    border: none;
    height: 100%;
}

.ticket-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15) !important;
}

.filter-card {
    border: none;
    background: white;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state i {
    font-size: 5rem;
    color: #dee2e6;
    margin-bottom: 1.5rem;
}

.card-header {
    background: white;
    border-bottom: 1px solid #e9ecef;
}

/* Badges customizados */
.priority-badge {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
}

/* Responsivo */
@media (max-width: 768px) {
    .page-header {
        padding: 1.5rem 0;
    }
    
    .page-header h1 {
        font-size: 22px;
    }
}
</style>

<div class="container-fluid px-4">
    
    <!-- Header da Página -->
    <div class="page-header">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1>
                        <i class="fas fa-ticket-alt me-2"></i>
                        Meus Tickets
                    </h1>
                    <p>Acompanhe todos os seus chamados</p>
                </div>
                <a href="<?php echo BASE_URL; ?>views/tickets/create.php" 
                   class="btn btn-light btn-lg">
                    <i class="fas fa-plus me-2"></i>
                    Novo Ticket
                </a>
            </div>
        </div>
    </div>
    
    <div class="container-fluid px-4">
        
        <!-- Cards de Estatísticas -->
        <div class="row g-3 mb-4">
            
            <div class="col-6 col-md-3">
                <a href="?" class="text-decoration-none">
                    <div class="card stats-card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 small fw-semibold">Total</p>
                                    <h3 class="mb-0 text-dark"><?php echo $stats['total']; ?></h3>
                                </div>
                                <div class="text-primary">
                                    <i class="fas fa-ticket-alt fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="col-6 col-md-3">
                <a href="?status=created" class="text-decoration-none">
                    <div class="card stats-card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 small fw-semibold">Abertos</p>
                                    <h3 class="mb-0 text-warning"><?php echo $stats['open']; ?></h3>
                                </div>
                                <div class="text-warning">
                                    <i class="fas fa-folder-open fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="col-6 col-md-3">
                <a href="?status=in_progress" class="text-decoration-none">
                    <div class="card stats-card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 small fw-semibold">Em Andamento</p>
                                    <h3 class="mb-0 text-info"><?php echo $stats['in_progress']; ?></h3>
                                </div>
                                <div class="text-info">
                                    <i class="fas fa-spinner fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="col-6 col-md-3">
                <a href="?status=resolved" class="text-decoration-none">
                    <div class="card stats-card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 small fw-semibold">Resolvidos</p>
                                    <h3 class="mb-0 text-success"><?php echo $stats['resolved']; ?></h3>
                                </div>
                                <div class="text-success">
                                    <i class="fas fa-check-circle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            
        </div>
        
        <!-- Filtros -->
        <div class="card filter-card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    
                    <!-- Busca -->
                    <div class="col-md-5">
                        <label class="form-label fw-semibold small mb-2">
                            <i class="fas fa-search me-1"></i> Buscar Tickets
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" 
                                   class="form-control border-start-0 ps-0" 
                                   name="search" 
                                   placeholder="Digite título ou descrição..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    
                    <!-- Status -->
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small mb-2">
                            <i class="fas fa-flag me-1"></i> Status
                        </label>
                        <select class="form-select" name="status">
                            <option value="">Todos os Status</option>
                            <?php foreach (TICKET_STATUS as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $status === $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Prioridade -->
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small mb-2">
                            <i class="fas fa-exclamation-circle me-1"></i> Prioridade
                        </label>
                        <select class="form-select" name="priority">
                            <option value="">Todas</option>
                            <?php foreach (TICKET_PRIORITIES as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $priority === $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Botões -->
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-1"></i> Filtrar
                        </button>
                    </div>
                    
                </form>
                
                <?php if (!empty($status) || !empty($priority) || !empty($search)): ?>
                <div class="mt-3 pt-3 border-top">
                    <a href="<?php echo BASE_URL; ?>views/tickets/my-tickets.php" 
                       class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times me-1"></i> Limpar Filtros
                    </a>
                    <span class="text-muted ms-3 small">
                        <i class="fas fa-filter me-1"></i>
                        Filtros ativos: <?php echo count(array_filter([$status, $priority, $search])); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Lista de Tickets -->
        <?php if (empty($tickets)): ?>
            
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h4 class="text-muted mb-3">Nenhum ticket encontrado</h4>
                        <?php if (!empty($status) || !empty($priority) || !empty($search)): ?>
                            <p class="text-muted mb-4">Tente ajustar os filtros de busca</p>
                            <a href="<?php echo BASE_URL; ?>views/tickets/my-tickets.php" 
                               class="btn btn-outline-secondary">
                                <i class="fas fa-list me-2"></i>
                                Ver Todos os Tickets
                            </a>
                        <?php else: ?>
                            <p class="text-muted mb-4">Você ainda não criou nenhum ticket</p>
                            <a href="<?php echo BASE_URL; ?>views/tickets/create.php" 
                               class="btn btn-success btn-lg">
                                <i class="fas fa-plus me-2"></i>
                                Criar Primeiro Ticket
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            
            <!-- Grid de Cards -->
            <div class="row g-3 mb-4">
                <?php foreach ($tickets as $ticket): ?>
                
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card ticket-card shadow-sm">
                        
                        <!-- Header do Card -->
                        <div class="card-header d-flex justify-content-between align-items-center py-3">
                            <span class="fw-bold text-primary fs-5">
                                #<?php echo $ticket['id']; ?>
                            </span>
                            <span class="badge priority-badge bg-<?php echo getPriorityColor($ticket['priority']); ?>">
                                <i class="fas <?php echo getPriorityIcon($ticket['priority']); ?> me-1"></i>
                                <?php echo getPriorityLabel($ticket['priority']); ?>
                            </span>
                        </div>
                        
                        <!-- Body do Card -->
                        <div class="card-body">
                            
                            <!-- Título -->
                            <h5 class="card-title mb-3" style="min-height: 48px; line-height: 1.4;">
                                <?php echo htmlspecialchars(limitText($ticket['title'], 60)); ?>
                            </h5>
                            
                            <!-- Descrição -->
                            <p class="card-text text-muted small mb-3" style="min-height: 60px; overflow: hidden;">
                                <?php echo htmlspecialchars(limitText($ticket['description'], 120)); ?>
                            </p>
                            
                            <!-- Informações -->
                            <div class="mb-2">
                                <span class="badge bg-light text-dark border">
                                    <i class="fas <?php echo getCategoryIcon($ticket['category']); ?> me-1"></i>
                                    <?php echo getCategoryLabel($ticket['category']); ?>
                                </span>
                            </div>
                            
                            <div class="mb-2">
                                <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?>">
                                    <?php echo getStatusLabel($ticket['status']); ?>
                                </span>
                            </div>
                            
                            <!-- Responsável -->
                            <?php if ($ticket['assigned_user_name']): ?>
                            <div class="mb-2 small text-muted">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo htmlspecialchars($ticket['assigned_user_name']); ?>
                            </div>
                            <?php else: ?>
                            <div class="mb-2 small text-warning">
                                <i class="fas fa-user-slash me-1"></i>
                                Aguardando atribuição
                            </div>
                            <?php endif; ?>
                            
                            <!-- Data -->
                            <div class="text-muted small">
                                <i class="far fa-clock me-1"></i>
                                <?php echo timeElapsed($ticket['created_at']); ?>
                            </div>
                            
                        </div>
                        
                        <!-- Footer do Card -->
                        <div class="card-footer bg-white border-top">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <a href="<?php echo BASE_URL; ?>views/tickets/view.php?id=<?php echo $ticket['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary flex-grow-1">
                                    <i class="fas fa-eye me-1"></i>
                                    Ver Detalhes
                                </a>
                                
                                <?php if ($ticket['status'] === 'closed' || $ticket['status'] === 'resolved'): ?>
                                <button class="btn btn-sm btn-outline-warning btn-reopen"
                                        data-ticket-id="<?php echo $ticket['id']; ?>"
                                        title="Reabrir Ticket">
                                    <i class="fas fa-redo"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <?php endforeach; ?>
            </div>
            
        <?php endif; ?>
        
    </div>
    
</div>

<script>
$(document).ready(function() {
    
    // Reabrir ticket
    $('.btn-reopen').click(function() {
        const ticketId = $(this).data('ticket-id');
        const button = $(this);
        
        if (!confirm('Deseja reabrir este ticket?\n\nNossa equipe será notificada sobre a reabertura.')) {
            return;
        }
        
        const originalHtml = button.html();
        button.prop('disabled', true);
        button.html('<i class="fas fa-spinner fa-spin"></i>');
        
        $.ajax({
            url: '<?php echo BASE_URL; ?>controllers/TicketController.php?action=reopen',
            method: 'POST',
            data: {
                ticket_id: ticketId,
                csrf_token: '<?php echo generateCSRFToken(); ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showNotification('success', response.message || 'Ticket reaberto com sucesso!');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification('error', 'Erro: ' + (response.message || 'Não foi possível reabrir o ticket'));
                    button.prop('disabled', false);
                    button.html(originalHtml);
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro na requisição:', {xhr, status, error});
                showNotification('error', 'Erro ao reabrir ticket. Tente novamente.');
                button.prop('disabled', false);
                button.html(originalHtml);
            }
        });
    });
    
    // Função para mostrar notificações
    function showNotification(type, message) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        
        const notification = $(`
            <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px; box-shadow: 0 8px 24px rgba(0,0,0,0.2);"
                 role="alert">
                <i class="fas ${icon} me-2"></i>
                <strong>${message}</strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        
        $('body').append(notification);
        
        setTimeout(function() {
            notification.alert('close');
        }, 5000);
    }
    
    // Animação de entrada dos cards
    $('.ticket-card').each(function(index) {
        $(this).css({
            'opacity': '0',
            'transform': 'translateY(20px)'
        });
        
        setTimeout(() => {
            $(this).css({
                'transition': 'all 0.5s ease',
                'opacity': '1',
                'transform': 'translateY(0)'
            });
        }, index * 50);
    });
    
    // Destaque nos filtros ativos
    $('select, input[name="search"]').each(function() {
        if ($(this).val() !== '') {
            $(this).addClass('border-primary');
        }
    });
    
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>