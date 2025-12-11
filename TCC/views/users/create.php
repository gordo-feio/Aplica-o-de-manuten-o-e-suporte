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
    if (empty($password)) $errors[] = 'Senha é obrigatória';
    if (strlen($password) < 6) $errors[] = 'Senha deve ter no mínimo 6 caracteres';
    if ($password !== $confirm_password) $errors[] = 'As senhas não coincidem';
    if (empty($role)) $errors[] = 'Perfil é obrigatório';

    // Verificar se email já existe
    if (empty($errors) && $userModel->emailExists($email)) {
        $errors[] = 'Este email já está cadastrado';
    }

    if (empty($errors)) {
        $userData = [
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'phone' => $phone,
            'status' => $status
        ];

        if ($userModel->create($userData)) {
            $success = true;
            $_SESSION['success_message'] = 'Usuário cadastrado com sucesso!';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Erro ao cadastrar usuário. Tente novamente.';
        }
    }
}

$pageTitle = 'Novo Usuário';
include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="content-header">
        <h1><i class="fas fa-user-plus"></i> Novo Usuário</h1>
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
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" 
                               required class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                               required class="form-control">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Telefone</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" 
                               class="form-control" placeholder="(00) 00000-0000">
                    </div>

                    <div class="form-group">
                        <label for="role">Perfil *</label>
                        <select id="role" name="role" required class="form-control">
                            <option value="">Selecione o perfil</option>
                            <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>
                                Administrador
                            </option>
                            <option value="technician" <?= ($_POST['role'] ?? '') === 'technician' ? 'selected' : '' ?>>
                                Técnico
                            </option>
                            <option value="attendant" <?= ($_POST['role'] ?? '') === 'attendant' ? 'selected' : '' ?>>
                                Atendente
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Credenciais de Acesso</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Senha *</label>
                        <input type="password" id="password" name="password" 
                               required class="form-control" minlength="6">
                        <small class="form-text">Mínimo de 6 caracteres</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirmar Senha *</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               required class="form-control" minlength="6">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="active" <?= ($_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>
                                Ativo
                            </option>
                            <option value="inactive" <?= ($_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>
                                Inativo
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Cadastrar Usuário
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
    
    if (password !== confirmPassword) {
        this.setCustomValidity('As senhas não coincidem');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include_once '../../includes/footer.php'; ?>