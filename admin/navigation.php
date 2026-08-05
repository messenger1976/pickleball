<?php
$currentAdminPage = basename($_SERVER['PHP_SELF'], '.php');

function admin_nav_active($page) {
    global $currentAdminPage;
    return $currentAdminPage === $page ? ' is-active' : '';
}

if (fh_auth_is_logged_in() && !isset($_SESSION['user_avatar']) && isset($conn) && $conn) {
    fh_auth_refresh_session($conn);
}

$navManagingBizId = (fh_auth_is_managing_business() && isset($conn) && $conn)
    ? fh_auth_managing_business_id()
    : 0;
$navVenueMode = $navManagingBizId > 0 || !fh_auth_is_super_admin();
$navDashboardHref = $navManagingBizId > 0
    ? ('index.php?business_id=' . (int)$navManagingBizId)
    : 'index.php';
$navVenueQs = $navManagingBizId > 0 ? ('?business_id=' . (int)$navManagingBizId) : '';
$navLogoutHref = $navManagingBizId > 0 ? 'enter_business.php?exit=1' : 'logout.php';
$navLogoutLabel = 'Logout';
?>
<div class="admin-sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<nav class="admin-sidebar" id="mySidebar" aria-label="Admin navigation">
    <div class="admin-sidebar-head">
        <div class="admin-sidebar-head-top">
            <span class="admin-sidebar-brand">Pickleball Admin</span>
            <button type="button" class="admin-sidebar-close" onclick="closeSidebar()" aria-label="Close menu">&times;</button>
        </div>
        <a class="admin-sidebar-profile<?php echo admin_nav_active('profile'); ?>" href="profile.php" title="Edit my profile">
            <img class="admin-sidebar-avatar" src="<?php echo htmlspecialchars(fh_auth_avatar_url()); ?>" alt="" />
            <div>
                <div class="admin-sidebar-profile-name"><?php echo htmlspecialchars(isset($_SESSION['user_fullname']) ? $_SESSION['user_fullname'] : 'User'); ?></div>
                <div class="admin-sidebar-profile-role"><?php echo fh_auth_role_label(isset($_SESSION['user_role']) ? $_SESSION['user_role'] : ''); ?></div>
            </div>
        </a>
    </div>

    <div class="admin-sidebar-nav">
        <div class="admin-nav-label">Main</div>
        <a class="admin-nav-link<?php echo admin_nav_active('index'); ?>" href="<?php echo htmlspecialchars($navDashboardHref); ?>"><span class="admin-nav-icon">📊</span> Dashboard</a>

        <?php if (fh_auth_is_super_admin() && !$navVenueMode): ?>
        <div class="admin-nav-label">Portal</div>
        <a class="admin-nav-link<?php echo admin_nav_active('business_account'); ?>" href="business_account.php"><span class="admin-nav-icon">🏢</span> Business Accounts</a>
        <a class="admin-nav-link<?php echo admin_nav_active('users'); ?>" href="users.php"><span class="admin-nav-icon">👥</span> Users &amp; Roles</a>
        <a class="admin-nav-link<?php echo admin_nav_active('customers'); ?>" href="customers.php"><span class="admin-nav-icon">🧑</span> Customers</a>
        <div class="admin-nav-label">Portal Settings</div>
        <a class="admin-nav-link<?php echo admin_nav_active('portal_news'); ?>" href="portal_news.php"><span class="admin-nav-icon">📰</span> Portal News</a>
        <a class="admin-nav-link<?php echo admin_nav_active('portal_subscription'); ?>" href="portal_subscription.php"><span class="admin-nav-icon">💳</span> Subscription</a>
        <?php endif; ?>

        <div class="admin-nav-label">Venue</div>
        <a class="admin-nav-link<?php echo admin_nav_active('courts'); ?>" href="courts.php<?php echo $navVenueQs; ?>"><span class="admin-nav-icon">🎾</span> Courts</a>
        <a class="admin-nav-link<?php echo admin_nav_active('availability'); ?>" href="availability.php<?php echo $navVenueQs; ?>"><span class="admin-nav-icon">📅</span> Availability</a>
        <a class="admin-nav-link<?php echo admin_nav_active('bookings'); ?>" href="bookings.php<?php echo $navVenueQs; ?>"><span class="admin-nav-icon">📋</span> Bookings</a>
        <a class="admin-nav-link<?php echo admin_nav_active('coaches'); ?>" href="coaches.php<?php echo $navVenueQs; ?>"><span class="admin-nav-icon">🧑‍🏫</span> Coaches &amp; Lessons</a>
        <a class="admin-nav-link<?php echo admin_nav_active('memberships'); ?>" href="memberships.php<?php echo $navVenueQs; ?>"><span class="admin-nav-icon">🪪</span> Memberships</a>
        <a class="admin-nav-link<?php echo admin_nav_active('chat_inbox'); ?>" href="chat_inbox.php<?php echo $navVenueQs; ?>"><span class="admin-nav-icon">💬</span> Chat Inbox</a>
        <a class="admin-nav-link<?php echo admin_nav_active('business_page'); ?>" href="business_page.php<?php echo $navVenueQs; ?>"><span class="admin-nav-icon">🏪</span> Venue Page (CMS)</a>
        <?php if ($navVenueMode && (fh_auth_is_super_admin() || (isset($_SESSION['user_role']) && $_SESSION['user_role'] === FH_ROLE_BUSINESS_ADMIN))): ?>
        <a class="admin-nav-link<?php echo admin_nav_active('subscription'); ?>" href="subscription.php<?php echo $navVenueQs; ?>"><span class="admin-nav-icon">💎</span> Subscription</a>
        <?php endif; ?>
        <?php if (fh_auth_can_manage_users() && $navVenueMode): ?>
        <a class="admin-nav-link<?php echo admin_nav_active('users'); ?>" href="users.php<?php echo $navVenueQs; ?>"><span class="admin-nav-icon">👤</span> Staff Users</a>
        <?php endif; ?>

        <div class="admin-nav-label">Account</div>
        <a class="admin-nav-link<?php echo admin_nav_active('profile'); ?>" href="profile.php"><span class="admin-nav-icon">👤</span> My Profile</a>
        <a class="admin-nav-link admin-nav-link-logout" href="<?php echo htmlspecialchars($navLogoutHref); ?>"><span class="admin-nav-icon">⏻</span> <?php echo htmlspecialchars($navLogoutLabel); ?></a>
    </div>

    <div class="admin-sidebar-foot">Pickleball Portal &copy; <?php echo date('Y'); ?></div>
</nav>

<header class="admin-topbar">
    <button type="button" class="admin-menu-btn" onclick="openSidebar()" aria-label="Open menu">&#9776;</button>

    <div class="admin-topbar-brand">
        <span class="admin-logo-dot"></span>
        Pickleball
    </div>

    <div class="admin-topbar-divider"></div>

    <div class="admin-topbar-title"><?php echo isset($titleheader) ? htmlspecialchars($titleheader) : 'Admin'; ?></div>

    <div class="admin-topbar-spacer"></div>

    <div class="admin-topbar-user">
        <div class="admin-user-meta">
            <div class="admin-user-name"><?php echo htmlspecialchars(isset($_SESSION['user_fullname']) ? $_SESSION['user_fullname'] : ''); ?></div>
            <div class="admin-user-role"><?php echo fh_auth_role_label(isset($_SESSION['user_role']) ? $_SESSION['user_role'] : ''); ?></div>
        </div>
        <a href="../home.php" class="admin-topbar-link admin-topbar-link-portal" target="_blank"><span class="hide-mobile">Portal</span> ↗</a>
        <a href="<?php echo htmlspecialchars($navLogoutHref); ?>" class="admin-topbar-link"><?php echo htmlspecialchars($navLogoutLabel); ?></a>
    </div>
</header>
<?php if ($navManagingBizId > 0 && isset($conn) && $conn): ?>
<script>document.body.classList.add('is-managing-venue');</script>
<?php echo fh_admin_managing_banner($conn); ?>
<?php endif; ?>
