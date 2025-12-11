<?php
/**
 * CRON JOB - Verificar Tickets Atrasados
 * Sistema de Suporte e Manutenção
 * 
 * Este script deve ser executado periodicamente (ex: a cada hora)
 * Para configurar no Linux/cPanel:
 * 0 * * * * /usr/bin/php /caminho/para/sistema_suporte/cron/check_overdue.php
 * 
 * Para testar manualmente:
 * php /caminho/para/sistema_suporte/cron/check_overdue.php
 */

// Permitir execução apenas via CLI ou localhost
if (php_sapi_name() !== 'cli' && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
    die('Acesso negado. Execute via linha de comando.');
}

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

// Incluir dependências
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Ticket.php';
require_once __DIR__ . '/../classes/Notification.php';
require_once __DIR__ . '/../classes/Log.php';

echo "===========================================\n";
echo "CRON: Verificação de Tickets Atrasados\n";
echo "Executado em: " . date('d/m/Y H:i:s') . "\n";
echo "===========================================\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $notification = new Notification($db);
    $log = new Log($db);
    
    // ============================================
    // 1. VERIFICAR TICKETS CRIADOS HÁ MAIS DE 24H
    // ============================================
    
    echo "[1] Verificando tickets sem atendimento...\n";
    
    $sql = "SELECT t.*, c.name as company_name, c.email as company_email
            FROM tickets t
            INNER JOIN companies c ON t.company_id = c.id
            WHERE t.status = 'created'
            AND t.created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $overdueTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count = count($overdueTickets);
    echo "   → Encontrados: {$count} ticket(s) sem atendimento\n";
    
    foreach ($overdueTickets as $ticket) {
        // Notificar empresa
        $message = "Seu ticket #{$ticket['id']} - '{$ticket['title']}' está aguardando atendimento há mais de 24 horas.";
        
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, ticket_id, type, message, created_at)
            VALUES (NULL, ?, 'overdue_warning', ?, NOW())
        ");
        $stmt->execute([$ticket['id'], $message]);
        
        // Log
        echo "   → Notificação enviada para empresa: {$ticket['company_name']}\n";
    }
    
    // ============================================
    // 2. VERIFICAR TICKETS ASSUMIDOS HÁ MAIS DE 48H
    // ============================================
    
    echo "\n[2] Verificando tickets assumidos sem progresso...\n";
    
    $sql = "SELECT t.*, c.name as company_name, u.name as user_name
            FROM tickets t
            INNER JOIN companies c ON t.company_id = c.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.status IN ('assumed', 'dispatched')
            AND t.updated_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $stuckTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count = count($stuckTickets);
    echo "   → Encontrados: {$count} ticket(s) sem atualização\n";
    
    foreach ($stuckTickets as $ticket) {
        // Notificar atendente responsável
        if ($ticket['assigned_to']) {
            $message = "Ticket #{$ticket['id']} está sem atualização há mais de 48 horas. Verifique o andamento.";
            
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, ticket_id, type, message, created_at)
                VALUES (?, ?, 'stuck_ticket', ?, NOW())
            ");
            $stmt->execute([$ticket['assigned_to'], $ticket['id'], $message]);
            
            echo "   → Notificação enviada para: {$ticket['user_name']}\n";
        }
    }
    
    // ============================================
    // 3. VERIFICAR TICKETS RESOLVIDOS HÁ MAIS DE 7 DIAS
    // ============================================
    
    echo "\n[3] Fechando automaticamente tickets resolvidos...\n";
    
    $sql = "SELECT t.*, c.name as company_name
            FROM tickets t
            INNER JOIN companies c ON t.company_id = c.id
            WHERE t.status = 'resolved'
            AND t.resolved_at < DATE_SUB(NOW(), INTERVAL 7 DAY)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $resolvedTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count = count($resolvedTickets);
    echo "   → Encontrados: {$count} ticket(s) para fechar automaticamente\n";
    
    foreach ($resolvedTickets as $ticket) {
        // Atualizar para 'closed'
        $stmt = $db->prepare("
            UPDATE tickets 
            SET status = 'closed',
                closed_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$ticket['id']]);
        
        // Criar log
        $stmt = $db->prepare("
            INSERT INTO ticket_logs (ticket_id, user_id, action, old_status, new_status, description, created_at)
            VALUES (?, NULL, 'auto_closed', 'resolved', 'closed', 'Ticket fechado automaticamente após 7 dias', NOW())
        ");
        $stmt->execute([$ticket['id']]);
        
        // Notificar empresa
        $message = "Seu ticket #{$ticket['id']} foi fechado automaticamente. Se precisar, você pode reabri-lo.";
        
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, ticket_id, type, message, created_at)
            VALUES (NULL, ?, 'auto_closed', ?, NOW())
        ");
        $stmt->execute([$ticket['id'], $message]);
        
        echo "   → Ticket #{$ticket['id']} fechado automaticamente\n";
    }
    
    // ============================================
    // 4. LIMPAR NOTIFICAÇÕES ANTIGAS (> 30 DIAS)
    // ============================================
    
    echo "\n[4] Limpando notificações antigas...\n";
    
    $stmt = $db->prepare("
        DELETE FROM notifications 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND is_read = 1
    ");
    $stmt->execute();
    $deletedNotifications = $stmt->rowCount();
    
    echo "   → Removidas: {$deletedNotifications} notificação(ões)\n";
    
    // ============================================
    // 5. LIMPAR LOGS ANTIGOS (> 90 DIAS)
    // ============================================
    
    echo "\n[5] Limpando logs antigos...\n";
    
    $stmt = $db->prepare("
        DELETE FROM ticket_logs 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $stmt->execute();
    $deletedLogs = $stmt->rowCount();
    
    echo "   → Removidos: {$deletedLogs} log(s)\n";
    
    // ============================================
    // 6. ESTATÍSTICAS GERAIS
    // ============================================
    
    echo "\n[6] Estatísticas do Sistema:\n";
    
    // Total de tickets
    $stmt = $db->query("SELECT COUNT(*) as total FROM tickets");
    $totalTickets = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "   → Total de tickets: {$totalTickets}\n";
    
    // Tickets abertos
    $stmt = $db->query("
        SELECT COUNT(*) as total 
        FROM tickets 
        WHERE status NOT IN ('closed', 'resolved')
    ");
    $openTickets = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "   → Tickets em aberto: {$openTickets}\n";
    
    // Tickets fechados hoje
    $stmt = $db->query("
        SELECT COUNT(*) as total 
        FROM tickets 
        WHERE status = 'closed'
        AND DATE(closed_at) = CURDATE()
    ");
    $closedToday = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "   → Tickets fechados hoje: {$closedToday}\n";
    
    // Registrar execução no log do sistema
    $logMessage = "CRON executado - Tickets atrasados: {$count}, Auto-fechados: " . count($resolvedTickets);
    file_put_contents(
        __DIR__ . '/../logs/cron.log',
        "[" . date('Y-m-d H:i:s') . "] {$logMessage}\n",
        FILE_APPEND
    );
    
    echo "\n===========================================\n";
    echo "CRON finalizado com sucesso!\n";
    echo "===========================================\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    
    // Registrar erro
    file_put_contents(
        __DIR__ . '/../logs/cron_errors.log',
        "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n",
        FILE_APPEND
    );
    
    exit(1);
}
?>