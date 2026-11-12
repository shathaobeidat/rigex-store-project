<?php
declare(strict_types=1);

require_once __DIR__ . '/guard.php';

date_default_timezone_set('Asia/Amman');

/*
|--------------------------------------------------------------------------
| DASHBOARD API
|--------------------------------------------------------------------------
| This endpoint is handled by the same index.php file.
| JSON is returned before loading the HTML layout.
*/

if (($_GET['action'] ?? '') === 'dashboard') {

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    try {

        require_once __DIR__ . '/../../backend/classes/ModelFactory.php';

        $requestedPeriod = filter_input(
            INPUT_GET,
            'period',
            FILTER_VALIDATE_INT
        );

        $allowedPeriods = [7, 30, 90];

        $period = (
            $requestedPeriod !== false
            && $requestedPeriod !== null
            && in_array($requestedPeriod, $allowedPeriods, true)
        ) ? $requestedPeriod : 30;

        // Create Dashboard Business Layer.
        $dashboard = ModelFactory::dashboard();

        // Retrieve dashboard data.
        $data = $dashboard->getDashboard($period);

        echo json_encode(
            [
                'success' => true,
                'data' => $data
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

    } catch (Throwable $e) {

        // Save detailed error in PHP error log.
        error_log(
            'REGIX Dashboard Error: ' . $e->getMessage()
        );

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Unable to load dashboard data.'
        ], JSON_UNESCAPED_UNICODE);
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| DASHBOARD PAGE
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../layout.php';

/*
|--------------------------------------------------------------------------
| LOAD SHARED LAYOUT
|--------------------------------------------------------------------------
| Fix relative asset paths without modifying layout.php.
*/

ob_start();

page_start('Overview', 'dashboard.php');

$layoutOutput = ob_get_clean();

/*
|--------------------------------------------------------------------------
| FIX SHARED ASSET PATHS
|--------------------------------------------------------------------------
*/

$layoutOutput = str_replace(
    [
        'href="assets/favicon.svg"',
        'href="css/admin.css"',
        'src="js/admin.js"',
        'src="assets/regix-logo.svg"',
        'src="assets/regix-mark.svg"'
    ],
    [
        'href="../assets/favicon.svg"',
        'href="../css/admin.css"',
        'src="../js/admin.js"',
        'src="../assets/regix-logo.svg"',
        'src="../assets/regix-mark.svg"'
    ],
    $layoutOutput
);

/*
|--------------------------------------------------------------------------
| LOAD DASHBOARD STYLESHEET
|--------------------------------------------------------------------------
| Dashboard.css is loaded after admin.css so page-specific
| styles can override shared styles when necessary.
*/

$layoutOutput = str_replace(
    '<link rel="stylesheet" href="../css/admin.css">',
    '<link rel="stylesheet" href="../css/admin.css">' . PHP_EOL .
    '        <link rel="stylesheet" href="../css/Dashboard.css?v=' . filemtime(__DIR__ . '/../css/Dashboard.css') . '">',
    $layoutOutput
);

echo $layoutOutput;

/*
|--------------------------------------------------------------------------
| PAGE HEADER
|--------------------------------------------------------------------------
*/

heading(
    'STORE OVERVIEW',
    'Dashboard',
    "Here's what's happening with your store today."
);

?>


<!-- ============================================================
     DASHBOARD SCRIPTS
============================================================= -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer>
</script>

<script src="../js/Dashboard.js" defer>
</script>


<!-- ============================================================
     REPORTING TOOLBAR
============================================================= -->

<section class="toolbar">

    <span class="demo-label">
        Store performance and business insights
    </span>

    <form class="period-form" id="periodForm" method="GET">

        <label for="periodSelect">
            Reporting period
        </label>

        <select class="period-picker" name="period" id="periodSelect">

            <option value="7">Last 7 days</option>

            <option value="30" selected>Last 30 days</option>

            <option value="90">Last 90 days</option>

        </select>

        <button class="primary-button" type="submit">

            Apply period

            <span>→</span>

        </button>

        <button class="secondary-button" id="refreshButton" type="button" title="Refresh dashboard">

            ↻ Refresh

        </button>

    </form>

</section>


<!-- ============================================================
     ERROR MESSAGE
============================================================= -->

<div class="error-banner" id="errorBanner" role="alert">

    <span id="errorMessage">
        Unable to load dashboard data.
    </span>

    <button type="button" id="retryButton">

        Retry

    </button>

</div>


<!-- ============================================================
     LOADING STATE
============================================================= -->

<div class="loading-overlay" id="loadingState">

    <span class="spinner"></span>

    <span>
        Loading store analytics...
    </span>

</div>


<!-- ============================================================
     DASHBOARD CONTENT
============================================================= -->

<div id="dashboardContent">


    <!-- ========================================================
         KPI CARDS
    ========================================================= -->

    <section class="kpi-grid" aria-label="Key performance indicators">


        <!-- Net Sales -->

        <article class="kpi-card">

            <div class="kpi-top">

                <span class="kpi-label">
                    Net Sales
                </span>

                <span class="kpi-icon green">
                    ＄
                </span>

            </div>

            <div class="kpi-value" id="salesKpi">

                JD 0.00

            </div>

            <div class="kpi-bottom">

                <span class="trend neutral" id="salesTrend">

                    —

                </span>

                <span>
                    vs. previous period
                </span>

            </div>

        </article>


        <!-- Total Orders -->

        <article class="kpi-card">

            <div class="kpi-top">

                <span class="kpi-label">
                    Total Orders
                </span>

                <span class="kpi-icon purple">
                    ▣
                </span>

            </div>

            <div class="kpi-value" id="ordersKpi">

                0

            </div>

            <div class="kpi-bottom">

                <span class="trend neutral" id="ordersTrend">

                    —

                </span>

                <span>
                    vs. previous period
                </span>

            </div>

        </article>


        <!-- Average Order Value -->

        <article class="kpi-card">

            <div class="kpi-top">

                <span class="kpi-label">
                    Average Order Value
                </span>

                <span class="kpi-icon blue">
                    ↗
                </span>

            </div>

            <div class="kpi-value" id="aovKpi">

                JD 0.00

            </div>

            <div class="kpi-bottom">

                <span class="trend neutral" id="aovTrend">

                    —

                </span>

                <span>
                    vs. previous period
                </span>

            </div>

        </article>


        <!-- New Customers -->

        <article class="kpi-card">

            <div class="kpi-top">

                <span class="kpi-label">
                    New Customers
                </span>

                <span class="kpi-icon amber">
                    ♙
                </span>

            </div>

            <div class="kpi-value" id="customersKpi">

                0

            </div>

            <div class="kpi-bottom">

                <span class="trend neutral" id="customersTrend">

                    —

                </span>

                <span>
                    vs. previous period
                </span>

            </div>

        </article>

    </section>


    <!-- ========================================================
         SALES PERFORMANCE & CATEGORIES
    ========================================================= -->

    <section class="primary-grid">


        <!-- Sales Performance -->

        <article class="panel sales-panel" id="sales">

            <div class="panel-heading">

                <div>

                    <h2>
                        Sales Performance
                    </h2>

                    <p>
                        Track your revenue over time
                    </p>

                </div>

                <span class="live-tag">
                    LIVE DATA
                </span>

            </div>


            <div class="chart-summary">

                <strong id="chartSalesTotal">
                    JD 0.00
                </strong>

                <span>
                    total net sales
                </span>

                <span class="summary-trend" id="chartSummaryTrend">

                    —

                </span>

            </div>


            <div class="chart-container">

                <canvas id="salesChart" aria-label="Sales performance chart" role="img">
                </canvas>

            </div>


            <div class="chart-footnote">

                <span>

                    <span class="footnote-dot"></span>

                    Revenue from recorded orders

                </span>

                <span id="salesUpdated">
                    Updated when loaded
                </span>

            </div>

        </article>


        <!-- Sales by Category -->

        <article class="panel categories-panel" id="categories">

            <div class="panel-heading">

                <div>

                    <h2>
                        Sales by Category
                    </h2>

                    <p>
                        Revenue distribution across categories
                    </p>

                </div>

            </div>


            <div class="donut-area">

                <div class="donut-chart-wrap">

                    <canvas id="categoryChart" aria-label="Sales by category" role="img">
                    </canvas>

                    <div class="donut-center">

                        <span>
                            SALES
                        </span>

                        <strong id="categoryTotal">
                            0
                        </strong>

                        <small>
                            JOD
                        </small>

                    </div>

                </div>

                <p class="category-empty" id="categoryEmpty" hidden>

                    No category sales found.

                </p>

            </div>


            <div class="category-legend" id="categoryLegend">

                <!-- Populated from Dashboard API -->

            </div>


            <div class="category-footer">

                <span class="category-insight-icon">
                    ✦
                </span>

                <span class="category-insight-copy">

                    <strong id="topCategoryText">
                        No category data
                    </strong>

                    <small>
                        Top revenue category this period
                    </small>

                </span>

                <span class="category-share" id="topCategoryShare">

                    0%

                </span>

            </div>

        </article>

    </section>


    <!-- ========================================================
         PRODUCTS & INVENTORY
    ========================================================= -->

    <section class="secondary-grid">


        <!-- Top Selling Products -->

        <article class="panel products-panel" id="products">

            <div class="panel-heading">

                <div>

                    <h2>
                        Top Selling Products
                    </h2>

                    <p>
                        Best performers by units sold
                    </p>

                </div>

                <span class="live-tag">
                    TOP 5
                </span>

            </div>


            <div class="table-wrap">

                <table class="product-table">

                    <thead>

                        <tr>

                            <th>PRODUCT</th>

                            <th>REVENUE</th>

                            <th>UNITS SOLD</th>

                            <th>PERFORMANCE</th>

                        </tr>

                    </thead>


                    <tbody id="topProductsBody">

                        <tr>
                            <td colspan="4">
                                Loading products...
                            </td>
                        </tr>

                    </tbody>

                </table>

            </div>


            <div class="panel-bottom">

                <span id="productsCount">
                    Top selling products
                </span>

                <span class="bottom-status">

                    <span></span>

                    Based on order items

                </span>

            </div>

        </article>


        <!-- Inventory Alerts -->

        <article class="panel inventory-panel" id="inventory">

            <div class="panel-heading">

                <div>

                    <h2>
                        Inventory Alerts
                    </h2>

                    <p>
                        Products that need your attention
                    </p>

                </div>

                <span class="alert-count" id="alertCount">

                    0

                </span>

            </div>


            <div class="inventory-list" id="inventoryList">

                <div class="empty-state">
                    Loading inventory...
                </div>

            </div>


            <div class="inventory-footer">

                <span>ⓘ</span>

                <span>
                    Low stock threshold: 5 units.
                    Critical stock: 2 units or less.
                </span>

            </div>

        </article>

    </section>


    <!-- ========================================================
         RECENT ORDERS
    ========================================================= -->

    <section class="panel orders-panel" id="orders">


        <!-- Orders Header -->

        <div class="panel-heading orders-heading">

            <div>

                <h2>
                    Recent Orders
                </h2>

                <p>
                    Stay on top of the latest transactions
                </p>

            </div>


            <!-- Orders Filters -->

            <div class="orders-toolbar">


                <!-- Search -->

                <label class="search-input">

                    <span>⌕</span>

                    <input type="search" id="orderSearch" placeholder="Search orders or customers..."
                        aria-label="Search orders or customers">

                </label>


                <!-- Status Filter -->

                <select class="status-select" id="statusFilter" aria-label="Filter order status">

                    <option value="all">
                        All statuses
                    </option>

                    <option value="pending">
                        Pending
                    </option>

                    <option value="confirmed">
                        Confirmed
                    </option>

                    <option value="shipped">
                        Shipped
                    </option>

                    <option value="delivered">
                        Delivered
                    </option>

                    <option value="cancelled">
                        Cancelled
                    </option>

                </select>


                <!-- Reset -->

                <button class="secondary-button" id="clearFilters" type="button">

                    Reset

                </button>

            </div>

        </div>


        <!-- Orders Table -->

        <div class="table-wrap">

            <table class="orders-table">

                <thead>

                    <tr>

                        <th>ORDER ID</th>

                        <th>CUSTOMER</th>

                        <th>DATE</th>

                        <th>PRODUCTS</th>

                        <th>AMOUNT</th>

                        <th>STATUS</th>

                    </tr>

                </thead>


                <tbody id="ordersBody">

                    <tr>
                        <td colspan="6">
                            Loading orders...
                        </td>
                    </tr>

                </tbody>

            </table>


            <!-- Empty State -->

            <div class="empty-state" id="ordersEmpty" hidden>

                No orders match your search.

            </div>

        </div>


        <!-- Orders Footer -->

        <div class="orders-table-footer">

            <span id="ordersCountLabel">
                Loading orders...
            </span>

            <span id="ordersLastUpdated">
                Latest available orders
            </span>

        </div>

    </section>

</div>


<?php

// Close the shared layout.
page_end();

?>
