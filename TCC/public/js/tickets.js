/**
 * ============================================
 * TICKETS - Funcionalidades de Gerenciamento
 * Scripts específicos para manipulação de tickets
 * ============================================
 */

// ===== INICIALIZAÇÃO =====
document.addEventListener('DOMContentLoaded', function() {
    initTicketFunctions();
});

function initTicketFunctions() {
    // Inicializar botões de ação
    initTicketActions();
    
    // Inicializar filtros
    initTicketFilters();
    
    // Inicializar busca
    initTicketSearch();
    
    // Inicializar upload de arquivos
    initFileUpload();
    
    // Inicializar preview de anexos
    initAttachmentPreview();
    
    console.log('Funções de tickets inicializadas');
}

// ===== AÇÕES DOS TICKETS =====
function initTicketActions() {
    // Assumir ticket
    document.querySelectorAll('.btn-assume-ticket').forEach(btn => {
        btn.addEventListener('click', handleAssumeTicket);
    });
    
    // Despachar equipe
    document.querySelectorAll('.btn-dispatch-ticket').forEach(btn => {
        btn.addEventListener('click', handleDispatchTicket);
    });
    
    // Finalizar ticket
    document.querySelectorAll('.btn-close-ticket').forEach(btn => {
        btn.addEventListener('click', handleCloseTicket);
    });
    
    // Reabrir ticket
    document.querySelectorAll('.btn-reopen-ticket').forEach(btn => {
        btn.addEventListener('click', handleReopenTicket);
    });
}

/**
 * Assumir Ticket
 */
async function handleAssumeTicket(e) {
    e.preventDefault();
    
    const ticketId = this.dataset.ticketId;
    
    if (!confirm('Deseja assumir este ticket?')) {
        return;
    }
    
    window.AppUtils.showLoader();
    
    try {
        const response = await fetch('../ajax/assume_ticket.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ ticket_id: ticketId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.AppUtils.showAlert('Ticket assumido com sucesso!', 'success');
            
            // Atualizar interface
            updateTicketCard(ticketId, 'assumed');
            
            // Recarregar página após 1 segundo
            setTimeout(() => location.reload(), 1000);
        } else {
            window.AppUtils.showAlert(result.message || 'Erro ao assumir ticket', 'danger');
        }
        
    } catch (error) {
        console.error('Erro:', error);
        window.AppUtils.showAlert('Erro ao processar requisição', 'danger');
    } finally {
        window.AppUtils.hideLoader();
    }
}

/**
 * Despachar Equipe
 */
async function handleDispatchTicket(e) {
    e.preventDefault();
    
    const ticketId = this.dataset.ticketId;
    
    if (!confirm('Deseja despachar equipe para este ticket?')) {
        return;
    }
    
    window.AppUtils.showLoader();
    
    try {
        const response = await fetch('../ajax/dispatch_ticket.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ ticket_id: ticketId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.AppUtils.showAlert('Equipe despachada com sucesso!', 'success');
            
            // Atualizar interface
            updateTicketCard(ticketId, 'dispatched');
            
            // Recarregar página após 1 segundo
            setTimeout(() => location.reload(), 1000);
        } else {
            window.AppUtils.showAlert(result.message || 'Erro ao despachar equipe', 'danger');
        }
        
    } catch (error) {
        console.error('Erro:', error);
        window.AppUtils.showAlert('Erro ao processar requisição', 'danger');
    } finally {
        window.AppUtils.hideLoader();
    }
}

/**
 * Finalizar Ticket
 */
async function handleCloseTicket(e) {
    e.preventDefault();
    
    const ticketId = this.dataset.ticketId;
    
    if (!confirm('Deseja finalizar este ticket?')) {
        return;
    }
    
    window.AppUtils.showLoader();
    
    try {
        const response = await fetch('../ajax/close_ticket.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ ticket_id: ticketId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.AppUtils.showAlert('Ticket finalizado com sucesso!', 'success');
            
            // Atualizar interface
            updateTicketCard(ticketId, 'closed');
            
            // Recarregar página após 1 segundo
            setTimeout(() => location.reload(), 1000);
        } else {
            window.AppUtils.showAlert(result.message || 'Erro ao finalizar ticket', 'danger');
        }
        
    } catch (error) {
        console.error('Erro:', error);
        window.AppUtils.showAlert('Erro ao processar requisição', 'danger');
    } finally {
        window.AppUtils.hideLoader();
    }
}

/**
 * Reabrir Ticket
 */
async function handleReopenTicket(e) {
    e.preventDefault();
    
    const ticketId = this.dataset.ticketId;
    
    if (!confirm('Deseja reabrir este ticket?')) {
        return;
    }
    
    window.AppUtils.showLoader();
    
    try {
        const response = await fetch('../ajax/reopen_ticket.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ ticket_id: ticketId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.AppUtils.showAlert('Ticket reaberto com sucesso!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            window.AppUtils.showAlert(result.message || 'Erro ao reabrir ticket', 'danger');
        }
        
    } catch (error) {
        console.error('Erro:', error);
        window.AppUtils.showAlert('Erro ao processar requisição', 'danger');
    } finally {
        window.AppUtils.hideLoader();
    }
}

/**
 * Atualizar Card do Ticket
 */
function updateTicketCard(ticketId, newStatus) {
    const card = document.querySelector(`[data-ticket-id="${ticketId}"]`);
    
    if (card) {
        // Adicionar classe de transição
        card.style.opacity = '0.5';
        card.style.transition = 'opacity 0.3s ease';
        
        // Atualizar badge de status
        const statusBadge = card.querySelector('.ticket-status');
        if (statusBadge) {
            statusBadge.className = `badge ticket-status status-${newStatus}`;
            statusBadge.textContent = getStatusLabel(newStatus);
        }
    }
}

/**
 * Obter label do status
 */
function getStatusLabel(status) {
    const labels = {
        'created': 'Criado',
        'assumed': 'Assumido',
        'dispatched': 'Despachado',
        'in_progress': 'Em Andamento',
        'resolved': 'Resolvido',
        'closed': 'Encerrado',
        'reopened': 'Reaberto'
    };
    
    return labels[status] || status;
}

// ===== FILTROS =====
function initTicketFilters() {
    const filterPriority = document.getElementById('filter-priority');
    const filterStatus = document.getElementById('filter-status');
    const filterCategory = document.getElementById('filter-category');
    
    if (filterPriority) {
        filterPriority.addEventListener('change', applyFilters);
    }
    
    if (filterStatus) {
        filterStatus.addEventListener('change', applyFilters);
    }
    
    if (filterCategory) {
        filterCategory.addEventListener('change', applyFilters);
    }
}

function applyFilters() {
    const priority = document.getElementById('filter-priority')?.value || '';
    const status = document.getElementById('filter-status')?.value || '';
    const category = document.getElementById('filter-category')?.value || '';
    
    const tickets = document.querySelectorAll('.ticket-card');
    
    tickets.forEach(ticket => {
        const ticketPriority = ticket.dataset.priority || '';
        const ticketStatus = ticket.dataset.status || '';
        const ticketCategory = ticket.dataset.category || '';
        
        let show = true;
        
        if (priority && ticketPriority !== priority) show = false;
        if (status && ticketStatus !== status) show = false;
        if (category && ticketCategory !== category) show = false;
        
        ticket.style.display = show ? 'block' : 'none';
    });
}

// ===== BUSCA =====
function initTicketSearch() {
    const searchInput = document.getElementById('ticket-search');
    
    if (searchInput) {
        searchInput.addEventListener('input', window.AppUtils.debounce(performSearch, 300));
    }
}

function performSearch(e) {
    const searchTerm = e.target.value.toLowerCase();
    const tickets = document.querySelectorAll('.ticket-card');
    
    tickets.forEach(ticket => {
        const company = ticket.querySelector('.ticket-company')?.textContent.toLowerCase() || '';
        const description = ticket.querySelector('.ticket-description')?.textContent.toLowerCase() || '';
        const ticketId = ticket.dataset.ticketId || '';
        
        const match = company.includes(searchTerm) || 
                     description.includes(searchTerm) || 
                     ticketId.includes(searchTerm);
        
        ticket.style.display = match ? 'block' : 'none';
    });
}

// ===== UPLOAD DE ARQUIVOS =====
function initFileUpload() {
    const fileInput = document.getElementById('ticket-attachments');
    const fileList = document.getElementById('file-list');
    
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            displaySelectedFiles(e.target.files, fileList);
        });
    }
}

function displaySelectedFiles(files, container) {
    if (!container) return;
    
    container.innerHTML = '';
    
    Array.from(files).forEach(file => {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
            <span class="file-name">${file.name}</span>
            <span class="file-size">${formatFileSize(file.size)}</span>
        `;
        
        container.appendChild(fileItem);
    });
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// ===== PREVIEW DE ANEXOS =====
function initAttachmentPreview() {
    document.querySelectorAll('.attachment-link').forEach(link => {
        link.addEventListener('click', function(e) {
            const fileType = this.dataset.fileType;
            
            if (fileType && fileType.startsWith('image/')) {
                e.preventDefault();
                showImagePreview(this.href, this.dataset.fileName);
            }
        });
    });
}

function showImagePreview(url, fileName) {
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.id = 'image-preview-modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>${fileName || 'Visualização'}</h3>
                <button class="btn btn-secondary" onclick="window.AppUtils.closeModal('image-preview-modal')">Fechar</button>
            </div>
            <div class="modal-body">
                <img src="${url}" alt="${fileName}" style="max-width: 100%; height: auto;">
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Fechar ao clicar fora
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            window.AppUtils.closeModal('image-preview-modal');
            modal.remove();
        }
    });
}

// ===== ATUALIZAÇÃO EM TEMPO REAL =====
let updateInterval = null;

function startAutoUpdate(intervalSeconds = 30) {
    if (updateInterval) {
        clearInterval(updateInterval);
    }
    
    updateInterval = setInterval(checkForUpdates, intervalSeconds * 1000);
}

async function checkForUpdates() {
    try {
        const response = await fetch('../ajax/check_updates.php', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const result = await response.json();
        
        if (result.has_updates) {
            showUpdateNotification();
        }
        
    } catch (error) {
        console.error('Erro ao verificar atualizações:', error);
    }
}

function showUpdateNotification() {
    const notification = document.createElement('div');
    notification.className = 'update-notification';
    notification.innerHTML = `
        <span>Novos tickets disponíveis</span>
        <button class="btn btn-sm btn-primary" onclick="location.reload()">Atualizar</button>
    `;
    notification.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        display: flex;
        gap: 15px;
        align-items: center;
        z-index: 1000;
    `;
    
    document.body.appendChild(notification);
}

// ===== EXPORT =====
window.TicketUtils = {
    updateTicketCard,
    getStatusLabel,
    applyFilters,
    performSearch,
    startAutoUpdate
};