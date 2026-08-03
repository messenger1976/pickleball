<?php
/**
 * Transactional email helpers for bookings (uses same SMTP as ForgotPassword).
 */
use PHPMailer\PHPMailer\PHPMailer;

function pb_mailer_ready() {
    static $loaded = false;
    if ($loaded) {
        return true;
    }
    $base = dirname(__DIR__) . '/phpmailer';
    if (!is_file($base . '/PHPMailer.php')) {
        return false;
    }
    require_once $base . '/Exception.php';
    require_once $base . '/PHPMailer.php';
    require_once $base . '/SMTP.php';
    $loaded = true;
    return true;
}

function pb_send_mail($to, $subject, $htmlBody) {
    $to = trim((string)$to);
    if ($to === '' || !pb_mailer_ready()) {
        return false;
    }
    $smtpUser = getenv('PB_SMTP_USER') ?: (defined('PB_SMTP_USER') ? PB_SMTP_USER : 'info@foodhauz.bohollander.com');
    $smtpPass = getenv('PB_SMTP_PASS') ?: (defined('PB_SMTP_PASS') ? PB_SMTP_PASS : '');
    $smtpHost = getenv('PB_SMTP_HOST') ?: (defined('PB_SMTP_HOST') ? PB_SMTP_HOST : 'mail.supremecluster.com');
    if ($smtpPass === '') {
        return false;
    }
    try {
        $email = new PHPMailer();
        $email->IsHTML(true);
        $email->isSMTP();
        $email->Host = $smtpHost;
        $email->SMTPAuth = true;
        $email->Username = $smtpUser;
        $email->Password = $smtpPass;
        $email->SMTPSecure = 'ssl';
        $email->Port = 465;
        $email->setFrom($smtpUser, 'Pickleball Portal');
        $email->addReplyTo($smtpUser, 'Pickleball Portal');
        $email->Subject = $subject;
        $email->Body = $htmlBody;
        $email->addAddress($to);
        return (bool)$email->send();
    } catch (Exception $e) {
        return false;
    }
}

function pb_email_booking_created($booking) {
    if (!is_array($booking) || empty($booking['customer_email'])) {
        return false;
    }
    $name = trim(($booking['customer_fname'] ?? '') . ' ' . ($booking['customer_lname'] ?? ''));
    $receipt = $booking['booking_receipt_no'] ?? ('#' . $booking['booking_id']);
    $venue = $booking['business_name'] ?? 'Venue';
    $court = $booking['court_name'] ?? ($booking['lesson_title'] ?? 'Court');
    $when = date('D, M j, Y g:i A', strtotime($booking['start_datetime'])) . ' – ' . date('g:i A', strtotime($booking['end_datetime']));
    $status = ucfirst((string)$booking['booking_status']);
    $pay = str_replace('_', ' ', (string)$booking['payment_type']);
    $price = number_format((float)$booking['total_price'], 2);
    $subject = 'Pickleball Portal — Booking ' . $receipt;
    $html = '<html><body>'
        . '<p>Hi ' . htmlspecialchars($name !== '' ? $name : 'Player') . ',</p>'
        . '<p>Your booking has been received.</p>'
        . '<ul>'
        . '<li><strong>Receipt:</strong> ' . htmlspecialchars($receipt) . '</li>'
        . '<li><strong>Venue:</strong> ' . htmlspecialchars($venue) . '</li>'
        . '<li><strong>Court/Lesson:</strong> ' . htmlspecialchars($court) . '</li>'
        . '<li><strong>When:</strong> ' . htmlspecialchars($when) . '</li>'
        . '<li><strong>Status:</strong> ' . htmlspecialchars($status) . '</li>'
        . '<li><strong>Payment:</strong> ' . htmlspecialchars($pay) . ' — ₱' . $price . '</li>'
        . '</ul>'
        . '<p>Thank you,<br>Pickleball Portal</p>'
        . '</body></html>';
    return pb_send_mail($booking['customer_email'], $subject, $html);
}

function pb_email_booking_cancelled($booking, $reason = '') {
    if (!is_array($booking) || empty($booking['customer_email'])) {
        return false;
    }
    $name = trim(($booking['customer_fname'] ?? '') . ' ' . ($booking['customer_lname'] ?? ''));
    $receipt = $booking['booking_receipt_no'] ?? ('#' . $booking['booking_id']);
    $subject = 'Pickleball Portal — Booking cancelled ' . $receipt;
    $html = '<html><body>'
        . '<p>Hi ' . htmlspecialchars($name !== '' ? $name : 'Player') . ',</p>'
        . '<p>Your booking <strong>' . htmlspecialchars($receipt) . '</strong> has been cancelled.</p>'
        . ($reason !== '' ? '<p>Reason: ' . htmlspecialchars($reason) . '</p>' : '')
        . '<p>Thank you,<br>Pickleball Portal</p>'
        . '</body></html>';
    return pb_send_mail($booking['customer_email'], $subject, $html);
}
