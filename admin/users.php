<?php
include('config.php');
fh_auth_require_login();
if (!fh_auth_can_manage_users()) {
    header('Location: index.php');
    exit;
}

$titleheader = 'Users & Roles';
$adminBusinessId = fh_admin_business_id($conn);
$isSuperAdmin = fh_auth_is_super_admin();
$businesses = fh_get_all_businesses_admin($conn);
$roles = fh_get_manageable_roles();

$userScopeSql = '';
if (!$isSuperAdmin) {
    $userScopeSql = ' AND u.user_business_account = ' . (int)fh_auth_user_business_id()
        . " AND u.user_role <> '" . $conn->real_escape_string(FH_ROLE_SUPER) . "'";
}

$searchQ = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterRole = isset($_GET['role']) ? trim($_GET['role']) : 'all';
$allowedRoleFilters = array_merge(array('all'), $roles);
if (!in_array($filterRole, $allowedRoleFilters, true)) {
    $filterRole = 'all';
}
$filterStatus = isset($_GET['status']) && in_array($_GET['status'], array('all', 'active', 'inactive'), true)
    ? $_GET['status']
    : 'all';

$baseWhere = 'WHERE u.user_id > 0' . $userScopeSql;
if ($searchQ !== '') {
    $safe = $conn->real_escape_string($searchQ);
    $like = "'%" . $safe . "%'";
    $baseWhere .= " AND (
        u.user_name LIKE $like OR
        u.user_fullname LIKE $like OR
        u.user_email LIKE $like OR
        ba.business_name LIKE $like
    )";
}
if ($filterRole !== 'all') {
    $baseWhere .= " AND u.user_role = '" . $conn->real_escape_string($filterRole) . "'";
}
if ($filterStatus === 'active') {
    $baseWhere .= ' AND u.user_status=1';
} elseif ($filterStatus === 'inactive') {
    $baseWhere .= ' AND u.user_status=0';
}

$allowedSort = array(
    'username' => 'u.user_name',
    'fullname' => 'u.user_fullname',
    'email' => 'u.user_email',
    'role' => 'u.user_role',
    'restaurant' => 'ba.business_name',
    'status' => 'u.user_status',
);
$sortKey = isset($_GET['sort']) && isset($allowedSort[$_GET['sort']]) ? $_GET['sort'] : 'role';
$sortCol = $allowedSort[$sortKey];
$sortDir = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'desc') ? 'DESC' : 'ASC';

$allowedPerPage = array(10, 25, 50, 100);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 25;
}

$joinSql = 'FROM user u LEFT JOIN business_account ba ON u.user_business_account = ba.business_id';

$countResult = $conn->query("SELECT COUNT(*) AS c $joinSql $baseWhere");
$totalUsers = ($countResult && ($countRow = $countResult->fetch_assoc())) ? (int)$countRow['c'] : 0;
$totalPages = $totalUsers > 0 ? (int)ceil($totalUsers / $perPage) : 1;
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$showingFrom = $totalUsers > 0 ? $offset + 1 : 0;
$showingTo = min($offset + $perPage, $totalUsers);

$scopeWhere = 'WHERE user_id > 0' . ($isSuperAdmin
    ? ''
    : (' AND user_business_account = ' . (int)fh_auth_user_business_id()
        . " AND user_role <> '" . $conn->real_escape_string(FH_ROLE_SUPER) . "'"));
$activeResult = $conn->query("SELECT COUNT(*) AS c FROM user $scopeWhere AND user_status=1");
$activeCount = ($activeResult && ($arow = $activeResult->fetch_assoc())) ? (int)$arow['c'] : 0;
$inactiveResult = $conn->query("SELECT COUNT(*) AS c FROM user $scopeWhere AND user_status=0");
$inactiveCount = ($inactiveResult && ($irow = $inactiveResult->fetch_assoc())) ? (int)$irow['c'] : 0;

$users = array();
$usersSql = $conn->query(
    "SELECT u.*, ba.business_name
     $joinSql
     $baseWhere
     ORDER BY $sortCol $sortDir, u.user_fullname ASC
     LIMIT $offset, $perPage"
);
if ($usersSql) {
    while ($row = $usersSql->fetch_assoc()) {
        $users[] = $row;
    }
}

function admin_users_list_url($overrides = array()) {
    global $page, $perPage, $searchQ, $filterRole, $filterStatus, $sortKey, $sortDir, $adminBusinessId, $isSuperAdmin;
    $params = array(
        'page' => $page,
        'per_page' => $perPage,
        'sort' => $sortKey,
        'dir' => strtolower($sortDir),
        'role' => $filterRole,
        'status' => $filterStatus,
    );
    if ($isSuperAdmin) {
        $params['business_id'] = $adminBusinessId;
    }
    if ($searchQ !== '') {
        $params['q'] = $searchQ;
    }
    $params = array_merge($params, $overrides);
    if (isset($params['q']) && $params['q'] === '') {
        unset($params['q']);
    }
    return 'users.php?' . http_build_query($params);
}

function admin_users_sort_url($column) {
    global $sortKey, $sortDir, $page;
    $newDir = 'asc';
    if ($column === $sortKey && strtolower($sortDir) === 'asc') {
        $newDir = 'desc';
    }
    return admin_users_list_url(array(
        'sort' => $column,
        'dir' => $newDir,
        'page' => (int)$page,
    ));
}

function admin_users_sort_indicator($column) {
    global $sortKey, $sortDir;
    if ($column !== $sortKey) {
        return '<span class="portal-orders-sort-icon" aria-hidden="true">↕</span>';
    }
    return strtolower($sortDir) === 'asc'
        ? '<span class="portal-orders-sort-icon is-active" aria-hidden="true">↑</span>'
        : '<span class="portal-orders-sort-icon is-active" aria-hidden="true">↓</span>';
}

function admin_users_role_class($role) {
    if ($role === FH_ROLE_SUPER) {
        return 'is-danger';
    }
    if ($role === FH_ROLE_BUSINESS_ADMIN) {
        return 'is-success';
    }
    return 'is-neutral';
}

include('header.php');
include('navigation.php');
?>

<link rel="stylesheet" href="../css/portal-order.css?v=20260714b" type="text/css" />
<style>
.admin-orders-filter {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 14px 18px;
    padding: 16px 18px;
    margin: 0 0 20px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.06);
}
.admin-orders-filter .admin-users-filter-spacer {
    flex: 1 1 auto;
    min-width: 12px;
}
.admin-orders-filter label {
    display: block;
    margin: 0 0 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
}
.admin-orders-filter input[type="search"],
.admin-orders-filter select {
    padding: 8px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
    background: #fff;
    min-width: 150px;
}
.admin-orders-filter button,
.admin-users-toolbar-btn {
    padding: 9px 18px;
    border: none;
    border-radius: 8px;
    background: #2d6a4f;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}
.admin-orders-filter button:hover,
.admin-users-toolbar-btn:hover {
    background: #1b4332;
}
.admin-users-tip {
    margin: 0 0 16px;
    padding: 12px 14px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 10px;
    color: #065f46;
    font-size: 13px;
    line-height: 1.5;
}
.admin-users-tip em {
    font-style: normal;
    font-weight: 700;
}
.portal-order-modal-dialog-user {
    width: min(560px, calc(100% - 32px));
}
.portal-order-modal-section > label {
    display: block;
    margin: 0 0 6px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
}
.portal-order-modal-section > input[type="text"],
.portal-order-modal-section > input[type="email"],
.portal-order-modal-section > input[type="password"],
.portal-order-modal-section > select {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
    margin: 0 0 14px;
    background: #fff;
}
.portal-order-modal-section .admin-users-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.portal-order-modal-section .admin-users-row label {
    display: block;
    margin: 0 0 6px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
}
.portal-order-modal-section .admin-users-row input,
.portal-order-modal-section .admin-users-row select {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
    margin: 0 0 14px;
    background: #fff;
}
.portal-login-panel-alert {
    margin: 0 0 14px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    font-size: 13px;
}
@media (max-width: 640px) {
    .portal-order-modal-section .admin-users-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal">
        <h1>Users &amp; Roles</h1>
        <p>Create staff accounts and assign restaurant access by role.</p>
    </div>

    <?php echo fh_admin_business_selector($conn, 'users.php'); ?>

    <p class="admin-users-tip">
        <strong>Roles:</strong>
        <em>Super Admin</em> — full portal access.
        <em>Business Admin</em> — manage one restaurant (items, menu, orders, store page, staff).
        <em>Business Staff</em> — view/update menu and orders for assigned restaurant.
    </p>

    <form class="admin-orders-filter" method="get" action="users.php">
        <?php if ($isSuperAdmin): ?>
        <input type="hidden" name="business_id" value="<?php echo (int)$adminBusinessId; ?>">
        <?php endif; ?>
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
        <input type="hidden" name="dir" value="<?php echo htmlspecialchars(strtolower($sortDir)); ?>">
        <input type="hidden" name="per_page" value="<?php echo (int)$perPage; ?>">
        <input type="hidden" name="page" value="1">
        <div>
            <label for="filterSearch">Search</label>
            <input type="search" id="filterSearch" name="q" value="<?php echo htmlspecialchars($searchQ); ?>" placeholder="Username, name, email…">
        </div>
        <div>
            <label for="filterRole">Role</label>
            <select id="filterRole" name="role">
                <option value="all"<?php echo $filterRole === 'all' ? ' selected' : ''; ?>>All roles</option>
                <?php foreach ($roles as $roleOption): ?>
                <option value="<?php echo htmlspecialchars($roleOption); ?>"<?php echo $filterRole === $roleOption ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars(fh_auth_role_label($roleOption)); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="filterStatus">Status</label>
            <select id="filterStatus" name="status">
                <option value="all"<?php echo $filterStatus === 'all' ? ' selected' : ''; ?>>All statuses</option>
                <option value="active"<?php echo $filterStatus === 'active' ? ' selected' : ''; ?>>Active</option>
                <option value="inactive"<?php echo $filterStatus === 'inactive' ? ' selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <div>
            <label>&nbsp;</label>
            <button type="submit">Apply Filter</button>
        </div>
        <div class="admin-users-filter-spacer" aria-hidden="true"></div>
        <div>
            <label>&nbsp;</label>
            <button type="button" class="admin-users-toolbar-btn" id="btnAddUser">Add User</button>
        </div>
    </form>

    <div class="portal-orders-summary">
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($totalUsers); ?></strong>
            <span>Showing<?php echo ($searchQ !== '' || $filterRole !== 'all' || $filterStatus !== 'all') ? ' (filtered)' : ''; ?></span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($activeCount); ?></strong>
            <span>Active</span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($inactiveCount); ?></strong>
            <span>Inactive</span>
        </div>
    </div>

    <div class="portal-cart-panel portal-orders-panel">
        <div class="portal-cart-panel-head">
            <h2>User Accounts</h2>
            <?php if ($totalUsers > 0): ?>
                <span class="portal-cart-count"><?php echo number_format($totalUsers); ?> user<?php echo $totalUsers === 1 ? '' : 's'; ?></span>
            <?php endif; ?>
        </div>

        <?php if (empty($users)): ?>
            <div class="portal-cart-empty portal-orders-empty">
                <div class="portal-cart-empty-icon">👤</div>
                <h2>No users found</h2>
                <p><?php echo ($searchQ !== '' || $filterRole !== 'all' || $filterStatus !== 'all')
                    ? 'No users match this filter. Try another search, role, or status.'
                    : 'No users yet. Click <strong>Add User</strong> to create one.'; ?></p>
            </div>
        <?php else: ?>
            <div class="portal-orders-table-toolbar">
                <p class="portal-orders-showing">
                    Showing <strong><?php echo number_format($showingFrom); ?></strong>–<strong><?php echo number_format($showingTo); ?></strong>
                    of <strong><?php echo number_format($totalUsers); ?></strong>
                </p>
                <form class="portal-orders-per-page-form" method="get" action="users.php">
                    <?php if ($isSuperAdmin): ?>
                    <input type="hidden" name="business_id" value="<?php echo (int)$adminBusinessId; ?>">
                    <?php endif; ?>
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
                    <input type="hidden" name="dir" value="<?php echo htmlspecialchars(strtolower($sortDir)); ?>">
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($filterRole); ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filterStatus); ?>">
                    <?php if ($searchQ !== ''): ?>
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQ); ?>">
                    <?php endif; ?>
                    <input type="hidden" name="page" value="1">
                    <label for="perPageSelect">Rows per page</label>
                    <select id="perPageSelect" name="per_page" class="portal-orders-per-page-select" onchange="this.form.submit()">
                        <?php foreach ($allowedPerPage as $option): ?>
                            <option value="<?php echo $option; ?>"<?php echo $perPage === $option ? ' selected' : ''; ?>><?php echo $option; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <div class="portal-orders-table-wrap">
                <table class="portal-orders-table" id="userTable">
                    <thead>
                        <tr>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_users_sort_url('username')); ?>">
                                    Username <?php echo admin_users_sort_indicator('username'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_users_sort_url('fullname')); ?>">
                                    Full Name <?php echo admin_users_sort_indicator('fullname'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_users_sort_url('email')); ?>">
                                    Email <?php echo admin_users_sort_indicator('email'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_users_sort_url('role')); ?>">
                                    Role <?php echo admin_users_sort_indicator('role'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_users_sort_url('restaurant')); ?>">
                                    Restaurant <?php echo admin_users_sort_indicator('restaurant'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_users_sort_url('status')); ?>">
                                    Status <?php echo admin_users_sort_indicator('status'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-col-action">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $row):
                            $userId = (int)$row['user_id'];
                            $isActive = (int)$row['user_status'] === 1;
                            $roleClass = admin_users_role_class($row['user_role']);
                        ?>
                        <tr class="portal-orders-row" data-user-id="<?php echo $userId; ?>">
                            <td><strong><?php echo htmlspecialchars($row['user_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['user_fullname'] ?: '—'); ?></td>
                            <td><?php echo htmlspecialchars($row['user_email'] ?: '—'); ?></td>
                            <td><span class="portal-orders-status <?php echo $roleClass; ?>"><?php echo htmlspecialchars(fh_auth_role_label($row['user_role'])); ?></span></td>
                            <td><?php echo $row['business_name'] ? htmlspecialchars($row['business_name']) : '— Portal —'; ?></td>
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="portal-orders-status is-success">Active</span>
                                <?php else: ?>
                                    <span class="portal-orders-status is-warning">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="portal-orders-col-action">
                                <div class="portal-orders-actions">
                                    <button type="button" class="portal-orders-action-btn btn-user-edit" data-userid="<?php echo $userId; ?>" title="Edit user" aria-label="Edit user #<?php echo $userId; ?>">✎</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="portal-orders-pagination">
                <?php if ($page > 1): ?>
                    <a class="portal-orders-page-btn" href="<?php echo htmlspecialchars(admin_users_list_url(array('page' => $page - 1))); ?>">&larr; Previous</a>
                <?php else: ?>
                    <span class="portal-orders-page-btn is-disabled">&larr; Previous</span>
                <?php endif; ?>

                <div class="portal-orders-page-numbers">
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($p = $startPage; $p <= $endPage; $p++):
                    ?>
                        <?php if ($p === $page): ?>
                            <span class="portal-orders-page-num is-current"><?php echo $p; ?></span>
                        <?php else: ?>
                            <a class="portal-orders-page-num" href="<?php echo htmlspecialchars(admin_users_list_url(array('page' => $p))); ?>"><?php echo $p; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <?php if ($page < $totalPages): ?>
                    <a class="portal-orders-page-btn" href="<?php echo htmlspecialchars(admin_users_list_url(array('page' => $page + 1))); ?>">Next &rarr;</a>
                <?php else: ?>
                    <span class="portal-orders-page-btn is-disabled">Next &rarr;</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="portal-order-modal" id="userModal" aria-hidden="true">
    <div class="portal-order-modal-backdrop"></div>
    <div class="portal-order-modal-dialog portal-order-modal-dialog-user" role="dialog" aria-labelledby="userModalTitle">
        <div class="portal-order-modal-header">
            <h2 id="userModalTitle">Add User</h2>
            <button type="button" class="portal-order-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="portal-order-modal-body">
            <div id="userFormMsg" class="portal-login-panel-alert" style="display:none" role="alert"></div>
            <div class="portal-order-modal-section">
                <input type="hidden" id="user_id" value="0">

                <label for="user_name">Username *</label>
                <input type="text" id="user_name" maxlength="15" required>

                <label for="user_fullname">Full Name *</label>
                <input type="text" id="user_fullname" required>

                <label for="user_email">Email</label>
                <input type="email" id="user_email">

                <label for="user_password">Password <span id="passwordHint" class="portal-order-modal-hint" style="display:inline;margin:0;">(required for new users)</span></label>
                <input type="password" id="user_password" autocomplete="new-password">

                <div class="admin-users-row">
                    <div>
                        <label for="user_role">Role *</label>
                        <select id="user_role">
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo htmlspecialchars($role); ?>"><?php echo htmlspecialchars(fh_auth_role_label($role)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="user_status">Status</label>
                        <select id="user_status">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>

                <?php if ($isSuperAdmin): ?>
                <label for="user_business_account">Restaurant</label>
                <select id="user_business_account">
                    <option value="0">— Portal / Super Admin —</option>
                    <?php foreach ($businesses as $biz): ?>
                    <option value="<?php echo (int)$biz['business_id']; ?>"><?php echo htmlspecialchars($biz['business_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="portal-order-modal-hint">Required for Business Admin and Business Staff roles.</p>
                <?php else: ?>
                <input type="hidden" id="user_business_account" value="<?php echo (int)fh_auth_user_business_id(); ?>">
                <?php endif; ?>
            </div>
        </div>
        <div class="portal-order-modal-footer">
            <button type="button" class="portal-login-btn portal-login-btn-secondary portal-order-modal-dismiss">Cancel</button>
            <button type="button" class="portal-login-btn portal-login-btn-primary" id="btnSaveUser">Save User</button>
        </div>
    </div>
</div>

<script>
(function ($) {
    var $modal = $('#userModal');
    var defaultBizId = <?php echo $isSuperAdmin ? 0 : (int)fh_auth_user_business_id(); ?>;

    function openModal($el) {
        $el.addClass('is-open').attr('aria-hidden', 'false');
        $('body').css('overflow', 'hidden');
    }

    function closeModal($el) {
        $el.removeClass('is-open').attr('aria-hidden', 'true');
        if (!$('.portal-order-modal.is-open').length) {
            $('body').css('overflow', '');
        }
    }

    function resetForm() {
        $('#user_id').val(0);
        $('#user_name, #user_fullname, #user_email, #user_password').val('');
        $('#user_role').val('business_staff');
        $('#user_business_account').val(defaultBizId);
        $('#user_status').val(1);
        $('#userFormMsg').hide().text('');
        $('#userModalTitle').text('Add User');
        $('#passwordHint').text('(required for new users)');
    }

    function showUserForm(userId) {
        userId = userId || 0;
        resetForm();
        $('#user_id').val(userId);
        $('#userModalTitle').text(userId ? 'Edit User #' + userId : 'Add User');
        $('#passwordHint').text(userId ? '(leave blank to keep current password)' : '(required for new users)');

        if (!userId) {
            openModal($modal);
            return;
        }

        $.post('process.php', { function: 'LoadUser', user_id: userId }, function (data) {
            if (!data.user_id) {
                $('#userFormMsg').text(data.error || 'Could not load user.').show();
                openModal($modal);
                return;
            }
            $('#user_name').val(data.user_name || '');
            $('#user_fullname').val(data.user_fullname || '');
            $('#user_email').val(data.user_email || '');
            $('#user_role').val(data.user_role || 'business_staff');
            $('#user_business_account').val(data.user_business_account || 0);
            $('#user_status').val(String(parseInt(data.user_status, 10) === 1 ? 1 : 0));
            $('#user_password').val('');
            openModal($modal);
        }, 'json').fail(function () {
            $('#userFormMsg').text('Could not load user.').show();
            openModal($modal);
        });
    }

    function saveUser() {
        var $btn = $('#btnSaveUser').prop('disabled', true);
        $('#userFormMsg').hide().text('');

        $.post('process.php', {
            function: 'SaveUser',
            user_id: $('#user_id').val(),
            user_name: $('#user_name').val(),
            user_fullname: $('#user_fullname').val(),
            user_email: $('#user_email').val(),
            user_password: $('#user_password').val(),
            user_role: $('#user_role').val(),
            user_business_account: $('#user_business_account').val(),
            user_status: $('#user_status').val()
        }, function (data) {
            if (data.msg === true) {
                window.location.reload();
                return;
            }
            $('#userFormMsg').text(data.error || 'Unable to save user.').show();
            $btn.prop('disabled', false);
        }, 'json').fail(function () {
            $('#userFormMsg').text('Something went wrong. Please try again.').show();
            $btn.prop('disabled', false);
        });
    }

    $('#btnAddUser').on('click', function () {
        showUserForm(0);
    });

    $(document).on('click', '.btn-user-edit', function () {
        showUserForm(parseInt($(this).data('userid'), 10) || 0);
    });

    $('#btnSaveUser').on('click', saveUser);

    $('.portal-order-modal-close, .portal-order-modal-dismiss').on('click', function () {
        closeModal($(this).closest('.portal-order-modal'));
    });

    $('.portal-order-modal-backdrop').on('click', function () {
        closeModal($(this).closest('.portal-order-modal'));
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal($('.portal-order-modal.is-open').last());
        }
    });
})(jQuery);
</script>

<?php include('footer.php'); ?>
