<?php
include('config.php');
if(isset($_GET['logout'])){
    session_destroy();
    header("Location: login.php");
    exit;
}
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

if(isset($_POST['btnSave'])){
    $cat_id=1;
    $itemname=$_POST['itemname'];
    $itemprice=$_POST['itemprice'];
    $item_id=$_POST['item_id'];
    //echo $item_id;
    //exit;
    $target_dir = "images/";
    $target_file = $target_dir . basename($_FILES["itempix"]["name"]);
    $uploadOk = 1;

    $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));


    // Check if image file is a actual image or fake image
    //if(isset($_POST["submit"])) {
      $check = getimagesize($_FILES["itempix"]["tmp_name"]);
      if($check !== false) {
        echo "File is an image - " . $check["mime"] . ".";
        $uploadOk = 1;
      } else {
        echo "File is not an image.";
        $uploadOk = 0;
      }
    //}

    // Check if file already exists
    if (file_exists($target_file)) {
      echo "Sorry, file already exists.";
      $uploadOk = 0;
    }

    // Check file size
    if ($_FILES["itempix"]["size"] > 50000000) {
      echo "Sorry, your file is too large.";
      $uploadOk = 0;
    }

    // Allow certain file formats
    if($imageFileType != "JPG" && $imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
    && $imageFileType != "gif" ) {
      echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
      $uploadOk = 0;
    }


    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 0) {
      if($item_id==0){
            $stmtheader = $conn->prepare("INSERT INTO items (cat_id,name,price) VALUES ('$cat_id','$itemname','$itemprice')");
            $stmtheader->execute();
        }else{
            $stmtheader = $conn->prepare("update items set cat_id='$cat_id',name='$itemname',price='$itemprice' where id=$item_id");
            $stmtheader->execute();
        }
        header('Location: '.$_SERVER['PHP_SELF']);
        die;
    // if everything is ok, try to upload file
    } else {
      if (move_uploaded_file($_FILES["itempix"]["tmp_name"], $target_file)) {
        //echo "The file ". basename( $_FILES["itempix"]["name"]). " has been uploaded.";
        if($item_id==0){
            $stmtheader = $conn->prepare("INSERT INTO items (cat_id,name,price, picture) VALUES ('$cat_id','$itemname','$itemprice','$target_file')");
            $stmtheader->execute();
        }else{
            $stmtheader = $conn->prepare("update items set cat_id='$cat_id',name='$itemname',price='$itemprice', picture='$target_file' where id=$item_id");
            $stmtheader->execute();
        }
        header('Location: '.$_SERVER['PHP_SELF']);
        die;
      } else {
         if($item_id==0){
            $stmtheader = $conn->prepare("INSERT INTO items (cat_id,name,price) VALUES ('$cat_id','$itemname','$itemprice')");
            $stmtheader->execute();
        }else{
            $stmtheader = $conn->prepare("update items set cat_id='$cat_id',name='$itemname',price='$itemprice' where id=$item_id");
            $stmtheader->execute();
        }
        header('Location: '.$_SERVER['PHP_SELF']);
        die;
      }
      exit;
    }



}
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
//echo $_SESSION['user_fullname'];
include('header.php');
include('navigation.php')
?>

<style>
* {
  box-sizing: border-box;
}

#myInput {
  background-image: url('/css/searchicon.png');
  background-position: 10px 10px;
  background-repeat: no-repeat;
  width: 100%;
  font-size: 16px;
  padding: 12px 20px 12px 40px;
  border: 1px solid #ddd;
  margin-bottom: 12px;
}

#myTable {
  border-collapse: collapse;
  width: 100%;
  border: 1px solid #ddd;
  font-size: 18px;
}

#myTable th, #myTable td {
  text-align: left;
  padding: 12px;
}

#myTable tr {
  border-bottom: 1px solid #ddd;
}

#myTable tr.header, #myTable tr:hover {
  background-color: #f1f1f1;
}
</style>
<?php
if(isset($_POST['btnDate']) || isset($_POST['flag'])){
    $_SESSION['menudate'] = date('Y-m-d',strtotime($_POST['menudate']));
}else{
    if(!isset($_SESSION['menudate'])) $_SESSION['menudate'] = date('Y-m-d');
}
$menudate =  $_SESSION['menudate'];
//$menudate= $_SESSION['menudate'];
?>
<div id="errmsg"></div>
<div class="w3-container">

    <br/>
    <br/>
    <br/>
    <br/>

<div class="w3-container w3-teal">
    <h1>Menu Setup</h1>
</div>
<?php
//echo $menudate;
?>
<form id="menudate" action="?" method="POST"><label>Menu Date:</label><input type="date" id="menudate" name="menudate" value="<?php echo $menudate; ?>"><input type="submit" name="btnDate" value="Submit"></form>
<table id="myTable">
  <tr class="header">
    <th style="width:50%;">Food Name</th>
    <th style="width:40%;">Price</th>
    <th style="width:10%;">Action</th>
  </tr>


<?php
 $itemsql = $conn->query("select * from items LEFT JOIN menu_listing ON (items.id=menu_listing.menu_item and menu_listing.menu_date='$menudate')  order by items.name asc");
    $itemcount =$itemsql->num_rows;
    if($itemcount>0){
        $counterrow = 1;
        while ($row = $itemsql->fetch_assoc()){
            if($row['menu_item']==''){
              $buttonaction = '<input type="hidden" name="flag" value=1/><button>Add</button>';
            }else{
              $buttonaction = '<input type="hidden" name="flag" value=0/><button class="btndelete">Remove</button>';
            }
            echo '<form action="?" method="POST"><input type="hidden" name="menu_id" value="'.$row['menu_id'].'"/><input type="hidden" name="menudate" value="'.$menudate.'"/><input type="hidden" name="item_id" value="'.$row['id'].'"/><tr>
    <td><img src="'.$row['picture'].'" style="width:100%">'.$row['name'].'</td>
    <td><input type="number" name="price" value="'.$row['price'].'"/></td>
    <td><div class="btnline" style="padding:5px;"><center>'.$buttonaction.'</center>

                    </div></td>
  </tr></form>
            ';

        }


    }
?>


</table>



</div>

<div id="id01" class="modal">

    <form class="modal-content animate" action="?" method="post"  enctype="multipart/form-data">


        <div class="newcontainer">
            <label for="itemname"><b>Name</b></label>
            <input type="hidden" id="item_id" name="item_id" value=0/>
            <input type="text" placeholder="Enter Name" id="itemname" name="itemname" required>

            <label for="itemprice"><b>Price</b></label>
            <input type="number" placeholder="Enter Price" id="itemprice" name="itemprice" required>
            <label for="itempix"><b>Picture</b></label>
            <input type="file" id="itempix" name="itempix">
            <div id="image-holder"></div>
            <button type="submit" name="btnSave">Save</button>

        </div>

        <div class="newcontainer" style="background-color:#f1f1f1">
            <button type="button" onclick="popupwindowhide();" class="cancelbtn">Cancel</button>

        </div>
    </form>
</div>

<!--<footer class="w3-container w3-theme w3-margin-top" style="position: fixed; display: block; bottom: 0px; width: 100%;">
    <h3><button style="align:center;" onclick="document.getElementById('id01').style.display='block'" style="width:auto;">Add Item</button></h3>
</footer>-->
<script>
// Get the modal
var msg;
var modal = document.getElementById('id01');

$(document).ready(function() {
    $("#itempix").on('change', function() {
      //Get count of selected files
      var countFiles = $(this)[0].files.length;
      var imgPath = $(this)[0].value;
      var extn = imgPath.substring(imgPath.lastIndexOf('.') + 1).toLowerCase();
      var image_holder = $("#image-holder");
      image_holder.empty();
      if (extn == "gif" || extn == "png" || extn == "jpg" || extn == "jpeg") {
        if (typeof(FileReader) != "undefined") {
          //loop for each file selected for uploaded.
          for (var i = 0; i < countFiles; i++)
          {
            var reader = new FileReader();
            reader.onload = function(e) {
              $("<img />", {
                "src": e.target.result,
                "class": "thumb-image"
              }).appendTo(image_holder);
            }
            image_holder.show();
            reader.readAsDataURL($(this)[0].files[i]);
          }
        } else {
          alert("This browser does not support FileReader.");
        }
      } else {
        alert("Pls select only images");
      }
    });
});



// When the user clicks anywhere outside of the modal, close it
window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = "none";
    }
}
function deletewindow(item_id,item_pix){
   var deleterec = confirm('Are you sure to delete this record?');
   if(deleterec==true){
       $.ajax({
			   type: "POST",
			   url: "process.php",
			   data: {
			   			'function': 'DeleteRecord',
                        'item_id': item_id,
                        'item_pix': item_pix
						},
			   dataType: "json",

			   success: function(data){
			       msg = data.msg;

			   }
		 });

   }else{
        msg=false;
   }
   return msg;
   //alert(errormsg):
   //document.getElementById("errmsg").style.display = "block";
   //document.getElementById("errmsg").innerHTML = errormsg;
}
function popupwindowshow(item_id=0){
    if(item_id>0){
       $.ajax({
			   type: "POST",
			   url: "process.php",
			   data: {
			   			'function': 'EditRecord',
                        'item_id': item_id
			   },
			   dataType: "json",

			   success: function(data){
				   msg = data.msg;
                   $('#item_id').val(data.id);
                   $('#itemname').val(data.name);
			       $('#itemprice').val(data.price);
                   document.getElementById('image-holder').innerHTML = '<img src="'+data.picture+'" class="thumb-image"/>';
			   }
		 });

   }else{
        $('#item_id').val(item_id);
                   $('#itemname').val('');
			       $('#itemprice').val(0);
                   document.getElementById('image-holder').innerHTML = '';
   }
    modal.style.display='block';
}
function popupwindowhide(){
    modal.style.display='none';
}
</script>
<?php
include('footer.php');
?>

