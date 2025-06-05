<?php
// Prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    die('Direct access not permitted');
}

// Set restrictive permissions on the config directory and files
$config_dir = __DIR__;
$config_file = $config_dir . '/admin.json';

// Set directory permissions to 700 (rwx------)
chmod($config_dir, 0700);

// Set file permissions to 600 (rw-------)
if (file_exists($config_file)) {
    chmod($config_file, 0600);
} 