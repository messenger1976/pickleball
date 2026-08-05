<?php
include('config.php');

if (fh_auth_is_managing_business()) {
    fh_auth_exit_business();
    header('Location: index.php');
    exit;
}

session_destroy();
header('Location: login.php');
exit;
