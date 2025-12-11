-- =====================================================
-- SQL COMPLETO E FINAL - SISTEMA DE ORDEM DE SERVIÇO
-- Sistema de Suporte e Manutenção
-- Autor: Nicolas Clayton Parpinelli
-- Data: 2025-11-12
-- =====================================================

-- =====================================================
-- PARTE 1: CRIAR BANCO DE DADOS
-- =====================================================
CREATE DATABASE IF NOT EXISTS b12_40650723_BD 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE b12_40650723_BD;

-- =====================================================
-- PARTE 2: TABELAS PRINCIPAIS
-- =====================================================

-- Tabela: companies
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    cnpj VARCHAR(18) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(2),
    zip_code VARCHAR(10),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cnpj (cnpj),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'attendant', 'technician') NOT NULL,
    phone VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: tickets
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category ENUM('computer', 'mobile', 'server', 'printer', 'tv', 'network', 'other') NOT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('created', 'assumed', 'dispatched', 'work_order_created', 'in_progress', 'resolved', 'closed', 'reopened') DEFAULT 'created',
    address TEXT,
    assigned_user_id INT NULL,
    has_work_order BOOLEAN DEFAULT FALSE,
    work_order_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assumed_at TIMESTAMP NULL,
    dispatched_at TIMESTAMP NULL,
    resolved_at TIMESTAMP NULL,
    closed_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_company (company_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PARTE 3: TABELAS DE ORDEM DE SERVIÇO
-- =====================================================

-- Tabela: work_orders
CREATE TABLE IF NOT EXISTS work_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    created_by INT NOT NULL,
    status ENUM('available', 'in_progress', 'completed', 'cancelled') DEFAULT 'available',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    deadline DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_ticket (ticket_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: work_order_technicians
CREATE TABLE IF NOT EXISTS work_order_technicians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_order_id INT NOT NULL,
    technician_id INT NOT NULL,
    role ENUM('primary', 'support') DEFAULT 'primary',
    status ENUM('pending', 'accepted', 'working', 'completed') DEFAULT 'pending',
    accepted_at TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wo_tech (work_order_id, technician_id),
    INDEX idx_work_order (work_order_id),
    INDEX idx_technician (technician_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: work_order_logs
CREATE TABLE IF NOT EXISTS work_order_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_order_id INT NOT NULL,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_work_order (work_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PARTE 4: OUTRAS TABELAS NECESSÁRIAS
-- =====================================================

-- Tabela: attachments
CREATE TABLE IF NOT EXISTS attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(100),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: ticket_logs
CREATE TABLE IF NOT EXISTS ticket_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ticket_id INT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PARTE 5: PROCEDURES
-- =====================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_create_work_order$$
CREATE PROCEDURE sp_create_work_order(
    IN p_ticket_id INT,
    IN p_created_by INT,
    IN p_priority VARCHAR(10),
    IN p_deadline DATETIME,
    IN p_notes TEXT,
    OUT p_work_order_id INT,
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(255)
)
BEGIN
    DECLARE v_ticket_status VARCHAR(20);
    DECLARE v_has_work_order BOOLEAN;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Erro ao criar OS';
        SET p_work_order_id = NULL;
    END;
    
    START TRANSACTION;
    
    SELECT status, has_work_order INTO v_ticket_status, v_has_work_order
    FROM tickets WHERE id = p_ticket_id FOR UPDATE;
    
    IF v_ticket_status IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'Ticket não encontrado';
        ROLLBACK;
    ELSEIF v_has_work_order = TRUE THEN
        SET p_success = FALSE;
        SET p_message = 'Ticket já possui OS';
        ROLLBACK;
    ELSEIF v_ticket_status NOT IN ('assumed', 'dispatched') THEN
        SET p_success = FALSE;
        SET p_message = 'Ticket deve estar assumido';
        ROLLBACK;
    ELSE
        INSERT INTO work_orders (ticket_id, created_by, status, priority, deadline, notes)
        VALUES (p_ticket_id, p_created_by, 'available', p_priority, p_deadline, p_notes);
        
        SET p_work_order_id = LAST_INSERT_ID();
        
        UPDATE tickets 
        SET has_work_order = TRUE, work_order_id = p_work_order_id, 
            status = 'work_order_created', updated_at = NOW()
        WHERE id = p_ticket_id;
        
        INSERT INTO work_order_logs (work_order_id, user_id, action, description)
        VALUES (p_work_order_id, p_created_by, 'CREATED', CONCAT('OS criada do ticket #', p_ticket_id));
        
        SET p_success = TRUE;
        SET p_message = 'OS criada com sucesso';
        COMMIT;
    END IF;
END$$

DROP PROCEDURE IF EXISTS sp_accept_work_order$$
CREATE PROCEDURE sp_accept_work_order(
    IN p_work_order_id INT,
    IN p_technician_id INT,
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(255)
)
BEGIN
    DECLARE v_wo_status VARCHAR(20);
    DECLARE v_tech_count INT;
    DECLARE v_ticket_id INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Erro ao aceitar OS';
    END;
    
    START TRANSACTION;
    
    SELECT status, ticket_id INTO v_wo_status, v_ticket_id
    FROM work_orders WHERE id = p_work_order_id FOR UPDATE;
    
    SELECT COUNT(*) INTO v_tech_count
    FROM work_order_technicians
    WHERE work_order_id = p_work_order_id AND technician_id = p_technician_id;
    
    IF v_wo_status IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'OS não encontrada';
        ROLLBACK;
    ELSEIF v_wo_status != 'available' THEN
        SET p_success = FALSE;
        SET p_message = 'OS não está disponível';
        ROLLBACK;
    ELSEIF v_tech_count > 0 THEN
        SET p_success = FALSE;
        SET p_message = 'Você já está nesta OS';
        ROLLBACK;
    ELSE
        INSERT INTO work_order_technicians (work_order_id, technician_id, role, status, accepted_at)
        VALUES (p_work_order_id, p_technician_id, 'primary', 'accepted', NOW());
        
        UPDATE work_orders SET status = 'in_progress', updated_at = NOW()
        WHERE id = p_work_order_id;
        
        UPDATE tickets SET status = 'in_progress', updated_at = NOW()
        WHERE id = v_ticket_id;
        
        INSERT INTO work_order_logs (work_order_id, user_id, action, description)
        VALUES (p_work_order_id, p_technician_id, 'ACCEPTED', 'OS aceita');
        
        SET p_success = TRUE;
        SET p_message = 'OS aceita com sucesso';
        COMMIT;
    END IF;
END$$

DELIMITER ;

-- =====================================================
-- PARTE 6: VIEW
-- =====================================================

CREATE OR REPLACE VIEW v_work_orders_complete AS
SELECT 
    wo.id,
    wo.ticket_id,
    wo.status AS wo_status,
    wo.priority,
    wo.deadline,
    wo.notes AS wo_notes,
    wo.created_at,
    wo.completed_at,
    t.title AS ticket_title,
    t.description AS ticket_description,
    t.category,
    t.address,
    c.name AS company_name,
    c.email AS company_email,
    c.phone AS company_phone,
    c.address AS company_address,
    creator.name AS created_by_name,
    (SELECT COUNT(*) FROM work_order_technicians wot WHERE wot.work_order_id = wo.id) AS total_technicians,
    (SELECT u.name FROM work_order_technicians wot JOIN users u ON wot.technician_id = u.id
     WHERE wot.work_order_id = wo.id AND wot.role = 'primary' LIMIT 1) AS primary_technician,
    CASE WHEN wo.deadline < NOW() AND wo.status = 'in_progress' THEN TRUE ELSE FALSE END AS is_overdue
FROM work_orders wo
INNER JOIN tickets t ON wo.ticket_id = t.id
INNER JOIN companies c ON t.company_id = c.id
INNER JOIN users creator ON wo.created_by = creator.id;

-- =====================================================
-- PARTE 7: TRIGGER
-- =====================================================

DELIMITER $$

DROP TRIGGER IF EXISTS tr_work_order_status_change$$
CREATE TRIGGER tr_work_order_status_change
AFTER UPDATE ON work_orders
FOR EACH ROW
BEGIN
    IF NEW.status != OLD.status THEN
        INSERT INTO work_order_logs (work_order_id, user_id, action, description, metadata)
        VALUES (NEW.id, NULL, 'STATUS_CHANGED', CONCAT('Status: ', OLD.status, ' → ', NEW.status),
                JSON_OBJECT('old_status', OLD.status, 'new_status', NEW.status));
    END IF;
END$$

DELIMITER ;

-- =====================================================
-- PARTE 8: DADOS DE TESTE
-- =====================================================

INSERT IGNORE INTO companies (id, name, cnpj, email, phone, address, city, state, is_active)
VALUES (1, 'Empresa Teste', '12.345.678/0001-90', 'teste@empresa.com', '(11) 98765-4321', 
        'Rua Teste, 123', 'São Paulo', 'SP', TRUE);

INSERT IGNORE INTO users (id, name, email, password, role, is_active)
VALUES 
(1, 'Admin', 'admin@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE),
(2, 'Atendente', 'atendente@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'attendant', TRUE),
(3, 'Técnico 1', 'tecnico1@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'technician', TRUE),
(4, 'Técnico 2', 'tecnico2@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'technician', TRUE);

-- =====================================================
-- VERIFICAÇÃO FINAL
-- =====================================================

SELECT '✅ INSTALAÇÃO COMPLETA!' AS status;
SELECT 'Banco: sistema_suporte' AS info;
SELECT 'Usuários de teste criados:' AS usuarios;
SELECT 'Email: admin@sistema.com | Senha: password' AS admin;
SELECT 'Email: atendente@sistema.com | Senha: password' AS atendente;
SELECT 'Email: tecnico1@sistema.com | Senha: password' AS tecnico1;
SELECT 'Email: tecnico2@sistema.com | Senha: password' AS tecnico2;

SHOW TABLES;