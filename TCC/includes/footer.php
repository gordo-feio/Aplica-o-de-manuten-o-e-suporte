</div> <!-- Fecha container-fluid -->
    
    <!-- Footer Minimalista e Moderno -->
    <footer class="footer-modern">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <i class="fas fa-headset"></i>
                    <span><?php echo SYSTEM_SHORT_NAME ?? SYSTEM_NAME; ?></span>
                </div>
                <div class="footer-info">
                    <span class="footer-version">v<?php echo SYSTEM_VERSION; ?></span>
                    <span class="footer-separator">•</span>
                    <span class="footer-copyright">&copy; <?php echo date('Y'); ?></span>
                    <span class="footer-separator">•</span>
                    <span class="footer-author">Nicolas Clayton Parpinelli</span>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap 5 JS Bundle (inclui Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- JavaScript Customizado -->
    <script src="<?php echo ASSETS_URL; ?>js/main.js"></script>
    <script src="<?php echo ASSETS_URL; ?>js/notifications.js"></script>
    
    <?php if (isset($pageScript)): ?>
        <script src="<?php echo ASSETS_URL . 'js/' . $pageScript; ?>"></script>
    <?php endif; ?>
    
    <style>
    /* =============================================
       FOOTER MINIMALISTA E MODERNO COM DARK MODE
       ============================================= */
    
    .footer-modern {
        background: var(--bg-primary);
        border-top: 1px solid var(--border-color);
        padding: 2rem 0;
        margin-top: 4rem;
        transition: all 0.3s ease;
    }
    
    .footer-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
    }
    
    .footer-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-primary);
        transition: all 0.3s ease;
    }
    
    .footer-brand i {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .footer-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
        justify-content: center;
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    
    .footer-version {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    
    .footer-separator {
        color: var(--border-color);
        font-weight: 300;
    }
    
    .footer-copyright {
        font-weight: 500;
    }
    
    .footer-author {
        font-weight: 600;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    /* Responsivo */
    @media (max-width: 768px) {
        .footer-modern {
            padding: 1.5rem 0;
            margin-top: 3rem;
        }
        
        .footer-brand {
            font-size: 1.1rem;
        }
        
        .footer-brand i {
            width: 35px;
            height: 35px;
            font-size: 0.9rem;
        }
        
        .footer-info {
            font-size: 0.85rem;
            gap: 0.5rem;
        }
        
        .footer-version {
            font-size: 0.7rem;
            padding: 0.2rem 0.6rem;
        }
    }
    </style>
    
    <script>
    // Configurações globais JavaScript
    const BASE_URL = '<?php echo BASE_URL; ?>';
    const ASSETS_URL = '<?php echo ASSETS_URL; ?>';
    const IS_LOGGED_IN = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
    
    // Auto-fechar alertas após 5 segundos
    setTimeout(function() {
        $('.alert').not('.alert-permanent').fadeOut('slow');
    }, 5000);
    
    // Confirmação antes de excluir
    $('.btn-delete, .delete-action').on('click', function(e) {
        if (!confirm('Tem certeza que deseja excluir este item?')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Tooltip Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Carregar notificações ao abrir dropdown
    <?php if (isLoggedIn()): ?>
    const notificationDropdown = document.querySelector('.notification-dropdown');
    if (notificationDropdown) {
        notificationDropdown.addEventListener('show.bs.dropdown', function() {
            if (typeof loadNotifications === 'function') {
                loadNotifications();
            }
        });
    }
    <?php endif; ?>
    </script>
</body>
</html>