<?php
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
    include_once('config.php');
    $function = $_POST['function'];
    //global $conn;
    $log = array();

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
            break;
    }
    $conn->close();
    echo json_encode($log);
?>