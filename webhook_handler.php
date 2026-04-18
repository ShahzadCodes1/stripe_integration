<?php
// webhook_handler.php — Stripe webhook receiver
// Point your Stripe Dashboard webhook to this file
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/stripe.php';

$payload   = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$cfg    = getStripeKeys();
$secret = $cfg['stripe_webhook_secret'] ?? '';

http_response_code(200); // Acknowledge immediately

$event = null;
if ($secret) {
    $event = StripeAPI::verifyWebhook($payload, $sigHeader, $secret);
    if (!$event) {
        http_response_code(400);
        exit('Signature verification failed.');
    }
} else {
    $event = json_decode($payload, true);
}

if (!$event) exit;

$eventId   = $event['id']   ?? uniqid('evt_');
$eventType = $event['type'] ?? 'unknown';

// Log to DB
try {
    $db = db();
    // Avoid duplicate processing
    $existing = $db->prepare("SELECT id FROM webhook_logs WHERE event_id=?");
    $existing->execute([$eventId]);
    if ($existing->fetch()) exit; // already processed

    $db->prepare("INSERT INTO webhook_logs (event_id, event_type, payload, status) VALUES (?,?,?,?)")
       ->execute([$eventId, $eventType, $payload, 'received']);

    // Handle events
    $obj = $event['data']['object'] ?? [];

    switch ($eventType) {

        case 'payment_intent.succeeded':
            $piId  = $obj['id'] ?? null;
            $cid   = $obj['latest_charge'] ?? null;
            if ($piId) {
                $db->prepare("UPDATE payments SET status='succeeded', stripe_charge_id=? WHERE stripe_payment_intent_id=?")
                   ->execute([$cid, $piId]);
            }
            break;

        case 'payment_intent.payment_failed':
            $piId  = $obj['id'] ?? null;
            $msg   = $obj['last_payment_error']['message'] ?? null;
            if ($piId) {
                $db->prepare("UPDATE payments SET status='failed', failure_message=? WHERE stripe_payment_intent_id=?")
                   ->execute([$msg, $piId]);
            }
            break;

        case 'charge.refunded':
            $piId = $obj['payment_intent'] ?? null;
            if ($piId) {
                $db->prepare("UPDATE payments SET status='refunded' WHERE stripe_payment_intent_id=?")
                   ->execute([$piId]);
            }
            break;
    }

    $db->prepare("UPDATE webhook_logs SET status='processed' WHERE event_id=?")->execute([$eventId]);

} catch (Exception $e) {
    // Log failure silently — Stripe will retry
    try {
        db()->prepare("UPDATE webhook_logs SET status='failed' WHERE event_id=?")->execute([$eventId]);
    } catch (Exception $e2) {}
}

echo 'ok';
