<?php

declare(strict_types=1);

require_once __DIR__ . '/PaymentCurrency.php';

class StripeClass
{
    public function createCheckoutSession(array $items, int $orderId, string $returnBaseUrl): array
    {
        if (!defined('STRIPE_SECRET_KEY') || STRIPE_SECRET_KEY === '') {
            throw new RuntimeException('Stripe is not configured.');
        }
        if ($items === [] || $orderId < 1 || filter_var($returnBaseUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Invalid Stripe checkout details.');
        }

        $data = [
            'mode' => 'payment',
            'currency' => PaymentCurrency::STRIPE_CURRENCY,
            'success_url' => $returnBaseUrl . '/payment_success.php?session_id={CHECKOUT_SESSION_ID}&order_id=' . $orderId,
            'cancel_url' => $returnBaseUrl . '/payment_cancel.php?order_id=' . $orderId,
            'client_reference_id' => (string) $orderId,
            'metadata' => ['order_id' => (string) $orderId],
        ];

        $totalMinorUnits = 0;
        foreach ($items as $index => $item) {
            $price = (float) ($item['price'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($price <= 0 || !is_finite($price) || $quantity < 1) {
                throw new InvalidArgumentException('Invalid Stripe line item.');
            }

            $unitAmount = PaymentCurrency::toStripeMinorUnits($price);
            $totalMinorUnits += $unitAmount * $quantity;
            $data['line_items'][$index] = [
                'price_data' => [
                    'currency' => PaymentCurrency::STRIPE_CURRENCY,
                    'product_data' => ['name' => (string) $item['name']],
                    'unit_amount' => $unitAmount,
                ],
                'quantity' => $quantity,
            ];
        }

        if ($totalMinorUnits < 50) {
            throw new InvalidArgumentException('The Stripe payment total must be at least 0.50 USD.');
        }

        return $this->sendRequest('POST', '/v1/checkout/sessions', $data);
    }

    public function getSession(string $sessionId): array
    {
        if (!preg_match('/\Acs_[A-Za-z0-9_]+\z/', $sessionId)) {
            throw new InvalidArgumentException('Invalid Stripe session ID.');
        }

        return $this->sendRequest('GET', '/v1/checkout/sessions/' . rawurlencode($sessionId));
    }

    private function sendRequest(string $method, string $path, array $data = []): array
    {
        $handle = curl_init('https://api.stripe.com' . $path);
        if ($handle === false) {
            throw new RuntimeException('Could not initialize the Stripe request.');
        }

        curl_setopt_array($handle, [
            CURLOPT_USERPWD => STRIPE_SECRET_KEY . ':',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        if ($method === 'POST') {
            curl_setopt($handle, CURLOPT_POST, true);
            curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);

        if ($response === false) {
            throw new RuntimeException('Stripe request failed: ' . $curlError);
        }

        $result = json_decode($response, true);
        if (!is_array($result) || $status < 200 || $status >= 300) {
            $message = is_array($result) ? (string) ($result['error']['message'] ?? 'Stripe returned an invalid response.') : 'Stripe returned an invalid response.';
            throw new RuntimeException($message);
        }

        return $result;
    }
}
