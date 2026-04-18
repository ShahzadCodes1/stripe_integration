<?php
// webhook.php — Webhook event log viewer
require_once 'includes/config.php';
$pageTitle = 'Webhook Logs';

try {
    $db = db();
    $logs = $db->query("SELECT * FROM webhook_logs ORDER BY created_at DESC LIMIT 200")->fetchAll();
    $total = $db->query("SELECT COUNT(*) FROM webhook_logs")->fetchColumn();
    $processed = $db->query("SELECT COUNT(*) FROM webhook_logs WHERE status='processed'")->fetchColumn();
    $failed = $db->query("SELECT COUNT(*) FROM webhook_logs WHERE status='failed'")->fetchColumn();
} catch (Exception $e) {
    $logs = []; $total = $processed = $failed = 0;
}

require_once 'includes/header.php';
$cfg = getStripeKeys();
?>

<div class="stat-grid" style="margin-bottom:24px;grid-template-columns:repeat(3,1fr);">
  <div class="stat-card">
    <div class="stat-label">Total Events</div>
    <div class="stat-value"><?= number_format((int)$total) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Processed</div>
    <div class="stat-value" style="color:var(--success)"><?= number_format((int)$processed) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Failed</div>
    <div class="stat-value" style="color:var(--danger)"><?= number_format((int)$failed) ?></div>
  </div>
</div>

<div class="card" style="margin-bottom:18px;">
  <div class="card-header"><span class="card-title">🔗 Webhook Endpoint URL</span></div>
  <div class="card-body" style="display:flex;align-items:center;gap:12px;">
    <code style="background:#f0f2f5;padding:8px 14px;border-radius:6px;font-size:13px;flex:1;"><?= h(BASE_URL) ?>/webhook_handler.php</code>
    <button onclick="navigator.clipboard.writeText('<?= h(BASE_URL) ?>/webhook_handler.php');this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',2000)" class="btn btn-outline btn-sm">Copy</button>
    <a href="settings.php" class="btn btn-primary btn-sm">Configure Keys</a>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Event Log</span>
    <span style="font-size:12px;color:var(--text-muted)">Last 200 events</span>
  </div>
  <?php if (empty($logs)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">📡</div>
      <h3>No webhook events received yet</h3>
      <p>Configure your webhook endpoint in the Stripe Dashboard and set the URL above.</p>
    </div>
  <?php else: ?>
  <table class="data-table">
    <thead>
      <tr><th>Event ID</th><th>Type</th><th>Status</th><th>Received</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
      <tr>
        <td style="font-family:monospace;font-size:11.5px;color:var(--text-muted)"><?= h($log['event_id']) ?></td>
        <td style="font-family:monospace;font-size:12.5px"><?= h($log['event_type']) ?></td>
        <td>
          <?php
          $cls = $log['status']==='processed' ? 'success' : ($log['status']==='failed' ? 'danger' : 'warning');
          ?>
          <span class="badge badge-<?= $cls ?>"><?= ucfirst(h($log['status'])) ?></span>
        </td>
        <td style="color:var(--text-muted);font-size:12.5px"><?= date('M j, Y g:i:s a', strtotime($log['created_at'])) ?></td>
        <td>
          <button onclick="showPayload(<?= htmlspecialchars(json_encode($log['payload'] ?? '{}'), ENT_QUOTES) ?>)" class="btn btn-outline btn-sm">Payload</button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- Payload modal -->
<div class="modal-overlay" id="payload-modal">
  <div class="modal" style="max-width:640px;">
    <div class="modal-header">
      <span class="modal-title">Webhook Payload</span>
      <button class="modal-close" onclick="closeModal()">×</button>
    </div>
    <div class="modal-body">
      <pre id="payload-content" style="background:#0a2540;color:#a8d4ff;padding:16px;border-radius:8px;font-size:12px;overflow:auto;max-height:420px;line-height:1.6;"></pre>
    </div>
  </div>
</div>

<script>
function showPayload(raw) {
  try {
    const parsed = JSON.parse(raw);
    document.getElementById('payload-content').textContent = JSON.stringify(parsed, null, 2);
  } catch(e) {
    document.getElementById('payload-content').textContent = raw;
  }
  document.getElementById('payload-modal').classList.add('open');
}
function closeModal() {
  document.getElementById('payload-modal').classList.remove('open');
}
document.getElementById('payload-modal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>

<?php require_once 'includes/footer.php'; ?>
