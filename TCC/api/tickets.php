<?php
/**
 * API REST - Tickets
 * Sistema de Suporte e Manutenção
 * 
 * Endpoints:
 * GET    /api/tickets.php              - Listar todos os tickets
 * GET    /api/tickets.php?id={id}      - Buscar ticket específico
 * POST   /api/tickets.php              - Criar novo ticket
 * PUT    /api/tickets.php?id={id}      - Atualizar ticket
 * DELETE /api/tickets.php?id={id}      - Deletar ticket
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Tratrar requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Ticket.php';

// Função para retornar resposta JSON
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Função para validar API Key (exemplo simples)
function validateApiKey() {
    $headers = getallheaders();
    $apiKey = $headers['Authorization'] ?? null;
    
    // Exemplo: Bearer SEU_TOKEN_AQUI
    $validKey = 'Bearer ' . hash('sha256', 'sistema_suporte_2024');
    
    if ($apiKey !== $validKey) {
        jsonResponse([
            'success' => false,
            'error' => 'API Key inválida ou ausente'
        ], 401);
    }
}

// Validar API Key (comentar se quiser deixar aberto)
// validateApiKey();

try {
    $database = new Database();
    $db = $database->getConnection();
    $ticket = new Ticket($db);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $id = $_GET['id'] ?? null;
    
    switch ($method) {
        
        // ==================== GET - Listar ou Buscar ====================
        case 'GET':
            if ($id) {
                // Buscar ticket específico
                $stmt = $db->prepare("
                    SELECT t.*, 
                           c.name as company_name,
                           u.name as assigned_user_name
                    FROM tickets t
                    INNER JOIN companies c ON t.company_id = c.id
                    LEFT JOIN users u ON t.assigned_user_id = u.id
                    WHERE t.id = ?
                ");
                $stmt->execute([$id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    jsonResponse([
                        'success' => true,
                        'data' => $result
                    ]);
                } else {
                    jsonResponse([
                        'success' => false,
                        'error' => 'Ticket não encontrado'
                    ], 404);
                }
            } else {
                // Listar todos os tickets com filtros
                $filters = [
                    'status' => $_GET['status'] ?? null,
                    'priority' => $_GET['priority'] ?? null,
                    'company_id' => $_GET['company_id'] ?? null,
                    'category' => $_GET['category'] ?? null
                ];
                
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
                $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
                
                // Remover filtros vazios
                $filters = array_filter($filters);
                
                $tickets = $ticket->getAll($filters, $limit, $offset);
                $total = $ticket->count($filters);
                
                jsonResponse([
                    'success' => true,
                    'data' => $tickets,
                    'pagination' => [
                        'total' => $total,
                        'limit' => $limit,
                        'offset' => $offset,
                        'has_more' => ($offset + $limit) < $total
                    ]
                ]);
            }
            break;
        
        // ==================== POST - Criar Ticket ====================
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                jsonResponse([
                    'success' => false,
                    'error' => 'Dados inválidos'
                ], 400);
            }
            
            // Validações
            $required = ['company_id', 'title', 'description'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    jsonResponse([
                        'success' => false,
                        'error' => "Campo obrigatório ausente: {$field}"
                    ], 400);
                }
            }
            
            $result = $ticket->create($input);
            
            if ($result['success']) {
                jsonResponse([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => [
                        'id' => $result['id']
                    ]
                ], 201);
            } else {
                jsonResponse([
                    'success' => false,
                    'error' => $result['message']
                ], 400);
            }
            break;
        
        // ==================== PUT - Atualizar Ticket ====================
        case 'PUT':
            if (!$id) {
                jsonResponse([
                    'success' => false,
                    'error' => 'ID do ticket não fornecido'
                ], 400);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                jsonResponse([
                    'success' => false,
                    'error' => 'Dados inválidos'
                ], 400);
            }
            
            // Verificar se ticket existe
            $ticketData = $ticket->getById($id);
            if (!$ticketData) {
                jsonResponse([
                    'success' => false,
                    'error' => 'Ticket não encontrado'
                ], 404);
            }
            
            // Atualizar campos permitidos
            $allowedFields = ['title', 'description', 'priority', 'category', 'address'];
            $updateData = [];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateData[$field] = $input[$field];
                }
            }
            
            if (empty($updateData)) {
                jsonResponse([
                    'success' => false,
                    'error' => 'Nenhum campo para atualizar'
                ], 400);
            }
            
            // Construir SQL dinamicamente
            $fields = [];
            $params = [];
            foreach ($updateData as $key => $value) {
                $fields[] = "$key = ?";
                $params[] = $value;
            }
            $params[] = $id;
            
            $sql = "UPDATE tickets SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            jsonResponse([
                'success' => true,
                'message' => 'Ticket atualizado com sucesso'
            ]);
            break;
        
        // ==================== DELETE - Deletar Ticket ====================
        case 'DELETE':
            if (!$id) {
                jsonResponse([
                    'success' => false,
                    'error' => 'ID do ticket não fornecido'
                ], 400);
            }
            
            // Verificar se ticket existe
            $ticketData = $ticket->getById($id);
            if (!$ticketData) {
                jsonResponse([
                    'success' => false,
                    'error' => 'Ticket não encontrado'
                ], 404);
            }
            
            // Deletar ticket (soft delete recomendado em produção)
            $stmt = $db->prepare("DELETE FROM tickets WHERE id = ?");
            $stmt->execute([$id]);
            
            jsonResponse([
                'success' => true,
                'message' => 'Ticket deletado com sucesso'
            ]);
            break;
        
        default:
            jsonResponse([
                'success' => false,
                'error' => 'Método não suportado'
            ], 405);
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    
    jsonResponse([
        'success' => false,
        'error' => 'Erro interno do servidor',
        'details' => ENVIRONMENT === 'development' ? $e->getMessage() : null
    ], 500);
}
?>