<?php
/**
 * UserController - Controlador de Usuários
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 */

// Incluir configurações e classes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../includes/functions.php';

class UserController {
    private $auth;
    private $user;
    
    public function __construct() {
        $this->auth = new Auth();
        $this->user = new User();
    }
    
    /**
     * Criar usuário
     */
    public function create() {
        // Apenas admin pode criar usuários
        if (!$this->auth->isAdmin()) {
            setFlashMessage('Você não tem permissão para esta ação.', 'danger');
            redirect(BASE_URL . 'views/dashboard/index.php');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verificar CSRF
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                setFlashMessage('Token de segurança inválido.', 'danger');
                redirect(BASE_URL . 'views/users/create.php');
            }
            
            // Coletar dados
            $data = [
                'name' => sanitize($_POST['name'] ?? ''),
                'email' => sanitize($_POST['email'] ?? ''),
                'password' => $_POST['password'] ?? '',
                'role' => $_POST['role'] ?? 'attendant',
                'phone' => sanitize($_POST['phone'] ?? ''),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            
            // Criar usuário
            $result = $this->user->create($data);
            
            if ($result['success']) {
                setFlashMessage($result['message'], 'success');
                redirect(BASE_URL . 'views/users/index.php');
            } else {
                setFlashMessage($result['message'], 'danger');
                redirect(BASE_URL . 'views/users/create.php');
            }
        }
    }
    
    /**
     * Atualizar usuário
     */
    public function update() {
        // Apenas admin pode atualizar usuários (ou o próprio usuário seu perfil)
        $userId = (int)($_POST['user_id'] ?? $_GET['id'] ?? 0);
        
        if (!$userId) {
            setFlashMessage('Usuário inválido.', 'danger');
            redirect(BASE_URL . 'views/users/index.php');
        }
        
        // Verificar permissão
        $canEdit = $this->auth->isAdmin() || ($this->auth->getUserId() == $userId);
        
        if (!$canEdit) {
            setFlashMessage('Você não tem permissão para editar este usuário.', 'danger');
            redirect(BASE_URL . 'views/users/index.php');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verificar CSRF
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                setFlashMessage('Token de segurança inválido.', 'danger');
                redirect(BASE_URL . 'views/users/edit.php?id=' . $userId);
            }
            
            // Coletar dados
            $data = [
                'name' => sanitize($_POST['name'] ?? ''),
                'email' => sanitize($_POST['email'] ?? ''),
                'phone' => sanitize($_POST['phone'] ?? '')
            ];
            
            // Apenas admin pode alterar role e status
            if ($this->auth->isAdmin()) {
                if (isset($_POST['role'])) {
                    $data['role'] = $_POST['role'];
                }
                if (isset($_POST['is_active'])) {
                    $data['is_active'] = (int)$_POST['is_active'];
                }
            }
            
            // Se informou nova senha
            if (!empty($_POST['password'])) {
                $data['password'] = $_POST['password'];
            }
            
            // Atualizar usuário
            $result = $this->user->update($userId, $data);
            
            if ($result['success']) {
                setFlashMessage($result['message'], 'success');
                
                // Se editou próprio perfil, redirecionar para profile
                if ($this->auth->getUserId() == $userId) {
                    redirect(BASE_URL . 'views/users/profile.php');
                } else {
                    redirect(BASE_URL . 'views/users/index.php');
                }
            } else {
                setFlashMessage($result['message'], 'danger');
                redirect(BASE_URL . 'views/users/edit.php?id=' . $userId);
            }
        }
    }
    
    /**
     * Deletar usuário
     */
    public function delete() {
        // Apenas admin pode deletar usuários
        if (!$this->auth->isAdmin()) {
            jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = (int)($_POST['user_id'] ?? 0);
            
            if (!$userId) {
                jsonResponse(['success' => false, 'message' => 'Usuário inválido'], 400);
            }
            
            $result = $this->user->delete($userId);
            jsonResponse($result);
        }
    }
    
    /**
     * Listar usuários (AJAX)
     */
    public function list() {
        if (!$this->auth->isAdmin()) {
            jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
        }
        
        // Preparar filtros
        $filters = [];
        
        if (isset($_GET['role'])) {
            $filters['role'] = $_GET['role'];
        }
        
        if (isset($_GET['is_active'])) {
            $filters['is_active'] = (int)$_GET['is_active'];
        }
        
        if (isset($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }
        
        // Paginação
        $page = (int)($_GET['page'] ?? 1);
        $limit = ITEMS_PER_PAGE;
        $offset = ($page - 1) * $limit;
        
        // Buscar usuários
        $users = $this->user->getAll($filters, $limit, $offset);
        $total = $this->user->count($filters);
        $totalPages = ceil($total / $limit);
        
        jsonResponse([
            'success' => true,
            'users' => $users,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $total,
                'items_per_page' => $limit
            ]
        ]);
    }
    
    /**
     * Obter detalhes do usuário (AJAX)
     */
    public function getDetails() {
        $userId = (int)($_GET['id'] ?? 0);
        
        if (!$userId) {
            jsonResponse(['success' => false, 'message' => 'Usuário inválido'], 400);
        }
        
        // Verificar permissão
        $canView = $this->auth->isAdmin() || ($this->auth->getUserId() == $userId);
        
        if (!$canView) {
            jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
        }
        
        $userData = $this->user->getById($userId);
        
        if (!$userData) {
            jsonResponse(['success' => false, 'message' => 'Usuário não encontrado'], 404);
        }
        
        // Obter estatísticas
        $stats = $this->user->getStats($userId);
        
        jsonResponse([
            'success' => true,
            'user' => $userData,
            'stats' => $stats
        ]);
    }
    
    /**
     * Alternar status ativo/inativo
     */
    public function toggleStatus() {
        if (!$this->auth->isAdmin()) {
            jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = (int)($_POST['user_id'] ?? 0);
            
            if (!$userId) {
                jsonResponse(['success' => false, 'message' => 'Usuário inválido'], 400);
            }
            
            // Não permitir desativar própria conta
            if ($userId == $this->auth->getUserId()) {
                jsonResponse(['success' => false, 'message' => 'Você não pode desativar sua própria conta'], 400);
            }
            
            $userData = $this->user->getById($userId);
            
            if (!$userData) {
                jsonResponse(['success' => false, 'message' => 'Usuário não encontrado'], 404);
            }
            
            // Alternar status
            $newStatus = $userData['is_active'] ? 0 : 1;
            $result = $this->user->update($userId, ['is_active' => $newStatus]);
            
            jsonResponse($result);
        }
    }
    
    /**
     * Obter atendentes disponíveis (para atribuir tickets)
     */
    public function getAttendants() {
        if (!$this->auth->isUser()) {
            jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
        }
        
        $attendants = $this->user->getAvailableAttendants();
        
        jsonResponse([
            'success' => true,
            'attendants' => $attendants
        ]);
    }

        /**
     * Exibir perfil do usuário logado
     */
    public function profile() {
        // Garante que o usuário está logado
        if (!$this->auth->isLoggedIn()) {
            setFlashMessage('Faça login para acessar o perfil.', 'warning');
            redirect(BASE_URL . 'views/auth/login.php');
        }

        // Pega o ID do usuário logado
        $userId = $this->auth->getUserId();

        // Busca dados do usuário
        $user = $this->user->getById($userId);

        if (!$user) {
            setFlashMessage('Usuário não encontrado.', 'danger');
            redirect(BASE_URL . 'views/dashboard/index.php');
        }

        // Torna a variável $user disponível na view
        include __DIR__ . '/../views/users/profile.php';
    }

    
}



// Processar requisições
if (isset($_GET['action']) || isset($_POST['action'])) {
    $controller = new UserController();
    $action = $_GET['action'] ?? $_POST['action'];
    
    switch ($action) {
        case 'create':
            $controller->create();
            break;
        case 'update':
            $controller->update();
            break;
        case 'delete':
            $controller->delete();
            break;
        case 'list':
            $controller->list();
            break;
        case 'details':
            $controller->getDetails();
            break;
        case 'toggle_status':
            $controller->toggleStatus();
            break;
        case 'get_attendants':
            $controller->getAttendants();
            break;
            case 'profile':
            $controller->profile();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Ação inválida'], 400);
    }
}
?>