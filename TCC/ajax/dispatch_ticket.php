<?php
/**
 * SISTEMA DE SUPORTE E MANUTENÇÃO
 * Arquivo: ajax/dispatch_ticket.php
 * Descrição: Despachar equipe (técnico) para atendimento via AJAX
 * VERSÃO COM SELEÇÃO DE TÉCNICO
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Verificar autenticação
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuário não autenticado');
    }

    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Erro ao decodificar JSON: ' . json_last_error_msg());
    }

    // Validar dados
    if (!isset($data['ticket_id']) || !is_numeric($data['ticket_id'])) {
        throw new Exception('ID do ticket inválido');
    }

    // NOVO: Verificar se foi informado um técnico
    if (!isset($data['technician_id']) || !is_numeric($data['technician_id'])) {
        throw new Exception('É necessário selecionar um técnico para despachar a equipe');
    }

    $ticket_id = (int)$data['ticket_id'];
    $technician_id = (int)$data['technician_id'];
    $attendant_id = $_SESSION['user_id'];
    $attendant_name = $_SESSION['user_name'] ?? 'Atendente';

    // Conectar ao banco
    $pdo = getConnection();
    
    // Verificar se o técnico existe e está ativo
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? AND role = 'technician' AND is_active = TRUE");
    $stmt->execute([$technician_id]);
    $technician = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$technician) {
        throw new Exception('Técnico não encontrado ou inativo');
    }
    
    // Buscar ticket
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as company_name, c.email as company_email
        FROM tickets t
        LEFT JOIN companies c ON t.company_id = c.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        throw new Exception('Ticket não encontrado');
    }

    // Verificar status permitido
    $allowedStatuses = ['assumed', 'in_progress', 'reopened'];
    if (!in_array($ticket['status'], $allowedStatuses)) {
        throw new Exception('Ticket deve estar assumido para despachar equipe. Status atual: ' . getStatusLabel($ticket['status']));
    }

    // Verificar se o atendente é o responsável
    if ($ticket['assigned_user_id'] != $attendant_id) {
        throw new Exception('Apenas o atendente responsável pode despachar a equipe');
    }

    // Iniciar transação
    $pdo->beginTransaction();

    try {
        $dispatchTime = date('Y-m-d H:i:s');
        
        // IMPORTANTE: Manter o atendente como assigned_user_id
        // E criar um novo campo ou usar outra estratégia
        // Vamos atualizar o ticket e adicionar o técnico
        $stmt = $pdo->prepare("
            UPDATE tickets 
            SET status = 'dispatched',
                dispatched_at = ?,
                updated_at = ?
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $dispatchTime,
            $dispatchTime,
            $ticket_id
        ]);

        if (!$result) {
            throw new Exception('Erro ao atualizar status do ticket');
        }

        // Registrar log do despacho
        $stmt = $pdo->prepare("
            INSERT INTO ticket_logs (ticket_id, user_id, action, description, created_at)
            VALUES (?, ?, 'dispatch', ?, ?)
        ");
        
        $logDescription = "Equipe despachada por {$attendant_name}. Técnico designado: {$technician['name']}";
        $stmt->execute([
            $ticket_id,
            $attendant_id,
            $logDescription,
            $dispatchTime
        ]);

        // CRIAR TABELA DE ATRIBUIÇÃO DE TÉCNICOS (se não existir)
        // Essa tabela relaciona tickets com técnicos de campo
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ticket_technicians (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ticket_id INT NOT NULL,
                technician_id INT NOT NULL,
                assigned_by INT NOT NULL,
                assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('pending', 'accepted', 'in_progress', 'completed') DEFAULT 'pending',
                notes TEXT,
                FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
                FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_ticket (ticket_id),
                INDEX idx_technician (technician_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Atribuir técnico ao ticket
        $stmt = $pdo->prepare("
            INSERT INTO ticket_technicians (ticket_id, technician_id, assigned_by, notes)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                technician_id = VALUES(technician_id),
                assigned_by = VALUES(assigned_by),
                assigned_at = CURRENT_TIMESTAMP,
                status = 'pending',
                notes = VALUES(notes)
        ");
        
        $notes = "OS gerada pelo atendente {$attendant_name}";
        $stmt->execute([
            $ticket_id,
            $technician_id,
            $attendant_id,
            $notes
        ]);

        // Notificar a empresa
        if (!empty($ticket['company_id'])) {
            $address = $ticket['address'] ?? 'endereço não informado';
            $message = "Um técnico foi despachado para atender seu ticket #{$ticket_id}. Técnico: {$technician['name']}. Local: {$address}";
            
            $stmt = $pdo->prepare("
                INSERT INTO notifications (company_id, ticket_id, type, message, created_at)
                VALUES (?, ?, 'dispatch', ?, ?)
            ");
            
            $stmt->execute([
                $ticket['company_id'],
                $ticket_id,
                $message,
                $dispatchTime
            ]);
        }

        // NOTIFICAR O TÉCNICO
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, ticket_id, type, message, created_at)
            VALUES (?, ?, 'dispatch', ?, ?)
        ");
        
        $techMessage = "Nova OS #{$ticket_id}: {$ticket['title']}. Empresa: {$ticket['company_name']}. Local: " . ($ticket['address'] ?: 'Ver detalhes');
        $stmt->execute([
            $technician_id,
            $ticket_id,
            $techMessage,
            $dispatchTime
        ]);

        // Commit da transação
        $pdo->commit();

        // Retornar sucesso
        echo json_encode([
            'success' => true,
            'message' => "OS despachada para o técnico {$technician['name']} com sucesso!",
            'data' => [
                'ticket_id' => $ticket_id,
                'status' => 'dispatched',
                'status_label' => getStatusLabel('dispatched'),
                'dispatched_at' => formatDate($dispatchTime, true),
                'technician_name' => $technician['name']
            ]
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("ERROR in dispatch_ticket.php: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}