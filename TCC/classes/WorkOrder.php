<?php
/**
 * Classe WorkOrder - Sistema de Ordem de Serviço
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 */

class WorkOrder {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * ========================================
     * CRIAR ORDEM DE SERVIÇO
     * ========================================
     */
    public function create($ticketId, $userId) {
        try {
            $pdo = $this->db->getConnection();
            
            // Validar criação
            $validation = validateWorkOrderCreation($ticketId, $userId);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => implode(', ', $validation['errors'])
                ];
            }
            
            // Buscar dados do ticket
            $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
            $stmt->execute([$ticketId]);
            $ticket = $stmt->fetch();
            
            if (!$ticket) {
                return ['success' => false, 'message' => 'Ticket não encontrado'];
            }
            
            // Calcular prazo baseado na prioridade
            $deadline = calculateWorkOrderDeadline($ticket['priority']);
            
            // Iniciar transação
            $pdo->beginTransaction();
            
            // Criar OS
            $sql = "INSERT INTO work_orders (
                ticket_id, 
                created_by, 
                status, 
                priority, 
                deadline
            ) VALUES (?, ?, 'available', ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $ticketId,
                $userId,
                $ticket['priority'],
                $deadline
            ]);
            
            if (!$result) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Erro ao criar OS'];
            }
            
            $workOrderId = $pdo->lastInsertId();
            
            // Atualizar ticket
            $stmt = $pdo->prepare("
                UPDATE tickets 
                SET has_work_order = 1, 
                    work_order_id = ?,
                    status = 'work_order_created' 
                WHERE id = ?
            ");
            $stmt->execute([$workOrderId, $ticketId]);
            
            // Registrar log
            $this->addLog($workOrderId, $userId, 'CREATED', 'OS criada e notificada para todos os técnicos');
            
            // Commit
            $pdo->commit();
            
            // Notificar técnicos (fora da transação)
            notifyTechniciansNewWorkOrder($workOrderId);
            
            // Criar notificação para a empresa
            $notification = new Notification();
            $notification->create([
                'company_id' => $ticket['company_id'],
                'ticket_id' => $ticketId,
                'type' => 'work_order_created',
                'message' => "Ordem de Serviço #" . formatWorkOrderCode($workOrderId) . " foi criada para seu ticket"
            ]);
            
            logSystem("OS #{$workOrderId} criada para ticket #{$ticketId}", "INFO");
            
            return [
                'success' => true,
                'message' => 'OS criada com sucesso! Técnicos foram notificados.',
                'work_order_id' => $workOrderId
            ];
            
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            logSystem("Erro ao criar OS: " . $e->getMessage(), "ERROR");
            return [
                'success' => false,
                'message' => 'Erro ao criar OS: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ========================================
     * ACEITAR ORDEM DE SERVIÇO
     * ========================================
     */
    public function accept($workOrderId, $technicianId) {
        try {
            $pdo = $this->db->getConnection();
            
            // Iniciar transação com lock
            $pdo->beginTransaction();
            
            // Buscar OS com lock (evita race condition)
            $stmt = $pdo->prepare("
                SELECT * FROM work_orders 
                WHERE id = ? 
                FOR UPDATE
            ");
            $stmt->execute([$workOrderId]);
            $wo = $stmt->fetch();
            
            if (!$wo) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'OS não encontrada'];
            }
            
            // Verificar se ainda está disponível
            if ($wo['status'] !== 'available') {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Esta OS já foi aceita por outro técnico'];
            }
            
            // Verificar se técnico já está na OS
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM work_order_technicians 
                WHERE work_order_id = ? AND technician_id = ?
            ");
            $stmt->execute([$workOrderId, $technicianId]);
            
            if ($stmt->fetchColumn() > 0) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Você já está nesta OS'];
            }
            
            // Atualizar status da OS
            $stmt = $pdo->prepare("
                UPDATE work_orders 
                SET status = 'in_progress' 
                WHERE id = ?
            ");
            $stmt->execute([$workOrderId]);
            
            // Adicionar técnico como PRIMARY
            $stmt = $pdo->prepare("
                INSERT INTO work_order_technicians 
                (work_order_id, technician_id, role, status, accepted_at) 
                VALUES (?, ?, 'primary', 'accepted', NOW())
            ");
            $stmt->execute([$workOrderId, $technicianId]);
            
            // Atualizar ticket para in_progress
            $stmt = $pdo->prepare("
                UPDATE tickets t
                INNER JOIN work_orders wo ON t.id = wo.ticket_id
                SET t.status = 'in_progress'
                WHERE wo.id = ?
            ");
            $stmt->execute([$workOrderId]);
            
            // Registrar log
            $this->addLog($workOrderId, $technicianId, 'ACCEPTED', 'OS aceita como técnico principal');
            
            $pdo->commit();
            
            // Buscar dados para notificações
            $stmt = $pdo->prepare("
                SELECT wo.*, t.company_id, t.title
                FROM work_orders wo
                INNER JOIN tickets t ON wo.ticket_id = t.id
                WHERE wo.id = ?
            ");
            $stmt->execute([$workOrderId]);
            $woData = $stmt->fetch();
            
            // Notificar empresa
            $notification = new Notification();
            $notification->create([
                'company_id' => $woData['company_id'],
                'ticket_id' => $woData['ticket_id'],
                'type' => 'work_order_accepted',
                'message' => "Um técnico aceitou a OS #" . formatWorkOrderCode($workOrderId)
            ]);
            
            // Notificar atendente que criou
            $notification->create([
                'user_id' => $woData['created_by'],
                'ticket_id' => $woData['ticket_id'],
                'type' => 'work_order_accepted',
                'message' => "OS #" . formatWorkOrderCode($workOrderId) . " foi aceita"
            ]);
            
            logSystem("OS #{$workOrderId} aceita pelo técnico #{$technicianId}", "INFO");
            
            return [
                'success' => true,
                'message' => 'OS aceita com sucesso! Você é o técnico principal.'
            ];
            
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            logSystem("Erro ao aceitar OS: " . $e->getMessage(), "ERROR");
            return [
                'success' => false,
                'message' => 'Erro ao aceitar OS: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ========================================
     * ADICIONAR TÉCNICO DE SUPORTE
     * ========================================
     */
    public function addTechnician($workOrderId, $technicianId, $requesterId) {
        try {
            $pdo = $this->db->getConnection();
            
            // Buscar OS
            $stmt = $pdo->prepare("SELECT * FROM work_orders WHERE id = ?");
            $stmt->execute([$workOrderId]);
            $wo = $stmt->fetch();
            
            if (!$wo) {
                return ['success' => false, 'message' => 'OS não encontrada'];
            }
            
            // Verificar se requester tem permissão
            $stmt = $pdo->prepare("
                SELECT role FROM work_order_technicians 
                WHERE work_order_id = ? AND technician_id = ?
            ");
            $stmt->execute([$workOrderId, $requesterId]);
            $requester = $stmt->fetch();
            
            // Buscar info do requester (pode ser admin/atendente)
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$requesterId]);
            $user = $stmt->fetch();
            
            $canAdd = false;
            if ($user && in_array($user['role'], ['admin', 'attendant'])) {
                $canAdd = true;
            } elseif ($requester && $requester['role'] === 'primary') {
                $canAdd = true;
            }
            
            if (!$canAdd) {
                return ['success' => false, 'message' => 'Você não tem permissão para adicionar técnicos'];
            }
            
            // Verificar se técnico já está na OS
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM work_order_technicians 
                WHERE work_order_id = ? AND technician_id = ?
            ");
            $stmt->execute([$workOrderId, $technicianId]);
            
            if ($stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'Técnico já está nesta OS'];
            }
            
            // Verificar limite de técnicos
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM work_order_technicians 
                WHERE work_order_id = ?
            ");
            $stmt->execute([$workOrderId]);
            
            if ($stmt->fetchColumn() >= WORK_ORDER_LIMITS['max_technicians']) {
                return ['success' => false, 'message' => 'Limite de técnicos atingido'];
            }
            
            // Adicionar técnico como SUPPORT
            $stmt = $pdo->prepare("
                INSERT INTO work_order_technicians 
                (work_order_id, technician_id, role, status, accepted_at) 
                VALUES (?, ?, 'support', 'accepted', NOW())
            ");
            $result = $stmt->execute([$workOrderId, $technicianId]);
            
            if ($result) {
                // Registrar log
                $this->addLog($workOrderId, $requesterId, 'TECHNICIAN_ADDED', "Técnico de suporte adicionado");
                
                // Notificar técnico adicionado
                $notification = new Notification();
                $notification->create([
                    'user_id' => $technicianId,
                    'ticket_id' => $wo['ticket_id'],
                    'type' => 'technician_added_to_os',
                    'message' => "Você foi adicionado como suporte na OS #" . formatWorkOrderCode($workOrderId)
                ]);
                
                logSystem("Técnico #{$technicianId} adicionado à OS #{$workOrderId}", "INFO");
                
                return [
                    'success' => true,
                    'message' => 'Técnico adicionado como suporte'
                ];
            }
            
            return ['success' => false, 'message' => 'Erro ao adicionar técnico'];
            
        } catch (Exception $e) {
            logSystem("Erro ao adicionar técnico: " . $e->getMessage(), "ERROR");
            return [
                'success' => false,
                'message' => 'Erro ao adicionar técnico: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ========================================
     * REMOVER TÉCNICO
     * ========================================
     */
    public function removeTechnician($workOrderId, $technicianId, $requesterId) {
        try {
            $pdo = $this->db->getConnection();
            
            // Verificar se é o último técnico
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM work_order_technicians 
                WHERE work_order_id = ?
            ");
            $stmt->execute([$workOrderId]);
            
            if ($stmt->fetchColumn() <= 1) {
                return ['success' => false, 'message' => 'Não é possível remover o último técnico'];
            }
            
            // Remover técnico
            $stmt = $pdo->prepare("
                DELETE FROM work_order_technicians 
                WHERE work_order_id = ? AND technician_id = ?
            ");
            $result = $stmt->execute([$workOrderId, $technicianId]);
            
            if ($result) {
                $this->addLog($workOrderId, $requesterId, 'TECHNICIAN_REMOVED', "Técnico removido da OS");
                
                logSystem("Técnico #{$technicianId} removido da OS #{$workOrderId}", "INFO");
                
                return ['success' => true, 'message' => 'Técnico removido'];
            }
            
            return ['success' => false, 'message' => 'Erro ao remover técnico'];
            
        } catch (Exception $e) {
            logSystem("Erro ao remover técnico: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }
    
    /**
     * ========================================
     * COMPLETAR OS (TÉCNICO)
     * ========================================
     */
    public function completeTechnician($workOrderId, $technicianId, $notes = '') {
        try {
            $pdo = $this->db->getConnection();
            
            // Atualizar status do técnico
            $stmt = $pdo->prepare("
                UPDATE work_order_technicians 
                SET status = 'completed', 
                    completed_at = NOW(),
                    notes = ?
                WHERE work_order_id = ? AND technician_id = ?
            ");
            $stmt->execute([$notes, $workOrderId, $technicianId]);
            
            // Verificar se todos os técnicos completaram
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total,
                       SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM work_order_technicians
                WHERE work_order_id = ?
            ");
            $stmt->execute([$workOrderId]);
            $status = $stmt->fetch();
            
            $allCompleted = ($status['total'] == $status['completed']);
            
            if ($allCompleted) {
                // Completar OS
                return $this->complete($workOrderId);
            }
            
            $this->addLog($workOrderId, $technicianId, 'TECHNICIAN_COMPLETED', "Técnico finalizou sua parte");
            
            return [
                'success' => true,
                'message' => 'Sua parte foi concluída. Aguardando outros técnicos.',
                'all_completed' => false
            ];
            
        } catch (Exception $e) {
            logSystem("Erro ao completar técnico: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }
    
    /**
     * ========================================
     * COMPLETAR OS (FINAL)
     * ========================================
     */
    public function complete($workOrderId) {
        try {
            $pdo = $this->db->getConnection();
            
            $pdo->beginTransaction();
            
            // Buscar dados da OS
            $stmt = $pdo->prepare("
                SELECT wo.*, t.company_id
                FROM work_orders wo
                INNER JOIN tickets t ON wo.ticket_id = t.id
                WHERE wo.id = ?
            ");
            $stmt->execute([$workOrderId]);
            $wo = $stmt->fetch();
            
            if (!$wo) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'OS não encontrada'];
            }
            
            // Atualizar OS
            $stmt = $pdo->prepare("
                UPDATE work_orders 
                SET status = 'completed', 
                    completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$workOrderId]);
            
            // Atualizar ticket
            $stmt = $pdo->prepare("
                UPDATE tickets 
                SET status = 'resolved', 
                    resolved_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$wo['ticket_id']]);
            
            // Log
            $this->addLog($workOrderId, null, 'COMPLETED', 'OS concluída - todos os técnicos finalizaram');
            
            $pdo->commit();
            
            // Notificar empresa
            $notification = new Notification();
            $notification->create([
                'company_id' => $wo['company_id'],
                'ticket_id' => $wo['ticket_id'],
                'type' => 'work_order_completed',
                'message' => "OS #" . formatWorkOrderCode($workOrderId) . " foi concluída"
            ]);
            
            logSystem("OS #{$workOrderId} concluída", "INFO");
            
            return [
                'success' => true,
                'message' => 'OS concluída com sucesso!',
                'all_completed' => true
            ];
            
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            logSystem("Erro ao completar OS: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }
    
    /**
     * ========================================
     * CANCELAR OS
     * ========================================
     */
    public function cancel($workOrderId, $userId, $reason = '') {
        try {
            $pdo = $this->db->getConnection();
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                UPDATE work_orders 
                SET status = 'cancelled' 
                WHERE id = ?
            ");
            $stmt->execute([$workOrderId]);
            
            $this->addLog($workOrderId, $userId, 'CANCELLED', $reason ?: 'OS cancelada');
            
            $pdo->commit();
            
            logSystem("OS #{$workOrderId} cancelada", "INFO");
            
            return ['success' => true, 'message' => 'OS cancelada'];
            
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }
    
    /**
     * ========================================
     * BUSCAR DETALHES DA OS
     * ========================================
     */
    public function getById($id) {
        try {
            $pdo = $this->db->getConnection();
            
            $stmt = $pdo->prepare("SELECT * FROM v_work_orders_complete WHERE id = ?");
            $stmt->execute([$id]);
            
            return $stmt->fetch();
            
        } catch (Exception $e) {
            logSystem("Erro ao buscar OS: " . $e->getMessage(), "ERROR");
            return null;
        }
    }
    
    /**
     * ========================================
     * LISTAR OS DISPONÍVEIS
     * ========================================
     */
    public function getAvailable($limit = 50) {
        try {
            $pdo = $this->db->getConnection();
            
            $stmt = $pdo->prepare("
                SELECT * FROM v_work_orders_complete 
                WHERE status = 'available'
                ORDER BY 
                    CASE priority 
                        WHEN 'high' THEN 1 
                        WHEN 'medium' THEN 2 
                        ELSE 3 
                    END,
                    created_at ASC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            logSystem("Erro ao listar OS: " . $e->getMessage(), "ERROR");
            return [];
        }
    }
    
    /**
     * ========================================
     * MINHAS OS (TÉCNICO)
     * ========================================
     */
    public function getMyOrders($technicianId, $status = null) {
        try {
            $pdo = $this->db->getConnection();
            
            $sql = "
                SELECT DISTINCT wo.*
                FROM v_work_orders_complete wo
                INNER JOIN work_order_technicians wot ON wo.id = wot.work_order_id
                WHERE wot.technician_id = ?
            ";
            
            $params = [$technicianId];
            
            if ($status) {
                $sql .= " AND wo.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY wo.created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            logSystem("Erro ao buscar minhas OS: " . $e->getMessage(), "ERROR");
            return [];
        }
    }
    
    /**
     * ========================================
     * BUSCAR EQUIPE DA OS
     * ========================================
     */
    public function getTeam($workOrderId) {
        try {
            $pdo = $this->db->getConnection();
            
            $stmt = $pdo->prepare("
                SELECT 
                    wot.*,
                    u.name as technician_name,
                    u.email as technician_email,
                    u.phone as technician_phone
                FROM work_order_technicians wot
                INNER JOIN users u ON wot.technician_id = u.id
                WHERE wot.work_order_id = ?
                ORDER BY 
                    CASE wot.role 
                        WHEN 'primary' THEN 1 
                        ELSE 2 
                    END
            ");
            $stmt->execute([$workOrderId]);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            logSystem("Erro ao buscar equipe: " . $e->getMessage(), "ERROR");
            return [];
        }
    }
    
    /**
     * ========================================
     * ADICIONAR LOG
     * ========================================
     */
    private function addLog($workOrderId, $userId, $action, $description = '') {
        try {
            $pdo = $this->db->getConnection();
            
            $stmt = $pdo->prepare("
                INSERT INTO work_order_logs 
                (work_order_id, user_id, action, description) 
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([$workOrderId, $userId, $action, $description]);
            
            return true;
            
        } catch (Exception $e) {
            logSystem("Erro ao adicionar log: " . $e->getMessage(), "ERROR");
            return false;
        }
    }
    
    /**
     * ========================================
     * BUSCAR LOGS
     * ========================================
     */
    public function getLogs($workOrderId) {
        try {
            $pdo = $this->db->getConnection();
            
            $stmt = $pdo->prepare("
                SELECT 
                    wol.*,
                    u.name as user_name
                FROM work_order_logs wol
                LEFT JOIN users u ON wol.user_id = u.id
                WHERE wol.work_order_id = ?
                ORDER BY wol.created_at DESC
            ");
            $stmt->execute([$workOrderId]);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            logSystem("Erro ao buscar logs: " . $e->getMessage(), "ERROR");
            return [];
        }
    }
}
?>