<?php
/**
 * =====================================================
 * SCRIPT DE DEBUG - CRIAÇÃO DE USUÁRIOS DE TESTE
 * =====================================================
 * Este arquivo cria usuários e empresas de teste
 * com senhas conhecidas para facilitar o desenvolvimento
 * 
 * IMPORTANTE: REMOVER EM PRODUÇÃO!
 * =====================================================
 */

// Configuração do banco de dados
$host = 'localhost';
$dbname = 'sistema_suporte';
$username = 'root';
$password = '';

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Debug - Criar Usuários de Teste</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 2em;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            color: #721c24;
        }
        .info {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            color: #0c5460;
        }
        .credentials {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .credentials h3 {
            color: #495057;
            margin-bottom: 15px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .user-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .user-card h4 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        .credential-item {
            display: flex;
            margin: 8px 0;
            align-items: center;
        }
        .credential-label {
            font-weight: bold;
            width: 120px;
            color: #495057;
        }
        .credential-value {
            background: #e9ecef;
            padding: 5px 12px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            flex: 1;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
            margin-left: 10px;
        }
        .badge-admin { background: #dc3545; color: white; }
        .badge-attendant { background: #17a2b8; color: white; }
        .badge-technician { background: #28a745; color: white; }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border: 1px solid #dee2e6;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5568d3;
        }
        .section {
            margin: 30px 0;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }
    </style>
</head>
<body>
    <div class='container'>";

echo "<h1>🔧 Debug - Criar Usuários de Teste</h1>";
echo "<p style='color: #6c757d; margin-bottom: 20px;'>Sistema de Suporte - Criação de dados de teste para desenvolvimento</p>";

echo "<div class='warning'>
    <strong>⚠️ ATENÇÃO:</strong> Este arquivo é apenas para ambiente de desenvolvimento!<br>
    <strong>REMOVA ESTE ARQUIVO EM PRODUÇÃO!</strong>
</div>";

try {
    // Conectar ao banco
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='success'>✅ Conexão com banco de dados estabelecida com sucesso!</div>";
    
    // Função para criar hash de senha
    function createPasswordHash($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }
    
    // Limpar tabelas (opcional - comentar se não quiser limpar)
    echo "<div class='info'>🗑️ Limpando tabelas existentes...</div>";
    $pdo->exec("DELETE FROM feedbacks");
    $pdo->exec("DELETE FROM attachments");
    $pdo->exec("DELETE FROM notifications");
    $pdo->exec("DELETE FROM ticket_logs");
    $pdo->exec("DELETE FROM tickets");
    $pdo->exec("DELETE FROM companies");
    $pdo->exec("DELETE FROM users");
    
    // Resetar AUTO_INCREMENT
    $pdo->exec("ALTER TABLE users AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE companies AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE tickets AUTO_INCREMENT = 1");
    
    echo "<div class='success'>✅ Tabelas limpas com sucesso!</div>";
    
    // =====================================================
    // CRIAR USUÁRIOS (FUNCIONÁRIOS)
    // =====================================================
    echo "<div class='section'>";
    echo "<h2>👥 Criando Usuários (Funcionários)</h2>";
    
    $users = [
        [
            'name' => 'Administrador do Sistema',
            'email' => 'admin@sistema.com',
            'password' => 'admin123',
            'role' => 'admin',
            'phone' => '(14) 99999-0001'
        ],
        [
            'name' => 'Carlos Atendente',
            'email' => 'atendente@sistema.com',
            'password' => 'atendente123',
            'role' => 'attendant',
            'phone' => '(14) 99999-0002'
        ],
        [
            'name' => 'João Técnico',
            'email' => 'tecnico@sistema.com',
            'password' => 'tecnico123',
            'role' => 'technician',
            'phone' => '(14) 99999-0003'
        ],
        [
            'name' => 'Maria Santos',
            'email' => 'maria@sistema.com',
            'password' => 'senha123',
            'role' => 'attendant',
            'phone' => '(14) 99999-0004'
        ],
        [
            'name' => 'Pedro Silva',
            'email' => 'pedro@sistema.com',
            'password' => 'senha123',
            'role' => 'technician',
            'phone' => '(14) 99999-0005'
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role, phone, is_active) 
        VALUES (:name, :email, :password, :role, :phone, 1)
    ");
    
    $userCount = 0;
    foreach ($users as $user) {
        $stmt->execute([
            'name' => $user['name'],
            'email' => $user['email'],
            'password' => createPasswordHash($user['password']),
            'role' => $user['role'],
            'phone' => $user['phone']
        ]);
        $userCount++;
        echo "<div class='success'>✅ Usuário criado: {$user['name']} ({$user['role']})</div>";
    }
    
    echo "</div>";
    
    // =====================================================
    // CRIAR EMPRESAS (CLIENTES)
    // =====================================================
    echo "<div class='section'>";
    echo "<h2>🏢 Criando Empresas (Clientes)</h2>";
    
    $companies = [
        [
            'name' => 'Empresa ABC Ltda',
            'cnpj' => '12.345.678/0001-90',
            'email' => 'empresa1@email.com',
            'password' => 'empresa123',
            'phone' => '(14) 3333-0001',
            'address' => 'Rua das Flores, 123',
            'city' => 'Marília',
            'state' => 'SP',
            'zip_code' => '17500-000'
        ],
        [
            'name' => 'Tech Solutions',
            'cnpj' => '98.765.432/0001-10',
            'email' => 'empresa2@email.com',
            'password' => 'empresa123',
            'phone' => '(14) 3333-0002',
            'address' => 'Av. Principal, 456',
            'city' => 'Marília',
            'state' => 'SP',
            'zip_code' => '17501-000'
        ],
        [
            'name' => 'Comercial XYZ',
            'cnpj' => '11.222.333/0001-44',
            'email' => 'empresa3@email.com',
            'password' => 'empresa123',
            'phone' => '(14) 3333-0003',
            'address' => 'Rua do Comércio, 789',
            'city' => 'Marília',
            'state' => 'SP',
            'zip_code' => '17502-000'
        ],
        [
            'name' => 'Indústria Delta',
            'cnpj' => '22.333.444/0001-55',
            'email' => 'empresa4@email.com',
            'password' => 'empresa123',
            'phone' => '(14) 3333-0004',
            'address' => 'Distrito Industrial, 100',
            'city' => 'Marília',
            'state' => 'SP',
            'zip_code' => '17503-000'
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO companies (name, cnpj, email, password, phone, address, city, state, zip_code, is_active) 
        VALUES (:name, :cnpj, :email, :password, :phone, :address, :city, :state, :zip_code, 1)
    ");
    
    $companyCount = 0;
    foreach ($companies as $company) {
        $stmt->execute([
            'name' => $company['name'],
            'cnpj' => $company['cnpj'],
            'email' => $company['email'],
            'password' => createPasswordHash($company['password']),
            'phone' => $company['phone'],
            'address' => $company['address'],
            'city' => $company['city'],
            'state' => $company['state'],
            'zip_code' => $company['zip_code']
        ]);
        $companyCount++;
        echo "<div class='success'>✅ Empresa criada: {$company['name']}</div>";
    }
    
    echo "</div>";
    
    // =====================================================
    // CRIAR TICKETS DE EXEMPLO
    // =====================================================
    echo "<div class='section'>";
    echo "<h2>🎫 Criando Tickets de Exemplo</h2>";
    
    $tickets = [
        [
            'company_id' => 1,
            'title' => 'Computador não liga',
            'description' => 'O computador da recepção não está ligando após queda de energia',
            'category' => 'computer',
            'priority' => 'high',
            'address' => 'Rua das Flores, 123 - Recepção'
        ],
        [
            'company_id' => 1,
            'title' => 'Impressora com defeito',
            'description' => 'Impressora não está imprimindo documentos',
            'category' => 'printer',
            'priority' => 'medium',
            'address' => 'Rua das Flores, 123 - Setor Administrativo'
        ],
        [
            'company_id' => 2,
            'title' => 'Internet lenta',
            'description' => 'Velocidade da internet muito abaixo do contratado',
            'category' => 'network',
            'priority' => 'low',
            'address' => 'Av. Principal, 456'
        ],
        [
            'company_id' => 2,
            'title' => 'Servidor fora do ar',
            'description' => 'Servidor principal não está respondendo',
            'category' => 'server',
            'priority' => 'high',
            'address' => 'Av. Principal, 456 - Data Center'
        ],
        [
            'company_id' => 3,
            'title' => 'Celular com tela quebrada',
            'description' => 'Celular corporativo caiu e quebrou a tela',
            'category' => 'mobile',
            'priority' => 'medium',
            'address' => 'Rua do Comércio, 789'
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO tickets (company_id, title, description, category, priority, address, status) 
        VALUES (:company_id, :title, :description, :category, :priority, :address, 'created')
    ");
    
    $ticketCount = 0;
    foreach ($tickets as $ticket) {
        $stmt->execute($ticket);
        $ticketCount++;
        echo "<div class='success'>✅ Ticket criado: {$ticket['title']}</div>";
    }
    
    echo "</div>";
    
    // =====================================================
    // ESTATÍSTICAS
    // =====================================================
    echo "<div class='section'>";
    echo "<h2>📊 Estatísticas</h2>";
    echo "<div class='stats'>
        <div class='stat-card'>
            <div class='stat-label'>Usuários</div>
            <div class='stat-number'>$userCount</div>
        </div>
        <div class='stat-card'>
            <div class='stat-label'>Empresas</div>
            <div class='stat-number'>$companyCount</div>
        </div>
        <div class='stat-card'>
            <div class='stat-label'>Tickets</div>
            <div class='stat-number'>$ticketCount</div>
        </div>
    </div>";
    echo "</div>";
    
    // =====================================================
    // CREDENCIAIS DE ACESSO
    // =====================================================
    echo "<div class='section'>";
    echo "<h2>🔐 Credenciais de Acesso</h2>";
    
    echo "<div class='credentials'>";
    echo "<h3>👥 Funcionários (Login em ?page=login)</h3>";
    
    foreach ($users as $user) {
        $badgeClass = 'badge-' . $user['role'];
        $roleLabel = [
            'admin' => 'ADMIN',
            'attendant' => 'ATENDENTE',
            'technician' => 'TÉCNICO'
        ][$user['role']];
        
        echo "<div class='user-card'>
            <h4>{$user['name']} <span class='badge $badgeClass'>$roleLabel</span></h4>
            <div class='credential-item'>
                <span class='credential-label'>📧 Email:</span>
                <span class='credential-value'>{$user['email']}</span>
            </div>
            <div class='credential-item'>
                <span class='credential-label'>🔑 Senha:</span>
                <span class='credential-value'>{$user['password']}</span>
            </div>
            <div class='credential-item'>
                <span class='credential-label'>📱 Telefone:</span>
                <span class='credential-value'>{$user['phone']}</span>
            </div>
        </div>";
    }
    
    echo "</div>";
    
    echo "<div class='credentials' style='margin-top: 20px;'>";
    echo "<h3>🏢 Empresas (Login em ?page=login-company)</h3>";
    
    foreach ($companies as $company) {
        echo "<div class='user-card'>
            <h4>{$company['name']}</h4>
            <div class='credential-item'>
                <span class='credential-label'>📧 Email:</span>
                <span class='credential-value'>{$company['email']}</span>
            </div>
            <div class='credential-item'>
                <span class='credential-label'>🔑 Senha:</span>
                <span class='credential-value'>{$company['password']}</span>
            </div>
            <div class='credential-item'>
                <span class='credential-label'>📄 CNPJ:</span>
                <span class='credential-value'>{$company['cnpj']}</span>
            </div>
            <div class='credential-item'>
                <span class='credential-label'>📱 Telefone:</span>
                <span class='credential-value'>{$company['phone']}</span>
            </div>
        </div>";
    }
    
    echo "</div>";
    echo "</div>";
    
    // =====================================================
    // LINKS ÚTEIS
    // =====================================================
    echo "<div class='section'>";
    echo "<h2>🔗 Links Úteis</h2>";
    echo "<div style='margin: 20px 0;'>
        <a href='?page=login' class='btn'>🔐 Login Funcionários</a>
        <a href='?page=login-company' class='btn'>🏢 Login Empresas</a>
        <a href='index.php' class='btn'>🏠 Ir para Home</a>
    </div>";
    echo "</div>";
    
    echo "<div class='success' style='margin-top: 30px;'>
        <strong>🎉 Sucesso!</strong><br>
        Todos os usuários de teste foram criados com sucesso!<br>
        Agora você pode fazer login com qualquer uma das credenciais acima.
    </div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>
        <strong>❌ Erro ao conectar com o banco de dados:</strong><br>
        {$e->getMessage()}
    </div>";
    
    echo "<div class='info'>
        <strong>💡 Dica:</strong> Verifique se:
        <ul style='margin-top: 10px; margin-left: 20px;'>
            <li>O MySQL está rodando</li>
            <li>As credenciais estão corretas</li>
            <li>O banco 'sistema_suporte' existe</li>
            <li>As tabelas foram criadas (execute o BD.sql)</li>
        </ul>
    </div>";
}

echo "</div></body></html>";
?>