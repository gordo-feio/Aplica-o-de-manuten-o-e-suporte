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

$companyId = $_GET['id'] ?? null;
if (!$companyId) {
    header('Location: index.php');
    exit;
}

$db = new Database();
$companyModel = new Company($db->getConnection());
$company = $companyModel->getById($companyId);

if (!$company) {
    $_SESSION['error_message'] = 'Empresa não encontrada';
    header('Location: index.php');
    exit;
}

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

    // Validar senha apenas se foi preenchida
    if (!empty($password)) {
        if (strlen($password) < 6) $errors[] = 'Senha deve ter no mínimo 6 caracteres';
        if ($password !== $confirm_password) $errors[] = 'As senhas não coincidem';
    }

    // Verificar se CNPJ já existe (exceto para a própria empresa)
    if (empty($errors) && $cnpj !== $company['cnpj'] && $companyModel->cnpjExists($cnpj)) {
        $errors[] = 'Este CNPJ já está cadastrado';
    }

    // Verificar se email já existe (exceto para a própria empresa)
    if (empty($errors) && $email !== $company['email'] && $companyModel->emailExists($email)) {
        $errors[] = 'Este email já está cadastrado';
    }

    if (empty($errors)) {
        $companyData = [
            'name' => $name,
            'cnpj' => $cnpj,
            'email' => $email,
            'phone' => $phone,
            'contact_person' => $contact_person,
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'zip_code' => $zip_code,
            'status' => $status
        ];

        // Atualizar senha apenas se foi preenchida
        if (!empty($password)) {
            $companyData['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        if ($companyModel->update($companyId, $companyData)) {
            $_SESSION['success_message'] = 'Empresa atualizada com sucesso!';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Erro ao atualizar empresa. Tente novamente.';
        }
    }
}

// Formatar CNPJ para exibição
$cnpj_formatted = preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $company['cnpj']);

$pageTitle = 'Editar Empresa';
include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="content-header">
        <h1><i class="fas fa-building"></i> Editar Empresa</h1>
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Empresa atualizada com sucesso!
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

    <div class="form-card">
        <form method="POST" class="company-form">
            <div class="form-section">
                <h3>Informações da Empresa</h3>
                
                <div class="form-row">
                    <div class="form-group form-group-full">
                        <label for="name">Nome da Empresa *</label>
                        <input type="text" id="name" name="name" 
                               value="<?= htmlspecialchars($_POST['name'] ?? $company['name']) ?>" 
                               required class="form-control">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="cnpj">CNPJ *</label>
                        <input type="text" id="cnpj" name="cnpj" 
                               value="<?= htmlspecialchars($_POST['cnpj'] ?? $cnpj_formatted) ?>" 
                               required class="form-control" 
                               placeholder="00.000.000/0000-00"
                               maxlength="18">
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" 
                               value="<?= htmlspecialchars($_POST['email'] ?? $company['email']) ?>" 
                               required class="form-control">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Telefone</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?= htmlspecialchars($_POST['phone'] ?? $company['phone'] ?? '') ?>" 
                               class="form-control" placeholder="(00) 0000-0000">
                    </div>

                    <div class="form-group">
                        <label for="contact_person">Pessoa de Contato</label>
                        <input type="text" id="contact_person" name="contact_person" 
                               value="<?= htmlspecialchars($_POST['contact_person'] ?? $company['contact_person'] ?? '') ?>" 
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
                               value="<?= htmlspecialchars($_POST['zip_code'] ?? $company['zip_code'] ?? '') ?>" 
                               class="form-control" placeholder="00000-000"
                               maxlength="9">
                    </div>

                    <div class="form-group form-group-grow">
                        <label for="address">Endereço Completo</label>
                        <input type="text" id="address" name="address" 
                               value="<?= htmlspecialchars($_POST['address'] ?? $company['address'] ?? '') ?>" 
                               class="form-control" 
                               placeholder="Rua, número, bairro">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group form-group-grow">
                        <label for="city">Cidade</label>
                        <input type="text" id="city" name="city" 
                               value="<?= htmlspecialchars($_POST['city'] ?? $company['city'] ?? '') ?>" 
                               class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="state">Estado</label>
                        <select id="state" name="state" class="form-control">
                            <option value="">Selecione</option>
                            <?php
                            $states = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                            $current_state = $_POST['state'] ?? $company['state'] ?? '';
                            foreach ($states as $st) {
                                $selected = $current_state === $st ? 'selected' : '';
                                echo "<option value=\"{$st}\" {$selected}>{$st}</option>";
                            }
                            ?>
                        </select>
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

                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="active" <?= ($company['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>
                                Ativo
                            </option>
                            <option value="inactive" <?= ($company['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>
                                Inativo
                            </option>
                        </select>
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

    <!-- Informações Adicionais -->
    <div class="info-card">
        <h3><i class="fas fa-info-circle"></i> Informações Adicionais</h3>
        <div class="info-grid">
            <div class="info-item">
                <label>Data de Cadastro</label>
                <span><?= date('d/m/Y H:i', strtotime($company['created_at'])) ?></span>
            </div>
            <div class="info-item">
                <label>Última Atualização</label>
                <span><?= date('d/m/Y H:i', strtotime($company['updated_at'] ?? $company['created_at'])) ?></span>
            </div>
            <div class="info-item">
                <label>Total de Tickets</label>
                <span><?= $company['ticket_count'] ?? 0 ?> tickets</span>
            </div>
            <div class="info-item">
                <label>Tickets Abertos</label>
                <span><?= $company['open_tickets'] ?? 0 ?> abertos</span>
            </div>
        </div>
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

// Buscar endereço por CEP
document.getElementById('zip_code').addEventListener('blur', function() {
    const cep = this.value.replace(/\D/g, '');
    
    if (cep.length === 8) {
        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(res => res.json())
            .then(data => {
                if (!data.erro) {
                    const currentAddress = document.getElementById('address').value;
                    if (!currentAddress || confirm('Deseja substituir o endereço atual pelo endereço encontrado?')) {
                        document.getElementById('address').value = `${data.logradouro}, ${data.bairro}`;
                        document.getElementById('city').value = data.localidade;
                        document.getElementById('state').value = data.uf;
                    }
                }
            })
            .catch(err => console.log('Erro ao buscar CEP'));
    }
});

// Validação de senhas
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