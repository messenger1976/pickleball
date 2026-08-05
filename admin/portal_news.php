<?php
include('config.php');
fh_auth_require_super_admin();

include_once(__DIR__ . '/../includes/portal_news_helper.php');

$titleheader = 'Portal News';

$searchQ = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterType = isset($_GET['type']) && in_array($_GET['type'], array('all', 'default', 'daily', 'general'), true)
    ? $_GET['type']
    : 'all';
$filterStatus = isset($_GET['status']) && in_array($_GET['status'], array('all', 'active', 'inactive'), true)
    ? $_GET['status']
    : 'all';

$baseWhere = 'WHERE 1=1';
if ($searchQ !== '') {
    $safe = $conn->real_escape_string($searchQ);
    $like = "'%" . $safe . "%'";
    $baseWhere .= " AND (news_title LIKE $like OR news_message LIKE $like OR news_link_label LIKE $like)";
}
if ($filterType === 'default') {
    $baseWhere .= ' AND news_is_default=1';
} elseif ($filterType === 'daily') {
    $baseWhere .= ' AND news_is_default=0 AND news_date IS NOT NULL';
} elseif ($filterType === 'general') {
    $baseWhere .= ' AND news_is_default=0 AND news_date IS NULL';
}
if ($filterStatus === 'active') {
    $baseWhere .= ' AND news_status=1';
} elseif ($filterStatus === 'inactive') {
    $baseWhere .= ' AND news_status=0';
}

$allowedSort = array(
    'title' => 'news_title',
    'type' => 'news_is_default',
    'date' => 'news_date',
    'status' => 'news_status',
    'updated' => 'news_updated_at',
);
$sortKey = isset($_GET['sort']) && isset($allowedSort[$_GET['sort']]) ? $_GET['sort'] : 'updated';
$sortCol = $allowedSort[$sortKey];
$sortDir = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';
if ($sortKey === 'updated' && !isset($_GET['dir'])) {
    $sortDir = 'DESC';
}

$allowedPerPage = array(10, 25, 50, 100);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 25;
}

$tableExists = fh_portal_news_table_exists($conn);
$totalNews = 0;
$activeCount = 0;
$defaultCount = 0;
$dailyCount = 0;
$newsItems = array();
$totalPages = 1;
$showingFrom = 0;
$showingTo = 0;

if ($tableExists) {
    $countResult = $conn->query("SELECT COUNT(*) AS c FROM portal_news $baseWhere");
    $totalNews = ($countResult && ($countRow = $countResult->fetch_assoc())) ? (int)$countRow['c'] : 0;
    $totalPages = $totalNews > 0 ? (int)ceil($totalNews / $perPage) : 1;
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;
    $showingFrom = $totalNews > 0 ? $offset + 1 : 0;
    $showingTo = min($offset + $perPage, $totalNews);

    $activeResult = $conn->query('SELECT COUNT(*) AS c FROM portal_news WHERE news_status=1');
    $activeCount = ($activeResult && ($arow = $activeResult->fetch_assoc())) ? (int)$arow['c'] : 0;
    $defaultResult = $conn->query('SELECT COUNT(*) AS c FROM portal_news WHERE news_is_default=1');
    $defaultCount = ($defaultResult && ($drow = $defaultResult->fetch_assoc())) ? (int)$drow['c'] : 0;
    $dailyResult = $conn->query('SELECT COUNT(*) AS c FROM portal_news WHERE news_is_default=0 AND news_date IS NOT NULL');
    $dailyCount = ($dailyResult && ($yrow = $dailyResult->fetch_assoc())) ? (int)$yrow['c'] : 0;

    $secondarySort = $sortKey === 'type' ? ', news_date DESC, news_id DESC' : ', news_id DESC';
    $newsSql = $conn->query(
        "SELECT * FROM portal_news
         $baseWhere
         ORDER BY $sortCol $sortDir$secondarySort
         LIMIT $offset, $perPage"
    );
    if ($newsSql) {
        while ($row = $newsSql->fetch_assoc()) {
            $newsItems[] = $row;
        }
    }
}

function admin_news_list_url($overrides = array()) {
    global $page, $perPage, $searchQ, $filterType, $filterStatus, $sortKey, $sortDir;
    $params = array(
        'page' => $page,
        'per_page' => $perPage,
        'sort' => $sortKey,
        'dir' => strtolower($sortDir),
        'type' => $filterType,
        'status' => $filterStatus,
    );
    if ($searchQ !== '') {
        $params['q'] = $searchQ;
    }
    $params = array_merge($params, $overrides);
    if (isset($params['q']) && $params['q'] === '') {
        unset($params['q']);
    }
    return 'portal_news.php?' . http_build_query($params);
}

function admin_news_sort_url($column) {
    global $sortKey, $sortDir, $page;
    $newDir = 'desc';
    if ($column === $sortKey && strtolower($sortDir) === 'desc') {
        $newDir = 'asc';
    }
    return admin_news_list_url(array(
        'sort' => $column,
        'dir' => $newDir,
        'page' => (int)$page,
    ));
}

function admin_news_sort_indicator($column) {
    global $sortKey, $sortDir;
    if ($column !== $sortKey) {
        return '<span class="portal-orders-sort-icon" aria-hidden="true">↕</span>';
    }
    return strtolower($sortDir) === 'asc'
        ? '<span class="portal-orders-sort-icon is-active" aria-hidden="true">↑</span>'
        : '<span class="portal-orders-sort-icon is-active" aria-hidden="true">↓</span>';
}

include('header.php');
include('navigation.php');
?>

<link rel="stylesheet" href="../css/portal-order.css?v=20260804c" type="text/css" />
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
.admin-orders-filter .admin-news-filter-spacer {
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
.admin-news-toolbar-btn {
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
.admin-news-toolbar-btn:hover {
    background: #1b4332;
}
.admin-news-tip {
    margin: 0 0 16px;
    padding: 12px 14px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 10px;
    color: #065f46;
    font-size: 13px;
    line-height: 1.5;
}
.admin-news-tip em {
    font-style: normal;
    font-weight: 700;
}
.admin-news-message {
    margin-top: 4px;
    color: #64748b;
    font-size: 12px;
    line-height: 1.4;
}
.portal-order-modal-dialog-news {
    width: min(600px, calc(100% - 32px));
}
.portal-order-modal-section > label {
    display: block;
    margin: 0 0 6px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
}
.portal-order-modal-section > input[type="text"],
.portal-order-modal-section > input[type="url"],
.portal-order-modal-section > input[type="date"],
.portal-order-modal-section > textarea {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
    margin: 0 0 14px;
    background: #fff;
}
.portal-order-modal-section > textarea {
    resize: vertical;
    min-height: 100px;
}
.portal-order-modal-section .admin-news-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.portal-order-modal-section .admin-news-row label {
    display: block;
    margin: 0 0 6px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
}
.portal-order-modal-section .admin-news-row input {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
    margin: 0 0 14px;
    background: #fff;
}
.portal-order-modal-section .admin-news-check {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: 4px 0 10px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
}
.portal-order-modal-section .admin-news-check input {
    width: auto;
    margin: 2px 0 0;
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
    .portal-order-modal-section .admin-news-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal">
        <h1>Portal News</h1>
        <p>Manage announcements shown on the portal home page hero.</p>
    </div>

    <p class="admin-news-tip">
        <strong>How it works:</strong>
        A <em>daily</em> news item with today's date takes priority on the home page.
        If none is set for today, the <em>default</em> news item is shown instead.
        Only active items are displayed.
    </p>

    <?php if (!$tableExists): ?>
    <p class="admin-news-tip" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">
        Portal news table is not installed. Run <code>sql/migrate_portal_news.sql</code> first.
    </p>
    <?php endif; ?>

    <form class="admin-orders-filter" method="get" action="portal_news.php">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
        <input type="hidden" name="dir" value="<?php echo htmlspecialchars(strtolower($sortDir)); ?>">
        <input type="hidden" name="per_page" value="<?php echo (int)$perPage; ?>">
        <input type="hidden" name="page" value="1">
        <div>
            <label for="filterSearch">Search</label>
            <input type="search" id="filterSearch" name="q" value="<?php echo htmlspecialchars($searchQ); ?>" placeholder="Title or message…">
        </div>
        <div>
            <label for="filterType">Type</label>
            <select id="filterType" name="type">
                <option value="all"<?php echo $filterType === 'all' ? ' selected' : ''; ?>>All types</option>
                <option value="default"<?php echo $filterType === 'default' ? ' selected' : ''; ?>>Default</option>
                <option value="daily"<?php echo $filterType === 'daily' ? ' selected' : ''; ?>>Daily</option>
                <option value="general"<?php echo $filterType === 'general' ? ' selected' : ''; ?>>General</option>
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
        <div class="admin-news-filter-spacer" aria-hidden="true"></div>
        <div>
            <label>&nbsp;</label>
            <button type="button" class="admin-news-toolbar-btn" id="btnAddNews"<?php echo !$tableExists ? ' disabled' : ''; ?>>Add News</button>
        </div>
    </form>

    <div class="portal-orders-summary">
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($totalNews); ?></strong>
            <span>Showing<?php echo ($searchQ !== '' || $filterType !== 'all' || $filterStatus !== 'all') ? ' (filtered)' : ''; ?></span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($activeCount); ?></strong>
            <span>Active</span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($defaultCount); ?> / <?php echo number_format($dailyCount); ?></strong>
            <span>Default / Daily</span>
        </div>
    </div>

    <div class="portal-cart-panel portal-orders-panel">
        <div class="portal-cart-panel-head">
            <h2>News Items</h2>
            <?php if ($totalNews > 0): ?>
                <span class="portal-cart-count"><?php echo number_format($totalNews); ?> item<?php echo $totalNews === 1 ? '' : 's'; ?></span>
            <?php endif; ?>
        </div>

        <?php if (empty($newsItems)): ?>
            <div class="portal-cart-empty portal-orders-empty">
                <div class="portal-cart-empty-icon">📰</div>
                <h2>No news items found</h2>
                <p><?php
                    if (!$tableExists) {
                        echo 'Install the portal news table to get started.';
                    } elseif ($searchQ !== '' || $filterType !== 'all' || $filterStatus !== 'all') {
                        echo 'No news items match this filter. Try another search, type, or status.';
                    } else {
                        echo 'No news items yet. Click <strong>Add News</strong> to create one.';
                    }
                ?></p>
            </div>
        <?php else: ?>
            <div class="portal-orders-table-toolbar">
                <p class="portal-orders-showing">
                    Showing <strong><?php echo number_format($showingFrom); ?></strong>–<strong><?php echo number_format($showingTo); ?></strong>
                    of <strong><?php echo number_format($totalNews); ?></strong>
                </p>
                <form class="portal-orders-per-page-form" method="get" action="portal_news.php">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
                    <input type="hidden" name="dir" value="<?php echo htmlspecialchars(strtolower($sortDir)); ?>">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($filterType); ?>">
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
                <table class="portal-orders-table" id="newsTable">
                    <thead>
                        <tr>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_news_sort_url('title')); ?>">
                                    Title <?php echo admin_news_sort_indicator('title'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_news_sort_url('type')); ?>">
                                    Type <?php echo admin_news_sort_indicator('type'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_news_sort_url('date')); ?>">
                                    Date <?php echo admin_news_sort_indicator('date'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_news_sort_url('status')); ?>">
                                    Status <?php echo admin_news_sort_indicator('status'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_news_sort_url('updated')); ?>">
                                    Updated <?php echo admin_news_sort_indicator('updated'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-col-action">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($newsItems as $item):
                            $newsId = (int)$item['news_id'];
                            $isDefault = (int)$item['news_is_default'] === 1;
                            $hasDate = !empty($item['news_date']);
                            $isActive = (int)$item['news_status'] === 1;
                            $preview = strlen($item['news_message']) > 120
                                ? substr($item['news_message'], 0, 120) . '…'
                                : $item['news_message'];
                        ?>
                        <tr class="portal-orders-row" data-news-id="<?php echo $newsId; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($item['news_title']); ?></strong>
                                <div class="admin-news-message"><?php echo htmlspecialchars($preview); ?></div>
                            </td>
                            <td>
                                <?php if ($isDefault): ?>
                                    <span class="portal-orders-status is-success">Default</span>
                                <?php elseif ($hasDate): ?>
                                    <span class="portal-orders-status is-warning">Daily</span>
                                <?php else: ?>
                                    <span class="portal-orders-status is-neutral">General</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $hasDate ? htmlspecialchars(date('M j, Y', strtotime($item['news_date']))) : '—'; ?></td>
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="portal-orders-status is-success">Active</span>
                                <?php else: ?>
                                    <span class="portal-orders-status is-neutral">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($item['news_updated_at']) ? date('M j, Y g:i A', strtotime($item['news_updated_at'])) : '—'; ?></td>
                            <td class="portal-orders-col-action">
                                <div class="portal-orders-actions">
                                    <button type="button" class="portal-orders-action-btn btn-news-edit" data-newsid="<?php echo $newsId; ?>" title="Edit news" aria-label="Edit news #<?php echo $newsId; ?>">✎</button>
                                    <button type="button" class="portal-orders-action-btn portal-orders-action-cancel btn-news-delete" data-newsid="<?php echo $newsId; ?>" title="Delete news" aria-label="Delete news #<?php echo $newsId; ?>">✕</button>
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
                    <a class="portal-orders-page-btn" href="<?php echo htmlspecialchars(admin_news_list_url(array('page' => $page - 1))); ?>">&larr; Previous</a>
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
                            <a class="portal-orders-page-num" href="<?php echo htmlspecialchars(admin_news_list_url(array('page' => $p))); ?>"><?php echo $p; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <?php if ($page < $totalPages): ?>
                    <a class="portal-orders-page-btn" href="<?php echo htmlspecialchars(admin_news_list_url(array('page' => $page + 1))); ?>">Next &rarr;</a>
                <?php else: ?>
                    <span class="portal-orders-page-btn is-disabled">Next &rarr;</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="portal-order-modal" id="newsModal" aria-hidden="true">
    <div class="portal-order-modal-backdrop"></div>
    <div class="portal-order-modal-dialog portal-order-modal-dialog-news" role="dialog" aria-labelledby="newsModalTitle">
        <div class="portal-order-modal-header">
            <h2 id="newsModalTitle">Add News</h2>
            <button type="button" class="portal-order-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="portal-order-modal-body">
            <div id="newsFormMsg" class="portal-login-panel-alert" style="display:none" role="alert"></div>
            <div class="portal-order-modal-section">
                <input type="hidden" id="news_id" value="0">

                <label for="news_title">Title *</label>
                <input type="text" id="news_title" maxlength="200" placeholder="e.g. Weekend Promo" required>

                <label for="news_message">Message *</label>
                <textarea id="news_message" rows="4" placeholder="Short announcement shown on the home page hero" required></textarea>

                <div class="admin-news-row">
                    <div>
                        <label for="news_link_url">Link URL</label>
                        <input type="text" id="news_link_url" placeholder="e.g. signup.php or https://…">
                    </div>
                    <div>
                        <label for="news_link_label">Link Label</label>
                        <input type="text" id="news_link_label" value="Learn more">
                    </div>
                </div>

                <label for="news_date">News Date</label>
                <input type="date" id="news_date">
                <p class="portal-order-modal-hint">For daily news — leave blank for default/general items.</p>

                <label class="admin-news-check" for="news_is_default">
                    <input type="checkbox" id="news_is_default">
                    <span>Use as <strong>default</strong> news (shown when no daily news matches today)</span>
                </label>
                <label class="admin-news-check" for="news_status">
                    <input type="checkbox" id="news_status" checked>
                    <span>Active (visible on portal)</span>
                </label>
            </div>
        </div>
        <div class="portal-order-modal-footer">
            <button type="button" class="portal-login-btn portal-login-btn-secondary portal-order-modal-dismiss">Cancel</button>
            <button type="button" class="portal-login-btn portal-login-btn-primary" id="btnSaveNews">Save News</button>
        </div>
    </div>
</div>

<div class="portal-order-modal" id="newsDeleteModal" aria-hidden="true">
    <div class="portal-order-modal-backdrop"></div>
    <div class="portal-order-modal-dialog portal-order-modal-dialog-sm" role="dialog">
        <div class="portal-order-modal-header">
            <h2>Delete News <span id="newsDeleteId"></span></h2>
            <button type="button" class="portal-order-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="portal-order-modal-body">
            <div id="newsDeleteMsg" class="portal-login-panel-alert" style="display:none" role="alert"></div>
            <p class="portal-login-lead">Are you sure you want to delete this news item? This cannot be undone.</p>
        </div>
        <div class="portal-order-modal-footer">
            <button type="button" class="portal-login-btn portal-login-btn-secondary portal-order-modal-dismiss">Keep News</button>
            <button type="button" class="portal-login-btn portal-login-btn-primary portal-orders-cancel-confirm" id="btnConfirmDeleteNews">Confirm Delete</button>
        </div>
    </div>
</div>

<script>
(function ($) {
    var $modal = $('#newsModal');
    var $deleteModal = $('#newsDeleteModal');
    var pendingDeleteId = 0;

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
        $('#news_id').val(0);
        $('#news_title').val('');
        $('#news_message').val('');
        $('#news_link_url').val('');
        $('#news_link_label').val('Learn more');
        $('#news_date').val('');
        $('#news_is_default').prop('checked', false);
        $('#news_status').prop('checked', true);
        $('#newsFormMsg').hide().text('');
        $('#newsModalTitle').text('Add News');
    }

    function showNewsForm(newsId) {
        newsId = newsId || 0;
        resetForm();
        $('#news_id').val(newsId);
        $('#newsModalTitle').text(newsId ? 'Edit News #' + newsId : 'Add News');

        if (!newsId) {
            openModal($modal);
            return;
        }

        $.ajax({
            type: 'POST',
            url: 'process.php',
            data: { function: 'LoadPortalNews', news_id: newsId },
            dataType: 'json',
            success: function (res) {
                if (res.validation_status !== 'T' || !res.data) {
                    $('#newsFormMsg').text(res.msg || 'Could not load news.').show();
                    openModal($modal);
                    return;
                }
                var d = res.data;
                $('#news_id').val(d.news_id);
                $('#news_title').val(d.news_title || '');
                $('#news_message').val(d.news_message || '');
                $('#news_link_url').val(d.news_link_url || '');
                $('#news_link_label').val(d.news_link_label || 'Learn more');
                $('#news_date').val(d.news_date || '');
                $('#news_is_default').prop('checked', parseInt(d.news_is_default, 10) === 1);
                $('#news_status').prop('checked', parseInt(d.news_status, 10) === 1);
                openModal($modal);
            },
            error: function () {
                $('#newsFormMsg').text('Could not load news.').show();
                openModal($modal);
            }
        });
    }

    function saveNews() {
        var $btn = $('#btnSaveNews').prop('disabled', true);
        $('#newsFormMsg').hide().text('');
        $.ajax({
            type: 'POST',
            url: 'process.php',
            data: {
                function: 'SavePortalNews',
                news_id: $('#news_id').val(),
                news_title: $('#news_title').val(),
                news_message: $('#news_message').val(),
                news_link_url: $('#news_link_url').val(),
                news_link_label: $('#news_link_label').val(),
                news_date: $('#news_date').val(),
                news_is_default: $('#news_is_default').is(':checked') ? 1 : 0,
                news_status: $('#news_status').is(':checked') ? 1 : 0
            },
            dataType: 'json',
            success: function (res) {
                if (res.validation_status === 'T') {
                    location.reload();
                    return;
                }
                $('#newsFormMsg').text(res.msg || 'Could not save news.').show();
                $btn.prop('disabled', false);
            },
            error: function () {
                $('#newsFormMsg').text('Something went wrong. Please try again.').show();
                $btn.prop('disabled', false);
            }
        });
    }

    function showDelete(newsId) {
        pendingDeleteId = newsId;
        $('#newsDeleteId').text('#' + newsId);
        $('#newsDeleteMsg').hide().text('');
        openModal($deleteModal);
    }

    $('#btnAddNews').on('click', function () {
        showNewsForm(0);
    });

    $(document).on('click', '.btn-news-edit', function () {
        showNewsForm(parseInt($(this).data('newsid'), 10) || 0);
    });

    $(document).on('click', '.btn-news-delete', function () {
        showDelete(parseInt($(this).data('newsid'), 10) || 0);
    });

    $('#btnSaveNews').on('click', saveNews);

    $('#btnConfirmDeleteNews').on('click', function () {
        if (!pendingDeleteId) {
            return;
        }
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            type: 'POST',
            url: 'process.php',
            data: { function: 'DeletePortalNews', news_id: pendingDeleteId },
            dataType: 'json',
            success: function (res) {
                if (res.validation_status === 'T') {
                    location.reload();
                    return;
                }
                $('#newsDeleteMsg').text(res.msg || 'Could not delete news.').show();
                $btn.prop('disabled', false);
            },
            error: function () {
                $('#newsDeleteMsg').text('Something went wrong. Please try again.').show();
                $btn.prop('disabled', false);
            }
        });
    });

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
