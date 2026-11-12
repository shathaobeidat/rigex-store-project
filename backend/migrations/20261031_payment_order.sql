-- Run once when upgrading a database that links payments only through carts.
ALTER TABLE payments
    ADD COLUMN order_id BIGINT UNSIGNED NULL AFTER id,
    ADD INDEX idx_payment_order (order_id),
    ADD CONSTRAINT fk_payment_order FOREIGN KEY (order_id) REFERENCES orders (id);

-- Backfill only carts with an unambiguous order association.
UPDATE payments p
JOIN (
    SELECT cart_id, MIN(id) AS order_id
    FROM orders
    WHERE cart_id IS NOT NULL
    GROUP BY cart_id
    HAVING COUNT(*) = 1
) o ON o.cart_id = p.cart_id
SET p.order_id = o.order_id
WHERE p.order_id IS NULL;
