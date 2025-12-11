<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once '../../classes/Company.php';

session_start();

$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['user_type'] !== 'employee') {
    header('Location: ../auth/login.php');
    exit;
}

$db = new Database();
$companyModel = new Company($db->getConnection());

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $cnpj = preg_replace('/\D/', '', $_POST['cnpj'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip_code = preg_replace('/\D/', '', $_POST['zip_code'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validações
    if (empty($name)) $errors[] = 'Nome da empresa é obrigatório';
    if (empty($cnpj)) $errors[] = 'CNPJ é obrigatório';
    if (strlen($cnpj) !== 14) $errors[] = 'CNPJ deve ter 14 dígitos';
    if (empty($email)) $errors[] = 'Email é obrigatório';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido';
    if (empty($password)) $errors[] = 'Senha é obrigatória';
    if (strlen($password) < 6) $errors[] = 'Senha deve ter no mínimo 6 caracteres';
    if ($password !== $confirm_password) $errors[] = 'As senhas não coincidem';

    // Verificar se CNPJ já existe
    if (empty($errors) && $companyModel->cnpjExists($cnpj)) {
        $errors[] = 'Este CNPJ já está cadastrado';
    }

    // Verificar se email já existe
    if (empty($errors) && $companyModel->emailExists($email)) {
        $errors[] = 'Este email já está cadastrado';
    }

    if (empty($errors)) {
        $companyData = [
            'name' => $name,
            'cnpj' => $cnpj,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'phone' => $phone,
            'contact_person' => $contact_person,
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'zip_code' => $zip_code,
            'status' => $status
        ];

        if ($companyModel->create($companyData)) {
            $_SESSION['success_message'] = 'Empresa cadastrada com sucesso!';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Erro ao cadastrar empresa. Tente novamente.';
        }
    }
}

$pageTitle = 'Nova Empresa';
include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="content-header">
        <h1><i class="fas fa-building"></i> Nova Empresa</h1>
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
        <form method="POST" class="company-form">
            <div class="form-section">
                <h3>Informações da Empresa</h3>
                
                <div class="form-row">
                    <div class="form-group form-group-full">
                        <label for="name">Nome da Empresa *</label>
                        <input type="text" id="name" name="name" 
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" 
                               required class="form-control">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="cnpj">CNPJ *</label>
                        <input type="text" id="cnpj" name="cnpj" 
                               value="<?= htmlspecialchars($_POST['cnpj'] ?? '') ?>" 
                               required class="form-control" 
                               placeholder="00.000.000/0000-00"
                               maxlength="18">
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
                               class="form-control" placeholder="(00) 0000-0000">
                    </div>

                    <div class="form-group">
                        <label for="contact_person">Pessoa de Contato</label>
                        <input type="text" id="contact_person" name="contact_person" 
                               value="<?= htmlspecialchars($_POST['contact_person'] ?? '') ?>" 
                               class="form-control">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Endereço</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="zip_code">CEP</label>
                        <input type="text" id="zip_code" name="zip_code" 
                               value="<?= htmlspecialchars($_POST['zip_code'] ?? '') ?>" 
                               class="form-control" placeholder="00000-000"
                               maxlength="9">
                    </div>

                    <div class="form-group form-group-grow">
                        <label for="address">Endereço Completo</label>
                        <input type="text" id="address" name="address" 
                               value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" 
                               class="form-control" 
                               placeholder="Rua, número, bairro">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group form-group-grow">
                        <label for="city">Cidade</label>
                        <input type="text" id="city" name="city" 
                               value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" 
                               class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="state">Estado</label>
                        <select id="state" name="state" class="form-control">
                            <option value="">Selecione</option>
                            <option value="AC" <?= ($_POST['state'] ?? '') === 'AC' ? 'selected' : '' ?>>AC</option>
                            <option value="AL" <?= ($_POST['state'] ?? '') === 'AL' ? 'selected' : '' ?>>AL</option>
                            <option value="AP" <?= ($_POST['state'] ?? '') === 'AP' ? 'selected' : '' ?>>AP</option>
                            <option value="AM" <?= ($_POST['state'] ?? '') === 'AM' ? 'selected' : '' ?>>AM</option>
                            <option value="BA" <?= ($_POST['state'] ?? '') === 'BA' ? 'selected' : '' ?>>BA</option>
                            <option value="CE" <?= ($_POST['state'] ?? '') === 'CE' ? 'selected' : '' ?>>CE</option>
                            <option value="DF" <?= ($_POST['state'] ?? '') === 'DF' ? 'selected' : '' ?>>DF</option>
                            <option value="ES" <?= ($_POST['state'] ?? '') === 'ES' ? 'selected' : '' ?>>ES</option>
                            <option value="GO" <?= ($_POST['state'] ?? '') === 'GO' ? 'selected' : '' ?>>GO</option>
                            <option value="MA" <?= ($_POST['state'] ?? '') === 'MA' ? 'selected' : '' ?>>MA</option>
                            <option value="MT" <?= ($_POST['state'] ?? '') === 'MT' ? 'selected' : '' ?>>MT</option>
                            <option value="MS" <?= ($_POST['state'] ?? '') === 'MS' ? 'selected' : '' ?>>MS</option>
                            <option value="MG" <?= ($_POST['state'] ?? '') === 'MG' ? 'selected' : '' ?>>MG</option>
                            <option value="PA" <?= ($_POST['state'] ?? '') === 'PA' ? 'selected' : '' ?>>PA</option>
                            <option value="PB" <?= ($_POST['state'] ?? '') === 'PB' ? 'selected' : '' ?>>PB</option>
                            <option value="PR" <?= ($_POST['state'] ?? '') === 'PR' ? 'selected' : '' ?>>PR</option>
                            <option value="PE" <?= ($_POST['state'] ?? '') === 'PE' ? 'selected' : '' ?>>PE</option>
                            <option value="PI" <?= ($_POST['state'] ?? '') === 'PI' ? 'selected' : '' ?>>PI</option>
                            <option value="RJ" <?= ($_POST['state'] ?? '') === 'RJ' ? 'selected' : '' ?>>RJ</option>
                            <option value="RN" <?= ($_POST['state'] ?? '') === 'RN' ? 'selected' : '' ?>>RN</option>
                            <option value="RS" <?= ($_POST['state'] ?? '') === 'RS' ? 'selected' : '' ?>>RS</option>
                            <option value="RO" <?= ($_POST['state'] ?? '') === 'RO' ? 'selected' : '' ?>>RO</option>
                            <option value="RR" <?= ($_POST['state'] ?? '') === 'RR' ? 'selected' : '' ?>>RR</option>
                            <option value="SC" <?= ($_POST['state'] ?? '') === 'SC' ? 'selected' : '' ?>>SC</option>
                            <option value="SP" <?= ($_POST['state'] ?? '') === 'SP' ? 'selected' : '' ?>>SP</option>
                            <option value="SE" <?= ($_POST['state'] ?? '') === 'SE' ? 'selected' : '' ?>>SE</option>
                            <option value="TO" <?= ($_POST['state'] ?? '') === 'TO' ? 'selected' : '' ?>>TO</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Credenciais de Acesso</h3>
                <p class="form-description">A empresa usará o email cadastrado para fazer login no sistema</p>
                
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
                    <i class="fas fa-save"></i> Cadastrar Empresa
                </button>
                <a href="index.php" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
// Máscara de CNPJ
document.getElementById('cnpj').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 14) value = value.slice(0, 14);
    
    if (value.length > 12) {
        value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{0,2}).*/, '$1.$2.$3/$4-$5');
    } else if (value.length > 8) {
        value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{0,4})/, '$1.$2.$3/$4');
    } else if (value.length > 5) {
        value = value.replace(/^(\d{2})(\d{3})(\d{0,3})/, '$1.$2.$3');
    } else if (value.length > 2) {
        value = value.replace(/^(\d{2})(\d{0,3})/, '$1.$2');
    }
    
    e.target.value = value;
});

// Máscara de Telefone
document.getElementById('phone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 11) value = value.slice(0, 11);
    
    if (value.length > 6) {
        value = value.replace(/^(\d{2})(\d{4,5})(\d{0,4}).*/, '($1) $2-$3');
    } else if (value.length > 2) {
        value = value.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
    } else if (value.length > 0) {
        value = value.replace(/^(\d*)/, '($1');
    }
    
    e.target.value = value;
});

// Máscara de CEP
document.getElementById('zip_code').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 8) value = value.slice(0, 8);
    
    if (value.length > 5) {
        value = value.replace(/^(\d{5})(\d{0,3})/, '$1-$2');
    }
    
    e.target.value = value;
});

// Buscar endereço por CEP (ViaCEP API)
document.getElementById('zip_code').addEventListener('blur', function() {
    const cep = this.value.replace(/\D/g, '');
    
    if (cep.length === 8) {
        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(res => res.json())
            .then(data => {
                if (!data.erro) {
                    document.getElementById('address').value = `${data.logradouro}, ${data.bairro}`;
                    document.getElementById('city').value = data.localidade;
                    document.getElementById('state').value = data.uf;
                }
            })
            .catch(err => console.log('Erro ao buscar CEP'));
    }
});

// Validação de senhas
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