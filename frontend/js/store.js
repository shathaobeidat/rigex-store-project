'use strict';
const menuButton = document.querySelector('.menu-button');
const storeNav = document.querySelector('#store-nav');
menuButton?.addEventListener('click', () => {
    const expanded = menuButton.getAttribute('aria-expanded') !== 'true';
    menuButton.setAttribute('aria-expanded', String(expanded));
    storeNav.classList.toggle('is-open', expanded);
});
storeNav?.addEventListener('click', (event) => {
    if (event.target.closest('a')) {
        menuButton?.setAttribute('aria-expanded', 'false');
        storeNav.classList.remove('is-open');
    }
});

document.querySelectorAll('[data-open-store-dialog]').forEach((button) => {
    button.addEventListener('click', () => {
        const dialog = document.getElementById(button.dataset.openStoreDialog);
        if (!dialog) return;
        storeNav?.classList.remove('is-open');
        menuButton?.setAttribute('aria-expanded', 'false');
        document.querySelectorAll('.store-account[open]').forEach((account) => account.removeAttribute('open'));
        dialog.showModal();
        document.body.classList.add('store-dialog-open');
    });
});

document.querySelectorAll('[data-close-store-dialog]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog').close());
});

document.querySelectorAll('.store-search-dialog, .store-cart-dialog').forEach((dialog) => {
    dialog.addEventListener('close', () => document.body.classList.remove('store-dialog-open'));
    dialog.addEventListener('click', (event) => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
            dialog.close();
        }
    });
});

document.addEventListener('click', (event) => {
    document.querySelectorAll('.store-account[open]').forEach((account) => {
        if (!account.contains(event.target)) account.removeAttribute('open');
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    storeNav?.classList.remove('is-open');
    menuButton?.setAttribute('aria-expanded', 'false');
    document.querySelectorAll('.store-account[open]').forEach((account) => account.removeAttribute('open'));
});

const landingCategoryLinks = Array.from(document.querySelectorAll('.hardware-category'));
const categoryCards = landingCategoryLinks.length
    ? landingCategoryLinks
    : Array.from(document.querySelectorAll('.landing-featured .landing-product-card'));
const searchingProducts = landingCategoryLinks.length === 0;
const categoryFeedback = document.getElementById('landing-category-feedback');

function filterLandingCategories(query) {
    if (!categoryCards.length || !categoryFeedback) return;
    const normalized = query.trim().toLocaleLowerCase();
    let matches = 0;
    categoryCards.forEach((card) => {
        card.hidden = normalized !== '' && !card.textContent.toLocaleLowerCase().includes(normalized);
        const target = card.getAttribute('href');
        const categoryProducts = target ? document.querySelector(target) : null;
        if (categoryProducts) categoryProducts.hidden = card.hidden;
        if (!card.hidden) matches++;
    });
    categoryFeedback.hidden = normalized === '';
    categoryFeedback.textContent = searchingProducts
        ? (matches ? `${matches} featured ${matches === 1 ? 'product' : 'products'} matching “${query.trim()}”.` : `No featured products match “${query.trim()}”.`)
        : matches
        ? `${matches} ${matches === 1 ? 'category' : 'categories'} matching “${query.trim()}”. Choose All categories to reset.`
        : `No categories match “${query.trim()}”. Choose All categories to see everything.`;
}

document.getElementById('landing-category-search')?.addEventListener('submit', (event) => {
    if (!categoryCards.length) return;
    event.preventDefault();
    const query = document.getElementById('header-search').value;
    filterLandingCategories(query);
    event.currentTarget.closest('dialog').close();
    const url = new URL(window.location.href);
    url.searchParams.set('q', query);
    url.hash = searchingProducts ? 'featured-products' : 'categories';
    window.history.replaceState({}, '', url);
    document.getElementById(searchingProducts ? 'featured-products' : 'categories')?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
});

document.querySelectorAll('[data-clear-category-search]').forEach((link) => {
    link.addEventListener('click', () => {
        filterLandingCategories('');
        const url = new URL(window.location.href);
        url.searchParams.delete('q');
        window.history.replaceState({}, '', url);
    });
});

const initialCategoryQuery = new URLSearchParams(window.location.search).get('q');
if (initialCategoryQuery) filterLandingCategories(initialCategoryQuery);

const storeFilterForm = document.querySelector('.store-catalog-filters');

// Replace an unreadable product photo once, without retrying a failed fallback.
document.querySelectorAll('img[data-category-fallback]').forEach((image) => {
    const useCategoryImage = () => {
        if (!image.dataset.categoryFallback) return;
        const fallback = image.dataset.categoryFallback;
        delete image.dataset.categoryFallback;
        image.src = fallback;
        image.alt = 'Category illustration';
    };
    image.addEventListener('error', useCategoryImage, { once: true });
    if (image.complete && image.naturalWidth === 0) useCategoryImage();
});

if (storeFilterForm) {
    const storeSearch = document.getElementById('store-search');
    const storeCategory = document.getElementById('store-category');
    const storeCondition = document.getElementById('store-condition');
    const storeClear = document.getElementById('store-clear');
    const storeResultCount = document.getElementById('store-result-count');
    const storeNoResults = document.getElementById('store-no-results');
    const storeCards = Array.from(document.querySelectorAll('.store-catalog .landing-product-card'));
    const filterButtons = Array.from(storeFilterForm.querySelectorAll('[data-filter-target]'));
    let storeSearchTimer;

    const applyStoreFilters = () => {
        clearTimeout(storeSearchTimer);
        filterButtons.forEach((button) => {
            const input = button.dataset.filterTarget === 'store-category' ? storeCategory : storeCondition;
            button.setAttribute('aria-pressed', String(button.dataset.filterValue === input.value));
        });
        const query = storeSearch.value.trim().toLocaleLowerCase();
        const mergedCategory = filterButtons.find(button => button.dataset.filterTarget === 'store-category'
            && button.dataset.values && button.dataset.values.split(',').includes(storeCategory.value));
        const selectedCategories = mergedCategory ? mergedCategory.dataset.values.split(',') : [storeCategory.value];
        let matches = 0;

        storeCards.forEach((card) => {
            const visible = (!query || card.dataset.search.toLocaleLowerCase().includes(query))
                && (!storeCategory.value || selectedCategories.includes(card.dataset.category))
                && (!storeCondition.value || card.dataset.condition === storeCondition.value);

            card.hidden = !visible;
            card.style.display = visible ? '' : 'none';

            if (visible) {
                matches++;
            }
        });

        storeResultCount.textContent = matches + ' ' + (matches === 1 ? 'product' : 'products') + ' available';

        if (storeNoResults) {
            storeNoResults.style.display = storeCards.length > 0 && matches === 0 ? '' : 'none';
        }

        storeClear.disabled = !(query || storeCategory.value || storeCondition.value);

        const url = new URL(window.location.href);

        if (storeSearch.value.trim()) {
            url.searchParams.set('q', storeSearch.value.trim());
        } else {
            url.searchParams.delete('q');
        }

        if (storeCategory.value) {
            url.searchParams.set('category_id', storeCategory.value);
        } else {
            url.searchParams.delete('category_id');
        }

        if (storeCondition.value) {
            url.searchParams.set('condition', storeCondition.value);
        } else {
            url.searchParams.delete('condition');
        }

        window.history.replaceState({}, '', url);
        storeFilterForm.dispatchEvent(new CustomEvent('store:filters-updated'));
    };

    const params = new URLSearchParams(window.location.search);

    storeSearch.value = params.get('q') ?? storeSearch.value;
    storeCategory.value = params.get('category_id') ?? '';
    storeCondition.value = params.get('condition') ?? '';
    [storeCategory, storeCondition].forEach((input) => {
        if (!filterButtons.some((button) => button.dataset.filterTarget === input.id && button.dataset.filterValue === input.value)) input.value = '';
    });
    filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const input = button.dataset.filterTarget === 'store-category' ? storeCategory : storeCondition;
            input.value = button.dataset.filterValue;
            applyStoreFilters();
        });
    });

    storeSearch.addEventListener('input', () => {
        clearTimeout(storeSearchTimer);
        storeSearchTimer = setTimeout(applyStoreFilters, 300);
    });
    storeCategory.addEventListener('change', applyStoreFilters);
    storeCondition.addEventListener('change', applyStoreFilters);

    storeFilterForm.addEventListener('submit', (event) => {
        event.preventDefault();
    });

    storeClear.addEventListener('click', (event) => {
        event.preventDefault();
        storeSearch.value = '';
        storeCategory.value = '';
        storeCondition.value = '';
        applyStoreFilters();
    });

    applyStoreFilters();
}

// Browser-only enhancement: no edits to the PHP filter markup are needed.
if (storeFilterForm) {
    const enhancementBase = new URL('.', document.currentScript.src);
    const filterStyles = document.createElement('link');
    filterStyles.rel = 'stylesheet';
    filterStyles.href = new URL('../css/store-filter-enhancements.css?v=1', enhancementBase);
    document.head.append(filterStyles);
    const filterEnhancements = document.createElement('script');
    filterEnhancements.src = new URL('store-filter-enhancements.js?v=1', enhancementBase);
    document.head.append(filterEnhancements);
}
