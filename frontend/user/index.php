<?php

declare(strict_types=1);
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/product_images.php';
require_once __DIR__ . '/../../backend/config/db.php';

$db = DB_conn::getConnection();
$productSelect = "SELECT p.id, p.category_id, p.name, p.brand, p.description, p.price,
                         p.quantity, p.condition_type, p.product_img, c.name AS category_name
                  FROM products p
                  JOIN categories c ON c.id = p.category_id";
$productAvailability = "p.deleted_at IS NULL AND c.deleted_at IS NULL
                        AND p.listing_status = 'available' AND p.quantity > 0";
$products = $db->query(
    $productSelect . " JOIN (
        SELECT oi.product_id, SUM(oi.quantity) AS units_sold
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        WHERE o.deleted_at IS NULL AND o.status <> 'cancelled'
          AND EXISTS (
              SELECT 1 FROM payments pay
              WHERE pay.cart_id = o.cart_id
                AND pay.payment_status = 'paid' AND pay.deleted_at IS NULL
          )
        GROUP BY oi.product_id
        HAVING SUM(oi.quantity) > 0
    ) sales ON sales.product_id = p.id
    WHERE " . $productAvailability . "
    ORDER BY sales.units_sold DESC, p.created_at DESC, p.id DESC LIMIT 6"
)->fetchAll(PDO::FETCH_ASSOC);
$showBestSellers = $products !== [];
if (!$showBestSellers) {
    $products = $db->query(
        $productSelect . " WHERE " . $productAvailability . "
        ORDER BY p.created_at DESC, p.id DESC LIMIT 6"
    )->fetchAll(PDO::FETCH_ASSOC);
}
if (empty($_SESSION['cart_csrf'])) {
    $_SESSION['cart_csrf'] = bin2hex(random_bytes(32));
}

$cartNotice = $_SESSION['cart_notice'] ?? '';
unset($_SESSION['cart_notice']);


$rigs = [
    [
        'Titan Ultra',
        'For the uncompromising.',
        '4K ENTHUSIAST',
        'Intel Core i9-14900K',
        'RTX 4090 · 24GB',
        '64GB DDR5',
        'photo-1587202372775-e229f172b9d7',
        'ultra',
        'cyan',
    ],
    [
        'Apex AMD',
        'Find your competitive edge.',
        'COMPETITIVE GAMING',
        'AMD Ryzen 7 7800X3D',
        'RTX 4080 Super · 16GB',
        '32GB DDR5',
        'photo-1591488320449-011701bb6704',
        'apex',
        'purple',
    ],
    [
        'Phantom',
        'Your next chapter starts here.',
        'EVERYDAY GAMING',
        'Intel Core i5-14600K',
        'RTX 4070 Super · 12GB',
        '32GB DDR5',
        'photo-1547082299-de196ea013d6',
        'phantom',
        'pink',
    ],
];

store_page_start('Next level. No limits.', '', true);
?>
<div class="regix-intro" id="regix-intro" hidden aria-hidden="true"><span>REGIX / NEXT LEVEL SHOPPING</span><button type="button" id="skip-regix-intro">Skip intro ↗</button><strong><span id="regix-load-count">0</span><em>%</em></strong></div>
<section class="campaign-hero" id="top" aria-labelledby="campaign-title">
    <div class="campaign-hero-top">
        <p class="campaign-roles"><span>PERFORMANCE.</span><span>PRECISION.</span><span>POSSIBILITY.</span></p>
        <div class="campaign-hero-note"><span class="campaign-label">HARDWARE FOR WHAT’S NEXT</span><p>For the late-night players. The big-idea creators. The ones who never settle for the ordinary.</p><a href="#rigs" class="campaign-link">Find your next level <?= store_icon('arrow') ?></a></div>
    </div>
    <h1 class="campaign-hero-title" id="campaign-title" aria-label="Next level."><span class="hero-word" aria-hidden="true"><span>N</span><span>E</span><span>X</span><span>T</span></span><span class="hero-word" aria-hidden="true"><span>L</span><span>E</span><span>V</span><span>E</span><span>L</span><span>.</span></span></h1>
    <figure class="campaign-hero-product campaign-liquid" data-bare><img src="../assets/regix-hero-tower.png" alt="Black gaming tower with lime illuminated cooling fans" width="1024" height="1536" fetchpriority="high"></figure>
    <div class="campaign-hero-base" aria-hidden="true"></div>
    <div class="campaign-hero-bottom"><span>REGIX — HARDWARE WITH INTENT.</span><a href="#story">SCROLL TO EXPLORE <span>↓</span></a><span>AMMAN, JO / JOD</span></div>
</section>
<section class="campaign-marquee" aria-label="Play. Create. Go beyond."><div class="marquee-track" aria-hidden="true"><?php for ($i = 0; $i < 2; $i++): ?><div><span>PLAY.</span><span class="marquee-outline">CREATE.</span><span>GO BEYOND.</span><span class="marquee-star">✳</span></div><?php endfor; ?></div><button type="button" id="campaign-motion-toggle" aria-pressed="false">Pause motion Ⅱ</button></section>
<section class="campaign-story campaign-section" id="story">
    <div class="campaign-section-heading campaign-reveal"><span class="campaign-label">01 / THE REGIX MINDSET</span><h2>GREAT SETUPS<br>DON’T HAPPEN<br><span>BY ACCIDENT.</span></h2></div>
    <div class="campaign-story-copy campaign-reveal"><p class="campaign-lead">Every great session starts long before you press play.</p><p>It starts with the right parts. A considered choice. A setup that feels like an extension of you. REGIX brings the possibilities together, from your first upgrade to your next ambitious build.</p></div><div class="campaign-principles"><article><span>01</span><div><h3>Choose with intent.</h3><p>Build around what you actually do. Play, create, work, or a little of everything.</p></div></article><article><span>02</span><div><h3>Make room for more.</h3><p>Find components that fit your plans today and leave possibilities for tomorrow.</p></div></article><article><span>03</span><div><h3>Make it yours.</h3><p>New hardware or a pre-loved find. Your next setup doesn’t have to look like anyone else’s.</p></div></article></div>
</section>
<section class="campaign-categories campaign-section landing-featured" id="featured-products" aria-labelledby="category-title">
    <p class="landing-category-feedback" id="landing-category-feedback" role="status" hidden></p>
    <div class="campaign-section-heading campaign-reveal">
        <div><span class="campaign-label">02 / <?= $showBestSellers ? 'THE BEST SELLERS' : 'JUST LANDED' ?></span>
        <h2 id="category-title"><?= $showBestSellers ? 'MOST WANTED.' : 'FRESH PARTS.' ?><br><span><?= $showBestSellers ? 'FOR GOOD REASON.' : 'NEW POSSIBILITIES.' ?></span></h2></div>
        <p><?= $showBestSellers ? 'The parts our customers keep choosing for their next upgrade.' : 'Explore the latest additions for your next upgrade.' ?></p>
    </div>
    <?php if ($cartNotice !== ''): ?><p class="landing-category-feedback" role="status"><?= e($cartNotice) ?></p><?php endif; ?>
    <?php if ($products === []): ?>
        <p class="landing-products-empty">New parts are on the way. Check back soon for your next upgrade.</p>
    <?php else: ?>
        <div class="landing-products-grid">
            <?php foreach ($products as $product): ?>
                            <?php
                            $image = (string) ($product['product_img'] ?? '');
                            $imageIsValid = preg_match('#\Aassets/products/[A-Za-z0-9._-]+\z#', $image) === 1
                                && is_file(__DIR__ . '/../' . $image);
                            $fallbackImage = store_category_default_image((string) $product['category_name']);
                            ?>
                            <article class="landing-product-card">
                                <div class="landing-product-image">
                                    <img src="../<?= e($imageIsValid ? $image : $fallbackImage) ?>"
                                        data-category-fallback="../<?= e($fallbackImage) ?>"
                                        alt="<?= e($imageIsValid ? (string) $product['name'] : (string) $product['category_name'] . ' — category illustration') ?>"
                                        loading="lazy" width="480" height="320">
                                    <span><?= e(strtoupper((string) $product['condition_type'])) ?></span>
                                </div>
                                <div class="landing-product-copy">
                                    <span><?= e((string) ($product['brand'] ?: $product['category_name'])) ?></span>
                                    <h3><?= e($product['name']) ?></h3>
                                    <?php if ((string) $product['description'] !== ''): ?><p><?= e($product['description']) ?></p><?php endif; ?>
                                    <div class="landing-product-buy">
                                        <strong><?= number_format((float) $product['price'], 2) ?> <small>JOD</small></strong>
                                        <?php if (!empty($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'customer'): ?>
                                            <form method="post" action="cart_action.php">
                                                <input type="hidden" name="csrf_token" value="<?= e((string) $_SESSION['cart_csrf']) ?>">
                                                <input type="hidden" name="action" value="add">
                                                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                                <button type="submit" <?= (int) $product['quantity'] < 1 ? 'disabled' : '' ?>>Add to cart</button>
                                            </form>
                                        <?php else: ?>
                                            <?php if (!empty($_SESSION['user_id'])): ?>
                                                <a href="store.php">View in store <?= store_icon('arrow') ?></a>
                                            <?php else: ?>
                                                <a href="../../backend/auth/login.php">Sign in to buy <?= store_icon('arrow') ?></a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <small><?= (int) $product['quantity'] ?> in stock</small>
                                </div>
                            </article>
            <?php endforeach; ?>
        </div>
        <div class="featured-store-link"><span>More possibilities for your next build.</span><a href="store.php">Explore all products <?= store_icon('arrow') ?></a></div>
    <?php endif; ?>
</section>
<section class="campaign-rigs campaign-section" id="rigs" aria-labelledby="rig-title">
    <div class="campaign-section-heading campaign-reveal"><div><span class="campaign-label">03 / SELECTED BUILD CONCEPTS</span><h2 id="rig-title">THE NEXT THING.<br><span>YOUR THING.</span></h2></div><p>03 concepts. Countless possibilities.<br>Illustrative configurations to spark your next build.</p></div>
    <div class="campaign-rig-grid"><?php foreach ($rigs as $i => [$name, $description, $badge, $cpu, $gpu, $ram, $photo, $preset, $accent]): ?><article class="campaign-rig campaign-reveal"><a class="campaign-rig-image campaign-liquid" href="store.php" aria-label="Browse components for <?= e($name) ?>"><img src="https://images.unsplash.com/<?= e($photo) ?>?auto=format&fit=crop&w=1100&q=85" alt="<?= e($name) ?> gaming setup inspiration" width="1100" height="760" loading="lazy"><span class="campaign-image-index">0<?= $i + 1 ?> / REGIX CONCEPT</span><span class="campaign-image-arrow"><?= store_icon('arrow') ?></span></a><div class="campaign-rig-caption"><div><h3><?= e($name) ?></h3><p><?= e($description) ?></p></div><span><?= e($badge) ?></span></div><p class="campaign-rig-spec"><?= e($cpu) ?> / <?= e($gpu) ?> / <?= e($ram) ?></p></article><?php endforeach; ?><article class="campaign-custom-rig campaign-reveal"><span class="campaign-label">THE MOST IMPORTANT BUILD IS YOURS.</span><h3>YOUR RULES.<br>YOUR RIG.</h3><p>Start with a concept. Make it personal.</p><a href="store.php" class="campaign-link">Browse components <?= store_icon('arrow') ?></a><span class="campaign-custom-cross" aria-hidden="true">✳</span></article></div>
</section>
<section class="campaign-playstyles campaign-section" id="playstyles">
    <div class="campaign-section-heading campaign-reveal"><div><span class="campaign-label">04 / FIND YOUR FOCUS</span><h2>WHAT DRIVES<br><span>YOUR SETUP?</span></h2></div></div>
    <div class="campaign-playstyle-grid campaign-reveal"><div class="campaign-playstyle-tabs" role="tablist" aria-label="Choose your setup focus"><button type="button" role="tab" id="focus-play" aria-selected="true" aria-controls="focus-panel" data-focus="play"><span>01</span><span>THE PLAYER<small>FRAMES. FOCUS. FLOW.</small></span><i>↗</i></button><button type="button" role="tab" id="focus-create" aria-selected="false" aria-controls="focus-panel" tabindex="-1" data-focus="create"><span>02</span><span>THE CREATOR<small>IDEAS INTO REALITY.</small></span><i>↗</i></button><button type="button" role="tab" id="focus-everyday" aria-selected="false" aria-controls="focus-panel" tabindex="-1" data-focus="everyday"><span>03</span><span>THE EVERYDAY<small>LESS WAITING. MORE DOING.</small></span><i>↗</i></button></div><div class="campaign-focus-panel" role="tabpanel" id="focus-panel" aria-labelledby="focus-play" tabindex="0"><span class="campaign-focus-glyph" aria-hidden="true">↗</span><p id="focus-copy">Stay in the moment. Start with graphics performance, a balanced processor, and the responsiveness that makes every session feel right.</p><a href="store.php" class="campaign-link">Find your upgrade <?= store_icon('arrow') ?></a></div></div>
</section>
<section class="campaign-upgrade campaign-reveal" id="upgrade" aria-labelledby="upgrade-title">
    <div><span class="campaign-label">YOUR NEXT LEVEL STARTS HERE.</span><h2 id="upgrade-title">READY FOR AN UPGRADE?</h2><p>Find your next component. Make your setup yours.</p></div>
    <a class="campaign-upgrade-button" href="store.php">Browse the store <?= store_icon('arrow') ?></a>
</section>

<section class="campaign-contact campaign-section" id="contact"><span class="campaign-label campaign-reveal">YOUR NEXT CHAPTER STARTS HERE.</span><h2 class="campaign-reveal">MAKE IT<br><span>YOURS.</span></h2><div class="campaign-contact-bottom campaign-reveal"><p>One component. A whole new setup.<br>There’s always a next level.</p><a href="store.php" class="campaign-contact-link">LET’S BUILD <?= store_icon('arrow') ?></a><a href="store.php" class="campaign-link">Explore the store ↗</a></div></section>
<?php store_page_end(); ?>

