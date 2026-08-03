<?php
include('admin/db_config.php');

$pagetitle = 'Contact Us — Pickleball Portal';
include('header.php');
?>
<style>
.contact-page {
    margin-top: 56px;
    min-height: calc(100vh - 56px);
}
.contact-hero {
    position: relative;
    min-height: min(40vh, 340px);
    display: flex;
    align-items: flex-end;
    overflow: hidden;
    background: var(--pb-court, #0f3d2e);
    color: #fff;
}
.contact-hero-media {
    position: absolute;
    inset: 0;
    background: url('images/brand/hero-park-aerial.jpg') center / cover no-repeat;
    transform: scale(1.05);
    animation: pbKen 14s ease-out both;
}
.contact-hero::after {
    content: "";
    position: absolute;
    inset: 0;
    background:
        linear-gradient(105deg, rgba(8, 30, 22, 0.94) 0%, rgba(8, 30, 22, 0.7) 55%, rgba(8, 30, 22, 0.4) 100%),
        linear-gradient(180deg, transparent 15%, rgba(8, 30, 22, 0.8) 100%);
}
.contact-hero-inner {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 960px;
    margin: 0 auto;
    padding: 48px 20px 36px;
    animation: pbRise 0.85s ease-out both;
}
.contact-hero-badge {
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
.contact-hero h1 {
    margin: 0 0 10px;
    font-family: var(--pb-font-display, "Archivo Black", sans-serif);
    font-size: clamp(2rem, 5vw, 3.2rem);
    line-height: 0.95;
    letter-spacing: -0.02em;
    text-transform: uppercase;
    max-width: 12ch;
}
.contact-hero .lead {
    margin: 0;
    max-width: 42ch;
    font-size: 1.05rem;
    line-height: 1.55;
    color: rgba(255, 255, 255, 0.94);
}
.contact-body {
    max-width: 960px;
    margin: 0 auto 80px;
    padding: 36px 20px 0;
    animation: pbRise 0.9s ease-out 0.1s both;
}
.contact-grid {
    display: grid;
    grid-template-columns: 1fr 1.15fr;
    gap: 28px;
    align-items: start;
}
.contact-aside,
.contact-form-card {
    background: #fff;
    border: 1px solid var(--pb-line, #d7e0d9);
    border-radius: var(--pb-radius, 8px);
    box-shadow: var(--pb-shadow, 0 14px 36px rgba(12, 31, 23, 0.1));
}
.contact-aside {
    padding: 0;
    overflow: hidden;
}
.contact-aside-map {
    height: 200px;
    background: #0f3d2e;
    overflow: hidden;
}
.contact-aside-map img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    opacity: 0.92;
}
.contact-aside-body {
    padding: 22px 22px 26px;
}
.contact-aside-body h2 {
    margin: 0 0 8px;
    font-family: var(--pb-font-display, "Archivo Black", sans-serif);
    font-size: 1.25rem;
    text-transform: uppercase;
    color: var(--pb-court, #0f3d2e);
    letter-spacing: -0.01em;
}
.contact-aside-body > p {
    margin: 0 0 20px;
    color: var(--pb-muted, #3f5248);
    line-height: 1.5;
    font-size: 0.95rem;
}
.contact-detail {
    display: grid;
    gap: 14px;
    margin: 0;
    padding: 0;
    list-style: none;
}
.contact-detail li {
    display: grid;
    gap: 2px;
}
.contact-detail .label {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--pb-leaf, #1f7a52);
}
.contact-detail .value {
    color: var(--pb-ink, #0c1f17);
    font-weight: 600;
    line-height: 1.45;
}
.contact-detail a {
    color: var(--pb-court, #0f3d2e);
    font-weight: 700;
    text-decoration: none;
}
.contact-detail a:hover {
    color: var(--pb-leaf, #1f7a52);
    text-decoration: underline;
}
.contact-form-card {
    padding: 26px 24px 30px;
}
.contact-form-card .form-kicker {
    margin: 0 0 6px;
    color: var(--pb-leaf, #1f7a52);
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-size: 12px;
}
.contact-form-card .form-title {
    margin: 0 0 8px;
    font-family: var(--pb-font-display, "Archivo Black", sans-serif);
    color: var(--pb-court, #0f3d2e);
    font-size: 1.4rem;
    text-transform: uppercase;
}
.contact-form-card .intro {
    margin: 0 0 20px;
    color: var(--pb-muted, #3f5248);
    line-height: 1.5;
}
.contact-form-card label {
    display: block;
    font-weight: 700;
    margin: 12px 0 6px;
    color: var(--pb-ink, #0c1f17);
}
.contact-form-card input[type=text],
.contact-form-card input[type=email],
.contact-form-card input[type=tel],
.contact-form-card select,
.contact-form-card textarea {
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
.contact-form-card input:focus,
.contact-form-card select:focus,
.contact-form-card textarea:focus {
    outline: none;
    border-color: var(--pb-leaf, #1f7a52);
    box-shadow: 0 0 0 3px rgba(31, 122, 82, 0.15);
}
.contact-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.contact-form-card .btn-submit {
    background: var(--pb-ball, #e4f34a);
    color: var(--pb-ink, #0c1f17);
    border: none;
    padding: 14px 28px;
    font-size: 16px;
    font-weight: 800;
    border-radius: 8px;
    cursor: pointer;
    width: 100%;
    margin-top: 18px;
    transition: transform 0.2s ease, background 0.2s ease;
}
.contact-form-card .btn-submit:hover {
    background: var(--pb-ball-deep, #c5d62e);
    transform: translateY(-1px);
}
.contact-form-card .btn-submit:disabled {
    opacity: 0.7;
    cursor: wait;
    transform: none;
}
.contact-msg {
    display: none;
    padding: 12px 14px;
    border-radius: 8px;
    margin-bottom: 14px;
    font-weight: 600;
    line-height: 1.4;
}
.contact-msg.is-error {
    display: block;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}
.contact-msg.is-ok {
    display: block;
    background: #eaf6c8;
    border: 1px solid #c9df7a;
    color: #1a3d18;
}
.contact-success {
    text-align: center;
    padding: 36px 16px 12px;
}
.contact-success h2 {
    margin: 0 0 10px;
    font-family: var(--pb-font-display, "Archivo Black", sans-serif);
    color: var(--pb-court, #0f3d2e);
    text-transform: uppercase;
}
.contact-success p {
    color: var(--pb-muted, #3f5248);
    line-height: 1.5;
}
.contact-back {
    text-align: center;
    margin-top: 24px;
}
.contact-back a {
    color: var(--pb-court, #0f3d2e);
    font-weight: 700;
    text-decoration: none;
}
.contact-back a:hover {
    color: var(--pb-leaf, #1f7a52);
}
@media (max-width: 800px) {
    .contact-grid {
        grid-template-columns: 1fr;
    }
    .contact-hero h1 {
        max-width: none;
    }
    .contact-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="contact-page">
    <section class="contact-hero" aria-label="Contact Pickleball Portal">
        <div class="contact-hero-media" aria-hidden="true"></div>
        <div class="contact-hero-inner">
            <span class="contact-hero-badge">Support</span>
            <h1>Pickleball Portal</h1>
            <p class="lead">Questions about bookings, venues, or partnering? Send a message — we are here to help.</p>
        </div>
    </section>

    <div class="contact-body">
        <div class="contact-grid">
            <aside class="contact-aside">
                <div class="contact-aside-map">
                    <img src="images/brand/hero-court-outdoor.jpg" alt="Pickleball courts in Bohol" width="800" height="400" loading="lazy" />
                </div>
                <div class="contact-aside-body">
                    <h2>Get in touch</h2>
                    <p>We serve players and venue partners across Tagbilaran and Bohol.</p>
                    <ul class="contact-detail">
                        <li>
                            <span class="label">Email</span>
                            <span class="value"><a href="mailto:info@foodhauz.bohollander.com">info@foodhauz.bohollander.com</a></span>
                        </li>
                        <li>
                            <span class="label">Service area</span>
                            <span class="value">Tagbilaran City &amp; Bohol, Philippines</span>
                        </li>
                        <li>
                            <span class="label">Hours</span>
                            <span class="value">Mon–Sat, 9:00 AM – 6:00 PM (PHT)</span>
                        </li>
                        <li>
                            <span class="label">Partners</span>
                            <span class="value"><a href="partner_signup.php">Register your venue →</a></span>
                        </li>
                    </ul>
                </div>
            </aside>

            <div class="contact-form-card" id="contactCard">
                <div id="contactFormWrap">
                    <p class="form-kicker">Message us</p>
                    <h2 class="form-title">Send a message</h2>
                    <p class="intro">Tell us how we can help with courts, bookings, or partnership inquiries.</p>

                    <div id="msgbox" class="contact-msg" role="alert"></div>

                    <form id="frmContactUs" novalidate>
                        <div class="contact-row">
                            <div>
                                <label for="fname">First name *</label>
                                <input type="text" id="fname" name="fname" placeholder="Juan" required autocomplete="given-name" />
                            </div>
                            <div>
                                <label for="lname">Last name *</label>
                                <input type="text" id="lname" name="lname" placeholder="Dela Cruz" required autocomplete="family-name" />
                            </div>
                        </div>

                        <div class="contact-row">
                            <div>
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" placeholder="you@email.com" required autocomplete="email" />
                            </div>
                            <div>
                                <label for="phone">Phone (optional)</label>
                                <input type="tel" id="phone" name="phone" placeholder="09XXXXXXXXX" autocomplete="tel" />
                            </div>
                        </div>

                        <label for="topic">Topic *</label>
                        <select id="topic" name="topic" required>
                            <option value="">Select a topic</option>
                            <option value="booking">Court booking help</option>
                            <option value="venue">Venue / partner support</option>
                            <option value="payment">Payments &amp; memberships</option>
                            <option value="feedback">Feedback</option>
                            <option value="other">Other</option>
                        </select>

                        <label for="subject">Subject *</label>
                        <input type="text" id="subject" name="subject" placeholder="How can we help?" required />

                        <label for="message">Message *</label>
                        <textarea id="message" name="message" rows="5" placeholder="Share a few details so we can respond quickly..." required></textarea>

                        <button type="submit" class="btn-submit" id="btnContactSubmit">Send Message</button>
                    </form>
                </div>

                <div class="contact-success" id="successCard" style="display:none;">
                    <h2>Message sent</h2>
                    <p id="successMsg">Thanks — we received your message and will get back to you soon.</p>
                    <p style="margin-top:22px;">
                        <a href="home.php" class="portal-btn portal-btn-primary">Back to Home</a>
                    </p>
                </div>
            </div>
        </div>

        <p class="contact-back">
            <a href="home.php">← Back to Pickleball Portal</a>
        </p>
    </div>
</div>

<script>
$(document).ready(function() {
    function showMsg(text, ok) {
        var $box = $('#msgbox');
        $box.removeClass('is-error is-ok').addClass(ok ? 'is-ok' : 'is-error').text(text).show();
    }

    $('#frmContactUs').on('submit', function(e) {
        e.preventDefault();
        var fname = $.trim($('#fname').val() || '');
        var lname = $.trim($('#lname').val() || '');
        var email = $.trim($('#email').val() || '');
        var phone = $.trim($('#phone').val() || '');
        var topic = $('#topic').val() || '';
        var subject = $.trim($('#subject').val() || '');
        var message = $.trim($('#message').val() || '');

        if (!fname || !lname || !email || !topic || !subject || !message) {
            showMsg('Please fill in all required fields.', false);
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showMsg('Please enter a valid email address.', false);
            return;
        }

        var $btn = $('#btnContactSubmit').prop('disabled', true).text('Sending...');
        $.ajax({
            type: 'POST',
            url: 'process.php',
            dataType: 'json',
            data: {
                function: 'ContactUs',
                fname: fname,
                lname: lname,
                email: email,
                phone: phone,
                topic: topic,
                subject: subject,
                message: message
            },
            success: function(data) {
                if (data && data.validation_status === 'T') {
                    $('#contactFormWrap').hide();
                    $('#successMsg').text(data.msg || 'Thanks — we received your message.');
                    $('#successCard').show();
                    return;
                }
                showMsg((data && data.msg) ? data.msg : 'Could not send your message. Please try again.', false);
            },
            error: function() {
                showMsg('Could not send your message. Please try again.', false);
            },
            complete: function() {
                $btn.prop('disabled', false).text('Send Message');
            }
        });
    });
});
</script>

<?php include('footer.php'); ?>
