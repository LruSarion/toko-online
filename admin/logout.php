<?php
require_once __DIR__ . '/../includes/bootstrap.php';
unset($_SESSION['admin_id'], $_SESSION['admin_username']);
session_regenerate_id(true);
redirect('login.php');
