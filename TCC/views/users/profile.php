<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/user.php';
require_once __DIR__ . '/../../controllers/UserController.php';

// Verificar se está logado
requireLogin();

$pageTitle = 'profile - ' . SYSTEM_NAME;

// Conectar ao banco
$pdo = getConnection();




$errors = [];

$auth = new Auth();
$userModel = new User($pdo);

if (!$auth->isLoggedIn()) {
    redirect(BASE_URL . 'views/auth/login.php');
}

$userId = $_SESSION['user_id'];
$user = $userModel->getById($userId);

if (!$user) {
    die('Erro: Usuário não encontrado.');
}

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        // Validações
        if (empty($name)) $errors[] = 'Nome é obrigatório';
        if (empty($email)) $errors[] = 'Email é obrigatório';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido';

        // Verificar se email já existe (exceto para o próprio usuário)
        if (empty($errors) && $email !== $user['email'] && $userModel->emailExists($email)) {
            $errors[] = 'Este email já está cadastrado';
        }

        if (empty($errors)) {
            $userData = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone
            ];

            if ($userModel->update($_SESSION['user_id'], $userData)) {
                $_SESSION['user_name'] = $name;
                $success = true;
                $user = $userModel->getById($_SESSION['user_id']);
            } else {
                $errors[] = 'Erro ao atualizar perfil. Tente novamente.';
            }
        }
    }

    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validações
        if (empty($current_password)) $errors[] = 'Senha atual é obrigatória';
        if (empty($new_password)) $errors[] = 'Nova senha é obrigatória';
        if (strlen($new_password) < 6) $errors[] = 'Nova senha deve ter no mínimo 6 caracteres';
        if ($new_password !== $confirm_password) $errors[] = 'As senhas não coincidem';

        // Verificar senha atual
        if (empty($errors) && !password_verify($current_password, $user['password'])) {
            $errors[] = 'Senha atual incorreta';
        }

        if (empty($errors)) {
            $userData = [
                'password' => password_hash($new_password, PASSWORD_DEFAULT)
            ];

            if ($userModel->update($_SESSION['user_id'], $userData)) {
                $success = true;
            } else {
                $errors[] = 'Erro ao alterar senha. Tente novamente.';
            }
        }
    }
}

$pageTitle = 'Meu Perfil';
include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="content-header">
        <h1><i class="fas fa-user-circle"></i> Meu Perfil</h1>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Informações atualizadas com sucesso!
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="profile-grid">
        <!-- Card de Informações do Perfil -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar-large">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <h2><?= htmlspecialchars($user['name']) ?></h2>
                <p class="profile-role">
                    <span class="badge badge-role badge-<?= $user['role'] ?>">
                        <?= ucfirst($user['role']) ?>
                    </span>
                </p>
            </div>

            <div class="profile-info">
                <div class="info-item">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <label>Email</label>
                        <span><?= htmlspecialchars($user['email']) ?></span>
                    </div>
                </div>

                <div class="info-item">
                    <i class="fas fa-phone"></i>
                    <div>
                        <label>Telefone</label>
                        <span><?= htmlspecialchars($user['phone'] ?? 'Não informado') ?></span>
                    </div>
                </div>

                <div class="info-item">
                    <i class="fas fa-calendar"></i>
                    <div>
                        <label>Membro desde</label>
                        <span><?= date('d/m/Y', strtotime($user['created_at'])) ?></span>
                    </div>
                </div>

                <div class="info-item">
                    <i class="fas fa-circle"></i>
                    <div>
                        <label>Status</label>
                        <span class="badge badge-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?>">
                            <?= $user['status'] === 'active' ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulários -->
        <div class="profile-forms">
            <!-- Atualizar Perfil -->
            <div class="form-card">
                <h3><i class="fas fa-edit"></i> Atualizar Informações</h3>
                <form method="POST" class="profile-form">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label for="name">Nome Completo *</label>
                        <input type="text" id="name" name="name" 
                               value="<?= htmlspecialchars($user['name']) ?>" 
                               required class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" 
                               value="<?= htmlspecialchars($user['email']) ?>" 
                               required class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="phone">Telefone</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                               class="form-control" placeholder="(00) 00000-0000">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                </form>
            </div>

            <!-- Alterar Senha -->
            <div class="form-card">
                <h3><i class="fas fa-lock"></i> Alterar Senha</h3>
                <form method="POST" class="profile-form">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="current_password">Senha Atual *</label>
                        <input type="password" id="current_password" name="current_password" 
                               required class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="new_password">Nova Senha *</label>
                        <input type="password" id="new_password" name="new_password" 
                               required class="form-control" minlength="6">
                        <small class="form-text">Mínimo de 6 caracteres</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirmar Nova Senha *</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               required class="form-control" minlength="6">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-key"></i> Alterar Senha
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Máscara de telefone
document.getElementById('phone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 11) value = value.slice(0, 11);
    
    if (value.length > 6) {
        value = value.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3');
    } else if (value.length > 2) {
        value = value.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
    } else if (value.length > 0) {
        value = value.replace(/^(\d*)/, '($1');
    }
    
    e.target.value = value;
});

// Validação de senhas em tempo real
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('As senhas não coincidem');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include_once '../../includes/footer.php'; ?>