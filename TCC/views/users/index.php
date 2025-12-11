<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once '../../classes/User.php';

session_start();

$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['user_type'] !== 'employee') {
    header('Location: ../auth/login.php');
    exit;
}

$db = new Database();
$userModel = new User($db->getConnection());

// Filtros
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$users = $userModel->getAllUsers($search, $role_filter, $status_filter);

$pageTitle = 'Gerenciar Usuários';
include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="content-header">
        <h1><i class="fas fa-users"></i> Gerenciar Usuários</h1>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Novo Usuário
        </a>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <input type="text" name="search" placeholder="Buscar por nome ou email..." 
                       value="<?= htmlspecialchars($search) ?>" class="filter-input">
            </div>
            
            <div class="filter-group">
                <select name="role" class="filter-select">
                    <option value="">Todos os Perfis</option>
                    <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Administrador</option>
                    <option value="technician" <?= $role_filter === 'technician' ? 'selected' : '' ?>>Técnico</option>
                    <option value="attendant" <?= $role_filter === 'attendant' ? 'selected' : '' ?>>Atendente</option>
                </select>
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

    <!-- Tabela de Usuários -->
    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Perfil</th>
                    <th>Status</th>
                    <th>Data de Cadastro</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" class="no-data">Nenhum usuário encontrado</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                    </div>
                                    <strong><?= htmlspecialchars($user['name']) ?></strong>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <span class="badge badge-role badge-<?= $user['role'] ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= $user['status'] === 'active' ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                            <td class="actions">
                                <a href="edit.php?id=<?= $user['id'] ?>" class="btn-icon" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                    <button onclick="toggleUserStatus(<?= $user['id'] ?>, '<?= $user['status'] ?>')" 
                                            class="btn-icon" title="<?= $user['status'] === 'active' ? 'Desativar' : 'Ativar' ?>">
                                        <i class="fas fa-<?= $user['status'] === 'active' ? 'ban' : 'check' ?>"></i>
                                    </button>
                                    <button onclick="deleteUser(<?= $user['id'] ?>)" class="btn-icon btn-danger" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleUserStatus(userId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const action = newStatus === 'active' ? 'ativar' : 'desativar';
    
    if (confirm(`Deseja realmente ${action} este usuário?`)) {
        fetch('../../ajax/toggle_user_status.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({user_id: userId, status: newStatus})
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        });
    }
}

function deleteUser(userId) {
    if (confirm('Deseja realmente excluir este usuário? Esta ação não pode ser desfeita.')) {
        fetch('../../ajax/delete_user.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({user_id: userId})
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        });
    }
}
</script>

<?php include_once '../../includes/footer.php'; ?>