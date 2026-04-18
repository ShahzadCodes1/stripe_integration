<?php
// payment_detail.php
require_once 'includes/config.php';
require_once 'includes/stripe.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: payments.php'); exit; }

$cfg = getStripeKeys();
$error = $success = '';

try {
    $db = db();
    $stmt = $db->prepare("
        SELECT p.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone
        FROM payments p LEFT JOIN customers c ON c.id = p.customer_id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $payment = $stmt->fetch();
    if (!$payment) { header('Location: payments.php'); exit; }

    $refunds = $db->prepare("SELECT * FROM refunds WHERE payment_id=? ORDER BY created_at DESC");
    $refunds->execute([$id]);
    $refunds = $refunds->fetchAll();
} catch (Exception $e) {
    die('DB error: ' . h($e->getMessage()));
}

// Handle refund POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refund'])) {
    $refAmount = (float)($_POST['refund_amount'] ?? 0);
    $reason    = $_POST['refund_reason'] ?? 'requested_by_customer';

    if (!empty($cfg['stripe_secret_key']) && $payment['stripe_payment_intent_id']) {
        $stripe = new StripeAPI($cfg['stripe_secret_key']);
        $cents  = $refAmount > 0 ? (int)round($refAmount * 100) : 0;
        $result = $stripe->createRefund($payment['stripe_payment_intent_id'], $cents, $reason);

        if (isset($result['error'])) {
            $error = $result['error']['message'];
        } else {
            // Save refund
            $db->prepare("INSERT INTO refunds (payment_id, stripe_refund_id, amount, reason, status) VALUES (?,?,?,?,?)")
               ->execute([$id, $result['id'], $refAmount ?: $payment['amount'], $reason, $result['status']]);
            // Update payment status
            if ($result['status'] === 'succeeded') {
                $db->prepare("UPDATE payments SET status='refunded' WHERE id=?")->execute([$id]);
                $payment['status'] = 'refunded';
            }
            $success = 'Refund of ' . formatMoney($refAmount ?: $payment['amount'], $payment['currency']) . ' initiated successfully.';
        }
    } else {
        $error = 'Stripe not configured or payment intent ID missing.';
    }
}

$pageTitle = 'Payment #' . $id;
require_once 'includes/header.php';
$badgeMap = ['succeeded'=>'success','failed'=>'danger','pending'=>'warning','refunded'=>'info','canceled'=>'muted'];
?>

<div style="margin-bottom:18px;">
  <a href="payments.php" style="color:var(--text-muted);text-decoration:none;font-size:13px;">← Back to Payments</a>
</div>

<?php if ($error): ?><div class="alert alert-danger" data-auto-hide>❌ <?= h($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success" data-auto-hide>✅ <?= h($success) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start;">

  <!-- Left column -->
  <div style="display:flex;flex-direction:column;gap:18px;">

    <!-- Payment overview -->
    <div class="card">
      <div class="card-header">
        <div>
          <span class="card-title">Payment Overview</span>
          <div style="font-family:monospace;font-size:11.5px;color:var(--text-muted);margin-top:2px;"><?= h($payment['stripe_payment_intent_id'] ?? 'N/A') ?></div>
        </div>
        <span class="badge badge-<?= $badgeMap[$payment['status']] ?? 'muted' ?>" style="font-size:12px;padding:4px 12px;"><?= ucfirst(h($payment['status'])) ?></span>
      </div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">
          <div>
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);font-weight:600;margin-bottom:6px;">Amount</div>
            <div style="font-size:24px;font-weight:700;"><?= formatMoney((float)$payment['amount'], $payment['currency']) ?></div>
            <div style="font-size:11.5px;color:var(--text-muted);text-transform:uppercase"><?= h($payment['currency']) ?></div>
          </div>
          <div>
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);font-weight:600;margin-bottom:6px;">Date</div>
            <div style="font-size:14px;font-weight:500;"><?= date('M j, Y', strtotime($payment['created_at'])) ?></div>
            <div style="font-size:12px;color:var(--text-muted)"><?= date('g:i A', strtotime($payment['created_at'])) ?></div>
          </div>
          <div>
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);font-weight:600;margin-bottom:6px;">Description</div>
            <div style="font-size:13px;"><?= h($payment['description'] ?: '—') ?></div>
          </div>
        </div>

        <?php if ($payment['failure_message']): ?>
        <div class="alert alert-danger" style="margin-top:16px;margin-bottom:0;">
          <strong>Failure reason:</strong> <?= h($payment['failure_message']) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Card info -->
    <div class="card">
      <div class="card-header"><span class="card-title">💳 Payment Method</span></div>
      <div class="card-body">
        <?php if ($payment['card_brand']): ?>
        <div style="display:flex;align-items:center;gap:16px;">
          <div style="width:52px;height:34px;border:1.5px solid var(--border);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:20px;">💳</div>
          <div>
            <div style="font-weight:600;text-transform:capitalize;"><?= h($payment['card_brand']) ?> •••• <?= h($payment['card_last4'] ?? '') ?></div>
            <div style="font-size:12px;color:var(--text-muted)">Expires <?= h($payment['card_exp_month'] ?? '') ?>/<?= h($payment['card_exp_year'] ?? '') ?></div>
          </div>
          <?php if ($payment['receipt_url']): ?>
          <a href="<?= h($payment['receipt_url']) ?>" target="_blank" class="btn btn-outline btn-sm" style="margin-left:auto;">📄 Receipt</a>
          <?php endif; ?>
        </div>
        <?php else: ?>
          <div style="color:var(--text-muted);font-size:13px;">Card info not available.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Refund history -->
    <?php if (!empty($refunds)): ?>
    <div class="card">
      <div class="card-header"><span class="card-title">↩ Refunds</span></div>
      <table class="data-table">
        <thead><tr><th>Stripe Refund ID</th><th>Amount</th><th>Reason</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($refunds as $r): ?>
          <tr>
            <td style="font-family:monospace;font-size:12px;color:var(--text-muted)"><?= h($r['stripe_refund_id'] ?? '—') ?></td>
            <td style="font-weight:600"><?= formatMoney((float)$r['amount'], $payment['currency']) ?></td>
            <td style="color:var(--text-muted)"><?= h($r['reason'] ?? '—') ?></td>
            <td><span class="badge badge-<?= $r['status']==='succeeded'?'success':'warning' ?>"><?= ucfirst(h($r['status'])) ?></span></td>
            <td style="color:var(--text-muted);font-size:12px"><?= date('M j, Y g:i a', strtotime($r['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  </div>

  <!-- Right: Customer + Refund action -->
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- Customer -->
    <div class="card">
      <div class="card-header"><span class="card-title">👤 Customer</span></div>
      <div class="card-body">
        <div style="font-weight:600;margin-bottom:4px;"><?= h($payment['customer_name'] ?? 'Guest') ?></div>
        <?php if ($payment['customer_email']): ?>
          <div style="color:var(--text-muted);font-size:13px;"><?= h($payment['customer_email']) ?></div>
        <?php endif; ?>
        <?php if ($payment['customer_phone']): ?>
          <div style="color:var(--text-muted);font-size:13px;"><?= h($payment['customer_phone']) ?></div>
        <?php endif; ?>
        <?php if ($payment['customer_id']): ?>
          <a href="customers.php" class="btn btn-outline btn-sm" style="margin-top:12px;">View Customer</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Refund action -->
    <?php if ($payment['status'] === 'succeeded'): ?>
    <div class="card">
      <div class="card-header"><span class="card-title">Issue Refund</span></div>
      <div class="card-body">
        <form method="POST">
          <div class="form-group">
            <label class="form-label">Refund Amount</label>
            <input type="number" name="refund_amount" class="form-control" step="0.01" min="0.01"
                   max="<?= $payment['amount'] ?>" placeholder="<?= $payment['amount'] ?>" />
            <div class="form-hint">Leave blank to refund the full amount (<?= formatMoney((float)$payment['amount'], $payment['currency']) ?>)</div>
          </div>
          <div class="form-group">
            <label class="form-label">Reason</label>
            <select name="refund_reason" class="form-control">
              <option value="requested_by_customer">Requested by customer</option>
              <option value="duplicate">Duplicate charge</option>
              <option value="fraudulent">Fraudulent</option>
            </select>
          </div>
          <button type="submit" name="refund" class="btn btn-danger" style="width:100%;justify-content:center;"
                  onclick="return confirm('Issue a refund for this payment?')">
            ↩ Issue Refund
          </button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Raw IDs -->
    <div class="card">
      <div class="card-header"><span class="card-title">Metadata</span></div>
      <div class="card-body" style="padding:14px 18px;">
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Payment Intent ID</div>
        <div style="font-family:monospace;font-size:11.5px;word-break:break-all;color:var(--text-primary);"><?= h($payment['stripe_payment_intent_id'] ?? '—') ?></div>
        <?php if ($payment['stripe_charge_id']): ?>
        <div style="font-size:12px;color:var(--text-muted);margin-top:10px;margin-bottom:4px;">Charge ID</div>
        <div style="font-family:monospace;font-size:11.5px;word-break:break-all;"><?= h($payment['stripe_charge_id']) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<?php require_once 'includes/footer.php'; ?>
