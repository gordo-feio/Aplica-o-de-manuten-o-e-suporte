<?php
require_once '../../config/config.php'; 
require_once '../../classes/Auth.php';

session_start();

$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['user_type'] !== 'employee' || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$errors = [];
$success = false;

// Carregar configurações atuais
$settings = [];
$stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_general') {
        $system_name = trim($_POST['system_name'] ?? '');
        $system_email = trim($_POST['system_email'] ?? '');
        $support_phone = trim($_POST['support_phone'] ?? '');
        $max_file_size = intval($_POST['max_file_size'] ?? 5);
        $tickets_per_page = intval($_POST['tickets_per_page'] ?? 20);

        // Validações
        if (empty($system_name)) $errors[] = 'Nome do sistema é obrigatório';
        if (empty($system_email)) $errors[] = 'Email do sistema é obrigatório';
        if (!filter_var($system_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido';
        if ($max_file_size < 1 || $max_file_size > 50) $errors[] = 'Tamanho máximo deve estar entre 1 e 50 MB';
        if ($tickets_per_page < 10 || $tickets_per_page > 100) $errors[] = 'Tickets por página deve estar entre 10 e 100';

        if (empty($errors)) {
            try {
                $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) 
                                       ON DUPLICATE KEY UPDATE setting_value = ?");
                
                $stmt->execute(['system_name', $system_name, $system_name]);
                $stmt->execute(['system_email', $system_email, $system_email]);
                $stmt->execute(['support_phone', $support_phone, $support_phone]);
                $stmt->execute(['max_file_size', $max_file_size, $max_file_size]);
                $stmt->execute(['tickets_per_page', $tickets_per_page, $tickets_per_page]);

                $success = true;
                // Recarregar configurações
                $settings = [
                    'system_name' => $system_name,
                    'system_email' => $system_email,
                    'support_phone' => $support_phone,
                    'max_file_size' => $max_file_size,
                    'tickets_per_page' => $tickets_per_page
                ];
            } catch (PDOException $e) {
                $errors[] = 'Erro ao salvar configurações: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'update_notifications') {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $notify_on_create = isset($_POST['notify_on_create']) ? 1 : 0;
        $notify_on_assign = isset($_POST['notify_on_assign']) ? 1 : 0;
        $notify_on_dispatch = isset($_POST['notify_on_dispatch']) ? 1 : 0;
        $notify_on_close = isset($_POST['notify_on_close']) ? 1 : 0;

        try {
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) 
                                   ON DUPLICATE KEY UPDATE setting_value = ?");
            
            $stmt->execute(['email_notifications', $email_notifications, $email_notifications]);
            $stmt->execute(['sms_notifications', $sms_notifications, $sms_notifications]);
            $stmt->execute(['notify_on_create', $notify_on_create, $notify_on_create]);
            $stmt->execute(['notify_on_assign', $notify_on_assign, $notify_on_assign]);
            $stmt->execute(['notify_on_dispatch', $notify_on_dispatch, $notify_on_dispatch]);
            $stmt->execute(['notify_on_close', $notify_on_close, $notify_on_close]);

            $success = true;
            $settings['email_notifications'] = $email_notifications;
            $settings['sms_notifications'] = $sms_notifications;
            $settings['notify_on_create'] = $notify_on_create;
            $settings['notify_on_assign'] = $notify_on_assign;
            $settings['notify_on_dispatch'] = $notify_on_dispatch;
            $settings['notify_on_close'] = $notify_on_close;
        } catch (PDOException $e) {
            $errors[] = 'Erro ao salvar notificações: ' . $e->getMessage();
        }
    }

    if ($action === 'update_priorities') {
        $auto_escalate = isset($_POST['auto_escalate']) ? 1 : 0;
        $low_priority_hours = intval($_POST['low_priority_hours'] ?? 48);
        $medium_priority_hours = intval($_POST['medium_priority_hours'] ?? 24);
        $high_priority_hours = intval($_POST['high_priority_hours'] ?? 4);

        if ($low_priority_hours < 1 || $low_priority_hours > 168) $errors[] = 'Horas prioridade baixa: 1-168h';
        if ($medium_priority_hours < 1 || $medium_priority_hours > 72) $errors[] = 'Horas prioridade média: 1-72h';
        if ($high_priority_hours < 1 || $high_priority_hours > 24) $errors[] = 'Horas prioridade alta: 1-24h';

        if (empty($errors)) {
            try {
                $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) 
                                       ON DUPLICATE KEY UPDATE setting_value = ?");
                
                $stmt->execute(['auto_escalate', $auto_escalate, $auto_escalate]);
                $stmt->execute(['low_priority_hours', $low_priority_hours, $low_priority_hours]);
                $stmt->execute(['medium_priority_hours', $medium_priority_hours, $medium_priority_hours]);
                $stmt->execute(['high_priority_hours', $high_priority_hours, $high_priority_hours]);

                $success = true;
                $settings['auto_escalate'] = $auto_escalate;
                $settings['low_priority_hours'] = $low_priority_hours;
                $settings['medium_priority_hours'] = $medium_priority_hours;
                $settings['high_priority_hours'] = $high_priority_hours;
            } catch (PDOException $e) {
                $errors[] = 'Erro ao salvar prioridades: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Configurações do Sistema';
include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="content-header">
        <h1><i class="fas fa-cog"></i> Configurações do Sistema</h1>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Configurações salvas com sucesso!
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

    <div class="settings-tabs">
        <div class="tabs-navigation">
            <button class="tab-btn active" data-tab="general">
                <i class="fas fa-sliders-h"></i> Geral
            </button>
            <button class="tab-btn" data-tab="notifications">
                <i class="fas fa-bell"></i> Notificações
            </button>
            <button class="tab-btn" data-tab="priorities">
                <i class="fas fa-exclamation-triangle"></i> Prioridades
            </button>
            <button class="tab-btn" data-tab="maintenance">
                <i class="fas fa-tools"></i> Manutenção
            </button>
        </div>

        <div class="tabs-content">
            <!-- Tab Geral -->
            <div class="tab-content active" id="general">
                <div class="form-card">
                    <h3>Configurações Gerais</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_general">
                        
                        <div class="form-group">
                            <label for="system_name">Nome do Sistema *</label>
                            <input type="text" id="system_name" name="system_name" 
                                   value="<?= htmlspecialchars($settings['system_name'] ?? 'Sistema de Suporte') ?>" 
                                   required class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="system_email">Email do Sistema *</label>
                            <input type="email" id="system_email" name="system_email" 
                                   value="<?= htmlspecialchars($settings['system_email'] ?? '') ?>" 
                                   required class="form-control">
                            <small class="form-text">Email usado para envio de notificações</small>
                        </div>

                        <div class="form-group">
                            <label for="support_phone">Telefone de Suporte</label>
                            <input type="tel" id="support_phone" name="support_phone" 
                                   value="<?= htmlspecialchars($settings['support_phone'] ?? '') ?>" 
                                   class="form-control" placeholder="(00) 0000-0000">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="max_file_size">Tamanho Máximo de Arquivo (MB)</label>
                                <input type="number" id="max_file_size" name="max_file_size" 
                                       value="<?= htmlspecialchars($settings['max_file_size'] ?? 5) ?>" 
                                       min="1" max="50" class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="tickets_per_page">Tickets por Página</label>
                                <input type="number" id="tickets_per_page" name="tickets_per_page" 
                                       value="<?= htmlspecialchars($settings['tickets_per_page'] ?? 20) ?>" 
                                       min="10" max="100" class="form-control">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Salvar Configurações
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tab Notificações -->
            <div class="tab-content" id="notifications">
                <div class="form-card">
                    <h3>Configurações de Notificações</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_notifications">
                        
                        <div class="form-section">
                            <h4>Canais de Notificação</h4>
                            
                            <div class="checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="email_notifications" 
                                           <?= ($settings['email_notifications'] ?? 1) ? 'checked' : '' ?>>
                                    <span>Notificações por Email</span>
                                </label>

                                <label class="checkbox-label">
                                    <input type="checkbox" name="sms_notifications" 
                                           <?= ($settings['sms_notifications'] ?? 0) ? 'checked' : '' ?>>
                                    <span>Notificações por SMS (requer integração)</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4>Eventos de Notificação</h4>
                            
                            <div class="checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="notify_on_create" 
                                           <?= ($settings['notify_on_create'] ?? 1) ? 'checked' : '' ?>>
                                    <span>Notificar ao criar ticket</span>
                                </label>

                                <label class="checkbox-label">
                                    <input type="checkbox" name="notify_on_assign" 
                                           <?= ($settings['notify_on_assign'] ?? 1) ? 'checked' : '' ?>>
                                    <span>Notificar ao assumir ticket</span>
                                </label>

                                <label class="checkbox-label">
                                    <input type="checkbox" name="notify_on_dispatch" 
                                           <?= ($settings['notify_on_dispatch'] ?? 1) ? 'checked' : '' ?>>
                                    <span>Notificar ao despachar equipe</span>
                                </label>

                                <label class="checkbox-label">
                                    <input type="checkbox" name="notify_on_close" 
                                           <?= ($settings['notify_on_close'] ?? 1) ? 'checked' : '' ?>>
                                    <span>Notificar ao finalizar ticket</span>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Salvar Notificações
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tab Prioridades -->
            <div class="tab-content" id="priorities">
                <div class="form-card">
                    <h3>Configurações de Prioridade</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_priorities">
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="auto_escalate" 
                                       <?= ($settings['auto_escalate'] ?? 0) ? 'checked' : '' ?>>
                                <span>Escalar automaticamente prioridade de tickets não atendidos</span>
                            </label>
                        </div>

                        <div class="form-section">
                            <h4>Tempo de Escalação (em horas)</h4>
                            <p class="form-description">Tempo máximo antes de escalar a prioridade</p>
                            
                            <div class="priority-config">
                                <div class="priority-item priority-low">
                                    <div class="priority-header">
                                        <i class="fas fa-arrow-down"></i>
                                        <span>Baixa Prioridade</span>
                                    </div>
                                    <input type="number" name="low_priority_hours" 
                                           value="<?= htmlspecialchars($settings['low_priority_hours'] ?? 48) ?>" 
                                           min="1" max="168" class="form-control">
                                    <small>1-168 horas (1 semana)</small>
                                </div>

                                <div class="priority-item priority-medium">
                                    <div class="priority-header">
                                        <i class="fas fa-minus"></i>
                                        <span>Média Prioridade</span>
                                    </div>
                                    <input type="number" name="medium_priority_hours" 
                                           value="<?= htmlspecialchars($settings['medium_priority_hours'] ?? 24) ?>" 
                                           min="1" max="72" class="form-control">
                                    <small>1-72 horas (3 dias)</small>
                                </div>

                                <div class="priority-item priority-high">
                                    <div class="priority-header">
                                        <i class="fas fa-exclamation"></i>
                                        <span>Alta Prioridade</span>
                                    </div>
                                    <input type="number" name="high_priority_hours" 
                                           value="<?= htmlspecialchars($settings['high_priority_hours'] ?? 4) ?>" 
                                           min="1" max="24" class="form-control">
                                    <small>1-24 horas (1 dia)</small>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Salvar Prioridades
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tab Manutenção -->
            <div class="tab-content" id="maintenance">
                <div class="form-card">
                    <h3>Ferramentas de Manutenção</h3>
                    
                    <div class="maintenance-tools">
                        <div class="tool-card">
                            <div class="tool-icon">
                                <i class="fas fa-database"></i>
                            </div>
                            <div class="tool-info">
                                <h4>Otimizar Banco de Dados</h4>
                                <p>Otimiza tabelas e libera espaço não utilizado</p>
                            </div>
                            <button onclick="optimizeDatabase()" class="btn btn-secondary">
                                <i class="fas fa-sync"></i> Otimizar
                            </button>
                        </div>

                        <div class="tool-card">
                            <div class="tool-icon">
                                <i class="fas fa-trash-alt"></i>
                            </div>
                            <div class="tool-info">
                                <h4>Limpar Logs Antigos</h4>
                                <p>Remove logs com mais de 90 dias</p>
                            </div>
                            <button onclick="cleanOldLogs()" class="btn btn-secondary">
                                <i class="fas fa-broom"></i> Limpar
                            </button>
                        </div>

                        <div class="tool-card">
                            <div class="tool-icon">
                                <i class="fas fa-file-archive"></i>
                            </div>
                            <div class="tool-info">
                                <h4>Backup do Sistema</h4>
                                <p>Gera backup completo do banco de dados</p>
                            </div>
                            <button onclick="generateBackup()" class="btn btn-secondary">
                                <i class="fas fa-download"></i> Gerar Backup
                            </button>
                        </div>

                        <div class="tool-card">
                            <div class="tool-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="tool-info">
                                <h4>Estatísticas do Sistema</h4>
                                <p>Visualizar estatísticas de uso e desempenho</p>
                            </div>
                            <a href="reports.php" class="btn btn-secondary">
                                <i class="fas fa-eye"></i> Ver Estatísticas
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Sistema de Tabs
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tabId = this.dataset.tab;
        
        // Remover active de todos
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        // Adicionar active no selecionado
        this.classList.add('active');
        document.getElementById(tabId).classList.add('active');
    });
});

// Funções de manutenção
function optimizeDatabase() {
    if (confirm('Deseja otimizar o banco de dados? Este processo pode levar alguns minutos.')) {
        fetch('../../ajax/optimize_database.php', {method: 'POST'})
            .then(res => res.json())
            .then(data => {
                alert(data.success ? 'Banco otimizado com sucesso!' : 'Erro: ' + data.message);
            });
    }
}

function cleanOldLogs() {
    if (confirm('Deseja remover logs com mais de 90 dias?')) {
        fetch('../../ajax/clean_old_logs.php', {method: 'POST'})
            .then(res => res.json())
            .then(data => {
                alert(data.success ? `${data.deleted} registros removidos` : 'Erro: ' + data.message);
            });
    }
}

function generateBackup() {
    if (confirm('Gerar backup do sistema?')) {
        window.location.href = '../../ajax/generate_backup.php';
    }
}
</script>

<?php include_once '../../includes/footer.php'; ?>