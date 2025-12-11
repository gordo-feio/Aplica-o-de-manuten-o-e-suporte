<?php
/**
 * Classe User - Gerenciamento de Usuários (Funcionários)
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 */

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Criar novo usuário
     * @param array $data
     * @return array ['success' => bool, 'message' => string, 'id' => int]
     */
    public function create($data) {
        try {
            // Validações
            if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
                return ['success' => false, 'message' => 'Preencha todos os campos obrigatórios.'];
            }
            
            if (!validateEmail($data['email'])) {
                return ['success' => false, 'message' => 'Email inválido.'];
            }
            
            if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
                return ['success' => false, 'message' => 'A senha deve ter no mínimo ' . PASSWORD_MIN_LENGTH . ' caracteres.'];
            }
            
            // Verificar se email já existe
            if ($this->emailExists($data['email'])) {
                return ['success' => false, 'message' => 'Este email já está cadastrado.'];
            }
            
            // Hash da senha
            $hashedPassword = password_hash($data['password'], PASSWORD_HASH_ALGO);
            
            // Inserir no banco
            $sql = "INSERT INTO users (name, email, password, role, phone, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $params = [
                $data['name'],
                $data['email'],
                $hashedPassword,
                $data['role'] ?? 'attendant',
                $data['phone'] ?? null,
                isset($data['is_active']) ? (int)$data['is_active'] : 1
            ];
            
            $userId = $this->db->insert($sql, $params);
            
            if ($userId) {
                logSystem("Usuário criado: {$data['name']} ({$data['email']})", "INFO");
                return [
                    'success' => true, 
                    'message' => 'Usuário cadastrado com sucesso!',
                    'id' => $userId
                ];
            }
            
            return ['success' => false, 'message' => 'Erro ao cadastrar usuário.'];
            
        } catch (Exception $e) {
            logSystem("Erro ao criar usuário: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao cadastrar usuário.'];
        }
    }
    
    /**
     * Atualizar usuário
     * @param int $id
     * @param array $data
     * @return array ['success' => bool, 'message' => string]
     */
    public function update($id, $data) {
        try {
            // Verificar se usuário existe
            if (!$this->exists($id)) {
                return ['success' => false, 'message' => 'Usuário não encontrado.'];
            }
            
            // Validações
            if (!empty($data['email']) && !validateEmail($data['email'])) {
                return ['success' => false, 'message' => 'Email inválido.'];
            }
            
            // Verificar se email já existe (exceto para o próprio usuário)
            if (!empty($data['email']) && $this->emailExists($data['email'], $id)) {
                return ['success' => false, 'message' => 'Este email já está cadastrado.'];
            }
            
            // Construir SQL dinamicamente
            $fields = [];
            $params = [];
            
            if (!empty($data['name'])) {
                $fields[] = "name = ?";
                $params[] = $data['name'];
            }
            
            if (!empty($data['email'])) {
                $fields[] = "email = ?";
                $params[] = $data['email'];
            }
            
            if (!empty($data['phone'])) {
                $fields[] = "phone = ?";
                $params[] = $data['phone'];
            }
            
            if (isset($data['role'])) {
                $fields[] = "role = ?";
                $params[] = $data['role'];
            }
            
            if (isset($data['is_active'])) {
                $fields[] = "is_active = ?";
                $params[] = (int)$data['is_active'];
            }
            
            // Se tem nova senha
            if (!empty($data['password'])) {
                if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
                    return ['success' => false, 'message' => 'A senha deve ter no mínimo ' . PASSWORD_MIN_LENGTH . ' caracteres.'];
                }
                $fields[] = "password = ?";
                $params[] = password_hash($data['password'], PASSWORD_HASH_ALGO);
            }
            
            if (empty($fields)) {
                return ['success' => false, 'message' => 'Nenhum dado para atualizar.'];
            }
            
            $fields[] = "updated_at = NOW()";
            $params[] = $id;
            
            $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
            $this->db->update($sql, $params);
            
            logSystem("Usuário atualizado: ID {$id}", "INFO");
            
            return ['success' => true, 'message' => 'Usuário atualizado com sucesso!'];
            
        } catch (Exception $e) {
            logSystem("Erro ao atualizar usuário: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao atualizar usuário.'];
        }
    }
    
    /**
     * Deletar usuário
     * @param int $id
     * @return array ['success' => bool, 'message' => string]
     */
    public function delete($id) {
        try {
            if (!$this->exists($id)) {
                return ['success' => false, 'message' => 'Usuário não encontrado.'];
            }
            
            // Não permitir deletar o próprio usuário
            if ($id == getCurrentUserId()) {
                return ['success' => false, 'message' => 'Você não pode deletar sua própria conta.'];
            }
            
            $sql = "DELETE FROM users WHERE id = ?";
            $this->db->delete($sql, [$id]);
            
            logSystem("Usuário deletado: ID {$id}", "WARNING");
            
            return ['success' => true, 'message' => 'Usuário deletado com sucesso!'];
            
        } catch (Exception $e) {
            logSystem("Erro ao deletar usuário: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao deletar usuário.'];
        }
    }
    
    /**
     * Obter usuário por ID
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        $sql = "SELECT id, name, email, role, phone, is_active, created_at, updated_at 
                FROM users WHERE id = ?";
        return $this->db->selectOne($sql, [$id]);
    }
    
    /**
     * Obter todos os usuários
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAll($filters = [], $limit = null, $offset = 0) {
        $sql = "SELECT id, name, email, role, phone, is_active, created_at, updated_at 
                FROM users WHERE 1=1";
        $params = [];
        
        // Filtros
        if (!empty($filters['role'])) {
            $sql .= " AND role = ?";
            $params[] = $filters['role'];
        }
        
        if (isset($filters['is_active'])) {
            $sql .= " AND is_active = ?";
            $params[] = (int)$filters['is_active'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (name LIKE ? OR email LIKE ?)";
            $searchTerm = '%' . $this->db->escapeLike($filters['search']) . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY name ASC";
        
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int)$limit;
            $params[] = (int)$offset;
        }
        
        return $this->db->select($sql, $params);
    }
    
    /**
     * Contar usuários
     * @param array $filters
     * @return int
     */
    public function count($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM users WHERE 1=1";
        $params = [];
        
        if (!empty($filters['role'])) {
            $sql .= " AND role = ?";
            $params[] = $filters['role'];
        }
        
        if (isset($filters['is_active'])) {
            $sql .= " AND is_active = ?";
            $params[] = (int)$filters['is_active'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (name LIKE ? OR email LIKE ?)";
            $searchTerm = '%' . $this->db->escapeLike($filters['search']) . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $result = $this->db->selectOne($sql, $params);
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * Verificar se email já existe
     * @param string $email
     * @param int $excludeId
     * @return bool
     */
    public function emailExists($email, $excludeId = null) {
        $sql = "SELECT COUNT(*) as total FROM users WHERE email = ?";
        $params = [$email];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->db->selectOne($sql, $params);
        return $result && $result['total'] > 0;
    }
    
    /**
     * Verificar se usuário existe
     * @param int $id
     * @return bool
     */
    public function exists($id) {
        $sql = "SELECT COUNT(*) as total FROM users WHERE id = ?";
        $result = $this->db->selectOne($sql, [$id]);
        return $result && $result['total'] > 0;
    }
    
    /**
     * Obter atendentes disponíveis (ativos)
     * @return array
     */
    public function getAvailableAttendants() {
        $sql = "SELECT id, name, email, role FROM users 
                WHERE (role = 'attendant' OR role = 'technician') 
                AND is_active = 1 
                ORDER BY name ASC";
        return $this->db->select($sql);
    }
    
    /**
     * Obter estatísticas do usuário
     * @param int $userId
     * @return array
     */
    public function getStats($userId) {
        $stats = [
            'total_tickets' => 0,
            'open_tickets' => 0,
            'closed_tickets' => 0,
            'avg_resolution_time' => 0
        ];
        
        // Total de tickets assumidos
        $sql = "SELECT COUNT(*) as total FROM tickets WHERE assigned_user_id = ?";
        $result = $this->db->selectOne($sql, [$userId]);
        $stats['total_tickets'] = $result ? (int)$result['total'] : 0;
        
        // Tickets abertos
        $sql = "SELECT COUNT(*) as total FROM tickets 
                WHERE assigned_user_id = ? 
                AND status NOT IN ('closed', 'resolved')";
        $result = $this->db->selectOne($sql, [$userId]);
        $stats['open_tickets'] = $result ? (int)$result['total'] : 0;
        
        // Tickets fechados
        $sql = "SELECT COUNT(*) as total FROM tickets 
                WHERE assigned_user_id = ? 
                AND status IN ('closed', 'resolved')";
        $result = $this->db->selectOne($sql, [$userId]);
        $stats['closed_tickets'] = $result ? (int)$result['total'] : 0;
        
        return $stats;
    }
}
?>