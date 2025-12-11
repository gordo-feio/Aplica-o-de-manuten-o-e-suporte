<!-- ========================================
     ajax/assume_ticket.php
     ======================================== -->
<?php
/**
 * ASSUMIR TICKET - Redireciona para TicketController
 */
require_once __DIR__ . '/../controllers/TicketController.php';
?>

<!-- ========================================
     ajax/dispatch_ticket.php
     ======================================== -->
<?php
/**
 * DESPACHAR EQUIPE - Redireciona para TicketController
 */
require_once __DIR__ . '/../controllers/TicketController.php';
?>

<!-- ========================================
     ajax/close_ticket.php
     ======================================== -->
<?php
/**
 * ENCERRAR TICKET - Redireciona para TicketController
 */
require_once __DIR__ . '/../controllers/TicketController.php';
?>

<!-- ========================================
     ajax/reopen_ticket.php
     ======================================== -->
<?php
/**
 * REABRIR TICKET - Redireciona para TicketController
 */
require_once __DIR__ . '/../controllers/TicketController.php';
?>

<!-- ========================================
     ajax/add_comment.php
     ======================================== -->
<?php
/**
 * ADICIONAR COMENTÁRIO - Redireciona para TicketController
 */
require_once __DIR__ . '/../controllers/TicketController.php';
?>

<!-- ========================================
     ajax/get_comments.php - NOVO (Buscar comentários)
     ======================================== -->
<?php
/**
 * BUSCAR COMENTÁRIOS DO TICKET
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Não autenticado'], 401);
}

$ticketId = (int)($_GET['ticket_id'] ?? 0);

if (!$ticketId) {
    jsonResponse(['success' => false, 'message' => 'Ticket inválido'], 400);
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Buscar comentários
    $sql = "SELECT l.*, u.name as user_name 
            FROM ticket_logs l
            LEFT JOIN users u ON l.user_id = u.id
            WHERE l.ticket_id = ? AND l.action = 'COMMENTED'
            ORDER BY l.created_at ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ticketId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse([
        'success' => true,
        'comments' => $comments,
        'total' => count($comments)
    ]);
    
} catch (Exception $e) {
    logSystem("Erro ao buscar comentários: " . $e->getMessage(), "ERROR");
    jsonResponse(['success' => false, 'message' => 'Erro ao processar requisição'], 500);
}
?>

<!-- ========================================
     ajax/mark_all_read.php
     ======================================== -->
<?php
/**
 * MARCAR TODAS NOTIFICAÇÕES - Redireciona para NotificationController
 */
require_once __DIR__ . '/../controllers/NotificationController.php';
?>

<!-- ========================================
     ajax/mark_notification_read.php - NOVO
     ======================================== -->
<?php
/**
 * MARCAR NOTIFICAÇÃO COMO LIDA - Redireciona para NotificationController
 */
require_once __DIR__ . '/../controllers/NotificationController.php';
?>

<!-- ========================================
     ajax/get_notifications.php - NOVO
     ======================================== -->
<?php
/**
 * BUSCAR NOTIFICAÇÕES - Redireciona para NotificationController
 */
require_once __DIR__ . '/../controllers/NotificationController.php';
?>

<!-- ========================================
     INSTRUCÕES DE USO:
     
     Todos os arquivos ajax/ agora apenas redirecionam para os controllers apropriados.
     Os controllers centralizam TODA a lógica.
     
     CHAMADAS AJAX DEVEM SER FEITAS DIRETAMENTE PARA OS CONTROLLERS:
     
     Tickets:
     - BASE_URL + 'controllers/TicketController.php?action=assume'
     - BASE_URL + 'controllers/TicketController.php?action=dispatch'
     - BASE_URL + 'controllers/TicketController.php?action=resolve'
     - BASE_URL + 'controllers/TicketController.php?action=close'
     - BASE_URL + 'controllers/TicketController.php?action=reopen'
     - BASE_URL + 'controllers/TicketController.php?action=add_comment'
     
     Notificações:
     - BASE_URL + 'controllers/NotificationController.php?action=get'
     - BASE_URL + 'controllers/NotificationController.php?action=mark_read'
     - BASE_URL + 'controllers/NotificationController.php?action=mark_all_read'
     - BASE_URL + 'controllers/NotificationController.php?action=count_unread'
     
     ======================================== -->