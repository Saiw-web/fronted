<?php
include 'config.php';
session_destroy();
header('Location: login.php?logged_out=1');
exit;
?>