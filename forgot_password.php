<?php
include('admin/config.php');

if (isset($_SESSION['customers_id'])) {
    header('Location: home.php');
    exit;
}

$pagetitle = 'Reset Password — Food Park Portal';
$bodyClass = 'portal-order-page';
$extraStylesheets = array(
    'css/portal-home.css?v=20260704',
    'css/portal-order.css?v=20260711'
);
include('header.php');
?>

<div class="portal-order-hero portal-order-hero-compact">
    <div class="portal-order-hero-inner">
        <a class="portal-order-back" href="login.php">&larr; Back to Sign In</a>
        <span class="portal-order-badge">Account</span>
        <h1>Reset Your Password</h1>
        <p>Enter the email address linked to your account. We&rsquo;ll send you a temporary password so you can sign in again.</p>
    </div>
</div>

<div class="portal-order-body portal-login-body portal-forgot-body">
    <div class="portal-forgot-wrap">
        <div class="portal-cart-panel portal-forgot-panel" id="forgotFormPanel">
            <div class="portal-cart-panel-head">
                <h2>Forgot Password</h2>
            </div>
            <div class="portal-login-panel-body">
                <p class="portal-login-lead">Use the same email you used when you registered with Food Park Portal.</p>

                <form action="#" method="POST" id="frmForgotPassword">
                    <div id="forgotMsgbox" class="portal-login-panel-alert" style="display:none" role="alert" aria-live="polite"></div>

                    <div class="portal-login-field">
                        <label for="forgotEmail">Email address</label>
                        <input type="text" id="forgotEmail" name="email" placeholder="you@example.com" autocomplete="email">
                        <span class="portal-login-field-error" id="forgotEmail-error"></span>
                    </div>

                    <button type="submit" class="portal-login-btn portal-login-btn-primary" id="btnForgotSubmit">
                        Send Reset Email
                    </button>
                </form>

                <p class="portal-forgot-hint">
                    Remember your password?
                    <a href="login.php">Return to sign in</a>
                </p>
            </div>
        </div>

        <div class="portal-cart-panel portal-forgot-panel portal-forgot-success-panel" id="forgotSuccessPanel" style="display:none">
            <div class="portal-cart-panel-head">
                <h2>Check Your Email</h2>
            </div>
            <div class="portal-login-panel-body">
                <div class="portal-forgot-success-icon" aria-hidden="true">&#9993;</div>
                <p class="portal-forgot-success-lead">We sent a temporary password to:</p>
                <p class="portal-forgot-success-email" id="forgotSuccessEmail"></p>
                <p class="portal-login-lead">Open the email, sign in with the temporary password, then change it from your account when ready.</p>
                <a href="login.php" class="portal-login-btn portal-login-btn-primary">Back to Sign In</a>
            </div>
        </div>

        <div class="portal-forgot-steps">
            <h3>What happens next?</h3>
            <ol>
                <li>We look up your account by email</li>
                <li>A temporary password is sent to your inbox</li>
                <li>Sign in and update your password anytime</li>
            </ol>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>
