<?php
/**
 * SCRIPT DE TESTE DE CONEXÃO E SENHAS
 * Coloque este arquivo na raiz do projeto: /TCC/test_connection.php
 * Acesse: http://localhost/TCC/test_connection.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Teste de Conexão</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .card { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        h2 { border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .hash { font-family: monospace; font-size: 0.8em; word-break: break-all; }
    </style>
</head>
<body>";

echo "<h1>🔍 Teste de Conexão e Senhas - Sistema de Suporte</h1>";

// =====================================================
// TESTE 1: CONEXÃO COM BANCO DE DADOS
// =====================================================
echo "<div class='card'>";
echo "<h2>1️⃣ Teste de Conexão com Banco de Dados</h2>";

$host = 'localhost';
$dbname = 'sistema_suporte';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p class='success'>✅ Conexão com banco de dados estabelecida com sucesso!</p>";
    echo "<p class='info'>Host: {$host}</p>";
    echo "<p class='info'>Database: {$dbname}</p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ ERRO ao conectar com banco de dados:</p>";
    echo "<p class='error'>" . $e->getMessage() . "</p>";
    echo "</div></body></html>";
    exit;
}

echo "</div>";

// =====================================================
// TESTE 2: VERIFICAR USUÁRIOS
// =====================================================
echo "<div class='card'>";
echo "<h2>2️⃣ Usuários Cadastrados</h2>";

try {
    $stmt = $pdo->query("SELECT id, name, email, role, is_active, password FROM users ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "<p class='error'>❌ Nenhum usuário encontrado! Execute o debug.php para criar usuários de teste.</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Role</th><th>Ativo</th><th>Senha (Hash)</th><th>Teste de Login</th></tr>";
        
        foreach ($users as $user) {
            $activeIcon = $user['is_active'] ? '✅' : '❌';
            $testSenhas = ['admin123', 'atendente123', 'tecnico123', 'senha123'];
            $senhaCorreta = 'Nenhuma testada funciona';
            
            foreach ($testSenhas as $testPw) {
                if (password_verify($testPw, $user['password'])) {
                    $senhaCorreta = "<span class='success'>✅ Senha: <strong>{$testPw}</strong></span>";
                    break;
                }
            }
            
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['name']}</td>";
            echo "<td><strong>{$user['email']}</strong></td>";
            echo "<td>{$user['role']}</td>";
            echo "<td>{$activeIcon}</td>";
            echo "<td class='hash'>" . substr($user['password'], 0, 30) . "...</td>";
            echo "<td>{$senhaCorreta}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        echo "<p class='success'>Total de usuários: " . count($users) . "</p>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Erro ao buscar usuários: " . $e->getMessage() . "</p>";
}

echo "</div>";

// =====================================================
// TESTE 3: VERIFICAR EMPRESAS
// =====================================================
echo "<div class='card'>";
echo "<h2>3️⃣ Empresas Cadastradas</h2>";

try {
    $stmt = $pdo->query("SELECT id, name, email, cnpj, is_active, password FROM companies ORDER BY id");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($companies)) {
        echo "<p class='error'>❌ Nenhuma empresa encontrada! Execute o debug.php para criar empresas de teste.</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>CNPJ</th><th>Ativo</th><th>Teste de Login</th></tr>";
        
        foreach ($companies as $company) {
            $activeIcon = $company['is_active'] ? '✅' : '❌';
            $senhaCorreta = password_verify('empresa123', $company['password']) 
                ? "<span class='success'>✅ Senha: <strong>empresa123</strong></span>" 
                : "<span class='error'>❌ Senha incorreta</span>";
            
            echo "<tr>";
            echo "<td>{$company['id']}</td>";
            echo "<td>{$company['name']}</td>";
            echo "<td><strong>{$company['email']}</strong></td>";
            echo "<td>{$company['cnpj']}</td>";
            echo "<td>{$activeIcon}</td>";
            echo "<td>{$senhaCorreta}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        echo "<p class='success'>Total de empresas: " . count($companies) . "</p>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Erro ao buscar empresas: " . $e->getMessage() . "</p>";
}

echo "</div>";

// =====================================================
// TESTE 4: VERIFICAR FUNÇÕES DE SENHA
// =====================================================
echo "<div class='card'>";
echo "<h2>4️⃣ Teste de Funções de Senha</h2>";

$testPassword = 'admin123';
$hash = password_hash($testPassword, PASSWORD_DEFAULT);

echo "<p><strong>Senha teste:</strong> {$testPassword}</p>";
echo "<p><strong>Hash gerado:</strong> <span class='hash'>{$hash}</span></p>";
echo "<p><strong>Verificação:</strong> ";

if (password_verify($testPassword, $hash)) {
    echo "<span class='success'>✅ Função password_verify() está funcionando corretamente!</span>";
} else {
    echo "<span class='error'>❌ Função password_verify() NÃO está funcionando!</span>";
}

echo "</p></div>";

// =====================================================
// TESTE 5: SIMULAR LOGIN
// =====================================================
echo "<div class='card'>";
echo "<h2>5️⃣ Simulação de Login</h2>";

$testEmail = 'admin@sistema.com';
$testPw = 'admin123';

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$testEmail]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "<p class='error'>❌ Usuário '{$testEmail}' não encontrado ou inativo</p>";
    } else {
        echo "<p class='info'>📧 Email encontrado: {$user['email']}</p>";
        echo "<p class='info'>👤 Nome: {$user['name']}</p>";
        echo "<p class='info'>🎖️ Role: {$user['role']}</p>";
        echo "<p class='info'>✅ Ativo: " . ($user['is_active'] ? 'Sim' : 'Não') . "</p>";
        echo "<p><strong>Testando senha '{$testPw}':</strong> ";
        
        if (password_verify($testPw, $user['password'])) {
            echo "<span class='success'>✅ SENHA CORRETA! Login funcionaria!</span>";
        } else {
            echo "<span class='error'>❌ SENHA INCORRETA!</span>";
        }
        echo "</p>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Erro: " . $e->getMessage() . "</p>";
}

echo "</div>";

// =====================================================
// INSTRUÇÕES
// =====================================================
echo "<div class='card'>";
echo "<h2>📋 Instruções</h2>";
echo "<ol>";
echo "<li>Se todos os testes acima passarem (✅), o sistema deveria funcionar normalmente</li>";
echo "<li>Se algum usuário não tiver senha correta, execute <strong>debug.php</strong> novamente</li>";
echo "<li>Verifique se os emails estão escritos corretamente (case-sensitive)</li>";
echo "<li>Tente fazer login com as credenciais que apareceram como ✅ acima</li>";
echo "<li>Se ainda não funcionar, verifique o arquivo <code>controllers/LoginController.php</code></li>";
echo "</ol>";
echo "</div>";

echo "<div class='card'>";
echo "<p><strong>🔗 Links Úteis:</strong></p>";
echo "<p><a href='debug.php'>Executar debug.php (Recriar usuários)</a></p>";
echo "<p><a href='views/auth/login.php'>Ir para página de Login</a></p>";
echo "</div>";

echo "</body></html>";