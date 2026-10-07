<?php
    session_start();
    include('config.php');
    include_once(__DIR__ . '/../includes/order_helper.php');
    $function = $_POST['function'];
    //global $conn;
    $log = array();
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
    switch($function) {

    	 case('DeleteRecord'):
            $item_id = $_POST['item_id'];
            $item_pix = $_POST['item_pix'];

            $stmtheader = $conn->prepare("delete from items where id=$item_id");
            $stmtheader->execute();
            unlink($item_pix);
            $log['msg']=true;
            break;
         case('EditRecord'):
            $item_id = $_POST['item_id'];
            $adminsql = $conn->query("select * from items where id=".$item_id." limit 1");
            $row = $adminsql->fetch_assoc();
            $log['id'] = $row['id'];
            $log['name'] = $row['name'];
            $log['price'] = $row['price'];
            $log['picture'] = $row['picture'];
            $log['business_id'] = $row['business_id'];
            $log['cat_id'] = isset($row['cat_id']) ? (int)$row['cat_id'] : 0;
            $log['allow_preorder'] = isset($row['allow_preorder']) ? (int)$row['allow_preorder'] : 0;
            break;
        case('LoadCategory'):
            include_once(__DIR__ . '/../includes/category_helper.php');
            if (!fh_auth_is_logged_in()) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Access denied.';
                break;
            }
            $catId = isset($_POST['cat_id']) ? (int)$_POST['cat_id'] : 0;
            $item = fh_category_fetch($conn, $catId);
            if (!$item) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Category not found.';
                break;
            }
            $log['validation_status'] = 'T';
            $log['data'] = $item;
            break;
        case('SaveCategory'):
            include_once(__DIR__ . '/../includes/category_helper.php');
            if (!fh_auth_is_logged_in()) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Access denied.';
                break;
            }
            $result = fh_category_save($conn, array(
                'cat_id' => isset($_POST['cat_id']) ? $_POST['cat_id'] : 0,
                'cat_name' => isset($_POST['cat_name']) ? $_POST['cat_name'] : '',
                'cat_status' => isset($_POST['cat_status']) ? $_POST['cat_status'] : 0,
                'cat_sort' => isset($_POST['cat_sort']) ? $_POST['cat_sort'] : 0,
            ));
            $log['validation_status'] = $result['ok'] ? 'T' : 'F';
            $log['msg'] = $result['msg'];
            if (!empty($result['cat_id'])) {
                $log['cat_id'] = $result['cat_id'];
            }
            break;
        case('DeleteCategory'):
            include_once(__DIR__ . '/../includes/category_helper.php');
            if (!fh_auth_is_logged_in()) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Access denied.';
                break;
            }
            $result = fh_category_delete($conn, isset($_POST['cat_id']) ? $_POST['cat_id'] : 0);
            $log['validation_status'] = $result['ok'] ? 'T' : 'F';
            $log['msg'] = $result['msg'];
            break;
        case('UpdateOrder'):
            $orderid = (int)$_POST['orderid'];
            $status = isset($_POST['status']) ? $_POST['status'] : '';
            $remarks = isset($_POST['remarks']) ? $_POST['remarks'] : '';
            $address = isset($_POST['address']) ? trim($_POST['address']) : '';

            $order = fh_order_fetch_for_admin($conn, $orderid);
            if (!$order) {
                $log['msg'] = false;
                $log['error'] = 'Order not found or access denied.';
                break;
            }

            if ($address !== '') {
                fh_order_update_address($conn, $orderid, $address, false);
            }

            if ($status !== '' && $status !== 'Cancelled') {
                fh_order_set_status($conn, $orderid, $status, array(
                    'orders_remarks' => $remarks,
                ));
            } elseif ($remarks !== '') {
                $safeRemarks = $conn->real_escape_string($remarks);
                $conn->query("UPDATE orders SET orders_remarks='$safeRemarks' WHERE orders_id=$orderid");
            }

            $log['msg'] = true;
            break;
        case('getAdminOrderDetail'):
            $orderid = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
            $order = fh_order_fetch_for_admin($conn, $orderid);
            if (!$order) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Order not found or access denied.';
                break;
            }
            $log['validation_status'] = 'T';
            $log['data'] = fh_order_format_detail_payload($conn, $order);
            break;
        case('deleteAdminOrderItem'):
            $orderid = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
            $detailId = isset($_POST['detail_id']) ? (int)$_POST['detail_id'] : 0;
            $order = fh_order_fetch_for_admin($conn, $orderid);
            if (!$order) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Order not found or access denied.';
                break;
            }
            $result = fh_order_delete_item($conn, $orderid, $detailId, false);
            if (!$result['ok']) {
                $log['validation_status'] = 'F';
                $log['msg'] = $result['msg'];
                break;
            }
            $log['validation_status'] = 'T';
            $log['msg'] = $result['msg'];
            $log['total'] = $result['total'];
            $log['item_count'] = $result['item_count'];
            $log['data'] = fh_order_format_detail_payload($conn, fh_order_fetch($conn, $orderid));
            break;
        case('cancelAdminOrder'):
            $orderid = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
            $reason = isset($_POST['reason']) ? $_POST['reason'] : '';
            $order = fh_order_fetch_for_admin($conn, $orderid);
            if (!$order) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Order not found or access denied.';
                break;
            }
            $result = fh_order_cancel($conn, $orderid, $reason);
            if (!$result['ok']) {
                $log['validation_status'] = 'F';
                $log['msg'] = $result['msg'];
                break;
            }
            $log['validation_status'] = 'T';
            $log['msg'] = $result['msg'];
            break;
        case('updateAdminOrderAddress'):
            $orderid = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
            $address = isset($_POST['address']) ? $_POST['address'] : '';
            $order = fh_order_fetch_for_admin($conn, $orderid);
            if (!$order) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Order not found or access denied.';
                break;
            }
            $result = fh_order_update_address($conn, $orderid, $address, false);
            if (!$result['ok']) {
                $log['validation_status'] = 'F';
                $log['msg'] = $result['msg'];
                break;
            }
            $log['validation_status'] = 'T';
            $log['msg'] = $result['msg'];
            $log['data'] = fh_order_format_detail_payload($conn, fh_order_fetch($conn, $orderid));
            break;
        case('LoadSalesOrder'):
            $htmldata = '<tr class="header">
                    <th width=50>Order No.</th>
                    <th width=70>Trans Date</th>
                    <th width=70>Order Date</th>
                    <th>Full Name</th>
                    <th>Address</th>
                    <th>Company</th>
                    <th>Department</th>
                    <th>Payment Type</th>
                    <th>Delivery Type</th>
                    <th align=right>Total Amount</th>
                    <th>Remarks</th>
                    <th>Status</th>
                    <th>Action</th>
            </tr>';
            $itemsql = $conn->query("select *, company.name as company_name, department.code as department_code from orders inner join company on orders.orders_customers_company=company.id inner join department on orders_customers_department=department.id where orders.orders_status<>'Cancelled' order by orders_menudate desc");
            $itemcount =$itemsql->num_rows;
            if($itemcount>0){
                    $counterrow = 1;
                    while ($row = $itemsql->fetch_assoc()){
                            $orderid= $row['orders_id'];
                            $transdate = date('m/d/Y h:mA',strtotime($row['orders_transdate']));
                            $menudate = date('m/d/Y',strtotime($row['orders_menudate']));
                            $fullname = $row['orders_customers_lname'].', '.$row['orders_customers_fname'];
                            $address = $row['orders_delivery_address'];
                            $company = $row['company_name'];
                            $department = $row['department_code'];
                            $payment = $row['orders_payment_type'];
                            $delivery = $row['orders_shipping_type'];
                            $grandtotal = $row['orders_total_amount'];
                            $status = $row['orders_status'];
                            $remarks = $row['orders_remarks'];
                            $htmldata.= '<input type="hidden" id="'.$orderid.'_transdate" value="'.$transdate.'"/>
                                    <input type="hidden" id="'.$orderid.'_menudate" value="'.$menudate.'"/>
                                    <input type="hidden" id="'.$orderid.'_fullname" value="'.$fullname.'"/>
                                    <input type="hidden" id="'.$orderid.'_address" value="'.$address.'"/>
                                    <input type="hidden" id="'.$orderid.'_company" value="'.$company.'"/>
                                    <input type="hidden" id="'.$orderid.'_department" value="'.$department.'"/>
                                    <input type="hidden" id="'.$orderid.'_payment" value="'.$payment.'"/>
                                    <input type="hidden" id="'.$orderid.'_delivery" value="'.$delivery.'"/>
                                    <input type="hidden" id="'.$orderid.'_grandtotal" value="'.$grandtotal.'"/>
                                    <input type="hidden" id="'.$orderid.'_status" value="'.$status.'"/>
                                    <input type="hidden" id="'.$orderid.'_remarks" value="'.$remarks.'"/>
                                    <tr>
                                    <td>'.$row['orders_id'].'</td>
                                    <td>'.date('m/d/Y h:m:sA',strtotime($row['orders_transdate'])).'</td>
                                    <td>'.date('m/d/Y',strtotime($row['orders_menudate'])).'</td>
                                    <td>'.$fullname.'</td>
                                    <td>'.$row['orders_delivery_address'].'</td>
                                    <td>'.$row['company_name'].'</td>
                                    <td>'.$row['department_code'].'</td>
                                    <td align=center>'.$row['orders_payment_type'].'</td>
                                    <td align=center>'.$row['orders_shipping_type'].'</td>
                                    <td align=right>'.$row['orders_total_amount'].'</td>
                                    <td>'.$row['orders_remarks'].'</td><td align=center>'.$row['orders_status'].'</td><td align=center><img src="images/edit-icon.png" width=25 class="btneditimage" data-orderid="'.$row['orders_id'].'"/></td></tr>';
                    }
            }
            $log['msg']=$htmldata;
            break;
        case('LoadSalesOrderDetailItems'):
            $orderid = $_POST['orderid'];
            $htmldata = '<table id="myTable" width="100%">
                    <tr class="header"><th>Description</th><th align=center>Qty</th><th align=right>Unit Price</th><th align=right>Total</th></tr>
                ';
            $q="SELECT * FROM orders_details INNER JOIN orders ON orders_details.orders_details_orders_id=orders.orders_id INNER JOIN items ON orders_details.orders_details_menu_id=items.id where orders_details_orders_id=$orderid order by orders.orders_id desc,orders.orders_menudate asc";
            $result_set = $conn->query($q);
            while($row =  $result_set->fetch_assoc()){
                $total = $row['orders_details_qty'] * $row['price'];
                $htmldata.='<tr><td>'.$row['name'].'</td><td align=center>'.$row['orders_details_qty'].'</td><td align=right>'.$row['price'].'</td><td align=right>'.number_format($total,2).'</td></tr>';
            }
            $htmldata.='</table>';
            $log['msg']=$htmldata;
            break;
        case('CopyItems'):
            $sourceId = (int)$_POST['source_business_id'];
            $targetId = (int)$_POST['target_business_id'];
            $copyAll = isset($_POST['copy_all']) && (int)$_POST['copy_all'] === 1;
            if (!fh_auth_can_access_business($targetId)) {
                $log['msg'] = false;
                $log['error'] = 'Access denied.';
                break;
            }
            if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
                $log['msg'] = false;
                $log['error'] = 'Invalid source or target restaurant.';
                break;
            }
            if ($copyAll) {
                $where = "business_id = $sourceId";
            } else {
                $itemIds = isset($_POST['item_ids']) ? $_POST['item_ids'] : array();
                if (empty($itemIds) || !is_array($itemIds)) {
                    $log['msg'] = false;
                    $log['error'] = 'No items selected.';
                    break;
                }
                $ids = array_filter(array_map('intval', $itemIds));
                if (empty($ids)) {
                    $log['msg'] = false;
                    $log['error'] = 'No valid items selected.';
                    break;
                }
                $where = "id IN (" . implode(',', $ids) . ") AND business_id = $sourceId";
            }
            $copied = 0;
            $skipped = 0;
            $items = $conn->query("SELECT * FROM items WHERE $where ORDER BY name ASC");
            if ($items) {
                while ($row = $items->fetch_assoc()) {
                    $name = $conn->real_escape_string($row['name']);
                    $exists = $conn->query("SELECT id FROM items WHERE business_id=$targetId AND name='$name' LIMIT 1");
                    if ($exists && $exists->num_rows > 0) {
                        $skipped++;
                        continue;
                    }
                    $catId = (int)$row['cat_id'];
                    $price = (float)$row['price'];
                    $picture = $conn->real_escape_string($row['picture']);
                    $conn->query("INSERT INTO items (cat_id, name, price, picture, business_id) VALUES ($catId, '$name', $price, '$picture', $targetId)");
                    $copied++;
                }
            }
            $log['msg'] = true;
            $log['summary'] = "Copied $copied item(s). Skipped $skipped duplicate(s).";
            break;
        case('LoadUser'):
            if (!fh_auth_can_manage_users()) {
                $log['error'] = 'Access denied';
                break;
            }
            $uid = (int)$_POST['user_id'];
            $u = $conn->query("SELECT * FROM user WHERE user_id = $uid LIMIT 1");
            if ($u && $row = $u->fetch_assoc()) {
                if (!fh_auth_is_super_admin() && (int)$row['user_business_account'] !== fh_auth_user_business_id()) {
                    $log['error'] = 'Access denied';
                    break;
                }
                $log = array_merge($log, $row);
            }
            break;
        case('SaveUser'):
            if (!fh_auth_can_manage_users()) {
                $log['msg'] = false;
                $log['error'] = 'Access denied';
                break;
            }
            $userId = (int)$_POST['user_id'];
            $userName = trim($_POST['user_name']);
            $fullName = trim($_POST['user_fullname']);
            $email = trim($_POST['user_email']);
            $password = trim($_POST['user_password']);
            $role = $_POST['user_role'];
            $bizId = (int)$_POST['user_business_account'];
            $status = (int)$_POST['user_status'];
            $allowedRoles = fh_get_manageable_roles();
            if (!in_array($role, $allowedRoles, true)) {
                $log['msg'] = false;
                $log['error'] = 'Invalid role selected.';
                break;
            }
            if ($role !== FH_ROLE_SUPER && $bizId <= 0) {
                $log['msg'] = false;
                $log['error'] = 'Please assign a restaurant for business users.';
                break;
            }
            if ($role === FH_ROLE_SUPER) {
                $bizId = 0;
            }
            if (!fh_auth_is_super_admin()) {
                $bizId = fh_auth_user_business_id();
                if ($role === FH_ROLE_SUPER) {
                    $log['msg'] = false;
                    $log['error'] = 'Cannot create super admin users.';
                    break;
                }
            }
            if ($userName === '' || $fullName === '') {
                $log['msg'] = false;
                $log['error'] = 'Username and full name are required.';
                break;
            }
            $safeName = $conn->real_escape_string($userName);
            $safeFull = $conn->real_escape_string($fullName);
            $safeEmail = $conn->real_escape_string($email);
            $dup = $conn->query("SELECT user_id FROM user WHERE user_name='$safeName' AND user_id <> $userId LIMIT 1");
            if ($dup && $dup->num_rows > 0) {
                $log['msg'] = false;
                $log['error'] = 'Username already exists.';
                break;
            }
            if ($userId === 0) {
                if ($password === '') {
                    $log['msg'] = false;
                    $log['error'] = 'Password is required for new users.';
                    break;
                }
                $hash = md5($password);
                $conn->query("INSERT INTO user (user_name, user_password, user_fullname, user_role, user_email, user_status, user_business_account) VALUES ('$safeName','$hash','$safeFull','$role','$safeEmail','$status','$bizId')");
            } else {
                $existing = $conn->query("SELECT user_business_account FROM user WHERE user_id = $userId LIMIT 1");
                if ($existing && $er = $existing->fetch_assoc()) {
                    if (!fh_auth_is_super_admin() && (int)$er['user_business_account'] !== fh_auth_user_business_id()) {
                        $log['msg'] = false;
                        $log['error'] = 'Access denied';
                        break;
                    }
                }
                $passSql = $password !== '' ? ", user_password='" . md5($password) . "'" : '';
                $conn->query("UPDATE user SET user_name='$safeName', user_fullname='$safeFull', user_role='$role', user_email='$safeEmail', user_status='$status', user_business_account='$bizId' $passSql WHERE user_id=$userId");
            }
            $log['msg'] = true;
            break;
        case('SaveBusinessPage'):
            $bizId = (int)$_POST['business_id'];
            if (!fh_auth_can_access_business($bizId)) {
                $log['msg'] = false;
                $log['error'] = 'Access denied';
                break;
            }
            fh_ensure_business_page($conn, $bizId);
            $tagline = $conn->real_escape_string($_POST['page_tagline']);
            $about = $conn->real_escape_string($_POST['page_about']);
            $hours = $conn->real_escape_string($_POST['page_hours']);
            $specialties = $conn->real_escape_string($_POST['page_specialties']);
            $amenities = $conn->real_escape_string(isset($_POST['page_amenities']) ? $_POST['page_amenities'] : '');
            $theme = $conn->real_escape_string($_POST['page_theme_color']);
            $published = (int)$_POST['page_published'];
            $preorderEnabled = isset($_POST['preorder_enabled']) ? (int)$_POST['preorder_enabled'] : 0;
            $preorderEnabled = $preorderEnabled === 1 ? 1 : 0;
            $preorderMaxDays = isset($_POST['preorder_max_days']) ? (int)$_POST['preorder_max_days'] : 14;
            if ($preorderMaxDays < 1) {
                $preorderMaxDays = 1;
            }
            if ($preorderMaxDays > 90) {
                $preorderMaxDays = 90;
            }
            $autoConfirm = isset($_POST['auto_confirm']) ? ((int)$_POST['auto_confirm'] === 1 ? 1 : 0) : 1;
            $bookingMaxDays = isset($_POST['booking_max_days']) ? (int)$_POST['booking_max_days'] : $preorderMaxDays;
            if ($bookingMaxDays < 1) {
                $bookingMaxDays = 1;
            }
            if ($bookingMaxDays > 90) {
                $bookingMaxDays = 90;
            }
            $cancelNote = $conn->real_escape_string(isset($_POST['cancellation_note']) ? $_POST['cancellation_note'] : '');
            $hero = '';
            $banner = '';
            $existing = $conn->query("SELECT page_hero_image, page_banner_image FROM business_page WHERE business_id = $bizId LIMIT 1");
            if ($existing && $er = $existing->fetch_assoc()) {
                $hero = $er['page_hero_image'];
                $banner = $er['page_banner_image'];
            }
            $uploadDir = 'images/';
            if (!empty($_FILES['page_hero_image']['name'])) {
                $target = $uploadDir . basename($_FILES['page_hero_image']['name']);
                if (move_uploaded_file($_FILES['page_hero_image']['tmp_name'], $target)) {
                    $hero = $target;
                }
            }
            if (!empty($_FILES['page_banner_image']['name'])) {
                $target = $uploadDir . basename($_FILES['page_banner_image']['name']);
                if (move_uploaded_file($_FILES['page_banner_image']['tmp_name'], $target)) {
                    $banner = $target;
                }
            }
            $safeHero = $conn->real_escape_string($hero);
            $safeBanner = $conn->real_escape_string($banner);
            $now = date('Y-m-d H:i:s');
            $conn->query("UPDATE business_page SET page_tagline='$tagline', page_about='$about', page_hours='$hours', page_specialties='$specialties', page_amenities='$amenities', page_theme_color='$theme', page_published='$published', preorder_enabled='$preorderEnabled', preorder_max_days='$preorderMaxDays', auto_confirm='$autoConfirm', booking_max_days='$bookingMaxDays', cancellation_note='$cancelNote', page_hero_image='$safeHero', page_banner_image='$safeBanner', page_updated='$now' WHERE business_id=$bizId");
            $log['msg'] = true;
            break;
        case('UpdateBusiness'):
            if (!fh_auth_is_super_admin()) {
                $log['msg'] = false;
                $log['error'] = 'Access denied.';
                break;
            }
            $business_id = isset($_POST['business_id']) ? (int)$_POST['business_id'] : 0;
            $business_name = addslashes(isset($_POST['business_name']) ? $_POST['business_name'] : '');
            $address = isset($_POST['address']) ? $_POST['address'] : '';
            $city = isset($_POST['city']) ? $_POST['city'] : '';
            $barangay = isset($_POST['barangay']) ? $_POST['barangay'] : '';
            $province = isset($_POST['province']) ? $_POST['province'] : '';
            $country = isset($_POST['country']) ? $_POST['country'] : '';
            $zipcode = isset($_POST['zipcode']) ? $_POST['zipcode'] : '';
            $email = isset($_POST['email']) ? $_POST['email'] : '';
            $contactperson = isset($_POST['contactperson']) ? $_POST['contactperson'] : '';
            $contactnumber = isset($_POST['contactnumber']) ? $_POST['contactnumber'] : '';
            $status = isset($_POST['status']) ? $_POST['status'] : 0;
            $subdomain = isset($_POST['subdomain']) ? $_POST['subdomain'] : '';
            $receiptPrefix = strtoupper(trim(isset($_POST['receipt_prefix']) ? $_POST['receipt_prefix'] : ''));
            $receiptNext = isset($_POST['receipt_next']) ? (int)$_POST['receipt_next'] : 1;
            if ($receiptNext < 1) {
                $receiptNext = 1;
            }
            $signup_date = date('Y-m-d H:i:s');
            $safeAddress = $conn->real_escape_string($address);
            $safeCity = $conn->real_escape_string($city);
            $safeBarangay = $conn->real_escape_string($barangay);
            $safeProvince = $conn->real_escape_string($province);
            $safeCountry = $conn->real_escape_string($country);
            $safeZip = $conn->real_escape_string($zipcode);
            $safeEmail = $conn->real_escape_string($email);
            $safeContactPerson = $conn->real_escape_string($contactperson);
            $safeContact = $conn->real_escape_string($contactnumber);
            $safeSubdomain = $conn->real_escape_string($subdomain);
            $safeStatus = $conn->real_escape_string($status);
            $safeReceiptPrefix = $conn->real_escape_string($receiptPrefix);
            if ($business_id == 0) {
                if ($receiptPrefix === '') {
                    include_once(__DIR__ . '/../includes/order_helper.php');
                    $receiptPrefix = fh_business_derive_receipt_prefix(stripslashes($business_name));
                    $safeReceiptPrefix = $conn->real_escape_string($receiptPrefix);
                }
                $sql = "INSERT INTO business_account (business_name, business_address, business_city, business_barangay, business_province, business_country, business_zipcode, business_email, business_contact, business_cont_person, business_signup_date, business_status, business_subdomain, business_receipt_prefix, business_receipt_next) VALUES ('$business_name','$safeAddress','$safeCity','$safeBarangay','$safeProvince','$safeCountry','$safeZip','$safeEmail','$safeContact','$safeContactPerson','$signup_date','$safeStatus','$safeSubdomain','$safeReceiptPrefix',$receiptNext)";

                if ($conn->query($sql) === TRUE) {
                    $last_id = $conn->insert_id;
                    fh_ensure_business_page($conn, $last_id);
                    $log['msg'] = true;
                } else {
                    $log['msg'] = false;
                }
            } else {
                $stmtheader = $conn->prepare("update business_account set business_name='$business_name',business_address='$safeAddress',business_city='$safeCity',business_barangay='$safeBarangay',business_province='$safeProvince',business_country='$safeCountry', business_zipcode='$safeZip', business_email='$safeEmail', business_contact='$safeContact', business_cont_person='$safeContactPerson', business_status='$safeStatus',business_subdomain='$safeSubdomain', business_receipt_prefix='$safeReceiptPrefix', business_receipt_next=$receiptNext where business_id=$business_id");
                $stmtheader->execute();
                fh_ensure_business_page($conn, (int)$business_id);
                $log['msg'] = true;
            }
            break;
        case('LoadBusiness'):
            include_once(__DIR__ . '/../includes/portal_subscription_helper.php');
            if (!fh_auth_is_super_admin()) {
                $log['msg'] = false;
                $log['error'] = 'Access denied.';
                break;
            }
            $businessId = isset($_POST['business_id']) ? (int)$_POST['business_id'] : 0;
            $bsql = $conn->query("SELECT * FROM business_account WHERE business_id = $businessId LIMIT 1");
            if (!$bsql || !($row = $bsql->fetch_assoc())) {
                $log['msg'] = false;
                $log['error'] = 'Business not found.';
                break;
            }
            $subState = fh_portal_sub_access_state($conn, $businessId);
            $row['portal_status_key'] = 'inactive';
            $row['portal_status_label'] = ((int)$row['business_status'] === 1) ? 'Active' : 'Inactive';
            if ((int)$row['business_status'] === 1) {
                if ($subState['state'] === 'trialing') {
                    $row['portal_status_key'] = 'trial';
                    $row['portal_status_label'] = 'Trial Account';
                } elseif ($subState['state'] === 'expired') {
                    $row['portal_status_key'] = 'expired';
                    $row['portal_status_label'] = 'Expired Trial';
                } else {
                    $row['portal_status_key'] = 'active';
                    $row['portal_status_label'] = 'Active';
                }
            }
            $log['msg'] = true;
            $log['data'] = $row;
            break;
        case('LoginProcess'):
            header("Access-Control-Allow-Origin: *");
            header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
            $username = $_POST['user'];
            $password = md5($_POST['psw']);
            $safeUser = $conn->real_escape_string($username);
            $usersql = $conn->query("select * from user where user_name='$safeUser' and user_password='$password' and user_status=1 limit 1");
            $usercount =$usersql->num_rows;
            if($usercount>0){
                $row = $usersql->fetch_assoc();
                fh_auth_set_session($row);
                fh_auth_exit_business();
                $log['user_id']=$_SESSION['user_id'];
                $log['msg']="index.php";
            }else{
                $log['msg']=false;
            }
            break;
        case('LoadMenuSetup'):
            header("Access-Control-Allow-Origin: *");
            $menudate = $_POST['menudate'];
            $itemsql = $conn->query("select * from items LEFT JOIN menu_listing ON (items.id=menu_listing.menu_item and menu_listing.menu_date='$menudate')  order by items.name asc");
            $itemcount =$itemsql->num_rows;
            $htmlbody="";
            if($itemcount>0){
                $counterrow = 1;
                while ($row = $itemsql->fetch_assoc()){
                    if($row['menu_item']==''){
                        $buttonaction = '<input type="hidden" name="flag" value=1/><button class="btnConfirm" onclick="AddMenuSetup(\''.$row['id'].'\',\'1\',\''.$row['price'].'\',\''.$row['menu_id'].'\');">Add</button>';
                    }else{
                        $buttonaction = '<input type="hidden" name="flag" value=0/><button class="btnConfirm" id="btndelete"  onclick="AddMenuSetup(\''.$row['id'].'\',\'0\',\''.$row['price'].'\',\''.$row['menu_id'].'\');">Remove</button>';
                    }

                    if($counterrow==1){
                        $htmlbody.='<div class="w3-row-padding w3-margin-top">';
                    }
                    $htmlbody.='
                    <div class="w3-third">


                        <div class="w3-card">
                            <img src="https://foodhauz.bohollander.com/admin/'.$row['picture'].'" style="width:100%">
                            <div class="w3-container">
                                <h5>'.$row['name'].'</h5>
                                <h5><input type="number" name="price" value="'.$row['price'].'" style="width:100%;"/></h5>
                            </div>
                            <div class="btnline"><center>
                                 '.$buttonaction.'
                                </center>
                            </div>
                        </div>

                    </div>';
                    if($counterrow==3 || $counterrow==$itemcount){
                     $htmlbody.='</div>';
                     $counterrow=1;
                    }else{
                      $counterrow++;
                    }
                }






            }
            $log['msg']=$htmlbody;
            break;

        case('SaveMenuSetup'):
            if(isset($_POST['flag']) && $_POST['flag']==1){
                $menudate1 = $_POST['menudate'];
                $item_id = $_POST['item_id'];
                $price = $_POST['price'];
                $stmtheader = $conn->prepare("INSERT INTO menu_listing (menu_date,menu_item,menu_item_price) VALUES ('$menudate1','$item_id','$price')");
                $stmtheader->execute();
            }
            if(isset($_POST['flag']) && $_POST['flag']==0){
                $menu_id = $_POST['menu_id'];
                $stmtheader = $conn->prepare("delete from menu_listing where menu_id=$menu_id");
                $stmtheader->execute();
            }
            $log['status'] = 1;
            $log['message'] = 'Form data submitted successfully!';
            break;

        case('LoadFoodItems'):
            header("Access-Control-Allow-Origin: *");
            header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
            $htmlbody="";
            $itemsql = $conn->query("select * from items order by name asc");
            $itemcount =$itemsql->num_rows;
            if($itemcount>0){
                $counterrow = 1;
                while ($row = $itemsql->fetch_assoc()){
                    if($counterrow==1){
                        $htmlbody.='<div class="w3-row-padding w3-margin-top">';
                    }
                    $htmlbody.='
                    <div class="w3-third">
                        <div class="w3-card">
                            <img src="https://foodhauz.bohollander.com/admin/'.$row['picture'].'" style="width:100%">
                            <div class="w3-container">
                                <h5>'.$row['name'].'</h5>
                                <h5>'.$row['price'].'</h5>
                            </div>
                            <div class="btnline"><center>
                                <button onclick="popupwindowshow('.$row['id'].');">Edit</button>
                                <button class="btndelete" onclick="deletewindow('.$row['id'].',\''.$row['picture'].'\');">Delete</button></center>
                            </div>
                        </div>
                    </div>';
                    if($counterrow==3 || $counterrow==$itemcount){
                     $htmlbody.='</div>';
                     $counterrow=1;
                    }else{
                      $counterrow++;
                    }
                }


            }
            $log['msg']=$htmlbody;
            break;
        case('saveFoodItem'):
            $log = array(
                'status' => 0,
                'message' => 'Form submission failed, please try again.'
            );


            $uploadDir = 'images/';
            // If form is submitted
            if(isset($_POST['itemname']) || isset($_POST['itemprice']) || isset($_POST['itempix'])){
                // Get the submitted form data
                $cat_id=1;
                $itemname=$_POST['itemname'];
                $itemprice=$_POST['itemprice'];
                $item_id=$_POST['item_id'];

                // Check whether submitted data is not empty
                if(!empty($itemname) && !empty($itemprice)){
                    // Validate email

                    $uploadStatus = 1;

                    // Upload file
                    $uploadedFile = '';
                    if(!empty($_FILES["itempix"]["name"])){

                        // File path config
                        $fileName = basename($_FILES["itempix"]["name"]);
                        $targetFilePath = $uploadDir . $fileName;
                        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

                        // Allow certain file formats
                        $allowTypes = array('JPG','PNG','JPEG','jpg', 'png', 'jpeg');
                        if(in_array($fileType, $allowTypes)){
                            // Upload file to the server
                            if(move_uploaded_file($_FILES["itempix"]["tmp_name"], $targetFilePath)){
                                $uploadedFile = $fileName;
                            }else{
                                $uploadStatus = 0;
                                $log['message'] = 'Sorry, there was an error uploading your file.';
                            }
                        }else{
                            $uploadStatus = 0;
                            $log['message'] = 'Sorry, only JPG, JPEG, & PNG files are allowed to upload.';
                        }
                    }else{
                       $uploadStatus=0;
                    }

                    if($uploadStatus == 1){

                        if($item_id==0){
                            $stmtheader = $conn->prepare("INSERT INTO items (cat_id,name,price, picture) VALUES ('$cat_id','$itemname','$itemprice','$targetFilePath')");
                            $stmtheader->execute();
                        }else{
                            $stmtheader = $conn->prepare("update items set cat_id='$cat_id',name='$itemname',price='$itemprice', picture='$targetFilePath' where id=$item_id");
                            $stmtheader->execute();
                        }
                        $log['status'] = 1;
                        $log['message'] = 'Form data submitted successfully!';

                    }else{
                        if($item_id==0){
                            $stmtheader = $conn->prepare("INSERT INTO items (cat_id,name,price) VALUES ('$cat_id','$itemname','$itemprice')");
                            $stmtheader->execute();
                        }else{
                            $stmtheader = $conn->prepare("update items set cat_id='$cat_id',name='$itemname',price='$itemprice' where id=$item_id");
                            $stmtheader->execute();
                        }
                        $log['status'] = 1;
                        $log['message'] = 'Form data submitted successfully! but no image.';
                    }

                }else{
                     $log['message'] = 'Please fill all the mandatory fields (name and email).';
                }
            }

            break;
        case('LoadOrders'):
            $menudate = $_POST['menudate'];
            if(isset($_POST['filterstatus']) && $_POST['filterstatus']!=='All'){
                $filteroption =  "and orders.orders_status='".$_POST['filterstatus']."'";
            }else{
                $filteroption="and orders.orders_status<>'Cancelled'";
            }
            $htmlbody='<table id="myTable">
            <tr class="header">
                    <th width=50>Order No.</th>
                    <th width=70>Trans Date</th>
                    <th width=70>Order Date</th>
                    <th>Full Name</th>
                    <th>Address</th>
                    <th>Company</th>
                    <th>Department</th>
                    <th>Payment Type</th>
                    <th>Delivery Type</th>
                    <th align=right>Total Amount</th>
                    <th>Remarks</th>
                    <th>Status</th>
                    <th>Action</th>
            </tr>';
             $itemsql = $conn->query("select *, company.name as company_name, department.code as department_code from orders left join company on orders.orders_customers_company=company.id left join department on orders_customers_department=department.id where orders_menudate='".date('y-m-d',strtotime($menudate))."'  $filteroption order by orders_menudate desc, orders_transdate desc");
            $itemcount =$itemsql->num_rows;
            if($itemcount>0){
                $counterrow = 1;
                while ($row = $itemsql->fetch_assoc()){
                    $orderid= $row['orders_id'];
                    $transdate = date('m/d/Y h:i:sA',strtotime($row['orders_transdate']));
                    $menudate = date('m/d/Y',strtotime($row['orders_menudate']));
                    $fullname = $row['orders_customers_lname'].', '.$row['orders_customers_fname'];
                    $address = $row['orders_delivery_address'];
                    $company = $row['company_name'];
                    $department = $row['department_code'];
                    $payment = $row['orders_payment_type'];
                    $delivery = $row['orders_shipping_type'];
                    $grandtotal = $row['orders_total_amount'];
                    $status = $row['orders_status'];
                    $remarks = $row['orders_remarks'];
                    $contact = $row['orders_customers_contact'];
                    $htmlbody.= '<input type="hidden" id="'.$orderid.'_transdate" value="'.$transdate.'"/>
                        <input type="hidden" id="'.$orderid.'_menudate" value="'.$menudate.'"/>
                        <input type="hidden" id="'.$orderid.'_fullname" value="'.$fullname.'"/>
                        <input type="hidden" id="'.$orderid.'_address" value="'.$address.'"/>
                        <input type="hidden" id="'.$orderid.'_company" value="'.$company.'"/>
                        <input type="hidden" id="'.$orderid.'_department" value="'.$department.'"/>
                        <input type="hidden" id="'.$orderid.'_payment" value="'.$payment.'"/>
                        <input type="hidden" id="'.$orderid.'_delivery" value="'.$delivery.'"/>
                        <input type="hidden" id="'.$orderid.'_grandtotal" value="'.$grandtotal.'"/>
                        <input type="hidden" id="'.$orderid.'_status" value="'.$status.'"/>
                        <input type="hidden" id="'.$orderid.'_remarks" value="'.$remarks.'"/>
                        <input type="hidden" id="'.$orderid.'_contact" value="'.$contact.'"/>
                        <tr>
                        <td>'.$row['orders_id'].'</td>
                        <td>'.date('m/d/Y h:i:sA',strtotime($row['orders_transdate'])).'</td>
                        <td>'.date('m/d/Y',strtotime($row['orders_menudate'])).'</td>
                        <td>'.$fullname.'</td>
                        <td>'.$row['orders_delivery_address'].'</td>
                        <td>'.$row['company_name'].'</td>
                        <td>'.$row['department_code'].'</td>
                        <td align=center>'.$row['orders_payment_type'].'</td>
                        <td align=center>'.$row['orders_shipping_type'].'</td>
                        <td align=right>'.$row['orders_total_amount'].'</td>
                        <td>'.$row['orders_remarks'].'</td><td align=center>'.$row['orders_status'].'</td><td align=center><a href="#" class="btneditimage" onclick="popupwindow(\''.$row['orders_id'].'\');"><img src="https://foodhauz.bohollander.com/admin/images/edit-icon.png" width=25/></a></td></tr>';
                }
            }
            $log['msg']=$htmlbody;
            break;
    case('LoadCustomers'):
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
        $htmlbody='<table id="myTable">
    <tr class="header">
            <th>Email</th>
            <th>Lastname</th>
            <th>Firstname</th>
            <th>Company</th>
            <th>Department</th>
            <th>Action</th>
    </tr>';
        $itemsql = $conn->query("select *, company.name as company_name,department.name as department_name from customers left join company on customers.customers_company=company.id LEFT join department on customers.customers_department=department.id order by customers.customers_lastname, customers_firstname");
        $itemcount =$itemsql->num_rows;
        if($itemcount>0){
            $counterrow = 1;
            while ($row = $itemsql->fetch_assoc()){

                $htmlbody.= '<tr>
                 <td>'.htmlspecialchars($row['customers_email']).'</td>
                    <td>'.htmlspecialchars($row['customers_lastname']).'</td>
                    <td>'.htmlspecialchars($row['customers_firstname']).'</td>
                    <td>'.htmlspecialchars($row['company_name']).'</td>
                    <td>'.htmlspecialchars($row['department_name']).'</td>
                    <td align=center><a href="#" class="btneditimage" onclick="popupwindow(\''.$row['customers_id'].'\');"><img src="https://foodhauz.bohollander.com/admin/images/edit-icon.png" width=25/></a></td>
                  </tr>
                ';

            }


        }
        $log['msg']=$htmlbody;
        break;
        case('LoadPortalNews'):
            include_once(__DIR__ . '/../includes/portal_news_helper.php');
            if (!fh_auth_is_super_admin()) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Access denied.';
                break;
            }
            $newsId = isset($_POST['news_id']) ? (int)$_POST['news_id'] : 0;
            $item = fh_portal_news_fetch($conn, $newsId);
            if (!$item) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'News not found.';
                break;
            }
            $log['validation_status'] = 'T';
            $log['data'] = $item;
            break;
        case('SavePortalNews'):
            include_once(__DIR__ . '/../includes/portal_news_helper.php');
            if (!fh_auth_is_super_admin()) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Access denied.';
                break;
            }
            $result = fh_portal_news_save($conn, array(
                'news_id' => isset($_POST['news_id']) ? $_POST['news_id'] : 0,
                'news_title' => isset($_POST['news_title']) ? $_POST['news_title'] : '',
                'news_message' => isset($_POST['news_message']) ? $_POST['news_message'] : '',
                'news_link_url' => isset($_POST['news_link_url']) ? $_POST['news_link_url'] : '',
                'news_link_label' => isset($_POST['news_link_label']) ? $_POST['news_link_label'] : '',
                'news_status' => isset($_POST['news_status']) ? $_POST['news_status'] : 0,
                'news_is_default' => isset($_POST['news_is_default']) ? $_POST['news_is_default'] : 0,
                'news_date' => isset($_POST['news_date']) ? $_POST['news_date'] : '',
            ));
            $log['validation_status'] = $result['ok'] ? 'T' : 'F';
            $log['msg'] = $result['msg'];
            if (!empty($result['news_id'])) {
                $log['news_id'] = $result['news_id'];
            }
            break;
        case('DeletePortalNews'):
            include_once(__DIR__ . '/../includes/portal_news_helper.php');
            if (!fh_auth_is_super_admin()) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Access denied.';
                break;
            }
            $result = fh_portal_news_delete($conn, isset($_POST['news_id']) ? $_POST['news_id'] : 0);
            $log['validation_status'] = $result['ok'] ? 'T' : 'F';
            $log['msg'] = $result['msg'];
            break;
        case('LoadPortalSubscription'):
            include_once(__DIR__ . '/../includes/portal_subscription_helper.php');
            if (!fh_auth_is_super_admin()) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Access denied.';
                break;
            }
            $log['validation_status'] = 'T';
            $log['data'] = fh_portal_sub_get_settings($conn);
            $log['preview'] = fh_portal_sub_pricing_preview($conn);
            break;
        case('SavePortalSubscription'):
            include_once(__DIR__ . '/../includes/portal_subscription_helper.php');
            if (!fh_auth_is_super_admin()) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Access denied.';
                break;
            }
            $result = fh_portal_sub_save_settings($conn, array(
                'trial_enabled' => isset($_POST['trial_enabled']) ? $_POST['trial_enabled'] : 0,
                'trial_days' => isset($_POST['trial_days']) ? $_POST['trial_days'] : 7,
                'monthly_fee' => isset($_POST['monthly_fee']) ? $_POST['monthly_fee'] : 1500,
                'yearly_discount_pct' => isset($_POST['yearly_discount_pct']) ? $_POST['yearly_discount_pct'] : 10,
                'paymongo_fee_percent' => isset($_POST['paymongo_fee_percent']) ? $_POST['paymongo_fee_percent'] : 2.9,
                'paymongo_fee_fixed' => isset($_POST['paymongo_fee_fixed']) ? $_POST['paymongo_fee_fixed'] : 15,
                'signup_blurb' => isset($_POST['signup_blurb']) ? $_POST['signup_blurb'] : '',
            ));
            $log['validation_status'] = $result['ok'] ? 'T' : 'F';
            $log['msg'] = $result['msg'];
            if ($result['ok']) {
                $log['data'] = fh_portal_sub_get_settings($conn);
                $log['preview'] = fh_portal_sub_pricing_preview($conn);
            }
            break;
        case('StartVenueSubscriptionCheckout'):
            include_once(__DIR__ . '/../includes/portal_subscription_helper.php');
            include_once(__DIR__ . '/../includes/payment_helper.php');
            if (!fh_auth_is_logged_in()) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Please sign in.';
                break;
            }
            $bizId = 0;
            if (fh_auth_is_managing_business()) {
                $bizId = fh_auth_managing_business_id();
            } elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === FH_ROLE_BUSINESS_ADMIN) {
                $bizId = fh_auth_user_business_id();
            }
            if ($bizId <= 0 || (!fh_auth_is_super_admin() && (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== FH_ROLE_BUSINESS_ADMIN))) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Access denied.';
                break;
            }
            if (!fh_auth_can_access_business($bizId)) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Access denied.';
                break;
            }
            $cycle = (isset($_POST['billing_cycle']) && $_POST['billing_cycle'] === 'yearly') ? 'yearly' : 'monthly';
            $months = isset($_POST['months']) ? (int)$_POST['months'] : 1;
            $sub = fh_portal_sub_fetch_by_business($conn, $bizId);
            if (!$sub) {
                $ownerId = 0;
                if (!fh_auth_is_super_admin()) {
                    $ownerId = (int)$_SESSION['user_id'];
                } else {
                    $ownerSql = $conn->query(
                        "SELECT user_id FROM user
                         WHERE user_business_account=$bizId AND user_role='business_admin'
                         ORDER BY user_id ASC LIMIT 1"
                    );
                    if ($ownerSql && ($orow = $ownerSql->fetch_assoc())) {
                        $ownerId = (int)$orow['user_id'];
                    }
                }
                $created = fh_portal_sub_create_pending($conn, array(
                    'business_id' => $bizId,
                    'owner_user_id' => $ownerId,
                    'signup_intent' => 'subscribe',
                    'billing_cycle' => $cycle,
                    'months' => $months,
                ));
                if (!$created['ok']) {
                    $log['validation_status'] = 'F';
                    $log['msg'] = $created['msg'];
                    break;
                }
                $sub = fh_portal_sub_fetch($conn, $created['subscription_id']);
            }
            $prep = fh_portal_sub_prepare_payment($conn, (int)$sub['subscription_id'], $cycle, $months);
            if (!$prep['ok']) {
                $log['validation_status'] = 'F';
                $log['msg'] = $prep['msg'];
                break;
            }
            $amounts = $prep['amounts'];
            $successUrl = pb_app_base_url() . '/admin/subscription.php?paid=1&business_id=' . $bizId;
            $cancelUrl = pb_app_base_url() . '/admin/subscription.php?paid=0&business_id=' . $bizId;
            $checkout = pb_paymongo_create_checkout($conn, array(
                'amount' => $amounts['total'],
                'description' => 'Venue subscription — ' . ($cycle === 'yearly' ? 'yearly' : ($amounts['months'] . ' month(s)')),
                'business_id' => $bizId,
                'platform_subscription_id' => (int)$sub['subscription_id'],
                'credentials' => 'platform',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ));
            if (empty($checkout['ok'])) {
                $log['validation_status'] = 'F';
                $log['msg'] = isset($checkout['msg']) ? $checkout['msg'] : 'Unable to start checkout.';
                break;
            }
            $log['validation_status'] = 'T';
            $log['checkout_url'] = $checkout['checkout_url'];
            $log['msg'] = 'Checkout ready.';
            break;
        case('GetCustomerDepartments'):
            if (!fh_auth_is_super_admin()) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Access denied.';
                break;
            }
            $companyId = isset($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
            $html = '<option value="0">N/A</option>';
            if ($companyId > 0) {
                $deptsql = $conn->query("SELECT id, name FROM department WHERE company_id = $companyId ORDER BY name ASC");
                if ($deptsql) {
                    while ($drow = $deptsql->fetch_assoc()) {
                        $html .= '<option value="' . (int)$drow['id'] . '">' . htmlspecialchars($drow['name']) . '</option>';
                    }
                }
            }
            $log['validation_status'] = 'T';
            $log['html'] = $html;
            break;
        case('LoadCustomer'):
            if (!fh_auth_is_super_admin()) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Access denied.';
                break;
            }
            $customerId = isset($_POST['customers_id']) ? (int)$_POST['customers_id'] : 0;
            $csql = $conn->query("SELECT * FROM customers WHERE customers_id = $customerId LIMIT 1");
            if (!$csql || !($row = $csql->fetch_assoc())) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Customer not found.';
                break;
            }
            unset($row['customers_password']);
            $log['validation_status'] = 'T';
            $log['data'] = $row;
            break;
        case('SaveCustomer'):
            if (!fh_auth_is_super_admin()) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Access denied.';
                break;
            }
            $customerId = isset($_POST['customers_id']) ? (int)$_POST['customers_id'] : 0;
            $email = trim(isset($_POST['customers_email']) ? $_POST['customers_email'] : '');
            $password = trim(isset($_POST['customers_password']) ? $_POST['customers_password'] : '');
            $firstname = trim(isset($_POST['customers_firstname']) ? $_POST['customers_firstname'] : '');
            $lastname = trim(isset($_POST['customers_lastname']) ? $_POST['customers_lastname'] : '');
            $phone = trim(isset($_POST['customers_phone']) ? $_POST['customers_phone'] : '');
            $company = isset($_POST['customers_company']) ? (int)$_POST['customers_company'] : 0;
            $department = isset($_POST['customers_department']) ? (int)$_POST['customers_department'] : 0;
            $address = trim(isset($_POST['customers_complete_address']) ? $_POST['customers_complete_address'] : '');
            $country = trim(isset($_POST['customers_country']) ? $_POST['customers_country'] : '');
            $province = trim(isset($_POST['customers_province']) ? $_POST['customers_province'] : '');
            $town = trim(isset($_POST['customers_town']) ? $_POST['customers_town'] : '');
            $barangay = trim(isset($_POST['customers_barangay']) ? $_POST['customers_barangay'] : '');
            $zipcode = trim(isset($_POST['customers_zipcode']) ? $_POST['customers_zipcode'] : '');
            $verify = isset($_POST['customers_verify']) ? (int)$_POST['customers_verify'] : 0;

            if ($email === '') {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Email is required.';
                break;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Please enter a valid email address.';
                break;
            }
            if ($customerId <= 0 && $password === '') {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Password is required for new customers.';
                break;
            }
            if ($password !== '' && strlen($password) < 6) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Password must be at least 6 characters.';
                break;
            }

            $safeEmail = $conn->real_escape_string($email);
            $dupCheck = $conn->query("SELECT customers_id FROM customers WHERE customers_email = '$safeEmail' AND customers_id <> $customerId LIMIT 1");
            if ($dupCheck && $dupCheck->num_rows > 0) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Another customer already uses this email address.';
                break;
            }

            $safeFirst = $conn->real_escape_string($firstname);
            $safeLast = $conn->real_escape_string($lastname);
            $safePhone = $conn->real_escape_string($phone);
            $safeAddress = $conn->real_escape_string($address);
            $safeCountry = $conn->real_escape_string($country);
            $safeProvince = $conn->real_escape_string($province);
            $safeTown = $conn->real_escape_string($town);
            $safeBarangay = $conn->real_escape_string($barangay);
            $safeZipcode = $conn->real_escape_string($zipcode);
            $passwordSql = '';
            if ($password !== '') {
                $hashed = md5($password);
                $passwordSql = ", customers_password = '$hashed'";
            }

            if ($customerId <= 0) {
                $hashed = md5($password);
                $sql = "INSERT INTO customers (
                    customers_email, customers_password, customers_firstname, customers_lastname,
                    customers_phone, customers_company, customers_department,
                    customers_complete_address, customers_country, customers_province,
                    customers_town, customers_barangay, customers_zipcode, customers_verify
                ) VALUES (
                    '$safeEmail', '$hashed', '$safeFirst', '$safeLast',
                    '$safePhone', $company, $department,
                    '$safeAddress', '$safeCountry', '$safeProvince',
                    '$safeTown', '$safeBarangay', '$safeZipcode', $verify
                )";
            } else {
                $sql = "UPDATE customers SET
                    customers_email = '$safeEmail',
                    customers_firstname = '$safeFirst',
                    customers_lastname = '$safeLast',
                    customers_phone = '$safePhone',
                    customers_company = $company,
                    customers_department = $department,
                    customers_complete_address = '$safeAddress',
                    customers_country = '$safeCountry',
                    customers_province = '$safeProvince',
                    customers_town = '$safeTown',
                    customers_barangay = '$safeBarangay',
                    customers_zipcode = '$safeZipcode',
                    customers_verify = $verify
                    $passwordSql
                    WHERE customers_id = $customerId";
            }

            if ($conn->query($sql)) {
                $log['validation_status'] = 'T';
                $log['msg'] = $customerId > 0 ? 'Customer updated.' : 'Customer created.';
            } else {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Unable to save customer.';
            }
            break;
        case('DeleteCustomer'):
            if (!fh_auth_is_super_admin()) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Access denied.';
                break;
            }
            $customerId = isset($_POST['customers_id']) ? (int)$_POST['customers_id'] : 0;
            if ($customerId <= 0) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Invalid customer.';
                break;
            }
            $orderCheck = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE orders_customers_id = $customerId");
            $orderCount = ($orderCheck && ($orow = $orderCheck->fetch_assoc())) ? (int)$orow['c'] : 0;
            if ($orderCount > 0) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Cannot delete this customer because they have existing orders.';
                break;
            }
            $picSql = $conn->query("SELECT customers_profile_pic FROM customers WHERE customers_id = $customerId LIMIT 1");
            if ($picSql && ($prow = $picSql->fetch_assoc()) && !empty($prow['customers_profile_pic'])) {
                $picPath = __DIR__ . '/../' . ltrim($prow['customers_profile_pic'], '/');
                if (is_file($picPath)) {
                    @unlink($picPath);
                }
            }
            if ($conn->query("DELETE FROM customers WHERE customers_id = $customerId")) {
                $log['validation_status'] = 'T';
                $log['msg'] = 'Customer deleted.';
            } else {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Unable to delete customer.';
            }
            break;
        case('SaveMyProfile'):
            if (!fh_auth_is_logged_in()) {
                $log['msg'] = false;
                $log['error'] = 'Access denied.';
                break;
            }
            $userId = (int)$_SESSION['user_id'];
            $userName = trim(isset($_POST['user_name']) ? $_POST['user_name'] : '');
            $fullName = trim(isset($_POST['user_fullname']) ? $_POST['user_fullname'] : '');
            $email = trim(isset($_POST['user_email']) ? $_POST['user_email'] : '');
            $password = trim(isset($_POST['user_password']) ? $_POST['user_password'] : '');

            if ($userName === '' || $fullName === '') {
                $log['msg'] = false;
                $log['error'] = 'Username and full name are required.';
                break;
            }
            if ($password !== '' && strlen($password) < 6) {
                $log['msg'] = false;
                $log['error'] = 'Password must be at least 6 characters.';
                break;
            }

            $safeName = $conn->real_escape_string($userName);
            $safeFull = $conn->real_escape_string($fullName);
            $safeEmail = $conn->real_escape_string($email);
            $dup = $conn->query("SELECT user_id FROM user WHERE user_name='$safeName' AND user_id <> $userId LIMIT 1");
            if ($dup && $dup->num_rows > 0) {
                $log['msg'] = false;
                $log['error'] = 'Username already exists.';
                break;
            }

            $passSql = $password !== '' ? ", user_password='" . md5($password) . "'" : '';
            if ($conn->query(
                "UPDATE user SET
                    user_name='$safeName',
                    user_fullname='$safeFull',
                    user_email='$safeEmail'
                    $passSql
                 WHERE user_id=$userId"
            )) {
                fh_auth_refresh_session($conn);
                $log['msg'] = true;
            } else {
                $log['msg'] = false;
                $log['error'] = 'Unable to save profile.';
            }
            break;
        case('UploadAdminAvatar'):
            if (!fh_auth_is_logged_in()) {
                $log['msg'] = false;
                $log['error'] = 'Access denied.';
                break;
            }
            $userId = (int)$_SESSION['user_id'];
            if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                $log['msg'] = false;
                $log['error'] = 'Please choose an image to upload.';
                break;
            }

            $uploadDir = __DIR__ . '/uploads/avatars/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $allowed = array('jpg', 'jpeg', 'png', 'gif', 'webp');
            if (!in_array($ext, $allowed, true)) {
                $log['msg'] = false;
                $log['error'] = 'Please choose a JPG, PNG, GIF, or WebP image.';
                break;
            }
            if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                $log['msg'] = false;
                $log['error'] = 'Image must be 2 MB or smaller.';
                break;
            }
            $check = @getimagesize($_FILES['avatar']['tmp_name']);
            if ($check === false) {
                $log['msg'] = false;
                $log['error'] = 'Invalid image file.';
                break;
            }

            $existing = $conn->query("SELECT user_avatar FROM user WHERE user_id=$userId LIMIT 1");
            $oldAvatar = ($existing && ($er = $existing->fetch_assoc()) && !empty($er['user_avatar'])) ? $er['user_avatar'] : '';
            if ($oldAvatar !== '' && strpos($oldAvatar, 'uploads/avatars/') === 0) {
                $oldPath = __DIR__ . '/' . $oldAvatar;
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $filename = 'admin_' . $userId . '_' . time() . '.' . $ext;
            $relativePath = 'uploads/avatars/' . $filename;
            $fullPath = $uploadDir . $filename;
            if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $fullPath)) {
                $log['msg'] = false;
                $log['error'] = 'Upload failed. Please try again.';
                break;
            }

            $safePath = $conn->real_escape_string($relativePath);
            if ($conn->query("UPDATE user SET user_avatar='$safePath' WHERE user_id=$userId")) {
                $_SESSION['user_avatar'] = $relativePath;
                $log['msg'] = true;
                $log['avatar_url'] = $relativePath;
            } else {
                @unlink($fullPath);
                $log['msg'] = false;
                $log['error'] = 'Could not save avatar.';
            }
            break;
        case('GeocodeMapAddress'):
            include_once(__DIR__ . '/../includes/dashboard_helper.php');
            if (!fh_auth_is_logged_in()) {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Access denied.';
                break;
            }
            $address = trim(isset($_POST['address']) ? $_POST['address'] : '');
            if ($address === '') {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Address is required.';
                break;
            }
            $coords = fh_dashboard_geocode_address($address);
            if ($coords) {
                $log['validation_status'] = 'T';
                $log['lat'] = $coords['lat'];
                $log['lng'] = $coords['lng'];
            } else {
                $log['validation_status'] = 'F';
                $log['msg'] = 'Address could not be located.';
            }
            break;
    }

    $conn->close();
    echo json_encode($log);