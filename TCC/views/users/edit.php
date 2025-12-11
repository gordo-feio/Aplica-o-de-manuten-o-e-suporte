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

$userId = $_GET['id'] ?? null;
if (!$userId) {
    header('Location: index.php');
    exit;
}

$db = new Database();
$userModel = new User($db->getConnection());
$user = $userModel->getById($userId);

if (!$user) {
    $_SESSION['error_message'] = 'Usuário não encontrado';
    header('Location: index.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';

    // Validações
    if (empty($name)) $errors[] = 'Nome é obrigatório';
    if (empty($email)) $errors[] = 'Email é obrigatório';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido';
    if (empty($role)) $errors[] = 'Perfil é obrigatório';

    // Validar senha apenas se foi preenchida
    if (!empty($password)) {
        if (strlen($password) < 6) $errors[] = 'Senha deve ter no mínimo 6 caracteres';
        if ($password !== $confirm_password) $errors[] = 'As senhas não coincidem';
    }

    // Verificar se email já existe (exceto para o próprio usuário)
    if (empty($errors) && $email !== $user['email'] && $userModel->emailExists($email)) {
        $errors[] = 'Este email já está cadastrado';
    }

    if (empty($errors)) {
        $userData = [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'phone' => $phone,
            'status' => $status
        ];

        // Atualizar senha apenas se foi preenchida
        if (!empty($password)) {
            $userData['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        if ($userModel->update($userId, $userData)) {
            $_SESSION['success_message'] = 'Usuário atualizado com sucesso!';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Erro ao atualizar usuário. Tente novamente.';
        }
    }
}

$pageTitle = 'Editar Usuário';
include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="content-header">
        <h1><i class="fas fa-user-edit"></i> Editar Usuário</h1>
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>

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

    <div class="form-card">
        <form method="POST" class="user-form">
            <div class="form-section">
                <h3>Informações Pessoais</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Nome Completo *</label>
                        <input type="text" id="name" name="name" 
                               value="<?= htmlspecialchars($_POST['name'] ?? $user['name']) ?>" 
                               required class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" 
                               value="<?= htmlspecialchars($_POST['email'] ?? $user['email']) ?>" 
                               required class="form-control">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Telefone</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? '') ?>" 
                               class="form-control" placeholder="(00) 00000-0000">
                    </div>

                    <div class="form-group">
                        <label for="role">Perfil *</label>
                        <select id="role" name="role" required class="form-control"
                                <?= $user['id'] === $_SESSION['user_id'] ? 'disabled' : '' ?>>
                            <option value="">Selecione o perfil</option>
                            <option value="admin" <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>
                                Administrador
                            </option>
                            <option value="technician" <?= ($user['role'] ?? '') === 'technician' ? 'selected' : '' ?>>
                                Técnico
                            </option>
                            <option value="attendant" <?= ($user['role'] ?? '') === 'attendant' ? 'selected' : '' ?>>
                                Atendente
                            </option>
                        </select>
                        <?php if ($user['id'] === $_SESSION['user_id']): ?>
                            <input type="hidden" name="role" value="<?= $user['role'] ?>">
                            <small class="form-text">Você não pode alterar seu próprio perfil</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Alterar Senha (opcional)</h3>
                <p class="form-description">Deixe em branco se não deseja alterar a senha</p>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Nova Senha</label>
                        <input type="password" id="password" name="password" 
                               class="form-control" minlength="6">
                        <small class="form-text">Mínimo de 6 caracteres</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirmar Nova Senha</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="form-control" minlength="6">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Status</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Status do Usuário</label>
                        <select id="status" name="status" class="form-control"
                                <?= $user['id'] === $_SESSION['user_id'] ? 'disabled' : '' ?>>
                            <option value="active" <?= ($user['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>
                                Ativo
                            </option>
                            <option value="inactive" <?= ($user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>
                                Inativo
                            </option>
                        </select>
                        <?php if ($user['id'] === $_SESSION['user_id']): ?>
                            <input type="hidden" name="status" value="<?= $user['status'] ?>">
                            <small class="form-text">Você não pode desativar sua própria conta</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
                <a href="index.php" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
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
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (password && confirmPassword && password !== confirmPassword) {
        this.setCustomValidity('As senhas não coincidem');
    } else {
        this.setCustomValidity('');
    }
});

// Se o campo de senha for preenchido, tornar confirmação obrigatória
document.getElementById('password').addEventListener('input', function() {
    const confirmField = document.getElementById('confirm_password');
    if (this.value) {
        confirmField.required = true;
    } else {
        confirmField.required = false;
    }
});
</script>

<?php include_once '../../includes/footer.php'; ?>