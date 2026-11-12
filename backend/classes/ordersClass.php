<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/OrderClass.php';

final class OrderSaveMode
{
    public const ADD_NEW = 'add_new';
    public const UPDATE = 'update';
}

class Order
{
    public function __construct(
        private PDO $db,
        private string $mode = OrderSaveMode::ADD_NEW,
        public ?int $id = null,
        public ?int $userId = null,
        public ?int $cartId = null,
        public string $status = 'pending',
        public string $totalJod = '0.00',
        public string $shippingName = '',
        public string $shippingPhone = '',
        public string $shippingAddressLine1 = '',
        public ?string $shippingAddressLine2 = null,
        public ?string $notes = null
    ) {
        if (!in_array($mode, [OrderSaveMode::ADD_NEW, OrderSaveMode::UPDATE], true)) {
            throw new InvalidArgumentException('Invalid order save mode.');
        }
    }

    public function save(?string $orderSaveMode = null): bool
    {
        $mode = $orderSaveMode ?? $this->mode;

        if ($mode === OrderSaveMode::ADD_NEW) {
            return $this->create();
        }

        if ($mode === OrderSaveMode::UPDATE) {
            return $this->update();
        }

        throw new InvalidArgumentException('Invalid order save mode.');
    }

    public function create(): bool
    {
        $this->validate();

        $sql = 'INSERT INTO orders (
                    user_id, cart_id, status, total_jod,
                    shipping_name, shipping_phone, shipping_address_line1,
                    shipping_address_line2, notes
                ) VALUES (
                    :user_id, :cart_id, :status, :total_jod,
                    :shipping_name, :shipping_phone, :shipping_address_line1,
                    :shipping_address_line2, :notes
                )';

        $statement = $this->db->prepare($sql);
        $saved = $statement->execute($this->values());

        if ($saved) {
            $this->id = (int) $this->db->lastInsertId();
            $this->mode = OrderSaveMode::UPDATE;
        }

        return $saved;
    }

    public function findById(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM orders WHERE id = :id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id]);
        $order = $statement->fetch(PDO::FETCH_ASSOC);

        return $order === false ? null : $order;
    }

    public function findAll(): array
    {
        $statement = $this->db->query(
            'SELECT * FROM orders WHERE deleted_at IS NULL ORDER BY id DESC'
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSummary(): array
    {
        $month = new DateTimeImmutable('first day of this month midnight', new DateTimeZone('Asia/Amman'));
        $start = $month->format('Y-m-d H:i:s');
        $next = $month->modify('+1 month')->format('Y-m-d H:i:s');
        $previous = $month->modify('-1 month')->format('Y-m-d H:i:s');
        $statement = $this->db->prepare(
            "SELECT COUNT(*) AS total_orders,
                    COALESCE(SUM(status IN ('pending', 'confirmed')), 0) AS needs_attention,
                    COALESCE(SUM(status = 'shipped'), 0) AS in_transit,
                    COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN total_jod ELSE 0 END), 0) AS gross_sales,
                    COALESCE(SUM(created_at >= ? AND created_at < ?), 0) AS current_orders,
                    COALESCE(SUM(created_at >= ? AND created_at < ?), 0) AS previous_orders,
                    COALESCE(SUM(CASE WHEN status <> 'cancelled' AND created_at >= ? AND created_at < ? THEN total_jod ELSE 0 END), 0) AS current_sales,
                    COALESCE(SUM(CASE WHEN status <> 'cancelled' AND created_at >= ? AND created_at < ? THEN total_jod ELSE 0 END), 0) AS previous_sales
             FROM orders WHERE deleted_at IS NULL"
        );
        $statement->execute([$start, $next, $previous, $start, $start, $next, $previous, $start]);
        $summary = $statement->fetch(PDO::FETCH_ASSOC);
        foreach (['orders', 'sales'] as $metric) {
            $current = (float) $summary['current_' . $metric];
            $previousValue = (float) $summary['previous_' . $metric];
            $change = $previousValue > 0 ? ($current - $previousValue) / $previousValue * 100 : null;
            $summary[$metric . '_change'] = $change;
            $summary[$metric . '_comparison'] = $change === null
                ? 'No previous-month data'
                : ($change > 0 ? '↑ ' : ($change < 0 ? '↓ ' : '')) . number_format(abs($change), 1) . '% this month vs. last month';
        }
        return $summary;
    }

   public function findAllForAdmin(): array
    {
        $sql = "
        SELECT
            o.*,
            u.name AS customer_name,
            u.email,

            COALESCE(i.item_count, 0) AS item_count,

            COALESCE(
                (
                    SELECT pay.payment_status
                    FROM payments pay
                    WHERE pay.cart_id = o.cart_id
                      AND pay.deleted_at IS NULL
                    ORDER BY pay.created_at DESC, pay.id DESC
                    LIMIT 1
                ),
                'pending'
            ) AS payment_status,

            COALESCE(
                (
                    SELECT pay.payment_method
                    FROM payments pay
                    WHERE pay.cart_id = o.cart_id
                      AND pay.deleted_at IS NULL
                    ORDER BY pay.created_at DESC, pay.id DESC
                    LIMIT 1
                ),
                'not_recorded'
            ) AS payment_method

        FROM orders o

        LEFT JOIN users u
            ON u.id = o.user_id

        LEFT JOIN (
            SELECT
                order_id,
                SUM(quantity) AS item_count
            FROM order_items
            GROUP BY order_id
        ) i
            ON i.order_id = o.id

        WHERE o.deleted_at IS NULL

        ORDER BY o.id DESC
    ";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
    public function findByUserId(int $userId): array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM orders
             WHERE user_id = :user_id AND deleted_at IS NULL
             ORDER BY id DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(): bool
    {
        if ($this->id === null || $this->findById($this->id) === null) {
            return false;
        }

        $this->validate();

        $sql = 'UPDATE orders SET
                    user_id = :user_id,
                    cart_id = :cart_id,
                    status = :status,
                    total_jod = :total_jod,
                    shipping_name = :shipping_name,
                    shipping_phone = :shipping_phone,
                    shipping_address_line1 = :shipping_address_line1,
                    shipping_address_line2 = :shipping_address_line2,
                    notes = :notes
                WHERE id = :id AND deleted_at IS NULL';

        $values = $this->values();
        $values['id'] = $this->id;
        $statement = $this->db->prepare($sql);

        return $statement->execute($values);
    }

    public function updateStatuses(int $id, string $status, string $paymentStatus): bool
    {
        if (
            !in_array($status, ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'], true)
            || !in_array($paymentStatus, ['pending', 'authorized', 'paid', 'failed', 'refunded'], true)
        ) {
            throw new InvalidArgumentException('Invalid order or payment status.');
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $statement = $this->db->prepare('SELECT * FROM orders WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
            $statement->execute([$id]);
            $order = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                if ($ownsTransaction) {
                    $this->db->rollBack();
                }
                return false;
            }

            $payment = $this->db->prepare(
                'SELECT id FROM payments WHERE order_id = ? AND deleted_at IS NULL
                 LIMIT 1 FOR UPDATE'
            );
            $payment->execute([$id]);
            $paymentId = $payment->fetchColumn();
            if (!$paymentId && $paymentStatus !== 'pending') {
                throw new InvalidArgumentException('This order has no payment record. Keep payment status Pending.');
            }

            if (
                in_array($order['status'], ['pending', 'confirmed'], true) &&
                $status === 'cancelled'
            ) {
                (new OrderClass())->restoreInventoryForOrder($id);
            } elseif ($order['status'] === 'cancelled' && $status !== 'cancelled') {
                (new OrderClass())->reserveInventoryForOrder($id);
            }

            $existing = new self(
                $this->db,
                OrderSaveMode::UPDATE,
                (int) $order['id'],
                (int) $order['user_id'],
                $order['cart_id'] === null ? null : (int) $order['cart_id'],
                $status,
                $order['total_jod'],
                $order['shipping_name'],
                $order['shipping_phone'],
                $order['shipping_address_line1'],
                $order['shipping_address_line2'],
                $order['notes']
            );
            if (!$existing->save(OrderSaveMode::UPDATE)) {
                throw new RuntimeException('Could not update this order.');
            }

            if ($paymentId) {
                $statement = $this->db->prepare('UPDATE payments SET payment_status = ? WHERE id = ? AND deleted_at IS NULL');
                $statement->execute([$paymentStatus, $paymentId]);
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }
            return true;
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare(
                'SELECT id, status, cart_id FROM orders
                 WHERE id = ? AND deleted_at IS NULL FOR UPDATE'
            );
            $statement->execute([$id]);
            $order = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                $this->db->rollBack();
                return false;
            }

            if (in_array($order['status'], ['pending', 'confirmed'], true) && $order['cart_id'] !== null) {
                $statement = $this->db->prepare(
                    'SELECT id, payment_method, payment_status FROM payments
                     WHERE order_id = ? AND deleted_at IS NULL
                     LIMIT 1 FOR UPDATE'
                );
                $statement->execute([(int) $order['id']]);
                $payment = $statement->fetch(PDO::FETCH_ASSOC);
                if ($payment && $payment['payment_status'] !== 'paid') {
                    (new OrderClass())->restoreInventoryForOrder($id);
                    $statement = $this->db->prepare(
                        "UPDATE orders SET status = 'cancelled'
                         WHERE id = ? AND status IN ('pending', 'confirmed')"
                    );
                    $statement->execute([$id]);
                    if ($payment['payment_status'] === 'pending') {
                        $statement = $this->db->prepare(
                            "UPDATE payments SET payment_status = 'failed'
                             WHERE id = ? AND payment_status = 'pending'"
                        );
                        $statement->execute([(int) $payment['id']]);
                    }
                    $statement = $this->db->prepare(
                        "UPDATE carts SET status = 'active', updated_at = CURRENT_TIMESTAMP
                         WHERE id = ? AND deleted_at IS NULL"
                    );
                    $statement->execute([(int) $order['cart_id']]);
                }
            }

            $statement = $this->db->prepare(
                'UPDATE orders SET deleted_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND deleted_at IS NULL'
            );
            $statement->execute([$id]);
            $deleted = $statement->rowCount() > 0;
            $this->db->commit();
            return $deleted;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private function values(): array
    {
        return [
            'user_id' => $this->userId,
            'cart_id' => $this->cartId,
            'status' => $this->status,
            'total_jod' => $this->totalJod,
            'shipping_name' => $this->shippingName,
            'shipping_phone' => $this->shippingPhone,
            'shipping_address_line1' => $this->shippingAddressLine1,
            'shipping_address_line2' => $this->shippingAddressLine2,
            'notes' => $this->notes,
        ];
    }

    private function validate(): void
    {
        if ($this->userId === null || $this->userId < 1) {
            throw new InvalidArgumentException('A valid customer ID is required.');
        }

        $statuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
        if (!in_array($this->status, $statuses, true)) {
            throw new InvalidArgumentException('Invalid order status.');
        }

        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $this->totalJod)) {
            throw new InvalidArgumentException('Total must be a valid non-negative amount with up to 2 decimal places.');
        }

        if (trim($this->shippingName) === '' || trim($this->shippingPhone) === '' || trim($this->shippingAddressLine1) === '') {
            throw new InvalidArgumentException('Shipping name, phone, and address are required.');
        }
    }
}