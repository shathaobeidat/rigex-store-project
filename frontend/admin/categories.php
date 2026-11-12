<?php
require_once __DIR__ . '/../../backend/classes/CategorieClass.php';
require_once __DIR__ . '/../layout.php';

$category = new Categorie();

$imagePath = '../assets/category/';
$search = trim($_GET['q'] ?? '');
$error = '';
$openModalOnLoad = false;

function redirectCategories(string $status): void
{
    header('Location: categories.php?status=' . urlencode($status));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_category' || $action === 'update_category') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $id = filter_var(
                $_POST['category_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($name === '') {
                throw new RuntimeException('Category name is required.');
            }

            if (mb_strlen($name) > 100) {
                throw new RuntimeException(
                    'Category name must be 100 characters or less.'
                );
            }

            if (mb_strlen($description) > 2000) {
                throw new RuntimeException(
                    'Description must be 2000 characters or less.'
                );
            }

            $isUpdate = $action === 'update_category';

            if ($isUpdate && (!$id || $id < 1)) {
                throw new RuntimeException('Invalid category ID.');
            }

            if ($isUpdate && !$category->getById((int) $id)) {
                throw new RuntimeException('Category not found.');
            }

            $uploadedFile = $_FILES['category_image'] ?? null;
            $hasNewImage = $uploadedFile
                && ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE)
                !== UPLOAD_ERR_NO_FILE;

            if (!$isUpdate && !$hasNewImage) {
                throw new RuntimeException(
                    'Please select a category image.'
                );
            }

            $fileName = '';

            if ($hasNewImage) {
                $imageError = $category->checkImage($uploadedFile);

                if ($imageError !== '') {
                    throw new RuntimeException($imageError);
                }

                $fileName = $category->uploadImage($uploadedFile);
            }

            try {
                if ($isUpdate) {
                    $category->update(
                        (int) $id,
                        $name,
                        $description,
                        $fileName
                    );

                    redirectCategories('updated');
                } else {
                    $category->add($name, $description, $fileName);

                    redirectCategories('created');
                }
            } catch (Throwable $exception) {
                if ($fileName !== '') {
                    $category->deleteImage($fileName);
                }

                throw $exception;
            }
        }

        if ($action === 'delete_category') {
            $id = filter_var(
                $_POST['category_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id || $id < 1) {
                throw new RuntimeException('Invalid category ID.');
            }

            $category->delete((int) $id);
            redirectCategories('deleted');
        }

        throw new RuntimeException('Invalid request.');
    } catch (Throwable $exception) {
        error_log($exception->getMessage());

        $error = $exception->getMessage();

        if (
            $exception instanceof PDOException ||
            str_contains(strtolower($error), 'sqlstate')
        ) {
            $error = 'Something went wrong. Please try again.';
        }

        $openModalOnLoad = in_array(
            $action,
            ['create_category', 'update_category'],
            true
        );
    }
}

$categories = $category->getAll($search);
$productFilter = is_string($_GET['products'] ?? null) ? $_GET['products'] : '';
$imageFilter = is_string($_GET['image'] ?? null) ? $_GET['image'] : '';
$sort = is_string($_GET['sort'] ?? null) ? $_GET['sort'] : 'newest';
$categories = array_values(array_filter($categories, static function (array $cat) use ($productFilter, $imageFilter): bool {
    $hasProducts = (int) $cat['products_count'] > 0;
    $hasImage = !empty($cat['category_img']);
    return ($productFilter !== 'with' || $hasProducts)
        && ($productFilter !== 'empty' || !$hasProducts)
        && ($imageFilter !== 'with' || $hasImage)
        && ($imageFilter !== 'missing' || !$hasImage);
}));
usort($categories, static function (array $a, array $b) use ($sort): int {
    return match ($sort) {
        'name-asc' => strnatcasecmp($a['name'], $b['name']),
        'name-desc' => strnatcasecmp($b['name'], $a['name']),
        'products-desc' => (int) $b['products_count'] <=> (int) $a['products_count'] ?: (int) $b['id'] <=> (int) $a['id'],
        'products-asc' => (int) $a['products_count'] <=> (int) $b['products_count'] ?: (int) $b['id'] <=> (int) $a['id'],
        default => (int) $b['id'] <=> (int) $a['id'],
    };
});
$hasFilters = $search !== '' || $productFilter !== '' || $imageFilter !== '' || $sort !== 'newest';

page_start('Categories', 'categories.php');
heading('STORE MANAGEMENT', 'Categories', 'Organize your catalog and manage product collections.');
?>

<link rel="stylesheet" href="../css/categories.css?v=<?= filemtime(__DIR__ . '/../css/categories.css') ?>">

<div class="categories-page">

    <section class="category-toolbar">
        <span class="catalog-toolbar-note">Keep every collection organized and easy to discover.</span>
        <button type="button" class="add-category-btn" id="openCategoryModal">
            <span aria-hidden="true">＋</span>
            Add Category
        </button>
    </section>

    <?php
    $messages = [
        'created' => 'Category created successfully.',
        'updated' => 'Category updated successfully.',
        'deleted' => 'Category deleted successfully.',
    ];

    $status = $_GET['status'] ?? '';

    if (isset($messages[$status])):
        ?>
        <div class="category-alert success" role="status">
            <?= e($messages[$status]) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="category-alert error" role="alert">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <section class="category-controls">
        <div>
            <h3>All categories</h3>
            <p><?= count($categories) ?> categories found</p>
        </div>

        <form method="get" class="category-search">
            <div class="category-search-input"><span aria-hidden="true">⌕</span>
                <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search categories..." aria-label="Search categories">
            </div>
            <select name="products" aria-label="Filter by product count">
                <option value="">All categories</option>
                <option value="with" <?= $productFilter === 'with' ? 'selected' : '' ?>>With products</option>
                <option value="empty" <?= $productFilter === 'empty' ? 'selected' : '' ?>>Empty categories</option>
            </select>
            <select name="image" aria-label="Filter by category image">
                <option value="">All images</option>
                <option value="with" <?= $imageFilter === 'with' ? 'selected' : '' ?>>With image</option>
                <option value="missing" <?= $imageFilter === 'missing' ? 'selected' : '' ?>>Missing image</option>
            </select>
            <select name="sort" aria-label="Sort categories">
                <?php foreach (['newest' => 'Newest first', 'name-asc' => 'Name: A to Z', 'name-desc' => 'Name: Z to A', 'products-desc' => 'Most products', 'products-asc' => 'Fewest products'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($hasFilters): ?>
                <a href="categories.php" class="clear-search">Reset</a>
            <?php endif; ?>

            <button type="submit">Search</button>
        </form>
    </section>

    <?php if (empty($categories)): ?>

        <section class="category-empty">
            <div class="empty-icon" aria-hidden="true">▦</div>

            <h3>
                <?= $hasFilters
                    ? 'No matching categories'
                    : 'Your catalog starts here' ?>
            </h3>

            <p>
                <?= $hasFilters ? 'Try another keyword or reset your filters.' : 'Create your first category to organize your products.' ?>
            </p>

            <?php if (!$hasFilters): ?>
                <button type="button" class="add-category-btn" id="openCategoryModalEmpty">
                    ＋ Create your first category
                </button>
            <?php endif; ?>
        </section>

    <?php else: ?>

        <section class="category-table-wrap">
            <div class="category-table-scroll">
                <table class="category-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Products</th>
                            <th>Image</th>
                            <th class="actions-heading">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <?php
                            $description = trim($cat['description'] ?? '');
                            $description = $description !== ''
                                ? $description
                                : 'No description';
                            ?>

                            <tr>
                                <td>
                                    <div class="category-name-cell">
                                        <strong title="<?= e($cat['name']) ?>"><?= e($cat['name']) ?></strong>
                                    </div>
                                </td>

                                <td class="description-cell">
                                    <div class="description-content">
                                        <span class="description-text"><?= e($description) ?></span>

                                        <button type="button" class="description-toggle" aria-expanded="false" hidden>
                                            Show more
                                        </button>
                                    </div>
                                </td>

                                <td>
                                    <span class="product-count">
                                        <?= (int) $cat['products_count'] ?>
                                        <?= (int) $cat['products_count'] === 1
                                            ? 'product'
                                            : 'products' ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if (!empty($cat['category_img'])): ?>
                                        <img class="table-category-image"
                                            src="<?= e($imagePath . basename($cat['category_img'])) ?>" alt="<?= e($cat['name']) ?>"
                                            loading="lazy">
                                    <?php else: ?>
                                        <span class="table-image-placeholder">
                                            No image
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="category-actions">
                                        <button type="button" class="action-btn edit-category-btn"
                                            data-id="<?= (int) $cat['id'] ?>" data-name="<?= e($cat['name']) ?>"
                                            data-description="<?= e($cat['description'] ?? '') ?>"
                                            data-image="<?= e($cat['category_img'] ?? '') ?>">
                                            Edit
                                        </button>

                                        <form method="post" class="delete-category-form"
                                            data-category-name="<?= e($cat['name']) ?>">
                                            <input type="hidden" name="action" value="delete_category">

                                            <input type="hidden" name="category_id" value="<?= (int) $cat['id'] ?>">

                                            <button type="submit" class="action-btn delete-btn">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php endif; ?>
</div>

<div class="category-modal" id="categoryModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>

    <section class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-header">
            <div>
                <div class="category-kicker">CATALOG MANAGEMENT</div>

                <h2 id="modalTitle">
                    Add category<span>.</span>
                </h2>

                <p id="modalDescription">
                    Create a new collection for your store.
                </p>
            </div>

            <button type="button" class="modal-close" id="closeCategoryModal" aria-label="Close modal">
                &times;
            </button>
        </div>

        <form method="post" enctype="multipart/form-data" id="categoryForm">
            <input type="hidden" name="action" id="categoryAction" value="create_category">

            <input type="hidden" name="category_id" id="categoryId" value="">

            <div class="modal-body">
                <div class="form-field">
                    <label for="categoryName">
                        Category name <span>*</span>
                    </label>

                    <input id="categoryName" type="text" name="name" maxlength="100" placeholder="e.g. Graphics Cards"
                        required>
                </div>

                <div class="form-field">
                    <label for="categoryDescription">Description</label>

                    <textarea id="categoryDescription" name="description" rows="3" maxlength="2000"
                        placeholder="Describe the products in this category..."></textarea>

                    <div class="description-preview" id="descriptionPreview" hidden>
                        <p id="descriptionPreviewText"></p>
                        <button type="button" class="description-preview-toggle" id="descriptionPreviewToggle"
                            aria-expanded="false">See more</button>
                    </div>
                </div>

                <div class="form-field">
                    <label for="categoryImage">
                        Category image <span id="imageRequiredMark">*</span>
                    </label>

                    <label for="categoryImage" class="upload-area" id="uploadArea">
                        <img id="imagePreview" alt="Selected category image preview" hidden>

                        <span class="upload-icon" id="uploadIcon" aria-hidden="true">＋</span>

                        <strong id="uploadTitle">
                            Upload category image
                        </strong>

                        <span id="uploadHint">
                            Click to browse your files
                        </span>

                        <small>JPG, PNG or WEBP · Maximum 2 MB</small>
                    </label>

                    <input id="categoryImage" type="file" name="category_image"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required hidden>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="cancel-btn" data-close-modal>
                    Cancel
                </button>

                <button type="submit" class="add-category-btn" id="submitCategory">
                    Create Category <span aria-hidden="true">→</span>
                </button>
            </div>
        </form>
    </section>
</div>

<div class="category-modal" id="deleteConfirmModal" aria-hidden="true">
    <div class="modal-backdrop" id="deleteConfirmBackdrop"></div>

    <section class="modal-panel delete-confirm-panel" role="dialog" aria-modal="true"
        aria-labelledby="deleteConfirmTitle">
        <div class="modal-header">
            <div>
                <div class="category-kicker">CONFIRM ACTION</div>
                <h2 id="deleteConfirmTitle">Delete category<span>.</span></h2>
                <p>Are you sure you want to delete <strong id="deleteCategoryName"></strong>? This action cannot be
                    undone.</p>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="cancel-btn" id="cancelDeleteCategory">
                Cancel
            </button>
            <button type="button" class="confirm-delete-btn" id="confirmDeleteCategory">
                Delete Category
            </button>
        </div>
    </section>
</div>


<script>
    (() => {
        const modal = document.getElementById('categoryModal');
        const deleteModal = document.getElementById('deleteConfirmModal');
        const deleteCategoryName = document.getElementById('deleteCategoryName');
        const cancelDeleteButton = document.getElementById('cancelDeleteCategory');
        const confirmDeleteButton = document.getElementById('confirmDeleteCategory');
        const form = document.getElementById('categoryForm');
        const fileInput = document.getElementById('categoryImage');
        const preview = document.getElementById('imagePreview');
        const uploadIcon = document.getElementById('uploadIcon');
        const uploadTitle = document.getElementById('uploadTitle');
        const uploadHint = document.getElementById('uploadHint');
        const submitButton = document.getElementById('submitCategory');

        const actionInput = document.getElementById('categoryAction');
        const categoryIdInput = document.getElementById('categoryId');
        const nameInput = document.getElementById('categoryName');
        const descriptionInput = document.getElementById('categoryDescription');
        const title = document.getElementById('modalTitle');
        const modalDescription = document.getElementById('modalDescription');
        const imageRequiredMark = document.getElementById('imageRequiredMark');
        const descriptionPreview = document.getElementById('descriptionPreview');
        const descriptionPreviewText = document.getElementById('descriptionPreviewText');
        const descriptionPreviewToggle = document.getElementById('descriptionPreviewToggle');

        let previousFocus = null;
        let previewUrl = null;
        let descriptionPreviewExpanded = false;
        let pendingDeleteForm = null;
        let authorizedDeleteForm = null;
        let deletePreviousFocus = null;

        document.querySelectorAll('.category-alert').forEach(alert => {
            window.setTimeout(() => {
                alert.classList.add('is-dismissing');
                window.setTimeout(() => alert.remove(), 300);
            }, 4000);
        });

        function updateDescriptionPreview() {
            const description = descriptionInput.value.trim();
            const isLong = description.length > 100;

            descriptionPreviewText.textContent = description;
            descriptionPreview.hidden = !isLong;
            descriptionPreviewText.classList.toggle(
                'is-expanded',
                descriptionPreviewExpanded
            );
            descriptionPreviewToggle.textContent = descriptionPreviewExpanded
                ? 'See less'
                : 'See more';
            descriptionPreviewToggle.setAttribute(
                'aria-expanded',
                String(descriptionPreviewExpanded)
            );
        }

        descriptionInput.addEventListener('input', () => {
            descriptionPreviewExpanded = false;
            updateDescriptionPreview();
        });

        descriptionPreviewToggle.addEventListener('click', () => {
            descriptionPreviewExpanded = !descriptionPreviewExpanded;
            updateDescriptionPreview();
        });

        function clearPreview() {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = null;
            }

            preview.hidden = true;
            preview.removeAttribute('src');
            uploadIcon.hidden = false;
        }

        function resetForm() {
            form.reset();
            descriptionPreviewExpanded = false;
            updateDescriptionPreview();
            categoryIdInput.value = '';
            actionInput.value = 'create_category';
            fileInput.required = true;

            title.innerHTML = 'Add category<span>.</span>';
            modalDescription.textContent =
                'Create a new collection for your store.';
            uploadTitle.textContent = 'Upload category image';
            uploadHint.textContent = 'Click to browse your files';
            imageRequiredMark.hidden = false;

            submitButton.innerHTML =
                'Create Category <span aria-hidden="true">→</span>';

            clearPreview();
        }

        function openModal() {
            previousFocus = document.activeElement;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');

            nameInput.focus();
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');

            clearPreview();

            if (previousFocus && typeof previousFocus.focus === 'function') {
                previousFocus.focus();
            }
        }

        function openDeleteModal(formElement) {
            pendingDeleteForm = formElement;
            deletePreviousFocus = document.activeElement;
            deleteCategoryName.textContent =
                formElement.dataset.categoryName || 'this category';
            deleteModal.classList.add('is-open');
            deleteModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
            cancelDeleteButton.focus();
        }

        function closeDeleteModal() {
            deleteModal.classList.remove('is-open');
            deleteModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            pendingDeleteForm = null;

            if (deletePreviousFocus && typeof deletePreviousFocus.focus === 'function') {
                deletePreviousFocus.focus();
            }
        }

        function openCreateModal() {
            resetForm();
            openModal();
        }

        document.getElementById('openCategoryModal')
            .addEventListener('click', openCreateModal);

        const emptyButton = document.getElementById('openCategoryModalEmpty');

        if (emptyButton) {
            emptyButton.addEventListener('click', openCreateModal);
        }

        document.getElementById('closeCategoryModal')
            .addEventListener('click', closeModal);

        modal.querySelectorAll('[data-close-modal]').forEach(element => {
            element.addEventListener('click', closeModal);
        });

        document.querySelectorAll('.edit-category-btn').forEach(button => {
            button.addEventListener('click', () => {
                resetForm();

                actionInput.value = 'update_category';
                categoryIdInput.value = button.dataset.id || '';
                nameInput.value = button.dataset.name || '';
                descriptionInput.value = button.dataset.description || '';
                updateDescriptionPreview();

                title.innerHTML = 'Edit category<span>.</span>';
                modalDescription.textContent =
                    'Update this category’s information.';
                uploadTitle.textContent = 'Choose a replacement image';
                uploadHint.textContent =
                    'Leave empty to keep the current image';
                imageRequiredMark.hidden = true;
                fileInput.required = false;

                const existingImage = button.dataset.image || '';

                if (existingImage) {
                    preview.src = '../assets/category/' +
                        encodeURIComponent(existingImage.split(/[\\/]/).pop());
                    preview.hidden = false;
                    uploadIcon.hidden = true;
                    uploadTitle.textContent = 'Current image loaded';
                    uploadHint.textContent =
                        'Choose another image to replace it';
                }

                submitButton.innerHTML =
                    'Save Changes <span aria-hidden="true">→</span>';

                openModal();
            });
        });

        document.querySelectorAll('.delete-category-form').forEach(formElement => {
            formElement.addEventListener('submit', event => {
                if (formElement === authorizedDeleteForm) {
                    authorizedDeleteForm = null;
                    return;
                }

                event.preventDefault();
                openDeleteModal(formElement);
            });
        });

        document.getElementById('deleteConfirmBackdrop')
            .addEventListener('click', closeDeleteModal);
        cancelDeleteButton.addEventListener('click', closeDeleteModal);
        confirmDeleteButton.addEventListener('click', () => {
            if (!pendingDeleteForm) {
                return;
            }

            authorizedDeleteForm = pendingDeleteForm;
            pendingDeleteForm.requestSubmit();
        });

        document.addEventListener('keydown', event => {
            if (event.key !== 'Escape') {
                return;
            }

            if (deleteModal.classList.contains('is-open')) {
                closeDeleteModal();
            } else if (modal.classList.contains('is-open')) {
                closeModal();
            }
        });

        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];

            clearPreview();

            if (!file) {
                if (actionInput.value === 'create_category') {
                    uploadTitle.textContent = 'Upload category image';
                    uploadHint.textContent = 'Click to browse your files';
                } else {
                    uploadTitle.textContent = 'Choose a replacement image';
                    uploadHint.textContent =
                        'Leave empty to keep the current image';
                }
                return;
            }

            const allowedTypes = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];

            if (
                !allowedTypes.includes(file.type) ||
                file.size > 2 * 1024 * 1024
            ) {
                window.alert('Choose a JPG, PNG or WEBP image up to 2 MB.');
                fileInput.value = '';
                return;
            }

            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
            preview.hidden = false;
            uploadIcon.hidden = true;
            uploadTitle.textContent = file.name;
            uploadHint.textContent = 'Image selected successfully';
        });

        form.addEventListener('submit', event => {
            if (!form.reportValidity()) {
                event.preventDefault();
                return;
            }

            submitButton.disabled = true;
            submitButton.textContent =
                actionInput.value === 'update_category' ? 'Saving changes...' : 'Creating category...';
        });

        function setupDescriptionToggles() {
            document.querySelectorAll('.description-content').forEach(content => {
                const text = content.querySelector('.description-text');
                const button = content.querySelector('.description-toggle');

                if (!text || !button) {
                    return;
                }

                text.classList.remove('is-expanded');

                button.textContent = 'Show more';
                button.setAttribute('aria-expanded', 'false');

                const isLong = text.textContent.trim().length > 80;
                button.hidden = !isLong;

                button.onclick = () => {
                    const expanded = text.classList.toggle('is-expanded');

                    button.textContent = expanded
                        ? 'Show less'
                        : 'Show more';

                    button.setAttribute(
                        'aria-expanded',
                        String(expanded)
                    );
                };
            });
        }

        setupDescriptionToggles();

        let descriptionResizeFrame = null;

        window.addEventListener('resize', () => {
            if (descriptionResizeFrame !== null) {
                cancelAnimationFrame(descriptionResizeFrame);
            }

            descriptionResizeFrame = requestAnimationFrame(() => {
                setupDescriptionToggles();
                descriptionResizeFrame = null;
            });
        });

        <?php if ($openModalOnLoad): ?>
            openModal();
        <?php endif; ?>
    })();
</script>

<?php page_end(); ?>
