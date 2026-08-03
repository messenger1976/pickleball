<?php

function pb_chat_get_or_create_thread($conn, $businessId, $customersId, $bookingId = 0) {
    $businessId = (int)$businessId;
    $customersId = (int)$customersId;
    $bookingId = (int)$bookingId;

    if ($bookingId > 0) {
        $sql = $conn->query(
            "SELECT * FROM chat_threads
             WHERE business_id=$businessId AND customers_id=$customersId AND booking_id=$bookingId
             LIMIT 1"
        );
        if ($sql && ($row = $sql->fetch_assoc())) {
            return $row;
        }
    }

    $sql = $conn->query(
        "SELECT * FROM chat_threads
         WHERE business_id=$businessId AND customers_id=$customersId AND thread_status='open'
         ORDER BY thread_id DESC LIMIT 1"
    );
    if ($sql && ($row = $sql->fetch_assoc())) {
        return $row;
    }

    $bidSql = $bookingId > 0 ? $bookingId : 'NULL';
    $conn->query(
        "INSERT INTO chat_threads (business_id, customers_id, booking_id, thread_status, created_at, updated_at)
         VALUES ($businessId, $customersId, $bidSql, 'open', NOW(), NOW())"
    );
    $id = (int)$conn->insert_id;
    $sql = $conn->query("SELECT * FROM chat_threads WHERE thread_id=$id LIMIT 1");
    return ($sql && ($row = $sql->fetch_assoc())) ? $row : null;
}

function pb_chat_messages($conn, $threadId, $limit = 100) {
    $threadId = (int)$threadId;
    $limit = max(1, min(300, (int)$limit));
    $list = array();
    $sql = $conn->query(
        "SELECT * FROM chat_messages WHERE thread_id=$threadId ORDER BY message_id ASC LIMIT $limit"
    );
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

function pb_chat_post($conn, $threadId, $senderRole, $senderId, $body) {
    include_once __DIR__ . '/realtime_helper.php';
    $threadId = (int)$threadId;
    $senderId = (int)$senderId;
    $body = trim((string)$body);
    if ($body === '' || $threadId <= 0) {
        return array('ok' => false, 'msg' => 'Empty message.');
    }
    $safeRole = $conn->real_escape_string($senderRole);
    $safeBody = $conn->real_escape_string($body);
    $ok = $conn->query(
        "INSERT INTO chat_messages (thread_id, sender_role, sender_id, message_body, created_at)
         VALUES ($threadId, '$safeRole', $senderId, '$safeBody', NOW())"
    );
    if (!$ok) {
        return array('ok' => false, 'msg' => 'Could not send.');
    }
    $messageId = (int)$conn->insert_id;
    $conn->query("UPDATE chat_threads SET updated_at=NOW() WHERE thread_id=$threadId");

    $thread = $conn->query("SELECT * FROM chat_threads WHERE thread_id=$threadId LIMIT 1");
    $t = $thread ? $thread->fetch_assoc() : null;
    pb_realtime_publish('chat_message', array(
        'thread_id' => $threadId,
        'business_id' => $t ? (int)$t['business_id'] : 0,
        'customers_id' => $t ? (int)$t['customers_id'] : 0,
        'message_id' => $messageId,
        'sender_role' => $senderRole,
        'body' => $body,
        'created_at' => date('Y-m-d H:i:s'),
    ));

    return array('ok' => true, 'message_id' => $messageId, 'msg' => 'Sent');
}

function pb_chat_set_status($conn, $threadId, $status) {
    $allowed = array('open', 'closed', 'flagged');
    $status = strtolower(trim($status));
    if (!in_array($status, $allowed, true)) {
        return array('ok' => false, 'msg' => 'Invalid status.');
    }
    $threadId = (int)$threadId;
    $safe = $conn->real_escape_string($status);
    $ok = $conn->query("UPDATE chat_threads SET thread_status='$safe', updated_at=NOW() WHERE thread_id=$threadId");
    if (!$ok) {
        return array('ok' => false, 'msg' => 'Could not update thread.');
    }
    return array('ok' => true, 'msg' => 'Thread updated.');
}

function pb_chat_threads_for_business($conn, $businessId, $includeClosed = true) {
    $businessId = (int)$businessId;
    $where = "WHERE t.business_id=$businessId";
    if (!$includeClosed) {
        $where .= " AND t.thread_status='open'";
    }
    $list = array();
    $sql = $conn->query(
        "SELECT t.*, c.customers_firstname, c.customers_lastname, c.customers_email,
                (SELECT message_body FROM chat_messages m WHERE m.thread_id=t.thread_id ORDER BY m.message_id DESC LIMIT 1) AS last_message
         FROM chat_threads t
         LEFT JOIN customers c ON c.customers_id = t.customers_id
         $where
         ORDER BY COALESCE(t.updated_at, t.created_at) DESC
         LIMIT 100"
    );
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

function pb_chat_thread_fetch($conn, $threadId) {
    $threadId = (int)$threadId;
    $sql = $conn->query("SELECT * FROM chat_threads WHERE thread_id=$threadId LIMIT 1");
    return ($sql && ($row = $sql->fetch_assoc())) ? $row : null;
}
