<?php
require_once 'config/auth.php';

$auth = new Auth();
$auth->logout();

header('Location: views/login.php');
exit;
?> 