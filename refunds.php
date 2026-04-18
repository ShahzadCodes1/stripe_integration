<?php
// refunds.php
require_once 'includes/config.php';
$pageTitle = 'Refunds';

try {
    $db = db();
    $refunds = $db->query("
        SELECT r.*, p.amount AS payment_amount, p.currency, p.stripe_payment_intent_id,
               c.name AS customer_name, c.email AS customer_email
        FROM refunds r
        JOIN payments p ON p.id = r.payment_id
        LEFT JOIN customers c ON c.id = p.customer_id
        ORDER BY r.created_at DESC
        LIMIT 100
    ")->fetchAll();
    $totalRefunded = $db->query("SELECT COALESCE(SUM(amount),0) FROM refunds WHERE status='succeeded'")->fetchColumn();
    $totalCount    = $db->query("SELECT COUNT(*) FROM refunds WHERE status='succeeded'")->fetchColumn();
} catch (Exception $e) {
    $refunds = []; $totalRefunded = 0; $totalCount = 0;
}

require_once 'includes/header.php';
?>

<div class="stat-grid" style="margin-bottom:24px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
  <div class="stat-card">
    <div class="stat-icon" style="background:#fde8ec;color:var(--danger);">↩</div>
    <div class="stat-label">Total Refunded</div>
    <div class="stat-value"><?= formatMoney((float)$totalRefunded) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fde8ec;color:var(--danger);">#</div>
    <div class="stat-label">Refund Count</div>
    <div class="stat-value"><?= number_format((int)$totalCount) ?></div>
  </div>
</div>

<div class="card">
  <?php if (empty($refunds)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">↩</div>
      <h3>No refunds yet</h3>
      <p>Refunds appear here when you issue them from a payment's detail page.</p>
    </div>
  <?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th>Refund ID</th>
        <th>Customer</th>
        <th>Amount</th>
        <th>Reason</th>
        <th>Status</th>
        <th>Date</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($refunds as $r): ?>
      <tr>
        <td style="font-family:monospace;font-size:11.5px;color:var(--text-muted)"><?= h($r['stripe_refund_id'] ?? '—') ?></td>
        <td>
          <div style="font-weight:500"><?= h($r['customer_name'] ?? '—') ?></div>
          <div style="font-size:12px;color:var(--text-muted)"><?= h($r['customer_email'] ?? '') ?></div>
        </td>
        <td style="font-weight:600;color:var(--danger)">-<?= formatMoney((float)$r['amount'], $r['currency']) ?></td>
        <td style="color:var(--text-muted)"><?= h(str_replace('_',' ',$r['reason'] ?? '—')) ?></td>
        <td><span class="badge badge-<?= $r['status']==='succeeded'?'success':($r['status']==='failed'?'danger':'warning') ?>"><?= ucfirst(h($r['status'])) ?></span></td>
        <td style="color:var(--text-muted);font-size:12.5px"><?= date('M j, Y g:i a', strtotime($r['created_at'])) ?></td>
        <td><a href="payment_detail.php?id=<?= $r['payment_id'] ?>" class="btn btn-outline btn-sm">Payment</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
