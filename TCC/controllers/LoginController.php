<?php
/**
 * LoginController - VERSÃO CORRIGIDA FINAL
 * Sistema de Suporte e Manutenção
 */

// =====================================================
// INCLUIR ARQUIVOS NA ORDEM CORRETA
// =====================================================

// 1. Paths primeiro (define caminhos e carrega autoload)
require_once __DIR__ . '/../config/paths.php';

// 2. Config (configurações do sistema)
require_once __DIR__ . '/../config/config.php';

// 3. Database config
require_once __DIR__ . '/../config/database.php';

// 4. Functions (funções auxiliares)
require_once __DIR__ . '/../includes/functions.php';

// =====================================================
// VERIFICAR SE JÁ ESTÁ LOGADO
// =====================================================


// =====================================================
// PROCESSAR LOGIN (POST)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verificar CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('Token de segurança inválido.', 'danger');
        header('Location: ' . BASE_URL . 'views/auth/login.php');
        exit;
    }
    
    // Coletar dados
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $userType = $_POST['user_type'] ?? 'user'; // 'user' ou 'company'
    
    // Validações básicas
    if (empty($email) || empty($password)) {
        setFlashMessage('Preencha todos os campos.', 'warning');
        header('Location: ' . BASE_URL . 'views/auth/login.php');
        exit;
    }
    
    try {
        // Obter conexão PDO
        $pdo = getConnection();
        
        if ($userType === 'company') {
            // ========================================
            // LOGIN DE EMPRESA
            // ========================================
            
            $stmt = $pdo->prepare("SELECT * FROM companies WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $company = $stmt->fetch();
            
            if (!$company) {
                logSystem("Tentativa de login falhou: empresa não encontrada - {$email}", "WARNING");
                setFlashMessage('Email ou senha incorretos.', 'danger');
                header('Location: ' . BASE_URL . 'views/auth/login.php');
                exit;
            }
            
            // Verificar senha
            if (!password_verify($password, $company['password'])) {
                logSystem("Tentativa de login falhou: senha incorreta - {$email}", "WARNING");
                setFlashMessage('Email ou senha incorretos.', 'danger');
                header('Location: ' . BASE_URL . 'views/auth/login.php');
                exit;
            }
            
            // Login bem-sucedido! Criar sessão
            $_SESSION['company_id'] = $company['id'];
            $_SESSION['company_name'] = $company['name'];
            $_SESSION['company_email'] = $company['email'];
            $_SESSION['last_activity'] = time();
            
            logSystem("Login de empresa realizado: {$company['name']} ({$email})", "INFO");
            
            setFlashMessage('Login realizado com sucesso! Bem-vindo(a), ' . $company['name'], 'success');
            header('Location: ' . BASE_URL . 'views/dashboard/index.php');
            exit;
            
        } else {
            // ========================================
            // LOGIN DE USUÁRIO (FUNCIONÁRIO)
            // ========================================
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                logSystem("Tentativa de login falhou: usuário não encontrado - {$email}", "WARNING");
                setFlashMessage('Email ou senha incorretos.', 'danger');
                header('Location: ' . BASE_URL . 'views/auth/login.php');
                exit;
            }
            
            // Verificar senha
            if (!password_verify($password, $user['password'])) {
                logSystem("Tentativa de login falhou: senha incorreta - {$email}", "WARNING");
                setFlashMessage('Email ou senha incorretos.', 'danger');
                header('Location: ' . BASE_URL . 'views/auth/login.php');
                exit;
            }
            
            // Login bem-sucedido! Criar sessão
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            
            logSystem("Login de usuário realizado: {$user['name']} ({$email})", "INFO");
            
            setFlashMessage('Login realizado com sucesso! Bem-vindo(a), ' . $user['name'], 'success');
            header('Location: ' . BASE_URL . 'views/dashboard/index.php');
            exit;
        }
        
    } catch (PDOException $e) {
        // Erro de banco de dados
        error_log("ERRO NO LOGIN: " . $e->getMessage());
        logSystem("Erro no login: " . $e->getMessage(), "ERROR");
        
        setFlashMessage('Erro ao processar login. Tente novamente.', 'danger');
        header('Location: ' . BASE_URL . 'views/auth/login.php');
        exit;
        
    } catch (Exception $e) {
        // Erro genérico
        error_log("ERRO GENÉRICO NO LOGIN: " . $e->getMessage());
        logSystem("Erro no login: " . $e->getMessage(), "ERROR");
        
        setFlashMessage('Erro ao processar login. Tente novamente.', 'danger');
        header('Location: ' . BASE_URL . 'views/auth/login.php');
        exit;
    }
}

// =====================================================
// SE NÃO FOR POST, REDIRECIONAR PARA LOGIN
// =====================================================
header('Location: ' . BASE_URL . 'views/auth/login.php');
exit;