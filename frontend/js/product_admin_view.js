const modal = document.getElementById('product-modal');
const form = document.querySelector('.add-product-form');
const openButton = document.getElementById('add-product-btn');
const closeButton = document.getElementById('exit-add-product-form-btn');
const fileInput = document.getElementById('product_img_file');
const imagePath = document.getElementById('product_img');
const preview = document.getElementById('product-img-preview');
const placeholder = document.getElementById('product-img-placeholder');

const rows = Array.from(document.querySelectorAll('.product-row'));
const searchInput = document.getElementById('filter_q');
const categorySelect = document.getElementById('filter_category');
const conditionSelect = document.getElementById('filter_condition');
const statusSelect = document.getElementById('filter_status');
const resetButton = document.getElementById('filter-reset');
const noResultsRow = document.getElementById('no-results');
const countLabel = document.getElementById('product-count');
const paginationBar = document.getElementById('pagination-bar');
const paginationInfo = document.getElementById('pagination-info');
const pagination = document.getElementById('pagination');
const perPage = 10;
let currentPage = 1;

const showPreview = (source) => {
    if (source) {
        preview.src = source;
        preview.hidden = false;
        placeholder.hidden = true;
    } else {
        preview.removeAttribute('src');
        preview.hidden = true;
        placeholder.hidden = false;
    }
};

const resetForm = () => {
    form.querySelectorAll('input[type="text"], input[type="number"], textarea').forEach((field) => {
        field.value = '';
    });

    document.getElementById('category_id').selectedIndex = 0;
    document.getElementById('condition_type').selectedIndex = 0;
    document.getElementById('listing_status').value = 'available';

    imagePath.value = '';
    fileInput.value = '';
    showPreview('');

    const productId = form.querySelector('input[name="productId"]');

    if (productId) {
        productId.remove();
    }

    form.querySelector('.form-header h2').textContent = 'Add product';

    const submitButton = form.querySelector('.form-actions button[type="submit"]');

    submitButton.name = 'add_product';
    submitButton.textContent = 'Add Product';
};

const openForm = () => {
    modal.classList.add('open');
};

const closeForm = () => {
    modal.classList.remove('open');
};

const addPageButton = (label, page, className, disabled) => {
    const button = document.createElement('button');

    button.type = 'button';
    button.className = 'page-link ' + className;
    button.textContent = label;

    if (disabled) {
        button.disabled = true;
        button.classList.add('disabled');
    } else {
        button.addEventListener('click', () => {
            currentPage = page;
            applyFilters();
        });
    }

    pagination.appendChild(button);

    return button;
};

const renderPagination = (total, totalPages) => {
    pagination.innerHTML = '';
    pagination.hidden = totalPages <= 1;

    if (totalPages <= 1) {
        return;
    }

    addPageButton('‹ Previous', currentPage - 1, 'page-arrow', currentPage === 1);

    const numbers = [...new Set([1, totalPages, ...Array.from({ length: 5 }, (_, index) => currentPage - 2 + index)])]
        .filter((number) => number >= 1 && number <= totalPages)
        .sort((first, second) => first - second);

    let previousNumber = 0;

    numbers.forEach((number) => {
        if (number - previousNumber > 1) {
            const gap = document.createElement('span');

            gap.className = 'page-gap';
            gap.textContent = '…';
            pagination.appendChild(gap);
        }

        const button = addPageButton(String(number), number, number === currentPage ? 'active' : '', false);

        if (number === currentPage) {
            button.setAttribute('aria-current', 'page');
        }

        previousNumber = number;
    });

    addPageButton('Next ›', currentPage + 1, 'page-arrow', currentPage === totalPages);
};

const applyFilters = () => {
    const query = searchInput.value.trim().toLowerCase();

    const matches = rows.filter((row) => {
        return (!query || row.dataset.search.toLowerCase().includes(query))
            && (!categorySelect.value || row.dataset.category === categorySelect.value)
            && (!conditionSelect.value || row.dataset.condition === conditionSelect.value)
            && (!statusSelect.value || row.dataset.status === statusSelect.value);
    });

    const hasFilters = query || categorySelect.value || conditionSelect.value || statusSelect.value;
    const totalPages = Math.max(1, Math.ceil(matches.length / perPage));
    currentPage = Math.min(currentPage, totalPages);

    const start = (currentPage - 1) * perPage;

    rows.forEach((row) => {
        row.hidden = true;
    });

    matches.slice(start, start + perPage).forEach((row) => {
        row.hidden = false;
    });

    countLabel.textContent = matches.length + ' products ' + (hasFilters ? 'found' : 'in total');

    if (noResultsRow) {
        noResultsRow.hidden = matches.length > 0;
    }

    paginationBar.hidden = matches.length === 0;
    paginationInfo.innerHTML = 'Showing <strong>' + (start + 1) + '–' + Math.min(start + perPage, matches.length) + '</strong> of <strong>' + matches.length + '</strong> products';

    renderPagination(matches.length, totalPages);
};

openButton.addEventListener('click', () => {
    resetForm();
    openForm();
    document.getElementById('category_id').focus();
});

closeButton.addEventListener('click', closeForm);

modal.addEventListener('click', (event) => {
    if (event.target === modal) {
        closeForm();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeForm();
    }
});

[searchInput, categorySelect, conditionSelect, statusSelect].forEach((control) => {
    control.addEventListener(control === searchInput ? 'input' : 'change', () => {
        currentPage = 1;
        applyFilters();
    });
});

resetButton.addEventListener('click', () => {
    searchInput.value = '';
    categorySelect.value = '';
    conditionSelect.value = '';
    statusSelect.value = '';
    currentPage = 1;
    applyFilters();
});

fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];

    if (file) {
        showPreview(URL.createObjectURL(file));
    } else {
        showPreview(imagePath.value ? '../' + imagePath.value : '');
    }
});

if (rows.length > 0) {
    applyFilters();
}