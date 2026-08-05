<?php
include('config.php');
include_once(__DIR__ . '/../includes/portal_subscription_helper.php');
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
if (isset($_POST['submit'])) {
    $username = trim($_POST['user']);
    $password = md5($_POST['psw']);
    $safeUser = $conn->real_escape_string($username);
    $usersql = $conn->query("SELECT * FROM user WHERE user_name='$safeUser' AND user_password='$password' AND user_status=1 LIMIT 1");
    if ($usersql && $usersql->num_rows > 0) {
        $row = $usersql->fetch_assoc();
        if ((int)$row['user_business_account'] > 0) {
            $bizCheck = $conn->query("SELECT business_status FROM business_account WHERE business_id=" . (int)$row['user_business_account'] . " LIMIT 1");
            if ($bizCheck && $bizRow = $bizCheck->fetch_assoc()) {
                if ((int)$bizRow['business_status'] !== 1 && $row['user_role'] !== FH_ROLE_SUPER) {
                    $error = 'Your venue account is not active yet. Please check your email for the activation link, or contact support.';
                }
            }
            if ($error === '' && $row['user_role'] !== FH_ROLE_SUPER) {
                $access = fh_portal_sub_access_state($conn, (int)$row['user_business_account']);
                if ($access['state'] === 'pending') {
                    $error = 'Your venue subscription is not active yet. Please complete activation or payment from your email link.';
                }
            }
        }
        if ($error === '') {
            fh_auth_set_session($row);
            fh_auth_exit_business();
            $bizId = (int)$row['user_business_account'];
            if ($bizId > 0 && $row['user_role'] !== FH_ROLE_SUPER) {
                $access = fh_portal_sub_access_state($conn, $bizId);
                if (!empty($access['is_expired']) || $access['state'] === 'expired') {
                    header('Location: subscription.php');
                    exit;
                }
            }
            header("Location: index.php");
            exit;
        }
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f3d2e">
    <title>Sign In — Pickleball Portal Admin</title>
    <link rel="icon" type="image/png" href="../images/pwa-icon-192.png">
    <link rel="apple-touch-icon" href="../images/pwa-icon-192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css?v=20260804a" type="text/css" />
</head>
<body class="login-page">

<div class="login-shell">

    <aside class="login-brand" aria-hidden="true">
        <div class="login-brand-media">
            <img
                src="../images/brand/hero-paddle-ball.jpg"
                alt=""
                class="login-brand-img is-primary"
                width="1600"
                height="1200"
            />
            <img
                src="../images/brand/hero-match-play.jpg"
                alt=""
                class="login-brand-img is-secondary"
                width="1600"
                height="1200"
            />
        </div>
        <div class="login-brand-inner">
            <div class="login-logo">
                <img src="../images/pwa-icon-192.png" alt="" class="login-logo-icon" width="36" height="36" />
                Pickleball Portal
            </div>
            <h1>Run your courts from one desk</h1>
            <p>Manage courts, schedules, bookings, and your public venue page — built for clubs across Bohol.</p>
            <ul class="login-features">
                <li><span class="icon" aria-hidden="true"></span> Add courts, pricing, and availability</li>
                <li><span class="icon" aria-hidden="true"></span> Confirm and track player bookings</li>
                <li><span class="icon" aria-hidden="true"></span> Customize your venue landing page</li>
                <li><span class="icon" aria-hidden="true"></span> Invite staff with role-based access</li>
            </ul>
        </div>
    </aside>

    <main class="login-panel">
        <div class="login-card">
            <div class="login-mobile-brand">
                <div class="login-logo">
                    <img src="../images/pwa-icon-192.png" alt="" class="login-logo-icon" width="36" height="36" />
                    Pickleball Portal
                </div>
                <div class="login-mobile-photo">
                    <img src="../images/brand/hero-court-outdoor.jpg" alt="Outdoor pickleball court" width="800" height="480" loading="lazy" />
                </div>
                <h1>Admin Sign In</h1>
            </div>

            <div class="login-card-header">
                <p class="login-kicker">Venue admin</p>
                <h2>Welcome back</h2>
                <p>Sign in as portal admin or venue manager</p>
            </div>

            <?php if ($error !== ''): ?>
            <div class="login-alert" role="alert">
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>

            <form action="login.php" method="POST" autocomplete="on">
                <div class="login-field">
                    <label for="user">Username</label>
                    <div class="login-input-wrap">
                        <input type="text" id="user" name="user" placeholder="Enter your username" required autofocus autocomplete="username" />
                    </div>
                </div>

                <div class="login-field">
                    <label for="psw">Password</label>
                    <div class="login-input-wrap">
                        <input type="password" id="psw" name="psw" placeholder="Enter your password" required autocomplete="current-password" />
                        <button type="button" class="login-toggle-pw" id="togglePw" aria-label="Show password">Show</button>
                    </div>
                </div>

                <button type="submit" class="login-submit" name="submit">Sign In</button>
            </form>

            <div class="login-divider">or</div>

            <div class="login-links">
                <a href="../home.php" class="login-link login-link-secondary">← Back to Pickleball Portal</a>
                <a href="../partner_signup.php" class="login-link login-link-outline">Register your venue</a>
            </div>
        </div>
    </main>

</div>

<script>
(function() {
    var toggle = document.getElementById('togglePw');
    var pw = document.getElementById('psw');
    if (toggle && pw) {
        toggle.addEventListener('click', function() {
            var isHidden = pw.type === 'password';
            pw.type = isHidden ? 'text' : 'password';
            toggle.textContent = isHidden ? 'Hide' : 'Show';
            toggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    }
})();
</script>

</body>
</html>
