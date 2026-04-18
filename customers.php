<?php
// customers.php
require_once 'includes/config.php';
$pageTitle = 'Customers';

$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    $db = db();
    $where = $search ? 'WHERE c.name LIKE ? OR c.email LIKE ?' : '';
    $params = $search ? ["%$search%", "%$search%"] : [];

    $total = $db->prepare("SELECT COUNT(*) FROM customers c $where");
    $total->execute($params);
    $totalCount = (int)$total->fetchColumn();

    $stmt = $db->prepare("
        SELECT c.*,
               COUNT(p.id) AS payment_count,
               COALESCE(SUM(CASE WHEN p.status='succeeded' THEN p.amount ELSE 0 END),0) AS total_spent
        FROM customers c
        LEFT JOIN payments p ON p.customer_id = c.id
        $where
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $customers = $stmt->fetchAll();
} catch (Exception $e) {
    $customers = []; $totalCount = 0;
}

$totalPages = max(1, (int)ceil($totalCount / $perPage));
require_once 'includes/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;gap:12px;flex-wrap:wrap;">
  <div style="color:var(--text-muted);font-size:13px;"><?= number_format($totalCount) ?> customers</div>
  <form method="GET" style="display:flex;gap:10px;align-items:center;">
    <div class="search-wrap">
      <svg class="search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" name="search" class="form-control" style="width:240px;" placeholder="Search name or email…" value="<?= h($search) ?>" />
    </div>
    <button class="btn btn-outline btn-sm">Search</button>
    <?php if ($search): ?><a href="customers.php" class="btn btn-outline btn-sm">✕</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <?php if (empty($customers)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">👥</div>
      <h3>No customers yet</h3>
      <p>Customers are created automatically when you process a payment with an email.</p>
    </div>
  <?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th>Customer</th>
        <th>Stripe ID</th>
        <th>Payments</th>
        <th>Total Spent</th>
        <th>Joined</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($customers as $c): ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:34px;height:34px;border-radius:50%;background:var(--stripe-purple-l);color:var(--stripe-purple);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">
              <?= strtoupper(mb_substr($c['name'], 0, 1)) ?>
            </div>
            <div>
              <div style="font-weight:500"><?= h($c['name']) ?></div>
              <div style="font-size:12px;color:var(--text-muted)"><?= h($c['email']) ?></div>
            </div>
          </div>
        </td>
        <td style="font-family:monospace;font-size:11.5px;color:var(--text-muted)"><?= h($c['stripe_customer_id'] ?? '—') ?></td>
        <td><?= number_format($c['payment_count']) ?></td>
        <td style="font-weight:600"><?= formatMoney((float)$c['total_spent']) ?></td>
        <td style="color:var(--text-muted);font-size:12.5px"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($totalPages > 1): ?>
  <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
    <span style="font-size:12.5px;color:var(--text-muted)">Showing <?= $offset+1 ?>–<?= min($offset+$perPage,$totalCount) ?> of <?= $totalCount ?></span>
    <div class="pagination">
      <?php if ($page>1): ?><a href="?page=<?=$page-1?>&search=<?=h($search)?>" class="page-btn">‹</a><?php endif; ?>
      <?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?><a href="?page=<?=$i?>&search=<?=h($search)?>" class="page-btn <?=$i===$page?'active':''?>"><?=$i?></a><?php endfor; ?>
      <?php if ($page<$totalPages): ?><a href="?page=<?=$page+1?>&search=<?=h($search)?>" class="page-btn">›</a><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
