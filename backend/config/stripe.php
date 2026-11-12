<?php

declare(strict_types=1);

$localStripeConfig = __DIR__ . '/stripe.local.php';
if (is_file($localStripeConfig)) {
    require_once $localStripeConfig;
}

if (!defined('STRIPE_SECRET_KEY')) {
    $stripeSecretKey = getenv('STRIPE_SECRET_KEY');
    define('STRIPE_SECRET_KEY', is_string($stripeSecretKey) ? $stripeSecretKey : '');
}

if (!defined('SITE_URL')) {
    $siteUrl = getenv('SITE_URL');
    define('SITE_URL', is_string($siteUrl) ? rtrim($siteUrl, '/') : '');
}
