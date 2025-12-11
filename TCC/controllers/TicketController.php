<?php
/**
 * TicketController - Controlador de Tickets (REFATORADO)
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 * 
 * TODAS as operações de tickets passam por aqui - SEM DUPLICAÇÃO
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Ticket.php';
require_once __DIR__ . '/../classes/Upload.php';
require_once __DIR__ . '/../classes/Notification.php';
require_once __DIR__ . '/../includes/functions.php';

class TicketController {
    private $auth;
    private $ticket;
    private $upload;
    private $notification;
    private $db;
    
    public function __construct() {
        $this->auth = new Auth();
        $this->ticket = new Ticket();
        $this->upload = new Upload();
        $this->notification = new Notification();
        $this->db = Database::getInstance();
    }
    
    /**
     * ========================================
     * CRIAR TICKET (APENAS EMPRESAS)
     * ========================================
     */
    public function create() {
        // Validar permissão
        if (!$this->auth->isCompany()) {
            setFlashMessage('Apenas empresas podem criar tickets.', 'danger');
            redirect(BASE_URL . 'views/dashboard/index.php');
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            setFlashMessage('Método inválido.', 'danger');
            redirect(BASE_URL . 'views/tickets/create.php');
        }
        
        // Verificar CSRF
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            setFlashMessage('Token de segurança inválido.', 'danger');
            redirect(BASE_URL . 'views/tickets/create.php');
        }
        
        // Validar dados
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $category = $_POST['category'] ?? '';
        $priority = $_POST['priority'] ?? 'medium';
        $address = sanitize($_POST['address'] ?? '');
        
        if (empty($title) || empty($description) || empty($category)) {
            setFlashMessage('Preencha todos os campos obrigatórios.', 'warning');
            redirect(BASE_URL . 'views/tickets/create.php');
        }
        
        if (strlen($title) < 10) {
            setFlashMessage('O título deve ter pelo menos 10 caracteres.', 'warning');
            redirect(BASE_URL . 'views/tickets/create.php');
        }
        
        if (strlen($description) < 20) {
            setFlashMessage('A descrição deve ter pelo menos 20 caracteres.', 'warning');
            redirect(BASE_URL . 'views/tickets/create.php');
        }
        
        try {
            // Criar ticket
            $data = [
                'company_id' => $this->auth->getCompanyId(),
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'priority' => $priority,
                'address' => $address
            ];
            
            $result = $this->ticket->create($data);
            
            if ($result['success']) {
                $ticketId = $result['id'];
                
                // Processar uploads se houver
                if (isset($_FILES['attachments']) && $_FILES['attachments']['name'][0] != '') {
                    $uploadResult = $this->upload->uploadMultiple($_FILES['attachments'], $ticketId);
                    
                    if (!$uploadResult['success']) {
                        logSystem("Aviso: Ticket criado mas houve problemas com uploads - Ticket #$ticketId", "WARNING");
                    }
                }
                
                setFlashMessage('Ticket criado com sucesso! Nossa equipe foi notificada.', 'success');
                redirect(BASE_URL . 'views/tickets/view.php?id=' . $ticketId);
            } else {
                setFlashMessage($result['message'], 'danger');
                redirect(BASE_URL . 'views/tickets/create.php');
            }
            
        } catch (Exception $e) {
            logSystem("Erro ao criar ticket: " . $e->getMessage(), "ERROR");
            setFlashMessage('Erro ao criar ticket. Tente novamente.', 'danger');
            redirect(BASE_URL . 'views/tickets/create.php');
        }
    }
    
    /**
     * ========================================
     * ASSUMIR TICKET (USUÁRIOS)
     * ========================================
     */
    public function assume() {
        if (!$this->auth->isUser()) {
            jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
        }
        
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        
        if (!$ticketId) {
            jsonResponse(['success' => false, 'message' => 'Ticket inválido'], 400);
        }
        
        try {
            $userId = $this->auth->getUserId();
            $result = $this->ticket->assume($ticketId, $userId);
            
            jsonResponse($result, $result['success'] ? 200 : 400);
            
        } catch (Exception $e) {
            logSystem("Erro ao assumir ticket: " . $e->getMessage(), "ERROR");
            jsonResponse(['success' => false, 'message' => 'Erro ao processar requisição'], 500);
        }
    }
    
    /**
     * ========================================
     * DESPACHAR EQUIPE (USUÁRIOS)
     * ========================================
     */
    public function dispatch() {
        if (!$this->auth->isUser()) {
            jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
        }
        
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        
        if (!$ticketId) {
            jsonResponse(['success' => false, 'message' => 'Ticket inválido'], 400);
        }
        
        try {
            $userId = $this->auth->getUserId();
            $result = $this->ticket->dispatch($ticketId, $userId);
            
            jsonResponse($result, $result['success'] ? 200 : 400);
            
        } catch (Exception $e) {
            logSystem("Erro ao despachar equipe: " . $e->getMessage(), "ERROR");
            jsonResponse(['success' => false, 'message' => 'Erro ao processar requisição'], 500);
        }
    }
    
    /**
     * ========================================
     * MARCAR COMO EM ANDAMENTO (USUÁRIOS)
     * ========================================
     */
    public function setInProgress() {
        if (!$this->auth->isUser()) {
            jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
        }
        
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        
        if (!$ticketId) {
            jsonResponse(['success' => false, 'message' => 'Ticket inválido'], 400);
        }
        
        try {
            $userId = $this->auth->getUserId();
            $result = $this->ticket->setInProgress($ticketId, $userId);
            
            jsonResponse($result, $result['success'] ? 200 : 400);
            
        } catch (Exception $e) {
            logSystem("Erro ao atualizar status: " . $e->getMessage(), "ERROR");
            jsonResponse(['success' => false, 'message' => 'Erro ao processar requisição'], 500);
        }
    }
    
    /**
     * ========================================
     * RESOLVER TICKET (USUÁRIOS)
     * ========================================
     */
    public function resolve() {
        if (!$this->auth->isUser()) {
            jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
        }
        
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        
        if (!$ticketId) {
            jsonResponse(['success' => false, 'message' => 'Ticket inválido'], 400);
        }
        
        try {
            $userId = $this->auth->getUserId();
            $result = $this->ticket->resolve($ticketId, $userId);
            
            jsonResponse($result, $result['success'] ? 200 : 400);
            
        } catch (Exception $e) {
            logSystem("Erro ao resolver ticket: " . $e->getMessage(), "ERROR");
            jsonResponse(['success' => false, 'message' => 'Erro ao processar requisição'], 500);
        }
    }
    
    /**
     * ========================================
     * ENCERRAR TICKET (USUÁRIOS)
     * ========================================
     */
    public function close() {
        if (!$this->auth->isUser()) {
            jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
        }
        
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        
        if (!$ticketId) {
            jsonResponse(['success' => false, 'message' => 'Ticket inválido'], 400);
        }
        
        try {
            $userId = $this->auth->getUserId();
            $result = $this->ticket->close($ticketId, $userId);
            
            jsonResponse($result, $result['success'] ? 200 : 400);
            
        } catch (Exception $e) {
            logSystem("Erro ao encerrar ticket: " . $e->getMessage(), "ERROR");
            jsonResponse(['success' => false, 'message' => 'Erro ao processar requisição'], 500);
        }
    }
    
    /**
     * ========================================
     * REABRIR TICKET (EMPRESAS)
     * ========================================
     */
    public function reopen() {
        if (!$this->auth->isCompany()) {
            jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
        }
        
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        
        if (!$ticketId) {
            jsonResponse(['success' => false, 'message' => 'Ticket inválido'], 400);
        }
        
        try {
            // Verificar se ticket pertence à empresa
            $ticketData = $this->ticket->getById($ticketId);
            
            if (!$ticketData) {
                jsonResponse(['success' => false, 'message' => 'Ticket não encontrado'], 404);
            }
            
            if ($ticketData['company_id'] != $this->auth->getCompanyId()) {
                jsonResponse(['success' => false, 'message' => 'Ticket não pertence a esta empresa'], 403);
            }
            
            $companyId = $this->auth->getCompanyId();
            $result = $this->ticket->reopen($ticketId, $companyId);
            
            jsonResponse($result, $result['success'] ? 200 : 400);
            
        } catch (Exception $e) {
            logSystem("Erro ao reabrir ticket: " . $e->getMessage(), "ERROR");
            jsonResponse(['success' => false, 'message' => 'Erro ao processar requisição'], 500);
        }
    }
    
    /**
     * ========================================
     * OBTER DETALHES DO TICKET
     * ========================================
     */
    public function getDetails() {
        if (!$this->auth->isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Não autenticado'], 401);
        }
        
        $ticketId = (int)($_GET['id'] ?? 0);
        
        if (!$ticketId) {
            jsonResponse(['success' => false, 'message' => 'Ticket inválido'], 400);
        }
        
        try {
            $ticketData = $this->ticket->getById($ticketId);
            
            if (!$ticketData) {
                jsonResponse(['success' => false, 'message' => 'Ticket não encontrado'], 404);
            }
            
            // Verificar permissão
            if ($this->auth->isCompany()) {
                if ($ticketData['company_id'] != $this->auth->getCompanyId()) {
                    jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
                }
            }
            
            // Buscar informações adicionais
            $logs = $this->ticket->getLogs($ticketId);
            $attachments = $this->upload->getByTicket($ticketId);
            
            jsonResponse([
                'success' => true,
                'ticket' => $ticketData,
                'logs' => $logs,
                'attachments' => $attachments
            ]);
            
        } catch (Exception $e) {
            logSystem("Erro ao buscar detalhes: " . $e->getMessage(), "ERROR");
            jsonResponse(['success' => false, 'message' => 'Erro ao processar requisição'], 500);
        }
    }
    
    /**
     * ========================================
     * LISTAR TICKETS COM FILTROS
     * ========================================
     */
    public function list() {
        if (!$this->auth->isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Não autenticado'], 401);
        }
        
        try {
            // Preparar filtros
            $filters = [];
            
            // Se for empresa, filtrar apenas seus tickets
            if ($this->auth->isCompany()) {
                $filters['company_id'] = $this->auth->getCompanyId();
            }
            
            // Filtros adicionais
            if (isset($_GET['status']) && !empty($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            
            if (isset($_GET['priority']) && !empty($_GET['priority'])) {
                $filters['priority'] = $_GET['priority'];
            }
            
            if (isset($_GET['category']) && !empty($_GET['category'])) {
                $filters['category'] = $_GET['category'];
            }
            
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $filters['search'] = $_GET['search'];
            }
            
            if (isset($_GET['assigned_user_id']) && !empty($_GET['assigned_user_id'])) {
                $filters['assigned_user_id'] = (int)$_GET['assigned_user_id'];
            }
            
            // Paginação
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = ITEMS_PER_PAGE;
            $offset = ($page - 1) * $limit;
            
            // Buscar tickets
            $tickets = $this->ticket->getAll($filters, $limit, $offset);
            $total = $this->ticket->count($filters);
            $totalPages = ceil($total / $limit);
            
            jsonResponse([
                'success' => true,
                'tickets' => $tickets,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $total,
                    'items_per_page' => $limit
                ]
            ]);
            
        } catch (Exception $e) {
            logSystem("Erro ao listar tickets: " . $e->getMessage(), "ERROR");
            jsonResponse(['success' => false, 'message' => 'Erro ao processar requisição'], 500);
        }
    }
    
    /**
     * ========================================
     * ADICIONAR COMENTÁRIO
     * ========================================
     */
    public function addComment() {
        if (!$this->auth->isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Não autenticado'], 401);
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
        }
        
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $comment = sanitize($_POST['comment'] ?? '');
        
        if (!$ticketId || empty($comment)) {
            jsonResponse(['success' => false, 'message' => 'Dados inválidos'], 400);
        }
        
        try {
            // Verificar permissão de acesso ao ticket
            $ticketData = $this->ticket->getById($ticketId);
            
            if (!$ticketData) {
                jsonResponse(['success' => false, 'message' => 'Ticket não encontrado'], 404);
            }
            
            if ($this->auth->isCompany() && $ticketData['company_id'] != $this->auth->getCompanyId()) {
                jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
            }
            
            // Adicionar comentário como log
            $userId = $this->auth->isUser() ? $this->auth->getUserId() : null;
            $userName = $this->auth->isUser() ? 
                        $_SESSION['user_name'] : 
                        $_SESSION['company_name'];
            
            $pdo = $this->db->getConnection();
            $sql = "INSERT INTO ticket_logs (ticket_id, user_id, action, description) 
                    VALUES (?, ?, 'COMMENTED', ?)";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$ticketId, $userId, "$userName: $comment"]);
            
            if ($result) {
                // Criar notificação
                if ($this->auth->isCompany()) {
                    // Notificar responsável do ticket
                    if ($ticketData['assigned_user_id']) {
                        $this->notification->create([
                            'ticket_id' => $ticketId,
                            'company_id' => $this->auth->getCompanyId(),
                            'user_id' => $ticketData['assigned_user_id'],
                            'type' => 'comment',
                            'message' => "Novo comentário no ticket #$ticketId"
                        ]);
                    }
                } else {
                    // Notificar empresa
                    $this->notification->create([
                        'ticket_id' => $ticketId,
                        'company_id' => $ticketData['company_id'],
                        'user_id' => null,
                        'type' => 'comment',
                        'message' => "Novo comentário no ticket #$ticketId"
                    ]);
                }
                
                jsonResponse(['success' => true, 'message' => 'Comentário adicionado']);
            } else {
                jsonResponse(['success' => false, 'message' => 'Erro ao adicionar comentário'], 500);
            }
            
        } catch (Exception $e) {
            logSystem("Erro ao adicionar comentário: " . $e->getMessage(), "ERROR");
            jsonResponse(['success' => false, 'message' => 'Erro ao processar requisição'], 500);
        }
    }
}

// ========================================
// PROCESSAR REQUISIÇÕES
// ========================================
if (isset($_GET['action']) || isset($_POST['action'])) {
    $controller = new TicketController();
    $action = $_GET['action'] ?? $_POST['action'];
    
    switch ($action) {
        case 'create':
            $controller->create();
            break;
        case 'assume':
            $controller->assume();
            break;
        case 'dispatch':
            $controller->dispatch();
            break;
        case 'in_progress':
            $controller->setInProgress();
            break;
        case 'resolve':
            $controller->resolve();
            break;
        case 'close':
            $controller->close();
            break;
        case 'reopen':
            $controller->reopen();
            break;
        case 'details':
            $controller->getDetails();
            break;
        case 'list':
            $controller->list();
            break;
        case 'add_comment':
            $controller->addComment();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Ação inválida'], 400);
    }
}
?>