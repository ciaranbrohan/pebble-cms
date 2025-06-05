<?php
// Prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    die('Direct access not permitted');
}

// Define a constant to check if the file is being included properly
define('ADMIN_SECURE_ACCESS', true);

// Function to check if the current file is being accessed directly
function check_direct_access() {
    if (!defined('ADMIN_SECURE_ACCESS')) {
        die('Direct access not permitted');
    }
}

// Function to secure a file
function secure_file() {
    // Check if the file is being accessed directly
    check_direct_access();
    
    // Additional security headers
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; style-src \'self\' \'unsafe-inline\' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; font-src \'self\' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net;');
} 