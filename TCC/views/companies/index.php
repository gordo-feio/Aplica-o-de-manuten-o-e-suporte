<?php
require_once '../../config/config.php'; 
require_once '../../classes/Auth.php';
require_once '../../classes/Company.php';

session_start();

$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['user_type'] !== 'employee') {
    header('Location: ../auth/login.php');
    exit;
}

$db = new Database();
$companyModel = new Company($db->getConnection());

// Filtros
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$companies = $companyModel->getAll($search, $status_filter);

$pageTitle = 'Gerenciar Empresas';
include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="content-header">
        <h1><i class="fas fa-building"></i> Gerenciar Empresas</h1>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nova Empresa
        </a>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <input type="text" name="search" placeholder="Buscar por nome, CNPJ ou email..." 
                       value="<?= htmlspecialchars($search) ?>" class="filter-input">
            </div>

            <div class="filter-group">
                <select name="status" class="filter-select">
                    <option value="">Todos os Status</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Ativo</option>
                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                </select>
            </div>

            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <a href="index.php" class="btn btn-outline">Limpar</a>
        </form>
    </div>

    <!-- Cards de Empresas -->
    <div class="companies-grid">
        <?php if (empty($companies)): ?>
            <div class="no-data-message">
                <i class="fas fa-building"></i>
                <p>Nenhuma empresa encontrada</p>
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Cadastrar Primeira Empresa
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($companies as $company): ?>
                <div class="company-card">
                    <div class="company-header">
                        <div class="company-avatar">
                            <?= strtoupper(substr($company['name'], 0, 2)) ?>
                        </div>
                        <div class="company-status">
                            <span class="badge badge-<?= $company['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= $company['status'] === 'active' ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </div>
                    </div>

                    <div class="company-info">
                        <h3><?= htmlspecialchars($company['name']) ?></h3>
                        
                        <div class="info-item">
                            <i class="fas fa-id-card"></i>
                            <span><?= htmlspecialchars($company['cnpj']) ?></span>
                        </div>

                        <div class="info-item">
                            <i class="fas fa-envelope"></i>
                            <span><?= htmlspecialchars($company['email']) ?></span>
                        </div>

                        <div class="info-item">
                            <i class="fas fa-phone"></i>
                            <span><?= htmlspecialchars($company['phone'] ?? 'Não informado') ?></span>
                        </div>

                        <div class="info-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?= htmlspecialchars($company['address'] ?? 'Não informado') ?></span>
                        </div>

                        <?php if (!empty($company['contact_person'])): ?>
                            <div class="info-item">
                                <i class="fas fa-user"></i>
                                <span><?= htmlspecialchars($company['contact_person']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="company-stats">
                        <div class="stat">
                            <span class="stat-value"><?= $company['ticket_count'] ?? 0 ?></span>
                            <span class="stat-label">Tickets</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value"><?= $company['open_tickets'] ?? 0 ?></span>
                            <span class="stat-label">Abertos</span>
                        </div>
                    </div>

                    <div class="company-footer">
                        <small class="text-muted">
                            Cadastrado em <?= date('d/m/Y', strtotime($company['created_at'])) ?>
                        </small>
                    </div>

                    <div class="company-actions">
                        <a href="edit.php?id=<?= $company['id'] ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                        <button onclick="toggleCompanyStatus(<?= $company['id'] ?>, '<?= $company['status'] ?>')" 
                                class="btn btn-sm btn-<?= $company['status'] === 'active' ? 'warning' : 'success' ?>">
                            <i class="fas fa-<?= $company['status'] === 'active' ? 'ban' : 'check' ?>"></i>
                            <?= $company['status'] === 'active' ? 'Desativar' : 'Ativar' ?>
                        </button>
                        <button onclick="deleteCompany(<?= $company['id'] ?>)" class="btn btn-sm btn-danger">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleCompanyStatus(companyId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const action = newStatus === 'active' ? 'ativar' : 'desativar';
    
    if (confirm(`Deseja realmente ${action} esta empresa?`)) {
        fetch('../../ajax/toggle_company_status.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({company_id: companyId, status: newStatus})
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        })
        .catch(err => alert('Erro ao processar solicitação'));
    }
}

function deleteCompany(companyId) {
    if (confirm('Deseja realmente excluir esta empresa? Todos os tickets associados também serão removidos. Esta ação não pode ser desfeita.')) {
        fetch('../../ajax/delete_company.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({company_id: companyId})
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        })
        .catch(err => alert('Erro ao processar solicitação'));
    }
}
</script>

<?php include_once '../../includes/footer.php'; ?>