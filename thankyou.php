<?php
include('admin/config.php');



$pagetitle="Thank you - Eulies Food Hauz";
include('header.php');
?>
<style>
* {
    box-sizing: border-box;
}

input[type=text], select, textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
    resize: vertical;
}

label {
    padding: 12px 12px 12px 0;
    display: inline-block;
}

input[type=submit] {
    background-color: #4CAF50;
    color: white;
    padding: 12px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    float: right;
}

input[type=submit]:hover {
    background-color: #45a049;
}

.container {
    border-radius: 5px;
    background-color: #f2f2f2;
    padding: 20px;
}

.col-25 {
    float: left;
    width: 25%;
    margin-top: 6px;
}

.col-75 {
    float: left;
    width: 75%;
    margin-top: 6px;
}

/* Clear floats after the columns */
.row:after {
    content: "";
    display: table;
    clear: both;
}

/* Responsive layout - when the screen is less than 600px wide, make the two columns stack on top of each other instead of next to each other */
@media screen and (max-width: 600px) {
    .col-25, .col-75, input[type=submit] {
        width: 100%;
        margin-top: 0;
    }
}
#msgbox{
    display: none;
    position: relative;
    font-size: 18px;
    margin:20px;
    padding: 10px;
    background-color: #95C6CC;
    border-radius: 5px;
    text-align:center;
}
</style>


<div class="main"><br/>
    <div class="w3-row-padding w3-center">
        
        <div class="container">
            THANK YOU FOR YOUR ORDER...
        </div>
        <img src="admin/images/check1.gif" alt="" />

        <div class="container">PLEASE CHECK YOUR ORDER PAGE <a href="orders.php">HERE</a></div>
        
    </div>
</div>


<?php
include('footer.php');
?>