<?php
    include('admin/config.php');
    include_once(__DIR__ . '/includes/business_helper.php');
    include_once(__DIR__ . '/includes/order_helper.php');
    include_once(__DIR__ . '/includes/geo_helper.php');
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    require 'phpmailer/Exception.php';
    require 'phpmailer/PHPMailer.php';
    require 'phpmailer/SMTP.php';

    $function = $_POST['function'];
    //global $conn;
    $log = array();
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
    switch($function) {
         case('getcountCart'):
            if(isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
                    $countcart =sizeof($_SESSION['cart']);
            }else{
                	$countcart = 0;
            }
            $elements['size_of_cart'] = $countcart;
            break;
    	 case('AddtoCart'):

            $p_id=$_POST['p_id'];
            $qty=$_POST['qty'];
            $price=$_POST['price'];
            $name=$_POST['name'];

            $elements=array("msg"=>"","size_of_cart"=>"0","validation_status"=>"T","menudate"=>isset($_SESSION['menudate']) ? $_SESSION['menudate'] : date('Y-m-d'));

            if(!is_numeric($p_id)){
                $elements['validation_status']="F";
                $elements['msg'].="Data Error";
            }
            if(!is_numeric($qty)){
                $elements['validation_status']="F";
                $elements['msg'].="Data Error";
            }

            if($elements['validation_status']=='T'){
                $itemBusinessId = fh_item_business_id($conn, $p_id);
                if ($itemBusinessId <= 0) {
                    $elements['validation_status'] = "F";
                    $elements['msg'] = "This item is not assigned to a restaurant yet.";
                } elseif (isset($_SESSION['cart_business_id']) && (int)$_SESSION['cart_business_id'] !== $itemBusinessId && !empty($_SESSION['cart'])) {
                    $biz = fh_resolve_business($conn, $_SESSION['cart_business_id']);
                    $bizName = $biz ? $biz['business_name'] : 'another restaurant';
                    $elements['validation_status'] = "F";
                    $elements['msg'] = "Your cart already has items from $bizName. Please checkout or remove items before ordering from a different restaurant.";
                }
            }

            if($elements['validation_status']=='T'){
                $preSettings = fh_preorder_settings($conn, $itemBusinessId);
                if (isset($_POST['menudate']) && trim($_POST['menudate']) !== '') {
                    $_SESSION['menudate'] = fh_clamp_menudate($_POST['menudate'], $preSettings['preorder_max_days']);
                } elseif (!isset($_SESSION['menudate'])) {
                    $_SESSION['menudate'] = date('Y-m-d');
                }
                $elements['menudate'] = $_SESSION['menudate'];

                // Qty 0 = remove from cart; skip availability check
                if ((float)$qty != 0 && !fh_item_orderable($conn, $p_id, $_SESSION['menudate'])) {
                    $elements['validation_status'] = "F";
                    $elements['msg'] = "This item is not available on the selected date. Choose a daily menu item or a pre-order dish.";
                }
            }

            if($elements['validation_status']=='T'){

                if(isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {

                }else{
                	$_SESSION['cart']=array();
                }

                foreach ($_SESSION['cart'] as $key => $val) {
                    if($p_id=== $_SESSION['cart'][$key]['p_id']){
                        $qty+=$_SESSION['cart'][$key]['qty'];
                        unset($_SESSION['cart'][$key]); // Remove the product
                    }
                }
                /// Add the  product with quantity more than 0 //
                if($qty !=0){
                    $b=array("p_id"=>"$p_id","qty"=>$qty,"price"=>$price,"name"=>$name,"business_id"=>$itemBusinessId);
                    array_push($_SESSION['cart'],$b); // Items added to cart
                    $_SESSION['cart_business_id'] = $itemBusinessId;
                    $elements['msg'].="Product Added:  $name , qty = $qty ";
                }else{
                    $elements['msg'].="Product Removed : $name   ";
                    fh_sync_cart_business_session();
                }
                ////
            }

            $elements['size_of_cart']=sizeof(isset($_SESSION['cart']) ? $_SESSION['cart'] : array());
            //echo json_encode($elements);



            /*$item_id = $_POST['item_id'];
            $item_pix = $_POST['item_pix'];

            $stmtheader = $conn->prepare("delete from items where id=$item_id");
            $stmtheader->execute();
            unlink($item_pix);
            $log['msg']=true;*/
            break;
         case('EditRecord'):
            $item_id = $_POST['item_id'];
            $adminsql = $conn->query("select * from items where id=".$item_id." limit 1");
            $row = $adminsql->fetch_assoc();
            $log['id'] = $row['id'];
            $log['name'] = $row['name'];
            $log['price'] = $row['price'];
            $log['picture'] = $row['picture'];
            break;
         case('LoginValidate'):
            $elements['validation_status']="F";
            $username = $_POST['email'];
            $password = md5($_POST['password']);
            if(empty($username)){
              $elements['validation_status']="F";
              $msgbox="Email should not be empty";
              $elements['msg']=$msgbox;
              break;
            }
            $usersql = $conn->query("select * from customers where customers_email='$username' limit 1");
            $usercount =$usersql->num_rows;
            if($usercount>0){
                    $row = $usersql->fetch_assoc();
                    if($row['customers_verify']==1){
                            if($row['customers_password']==$password){
                                    $_SESSION['customers_id']=$row['customers_id'];
                                    $_SESSION['customers_firstname']=$row['customers_firstname'];
                                    $_SESSION['customers_lastname']=$row['customers_lastname'];
                                    $_SESSION['customers_profile_pic']=!empty($row['customers_profile_pic']) ? $row['customers_profile_pic'] : '';
                                    $elements['validation_status']="T";
                                    $elements['customers_id']=$row['customers_id'];
                                    $elements['customers_firstname']=$row['customers_firstname'];
                                    $elements['customers_profile_pic']=$_SESSION['customers_profile_pic'];

                                    //header('Location:index.php');
                                    //exit;
                            }else{
                                    $msgbox="Password does not match";
                                     $elements['validation_status']="F";
                            }

                    }else{
                            $last_id=$row['customers_id'];
                            $password = $row['customers_password'];
                            $msgbox="Email account already exist but not verified..<a href='email_verify.php?customers_id=$last_id&valcode=$password'>Resend Email</a>";
                             $elements['validation_status']="F";
                    }
                    //exit;
            }else{
                    $msgbox="Email account does not exist..";
                     $elements['validation_status']="F";

            }
            $elements['msg']=$msgbox;
            break;
         case('getDepartment'):
            $company = $_POST['company'];
            $deplist = '<option value="0">N/A</option>';
             $companysql = $conn->query("select * from department where company_id=$company order by name asc");
             while ($row = $companysql->fetch_assoc()){
                $deplist.='<option value="'.$row['id'].'">'.$row['name'].'</option>';
             }
             $elements['msg']= $deplist;
             break;
         case('getProvinces'):
            $countryId = isset($_POST['country_id']) ? (int)$_POST['country_id'] : 0;
            $provinces = fh_geo_get_provinces($conn, $countryId);
            $elements['msg'] = fh_geo_options_html($provinces, 'province_id', 'province_name', '', 'Select province');
            $elements['count'] = count($provinces);
            break;
         case('getTowns'):
            $provinceId = isset($_POST['province_id']) ? (int)$_POST['province_id'] : 0;
            $towns = fh_geo_get_towns($conn, $provinceId);
            $elements['msg'] = fh_geo_options_html($towns, 'town_id', 'town_name', '', 'Select town / city');
            $elements['count'] = count($towns);
            break;
         case('getBarangays'):
            $townId = isset($_POST['town_id']) ? (int)$_POST['town_id'] : 0;
            $barangays = fh_geo_get_barangays($conn, $townId);
            $elements['msg'] = fh_geo_options_html($barangays, 'barangay_id', 'barangay_name', '', 'Select barangay');
            $elements['count'] = count($barangays);
            break;
         case('saveProfile'):
            $elements['validation_status'] = 'F';
            if (!isset($_SESSION['customers_id'])) {
                $elements['msg'] = 'You must be signed in.';
                break;
            }

            $customer_id = (int)$_POST['customer_id'];
            if ($customer_id !== (int)$_SESSION['customers_id']) {
                $elements['msg'] = 'Invalid account.';
                break;
            }

            $fname = trim($_POST['fname']);
            $lname = trim($_POST['lname']);
            $company = 0;
            $department = 0;
            $address = trim(isset($_POST['address']) ? $_POST['address'] : '');
            $country = trim(isset($_POST['country']) ? $_POST['country'] : '');
            $province = trim(isset($_POST['province']) ? $_POST['province'] : '');
            $town = trim(isset($_POST['town']) ? $_POST['town'] : '');
            $barangay = trim(isset($_POST['barangay']) ? $_POST['barangay'] : '');
            $zipcode = trim(isset($_POST['zipcode']) ? $_POST['zipcode'] : '');
            $contact = trim(isset($_POST['contact']) ? $_POST['contact'] : '');

            if ($fname === '') {
                $elements['msg'] = 'First name is required.';
                break;
            }
            if ($lname === '') {
                $elements['msg'] = 'Last name is required.';
                break;
            }
            if ($country === '') {
                $elements['msg'] = 'Country is required.';
                break;
            }
            if ($province === '') {
                $elements['msg'] = 'Province is required.';
                break;
            }
            if ($town === '') {
                $elements['msg'] = 'Town / City is required.';
                break;
            }
            if ($barangay === '') {
                $elements['msg'] = 'Barangay is required.';
                break;
            }

            $safeFname = $conn->real_escape_string($fname);
            $safeLname = $conn->real_escape_string($lname);
            $safeAddress = $conn->real_escape_string($address);
            $safeCountry = $conn->real_escape_string($country);
            $safeProvince = $conn->real_escape_string($province);
            $safeTown = $conn->real_escape_string($town);
            $safeBarangay = $conn->real_escape_string($barangay);
            $safeZipcode = $conn->real_escape_string($zipcode);
            $safeContact = $conn->real_escape_string($contact);

            $conn->query("UPDATE customers SET customers_firstname='$safeFname', customers_lastname='$safeLname', customers_company=$company, customers_department=$department, customers_complete_address='$safeAddress', customers_country='$safeCountry', customers_province='$safeProvince', customers_town='$safeTown', customers_barangay='$safeBarangay', customers_zipcode='$safeZipcode', customers_phone='$safeContact' WHERE customers_id=$customer_id");

            $_SESSION['customers_firstname'] = $fname;
            $_SESSION['customers_lastname'] = $lname;
            setcookie('customers_firstname', $fname, time() + (86400 * 30), '/');

            $elements['validation_status'] = 'T';
            $elements['msg'] = 'Profile updated successfully.';
            $elements['firstname'] = $fname;
            $elements['lastname'] = $lname;
            break;
         case('uploadAvatar'):
            $elements['validation_status'] = 'F';
            if (!isset($_SESSION['customers_id'])) {
                $elements['msg'] = 'You must be signed in.';
                break;
            }

            $customer_id = (int)$_SESSION['customers_id'];
            if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                $elements['msg'] = 'Please select an image to upload.';
                break;
            }

            $uploadDir = __DIR__ . '/uploads/avatars/';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                $elements['msg'] = 'Upload folder is not available.';
                break;
            }

            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $allowed = array('jpg', 'jpeg', 'png', 'gif', 'webp');
            if (!in_array($ext, $allowed, true)) {
                $elements['msg'] = 'Only JPG, PNG, GIF, or WebP images are allowed.';
                break;
            }

            if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                $elements['msg'] = 'Image must be 2 MB or smaller.';
                break;
            }

            $check = getimagesize($_FILES['avatar']['tmp_name']);
            if ($check === false) {
                $elements['msg'] = 'Invalid image file.';
                break;
            }

            $usersql = $conn->query("SELECT customers_profile_pic FROM customers WHERE customers_id=$customer_id LIMIT 1");
            $row = $usersql ? $usersql->fetch_assoc() : null;
            if ($row && !empty($row['customers_profile_pic']) && strpos($row['customers_profile_pic'], 'uploads/avatars/') === 0) {
                $oldPath = __DIR__ . '/' . $row['customers_profile_pic'];
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $filename = 'customer_' . $customer_id . '_' . time() . '.' . $ext;
            $relativePath = 'uploads/avatars/' . $filename;
            $fullPath = __DIR__ . '/' . $relativePath;

            if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $fullPath)) {
                $elements['msg'] = 'Could not save image. Please try again.';
                break;
            }

            $safePath = $conn->real_escape_string($relativePath);
            $conn->query("UPDATE customers SET customers_profile_pic='$safePath' WHERE customers_id=$customer_id");

            $_SESSION['customers_profile_pic'] = $relativePath;

            $elements['validation_status'] = 'T';
            $elements['msg'] = 'Profile photo updated.';
            $elements['avatar_url'] = $relativePath;
            break;
         case('confirmCheckout'):
            $elements['validation_status'] = 'F';
            if (!isset($_SESSION['cart']) || count($_SESSION['cart']) <= 0) {
                $elements['msg'] = 'Your cart is empty. Please add items before checking out.';
                break;
            }

            $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
            $customer_email = trim(isset($_POST['email']) ? $_POST['email'] : '');
            $fname = trim(isset($_POST['fname']) ? $_POST['fname'] : '');
            $lname = trim(isset($_POST['lname']) ? $_POST['lname'] : '');
            $company = isset($_POST['company']) ? (int)$_POST['company'] : 0;
            $department = isset($_POST['department']) ? (int)$_POST['department'] : 0;
            $address = trim(isset($_POST['address']) ? $_POST['address'] : '');
            $country = trim(isset($_POST['country']) ? $_POST['country'] : '');
            $province = trim(isset($_POST['province']) ? $_POST['province'] : '');
            $town = trim(isset($_POST['town']) ? $_POST['town'] : '');
            $barangay = trim(isset($_POST['barangay']) ? $_POST['barangay'] : '');
            $zipcode = trim(isset($_POST['zipcode']) ? $_POST['zipcode'] : '');
            $contact = trim(isset($_POST['contact']) ? $_POST['contact'] : '');
            $payment = trim(isset($_POST['payment']) ? $_POST['payment'] : '');
            $shipping = trim(isset($_POST['shipping']) ? $_POST['shipping'] : '');
            $remarks = trim(isset($_POST['remarks']) ? $_POST['remarks'] : '');
            $shippingDate = trim(isset($_POST['shipping_date']) ? $_POST['shipping_date'] : '');
            $shippingTime = trim(isset($_POST['shipping_time']) ? $_POST['shipping_time'] : '');
            $dineinTable = trim(isset($_POST['dinein_table']) ? $_POST['dinein_table'] : '');

            if ($fname === '') {
                $elements['msg'] = 'First name is required.';
                break;
            }
            if ($lname === '') {
                $elements['msg'] = 'Last name is required.';
                break;
            }
            if ($customer_email === '') {
                $elements['msg'] = 'Email is required.';
                break;
            }
            if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
                $elements['msg'] = 'Please enter a valid email address.';
                break;
            }
            if ($contact === '') {
                $elements['msg'] = 'Contact number is required.';
                break;
            }
            $contactDigits = preg_replace('/\D+/', '', $contact);
            if (strlen($contactDigits) < 10 || strlen($contactDigits) > 13) {
                $elements['msg'] = 'Please enter a valid contact number.';
                break;
            }
            if ($country === '') {
                $elements['msg'] = 'Country is required.';
                break;
            }
            if ($province === '') {
                $elements['msg'] = 'Province is required.';
                break;
            }
            if ($town === '') {
                $elements['msg'] = 'Town / City is required.';
                break;
            }
            if ($barangay === '') {
                $elements['msg'] = 'Barangay is required.';
                break;
            }
            if ($shipping === 'delivery' && $address === '') {
                $elements['msg'] = 'Delivery address is required for delivery orders.';
                break;
            }
            if ($payment === '') {
                $elements['msg'] = 'Please select a payment method.';
                break;
            }
            if ($shipping === '') {
                $elements['msg'] = 'Please select a shipping method.';
                break;
            }
            if ($shipping === 'dinein' && $dineinTable === '') {
                $elements['msg'] = 'Table number is required for dine-in orders.';
                break;
            }
            if ($shipping !== 'dinein') {
                $dineinTable = '';
            }

            $orderdate = isset($_SESSION['menudate']) ? $_SESSION['menudate'] : date('Y-m-d');
            $isPreorder = (strtotime($orderdate) > strtotime(date('Y-m-d')));
            $orders_business_id = isset($_SESSION['cart_business_id']) ? (int)$_SESSION['cart_business_id'] : 0;
            if ($orders_business_id <= 0 && !empty($_SESSION['cart'])) {
                $firstItem = reset($_SESSION['cart']);
                $orders_business_id = isset($firstItem['business_id']) ? (int)$firstItem['business_id'] : fh_item_business_id($conn, $firstItem['p_id']);
            }

            // Company / Department are no longer collected on checkout
            $company = 0;
            $department = 0;

            $safeShippingDate = 'NULL';
            $safeShippingTime = 'NULL';
            $shippingLabels = array('delivery' => 'Delivery', 'pickup' => 'Pick-up', 'dinein' => 'Dine-in');
            $methodLabel = isset($shippingLabels[$shipping]) ? $shippingLabels[$shipping] : 'Delivery';
            $preSettings = fh_preorder_settings($conn, $orders_business_id);
            if ($shippingDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $shippingDate)) {
                $elements['msg'] = $methodLabel . ' date is required.';
                break;
            }
            $shippingDate = fh_clamp_menudate($shippingDate, $preSettings['preorder_max_days']);
            if ($shippingTime === '' || !preg_match('/^\d{2}:\d{2}$/', $shippingTime)) {
                $elements['msg'] = $methodLabel . ' time is required.';
                break;
            }
            $orderdate = $shippingDate;
            $_SESSION['menudate'] = $shippingDate;
            $safeShippingDate = "'" . $conn->real_escape_string($shippingDate) . "'";
            $safeShippingTime = "'" . $conn->real_escape_string($shippingTime) . "'";
            $timeDisplay = date('g:i A', strtotime($shippingTime));
            $dateDisplay = date('M j, Y', strtotime($shippingDate));
            $schedulePrefix = $isPreorder ? 'Pre-order ' : '';
            $scheduleNote = '[' . $schedulePrefix . $methodLabel . ': ' . $dateDisplay . ' at ' . $timeDisplay;
            if ($shipping === 'dinein' && $dineinTable !== '') {
                $scheduleNote .= ' — Table ' . $dineinTable;
            }
            $scheduleNote .= ']';
            $remarks = $remarks === '' ? $scheduleNote : $scheduleNote . "\n" . $remarks;

            $safeEmail = $conn->real_escape_string($customer_email);
            $safeFname = $conn->real_escape_string($fname);
            $safeLname = $conn->real_escape_string($lname);
            $safeAddress = $conn->real_escape_string($address);
            $safeCountry = $conn->real_escape_string($country);
            $safeProvince = $conn->real_escape_string($province);
            $safeTown = $conn->real_escape_string($town);
            $safeBarangay = $conn->real_escape_string($barangay);
            $safeZipcode = $conn->real_escape_string($zipcode);
            $safeContact = $conn->real_escape_string($contact);
            $safePayment = $conn->real_escape_string($payment);
            $safeShipping = $conn->real_escape_string($shipping);
            $safeDineinTable = $conn->real_escape_string($dineinTable);
            $safeRemarks = $conn->real_escape_string($remarks);

            $transdate = date('Y-m-d H:i:s');
            $total_amount =  $_SESSION['total_amount'];
            $receiptNo = fh_order_allocate_receipt_no($conn, $orders_business_id);
            $safeReceiptNo = $conn->real_escape_string($receiptNo);
            $sql = "INSERT INTO orders (orders_receipt_no, orders_menudate,orders_transdate, orders_customers_id, orders_customers_email, orders_customers_fname, orders_customers_lname, orders_customers_company, orders_customers_department, orders_delivery_address, orders_country, orders_province, orders_town, orders_barangay, orders_zipcode, orders_customers_contact, orders_payment_type, orders_shipping_type, orders_shipping_date, orders_shipping_time, orders_dinein_table, orders_total_amount, orders_remarks, orders_business_id) VALUES ('$safeReceiptNo','$orderdate','$transdate','$customer_id','$safeEmail','$safeFname','$safeLname','$company','$department','$safeAddress','$safeCountry','$safeProvince','$safeTown','$safeBarangay','$safeZipcode','$safeContact','$safePayment','$safeShipping',$safeShippingDate,$safeShippingTime,'$safeDineinTable','$total_amount','$safeRemarks','$orders_business_id')";

            if ($conn->query($sql) === TRUE) {
              $last_id = $conn->insert_id;
              foreach ($_SESSION['cart'] as $key => $val) {
                    $menuid = $_SESSION['cart'][$key]['p_id'];
                    $menuqty = $_SESSION['cart'][$key]['qty'];
                    $stmtheader = $conn->prepare("INSERT INTO orders_details (orders_details_orders_id,orders_details_menu_id,orders_details_qty) VALUES ('$last_id','$menuid','$menuqty')");
                    $stmtheader->execute();

              }

              $itemsql = $conn->query("SELECT * FROM orders_details INNER JOIN orders ON orders_details.orders_details_orders_id=orders.orders_id INNER JOIN items ON orders_details.orders_details_menu_id=items.id where orders_id=$last_id order by orders.orders_id desc,orders.orders_menudate asc");
              $orderdetailbody="";
              $smsbody ="";
              while ($row = $itemsql->fetch_assoc()){
                    $orders_id= $row['orders_details_orders_id'];
                    $transdate = date('m/d/Y h:iA',strtotime($row['orders_transdate']));
                    $menudate = date('m/d/Y',strtotime($row['orders_menudate']));
                    $fullname = $row['orders_customers_lname'].', '.$row['orders_customers_fname'];
                    $address = $row['orders_delivery_address'];
                    $company = $row['orders_customers_company'];
                    $department = $row['orders_customers_department'];
                    $payment = $row['orders_payment_type'];
                    $contact = $row['orders_customers_contact'];
                    $delivery = $row['orders_shipping_type'];
                    $grandtotal = $row['orders_total_amount'];
                    $status = $row['orders_status'];
                    $remarks = $row['orders_remarks'];
                    $foodname = $row['name'];
                    $qty = $row['orders_details_qty'];
                    $unitprice = number_format($row['price'],2);
                    $total = number_format($row['orders_details_qty'] * $row['price'],2);
                    $grandtotal = $row['orders_total_amount'];
                    $orderdetailbody.="<tr><td align=center>$last_id</td><td>$transdate</td><td>$menudate</td><td>$foodname</td><td align=center>$qty</td><td align=right>$unitprice</td><td align=right>$total</td></tr>";
                    $smsbody.="$qty-$foodname, ";

              }

/*
                    $email = new PHPMailer();
                    $email->IsHTML(true);
                    //$email->SMTPDebug = 3;

                    //Set PHPMailer to use SMTP.
                    $email->isSMTP();
                    //Set SMTP host name
                    $email->Host = "mail.supremecluster.com";
                    //Set this to true if SMTP host requires authentication to send email
                    $email->SMTPAuth = true;
                    //Provide username and password
                    $email->Username = "info@foodhauz.bohollander.com";
                    $email->Password = "***";
                    //If SMTP requires TLS encryption then set it
                    $email->SMTPSecure = "ssl";
                    //Set TCP port to connect to
                    $email->Port = 465;

                    $email->setFrom("info@foodhauz.bohollander.com","Eulie's Food Hauz");
                    $email->addReplyTo('info@foodhauz.bohollander.com', "Eulie's Food Hauz");
                    //$email->SetFrom('webmaster@bohollander.com','Webmaster'); //Name is optional
                    $email->Subject   = '[ORDER No.  '.$orders_id.'] - Eulies Food Hauz';


                     $companysql = $conn->query("SELECT * FROM company where id=$company limit 1");
                     $row1 = $companysql->fetch_assoc();
                     $company= $row1['name'];

                    $deptsql = $conn->query("SELECT * FROM department where id=$department limit 1");
                     $row2 = $deptsql->fetch_assoc();
                     $department= $row2['name'];

                    $email->Body = "<html>
                    <head>
                    <title>[Order No. $orders_id] - Eulies Food Hauz</title>
                    </head>
                    <body>
                    <p>Hi $fullname,</p>
                    <br/>
                    <p>Thank you! Below is the details of your order:</p>
                    <table>
                    <tr><th>Customer:</th><td>$fullname</td></tr>
                    <tr><th>Address:</th><td>$address</td></tr>
                    <tr><th>Company:</th><td>$company</td></tr>
                    <tr><th>Department:</th><td>$department</td></tr>
                    <tr><th>Contact:</th><td>$contact</td></tr>
                    <tr><th>Remarks:</th><td>$remarks</td></tr>
                    </table>
                    <table width=100% border=1 cellspacing=0 cellpadding=1>
                    <tr><th align=center>Order No.</th><th>Trans Date</th><th>Menu Date</th><th>Description</th><th align=center>Qty</th><th align=right>Unit Price</th><th align=right>Total</th></td>
                    $orderdetailbody
                    </table>
                    
                    <h2>Total Payable:$grandtotal</h2>
                    <br/>
                    <br/>
                    Thank you,
                    <br/>
                    <br/>
                    Store Manager
                    </body>
                    </html>";

                    $email->addAddress($customer_email);
                    $email->addAddress("orders@foodhauz.bohollander.com");



                    if(!$email->send())
                    {
                        $elements['validation_status']="F";
                        $elements['msg']="Mailer Error: " . $email->ErrorInfo;
                    }
                    else
                    {
                        //echo "New record created successfully. Last inserted ID is: " . $last_id;
                        $elements['validation_status']="T";
                        $elements['msg']=$last_id;
                        $elements['fullname']=$fullname;
                        $elements['menudate']=$menudate;
                        $elements['smsbody']=$smsbody;
                        $elements['remarks']=$remarks;
                        $elements['grandtotal']=number_format($grandtotal,2);
                        unset($_SESSION['cart']);
                        unset($_SESSION['total_amount']);
                    }

*/

//echo "New record created successfully. Last inserted ID is: " . $last_id;
$elements['validation_status']="T";
$elements['msg']=$last_id;
$elements['receipt_no']=$receiptNo;
$elements['fullname']=$fullname;
$elements['menudate']=$menudate;
$elements['smsbody']=$smsbody;
$elements['remarks']=$remarks;
$elements['grandtotal']=number_format($grandtotal,2);
unset($_SESSION['cart']);
unset($_SESSION['total_amount']);
unset($_SESSION['cart_business_id']);

              //header('Location:thankyou.php?orders_id='.$last_id);
            } else {
                $elements['validation_status']="F";
                $elements['msg']= 'Unable to place your order. Please try again.';
            }
            break;
         case('SignUp'):
            $elements['validation_status']="F";
            $username = $_POST['email'];
            $password = md5($_POST['psw']);
            $usersql = $conn->query("select * from customers where customers_email='$username' limit 1");
            $usercount =$usersql->num_rows;
            if($usercount>0){
                $row = $usersql->fetch_assoc();
                if($row['customers_verify']==1){
                     $elements['msg']= "Email Account already exist..";
                }else{
                    $last_id=$row['customers_id'];
                    $password = $row['customers_password'];
                    $elements['msg']= "Email account already exist but not verified..<a href='email_verify.php?customers_id=$last_id&valcode=$password'>Resend Email</a>";
                }
            }else{

                $sql = "INSERT INTO customers (customers_email,customers_password) VALUES ('$username','$password')";

                if ($conn->query($sql) === TRUE) {
                  $last_id = $conn->insert_id;
                  //echo "New record created successfully. Last inserted ID is: " . $last_id;
                  $elements['validation_status']="T";
                  $elements['msg']='email_verify.php?customers_id='.$last_id.'&valcode='.$password;
                } else {
                  $elements['validation_status']="F";
                  $elements['msg']= "Error: " . $sql . "<br>" . $conn->error;
                }
            }

            break;
         case('ForgotPassword'):
            $elements['validation_status'] = 'F';
            $email1 = isset($_POST['email']) ? trim($_POST['email']) : '';

            if ($email1 === '') {
                $elements['msg'] = 'Email is required.';
                break;
            }

            $safeEmail = $conn->real_escape_string($email1);
            $usersql = $conn->query("SELECT * FROM customers WHERE customers_email='$safeEmail' LIMIT 1");
            if (!$usersql || $usersql->num_rows === 0) {
                $elements['msg'] = 'No account was found with that email address.';
                break;
            }

            $row = $usersql->fetch_assoc();
            if ((int)$row['customers_verify'] === 0) {
                $last_id = (int)$row['customers_id'];
                $valcode = $row['customers_password'];
                $elements['msg'] = "This email is registered but not verified yet. <a href='email_verify.php?customers_id=$last_id&valcode=$valcode'>Resend verification email</a>";
                break;
            }

            $last_id = (int)$row['customers_id'];
            $temppassword = (string)mt_rand(10000000, 99999999);

            $email = new PHPMailer();
            $email->IsHTML(true);
            $email->isSMTP();
            $email->Host = defined('PB_SMTP_HOST') ? PB_SMTP_HOST : (getenv('PB_SMTP_HOST') ?: 'mail.supremecluster.com');
            $email->SMTPAuth = true;
            $email->Username = defined('PB_SMTP_USER') ? PB_SMTP_USER : (getenv('PB_SMTP_USER') ?: 'info@foodhauz.bohollander.com');
            $email->Password = defined('PB_SMTP_PASS') ? PB_SMTP_PASS : (getenv('PB_SMTP_PASS') ?: '');
            $email->SMTPSecure = "ssl";
            $email->Port = 465;
            if ($email->Password === '') {
                $elements['msg'] = 'Email is not configured. Set PB_SMTP_PASS in admin/payment_config.local.php.';
                break;
            }
            $email->setFrom($email->Username, "Pickleball Portal");
            $email->addReplyTo($email->Username, 'Pickleball Portal');
            $email->Subject = 'Pickleball Portal — Reset Password';
            $email->Body = "<html>
            <head><title>Pickleball Portal — Reset password</title></head>
            <body>
            <p>Hello,</p>
            <p>We received a request to reset your Pickleball Portal password. Use the temporary password below to sign in:</p>
            <h1>$temppassword</h1>
            <p>After signing in, we recommend updating your password from your account settings.</p>
            <p>If you did not request this, you can ignore this email.</p>
            <p>Thank you,<br>Pickleball Portal</p>
            </body>
            </html>";
            $email->addAddress($row['customers_email']);

            if (!$email->send()) {
                $elements['msg'] = 'We could not send the reset email right now. Please try again in a few minutes.';
                break;
            }

            $hashedPassword = md5($temppassword);
            $conn->query("UPDATE customers SET customers_password='$hashedPassword' WHERE customers_id=$last_id");

            $elements['validation_status'] = 'T';
            $elements['msg'] = 'A temporary password has been sent to your email.';
            break;
         case('ChangePassword'):
            $elements['validation_status'] = 'F';
            if (!isset($_SESSION['customers_id'])) {
                $elements['msg'] = 'You must be signed in.';
                break;
            }

            $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
            $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
            $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
            $customers_id = (int)$_SESSION['customers_id'];

            if ($currentPassword === '') {
                $elements['msg'] = 'Current password is required.';
                break;
            }
            if ($newPassword === '') {
                $elements['msg'] = 'New password is required.';
                break;
            }
            if (strlen($newPassword) < 8) {
                $elements['msg'] = 'New password must be at least 8 characters.';
                break;
            }
            if ($newPassword !== $confirmPassword) {
                $elements['msg'] = 'New passwords do not match.';
                break;
            }
            if ($currentPassword === $newPassword) {
                $elements['msg'] = 'New password must be different from your current password.';
                break;
            }

            $usersql = $conn->query("SELECT * FROM customers WHERE customers_id=$customers_id LIMIT 1");
            if (!$usersql || $usersql->num_rows === 0) {
                $elements['msg'] = 'Account not found.';
                break;
            }

            $row = $usersql->fetch_assoc();
            if ((int)$row['customers_verify'] === 0) {
                $elements['msg'] = 'Your email is not verified yet.';
                break;
            }
            if ($row['customers_password'] !== md5($currentPassword)) {
                $elements['msg'] = 'Current password is incorrect.';
                break;
            }

            $hashedPassword = md5($newPassword);
            $conn->query("UPDATE customers SET customers_password='$hashedPassword' WHERE customers_id=$customers_id");

            $elements['validation_status'] = 'T';
            $elements['msg'] = 'Your password has been updated successfully.';
            break;
         case('getCustomerOrderDetail'):
            $elements['validation_status'] = 'F';
            if (!isset($_SESSION['customers_id'])) {
                $elements['msg'] = 'You must be signed in.';
                break;
            }
            $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
            $order = fh_order_fetch_for_customer($conn, $orderId, (int)$_SESSION['customers_id']);
            if (!$order) {
                $elements['msg'] = 'Order not found.';
                break;
            }
            $elements['validation_status'] = 'T';
            $elements['data'] = fh_order_format_detail_payload($conn, $order);
            break;
         case('updateCustomerOrderAddress'):
            $elements['validation_status'] = 'F';
            if (!isset($_SESSION['customers_id'])) {
                $elements['msg'] = 'You must be signed in.';
                break;
            }
            $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
            $order = fh_order_fetch_for_customer($conn, $orderId, (int)$_SESSION['customers_id']);
            if (!$order) {
                $elements['msg'] = 'Order not found.';
                break;
            }
            $result = fh_order_update_address($conn, $orderId, isset($_POST['address']) ? $_POST['address'] : '', true);
            if (!$result['ok']) {
                $elements['msg'] = $result['msg'];
                break;
            }
            $elements['validation_status'] = 'T';
            $elements['msg'] = $result['msg'];
            $elements['data'] = fh_order_format_detail_payload($conn, fh_order_fetch($conn, $orderId));
            break;
         case('deleteCustomerOrderItem'):
            $elements['validation_status'] = 'F';
            if (!isset($_SESSION['customers_id'])) {
                $elements['msg'] = 'You must be signed in.';
                break;
            }
            $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
            $detailId = isset($_POST['detail_id']) ? (int)$_POST['detail_id'] : 0;
            $order = fh_order_fetch_for_customer($conn, $orderId, (int)$_SESSION['customers_id']);
            if (!$order) {
                $elements['msg'] = 'Order not found.';
                break;
            }
            $result = fh_order_delete_item($conn, $orderId, $detailId, false);
            if (!$result['ok']) {
                $elements['msg'] = $result['msg'];
                break;
            }
            $elements['validation_status'] = 'T';
            $elements['msg'] = $result['msg'];
            $elements['total'] = $result['total'];
            $elements['item_count'] = $result['item_count'];
            $elements['data'] = fh_order_format_detail_payload($conn, fh_order_fetch($conn, $orderId));
            break;
         case('cancelCustomerOrder'):
            $elements['validation_status'] = 'F';
            if (!isset($_SESSION['customers_id'])) {
                $elements['msg'] = 'You must be signed in.';
                break;
            }
            $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
            $reason = isset($_POST['reason']) ? $_POST['reason'] : '';
            $order = fh_order_fetch_for_customer($conn, $orderId, (int)$_SESSION['customers_id']);
            if (!$order) {
                $elements['msg'] = 'Order not found.';
                break;
            }
            $result = fh_order_cancel($conn, $orderId, $reason);
            if (!$result['ok']) {
                $elements['msg'] = $result['msg'];
                break;
            }
            $elements['validation_status'] = 'T';
            $elements['msg'] = $result['msg'];
            break;
         case('PartnerSignup'):
            include_once(__DIR__ . '/includes/portal_subscription_helper.php');
            include_once(__DIR__ . '/includes/email_helper.php');
            $elements = array('validation_status' => 'F', 'msg' => '');

            $business_name = trim($_POST['business_name']);
            $subdomain = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($_POST['subdomain'])));
            $address = trim($_POST['address']);
            $barangay = trim(isset($_POST['barangay']) ? $_POST['barangay'] : '');
            $city = trim($_POST['city']);
            $province = trim($_POST['province']);
            $country = trim($_POST['country']);
            $zipcode = trim($_POST['zipcode']);
            $email = trim($_POST['email']);
            $contactperson = trim($_POST['contactperson']);
            $contactnumber = trim($_POST['contactnumber']);
            $description = trim($_POST['description']);
            $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
            $password2 = isset($_POST['password_confirm']) ? (string)$_POST['password_confirm'] : '';
            $signupIntent = (isset($_POST['signup_intent']) && $_POST['signup_intent'] === 'subscribe') ? 'subscribe' : 'trial';
            $billingCycle = (isset($_POST['billing_cycle']) && $_POST['billing_cycle'] === 'yearly') ? 'yearly' : 'monthly';

            $settings = fh_portal_sub_get_settings($conn);
            if ($signupIntent === 'trial' && empty($settings['trial_enabled'])) {
                $elements['msg'] = 'Free trial is not available right now. Please choose Subscribe.';
                break;
            }

            if ($business_name === '' || $subdomain === '' || $address === '' || $city === '' ||
                $province === '' || $country === '' || $zipcode === '' || $barangay === '' ||
                $email === '' || $contactperson === '' || $contactnumber === '') {
                $elements['msg'] = 'Please fill in all required fields.';
                break;
            }
            if (strlen($subdomain) < 3) {
                $elements['msg'] = 'Short name must be at least 3 characters.';
                break;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $elements['msg'] = 'Please enter a valid email address.';
                break;
            }
            if (strlen($password) < 6) {
                $elements['msg'] = 'Password must be at least 6 characters.';
                break;
            }
            if ($password !== $password2) {
                $elements['msg'] = 'Password confirmation does not match.';
                break;
            }
            if (!fh_portal_sub_table_exists($conn)) {
                $elements['msg'] = 'Subscription signup is not available yet. Please contact support.';
                break;
            }

            $safeName = $conn->real_escape_string($business_name);
            $safeEmail = $conn->real_escape_string($email);
            $safeSub = $conn->real_escape_string($subdomain);
            $safeUserName = $safeEmail;

            $dup = $conn->query(
                "SELECT business_id FROM business_account
                 WHERE business_subdomain='$safeSub'
                    OR business_email='$safeEmail'
                    OR business_name='$safeName'
                 LIMIT 1"
            );
            if ($dup && $dup->num_rows > 0) {
                $elements['msg'] = 'A business with this name, email, or short name already exists. Please contact us if you need help.';
                break;
            }

            $dupUser = $conn->query(
                "SELECT user_id FROM user
                 WHERE user_name='$safeUserName' OR user_email='$safeEmail'
                 LIMIT 1"
            );
            if ($dupUser && $dupUser->num_rows > 0) {
                $elements['msg'] = 'An admin account with this email already exists. Please sign in or use a different email.';
                break;
            }

            $fullAddress = $address;
            if ($barangay !== '') {
                $fullAddress = rtrim($address, ', ') . ', Brgy. ' . $barangay;
            }
            $safeAddress = $conn->real_escape_string($fullAddress);
            if ($description !== '') {
                $safeAddress = $conn->real_escape_string($fullAddress . "\n\nAbout: " . $description);
            }
            $safeCity = $conn->real_escape_string($city);
            $safeProvince = $conn->real_escape_string($province);
            $safeCountry = $conn->real_escape_string($country);
            $safeZip = $conn->real_escape_string($zipcode);
            $safeContact = $conn->real_escape_string($contactnumber);
            $safePerson = $conn->real_escape_string($contactperson);
            $signupDate = date('Y-m-d');
            $status = 0;
            $passHash = md5($password);
            $safeFullName = $conn->real_escape_string($contactperson);
            $role = FH_ROLE_BUSINESS_ADMIN;

            $conn->begin_transaction();
            try {
                $sql = "INSERT INTO business_account (
                    business_name, business_address, business_city, business_province,
                    business_country, business_zipcode, business_email, business_contact,
                    business_cont_person, business_signup_date, business_status, business_subdomain
                ) VALUES (
                    '$safeName','$safeAddress','$safeCity','$safeProvince','$safeCountry','$safeZip',
                    '$safeEmail','$safeContact','$safePerson','$signupDate','$status','$safeSub'
                )";
                if ($conn->query($sql) !== TRUE) {
                    throw new Exception('Unable to create business account.');
                }
                $newBizId = (int)$conn->insert_id;
                fh_ensure_business_page($conn, $newBizId);

                $userSql = "INSERT INTO user
                    (user_name, user_password, user_fullname, user_role, user_email, user_status, user_business_account)
                    VALUES ('$safeUserName','$passHash','$safeFullName','$role','$safeEmail',0,$newBizId)";
                if ($conn->query($userSql) !== TRUE) {
                    throw new Exception('Unable to create admin user.');
                }
                $newUserId = (int)$conn->insert_id;

                $subCreate = fh_portal_sub_create_pending($conn, array(
                    'business_id' => $newBizId,
                    'owner_user_id' => $newUserId,
                    'signup_intent' => $signupIntent,
                    'billing_cycle' => $billingCycle,
                    'months' => $billingCycle === 'yearly' ? 12 : 1,
                ));
                if (!$subCreate['ok']) {
                    throw new Exception($subCreate['msg']);
                }

                $mailOk = fh_portal_sub_send_activation_email($email, $business_name, $subCreate['token'], $signupIntent);
                $conn->commit();

                $elements['validation_status'] = 'T';
                if ($mailOk) {
                    $elements['msg'] = 'Account created. Please check your email (' . htmlspecialchars($email) . ') and click the activation link to continue.';
                } else {
                    $activateUrl = fh_portal_sub_activation_url($subCreate['token']);
                    $elements['msg'] = 'Account created, but we could not send email. Use this activation link: ' . $activateUrl;
                }
            } catch (Exception $e) {
                $conn->rollback();
                $elements['msg'] = $e->getMessage() !== '' ? $e->getMessage() : 'Unable to submit application. Please try again or contact us directly.';
            }
            break;

        case('GetCourtSlots'):
            include_once(__DIR__ . '/includes/court_helper.php');
            $courtId = isset($_POST['court_id']) ? (int)$_POST['court_id'] : 0;
            $date = isset($_POST['date']) ? trim($_POST['date']) : date('Y-m-d');
            $court = pb_court_fetch($conn, $courtId);
            if (!$court || (int)$court['court_status'] !== 1) {
                $elements['validation_status'] = 'F';
                $elements['msg'] = 'Court not available.';
                $elements['slots'] = array();
                break;
            }
            $settings = pb_booking_settings($conn, (int)$court['business_id']);
            $date = pb_clamp_booking_date($date, $settings['booking_max_days']);
            $elements['validation_status'] = 'T';
            $elements['date'] = $date;
            $elements['slots'] = pb_generate_slots($conn, $court, $date);
            break;

        case('CreateBooking'):
            include_once(__DIR__ . '/includes/booking_helper.php');
            if (!isset($_SESSION['customers_id']) || (int)$_SESSION['customers_id'] <= 0) {
                $elements['validation_status'] = 'F';
                $elements['msg'] = 'Please sign in to book.';
                break;
            }
            $result = pb_booking_create($conn, array(
                'court_id' => isset($_POST['court_id']) ? (int)$_POST['court_id'] : 0,
                'customers_id' => (int)$_SESSION['customers_id'],
                'start_datetime' => isset($_POST['start_datetime']) ? $_POST['start_datetime'] : '',
                'end_datetime' => isset($_POST['end_datetime']) ? $_POST['end_datetime'] : '',
                'customer_fname' => isset($_POST['customer_fname']) ? $_POST['customer_fname'] : '',
                'customer_lname' => isset($_POST['customer_lname']) ? $_POST['customer_lname'] : '',
                'customer_email' => isset($_POST['customer_email']) ? $_POST['customer_email'] : '',
                'customer_contact' => isset($_POST['customer_contact']) ? $_POST['customer_contact'] : '',
                'booking_remarks' => isset($_POST['booking_remarks']) ? $_POST['booking_remarks'] : '',
                'payment_type' => 'pay_at_venue',
            ));
            $elements['validation_status'] = !empty($result['ok']) ? 'T' : 'F';
            $elements['msg'] = $result['msg'];
            if (!empty($result['ok'])) {
                $elements['booking_id'] = $result['booking_id'];
                $elements['receipt_no'] = $result['receipt_no'];
                $elements['status'] = $result['status'];
            }
            break;

        case('CancelBooking'):
            include_once(__DIR__ . '/includes/booking_helper.php');
            if (!isset($_SESSION['customers_id']) || (int)$_SESSION['customers_id'] <= 0) {
                $elements['validation_status'] = 'F';
                $elements['msg'] = 'Please sign in.';
                break;
            }
            $result = pb_booking_cancel(
                $conn,
                isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0,
                (int)$_SESSION['customers_id'],
                isset($_POST['reason']) ? $_POST['reason'] : 'Cancelled by player'
            );
            $elements['validation_status'] = !empty($result['ok']) ? 'T' : 'F';
            $elements['msg'] = $result['msg'];
            break;
        case('ContactUs'):
            include_once(__DIR__ . '/includes/email_helper.php');
            $elements = array('validation_status' => 'F', 'msg' => '');
            $fname = trim(isset($_POST['fname']) ? $_POST['fname'] : '');
            $lname = trim(isset($_POST['lname']) ? $_POST['lname'] : '');
            $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
            $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
            $topic = trim(isset($_POST['topic']) ? $_POST['topic'] : '');
            $subject = trim(isset($_POST['subject']) ? $_POST['subject'] : '');
            $message = trim(isset($_POST['message']) ? $_POST['message'] : '');

            $topicLabels = array(
                'booking' => 'Court booking help',
                'venue' => 'Venue / partner support',
                'payment' => 'Payments & memberships',
                'feedback' => 'Feedback',
                'other' => 'Other',
            );

            if ($fname === '' || $lname === '' || $email === '' || $topic === '' || $subject === '' || $message === '') {
                $elements['msg'] = 'Please fill in all required fields.';
                break;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $elements['msg'] = 'Please enter a valid email address.';
                break;
            }
            if (!isset($topicLabels[$topic])) {
                $elements['msg'] = 'Please select a valid topic.';
                break;
            }
            if (strlen($message) > 4000) {
                $elements['msg'] = 'Message is too long. Please keep it under 4000 characters.';
                break;
            }

            $topicLabel = $topicLabels[$topic];
            $mailSubject = 'Pickleball Portal Contact — ' . $topicLabel . ': ' . $subject;
            $html = '<html><body style="font-family:Arial,sans-serif;color:#10251c;">'
                . '<h2 style="color:#0f3d2e;">New contact message</h2>'
                . '<p><strong>Name:</strong> ' . htmlspecialchars($fname . ' ' . $lname) . '</p>'
                . '<p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>'
                . '<p><strong>Phone:</strong> ' . htmlspecialchars($phone !== '' ? $phone : '—') . '</p>'
                . '<p><strong>Topic:</strong> ' . htmlspecialchars($topicLabel) . '</p>'
                . '<p><strong>Subject:</strong> ' . htmlspecialchars($subject) . '</p>'
                . '<p><strong>Message:</strong></p>'
                . '<p style="white-space:pre-wrap;">' . nl2br(htmlspecialchars($message)) . '</p>'
                . '</body></html>';

            $sent = pb_send_mail('info@foodhauz.bohollander.com', $mailSubject, $html);
            $elements['validation_status'] = 'T';
            $elements['msg'] = $sent
                ? 'Thanks, ' . htmlspecialchars($fname) . '! We received your message and will reply to ' . htmlspecialchars($email) . ' soon.'
                : 'Thanks, ' . htmlspecialchars($fname) . '! Your message was recorded. If you need a faster reply, email info@foodhauz.bohollander.com.';
            break;
        case('NewsletterSubscribe'):
            include_once(__DIR__ . '/includes/email_helper.php');
            $elements = array('validation_status' => 'F', 'msg' => '');
            $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $elements['msg'] = 'Please enter a valid email address.';
                break;
            }
            $safeEmail = $conn->real_escape_string($email);
            $tableCheck = $conn->query("SHOW TABLES LIKE 'newsletter_subscribers'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $exists = $conn->query("SELECT subscriber_id FROM newsletter_subscribers WHERE subscriber_email='$safeEmail' LIMIT 1");
                if ($exists && $exists->num_rows > 0) {
                    $elements['validation_status'] = 'T';
                    $elements['msg'] = 'You are already subscribed. Thanks for staying with us!';
                    break;
                }
                $conn->query("INSERT INTO newsletter_subscribers (subscriber_email, subscribed_at, subscriber_status) VALUES ('$safeEmail', NOW(), 1)");
            }
            $html = '<html><body style="font-family:Arial,sans-serif;color:#10251c;">'
                . '<h2 style="color:#0f3d2e;">New newsletter subscriber</h2>'
                . '<p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>'
                . '</body></html>';
            pb_send_mail('info@foodhauz.bohollander.com', 'Pickleball Portal — Newsletter signup', $html);
            $elements['validation_status'] = 'T';
            $elements['msg'] = 'Thanks for subscribing! Watch your inbox for court tips and venue updates.';
            break;
    }
    $conn->close();
    echo json_encode($elements);
?>