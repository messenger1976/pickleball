<?php
include('admin/config.php');
include('header.php');
if(isset($_POST['btnDate'])){
    $_SESSION['menudate'] = $_POST['menudate'];
}else{
    if(!isset($_SESSION['menudate'])) $_SESSION['menudate'] = date('Y-m-d');
}
$menudate = $_SESSION['menudate'];
?>


<div class="main">
    <form id="menudate" action="?" method="POST"><label>Menu Date:</label><input type="date" id="menudate" name="menudate" value="<?php echo $menudate; ?>"><input type="submit" name="btnDate" value="Submit"></form>
<?php
    //echo session_id();
?>
    <h2>MENU for <?php echo date('l F d, Y',strtotime($menudate));?></h2>
    <p>Click the Add to Cart button to select your order. Once done, click the checkout button below to finalize your order.</p>
    <br>


<?php
 $itemsql = $conn->query("select * from items LEFT JOIN menu_listing ON (items.id=menu_listing.menu_item) where menu_listing.menu_date='$menudate' order by items.name asc");
    $itemcount =$itemsql->num_rows;
    if($itemcount>0){
        $counterrow = 1;
        while ($row = $itemsql->fetch_assoc()){
            if($counterrow==1){
                echo '<div class="row">';
            }
            echo '<div class="column">
                <div class="card">
                    <img src="admin/'.$row['picture'].'" alt="'.$row['name'].'" style="width:100%">
                    <div class="container">
                        <h2>'.$row['name'].'</h2>
                        <p class="title">Php '.$row['price'].'</p>
                        <p><button class="btnaddtocart">Add to Cart</button></p>
                    </div>

                </div>
            </div>';
            if($counterrow==3 || $counterrow==$itemcount){
             echo '<div>';
             $counterrow=1;
            }else{
              $counterrow++;
            }
        }


    }
?>
<script>

var cart = new Cart();


</script>
<!-- Start of HubSpot Embed Code -->
    <script type="text/javascript" id="hs-script-loader" async defer src="//js.hs-scripts.com/7751337.js"></script>
<!-- End of HubSpot Embed Code -->
<?php
include('footer.php');
?>
