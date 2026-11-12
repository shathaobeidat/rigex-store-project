'use strict';
(() => {
    // Edit these two settings to change labels and category order.
    const labelMap = {
        'Gbu': 'Graphics Cards', 'GPU': 'Graphics Cards',
        'Processors (CPU)': 'Processors', 'CPU': 'Processors',
        'Memory (RAM)': 'RAM'
    };
    const orderList = ['Graphics Cards', 'Processors', 'Motherboards', 'RAM', 'Storage',
        'Power Supplies', 'PC Cases', 'Cooling', 'Monitors'];
    const form = document.getElementById('catalog-filters');
    const row = form?.querySelector('.catalog-categories .catalog-filter-buttons');
    if (!row || form.dataset.filterEnhanced) return;
    form.dataset.filterEnhanced = 'true';
    const category = document.getElementById('store-category');
    const condition = document.getElementById('store-condition');
    const search = document.getElementById('store-search');
    const reset = document.getElementById('store-clear');
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const aliases = new Map(Object.entries(labelMap).map(([key, value]) => [key.toLocaleLowerCase(), value]));
    const groups = new Map();
    const originalButtons = [...row.querySelectorAll('[data-filter-target="store-category"]')];
    let allButton;
    originalButtons.forEach(button => {
        const originalValue = button.dataset.filterValue;
        const name = button.textContent.trim().replace(/\s+/g, ' ');
        if (originalValue === '') { allButton = button; return; }
        if (!name) { button.hidden = true; return; }
        const label = aliases.get(name.toLocaleLowerCase()) ?? name;
        const key = label.toLocaleLowerCase();
        if (!groups.has(key)) groups.set(key, {button, label, values: []});
        const group = groups.get(key);
        if (!group.values.includes(originalValue)) group.values.push(originalValue);
        if (button !== group.button) button.hidden = true;
    });
    const rank = name => {
        const index = orderList.findIndex(value => value.toLocaleLowerCase() === name.toLocaleLowerCase());
        return index < 0 ? orderList.length : index;
    };
    const sorted = [...groups.values()].sort((a, b) => rank(a.label) - rank(b.label) || a.label.localeCompare(b.label, undefined, {sensitivity: 'base'}));
    const tabs = [allButton, ...sorted.map(group => group.button)].filter(Boolean);
    const cards = [...document.querySelectorAll('.store-catalog .landing-product-card')];
    const hasCategories = cards.length > 0 && cards.every(card => card.hasAttribute('data-category'));
    function decorate(button, label, values) {
        button.dataset.values = values.join(',');
        button.dataset.categoryLabel = label;
        const text = document.createElement('span'); text.textContent = label;
        button.replaceChildren(text);
        if (hasCategories) {
            const number = document.createElement('span');
            number.className = 'filter-tab-count';
            number.textContent = values.length === 0 ? cards.length : cards.filter(card => values.includes(card.dataset.category)).length;
            number.setAttribute('aria-hidden', 'true');
            button.append(number);
            button.setAttribute('aria-label', `${label}, ${number.textContent} products`);
        }
        button.setAttribute('role', 'tab');
        button.setAttribute('aria-controls', 'store-product-results');
        row.append(button);
    }
    if (allButton) decorate(allButton, 'All categories', []);
    sorted.forEach(group => decorate(group.button, group.label, group.values));
    // Keep merged originals (and every existing data attribute/listener) in the DOM.
    row.querySelectorAll('button[hidden]').forEach(button => { button.tabIndex = -1; });
    row.setAttribute('role', 'tablist');
    row.setAttribute('aria-label', 'Product categories');
    row.setAttribute('aria-orientation', 'horizontal');
    const resultGrid = document.querySelector('.store-catalog .landing-products-grid');
    if (resultGrid && !resultGrid.id) resultGrid.id = 'store-product-results';
    tabs.forEach(tab => {
        if (resultGrid) tab.setAttribute('aria-controls', resultGrid.id);
        else tab.removeAttribute('aria-controls');
    });
    const viewport = document.createElement('div');
    viewport.className = 'filter-tabs-viewport';
    row.before(viewport); viewport.append(row);
    form.classList.add('filter-bar-enhanced');
    function edges() {
        viewport.classList.toggle('has-scroll-left', row.scrollLeft > 2);
        viewport.classList.toggle('has-scroll-right', row.scrollWidth - row.clientWidth - row.scrollLeft > 2);
    }
    function showTab(tab, smooth = true) {
        const r = row.getBoundingClientRect(), b = tab.getBoundingClientRect();
        const padding = 18;
        let delta = 0;
        if (b.left < r.left + padding) delta = b.left - r.left - padding;
        else if (b.right > r.right - padding) delta = b.right - r.right + padding;
        if (delta) row.scrollBy({left: delta, behavior: smooth && !reduced.matches ? 'smooth' : 'instant'});
    }
    let lastCategory;
    function sync() {
        const active = tabs.find(tab => tab.dataset.values.split(',').includes(category.value)) ?? allButton ?? tabs[0];
        tabs.forEach(tab => {
            const selected = tab === active;
            tab.setAttribute('aria-selected', String(selected));
            tab.setAttribute('aria-pressed', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        });
        const enabled = Boolean(search.value.trim() || condition.value || category.value);
        reset.classList.toggle('filter-reset-active', enabled);
        reset.setAttribute('aria-hidden', String(!enabled));
        if (lastCategory !== category.value) {
            lastCategory = category.value;
            if (active) requestAnimationFrame(() => showTab(active));
        }
        requestAnimationFrame(edges);
    }
    form.addEventListener('store:filters-updated', sync);
    // Reset through the existing handler; remove old query parameters first.
    reset.addEventListener('click', () => {
        const url = new URL(location.href); url.search = '';
        history.replaceState({}, '', url);
    }, true);
    // Fade the Reset control as the user types, while matching remains debounced.
    search.addEventListener('input', () => {
        const enabled = Boolean(search.value.trim() || condition.value || category.value);
        reset.disabled = !enabled;
        reset.classList.toggle('filter-reset-active', enabled);
        reset.setAttribute('aria-hidden', String(!enabled));
    });
    row.addEventListener('keydown', event => {
        const keys = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];
        if (!keys.includes(event.key)) return;
        const current = tabs.indexOf(event.target.closest('[role="tab"]'));
        if (current < 0) return;
        event.preventDefault();
        const index = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1
            : (current + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
        tabs[index].focus({preventScroll: true}); tabs[index].click(); showTab(tabs[index]);
    });
    let drag = null, suppressClick = false;
    row.addEventListener('pointerdown', event => {
        if (event.pointerType !== 'mouse' || event.button !== 0) return;
        drag = {id: event.pointerId, x: event.clientX, scroll: row.scrollLeft, moved: false};
        suppressClick = false;
    });
    row.addEventListener('pointermove', event => {
        if (!drag || event.pointerId !== drag.id) return;
        const distance = event.clientX - drag.x;
        if (Math.abs(distance) > 5 && !drag.moved) {
            drag.moved = true; row.setPointerCapture(event.pointerId); row.classList.add('is-dragging');
        }
        if (drag.moved) { event.preventDefault(); row.scrollLeft = drag.scroll - distance; }
    });
    function stopDrag(event) {
        if (!drag || event.pointerId !== drag.id) return;
        suppressClick = drag.moved;
        if (row.hasPointerCapture(drag.id)) row.releasePointerCapture(drag.id);
        row.classList.remove('is-dragging'); drag = null;
        setTimeout(() => { suppressClick = false; }, 0);
    }
    row.addEventListener('pointerup', stopDrag);
    row.addEventListener('pointercancel', stopDrag);
    row.addEventListener('pointerleave', event => { if (drag && !drag.moved) drag = null; });
    row.addEventListener('click', event => {
        if (suppressClick) { event.preventDefault(); event.stopImmediatePropagation(); }
    }, true);
    row.addEventListener('scroll', edges, {passive: true});
    if ('ResizeObserver' in window) new ResizeObserver(edges).observe(row);
    addEventListener('resize', edges, {passive: true});
    // Reapply existing filters now that categories can represent multiple IDs.
    category.dispatchEvent(new Event('change', {bubbles: true}));
    sync();
})();
