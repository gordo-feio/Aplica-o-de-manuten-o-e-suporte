<?php
/**
 * AJAX: Listar TODAS as Ordens de Serviço
 * Apenas Admin e Atendentes podem ver todas as OS do sistema
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar se está logado
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Não autenticado'], 401);
}

// Apenas usuários (admin e atendentes)
if (!isUser()) {
    jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
}

// Verificar se é admin ou atendente
if (!isAdmin() && !isAttendant()) {
    jsonResponse(['success' => false, 'message' => 'Apenas admin e atendentes podem acessar'], 403);
}

// Apenas GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
}

try {
    $pdo = getConnection();
    
    // Parâmetros de filtro
    $status = isset($_GET['status']) ? sanitize($_GET['status']) : null;
    $priority = isset($_GET['priority']) ? sanitize($_GET['priority']) : null;
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    // Construir query
    $sql = "
        SELECT 
            wo.*,
            t.title as ticket_title,
            t.description as ticket_description,
            t.priority as ticket_priority,
            c.name as company_name,
            c.address as company_address,
            c.phone as company_phone,
            c.email as company_email,
            u.name as created_by_name,
            (SELECT COUNT(*) FROM work_order_technicians wot WHERE wot.work_order_id = wo.id) as technician_count,
            (SELECT GROUP_CONCAT(users.name SEPARATOR ', ') 
             FROM work_order_technicians wot2 
             INNER JOIN users ON wot2.technician_id = users.id 
             WHERE wot2.work_order_id = wo.id) as technician_names
        FROM work_orders wo
        INNER JOIN tickets t ON wo.ticket_id = t.id
        INNER JOIN companies c ON t.company_id = c.id
        LEFT JOIN users u ON wo.created_by = u.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Aplicar filtros
    if ($status) {
        $sql .= " AND wo.status = ?";
        $params[] = $status;
    }
    
    if ($priority) {
        $sql .= " AND wo.priority = ?";
        $params[] = $priority;
    }
    
    if ($search) {
        $sql .= " AND (
            t.title LIKE ? OR 
            c.name LIKE ? OR 
            wo.id LIKE ?
        )";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Ordenação: Prioridade > Data de criação
    $sql .= " 
        ORDER BY 
            CASE wo.status 
                WHEN 'available' THEN 1 
                WHEN 'in_progress' THEN 2 
                WHEN 'completed' THEN 3 
                ELSE 4 
            END,
            CASE wo.priority 
                WHEN 'high' THEN 1 
                WHEN 'medium' THEN 2 
                ELSE 3 
            END,
            wo.created_at DESC
    ";
    
    // Paginação
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    // Executar query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    // Formatar dados
    $formatted = [];
    foreach ($orders as $wo) {
        $formatted[] = [
            'id' => $wo['id'],
            'code' => formatWorkOrderCode($wo['id']),
            'ticket_id' => $wo['ticket_id'],
            'ticket_title' => $wo['ticket_title'],
            'ticket_description' => $wo['ticket_description'],
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
            'company_email' => $wo['company_email'],
            'deadline' => $wo['deadline'],
            'deadline_formatted' => formatDate($wo['deadline'], true),
            'time_remaining' => getWorkOrderTimeRemaining($wo['deadline']),
            'is_overdue' => isWorkOrderOverdue($wo['deadline'], $wo['status']),
            'created_at' => $wo['created_at'],
            'created_at_formatted' => formatDate($wo['created_at'], true),
            'elapsed_time' => timeElapsed($wo['created_at']),
            'completed_at' => $wo['completed_at'],
            'completed_at_formatted' => $wo['completed_at'] ? formatDate($wo['completed_at'], true) : null,
            'created_by_name' => $wo['created_by_name'],
            'technician_count' => (int)$wo['technician_count'],
            'technician_names' => $wo['technician_names']
        ];
    }
    
    // Contar total (para paginação)
    $countSql = "
        SELECT COUNT(*) 
        FROM work_orders wo
        INNER JOIN tickets t ON wo.ticket_id = t.id
        INNER JOIN companies c ON t.company_id = c.id
        WHERE 1=1
    ";
    
    $countParams = [];
    
    if ($status) {
        $countSql .= " AND wo.status = ?";
        $countParams[] = $status;
    }
    
    if ($priority) {
        $countSql .= " AND wo.priority = ?";
        $countParams[] = $priority;
    }
    
    if ($search) {
        $countSql .= " AND (
            t.title LIKE ? OR 
            c.name LIKE ? OR 
            wo.id LIKE ?
        )";
        $searchTerm = "%{$search}%";
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
    }
    
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($countParams);
    $total = (int)$stmtCount->fetchColumn();
    
    // Estatísticas gerais
    $stats = [
        'total' => $total,
        'available' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'cancelled' => 0,
        'overdue' => 0
    ];
    
    // Contar por status
    $statsSql = "
        SELECT 
            status,
            COUNT(*) as count
        FROM work_orders
        GROUP BY status
    ";
    $statsStmt = $pdo->query($statsSql);
    $statsData = $statsStmt->fetchAll();
    
    foreach ($statsData as $stat) {
        $stats[$stat['status']] = (int)$stat['count'];
    }
    
    // Contar atrasadas
    $overdueStmt = $pdo->query("
        SELECT COUNT(*) 
        FROM work_orders 
        WHERE status = 'in_progress' 
        AND deadline < NOW()
    ");
    $stats['overdue'] = (int)$overdueStmt->fetchColumn();
    
    jsonResponse([
        'success' => true,
        'count' => count($formatted),
        'total' => $total,
        'work_orders' => $formatted,
        'stats' => $stats,
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total
        ]
    ], 200);
    
} catch (Exception $e) {
    logSystem("Erro no AJAX get_all_work_orders: " . $e->getMessage(), "ERROR");
    jsonResponse([
        'success' => false,
        'message' => 'Erro ao buscar ordens de serviço.',
        'error' => ENVIRONMENT === 'development' ? $e->getMessage() : null
    ], 500);
}
?>