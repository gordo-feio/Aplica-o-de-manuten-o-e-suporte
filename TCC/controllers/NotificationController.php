<?php
/**
 * NotificationController - Controlador de Notificações
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 * 
 * CENTRALIZA TODA lógica de notificações
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Notification.php';
require_once __DIR__ . '/../includes/functions.php';

class NotificationController {
    private $auth;
    private $notification;
    
    public function __construct() {
        $this->auth = new Auth();
        $this->notification = new Notification();
    }
    
    /**
     * Obter notificações do usuário/empresa logado
     */
    public function getNotifications() {
        if (!$this->auth->isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Não autenticado'], 401);
        }
        
        $onlyUnread = isset($_GET['unread']) && $_GET['unread'] === 'true';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        
        try {
            if ($this->auth->isCompany()) {
                $companyId = $this->auth->getCompanyId();
                $notifications = $this->notification->getByCompany($companyId, $onlyUnread, $limit);
                $unreadCount = $this->notification->countUnread($companyId, null);
            } else {
                $userId = $this->auth->getUserId();
                $notifications = $this->notification->getByUser($userId, $onlyUnread, $limit);
                $unreadCount = $this->notification->countUnread(null, $userId);
            }
            
            jsonResponse([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
                'total' => count($notifications)
            ]);
            
        } catch (Exception $e) {
            logSystem("Erro ao buscar notificações: " . $e->getMessage(), "ERROR");
            jsonResponse(['success' => false, 'message' => 'Erro ao buscar notificações'], 500);
        }
    }
    
    /**
     * Marcar notificação como lida
     */
    public function markAsRead() {
        if (!$this->auth->isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Não autenticado'], 401);
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
        }
        
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        
        if (!$notificationId) {
            jsonResponse(['success' => false, 'message' => 'ID de notificação inválido'], 400);
        }
        
        try {
            $result = $this->notification->markAsRead($notificationId);
            
            if ($result) {
                jsonResponse([
                    'success' => true,
                    'message' => 'Notificação marcada como lida'
                ]);
            } else {
                jsonResponse([
                    'success' => false,
                    'message' => 'Erro ao marcar notificação'
                ], 500);
            }
            
        } catch (Exception $e) {
            logSystem("Erro ao marcar notificação: " . $e->getMessage(), "ERROR");
            jsonResponse(['success' => false, 'message' => 'Erro ao processar requisição'], 500);
        }
    }
    
    /**
     * Marcar todas as notificações como lidas
     */
    public function markAllAsRead() {
        if (!$this->auth->isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Não autenticado'], 401);
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
        }
        
        try {
            if ($this->auth->isCompany()) {
                $companyId = $this->auth->getCompanyId();
                $result = $this->notification->markAllAsRead($companyId, null);
            } else {
                $userId = $this->auth->getUserId();
                $result = $this->notification->markAllAsRead(null, $userId);
            }
            
            if ($result) {
                jsonResponse([
                    'success' => true,
                    'message' => 'Todas as notificações foram marcadas como lidas'
                ]);
            } else {
                jsonResponse([
                    'success' => false,
                    'message' => 'Nenhuma notificação para marcar'
                ], 400);
            }
            
        } catch (Exception $e) {
            logSystem("Erro ao marcar todas notificações: " . $e->getMessage(), "ERROR");
            jsonResponse(['success' => false, 'message' => 'Erro ao processar requisição'], 500);
        }
    }
    
    /**
     * Deletar notificação
     */
    public function delete() {
        if (!$this->auth->isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Não autenticado'], 401);
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
        }
        
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        
        if (!$notificationId) {
            jsonResponse(['success' => false, 'message' => 'ID de notificação inválido'], 400);
        }
        
        try {
            $result = $this->notification->delete($notificationId);
            
            if ($result) {
                jsonResponse([
                    'success' => true,
                    'message' => 'Notificação deletada com sucesso'
                ]);
            } else {
                jsonResponse([
                    'success' => false,
                    'message' => 'Erro ao deletar notificação'
                ], 500);
            }
            
        } catch (Exception $e) {
            logSystem("Erro ao deletar notificação: " . $e->getMessage(), "ERROR");
            jsonResponse(['success' => false, 'message' => 'Erro ao processar requisição'], 500);
        }
    }
    
    /**
     * Contar notificações não lidas
     */
    public function countUnread() {
        if (!$this->auth->isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Não autenticado'], 401);
        }
        
        try {
            if ($this->auth->isCompany()) {
                $companyId = $this->auth->getCompanyId();
                $count = $this->notification->countUnread($companyId, null);
            } else {
                $userId = $this->auth->getUserId();
                $count = $this->notification->countUnread(null, $userId);
            }
            
            jsonResponse([
                'success' => true,
                'unread_count' => $count
            ]);
            
        } catch (Exception $e) {
            logSystem("Erro ao contar notificações: " . $e->getMessage(), "ERROR");
            jsonResponse(['success' => false, 'message' => 'Erro ao processar requisição'], 500);
        }
    }
    
    /**
     * Limpar notificações antigas (admin)
     */
    public function cleanOld() {
        if (!$this->auth->isAdmin()) {
            jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
        }
        
        try {
            $deleted = $this->notification->deleteOld();
            
            jsonResponse([
                'success' => true,
                'message' => "$deleted notificação(ões) antiga(s) deletada(s)",
                'deleted_count' => $deleted
            ]);
            
        } catch (Exception $e) {
            logSystem("Erro ao limpar notificações antigas: " . $e->getMessage(), "ERROR");
            jsonResponse(['success' => false, 'message' => 'Erro ao processar requisição'], 500);
        }
    }
}

// ========================================
// PROCESSAR REQUISIÇÕES
// ========================================
if (isset($_GET['action']) || isset($_POST['action'])) {
    $controller = new NotificationController();
    $action = $_GET['action'] ?? $_POST['action'];
    
    switch ($action) {
        case 'get':
        case 'list':
            $controller->getNotifications();
            break;
        case 'mark_read':
            $controller->markAsRead();
            break;
        case 'mark_all_read':
            $controller->markAllAsRead();
            break;
        case 'delete':
            $controller->delete();
            break;
        case 'count_unread':
            $controller->countUnread();
            break;
        case 'clean_old':
            $controller->cleanOld();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Ação inválida'], 400);
    }
}
?>