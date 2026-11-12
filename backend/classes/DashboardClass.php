<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Amman');

require_once __DIR__ . '/../config/db.php';


/*
|--------------------------------------------------------------------------
| DAL - DATA ACCESS LAYER
|--------------------------------------------------------------------------
| All SQL queries belong here.
*/

class DashboardDAL
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /*
    |--------------------------------------------------------------------------
    | KPI DATA
    |--------------------------------------------------------------------------
    */

    public function getKpis(
        string $startDate,
        string $endDate
    ): array {
        $sql = "
            SELECT
                COUNT(*) AS total_orders,
                COALESCE(SUM(total_jod), 0) AS total_sales
            FROM orders
            WHERE deleted_at IS NULL
              AND status <> 'cancelled'
              AND created_at >= :start_date
              AND created_at < :end_date
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);

        $orders = $stmt->fetch();

        $customerSql = "
            SELECT COUNT(*) AS total_customers
            FROM users
            WHERE deleted_at IS NULL
              AND role = 'customer'
              AND created_at >= :start_date
              AND created_at < :end_date
        ";

        $customerStmt = $this->db->prepare($customerSql);

        $customerStmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);

        $customers = $customerStmt->fetch();

        return [
            'orders' => (int) $orders['total_orders'],
            'sales' => (float) $orders['total_sales'],
            'customers' => (int) $customers['total_customers'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | DAILY SALES
    |--------------------------------------------------------------------------
    */

    public function getDailySales(
        string $startDate,
        string $endDate
    ): array {
        $sql = "
            SELECT
                DATE(created_at) AS sales_date,
                SUM(total_jod) AS total_sales
            FROM orders
            WHERE deleted_at IS NULL
              AND status <> 'cancelled'
              AND created_at >= :start_date
              AND created_at < :end_date
            GROUP BY DATE(created_at)
            ORDER BY sales_date ASC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);

        return $stmt->fetchAll();
    }

    /*
    |--------------------------------------------------------------------------
    | SALES BY CATEGORY
    |--------------------------------------------------------------------------
    */

    public function getSalesByCategory(
        string $startDate,
        string $endDate
    ): array {
        $sql = "
            SELECT
                c.id AS category_id,
                c.name AS category_name,
                COALESCE(
                    SUM(oi.quantity * oi.unit_price_jod),
                    0
                ) AS category_sales
            FROM order_items oi

            INNER JOIN orders o
                ON o.id = oi.order_id

            INNER JOIN products p
                ON p.id = oi.product_id

            INNER JOIN categories c
                ON c.id = p.category_id

            WHERE o.deleted_at IS NULL
              AND o.status <> 'cancelled'
              AND o.created_at >= :start_date
              AND o.created_at < :end_date

            GROUP BY c.id, c.name

            ORDER BY category_sales DESC

            LIMIT 6
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);

        return $stmt->fetchAll();
    }

    /*
    |--------------------------------------------------------------------------
    | TOP SELLING PRODUCTS
    |--------------------------------------------------------------------------
    */

    public function getTopProducts(
        string $startDate,
        string $endDate
    ): array {
        $sql = "
            SELECT
                p.id,
                p.name,
                p.brand,
                c.name AS category_name,

                SUM(oi.quantity) AS units_sold,

                SUM(
                    oi.quantity * oi.unit_price_jod
                ) AS revenue

            FROM order_items oi

            INNER JOIN orders o
                ON o.id = oi.order_id

            INNER JOIN products p
                ON p.id = oi.product_id

            INNER JOIN categories c
                ON c.id = p.category_id

            WHERE o.deleted_at IS NULL
              AND o.status <> 'cancelled'
              AND o.created_at >= :start_date
              AND o.created_at < :end_date

            GROUP BY
                p.id,
                p.name,
                p.brand,
                c.name

            ORDER BY units_sold DESC

            LIMIT 5
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);

        return $stmt->fetchAll();
    }

    /*
    |--------------------------------------------------------------------------
    | INVENTORY ALERTS
    |--------------------------------------------------------------------------
    */

    public function getInventoryAlerts(): array
    {
        $sql = "
            SELECT
                p.id,
                p.name,
                p.brand,
                p.quantity,
                p.condition_type,
                p.listing_status,
                c.name AS category_name

            FROM products p

            INNER JOIN categories c
                ON c.id = p.category_id

            WHERE p.deleted_at IS NULL
              AND p.listing_status = 'available'
              AND p.quantity <= 5

            ORDER BY
                p.quantity ASC,
                p.name ASC

            LIMIT 6
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute();

        return $stmt->fetchAll();
    }

    /*
    |--------------------------------------------------------------------------
    | RECENT ORDERS
    |--------------------------------------------------------------------------
    */

    public function getRecentOrders(
        string $startDate,
        string $endDate
    ): array {
        $sql = "
            SELECT
                o.id,
                o.status,
                o.total_jod,
                o.created_at,

                u.name AS customer_name,
                u.email AS customer_email,

                COUNT(oi.id) AS items_count

            FROM orders o

            LEFT JOIN users u
                ON u.id = o.user_id

            LEFT JOIN order_items oi
                ON oi.order_id = o.id

            WHERE o.deleted_at IS NULL
              AND o.created_at >= :start_date
              AND o.created_at < :end_date

            GROUP BY
                o.id,
                o.status,
                o.total_jod,
                o.created_at,
                u.name,
                u.email

            ORDER BY o.created_at DESC

            LIMIT 8
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);

        return $stmt->fetchAll();
    }
}

/*
|--------------------------------------------------------------------------
| BAL - BUSINESS ACCESS LAYER
|--------------------------------------------------------------------------
| Business calculations and dashboard preparation.
*/

class DashboardBAL
{
    private DashboardDAL $dal;

    private array $categoryColors = [
        '#a3e635',
        '#38bdf8',
        '#a78bfa',
        '#f59e0b',
        '#fb7185',
        '#2dd4bf',
    ];

    public function __construct(DashboardDAL $dal)
    {
        $this->dal = $dal;
    }

    /*
    |--------------------------------------------------------------------------
    | PERCENTAGE GROWTH
    |--------------------------------------------------------------------------
    */

    private function calculateGrowth(
        float|int $current,
        float|int $previous
    ): ?float {
        if ((float) $previous === 0.0) {
            return null;
        }

        return round(
            (($current - $previous) / $previous) * 100,
            1
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD SUMMARY
    |--------------------------------------------------------------------------
    */

    private function buildSummary(
        array $current,
        array $previous
    ): array {
        $currentAov = $current['orders'] > 0
            ? $current['sales'] / $current['orders']
            : 0;

        $previousAov = $previous['orders'] > 0
            ? $previous['sales'] / $previous['orders']
            : 0;

        return [
            'sales' => round($current['sales'], 2),

            'orders' => $current['orders'],

            'aov' => round($currentAov, 2),

            'customers' => $current['customers'],

            'sales_growth' => $this->calculateGrowth(
                $current['sales'],
                $previous['sales']
            ),

            'orders_growth' => $this->calculateGrowth(
                $current['orders'],
                $previous['orders']
            ),

            'aov_growth' => $this->calculateGrowth(
                $currentAov,
                $previousAov
            ),

            'customers_growth' => $this->calculateGrowth(
                $current['customers'],
                $previous['customers']
            ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD SALES CHART
    |--------------------------------------------------------------------------
    */

    private function buildSalesChart(
        DateTimeImmutable $currentStart,
        DateTimeImmutable $previousStart,
        int $period
    ): array {
        $currentEnd = $currentStart->modify(
            '+' . $period . ' days'
        );

        $previousEnd = $currentStart;

        $currentRows = $this->dal->getDailySales(
            $currentStart->format('Y-m-d H:i:s'),
            $currentEnd->format('Y-m-d H:i:s')
        );

        $previousRows = $this->dal->getDailySales(
            $previousStart->format('Y-m-d H:i:s'),
            $previousEnd->format('Y-m-d H:i:s')
        );

        $currentMap = [];
        $previousMap = [];

        foreach ($currentRows as $row) {
            $currentMap[$row['sales_date']] =
                (float) $row['total_sales'];
        }

        foreach ($previousRows as $row) {
            $previousMap[$row['sales_date']] =
                (float) $row['total_sales'];
        }

        $labels = [];
        $currentValues = [];
        $previousValues = [];

        for ($i = 0; $i < $period; $i++) {
            $currentDate = $currentStart->modify(
                '+' . $i . ' days'
            );

            $previousDate = $previousStart->modify(
                '+' . $i . ' days'
            );

            $currentKey = $currentDate->format('Y-m-d');
            $previousKey = $previousDate->format('Y-m-d');

            $labels[] = $period === 7
                ? $currentDate->format('D')
                : $currentDate->format('M j');

            $currentValues[] = round(
                $currentMap[$currentKey] ?? 0,
                2
            );

            $previousValues[] = round(
                $previousMap[$previousKey] ?? 0,
                2
            );
        }

        return [
            'labels' => $labels,
            'current' => $currentValues,
            'previous' => $previousValues,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD CATEGORY DATA
    |--------------------------------------------------------------------------
    */

    private function buildCategories(
        array $rows
    ): array {
        $total = array_sum(
            array_map(
                static fn(array $row): float =>
                    (float) $row['category_sales'],
                $rows
            )
        );

        $categories = [];

        foreach ($rows as $index => $row) {
            $amount = (float) $row['category_sales'];

            $categories[] = [
                'id' => (int) $row['category_id'],

                'name' => $row['category_name'],

                'amount' => round($amount, 2),

                'percentage' => $total > 0
                    ? round(($amount / $total) * 100, 1)
                    : 0,

                'color' => $this->categoryColors[
                    $index % count($this->categoryColors)
                ],
            ];
        }

        return $categories;
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD TOP PRODUCTS
    |--------------------------------------------------------------------------
    */

    private function buildProducts(array $rows): array
    {
        $products = [];

        $maxUnits = !empty($rows)
            ? max(array_map(
                static fn(array $row): int =>
                    (int) $row['units_sold'],
                $rows
            ))
            : 0;

        foreach ($rows as $row) {
            $units = (int) $row['units_sold'];

            $products[] = [
                'id' => (int) $row['id'],

                'name' => $row['name'],

                'brand' => $row['brand'],

                'category' => $row['category_name'],

                'units' => $units,

                'revenue' => round(
                    (float) $row['revenue'],
                    2
                ),

                'performance' => $maxUnits > 0
                    ? (int) round(
                        ($units / $maxUnits) * 100
                    )
                    : 0,
            ];
        }

        return $products;
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD INVENTORY
    |--------------------------------------------------------------------------
    */

    private function buildInventory(array $rows): array
    {
        $inventory = [];

        foreach ($rows as $row) {
            $quantity = (int) $row['quantity'];

            $inventory[] = [
                'id' => (int) $row['id'],

                'name' => $row['name'],

                'brand' => $row['brand'],

                'category' => $row['category_name'],

                'stock' => $quantity,

                'state' => $quantity <= 2
                    ? 'Critical'
                    : 'Low',
            ];
        }

        return $inventory;
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD RECENT ORDERS
    |--------------------------------------------------------------------------
    */

    private function buildOrders(array $rows): array
    {
        $orders = [];

        foreach ($rows as $row) {
            $date = new DateTimeImmutable(
                $row['created_at']
            );

            $orders[] = [
                'id' => (int) $row['id'],

                'customer' => $row['customer_name']
                    ?? 'Unknown customer',

                'email' => $row['customer_email']
                    ?? '',

                'date' => $date->format('M j, Y'),

                'time' => $date->format('h:i A'),

                'items' => (int) $row['items_count'],

                'amount' => (float) $row['total_jod'],

                'status' => $row['status'],

                'initials' => strtoupper(
                    implode(
                        '',
                        array_slice(
                            preg_split(
                                '/\s+/',
                                trim($row['customer_name'] ?? 'U')
                            ),
                            0,
                            2
                        )
                    )
                ),
            ];
        }

        return $orders;
    }

    /*
    |--------------------------------------------------------------------------
    | MAIN DASHBOARD METHOD
    |--------------------------------------------------------------------------
    */

    public function getDashboard(int $period): array
    {
        $timezone = new DateTimeZone('Asia/Amman');

        $today = new DateTimeImmutable(
            'today',
            $timezone
        );

        // Current period.
        $currentStart = $today->modify(
            '-' . ($period - 1) . ' days'
        );

        $currentEnd = $today->modify('+1 day');

        // Previous period of equal length.
        $previousStart = $currentStart->modify(
            '-' . $period . ' days'
        );

        $current = $this->dal->getKpis(
            $currentStart->format('Y-m-d H:i:s'),
            $currentEnd->format('Y-m-d H:i:s')
        );

        $previous = $this->dal->getKpis(
            $previousStart->format('Y-m-d H:i:s'),
            $currentStart->format('Y-m-d H:i:s')
        );

        $summary = $this->buildSummary(
            $current,
            $previous
        );

        $chart = $this->buildSalesChart(
            $currentStart,
            $previousStart,
            $period
        );

        $categoryRows = $this->dal->getSalesByCategory(
            $currentStart->format('Y-m-d H:i:s'),
            $currentEnd->format('Y-m-d H:i:s')
        );

        $productRows = $this->dal->getTopProducts(
            $currentStart->format('Y-m-d H:i:s'),
            $currentEnd->format('Y-m-d H:i:s')
        );

        $inventoryRows = $this->dal->getInventoryAlerts();

        $orderRows = $this->dal->getRecentOrders(
            $currentStart->format('Y-m-d H:i:s'),
            $currentEnd->format('Y-m-d H:i:s')
        );

        $categories = $this->buildCategories(
            $categoryRows
        );

        $products = $this->buildProducts(
            $productRows
        );

        $inventory = $this->buildInventory(
            $inventoryRows
        );

        $orders = $this->buildOrders(
            $orderRows
        );

        return [
            'period' => $period,

            'generated_at' => date('Y-m-d H:i:s'),

            'summary' => $summary,

            'chart' => $chart,

            'categories' => $categories,

            'category_total' => round(
                array_sum(
                    array_column($categories, 'amount')
                ),
                2
            ),

            'products' => $products,

            'inventory' => $inventory,

            'inventory_count' => count($inventory),

            'orders' => $orders,
        ];
    }
}