<?php
declare(strict_types=1);

require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/classes/CategorieClass.php';
require_once __DIR__ . '/../../backend/classes/ProductsClass.php';
require_once __DIR__ . '/../layout.php';

$productModel = new Product();
$categoryModel = new Categorie();
$categories = $categoryModel->getAll();
$categoryIds = array_map('intval', array_column($categories, 'id'));

$errorMessages = [];
$successMessage = "";
$data = [];

function getFormData () : array {
    $currentImage = trim($_POST['product_img'] ?? '');

    return [
            'category_id' => $_POST['category_id'] ?? '',
            'name' => trim($_POST['name'] ?? ''),
            'brand' => trim($_POST['brand'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'price' => $_POST['price'] ?? '',
            'quantity' => $_POST['quantity'] ?? '',
            'condition_type' => $_POST['condition_type'] ?? '',
            'listing_status' => $_POST['listing_status'] ?? 'available',
            'product_img' => preg_match('#^assets/products/[A-Za-z0-9._-]+$#', $currentImage) ? $currentImage : null,
            'product_variant' => !empty($_POST['product_variant']) ? trim($_POST['product_variant']) : null
        ];
}

function validateFormData (array $data, array $categoryIds) : array {
    $errors = [];

    if (!ctype_digit((string) $data['category_id']) || !in_array((int) $data['category_id'], $categoryIds, true)) {
        $errors['category_id'] = "Please choose a valid category.";
    }
    if ($data['name'] === '') {
        $errors['name'] = "Product name is required.";
    }
    if ($data['brand'] === '') {
        $errors['brand'] = "Brand is required.";
    }
    if ($data['description'] === '') {
        $errors['description'] = "Description is required.";
    }
    if (!is_numeric($data['price']) || (float) $data['price'] < 0) {
        $errors['price'] = "Price must be a valid positive number.";
    }
    if (!ctype_digit((string) $data['quantity'])) {
        $errors['quantity'] = "Quantity must be a whole number.";
    }
    if (!in_array($data['condition_type'], ['new', 'used'], true)) {
        $errors['condition_type'] = "Condition is invalid.";
    }
    if (!in_array($data['listing_status'], ['available', 'sold', 'hidden', 'reserved'], true)) {
        $errors['listing_status'] = "Listing status is invalid.";
    }

    return $errors;
}

function uploadProductImage () : array {

    if (!isset($_FILES['product_img_file']) || $_FILES['product_img_file']['error'] === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => null];
    }

    $file = $_FILES['product_img_file'];

    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['path' => null, 'error' => "Image must be 2 MB or smaller."];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => "Image upload failed."];
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        return ['path' => null, 'error' => "Image must be 2 MB or smaller."];
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $info = @getimagesize($file['tmp_name']);
    $mime = $info['mime'] ?? '';

    if (!isset($allowed[$mime])) {
        return ['path' => null, 'error' => "Image must be a JPG, PNG, WEBP or GIF file."];
    }

    $directory = __DIR__ . '/../assets/products/';

    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        return ['path' => null, 'error' => "Image folder could not be created."];
    }

    $fileName = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];

    if (!move_uploaded_file($file['tmp_name'], $directory . $fileName)) {
        return ['path' => null, 'error' => "Image could not be saved."];
    }

    return ['path' => 'assets/products/' . $fileName, 'error' => null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['add_product'])) {

        $data = getFormData();
        $errorMessages = validateFormData($data, $categoryIds);

        if (empty($errorMessages)) {
            $upload = uploadProductImage();

            if ($upload['error'] !== null) {
                $errorMessages['product_img'] = $upload['error'];
            } elseif ($upload['path'] !== null) {
                $data['product_img'] = $upload['path'];
            }
        }

        if (empty($errorMessages)) {
            try {
                $data['seller_id'] = (int) $_SESSION['user_id'];
                if ($productModel->createProduct($data)) {
                    $successMessage = "Product has been created successfully.";
                    $data = [];
                } else {
                    $errorMessages['createProduct'] = "Product has not been added.";
                }
            } catch (Throwable $e) {
                $errorMessages['createProduct'] = "Something went wrong while creating the product.";
            }
        }
    }

    elseif (isset($_POST['update_product'])) {

        $data = getFormData();
        $data['id'] = (int) ($_POST['productId'] ?? 0);
        $errorMessages = validateFormData($data, $categoryIds);

        if (empty($errorMessages)) {
            $upload = uploadProductImage();

            if ($upload['error'] !== null) {
                $errorMessages['product_img'] = $upload['error'];
            } elseif ($upload['path'] !== null) {
                $data['product_img'] = $upload['path'];
            }
        }

        if (empty($errorMessages)) {
            try {
                if ($productModel->updateProduct($data['id'], $data)) {
                    $successMessage = "Product has been updated successfully.";
                    $data = [];
                } else {
                    $errorMessages['updateProduct'] = "Product has not been updated.";
                }
            } catch (Throwable $e) {
                $errorMessages['updateProduct'] = "Something went wrong while updating the product.";
            }
        }
    }

    elseif (isset($_POST['updateAction'])) {

        try {
            $data = $productModel->getProduct((int) ($_POST['productId'] ?? 0), true) ?: [];
        } catch (Throwable $e) {
            $errorMessages['getProduct'] = "Something went wrong while loading the product.";
        }

        if (empty($data) && empty($errorMessages)) {
            $errorMessages['getProduct'] = "Product was not found.";
        }
    }

    elseif (isset($_POST['action'])) {

        $productId = (int) ($_POST['productId'] ?? 0);

        if ($_POST['action'] === 'delete') {
            try {
                if ($productModel->deleteProduct($productId)) {
                    $successMessage = "Product has been deleted successfully.";
                } else {
                    $errorMessages['deleteProduct'] = "Product has not been deleted.";
                }
            } catch (Throwable $e) {
                $errorMessages['deleteProduct'] = "Something went wrong while deleting the product.";
            }
        }

        elseif ($_POST['action'] === 'reactivate') {
            try {
                if ($productModel->reactivateProduct($productId)) {
                    $successMessage = "Product has been reactivated successfully.";
                } else {
                    $errorMessages['reactivateProduct'] = "Product has not been reactivated.";
                }
            } catch (Throwable $e) {
                $errorMessages['reactivateProduct'] = "Something went wrong while reactivating the product.";
            }
        }
    }
}

$products = $productModel->getAllProductsWithCategory(false);

page_start('Products', 'products.php');
heading('Catalog', 'Products', 'Create, update and manage every product listed in your store.');
?>

<link rel="stylesheet" href="../css/product_admin_view.css?v=<?= (int) @filemtime(__DIR__ . '/../css/product_admin_view.css') ?>">

<div class="toolbar">
    <span class="demo-label" id="product-count"><?= count($products) ?> products in total</span>
    <button type="button" id="add-product-btn" class="primary-button">
        <span aria-hidden="true">+</span> Add product
    </button>
</div>

<?php if ($successMessage): ?>
    <div class="form-success"><?= htmlspecialchars($successMessage) ?></div>
<?php endif; ?>

<?php if (!empty($errorMessages)): ?>
    <?php foreach ($errorMessages as $message): ?>
        <div class="form-error"><?= htmlspecialchars($message) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-overlay<?= !empty($data) ? ' open' : '' ?>" id="product-modal">

<form action="products.php" method="POST" enctype="multipart/form-data" class="add-product-form">

    <div class="form-header">
        <h2><?= isset($data['id']) ? 'Update product' : 'Add product'; ?></h2>
        <button type="button" id="exit-add-product-form-btn" class="form-close" aria-label="Close form">&times;</button>
    </div>

    <div class="form-grid">

        <div class="form-field">
            <label for="category_id">Category</label>
            <select name="category_id" id="category_id" required <?= $categories === [] ? 'disabled' : '' ?>>
                <option value=""><?= $categories === [] ? 'No categories available' : 'Select a category' ?></option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= (int) ($data['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($categories === []): ?>
                <a href="categories.php">Add a category first</a>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="name">Product Name</label>
            <input type="text" name="name" id="name"
            value="<?= htmlspecialchars((string) ($data['name'] ?? '')); ?>" required>
        </div>

        <div class="form-field">
            <label for="brand">Brand</label>
            <input type="text" name="brand" id="brand"
            value="<?= htmlspecialchars((string) ($data['brand'] ?? '')); ?>" required>
        </div>

        <div class="form-field">
            <label for="price">Price (JOD)</label>
            <input type="number" name="price" id="price" min="0" step="0.01"
            value="<?= htmlspecialchars((string) ($data['price'] ?? '')); ?>" required>
        </div>

        <div class="form-field">
            <label for="quantity">Quantity</label>
            <input type="number" name="quantity" id="quantity" min="0"
            value="<?= htmlspecialchars((string) ($data['quantity'] ?? '')); ?>" required>
        </div>

        <div class="form-field">
            <label for="condition_type">Condition</label>
            <select name="condition_type" id="condition_type" required>
                <option value="">Select condition</option>
                <option value="new" <?= ($data['condition_type'] ?? '') === 'new' ? 'selected' : '' ?>>New</option>
                <option value="used" <?= ($data['condition_type'] ?? '') === 'used' ? 'selected' : '' ?>>Used</option>
            </select>
        </div>

        <div class="form-field">
            <label for="listing_status">Listing Status</label>
            <select name="listing_status" id="listing_status" required>
                <option value="available" <?= ($data['listing_status'] ?? 'available') === 'available' ? 'selected' : '' ?>>Available</option>
                <option value="sold" <?= ($data['listing_status'] ?? '') === 'sold' ? 'selected' : '' ?>>Sold</option>
                <option value="hidden" <?= ($data['listing_status'] ?? '') === 'hidden' ? 'selected' : '' ?>>Hidden</option>
                <option value="reserved" <?= ($data['listing_status'] ?? '') === 'reserved' ? 'selected' : '' ?>>Reserved</option>
            </select>
        </div>

        <div class="form-field">
            <label for="product_variant">Product Variant</label>
            <input type="text" name="product_variant" id="product_variant"
            value="<?= htmlspecialchars((string) ($data['product_variant'] ?? '')); ?>">
        </div>

        <div class="form-field form-field-full">
            <label for="product_img_file">Product Image</label>
            <div class="image-field">
                <div class="img-preview-box">
                    <img id="product-img-preview" class="img-preview" width="110" height="110" alt="Product image preview"
                    src="<?= !empty($data['product_img']) ? '../' . htmlspecialchars((string) $data['product_img']) : '' ?>"
                    <?= empty($data['product_img']) ? 'hidden' : '' ?>>
                    <span id="product-img-placeholder" class="img-placeholder" <?= !empty($data['product_img']) ? 'hidden' : '' ?>>No image</span>
                </div>
                <div class="image-controls">
                    <input type="file" name="product_img_file" id="product_img_file" accept="image/jpeg,image/png,image/webp,image/gif">
                    <small class="file-hint">JPG, PNG, WEBP or GIF · up to 2 MB</small>
                </div>
            </div>
            <input type="hidden" name="product_img" id="product_img"
            value="<?= htmlspecialchars((string) ($data['product_img'] ?? '')); ?>">
        </div>

        <div class="form-field form-field-full">
            <label for="description">Description</label>
            <textarea name="description" id="description" rows="4" required><?= htmlspecialchars((string) ($data['description'] ?? '')); ?></textarea>
        </div>

    </div>

    <div class="form-actions">
        <?php if (isset($data['id'])) : ?>
            <input type="hidden" name="productId" value="<?= (int) $data['id']; ?>">
            <button type="submit" name="update_product" class="primary-button">Update Product</button>
        <?php else : ?>
            <button type="submit" name="add_product" class="primary-button">Add Product</button>
        <?php endif; ?>
    </div>
</form>

</div>

<section class="panel">

    <div class="panel-heading">
        <div>
            <h2>All products</h2>
            <p>Manage inventory and pricing, including archived listings.</p>
        </div>
    </div>

    <div class="filter-bar">

        <div class="filter-field filter-search">
            <label for="filter_q">Search</label>
            <div class="search-box">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="m20 20-3.5-3.5"/>
                </svg>
                <input type="search" id="filter_q" placeholder="Search by name, brand, category or description" autocomplete="off">
            </div>
        </div>

        <div class="filter-field">
            <label for="filter_category">Category</label>
            <select id="filter_category">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"><?= htmlspecialchars((string) $category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-field">
            <label for="filter_condition">Condition</label>
            <select id="filter_condition">
                <option value="">Any condition</option>
                <option value="new">New</option>
                <option value="used">Used</option>
            </select>
        </div>

        <div class="filter-field">
            <label for="filter_status">Status</label>
            <select id="filter_status">
                <option value="">Any status</option>
                <option value="available">Available</option>
                <option value="sold">Sold</option>
                <option value="hidden">Hidden</option>
                <option value="reserved">Reserved</option>
                <option value="archived">Archived</option>
            </select>
        </div>

        <div class="filter-actions">
            <button type="button" id="filter-reset" class="btn-ghost">Reset</button>
        </div>

    </div>

    <div class="table-scroll">
        <table class="products-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Condition</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

                <?php if (empty($products)): ?>

                    <tr>
                        <td colspan="7" class="table-empty">No products yet.</td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($products as $product): ?>

                        <?php $stock = (int) $product['quantity']; ?>
                        <?php $rowStatus = $product['deleted_at'] !== null ? 'archived' : (string) $product['listing_status']; ?>

                        <tr class="product-row"
                        data-search="<?= htmlspecialchars(implode(' ', [(string) $product['name'], (string) $product['brand'], (string) $product['description'], (string) ($product['product_variant'] ?? ''), (string) ($product['category_name'] ?? '')])) ?>"
                        data-category="<?= (int) $product['category_id'] ?>"
                        data-condition="<?= htmlspecialchars((string) $product['condition_type']) ?>"
                        data-status="<?= htmlspecialchars($rowStatus) ?>">
                            <td>
                                <div class="product-info">
                                    <?php if (!empty($product['product_img'])): ?>
                                        <img class="product-photo" width="60" height="60" src="../<?= htmlspecialchars((string)$product['product_img']) ?>" alt="<?= htmlspecialchars((string)$product['name']) ?>">
                                    <?php else : ?>
                                        <span class="product-photo product-photo-empty">—</span>
                                    <?php endif; ?>

                                    <div class="product-text">
                                        <strong><?= htmlspecialchars((string)$product['name']) ?></strong>
                                        <span class="product-meta"><?= htmlspecialchars((string)$product['brand']) ?> · #<?= (int) $product['id'] ?></span>

                                        <details class="product-details">
                                            <summary>Details</summary>
                                            <div class="product-details-body">
                                                <span><?= htmlspecialchars((string)$product['description']) ?></span>
                                                <?php if (!empty($product['product_variant'])): ?>
                                                    <span>Variant: <?= htmlspecialchars((string)$product['product_variant']) ?></span>
                                                <?php endif; ?>
                                                <span>Created: <?= htmlspecialchars((string)$product['created_at']) ?></span>
                                                <span>Updated: <?= htmlspecialchars((string)$product['updated_at']) ?></span>
                                                <?php if ($product['deleted_at'] !== null): ?>
                                                    <span>Archived: <?= htmlspecialchars((string)$product['deleted_at']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                    </div>
                                </div>
                            </td>
                            <td class="category-cell"><?= htmlspecialchars((string)($product['category_name'] ?? 'Uncategorized')) ?></td>
                            <td class="price-cell">
                                <strong><?= number_format((float) $product['price'], 2) ?></strong>
                                <small>JOD</small>
                            </td>
                            <td><span class="stock-value <?= $stock === 0 ? 'stock-out' : ($stock <= 4 ? 'stock-low' : '') ?>"><?= $stock ?></span></td>
                            <td><span class="badge badge-condition"><?= htmlspecialchars(ucfirst((string)$product['condition_type'])) ?></span></td>
                            <td>
                                <?php if ($product['deleted_at'] !== null): ?>
                                    <span class="badge badge-archived">Archived</span>
                                <?php else : ?>
                                    <span class="badge badge-<?= htmlspecialchars((string)$product['listing_status']) ?>"><?= htmlspecialchars(ucfirst((string)$product['listing_status'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <form action="products.php" method="POST">
                                        <input type="hidden" name="productId" value="<?= (int) $product['id']; ?>">
                                        <button type="submit" name="updateAction" class="btn-small">
                                            Update
                                        </button>
                                    </form>

                                    <?php if ($product['deleted_at'] === null): ?>
                                        <form action="products.php" method="POST">
                                            <input type="hidden" name="productId" value="<?= (int) $product['id']; ?>">
                                            <button type="submit" name="action" value="delete" class="btn-small btn-danger">
                                                Delete
                                            </button>
                                        </form>
                                    <?php else : ?>
                                        <form action="products.php" method="POST">
                                            <input type="hidden" name="productId" value="<?= (int) $product['id']; ?>">
                                            <button type="submit" name="action" value="reactivate" class="btn-small">
                                                Reactivate
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    <tr id="no-results" hidden>
                        <td colspan="7" class="table-empty">No products match your filters.</td>
                    </tr>

                <?php endif; ?>

            </tbody>
        </table>
    </div>

    <div class="pagination-bar" id="pagination-bar" hidden>
        <span class="pagination-info" id="pagination-info"></span>
        <nav class="pagination" id="pagination" aria-label="Products pagination"></nav>
    </div>

</section>

<script src="../js/product_admin_view.js?v=<?= (int) @filemtime(__DIR__ . '/../js/product_admin_view.js') ?>"></script>

<?php page_end(); ?>