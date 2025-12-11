<?php
/**
 * AJAX: Criar Ordem de Serviço
 * Apenas atendentes e admins podem criar OS
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/WorkOrder.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar se está logado
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Não autenticado'], 401);
}

// Apenas usuários (não empresas)
if (!isUser()) {
    jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
}

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
}

// Verificar CSRF
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    jsonResponse(['success' => false, 'message' => 'Token de segurança inválido'], 403);
}

// Validar ticket_id
$ticketId = (int)($_POST['ticket_id'] ?? 0);

if (!$ticketId) {
    jsonResponse(['success' => false, 'message' => 'Ticket inválido'], 400);
}

try {
    // Criar OS
    $workOrder = new WorkOrder();
    $userId = getCurrentUserId();
    
    $result = $workOrder->create($ticketId, $userId);
    
    if ($result['success']) {
        jsonResponse($result, 200);
    } else {
        jsonResponse($result, 400);
    }
    
} catch (Exception $e) {
    logSystem("Erro no AJAX create_work_order: " . $e->getMessage(), "ERROR");
    jsonResponse([
        'success' => false,
        'message' => 'Erro ao criar OS. Tente novamente.'
    ], 500);
}
?>