<?php
/**
 * AJAX: Cancelar Ordem de Serviço
 * Apenas admin, atendente que criou, ou técnico principal podem cancelar
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
$reason = sanitize($_POST['reason'] ?? 'Cancelada pelo usuário');

if (!$workOrderId) {
    jsonResponse(['success' => false, 'message' => 'OS inválida'], 400);
}

if (empty($reason)) {
    jsonResponse(['success' => false, 'message' => 'Informe o motivo do cancelamento'], 400);
}

try {
    $workOrder = new WorkOrder();
    $userId = getCurrentUserId();
    $userRole = getCurrentUserRole();
    
    // Buscar dados da OS
    $woData = $workOrder->getById($workOrderId);
    
    if (!$woData) {
        jsonResponse(['success' => false, 'message' => 'OS não encontrada'], 404);
    }
    
    // Verificar se já foi concluída
    if ($woData['status'] === 'completed') {
        jsonResponse(['success' => false, 'message' => 'Não é possível cancelar OS já concluída'], 400);
    }
    
    // Verificar permissão
    $canCancel = false;
    
    // Admin pode sempre cancelar
    if ($userRole === 'admin') {
        $canCancel = true;
    }
    // Atendente que criou pode cancelar
    elseif ($woData['created_by'] == $userId) {
        $canCancel = true;
    }
    // Técnico principal pode cancelar
    else {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT role FROM work_order_technicians 
            WHERE work_order_id = ? AND technician_id = ? AND role = 'primary'
        ");
        $stmt->execute([$workOrderId, $userId]);
        if ($stmt->fetch()) {
            $canCancel = true;
        }
    }
    
    if (!$canCancel) {
        jsonResponse(['success' => false, 'message' => 'Você não tem permissão para cancelar esta OS'], 403);
    }
    
    // Cancelar
    $result = $workOrder->cancel($workOrderId, $userId, $reason);
    
    if ($result['success']) {
        // Notificar todos os envolvidos
        $pdo = getConnection();
        
        // Buscar técnicos da OS
        $stmt = $pdo->prepare("
            SELECT technician_id FROM work_order_technicians 
            WHERE work_order_id = ?
        ");
        $stmt->execute([$workOrderId]);
        $technicians = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Notificar cada técnico
        $notification = new Notification();
        foreach ($technicians as $techId) {
            $notification->create([
                'user_id' => $techId,
                'ticket_id' => $woData['ticket_id'],
                'type' => 'work_order_cancelled',
                'message' => "OS #" . formatWorkOrderCode($workOrderId) . " foi cancelada. Motivo: {$reason}"
            ]);
        }
        
        // Notificar empresa
        $notification->create([
            'company_id' => $woData['company_id'],
            'ticket_id' => $woData['ticket_id'],
            'type' => 'work_order_cancelled',
            'message' => "OS #" . formatWorkOrderCode($workOrderId) . " foi cancelada"
        ]);
        
        jsonResponse($result, 200);
    } else {
        jsonResponse($result, 400);
    }
    
} catch (Exception $e) {
    logSystem("Erro no AJAX cancel_work_order: " . $e->getMessage(), "ERROR");
    jsonResponse([
        'success' => false,
        'message' => 'Erro ao cancelar OS. Tente novamente.'
    ], 500);
}
?>