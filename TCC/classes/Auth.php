<?php
/**
 * Classe Auth - Autenticação e Autorização
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 */

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Login de usuário (funcionário)
     * @param string $email
     * @param string $password
     * @return array ['success' => bool, 'message' => string]
     */
    public function loginUser($email, $password) {
        try {
            $sql = "SELECT * FROM users WHERE email = ? AND is_active = 1";
            $user = $this->db->selectOne($sql, [$email]);
            
            if (!$user) {
                logSystem("Tentativa de login falhou: email não encontrado - {$email}", "WARNING");
                return ['success' => false, 'message' => 'Email ou senha incorretos.'];
            }
            
            if (!password_verify($password, $user['password'])) {
                logSystem("Tentativa de login falhou: senha incorreta - {$email}", "WARNING");
                return ['success' => false, 'message' => 'Email ou senha incorretos.'];
            }
            
            // Criar sessão
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            
            logSystem("Login de usuário realizado: {$user['name']} ({$email})", "INFO");
            
            return ['success' => true, 'message' => 'Login realizado com sucesso!'];
            
        } catch (Exception $e) {
            logSystem("Erro no login de usuário: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao processar login.'];
        }
    }
    
    /**
     * Login de empresa (cliente)
     * @param string $email
     * @param string $password
     * @return array ['success' => bool, 'message' => string]
     */
    public function loginCompany($email, $password) {
        try {
            $sql = "SELECT * FROM companies WHERE email = ? AND is_active = 1";
            $company = $this->db->selectOne($sql, [$email]);
            
            if (!$company) {
                logSystem("Tentativa de login falhou: empresa não encontrada - {$email}", "WARNING");
                return ['success' => false, 'message' => 'Email ou senha incorretos.'];
            }
            
            if (!password_verify($password, $company['password'])) {
                logSystem("Tentativa de login falhou: senha incorreta - {$email}", "WARNING");
                return ['success' => false, 'message' => 'Email ou senha incorretos.'];
            }
            
            // Criar sessão
            $_SESSION['company_id'] = $company['id'];
            $_SESSION['company_name'] = $company['name'];
            $_SESSION['company_email'] = $company['email'];
            $_SESSION['last_activity'] = time();
            
            logSystem("Login de empresa realizado: {$company['name']} ({$email})", "INFO");
            
            return ['success' => true, 'message' => 'Login realizado com sucesso!'];
            
        } catch (Exception $e) {
            logSystem("Erro no login de empresa: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao processar login.'];
        }
    }
    
    /**
     * Logout do sistema
     * @return bool
     */
    public function logout() {
        $userName = $_SESSION['user_name'] ?? $_SESSION['company_name'] ?? 'Desconhecido';
        
        session_unset();
        session_destroy();
        
        logSystem("Logout realizado: {$userName}", "INFO");
        
        return true;
    }
    
    /**
     * Verificar se usuário está logado
     * @return bool
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) || isset($_SESSION['company_id']);
    }
    
    /**
     * Verificar se é usuário (funcionário)
     * @return bool
     */
    public function isUser() {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Verificar se é empresa
     * @return bool
     */
    public function isCompany() {
        return isset($_SESSION['company_id']);
    }
    
    /**
     * Verificar se é admin
     * @return bool
     */
    public function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
    
    /**
     * Obter ID do usuário logado
     * @return int|null
     */
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Obter ID da empresa logada
     * @return int|null
     */
    public function getCompanyId() {
        return $_SESSION['company_id'] ?? null;
    }
    
    /**
     * Alterar senha de usuário
     * @param int $userId
     * @param string $oldPassword
     * @param string $newPassword
     * @return array ['success' => bool, 'message' => string]
     */
    public function changeUserPassword($userId, $oldPassword, $newPassword) {
        try {
            $sql = "SELECT password FROM users WHERE id = ?";
            $user = $this->db->selectOne($sql, [$userId]);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Usuário não encontrado.'];
            }
            
            if (!password_verify($oldPassword, $user['password'])) {
                return ['success' => false, 'message' => 'Senha atual incorreta.'];
            }
            
            if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                return ['success' => false, 'message' => 'A senha deve ter no mínimo ' . PASSWORD_MIN_LENGTH . ' caracteres.'];
            }
            
            $hashedPassword = password_hash($newPassword, PASSWORD_HASH_ALGO);
            
            $sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
            $this->db->update($sql, [$hashedPassword, $userId]);
            
            logSystem("Senha alterada para usuário ID: {$userId}", "INFO");
            
            return ['success' => true, 'message' => 'Senha alterada com sucesso!'];
            
        } catch (Exception $e) {
            logSystem("Erro ao alterar senha: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao alterar senha.'];
        }
    }
    
    /**
     * Alterar senha de empresa
     * @param int $companyId
     * @param string $oldPassword
     * @param string $newPassword
     * @return array ['success' => bool, 'message' => string]
     */
    public function changeCompanyPassword($companyId, $oldPassword, $newPassword) {
        try {
            $sql = "SELECT password FROM companies WHERE id = ?";
            $company = $this->db->selectOne($sql, [$companyId]);
            
            if (!$company) {
                return ['success' => false, 'message' => 'Empresa não encontrada.'];
            }
            
            if (!password_verify($oldPassword, $company['password'])) {
                return ['success' => false, 'message' => 'Senha atual incorreta.'];
            }
            
            if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                return ['success' => false, 'message' => 'A senha deve ter no mínimo ' . PASSWORD_MIN_LENGTH . ' caracteres.'];
            }
            
            $hashedPassword = password_hash($newPassword, PASSWORD_HASH_ALGO);
            
            $sql = "UPDATE companies SET password = ?, updated_at = NOW() WHERE id = ?";
            $this->db->update($sql, [$hashedPassword, $companyId]);
            
            logSystem("Senha alterada para empresa ID: {$companyId}", "INFO");
            
            return ['success' => true, 'message' => 'Senha alterada com sucesso!'];
            
        } catch (Exception $e) {
            logSystem("Erro ao alterar senha: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao alterar senha.'];
        }
    }
    
    /**
     * Resetar senha (gerar nova senha aleatória)
     * @param string $email
     * @param string $type ('user' ou 'company')
     * @return array ['success' => bool, 'message' => string, 'password' => string]
     */
    public function resetPassword($email, $type = 'user') {
        try {
            $table = $type === 'user' ? 'users' : 'companies';
            $sql = "SELECT id, name FROM {$table} WHERE email = ?";
            $record = $this->db->selectOne($sql, [$email]);
            
            if (!$record) {
                return ['success' => false, 'message' => 'Email não encontrado.'];
            }
            
            $newPassword = generateRandomPassword(10);
            $hashedPassword = password_hash($newPassword, PASSWORD_HASH_ALGO);
            
            $sql = "UPDATE {$table} SET password = ?, updated_at = NOW() WHERE email = ?";
            $this->db->update($sql, [$hashedPassword, $email]);
            
            logSystem("Senha resetada para {$type}: {$email}", "INFO");
            
            return [
                'success' => true, 
                'message' => 'Senha resetada com sucesso!',
                'password' => $newPassword
            ];
            
        } catch (Exception $e) {
            logSystem("Erro ao resetar senha: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao resetar senha.'];
        }
    }
    
    /**
     * Verificar permissão do usuário
     * @param string $permission
     * @return bool
     */
    public function hasPermission($permission) {
        if (!$this->isUser()) {
            return false;
        }
        
        $role = $_SESSION['user_role'] ?? null;
        
        if (!$role || !isset(USER_PERMISSIONS[$role])) {
            return false;
        }
        
        return in_array($permission, USER_PERMISSIONS[$role]);
    }
    
    /**
     * Registrar tentativa de acesso não autorizado
     * @param string $page
     */
    public function logUnauthorizedAccess($page) {
        $userName = $_SESSION['user_name'] ?? $_SESSION['company_name'] ?? 'Visitante';
        $userType = $this->isUser() ? 'Usuário' : ($this->isCompany() ? 'Empresa' : 'Visitante');
        
        logSystem("Acesso não autorizado: {$userType} '{$userName}' tentou acessar {$page}", "WARNING");
    }
}
?>