<?php

function fh_customer_nav_avatar($conn) {
    if (!isset($_SESSION['customers_id']) || (int)$_SESSION['customers_id'] <= 0) {
        return '';
    }

    if (array_key_exists('customers_profile_pic', $_SESSION)) {
        return (string)$_SESSION['customers_profile_pic'];
    }

    if (!$conn) {
        return '';
    }

    $id = (int)$_SESSION['customers_id'];
    $sql = $conn->query(
        "SELECT customers_profile_pic, customers_firstname, customers_lastname
         FROM customers
         WHERE customers_id = $id
         LIMIT 1"
    );

    if ($sql && $row = $sql->fetch_assoc()) {
        $_SESSION['customers_profile_pic'] = !empty($row['customers_profile_pic'])
            ? $row['customers_profile_pic']
            : '';
        if (empty($_SESSION['customers_firstname']) && !empty($row['customers_firstname'])) {
            $_SESSION['customers_firstname'] = $row['customers_firstname'];
        }
        if (empty($_SESSION['customers_lastname']) && !empty($row['customers_lastname'])) {
            $_SESSION['customers_lastname'] = $row['customers_lastname'];
        }
        return $_SESSION['customers_profile_pic'];
    }

    $_SESSION['customers_profile_pic'] = '';
    return '';
}

function fh_customer_nav_initials() {
    $firstname = '';
    $lastname = '';

    if (!empty($_SESSION['customers_firstname'])) {
        $firstname = trim((string)$_SESSION['customers_firstname']);
    } elseif (!empty($_COOKIE['customers_firstname'])) {
        $firstname = trim((string)$_COOKIE['customers_firstname']);
    }

    if (!empty($_SESSION['customers_lastname'])) {
        $lastname = trim((string)$_SESSION['customers_lastname']);
    }

    $initials = '';
    if ($firstname !== '') {
        $initials .= strtoupper(substr($firstname, 0, 1));
    }
    if ($lastname !== '') {
        $initials .= strtoupper(substr($lastname, 0, 1));
    }
    if ($initials === '' && $firstname !== '') {
        $initials = strtoupper(substr($firstname, 0, 2));
    }

    return $initials !== '' ? $initials : 'U';
}
