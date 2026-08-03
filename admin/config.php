<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
error_reporting(0);
session_start();
ini_set('date.timezone', 'Asia/Manila');
include('db_config.php');
include_once(__DIR__ . '/payment_config.php');
include_once(__DIR__ . '/../includes/business_helper.php');
include_once(__DIR__ . '/../includes/auth_helper.php');

?>