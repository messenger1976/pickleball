<?php
include('admin/config.php'); 
session_destroy();
header('Location: index.php');
exit;

?>