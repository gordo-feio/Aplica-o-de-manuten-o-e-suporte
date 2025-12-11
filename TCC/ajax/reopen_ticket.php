<?php
/**
 * SISTEMA DE SUPORTE E MANUTENÇÃO
 * Arquivo: ajax/reopen_ticket.php
 * Descrição: Reabrir ticket via AJAX
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

if (!isset($data['reason']) || empty(trim($data['reason']))) {
    echo json_encode([
        'success' => false,
        'message' => 'Motivo da reabertura é obrigatório'
    ]);
    exit;
}

$ticket_id = (int)$data['ticket_id'];
$reason = trim($data['reason']);
$user_id = $_SESSION['user_id'];

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
    
    // Verificar se ticket está fechado ou resolvido
    if (!in_array($ticketInfo['status'], ['closed', 'resolved'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Apenas tickets fechados ou resolvidos podem ser reabertos'
        ]);
        exit;
    }
    
    // Iniciar transação
    $db->beginTransaction();
    
    // Atualizar status para 'reopened'
    $ticket->updateStatus($ticket_id, 'reopened', $user_id);
    
    // Registrar reabertura
    $reopenedTime = date('Y-m-d H:i:s');
    $stmt = $db->prepare("
        UPDATE tickets 
        SET reopened_at = :reopened_at,
            reopened_by = :user_id,
            reopen_reason = :reason
        WHERE id = :ticket_id
    ");
    $stmt->execute([
        ':reopened_at' => $reopenedTime,
        ':user_id' => $user_id,
        ':reason' => $reason,
        ':ticket_id' => $ticket_id
    ]);
    
    // Registrar log
    $logDescription = 'Ticket reaberto por ' . $_SESSION['user_name'] . '. Motivo: ' . $reason;
    
    $log->create([
        'ticket_id' => $ticket_id,
        'user_id' => $user_id,
        'action' => 'reopen',
        'description' => $logDescription
    ]);
    
    // Notificar equipe responsável
    if ($ticketInfo['assigned_to']) {
        $message = "O ticket #{$ticket_id} foi reaberto. Motivo: {$reason}";
        $notification->create([
            'user_id' => $ticketInfo['assigned_to'],
            'ticket_id' => $ticket_id,
            'type' => 'ticket_reopened',
            'message' => $message
        ]);
    }
    
    // Commit
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Ticket reaberto com sucesso',
        'data' => [
            'ticket_id' => $ticket_id,
            'status' => 'reopened',
            'reopened_at' => $reopenedTime
        ]
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Erro ao reabrir ticket: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar requisição. Tente novamente.'
    ]);
}
?>