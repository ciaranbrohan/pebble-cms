<?php
require_once __DIR__ . '/../includes/security.php';
secure_file();

// Get password from command line argument
if ($argc < 2) {
    die("Usage: php generate_password.php 'your_password_here'\n");
}

$password = $argv[1];
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Your password hash is: " . $hash . "\n";
echo "Copy this into your admin.json file as the password_hash value\n";