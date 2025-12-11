<?php
/**
 * AJAX: Concluir Ordem de Serviço
 * Técnico marca sua parte como concluída
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

// Apenas usuários (técnicos)
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

// Validar dados
$workOrderId = (int)($_POST['work_order_id'] ?? 0);
$notes = sanitize($_POST['notes'] ?? '');

if (!$workOrderId) {
    jsonResponse(['success' => false, 'message' => 'OS inválida'], 400);
}

// Validar tamanho das notas
if (strlen($notes) > WORK_ORDER_LIMITS['max_notes_length']) {
    jsonResponse([
        'success' => false, 
        'message' => 'Observações muito longas (máximo: ' . WORK_ORDER_LIMITS['max_notes_length'] . ' caracteres)'
    ], 400);
}

try {
    $workOrder = new WorkOrder();
    $technicianId = getCurrentUserId();
    
    // Verificar se técnico está na OS
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM work_order_technicians 
        WHERE work_order_id = ? AND technician_id = ?
    ");
    $stmt->execute([$workOrderId, $technicianId]);
    
    if ($stmt->fetchColumn() == 0) {
        jsonResponse(['success' => false, 'message' => 'Você não está nesta OS'], 403);
    }
    
    // Concluir
    $result = $workOrder->completeTechnician($workOrderId, $technicianId, $notes);
    
    if ($result['success']) {
        jsonResponse($result, 200);
    } else {
        jsonResponse($result, 400);
    }
    
} catch (Exception $e) {
    logSystem("Erro no AJAX complete_work_order: " . $e->getMessage(), "ERROR");
    jsonResponse([
        'success' => false,
        'message' => 'Erro ao concluir OS. Tente novamente.'
    ], 500);
}
?>