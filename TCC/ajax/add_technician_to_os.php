<?php
/**
 * AJAX: Adicionar Técnico à OS
 * Técnico principal, atendente ou admin podem adicionar suporte
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

// Apenas usuários
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
$technicianId = (int)($_POST['technician_id'] ?? 0);

if (!$workOrderId || !$technicianId) {
    jsonResponse(['success' => false, 'message' => 'Dados inválidos'], 400);
}

try {
    $workOrder = new WorkOrder();
    $requesterId = getCurrentUserId();
    
    $result = $workOrder->addTechnician($workOrderId, $technicianId, $requesterId);
    
    if ($result['success']) {
        jsonResponse($result, 200);
    } else {
        jsonResponse($result, 400);
    }
    
} catch (Exception $e) {
    logSystem("Erro no AJAX add_technician: " . $e->getMessage(), "ERROR");
    jsonResponse([
        'success' => false,
        'message' => 'Erro ao adicionar técnico. Tente novamente.'
    ], 500);
}
?>