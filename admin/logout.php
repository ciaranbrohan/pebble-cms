<?php
require_once __DIR__ . '/includes/security.php';
secure_file();

session_start();
session_destroy();
header('Location: login.php');
exit; 