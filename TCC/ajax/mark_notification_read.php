<?php
/**
 * SISTEMA DE SUPORTE E MANUTENÇÃO
 * Arquivo: ajax/mark_notification_read.php
 * Descrição: Marcar notificação como lida via AJAX
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Notification.php';

// Definir cabeçalho JSON
header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não autenticado'
    ]);
    exit;
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido'
    ]);
    exit;
}

// Obter dados
$data = json_decode(file_get_contents('php://input'), true);

// Validar
if (!isset($data['notification_id']) || !is_numeric($data['notification_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID da notificação inválido'
    ]);
    exit;
}

$notification_id = (int)$data['notification_id'];
$user_id = $_SESSION['user_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $notification = new Notification($db);
    
    // Verificar se a notificação pertence ao usuário
    $stmt = $db->prepare("
        SELECT id, user_id, is_read
        FROM notifications
        WHERE id = :notification_id
    ");
    $stmt->execute([':notification_id' => $notification_id]);
    $notif = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$notif) {
        echo json_encode([
            'success' => false,
            'message' => 'Notificação não encontrada'
        ]);
        exit;
    }
    
    // Verificar permissão
    if ($notif['user_id'] != $user_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Sem permissão para esta notificação'
        ]);
        exit;
    }
    
    // Marcar como lida
    if (!$notif['is_read']) {
        $stmt = $db->prepare("
            UPDATE notifications
            SET is_read = 1,
                read_at = NOW()
            WHERE id = :notification_id
        ");
        $stmt->execute([':notification_id' => $notification_id]);
    }
    
    // Contar não lidas restantes
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM notifications
        WHERE user_id = :user_id 
        AND is_read = 0
    ");
    $stmt->execute([':user_id' => $user_id]);
    $unread_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'message' => 'Notificação marcada como lida',
        'unread_count' => (int)$unread_count
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao marcar notificação: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar requisição'
    ]);
}
?>