<?php
    include_once('admin/config.php');
    $function = $_POST['function'];
    //global $conn;
    $log = array();

    switch($function) {
         case('getcountCart'):
            if(isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
                	//echo " session  is available ";
                    $countcart =sizeof($_SESSION['cart']);

                }else{
                	//echo " session is not available ";
                	$countcart = 0;
            }
            $elements['size_of_cart'] = $countcart;
            break;
    	 case('AddtoCart'):



            $p_id=$_POST['p_id'];
            $qty=$_POST['qty'];


            $elements=array("msg"=>"","size_of_cart"=>"0","validation_status"=>"T","menudate"=>$_SESSION['menudate']);

            if(!is_numeric($p_id)){
                $elements['validation_status']="F";
                $elements['msg'].="Data Error";
            }
            if(!is_numeric($qty)){
                $elements['validation_status']="F";
                $elements['msg'].="Data Error";
            }

            if($elements['validation_status']=='T'){

                if(isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
                	//echo " session  is available ";

                }else{
                	//echo " session is not available ";
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
                    $b=array("p_id"=>"$p_id","qty"=>$qty);
                    array_push($_SESSION['cart'],$b); // Items added to cart
                    $elements['msg'].="Product Added : p_id: $p_id , qty = $qty ";
                }else{
                    $elements['msg'].="Product Removed : p_id: $p_id   ";
                }
                ////
            }

            $elements['size_of_cart']=sizeof($_SESSION['cart']);
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
    }
    $conn->close();
    echo json_encode($elements);
?>