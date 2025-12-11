<?php
/**
 * Dashboard Router - Sistema de Suporte
 * Redireciona usuários para dashboards específicos baseado no tipo de conta
 * Autor: Nicolas Clayton Parpinelli
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Garantir que está logado
requireLogin();

// ============================================
// DETECTAR TIPO DE USUÁRIO E REDIRECIONAR
// ============================================

if (isset($_SESSION['user_role'])) {
    // USUÁRIOS DO SISTEMA (Funcionários)
    switch ($_SESSION['user_role']) {
        case 'admin':
            // Administradores veem dashboard completo
            include __DIR__ . '/dash_admin.php';
            break;
            
        case 'attendant':
            // Atendentes veem tickets para assumir
            include __DIR__ . '/dash_atendente.php';
            break;
            
        case 'technician':
            // Técnicos veem tickets despachados
            include __DIR__ . '/dash_tecnico.php';
            break;
            
        default:
            // Fallback para funcionários sem role definida
            include __DIR__ . '/dash_atendente.php';
            break;
    }
    
} elseif (isset($_SESSION['company_id'])) {
    // EMPRESAS CLIENTES
    include __DIR__ . '/dash_empresa.php';
    
} else {
    // Caso nenhuma condição seja atendida (segurança)
    session_destroy();
    header('Location: ' . BASE_URL . 'views/auth/login.php');
    exit;
}
?>