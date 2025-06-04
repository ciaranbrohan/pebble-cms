<?php
// Define required constants
define('ROOT_DIR', dirname(__DIR__));
define('CONFIG_DIR', ROOT_DIR . '/config');

// Now include the API auth functionality
require_once __DIR__ . '/../core/ApiAuth.php';

$apiAuth = new ApiAuth();

// Simple CLI interface
if ($argc < 2) {
    echo "Usage:\n";
    echo "  php manage_api_keys.php generate <name> [permissions]\n";
    echo "  php manage_api_keys.php list\n";
    echo "  php manage_api_keys.php revoke <api_key>\n";
    exit(1);
}

$command = $argv[1];

switch ($command) {
    case 'generate':
        if ($argc < 3) {
            echo "Error: Name required for generate command\n";
            exit(1);
        }
        $name = $argv[2];
        $permissions = isset($argv[3]) ? explode(',', $argv[3]) : ['read'];
        $apiKey = $apiAuth->generateApiKey($name, $permissions);
        echo "Generated API Key: $apiKey\n";
        break;
        
    case 'list':
        $keys = $apiAuth->listApiKeys();
        foreach ($keys as $key => $info) {
            echo "Key: $key\n";
            echo "  Name: {$info['name']}\n";
            echo "  Status: " . ($info['active'] ? 'Active' : 'Inactive') . "\n";
            echo "  Permissions: " . implode(', ', $info['permissions']) . "\n";
            echo "  Created: {$info['created_at']}\n";
            echo "  Last Used: {$info['last_used']}\n";
            echo "\n";
        }
        break;
        
    case 'revoke':
        if ($argc < 3) {
            echo "Error: API key required for revoke command\n";
            exit(1);
        }
        $apiKey = $argv[2];
        if ($apiAuth->revokeApiKey($apiKey)) {
            echo "API key revoked successfully\n";
        } else {
            echo "Error: API key not found\n";
            exit(1);
        }
        break;
        
    default:
        echo "Unknown command: $command\n";
        exit(1);
} 