<?php
/**
 * AJAX: Listar Técnicos Disponíveis
 * Retorna técnicos ativos que podem ser adicionados a uma OS
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar se está logado
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Não autenticado'], 401);
}

// Apenas usuários
if (!isUser()) {
    jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
}

// Apenas GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
}

try {
    $pdo = getConnection();
    
    // Parâmetros opcionais
    $workOrderId = isset($_GET['work_order_id']) ? (int)$_GET['work_order_id'] : null;
    $excludeInWorkOrder = isset($_GET['exclude_in_os']) && $_GET['exclude_in_os'] === 'true';
    
    // Query base: buscar técnicos ativos
    $sql = "
        SELECT 
            u.id,
            u.name,
            u.email,
            u.phone,
            u.role,
            (SELECT COUNT(*) 
             FROM work_order_technicians wot 
             INNER JOIN work_orders wo ON wot.work_order_id = wo.id 
             WHERE wot.technician_id = u.id 
             AND wo.status IN ('available', 'in_progress')) as active_os_count,
            (SELECT COUNT(*) 
             FROM work_order_technicians wot 
             INNER JOIN work_orders wo ON wot.work_order_id = wo.id 
             WHERE wot.technician_id = u.id 
             AND wo.status = 'completed') as completed_os_count
        FROM users u
        WHERE u.is_active = 1 
        AND u.role = 'technician'
    ";
    
    // Se deve excluir técnicos já na OS
    if ($workOrderId && $excludeInWorkOrder) {
        $sql .= " AND u.id NOT IN (
            SELECT technician_id 
            FROM work_order_technicians 
            WHERE work_order_id = ?
        )";
        $params = [$workOrderId];
    } else {
        $params = [];
    }
    
    $sql .= " ORDER BY u.name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $technicians = $stmt->fetchAll();
    
    // Formatar dados
    $formatted = [];
    foreach ($technicians as $tech) {
        $formatted[] = [
            'id' => $tech['id'],
            'name' => $tech['name'],
            'email' => $tech['email'],
            'phone' => $tech['phone'],
            'role' => $tech['role'],
            'role_label' => getRoleLabel($tech['role']),
            'active_os_count' => (int)$tech['active_os_count'],
            'completed_os_count' => (int)$tech['completed_os_count'],
            'is_available' => (int)$tech['active_os_count'] < 5, // Máximo 5 OS ativas por técnico
            'workload' => (int)$tech['active_os_count'], // Para ordenar por carga de trabalho
            'experience' => (int)$tech['completed_os_count'] // Para mostrar experiência
        ];
    }
    
    // Ordenar por disponibilidade (menos OS ativas primeiro)
    usort($formatted, function($a, $b) {
        // Primeiro, técnicos disponíveis
        if ($a['is_available'] != $b['is_available']) {
            return $b['is_available'] - $a['is_available'];
        }
        // Depois, por menor carga de trabalho
        if ($a['workload'] != $b['workload']) {
            return $a['workload'] - $b['workload'];
        }
        // Por último, por nome
        return strcmp($a['name'], $b['name']);
    });
    
    // Estatísticas gerais
    $totalTechnicians = count($formatted);
    $availableTechnicians = count(array_filter($formatted, fn($t) => $t['is_available']));
    $busyTechnicians = $totalTechnicians - $availableTechnicians;
    
    jsonResponse([
        'success' => true,
        'count' => $totalTechnicians,
        'technicians' => $formatted,
        'stats' => [
            'total' => $totalTechnicians,
            'available' => $availableTechnicians,
            'busy' => $busyTechnicians
        ]
    ], 200);
    
} catch (Exception $e) {
    logSystem("Erro no AJAX get_available_technicians: " . $e->getMessage(), "ERROR");
    jsonResponse([
        'success' => false,
        'message' => 'Erro ao buscar técnicos disponíveis.',
        'error' => ENVIRONMENT === 'development' ? $e->getMessage() : null
    ], 500);
}
?>