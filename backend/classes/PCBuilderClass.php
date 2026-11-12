<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/CompatibilityClass.php';
require_once __DIR__ . '/PerformanceRankingClass.php';
require_once __DIR__ . '/BuildOptimizerClass.php';

/*
|--------------------------------------------------------------------------
| DAL - DATA ACCESS LAYER
|--------------------------------------------------------------------------
| All PC component SQL queries belong here.
*/

class PCBuilderDAL
{
    private PDO $db;

    private const COMPONENT_TYPES = [
        'cpu',
        'motherboard',
        'ram',
        'gpu',
        'storage',
        'psu',
        'case',
        'cooler',
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /*
    |--------------------------------------------------------------------------
    | SHARED COMPONENT QUERY
    |--------------------------------------------------------------------------
    */

    private function baseSelectQuery(): string
    {
        return "
            SELECT
                p.id AS product_id,
                p.name,
                p.brand,
                p.description,
                p.price,
                p.quantity,
                p.condition_type,
                p.listing_status,
                p.product_img,
                p.product_variant,
                p.deleted_at,

                c.name AS category_name,

                s.component_type,
                s.specifications,
                s.spec_status,
                s.source_url,
                s.verified_at,
                s.notes

            FROM products p

            INNER JOIN pc_component_specs s
                ON s.product_id = p.id

            LEFT JOIN categories c
                ON c.id = p.category_id
                AND c.deleted_at IS NULL
        ";
    }

    /*
    |--------------------------------------------------------------------------
    | GET AVAILABLE COMPONENTS
    |--------------------------------------------------------------------------
    */

    public function getAvailableComponents(
        ?string $componentType = null
    ): array {

        $sql = $this->baseSelectQuery() . "
            WHERE p.listing_status = 'available'
              AND p.quantity > 0
              AND p.deleted_at IS NULL
        ";

        $parameters = [];

        if ($componentType !== null) {

            $this->validateComponentType($componentType);

            $sql .= "
                AND s.component_type = :component_type
            ";

            $parameters['component_type'] = $componentType;
        }

        $sql .= "
            ORDER BY s.component_type, p.price ASC
        ";

        $statement = $this->db->prepare($sql);

        $statement->execute($parameters);

        return $this->normalizeRows(
            $statement->fetchAll()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GET COMPONENT CATALOG
    |--------------------------------------------------------------------------
    */

    public function getAvailableCatalog(): array
    {
        $catalog = array_fill_keys(
            self::COMPONENT_TYPES,
            []
        );

        $components = $this->getAvailableComponents();

        foreach ($components as $component) {

            $type = $component['component_type'];

            $catalog[$type][] = $component;
        }

        return $catalog;
    }

    /*
    |--------------------------------------------------------------------------
    | FIND COMPONENT BY ID
    |--------------------------------------------------------------------------
    */

    public function findByProductId(int $productId): ?array
    {
        if ($productId <= 0) {
            return null;
        }

        $sql = $this->baseSelectQuery() . "
            WHERE p.id = :product_id
  AND p.listing_status = 'available'
  AND p.quantity > 0
  AND p.deleted_at IS NULL
LIMIT 1
        ";

        $statement = $this->db->prepare($sql);

        $statement->execute([
            'product_id' => $productId
        ]);

        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /*
    |--------------------------------------------------------------------------
    | FIND MULTIPLE COMPONENTS
    |--------------------------------------------------------------------------
    */

    public function findManyByProductIds(array $productIds): array
    {
        $ids = [];

        foreach ($productIds as $id) {

            $validatedId = filter_var(
                $id,
                FILTER_VALIDATE_INT
            );

            if (
                $validatedId !== false &&
                $validatedId !== null &&
                $validatedId > 0
            ) {
                $ids[] = (int) $validatedId;
            }
        }

        $ids = array_values(array_unique($ids));

        if (empty($ids)) {
            return [];
        }

        $placeholders = [];
        $parameters = [];

        foreach ($ids as $index => $id) {

            $placeholder = ':product_' . $index;

            $placeholders[] = $placeholder;

            $parameters[$placeholder] = $id;
        }

        $inClause = implode(', ', $placeholders);

        $sql = $this->baseSelectQuery() . "
            WHERE p.id IN ($inClause)
  AND p.listing_status = 'available'
  AND p.quantity > 0
  AND p.deleted_at IS NULL
        ";

        $statement = $this->db->prepare($sql);

        $statement->execute($parameters);

        $rows = $statement->fetchAll();

        $components = $this->normalizeRows($rows);

        $result = [];

        foreach ($components as $component) {

            $result[$component['product_id']] = $component;
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE DATABASE DATA
    |--------------------------------------------------------------------------
    */

    private function normalizeRows(array $rows): array
    {
        $components = [];

        foreach ($rows as $row) {

            $components[] = $this->normalizeRow($row);
        }

        return $components;
    }

    private function normalizeRow(array $row): array
    {
        $specifications = [];
        $specDataValid = true;

        try {

            $decoded = json_decode(
                (string) $row['specifications'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (is_array($decoded)) {
                $specifications = $decoded;
            } else {
                $specDataValid = false;
            }

        } catch (JsonException $exception) {

            $specDataValid = false;
        }

        $quantity = (int) $row['quantity'];

        return [
            'product_id' => (int) $row['product_id'],

            'name' => (string) $row['name'],

            'brand' => $row['brand'],

            'description' => (string) $row['description'],

            'price' => (float) $row['price'],

            'quantity' => $quantity,

            'condition_type' => (string) $row['condition_type'],

            'listing_status' => (string) $row['listing_status'],

            'product_img' => $row['product_img'],

            'product_variant' => $row['product_variant'],

            'category_name' => $row['category_name'],

            'component_type' => (string) $row['component_type'],

            'specifications' => $specifications,

            'spec_status' => (string) $row['spec_status'],

            'spec_data_valid' => $specDataValid,

            'source_url' => $row['source_url'],

            'verified_at' => $row['verified_at'],

            'notes' => $row['notes'],

            'is_available' => (
                $row['listing_status'] === 'available' &&
                $quantity > 0 &&
                $row['deleted_at'] === null
            ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE COMPONENT TYPE
    |--------------------------------------------------------------------------
    */

    private function validateComponentType(string $componentType): void
    {
        if (
            !in_array(
                $componentType,
                self::COMPONENT_TYPES,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Unsupported component type.'
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| BAL - BUSINESS ACCESS LAYER
|--------------------------------------------------------------------------
| Coordinates PC Builder operations.
*/

class PCBuilderBAL
{
    private PCBuilderDAL $dal;
    private CompatibilityClass $compatibility;
    private BuildOptimizerClass $optimizer;

    public function __construct(
        PCBuilderDAL $dal,
        CompatibilityClass $compatibility,
        BuildOptimizerClass $optimizer
    ) {
        $this->dal = $dal;
        $this->compatibility = $compatibility;
        $this->optimizer = $optimizer;
    }

    /*
    |--------------------------------------------------------------------------
    | GET AVAILABLE COMPONENTS
    |--------------------------------------------------------------------------
    */

    public function getAvailableComponents(
        ?string $componentType = null
    ): array {

        return $this->dal->getAvailableComponents(
            $componentType
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GET CATALOG
    |--------------------------------------------------------------------------
    */

    public function getCatalog(): array
    {
        return $this->dal->getAvailableCatalog();
    }

    /*
    |--------------------------------------------------------------------------
    | GET COMPONENT BY ID
    |--------------------------------------------------------------------------
    */

    public function getComponent(int $productId): ?array
    {
        return $this->dal->findByProductId($productId);
    }

    /*
    |--------------------------------------------------------------------------
    | GET MULTIPLE COMPONENTS
    |--------------------------------------------------------------------------
    */

    public function getComponents(array $productIds): array
    {
        return $this->dal->findManyByProductIds(
            $productIds
        );
    }

    public function checkCompatibility(array $components): array
    {
        return $this->compatibility->validateBuild($components);
    }

    /*
|--------------------------------------------------------------------------
| GENERATE OPTIMIZED BUILD
|--------------------------------------------------------------------------
*/

    public function generateBuild(
        float $budget,
        string $purpose,
        int $limit = 3,
        string $resolution = '1440p'
    ): array {

        $catalog = $this->dal->getAvailableCatalog();

        return $this->optimizer->generate(
            $catalog,
            $budget,
            $purpose,
            $limit,
            $resolution
        );
    }
}