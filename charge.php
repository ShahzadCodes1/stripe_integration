<?php
// charge.php — New Payment with Stripe Elements
require_once 'includes/config.php';
require_once 'includes/stripe.php';
$pageTitle = 'New Charge';
$cfg = getStripeKeys();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_intent') {
        // Create Payment Intent and return client secret as JSON
        header('Content-Type: application/json');

        $amount      = (float)($_POST['amount'] ?? 0);
        $currency    = strtolower(trim($_POST['currency'] ?? 'usd'));
        $description = trim($_POST['description'] ?? '');
        $custName    = trim($_POST['customer_name'] ?? '');
        $custEmail   = trim($_POST['customer_email'] ?? '');

        if ($amount <= 0 || empty($cfg['stripe_secret_key'])) {
            echo json_encode(['error' => 'Invalid amount or Stripe not configured.']);
            exit;
        }

        $stripe = new StripeAPI($cfg['stripe_secret_key']);

        // Upsert customer in DB
        $customerId = null;
        if ($custEmail) {
            try {
                $stmt = db()->prepare("SELECT id FROM customers WHERE email=?");
                $stmt->execute([$custEmail]);
                $existingCust = $stmt->fetch();
                if ($existingCust) {
                    $customerId = $existingCust['id'];
                } else {
                    $ins = db()->prepare("INSERT INTO customers (name, email) VALUES (?,?)");
                    $ins->execute([$custName ?: $custEmail, $custEmail]);
                    $customerId = db()->lastInsertId();
                }
            } catch (Exception $e) {}
        }

        // Create PI on Stripe
        $amountCents = (int)round($amount * 100);
        $pi = $stripe->createPaymentIntent($amountCents, $currency, $description, ['customer_name' => $custName]);

        if (isset($pi['error'])) {
            echo json_encode(['error' => $pi['error']['message']]);
            exit;
        }

        // Save pending payment to DB
        try {
            $ins = db()->prepare("
                INSERT INTO payments (customer_id, stripe_payment_intent_id, amount, currency, description, status)
                VALUES (?,?,?,?,?,'pending')
            ");
            $ins->execute([$customerId, $pi['id'], $amount, $currency, $description]);
        } catch (Exception $e) {}

        echo json_encode([
            'clientSecret' => $pi['client_secret'],
            'paymentIntentId' => $pi['id'],
        ]);
        exit;
    }

    if ($action === 'confirm_payment') {
        // Called after Stripe.js confirms — update DB status
        header('Content-Type: application/json');
        $piId = trim($_POST['payment_intent_id'] ?? '');

        if (empty($piId) || empty($cfg['stripe_secret_key'])) {
            echo json_encode(['error' => 'Missing data.']);
            exit;
        }

        $stripe = new StripeAPI($cfg['stripe_secret_key']);
        $pi     = $stripe->retrievePaymentIntent($piId);

        if (isset($pi['error'])) {
            echo json_encode(['error' => $pi['error']['message']]);
            exit;
        }

        $status      = $pi['status'] === 'succeeded' ? 'succeeded' : ($pi['status'] === 'canceled' ? 'canceled' : 'failed');
        $chargeId    = $pi['latest_charge'] ?? null;
        $cardBrand   = null; $cardLast4 = null; $cardExpM = null; $cardExpY = null; $receiptUrl = null;

        if ($chargeId) {
            $charge = $stripe->retrieveCharge($chargeId);
            $pm     = $charge['payment_method_details']['card'] ?? [];
            $cardBrand  = $pm['brand'] ?? null;
            $cardLast4  = $pm['last4'] ?? null;
            $cardExpM   = $pm['exp_month'] ?? null;
            $cardExpY   = $pm['exp_year'] ?? null;
            $receiptUrl = $charge['receipt_url'] ?? null;
            $failMsg    = $charge['failure_message'] ?? null;
        }

        try {
            db()->prepare("
                UPDATE payments
                SET status=?, stripe_charge_id=?, card_brand=?, card_last4=?,
                    card_exp_month=?, card_exp_year=?, receipt_url=?, failure_message=?
                WHERE stripe_payment_intent_id=?
            ")->execute([$status, $chargeId, $cardBrand, $cardLast4, $cardExpM, $cardExpY, $receiptUrl, $failMsg ?? null, $piId]);

            $row = db()->prepare("SELECT id FROM payments WHERE stripe_payment_intent_id=?");
            $row->execute([$piId]);
            $payment = $row->fetch();
            $paymentId = $payment['id'] ?? null;
        } catch (Exception $e) {}

        echo json_encode([
            'status'    => $status,
            'paymentId' => $paymentId ?? null,
            'receiptUrl'=> $receiptUrl,
        ]);
        exit;
    }
}

require_once 'includes/header.php';
$pubKey = $cfg['stripe_publishable_key'] ?? '';
$defaultCurrency = strtolower($cfg['currency'] ?? 'usd');
?>

<div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start;">

  <!-- ── Left: Payment Form ── -->
  <div>
    <div class="card">
      <div class="card-header">
        <span class="card-title">💳 Charge a Card</span>
      </div>
      <div class="card-body">

        <div id="payment-alert" class="alert" style="display:none;"></div>

        <!-- Step 1: Payment details -->
        <div id="step-1">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Customer Name</label>
              <input type="text" id="customer_name" class="form-control" placeholder="Jane Doe" />
            </div>
            <div class="form-group">
              <label class="form-label">Customer Email</label>
              <input type="email" id="customer_email" class="form-control" placeholder="jane@example.com" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Amount</label>
              <input type="number" id="amount" class="form-control" placeholder="0.00" min="0.50" step="0.01" />
            </div>
            <div class="form-group">
              <label class="form-label">Currency</label>
              <select id="currency" class="form-control">
                <option value="usd" <?= $defaultCurrency==='usd'?'selected':'' ?>>USD — US Dollar</option>
                <option value="eur" <?= $defaultCurrency==='eur'?'selected':'' ?>>EUR — Euro</option>
                <option value="gbp" <?= $defaultCurrency==='gbp'?'selected':'' ?>>GBP — Pound Sterling</option>
                <option value="bhd" <?= $defaultCurrency==='bhd'?'selected':'' ?>>BHD — Bahraini Dinar</option>
                <option value="jpy" <?= $defaultCurrency==='jpy'?'selected':'' ?>>JPY — Japanese Yen</option>
                <option value="cad">CAD — Canadian Dollar</option>
                <option value="aud">AUD — Australian Dollar</option>
                <option value="sgd">SGD — Singapore Dollar</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Description <span style="color:var(--text-muted)">(optional)</span></label>
            <input type="text" id="description" class="form-control" placeholder="Service charge, order #1234..." />
          </div>

          <!-- Stripe Card Element -->
          <div class="form-group">
            <label class="form-label">Card Details</label>
            <?php if (!$pubKey): ?>
              <div class="alert alert-danger">Stripe Publishable Key not set. <a href="settings.php">Configure API Keys →</a></div>
            <?php else: ?>
              <div class="stripe-element-wrap" id="card-element-wrap">
                <div id="card-element"></div>
              </div>
              <div id="card-errors" style="color:var(--danger);font-size:12.5px;margin-top:6px;"></div>
            <?php endif; ?>
          </div>

          <button class="btn btn-primary" id="pay-btn" onclick="handlePayment()" style="width:100%;justify-content:center;padding:12px;" <?= !$pubKey ? 'disabled' : '' ?>>
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            <span id="pay-btn-text">Charge Card</span>
          </button>

          <p style="font-size:11.5px;color:var(--text-muted);text-align:center;margin-top:12px;">
            🔒 Payments are processed securely via Stripe. Card data never touches your server.
          </p>
        </div>

        <!-- Success state -->
        <div id="step-success" style="display:none;text-align:center;padding:32px 0;">
          <div style="font-size:56px;margin-bottom:16px;">✅</div>
          <h2 style="font-size:22px;font-weight:700;color:var(--success);margin-bottom:8px;">Payment Successful!</h2>
          <p style="color:var(--text-muted);margin-bottom:24px;" id="success-amount"></p>
          <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
            <a id="receipt-link" href="#" target="_blank" class="btn btn-outline" style="display:none;">View Receipt</a>
            <a id="detail-link" href="#" class="btn btn-primary">View Payment</a>
            <button onclick="resetForm()" class="btn btn-outline">New Charge</button>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- ── Right: Card preview + tips ── -->
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- Card visual -->
    <div class="card-visual" id="card-preview">
      <div class="card-visual-chip">▣</div>
      <div class="card-visual-number" id="preview-number">•••• •••• •••• ••••</div>
      <div class="card-visual-meta">
        <div>
          <div class="card-visual-label">Card Holder</div>
          <div id="preview-name">YOUR NAME</div>
        </div>
        <div>
          <div class="card-visual-label">Expires</div>
          <div id="preview-expiry">MM/YY</div>
        </div>
        <div style="font-size:20px;align-self:center" id="preview-brand">💳</div>
      </div>
    </div>

    <!-- Amount preview -->
    <div class="card" style="text-align:center;padding:20px;">
      <div style="font-size:11px;color:var(--text-muted);font-weight:600;letter-spacing:.5px;text-transform:uppercase;margin-bottom:8px;">Charge Amount</div>
      <div style="font-size:32px;font-weight:700;color:var(--text-primary)" id="amount-preview">$0.00</div>
      <div style="font-size:12px;color:var(--text-muted);margin-top:4px;" id="currency-preview">US Dollar</div>
    </div>

    <!-- Test card hint -->
    <div class="card">
      <div class="card-header"><span class="card-title">🧪 Test Cards</span></div>
      <div class="card-body" style="padding:14px 18px;">
        <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:10px;">Use these in Test Mode:</div>
        <?php
        $testCards = [
            ['4242 4242 4242 4242', 'success', 'Visa — Succeeds'],
            ['4000 0025 6000 0051', 'info', '3D Secure Required'],
            ['4000 0000 0000 9995', 'danger', 'Always Declined'],
            ['5555 5555 5555 4444', 'success', 'Mastercard — Succeeds'],
        ];
        foreach ($testCards as [$num, $cls, $label]):
        ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border);">
          <span style="font-family:monospace;font-size:12px;"><?= $num ?></span>
          <span class="badge badge-<?= $cls ?>" style="font-size:10.5px;"><?= $label ?></span>
        </div>
        <?php endforeach; ?>
        <div style="font-size:11.5px;color:var(--text-muted);margin-top:10px;">Any future date & any 3-digit CVC</div>
      </div>
    </div>
  </div>
</div>

<?php if ($pubKey): ?>
<script>
const stripe = Stripe('<?= h($pubKey) ?>');
const elements = stripe.elements();
const cardElement = elements.create('card', {
  style: {
    base: {
      fontFamily: '"Inter", sans-serif',
      fontSize: '14px',
      color: '#0a2540',
      '::placeholder': { color: '#adb5bd' },
    }
  }
});
cardElement.mount('#card-element');

const wrap = document.getElementById('card-element-wrap');
cardElement.on('focus',  () => wrap.classList.add('focused'));
cardElement.on('blur',   () => wrap.classList.remove('focused'));
cardElement.on('change', e => {
  document.getElementById('card-errors').textContent = e.error ? e.error.message : '';
  // Update preview brand
  const brands = { visa:'💳', mastercard:'💳', amex:'💳', discover:'💳' };
  document.getElementById('preview-brand').textContent = '💳';
});

// Live update card preview
document.getElementById('customer_name').addEventListener('input', function() {
  document.getElementById('preview-name').textContent = (this.value || 'YOUR NAME').toUpperCase();
});
document.getElementById('amount').addEventListener('input', updateAmountPreview);
document.getElementById('currency').addEventListener('change', updateAmountPreview);

const currencyNames = {usd:'US Dollar',eur:'Euro',gbp:'Pound Sterling',bhd:'Bahraini Dinar',jpy:'Japanese Yen',cad:'Canadian Dollar',aud:'Australian Dollar',sgd:'Singapore Dollar'};
const currencySymbols = {usd:'$',eur:'€',gbp:'£',bhd:'BD',jpy:'¥',cad:'$',aud:'$',sgd:'$'};

function updateAmountPreview() {
  const amt = parseFloat(document.getElementById('amount').value) || 0;
  const cur = document.getElementById('currency').value;
  const sym = currencySymbols[cur] || cur.toUpperCase()+' ';
  document.getElementById('amount-preview').textContent = sym + amt.toFixed(2);
  document.getElementById('currency-preview').textContent = currencyNames[cur] || cur.toUpperCase();
}
updateAmountPreview();

async function handlePayment() {
  const btn = document.getElementById('pay-btn');
  const btnTxt = document.getElementById('pay-btn-text');
  const alertEl = document.getElementById('payment-alert');

  const amount      = parseFloat(document.getElementById('amount').value);
  const currency    = document.getElementById('currency').value;
  const description = document.getElementById('description').value;
  const custName    = document.getElementById('customer_name').value;
  const custEmail   = document.getElementById('customer_email').value;

  if (!amount || amount < 0.5) {
    showAlert('danger', 'Please enter a valid amount (minimum 0.50).');
    return;
  }

  btn.disabled = true;
  btnTxt.innerHTML = '<span class="spinner"></span> Processing…';
  hideAlert();

  try {
    // 1. Create Payment Intent
    const fd = new FormData();
    fd.append('action', 'create_intent');
    fd.append('amount', amount);
    fd.append('currency', currency);
    fd.append('description', description);
    fd.append('customer_name', custName);
    fd.append('customer_email', custEmail);

    const r1 = await fetch('charge.php', { method: 'POST', body: fd });
    const d1 = await r1.json();

    if (d1.error) { showAlert('danger', d1.error); resetBtn(); return; }

    // 2. Confirm with Stripe.js
    const { paymentIntent, error } = await stripe.confirmCardPayment(d1.clientSecret, {
      payment_method: {
        card: cardElement,
        billing_details: {
          name:  custName  || undefined,
          email: custEmail || undefined,
        }
      }
    });

    if (error) { showAlert('danger', error.message); resetBtn(); return; }

    // 3. Confirm in our DB
    const fd2 = new FormData();
    fd2.append('action', 'confirm_payment');
    fd2.append('payment_intent_id', d1.paymentIntentId);
    const r2 = await fetch('charge.php', { method: 'POST', body: fd2 });
    const d2 = await r2.json();

    if (d2.error) { showAlert('danger', d2.error); resetBtn(); return; }

    // Show success
    const cur = document.getElementById('currency').value;
    const sym = currencySymbols[cur] || cur.toUpperCase()+' ';
    document.getElementById('success-amount').textContent = sym + amount.toFixed(2) + ' payment completed successfully';
    if (d2.receiptUrl) {
      const rl = document.getElementById('receipt-link');
      rl.href = d2.receiptUrl; rl.style.display = 'inline-flex';
    }
    if (d2.paymentId) {
      document.getElementById('detail-link').href = 'payment_detail.php?id=' + d2.paymentId;
    }
    document.getElementById('step-1').style.display = 'none';
    document.getElementById('step-success').style.display = 'block';

  } catch(e) {
    showAlert('danger', 'Network error. Please try again.');
    resetBtn();
  }
}

function resetBtn() {
  const btn = document.getElementById('pay-btn');
  btn.disabled = false;
  document.getElementById('pay-btn-text').innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> Charge Card';
}

function resetForm() {
  document.getElementById('step-1').style.display = 'block';
  document.getElementById('step-success').style.display = 'none';
  document.getElementById('amount').value = '';
  document.getElementById('description').value = '';
  document.getElementById('customer_name').value = '';
  document.getElementById('customer_email').value = '';
  document.getElementById('preview-name').textContent = 'YOUR NAME';
  updateAmountPreview();
  resetBtn();
  cardElement.clear();
}

function showAlert(type, msg) {
  const el = document.getElementById('payment-alert');
  el.className = 'alert alert-' + type;
  el.innerHTML = (type === 'danger' ? '❌ ' : '✅ ') + msg;
  el.style.display = 'flex';
}
function hideAlert() {
  document.getElementById('payment-alert').style.display = 'none';
}
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
