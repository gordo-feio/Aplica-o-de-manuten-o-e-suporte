<?php
/**
 * AJAX: Obter Detalhes Completos da Ordem de Serviço
 * Inclui dados da OS, equipe, logs e ticket relacionado
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

// Apenas GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
}

// Validar ID
$workOrderId = (int)($_GET['id'] ?? 0);

if (!$workOrderId) {
    jsonResponse(['success' => false, 'message' => 'OS inválida'], 400);
}

try {
    $workOrder = new WorkOrder();
    
    // Buscar dados da OS
    $woData = $workOrder->getById($workOrderId);
    
    if (!$woData) {
        jsonResponse(['success' => false, 'message' => 'OS não encontrada'], 404);
    }
    
    // Verificar permissão de acesso
    if (isCompany()) {
        // Empresa só pode ver suas próprias OS
        if ($woData['company_id'] != getCurrentCompanyId()) {
            jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
        }
    }
    
    // Buscar equipe
    $team = $workOrder->getTeam($workOrderId);
    
    // Buscar logs
    $logs = $workOrder->getLogs($workOrderId);
    
    // Formatar dados da OS
    $formatted = [
        'id' => $woData['id'],
        'code' => formatWorkOrderCode($woData['id']),
        'ticket_id' => $woData['ticket_id'],
        'ticket_title' => $woData['ticket_title'],
        'ticket_description' => $woData['ticket_description'],
        'status' => $woData['status'],
        'status_label' => getWorkOrderStatusLabel($woData['status']),
        'status_color' => getWorkOrderStatusColor($woData['status']),
        'status_icon' => getWorkOrderStatusIcon($woData['status']),
        'priority' => $woData['priority'],
        'priority_label' => getPriorityLabel($woData['priority']),
        'priority_color' => getPriorityColor($woData['priority']),
        'company_name' => $woData['company_name'],
        'company_address' => $woData['company_address'],
        'company_phone' => $woData['company_phone'],
        'company_email' => $woData['company_email'],
        'deadline' => $woData['deadline'],
        'deadline_formatted' => formatDate($woData['deadline'], true),
        'time_remaining' => getWorkOrderTimeRemaining($woData['deadline']),
        'is_overdue' => isWorkOrderOverdue($woData['deadline'], $woData['status']),
        'created_at' => $woData['created_at'],
        'created_at_formatted' => formatDate($woData['created_at'], true),
        'completed_at' => $woData['completed_at'],
        'completed_at_formatted' => $woData['completed_at'] ? formatDate($woData['completed_at'], true) : null,
        'created_by_name' => $woData['created_by_name']
    ];
    
    // Formatar equipe
    $formattedTeam = [];
    foreach ($team as $member) {
        $formattedTeam[] = [
            'id' => $member['id'],
            'technician_id' => $member['technician_id'],
            'technician_name' => $member['technician_name'],
            'technician_email' => $member['technician_email'],
            'technician_phone' => $member['technician_phone'],
            'role' => $member['role'],
            'role_label' => getTechnicianRoleLabel($member['role']),
            'role_color' => getTechnicianRoleColor($member['role']),
            'role_icon' => getTechnicianRoleIcon($member['role']),
            'status' => $member['status'],
            'status_label' => getTechnicianStatusLabel($member['status']),
            'status_color' => getTechnicianStatusColor($member['status']),
            'accepted_at' => $member['accepted_at'],
            'accepted_at_formatted' => formatDate($member['accepted_at'], true),
            'completed_at' => $member['completed_at'],
            'completed_at_formatted' => $member['completed_at'] ? formatDate($member['completed_at'], true) : null,
            'notes' => $member['notes']
        ];
    }
    
    // Formatar logs
    $formattedLogs = [];
    foreach ($logs as $log) {
        $formattedLogs[] = [
            'id' => $log['id'],
            'action' => $log['action'],
            'action_label' => WORK_ORDER_ACTIONS[$log['action']] ?? $log['action'],
            'description' => $log['description'],
            'user_name' => $log['user_name'] ?? 'Sistema',
            'created_at' => $log['created_at'],
            'created_at_formatted' => formatDate($log['created_at'], true),
            'elapsed_time' => timeElapsed($log['created_at'])
        ];
    }
    
    jsonResponse([
        'success' => true,
        'work_order' => $formatted,
        'team' => $formattedTeam,
        'logs' => $formattedLogs,
        'team_count' => count($formattedTeam),
        'can_add_technician' => count($formattedTeam) < WORK_ORDER_LIMITS['max_technicians']
    ], 200);
    
} catch (Exception $e) {
    logSystem("Erro no AJAX get_work_order_details: " . $e->getMessage(), "ERROR");
    jsonResponse([
        'success' => false,
        'message' => 'Erro ao buscar detalhes da OS.'
    ], 500);
}
?>