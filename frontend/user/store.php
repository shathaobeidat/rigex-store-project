<?php

declare(strict_types=1);

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/product_images.php';
require_once __DIR__ . '/../../backend/config/db.php';

$db = DB_conn::getConnection();
$categories = $db->query(
    "SELECT id, name
     FROM categories
     WHERE deleted_at IS NULL
     ORDER BY name"
)->fetchAll(PDO::FETCH_ASSOC);

$search = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$search = mb_substr($search, 0, 100);

$products = $db->query(
    "SELECT p.id, p.category_id, p.name, p.brand, p.description, p.price,
            p.quantity, p.condition_type, p.product_img, c.name AS category_name
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.deleted_at IS NULL
       AND p.listing_status = 'available'
       AND p.quantity > 0
       AND c.deleted_at IS NULL
     ORDER BY p.created_at DESC, p.id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$cartNotice = (string) ($_SESSION['cart_notice'] ?? '');
unset($_SESSION['cart_notice']);

store_page_start('Store', $search);
?>
<link rel="stylesheet" href="../css/store-catalog.css?v=<?= filemtime(__DIR__ . '/../css/store-catalog.css') ?>">
<script src="../js/store-reveal.js?v=<?= filemtime(__DIR__ . '/../js/store-reveal.js') ?>" defer></script>

<section class="store-catalog" aria-labelledby="store-title">
    <div class="store-catalog-heading">
        <span class="campaign-label">REGIX STORE</span>
        <h1 id="store-title"><span class="catalog-title-line">YOUR NEXT</span><em class="catalog-title-line">UPGRADE.</em></h1>
        <p>More frames. Faster workflows. A setup that feels like you.<br>Explore the hardware to make it happen.</p>
        <div class="catalog-heading-detail"><span>HARDWARE / COMPONENTS / POSSIBILITIES</span><a href="#catalog-filters">Find your component <?= store_icon('arrow') ?></a></div>
    </div>

    <?php if ($cartNotice !== ''): ?>
        <p class="landing-category-feedback" role="status"><?= e($cartNotice) ?></p>
    <?php endif; ?>

    <form class="store-catalog-filters" id="catalog-filters" action="store.php" method="get" role="search">
        <label>
            <span>Search products</span>
            <div class="store-search-box">
                <svg class="store-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="m20 20-3.5-3.5"/>
                </svg>
                <input type="search" name="q" id="store-search" value="<?= e($search) ?>" maxlength="100" autocomplete="off" placeholder="Search hardware, brands, components…">
            </div>
        </label>
        <fieldset class="catalog-filter-group catalog-categories">
            <legend>Category</legend>
            <input type="hidden" name="category_id" id="store-category" value="">
            <div class="catalog-filter-buttons">
                <button type="button" data-filter-target="store-category" data-filter-value="" aria-pressed="true">All categories</button>
                <?php foreach ($categories as $category): ?>
                    <button type="button" data-filter-target="store-category" data-filter-value="<?= (int) $category['id'] ?>" aria-pressed="false">
                        <?= e($category['name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <fieldset class="catalog-filter-group catalog-conditions">
            <legend>Condition</legend>
            <input type="hidden" name="condition" id="store-condition" value="">
            <div class="catalog-filter-buttons">
                <button type="button" data-filter-target="store-condition" data-filter-value="" aria-pressed="true">All</button>
                <button type="button" data-filter-target="store-condition" data-filter-value="new" aria-pressed="false">New</button>
                <button type="button" data-filter-target="store-condition" data-filter-value="used" aria-pressed="false">Used</button>
            </div>
        </fieldset>
        <button type="button" id="store-clear" disabled><?= store_icon('close') ?> Reset</button>
    </form>

    <div class="store-catalog-results">
        <p id="store-result-count" role="status" aria-live="polite"><?= count($products) ?> <?= count($products) === 1 ? 'product' : 'products' ?> available</p>
        <span class="catalog-stock-note"><i aria-hidden="true"></i> In stock &amp; ready to browse</span>
    </div>

    <?php if ($products === []): ?>
        <p class="landing-products-empty">No products match your search yet.</p>
    <?php else: ?>
        <p class="landing-products-empty" id="store-no-results" style="display: none;">No products match your search yet.</p>

        <div class="landing-products-grid">
            <?php foreach ($products as $product): ?>
                <?php
                $image = (string) ($product['product_img'] ?? '');
                $imageIsValid = preg_match('#\Aassets/products/[A-Za-z0-9._-]+\z#', $image) === 1
                    && is_file(__DIR__ . '/../' . $image);
                $fallbackImage = store_category_default_image((string) $product['category_name']);
                ?>
                <article class="landing-product-card"
                    data-search="<?= e(implode(' ', [(string) $product['name'], (string) $product['brand'], (string) $product['description'], (string) $product['category_name']])) ?>"
                    data-category="<?= (int) $product['category_id'] ?>"
                    data-condition="<?= e((string) $product['condition_type']) ?>">
                    <div class="landing-product-image">
                        <img src="../<?= e($imageIsValid ? $image : $fallbackImage) ?>"
                            data-category-fallback="../<?= e($fallbackImage) ?>"
                            alt="<?= e($imageIsValid ? (string) $product['name'] : (string) $product['category_name'] . ' — category illustration') ?>"
                            loading="lazy" width="480" height="360">
                        <span><?= e(strtoupper((string) $product['condition_type'])) ?></span>
                    </div>
                    <div class="landing-product-copy">
                        <span><?= e((string) ($product['brand'] ?: $product['category_name'])) ?> ·
                            <?= e($product['category_name']) ?></span>
                        <h2><?= e($product['name']) ?></h2>
                        <?php if ((string) $product['description'] !== ''): ?>
                            <p><?= e($product['description']) ?></p>
                        <?php endif; ?>
                        <div class="landing-product-buy">
                            <strong><?= number_format((float) $product['price'], 2) ?> <small>JOD</small></strong>
                            <?php if (!empty($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'customer'): ?>
                                <form method="post" action="cart_action.php">
                                    <input type="hidden" name="csrf_token" value="<?= e((string) $_SESSION['cart_csrf']) ?>">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                    <input type="hidden" name="return_to" value="store">
                                    <button type="submit">Add to cart</button>
                                </form>
                            <?php else: ?>
                                <a href="../../backend/auth/login.php">Sign in to buy</a>
                            <?php endif; ?>
                        </div>
                        <small><?= (int) $product['quantity'] ?> in stock</small>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php store_page_end(); ?>
