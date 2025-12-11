<?php
/**
 * SISTEMA DE SUPORTE E MANUTENÇÃO
 * Arquivo: ajax/get_comments.php
 * Descrição: Buscar comentários de um ticket via AJAX
 */

session_start();
require_once __DIR__ . '/../config/database.php';

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

// Validar ticket_id
if (!isset($_GET['ticket_id']) || !is_numeric($_GET['ticket_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID do ticket inválido'
    ]);
    exit;
}

$ticket_id = (int)$_GET['ticket_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Buscar comentários do ticket
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.comment,
            c.created_at,
            u.id as user_id,
            u.name as user_name,
            u.email as user_email
        FROM ticket_comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.ticket_id = :ticket_id
        ORDER BY c.created_at ASC
    ");
    
    $stmt->execute([':ticket_id' => $ticket_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar comentários
    $formatted_comments = array_map(function($comment) {
        return [
            'id' => (int)$comment['id'],
            'user_id' => (int)$comment['user_id'],
            'user_name' => $comment['user_name'],
            'comment' => $comment['comment'],
            'created_at' => $comment['created_at']
        ];
    }, $comments);
    
    echo json_encode([
        'success' => true,
        'comments' => $formatted_comments,
        'total' => count($formatted_comments)
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar comentários: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar comentários',
        'comments' => []
    ]);
}
?>