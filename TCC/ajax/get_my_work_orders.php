<?php
/**
 * AJAX: Obter Minhas Ordens de Serviço
 * Técnico vê as OS que aceitou (como primary ou support)
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
    $technicianId = getCurrentUserId();
    
    // Filtro de status (opcional)
    $status = isset($_GET['status']) ? sanitize($_GET['status']) : null;
    
    // Validar status se fornecido
    if ($status && !in_array($status, ['available', 'in_progress', 'completed', 'cancelled'])) {
        jsonResponse(['success' => false, 'message' => 'Status inválido'], 400);
    }
    
    // Buscar OS do técnico
    $myOrders = $workOrder->getMyOrders($technicianId, $status);
    
    // Formatar dados
    $formatted = [];
    foreach ($myOrders as $wo) {
        // Buscar papel do técnico nesta OS
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT role, status as tech_status, notes 
            FROM work_order_technicians 
            WHERE work_order_id = ? AND technician_id = ?
        ");
        $stmt->execute([$wo['id'], $technicianId]);
        $techData = $stmt->fetch();
        
        $formatted[] = [
            'id' => $wo['id'],
            'code' => formatWorkOrderCode($wo['id']),
            'ticket_id' => $wo['ticket_id'],
            'ticket_title' => $wo['ticket_title'],
            'status' => $wo['status'],
            'status_label' => getWorkOrderStatusLabel($wo['status']),
            'status_color' => getWorkOrderStatusColor($wo['status']),
            'status_icon' => getWorkOrderStatusIcon($wo['status']),
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
            'my_role' => $techData['role'] ?? null,
            'my_role_label' => isset($techData['role']) ? getTechnicianRoleLabel($techData['role']) : null,
            'my_status' => $techData['tech_status'] ?? null,
            'my_status_label' => isset($techData['tech_status']) ? getTechnicianStatusLabel($techData['tech_status']) : null,
            'my_notes' => $techData['notes'] ?? null,
            'is_primary' => isset($techData['role']) && $techData['role'] === 'primary',
            'is_completed' => isset($techData['tech_status']) && $techData['tech_status'] === 'completed'
        ];
    }
    
    // Estatísticas
    $stats = [
        'total' => count($formatted),
        'in_progress' => count(array_filter($formatted, fn($wo) => $wo['status'] === 'in_progress')),
        'completed' => count(array_filter($formatted, fn($wo) => $wo['status'] === 'completed')),
        'overdue' => count(array_filter($formatted, fn($wo) => $wo['is_overdue'])),
        'as_primary' => count(array_filter($formatted, fn($wo) => $wo['is_primary'])),
        'as_support' => count(array_filter($formatted, fn($wo) => !$wo['is_primary']))
    ];
    
    jsonResponse([
        'success' => true,
        'count' => count($formatted),
        'work_orders' => $formatted,
        'stats' => $stats
    ], 200);
    
} catch (Exception $e) {
    logSystem("Erro no AJAX get_my_work_orders: " . $e->getMessage(), "ERROR");
    jsonResponse([
        'success' => false,
        'message' => 'Erro ao buscar suas OS.'
    ], 500);
}
?>