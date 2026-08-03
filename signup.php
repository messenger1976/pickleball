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
    $usercount = $usersql ? $usersql->num_rows : 0;
    if ($usercount > 0) {
        $row = $usersql->fetch_assoc();
        if ($row['customers_verify'] == 1) {
            $msgbox = 'Email account already exists.';
        } else {
            $last_id = $row['customers_id'];
            $valcode = $row['customers_password'];
            $msgbox = "Email account exists but is not verified. <a href='email_verify.php?customers_id=$last_id&amp;valcode=$valcode'>Resend verification email</a>";
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

$pagetitle = 'Sign Up — Pickleball Portal';
include('header.php');
?>
<style>
.signup-page {
    margin-top: 56px;
    min-height: calc(100vh - 56px);
}
.signup-hero {
    position: relative;
    min-height: min(40vh, 340px);
    display: flex;
    align-items: flex-end;
    overflow: hidden;
    background: var(--pb-court, #0f3d2e);
    color: #fff;
}
.signup-hero-media {
    position: absolute;
    inset: 0;
    background: url('images/brand/hero-match-play.jpg') center / cover no-repeat;
    transform: scale(1.05);
    animation: pbKen 14s ease-out both;
}
.signup-hero::after {
    content: "";
    position: absolute;
    inset: 0;
    background:
        linear-gradient(105deg, rgba(8, 30, 22, 0.94) 0%, rgba(8, 30, 22, 0.7) 55%, rgba(8, 30, 22, 0.4) 100%),
        linear-gradient(180deg, transparent 15%, rgba(8, 30, 22, 0.8) 100%);
}
.signup-hero-inner {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 960px;
    margin: 0 auto;
    padding: 48px 20px 36px;
    animation: pbRise 0.85s ease-out both;
}
.signup-hero-badge {
    display: inline-block;
    border-left: 3px solid var(--pb-ball, #e4f34a);
    padding-left: 12px;
    margin-bottom: 12px;
    color: #f4ff8a;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    font-size: 12px;
}
.signup-hero h1 {
    margin: 0 0 10px;
    font-family: var(--pb-font-display, "Archivo Black", sans-serif);
    font-size: clamp(2rem, 5vw, 3.2rem);
    line-height: 0.95;
    letter-spacing: -0.02em;
    text-transform: uppercase;
    max-width: 11ch;
}
.signup-hero .lead {
    margin: 0;
    max-width: 40ch;
    font-size: 1.05rem;
    line-height: 1.55;
    color: rgba(255, 255, 255, 0.94);
}
.signup-body {
    max-width: 960px;
    margin: 0 auto 80px;
    padding: 36px 20px 0;
    animation: pbRise 0.9s ease-out 0.1s both;
}
.signup-grid {
    display: grid;
    grid-template-columns: 1.15fr 0.95fr;
    gap: 28px;
    align-items: start;
}
.signup-card,
.signup-aside-card {
    background: #fff;
    border: 1px solid var(--pb-line, #d7e0d9);
    border-radius: var(--pb-radius, 8px);
    box-shadow: var(--pb-shadow, 0 14px 36px rgba(12, 31, 23, 0.1));
}
.signup-card {
    padding: 26px 24px 30px;
}
.signup-card .form-kicker {
    margin: 0 0 6px;
    color: var(--pb-leaf, #1f7a52);
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-size: 12px;
}
.signup-card .form-title {
    margin: 0 0 8px;
    font-family: var(--pb-font-display, "Archivo Black", sans-serif);
    color: var(--pb-court, #0f3d2e);
    font-size: 1.4rem;
    text-transform: uppercase;
}
.signup-card .intro {
    margin: 0 0 20px;
    color: var(--pb-muted, #3f5248);
    line-height: 1.5;
}
.signup-page .portal-login-field {
    margin-bottom: 4px;
}
.signup-page .portal-login-field label {
    display: block;
    font-weight: 700;
    margin: 12px 0 6px;
    color: var(--pb-ink, #0c1f17);
}
.signup-page .portal-login-field input {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid var(--pb-line, #d7e0d9);
    border-radius: 8px;
    font-size: 15px;
    margin: 0;
    box-sizing: border-box;
    background: #fff;
    color: var(--pb-ink, #0c1f17);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.signup-page .portal-login-field input:focus {
    outline: none;
    border-color: var(--pb-leaf, #1f7a52);
    box-shadow: 0 0 0 3px rgba(31, 122, 82, 0.15);
}
.signup-page .portal-login-field input.portal-input-error {
    border-color: #dc2626;
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12);
}
.signup-page .portal-login-field-error {
    display: block;
    min-height: 0;
    margin: 4px 0 0;
    font-size: 12px;
    font-weight: 600;
    color: #dc2626;
    line-height: 1.3;
}
.signup-page .portal-login-field-error:empty {
    display: none;
}
.signup-page .portal-login-btn {
    display: block;
    width: 100%;
    margin-top: 18px;
    padding: 14px 28px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 800;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    transition: transform 0.2s ease, background 0.2s ease;
}
.signup-page .portal-login-btn-primary {
    background: var(--pb-ball, #e4f34a);
    color: var(--pb-ink, #0c1f17);
}
.signup-page .portal-login-btn-primary:hover {
    background: var(--pb-ball-deep, #c5d62e);
    transform: translateY(-1px);
}
.signup-page .portal-login-btn:disabled {
    opacity: 0.7;
    cursor: wait;
    transform: none;
}
.signup-page .portal-login-panel-alert,
.signup-page .portal-login-msgbox {
    display: none;
    padding: 12px 14px;
    border-radius: 8px;
    margin-bottom: 14px;
    font-weight: 600;
    line-height: 1.4;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}
.signup-page .portal-login-panel-alert a,
.signup-page .portal-login-msgbox a {
    color: #7f1d1d;
    font-weight: 800;
}
.signup-page .portal-login-panel-alert.is-success {
    background: #eaf6c8;
    border-color: #c9df7a;
    color: #1a3d18;
}
.signup-hint {
    margin: 18px 0 0;
    text-align: center;
    color: var(--pb-muted, #3f5248);
    font-size: 0.95rem;
}
.signup-hint a {
    color: var(--pb-court, #0f3d2e);
    font-weight: 800;
    text-decoration: none;
}
.signup-hint a:hover {
    color: var(--pb-leaf, #1f7a52);
    text-decoration: underline;
}
.signup-aside {
    display: grid;
    gap: 16px;
}
.signup-aside-card {
    padding: 22px 22px 24px;
    overflow: hidden;
}
.signup-aside-card h3 {
    margin: 0 0 12px;
    font-family: var(--pb-font-display, "Archivo Black", sans-serif);
    font-size: 1.1rem;
    text-transform: uppercase;
    color: var(--pb-court, #0f3d2e);
    letter-spacing: -0.01em;
}
.signup-benefits {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 12px;
}
.signup-benefits li {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    color: var(--pb-ink, #0c1f17);
    font-weight: 600;
    line-height: 1.45;
    font-size: 0.95rem;
}
.signup-benefits li::before {
    content: "";
    flex-shrink: 0;
    width: 10px;
    height: 10px;
    margin-top: 6px;
    border-radius: 2px;
    background: var(--pb-ball, #e4f34a);
}
.signup-steps {
    margin: 0;
    padding-left: 18px;
    color: var(--pb-muted, #3f5248);
    line-height: 1.55;
}
.signup-steps li {
    margin-bottom: 8px;
}
.signup-aside-photo {
    height: 140px;
    margin: -22px -22px 16px;
    overflow: hidden;
    background: var(--pb-court, #0f3d2e);
}
.signup-aside-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.signup-back {
    text-align: center;
    margin-top: 24px;
}
.signup-back a {
    color: var(--pb-court, #0f3d2e);
    font-weight: 700;
    text-decoration: none;
}
.signup-back a:hover {
    color: var(--pb-leaf, #1f7a52);
}
@media (max-width: 800px) {
    .signup-grid {
        grid-template-columns: 1fr;
    }
    .signup-hero h1 {
        max-width: none;
    }
}
</style>

<div class="signup-page">
    <section class="signup-hero" aria-label="Create your Pickleball Portal account">
        <div class="signup-hero-media" aria-hidden="true"></div>
        <div class="signup-hero-inner">
            <span class="signup-hero-badge">Player account</span>
            <h1>Pickleball Portal</h1>
            <p class="lead">Create a free account to book courts, manage reservations, and join venue memberships.</p>
        </div>
    </section>

    <div class="signup-body">
        <div id="msgbox" class="portal-login-msgbox"<?php echo $msgbox === '' ? '' : ' style="display:block"'; ?>>
            <?php echo $msgbox; ?>
        </div>

        <div class="signup-grid">
            <div class="signup-card">
                <p class="form-kicker">Register</p>
                <h2 class="form-title">Create your account</h2>
                <p class="intro">Enter your email and password. We&rsquo;ll send a verification link to activate your account.</p>

                <form action="#" method="POST" id="frmRegister" novalidate>
                    <div id="registerMsgbox" class="portal-login-panel-alert" style="display:none" role="alert" aria-live="polite"></div>

                    <div class="portal-login-field">
                        <label for="email">Email *</label>
                        <input type="email" placeholder="you@example.com" id="email" name="email" autocomplete="email" required>
                        <span class="portal-login-field-error" id="email-error"></span>
                    </div>

                    <div class="portal-login-field">
                        <label for="psw">Password *</label>
                        <input type="password" placeholder="Choose a password" id="psw" name="psw" autocomplete="new-password" required>
                        <span class="portal-login-field-error" id="psw-error"></span>
                    </div>

                    <div class="portal-login-field">
                        <label for="psw-repeat">Confirm password *</label>
                        <input type="password" placeholder="Repeat your password" id="psw-repeat" name="psw-repeat" autocomplete="new-password" required>
                        <span class="portal-login-field-error" id="psw-repeat-error"></span>
                    </div>

                    <button type="submit" name="btnSubmit" class="portal-login-btn portal-login-btn-primary" id="registerbtn">
                        Create Account
                    </button>
                </form>

                <p class="signup-hint">
                    Already have an account?
                    <a href="login.php">Sign in</a>
                </p>
            </div>

            <aside class="signup-aside" aria-label="Why create an account">
                <div class="signup-aside-card">
                    <div class="signup-aside-photo">
                        <img src="images/brand/hero-paddle-ball.jpg" alt="Pickleball paddle and ball" width="640" height="280" loading="lazy" />
                    </div>
                    <h3>Why create an account?</h3>
                    <ul class="signup-benefits">
                        <li>Book courts and open slots in advance</li>
                        <li>Track and manage your reservations</li>
                        <li>Join venue memberships and lessons</li>
                        <li>Free to join — takes under a minute</li>
                    </ul>
                </div>

                <div class="signup-aside-card">
                    <h3>What happens next?</h3>
                    <ol class="signup-steps">
                        <li>Submit your email and password</li>
                        <li>Verify your email from the link we send</li>
                        <li>Sign in and start booking courts</li>
                    </ol>
                </div>
            </aside>
        </div>

        <p class="signup-back">
            <a href="home.php">← Back to Pickleball Portal</a>
        </p>
    </div>
</div>

<?php include('footer.php'); ?>
