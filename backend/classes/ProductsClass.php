<?php
declare(strict_types=1);

require_once __DIR__ . '/../../backend/config/db.php';

class Product {

    private PDO $pdo;

    public function __construct() {

        $this->pdo = DB_conn::getConnection();
    }

    public function getAllProducts(bool $deleted_at = true) : array|false{

        if ($deleted_at) {
            $sql = "SELECT * FROM products WHERE deleted_at IS NULL";
        } else {
            $sql = "SELECT * FROM products";
        }

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute();

        $results = $stmt->fetchAll();

        return empty($results) ? false : $results;
    }

    public function getAllProductsWithCategory(bool $deleted_at = true) : array {

        if ($deleted_at) {
            $sql = "SELECT p.*, c.name AS category_name
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.deleted_at IS NULL
                ORDER BY p.id DESC";
        } else {
            $sql = "SELECT p.*, c.name AS category_name
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                ORDER BY p.id DESC";
        }

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getProduct(int $id, bool $includeDeleted = false) : array|false {

        if ($includeDeleted) {
            $sql = "SELECT * FROM products WHERE id = ?";
        } else {
            $sql = "SELECT * FROM products WHERE deleted_at IS NULL AND id = ?";
        }
        
        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([$id]);

        $results = $stmt->fetch();

        return empty($results) ? false : $results;
        
    }

    public function createProduct(array $data) : bool {

        $sql = "INSERT INTO products (
                seller_id,
                category_id,
                name,
                brand,
                description,
                price,
                quantity,
                condition_type,
                listing_status,
                product_img,
                product_variant
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            $data['seller_id'],
            $data['category_id'],
            $data['name'],
            $data['brand'],
            $data['description'],
            $data['price'],
            $data['quantity'],
            $data['condition_type'],
            $data['listing_status'] ?? 'available',
            $data['product_img'] ?? null,
            $data['product_variant'] ?? null
        ]);
    }

    public function updateProduct(int $id, array $data) : bool {
        
        $sql = "UPDATE products
            SET category_id = ?,
                name = ?,
                brand = ?,
                description = ?,
                price = ?,
                quantity = ?,
                condition_type = ?,
                listing_status = ?,
                product_img = ?,
                product_variant = ?
            WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            $data['category_id'],
            $data['name'],
            $data['brand'],
            $data['description'],
            $data['price'],
            $data['quantity'],
            $data['condition_type'],
            $data['listing_status'] ?? 'available',
            $data['product_img'] ?? null,
            $data['product_variant'] ?? null,
            $id
        ]);
    }

    public function deleteProduct(int $id) : bool {

        $sql = "UPDATE products
            SET deleted_at = CURRENT_TIMESTAMP
            WHERE id = ?
              AND deleted_at IS NULL";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    public function reactivateProduct(int $id) : bool {
        
        $sql = "UPDATE products
            SET deleted_at = NULL
            WHERE id = ? AND deleted_at IS NOT NULL";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([$id]) && $stmt->rowCount() > 0;

    }   
}
?>