<?php
/**
 * Funções Auxiliares do Sistema - VERSÃO COMPLETA COM OS
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 */

// =====================================================
// FUNÇÕES DE AUTENTICAÇÃO E CONTROLE DE ACESSO
// =====================================================

/**
 * Verificar se usuário está logado
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) || isset($_SESSION['company_id']);
}

/**
 * Verificar se é usuário (funcionário)
 * @return bool
 */
function isUser() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Verificar se é empresa
 * @return bool
 */
function isCompany() {
    return isset($_SESSION['company_id']);
}

/**
 * Verificar se é admin
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Verificar se é técnico
 * @return bool
 */
function isTechnician() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'technician';
}

/**
 * Verificar se é atendente
 * @return bool
 */
function isAttendant() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'attendant';
}

/**
 * Obter ID do usuário logado
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Obter ID da empresa logada
 * @return int|null
 */
function getCurrentCompanyId() {
    return $_SESSION['company_id'] ?? null;
}

/**
 * Obter nome do usuário/empresa logado
 * @return string|null
 */
function getCurrentUserName() {
    return $_SESSION['user_name'] ?? $_SESSION['company_name'] ?? null;
}

/**
 * Obter role do usuário
 * @return string|null
 */
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Requerer login - redireciona se não estiver logado
 */
function requireLogin() {
    if (!isLoggedIn()) {
        setFlashMessage('Você precisa estar logado para acessar esta página.', 'warning');
        redirect(BASE_URL . 'views/auth/login.php');
    }
}

/**
 * Requerer usuário (funcionário) - redireciona se não for usuário
 */
function requireUser() {
    requireLogin();
    if (!isUser()) {
        setFlashMessage('Acesso negado. Área restrita para funcionários.', 'danger');
        redirect(BASE_URL . 'views/dashboard/index.php');
    }
}

/**
 * Requerer empresa - redireciona se não for empresa
 */
function requireCompany() {
    requireLogin();
    if (!isCompany()) {
        setFlashMessage('Acesso negado. Área restrita para empresas.', 'danger');
        redirect(BASE_URL . 'views/dashboard/index.php');
    }
}

/**
 * Requerer admin - redireciona se não for admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        setFlashMessage('Acesso negado. Área restrita para administradores.', 'danger');
        redirect(BASE_URL . 'views/dashboard/index.php');
    }
}

/**
 * Requerer técnico
 */
function requireTechnician() {
    requireLogin();
    if (!isTechnician() && !isAdmin()) {
        setFlashMessage('Acesso negado. Área restrita para técnicos.', 'danger');
        redirect(BASE_URL . 'views/dashboard/index.php');
    }
}

// =====================================================
// FUNÇÕES DE REDIRECIONAMENTO E MENSAGENS
// =====================================================

/**
 * Redirecionar para URL
 * @param string $url
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Definir mensagem flash
 * @param string $message
 * @param string $type (success, danger, warning, info)
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Obter e limpar mensagem flash
 * @return array|null ['message' => string, 'type' => string]
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $flash = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type'] ?? 'info'
        ];
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return $flash;
    }
    return null;
}

/**
 * Exibir mensagem flash (HTML)
 */
function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        echo '<div class="alert alert-' . $flash['type'] . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($flash['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}

// =====================================================
// FUNÇÕES DE CSRF
// =====================================================

/**
 * Gerar token CSRF
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF
 * @param string $token
 * @return bool
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// =====================================================
// FUNÇÕES DE FORMATAÇÃO
// =====================================================

/**
 * Sanitizar string
 * @param string $string
 * @return string
 */
function sanitize($string) {
    return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
}

/**
 * Formatar data brasileira
 * @param string $date
 * @param bool $withTime
 * @return string
 */
function formatDate($date, $withTime = false) {
    if (empty($date)) return '-';
    
    $timestamp = strtotime($date);
    if ($withTime) {
        return date('d/m/Y H:i', $timestamp);
    }
    return date('d/m/Y', $timestamp);
}

/**
 * Tempo decorrido (ex: "2 horas atrás")
 * @param string $datetime
 * @return string
 */
function timeElapsed($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Agora mesmo';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minuto' . ($mins > 1 ? 's' : '') . ' atrás';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hora' . ($hours > 1 ? 's' : '') . ' atrás';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' dia' . ($days > 1 ? 's' : '') . ' atrás';
    } else {
        return formatDate($datetime, true);
    }
}

/**
 * Formatar telefone
 * @param string $phone
 * @return string
 */
function formatPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($phone) === 11) {
        return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7);
    } elseif (strlen($phone) === 10) {
        return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6);
    }
    
    return $phone;
}

/**
 * Formatar CNPJ
 * @param string $cnpj
 * @return string
 */
function formatCNPJ($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    if (strlen($cnpj) === 14) {
        return substr($cnpj, 0, 2) . '.' . 
               substr($cnpj, 2, 3) . '.' . 
               substr($cnpj, 5, 3) . '/' . 
               substr($cnpj, 8, 4) . '-' . 
               substr($cnpj, 12, 2);
    }
    
    return $cnpj;
}

/**
 * Formatar bytes para tamanho legível
 * @param int $bytes
 * @param int $precision
 * @return string
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Limitar texto
 * @param string $text
 * @param int $limit
 * @param string $suffix
 * @return string
 */
function limitText($text, $limit = 100, $suffix = '...') {
    if (strlen($text) <= $limit) {
        return $text;
    }
    return substr($text, 0, $limit) . $suffix;
}

// =====================================================
// FUNÇÕES DE STATUS, PRIORIDADE E CATEGORIA - TICKETS
// =====================================================

/**
 * Obter label do status
 * @param string $status
 * @return string
 */
function getStatusLabel($status) {
    return TICKET_STATUS[$status] ?? $status;
}

/**
 * Obter cor do status
 * @param string $status
 * @return string
 */
function getStatusColor($status) {
    return TICKET_STATUS_COLORS[$status] ?? 'secondary';
}

/**
 * Obter label da prioridade
 * @param string $priority
 * @return string
 */
function getPriorityLabel($priority) {
    return TICKET_PRIORITIES[$priority] ?? $priority;
}

/**
 * Obter cor da prioridade
 * @param string $priority
 * @return string
 */
function getPriorityColor($priority) {
    return TICKET_PRIORITY_COLORS[$priority] ?? 'secondary';
}

/**
 * Obter ícone da prioridade
 * @param string $priority
 * @return string
 */
function getPriorityIcon($priority) {
    return TICKET_PRIORITY_ICONS[$priority] ?? 'fa-minus';
}

/**
 * Obter label da categoria
 * @param string $category
 * @return string
 */
function getCategoryLabel($category) {
    return TICKET_CATEGORIES[$category] ?? $category;
}

/**
 * Obter ícone da categoria
 * @param string $category
 * @return string
 */
function getCategoryIcon($category) {
    return TICKET_CATEGORY_ICONS[$category] ?? 'fa-tools';
}

/**
 * Obter label do role
 * @param string $role
 * @return string
 */
function getRoleLabel($role) {
    return USER_ROLES[$role] ?? $role;
}

// =====================================================
// FUNÇÕES DE NOTIFICAÇÃO
// =====================================================

/**
 * Obter mensagem de notificação formatada
 * @param string $type
 * @param string $userName
 * @return string
 */
function getNotificationMessage($type, $userName = 'Atendente') {
    $messages = [
        'assumed' => "Seu ticket foi assumido por {$userName}",
        'dispatched' => 'Nossa equipe foi despachada para o atendimento',
        'resolved' => 'Seu ticket foi marcado como resolvido',
        'closed' => 'Seu ticket foi encerrado',
        'reopened' => 'O ticket foi reaberto pela empresa'
    ];
    
    return $messages[$type] ?? 'Atualização no ticket';
}

// =====================================================
// FUNÇÕES DE LOG
// =====================================================

/**
 * Registrar log do sistema
 * @param string $message
 * @param string $level (INFO, WARNING, ERROR)
 */
function logSystem($message, $level = 'INFO') {
    if (!defined('LOG_ENABLED') || !LOG_ENABLED) {
        return;
    }
    
    $logFile = LOGS_PATH . 'system_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// =====================================================
// FUNÇÕES DE RESPOSTA JSON
// =====================================================

/**
 * Retornar resposta JSON
 * @param array $data
 * @param int $statusCode
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// =====================================================
// FUNÇÕES DE VALIDAÇÃO
// =====================================================

/**
 * Validar email
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validar CNPJ
 * @param string $cnpj
 * @return bool
 */
function isValidCNPJ($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    if (strlen($cnpj) != 14) {
        return false;
    }
    
    if (preg_match('/(\d)\1{13}/', $cnpj)) {
        return false;
    }
    
    $b = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    
    for ($i = 0, $n = 0; $i < 12; $n += $cnpj[$i] * $b[++$i]);
    
    if ($cnpj[12] != ((($n %= 11) < 2) ? 0 : 11 - $n)) {
        return false;
    }
    
    for ($i = 0, $n = 0; $i <= 12; $n += $cnpj[$i] * $b[$i++]);
    
    if ($cnpj[13] != ((($n %= 11) < 2) ? 0 : 11 - $n)) {
        return false;
    }
    
    return true;
}

/**
 * Validar telefone brasileiro
 * @param string $phone
 * @return bool
 */
function isValidPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 11;
}

// =====================================================
// FUNÇÕES UTILITÁRIAS
// =====================================================

/**
 * Gerar senha aleatória
 * @param int $length
 * @return string
 */
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    $password = '';
    $max = strlen($chars) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    
    return $password;
}

/**
 * Hash de senha
 * @param string $password
 * @return string
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verificar senha
 * @param string $password
 * @param string $hash
 * @return bool
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Obter IP do cliente
 * @return string
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Gerar número de protocolo único
 * @return string
 */
function generateProtocol() {
    return date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Debug (apenas em desenvolvimento)
 * @param mixed $data
 * @param bool $die
 */
function debug($data, $die = false) {
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
        
        if ($die) {
            die();
        }
    }
}

// =====================================================
// ========== FUNÇÕES DO SISTEMA DE OS ==========
// =====================================================

// =====================================================
// FUNÇÕES DE STATUS DA OS
// =====================================================

/**
 * Obter label do status da OS
 */
function getWorkOrderStatusLabel($status) {
    return WORK_ORDER_STATUS[$status] ?? $status;
}

/**
 * Obter cor do status da OS
 */
function getWorkOrderStatusColor($status) {
    return WORK_ORDER_STATUS_COLORS[$status] ?? 'secondary';
}

/**
 * Obter ícone do status da OS
 */
function getWorkOrderStatusIcon($status) {
    return WORK_ORDER_STATUS_ICONS[$status] ?? 'fa-question';
}

// =====================================================
// FUNÇÕES DE PAPEL DO TÉCNICO
// =====================================================

/**
 * Obter label do papel do técnico
 */
function getTechnicianRoleLabel($role) {
    return TECHNICIAN_ROLES[$role] ?? $role;
}

/**
 * Obter cor do papel do técnico
 */
function getTechnicianRoleColor($role) {
    return TECHNICIAN_ROLE_COLORS[$role] ?? 'secondary';
}

/**
 * Obter ícone do papel do técnico
 */
function getTechnicianRoleIcon($role) {
    return TECHNICIAN_ROLE_ICONS[$role] ?? 'fa-user';
}

// =====================================================
// FUNÇÕES DE STATUS DO TÉCNICO
// =====================================================

/**
 * Obter label do status do técnico
 */
function getTechnicianStatusLabel($status) {
    return TECHNICIAN_STATUS[$status] ?? $status;
}

/**
 * Obter cor do status do técnico
 */
function getTechnicianStatusColor($status) {
    return TECHNICIAN_STATUS_COLORS[$status] ?? 'secondary';
}

// =====================================================
// FUNÇÕES DE PRAZO
// =====================================================

/**
 * Calcular prazo baseado na prioridade
 */
function calculateWorkOrderDeadline($priority) {
    $hours = WORK_ORDER_DEADLINES[$priority] ?? 24;
    return date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
}

/**
 * Verificar se OS está atrasada
 */
function isWorkOrderOverdue($deadline, $status) {
    if ($status === 'completed' || $status === 'cancelled') {
        return false;
    }
    
    if (empty($deadline)) {
        return false;
    }
    
    return strtotime($deadline) < time();
}

/**
 * Calcular tempo restante até o prazo
 */
function getWorkOrderTimeRemaining($deadline) {
    if (empty($deadline)) {
        return 'Sem prazo definido';
    }
    
    $diff = strtotime($deadline) - time();
    
    if ($diff < 0) {
        return 'Atrasada';
    }
    
    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);
    
    if ($hours < 1) {
        return "{$minutes} minutos";
    } elseif ($hours < 24) {
        return "{$hours}h {$minutes}min";
    } else {
        $days = floor($hours / 24);
        $hours = $hours % 24;
        return "{$days}d {$hours}h";
    }
}

// =====================================================
// FUNÇÕES DE FORMATAÇÃO - OS
// =====================================================

/**
 * Formatar código da OS
 */
function formatWorkOrderCode($id) {
    return 'OS-' . str_pad($id, 6, '0', STR_PAD_LEFT);
}

/**
 * Obter badge HTML do status da OS
 */
function getWorkOrderStatusBadge($status) {
    $label = getWorkOrderStatusLabel($status);
    $color = getWorkOrderStatusColor($status);
    $icon = getWorkOrderStatusIcon($status);
    
    return "<span class=\"badge bg-{$color}\"><i class=\"fas {$icon} me-1\"></i>{$label}</span>";
}

/**
 * Obter badge HTML do papel do técnico
 */
function getTechnicianRoleBadge($role) {
    $label = getTechnicianRoleLabel($role);
    $color = getTechnicianRoleColor($role);
    $icon = getTechnicianRoleIcon($role);
    
    return "<span class=\"badge bg-{$color}\"><i class=\"fas {$icon} me-1\"></i>{$label}</span>";
}

// =====================================================
// FUNÇÕES DE PERMISSÃO - OS
// =====================================================

/**
 * Verificar se usuário pode gerenciar OS
 */
function canUserManageWorkOrder($userId, $workOrderId, $userRole) {
    if ($userRole === 'admin') {
        return true;
    }
    
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT created_by FROM work_orders WHERE id = ?");
        $stmt->execute([$workOrderId]);
        $wo = $stmt->fetch();
        
        if ($wo && $wo['created_by'] == $userId) {
            return true;
        }
        
        $stmt = $pdo->prepare("
            SELECT role FROM work_order_technicians 
            WHERE work_order_id = ? AND technician_id = ?
        ");
        $stmt->execute([$workOrderId, $userId]);
        $tech = $stmt->fetch();
        
        return $tech && $tech['role'] === 'primary';
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Verificar se ticket já tem OS
 */
function hasWorkOrder($ticketId) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT has_work_order FROM tickets WHERE id = ?");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();
        
        return $ticket && $ticket['has_work_order'];
        
    } catch (Exception $e) {
        return false;
    }
}

// =====================================================
// FUNÇÕES DE BUSCA - OS
// =====================================================

/**
 * Obter técnicos ativos
 */
function getActiveTechnicians() {
    try {
        $pdo = getConnection();
        $stmt = $pdo->query("
            SELECT id, name, email, phone 
            FROM users 
            WHERE role = 'technician' 
            AND is_active = 1 
            ORDER BY name ASC
        ");
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Contar OS disponíveis
 */
function countAvailableWorkOrders() {
    try {
        $pdo = getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM work_orders WHERE status = 'available'");
        return (int)$stmt->fetchColumn();
        
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Contar minhas OS (técnico)
 */
function countMyWorkOrders($technicianId, $status = null) {
    try {
        $pdo = getConnection();
        
        $sql = "
            SELECT COUNT(DISTINCT wo.id) 
            FROM work_orders wo
            INNER JOIN work_order_technicians wot ON wo.id = wot.work_order_id
            WHERE wot.technician_id = ?
        ";
        
        $params = [$technicianId];
        
        if ($status) {
            $sql .= " AND wo.status = ?";
            $params[] = $status;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return (int)$stmt->fetchColumn();
        
    } catch (Exception $e) {
        return 0;
    }
}

// =====================================================
// FUNÇÕES DE NOTIFICAÇÃO - OS
// =====================================================

/**
 * Notificar todos os técnicos sobre nova OS
 */
function notifyTechniciansNewWorkOrder($workOrderId) {
    try {
        $pdo = getConnection();
        
        $stmt = $pdo->prepare("SELECT * FROM v_work_orders_complete WHERE id = ?");
        $stmt->execute([$workOrderId]);
        $wo = $stmt->fetch();
        
        if (!$wo) return false;
        
        $technicians = getActiveTechnicians();
        
        $message = "Nova OS #{$workOrderId} disponível! Prioridade: " . 
                   getPriorityLabel($wo['priority']) . ". Cliente: {$wo['company_name']}";
        
        foreach ($technicians as $tech) {
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, ticket_id, type, message) 
                VALUES (?, ?, 'work_order_created', ?)
            ");
            $stmt->execute([$tech['id'], $wo['ticket_id'], $message]);
        }
        
        return true;
        
    } catch (Exception $e) {
        logSystem("Erro ao notificar técnicos: " . $e->getMessage(), "ERROR");
        return false;
    }
}

/**
 * Obter mensagem de notificação da OS
 */
function getWorkOrderNotificationMessage($type, $workOrderId, $additionalInfo = '') {
    $messages = NOTIFICATION_TYPES_WORK_ORDER;
    $baseMessage = $messages[$type] ?? 'Atualização na OS';
    
    return "{$baseMessage} #" . formatWorkOrderCode($workOrderId) . 
           ($additionalInfo ? " - {$additionalInfo}" : '');
}

// =====================================================
// FUNÇÕES DE VALIDAÇÃO - OS
// =====================================================

/**
 * Validar criação de OS
 */
function validateWorkOrderCreation($ticketId, $userId) {
    $errors = [];
    
    try {
        $pdo = getConnection();
        
        $stmt = $pdo->prepare("SELECT status, has_work_order FROM tickets WHERE id = ?");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();
        
        if (!$ticket) {
            $errors[] = 'Ticket não encontrado';
        } elseif ($ticket['has_work_order']) {
            $errors[] = 'Ticket já possui OS';
        } elseif (!in_array($ticket['status'], ['assumed', 'dispatched'])) {
            $errors[] = 'Ticket deve estar assumido para criar OS';
        }
        
        if (count(getActiveTechnicians()) === 0) {
            $errors[] = 'Nenhum técnico ativo no sistema';
        }
        
    } catch (Exception $e) {
        $errors[] = 'Erro ao validar: ' . $e->getMessage();
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

?>