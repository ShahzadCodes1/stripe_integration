# Stripe Payment Gateway Integration — PHP + phpMyAdmin

A full-featured Stripe payment dashboard built in PHP with a Stripe-like UI.

---

## 📁 Project Structure

```
stripe_integration/
├── index.php               ← Dashboard (stats + chart + recent payments)
├── charge.php              ← New payment form (Stripe Elements)
├── payments.php            ← All payments with search/filter/pagination
├── payment_detail.php      ← Single payment detail + issue refund
├── customers.php           ← Customer list
├── refunds.php             ← All refunds
├── webhook.php             ← Webhook log viewer
├── webhook_handler.php     ← Stripe webhook receiver (point Stripe here)
├── settings.php            ← API key configuration
├── database.sql            ← DB schema — import into phpMyAdmin
└── includes/
    ├── config.php          ← DB credentials + helpers
    ├── stripe.php          ← Stripe API wrapper (no SDK needed, pure cURL)
    ├── header.php          ← Sidebar + topbar layout
    └── footer.php          ← Closing tags + JS
```

---

## ⚡ Quick Setup

### 1. Import the database
- Open **phpMyAdmin** (http://localhost/phpmyadmin)
- Click **Import** tab
- Select `database.sql` and click Go
- Database `stripe_integration` will be created with all tables

### 2. Configure your database credentials
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'stripe_integration');
define('DB_USER', 'root');     // your phpMyAdmin username
define('DB_PASS', '');         // your phpMyAdmin password
define('BASE_URL', 'http://localhost/stripe_integration');
```

### 3. Place files in your web server
- **XAMPP**: Copy the folder to `C:/xampp/htdocs/stripe_integration/`
- **WAMP**: Copy to `C:/wamp64/www/stripe_integration/`
- **Linux Apache**: Copy to `/var/www/html/stripe_integration/`

### 4. Configure Stripe API Keys
- Open http://localhost/stripe_integration/settings.php
- Enter your **Publishable Key** (pk_test_…) and **Secret Key** (sk_test_…)
- Get keys from: https://dashboard.stripe.com/test/apikeys

### 5. Set up Webhooks (optional but recommended)
- Go to https://dashboard.stripe.com/test/webhooks
- Add endpoint: `http://YOUR_DOMAIN/stripe_integration/webhook_handler.php`
- Select events: `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`
- Copy the signing secret → paste in Settings page

---

## 🧪 Test Cards (Test Mode)

| Card Number          | Result              |
|---------------------|---------------------|
| 4242 4242 4242 4242  | ✅ Always succeeds  |
| 4000 0025 6000 0051  | 🔐 3D Secure        |
| 4000 0000 0000 9995  | ❌ Always declined  |
| 5555 5555 5555 4444  | ✅ Mastercard       |

Use any future expiry date and any 3-digit CVC.

---

## 🗄️ Database Tables

| Table           | Purpose                        |
|----------------|--------------------------------|
| `customers`     | Customer profiles              |
| `payments`      | Payment records + card info    |
| `refunds`       | Refund records                 |
| `webhook_logs`  | Raw Stripe webhook events      |
| `settings`      | API keys + configuration       |

---

## 🔒 Security Notes
- Secret key is stored in the database (settings table), never in front-end code
- Card data never touches your server — handled entirely by Stripe.js
- Webhook signature verification prevents spoofed events
- All user input is sanitized via PDO prepared statements
- XSS protection via `htmlspecialchars()` on all output

---

## 📦 Requirements
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- cURL extension (usually enabled by default)
- Apache / Nginx web server
- phpMyAdmin (for DB management)
- No Composer or Stripe SDK needed — uses raw cURL
