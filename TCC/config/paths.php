<?php
/**
 * CONFIGURAÇÃO CENTRALIZADA DE CAMINHOS
 * Sistema de Suporte e Manutenção
 * 
 * Este arquivo resolve TODOS os problemas de paths do sistema
 * Coloque este arquivo em: /config/paths.php
 */

// =====================================================
// DETECTAR DIRETÓRIO RAIZ DO PROJETO
// =====================================================

// Define o diretório raiz baseado na localização deste arquivo
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__) . '/');
}

// =====================================================
// CAMINHOS DE DIRETÓRIOS
// =====================================================

if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', PROJECT_ROOT . 'config/');
}

if (!defined('CLASSES_PATH')) {
    define('CLASSES_PATH', PROJECT_ROOT . 'classes/');
}

if (!defined('CONTROLLERS_PATH')) {
    define('CONTROLLERS_PATH', PROJECT_ROOT . 'controllers/');
}

if (!defined('VIEWS_PATH')) {
    define('VIEWS_PATH', PROJECT_ROOT . 'views/');
}

if (!defined('INCLUDES_PATH')) {
    define('INCLUDES_PATH', PROJECT_ROOT . 'includes/');
}

if (!defined('AJAX_PATH')) {
    define('AJAX_PATH', PROJECT_ROOT . 'ajax/');
}

if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', PROJECT_ROOT . 'public/');
}

if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', PUBLIC_PATH . 'uploads/');
}

if (!defined('LOGS_PATH')) {
    define('LOGS_PATH', PROJECT_ROOT . 'logs/');
}

// =====================================================
// CRIAR DIRETÓRIOS SE NÃO EXISTIREM
// =====================================================

$directories = [
    UPLOAD_PATH,
    LOGS_PATH,
    PUBLIC_PATH . 'css/',
    PUBLIC_PATH . 'js/',
    PUBLIC_PATH . 'images/',
    VIEWS_PATH  . 'views/'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// =====================================================
// FUNÇÃO HELPER PARA REQUIRE SEGURO
// =====================================================

/**
 * Inclui arquivo PHP de forma segura
 * @param string $file Caminho do arquivo
 * @param bool $once Se deve usar require_once
 * @return bool
 */
function safe_require($file, $once = true) {
    if (file_exists($file)) {
        if ($once) {
            require_once $file;
        } else {
            require $file;
        }
        return true;
    } else {
        error_log("Arquivo não encontrado: {$file}");
        return false;
    }
}

/**
 * Inclui arquivo e retorna se existe
 * @param string $file Caminho do arquivo
 * @return bool
 */
function safe_include($file) {
    if (file_exists($file)) {
        include $file;
        return true;
    }
    return false;
}

// =====================================================
// AUTO-CARREGAR ARQUIVOS ESSENCIAIS
// =====================================================

// Carregar database.php
safe_require(CONFIG_PATH . 'database.php');

// Carregar constants.php
safe_require(CONFIG_PATH . 'constants.php');

// Carregar functions.php
safe_require(INCLUDES_PATH . 'functions.php');

// =====================================================
// AUTO-LOADER DE CLASSES
// =====================================================

spl_autoload_register(function ($class) {
    $file = CLASSES_PATH . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    return false;
});

// =====================================================
// VERIFICAR INTEGRIDADE DO SISTEMA
// =====================================================

/**
 * Verifica se todos os diretórios essenciais existem
 * @return array
 */
function check_system_integrity() {
    $checks = [
        'config' => file_exists(CONFIG_PATH),
        'classes' => file_exists(CLASSES_PATH),
        'controllers' => file_exists(CONTROLLERS_PATH),
        'views' => file_exists(VIEWS_PATH),
        'includes' => file_exists(INCLUDES_PATH),
        'public' => file_exists(PUBLIC_PATH),
        'uploads' => is_writable(UPLOAD_PATH),
        'logs' => is_writable(LOGS_PATH),
    ];
    
    return $checks;
}

/**
 * Exibe relatório de integridade (apenas em dev)
 */
function show_integrity_report() {
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        $checks = check_system_integrity();
        $allOk = !in_array(false, $checks);
        
        if (!$allOk) {
            echo "<div style='background:#f44336;color:white;padding:10px;margin:10px;border-radius:5px;'>";
            echo "<strong>⚠️ PROBLEMAS DE INTEGRIDADE DO SISTEMA:</strong><br>";
            foreach ($checks as $item => $status) {
                if (!$status) {
                    echo "❌ {$item}: FALHOU<br>";
                }
            }
            echo "</div>";
        }
    }
}
?>