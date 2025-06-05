<?php
require_once __DIR__ . '/../includes/security.php';
secure_file();

// Define required constants
define('ROOT_DIR', dirname(dirname(__DIR__)));

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get the page path from POST data
$pagePath = $_POST['path'] ?? '';
if (empty($pagePath)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No page path provided']);
    exit;
}

// Construct the full path
$fullPath = ROOT_DIR . '/pages/' . $pagePath;

// Validate the path
if (!file_exists($fullPath)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Page not found']);
    exit;
}

// Check if the file is within the pages directory
$realPath = realpath($fullPath);
$pagesDir = realpath(ROOT_DIR . '/pages');
if (strpos($realPath, $pagesDir) !== 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid page path']);
    exit;
}

// Try to delete the file
if (unlink($fullPath)) {
    // If this was the last file in the directory, remove the directory
    $dirPath = dirname($fullPath);
    if (is_dir($dirPath) && count(glob("$dirPath/*")) === 0) {
        rmdir($dirPath);
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to delete page']);
} 