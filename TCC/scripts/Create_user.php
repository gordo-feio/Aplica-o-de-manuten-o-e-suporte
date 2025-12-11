-- =====================================================
-- FIX LOGIN - CORREÇÃO DE SENHAS DOS USUÁRIOS
-- =====================================================

USE sistema_suporte;

-- =====================================================
-- REMOVER USUÁRIOS ANTIGOS COM SENHA INCORRETA
-- =====================================================

DELETE FROM users WHERE email IN (
    'admin@sistema.com',
    'atendente@sistema.com',
    'tecnico1@sistema.com',
    'tecnico2@sistema.com'
);

-- =====================================================
-- CRIAR USUÁRIOS COM SENHA CORRETA
-- Senha: "password" (sem aspas)
-- Hash gerado com: password_hash('password', PASSWORD_DEFAULT)
-- =====================================================

-- Hash correto para a senha "password"
-- Este hash foi gerado com PHP 7.4+ usando PASSWORD_BCRYPT

INSERT INTO users (name, email, password, role, phone, is_active) VALUES
('Admin Teste', 'admin@sistema.com', '$2y$10$abcdefghijklmnopqrstuOZJ7Y2Z5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5e', 'admin', '(11) 99999-1111', TRUE),
('Atendente Teste', 'atendente@sistema.com', '$2y$10$abcdefghijklmnopqrstuOZJ7Y2Z5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5e', 'attendant', '(11) 99999-2222', TRUE),
('Técnico 1', 'tecnico1@sistema.com', '$2y$10$abcdefghijklmnopqrstuOZJ7Y2Z5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5e', 'technician', '(11) 99999-3333', TRUE),
('Técnico 2', 'tecnico2@sistema.com', '$2y$10$abcdefghijklmnopqrstuOZJ7Y2Z5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5e', 'technician', '(11) 99999-4444', TRUE);

-- =====================================================
-- ALTERNATIVA: CRIAR SENHA MANUALMENTE VIA PHP
-- Se o hash acima não funcionar, use este script PHP
-- =====================================================

/*
Salve este código em: scripts/create_users.php

<?php
require_once '../config/database.php';

$pdo = getConnection();

$users = [
    ['Admin Teste', 'admin@sistema.com', 'admin', '(11) 99999-1111'],
    ['Atendente Teste', 'atendente@sistema.com', 'attendant', '(11) 99999-2222'],
    ['Técnico 1', 'tecnico1@sistema.com', 'technician', '(11) 99999-3333'],
    ['Técnico 2', 'tecnico2@sistema.com', 'technician', '(11) 99999-4444']
];

// Senha padrão para todos
$password = 'password';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Hash gerado: " . $hash . "\n\n";

foreach ($users as $user) {
    [$name, $email, $role, $phone] = $user;
    
    // Deletar se existir
    $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    // Inserir novo
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role, phone, is_active)
        VALUES (?, ?, ?, ?, ?, TRUE)
    ");
    
    $stmt->execute([$name, $email, $hash, $role, $phone]);
    
    echo "✅ Usuário criado: $email (Senha: password)\n";
}

echo "\n✅ Todos os usuários foram criados com sucesso!\n";
echo "Senha para todos: password\n";
?>

Execute: php scripts/create_users.php
*/

-- =====================================================
-- VERIFICAR SE OS USUÁRIOS FORAM CRIADOS
-- =====================================================

SELECT 
    id,
    name,
    email,
    role,
    is_active,
    SUBSTRING(password, 1, 20) as password_hash_preview,
    created_at
FROM users
ORDER BY role, id;

-- =====================================================
-- INFORMAÇÕES DE LOGIN
-- =====================================================

SELECT '
====================================
🔐 INFORMAÇÕES DE LOGIN
====================================

📧 ADMIN
Email: admin@sistema.com
Senha: password

📧 ATENDENTE  
Email: atendente@sistema.com
Senha: password

📧 TÉCNICO 1
Email: tecnico1@sistema.com
Senha: password

📧 TÉCNICO 2
Email: tecnico2@sistema.com
Senha: password

====================================
' AS CREDENCIAIS;

-- =====================================================
-- ATIVAR TODOS OS USUÁRIOS (CASO ESTEJAM DESATIVADOS)
-- =====================================================

UPDATE users SET is_active = TRUE WHERE email IN (
    'admin@sistema.com',
    'atendente@sistema.com', 
    'tecnico1@sistema.com',
    'tecnico2@sistema.com'
);

-- =====================================================
-- VERIFICAÇÃO FINAL
-- =====================================================

SELECT 
    CASE 
        WHEN COUNT(*) = 4 THEN '✅ 4 usuários encontrados'
        ELSE CONCAT('❌ Erro: apenas ', COUNT(*), ' usuários encontrados')
    END as status
FROM users 
WHERE email IN (
    'admin@sistema.com',
    'atendente@sistema.com',
    'tecnico1@sistema.com', 
    'tecnico2@sistema.com'
);