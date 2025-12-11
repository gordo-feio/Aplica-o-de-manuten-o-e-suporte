<?php
/**
 * SISTEMA DE SUPORTE E MANUTENÇÃO
 * Arquivo: ajax/mark_all_read.php
 * Descrição: Marcar todas as notificações como lidas
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';

// Definir cabeçalho JSON
header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['user_id']) && !isset($_SESSION['company_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Não autorizado'
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

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (isset($_SESSION['user_id'])) {
        // Marcar todas as notificações do usuário como lidas
        $user_id = $_SESSION['user_id'];
        
        $stmt = $db->prepare("
            UPDATE notifications
            SET is_read = 1,
                read_at = NOW()
            WHERE user_id = :user_id 
            AND is_read = 0
        ");
        $stmt->execute([':user_id' => $user_id]);
        
        $affected = $stmt->rowCount();
        
    } elseif (isset($_SESSION['company_id'])) {
        // Marcar todas as notificações da empresa como lidas
        $company_id = $_SESSION['company_id'];
        
        $stmt = $db->prepare("
            UPDATE notifications
            SET is_read = 1,
                read_at = NOW()
            WHERE company_id = :company_id 
            AND is_read = 0
        ");
        $stmt->execute([':company_id' => $company_id]);
        
        $affected = $stmt->rowCount();
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Sessão inválida'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => $affected > 0 
            ? "{$affected} notificação(ões) marcada(s) como lida(s)" 
            : 'Nenhuma notificação para marcar',
        'affected_rows' => $affected
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao marcar todas notificações como lidas: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar requisição'
    ]);
}
?>