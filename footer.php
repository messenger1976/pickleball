<?php
$currentpage = basename($_SERVER['SCRIPT_FILENAME'], '.php');
$year = (int)date('Y');

$stickyFooterBtn = '';
if ($currentpage === 'store' || $currentpage === 'book') {
    $stickyFooterBtn = '<button type="button" onclick="window.location.href=\'my_bookings.php\'">My Bookings</button>';
} elseif ($currentpage === 'my_bookings') {
    $stickyFooterBtn = '<button type="button" onclick="window.location.href=\'home.php#venues\'">Find Venues</button>';
}
$hasStickyBar = ($stickyFooterBtn !== '');
?>
<footer class="pb-site-footer" role="contentinfo">
    <div class="pb-site-footer-media" aria-hidden="true">
        <img src="images/brand/footer-banner.jpg" alt="" width="1600" height="900" loading="lazy" />
    </div>

    <div class="pb-site-footer-inner">
        <div class="pb-site-footer-brand">
            <img class="pb-site-footer-brand-icon" src="images/pwa-icon-192.png" alt="" width="52" height="52" loading="lazy" />
            <div class="pb-site-footer-brand-copy">
                <strong>Pickleball Portal</strong>
                <span>Community courts, open slots, and advance bookings across Bohol.</span>
            </div>
        </div>

        <div class="pb-site-footer-grid">
            <section class="pb-footer-box" aria-labelledby="footer-address-title">
                <div class="pb-footer-box-visual">
                    <img src="images/brand/hero-court-outdoor.jpg" alt="" width="640" height="200" loading="lazy" />
                </div>
                <h3 id="footer-address-title">Visit / Reach us</h3>
                <p class="pb-footer-address">
                    <strong>Pickleball Portal HQ</strong>
                    Tagbilaran City, Bohol<br>
                    Philippines 6300<br><br>
                    <a href="mailto:info@foodhauz.bohollander.com">info@foodhauz.bohollander.com</a><br>
                    Mon–Sat · 9:00 AM – 6:00 PM (PHT)
                </p>
            </section>

            <section class="pb-footer-box" aria-labelledby="footer-menu-title">
                <div class="pb-footer-box-visual">
                    <img src="images/brand/hero-match-play.jpg" alt="" width="640" height="200" loading="lazy" />
                </div>
                <h3 id="footer-menu-title">Explore</h3>
                <ul class="pb-footer-links">
                    <li><a href="home.php">Home</a></li>
                    <li><a href="home.php#venues">Partner venues</a></li>
                    <li><a href="my_bookings.php">My bookings</a></li>
                    <li><a href="my_memberships.php">Memberships</a></li>
                    <li><a href="partner_signup.php">Become a partner</a></li>
                    <li><a href="contactus.php">Contact us</a></li>
                    <li><a href="signup.php">Create account</a></li>
                </ul>
            </section>

            <section class="pb-footer-box" aria-labelledby="footer-subscribe-title">
                <div class="pb-footer-box-visual">
                    <img src="images/brand/hero-paddle-ball.jpg" alt="" width="640" height="200" loading="lazy" />
                </div>
                <h3 id="footer-subscribe-title">Subscribe</h3>
                <p class="pb-footer-subscribe-lead">Get court tips, open-play updates, and venue news in your inbox.</p>
                <form class="pb-footer-subscribe-form" id="frmFooterSubscribe" novalidate>
                    <label for="footer_subscribe_email" class="pb-footer-sr-only">Email</label>
                    <input type="email" id="footer_subscribe_email" name="email" placeholder="you@email.com" required autocomplete="email" />
                    <button type="submit" id="btnFooterSubscribe">Subscribe</button>
                    <p class="pb-footer-subscribe-msg" id="footerSubscribeMsg" role="status" aria-live="polite"></p>
                </form>
            </section>

            <section class="pb-footer-box" aria-labelledby="footer-social-title">
                <div class="pb-footer-box-visual">
                    <img src="images/brand/hero-park-aerial.jpg" alt="" width="640" height="200" loading="lazy" />
                </div>
                <h3 id="footer-social-title">Social</h3>
                <div class="pb-footer-social">
                    <a href="https://www.facebook.com/" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 9h3V6h-3c-1.6 0-3 1.4-3 3v2H8v3h3v7h3v-7h3l1-3h-4V9c0-.6.4-1 1-1z"/></svg>
                    </a>
                    <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4zm5 4.5A4.5 4.5 0 1 0 16.5 12 4.5 4.5 0 0 0 12 7.5zm5.8-.9a1.1 1.1 0 1 0 1.1 1.1 1.1 1.1 0 0 0-1.1-1.1zM12 9.5A2.5 2.5 0 1 1 9.5 12 2.5 2.5 0 0 1 12 9.5z"/></svg>
                    </a>
                    <a href="https://www.youtube.com/" target="_blank" rel="noopener noreferrer" aria-label="YouTube" title="YouTube">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M23 12.2s0-3.2-.4-4.7c-.2-.9-.9-1.6-1.8-1.8C18.5 5.2 12 5.2 12 5.2s-6.5 0-8.8.5c-.9.2-1.6.9-1.8 1.8C1 9 1 12.2 1 12.2s0 3.2.4 4.7c.2.9.9 1.6 1.8 1.8 2.3.5 8.8.5 8.8.5s6.5 0 8.8-.5c.9-.2 1.6-.9 1.8-1.8.4-1.5.4-4.7.4-4.7zM9.8 15.5v-6.6l5.7 3.3-5.7 3.3z"/></svg>
                    </a>
                    <a href="https://www.tiktok.com/" target="_blank" rel="noopener noreferrer" aria-label="TikTok" title="TikTok">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19.6 7.6a5.6 5.6 0 0 1-3.3-1.1v7.1a5.4 5.4 0 1 1-5.4-5.4c.3 0 .6 0 .9.1v2.7a2.7 2.7 0 1 0 1.9 2.6V2.5h2.7a5.6 5.6 0 0 0 3.2 5.1z"/></svg>
                    </a>
                </div>
                <p class="pb-footer-social-note">Follow for open-play days, clinic drops, and venue highlights.</p>
            </section>
        </div>
    </div>

    <div class="pb-site-footer-bottom">
        <div class="pb-site-footer-bottom-inner">
            <div>
                &copy; <?php echo $year; ?> Pickleball Portal · Bohol
                · <a href="contactus.php">Support</a>
                · <a href="partner_signup.php">Partner</a>
            </div>
            <div class="pb-site-footer-thumbs" aria-hidden="true">
                <img src="images/brand/court-outdoor.jpg" alt="" width="48" height="36" loading="lazy" />
                <img src="images/brand/court-indoor.jpg" alt="" width="48" height="36" loading="lazy" />
                <img src="images/brand/hero-paddle-ball.jpg" alt="" width="48" height="36" loading="lazy" />
            </div>
        </div>
    </div>
</footer>

<div class="footernavbar<?php echo $hasStickyBar ? '' : ' is-empty'; ?>">
    <?php echo $stickyFooterBtn; ?>
</div>
<?php if ($hasStickyBar): ?>
<script>document.body.classList.add('has-sticky-footer-bar');</script>
<?php endif; ?>

<script>
(function($) {
    if (!$ || !$('#frmFooterSubscribe').length) return;
    $('#frmFooterSubscribe').on('submit', function(e) {
        e.preventDefault();
        var email = $.trim($('#footer_subscribe_email').val() || '');
        var $msg = $('#footerSubscribeMsg').removeClass('is-ok is-error').hide();
        var $btn = $('#btnFooterSubscribe').prop('disabled', true).text('Subscribing…');
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            $msg.addClass('is-error').text('Please enter a valid email.').show();
            $btn.prop('disabled', false).text('Subscribe');
            return;
        }
        $.ajax({
            type: 'POST',
            url: 'process.php',
            dataType: 'json',
            data: { function: 'NewsletterSubscribe', email: email },
            success: function(data) {
                if (data && data.validation_status === 'T') {
                    $msg.addClass('is-ok').text(data.msg || 'Thanks for subscribing!').show();
                    $('#footer_subscribe_email').val('');
                } else {
                    $msg.addClass('is-error').text((data && data.msg) ? data.msg : 'Could not subscribe. Try again.').show();
                }
            },
            error: function() {
                $msg.addClass('is-error').text('Could not subscribe. Try again.').show();
            },
            complete: function() {
                $btn.prop('disabled', false).text('Subscribe');
            }
        });
    });
})(window.jQuery);
</script>

<?php
if ($currentpage !== 'checkout' || $currentpage !== 'cart') {
?>
<script>
if (typeof(Storage) !== 'undefined') {
    if (sessionStorage.customers_id) {
        var el = document.getElementById('namesignin');
        if (el) el.innerHTML = sessionStorage.getItem('customers_firstname');
    } else {
        var guest = document.getElementById('namesignin');
        if (guest) guest.innerHTML = 'Guest';
    }
} else {
    var fallback = document.getElementById('namesignin');
    if (fallback) fallback.innerHTML = 'Guest';
}
</script>
<?php
}
?>
</body>
</html>
