<?php

require_once __DIR__ . '/../layout.php';

require_once __DIR__ . '/../../backend/classes/ordersClass.php';

if (empty($_SESSION['orders_csrf'])) {
    $_SESSION['orders_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $db = DB_conn::getConnection();
        $admin = $db->prepare(
            "SELECT id FROM users
             WHERE id = ? AND role = 'admin' AND deleted_at IS NULL"
        );
        $admin->execute([(int) ($_SESSION['user_id'] ?? 0)]);
        if (($_SESSION['user_role'] ?? '') !== 'admin' || !$admin->fetchColumn()) {
            http_response_code(403);
            echo json_encode(['error' => 'Please sign in with an admin account to manage orders.']);
            exit;
        }
        if (!hash_equals($_SESSION['orders_csrf'], (string) ($_POST['csrf'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['error' => 'Your session expired. Refresh this page and try again.']);
            exit;
        }
        $id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $action = $_POST['action'] ?? '';
        if (!in_array($action, ['delete_order', 'update_order'], true) || $id === false || $id === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid order request.']);
            exit;
        }
        $orderModel = new Order($db);
        if ($action === 'update_order') {
            $status = (string) ($_POST['status'] ?? '');
            $paymentStatus = (string) ($_POST['payment_status'] ?? '');
            if (!$orderModel->updateStatuses($id, $status, $paymentStatus)) {
                http_response_code(404);
                echo json_encode(['error' => 'Order not found. Refresh the page.']);
                exit;
            }
            echo json_encode(['success' => true, 'status' => $status, 'payment_status' => $paymentStatus, 'summary' => $orderModel->getSummary()]);
            exit;
        }
        if (!$orderModel->delete($id)) {
            http_response_code(404);
            echo json_encode(['error' => 'This order was already deleted or does not exist. Refresh the page.']);
            exit;
        }
        echo json_encode(['success' => true, 'summary' => $orderModel->getSummary()]);
    } catch (InvalidArgumentException $error) {
        http_response_code(422);
        echo json_encode(['error' => $error->getMessage()]);
    } catch (Throwable $error) {
        error_log('Order action failed: ' . $error->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Could not save the order change. Please try again.']);
    }
    exit;
}

$orderModel = new Order(DB_conn::getConnection());
$summary = $orderModel->getSummary();
$orders = array_map(static function (array $order): array {
    return [
        'database_id' => (string) $order['id'],
        'id' => '#RGX-' . $order['id'],
        'customer' => (string) ($order['customer_name'] ?? $order['shipping_name']),
        'email' => (string) ($order['email'] ?? 'Email unavailable'),
        'date' => date('M d, Y', strtotime($order['created_at'])),
        'created' => strtotime($order['created_at']),
        'items' => $order['item_count'] . ' items',
        'total' => number_format((float) $order['total_jod'], 2) . ' JOD',
        'payment' => ucfirst($order['payment_status']),
        'status' => ucfirst($order['status']),
        'method' => ucwords(str_replace('_', ' ', $order['payment_method'])),
    ];
}, $orderModel->findAllForAdmin());

$statusCounts = [
    'all' => count($orders),
    'pending' => 0,
    'confirmed' => 0,
    'shipped' => 0,
    'delivered' => 0,
    'cancelled' => 0,
];

foreach ($orders as $order) {
    $status = strtolower($order['status']);
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
}

page_start('Orders', 'orders.php');

?>
<link rel="stylesheet" href="../css/order-update.css?v=<?= filemtime(__DIR__ . '/../css/order-update.css') ?>">
<div class="orders-page">
<?php

heading(
    'STORE MANAGEMENT',
    'Orders',
    'Track, review and manage your store orders.'
);
?>

<section class="orders-overview" aria-label="Order overview">
    <article class="orders-stat">
        <div class="orders-stat-top">
            <span>Total orders</span>
            <span class="orders-stat-icon orders-icon-green" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M4 17 10 11l4 4 6-8M14 7h6v6"/></svg>
            </span>
        </div>
        <strong id="orders-stat-total"><?= number_format((int) $summary['total_orders']) ?></strong>
        <small><span id="orders-stat-count-change"><?= e($summary['orders_comparison']) ?></span></small>
    </article>
    <article class="orders-stat">
        <div class="orders-stat-top">
            <span>Needs attention</span>
            <span class="orders-stat-icon orders-icon-amber" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M12 8v4m0 4h.01"/><path d="m10.3 3.9-8 14a2 2 0 0 0 1.7 3h16a2 2 0 0 0 1.7-3l-8-14a2 2 0 0 0-3.4 0Z"/></svg>
            </span>
        </div>
        <strong id="orders-stat-attention"><?= number_format((int) $summary['needs_attention']) ?></strong>
        <small><span>Orders awaiting fulfillment</span></small>
    </article>
    <article class="orders-stat">
        <div class="orders-stat-top">
            <span>In transit</span>
            <span class="orders-stat-icon orders-icon-blue" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M3 7h11v11H3zM14 11h4l3 3v4h-7z"/><circle cx="7.5" cy="18.5" r="1.5"/><circle cx="17.5" cy="18.5" r="1.5"/></svg>
            </span>
        </div>
        <strong id="orders-stat-transit"><?= number_format((int) $summary['in_transit']) ?></strong>
        <small><span>Orders on their way</span></small>
    </article>
    <article class="orders-stat">
        <div class="orders-stat-top">
            <span>Gross sales</span>
            <span class="orders-stat-icon orders-icon-purple" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M12 3v18m4-14H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H7"/></svg>
            </span>
        </div>
        <strong id="orders-stat-sales" title="All-time order value excluding cancelled and deleted orders"><?= number_format((float) $summary['gross_sales'], 2) ?> JOD</strong>
        <small><span id="orders-stat-sales-change"><?= e($summary['sales_comparison']) ?></span></small>
    </article>
</section>

<section class="panel orders-panel" aria-labelledby="orders-list-title">
    <div class="orders-panel-heading">
        <div>
            <div class="orders-title-line">
                <h2 id="orders-list-title">All orders</h2>
                <span class="orders-sample-tag"><span></span> LIVE ORDERS</span>
            </div>
            <p>Review and track every order in your store.</p>
        </div>

    </div>

    <div class="orders-controls">
        <label class="orders-search">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="10.8" cy="10.8" r="6.8" />
                <path d="m16 16 4.2 4.2" />
            </svg>
            <span class="visually-hidden">Search orders</span>
            <input id="orders-search" type="search" placeholder="Search orders..." autocomplete="off">
        </label>
        <select id="orders-status-select" class="orders-toolbar-select" aria-label="Filter by order status">
            <option value="all">All statuses</option>
            <?php foreach (['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'] as $status): ?>
                <option value="<?= e($status) ?>"><?= e(ucfirst($status)) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="orders-payment-select" class="orders-toolbar-select" aria-label="Filter by payment status">
            <option value="all">All payments</option>
            <?php foreach (['pending', 'authorized', 'paid', 'failed', 'refunded'] as $payment): ?>
                <option value="<?= e($payment) ?>"><?= e(ucfirst($payment)) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="orders-sort-select" class="orders-toolbar-select" aria-label="Sort orders">
            <option value="newest">Newest first</option>
            <option value="oldest">Oldest first</option>
            <option value="total-desc">Total: high to low</option>
            <option value="total-asc">Total: low to high</option>
        </select>
        <button class="orders-export" id="orders-export" type="button">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 3v12m0 0 4-4m-4 4-4-4M5 17v3h14v-3" />
            </svg>
            Export CSV
        </button>
    </div>

    <div class="table-scroll orders-table-scroll">
        <table class="orders-table">
            <thead>
                <tr>
                    <th scope="col">ORDER</th>
                    <th scope="col">CUSTOMER</th>
                    <th scope="col">DATE</th>
                    <th scope="col">ITEMS</th>
                    <th scope="col">TOTAL</th>
                    <th scope="col">PAYMENT</th>
                    <th scope="col">STATUS</th>
                </tr>
            </thead>
            <tbody id="orders-table-body">
                <?php foreach ($orders as $index => $order): ?>
                    <tr
                        data-order-row
                        data-order-id="<?= e($order['database_id']) ?>"
                        data-created="<?= (int) $order['created'] ?>"
                        data-status="<?= e(strtolower($order['status'])) ?>"
                    >
                        <td><strong class="orders-id"><?= e($order['id']) ?></strong></td>
                        <td>
                            <div class="orders-customer">
                                <span class="orders-avatar orders-avatar-<?= $index % 4 ?>"><?= e(initials($order['customer'])) ?></span>
                                <span>
                                    <strong><?= e($order['customer']) ?></strong>
                                    <small><?= e($order['email']) ?></small>
                                </span>
                            </div>
                        </td>
                        <td><?= e($order['date']) ?></td>
                        <td><?= e($order['items']) ?></td>
                        <td><strong class="orders-total"><?= e($order['total']) ?></strong></td>
                        <td>
                            <span class="orders-payment orders-payment-<?= e(strtolower($order['payment'])) ?>">
                                <span></span><?= e($order['payment']) ?>
                            </span>
                            <small class="orders-method"><?= e($order['method']) ?></small>
                        </td>
                        <td>
                            <div class="order-status-actions">
                            <span class="orders-status orders-status-<?= e(strtolower($order['status'])) ?>">
                                <span></span><?= e($order['status']) ?>
                            </span>
                            <button class="order-edit-button" type="button" data-edit-order aria-label="Update order <?= e($order['id']) ?>" title="Update order">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 5 4 4M4 20l4-1L20 7a2.8 2.8 0 0 0-4-4L4 15Z"/></svg>
                            </button>
                            <button class="order-edit-button order-delete-button" type="button" data-delete-order aria-label="Delete order <?= e($order['id']) ?>" title="Delete order">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M9 6V3h6v3M6 6l1 15h10l1-15M10 10v7M14 10v7"/></svg>
                            </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr class="orders-empty" id="orders-empty" hidden>
                    <td colspan="7">No orders match your search.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="orders-table-footer">
        <span id="orders-result-count" aria-live="polite">Showing <?= count($orders) ?> orders</span>
        <span class="orders-demo-note">Live database</span>
    </div>
</section>

<p class="order-update-message" id="order-update-message" role="status"></p>
<dialog class="order-update-dialog" id="order-update-dialog" aria-labelledby="order-update-title" aria-describedby="order-update-description">
    <form id="order-update-form" method="post" action="orders.php">
        <input type="hidden" name="action" value="update_order">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['orders_csrf']) ?>">
        <div class="order-update-heading">
            <div><span class="order-update-eyebrow">ORDER MANAGEMENT</span><h2 id="order-update-title">Update order</h2></div>
            <button type="button" class="order-update-close" data-close-order aria-label="Close update popup">&times;</button>
        </div>
        <p id="order-update-description">Review the order and save its order and payment statuses.</p>
        <div class="order-update-summary"><div><small>CUSTOMER</small><strong id="order-update-customer"></strong><span id="order-update-email"></span></div><div><small>ORDER TOTAL</small><strong id="order-update-total"></strong></div></div>
        <div class="order-update-fields">
            <div><label for="order-update-status">Order status</label><select id="order-update-status" name="status" required><option value="pending">Pending</option><option value="confirmed">Confirmed</option><option value="shipped">Shipped</option><option value="delivered">Delivered</option><option value="cancelled">Cancelled</option></select></div>
            <div><label for="order-update-payment">Payment status</label><select id="order-update-payment" name="payment_status" required><option value="pending">Pending</option><option value="authorized">Authorized</option><option value="paid">Paid</option><option value="failed">Failed</option><option value="refunded">Refunded</option></select></div>
        </div>
        <p class="order-update-note">Changes are saved to the database.</p>
        <p id="order-update-error" role="alert" style="color: #f87171"></p>
        <div class="order-update-footer"><button type="button" data-close-order>Cancel</button><button type="submit">Save changes</button></div>
    </form>
</dialog>
<dialog class="order-update-dialog" id="order-delete-dialog" aria-labelledby="order-delete-title" aria-describedby="order-delete-description">
    <form id="order-delete-form" method="post" action="orders.php">
        <input type="hidden" name="action" value="delete_order">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['orders_csrf']) ?>">
        <div class="order-update-heading">
            <div><span class="order-update-eyebrow">ORDER MANAGEMENT</span><h2 id="order-delete-title">Delete order?</h2></div>
            <button type="button" class="order-update-close" data-close-delete aria-label="Close delete popup">&times;</button>
        </div>
        <p id="order-delete-description">Delete this order from the order list? Its history, items, and payments will be retained in the database.</p>
        <p id="order-delete-error" role="alert" style="color: #f87171"></p>
        <div class="order-update-footer"><button type="button" data-close-delete>Keep order</button><button type="submit" class="order-delete-confirm">Delete order</button></div>
    </form>
</dialog>
<script src="../js/orders.js?v=<?= filemtime(__DIR__ . '/../js/orders.js') ?>" defer></script>
</div>
<?php page_end(); ?>
