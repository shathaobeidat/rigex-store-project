<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/PaymentCurrency.php';

class OrderClass
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB_conn::getConnection();
    }

    public function createOrder(int $userId, int $cartId, array $customer, string $paymentMethod): array
    {
        $name = trim((string) ($customer['name'] ?? ''));
        $phone = trim((string) ($customer['phone'] ?? ''));
        $address = trim((string) ($customer['address'] ?? ''));

        if ($userId < 1 || $cartId < 1) {
            throw new InvalidArgumentException('A valid customer cart is required.');
        }
        if ($name === '' || mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Enter a valid name.');
        }
        if ($phone === '' || mb_strlen($phone) > 30) {
            throw new InvalidArgumentException('Enter a valid phone number.');
        }
        if ($address === '' || mb_strlen($address) > 255) {
            throw new InvalidArgumentException('Enter a valid delivery address.');
        }
        if (!in_array($paymentMethod, ['cash_on_delivery', 'card'], true)) {
            throw new InvalidArgumentException('Choose a valid payment method.');
        }

        $this->db->beginTransaction();

        try {
            $cartStatement = $this->db->prepare(
                "SELECT id FROM carts
                 WHERE id = ? AND user_id = ? AND status = 'active' AND deleted_at IS NULL
                 FOR UPDATE"
            );
            $cartStatement->execute([$cartId, $userId]);
            if (!$cartStatement->fetchColumn()) {
                throw new InvalidArgumentException('Your cart is no longer available.');
            }

            $itemsStatement = $this->db->prepare(
                "SELECT ci.product_id, ci.quantity, p.name, p.price, p.quantity AS stock,
                        p.listing_status, p.deleted_at AS product_deleted_at
                 FROM cart_items ci
                 LEFT JOIN products p ON p.id = ci.product_id
                 WHERE ci.cart_id = ? AND ci.deleted_at IS NULL
                 ORDER BY ci.id
                 FOR UPDATE"
            );
            $itemsStatement->execute([$cartId]);
            $items = $itemsStatement->fetchAll(PDO::FETCH_ASSOC);
            if ($items === []) {
                throw new InvalidArgumentException('Your cart is empty.');
            }

            $total = 0.0;
            foreach ($items as &$item) {
                $item['product_id'] = (int) $item['product_id'];
                $item['quantity'] = (int) $item['quantity'];
                $item['price'] = (float) $item['price'];
                if (
                    $item['product_deleted_at'] !== null ||
                    $item['listing_status'] !== 'available' ||
                    $item['name'] === null
                ) {
                    throw new InvalidArgumentException('A product in your cart is no longer available. Update your cart and try again.');
                }
                if ($item['quantity'] < 1 || $item['quantity'] > (int) $item['stock']) {
                    throw new InvalidArgumentException(
                        'The available quantity for ' . $item['name'] . ' has changed. Update your cart and try again.'
                    );
                }
                $total += $item['price'] * $item['quantity'];
            }
            unset($item);
            $total = round($total, 2);

            $orderStatement = $this->db->prepare(
                "INSERT INTO orders
                    (user_id, cart_id, status, total_jod, shipping_name,
                     shipping_phone, shipping_address_line1)
                 VALUES (?, ?, 'pending', ?, ?, ?, ?)"
            );
            $orderStatement->execute([$userId, $cartId, $total, $name, $phone, $address]);
            $orderId = (int) $this->db->lastInsertId();

            $orderItemStatement = $this->db->prepare(
                'INSERT INTO order_items
                    (order_id, product_id, product_name, quantity, unit_price_jod)
                 VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($items as $item) {
                $orderItemStatement->execute([
                    $orderId,
                    $item['product_id'],
                    $item['name'],
                    $item['quantity'],
                    $item['price'],
                ]);
            }

            $paymentStatement = $this->db->prepare(
                'INSERT INTO payments (order_id, cart_id, payment_method, amount_jod, payment_status)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $paymentStatement->execute([
                $orderId,
                $cartId,
                $paymentMethod,
                $total,
                'pending',
            ]);

            foreach ($items as $item) {
                $stock = (int) $item['stock'];
                if ($stock === $item['quantity']) {
                    $stockStatement = $this->db->prepare(
                        "UPDATE products
                         SET quantity = quantity - ?, listing_status = 'sold'
                         WHERE id = ? AND deleted_at IS NULL
                           AND listing_status = 'available' AND quantity >= ?"
                    );
                } else {
                    $stockStatement = $this->db->prepare(
                        "UPDATE products SET quantity = quantity - ?
                         WHERE id = ? AND deleted_at IS NULL
                           AND listing_status = 'available' AND quantity >= ?"
                    );
                }
                $stockStatement->execute([
                    $item['quantity'],
                    $item['product_id'],
                    $item['quantity'],
                ]);
                if ($stockStatement->rowCount() !== 1) {
                    throw new InvalidArgumentException('Product availability changed. Please review your cart and try again.');
                }
            }

            if ($paymentMethod === 'cash_on_delivery') {
                $this->clearCart($cartId);
            }

            $this->db->commit();

            return [
                'id' => $orderId,
                'cart_id' => $cartId,
                'total_jod' => $total,
                'items' => $items,
            ];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function restoreInventoryForOrder(int $orderId): void
    {
        $this->adjustOrderInventory($orderId, false);
    }

    public function reserveInventoryForOrder(int $orderId): void
    {
        $this->adjustOrderInventory($orderId, true);
    }

    public function clearCart(int $cartId): void
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $itemStatement = $this->db->prepare(
                'UPDATE cart_items
                 SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                 WHERE cart_id = ? AND deleted_at IS NULL'
            );
            $itemStatement->execute([$cartId]);

            $cartStatement = $this->db->prepare(
                "UPDATE carts SET total = 0, status = 'abandoned', updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );
            $cartStatement->execute([$cartId]);

            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function holdCart(int $cartId): void
    {
        $statement = $this->db->prepare(
            "UPDATE carts SET status = 'abandoned', updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND status = 'active' AND deleted_at IS NULL"
        );
        $statement->execute([$cartId]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('The checkout cart could not be secured.');
        }
    }

    public function failPayment(int $orderId, ?int $userId = null): void
    {
        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare(
                "SELECT cart_id, status FROM orders
                 WHERE id = ? AND deleted_at IS NULL
                   AND (? IS NULL OR user_id = ?)
                 FOR UPDATE"
            );
            $statement->execute([$orderId, $userId, $userId]);
            $order = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                throw new RuntimeException('The pending order could not be found.');
            }
            $cartId = (int) $order['cart_id'];

            $statement = $this->db->prepare(
                "SELECT id, payment_status FROM payments
                 WHERE order_id = ? AND payment_method = 'card' AND deleted_at IS NULL
                 LIMIT 1 FOR UPDATE"
            );
            $statement->execute([$orderId]);
            $payment = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$payment) {
                throw new RuntimeException('The payment record could not be found.');
            }
            if ($payment['payment_status'] === 'paid') {
                $this->db->commit();
                return;
            }
            if ($order['status'] === 'cancelled') {
                $this->db->commit();
                return;
            }

            $statement = $this->db->prepare(
                "UPDATE payments SET payment_status = 'failed'
                 WHERE id = ? AND payment_status = 'pending' AND deleted_at IS NULL"
            );
            $statement->execute([(int) $payment['id']]);
            $statement = $this->db->prepare(
                "UPDATE orders SET status = 'cancelled'
                 WHERE id = ? AND status = 'pending' AND deleted_at IS NULL"
            );
            $statement->execute([$orderId]);
            if ($statement->rowCount() === 1) {
                $this->restoreInventoryForOrder($orderId);
            }
            $statement = $this->db->prepare(
                "UPDATE carts SET status = 'active', updated_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND deleted_at IS NULL"
            );
            $statement->execute([$cartId]);
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function confirmStripePayment(int $orderId, int $amountMinor, string $currency): bool
    {
        if ($orderId < 1 || strtolower($currency) !== PaymentCurrency::STRIPE_CURRENCY) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                "SELECT o.id, o.cart_id, o.status AS order_status,
                        (
                            SELECT COALESCE(SUM(ROUND(oi.unit_price_jod * " . PaymentCurrency::USD_CENTS_PER_JOD . ") * oi.quantity), 0)
                            FROM order_items oi
                            WHERE oi.order_id = o.id
                        ) AS expected_amount_minor,
                        p.id AS payment_id, p.payment_status
                 FROM orders o
                 JOIN payments p ON p.order_id = o.id
                 WHERE o.id = ? AND o.deleted_at IS NULL
                   AND p.payment_method = 'card' AND p.deleted_at IS NULL
                 ORDER BY p.id DESC LIMIT 1
                 FOR UPDATE"
            );
            $statement->execute([$orderId]);
            $order = $statement->fetch(PDO::FETCH_ASSOC);
            if (
                !$order ||
                $order['order_status'] === 'cancelled' ||
                (int) $order['expected_amount_minor'] !== $amountMinor
            ) {
                $this->db->rollBack();
                return false;
            }

            if ($order['payment_status'] !== 'paid') {
                $statement = $this->db->prepare(
                    "UPDATE payments SET payment_status = 'paid'
                     WHERE id = ? AND payment_status = 'pending' AND deleted_at IS NULL"
                );
                $statement->execute([(int) $order['payment_id']]);
                if ($statement->rowCount() !== 1) {
                    $this->db->rollBack();
                    return false;
                }

            }

            $this->clearCart((int) $order['cart_id']);
            $this->db->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private function adjustOrderInventory(int $orderId, bool $reserve): void
    {
        if ($orderId < 1) {
            throw new InvalidArgumentException('A valid order is required.');
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $statement = $this->db->prepare(
                'SELECT product_id, quantity FROM order_items
                 WHERE order_id = ? ORDER BY id FOR UPDATE'
            );
            $statement->execute([$orderId]);
            $items = $statement->fetchAll(PDO::FETCH_ASSOC);
            if ($items === []) {
                throw new RuntimeException('The order has no inventory items.');
            }

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $quantity = (int) $item['quantity'];
                if ($reserve) {
                    $productStatement = $this->db->prepare(
                        "SELECT quantity FROM products
                         WHERE id = ? AND deleted_at IS NULL
                           AND listing_status = 'available'
                         FOR UPDATE"
                    );
                    $productStatement->execute([$productId]);
                    $stock = $productStatement->fetchColumn();
                    if ($stock === false || (int) $stock < $quantity) {
                        throw new InvalidArgumentException('The order cannot be reopened because inventory is unavailable.');
                    }

                    $productStatement = $this->db->prepare(
                        'UPDATE products SET quantity = quantity - ? WHERE id = ?'
                    );
                    $productStatement->execute([$quantity, $productId]);
                    if ((int) $stock === $quantity) {
                        $productStatement = $this->db->prepare(
                            "UPDATE products SET listing_status = 'sold' WHERE id = ?"
                        );
                        $productStatement->execute([$productId]);
                    }
                } else {
                    $productStatement = $this->db->prepare(
                        "UPDATE products
                         SET quantity = quantity + ?,
                             listing_status = CASE
                                 WHEN listing_status = 'sold' THEN 'available'
                                 ELSE listing_status
                             END
                         WHERE id = ? AND deleted_at IS NULL"
                    );
                    $productStatement->execute([$quantity, $productId]);
                }
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }
}
