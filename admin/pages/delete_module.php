<?php
require_once __DIR__ . '/../includes/security.php';
secure_file();

// Define required constants
define('ROOT_DIR', dirname(dirname(__DIR__)));

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['module_dir']) || !isset($input['parent_path'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$moduleDir = $input['module_dir'];
$parentPath = $input['parent_path'];

// Construct full path to module directory
$moduleDirPrefix = str_starts_with($moduleDir, '_') ? '' : '_';
$fullModulePath = ROOT_DIR . '/pages/' . dirname($parentPath) . '/' . $moduleDirPrefix . $moduleDir;

// Validate the path is within allowed directory
if (!str_starts_with(realpath($fullModulePath), realpath(ROOT_DIR . '/pages/'))) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid module path', 'fullModulePath' => $fullModulePath]);
    exit;
}

// Check if directory exists
if (!is_dir($fullModulePath)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Module directory not found']);
    exit;
}

// Function to recursively delete directory
function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    return rmdir($dir);
}

// Attempt to delete the module directory
if (deleteDirectory($fullModulePath)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to delete module directory']);
}