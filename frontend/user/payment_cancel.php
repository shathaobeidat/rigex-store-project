
<?php

require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/classes/OrderClass.php';

$orderId = filter_var($_GET['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (
    $orderId !== false &&
    !empty($_SESSION['user_id']) &&
    ($_SESSION['user_role'] ?? '') === 'customer'
) {
    try {
        (new OrderClass())->failPayment($orderId, (int) $_SESSION['user_id']);
    } catch (Throwable $exception) {
        error_log('Stripe cancellation: ' . $exception->getMessage());
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#090a0c">
    <title>Payment canceled | REGIX Store</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/payment_cancel.css">

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
        <section class="payment-panel" aria-labelledby="cancelTitle">
            <div class="payment-symbol" aria-hidden="true">!</div>
            <div class="payment-eyebrow">CHECKOUT CANCELED</div>
            <h1 id="cancelTitle">Your payment was canceled.</h1>
            <p>No payment was completed. Your cart is available if you want to try again, or you can continue browsing.</p>
            <div class="payment-actions">
                <a class="payment-link primary" href="checkout.php">Return to checkout</a>
                <a class="payment-link" href="index.php#categories">Continue shopping</a>
            </div>
        </section>
    </main>

    <footer class="payment-footer"><strong>REGIX<span>.</span></strong> · Payment canceled</footer>
</body>

</html>