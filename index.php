<?php
// index.php — Dashboard
require_once 'includes/config.php';
$pageTitle = 'Dashboard';

// ── Stats ─────────────────────────────────────────────────
try {
    $db = db();

    $totalRevenue = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='succeeded'")->fetchColumn();
    $totalPayments = $db->query("SELECT COUNT(*) FROM payments WHERE status='succeeded'")->fetchColumn();
    $pendingPayments = $db->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn();
    $totalCustomers = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    $totalRefunds = $db->query("SELECT COALESCE(SUM(amount),0) FROM refunds WHERE status='succeeded'")->fetchColumn();
    $failedPayments = $db->query("SELECT COUNT(*) FROM payments WHERE status='failed'")->fetchColumn();

    // Last 30 days vs previous 30 days
    $recentRevenue = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='succeeded' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $prevRevenue   = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='succeeded' AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 60 DAY) AND DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $revenueChange = $prevRevenue > 0 ? round((($recentRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : 0;

    // Recent payments
    $recentPayments = $db->query("
        SELECT p.*, c.name AS customer_name, c.email AS customer_email
        FROM payments p
        LEFT JOIN customers c ON c.id = p.customer_id
        ORDER BY p.created_at DESC LIMIT 10
    ")->fetchAll();

    // Daily revenue chart data (last 14 days)
    $chartData = $db->query("
        SELECT DATE(created_at) AS day, COALESCE(SUM(amount),0) AS total
        FROM payments WHERE status='succeeded'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
        GROUP BY DATE(created_at)
        ORDER BY day ASC
    ")->fetchAll();

    $dbOk = true;
} catch (Exception $e) {
    $dbOk = false;
    $dbError = $e->getMessage();
    $totalRevenue = $totalPayments = $pendingPayments = $totalCustomers = $totalRefunds = $failedPayments = $revenueChange = 0;
    $recentPayments = $chartData = [];
}

$cfg = getStripeKeys();
$keysSet = !empty($cfg['stripe_secret_key']) && !empty($cfg['stripe_publishable_key']);

require_once 'includes/header.php';
?>

<?php if (!$dbOk): ?>
<div class="alert alert-danger">
  <strong>⚠ Database error:</strong> <?= h($dbError ?? '') ?> — Please import <code>database.sql</code> and check your credentials in <code>includes/config.php</code>.
</div>
<?php endif; ?>

<?php if (!$keysSet): ?>
<div class="alert alert-info" data-auto-hide>
  <span>🔑</span>
  <span>Stripe API keys not configured. <a href="settings.php" style="color:inherit;font-weight:600;">Go to API Settings →</a></span>
</div>
<?php endif; ?>

<!-- ── Stat Cards ── -->
<div class="stat-grid" style="margin-bottom:24px;">
  <div class="stat-card">
    <div class="stat-icon" style="background:#ede9fe;color:var(--stripe-purple);">💰</div>
    <div class="stat-label">Gross Revenue</div>
    <div class="stat-value"><?= formatMoney((float)$totalRevenue) ?></div>
    <div class="stat-change <?= $revenueChange >= 0 ? 'up' : 'down' ?>">
      <?= $revenueChange >= 0 ? '▲' : '▼' ?> <?= abs($revenueChange) ?>% vs last 30d
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#e3f5ed;color:var(--success);">✅</div>
    <div class="stat-label">Successful Payments</div>
    <div class="stat-value"><?= number_format((int)$totalPayments) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fef9e7;color:var(--warning);">⏳</div>
    <div class="stat-label">Pending</div>
    <div class="stat-value"><?= number_format((int)$pendingPayments) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#e8f3fd;color:var(--info);">👥</div>
    <div class="stat-label">Customers</div>
    <div class="stat-value"><?= number_format((int)$totalCustomers) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fde8ec;color:var(--danger);">↩</div>
    <div class="stat-label">Refunded</div>
    <div class="stat-value"><?= formatMoney((float)$totalRefunds) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fde8ec;color:var(--danger);">❌</div>
    <div class="stat-label">Failed</div>
    <div class="stat-value"><?= number_format((int)$failedPayments) ?></div>
  </div>
</div>

<!-- ── Revenue Chart ── -->
<div class="card" style="margin-bottom:24px;">
  <div class="card-header">
    <span class="card-title">Revenue — Last 14 Days</span>
    <a href="payments.php" class="btn btn-outline btn-sm">View all</a>
  </div>
  <div class="card-body" style="padding:16px 22px 10px;">
    <canvas id="revenueChart" height="80"></canvas>
  </div>
</div>

<!-- ── Recent Payments ── -->
<div class="card">
  <div class="card-header">
    <span class="card-title">Recent Payments</span>
    <a href="payments.php" class="btn btn-outline btn-sm">See all</a>
  </div>

  <?php if (empty($recentPayments)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">💳</div>
      <h3>No payments yet</h3>
      <p>Create your first payment to see it here.</p>
      <a href="charge.php" class="btn btn-primary" style="margin-top:16px;">New Charge</a>
    </div>
  <?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th>Customer</th>
        <th>Amount</th>
        <th>Status</th>
        <th>Card</th>
        <th>Date</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($recentPayments as $p): ?>
      <tr>
        <td>
          <div style="font-weight:500"><?= h($p['customer_name'] ?? 'Guest') ?></div>
          <div style="font-size:12px;color:var(--text-muted)"><?= h($p['customer_email'] ?? '') ?></div>
        </td>
        <td style="font-weight:600"><?= formatMoney((float)$p['amount'], $p['currency']) ?></td>
        <td>
          <?php
            $sc = ['succeeded'=>'success','failed'=>'danger','pending'=>'warning','refunded'=>'info','canceled'=>'muted'];
            $sc2 = $sc[$p['status']] ?? 'muted';
          ?>
          <span class="badge badge-<?= $sc2 ?>"><?= ucfirst(h($p['status'])) ?></span>
        </td>
        <td>
          <?php if ($p['card_brand']): ?>
            <span style="text-transform:capitalize"><?= h($p['card_brand']) ?></span>
            •••• <?= h($p['card_last4'] ?? '') ?>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td style="color:var(--text-muted);font-size:12.5px"><?= date('M j, Y g:i a', strtotime($p['created_at'])) ?></td>
        <td>
          <a href="payment_detail.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">View</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
const raw = <?= json_encode($chartData) ?>;

// Fill last 14 days
const days = [];
const amounts = [];
for (let i = 13; i >= 0; i--) {
  const d = new Date();
  d.setDate(d.getDate() - i);
  const key = d.toISOString().slice(0,10);
  days.push(d.toLocaleDateString('en-US',{month:'short',day:'numeric'}));
  const found = raw.find(r => r.day === key);
  amounts.push(found ? parseFloat(found.total) : 0);
}

new Chart(document.getElementById('revenueChart'), {
  type: 'line',
  data: {
    labels: days,
    datasets: [{
      label: 'Revenue (USD)',
      data: amounts,
      borderColor: '#635bff',
      backgroundColor: 'rgba(99,91,255,.08)',
      borderWidth: 2.5,
      fill: true,
      tension: 0.4,
      pointRadius: 3,
      pointHoverRadius: 5,
      pointBackgroundColor: '#635bff',
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#8792a2' } },
      y: { grid: { color: '#f0f2f5' }, ticks: { font: { size: 11 }, color: '#8792a2', callback: v => '$'+v } }
    }
  }
});
</script>

<?php require_once 'includes/footer.php'; ?>
