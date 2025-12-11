/**
 * ============================================
 * SISTEMA DE SUPORTE E MANUTENÇÃO - TCC
 * Autor: Nicolas Clayton Parpinelli
 * Arquivo: main.js - JavaScript Principal
 * ============================================
 */

// ===== INICIALIZAÇÃO =====
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

/**
 * Inicializa todas as funcionalidades da aplicação
 */
function initializeApp() {
    // Inicializar tooltips
    initTooltips();
    
    // Inicializar modais
    initModals();
    
    // Inicializar alertas auto-dismiss
    initAlerts();
    
    // Inicializar menu mobile
    initMobileMenu();
    
    // Inicializar validação de formulários
    initFormValidation();
    
    // Inicializar confirmações
    initConfirmations();
    
    console.log('Sistema inicializado com sucesso!');
}

// ===== TOOLTIPS =====
function initTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const text = e.target.dataset.tooltip;
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = text;
    tooltip.id = 'active-tooltip';
    
    document.body.appendChild(tooltip);
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.top = (rect.top - tooltip.offsetHeight - 10) + 'px';
    tooltip.style.left = (rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)) + 'px';
}

function hideTooltip() {
    const tooltip = document.getElementById('active-tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

// ===== MODAIS =====
function initModals() {
    // Abrir modal
    const modalTriggers = document.querySelectorAll('[data-modal]');
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const modalId = this.dataset.modal;
            openModal(modalId);
        });
    });
    
    // Fechar modal
    const closeButtons = document.querySelectorAll('.modal-close, [data-close-modal]');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                closeModal(modal.id);
            }
        });
    });
    
    // Fechar modal ao clicar fora
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeModal(e.target.id);
        }
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// ===== ALERTAS =====
function initAlerts() {
    const alerts = document.querySelectorAll('.alert[data-auto-dismiss]');
    
    alerts.forEach(alert => {
        const timeout = parseInt(alert.dataset.autoDismiss) || 5000;
        
        setTimeout(() => {
            dismissAlert(alert);
        }, timeout);
    });
}

function showAlert(message, type = 'info') {
    const alertHtml = `
        <div class="alert alert-${type}" data-auto-dismiss="5000">
            ${message}
            <button class="alert-close" onclick="dismissAlert(this.parentElement)">&times;</button>
        </div>
    `;
    
    const container = document.querySelector('.alerts-container') || createAlertsContainer();
    container.insertAdjacentHTML('beforeend', alertHtml);
    
    // Auto dismiss
    const newAlert = container.lastElementChild;
    setTimeout(() => dismissAlert(newAlert), 5000);
}

function dismissAlert(alert) {
    if (alert && alert.parentElement) {
        alert.style.opacity = '0';
        setTimeout(() => {
            alert.remove();
        }, 300);
    }
}

function createAlertsContainer() {
    const container = document.createElement('div');
    container.className = 'alerts-container';
    container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
    document.body.appendChild(container);
    return container;
}

// ===== MENU MOBILE =====
function initMobileMenu() {
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.dashboard-sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
}

// ===== VALIDAÇÃO DE FORMULÁRIOS =====
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                showAlert('Por favor, corrija os erros no formulário.', 'danger');
            }
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    // Limpar erros anteriores
    form.querySelectorAll('.form-error').forEach(error => error.remove());
    form.querySelectorAll('.is-invalid').forEach(field => field.classList.remove('is-invalid'));
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            showFieldError(field, 'Este campo é obrigatório');
            isValid = false;
        } else if (field.type === 'email' && !isValidEmail(field.value)) {
            showFieldError(field, 'E-mail inválido');
            isValid = false;
        }
    });
    
    return isValid;
}

function showFieldError(field, message) {
    field.classList.add('is-invalid');
    
    const error = document.createElement('div');
    error.className = 'form-error';
    error.textContent = message;
    
    field.parentElement.appendChild(error);
}

function isValidEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

// ===== CONFIRMAÇÕES =====
function initConfirmations() {
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    
    confirmButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.dataset.confirm || 'Tem certeza que deseja continuar?';
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
}

// ===== LOADER =====
function showLoader() {
    const loader = document.createElement('div');
    loader.id = 'global-loader';
    loader.innerHTML = '<div class="loader"></div>';
    loader.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    `;
    
    document.body.appendChild(loader);
}

function hideLoader() {
    const loader = document.getElementById('global-loader');
    if (loader) {
        loader.remove();
    }
}

// ===== UTILITÁRIOS =====
/**
 * Formata data para padrão brasileiro
 */
function formatDate(date, includeTime = false) {
    const d = new Date(date);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    
    let formatted = `${day}/${month}/${year}`;
    
    if (includeTime) {
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        formatted += ` ${hours}:${minutes}`;
    }
    
    return formatted;
}

/**
 * Debounce para otimizar chamadas
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Faz requisição AJAX
 */
async function ajaxRequest(url, method = 'GET', data = null) {
    try {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }
        
        const response = await fetch(url, options);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
        
    } catch (error) {
        console.error('Erro na requisição:', error);
        showAlert('Erro ao processar requisição. Tente novamente.', 'danger');
        return null;
    }
}

/**
 * Copia texto para clipboard
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showAlert('Copiado para área de transferência!', 'success');
    }).catch(err => {
        console.error('Erro ao copiar:', err);
        showAlert('Erro ao copiar texto.', 'danger');
    });
}

/**
 * Formata número de telefone
 */
function formatPhone(phone) {
    const cleaned = phone.replace(/\D/g, '');
    
    if (cleaned.length === 11) {
        return cleaned.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    } else if (cleaned.length === 10) {
        return cleaned.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    }
    
    return phone;
}

/**
 * Formata CEP
 */
function formatCEP(cep) {
    const cleaned = cep.replace(/\D/g, '');
    return cleaned.replace(/(\d{5})(\d{3})/, '$1-$2');
}

/**
 * Scroll suave para elemento
 */
function smoothScrollTo(element) {
    if (typeof element === 'string') {
        element = document.querySelector(element);
    }
    
    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

// ===== EXPORTS (para uso em outros arquivos) =====
window.AppUtils = {
    showAlert,
    dismissAlert,
    showLoader,
    hideLoader,
    openModal,
    closeModal,
    formatDate,
    formatPhone,
    formatCEP,
    copyToClipboard,
    smoothScrollTo,
    ajaxRequest,
    debounce
};