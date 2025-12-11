<?php
/**
 * Classe Notification - Gerenciamento de Notificações
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 */

class Notification {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Criar notificação
     * @param array $data
     * @return bool
     */
    public function create($data) {
        try {
            $sql = "INSERT INTO notifications (ticket_id, company_id, user_id, type, message) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $params = [
                $data['ticket_id'],
                $data['company_id'],
                $data['user_id'] ?? null,
                $data['type'],
                $data['message']
            ];
            
            $id = $this->db->insert($sql, $params);
            
            return $id !== false;
            
        } catch (Exception $e) {
            logSystem("Erro ao criar notificação: " . $e->getMessage(), "ERROR");
            return false;
        }
    }
    
    /**
     * Obter notificações da empresa
     * @param int $companyId
     * @param bool $onlyUnread
     * @param int $limit
     * @return array
     */
    public function getByCompany($companyId, $onlyUnread = false, $limit = 50) {
        $sql = "SELECT n.*, t.title as ticket_title, u.name as user_name
                FROM notifications n
                INNER JOIN tickets t ON n.ticket_id = t.id
                LEFT JOIN users u ON n.user_id = u.id
                WHERE n.company_id = ?";
        
        $params = [$companyId];
        
        if ($onlyUnread) {
            $sql .= " AND n.is_read = 0";
        }
        
        $sql .= " ORDER BY n.created_at DESC LIMIT ?";
        $params[] = (int)$limit;
        
        return $this->db->select($sql, $params);
    }
    
    /**
     * Obter notificações do usuário
     * @param int $userId
     * @param bool $onlyUnread
     * @param int $limit
     * @return array
     */
    public function getByUser($userId, $onlyUnread = false, $limit = 50) {
        $sql = "SELECT n.*, t.title as ticket_title, c.name as company_name
                FROM notifications n
                INNER JOIN tickets t ON n.ticket_id = t.id
                INNER JOIN companies c ON n.company_id = c.id
                WHERE n.user_id = ?";
        
        $params = [$userId];
        
        if ($onlyUnread) {
            $sql .= " AND n.is_read = 0";
        }
        
        $sql .= " ORDER BY n.created_at DESC LIMIT ?";
        $params[] = (int)$limit;
        
        return $this->db->select($sql, $params);
    }
    
    /**
     * Marcar notificação como lida
     * @param int $id
     * @return bool
     */
    public function markAsRead($id) {
        try {
            $sql = "UPDATE notifications SET is_read = 1 WHERE id = ?";
            $this->db->update($sql, [$id]);
            return true;
        } catch (Exception $e) {
            logSystem("Erro ao marcar notificação como lida: " . $e->getMessage(), "ERROR");
            return false;
        }
    }
    
    /**
     * Marcar todas as notificações como lidas
     * @param int $companyId
     * @param int $userId
     * @return bool
     */
    public function markAllAsRead($companyId = null, $userId = null) {
        try {
            if ($companyId) {
                $sql = "UPDATE notifications SET is_read = 1 WHERE company_id = ?";
                $this->db->update($sql, [$companyId]);
            } elseif ($userId) {
                $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
                $this->db->update($sql, [$userId]);
            } else {
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            logSystem("Erro ao marcar todas notificações como lidas: " . $e->getMessage(), "ERROR");
            return false;
        }
    }
    
    /**
     * Contar notificações não lidas
     * @param int $companyId
     * @param int $userId
     * @return int
     */
    public function countUnread($companyId = null, $userId = null) {
        try {
            if ($companyId) {
                $sql = "SELECT COUNT(*) as total FROM notifications 
                        WHERE company_id = ? AND is_read = 0";
                $result = $this->db->selectOne($sql, [$companyId]);
            } elseif ($userId) {
                $sql = "SELECT COUNT(*) as total FROM notifications 
                        WHERE user_id = ? AND is_read = 0";
                $result = $this->db->selectOne($sql, [$userId]);
            } else {
                return 0;
            }
            
            return $result ? (int)$result['total'] : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Deletar notificação
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            $sql = "DELETE FROM notifications WHERE id = ?";
            $this->db->delete($sql, [$id]);
            return true;
        } catch (Exception $e) {
            logSystem("Erro ao deletar notificação: " . $e->getMessage(), "ERROR");
            return false;
        }
    }
    
    /**
     * Deletar notificações antigas (mais de 30 dias)
     * @return int Número de notificações deletadas
     */
    public function deleteOld() {
        try {
            $sql = "DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            return $this->db->delete($sql, []);
        } catch (Exception $e) {
            logSystem("Erro ao deletar notificações antigas: " . $e->getMessage(), "ERROR");
            return 0;
        }
    }
}
?>