<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
//CSRF
$returnToStore = ($_POST['return_to'] ?? '') === 'store';
$returnPage = $returnToStore ? 'store.php' : 'index.php';
$token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
if (empty($_SESSION['cart_csrf']) || !hash_equals($_SESSION['cart_csrf'], $token)) {
    $_SESSION['cart_notice'] = 'Your session expired. Reload the page and try again.';
    header('Location: ' . $returnPage . ($returnToStore ? '' : '#categories'));
    exit;
}

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'customer') {
    $_SESSION['cart_notice'] = 'Sign in with a customer account to manage your cart.';
    header('Location: ../../backend/auth/login.php');
    exit;
}

$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
$userId = (int) $_SESSION['user_id'];
$db = DB_conn::getConnection();

try {
    $db->beginTransaction();

    if ($action === 'add') {
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($productId === false) {
            throw new InvalidArgumentException('Choose a valid product.');
        }

        $userStatement = $db->prepare(
            "SELECT id FROM users
             WHERE id = ? AND role = 'customer' AND deleted_at IS NULL
             FOR UPDATE"
        );
        $userStatement->execute([$userId]);
        if (!$userStatement->fetchColumn()) {
            throw new InvalidArgumentException('A valid customer account is required.');
        }

        $cartStatement = $db->prepare(
            "SELECT id FROM carts
             WHERE user_id = ? AND status = 'active' AND deleted_at IS NULL
             ORDER BY id DESC LIMIT 1 FOR UPDATE"
        );
        $cartStatement->execute([$userId]);
        $cartId = $cartStatement->fetchColumn();
        if (!$cartId) {
            $cartStatement = $db->prepare('INSERT INTO carts (user_id) VALUES (?)');
            $cartStatement->execute([$userId]);
            $cartId = (int) $db->lastInsertId();
        }

        $productStatement = $db->prepare(
            "SELECT id, category_id, quantity
             FROM products
             WHERE id = ? AND deleted_at IS NULL AND listing_status = 'available'
             FOR UPDATE"
        );
        $productStatement->execute([$productId]);
        $product = $productStatement->fetch(PDO::FETCH_ASSOC);
        if (!$product || (int) $product['quantity'] < 1) {
            throw new InvalidArgumentException('This product is no longer available.');
        }

        $itemStatement = $db->prepare(
            'SELECT id, quantity FROM cart_items
             WHERE cart_id = ? AND product_id = ? AND deleted_at IS NULL
             LIMIT 1 FOR UPDATE'
        );
        $itemStatement->execute([$cartId, $productId]);
        $item = $itemStatement->fetch(PDO::FETCH_ASSOC);
        $quantity = (int) ($item['quantity'] ?? 0) + 1;
        if ($quantity > (int) $product['quantity']) {
            throw new InvalidArgumentException('The requested quantity exceeds the available stock.');
        }

        if ($item) {
            $itemStatement = $db->prepare('UPDATE cart_items SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $itemStatement->execute([$quantity, $item['id']]);
        } else {
            $itemStatement = $db->prepare(
                'SELECT id FROM cart_items
                 WHERE cart_id = ? AND product_id = ? AND deleted_at IS NOT NULL
                 ORDER BY id DESC LIMIT 1 FOR UPDATE'
            );
            $itemStatement->execute([$cartId, $productId]);
            $deletedItemId = $itemStatement->fetchColumn();
            if ($deletedItemId) {
                $itemStatement = $db->prepare(
                    'UPDATE cart_items
                     SET quantity = 1, deleted_at = NULL, updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?'
                );
                $itemStatement->execute([$deletedItemId]);
            } else {
                $itemStatement = $db->prepare(
                    'INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, 1)'
                );
                $itemStatement->execute([$cartId, $productId]);
            }
        }
        $notice = 'Product added to your cart.';
        $anchor = $returnToStore ? '' : '#products-' . (int) $product['category_id'];
    } elseif ($action === 'update' || $action === 'remove') {
        $cartItemId = filter_var($_POST['cart_item_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($cartItemId === false) {
            throw new InvalidArgumentException('Choose a valid cart item.');
        }

        $itemStatement = $db->prepare(
            "SELECT ci.id, ci.cart_id, ci.product_id, p.quantity AS stock
             FROM cart_items ci
             JOIN carts c ON c.id = ci.cart_id
             LEFT JOIN products p ON p.id = ci.product_id
             WHERE ci.id = ? AND c.user_id = ? AND c.status = 'active'
               AND c.deleted_at IS NULL AND ci.deleted_at IS NULL
             FOR UPDATE"
        );
        $itemStatement->execute([$cartItemId, $userId]);
        $item = $itemStatement->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            throw new InvalidArgumentException('This cart item is no longer available.');
        }

        if ($action === 'remove') {
            $itemStatement = $db->prepare(
                'UPDATE cart_items SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
            );
            $itemStatement->execute([$cartItemId]);
            $notice = 'Product removed from your cart.';
        } else {
            $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $availabilityStatement = $db->prepare(
                "SELECT id FROM products
                 WHERE id = ? AND deleted_at IS NULL AND listing_status = 'available'
                   AND quantity >= ?"
            );
            $availabilityStatement->execute([(int) $item['product_id'], $quantity === false ? 0 : $quantity]);
            if ($quantity === false || !$availabilityStatement->fetchColumn()) {
                throw new InvalidArgumentException('Enter a quantity within the available stock.');
            }
            $itemStatement = $db->prepare('UPDATE cart_items SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $itemStatement->execute([$quantity, $cartItemId]);
            $notice = 'Cart quantity updated.';
        }

        $anchor = $returnToStore ? '' : '#categories';
    } else {
        throw new InvalidArgumentException('Choose a valid cart action.');
    }

    $totalStatement = $db->prepare(
        'UPDATE carts c
         SET total = (
             SELECT COALESCE(SUM(ci.quantity * p.price), 0)
             FROM cart_items ci
             JOIN products p ON p.id = ci.product_id
             WHERE ci.cart_id = c.id AND ci.deleted_at IS NULL AND p.deleted_at IS NULL
         ), updated_at = CURRENT_TIMESTAMP
         WHERE c.id = ?'
    );
    $totalStatement->execute([$cartId ?? $item['cart_id']]);
    $db->commit();
    $_SESSION['cart_notice'] = $notice;
} catch (InvalidArgumentException $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['cart_notice'] = $error->getMessage();
    $anchor = $returnToStore ? '' : '#categories';
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Cart update: ' . $error->getMessage());
    $_SESSION['cart_notice'] = 'We could not update your cart. Please try again.';
    $anchor = $returnToStore ? '' : '#categories';
}

header('Location: ' . $returnPage . ($anchor ?? ($returnToStore ? '' : '#categories')));
exit;
