<?php
/**
 * Classe Log - Gerenciamento de Logs do Sistema
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 */

class Log {
    private $db;
    private $logFile;
    private static $initComplete = false;
    
    public function __construct() {
        // Só inicializa DB se já estiver disponível
        try {
            $this->db = Database::getInstance();
            self::$initComplete = true;
        } catch (Exception $e) {
            $this->db = null;
            error_log("Log: Database não disponível ainda");
        }
        
        $this->logFile = defined('LOG_FILE') ? LOG_FILE : __DIR__ . '/../logs/system.log';
        
        // Criar diretório de logs se não existir
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Registrar log no arquivo
     * @param string $message
     * @param string $level (DEBUG, INFO, WARNING, ERROR)
     * @param array $context Dados adicionais
     * @return bool
     */
    public function write($message, $level = 'INFO', $context = []) {
        // Verificar se logs estão habilitados
        if (defined('LOG_ENABLED') && !LOG_ENABLED) {
            return false;
        }
        
        // Verificar nível de log
        $logLevels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
        $minLevel = defined('LOG_LEVEL') ? LOG_LEVEL : 'INFO';
        
        if (!isset($logLevels[$level]) || $logLevels[$level] < $logLevels[$minLevel]) {
            return false;
        }
        
        try {
            $timestamp = date('Y-m-d H:i:s');
            $userInfo = $this->getUserInfo();
            
            // Formatar mensagem
            $logMessage = sprintf(
                "[%s] [%s] [%s] %s",
                $timestamp,
                $level,
                $userInfo,
                $message
            );
            
            // Adicionar contexto se houver
            if (!empty($context)) {
                $logMessage .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
            }
            
            $logMessage .= PHP_EOL;
            
            // Escrever no arquivo
            $result = @file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
            
            // Se falhar, usar error_log como fallback
            if ($result === false) {
                error_log($logMessage);
            }
            
            return $result !== false;
            
        } catch (Exception $e) {
            error_log("Erro ao escrever log: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log de DEBUG
     */
    public function debug($message, $context = []) {
        return $this->write($message, 'DEBUG', $context);
    }
    
    /**
     * Log de INFO
     */
    public function info($message, $context = []) {
        return $this->write($message, 'INFO', $context);
    }
    
    /**
     * Log de WARNING
     */
    public function warning($message, $context = []) {
        return $this->write($message, 'WARNING', $context);
    }
    
    /**
     * Log de ERROR
     */
    public function error($message, $context = []) {
        return $this->write($message, 'ERROR', $context);
    }
    
    /**
     * Registrar log de ticket no banco de dados
     */
    public function ticketLog($ticketId, $userId, $action, $oldStatus, $newStatus, $description) {
        // Só registra no DB se estiver disponível
        if (!$this->db) {
            $this->write("Ticket #{$ticketId} - {$action}: {$description}", 'INFO');
            return false;
        }
        
        try {
            $sql = "INSERT INTO ticket_logs (ticket_id, user_id, action, old_status, new_status, description) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $result = $this->db->insert($sql, [
                $ticketId,
                $userId,
                $action,
                $oldStatus,
                $newStatus,
                $description
            ]);
            
            // Também registrar no arquivo
            $this->info("Ticket #{$ticketId} - {$action}: {$description}", [
                'user_id' => $userId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);
            
            return $result !== false;
            
        } catch (Exception $e) {
            $this->error("Erro ao registrar log de ticket: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter logs de um ticket
     */
    public function getTicketLogs($ticketId, $limit = 100) {
        if (!$this->db) return [];
        
        $sql = "SELECT l.*, u.name as user_name 
                FROM ticket_logs l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE l.ticket_id = ?
                ORDER BY l.created_at DESC
                LIMIT ?";
        
        return $this->db->select($sql, [$ticketId, $limit]);
    }
    
    /**
     * Obter informações do usuário atual
     */
    private function getUserInfo() {
        if (isset($_SESSION['user_id'])) {
            $name = $_SESSION['user_name'] ?? 'Desconhecido';
            $role = $_SESSION['user_role'] ?? '';
            return "User #{$_SESSION['user_id']} - {$name} ({$role})";
        }
        
        if (isset($_SESSION['company_id'])) {
            $name = $_SESSION['company_name'] ?? 'Desconhecida';
            return "Company #{$_SESSION['company_id']} - {$name}";
        }
        
        return "Visitante - IP: " . $this->getClientIP();
    }
    
    /**
     * Obter IP do cliente
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                   'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return $_SERVER[$key];
            }
        }
        
        return 'Unknown';
    }
    
    /**
     * Ler logs do arquivo
     */
    public function readLogs($lines = 100, $level = null) {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        try {
            $file = new SplFileObject($this->logFile, 'r');
            $file->seek(PHP_INT_MAX);
            $totalLines = $file->key();
            
            $logs = [];
            $startLine = max(0, $totalLines - $lines);
            
            $file->seek($startLine);
            
            while (!$file->eof()) {
                $line = trim($file->fgets());
                
                if (empty($line)) {
                    continue;
                }
                
                // Filtrar por nível se especificado
                if ($level && strpos($line, "[{$level}]") === false) {
                    continue;
                }
                
                $logs[] = $this->parseLogLine($line);
            }
            
            return array_reverse($logs);
            
        } catch (Exception $e) {
            $this->error("Erro ao ler logs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Parsear linha de log
     */
    private function parseLogLine($line) {
        $pattern = '/\[([^\]]+)\]\s+\[([^\]]+)\]\s+\[([^\]]+)\]\s+(.+)/';
        
        if (preg_match($pattern, $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'level' => $matches[2],
                'user' => $matches[3],
                'message' => $matches[4],
                'raw' => $line
            ];
        }
        
        return [
            'timestamp' => '',
            'level' => '',
            'user' => '',
            'message' => $line,
            'raw' => $line
        ];
    }
    
    /**
     * Obter tamanho do arquivo de log
     */
    public function getLogFileSize() {
        if (!file_exists($this->logFile)) {
            return '0 B';
        }
        
        $bytes = filesize($this->logFile);
        
        // Função formatBytes inline
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Limpar logs antigos do banco de dados
     */
    public function clearOldDatabaseLogs($days = 90) {
        if (!$this->db) return 0;
        
        try {
            $sql = "DELETE FROM ticket_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $removed = $this->db->delete($sql, [$days]);
            
            $this->info("Logs do banco de dados limpos", [
                'removidos' => $removed,
                'dias' => $days
            ]);
            
            return $removed;
            
        } catch (Exception $e) {
            $this->error("Erro ao limpar logs do banco: " . $e->getMessage());
            return 0;
        }
    }
}
?>
