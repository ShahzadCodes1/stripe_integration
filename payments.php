<?php
// payments.php — All Payments
require_once 'includes/config.php';
$pageTitle = 'All Payments';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$status  = $_GET['status'] ?? '';
$search  = trim($_GET['search'] ?? '');

$where  = '1=1';
$params = [];
if ($status) { $where .= ' AND p.status = ?'; $params[] = $status; }
if ($search) { $where .= ' AND (c.name LIKE ? OR c.email LIKE ? OR p.stripe_payment_intent_id LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }

try {
    $db = db();
    $total = $db->prepare("SELECT COUNT(*) FROM payments p LEFT JOIN customers c ON c.id=p.customer_id WHERE $where");
    $total->execute($params);
    $totalCount = (int)$total->fetchColumn();

    $stmt = $db->prepare("
        SELECT p.*, c.name AS customer_name, c.email AS customer_email
        FROM payments p
        LEFT JOIN customers c ON c.id = p.customer_id
        WHERE $where
        ORDER BY p.created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
} catch (Exception $e) {
    $payments = []; $totalCount = 0;
}

$totalPages = max(1, (int)ceil($totalCount / $perPage));
require_once 'includes/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;gap:12px;flex-wrap:wrap;">
  <div style="color:var(--text-muted);font-size:13px;"><?= number_format($totalCount) ?> payments</div>

  <!-- Filters -->
  <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <div class="search-wrap">
      <svg class="search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" name="search" class="form-control" style="width:220px;" placeholder="Search name, email, ID…" value="<?= h($search) ?>" />
    </div>
    <select name="status" class="form-control" style="width:150px;" onchange="this.form.submit()">
      <option value="">All Status</option>
      <?php foreach (['succeeded','pending','failed','refunded','canceled'] as $s): ?>
        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline btn-sm">Filter</button>
    <?php if ($status || $search): ?>
      <a href="payments.php" class="btn btn-outline btn-sm">✕ Clear</a>
    <?php endif; ?>
  </form>

  <a href="charge.php" class="btn btn-primary btn-sm">+ New Charge</a>
</div>

<div class="card">
  <?php if (empty($payments)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">💳</div>
      <h3>No payments found</h3>
      <p><?= $search || $status ? 'Try adjusting your filters.' : 'Create your first payment.' ?></p>
    </div>
  <?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Customer</th>
        <th>Amount</th>
        <th>Status</th>
        <th>Card</th>
        <th>Description</th>
        <th>Date</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php
    $badgeMap = ['succeeded'=>'success','failed'=>'danger','pending'=>'warning','refunded'=>'info','canceled'=>'muted'];
    foreach ($payments as $p):
    ?>
      <tr>
        <td style="font-family:monospace;font-size:11.5px;color:var(--text-muted);max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= h($p['stripe_payment_intent_id'] ?? '') ?>">
          <?= $p['id'] ?>
        </td>
        <td>
          <div style="font-weight:500"><?= h($p['customer_name'] ?? '—') ?></div>
          <div style="font-size:12px;color:var(--text-muted)"><?= h($p['customer_email'] ?? '') ?></div>
        </td>
        <td style="font-weight:600"><?= formatMoney((float)$p['amount'], $p['currency']) ?></td>
        <td><span class="badge badge-<?= $badgeMap[$p['status']] ?? 'muted' ?>"><?= ucfirst(h($p['status'])) ?></span></td>
        <td>
          <?php if ($p['card_brand']): ?>
            <span style="text-transform:capitalize"><?= h($p['card_brand']) ?></span> •••• <?= h($p['card_last4'] ?? '') ?>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text-muted);font-size:12.5px;"><?= h($p['description'] ?? '—') ?></td>
        <td style="color:var(--text-muted);font-size:12.5px;white-space:nowrap"><?= date('M j, Y g:i a', strtotime($p['created_at'])) ?></td>
        <td>
          <a href="payment_detail.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">View</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
    <span style="font-size:12.5px;color:var(--text-muted)">
      Showing <?= number_format($offset+1) ?>–<?= number_format(min($offset+$perPage,$totalCount)) ?> of <?= number_format($totalCount) ?>
    </span>
    <div class="pagination">
      <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>&status=<?= h($status) ?>&search=<?= h($search) ?>" class="page-btn">‹</a><?php endif; ?>
      <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
        <a href="?page=<?= $i ?>&status=<?= h($status) ?>&search=<?= h($search) ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?><a href="?page=<?= $page+1 ?>&status=<?= h($status) ?>&search=<?= h($search) ?>" class="page-btn">›</a><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
