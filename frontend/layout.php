<?php

require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/admin/guard.php';

function regix_brand(string $assetPrefix = ''): void
{
    ?>
    <a class="brand" href="dashboard.php" aria-label="REGIX dashboard">
        <img class="brand-logo" src="<?= e($assetPrefix) ?>assets/regix-logo.svg"
            alt="REGIX — Next level shopping" width="220" height="53">
    </a>
    <?php
}

// CHANGED: added optional 3rd parameter $extraHead so a page can load its own CSS/fonts/icons inside <head>
function page_start(string $title, string $active, string $extraHead = ''): void
{
    $inAdminDirectory = basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'admin';
    $assetPrefix = $inAdminDirectory ? '../' : '';
    $loginPath = $inAdminDirectory
        ? '../../backend/auth/login.php'
        : '../backend/auth/login.php';
    $logoutPath = $inAdminDirectory
        ? '../../backend/auth/logout.php'
        : '../backend/auth/logout.php';

    $links = [
        ['dashboard.php', 'Overview', 'overview'],
        ['categories.php', 'Categories', 'categories'],
        ['products.php', 'Products', 'products'],
        ['orders.php', 'Orders', 'orders'],
        ['users.php', 'Customers / Users', 'users'],
    ];

    $name = admin_name();
    $isLoggedIn = !empty($_SESSION['email']) || !empty($_SESSION['user_id']);
    $profilePath = $inAdminDirectory ? 'adminprofile.php' : 'admin/adminprofile.php';
    $profileHref = $isLoggedIn ? $profilePath : $loginPath;
    $roleLabels = [
        'admin' => 'Administrator',
        'customer' => 'Customer',
        'seller' => 'Seller',
    ];
    $roleLabel = $isLoggedIn
        ? ($roleLabels[$_SESSION['user_role'] ?? ''] ?? 'User')
        : 'Not signed in';

    $icons = [
        'overview' =>
            '<rect x="3" y="3" width="7" height="7" rx="1"/>
             <rect x="14" y="3" width="7" height="7" rx="1"/>
             <rect x="3" y="14" width="7" height="7" rx="1"/>
             <rect x="14" y="14" width="7" height="7" rx="1"/>',

        'categories' =>
            '<rect x="3" y="3" width="7" height="7" rx="1"/>
             <rect x="14" y="3" width="7" height="7" rx="1"/>
             <rect x="3" y="14" width="7" height="7" rx="1"/>
             <rect x="14" y="14" width="7" height="7" rx="1"/>',

        'products' =>
            '<path d="m3 7 9-4 9 4v10l-9 4-9-4Z"/>
             <path d="m3 7 9 4 9-4M12 11v10"/>',

        'orders' =>
            '<rect x="5" y="3" width="14" height="18" rx="2"/>
             <path d="M9 3.5h6"/>
             <path d="M9 9h6"/>
             <path d="M9 13h6"/>
             <path d="M9 17h4"/>',

        'users' =>
            '<circle cx="9" cy="8" r="3"/>
             <path d="M3 20v-2a6 6 0 0 1 12 0v2"/>
             <path d="M16 5a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 5v1"/>',
    ];
    ?>

    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="utf-8">

        <meta name="viewport" content="width=device-width, initial-scale=1">

        <meta name="theme-color" content="#090a0c">
        <meta name="color-scheme" content="dark">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Oxanium:wght@500;600;700;800&display=swap" rel="stylesheet">

        <title><?= e($title) ?> · REGIX Admin</title>

        <link rel="icon" type="image/svg+xml" href="<?= e($assetPrefix) ?>assets/favicon.svg">

        <link rel="stylesheet" href="<?= e($assetPrefix) ?>css/admin.css">
        <script src="<?= e($assetPrefix) ?>js/admin.js" defer></script>

        <?= $extraHead ?>
        <link rel="stylesheet" href="<?= e($assetPrefix) ?>css/admin-unified.css?v=<?= filemtime(__DIR__ . '/css/admin-unified.css') ?>">
        <link rel="stylesheet" href="<?= e($assetPrefix) ?>css/typography.css?v=<?= filemtime(__DIR__ . '/css/typography.css') ?>">

    </head>

    <body data-admin-page="<?= e(pathinfo($active, PATHINFO_FILENAME)) ?>" class="<?= in_array($active, ['products.php', 'categories.php', 'users.php'], true) ? 'admin-management' : 'admin-dashboard' ?>">

        <input class="nav-toggle" type="checkbox" id="nav-toggle" aria-label="Open navigation">

        <div class="app-shell">

            <!-- Sidebar -->
            <aside class="sidebar" id="sidebar">

                <div class="sidebar-top">

                    <?php regix_brand($assetPrefix); ?>

                    <!-- Workspace Switcher -->
                    <details class="workspace-menu">
                    <summary class="workspace-switcher">

                        <span class="workspace-icon">
                            <img src="<?= e($assetPrefix) ?>assets/regix-mark.svg" alt=""
                                width="27" height="27">
                        </span>

                        <span class="workspace-copy">
                            <strong>Regix Store</strong>
                            <small>Admin workspace</small>
                        </span>

                        <span class="chevron" aria-hidden="true">
                            ⌄
                        </span>

                    </summary>
                    <nav class="workspace-menu-links" aria-label="Workspace account">
                        <a href="<?= e($profileHref) ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg>
                            Your profile
                        </a>
                        <a class="workspace-sign-out" href="<?= e($logoutPath) ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5H4v14h5M14 8l4 4-4 4M8 12h12"/></svg>
                            Sign out
                        </a>
                    </nav>
                    </details>

                    <!-- Workspace Navigation -->
                    <div class="sidebar-nav-scroll" tabindex="0" role="region" aria-label="Sidebar navigation">
                    <div class="nav-label">
                        WORKSPACE
                    </div>

                    <nav class="main-nav" aria-label="Main navigation">

                        <?php foreach ($links as [$file, $label, $icon]): ?>

                            <a class="nav-link <?= $active === $file ? 'active' : '' ?>"
                                href="<?= e($file === 'orders.php' && !$inAdminDirectory ? 'admin/orders.php' : $file) ?>"
                                <?= $active === $file ? 'aria-current="page"' : '' ?>>

                                <span class="nav-icon" aria-hidden="true">

                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <?= $icons[$icon] ?>
                                    </svg>

                                </span>

                                <span>
                                    <?= e($label) ?>
                                </span>

                                <?php if ($active === $file): ?>
                                    <span class="nav-pip"></span>
                                <?php endif; ?>

                            </a>

                        <?php endforeach; ?>

                    </nav>

                    <!-- Account Navigation -->
                    <div class="nav-label tools-label">
                        ACCOUNT
                    </div>

                    <nav class="main-nav" aria-label="Account">

                        <?php if (!$isLoggedIn): ?>
                        <a class="nav-link" href="<?= e($loginPath) ?>">

                            <span class="nav-icon" aria-hidden="true">
                                ↪
                            </span>

                            <span>Login</span>

                        </a>

                        <?php else: ?>
                        <a class="nav-link" href="<?= e($logoutPath) ?>">

                            <span class="nav-icon" aria-hidden="true">
                                ⇥
                            </span>

                            <span>Sign out</span>

                        </a>
                        <?php endif; ?>

                    </nav>

                    </div>
                </div>

                <!-- Sidebar Bottom -->
                <div class="sidebar-bottom">

                    <!-- Help Card -->
                    <div class="help-card">

                        <span class="help-orbit">
                            ✦
                        </span>

                        <strong>
                            Everything in one place
                        </strong>

                        <p>
                            Your products, customers and store insights,
                            together in REGIX.
                        </p>

                        <a href="dashboard.php" class="text-button">
                            Explore overview
                            <span aria-hidden="true">↗</span>
                        </a>

                    </div>

                    <!-- Admin Profile -->
                    <a class="profile-mini" href="<?= e($profileHref) ?>" title="View my profile">

                        <span class="avatar">
                            <?= e(initials($name)) ?>
                        </span>

                        <span class="profile-copy">

                            <strong>
                                <?= e($name) ?>
                            </strong>

                            <small>
                                <?= e($roleLabel) ?>
                            </small>

                        </span>

                        <span
                            class="profile-status"
                            title="<?= $isLoggedIn ? 'Signed in' : 'Not signed in' ?>"
                            <?= !$isLoggedIn ? 'style="background: #64748b; box-shadow: none;"' : '' ?>
                        ></span>

                    </a>

                </div>

            </aside>

            <!-- Mobile Backdrop -->
            <label for="nav-toggle" class="mobile-backdrop" aria-label="Close navigation"></label>

            <!-- Main Content -->
            <div class="main-content">

                <main class="page-wrap" id="main-content">

                    <?php
}

function page_end(): void
{
    ?>

                </main>

                <!-- Footer -->
                <footer class="footer">

                    <span class="footer-brand">
                        REGIX<span class="accent">.</span>
                    </span>

                    <span>
                        Next level shopping · Admin dashboard
                    </span>

                    <span class="footer-right">
                        Built for better commerce
                    </span>

                </footer>

            </div>

        </div>

    </body>

    </html>

    <?php
}

function heading(
    string $eyebrow,
    string $title,
    string $description
): void {
    ?>

    <section class="page-heading">

        <!-- Mobile Menu -->
        <label for="nav-toggle" class="mobile-menu" aria-label="Open navigation">

            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M4 7h16M4 12h16M4 17h16" />
            </svg>

        </label>

        <!-- Heading Content -->
        <div class="heading-copy">

            <div class="eyebrow">

                <span class="eyebrow-line"></span>

                <?= e($eyebrow) ?>

            </div>

            <h1>
                <?= e($title) ?>
            </h1>

            <p>
                <?= e($description) ?>
            </p>

        </div>

    </section>

    <?php
}
