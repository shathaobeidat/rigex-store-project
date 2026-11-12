document.addEventListener('DOMContentLoaded', () => {
  const tableBody = document.getElementById('orders-table-body');
  const searchInput = document.getElementById('orders-search');
  const filterButtons = document.querySelectorAll('[data-status-filter]');
  const exportButton = document.getElementById('orders-export');
  const count = document.getElementById('orders-result-count');
  const emptyRow = document.getElementById('orders-empty');

  if (!tableBody || !searchInput || !exportButton || !count || !emptyRow) return;

  let rows = Array.from(tableBody.querySelectorAll('[data-order-row]'));
  let activeStatus = 'all';
  const statusSelect = document.getElementById('orders-status-select');
  const paymentSelect = document.getElementById('orders-payment-select');
  const sortSelect = document.getElementById('orders-sort-select');

  const updateSummary = (summary) => {
    if (!summary) return;
    const number = new Intl.NumberFormat('en-JO');
    document.getElementById('orders-stat-total').textContent = number.format(Number(summary.total_orders));
    document.getElementById('orders-stat-attention').textContent = number.format(Number(summary.needs_attention));
    document.getElementById('orders-stat-transit').textContent = number.format(Number(summary.in_transit));
    document.getElementById('orders-stat-sales').textContent = Number(summary.gross_sales).toLocaleString('en-JO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' JOD';
    document.getElementById('orders-stat-count-change').textContent = summary.orders_comparison;
    document.getElementById('orders-stat-sales-change').textContent = summary.sales_comparison;
  };

  const updateRows = () => {
    const query = searchInput.value.trim().toLocaleLowerCase();
    let visibleCount = 0;
    const sort = sortSelect?.value || 'newest';
    const total = (row) => Number(row.querySelector('.orders-total').textContent.replace(/[^0-9.]/g, ''));
    rows.sort((a, b) => sort.startsWith('total-')
      ? (total(a) - total(b)) * (sort === 'total-asc' ? 1 : -1) || Number(b.dataset.orderId) - Number(a.dataset.orderId)
      : (Number(a.dataset.created) - Number(b.dataset.created) || Number(a.dataset.orderId) - Number(b.dataset.orderId)) * (sort === 'oldest' ? 1 : -1));
    rows.forEach((row) => tableBody.insertBefore(row, emptyRow));

    rows.forEach((row) => {
      const matchesStatus = activeStatus === 'all' || row.dataset.status === activeStatus;
      const matchesSearch = row.textContent.toLocaleLowerCase().includes(query);
      const payment = row.querySelector('.orders-payment').textContent.trim().toLowerCase();
      const matchesPayment = !paymentSelect || paymentSelect.value === 'all' || paymentSelect.value === payment;
      const visible = matchesStatus && matchesSearch && matchesPayment;
      row.hidden = !visible;
      if (visible) visibleCount += 1;
    });

    emptyRow.hidden = visibleCount > 0;
    count.textContent = `Showing ${visibleCount} of ${rows.length} orders`;
    filterButtons.forEach((button) => {
      const status = button.dataset.statusFilter;
      button.querySelector('span').textContent = rows.filter(
        (row) => status === 'all' || row.dataset.status === status
      ).length;
    });
  };

  filterButtons.forEach((button) => {
    button.addEventListener('click', () => {
      activeStatus = button.dataset.statusFilter || 'all';
      filterButtons.forEach((filterButton) => {
        const isActive = filterButton === button;
        filterButton.classList.toggle('active', isActive);
        filterButton.setAttribute('aria-pressed', String(isActive));
      });
      updateRows();
    });
  });

  searchInput.addEventListener('input', updateRows);
  statusSelect?.addEventListener('change', () => {
    activeStatus = statusSelect.value;
    updateRows();
  });
  paymentSelect?.addEventListener('change', updateRows);
  sortSelect?.addEventListener('change', updateRows);

  const deleteDialog = document.getElementById('order-delete-dialog');
  let deletingRow = null;

  tableBody.addEventListener('click', (event) => {
    const button = event.target.closest('[data-delete-order]');
    if (!button) return;
    deletingRow = button.closest('[data-order-row]');
    document.getElementById('order-delete-title').textContent = `Delete ${deletingRow.querySelector('.orders-id').textContent.trim()}?`;
    deleteDialog.showModal();
  });

  document.querySelectorAll('[data-close-delete]').forEach((button) => {
    button.addEventListener('click', () => deleteDialog.close());
  });

  document.getElementById('order-delete-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!deletingRow) return;
    const row = deletingRow;
    const form = event.currentTarget;
    const submit = form.querySelector('[type="submit"]');
    if (submit.disabled) return;
    const errorMessage = document.getElementById('order-delete-error');
    errorMessage.textContent = '';
    const request = new FormData(form);
    request.set('order_id', row.dataset.orderId);
    submit.disabled = true;
    submit.textContent = 'Deleting…';
    try {
      const response = await fetch(form.getAttribute('action'), {
        method: 'POST',
        body: request,
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      if (!response.headers.get('content-type')?.includes('application/json')) {
        throw new Error('The server returned an unexpected page. Refresh and sign in as an admin, then try again.');
      }
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.error || 'Could not delete the order.');
      updateSummary(result.summary);
      const orderId = row.querySelector('.orders-id').textContent.trim();
      deleteDialog.close();
      row.remove();
      rows = rows.filter((item) => item !== row);
      deletingRow = null;
      updateRows();
      document.getElementById('order-update-message').textContent = `${orderId} deleted from the order list.`;
      searchInput.focus();
    } catch (error) {
      errorMessage.textContent = error.message || 'Connection failed. Please try again.';
      if (!deleteDialog.open) deleteDialog.showModal();
    } finally {
      submit.disabled = false;
      submit.textContent = 'Delete order';
    }
  });

  const editDialog = document.getElementById('order-update-dialog');
  const editStatus = document.getElementById('order-update-status');
  const editPayment = document.getElementById('order-update-payment');
  let editingRow = null;

  tableBody.addEventListener('click', (event) => {
    const button = event.target.closest('[data-edit-order]');
    if (!button) return;
    editingRow = button.closest('[data-order-row]');
    document.getElementById('order-update-title').textContent = `Update ${editingRow.querySelector('.orders-id').textContent.trim()}`;
    document.getElementById('order-update-customer').textContent = editingRow.querySelector('.orders-customer strong').textContent.trim();
    document.getElementById('order-update-email').textContent = editingRow.querySelector('.orders-customer small').textContent.trim();
    document.getElementById('order-update-total').textContent = editingRow.querySelector('.orders-total').textContent.trim();
    editStatus.value = editingRow.dataset.status;
    editPayment.value = editingRow.querySelector('.orders-payment').textContent.trim().toLowerCase();
    editDialog.showModal();
  });

  document.querySelectorAll('[data-close-order]').forEach((button) => {
    button.addEventListener('click', () => editDialog.close());
  });

  document.getElementById('order-update-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!editingRow) return;
    const row = editingRow;
    const form = event.currentTarget;
    const submit = form.querySelector('[type="submit"]');
    if (submit.disabled) return;
    const errorMessage = document.getElementById('order-update-error');
    errorMessage.textContent = '';
    const request = new FormData(form);
    request.set('order_id', row.dataset.orderId);
    submit.disabled = true;
    submit.textContent = 'Saving…';
    try {
      const response = await fetch(form.getAttribute('action'), {
        method: 'POST', body: request, credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      if (!response.headers.get('content-type')?.includes('application/json')) {
        throw new Error('Unexpected server response. Refresh and sign in as an admin.');
      }
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.error || 'Could not update the order.');
      updateSummary(result.summary);
      const setBadge = (selector, prefix, value) => {
        const badge = row.querySelector(selector);
        badge.className = prefix + ' ' + prefix + '-' + value;
        badge.replaceChildren(document.createElement('span'), document.createTextNode(value.charAt(0).toUpperCase() + value.slice(1)));
      };
      row.dataset.status = result.status;
      setBadge('.orders-status', 'orders-status', result.status);
      setBadge('.orders-payment', 'orders-payment', result.payment_status);
      document.getElementById('order-update-message').textContent = row.querySelector('.orders-id').textContent.trim() + ' saved successfully.';
      editDialog.close();
      updateRows();
    } catch (error) {
      errorMessage.textContent = error.message || 'Connection failed. Please try again.';
      if (!editDialog.open) editDialog.showModal();
    } finally {
      submit.disabled = false;
      submit.textContent = 'Save changes';
    }
  });

  exportButton.addEventListener('click', () => {
    const visibleRows = rows.filter((row) => !row.hidden);
    const csvRows = [
      ['Order', 'Customer', 'Email', 'Date', 'Items', 'Total', 'Payment', 'Payment method', 'Status'],
      ...visibleRows.map((row) => {
        const cells = row.querySelectorAll('td');
        return [
          cells[0].innerText.trim(),
          cells[1].querySelector('strong').innerText.trim(),
          cells[1].querySelector('small').innerText.trim(),
          cells[2].innerText.trim(),
          cells[3].innerText.trim(),
          cells[4].innerText.trim(),
          cells[5].querySelector('.orders-payment').innerText.trim(),
          cells[5].querySelector('.orders-method').innerText.trim(),
          cells[6].innerText.trim(),
        ];
      }),
    ];
    const csv = csvRows
      .map((values) => values.map((value) => `"${value.replaceAll('"', '""')}"`).join(','))
      .join('\r\n');
    const file = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(file);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'regix-orders.csv';
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  });
});
