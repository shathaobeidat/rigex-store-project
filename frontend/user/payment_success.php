<?php
require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/config/stripe.php';
require_once __DIR__ . '/../../backend/classes/StripeClass.php';
require_once __DIR__ . '/../../backend/classes/OrderClass.php';

$state = 'unverified';
$sessionId = $_GET['session_id'] ?? '';
$orderId = filter_var($_GET['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (
    is_string($sessionId) &&
    preg_match('/\Acs_[A-Za-z0-9_]+\z/', $sessionId) &&
    $orderId !== false
) {
    try {
        $stripeSession = (new StripeClass())->getSession($sessionId);

        if (
            is_array($stripeSession) &&
            isset($stripeSession['id']) &&
            hash_equals($sessionId, $stripeSession['id']) &&
            (int) ($stripeSession['client_reference_id'] ?? 0) === $orderId &&
            (int) ($stripeSession['metadata']['order_id'] ?? 0) === $orderId
        ) {
            $isPaid = ($stripeSession['payment_status'] ?? '') === 'paid';
            $saved = $isPaid && (new OrderClass())->confirmStripePayment(
                $orderId,
                (int) ($stripeSession['amount_total'] ?? 0),
                (string) ($stripeSession['currency'] ?? '')
            );
            $state = $saved ? 'paid' : 'not-paid';
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
    }
}

$content = [
    'paid' => [
        'class' => 'success',
        'symbol' => '✓',
        'eyebrow' => 'PAYMENT CONFIRMED',
        'title' => 'Thank you for your order.',
        'message' => 'Stripe confirmed that your payment was successful.',
    ],
    'not-paid' => [
        'class' => 'pending',
        'symbol' => '!',
        'eyebrow' => 'PAYMENT NOT COMPLETE',
        'title' => 'Your payment is not complete yet.',
        'message' => 'The payment session has not been marked as paid. You can return to checkout and try again.',
    ],
    'unverified' => [
        'class' => 'pending',
        'symbol' => '!',
        'eyebrow' => 'STATUS UNAVAILABLE',
        'title' => 'We could not verify your payment.',
        'message' => 'The payment reference is missing or Stripe could not confirm its status. Please check your payment before trying again.',
    ],
]

[$state];
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#090a0c">
    <title>Payment status | REGIX Store</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/payment_success.css">
   
</head>

<body class="payment-page">
    <header class="payment-header">
        <div class="payment-header-inner">
            <a class="payment-brand" href="index.php" aria-label="REGIX Store home">
                <img src="../assets/regix-logo.svg" alt="REGIX Store" width="220" height="53">
            </a>
            <a class="payment-brand-link" href="index.php#categories">Continue shopping</a>
        </div>
    </header>

    <main class="payment-main">
        <section class="payment-panel" aria-labelledby="paymentTitle">
            <div class="payment-symbol <?= htmlspecialchars($content['class'], ENT_QUOTES, 'UTF-8') ?>"
                aria-hidden="true">
                <?= htmlspecialchars($content['symbol'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="payment-eyebrow"><?= htmlspecialchars($content['eyebrow'], ENT_QUOTES, 'UTF-8') ?></div>
            <h1 id="paymentTitle"><?= htmlspecialchars($content['title'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p><?= htmlspecialchars($content['message'], ENT_QUOTES, 'UTF-8') ?></p>
            <div class="payment-actions">
                <a class="payment-link primary" href="index.php#categories">Continue shopping</a>
                <?php if ($state !== 'paid'): ?>
                    <a class="payment-link" href="checkout.php">Return to checkout</a>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="payment-footer"><strong>REGIX<span>.</span></strong> · Payment status</footer>
</body>

</html>