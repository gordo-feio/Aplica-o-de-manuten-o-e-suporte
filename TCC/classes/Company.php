<?php
/**
 * Classe Company - Gerenciamento de Empresas (Clientes)
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 */

class Company {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Criar nova empresa
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
            
            if (!empty($data['cnpj']) && !validateCNPJ($data['cnpj'])) {
                return ['success' => false, 'message' => 'CNPJ inválido.'];
            }
            
            if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
                return ['success' => false, 'message' => 'A senha deve ter no mínimo ' . PASSWORD_MIN_LENGTH . ' caracteres.'];
            }
            
            // Verificar se email já existe
            if ($this->emailExists($data['email'])) {
                return ['success' => false, 'message' => 'Este email já está cadastrado.'];
            }
            
            // Verificar se CNPJ já existe
            if (!empty($data['cnpj']) && $this->cnpjExists($data['cnpj'])) {
                return ['success' => false, 'message' => 'Este CNPJ já está cadastrado.'];
            }
            
            // Hash da senha
            $hashedPassword = password_hash($data['password'], PASSWORD_HASH_ALGO);
            
            // Inserir no banco
            $sql = "INSERT INTO companies (name, cnpj, email, password, phone, address, city, state, zip_code, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $data['name'],
                $data['cnpj'] ?? null,
                $data['email'],
                $hashedPassword,
                $data['phone'] ?? null,
                $data['address'] ?? null,
                $data['city'] ?? null,
                $data['state'] ?? null,
                $data['zip_code'] ?? null,
                isset($data['is_active']) ? (int)$data['is_active'] : 1
            ];
            
            $companyId = $this->db->insert($sql, $params);
            
            if ($companyId) {
                logSystem("Empresa criada: {$data['name']} ({$data['email']})", "INFO");
                return [
                    'success' => true, 
                    'message' => 'Empresa cadastrada com sucesso!',
                    'id' => $companyId
                ];
            }
            
            return ['success' => false, 'message' => 'Erro ao cadastrar empresa.'];
            
        } catch (Exception $e) {
            logSystem("Erro ao criar empresa: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao cadastrar empresa.'];
        }
    }
    
    /**
     * Atualizar empresa
     * @param int $id
     * @param array $data
     * @return array ['success' => bool, 'message' => string]
     */
    public function update($id, $data) {
        try {
            // Verificar se empresa existe
            if (!$this->exists($id)) {
                return ['success' => false, 'message' => 'Empresa não encontrada.'];
            }
            
            // Validações
            if (!empty($data['email']) && !validateEmail($data['email'])) {
                return ['success' => false, 'message' => 'Email inválido.'];
            }
            
            if (!empty($data['cnpj']) && !validateCNPJ($data['cnpj'])) {
                return ['success' => false, 'message' => 'CNPJ inválido.'];
            }
            
            // Verificar se email já existe (exceto para a própria empresa)
            if (!empty($data['email']) && $this->emailExists($data['email'], $id)) {
                return ['success' => false, 'message' => 'Este email já está cadastrado.'];
            }
            
            // Verificar se CNPJ já existe (exceto para a própria empresa)
            if (!empty($data['cnpj']) && $this->cnpjExists($data['cnpj'], $id)) {
                return ['success' => false, 'message' => 'Este CNPJ já está cadastrado.'];
            }
            
            // Construir SQL dinamicamente
            $fields = [];
            $params = [];
            
            if (!empty($data['name'])) {
                $fields[] = "name = ?";
                $params[] = $data['name'];
            }
            
            if (!empty($data['cnpj'])) {
                $fields[] = "cnpj = ?";
                $params[] = $data['cnpj'];
            }
            
            if (!empty($data['email'])) {
                $fields[] = "email = ?";
                $params[] = $data['email'];
            }
            
            if (!empty($data['phone'])) {
                $fields[] = "phone = ?";
                $params[] = $data['phone'];
            }
            
            if (!empty($data['address'])) {
                $fields[] = "address = ?";
                $params[] = $data['address'];
            }
            
            if (!empty($data['city'])) {
                $fields[] = "city = ?";
                $params[] = $data['city'];
            }
            
            if (!empty($data['state'])) {
                $fields[] = "state = ?";
                $params[] = $data['state'];
            }
            
            if (!empty($data['zip_code'])) {
                $fields[] = "zip_code = ?";
                $params[] = $data['zip_code'];
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
            
            $sql = "UPDATE companies SET " . implode(', ', $fields) . " WHERE id = ?";
            $this->db->update($sql, $params);
            
            logSystem("Empresa atualizada: ID {$id}", "INFO");
            
            return ['success' => true, 'message' => 'Empresa atualizada com sucesso!'];
            
        } catch (Exception $e) {
            logSystem("Erro ao atualizar empresa: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao atualizar empresa.'];
        }
    }
    
    /**
     * Deletar empresa
     * @param int $id
     * @return array ['success' => bool, 'message' => string]
     */
    public function delete($id) {
        try {
            if (!$this->exists($id)) {
                return ['success' => false, 'message' => 'Empresa não encontrada.'];
            }
            
            $sql = "DELETE FROM companies WHERE id = ?";
            $this->db->delete($sql, [$id]);
            
            logSystem("Empresa deletada: ID {$id}", "WARNING");
            
            return ['success' => true, 'message' => 'Empresa deletada com sucesso!'];
            
        } catch (Exception $e) {
            logSystem("Erro ao deletar empresa: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao deletar empresa.'];
        }
    }
    
    /**
     * Obter empresa por ID
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        $sql = "SELECT * FROM companies WHERE id = ?";
        return $this->db->selectOne($sql, [$id]);
    }
    
    /**
     * Obter todas as empresas
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAll($filters = [], $limit = null, $offset = 0) {
        $sql = "SELECT * FROM companies WHERE 1=1";
        $params = [];
        
        // Filtros
        if (isset($filters['is_active'])) {
            $sql .= " AND is_active = ?";
            $params[] = (int)$filters['is_active'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (name LIKE ? OR email LIKE ? OR cnpj LIKE ?)";
            $searchTerm = '%' . $this->db->escapeLike($filters['search']) . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['city'])) {
            $sql .= " AND city = ?";
            $params[] = $filters['city'];
        }
        
        if (!empty($filters['state'])) {
            $sql .= " AND state = ?";
            $params[] = $filters['state'];
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
     * Contar empresas
     * @param array $filters
     * @return int
     */
    public function count($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM companies WHERE 1=1";
        $params = [];
        
        if (isset($filters['is_active'])) {
            $sql .= " AND is_active = ?";
            $params[] = (int)$filters['is_active'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (name LIKE ? OR email LIKE ? OR cnpj LIKE ?)";
            $searchTerm = '%' . $this->db->escapeLike($filters['search']) . '%';
            $params[] = $searchTerm;
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
        $sql = "SELECT COUNT(*) as total FROM companies WHERE email = ?";
        $params = [$email];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->db->selectOne($sql, $params);
        return $result && $result['total'] > 0;
    }
    
    /**
     * Verificar se CNPJ já existe
     * @param string $cnpj
     * @param int $excludeId
     * @return bool
     */
    public function cnpjExists($cnpj, $excludeId = null) {
        $sql = "SELECT COUNT(*) as total FROM companies WHERE cnpj = ?";
        $params = [$cnpj];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->db->selectOne($sql, $params);
        return $result && $result['total'] > 0;
    }
    
    /**
     * Verificar se empresa existe
     * @param int $id
     * @return bool
     */
    public function exists($id) {
        $sql = "SELECT COUNT(*) as total FROM companies WHERE id = ?";
        $result = $this->db->selectOne($sql, [$id]);
        return $result && $result['total'] > 0;
    }
    
    /**
     * Obter estatísticas da empresa
     * @param int $companyId
     * @return array
     */
    public function getStats($companyId) {
        $stats = [
            'total_tickets' => 0,
            'open_tickets' => 0,
            'closed_tickets' => 0,
            'high_priority' => 0
        ];
        
        // Total de tickets
        $sql = "SELECT COUNT(*) as total FROM tickets WHERE company_id = ?";
        $result = $this->db->selectOne($sql, [$companyId]);
        $stats['total_tickets'] = $result ? (int)$result['total'] : 0;
        
        // Tickets abertos
        $sql = "SELECT COUNT(*) as total FROM tickets 
                WHERE company_id = ? 
                AND status NOT IN ('closed', 'resolved')";
        $result = $this->db->selectOne($sql, [$companyId]);
        $stats['open_tickets'] = $result ? (int)$result['total'] : 0;
        
        // Tickets fechados
        $sql = "SELECT COUNT(*) as total FROM tickets 
                WHERE company_id = ? 
                AND status IN ('closed', 'resolved')";
        $result = $this->db->selectOne($sql, [$companyId]);
        $stats['closed_tickets'] = $result ? (int)$result['total'] : 0;
        
        // Tickets de alta prioridade
        $sql = "SELECT COUNT(*) as total FROM tickets 
                WHERE company_id = ? 
                AND priority = 'high'
                AND status NOT IN ('closed', 'resolved')";
        $result = $this->db->selectOne($sql, [$companyId]);
        $stats['high_priority'] = $result ? (int)$result['total'] : 0;
        
        return $stats;
    }
}
?>