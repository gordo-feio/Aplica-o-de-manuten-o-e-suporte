<?php
/**
 * ASSUMIR TICKET - AJAX (VERSÃO CORRIGIDA FINAL)
 * Sistema de Suporte e Manutenção
 */

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir configurações
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

// Definir cabeçalho JSON
header('Content-Type: application/json; charset=utf-8');

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não autenticado'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Obter dados (suporta JSON e POST normal)
$ticket_id = null;

// Tentar JSON primeiro
$json = file_get_contents('php://input');
if (!empty($json)) {
    $data = json_decode($json, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($data['ticket_id'])) {
        $ticket_id = $data['ticket_id'];
    }
}

// Se não veio JSON, tentar POST normal
if ($ticket_id === null && isset($_POST['ticket_id'])) {
    $ticket_id = $_POST['ticket_id'];
}

// Validar ticket_id
if (!$ticket_id || !is_numeric($ticket_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID do ticket inválido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$ticket_id = (int)$ticket_id;
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Atendente';

try {
    // Obter conexão com banco
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // Buscar ticket
    $stmt = $db->prepare("SELECT * FROM tickets WHERE id = :id");
    $stmt->execute([':id' => $ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Ticket não encontrado'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Verificar se já foi assumido
    if ($ticket['status'] !== 'created' && $ticket['status'] !== 'reopened') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Este ticket já foi assumido ou está em outro status'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Iniciar transação
    $db->beginTransaction();
    
    // Atualizar ticket
    $stmt = $db->prepare("
        UPDATE tickets 
        SET status = 'assumed',
            assigned_user_id = :user_id,
            assumed_at = NOW(),
            updated_at = NOW()
        WHERE id = :ticket_id
    ");
    $stmt->execute([
        ':user_id' => $user_id,
        ':ticket_id' => $ticket_id
    ]);
    
    // Criar log
    $stmt = $db->prepare("
        INSERT INTO ticket_logs (ticket_id, user_id, action, old_status, new_status, description, created_at)
        VALUES (:ticket_id, :user_id, 'ASSUMED', :old_status, 'assumed', :description, NOW())
    ");
    $stmt->execute([
        ':ticket_id' => $ticket_id,
        ':user_id' => $user_id,
        ':old_status' => $ticket['status'],
        ':description' => 'Ticket assumido por ' . $user_name
    ]);
    
    // Criar notificação para a empresa
    $stmt = $db->prepare("
        INSERT INTO notifications (ticket_id, company_id, user_id, type, message, created_at, is_read)
        VALUES (:ticket_id, :company_id, :user_id, 'assumed', :message, NOW(), 0)
    ");
    $stmt->execute([
        ':ticket_id' => $ticket_id,
        ':company_id' => $ticket['company_id'],
        ':user_id' => $user_id,
        ':message' => 'Seu ticket #' . $ticket_id . ' está sendo atendido por ' . $user_name
    ]);
    
    // Commit
    $db->commit();
    
    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Ticket assumido com sucesso! 🎉',
        'data' => [
            'ticket_id' => $ticket_id,
            'status' => 'assumed',
            'assigned_user' => $user_name,
            'assigned_user_id' => $user_id
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Log do erro
    error_log("Erro ao assumir ticket #$ticket_id: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar requisição. Por favor, tente novamente.',
        'debug' => ENVIRONMENT === 'development' ? $e->getMessage() : null
    ], JSON_UNESCAPED_UNICODE);
}
?>