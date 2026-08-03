<?php
include('admin/config.php');

$msgbox = '';

if (isset($_SESSION['customers_id'])) {
    header('Location: home.php');
    exit;
}

if (isset($_POST['btnSubmit']) && isset($_POST['email'])) {
    $username = $_POST['email'];
    $password = md5($_POST['psw']);
    $usersql = $conn->query("SELECT * FROM customers WHERE customers_email='$username' LIMIT 1");
    if ($usersql && $usersql->num_rows > 0) {
        $row = $usersql->fetch_assoc();
        if ($row['customers_verify'] == 1) {
            $msgbox = 'Email account already exists.';
        } else {
            $last_id = $row['customers_id'];
            $valcode = $row['customers_password'];
            $msgbox = "Email account exists but is not verified. <a href='email_verify.php?customers_id=$last_id&valcode=$valcode'>Resend verification email</a>";
        }
    } else {
        $sql = "INSERT INTO customers (customers_email, customers_password) VALUES ('$username', '$password')";
        if ($conn->query($sql) === true) {
            $last_id = $conn->insert_id;
            header('Location: email_verify.php?customers_id=' . $last_id . '&valcode=' . $password);
            exit;
        }
        $msgbox = 'Could not create account. Please try again.';
    }
}

$fromCart = isset($_GET['from']) && ($_GET['from'] === 'cart' || $_GET['from'] === 'checkout');
if (!$fromCart && !empty($_SERVER['HTTP_REFERER'])) {
    $refererPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
    if ($refererPath) {
        $refPage = basename($refererPath, '.php');
        $fromCart = ($refPage === 'cart' || $refPage === 'checkout');
    }
}
$loginRedirect = 'home.php';
if (!empty($_GET['redirect'])) {
    $candidate = $_GET['redirect'];
    if (is_string($candidate) && $candidate !== '' && strpos($candidate, '://') === false && strpos($candidate, '//') !== 0) {
        $loginRedirect = $candidate;
    }
} elseif ($fromCart) {
    $loginRedirect = 'checkout.php';
}

$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = count($_SESSION['cart']);
}

$pagetitle = 'Sign In — Pickleball Portal';
$bodyClass = 'portal-order-page';
$extraStylesheets = array(
    'css/portal-home.css?v=20260704',
    'css/portal-order.css?v=20260710'
);
include('header.php');
?>

<div class="portal-order-hero portal-order-hero-compact">
    <div class="portal-order-hero-inner">
        <a class="portal-order-back" href="home.php">&larr; Back to Home</a>
        <span class="portal-order-badge">Account</span>
        <h1>Welcome Back</h1>
        <p>Sign in to book courts and manage your reservations.</p>
    </div>
</div>

<div class="portal-order-body portal-login-body">
    <div id="msgbox" class="portal-login-msgbox"<?php echo $msgbox === '' ? ' style="display:none"' : ''; ?>>
        <?php echo $msgbox; ?>
    </div>

    <div class="portal-login-layout">
        <div class="portal-cart-panel portal-login-panel">
            <div class="portal-cart-panel-head">
                <h2><?php echo $fromCart ? 'Quick Checkout' : 'Create Account'; ?></h2>
            </div>
            <div class="portal-login-panel-body">
                <?php if ($fromCart): ?>
                    <p class="portal-login-lead">No account? Choose how you'd like to proceed:</p>
                    <ul class="portal-login-benefits">
                        <li>Guest checkout — no sign-up required</li>
                        <li>Register once for faster future orders</li>
                        <li>Track your order history anytime</li>
                    </ul>
                    <div class="portal-login-actions">
                        <a href="checkout.php?guest=1" class="portal-login-btn portal-login-btn-primary">
                            Continue as Guest
                        </a>
                        <a href="signup.php" class="portal-login-btn portal-login-btn-secondary">
                            Create Free Account
                        </a>
                    </div>
                <?php else: ?>
                    <p class="portal-login-lead">Create an account to move through checkout faster and view your order history.</p>
                    <form action="#" method="POST" id="frmRegister">
                        <div id="registerMsgbox" class="portal-login-panel-alert" style="display:none" role="alert" aria-live="polite"></div>

                        <div class="portal-login-field">
                            <label for="email">Email</label>
                            <input type="text" placeholder="you@example.com" id="email" name="email" autocomplete="email">
                            <span class="portal-login-field-error" id="email-error"></span>
                        </div>

                        <div class="portal-login-field">
                            <label for="psw">Password</label>
                            <input type="password" placeholder="Choose a password" id="psw" name="psw" autocomplete="new-password">
                            <span class="portal-login-field-error" id="psw-error"></span>
                        </div>

                        <div class="portal-login-field">
                            <label for="psw-repeat">Confirm Password</label>
                            <input type="password" placeholder="Repeat your password" id="psw-repeat" name="psw-repeat" autocomplete="new-password">
                            <span class="portal-login-field-error" id="psw-repeat-error"></span>
                        </div>

                        <button type="submit" name="btnSubmit" class="portal-login-btn portal-login-btn-primary" id="registerbtn">
                            Register
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="portal-login-or" aria-hidden="true"><span>or</span></div>

        <div class="portal-cart-panel portal-login-panel">
            <div class="portal-cart-panel-head">
                <h2>Sign In</h2>
            </div>
            <div class="portal-login-panel-body portal-login-signin-body">
                <p class="portal-login-lead">Already have an account? Enter your credentials below.</p>

                <div id="loginMsgbox" class="portal-login-panel-alert" style="display:none" role="alert" aria-live="polite"></div>

                <div class="portal-login-field">
                    <label for="emaillogin">Email</label>
                    <input type="text" name="emaillogin" id="emaillogin" placeholder="you@example.com" autocomplete="email">
                    <span class="portal-login-field-error" id="emaillogin-error"></span>
                </div>

                <div class="portal-login-field">
                    <label for="passwordlogin">Password</label>
                    <input type="password" id="passwordlogin" name="passwordlogin" placeholder="Your password" autocomplete="current-password">
                    <span class="portal-login-field-error" id="passwordlogin-error"></span>
                </div>

                <button type="button" name="Login" id="btnLogin" class="portal-login-btn portal-login-btn-primary">
                    Sign In
                </button>
            </div>
        </div>
    </div>

    <div class="portal-login-footer">
        <a href="forgot_password.php" class="portal-login-forgot">Forgot your password?</a>
    </div>
</div>

<script>
window.fhLoginRedirect = <?php echo json_encode($loginRedirect); ?>;
</script>
<?php include('footer.php'); ?>
