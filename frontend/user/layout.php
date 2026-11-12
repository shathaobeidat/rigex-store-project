<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/config/config.php';

function store_icon(string $name): string
{
    $paths = [
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/>',
        'arrow' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'box' => '<path d="m3 7 9-4 9 4v10l-9 4-9-4ZM3 7l9 4 9-4M12 11v10M7 5l10 4"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'cart' => '<path d="M3 3h2l3 12h11l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'chevron' => '<path d="m8 10 4 4 4-4"/>',
    ];

    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"'
        . ' stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"'
        . ' aria-hidden="true">'
        . ($paths[$name] ?? $paths['box'])
        . '</svg>';
}

function store_cart(): array
{
    static $cart = null;
    if ($cart !== null) {
        return $cart;
    }

    $cart = ['items' => [], 'count' => 0, 'total' => 0.0, 'error' => false];
    if (empty($_SESSION['user_id'])) {
        return $cart;
    }

    try {
        require_once __DIR__ . '/../../backend/config/db.php';
        $db = DB_conn::getConnection();
        $statement = $db->prepare(
            "SELECT c.id FROM carts c
             WHERE c.user_id = ?
               AND c.status = 'active' AND c.deleted_at IS NULL
             ORDER BY c.created_at DESC, c.id DESC LIMIT 1"
        );
        $statement->execute([(int) $_SESSION['user_id']]);
        $id = $statement->fetchColumn();
        if ($id) {
            $statement = $db->prepare(
                "SELECT ci.id AS cart_item_id, p.id AS product_id,
                        COALESCE(p.name, 'Unavailable product') AS name, p.price,
                        ci.quantity, p.quantity AS stock, p.listing_status,
                        p.deleted_at AS product_deleted_at
                 FROM cart_items ci LEFT JOIN products p ON p.id = ci.product_id
                 WHERE ci.cart_id = ? AND ci.deleted_at IS NULL AND ci.quantity > 0
                 ORDER BY ci.id"
            );
            $statement->execute([$id]);
            $cart['items'] = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cart['items'] as $item) {
                $cart['count'] += (int) $item['quantity'];
                $cart['total'] += (float) $item['price'] * (int) $item['quantity'];
            }
        }
    } catch (Throwable $error) {
        error_log('Store cart: ' . $error->getMessage());
        $cart['error'] = true;
    }
    return $cart;
}

function store_page_start(
    string $title = 'Discover your next find',
    string $search = '',
    bool $landing = false
): void {
    $cart = store_cart();
    $signedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['email']);
    $currentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $isStorePage = $currentPage === 'store.php';
    $isProfilePage = $currentPage === 'userprofile.php';
    $isBuilderPage = $currentPage === 'pc_builder.php';
    $pageSearch = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    if (empty($_SESSION['cart_csrf'])) {
        $_SESSION['cart_csrf'] = bin2hex(random_bytes(32));
    }
    ?>
    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#090a0c">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Oxanium:wght@500;600;700;800&display=swap"
            rel="stylesheet">
        <meta name="description"
            content="Discover new and pre-loved products at REGIX. Browse featured products and find your next everyday essential.">
        <title>
            <?= e($title) ?>
            · REGIX
        </title>
        <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
        <link rel="stylesheet" href="../css/store.css">
        <?php if ($landing): ?>
            <link rel="stylesheet" href="../css/landing.css">
        <?php endif; ?>
        <link rel="stylesheet" href="../css/storefront.css?v=<?= filemtime(__DIR__ . '/../css/storefront.css') ?>">
        <link rel="stylesheet" href="../css/typography.css?v=<?= filemtime(__DIR__ . '/../css/typography.css') ?>">
        <?php if ($landing || $isStorePage || $isProfilePage || $isBuilderPage): ?>
            <link href="https://fonts.googleapis.com/css2?family=Anton&family=Onest:wght@400;500;600&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="../css/cinematic.css?v=<?= filemtime(__DIR__ . '/../css/cinematic.css') ?>">
        <?php endif; ?>
        <?php if ($landing): ?>
            <script src="../js/cinematic.js?v=<?= filemtime(__DIR__ . '/../js/cinematic.js') ?>" defer></script>
        <?php endif; ?>
        <link rel="stylesheet" href="../css/store-cart.css?v=<?= filemtime(__DIR__ . '/../css/store-cart.css') ?>">
        <script src="../js/store.js?v=<?= filemtime(__DIR__ . '/../js/store.js') ?>" defer></script>
    </head>
    <body<?= $landing ? ' class="gaming-page"' : ($isStorePage ? ' class="gaming-page store-page"' : ($isProfilePage ? ' class="gaming-page profile-page"' : ($isBuilderPage ? ' class="gaming-page builder-page"' : ''))) ?>>
        <div class="store-ambient" aria-hidden="true"><span class="ambient-glow ambient-glow-one"></span><span class="ambient-glow ambient-glow-two"></span><span class="ambient-grid"></span><span class="ambient-stars"></span></div>
        <div class="announcement">
            <span>
                NEXT LEVEL HARDWARE. EVERYDAY POSSIBILITIES.
            </span>
            <span>
                <a href="index.php#featured-products">Find your next upgrade <?= store_icon('arrow') ?></a>
            </span>
        </div>
        <header class="store-header">
            <div class="header-main wrap">
                <a class="store-brand" href="index.php" aria-label="REGIX home">
                    <img src="../assets/regix-logo.svg" alt="REGIX — Next level shopping" width="180" height="44">
                </a>
                <nav class="store-nav" id="store-nav" aria-label="Store navigation">
                <a href="index.php"<?= $landing ? ' aria-current="page"' : '' ?>>Home</a>
                <a href="store.php"<?= $isStorePage ? ' aria-current="page"' : '' ?>>Store</a>
               
                <a href="index.php#featured-products">Featured products</a>
                <a href="index.php#rigs">Featured rigs</a>
                <a href="pc_builder.php"<?= $isBuilderPage ? ' aria-current="page"' : '' ?>>Build planner</a>
                </nav>
                <div class="store-header-actions">
                    <button class="store-icon-button" type="button" data-open-store-dialog="store-search-dialog" aria-label="<?= $isStorePage ? 'Search products' : 'Search featured products' ?>" title="<?= $isStorePage ? 'Search products' : 'Search featured products' ?>"><?= store_icon('search') ?></button>
                    <?php if ($signedIn): ?>
                        <details class="store-account">
                            <summary aria-label="Your account"><?= store_icon('user') ?><span
                                    class="account-name"><?= e((string) ($_SESSION['user_name'] ?? 'Account')) ?></span><?= store_icon('chevron') ?>
                            </summary>
                            <div class="store-account-panel"><small>WELCOME
                                    BACK</small><strong><?= e((string) ($_SESSION['user_name'] ?? 'Your account')) ?></strong><a
                                    href="userprofile.php">Your profile
                                    <?= store_icon('arrow') ?></a><?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?><a
                                        href="../admin/dashboard.php">Admin dashboard
                                        <?= store_icon('arrow') ?></a><?php endif; ?><a href="../../backend/auth/logout.php">Sign
                                    out</a></div>
                        </details>
                    <?php else: ?>
                        <a class="store-icon-button store-sign-in" href="../../backend/auth/login.php"
                            aria-label="Sign in"><?= store_icon('user') ?><span>Sign in</span></a>
                    <?php endif; ?>
                    <button class="store-cart-button" type="button" data-open-store-dialog="store-cart-dialog"
                        aria-label="Open cart, <?= (int) $cart['count'] ?> items">
                        <?= store_icon('cart') ?><span class="cart-button-label">Cart</span><span
                            class="store-cart-count"><?= (int) $cart['count'] ?></span>
                    </button>
                    <button class="menu-button" type="button" aria-controls="store-nav" aria-expanded="false"
                        aria-label="Toggle navigation"><?= store_icon('menu') ?></button>
                </div>
            </div>
        </header>
        <main id="main-content" class="wrap store-main">
            <?php
}

function store_page_end(): void
{
    $cart = store_cart();
    $signedIn = !empty($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'customer';
    $isStorePage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'store.php';
    $pageSearch = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    ?>
        </main>
        <dialog class="store-search-dialog" id="store-search-dialog" aria-labelledby="store-search-title">
            <div class="store-dialog-heading">
                <div><span class="gaming-kicker">FIND YOUR NEXT UPGRADE</span><h2 id="store-search-title"><?= $isStorePage ? 'Search products' : 'Search featured products' ?></h2></div>
                <button class="store-icon-button" type="button" data-close-store-dialog aria-label="Close search"><?= store_icon('close') ?></button>
            </div>
            <p><?= $isStorePage ? 'Find a product by name, brand, or description.' : 'Search featured products for your next upgrade.' ?></p>
            <form class="store-search-form" id="landing-category-search" action="<?= $isStorePage ? 'store.php' : 'index.php#featured-products' ?>" method="get">
                <?= store_icon('search') ?>
                <label class="sr-only" for="header-search"><?= $isStorePage ? 'Search products' : 'Search featured products' ?></label>
                <input id="header-search" name="q" type="search" value="<?= $isStorePage ? e($pageSearch) : '' ?>" placeholder="<?= $isStorePage ? 'Search products' : 'Search featured products' ?>" autocomplete="off">
                <button type="submit" aria-label="Search"><?= store_icon('arrow') ?></button>
            </form>
        </dialog>
        <dialog class="store-cart-dialog" id="store-cart-dialog" aria-labelledby="store-cart-title">
            <div class="store-dialog-heading">
                <div><span class="gaming-kicker">YOUR SELECTION</span>
                    <h2 id="store-cart-title">Your cart <span><?= (int) $cart['count'] ?></span></h2>
                </div>
                <button class="store-icon-button" type="button" data-close-store-dialog
                    aria-label="Close cart"><?= store_icon('close') ?></button>
            </div>
            <?php if ($cart['error']): ?>
                <p class="store-cart-empty" role="alert">Your cart could not be loaded. Please try again.</p>
            <?php elseif ($cart['items'] === []): ?>
                <div class="store-cart-empty">
                    <span class="cart-empty-orbit"><?= store_icon('cart') ?></span>
                    <span class="gaming-kicker">NOTHING HERE YET</span>
                    <h3>Your next upgrade is waiting.</h3>
                    <p>Explore featured products and add your favorites to your cart.</p>
                    <button class="button" type="button" data-close-store-dialog>Explore products</button>
                </div>
            <?php else: ?>
                <div class="store-cart-items">
                    <?php foreach ($cart['items'] as $item): ?>
                        <article class="store-cart-item">
                            <span class="cart-item-icon"><?= store_icon('box') ?></span>
                            <div>
                                <h3><?= e((string) $item['name']) ?></h3>
                                <p><?= number_format((float) $item['price'], 2) ?> JOD each</p>
                                <?php if ($signedIn): ?>
                                    <form class="store-cart-quantity" method="post" action="cart_action.php">
                                        <input type="hidden" name="csrf_token" value="<?= e((string) $_SESSION['cart_csrf']) ?>">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="cart_item_id" value="<?= (int) $item['cart_item_id'] ?>">
                                        <label class="sr-only" for="cart-quantity-<?= (int) $item['cart_item_id'] ?>">Quantity for
                                            <?= e((string) $item['name']) ?></label>
                                        <input id="cart-quantity-<?= (int) $item['cart_item_id'] ?>" name="quantity" type="number"
                                            min="1" max="<?= (int) $item['stock'] ?>" value="<?= (int) $item['quantity'] ?>"
                                            <?= empty($item['stock']) || $item['product_deleted_at'] !== null || $item['listing_status'] !== 'available' ? 'disabled' : '' ?>>
                                        <button type="submit" <?= empty($item['stock']) || $item['product_deleted_at'] !== null || $item['listing_status'] !== 'available' ? 'disabled' : '' ?>>Update</button>
                                    </form>
                                    <form method="post" action="cart_action.php">
                                        <input type="hidden" name="csrf_token" value="<?= e((string) $_SESSION['cart_csrf']) ?>">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="cart_item_id" value="<?= (int) $item['cart_item_id'] ?>">
                                        <button class="store-cart-remove" type="submit">Remove</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($item['product_deleted_at'] !== null || $item['listing_status'] !== 'available' || (int) $item['stock'] < 1): ?><small>This
                                        product is no longer available.</small><?php endif; ?>
                            </div>
                            <strong><?= number_format((float) $item['price'] * (int) $item['quantity'], 2) ?><small>JOD</small></strong>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="store-cart-summary"><span>Subtotal</span><strong><?= number_format((float) $cart['total'], 2) ?>
                        JOD</strong>
                    <p>Delivery charges, if any, are confirmed at checkout.</p>
                </div>
                <div class="store-cart-bottom">
                    <?php if ($signedIn): ?>
                        <a class="button" href="checkout.php">Proceed to checkout <?= store_icon('arrow') ?></a>
                    <?php else: ?>
                        <a class="button" href="../../backend/auth/login.php">Sign in to checkout <?= store_icon('arrow') ?></a>
                    <?php endif; ?>
                    <span><?= store_icon('check') ?> Current prices are checked again at checkout.</span>
                </div>
            <?php endif; ?>
        </dialog>
        <footer class="store-footer">
            <div class="wrap footer-inner">
                <div>
                    <a class="footer-logo" href="index.php">
                        REGIX
                        <span>
                            .
                        </span>
                    </a>
                    <p>
                        Next level shopping. Everyday discoveries.
                    </p>
                </div>
                <nav aria-label="Footer navigation">
                    <a href="index.php#rigs">
                        Featured builds
                    </a>
                    <a href="store.php">Browse the store</a>
                    <a href="<?= (!empty($_SESSION['email']) || !empty($_SESSION['user_id']))
                        ? 'userprofile.php'
                        : '../../backend/auth/login.php' ?>">
                        Your account
                    </a>
                </nav>
                <small>
                    &copy; <?= date('Y') ?> REGIX
                </small>
            </div>
        </footer>
        </body>

    </html>
    <?php
}
