<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $pagetitle;?></title>
    <meta name="description" content="Find and book pickleball courts in your community">
    <meta name="keywords" content="pickleball, courts, booking, clubs, Bohol">
    <meta name="author" content="Herald B. Felisilda">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0f3d2e">
<link rel="manifest" href="manifest.webmanifest">
<link rel="apple-touch-icon" href="images/pwa-icon-192.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin/css/w3.css">
<link rel="stylesheet" href="css/foodhauz.css?v=20260803b" type="text/css" />
<link rel="stylesheet" href="admin/css/sweetalert.css" type="text/css" />
<link rel="stylesheet" href="css/pickleball-theme.css?v=20260803d" type="text/css" />
<link rel="stylesheet" href="css/site-footer.css?v=20260803" type="text/css" />
<?php if (!empty($extraStylesheets) && is_array($extraStylesheets)): ?>
<?php foreach ($extraStylesheets as $cssHref): ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($cssHref); ?>" type="text/css" />
<?php endforeach; ?>
<?php endif; ?>
<script type="text/javascript" src="admin/js/jquery.min.js"></script>
<script type="text/javascript" src="admin/js/sweetalert.min.js"></script>
<script type="text/javascript" src="js/foodhauz.js?v=20260715a"></script>
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('sw.js').catch(function () {});
  });
}
</script>
<style>
/** {
    font-family: Arial, Helvetica, sans-serif;
}*/

/*html {
    box-sizing: border-box;
}*/

*, *:before, *:after {
    box-sizing: inherit;
}


/* Style inputs */
input[type=text], input[type=email], input[type=tel], select, textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    margin-top: 6px;
    margin-bottom: 16px;
    resize: vertical;
    box-sizing: border-box;
}
input[type=date]{
     width: 150px;
    padding: 12px;
    border: 1px solid #ccc;
    margin-top: 6px;
    margin-bottom: 16px;
    resize: vertical;
}

input[type=submit] {
    background-color: #4CAF50;
    color: white;
    padding: 12px 20px;
    border: none;
    cursor: pointer;
}

input[type=submit]:hover {
    background-color: #45a049;
}

.column {
    float: left;
    width: 33.3%;
    margin-bottom: 16px;
    padding: 0 8px;
}

@media screen and (max-width: 650px) {
    .column {
        width: 100%;
        display: block;
    }
    .column, input[type=submit] {

        margin-top: 0;
    }
}


.container {
    padding: 0 16px;
    /*text-align: center; */
}

.container::after, .row::after {
    content: "";
    clear: both;
    display: table;
}

.title {
    color: grey;
    font-size: 18px;
    font-weight: bold;
}

.btnaddtocart {
    border: none;
    outline: 0;
    display: inline-block;
    padding: 12px;
    color: white;
    background-color: red;
    text-align: center;
    cursor: pointer;
    width: 100%;
    font-weight: bolder;
    font-size: 18px;
}

.btnaddtocart:hover {
    background-color: green;
}
</style>
<script>

var cart = new Cart();


</script>
</head>
<body<?php echo isset($bodyClass) ? ' class="' . htmlspecialchars($bodyClass) . '"' : ''; ?>>
<?php
include('navigation.php');

?>