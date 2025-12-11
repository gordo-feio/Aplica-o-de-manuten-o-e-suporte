<?php
/**
 * Classe Ticket - Gerenciamento de Tickets/Chamados
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 */

class Ticket {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Criar novo ticket
     * @param array $data
     * @return array ['success' => bool, 'message' => string, 'id' => int]
     */
    public function create($data) {
        try {
            // Validações
            if (empty($data['company_id']) || empty($data['title']) || empty($data['description'])) {
                return ['success' => false, 'message' => 'Preencha todos os campos obrigatórios.'];
            }
            
            // Inserir ticket
            $sql = "INSERT INTO tickets (company_id, title, description, category, priority, address) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $params = [
                $data['company_id'],
                $data['title'],
                $data['description'],
                $data['category'] ?? 'other',
                $data['priority'] ?? 'medium',
                $data['address'] ?? null
            ];
            
            $ticketId = $this->db->insert($sql, $params);
            
            if ($ticketId) {
                // Criar log
                $this->addLog($ticketId, null, 'CREATED', null, 'created', 'Ticket criado');
                
                logSystem("Ticket criado: ID {$ticketId} pela empresa {$data['company_id']}", "INFO");
                
                return [
                    'success' => true, 
                    'message' => 'Ticket criado com sucesso!',
                    'id' => $ticketId
                ];
            }
            
            return ['success' => false, 'message' => 'Erro ao criar ticket.'];
            
        } catch (Exception $e) {
            logSystem("Erro ao criar ticket: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao criar ticket.'];
        }
    }
    
    /**
     * Assumir ticket
     * @param int $ticketId
     * @param int $userId
     * @return array ['success' => bool, 'message' => string]
     */
    public function assume($ticketId, $userId) {
        try {
            $ticket = $this->getById($ticketId);
            
            if (!$ticket) {
                return ['success' => false, 'message' => 'Ticket não encontrado.'];
            }
            
            if ($ticket['status'] !== 'created' && $ticket['status'] !== 'reopened') {
                return ['success' => false, 'message' => 'Este ticket já foi assumido.'];
            }
            
            // Atualizar ticket
            $sql = "UPDATE tickets SET 
                    status = 'assumed', 
                    assigned_user_id = ?, 
                    assumed_at = NOW(),
                    updated_at = NOW()
                    WHERE id = ?";
            
            $this->db->update($sql, [$userId, $ticketId]);
            
            // Criar log
            $this->addLog($ticketId, $userId, 'ASSUMED', $ticket['status'], 'assumed', 'Ticket assumido');
            
            // Criar notificação
            $user = $this->db->selectOne("SELECT name FROM users WHERE id = ?", [$userId]);
            $userName = $user['name'] ?? 'Atendente';
            
            $this->createNotification(
                $ticketId,
                $ticket['company_id'],
                $userId,
                'assumed',
                getNotificationMessage('assumed', $userName)
            );
            
            logSystem("Ticket {$ticketId} assumido pelo usuário {$userId}", "INFO");
            
            return ['success' => true, 'message' => 'Ticket assumido com sucesso!'];
            
        } catch (Exception $e) {
            logSystem("Erro ao assumir ticket: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao assumir ticket.'];
        }
    }
    
    /**
     * Despachar equipe
     * @param int $ticketId
     * @param int $userId
     * @return array ['success' => bool, 'message' => string]
     */
    public function dispatch($ticketId, $userId) {
        try {
            $ticket = $this->getById($ticketId);
            
            if (!$ticket) {
                return ['success' => false, 'message' => 'Ticket não encontrado.'];
            }
            
            if ($ticket['status'] === 'closed') {
                return ['success' => false, 'message' => 'Ticket já está encerrado.'];
            }
            
            // Atualizar ticket
            $sql = "UPDATE tickets SET 
                    status = 'dispatched', 
                    dispatched_at = NOW(),
                    updated_at = NOW()
                    WHERE id = ?";
            
            $this->db->update($sql, [$ticketId]);
            
            // Criar log
            $this->addLog($ticketId, $userId, 'DISPATCHED', $ticket['status'], 'dispatched', 'Equipe despachada');
            
            // Criar notificação
            $this->createNotification(
                $ticketId,
                $ticket['company_id'],
                $userId,
                'dispatched',
                getNotificationMessage('dispatched')
            );
            
            logSystem("Equipe despachada para ticket {$ticketId}", "INFO");
            
            return ['success' => true, 'message' => 'Equipe despachada com sucesso!'];
            
        } catch (Exception $e) {
            logSystem("Erro ao despachar equipe: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao despachar equipe.'];
        }
    }
    
    /**
     * Marcar ticket como em andamento
     * @param int $ticketId
     * @param int $userId
     * @return array ['success' => bool, 'message' => string]
     */
    public function setInProgress($ticketId, $userId) {
        try {
            $ticket = $this->getById($ticketId);
            
            if (!$ticket) {
                return ['success' => false, 'message' => 'Ticket não encontrado.'];
            }
            
            $sql = "UPDATE tickets SET 
                    status = 'in_progress',
                    updated_at = NOW()
                    WHERE id = ?";
            
            $this->db->update($sql, [$ticketId]);
            
            $this->addLog($ticketId, $userId, 'IN_PROGRESS', $ticket['status'], 'in_progress', 'Ticket em andamento');
            
            return ['success' => true, 'message' => 'Status atualizado com sucesso!'];
            
        } catch (Exception $e) {
            logSystem("Erro ao atualizar status: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao atualizar status.'];
        }
    }
    
    /**
     * Finalizar ticket (marcar como resolvido)
     * @param int $ticketId
     * @param int $userId
     * @return array ['success' => bool, 'message' => string]
     */
    public function resolve($ticketId, $userId) {
        try {
            $ticket = $this->getById($ticketId);
            
            if (!$ticket) {
                return ['success' => false, 'message' => 'Ticket não encontrado.'];
            }
            
            $sql = "UPDATE tickets SET 
                    status = 'resolved',
                    resolved_at = NOW(),
                    updated_at = NOW()
                    WHERE id = ?";
            
            $this->db->update($sql, [$ticketId]);
            
            $this->addLog($ticketId, $userId, 'RESOLVED', $ticket['status'], 'resolved', 'Ticket resolvido');
            
            $this->createNotification(
                $ticketId,
                $ticket['company_id'],
                $userId,
                'resolved',
                getNotificationMessage('resolved')
            );
            
            logSystem("Ticket {$ticketId} resolvido", "INFO");
            
            return ['success' => true, 'message' => 'Ticket resolvido com sucesso!'];
            
        } catch (Exception $e) {
            logSystem("Erro ao resolver ticket: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao resolver ticket.'];
        }
    }
    
    /**
     * Encerrar ticket
     * @param int $ticketId
     * @param int $userId
     * @return array ['success' => bool, 'message' => string]
     */
    public function close($ticketId, $userId) {
        try {
            $ticket = $this->getById($ticketId);
            
            if (!$ticket) {
                return ['success' => false, 'message' => 'Ticket não encontrado.'];
            }
            
            $sql = "UPDATE tickets SET 
                    status = 'closed',
                    closed_at = NOW(),
                    updated_at = NOW()
                    WHERE id = ?";
            
            $this->db->update($sql, [$ticketId]);
            
            $this->addLog($ticketId, $userId, 'CLOSED', $ticket['status'], 'closed', 'Ticket encerrado');
            
            $this->createNotification(
                $ticketId,
                $ticket['company_id'],
                $userId,
                'closed',
                getNotificationMessage('closed')
            );
            
            logSystem("Ticket {$ticketId} encerrado", "INFO");
            
            return ['success' => true, 'message' => 'Ticket encerrado com sucesso!'];
            
        } catch (Exception $e) {
            logSystem("Erro ao encerrar ticket: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao encerrar ticket.'];
        }
    }
    
    /**
     * Reabrir ticket
     * @param int $ticketId
     * @param int $companyId
     * @return array ['success' => bool, 'message' => string]
     */
    public function reopen($ticketId, $companyId) {
        try {
            $ticket = $this->getById($ticketId);
            
            if (!$ticket) {
                return ['success' => false, 'message' => 'Ticket não encontrado.'];
            }
            
            if ($ticket['status'] !== 'closed' && $ticket['status'] !== 'resolved') {
                return ['success' => false, 'message' => 'Apenas tickets encerrados podem ser reabertos.'];
            }
            
            $sql = "UPDATE tickets SET 
                    status = 'reopened',
                    updated_at = NOW()
                    WHERE id = ?";
            
            $this->db->update($sql, [$ticketId]);
            
            $this->addLog($ticketId, null, 'REOPENED', $ticket['status'], 'reopened', 'Ticket reaberto pela empresa');
            
            $this->createNotification(
                $ticketId,
                $companyId,
                null,
                'reopened',
                getNotificationMessage('reopened')
            );
            
            logSystem("Ticket {$ticketId} reaberto", "WARNING");
            
            return ['success' => true, 'message' => 'Ticket reaberto com sucesso!'];
            
        } catch (Exception $e) {
            logSystem("Erro ao reabrir ticket: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao reabrir ticket.'];
        }
    }
    
    /**
     * Obter ticket por ID
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        $sql = "SELECT t.*, 
                c.name as company_name, c.email as company_email, c.phone as company_phone,
                u.name as assigned_user_name
                FROM tickets t
                INNER JOIN companies c ON t.company_id = c.id
                LEFT JOIN users u ON t.assigned_user_id = u.id
                WHERE t.id = ?";
        return $this->db->selectOne($sql, [$id]);
    }
    
    /**
     * Obter todos os tickets com filtros
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAll($filters = [], $limit = null, $offset = 0) {
        $sql = "SELECT t.*, 
                c.name as company_name,
                u.name as assigned_user_name
                FROM tickets t
                INNER JOIN companies c ON t.company_id = c.id
                LEFT JOIN users u ON t.assigned_user_id = u.id
                WHERE 1=1";
        $params = [];
        
        // Filtros
        if (!empty($filters['company_id'])) {
            $sql .= " AND t.company_id = ?";
            $params[] = $filters['company_id'];
        }
        
        if (!empty($filters['assigned_user_id'])) {
            $sql .= " AND t.assigned_user_id = ?";
            $params[] = $filters['assigned_user_id'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = ?";
            $params[] = $filters['priority'];
        }
        
        if (!empty($filters['category'])) {
            $sql .= " AND t.category = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
            $searchTerm = '%' . $this->db->escapeLike($filters['search']) . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Ordenação: por prioridade e data
        $sql .= " ORDER BY 
                  CASE t.priority 
                    WHEN 'high' THEN 1 
                    WHEN 'medium' THEN 2 
                    WHEN 'low' THEN 3 
                  END ASC,
                  t.created_at ASC";
        
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int)$limit;
            $params[] = (int)$offset;
        }
        
        return $this->db->select($sql, $params);
    }
    
    /**
     * Contar tickets
     * @param array $filters
     * @return int
     */
    public function count($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM tickets WHERE 1=1";
        $params = [];
        
        if (!empty($filters['company_id'])) {
            $sql .= " AND company_id = ?";
            $params[] = $filters['company_id'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['priority'])) {
            $sql .= " AND priority = ?";
            $params[] = $filters['priority'];
        }
        
        $result = $this->db->selectOne($sql, $params);
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * Adicionar log ao ticket
     * @param int $ticketId
     * @param int $userId
     * @param string $action
     * @param string $oldStatus
     * @param string $newStatus
     * @param string $description
     */
    private function addLog($ticketId, $userId, $action, $oldStatus, $newStatus, $description) {
        $sql = "INSERT INTO ticket_logs (ticket_id, user_id, action, old_status, new_status, description) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $this->db->insert($sql, [$ticketId, $userId, $action, $oldStatus, $newStatus, $description]);
    }
    
    /**
     * Criar notificação
     * @param int $ticketId
     * @param int $companyId
     * @param int $userId
     * @param string $type
     * @param string $message
     */
    private function createNotification($ticketId, $companyId, $userId, $type, $message) {
        $sql = "INSERT INTO notifications (ticket_id, company_id, user_id, type, message) 
                VALUES (?, ?, ?, ?, ?)";
        $this->db->insert($sql, [$ticketId, $companyId, $userId, $type, $message]);
    }
    
    /**
     * Obter logs do ticket
     * @param int $ticketId
     * @return array
     */
    public function getLogs($ticketId) {
        $sql = "SELECT l.*, u.name as user_name 
                FROM ticket_logs l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE l.ticket_id = ?
                ORDER BY l.created_at DESC";
        return $this->db->select($sql, [$ticketId]);
    }
}
?>