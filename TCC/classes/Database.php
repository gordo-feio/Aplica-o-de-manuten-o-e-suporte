<?php
/**
 * Classe Database - Gerenciamento de Conexão com Banco de Dados
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 * 
 * IMPORTANTE: Esta classe NÃO pode chamar logSystem() para evitar loop infinito
 */

class Database {
    private static $instance = null;
    private $pdo;
    
    /**
     * Construtor privado (Singleton Pattern)
     */
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
            
            // NÃO usar logSystem() aqui! Causa loop infinito
            // Use error_log() para debug se necessário
            
        } catch (PDOException $e) {
            // Usar error_log() nativo do PHP ao invés de logSystem()
            error_log("ERRO DE CONEXÃO COM BANCO: " . $e->getMessage());
            
            // Em desenvolvimento, mostrar erro
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                die("Erro ao conectar com banco de dados: " . $e->getMessage());
            }
            
            throw new Exception("Erro ao conectar com o banco de dados");
        }
    }
    
    /**
     * Obter instância única da classe (Singleton)
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obter conexão PDO
     * @return PDO
     */
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * Executar query SELECT
     * @param string $sql
     * @param array $params
     * @return array
     */
    public function select($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro em SELECT: " . $e->getMessage() . " | SQL: " . $sql);
            return [];
        }
    }
    
    /**
     * Executar query SELECT e retornar apenas uma linha
     * @param string $sql
     * @param array $params
     * @return array|null
     */
    public function selectOne($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result : null;
        } catch (PDOException $e) {
            error_log("Erro em SELECT ONE: " . $e->getMessage() . " | SQL: " . $sql);
            return null;
        }
    }
    
    /**
     * Executar query INSERT
     * @param string $sql
     * @param array $params
     * @return int|bool ID inserido ou false em caso de erro
     */
    public function insert($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);
            return $result ? $this->pdo->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log("Erro em INSERT: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }
    
    /**
     * Executar query UPDATE
     * @param string $sql
     * @param array $params
     * @return int Número de linhas afetadas
     */
    public function update($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Erro em UPDATE: " . $e->getMessage() . " | SQL: " . $sql);
            return 0;
        }
    }
    
    /**
     * Executar query DELETE
     * @param string $sql
     * @param array $params
     * @return int Número de linhas afetadas
     */
    public function delete($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Erro em DELETE: " . $e->getMessage() . " | SQL: " . $sql);
            return 0;
        }
    }
    
    /**
     * Executar query genérica
     * @param string $sql
     * @param array $params
     * @return bool
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Erro em EXECUTE: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }
    
    /**
     * Contar registros
     * @param string $table
     * @param string $where
     * @param array $params
     * @return int
     */
    public function count($table, $where = '', $params = []) {
        $sql = "SELECT COUNT(*) as total FROM {$table}";
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }
        
        $result = $this->selectOne($sql, $params);
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * Iniciar transação
     * @return bool
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Confirmar transação
     * @return bool
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Reverter transação
     * @return bool
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * Verificar se está em transação
     * @return bool
     */
    public function inTransaction() {
        return $this->pdo->inTransaction();
    }
    
    /**
     * Escapar string para uso em LIKE
     * @param string $string
     * @return string
     */
    public function escapeLike($string) {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $string);
    }
    
    /**
     * Construir cláusula WHERE dinamicamente
     * @param array $conditions ['campo' => 'valor', ...]
     * @param string $operator (AND/OR)
     * @return array ['sql' => string, 'params' => array]
     */
    public function buildWhere($conditions, $operator = 'AND') {
        if (empty($conditions)) {
            return ['sql' => '', 'params' => []];
        }
        
        $whereParts = [];
        $params = [];
        
        foreach ($conditions as $field => $value) {
            if ($value === null) {
                $whereParts[] = "{$field} IS NULL";
            } else {
                $whereParts[] = "{$field} = ?";
                $params[] = $value;
            }
        }
        
        $sql = implode(" {$operator} ", $whereParts);
        
        return ['sql' => $sql, 'params' => $params];
    }
    
    /**
     * Prevenir clonagem (Singleton)
     */
    private function __clone() {}
    
    /**
     * Prevenir deserialização (Singleton)
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}