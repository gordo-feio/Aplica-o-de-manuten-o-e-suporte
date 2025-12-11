<?php
/**
 * SISTEMA DE SUPORTE E MANUTENÇÃO
 * Arquivo: ajax/add_comment.php
 * Descrição: Adicionar comentário em ticket via AJAX
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Ticket.php';
require_once __DIR__ . '/../classes/Notification.php';
require_once __DIR__ . '/../classes/Log.php';

// Definir cabeçalho JSON
header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não autenticado'
    ]);
    exit;
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido'
    ]);
    exit;
}

// Obter dados
$data = json_decode(file_get_contents('php://input'), true);

// Validar
if (!isset($data['ticket_id']) || !is_numeric($data['ticket_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID do ticket inválido'
    ]);
    exit;
}

if (!isset($data['comment']) || empty(trim($data['comment']))) {
    echo json_encode([
        'success' => false,
        'message' => 'Comentário não pode estar vazio'
    ]);
    exit;
}

$ticket_id = (int)$data['ticket_id'];
$comment = trim($data['comment']);
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $ticket = new Ticket($db);
    $notification = new Notification($db);
    $log = new Log($db);
    
    // Buscar ticket
    $ticketInfo = $ticket->getById($ticket_id);
    
    if (!$ticketInfo) {
        echo json_encode([
            'success' => false,
            'message' => 'Ticket não encontrado'
        ]);
        exit;
    }
    
    // Iniciar transação
    $db->beginTransaction();
    
    // Inserir comentário
    $stmt = $db->prepare("
        INSERT INTO ticket_comments 
        (ticket_id, user_id, comment, created_at)
        VALUES 
        (:ticket_id, :user_id, :comment, NOW())
    ");
    
    $stmt->execute([
        ':ticket_id' => $ticket_id,
        ':user_id' => $user_id,
        ':comment' => $comment
    ]);
    
    $comment_id = $db->lastInsertId();
    
    // Registrar log
    $log->create([
        'ticket_id' => $ticket_id,
        'user_id' => $user_id,
        'action' => 'comment',
        'description' => "{$user_name} adicionou um comentário"
    ]);
    
    // Notificar partes interessadas
    $notify_users = [];
    
    // Notificar solicitante (se não for ele mesmo)
    if ($ticketInfo['created_by'] != $user_id) {
        $notify_users[] = $ticketInfo['created_by'];
    }
    
    // Notificar atendente (se houver e não for ele mesmo)
    if ($ticketInfo['assigned_to'] && $ticketInfo['assigned_to'] != $user_id) {
        $notify_users[] = $ticketInfo['assigned_to'];
    }
    
    // Enviar notificações
    foreach (array_unique($notify_users) as $notify_user_id) {
        $message = "{$user_name} comentou no ticket #{$ticket_id}";
        $notification->create([
            'user_id' => $notify_user_id,
            'ticket_id' => $ticket_id,
            'type' => 'comment_added',
            'message' => $message
        ]);
    }
    
    // Commit
    $db->commit();
    
    // Buscar comentário recém criado com dados do usuário
    $stmt = $db->prepare("
        SELECT 
            c.*,
            u.name as user_name
        FROM ticket_comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = :comment_id
    ");
    $stmt->execute([':comment_id' => $comment_id]);
    $newComment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Comentário adicionado com sucesso',
        'comment' => [
            'id' => (int)$newComment['id'],
            'user_name' => $newComment['user_name'],
            'comment' => $newComment['comment'],
            'created_at' => $newComment['created_at']
        ]
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Erro ao adicionar comentário: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar requisição. Tente novamente.'
    ]);
}
?>