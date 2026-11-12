<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../backend/classes/UsersClass.php';   

$usersObj = new UsersClass();

$loginUrl = '../../backend/auth/login.php';
if (empty($_SESSION['user_id'])) {
    header('Location: ' . $loginUrl);
    exit;
}

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../user/index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $allowedRoles    = ['Admin', 'Customer'];
    $action   = $_POST['action'] ?? '';
    $id       = (int)($_POST['id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $role     = $_POST['role'] ?? '';

    $respond = function (bool $ok, string $msg = '') {
        echo json_encode(['success' => $ok, 'message' => $msg]);
        exit;
    };

    try {
        switch ($action) {
            case 'add':
                if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $respond(false, 'Please enter a valid name and email.');
                }
                if (!in_array($role, $allowedRoles, true)) {
                    $respond(false, 'Invalid role.');
                }
                if (strlen($password) < 8) {
                    $respond(false, 'Password must be at least 8 characters.');
                }
                if ($password !== $confirm) {
                    $respond(false, 'Passwords do not match!');
                }
                if ($usersObj->emailExists($email)) {
                    $respond(false, 'This email is already used.');
                }
                $ok = $usersObj->addUser($name, $email, $password, $role);
                $respond($ok, $ok ? 'User added.' : 'Could not add user.');
                break;

            case 'update':
                if ($id <= 0 || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $respond(false, 'Please enter a valid name and email.');
                }
                if (!in_array($role, $allowedRoles, true)) {
                    $respond(false, 'Invalid role.');
                }
                if ($password !== '') {
                    if (strlen($password) < 8) {
                        $respond(false, 'Password must be at least 8 characters.');
                    }
                    if ($password !== $confirm) {
                        $respond(false, 'Passwords do not match!');
                    }
                }
                if ($usersObj->emailExists($email, $id)) {
                    $respond(false, 'This email is already used.');
                }
                $ok = $usersObj->updateUser($id, $name, $email, $role, $password);
                $respond($ok, $ok ? 'User updated.' : 'Could not update user.');
                break;

            case 'delete':
                if ($id <= 0) {
                    $respond(false, 'Invalid request.');
                }
                $ok = $usersObj->deleteUser($id);
                $respond($ok, $ok ? 'User deleted.' : 'User not found.');
                break;

            default:
                $respond(false, 'Unknown action.');
        }
    } catch (Throwable $e) {
        $respond(false, 'Server error, please try again.');
    }
}


$users = $usersObj->getAllUsers();
$stats = $usersObj->getStats();
$growth = $stats['revenue_growth'];

require_once __DIR__ . '/../layout.php';   

$extraHead = '
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/admin-users.css">
  <style>
    .pagination-bar{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;padding:1rem 0 0;margin-top:.5rem}
    .pagination-info{color:var(--color-text-muted,#a3aa9e);font-size:.85rem}
    .pagination-controls{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}
    .pagination-controls .select-filter{min-width:0;padding:.45rem .75rem}
    .pagination-buttons{display:flex;align-items:center;gap:.35rem;flex-wrap:wrap}
    .page-btn{min-width:36px;height:36px;padding:0 .6rem;border-radius:var(--radius-md,8px);border:1px solid var(--color-border,#292e27);background:var(--color-bg,transparent);color:var(--color-text,#f4f5ef);font-size:.85rem;font-weight:600;cursor:pointer;transition:background .2s,border-color .2s}
    .page-btn:hover:not(:disabled):not(.active){background:var(--color-surface-hover,#1c201b)}
    .page-btn.active{background:var(--color-primary,#a3e635);border-color:var(--color-primary,#a3e635);color:#0b0d0b}
    .page-btn:disabled{opacity:.4;cursor:not-allowed}
    .page-ellipsis{color:var(--color-text-muted,#a3aa9e);padding:0 .25rem}
  </style>
';


page_start('Users', 'users.php', $extraHead);
?>
<link rel="stylesheet" href="../css/users-orders.css?v=<?= filemtime(__DIR__ . '/../css/users-orders.css') ?>">
<div class="users-page orders-page">
<?php

heading('STORE MANAGEMENT', 'Customers & users', 'Manage customers and admins, permissions and spending.');
?>

    <section class="orders-overview users-overview" aria-label="User overview">
      <article class="orders-stat">
        <div class="orders-stat-top"><span>Total customers</span><span class="orders-stat-icon orders-icon-green"><i class="fa-solid fa-users" aria-hidden="true"></i></span></div>
        <strong><?= number_format($stats['total_customers']) ?></strong>
        <small><span class="orders-positive">+<?= number_format($stats['new_this_month']) ?></span> this month</small>
      </article>
      <article class="orders-stat">
        <div class="orders-stat-top"><span>Customer accounts</span><span class="orders-stat-icon orders-icon-blue"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span></div>
        <strong><?= number_format($stats['active_customers']) ?></strong>
        <small>Available to sign in</small>
      </article>
      <article class="orders-stat">
        <div class="orders-stat-top"><span>Total revenue spent</span><span class="orders-stat-icon orders-icon-purple"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span></div>
        <strong><?= number_format($stats['revenue_total'], 2) ?> JOD</strong>
        <small><span><?= $growth >= 0 ? '+' : '' ?><?= e((string) $growth) ?>% vs last month</span></small>
      </article>
    </section>
    <section class="table-container panel orders-panel">

      <div id="pageMessage" class="form-message"></div>

      <div class="table-header-actions">
        <div class="management-panel-title"><h2>All users</h2><p>Review accounts, permissions, and customer activity.</p></div>
        <!-- Search, centered filters, and create action. -->
        <div class="top-controls-row">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Search by name, email..." onkeyup="filterAndSortUsers()">
          </div>


        </div>

      
        <div class="filters-row">
          <select id="filterRole" class="select-filter" aria-label="Filter by role" onchange="filterAndSortUsers()">
            <option value="">All Roles</option>
            <option value="Admin">Admin</option>
            <option value="Customer">Customer</option>
          </select>

          <select id="sortSpent" class="select-filter" aria-label="Sort by spending" onchange="filterAndSortUsers()">
            <option value="">Sort by Spent</option>
            <option value="spent-desc">Spent: High to Low</option>
            <option value="spent-asc">Spent: Low to High</option>
          </select>

          <select id="sortOrders" class="select-filter" aria-label="Sort by order count" onchange="filterAndSortUsers()">
            <option value="">Sort by Orders</option>
            <option value="orders-desc">Orders: High to Low</option>
            <option value="orders-asc">Orders: Low to High</option>
          </select>
        </div>
        <div class="users-create-row">
          <button type="button" class="btn-primary users-add-button" onclick="openAddUserModal()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            <span>Add user</span>
          </button>
        </div>
      </div>


      <div class="users-table-scroll">
      <table class="data-table" id="usersTable">
        <thead>
          <tr>
            <th>User</th>
            <th>Role</th>
            <th>Orders</th>
            <th>Total Spent</th>
            <th>Joined Date</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody id="usersTbody">
          <?php foreach ($users as $u): ?>
          <tr data-id="<?= (int)$u['id'] ?>" data-name="<?= e(mb_strtolower($u['name'])) ?>" data-email="<?= e(mb_strtolower($u['email'])) ?>" data-role="<?= e($u['role']) ?>" data-spent="<?= $u['total_spent'] ?>" data-orders="<?= $u['orders_count'] ?>">
            <td>
              <div class="user-info-cell">
                <div class="user-avatar">
                  <?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?>
                </div>
                <div>
                  <span class="user-name"><?= e($u['name']) ?></span>
                  <span class="user-email"><?= e($u['email']) ?></span>
                </div>
              </div>
            </td>
            <td>
              <span class="badge <?= $u['role'] === 'Admin' ? 'badge-role-admin' : 'badge-role-customer' ?>">
                <?= e($u['role']) ?>
              </span>
            </td>
            <td><?= $u['orders_count'] ?></td>
            <td><strong>JD <?= number_format($u['total_spent'], 2) ?></strong></td>
            <td><?= e($u['created_at']) ?></td>
            <td style="text-align: right;">
              <button class="action-btn" title="Edit User" onclick="openEditUserModal(this)"><i class="fa-solid fa-pen-to-square"></i></button>
              <button class="action-btn delete" title="Delete User" onclick="deleteUser(this)"><i class="fa-solid fa-trash"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>

      <!-- Pagination -->
      <div class="pagination-bar">
        <div class="pagination-info" id="paginationInfo"></div>
        <div class="pagination-controls">
          <select id="pageSize" class="select-filter" onchange="changePageSize()" aria-label="Rows per page">
            <option value="10">10 / page</option>
            <option value="25">25 / page</option>
            <option value="50">50 / page</option>
          </select>
          <div class="pagination-buttons" id="paginationButtons"></div>
        </div>
      </div>

    </section>




  <div id="addUserModal" class="modal-overlay">
    <div class="modal-card">
      <div class="modal-header">
        <h3>Add New User</h3>
        <button class="modal-close" onclick="closeAddUserModal()"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form id="addUserForm" onsubmit="handleAddNewUser(event)">
        <div class="form-group">
          <label for="newUserName">Full Name</label>
          <input type="text" id="newUserName" class="form-control" placeholder="e.g. Ali Ahmed" required>
        </div>
        <div class="form-group">
          <label for="newUserEmail">Email Address</label>
          <input type="email" id="newUserEmail" class="form-control" placeholder="e.g. ali@example.com" required>
        </div>
        <div class="form-group">
          <label for="newUserPassword">Password</label>
          <input type="password" id="newUserPassword" class="form-control" placeholder="********" required>
        </div>
        <div class="form-group">
          <label for="newUserConfirmPassword">Confirm Password</label>
          <input type="password" id="newUserConfirmPassword" class="form-control" placeholder="********" required>
        </div>
        <div class="form-group">
          <label for="newUserRole">Role</label>
          <select id="newUserRole" class="form-control" required>
            <option value="Customer">Customer</option>
            <option value="Admin">Admin</option>
          </select>
        </div>
        <div id="addUserError" class="form-message"></div>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" onclick="closeAddUserModal()">Cancel</button>
          <button type="submit" class="btn-primary">Save User</button>
        </div>
      </form>
    </div>
  </div>

  <div id="editUserModal" class="modal-overlay">
    <div class="modal-card">
      <div class="modal-header">
        <h3>Edit User</h3>
        <button class="modal-close" onclick="closeEditUserModal()"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form id="editUserForm" onsubmit="handleEditUser(event)">
        <input type="hidden" id="editUserId">
        <div class="form-group">
          <label for="editUserName">Full Name</label>
          <input type="text" id="editUserName" class="form-control" required>
        </div>
        <div class="form-group">
          <label for="editUserEmail">Email Address</label>
          <input type="email" id="editUserEmail" class="form-control" required>
        </div>
        <div class="form-group">
          <label for="editUserPassword">New Password (leave empty to keep current)</label>
          <input type="password" id="editUserPassword" class="form-control" placeholder="********">
        </div>
        <div class="form-group">
          <label for="editUserConfirmPassword">Confirm New Password</label>
          <input type="password" id="editUserConfirmPassword" class="form-control" placeholder="********">
        </div>
        <div class="form-group">
          <label for="editUserRole">Role</label>
          <select id="editUserRole" class="form-control" required>
            <option value="Customer">Customer</option>
            <option value="Admin">Admin</option>
          </select>
        </div>
        <div id="editUserError" class="form-message"></div>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" onclick="closeEditUserModal()">Cancel</button>
          <button type="submit" class="btn-primary">Update User</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function showMessage(id, text) {
      const box = document.getElementById(id);
      box.textContent = text;
      box.classList.add('show');
    }

    function hideMessage(id) {
      const box = document.getElementById(id);
      box.textContent = '';
      box.classList.remove('show');
    }

    function openAddUserModal() {
      hideMessage('addUserError');
      document.getElementById('addUserModal').classList.add('active');
    }

    function closeAddUserModal() {
      hideMessage('addUserError');
      document.getElementById('addUserModal').classList.remove('active');
      document.getElementById('addUserForm').reset();
    }

    async function sendRequest(data) {
      try {
        const res = await fetch('users.php', { method: 'POST', body: new URLSearchParams(data) });
        return await res.json();
      } catch (err) {
        return { success: false, message: 'Network or server error.' };
      }
    }

    async function handleAddNewUser(event) {
      event.preventDefault();
      hideMessage('addUserError');

      const password = document.getElementById('newUserPassword').value;
      const confirmPassword = document.getElementById('newUserConfirmPassword').value;

     
      if (password !== confirmPassword) {
        showMessage('addUserError', 'Passwords do not match!');
        return;
      }

      const result = await sendRequest({
        action: 'add',
        name: document.getElementById('newUserName').value,
        email: document.getElementById('newUserEmail').value,
        password: password,
        confirm_password: confirmPassword,
        role: document.getElementById('newUserRole').value
      });

      if (!result.success) {
        showMessage('addUserError', result.message);
        return;
      }

      closeAddUserModal();
      window.location.reload(); 
    }

    function openEditUserModal(button) {
      hideMessage('editUserError');
      const row = button.closest('tr');
      document.getElementById('editUserId').value = row.getAttribute('data-id');
      document.getElementById('editUserName').value = row.querySelector('.user-name').textContent.trim();
      document.getElementById('editUserEmail').value = row.querySelector('.user-email').textContent.trim();
      document.getElementById('editUserRole').value = row.getAttribute('data-role');
      document.getElementById('editUserPassword').value = '';
      document.getElementById('editUserConfirmPassword').value = '';
      document.getElementById('editUserModal').classList.add('active');
    }

    function closeEditUserModal() {
      hideMessage('editUserError');
      document.getElementById('editUserModal').classList.remove('active');
      document.getElementById('editUserForm').reset();
    }

    async function handleEditUser(event) {
      event.preventDefault();
      hideMessage('editUserError');

      const password = document.getElementById('editUserPassword').value;
      const confirmPassword = document.getElementById('editUserConfirmPassword').value;

      if (password !== confirmPassword) {
        showMessage('editUserError', 'Passwords do not match!');
        return;
      }

      const result = await sendRequest({
        action: 'update',
        id: document.getElementById('editUserId').value,
        name: document.getElementById('editUserName').value,
        email: document.getElementById('editUserEmail').value,
        password: password,
        confirm_password: confirmPassword,
        role: document.getElementById('editUserRole').value
      });

      if (!result.success) {
        showMessage('editUserError', result.message);
        return;
      }

      closeEditUserModal();
      window.location.reload();
    }

    async function deleteUser(button) {
      const row = button.closest('tr');
      if (!confirm('Are you sure you want to delete this user?')) return;
      hideMessage('pageMessage');

      const result = await sendRequest({ action: 'delete', id: row.getAttribute('data-id') });

      if (!result.success) {
        showMessage('pageMessage', result.message);
        return;
      }
      row.remove();
      filterAndSortUsers(false);
    }

    let currentPage  = 1;
    let pageSize     = 10;
    let filteredRows = [];

    /* Filter + sort ALL rows, then show only the current page.
       resetPage = true  -> go back to page 1 (search / filter / sort changed)
       resetPage = false -> stay on the current page (e.g. after delete)      */
    function filterAndSortUsers(resetPage = true) {
      const searchVal = document.getElementById('searchInput').value.toLowerCase();
      const roleVal   = document.getElementById('filterRole').value;
      const spentSort  = document.getElementById('sortSpent').value;
      const ordersSort = document.getElementById('sortOrders').value;

      const tbody = document.getElementById('usersTbody');
      const rows  = Array.from(tbody.querySelectorAll('tr'));

      rows.sort((a, b) => {
        const spentA  = parseFloat(a.getAttribute('data-spent')) || 0;
        const spentB  = parseFloat(b.getAttribute('data-spent')) || 0;
        const ordersA = parseInt(a.getAttribute('data-orders')) || 0;
        const ordersB = parseInt(b.getAttribute('data-orders')) || 0;

        if (spentSort === 'spent-desc') return spentB - spentA;
        if (spentSort === 'spent-asc')  return spentA - spentB;
        if (ordersSort === 'orders-desc') return ordersB - ordersA;
        if (ordersSort === 'orders-asc')  return ordersA - ordersB;
        return 0;
      });
      rows.forEach(row => tbody.appendChild(row));

      filteredRows = rows.filter(row => {
        const name   = row.getAttribute('data-name');
        const email  = row.getAttribute('data-email');
        const role   = row.getAttribute('data-role');

        const matchesSearch = name.includes(searchVal) || email.includes(searchVal);
        const matchesRole   = !roleVal   || role === roleVal;
        return matchesSearch && matchesRole;
      });

      rows.forEach(row => { row.style.display = 'none'; });

      if (resetPage) currentPage = 1;
      renderPage();
    }

    function renderPage() {
      const total      = filteredRows.length;
      const totalPages = Math.max(1, Math.ceil(total / pageSize));
      if (currentPage > totalPages) currentPage = totalPages;
      if (currentPage < 1) currentPage = 1;

      const start = (currentPage - 1) * pageSize;
      const end   = Math.min(start + pageSize, total);

      filteredRows.forEach((row, i) => {
        row.style.display = (i >= start && i < end) ? '' : 'none';
      });

      document.getElementById('paginationInfo').textContent =
        total === 0
          ? 'No users found'
          : 'Showing ' + (start + 1) + '-' + end + ' of ' + total + ' users';

      renderPaginationButtons(totalPages);
    }

    function pageNumbers(current, totalPages) {
      if (totalPages <= 7) {
        return Array.from({ length: totalPages }, (_, i) => i + 1);
      }
      const pages = [1];
      const from = Math.max(2, current - 1);
      const to   = Math.min(totalPages - 1, current + 1);
      if (from > 2) pages.push('...');
      for (let p = from; p <= to; p++) pages.push(p);
      if (to < totalPages - 1) pages.push('...');
      pages.push(totalPages);
      return pages;
    }

    function renderPaginationButtons(totalPages) {
      const box = document.getElementById('paginationButtons');
      if (filteredRows.length <= pageSize) {   // everything fits on one page
        box.innerHTML = '';
        return;
      }

      let html = '<button type="button" class="page-btn" onclick="goToPage(' + (currentPage - 1) + ')"' +
                 (currentPage === 1 ? ' disabled' : '') + '>&laquo; Prev</button>';

      pageNumbers(currentPage, totalPages).forEach(p => {
        if (p === '...') {
          html += '<span class="page-ellipsis">&hellip;</span>';
        } else {
          html += '<button type="button" class="page-btn' + (p === currentPage ? ' active' : '') +
                  '" onclick="goToPage(' + p + ')">' + p + '</button>';
        }
      });

      html += '<button type="button" class="page-btn" onclick="goToPage(' + (currentPage + 1) + ')"' +
              (currentPage === totalPages ? ' disabled' : '') + '>Next &raquo;</button>';

      box.innerHTML = html;
    }

    function goToPage(page) {
      currentPage = page;
      renderPage();
      document.getElementById('usersTable').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function changePageSize() {
      pageSize = parseInt(document.getElementById('pageSize').value, 10) || 10;
      currentPage = 1;
      renderPage();
    }

   
    filterAndSortUsers();
  </script>

</div>
<?php page_end();  ?>
