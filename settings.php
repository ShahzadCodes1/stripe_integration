<?php
// settings.php
require_once 'includes/config.php';
$pageTitle = 'API Settings';

$cfg = getStripeKeys();
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['stripe_publishable_key','stripe_secret_key','stripe_webhook_secret','currency','business_name'];
    try {
        $db = db();
        foreach ($fields as $f) {
            $val = trim($_POST[$f] ?? '');
            $db->prepare("INSERT INTO settings (key_name,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?")->execute([$f,$val,$val]);
        }
        $success = 'Settings saved successfully!';
        $cfg = getStripeKeys();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once 'includes/header.php';
?>

<div style="max-width:700px;">

<?php if ($error):   ?><div class="alert alert-danger"  data-auto-hide>❌ <?= h($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success" data-auto-hide>✅ <?= h($success) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:20px;">
  <div class="card-header"><span class="card-title">🔑 Stripe API Keys</span></div>
  <div class="card-body">
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Business Name</label>
        <input type="text" name="business_name" class="form-control" value="<?= h($cfg['business_name'] ?? '') ?>" placeholder="My Business" />
      </div>

      <hr style="border:none;border-top:1px solid var(--border);margin:18px 0;">

      <div class="form-group">
        <label class="form-label">Publishable Key <span style="color:var(--text-muted)">(pk_test_… or pk_live_…)</span></label>
        <input type="text" name="stripe_publishable_key" class="form-control"
               value="<?= h($cfg['stripe_publishable_key'] ?? '') ?>"
               placeholder="pk_test_..." autocomplete="off" />
        <div class="form-hint">Used in the browser (Stripe.js). Safe to expose publicly.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Secret Key <span style="color:var(--danger)">★ Keep private</span></label>
        <div style="position:relative;">
          <input type="password" name="stripe_secret_key" id="secret_key_input" class="form-control"
                 value="<?= h($cfg['stripe_secret_key'] ?? '') ?>"
                 placeholder="sk_test_..." autocomplete="off" />
          <button type="button" onclick="toggleSecret()" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:13px;" id="toggle-secret-btn">👁 Show</button>
        </div>
        <div class="form-hint">Server-side only. Never expose this in front-end code.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Webhook Secret <span style="color:var(--text-muted)">(whsec_…)</span></label>
        <input type="password" name="stripe_webhook_secret" class="form-control"
               value="<?= h($cfg['stripe_webhook_secret'] ?? '') ?>"
               placeholder="whsec_..." autocomplete="off" />
        <div class="form-hint">Found in the Stripe Dashboard → Webhooks → your endpoint → Signing secret.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Default Currency</label>
        <select name="currency" class="form-control" style="max-width:280px;">
          <?php
          $currencies = ['usd'=>'USD — US Dollar','eur'=>'EUR — Euro','gbp'=>'GBP — Pound Sterling','bhd'=>'BHD — Bahraini Dinar','jpy'=>'JPY — Japanese Yen','cad'=>'CAD — Canadian Dollar','aud'=>'AUD — Australian Dollar','sgd'=>'SGD — Singapore Dollar'];
          $currentCur = strtolower($cfg['currency'] ?? 'usd');
          foreach ($currencies as $code => $label):
          ?>
            <option value="<?= $code ?>" <?= $currentCur===$code?'selected':'' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" class="btn btn-primary" style="padding:10px 28px;">Save Settings</button>
    </form>
  </div>
</div>

<!-- Webhook guide -->
<div class="card">
  <div class="card-header"><span class="card-title">🔗 Webhook Setup</span></div>
  <div class="card-body" style="font-size:13.5px;line-height:1.7;color:var(--text-secondary);">
    <p style="margin-bottom:12px;">To receive real-time payment events, configure a webhook endpoint in your Stripe Dashboard:</p>
    <ol style="padding-left:20px;margin-bottom:14px;">
      <li>Go to <strong>Stripe Dashboard → Developers → Webhooks</strong></li>
      <li>Click <strong>Add endpoint</strong></li>
      <li>Set the URL to:<br>
        <code style="background:#f0f2f5;padding:4px 10px;border-radius:5px;font-size:12.5px;display:inline-block;margin-top:4px;"><?= h(BASE_URL) ?>/webhook_handler.php</code>
      </li>
      <li>Select events: <code>payment_intent.succeeded</code>, <code>payment_intent.payment_failed</code>, <code>charge.refunded</code></li>
      <li>Copy the Signing secret and paste it above</li>
    </ol>
    <div class="alert alert-info" style="margin:0;">
      💡 Webhooks ensure your database stays in sync even if the user closes the browser mid-payment.
    </div>
  </div>
</div>

</div>

<script>
function toggleSecret() {
  const inp = document.getElementById('secret_key_input');
  const btn = document.getElementById('toggle-secret-btn');
  if (inp.type === 'password') { inp.type = 'text'; btn.textContent = '🙈 Hide'; }
  else { inp.type = 'password'; btn.textContent = '👁 Show'; }
}
</script>

<?php require_once 'includes/footer.php'; ?>
