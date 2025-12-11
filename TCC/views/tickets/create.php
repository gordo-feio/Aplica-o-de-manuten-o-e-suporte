<?php
/**
 * Criar Novo Ticket - Design Moderno e Profissional
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

// Verificar se é empresa
requireLogin();
requireCompany();

$pageTitle = 'Criar Novo Ticket - ' . SYSTEM_NAME;

// Buscar dados da empresa para preencher endereço
$pdo = getConnection();
$stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([getCurrentCompanyId()]);
$company = $stmt->fetch();

// Incluir header
include __DIR__ . '/../../includes/header.php';
?>

<style>
/* ================================================
   SISTEMA DE TEMA CLARO/ESCURO
   ================================================ */
:root {
    --bg-primary: #ffffff;
    --bg-secondary: #f8f9fa;
    --text-primary: #212529;
    --text-secondary: #6c757d;
    --border-color: #dee2e6;
    --card-shadow: 0 2px 8px rgba(0,0,0,0.08);
    --gradient-start: #667eea;
    --gradient-end: #764ba2;
}

[data-theme="dark"] {
    --bg-primary: #1a1d23;
    --bg-secondary: #252932;
    --text-primary: #e9ecef;
    --text-secondary: #adb5bd;
    --border-color: #343a40;
    --card-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

body {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    transition: background-color 0.3s ease, color 0.3s ease;
}

/* ================================================
   HEADER HERO
   ================================================ */
.create-hero {
    background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
    padding: 2.5rem 0 3rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 24px 24px;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.2);
}

.hero-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
}

.hero-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.hero-icon {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.hero-title h1 {
    font-size: 28px;
    font-weight: 700;
    color: white;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.hero-subtitle {
    color: rgba(255, 255, 255, 0.9);
    font-size: 15px;
    margin: 0.5rem 0 0 0;
}

/* ================================================
   FORM CARD
   ================================================ */
.form-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: var(--card-shadow);
    padding: 2rem;
    margin-bottom: 2rem;
}

.form-section {
    margin-bottom: 2rem;
}

.form-section-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-section-title i {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
    color: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

/* ================================================
   FORM INPUTS MODERNOS
   ================================================ */
.form-label {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 14px;
}

.form-label .required {
    color: #dc3545;
    margin-left: 3px;
}

.form-control,
.form-select {
    background-color: var(--bg-primary);
    border: 2px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 10px;
    padding: 0.75rem 1rem;
    font-size: 15px;
    transition: all 0.3s ease;
}

.form-control:focus,
.form-select:focus {
    background-color: var(--bg-primary);
    border-color: var(--gradient-start);
    color: var(--text-primary);
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
}

.form-control::placeholder {
    color: var(--text-secondary);
    opacity: 0.6;
}

.form-text {
    color: var(--text-secondary);
    font-size: 13px;
    margin-top: 0.5rem;
}

textarea.form-control {
    min-height: 120px;
    resize: vertical;
}

/* ================================================
   FILE UPLOAD
   ================================================ */
.file-upload-area {
    border: 2px dashed var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
    background: var(--bg-secondary);
}

.file-upload-area:hover {
    border-color: var(--gradient-start);
    background: var(--bg-primary);
}

.file-upload-area i {
    font-size: 48px;
    color: var(--text-secondary);
    margin-bottom: 1rem;
}

.file-upload-area .upload-text {
    color: var(--text-primary);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.file-upload-area .upload-hint {
    color: var(--text-secondary);
    font-size: 13px;
}

#filePreview {
    margin-top: 1rem;
}

.file-preview-item {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 0.75rem 1rem;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.file-preview-item i {
    font-size: 24px;
    color: var(--gradient-start);
}

.file-preview-info {
    flex: 1;
    min-width: 0;
}

.file-preview-name {
    color: var(--text-primary);
    font-weight: 600;
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.file-preview-size {
    color: var(--text-secondary);
    font-size: 12px;
}

/* ================================================
   SIDEBAR TIPS - CORRIGIDO
   ================================================ */
.sidebar-sticky-wrapper {
    position: relative;
}

.tips-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: var(--card-shadow);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

/* Sticky apenas em telas grandes */
@media (min-width: 992px) {
    .sidebar-sticky-wrapper {
        position: sticky;
        top: 20px;
        max-height: calc(100vh - 40px);
        overflow-y: auto;
    }
    
    /* Esconder scrollbar mas manter funcionalidade */
    .sidebar-sticky-wrapper::-webkit-scrollbar {
        width: 6px;
    }
    
    .sidebar-sticky-wrapper::-webkit-scrollbar-track {
        background: transparent;
    }
    
    .sidebar-sticky-wrapper::-webkit-scrollbar-thumb {
        background: var(--border-color);
        border-radius: 3px;
    }
    
    .sidebar-sticky-wrapper::-webkit-scrollbar-thumb:hover {
        background: var(--text-secondary);
    }
}

.tips-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tips-title i {
    color: #ffc107;
    font-size: 20px;
}

.tips-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.tips-list li {
    color: var(--text-secondary);
    font-size: 13px;
    line-height: 1.6;
    margin-bottom: 0.75rem;
    padding-left: 1.5rem;
    position: relative;
}

.tips-list li::before {
    content: "→";
    position: absolute;
    left: 0;
    color: var(--gradient-start);
    font-weight: bold;
}

/* ================================================
   PRIORITY INFO
   ================================================ */
.priority-info {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.priority-item {
    display: flex;
    align-items: start;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.priority-item:last-child {
    margin-bottom: 0;
}

.priority-badge {
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-weight: 600;
    font-size: 12px;
    white-space: nowrap;
}

.priority-desc {
    color: var(--text-secondary);
    font-size: 13px;
    line-height: 1.5;
}

/* ================================================
   BUTTONS
   ================================================ */
.btn-primary {
    background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
    border: none;
    padding: 0.875rem 2rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
}

.btn-outline-secondary {
    border: 2px solid var(--border-color);
    color: var(--text-primary);
    background: var(--bg-primary);
    padding: 0.875rem 2rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.3s ease;
}

.btn-outline-secondary:hover {
    border-color: var(--gradient-start);
    color: var(--gradient-start);
    background: var(--bg-primary);
}

/* ================================================
   RESPONSIVO
   ================================================ */
@media (max-width: 768px) {
    .create-hero {
        padding: 2rem 0 2.5rem 0;
    }
    
    .hero-header {
        flex-direction: column;
        text-align: center;
    }
    
    .hero-title h1 {
        font-size: 24px;
    }
    
    .form-card {
        padding: 1.5rem;
    }
    
    .tips-card {
        margin-bottom: 1rem;
    }
}
</style>

<!-- HERO SECTION -->
<div class="create-hero">
    <div class="hero-content">
        <div class="hero-header">
            <div class="hero-icon">
                <i class="fas fa-plus-circle"></i>
            </div>
            <div class="hero-title">
                <h1>Criar Novo Ticket</h1>
                <p class="hero-subtitle">Preencha os dados abaixo para abrir um chamado de suporte</p>
            </div>
        </div>
    </div>
</div>

<div class="container" style="max-width: 1200px; padding-bottom: 3rem;">
    <div class="row">
        
        <!-- FORMULÁRIO -->
        <div class="col-lg-8">
            <div class="form-card">
                
                <form method="POST" 
                      action="<?php echo BASE_URL; ?>controllers/TicketController.php?action=create" 
                      enctype="multipart/form-data"
                      id="createTicketForm">
                    
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <!-- INFORMAÇÕES BÁSICAS -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-info-circle"></i>
                            Informações do Problema
                        </div>
                        
                        <!-- Título -->
                        <div class="mb-4">
                            <label for="title" class="form-label">
                                Título do Problema <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="title" 
                                   name="title" 
                                   placeholder="Ex: Computador não liga após queda de energia"
                                   required
                                   maxlength="200">
                            <div class="form-text">Seja claro e objetivo</div>
                        </div>
                        
                        <!-- Categoria e Prioridade -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="category" class="form-label">
                                    Categoria <span class="required">*</span>
                                </label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">Selecione a categoria...</option>
                                    <?php foreach (TICKET_CATEGORIES as $key => $label): ?>
                                    <option value="<?php echo $key; ?>">
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="priority" class="form-label">
                                    Prioridade <span class="required">*</span>
                                </label>
                                <select class="form-select" id="priority" name="priority" required>
                                    <option value="">Selecione a prioridade...</option>
                                    <option value="low">🟢 Baixa - Pode aguardar</option>
                                    <option value="medium" selected>🟡 Média - Normal</option>
                                    <option value="high">🔴 Alta - Urgente</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Descrição -->
                        <div class="mb-4">
                            <label for="description" class="form-label">
                                Descrição Detalhada <span class="required">*</span>
                            </label>
                            <textarea class="form-control" 
                                      id="description" 
                                      name="description" 
                                      rows="6"
                                      placeholder="Descreva o problema em detalhes:&#10;• O que aconteceu?&#10;• Quando começou?&#10;• Já tentou alguma solução?&#10;• Tem alguma mensagem de erro?"
                                      required></textarea>
                            <div class="form-text">Quanto mais detalhes, melhor será o atendimento</div>
                        </div>
                    </div>
                    
                    <!-- LOCAL DO ATENDIMENTO -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Local do Atendimento
                        </div>
                        
                        <div class="mb-4">
                            <label for="address" class="form-label">
                                Endereço/Local
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="address" 
                                   name="address" 
                                   placeholder="Ex: Rua das Flores, 123 - Sala 5"
                                   value="<?php echo htmlspecialchars($company['address'] ?? ''); ?>">
                            <div class="form-text">Onde a equipe técnica deve ir (se necessário)</div>
                        </div>
                    </div>
                    
                    <!-- ANEXOS -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-paperclip"></i>
                            Anexar Arquivos (Opcional)
                        </div>
                        
                        <div class="file-upload-area" onclick="document.getElementById('attachments').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div class="upload-text">Clique aqui para adicionar arquivos</div>
                            <div class="upload-hint">
                                Fotos do problema, prints de erros, etc.<br>
                                <small>Tipos aceitos: JPG, PNG, GIF, PDF, DOC, XLS, TXT | Máx: 5MB por arquivo</small>
                            </div>
                        </div>
                        
                        <input type="file" 
                               class="d-none" 
                               id="attachments" 
                               name="attachments[]"
                               multiple
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                        
                        <div id="filePreview"></div>
                    </div>
                    
                    <!-- BOTÕES -->
                    <div class="d-flex justify-content-between align-items-center pt-4 border-top" style="border-color: var(--border-color) !important;">
                        <a href="<?php echo BASE_URL; ?>views/tickets/my-tickets.php" 
                           class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>
                            Criar Ticket
                        </button>
                    </div>
                    
                </form>
                
            </div>
        </div>
        
        <!-- SIDEBAR COM DICAS -->
        <div class="col-lg-4">
            <div class="sidebar-sticky-wrapper">
                
                <!-- Dicas -->
                <div class="tips-card">
                    <div class="tips-title">
                        <i class="fas fa-lightbulb"></i>
                        Dicas para um Bom Atendimento
                    </div>
                    <ul class="tips-list">
                        <li><strong>Seja específico:</strong> Descreva exatamente o que está acontecendo</li>
                        <li><strong>Anexe imagens:</strong> Fotos ajudam muito a entender o problema</li>
                        <li><strong>Informe detalhes:</strong> Modelo do equipamento, mensagens de erro, etc.</li>
                        <li><strong>Prioridade correta:</strong> Use "Alta" apenas para urgências reais</li>
                        <li><strong>Local preciso:</strong> Informe onde o técnico deve ir</li>
                    </ul>
                </div>
                
                <!-- Níveis de Prioridade -->
                <div class="tips-card">
                    <div class="tips-title">
                        <i class="fas fa-info-circle"></i>
                        Níveis de Prioridade
                    </div>
                    
                    <div class="priority-info">
                        <div class="priority-item">
                            <span class="priority-badge bg-danger text-white">🔴 Alta</span>
                            <div class="priority-desc">
                                Sistema parado, perda de dados, urgência extrema
                            </div>
                        </div>
                        
                        <div class="priority-item">
                            <span class="priority-badge bg-warning text-dark">🟡 Média</span>
                            <div class="priority-desc">
                                Problemas que afetam o trabalho mas têm alternativas
                            </div>
                        </div>
                        
                        <div class="priority-item">
                            <span class="priority-badge bg-success text-white">🟢 Baixa</span>
                            <div class="priority-desc">
                                Melhorias, dúvidas, problemas que podem aguardar
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tempo de Resposta -->
                <div class="tips-card">
                    <div class="tips-title">
                        <i class="fas fa-clock"></i>
                        Tempo de Resposta
                    </div>
                    <p style="color: var(--text-secondary); font-size: 14px; line-height: 1.6; margin: 0;">
                        Nossa equipe irá analisar seu ticket e retornará o mais breve possível.
                        Você receberá notificações sobre cada atualização.
                    </p>
                </div>
                
            </div>
        </div>
        
    </div>
</div>

<script>
$(document).ready(function() {
    
    // Preview de arquivos selecionados
    $('#attachments').change(function() {
        const files = this.files;
        const preview = $('#filePreview');
        preview.html('');
        
        if (files.length > 0) {
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                const icon = getFileIcon(file.name);
                
                preview.append(`
                    <div class="file-preview-item">
                        <i class="fas ${icon}"></i>
                        <div class="file-preview-info">
                            <div class="file-preview-name">${file.name}</div>
                            <div class="file-preview-size">${fileSize} MB</div>
                        </div>
                        <span class="badge bg-secondary">${fileSize} MB</span>
                    </div>
                `);
            }
        }
    });
    
    // Ícone baseado na extensão
    function getFileIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const icons = {
            'jpg': 'fa-image',
            'jpeg': 'fa-image',
            'png': 'fa-image',
            'gif': 'fa-image',
            'pdf': 'fa-file-pdf',
            'doc': 'fa-file-word',
            'docx': 'fa-file-word',
            'xls': 'fa-file-excel',
            'xlsx': 'fa-file-excel',
            'txt': 'fa-file-alt'
        };
        return icons[ext] || 'fa-file';
    }
    
    // Validação do formulário
    $('#createTicketForm').submit(function(e) {
        const title = $('#title').val().trim();
        const description = $('#description').val().trim();
        const category = $('#category').val();
        const priority = $('#priority').val();
        
        if (!title || !description || !category || !priority) {
            e.preventDefault();
            showNotification('error', 'Por favor, preencha todos os campos obrigatórios.');
            return false;
        }
        
        if (title.length < 10) {
            e.preventDefault();
            showNotification('error', 'O título deve ter pelo menos 10 caracteres.');
            return false;
        }
        
        if (description.length < 20) {
            e.preventDefault();
            showNotification('error', 'A descrição deve ter pelo menos 20 caracteres.');
            return false;
        }
        
        // Verificar tamanho dos arquivos
        const files = document.getElementById('attachments').files;
        for (let i = 0; i < files.length; i++) {
            if (files[i].size > <?php echo MAX_FILE_SIZE; ?>) {
                e.preventDefault();
                showNotification('error', 'Um ou mais arquivos excedem o tamanho máximo de 5MB.');
                return false;
            }
        }
        
        // Mostrar loading no botão
        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Criando...').prop('disabled', true);
    });
    
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
        
        setTimeout(function() {
            notification.alert('close');
        }, 5000);
    }
    
    // Animação de entrada dos elementos
    $('.form-section').each(function(index) {
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
        }, index * 100);
    });
    
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>