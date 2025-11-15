<?php
require_once '../config/config.php';

session_unset();
session_destroy();

redirect('auth/login.php');
?>