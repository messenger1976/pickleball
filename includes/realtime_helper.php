<?php
include_once __DIR__ . '/../admin/payment_config.php';

function pb_realtime_publish($event, $payload) {
    $url = rtrim(PB_REALTIME_URL, '/') . '/publish';
    $body = json_encode(array(
        'secret' => PB_REALTIME_SECRET,
        'event' => $event,
        'payload' => $payload,
    ));
    // Prefer curl; fall back silently
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 1,
            CURLOPT_CONNECTTIMEOUT => 1,
        ));
        @curl_exec($ch);
        @curl_close($ch);
        return;
    }
    $ctx = stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $body,
            'timeout' => 1,
            'ignore_errors' => true,
        ),
    ));
    @file_get_contents($url, false, $ctx);
}
