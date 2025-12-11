<?php
/**
 * Configuração de Banco de Dados
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 * 
 * NOTA: A classe Database está em classes/Database.php
 * Este arquivo contém APENAS as constantes de conexão
 */

// =====================================================
// CONFIGURAÇÕES DE CONEXÃO COM BANCO DE DADOS
// =====================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_suporte');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Opções do PDO
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
]);

// =====================================================
// FUNÇÃO HELPER PARA OBTER CONEXÃO
// =====================================================

/**
 * Retorna instância do PDO via classe Database
 * @return PDO
 */
function getConnection() {
    return Database::getInstance()->getConnection();
}

// =====================================================
// FIM - Classe Database está em classes/Database.php
// =====================================================