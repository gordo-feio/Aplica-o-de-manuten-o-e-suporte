<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';

session_start();

$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['user_type'] !== 'employee') {
    header('Location: ../auth/login.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Período do relatório
$period = $_GET['period'] ?? '30';
$start_date = date('Y-m-d', strtotime("-{$period} days"));
$end_date = date('Y-m-d');

// Estatísticas Gerais
$stats_query = "
    SELECT 
        COUNT(*) as total_tickets,
        SUM(CASE WHEN status = 'created' THEN 1 ELSE 0 END) as created,
        SUM(CASE WHEN status = 'assumed' THEN 1 ELSE 0 END) as assumed,
        SUM(CASE WHEN status = 'dispatched' THEN 1 ELSE 0 END) as dispatched,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
        SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority,
        SUM(CASE WHEN priority = 'medium' THEN 1 ELSE 0 END) as medium_priority,
        SUM(CASE WHEN priority = 'low' THEN 1 ELSE 0 END) as low_priority
    FROM tickets
    WHERE created_at BETWEEN ? AND ?
";
$stmt = $conn->prepare($stats_query);
$stmt->execute([$start_date, $end_date]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Tickets por Categoria
$category_query = "
    SELECT category, COUNT(*) as count
    FROM tickets
    WHERE created_at BETWEEN ? AND ?
    GROUP BY category
    ORDER BY count DESC
";
$stmt = $conn->prepare($category_query);
$stmt->execute([$start_date, $end_date]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tickets por Empresa
$company_query = "
    SELECT c.name, COUNT(t.id) as count
    FROM companies c
    LEFT JOIN tickets t ON c.id = t.company_id AND t.created_at BETWEEN ? AND ?
    GROUP BY c.id
    ORDER BY count DESC
    LIMIT 10
";
$stmt = $conn->prepare($company_query);
$stmt->execute([$start_date, $end_date]);
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Performance dos Atendentes
$performance_query = "
    SELECT 
        u.name,
        COUNT(t.id) as tickets_handled,
        SUM(CASE WHEN t.status = 'closed' THEN 1 ELSE 0 END) as tickets_closed,
        AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.updated_at)) as avg_resolution_time
    FROM users u
    LEFT JOIN tickets t ON u.id = t.assigned_to AND t.created_at BETWEEN ? AND ?
    WHERE u.role IN ('technician', 'attendant')
    GROUP BY u.id
    ORDER BY tickets_handled DESC
";
$stmt = $conn->prepare($performance_query);
$stmt->execute([$start_date, $end_date]);
$performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tickets por dia (últimos 30 dias)
$daily_query = "
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM tickets
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date ASC
";
$stmt = $conn->prepare($daily_query);
$stmt->execute([$start_date, $end_date]);
$daily_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Relatórios e Estatísticas';
include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="content-header">
        <h1><i class="fas fa-chart-line"></i> Relatórios e Estatísticas</h1>
        <div class="header-actions">
            <select id="period-select" class="form-control" onchange="changePeriod(this.value)">
                <option value="7" <?= $period === '7' ? 'selected' : '' ?>>Últimos 7 dias</option>
                <option value="30" <?= $period === '30' ? 'selected' : '' ?>>Últimos 30 dias</option>
                <option value="90" <?= $period === '90' ? 'selected' : '' ?>>Últimos 90 dias</option>
                <option value="365" <?= $period === '365' ? 'selected' : '' ?>>Último ano</option>
            </select>
            <button onclick="exportReport()" class="btn btn-primary">
                <i class="fas fa-download"></i> Exportar PDF
            </button>
        </div>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon stat-primary">
                <i class="fas fa-ticket-alt"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['total_tickets']) ?></h3>
                <p>Total de Tickets</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['closed']) ?></h3>
                <p>Tickets Finalizados</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-warning">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['in_progress']) ?></h3>
                <p>Em Andamento</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-danger">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['high_priority']) ?></h3>
                <p>Alta Prioridade</p>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="charts-grid">
        <!-- Gráfico de Status -->
        <div class="chart-card">
            <h3><i class="fas fa-chart-pie"></i> Status dos Tickets</h3>
            <canvas id="statusChart"></canvas>
        </div>

        <!-- Gráfico de Prioridade -->
        <div class="chart-card">
            <h3><i class="fas fa-chart-bar"></i> Tickets por Prioridade</h3>
            <canvas id="priorityChart"></canvas>
        </div>
    </div>

    <!-- Gráfico de Linha - Tickets ao longo do tempo -->
    <div class="chart-card-full">
        <h3><i class="fas fa-chart-line"></i> Tickets ao Longo do Tempo</h3>
        <canvas id="timelineChart"></canvas>
    </div>

    <!-- Tabelas de Dados -->
    <div class="reports-grid">
        <!-- Tickets por Categoria -->
        <div class="report-card">
            <h3><i class="fas fa-tags"></i> Tickets por Categoria</h3>
            <div class="report-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th>Quantidade</th>
                            <th>Percentual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <?php $percentage = ($cat['count'] / $stats['total_tickets']) * 100; ?>
                            <tr>
                                <td><?= htmlspecialchars($cat['category']) ?></td>
                                <td><?= number_format($cat['count']) ?></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?= $percentage ?>%"></div>
                                        <span><?= number_format($percentage, 1) ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top 10 Empresas -->
        <div class="report-card">
            <h3><i class="fas fa-building"></i> Top 10 Empresas</h3>
            <div class="report-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>Tickets</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $company): ?>
                            <tr>
                                <td><?= htmlspecialchars($company['name']) ?></td>
                                <td><?= number_format($company['count']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Performance dos Atendentes -->
    <div class="report-card-full">
        <h3><i class="fas fa-users"></i> Performance dos Atendentes</h3>
        <div class="report-table">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Atendente</th>
                        <th>Tickets Atendidos</th>
                        <th>Tickets Finalizados</th>
                        <th>Taxa de Conclusão</th>
                        <th>Tempo Médio (horas)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($performance as $perf): ?>
                        <?php 
                        $completion_rate = $perf['tickets_handled'] > 0 
                            ? ($perf['tickets_closed'] / $perf['tickets_handled']) * 100 
                            : 0;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($perf['name']) ?></td>
                            <td><?= number_format($perf['tickets_handled']) ?></td>
                            <td><?= number_format($perf['tickets_closed']) ?></td>
                            <td>
                                <span class="badge badge-<?= $completion_rate >= 80 ? 'success' : ($completion_rate >= 50 ? 'warning' : 'danger') ?>">
                                    <?= number_format($completion_rate, 1) ?>%
                                </span>
                            </td>
                            <td><?= number_format($perf['avg_resolution_time'] ?? 0, 1) ?>h</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Dados para os gráficos
const statusData = {
    labels: ['Criados', 'Assumidos', 'Despachados', 'Em Andamento', 'Resolvidos', 'Encerrados'],
    values: [
        <?= $stats['created'] ?>, 
        <?= $stats['assumed'] ?>, 
        <?= $stats['dispatched'] ?>,
        <?= $stats['in_progress'] ?>,
        <?= $stats['resolved'] ?>,
        <?= $stats['closed'] ?>
    ]
};

const priorityData = {
    labels: ['Alta', 'Média', 'Baixa'],
    values: [
        <?= $stats['high_priority'] ?>,
        <?= $stats['medium_priority'] ?>,
        <?= $stats['low_priority'] ?>
    ]
};

const timelineData = {
    labels: [<?php echo implode(',', array_map(function($d) { return "'" . date('d/m', strtotime($d['date'])) . "'"; }, $daily_tickets)); ?>],
    values: [<?php echo implode(',', array_column($daily_tickets, 'count')); ?>]
};

// Gráfico de Status (Pizza)
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: statusData.labels,
        datasets: [{
            data: statusData.values,
            backgroundColor: [
                '#6c757d', '#17a2b8', '#ffc107', 
                '#007bff', '#28a745', '#20c997'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {position: 'bottom'}
        }
    }
});

// Gráfico de Prioridade (Barras)
new Chart(document.getElementById('priorityChart'), {
    type: 'bar',
    data: {
        labels: priorityData.labels,
        datasets: [{
            label: 'Tickets',
            data: priorityData.values,
            backgroundColor: ['#dc3545', '#ffc107', '#28a745']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {display: false}
        },
        scales: {
            y: {beginAtZero: true}
        }
    }
});

// Gráfico de Linha (Timeline)
new Chart(document.getElementById('timelineChart'), {
    type: 'line',
    data: {
        labels: timelineData.labels,
        datasets: [{
            label: 'Tickets Criados',
            data: timelineData.values,
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {display: true}
        },
        scales: {
            y: {beginAtZero: true}
        }
    }
});

// Funções auxiliares
function changePeriod(period) {
    window.location.href = '?period=' + period;
}

function exportReport() {
    window.location.href = '../../ajax/export_report.php?period=<?= $period ?>';
}
</script>

<?php include_once '../../includes/footer.php'; ?>