<?php
/**
 * Classe Upload - Gerenciamento de Upload de Arquivos
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 */

class Upload {
    private $db;
    private $uploadPath;
    private $allowedExtensions;
    private $allowedMimeTypes;
    private $maxFileSize;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->uploadPath = UPLOAD_PATH;
        $this->allowedExtensions = ALLOWED_EXTENSIONS;
        $this->allowedMimeTypes = ALLOWED_MIME_TYPES;
        $this->maxFileSize = MAX_FILE_SIZE;
        
        // Criar diretório de upload se não existir
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }
    
    /**
     * Fazer upload de um único arquivo
     * @param array $file Array $_FILES['nome_campo']
     * @param int $ticketId ID do ticket relacionado
     * @return array ['success' => bool, 'message' => string, 'id' => int]
     */
    public function upload($file, $ticketId) {
        try {
            // Validações
            $validation = $this->validateFile($file);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Gerar nome único para o arquivo
            $fileName = $this->generateUniqueFileName($file['name']);
            $filePath = $this->uploadPath . $fileName;
            
            // Mover arquivo
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                return ['success' => false, 'message' => 'Erro ao fazer upload do arquivo.'];
            }
            
            // Registrar no banco de dados
            $sql = "INSERT INTO attachments (ticket_id, file_name, file_path, file_size, mime_type) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $params = [
                $ticketId,
                $file['name'], // Nome original
                $fileName,     // Nome no servidor
                $file['size'],
                $file['type']
            ];
            
            $attachmentId = $this->db->insert($sql, $params);
            
            if ($attachmentId) {
                logSystem("Arquivo anexado ao ticket #{$ticketId}: {$file['name']}", "INFO");
                
                return [
                    'success' => true,
                    'message' => 'Arquivo enviado com sucesso!',
                    'id' => $attachmentId,
                    'file_name' => $fileName
                ];
            }
            
            // Se falhou ao registrar, deletar arquivo
            @unlink($filePath);
            return ['success' => false, 'message' => 'Erro ao registrar arquivo no banco de dados.'];
            
        } catch (Exception $e) {
            logSystem("Erro ao fazer upload: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao processar upload.'];
        }
    }
    
    /**
     * Fazer upload de múltiplos arquivos
     * @param array $files Array $_FILES['nome_campo']
     * @param int $ticketId
     * @return array ['success' => bool, 'message' => string, 'uploaded' => int, 'files' => array]
     */
    public function uploadMultiple($files, $ticketId) {
        $results = [
            'success' => true,
            'message' => '',
            'uploaded' => 0,
            'failed' => 0,
            'files' => []
        ];
        
        // Reorganizar array de arquivos
        $fileArray = $this->reorganizeFilesArray($files);
        
        foreach ($fileArray as $file) {
            // Pular se não foi enviado
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            
            $result = $this->upload($file, $ticketId);
            
            if ($result['success']) {
                $results['uploaded']++;
                $results['files'][] = [
                    'name' => $file['name'],
                    'status' => 'success',
                    'id' => $result['id']
                ];
            } else {
                $results['failed']++;
                $results['files'][] = [
                    'name' => $file['name'],
                    'status' => 'error',
                    'message' => $result['message']
                ];
            }
        }
        
        // Definir mensagem geral
        if ($results['uploaded'] > 0 && $results['failed'] === 0) {
            $results['message'] = "{$results['uploaded']} arquivo(s) enviado(s) com sucesso!";
        } elseif ($results['uploaded'] > 0 && $results['failed'] > 0) {
            $results['message'] = "{$results['uploaded']} enviado(s), {$results['failed']} com erro.";
            $results['success'] = false;
        } else {
            $results['message'] = "Nenhum arquivo foi enviado.";
            $results['success'] = false;
        }
        
        return $results;
    }
    
    /**
     * Validar arquivo
     * @param array $file
     * @return array ['success' => bool, 'message' => string]
     */
    private function validateFile($file) {
        // Verificar se houve erro no upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => $this->getUploadErrorMessage($file['error'])];
        }
        
        // Verificar tamanho
        if ($file['size'] > $this->maxFileSize) {
            $maxSizeMB = $this->maxFileSize / (1024 * 1024);
            return ['success' => false, 'message' => "Arquivo muito grande. Tamanho máximo: {$maxSizeMB}MB"];
        }
        
        // Verificar extensão
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            return [
                'success' => false, 
                'message' => 'Tipo de arquivo não permitido. Extensões aceitas: ' . implode(', ', $this->allowedExtensions)
            ];
        }
        
        // Verificar MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            return ['success' => false, 'message' => 'Tipo de arquivo não permitido.'];
        }
        
        // Verificar se é realmente um upload
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'Arquivo inválido.'];
        }
        
        return ['success' => true, 'message' => 'Arquivo válido'];
    }
    
    /**
     * Gerar nome único para arquivo
     * @param string $originalName
     * @return string
     */
    private function generateUniqueFileName($originalName) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Remover caracteres especiais
        $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '', $baseName);
        $baseName = substr($baseName, 0, 50); // Limitar tamanho
        
        // Gerar nome único
        return $baseName . '_' . time() . '_' . uniqid() . '.' . $extension;
    }
    
    /**
     * Reorganizar array de múltiplos arquivos
     * @param array $files
     * @return array
     */
    private function reorganizeFilesArray($files) {
        $result = [];
        
        if (!isset($files['name']) || !is_array($files['name'])) {
            return [$files];
        }
        
        $fileCount = count($files['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            $result[] = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
        }
        
        return $result;
    }
    
    /**
     * Obter mensagem de erro de upload
     * @param int $errorCode
     * @return string
     */
    private function getUploadErrorMessage($errorCode) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'Arquivo excede o tamanho máximo permitido pelo servidor.',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o tamanho máximo permitido.',
            UPLOAD_ERR_PARTIAL => 'Upload foi feito parcialmente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever arquivo no disco.',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão PHP.'
        ];
        
        return $errors[$errorCode] ?? 'Erro desconhecido no upload.';
    }
    
    /**
     * Obter anexos de um ticket
     * @param int $ticketId
     * @return array
     */
    public function getByTicket($ticketId) {
        $sql = "SELECT * FROM attachments WHERE ticket_id = ? ORDER BY uploaded_at DESC";
        return $this->db->select($sql, [$ticketId]);
    }
    
    /**
     * Obter anexo por ID
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        $sql = "SELECT * FROM attachments WHERE id = ?";
        return $this->db->selectOne($sql, [$id]);
    }
    
    /**
     * Deletar anexo
     * @param int $id
     * @return array ['success' => bool, 'message' => string]
     */
    public function delete($id) {
        try {
            // Obter informações do arquivo
            $attachment = $this->getById($id);
            
            if (!$attachment) {
                return ['success' => false, 'message' => 'Arquivo não encontrado.'];
            }
            
            // Deletar arquivo físico
            $filePath = $this->uploadPath . $attachment['file_path'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
            
            // Deletar registro do banco
            $sql = "DELETE FROM attachments WHERE id = ?";
            $this->db->delete($sql, [$id]);
            
            logSystem("Anexo deletado: ID {$id} - {$attachment['file_name']}", "INFO");
            
            return ['success' => true, 'message' => 'Arquivo deletado com sucesso!'];
            
        } catch (Exception $e) {
            logSystem("Erro ao deletar anexo: " . $e->getMessage(), "ERROR");
            return ['success' => false, 'message' => 'Erro ao deletar arquivo.'];
        }
    }
    
    /**
     * Fazer download de um arquivo
     * @param int $id
     * @return bool
     */
    public function download($id) {
        try {
            $attachment = $this->getById($id);
            
            if (!$attachment) {
                return false;
            }
            
            $filePath = $this->uploadPath . $attachment['file_path'];
            
            if (!file_exists($filePath)) {
                return false;
            }
            
            // Headers para download
            header('Content-Type: ' . $attachment['mime_type']);
            header('Content-Disposition: attachment; filename="' . $attachment['file_name'] . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');
            
            // Ler e enviar arquivo
            readfile($filePath);
            
            logSystem("Download de anexo: {$attachment['file_name']}", "INFO");
            
            return true;
            
        } catch (Exception $e) {
            logSystem("Erro ao fazer download: " . $e->getMessage(), "ERROR");
            return false;
        }
    }
    
    /**
     * Verificar se arquivo existe
     * @param int $id
     * @return bool
     */
    public function fileExists($id) {
        $attachment = $this->getById($id);
        
        if (!$attachment) {
            return false;
        }
        
        $filePath = $this->uploadPath . $attachment['file_path'];
        return file_exists($filePath);
    }
    
    /**
     * Limpar anexos órfãos (sem ticket)
     * @return int Número de arquivos removidos
     */
    public function cleanOrphanFiles() {
        try {
            // Buscar anexos sem ticket
            $sql = "SELECT a.* FROM attachments a 
                    LEFT JOIN tickets t ON a.ticket_id = t.id 
                    WHERE t.id IS NULL";
            
            $orphans = $this->db->select($sql);
            $removed = 0;
            
            foreach ($orphans as $attachment) {
                // Deletar arquivo físico
                $filePath = $this->uploadPath . $attachment['file_path'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
                
                // Deletar registro
                $this->db->delete("DELETE FROM attachments WHERE id = ?", [$attachment['id']]);
                $removed++;
            }
            
            if ($removed > 0) {
                logSystem("Limpeza de anexos órfãos: {$removed} arquivo(s) removido(s)", "INFO");
            }
            
            return $removed;
            
        } catch (Exception $e) {
            logSystem("Erro ao limpar anexos órfãos: " . $e->getMessage(), "ERROR");
            return 0;
        }
    }
    
    /**
     * Limpar anexos antigos de tickets fechados
     * @param int $days Manter apenas dos últimos N dias
     * @return int Número de arquivos removidos
     */
    public function cleanOldAttachments($days = 180) {
        try {
            $sql = "SELECT a.* FROM attachments a 
                    INNER JOIN tickets t ON a.ticket_id = t.id 
                    WHERE t.status = 'closed' 
                    AND t.closed_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            
            $oldAttachments = $this->db->select($sql, [$days]);
            $removed = 0;
            
            foreach ($oldAttachments as $attachment) {
                $result = $this->delete($attachment['id']);
                if ($result['success']) {
                    $removed++;
                }
            }
            
            if ($removed > 0) {
                logSystem("Limpeza de anexos antigos: {$removed} arquivo(s) removido(s)", "INFO");
            }
            
            return $removed;
            
        } catch (Exception $e) {
            logSystem("Erro ao limpar anexos antigos: " . $e->getMessage(), "ERROR");
            return 0;
        }
    }
    
    /**
     * Obter espaço usado por anexos
     * @return array ['total_files' => int, 'total_size' => int, 'total_size_formatted' => string]
     */
    public function getStorageStats() {
        try {
            $sql = "SELECT COUNT(*) as total_files, SUM(file_size) as total_size FROM attachments";
            $result = $this->db->selectOne($sql);
            
            return [
                'total_files' => (int)($result['total_files'] ?? 0),
                'total_size' => (int)($result['total_size'] ?? 0),
                'total_size_formatted' => formatBytes((int)($result['total_size'] ?? 0))
            ];
            
        } catch (Exception $e) {
            return ['total_files' => 0, 'total_size' => 0, 'total_size_formatted' => '0 B'];
        }
    }
    
    /**
     * Verificar integridade dos arquivos
     * @return array ['valid' => int, 'missing' => int, 'files' => array]
     */
    public function checkIntegrity() {
        $result = [
            'valid' => 0,
            'missing' => 0,
            'corrupted' => 0,
            'files' => []
        ];
        
        try {
            $sql = "SELECT * FROM attachments";
            $attachments = $this->db->select($sql);
            
            foreach ($attachments as $attachment) {
                $filePath = $this->uploadPath . $attachment['file_path'];
                
                if (!file_exists($filePath)) {
                    $result['missing']++;
                    $result['files'][] = [
                        'id' => $attachment['id'],
                        'name' => $attachment['file_name'],
                        'status' => 'missing'
                    ];
                } elseif (filesize($filePath) != $attachment['file_size']) {
                    $result['corrupted']++;
                    $result['files'][] = [
                        'id' => $attachment['id'],
                        'name' => $attachment['file_name'],
                        'status' => 'corrupted'
                    ];
                } else {
                    $result['valid']++;
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            logSystem("Erro ao verificar integridade: " . $e->getMessage(), "ERROR");
            return $result;
        }
    }
    
    /**
     * Obter extensões de arquivos mais comuns
     * @param int $limit
     * @return array
     */
    public function getFileTypeStats($limit = 10) {
        try {
            $sql = "SELECT 
                    SUBSTRING_INDEX(file_name, '.', -1) as extension,
                    COUNT(*) as count,
                    SUM(file_size) as total_size
                    FROM attachments
                    GROUP BY extension
                    ORDER BY count DESC
                    LIMIT ?";
            
            $results = $this->db->select($sql, [$limit]);
            
            foreach ($results as &$result) {
                $result['total_size_formatted'] = formatBytes($result['total_size']);
            }
            
            return $results;
            
        } catch (Exception $e) {
            return [];
        }
    }
}
?>