/**
 * SISTEMA DE SUPORTE E MANUTENÇÃO
 * Arquivo: notifications.js
 * Descrição: Scripts para sistema de notificações em tempo real
 */

// === CONFIGURAÇÕES ===
const NOTIFICATION_CONFIG = {
    checkInterval: 30000, // 30 segundos
    soundEnabled: true,
    desktopEnabled: true
};

// === INICIALIZAÇÃO ===
document.addEventListener('DOMContentLoaded', function() {
    initNotificationSystem();
    requestNotificationPermission();
});

// === SISTEMA DE NOTIFICAÇÕES ===
function initNotificationSystem() {
    // Verificar novas notificações periodicamente
    setInterval(checkForNewNotifications, NOTIFICATION_CONFIG.checkInterval);
    
    // Verificar imediatamente ao carregar
    checkForNewNotifications();
    
    // Configurar event listeners
    setupNotificationListeners();
}

// === VERIFICAR NOVAS NOTIFICAÇÕES ===
async function checkForNewNotifications() {
    try {
        const response = await fetch('/ajax/get_notifications.php?unread_only=1');
        const data = await response.json();

        if (data.success) {
            updateNotificationCounter(data.unread_count);
            
            // Se houver novas notificações
            if (data.new_notifications && data.new_notifications.length > 0) {
                handleNewNotifications(data.new_notifications);
            }
        }
    } catch (error) {
        console.error('Erro ao verificar notificações:', error);
    }
}

// === MANIPULAR NOVAS NOTIFICAÇÕES ===
function handleNewNotifications(notifications) {
    notifications.forEach(notification => {
        // Mostrar notificação desktop
        if (NOTIFICATION_CONFIG.desktopEnabled) {
            showDesktopNotification(notification);
        }
        
        // Tocar som
        if (NOTIFICATION_CONFIG.soundEnabled) {
            playNotificationSound();
        }
        
        // Mostrar notificação no sistema
        showInAppNotification(notification);
    });
}

// === NOTIFICAÇÃO DESKTOP ===
function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
}

function showDesktopNotification(notification) {
    if ('Notification' in window && Notification.permission === 'granted') {
        const notif = new Notification('Sistema de Suporte', {
            body: notification.message,
            icon: '/public/images/logo.png',
            tag: `notification-${notification.id}`,
            requireInteraction: false
        });

        notif.onclick = function() {
            window.focus();
            if (notification.ticket_id) {
                window.location.href = `/views/tickets/view.php?id=${notification.ticket_id}`;
            }
            notif.close();
        };

        // Fechar automaticamente após 5 segundos
        setTimeout(() => notif.close(), 5000);
    }
}

// === NOTIFICAÇÃO IN-APP ===
function showInAppNotification(notification) {
    const container = getOrCreateNotificationContainer();
    
    const notifElement = document.createElement('div');
    notifElement.className = 'toast-notification';
    notifElement.innerHTML = `
        <div class="toast-header">
            <strong>Nova Notificação</strong>
            <button type="button" class="toast-close" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="toast-body">
            ${escapeHtml(notification.message)}
        </div>
    `;

    container.appendChild(notifElement);

    // Animar entrada
    setTimeout(() => notifElement.classList.add('show'), 10);

    // Remover após 5 segundos
    setTimeout(() => {
        notifElement.classList.remove('show');
        setTimeout(() => notifElement.remove(), 300);
    }, 5000);
}

function getOrCreateNotificationContainer() {
    let container = document.getElementById('toast-container');
    
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        `;
        document.body.appendChild(container);
    }
    
    return container;
}

// === SOM DE NOTIFICAÇÃO ===
function playNotificationSound() {
    try {
        const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBTGH0fPTgjMGHm7A7+OZURE=');
        audio.volume = 0.3;
        audio.play().catch(e => console.log('Não foi possível tocar o som:', e));
    } catch (error) {
        console.log('Erro ao tocar som de notificação:', error);
    }
}

// === ATUALIZAR CONTADOR ===
function updateNotificationCounter(count) {
    const badge = document.querySelector('.notification-badge');
    const menuBadge = document.querySelector('.menu-badge');
    
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    }
    
    if (menuBadge) {
        if (count > 0) {
            menuBadge.textContent = count > 99 ? '99+' : count;
            menuBadge.style.display = 'inline-block';
        } else {
            menuBadge.style.display = 'none';
        }
    }

    // Atualizar título da página se houver notificações
    updatePageTitle(count);
}

function updatePageTitle(count) {
    const originalTitle = document.title.replace(/^\(\d+\)\s/, '');
    
    if (count > 0) {
        document.title = `(${count}) ${originalTitle}`;
    } else {
        document.title = originalTitle;
    }
}

// === EVENT LISTENERS ===
function setupNotificationListeners() {
    // Botão de configurações de notificação
    const settingsBtn = document.getElementById('notification-settings');
    if (settingsBtn) {
        settingsBtn.addEventListener('click', showNotificationSettings);
    }

    // Marcar todas como lidas
    const markAllBtn = document.getElementById('mark-all-read');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', markAllAsRead);
    }
}

// === CONFIGURAÇÕES ===
function showNotificationSettings() {
    const settings = `
        <div class="modal-overlay" onclick="this.remove()">
            <div class="modal-content" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <h3>Configurações de Notificações</h3>
                    <button onclick="this.closest('.modal-overlay').remove()">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="sound-enabled" ${NOTIFICATION_CONFIG.soundEnabled ? 'checked' : ''}>
                            Ativar som de notificação
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="desktop-enabled" ${NOTIFICATION_CONFIG.desktopEnabled ? 'checked' : ''}>
                            Ativar notificações desktop
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" onclick="saveNotificationSettings()">Salvar</button>
                    <button class="btn btn-secondary" onclick="this.closest('.modal-overlay').remove()">Cancelar</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', settings);
}

function saveNotificationSettings() {
    NOTIFICATION_CONFIG.soundEnabled = document.getElementById('sound-enabled').checked;
    NOTIFICATION_CONFIG.desktopEnabled = document.getElementById('desktop-enabled').checked;
    
    // Salvar no localStorage
    localStorage.setItem('notificationConfig', JSON.stringify(NOTIFICATION_CONFIG));
    
    // Se desktop habilitado, pedir permissão
    if (NOTIFICATION_CONFIG.desktopEnabled) {
        requestNotificationPermission();
    }
    
    showAlert('Configurações salvas!', 'success');
    document.querySelector('.modal-overlay')?.remove();
}

// === MARCAR TODAS COMO LIDAS ===
async function markAllAsRead() {
    try {
        const response = await fetch('/ajax/mark_all_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        const data = await response.json();

        if (data.success) {
            updateNotificationCounter(0);
            showAlert('Todas as notificações foram marcadas como lidas', 'success');
            
            // Recarregar lista de notificações
            if (typeof loadNotifications === 'function') {
                loadNotifications();
            }
        }
    } catch (error) {
        console.error('Erro ao marcar todas como lidas:', error);
        showAlert('Erro ao processar requisição', 'danger');
    }
}

// === CARREGAR CONFIGURAÇÕES SALVAS ===
function loadSavedConfig() {
    const saved = localStorage.getItem('notificationConfig');
    if (saved) {
        Object.assign(NOTIFICATION_CONFIG, JSON.parse(saved));
    }
}

// === UTILITÁRIOS ===
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Carregar configurações salvas ao iniciar
loadSavedConfig();

// === EXPORTAR FUNÇÕES GLOBAIS ===
window.showNotificationSettings = showNotificationSettings;
window.saveNotificationSettings = saveNotificationSettings;
window.markAllAsRead = markAllAsRead;

// === CSS PARA TOASTS (adicionar dinamicamente) ===
const toastStyles = document.createElement('style');
toastStyles.textContent = `
    .toast-notification {
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        min-width: 300px;
        max-width: 400px;
        opacity: 0;
        transform: translateX(400px);
        transition: all 0.3s ease;
    }
    
    .toast-notification.show {
        opacity: 1;
        transform: translateX(0);
    }
    
    .toast-header {
        padding: 12px 16px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .toast-close {
        background: none;
        border: none;
        color: #64748b;
        cursor: pointer;
        font-size: 18px;
    }
    
    .toast-body {
        padding: 12px 16px;
        color: #475569;
    }
`;
document.head.appendChild(toastStyles);