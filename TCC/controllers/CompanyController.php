<?php
/**
 * CompanyController - Controlador de Empresas
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 */

// Incluir configurações e classes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Company.php';
require_once __DIR__ . '/../includes/functions.php';

class CompanyController {
    private $auth;
    private $company;
    
    public function __construct() {
        $this->auth = new Auth();
        $this->company = new Company();
    }
    
    /**
     * Criar empresa
     */
    public function create() {
        // Apenas admin pode criar empresas
        if (!$this->auth->isAdmin()) {
            setFlashMessage('Você não tem permissão para esta ação.', 'danger');
            redirect(BASE_URL . 'views/dashboard/index.php');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verificar CSRF
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                setFlashMessage('Token de segurança inválido.', 'danger');
                redirect(BASE_URL . 'views/companies/create.php');
            }
            
            // Coletar dados
            $data = [
                'name' => sanitize($_POST['name'] ?? ''),
                'cnpj' => preg_replace('/[^0-9]/', '', $_POST['cnpj'] ?? ''),
                'email' => sanitize($_POST['email'] ?? ''),
                'password' => $_POST['password'] ?? '',
                'phone' => sanitize($_POST['phone'] ?? ''),
                'address' => sanitize($_POST['address'] ?? ''),
                'city' => sanitize($_POST['city'] ?? ''),
                'state' => $_POST['state'] ?? '',
                'zip_code' => preg_replace('/[^0-9]/', '', $_POST['zip_code'] ?? ''),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            
            // Criar empresa
            $result = $this->company->create($data);
            
            if ($result['success']) {
                setFlashMessage($result['message'], 'success');
                redirect(BASE_URL . 'views/companies/index.php');
            } else {
                setFlashMessage($result['message'], 'danger');
                redirect(BASE_URL . 'views/companies/create.php');
            }
        }
    }
    
    /**
     * Atualizar empresa
     */
    public function update() {
        $companyId = (int)($_POST['company_id'] ?? $_GET['id'] ?? 0);
        
        if (!$companyId) {
            setFlashMessage('Empresa inválida.', 'danger');
            redirect(BASE_URL . 'views/companies/index.php');
        }
        
        // Verificar permissão (admin ou a própria empresa)
        $canEdit = $this->auth->isAdmin() || ($this->auth->getCompanyId() == $companyId);
        
        if (!$canEdit) {
            setFlashMessage('Você não tem permissão para editar esta empresa.', 'danger');
            redirect(BASE_URL . 'views/companies/index.php');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verificar CSRF
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                setFlashMessage('Token de segurança inválido.', 'danger');
                redirect(BASE_URL . 'views/companies/edit.php?id=' . $companyId);
            }
            
            // Coletar dados
            $data = [
                'name' => sanitize($_POST['name'] ?? ''),
                'cnpj' => preg_replace('/[^0-9]/', '', $_POST['cnpj'] ?? ''),
                'email' => sanitize($_POST['email'] ?? ''),
                'phone' => sanitize($_POST['phone'] ?? ''),
                'address' => sanitize($_POST['address'] ?? ''),
                'city' => sanitize($_POST['city'] ?? ''),
                'state' => $_POST['state'] ?? '',
                'zip_code' => preg_replace('/[^0-9]/', '', $_POST['zip_code'] ?? '')
            ];
            
            // Apenas admin pode alterar status
            if ($this->auth->isAdmin() && isset($_POST['is_active'])) {
                $data['is_active'] = (int)$_POST['is_active'];
            }
            
            // Se informou nova senha
            if (!empty($_POST['password'])) {
                $data['password'] = $_POST['password'];
            }
            
            // Atualizar empresa
            $result = $this->company->update($companyId, $data);
            
            if ($result['success']) {
                setFlashMessage($result['message'], 'success');
                
                // Se editou próprio perfil, redirecionar para profile
                if ($this->auth->getCompanyId() == $companyId) {
                    redirect(BASE_URL . 'views/users/profile.php');
                } else {
                    redirect(BASE_URL . 'views/companies/index.php');
                }
            } else {
                setFlashMessage($result['message'], 'danger');
                redirect(BASE_URL . 'views/companies/edit.php?id=' . $companyId);
            }
        }
    }
    
    /**
     * Deletar empresa
     */
    public function delete() {
        // Apenas admin pode deletar empresas
        if (!$this->auth->isAdmin()) {
            jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $companyId = (int)($_POST['company_id'] ?? 0);
            
            if (!$companyId) {
                jsonResponse(['success' => false, 'message' => 'Empresa inválida'], 400);
            }
            
            $result = $this->company->delete($companyId);
            jsonResponse($result);
        }
    }
    
    /**
     * Listar empresas (AJAX)
     */
    public function list() {
        if (!$this->auth->isAdmin()) {
            jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
        }
        
        // Preparar filtros
        $filters = [];
        
        if (isset($_GET['is_active'])) {
            $filters['is_active'] = (int)$_GET['is_active'];
        }
        
        if (isset($_GET['city'])) {
            $filters['city'] = $_GET['city'];
        }
        
        if (isset($_GET['state'])) {
            $filters['state'] = $_GET['state'];
        }
        
        if (isset($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }
        
        // Paginação
        $page = (int)($_GET['page'] ?? 1);
        $limit = ITEMS_PER_PAGE;
        $offset = ($page - 1) * $limit;
        
        // Buscar empresas
        $companies = $this->company->getAll($filters, $limit, $offset);
        $total = $this->company->count($filters);
        $totalPages = ceil($total / $limit);
        
        jsonResponse([
            'success' => true,
            'companies' => $companies,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $total,
                'items_per_page' => $limit
            ]
        ]);
    }
    
    /**
     * Obter detalhes da empresa (AJAX)
     */
    public function getDetails() {
        $companyId = (int)($_GET['id'] ?? 0);
        
        if (!$companyId) {
            jsonResponse(['success' => false, 'message' => 'Empresa inválida'], 400);
        }
        
        // Verificar permissão
        $canView = $this->auth->isAdmin() || ($this->auth->getCompanyId() == $companyId);
        
        if (!$canView) {
            jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
        }
        
        $companyData = $this->company->getById($companyId);
        
        if (!$companyData) {
            jsonResponse(['success' => false, 'message' => 'Empresa não encontrada'], 404);
        }
        
        // Remover senha
        unset($companyData['password']);
        
        // Obter estatísticas
        $stats = $this->company->getStats($companyId);
        
        jsonResponse([
            'success' => true,
            'company' => $companyData,
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
            $companyId = (int)($_POST['company_id'] ?? 0);
            
            if (!$companyId) {
                jsonResponse(['success' => false, 'message' => 'Empresa inválida'], 400);
            }
            
            $companyData = $this->company->getById($companyId);
            
            if (!$companyData) {
                jsonResponse(['success' => false, 'message' => 'Empresa não encontrada'], 404);
            }
            
            // Alternar status
            $newStatus = $companyData['is_active'] ? 0 : 1;
            $result = $this->company->update($companyId, ['is_active' => $newStatus]);
            
            jsonResponse($result);
        }
    }
    
    /**
     * Obter estatísticas da empresa
     */
    public function getStats() {
        $companyId = (int)($_GET['company_id'] ?? 0);
        
        // Se não informou ID, usar empresa logada
        if (!$companyId && $this->auth->isCompany()) {
            $companyId = $this->auth->getCompanyId();
        }
        
        if (!$companyId) {
            jsonResponse(['success' => false, 'message' => 'Empresa inválida'], 400);
        }
        
        // Verificar permissão
        $canView = $this->auth->isAdmin() || ($this->auth->getCompanyId() == $companyId);
        
        if (!$canView) {
            jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
        }
        
        $stats = $this->company->getStats($companyId);
        
        jsonResponse([
            'success' => true,
            'stats' => $stats
        ]);
    }
}

// Processar requisições
if (isset($_GET['action']) || isset($_POST['action'])) {
    $controller = new CompanyController();
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
        case 'stats':
            $controller->getStats();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Ação inválida'], 400);
    }
}
?>