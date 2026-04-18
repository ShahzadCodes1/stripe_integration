<?php
// =============================================
// includes/stripe.php  — Stripe API Wrapper
// Uses raw cURL (no SDK dependency)
// =============================================

require_once __DIR__ . '/config.php';

class StripeAPI {

    private string $secretKey;
    private string $baseUrl = 'https://api.stripe.com/v1/';

    public function __construct(string $secretKey) {
        $this->secretKey = $secretKey;
    }

    // ── Low-level request ──────────────────────────────────
    private function request(string $method, string $endpoint, array $data = []): array {
        $url = $this->baseUrl . $endpoint;
        $ch  = curl_init();

        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/x-www-form-urlencoded',
            'Stripe-Version: 2024-04-10',
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => ['message' => 'cURL error: ' . $error]];
        }

        $decoded = json_decode($response, true);
        return $decoded ?: ['error' => ['message' => 'Invalid response from Stripe']];
    }

    // ── Create Payment Intent ──────────────────────────────
    public function createPaymentIntent(int $amountCents, string $currency, string $description = '', array $metadata = []): array {
        $data = [
            'amount'               => $amountCents,
            'currency'             => $currency,
            'description'          => $description,
            'automatic_payment_methods[enabled]' => 'true',
        ];
        foreach ($metadata as $k => $v) {
            $data["metadata[$k]"] = $v;
        }
        return $this->request('POST', 'payment_intents', $data);
    }

    // ── Confirm Payment Intent with card token ─────────────
    public function confirmPaymentIntent(string $piId, string $paymentMethodId): array {
        return $this->request('POST', "payment_intents/$piId/confirm", [
            'payment_method' => $paymentMethodId,
        ]);
    }

    // ── Create Payment Method (card) ───────────────────────
    public function createPaymentMethod(array $cardData): array {
        return $this->request('POST', 'payment_methods', [
            'type'            => 'card',
            'card[number]'    => $cardData['number'],
            'card[exp_month]' => $cardData['exp_month'],
            'card[exp_year]'  => $cardData['exp_year'],
            'card[cvc]'       => $cardData['cvc'],
        ]);
    }

    // ── Retrieve Payment Intent ────────────────────────────
    public function retrievePaymentIntent(string $piId): array {
        return $this->request('GET', "payment_intents/$piId");
    }

    // ── Retrieve Charge ────────────────────────────────────
    public function retrieveCharge(string $chargeId): array {
        return $this->request('GET', "charges/$chargeId");
    }

    // ── Create Refund ──────────────────────────────────────
    public function createRefund(string $paymentIntentId, int $amountCents, string $reason = ''): array {
        $data = ['payment_intent' => $paymentIntentId];
        if ($amountCents > 0)  $data['amount'] = $amountCents;
        if ($reason !== '')    $data['reason']  = $reason;
        return $this->request('POST', 'refunds', $data);
    }

    // ── Create Customer ────────────────────────────────────
    public function createCustomer(string $email, string $name, string $phone = ''): array {
        $data = ['email' => $email, 'name' => $name];
        if ($phone) $data['phone'] = $phone;
        return $this->request('POST', 'customers', $data);
    }

    // ── Verify Webhook Signature ───────────────────────────
    public static function verifyWebhook(string $payload, string $sigHeader, string $secret): ?array {
        $parts = [];
        foreach (explode(',', $sigHeader) as $part) {
            [$k, $v] = explode('=', $part, 2);
            $parts[$k][] = $v;
        }
        $timestamp = $parts['t'][0] ?? 0;
        if (abs(time() - (int)$timestamp) > 300) return null; // 5-min tolerance

        $signed    = $timestamp . '.' . $payload;
        $expected  = hash_hmac('sha256', $signed, $secret);
        $sigs      = $parts['v1'] ?? [];

        foreach ($sigs as $sig) {
            if (hash_equals($expected, $sig)) {
                return json_decode($payload, true);
            }
        }
        return null;
    }
}
