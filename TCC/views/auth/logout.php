<?php
/**
 * Página de Logout
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 */

// Incluir configurações
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Destruir sessão
session_unset();
session_destroy();

// Definir mensagem de sucesso
session_start();
setFlashMessage('Você saiu do sistema com sucesso.', 'success');

// Redirecionar para login
redirect(BASE_URL . 'views/auth/login.php');
?>