<?php
set_time_limit(30);
ini_set('memory_limit',"-1");
//ini_set('max_execution_time', "-1");
include('admin/db_config.php');
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/foodhauz.css" type="text/css" />


</head>
<body>
<!-- Top Navigation Menu -->
<div class="topnav">
    <div id="titlepage">MAIN MENU</div>
    <!--<a href="#home" class="active">EULIES FOOD HAUZ</a> -->
    <!-- Navigation links (hidden by default) -->

    <!-- "Hamburger menu" / "Bar icon" to toggle the navigation links  <i class="fa fa-bars"></i>-->
    <a href="javascript:void(0);" class="icon" onclick="openNav()">&#9776;</a>
    <!--<span style="font-size:30px;cursor:pointer" onclick="openNav()">&#9776; open</span> -->
</div>
 <div id="mySidenav" class="sidenav">
        <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
        <a href="#menu">Main Menu</a>
        <a href="contactus.php">Contact Us</a>
        <a href="#profile">Profile</a>
        <a href="#login">Login</a>
 </div>
<div class="container">
<h2 style="text-align:center">Food Menu</h2>

<div class="card">
    <img src="images/food/pork-hamonado-12.jpg" alt="PORK HAMONADO" style="width:100%">
    <h1>PORK HAMONADO</h1>
    <p class="price">Php 35.00</p>

    <p><button>Add to Cart</button></p>
</div>
<div class="card">
    <img src="images/food/pork-inatayan.jpg" alt="PORK (INATAYAN)" style="width:100%">
    <h1>PORK (INATAYAN)</h1>
    <p class="price">Php 30.00</p>

    <p><button>Add to Cart</button></p>
</div>
<div class="card">
    <img src="images/food/porkchop.png" alt="PORK CHOP" style="width:100%">
    <h1>PORK CHOP</h1>
    <p class="price">Php 40.00</p>

    <p><button>Add to Cart</button></p>
</div>
<div class="card">
    <img src="images/food/friedchicken.jpg" alt="Fried Chicken" style="width:100%">
    <h1>Fried Chicken</h1>
    <p class="price">Php 35.00</p>

    <p><button>Add to Cart</button></p>
</div>
<div class="card">
    <img src="images/food/chickenadobo.jpg" alt="CHICKEN ADOBO" style="width:100%">
    <h1>CHICKEN ADOBO</h1>
    <p class="price">Php 25.00</p>

    <p><button>Add to Cart</button></p>
</div>
<div class="card">
    <img src="images/food/sinigangnawhitemarlin.jpg" alt="SINIGANG NA WHITE MARLIN" style="width:100%">
    <h1>SINIGANG NA WHITE MARLIN</h1>
    <p class="price">Php 30.00</p>

    <p><button>Add to Cart</button></p>
</div>
<div class="card">
    <img src="images/food/kangkong.jpg" alt="KANGKONG" style="width:100%">
    <h1>KANGKONG</h1>
    <p class="price">Php 20.00</p>

    <p><button>Add to Cart</button></p>
</div>
<div class="card">
    <img src="images/food/pusonangsagingkinilaw.JPG" alt="PUSO SAGING (KINILAW)" style="width:100%">
    <h1>PUSO SAGING (KINILAW)</h1>
    <p class="price">Php 25.00</p>

    <p><button>Add to Cart</button></p>
</div>
<div class="card">
    <img src="images/food/spaghetti.jpg" alt="SPAGHETTI" style="width:100%">
    <h1>SPAGHETTI</h1>
    <p class="price">Php 25.00</p>

    <p><button>Add to Cart</button></p>
</div>
<div class="card">
    <img src="images/food/guso.jpg" alt="GUSO" style="width:100%">
    <h1>GUSO</h1>
    <p class="price">Php 15.00</p>

    <p><button>Add to Cart</button></p>
</div>
<div class="card">
    <img src="images/food/nilagangbaboy.jpg" alt="NILAGANG BABOY" style="width:100%">
    <h1>NILAGANG BABOY</h1>
    <p class="price">Php 40.00</p>

    <p><button>Add to Cart</button></p>
</div>
</div>
<script>
function openNav() {
  document.getElementById("mySidenav").style.width = "250px";
  /*document.getElementById("main").style.marginLeft = "250px";*/
  document.body.style.backgroundColor = "rgba(0,0,0,0.4)";
}

function closeNav() {
  document.getElementById("mySidenav").style.width = "0";
  document.getElementById("main").style.marginLeft = "0";
  document.body.style.backgroundColor = "white";
}
</script>
</body>
</html>
