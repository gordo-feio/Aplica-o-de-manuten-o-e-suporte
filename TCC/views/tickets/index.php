<?php
/**
 * Lista de Tickets - Visualização Profissional para Funcionários
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 */

// Incluir configurações na ordem correta
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Verificar se é funcionário
requireLogin();
requireUser();

$pageTitle = 'Gerenciar Tickets - ' . SYSTEM_NAME;

// Conectar ao banco
$pdo = getConnection();

// Filtros
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$assigned = $_GET['assigned'] ?? '';

// Construir SQL com filtros
$sql = "SELECT t.*, 
        c.name as company_name,
        c.phone as company_phone,
        u.name as assigned_user_name
        FROM tickets t
        INNER JOIN companies c ON t.company_id = c.id
        LEFT JOIN users u ON t.assigned_user_id = u.id
        WHERE 1=1";

$params = [];

if (!empty($status)) {
    $sql .= " AND t.status = ?";
    $params[] = $status;
}

if (!empty($priority)) {
    $sql .= " AND t.priority = ?";
    $params[] = $priority;
}

if (!empty($category)) {
    $sql .= " AND t.category = ?";
    $params[] = $category;
}

if (!empty($search)) {
    $sql .= " AND (t.title LIKE ? OR t.description LIKE ? OR c.name LIKE ? OR t.id LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($assigned === 'me') {
    $sql .= " AND t.assigned_user_id = ?";
    $params[] = getCurrentUserId();
} elseif ($assigned === 'unassigned') {
    $sql .= " AND t.assigned_user_id IS NULL";
}

// Ordenação por prioridade e data
$sql .= " ORDER BY 
          CASE t.priority 
            WHEN 'high' THEN 1 
            WHEN 'medium' THEN 2 
            WHEN 'low' THEN 3 
          END ASC,
          t.created_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// Estatísticas
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn(),
    'created' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'created'")->fetchColumn(),
    'high' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE priority = 'high' AND status NOT IN ('closed', 'resolved')")->fetchColumn(),
    'in_progress' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('assumed', 'dispatched', 'in_progress')")->fetchColumn(),
    'resolved_today' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('resolved', 'closed') AND DATE(resolved_at) = CURDATE()")->fetchColumn(),
    'my_tickets' => $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_user_id = ?")->execute([getCurrentUserId()]) ? $pdo->query("SELECT COUNT(*) FROM tickets WHERE assigned_user_id = " . getCurrentUserId())->fetchColumn() : 0,
];

// Incluir header
include __DIR__ . '/../../includes/header.php';
?>

<style>
/* Design Profissional e Moderno */
.stats-card {
    transition: all 0.3s ease;
    cursor: pointer;
    border-left: 4px solid transparent;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

.stats-card.active {
    border-left-color: var(--bs-primary);
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.stats-card.active * {
    color: white !important;
}

.filter-bar {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.ticket-row {
    transition: all 0.2s ease;
    cursor: pointer;
}

.ticket-row:hover {
    background-color: #f8f9fa;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.priority-indicator {
    width: 4px;
    height: 100%;
    position: absolute;
    left: 0;
    top: 0;
}

.priority-high { background: #dc3545; }
.priority-medium { background: #ffc107; }
.priority-low { background: #28a745; }

.action-btn {
    transition: all 0.2s ease;
}

.action-btn:hover {
    transform: scale(1.1);
}

.badge-custom {
    padding: 0.5rem 1rem;
    font-weight: 600;
    font-size: 0.85rem;
}

.quick-filters {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.quick-filter-btn {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    border: 2px solid #e9ecef;
    background: white;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}

.quick-filter-btn:hover {
    border-color: var(--bs-primary);
    background: var(--bs-primary);
    color: white;
}

.quick-filter-btn.active {
    border-color: var(--bs-primary);
    background: var(--bs-primary);
    color: white;
}

.table-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

.compact-info {
    font-size: 0.85rem;
    color: #6c757d;
}

.ticket-id {
    font-weight: 700;
    font-size: 1.1rem;
    color: #667eea;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e9ecef;
}

.view-mode-toggle {
    display: flex;
    gap: 0.5rem;
}

.view-mode-btn {
    padding: 0.5rem 1rem;
    border: 2px solid #dee2e6;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.view-mode-btn.active {
    border-color: var(--bs-primary);
    background: var(--bs-primary);
    color: white;
}

.grid-view {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.ticket-card {
    border: 1px solid #dee2e6;
    border-radius: 12px;
    padding: 1.5rem;
    background: white;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.ticket-card:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    transform: translateY(-5px);
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state i {
    font-size: 5rem;
    color: #dee2e6;
    margin-bottom: 1rem;
}
</style>

<div class="container-fluid px-4 py-4">
    
    <!-- Cabeçalho da Página -->
    <div class="section-header">
        <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-ticket-alt text-primary me-2"></i>
                Gerenciar Tickets
            </h1>
            <p class="text-muted mb-0">Visualize e gerencie todos os chamados de suporte</p>
        </div>
        <div class="view-mode-toggle">
            <button class="view-mode-btn active" id="tableView">
                <i class="fas fa-list"></i> Lista
            </button>
            <button class="view-mode-btn" id="gridView">
                <i class="fas fa-th"></i> Grade
            </button>
        </div>
    </div>
    
    <!-- Estatísticas Rápidas -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <a href="?" class="text-decoration-none">
                <div class="card stats-card border-0 shadow-sm h-100 <?php echo empty($_GET) ? 'active' : ''; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small mb-1">Total</div>
                                <h2 class="mb-0"><?php echo $stats['total']; ?></h2>
                            </div>
                            <i class="fas fa-ticket-alt fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-2">
            <a href="?status=created" class="text-decoration-none">
                <div class="card stats-card border-0 shadow-sm h-100 <?php echo $status === 'created' ? 'active' : ''; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small mb-1">Novos</div>
                                <h2 class="mb-0 text-warning"><?php echo $stats['created']; ?></h2>
                            </div>
                            <i class="fas fa-inbox fa-2x text-warning opacity-50"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-2">
            <a href="?priority=high" class="text-decoration-none">
                <div class="card stats-card border-0 shadow-sm h-100 <?php echo $priority === 'high' ? 'active' : ''; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small mb-1">Urgentes</div>
                                <h2 class="mb-0 text-danger"><?php echo $stats['high']; ?></h2>
                            </div>
                            <i class="fas fa-exclamation-triangle fa-2x text-danger opacity-50"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-2">
            <a href="?status=in_progress" class="text-decoration-none">
                <div class="card stats-card border-0 shadow-sm h-100 <?php echo $status === 'in_progress' ? 'active' : ''; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small mb-1">Em Progresso</div>
                                <h2 class="mb-0 text-info"><?php echo $stats['in_progress']; ?></h2>
                            </div>
                            <i class="fas fa-spinner fa-2x text-info opacity-50"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-2">
            <a href="?assigned=me" class="text-decoration-none">
                <div class="card stats-card border-0 shadow-sm h-100 <?php echo $assigned === 'me' ? 'active' : ''; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small mb-1">Meus Tickets</div>
                                <h2 class="mb-0 text-primary"><?php echo $stats['my_tickets']; ?></h2>
                            </div>
                            <i class="fas fa-user-check fa-2x text-primary opacity-50"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-2">
            <div class="card stats-card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small mb-1">Resolvidos Hoje</div>
                            <h2 class="mb-0 text-success"><?php echo $stats['resolved_today']; ?></h2>
                        </div>
                        <i class="fas fa-check-circle fa-2x text-success opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Barra de Filtros -->
    <div class="filter-bar">
        <form method="GET" id="filterForm">
            <div class="row g-3 align-items-end">
                
                <!-- Busca Global -->
                <div class="col-md-4">
                    <label class="form-label fw-bold small mb-2">
                        <i class="fas fa-search me-1"></i> Buscar
                    </label>
                    <input type="text" 
                           class="form-control form-control-lg" 
                           name="search" 
                           placeholder="ID, título, empresa..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <!-- Status -->
                <div class="col-md-2">
                    <label class="form-label fw-bold small mb-2">
                        <i class="fas fa-flag me-1"></i> Status
                    </label>
                    <select class="form-select form-select-lg" name="status">
                        <option value="">Todos</option>
                        <?php foreach (TICKET_STATUS as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $status === $key ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Prioridade -->
                <div class="col-md-2">
                    <label class="form-label fw-bold small mb-2">
                        <i class="fas fa-exclamation-circle me-1"></i> Prioridade
                    </label>
                    <select class="form-select form-select-lg" name="priority">
                        <option value="">Todas</option>
                        <?php foreach (TICKET_PRIORITIES as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $priority === $key ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Categoria -->
                <div class="col-md-2">
                    <label class="form-label fw-bold small mb-2">
                        <i class="fas fa-tag me-1"></i> Categoria
                    </label>
                    <select class="form-select form-select-lg" name="category">
                        <option value="">Todas</option>
                        <?php foreach (TICKET_CATEGORIES as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $category === $key ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Atribuição -->
                <div class="col-md-2">
                    <label class="form-label fw-bold small mb-2">
                        <i class="fas fa-user me-1"></i> Atribuído
                    </label>
                    <select class="form-select form-select-lg" name="assigned">
                        <option value="">Todos</option>
                        <option value="me" <?php echo $assigned === 'me' ? 'selected' : ''; ?>>Meus Tickets</option>
                        <option value="unassigned" <?php echo $assigned === 'unassigned' ? 'selected' : ''; ?>>Não Atribuídos</option>
                    </select>
                </div>
                
            </div>
            
            <!-- Botões de Ação -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="quick-filters">
                    <span class="text-muted small me-2">Filtros rápidos:</span>
                    <button type="button" class="quick-filter-btn" onclick="window.location='?status=created'">
                        🆕 Novos
                    </button>
                    <button type="button" class="quick-filter-btn" onclick="window.location='?priority=high'">
                        🔴 Urgentes
                    </button>
                    <button type="button" class="quick-filter-btn" onclick="window.location='?assigned=me'">
                        👤 Meus
                    </button>
                    <button type="button" class="quick-filter-btn" onclick="window.location='?assigned=unassigned'">
                        ⚠️ Sem Atribuição
                    </button>
                </div>
                <div>
                    <?php if (!empty($status) || !empty($priority) || !empty($category) || !empty($search) || !empty($assigned)): ?>
                    <a href="?" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-times me-1"></i> Limpar Filtros
                    </a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-filter me-2"></i> Aplicar Filtros
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Lista de Tickets (Vista Tabela) -->
    <div id="tableViewContent">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if (empty($tickets)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h4 class="text-muted mt-3">Nenhum ticket encontrado</h4>
                        <p class="text-muted">Tente ajustar os filtros ou aguarde novos chamados</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                <tr>
                                    <th width="100">ID</th>
                                    <th>Ticket</th>
                                    <th>Empresa</th>
                                    <th width="120">Categoria</th>
                                    <th width="120">Prioridade</th>
                                    <th width="130">Status</th>
                                    <th width="150">Criado</th>
                                    <th width="180" class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                <tr class="ticket-row position-relative" onclick="window.location='<?php echo BASE_URL; ?>views/tickets/view.php?id=<?php echo $ticket['id']; ?>'">
                                    <div class="priority-indicator priority-<?php echo $ticket['priority']; ?>"></div>
                                    <td>
                                        <span class="ticket-id">#<?php echo $ticket['id']; ?></span>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="fw-bold mb-1" style="max-width: 300px;">
                                                <?php echo htmlspecialchars($ticket['title']); ?>
                                            </div>
                                            <?php if ($ticket['assigned_user_name']): ?>
                                            <div class="compact-info">
                                                <i class="fas fa-user-circle me-1"></i>
                                                <?php echo htmlspecialchars($ticket['assigned_user_name']); ?>
                                            </div>
                                            <?php else: ?>
                                            <div class="compact-info text-warning">
                                                <i class="fas fa-user-slash me-1"></i>
                                                Não atribuído
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($ticket['company_name']); ?></div>
                                            <?php if ($ticket['company_phone']): ?>
                                            <div class="compact-info">
                                                <i class="fas fa-phone me-1"></i>
                                                <?php echo formatPhone($ticket['company_phone']); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <i class="fas <?php echo getCategoryIcon($ticket['category']); ?> me-1"></i>
                                            <?php echo getCategoryLabel($ticket['category']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-custom bg-<?php echo getPriorityColor($ticket['priority']); ?>">
                                            <i class="fas <?php echo getPriorityIcon($ticket['priority']); ?> me-1"></i>
                                            <?php echo getPriorityLabel($ticket['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-custom bg-<?php echo getStatusColor($ticket['status']); ?>">
                                            <?php echo getStatusLabel($ticket['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="compact-info">
                                            <i class="far fa-clock me-1"></i>
                                            <?php echo timeElapsed($ticket['created_at']); ?>
                                        </div>
                                        <div class="compact-info" style="font-size: 0.75rem;">
                                            <?php echo formatDate($ticket['created_at'], true); ?>
                                        </div>
                                    </td>
                                    <td onclick="event.stopPropagation()">
                                        <div class="table-actions">
                                            <a href="<?php echo BASE_URL; ?>views/tickets/view.php?id=<?php echo $ticket['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary action-btn"
                                               data-bs-toggle="tooltip"
                                               title="Ver Detalhes">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($ticket['status'] === 'created' || $ticket['status'] === 'reopened'): ?>
                                            <button class="btn btn-sm btn-success action-btn btn-assume"
                                                    data-ticket-id="<?php echo $ticket['id']; ?>"
                                                    data-bs-toggle="tooltip"
                                                    title="Assumir Ticket">
                                                <i class="fas fa-hand-paper"></i>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary dropdown-toggle action-btn" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>views/tickets/view.php?id=<?php echo $ticket['id']; ?>">
                                                        <i class="fas fa-eye me-2"></i> Ver Detalhes
                                                    </a></li>
                                                    <?php if ($ticket['status'] === 'created' || $ticket['status'] === 'reopened'): ?>
                                                    <li><a class="dropdown-item btn-assume-link" href="#" data-ticket-id="<?php echo $ticket['id']; ?>">
                                                        <i class="fas fa-hand-paper me-2"></i> Assumir
                                                    </a></li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item" href="mailto:<?php echo $ticket['company_name']; ?>">
                                                        <i class="fas fa-envelope me-2"></i> Contatar Empresa
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
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
    
    <!-- Vista em Grade (Oculta por padrão) -->
    <div id="gridViewContent" style="display: none;">
        <?php if (empty($tickets)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h4 class="text-muted mt-3">Nenhum ticket encontrado</h4>
                <p class="text-muted">Tente ajustar os filtros ou aguarde novos chamados</p>
            </div>
        <?php else: ?>
            <div class="grid-view">
                <?php foreach ($tickets as $ticket): ?>
                <div class="ticket-card" onclick="window.location='<?php echo BASE_URL; ?>views/tickets/view.php?id=<?php echo $ticket['id']; ?>'">
                    <div class="priority-indicator priority-<?php echo $ticket['priority']; ?>"></div>
                    
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="ticket-id">#<?php echo $ticket['id']; ?></span>
                        <span class="badge badge-custom bg-<?php echo getPriorityColor($ticket['priority']); ?>">
                            <?php echo getPriorityLabel($ticket['priority']); ?>
                        </span>
                    </div>
                    
                    <h5 class="mb-3"><?php echo htmlspecialchars(limitText($ticket['title'], 60)); ?></h5>
                    
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-building me-2 text-muted"></i>
                            <span class="fw-semibold"><?php echo htmlspecialchars($ticket['company_name']); ?></span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas <?php echo getCategoryIcon($ticket['category']); ?> me-2 text-muted"></i>
                            <span class="compact-info"><?php echo getCategoryLabel($ticket['category']); ?></span>
                        </div>
                        <?php if ($ticket['assigned_user_name']): ?>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-user-circle me-2 text-muted"></i>
                            <span class="compact-info"><?php echo htmlspecialchars($ticket['assigned_user_name']); ?></span>
                        </div>
                        <?php else: ?>
                        <div class="d-flex align-items-center text-warning">
                            <i class="fas fa-user-slash me-2"></i>
                            <span class="compact-info">Não atribuído</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                        <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?>">
                            <?php echo getStatusLabel($ticket['status']); ?>
                        </span>
                        <span class="compact-info">
                            <i class="far fa-clock me-1"></i>
                            <?php echo timeElapsed($ticket['created_at']); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
</div>

<script>
$(document).ready(function() {
    
    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Alternar entre vista de tabela e grade
    $('#tableView').click(function() {
        $(this).addClass('active');
        $('#gridView').removeClass('active');
        $('#tableViewContent').show();
        $('#gridViewContent').hide();
        localStorage.setItem('ticketView', 'table');
    });
    
    $('#gridView').click(function() {
        $(this).addClass('active');
        $('#tableView').removeClass('active');
        $('#tableViewContent').hide();
        $('#gridViewContent').show();
        localStorage.setItem('ticketView', 'grid');
    });
    
    // Restaurar vista preferida
    const savedView = localStorage.getItem('ticketView');
    if (savedView === 'grid') {
        $('#gridView').click();
    }
    
    // Assumir ticket (botão direto)
    $('.btn-assume').click(function(e) {
        e.stopPropagation();
        const ticketId = $(this).data('ticket-id');
        assumeTicket(ticketId, $(this));
    });
    
    // Assumir ticket (link dropdown)
    $('.btn-assume-link').click(function(e) {
        e.preventDefault();
        e.stopPropagation();
        const ticketId = $(this).data('ticket-id');
        assumeTicket(ticketId, $(this));
    });
    
    // Função para assumir ticket
    function assumeTicket(ticketId, button) {
        if (!confirm('Deseja assumir este ticket? Você será o responsável pelo atendimento.')) {
            return;
        }
        
        const originalHtml = button.html();
        button.prop('disabled', true);
        button.html('<i class="fas fa-spinner fa-spin"></i>');
        
        $.ajax({
            url: '<?php echo BASE_URL; ?>controllers/TicketController.php?action=assume',
            method: 'POST',
            data: {
                ticket_id: ticketId,
                csrf_token: '<?php echo generateCSRFToken(); ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Mostrar mensagem de sucesso
                    showNotification('success', response.message);
                    // Recarregar página após 1 segundo
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification('error', 'Erro: ' + response.message);
                    button.prop('disabled', false);
                    button.html(originalHtml);
                }
            },
            error: function(xhr) {
                showNotification('error', 'Erro ao assumir ticket. Tente novamente.');
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
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);"
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
    
    // Auto-enviar formulário ao mudar selects (opcional - comentado por padrão)
    // $('.form-select').change(function() {
    //     $('#filterForm').submit();
    // });
    
    // Atalhos de teclado
    $(document).keydown(function(e) {
        // Ctrl+F ou Cmd+F para focar na busca
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            $('input[name="search"]').focus().select();
        }
        
        // Ctrl+R ou Cmd+R para resetar filtros
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            window.location = '?';
        }
    });
    
    // Destacar filtros ativos
    highlightActiveFilters();
    
    function highlightActiveFilters() {
        $('select, input').each(function() {
            if ($(this).val() !== '' && $(this).attr('name') !== 'csrf_token') {
                $(this).addClass('border-primary border-2');
            }
        });
    }
    
    // Loading state para formulário
    $('#filterForm').submit(function() {
        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i> Carregando...').prop('disabled', true);
    });
    
    // Animação de entrada para os cards de estatísticas
    $('.stats-card').each(function(index) {
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
    
    // Adicionar indicador de loading ao clicar em cards de estatísticas
    $('.stats-card a').click(function(e) {
        if (!$(this).attr('href').includes('#')) {
            const card = $(this).find('.card-body');
            card.append('<div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background: rgba(255,255,255,0.8);"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></div>');
        }
    });
    
    // Copiar ID do ticket ao clicar
    $('.ticket-id').click(function(e) {
        e.stopPropagation();
        const ticketId = $(this).text();
        
        // Criar elemento temporário para copiar
        const temp = $('<input>');
        $('body').append(temp);
        temp.val(ticketId).select();
        document.execCommand('copy');
        temp.remove();
        
        // Feedback visual
        const original = $(this).html();
        $(this).html('<i class="fas fa-check text-success"></i> Copiado!');
        setTimeout(() => {
            $(this).html(original);
        }, 1500);
        
        showNotification('success', `ID ${ticketId} copiado para área de transferência!`);
    });
    
    // Prevenir propagação de cliques em ações dentro das linhas
    $('.table-actions, .table-actions *').click(function(e) {
        e.stopPropagation();
    });
    
    // Adicionar efeito de hover nos cards da grade
    $('.ticket-card').hover(
        function() {
            $(this).find('.priority-indicator').css('width', '8px');
        },
        function() {
            $(this).find('.priority-indicator').css('width', '4px');
        }
    );
    
    // Contador de tickets filtrados
    const totalTickets = <?php echo count($tickets); ?>;
    const totalInDB = <?php echo $stats['total']; ?>;
    
    if (totalTickets < totalInDB) {
        const filterInfo = $(`
            <div class="alert alert-info alert-dismissible fade show mt-3" role="alert">
                <i class="fas fa-filter me-2"></i>
                Exibindo <strong>${totalTickets}</strong> de <strong>${totalInDB}</strong> tickets totais
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        $('.filter-bar').after(filterInfo);
    }
    
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>    