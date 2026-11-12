<?php
require_once __DIR__ . '/../config/db.php';
class Categorie
{
    private PDO $db;
    private string $imageFolder;

    public function __construct()
    {
        $this->db = DB_conn::getConnection();

        $this->imageFolder = __DIR__
            . '/../../frontend/assets/category/';
    }


    public function getAll(string $search = ''): array
    {
        $sql = "SELECT c.*,
                    (
                        SELECT COUNT(*)
                        FROM products p
                        WHERE p.category_id = c.id
                          AND p.deleted_at IS NULL
                    ) AS products_count
                FROM categories c
                WHERE c.deleted_at IS NULL
                  AND c.name LIKE ?
                ORDER BY c.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['%' . $search . '%']);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT *
             FROM categories
             WHERE id = ? AND deleted_at IS NULL
             LIMIT 1"
        );

        $stmt->execute([$id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        return $category ?: null;
    }


    public function add(
        string $name,
        string $description,
        string $image
    ): void {
        $stmt = $this->db->prepare(
            "INSERT INTO categories (name, description, category_img)
             VALUES (?, ?, ?)"
        );

        $stmt->execute([$name, $description, $image]);
    }

    public function update(
        int $id,
        string $name,
        string $description,
        string $image = ''
    ): void {
        $current = $this->getById($id);

        if (!$current) {
            throw new RuntimeException('Category not found.');
        }

        if ($image === '') {
            $stmt = $this->db->prepare(
                "UPDATE categories
                 SET name = ?, description = ?
                 WHERE id = ? AND deleted_at IS NULL"
            );

            $stmt->execute([$name, $description, $id]);
            return;
        }

        $stmt = $this->db->prepare(
            "UPDATE categories
             SET name = ?, description = ?, category_img = ?
             WHERE id = ? AND deleted_at IS NULL"
        );

        $stmt->execute([$name, $description, $image, $id]);

        $oldImage = $current['category_img'] ?? '';

        if ($oldImage && $oldImage !== $image) {
            $this->deleteImage($oldImage);
        }
    }

    public function delete(int $id): void
    {
        if (!$this->getById($id)) {
            throw new RuntimeException('Category not found.');
        }

        if ($this->hasProducts($id)) {
            throw new RuntimeException(
                'This category cannot be deleted because it still has active products.'
            );
        }

        $stmt = $this->db->prepare(
            "UPDATE categories
             SET deleted_at = NOW()
             WHERE id = ? AND deleted_at IS NULL"
        );

        $stmt->execute([$id]);
    }

    public function hasProducts(int $id): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM products
             WHERE category_id = ?
               AND deleted_at IS NULL"
        );

        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function checkImage(array $file): string
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            return 'Invalid image upload.';
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return match ($file['error']) {
                UPLOAD_ERR_NO_FILE => 'Please select an image.',
                UPLOAD_ERR_INI_SIZE,
                UPLOAD_ERR_FORM_SIZE => 'Image must be 2 MB or less.',
                default => 'Image upload failed. Please try again.',
            };
        }

        if (
            empty($file['tmp_name']) ||
            !is_uploaded_file($file['tmp_name'])
        ) {
            return 'Invalid uploaded file.';
        }

        if ((int) $file['size'] > 2 * 1024 * 1024) {
            return 'Image must be 2 MB or less.';
        }

        $extension = strtolower(
            pathinfo($file['name'] ?? '', PATHINFO_EXTENSION)
        );

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($extension, $allowedExtensions, true)) {
            return 'Image must be JPG, PNG or WEBP.';
        }

        $imageInfo = @getimagesize($file['tmp_name']);

        if ($imageInfo === false) {
            return 'This file is not a valid image.';
        }

        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];

        if (!in_array($imageInfo['mime'] ?? '', $allowedMimeTypes, true)) {
            return 'Only JPG, PNG or WEBP images are allowed.';
        }

        return '';
    }

    public function uploadImage(array $file): string
    {
        $error = $this->checkImage($file);

        if ($error !== '') {
            throw new RuntimeException($error);
        }

        if (
            !is_dir($this->imageFolder) &&
            !mkdir($this->imageFolder, 0755, true) &&
            !is_dir($this->imageFolder)
        ) {
            throw new RuntimeException(
                'Could not create the category image directory.'
            );
        }

        $extension = strtolower(
            pathinfo($file['name'], PATHINFO_EXTENSION)
        );

        $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $this->imageFolder . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException(
                'Could not save the uploaded image.'
            );
        }

        return $fileName;
    }


    public function deleteImage(?string $fileName): void
    {
        if (!$fileName) {
            return;
        }

        $fileName = basename($fileName);
        $path = $this->imageFolder . $fileName;

        if (is_file($path)) {
            @unlink($path);
        }
    }
}