<?php
/**
 * CONFIGURAÇÃO GERAL DO SISTEMA - VERSÃO CORRIGIDA FINAL
 * Sistema de Suporte e Manutenção
 */

// =====================================================
// INICIAR SESSÃO PRIMEIRO (ANTES DE TUDO!)
// =====================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================================
// AMBIENTE
// =====================================================
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'development');
}

// Configurar erros
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// =====================================================
// CONFIGURAÇÕES DO SISTEMA
// =====================================================
define('SYSTEM_NAME', 'Sistema de Suporte e Manutenção');
define('SYSTEM_SHORT_NAME', 'Suporte');
define('SYSTEM_VERSION', '1.0.0');

// =====================================================
// CONFIGURAÇÕES DE URL
// =====================================================
define('BASE_URL', 'http://localhost/TCC/');
define('ASSETS_URL', BASE_URL . 'public/');

// =====================================================
// CONFIGURAÇÕES DE SESSÃO
// =====================================================
define('SESSION_TIMEOUT', 7200); // 2 horas

// =====================================================
// CONFIGURAÇÕES DE SEGURANÇA
// =====================================================
define('PASSWORD_MIN_LENGTH', 6);
define('PASSWORD_HASH_ALGO', PASSWORD_DEFAULT);

// =====================================================
// CONFIGURAÇÕES DE UPLOAD
// =====================================================
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

if (!defined('ALLOWED_EXTENSIONS')) {
    define('ALLOWED_EXTENSIONS', [
        'jpg', 'jpeg', 'png', 'gif', 'pdf', 
        'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'
    ]);
}

if (!defined('ALLOWED_MIME_TYPES')) {
    define('ALLOWED_MIME_TYPES', [
        'image/jpeg', 'image/png', 'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'application/zip'
    ]);
}

// =====================================================
// PAGINAÇÃO
// =====================================================
if (!defined('ITEMS_PER_PAGE')) {
    define('ITEMS_PER_PAGE', 20);
}

// =====================================================
// LOG
// =====================================================
define('LOG_ENABLED', true);
define('LOG_LEVEL', 'INFO');

// =====================================================
// FUSO HORÁRIO
// =====================================================
date_default_timezone_set('America/Sao_Paulo');
setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');

// =====================================================
// VERIFICAR TIMEOUT DE SESSÃO
// =====================================================
if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        session_start();
    }
}
$_SESSION['last_activity'] = time();

// =====================================================
// NÃO INCLUIR MAIS NADA AQUI!
// paths.php já carrega tudo que é necessário
// =====================================================