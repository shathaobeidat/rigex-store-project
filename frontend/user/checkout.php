<?php
declare(strict_types=1);
require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/config/stripe.php';
require_once __DIR__ . '/../../backend/classes/StripeClass.php';
require_once __DIR__ . '/../../backend/classes/OrderClass.php';
require_once __DIR__ . '/../../backend/classes/PaymentCurrency.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'customer') {
    header('Location: ../../backend/auth/login.php');
    exit;
}

$db = DB_conn::getConnection();
$userStatement = $db->prepare(
    'SELECT id, name, email, phone, address_line1
     FROM users
     WHERE id = ? AND role = \'customer\' AND deleted_at IS NULL'
);
$userStatement->execute([(int) $_SESSION['user_id']]);
$user = $userStatement->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    http_response_code(403);
    exit('A valid customer account is required.');
}

$confirmation = $_SESSION['checkout_confirmation'] ?? null;
unset($_SESSION['checkout_confirmation']);

$cartStatement = $db->prepare(
    "SELECT id FROM carts
     WHERE user_id = ? AND status = 'active' AND deleted_at IS NULL
     ORDER BY id DESC LIMIT 1"
);
$cartStatement->execute([(int) $user['id']]);
$cartId = (int) $cartStatement->fetchColumn();
if ($cartId < 1 && !$confirmation) {
    header('Location: index.php#categories');
    exit;
}

$items = [];
if ($cartId > 0) {
    $itemsStatement = $db->prepare(
        "SELECT ci.product_id, COALESCE(p.name, 'Unavailable product') AS name,
                p.price, ci.quantity
         FROM cart_items ci
         LEFT JOIN products p ON p.id = ci.product_id
         WHERE ci.cart_id = ? AND ci.deleted_at IS NULL
         ORDER BY ci.id"
    );
    $itemsStatement->execute([$cartId]);
    $items = $itemsStatement->fetchAll(PDO::FETCH_ASSOC);
}
if ($items === [] && !$confirmation) {
    header('Location: index.php#categories');
    exit;
}

$checkoutError = '';
$selectedPaymentMethod = 'cash_on_delivery';
$customerName = (string) $user['name'];
$customerPhone = (string) ($user['phone'] ?? '');
$deliveryAddress = (string) ($user['address_line1'] ?? '');

if (empty($_SESSION['checkout_csrf'])) {
    $_SESSION['checkout_csrf'] = bin2hex(random_bytes(32));
}

$subtotal = array_reduce(
    $items,
    static fn(float $total, array $item): float =>
        $total + ((float) $item['price'] * (int) $item['quantity']),
    0.0
);
$stripeTotalMinorUnits = array_sum(array_map(
    static fn(array $item): int =>
        PaymentCurrency::toStripeMinorUnits((float) $item['price']) * (int) $item['quantity'],
    $items
));
if ($confirmation) {
    $subtotal = (float) $confirmation['total_jod'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedPaymentMethod = is_string($_POST['payment_method'] ?? null) ? $_POST['payment_method'] : '';
    $customerName = is_string($_POST['customer_name'] ?? null) ? trim($_POST['customer_name']) : '';
    $customerPhone = is_string($_POST['customer_phone'] ?? null) ? trim($_POST['customer_phone']) : '';
    $deliveryAddress = is_string($_POST['delivery_address'] ?? null) ? trim($_POST['delivery_address']) : '';
    $submittedToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';

    if (!hash_equals($_SESSION['checkout_csrf'], $submittedToken)) {
        $checkoutError = 'Your checkout session expired. Please reload and try again.';
    } elseif (!in_array($selectedPaymentMethod, ['cash_on_delivery', 'stripe'], true)) {
        $checkoutError = 'Choose a payment method to continue.';
    } else {
        $order = null;
        try {
            $paymentMethod = $selectedPaymentMethod === 'stripe' ? 'card' : 'cash_on_delivery';
            $order = (new OrderClass())->createOrder(
                (int) $user['id'],
                $cartId,
                [
                    'name' => $customerName,
                    'phone' => $customerPhone,
                    'address' => $deliveryAddress,
                ],
                $paymentMethod
            );

            if ($selectedPaymentMethod === 'cash_on_delivery') {
                $_SESSION['checkout_confirmation'] = [
                    'id' => $order['id'],
                    'total_jod' => $order['total_jod'],
                ];
                $_SESSION['checkout_csrf'] = bin2hex(random_bytes(32));
                header('Location: checkout.php');
                exit;
            }

            (new OrderClass())->holdCart($cartId);
            $returnBaseUrl = defined('SITE_URL') && filter_var(SITE_URL, FILTER_VALIDATE_URL)
                ? rtrim(SITE_URL, '/')
                : '';
            if ($returnBaseUrl === '') {
                $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
                if (!preg_match('/\A(?:localhost|127\.0\.0\.1|\[::1\])(?::\d{1,5})?\z/i', $host)) {
                    throw new RuntimeException('Set a valid public SITE_URL to enable Stripe checkout.');
                }
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $scriptDirectory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
                $returnBaseUrl = $scheme . '://' . $host . rtrim($scriptDirectory, '/');
            } elseif (!str_ends_with($returnBaseUrl, '/frontend/User')) {
                $returnBaseUrl .= '/frontend/User';
            }
            $checkoutSession = (new StripeClass())->createCheckoutSession(
                $order['items'],
                (int) $order['id'],
                $returnBaseUrl
            );

            if (empty($checkoutSession['url']) || !filter_var($checkoutSession['url'], FILTER_VALIDATE_URL)) {
                throw new RuntimeException('Stripe did not return a valid checkout URL.');
            }

            header('Location: ' . $checkoutSession['url']);
            exit;
        } catch (InvalidArgumentException $exception) {
            if ($order !== null && $selectedPaymentMethod === 'stripe') {
                try {
                    (new OrderClass())->failPayment((int) $order['id'], (int) $user['id']);
                } catch (Throwable $failureError) {
                    error_log('Stripe order cleanup: ' . $failureError->getMessage());
                }
            }
            $checkoutError = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($order !== null && $selectedPaymentMethod === 'stripe') {
                try {
                    (new OrderClass())->failPayment((int) $order['id'], (int) $user['id']);
                } catch (Throwable $failureError) {
                    error_log('Stripe order cleanup: ' . $failureError->getMessage());
                }
            }
            error_log('Checkout: ' . $exception->getMessage());
            $checkoutError = 'We could not complete checkout. Your cart is still available; please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#090a0c">
    <title>Checkout | REGIX Store</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/checkout.css">
   
</head>

<body class="checkout-page">
    <header class="checkout-header">
        <div class="checkout-header-inner">
            <a class="checkout-brand" href="index.php" aria-label="REGIX Store home">
                <img src="../assets/regix-logo.svg" alt="REGIX Store" width="220" height="53">
            </a>
            <a class="back-link" href="index.php#categories"><span aria-hidden="true">←</span> Continue shopping</a>
        </div>
    </header>

    <main class="checkout-main">
        <div class="checkout-heading">
            <div class="checkout-kicker">SECURE CHECKOUT</div>
            <h1>Review your order</h1>
            <p>Check your items, add delivery details, and choose how you would like to pay.</p>
        </div>

        <?php if ($checkoutError !== ''): ?>
            <div class="checkout-error" role="alert"><?= htmlspecialchars($checkoutError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="checkout-grid">
            <section class="checkout-panel" aria-labelledby="orderItemsTitle">
                <div class="panel-heading">
                    <h2 id="orderItemsTitle">Order items</h2>
                    <span><?= count($items) ?> items</span>
                </div>
                <div class="order-items">
                    <?php foreach ($items as $item): ?>
                        <div class="order-item">
                            <div class="item-copy">
                                <strong><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <span>Quantity: <?= (int) $item['quantity'] ?></span>
                            </div>
                            <span class="item-price"><?= number_format((float) $item['price'] * (int) $item['quantity'], 2) ?> JOD</span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($items === [] && $confirmation): ?><p>Order confirmed. Thank you for shopping with REGIX.</p><?php endif; ?>
                </div>
                <section class="checkout-policies" aria-labelledby="checkoutPoliciesTitle">
                    <h3 id="checkoutPoliciesTitle">Store policies</h3>
                    <details class="checkout-policy">
                        <summary>Purchase policy</summary>
                        <p>Review the items, quantities, and total before placing your order. Cash on delivery is paid when the order arrives; Stripe payments are completed online.</p>
                    </details>
                    <details class="checkout-policy">
                        <summary>Returns and exchanges</summary>
                        <p>Keep items unused and in their original packaging. Contact the store with your order number to request a return or exchange; each request is reviewed according to the item and its condition.</p>
                    </details>
                    <details class="checkout-policy">
                        <summary>Delivery policy</summary>
                        <p>Enter an accurate phone number and delivery address. Delivery timing and any applicable charges depend on the delivery area and will be confirmed by the store.</p>
                    </details>
                </section>
            </section>

            <aside class="checkout-panel" aria-labelledby="summaryTitle">
                <div class="panel-heading">
                    <h2 id="summaryTitle">Order summary</h2>
                </div>
                <div class="summary-body">
                    <div class="summary-line"><span>Subtotal</span><span><?= number_format($subtotal, 2) ?> JOD</span>
                    </div>
                    <div class="summary-line"><span>Shipping</span><span>Calculated at payment</span></div>
                    <div class="summary-total"><span>Total
                            due</span><strong><?= number_format($subtotal, 2) ?> JOD</strong></div>
                    <?php if ($confirmation): ?>
                        <div class="checkout-confirmation" role="status">
                            <strong>Cash on delivery order placed</strong>
                            <span>Order #<?= (int) $confirmation['id'] ?></span>
                            Total due on delivery: <?= number_format((float) $confirmation['total_jod'], 2) ?> JOD
                            <a href="index.php#categories">Continue shopping</a>
                        </div>
                    <?php else: ?>
                        <form method="post" action="checkout.php" id="paymentForm">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['checkout_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                            <fieldset class="payment-choices">
                                <legend>Payment method</legend>
                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="cash_on_delivery"
                                        <?= $selectedPaymentMethod === 'cash_on_delivery' ? 'checked' : '' ?>>
                                    <span>
                                        <strong>Cash on delivery</strong>
                                        <small>Pay when your order arrives</small>
                                    </span>
                                    <span class="payment-option-badge">On delivery</span>
                                </label>
                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="stripe"
                                        <?= $selectedPaymentMethod === 'stripe' ? 'checked' : '' ?>>
                                    <span>
                                        <strong>Stripe</strong>
                                        <small>Pay securely online</small>
                                    </span>
                                    <span class="payment-option-badge">Secure</span>
                                </label>
                            </fieldset>
                            <div class="delivery-fields" id="deliveryFields">
                                <label for="customerName">
                                    Full name
                                    <input id="customerName" name="customer_name" type="text" maxlength="120" value="<?= htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') ?>" autocomplete="name" required>
                                </label>
                                <label for="customerPhone">
                                    Phone number
                                    <input id="customerPhone" name="customer_phone" type="tel" maxlength="30" value="<?= htmlspecialchars($customerPhone, ENT_QUOTES, 'UTF-8') ?>" autocomplete="tel" required>
                                </label>
                                <label for="deliveryAddress">
                                    Delivery address
                                    <textarea id="deliveryAddress" name="delivery_address" maxlength="255" autocomplete="street-address" required><?= htmlspecialchars($deliveryAddress, ENT_QUOTES, 'UTF-8') ?></textarea>
                                </label>
                            </div>
                            <p class="secure-note" id="paymentHelp"><span class="secure-mark"
                                    aria-hidden="true">✓</span><span>Payment details are handled securely by Stripe.</span>
                            </p>
                            <button class="pay-button" type="submit" id="paymentSubmit">Continue to secure payment</button>
                        </form>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </main>

    <footer class="checkout-footer"><strong>REGIX<span>.</span></strong> · Secure checkout</footer>
    <script>
        const paymentForm = document.getElementById('paymentForm');

        if (paymentForm) {
            const paymentSubmit = document.getElementById('paymentSubmit');
            const paymentHelp = document.getElementById('paymentHelp');

            paymentForm.querySelectorAll('input[name="payment_method"]').forEach(input => {
                input.addEventListener('change', () => {
                    const isStripe = input.value === 'stripe' && input.checked;
                    const stripeTotal = <?= json_encode(number_format($stripeTotalMinorUnits / 100, 2, '.', '')) ?>;

                    paymentSubmit.textContent = isStripe
                        ? 'Continue to secure payment'
                        : 'Place cash on delivery order';
                    paymentHelp.lastElementChild.textContent = isStripe
                        ? `Stripe will charge $${stripeTotal} USD at the test rate of 1 JOD = $1.41 USD. Card details are handled securely by Stripe.`
                        : 'Pay in cash when your order is delivered.';
                });
            });

            const selectedMethod = paymentForm.querySelector('input[name="payment_method"]:checked');
            if (selectedMethod) {
                selectedMethod.dispatchEvent(new Event('change'));
            }
        }
    </script>
</body>

</html>