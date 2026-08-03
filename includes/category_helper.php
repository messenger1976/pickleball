<?php

function fh_category_table_exists($conn) {
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    $check = $conn->query("SHOW TABLES LIKE 'category'");
    $exists = ($check && $check->num_rows > 0);
    return $exists;
}

function fh_category_has_sort_column($conn) {
    static $hasSort = null;
    if ($hasSort !== null) {
        return $hasSort;
    }
    $hasSort = false;
    if (!fh_category_table_exists($conn)) {
        return false;
    }
    $check = $conn->query("SHOW COLUMNS FROM category LIKE 'cat_sort'");
    $hasSort = ($check && $check->num_rows > 0);
    return $hasSort;
}

function fh_category_order_sql($conn) {
    if (fh_category_has_sort_column($conn)) {
        return 'cat_sort ASC, cat_name ASC, cat_id ASC';
    }
    return 'cat_name ASC, cat_id ASC';
}

function fh_category_list($conn, $activeOnly = false) {
    $items = array();
    if (!$conn || !fh_category_table_exists($conn)) {
        return $items;
    }
    $where = $activeOnly ? 'WHERE cat_status = 1' : '';
    $order = fh_category_order_sql($conn);
    $sql = $conn->query("SELECT * FROM category $where ORDER BY $order");
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $items[] = $row;
        }
    }
    return $items;
}

function fh_category_fetch($conn, $catId) {
    $catId = (int)$catId;
    if ($catId <= 0 || !fh_category_table_exists($conn)) {
        return null;
    }
    $sql = $conn->query("SELECT * FROM category WHERE cat_id = $catId LIMIT 1");
    return ($sql && $sql->num_rows > 0) ? $sql->fetch_assoc() : null;
}

function fh_category_options_html($conn, $selectedId = 0, $activeOnly = true) {
    $selectedId = (int)$selectedId;
    $html = '';
    $categories = fh_category_list($conn, $activeOnly);
    if (empty($categories)) {
        return '<option value="0">No categories yet</option>';
    }
    foreach ($categories as $cat) {
        $id = (int)$cat['cat_id'];
        $sel = ($id === $selectedId) ? ' selected' : '';
        $html .= '<option value="' . $id . '"' . $sel . '>'
            . htmlspecialchars($cat['cat_name'])
            . '</option>';
    }
    return $html;
}

function fh_category_item_count($conn, $catId) {
    $catId = (int)$catId;
    if ($catId <= 0) {
        return 0;
    }
    $sql = $conn->query("SELECT COUNT(*) AS c FROM items WHERE cat_id = $catId");
    if ($sql && ($row = $sql->fetch_assoc())) {
        return (int)$row['c'];
    }
    return 0;
}

function fh_category_save($conn, $data) {
    if (!fh_category_table_exists($conn)) {
        return array('ok' => false, 'msg' => 'Category table is not installed. Run sql/migrate_category_setup.sql.');
    }

    $catId = isset($data['cat_id']) ? (int)$data['cat_id'] : 0;
    $name = trim(isset($data['cat_name']) ? $data['cat_name'] : '');
    $status = !empty($data['cat_status']) ? 1 : 0;
    $sort = isset($data['cat_sort']) ? (int)$data['cat_sort'] : 0;
    $hasSort = fh_category_has_sort_column($conn);

    if ($name === '') {
        return array('ok' => false, 'msg' => 'Category name is required.');
    }
    if (strlen($name) > 150) {
        return array('ok' => false, 'msg' => 'Category name must be 150 characters or less.');
    }

    $safeName = $conn->real_escape_string($name);
    $dupSql = $conn->query(
        "SELECT cat_id FROM category
         WHERE cat_name = '$safeName'" . ($catId > 0 ? " AND cat_id <> $catId" : '') . "
         LIMIT 1"
    );
    if ($dupSql && $dupSql->num_rows > 0) {
        return array('ok' => false, 'msg' => 'A category with that name already exists.');
    }

    if ($catId > 0) {
        $existing = fh_category_fetch($conn, $catId);
        if (!$existing) {
            return array('ok' => false, 'msg' => 'Category not found.');
        }
        if ($hasSort) {
            $ok = $conn->query(
                "UPDATE category SET
                    cat_name = '$safeName',
                    cat_status = $status,
                    cat_sort = $sort
                 WHERE cat_id = $catId"
            );
        } else {
            $ok = $conn->query(
                "UPDATE category SET
                    cat_name = '$safeName',
                    cat_status = $status
                 WHERE cat_id = $catId"
            );
        }
        if (!$ok) {
            return array('ok' => false, 'msg' => 'Could not update category.');
        }
        return array('ok' => true, 'msg' => 'Category saved.', 'cat_id' => $catId);
    }

    if ($hasSort) {
        $ok = $conn->query(
            "INSERT INTO category (cat_name, cat_status, cat_sort)
             VALUES ('$safeName', $status, $sort)"
        );
    } else {
        $ok = $conn->query(
            "INSERT INTO category (cat_name, cat_status)
             VALUES ('$safeName', $status)"
        );
    }
    if (!$ok) {
        return array('ok' => false, 'msg' => 'Could not create category.');
    }
    return array('ok' => true, 'msg' => 'Category saved.', 'cat_id' => (int)$conn->insert_id);
}

function fh_category_delete($conn, $catId) {
    $catId = (int)$catId;
    if ($catId <= 0 || !fh_category_table_exists($conn)) {
        return array('ok' => false, 'msg' => 'Category not found.');
    }
    $existing = fh_category_fetch($conn, $catId);
    if (!$existing) {
        return array('ok' => false, 'msg' => 'Category not found.');
    }

    $itemCount = fh_category_item_count($conn, $catId);
    if ($itemCount > 0) {
        return array(
            'ok' => false,
            'msg' => 'Cannot delete — ' . $itemCount . ' food item(s) still use this category. Reassign or deactivate it instead.'
        );
    }

    $ok = $conn->query("DELETE FROM category WHERE cat_id = $catId");
    if (!$ok) {
        return array('ok' => false, 'msg' => 'Could not delete category.');
    }
    return array('ok' => true, 'msg' => 'Category deleted.');
}
