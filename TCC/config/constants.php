<?php
/**
 * Constantes do Sistema
 * Sistema de Suporte e Manutenção
 * Autor: Nicolas Clayton Parpinelli
 */

// =====================================================
// STATUS DOS TICKETS
// =====================================================

define('TICKET_STATUS', [
    'created' => 'Criado',
    'assumed' => 'Assumido',
    'dispatched' => 'Despachado',
    'work_order_created' => 'OS Criada',
    'in_progress' => 'Em Andamento',
    'resolved' => 'Resolvido',
    'closed' => 'Encerrado',
    'reopened' => 'Reaberto'
]);

// Cores dos status (para badges/cards)
define('TICKET_STATUS_COLORS', [
    'created' => 'secondary',
    'assumed' => 'info',
    'dispatched' => 'primary',
    'work_order_created' => 'warning',
    'in_progress' => 'warning',
    'resolved' => 'success',
    'closed' => 'dark',
    'reopened' => 'danger'
]);

// =====================================================
// PRIORIDADES DOS TICKETS
// =====================================================

define('TICKET_PRIORITIES', [
    'low' => 'Baixa',
    'medium' => 'Média',
    'high' => 'Alta'
]);

// Cores das prioridades
define('TICKET_PRIORITY_COLORS', [
    'low' => 'success',
    'medium' => 'warning',
    'high' => 'danger'
]);

// Ícones das prioridades (FontAwesome ou similar)
define('TICKET_PRIORITY_ICONS', [
    'low' => 'fa-arrow-down',
    'medium' => 'fa-minus',
    'high' => 'fa-arrow-up'
]);

// =====================================================
// CATEGORIAS DE EQUIPAMENTOS
// =====================================================

define('TICKET_CATEGORIES', [
    'computer' => 'Computador/Desktop',
    'mobile' => 'Celular/Tablet',
    'server' => 'Servidor',
    'printer' => 'Impressora',
    'tv' => 'TV/Monitor',
    'network' => 'Rede/Internet',
    'other' => 'Outros'
]);

// Ícones das categorias
define('TICKET_CATEGORY_ICONS', [
    'computer' => 'fa-desktop',
    'mobile' => 'fa-mobile-alt',
    'server' => 'fa-server',
    'printer' => 'fa-print',
    'tv' => 'fa-tv',
    'network' => 'fa-network-wired',
    'other' => 'fa-tools'
]);

// =====================================================
// ROLES (PAPÉIS) DE USUÁRIOS
// =====================================================

define('USER_ROLES', [
    'admin' => 'Administrador',
    'attendant' => 'Atendente',
    'technician' => 'Técnico'
]);

// Permissões por role
define('USER_PERMISSIONS', [
    'admin' => [
        'view_all_tickets',
        'assume_ticket',
        'dispatch_ticket',
        'create_work_order',
        'manage_work_orders',
        'close_ticket',
        'manage_users',
        'manage_companies',
        'view_reports',
        'system_settings'
    ],
    'attendant' => [
        'view_all_tickets',
        'assume_ticket',
        'dispatch_ticket',
        'create_work_order',
        'close_ticket',
        'view_reports'
    ],
    'technician' => [
        'view_assigned_tickets',
        'view_available_work_orders',
        'accept_work_order',
        'update_ticket_status'
    ]
]);

// =====================================================
// TIPOS DE NOTIFICAÇÃO
// =====================================================

define('NOTIFICATION_TYPES', [
    'assumed' => 'Ticket Assumido',
    'dispatched' => 'Equipe Despachada',
    'resolved' => 'Ticket Resolvido',
    'closed' => 'Ticket Encerrado',
    'reopened' => 'Ticket Reaberto'
]);

// =====================================================
// TIPOS DE LOG/AÇÃO
// =====================================================

define('LOG_ACTIONS', [
    'CREATED' => 'Ticket criado',
    'ASSUMED' => 'Ticket assumido',
    'DISPATCHED' => 'Equipe despachada',
    'IN_PROGRESS' => 'Ticket em andamento',
    'RESOLVED' => 'Ticket resolvido',
    'CLOSED' => 'Ticket encerrado',
    'REOPENED' => 'Ticket reaberto',
    'COMMENTED' => 'Comentário adicionado',
    'ATTACHMENT_ADDED' => 'Arquivo anexado',
    'PRIORITY_CHANGED' => 'Prioridade alterada',
    'ASSIGNED_CHANGED' => 'Responsável alterado'
]);

// =====================================================
// ESTADOS BRASILEIROS
// =====================================================

define('BRAZILIAN_STATES', [
    'AC' => 'Acre',
    'AL' => 'Alagoas',
    'AP' => 'Amapá',
    'AM' => 'Amazonas',
    'BA' => 'Bahia',
    'CE' => 'Ceará',
    'DF' => 'Distrito Federal',
    'ES' => 'Espírito Santo',
    'GO' => 'Goiás',
    'MA' => 'Maranhão',
    'MT' => 'Mato Grosso',
    'MS' => 'Mato Grosso do Sul',
    'MG' => 'Minas Gerais',
    'PA' => 'Pará',
    'PB' => 'Paraíba',
    'PR' => 'Paraná',
    'PE' => 'Pernambuco',
    'PI' => 'Piauí',
    'RJ' => 'Rio de Janeiro',
    'RN' => 'Rio Grande do Norte',
    'RS' => 'Rio Grande do Sul',
    'RO' => 'Rondônia',
    'RR' => 'Roraima',
    'SC' => 'Santa Catarina',
    'SP' => 'São Paulo',
    'SE' => 'Sergipe',
    'TO' => 'Tocantins'
]);

// =====================================================
// MENSAGENS DO SISTEMA
// =====================================================

define('SUCCESS_MESSAGES', [
    'ticket_created' => 'Ticket criado com sucesso!',
    'ticket_assumed' => 'Ticket assumido com sucesso!',
    'ticket_dispatched' => 'Equipe despachada com sucesso!',
    'ticket_closed' => 'Ticket encerrado com sucesso!',
    'user_created' => 'Usuário cadastrado com sucesso!',
    'user_updated' => 'Usuário atualizado com sucesso!',
    'company_created' => 'Empresa cadastrada com sucesso!',
    'company_updated' => 'Empresa atualizada com sucesso!',
    'login_success' => 'Login realizado com sucesso!',
    'logout_success' => 'Logout realizado com sucesso!'
]);

define('ERROR_MESSAGES', [
    'generic' => 'Ocorreu um erro. Tente novamente.',
    'login_failed' => 'Email ou senha incorretos.',
    'unauthorized' => 'Você não tem permissão para acessar esta página.',
    'ticket_not_found' => 'Ticket não encontrado.',
    'invalid_data' => 'Dados inválidos. Verifique os campos.',
    'file_too_large' => 'Arquivo muito grande. Tamanho máximo: 5MB',
    'invalid_file_type' => 'Tipo de arquivo não permitido.',
    'upload_failed' => 'Falha ao fazer upload do arquivo.',
    'csrf_invalid' => 'Token de segurança inválido.',
    'session_expired' => 'Sua sessão expirou. Faça login novamente.'
]);

// =====================================================
// ORDEM DE PRIORIDADE (para ordenação)
// =====================================================

define('PRIORITY_ORDER', [
    'high' => 1,
    'medium' => 2,
    'low' => 3
]);

// =====================================================
// ===== CONSTANTES DO SISTEMA DE ORDEM DE SERVIÇO =====
// =====================================================

// STATUS DAS ORDENS DE SERVIÇO
define('WORK_ORDER_STATUS', [
    'available' => 'Disponível',
    'in_progress' => 'Em Andamento',
    'completed' => 'Concluída',
    'cancelled' => 'Cancelada'
]);

define('WORK_ORDER_STATUS_COLORS', [
    'available' => 'info',
    'in_progress' => 'warning',
    'completed' => 'success',
    'cancelled' => 'danger'
]);

define('WORK_ORDER_STATUS_ICONS', [
    'available' => 'fa-clipboard-list',
    'in_progress' => 'fa-tools',
    'completed' => 'fa-check-circle',
    'cancelled' => 'fa-times-circle'
]);

// PAPÉIS DOS TÉCNICOS
define('TECHNICIAN_ROLES', [
    'primary' => 'Técnico Principal',
    'support' => 'Suporte'
]);

define('TECHNICIAN_ROLE_COLORS', [
    'primary' => 'primary',
    'support' => 'secondary'
]);

define('TECHNICIAN_ROLE_ICONS', [
    'primary' => 'fa-star',
    'support' => 'fa-user-friends'
]);

// STATUS DOS TÉCNICOS
define('TECHNICIAN_STATUS', [
    'pending' => 'Pendente',
    'accepted' => 'Aceito',
    'working' => 'Trabalhando',
    'completed' => 'Concluído'
]);

define('TECHNICIAN_STATUS_COLORS', [
    'pending' => 'secondary',
    'accepted' => 'info',
    'working' => 'warning',
    'completed' => 'success'
]);

// PRAZOS PADRÃO (EM HORAS)
define('WORK_ORDER_DEADLINES', [
    'high' => 4,
    'medium' => 24,
    'low' => 72
]);

// AÇÕES DE LOG DAS OS
define('WORK_ORDER_ACTIONS', [
    'CREATED' => 'OS criada',
    'ACCEPTED' => 'OS aceita',
    'TECHNICIAN_ADDED' => 'Técnico adicionado',
    'TECHNICIAN_REMOVED' => 'Técnico removido',
    'STATUS_CHANGED' => 'Status alterado',
    'STARTED' => 'Trabalho iniciado',
    'COMPLETED' => 'OS concluída',
    'CANCELLED' => 'OS cancelada',
    'NOTES_ADDED' => 'Observações adicionadas',
    'DEADLINE_EXTENDED' => 'Prazo estendido'
]);

// MENSAGENS DO SISTEMA - OS
define('WORK_ORDER_MESSAGES', [
    'created' => 'OS criada com sucesso! Técnicos foram notificados.',
    'accepted' => 'OS aceita! Você é o técnico principal.',
    'technician_added' => 'Técnico adicionado como suporte.',
    'completed' => 'OS concluída com sucesso!',
    'cancelled' => 'OS cancelada.',
    'not_found' => 'OS não encontrada.',
    'already_accepted' => 'Esta OS já foi aceita por outro técnico.',
    'already_in_os' => 'Você já está nesta OS.',
    'not_available' => 'OS não está disponível para aceitação.',
    'cannot_add_tech' => 'Não é possível adicionar técnico neste momento.',
    'no_permission' => 'Você não tem permissão para esta ação.',
    'ticket_already_has_os' => 'Ticket já possui uma OS.',
    'invalid_status' => 'Status do ticket não permite criar OS.',
    'overdue' => 'Esta OS está atrasada!',
    'deadline_approaching' => 'Prazo da OS se aproxima.',
    'last_technician' => 'Não é possível remover o último técnico.'
]);

// LIMITES E CONFIGURAÇÕES
define('WORK_ORDER_LIMITS', [
    'max_technicians' => 5,
    'min_technicians' => 1,
    'auto_cancel_hours' => 48,
    'overdue_alert_hours' => 1,
    'max_notes_length' => 5000
]);

// TIPOS DE NOTIFICAÇÃO - OS
define('NOTIFICATION_TYPES_WORK_ORDER', [
    'work_order_created' => 'Nova OS Disponível',
    'work_order_accepted' => 'OS Aceita',
    'technician_added_to_os' => 'Adicionado à OS',
    'technician_removed_from_os' => 'Removido da OS',
    'work_order_completed' => 'OS Concluída',
    'work_order_cancelled' => 'OS Cancelada',
    'work_order_overdue' => 'OS Atrasada',
    'work_order_deadline_approaching' => 'Prazo Próximo'
]);

?>