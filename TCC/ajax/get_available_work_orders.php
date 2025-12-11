<?php
/**
 * AJAX: Listar Ordens de Serviço Disponíveis
 * Técnicos veem OS que podem aceitar
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

// Apenas GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
}

try {
    $workOrder = new WorkOrder();
    
    // Parâmetros opcionais
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $limit = min($limit, 100); // Máximo 100
    
    // Buscar OS disponíveis
    $availableOrders = $workOrder->getAvailable($limit);
    
    // Formatar dados para JSON
    $formatted = [];
    foreach ($availableOrders as $wo) {
        $formatted[] = [
            'id' => $wo['id'],
            'code' => formatWorkOrderCode($wo['id']),
            'ticket_id' => $wo['ticket_id'],
            'ticket_title' => $wo['ticket_title'],
            'priority' => $wo['priority'],
            'priority_label' => getPriorityLabel($wo['priority']),
            'priority_color' => getPriorityColor($wo['priority']),
            'company_name' => $wo['company_name'],
            'company_address' => $wo['company_address'],
            'company_phone' => $wo['company_phone'],
            'deadline' => $wo['deadline'],
            'deadline_formatted' => formatDate($wo['deadline'], true),
            'time_remaining' => getWorkOrderTimeRemaining($wo['deadline']),
            'is_overdue' => isWorkOrderOverdue($wo['deadline'], $wo['status']),
            'created_at' => $wo['created_at'],
            'created_at_formatted' => formatDate($wo['created_at'], true),
            'elapsed_time' => timeElapsed($wo['created_at'])
        ];
    }
    
    jsonResponse([
        'success' => true,
        'count' => count($formatted),
        'work_orders' => $formatted
    ], 200);
    
} catch (Exception $e) {
    logSystem("Erro no AJAX get_available_work_orders: " . $e->getMessage(), "ERROR");
    jsonResponse([
        'success' => false,
        'message' => 'Erro ao buscar OS disponíveis.'
    ], 500);
}
?>