<?php
/**
 * SISTEMA DE SUPORTE E MANUTENÇÃO
 * Arquivo: ajax/close_ticket.php
 * Descrição: Finalizar ticket via AJAX
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

$ticket_id = (int)$data['ticket_id'];
$user_id = $_SESSION['user_id'];
$notes = isset($data['notes']) ? trim($data['notes']) : '';

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
    
    // Verificar se ticket pode ser fechado
    $allowedStatuses = ['assumed', 'dispatched', 'in_progress', 'resolved'];
    if (!in_array($ticketInfo['status'], $allowedStatuses)) {
        echo json_encode([
            'success' => false,
            'message' => 'Ticket não pode ser fechado neste status'
        ]);
        exit;
    }
    
    // Iniciar transação
    $db->beginTransaction();
    
    // Atualizar status para 'closed'
    $ticket->updateStatus($ticket_id, 'closed', $user_id);
    
    // Registrar finalização
    $closedTime = date('Y-m-d H:i:s');
    $stmt = $db->prepare("
        UPDATE tickets 
        SET closed_at = :closed_at,
            closed_by = :user_id,
            resolution_notes = :notes
        WHERE id = :ticket_id
    ");
    $stmt->execute([
        ':closed_at' => $closedTime,
        ':user_id' => $user_id,
        ':notes' => $notes,
        ':ticket_id' => $ticket_id
    ]);
    
    // Registrar log
    $logDescription = 'Ticket finalizado por ' . $_SESSION['user_name'];
    if (!empty($notes)) {
        $logDescription .= '. Observações: ' . $notes;
    }
    
    $log->create([
        'ticket_id' => $ticket_id,
        'user_id' => $user_id,
        'action' => 'close',
        'description' => $logDescription
    ]);
    
    // Notificar solicitante
    $message = "Seu ticket #{$ticket_id} foi encerrado. Agradecemos pela sua paciência! Por favor, avalie nosso atendimento.";
    $notification->create([
        'user_id' => $ticketInfo['created_by'],
        'ticket_id' => $ticket_id,
        'type' => 'ticket_closed',
        'message' => $message
    ]);
    
    // Commit
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Ticket finalizado com sucesso',
        'data' => [
            'ticket_id' => $ticket_id,
            'status' => 'closed',
            'closed_at' => $closedTime,
            'closed_by' => $user_id
        ]
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Erro ao finalizar ticket: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar requisição. Tente novamente.'
    ]);
}
?>