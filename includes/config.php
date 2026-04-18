<?php
// =============================================
// includes/config.php  — Database & App Config
// =============================================

define('DB_HOST',     'localhost');
define('DB_NAME',     'stripe_integration');
define('DB_USER',     'root');          // ← change to your phpMyAdmin user
define('DB_PASS',     '');             // ← change to your phpMyAdmin password
define('DB_CHARSET',  'utf8mb4');

define('APP_NAME',    'StripeHub');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    'http://localhost/stripe_integration'); // ← adjust if needed

// ── PDO Singleton ──────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ── Pull Stripe keys from DB ───────────────────────────────
function getStripeKeys(): array {
    try {
        $stmt = db()->query("SELECT key_name, value FROM settings WHERE key_name IN ('stripe_publishable_key','stripe_secret_key','stripe_webhook_secret','currency','business_name')");
        $rows  = $stmt->fetchAll();
        $cfg   = [];
        foreach ($rows as $r) {
            $cfg[$r['key_name']] = $r['value'];
        }
        return $cfg;
    } catch (Exception $e) {
        return [];
    }
}

// ── Helper: safe output ────────────────────────────────────
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── Helper: format money ───────────────────────────────────
function formatMoney(float $amount, string $currency = 'usd'): string {
    $symbols = ['usd' => '$', 'eur' => '€', 'gbp' => '£', 'jpy' => '¥', 'bhd' => 'BD'];
    $sym = $symbols[strtolower($currency)] ?? strtoupper($currency) . ' ';
    return $sym . number_format($amount, 2);
}
