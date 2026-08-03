<!-- Top Navigation Menu -->
<?php
include_once(__DIR__ . '/includes/customer_helper.php');

$navCartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $navCartCount = count($_SESSION['cart']);
}

$navIsLoggedIn = isset($_SESSION['customers_id']) && (int)$_SESSION['customers_id'] > 0;
$navDisplayName = 'Guest';
if ($navIsLoggedIn && !empty($_SESSION['customers_firstname'])) {
    $navDisplayName = trim($_SESSION['customers_firstname']);
} elseif ($navIsLoggedIn && !empty($_COOKIE['customers_firstname'])) {
    $navDisplayName = trim($_COOKIE['customers_firstname']);
}

$navProfileUrl = $navIsLoggedIn ? 'profile.php' : 'login.php';
$navProfileTitle = $navIsLoggedIn ? 'My Profile' : 'Sign In';
$navAvatarUrl = '';
$navInitials = 'U';
if ($navIsLoggedIn && isset($conn)) {
    $navAvatarUrl = fh_customer_nav_avatar($conn);
    $navInitials = fh_customer_nav_initials();
}

if (date('A') === 'AM') {
    $welcomemsg = 'Good Morning ';
} else {
    $welcomemsg = 'Good Afternoon ';
}
$welcomemsg .= '<span id="namesignin">' . htmlspecialchars($navDisplayName) . '</span>';
?>
<div class="topnav">
    <a href="home.php" id="titlepage">PICKLEBALL</a>

    <div class="topnav-right">
        <span id="welcomemsg"><i><?php echo $welcomemsg; ?>!</i></span>
        <div class="topnav-actions">
            <a href="my_bookings.php" class="topnav-action topnav-cart" title="My Bookings" aria-label="My bookings">
                <svg class="topnav-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <rect x="3" y="4" width="18" height="18" rx="2" fill="none" stroke="currentColor" stroke-width="2"></rect>
                    <path d="M16 2v4M8 2v4M3 10h18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                </svg>
            </a>
            <a href="<?php echo htmlspecialchars($navProfileUrl); ?>" class="topnav-action topnav-profile<?php echo $navIsLoggedIn ? ($navAvatarUrl !== '' ? ' has-avatar' : ' has-initials') : ''; ?>" title="<?php echo htmlspecialchars($navProfileTitle); ?>" aria-label="<?php echo htmlspecialchars($navProfileTitle); ?>">
                <?php if ($navIsLoggedIn): ?>
                    <img src="<?php echo $navAvatarUrl !== '' ? htmlspecialchars($navAvatarUrl) : ''; ?>" alt="" class="topnav-profile-avatar" id="topnavProfileAvatar"<?php echo $navAvatarUrl === '' ? ' style="display:none"' : ''; ?>>
                    <span class="topnav-profile-initials" id="topnavProfileInitials"<?php echo $navAvatarUrl !== '' ? ' style="display:none"' : ''; ?>><?php echo htmlspecialchars($navInitials); ?></span>
                <?php else: ?>
                    <svg class="topnav-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                        <circle cx="12" cy="7" r="4" fill="none" stroke="currentColor" stroke-width="2"></circle>
                    </svg>
                <?php endif; ?>
            </a>
            <a href="javascript:void(0);" class="topnav-action topnav-menu icon" onclick="openNav()" title="Menu" aria-label="Open menu">&#9776;</a>
        </div>
    </div>
</div>
<div id="mySidenav">
    <div class="sidenav">
        <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
        <a href="home.php">Home</a>
        <a href="home.php#venues">Venues</a>
        <a href="my_bookings.php">My Bookings</a>
        <a href="my_memberships.php">Memberships</a>
        <a href="contactus.php">Contact Us</a>
        <?php if (!$navIsLoggedIn): ?>
        <a href="partner_signup.php">Partner Signup</a>
        <a href="admin/login.php">Venue Admin</a>
        <?php endif; ?>
        <?php if ($navIsLoggedIn): ?>
        <a href="profile.php">Profile</a>
        <a href="my_bookings.php">My Bookings</a>
        <a href="#" id="logoutlink">Logout</a>
        <?php else: ?>
        <a href="login.php">Login</a>
        <a href="signup.php">Sign Up</a>
        <?php endif; ?>
    </div>
</div>
<div id="overlay">
    <div id="loader"></div>
</div>
<script>
$(function() {
    $('#logoutlink').on('click', function(e) {
        e.preventDefault();
        if (typeof sessionStorage !== 'undefined') {
            sessionStorage.clear();
        }
        window.location.href = 'logout.php';
    });

    if (typeof sessionStorage !== 'undefined' && sessionStorage.customers_id && $('#namesignin').text() === 'Guest') {
        var storedName = sessionStorage.getItem('customers_firstname');
        if (storedName) {
            $('#namesignin').text(storedName);
        }
    }
});
</script>
