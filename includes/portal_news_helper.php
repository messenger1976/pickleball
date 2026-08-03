<?php

function fh_portal_news_table_exists($conn) {
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    $check = $conn->query("SHOW TABLES LIKE 'portal_news'");
    $exists = ($check && $check->num_rows > 0);
    return $exists;
}

function fh_portal_news_fetch_active($conn) {
    if (!$conn || !fh_portal_news_table_exists($conn)) {
        return null;
    }

    $today = date('Y-m-d');
    $safeToday = $conn->real_escape_string($today);

    $sql = $conn->query(
        "SELECT * FROM portal_news
         WHERE news_status = 1
           AND (news_date = '$safeToday' OR (news_is_default = 1 AND news_date IS NULL))
         ORDER BY
           CASE WHEN news_date = '$safeToday' THEN 0 ELSE 1 END,
           news_id DESC
         LIMIT 1"
    );

    if ($sql && $sql->num_rows > 0) {
        return $sql->fetch_assoc();
    }

    return null;
}

function fh_portal_news_fetch($conn, $newsId) {
    $newsId = (int)$newsId;
    if ($newsId <= 0 || !fh_portal_news_table_exists($conn)) {
        return null;
    }
    $sql = $conn->query("SELECT * FROM portal_news WHERE news_id = $newsId LIMIT 1");
    return ($sql && $sql->num_rows > 0) ? $sql->fetch_assoc() : null;
}

function fh_portal_news_list($conn) {
    $items = array();
    if (!fh_portal_news_table_exists($conn)) {
        return $items;
    }
    $sql = $conn->query(
        "SELECT * FROM portal_news
         ORDER BY news_is_default DESC, news_date DESC, news_id DESC"
    );
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $items[] = $row;
        }
    }
    return $items;
}

function fh_portal_news_clear_default($conn, $exceptId = 0) {
    $exceptId = (int)$exceptId;
    $filter = $exceptId > 0 ? " AND news_id <> $exceptId" : '';
    $conn->query("UPDATE portal_news SET news_is_default = 0 WHERE news_is_default = 1$filter");
}

function fh_portal_news_save($conn, $data) {
    if (!fh_portal_news_table_exists($conn)) {
        return array('ok' => false, 'msg' => 'Portal news table is not installed. Run sql/migrate_portal_news.sql.');
    }

    $newsId = isset($data['news_id']) ? (int)$data['news_id'] : 0;
    $title = trim(isset($data['news_title']) ? $data['news_title'] : '');
    $message = trim(isset($data['news_message']) ? $data['news_message'] : '');
    $linkUrl = trim(isset($data['news_link_url']) ? $data['news_link_url'] : '');
    $linkLabel = trim(isset($data['news_link_label']) ? $data['news_link_label'] : '');
    $status = !empty($data['news_status']) ? 1 : 0;
    $isDefault = !empty($data['news_is_default']) ? 1 : 0;
    $newsDate = trim(isset($data['news_date']) ? $data['news_date'] : '');

    if ($title === '') {
        return array('ok' => false, 'msg' => 'Title is required.');
    }
    if ($message === '') {
        return array('ok' => false, 'msg' => 'Message is required.');
    }
    if ($linkLabel === '') {
        $linkLabel = 'Learn more';
    }

    $dateSql = 'NULL';
    if ($newsDate !== '') {
        $ts = strtotime($newsDate);
        if ($ts === false) {
            return array('ok' => false, 'msg' => 'Invalid news date.');
        }
        $dateSql = "'" . $conn->real_escape_string(date('Y-m-d', $ts)) . "'";
    }

    if ($isDefault) {
        fh_portal_news_clear_default($conn, $newsId);
        $dateSql = 'NULL';
    }

    $safeTitle = $conn->real_escape_string($title);
    $safeMessage = $conn->real_escape_string($message);
    $safeLinkUrl = $conn->real_escape_string($linkUrl);
    $safeLinkLabel = $conn->real_escape_string($linkLabel);

    if ($newsId > 0) {
        $conn->query(
            "UPDATE portal_news SET
                news_title = '$safeTitle',
                news_message = '$safeMessage',
                news_link_url = '$safeLinkUrl',
                news_link_label = '$safeLinkLabel',
                news_status = $status,
                news_is_default = $isDefault,
                news_date = $dateSql
             WHERE news_id = $newsId"
        );
        return array('ok' => true, 'msg' => 'News updated.', 'news_id' => $newsId);
    }

    $conn->query(
        "INSERT INTO portal_news
            (news_title, news_message, news_link_url, news_link_label, news_status, news_is_default, news_date)
         VALUES
            ('$safeTitle', '$safeMessage', '$safeLinkUrl', '$safeLinkLabel', $status, $isDefault, $dateSql)"
    );
    return array('ok' => true, 'msg' => 'News created.', 'news_id' => (int)$conn->insert_id);
}

function fh_portal_news_delete($conn, $newsId) {
    $newsId = (int)$newsId;
    if ($newsId <= 0 || !fh_portal_news_table_exists($conn)) {
        return array('ok' => false, 'msg' => 'News item not found.');
    }
    $conn->query("DELETE FROM portal_news WHERE news_id = $newsId");
    return array('ok' => true, 'msg' => 'News deleted.');
}
