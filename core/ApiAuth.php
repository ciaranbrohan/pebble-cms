<?php

class ApiAuth {
    private $apiKeys = [];
    private $apiKeysFile;
    private $rateLimit = 100; // Default rate limit per hour
    private $rateLimitWindow = 3600; // 1 hour in seconds
    
    public function __construct() {
        $this->apiKeysFile = CONFIG_DIR . '/api_keys.json';
        $this->loadApiKeys();
    }
    
    private function loadApiKeys() {
        if (file_exists($this->apiKeysFile)) {
            $this->apiKeys = json_decode(file_get_contents($this->apiKeysFile), true) ?? [];
        }
    }
    
    public function validateApiKey($apiKey) {
        if (empty($apiKey)) {
            return false;
        }
        
        // Check if API key exists and is valid
        return isset($this->apiKeys[$apiKey]) && $this->apiKeys[$apiKey]['active'] === true;
    }
    
    public function getApiKeyPermissions($apiKey) {
        return $this->apiKeys[$apiKey]['permissions'] ?? [];
    }
    
    public function generateApiKey($name, $permissions = ['read']) {
        $apiKey = bin2hex(random_bytes(32)); // Generate a secure random key
        $this->apiKeys[$apiKey] = [
            'name' => $name,
            'active' => true,
            'permissions' => $permissions,
            'created_at' => date('Y-m-d H:i:s'),
            'last_used' => null
        ];
        
        $this->saveApiKeys();
        return $apiKey;
    }
    
    private function saveApiKeys() {
        // Ensure the config directory exists and is writable
        if (!is_dir(CONFIG_DIR)) {
            mkdir(CONFIG_DIR, 0755, true);
        }
        
        // Save with proper permissions
        file_put_contents(
            $this->apiKeysFile,
            json_encode($this->apiKeys, JSON_PRETTY_PRINT),
            LOCK_EX
        );
        chmod($this->apiKeysFile, 0600); // Restrictive permissions
    }

    public function listApiKeys() {
        return $this->apiKeys;
    }

    public function getRateLimit() {
        return $this->rateLimit;
    }

    public function getRemainingRequests($apiKey) {
        if (!isset($this->apiKeys[$apiKey])) {
            return 0;
        }

        $keyData = $this->apiKeys[$apiKey];
        $lastUsed = strtotime($keyData['last_used'] ?? '2000-01-01 00:00:00');
        $now = time();

        // Reset rate limit if window has passed
        if ($now - $lastUsed > $this->rateLimitWindow) {
            return $this->rateLimit;
        }

        // Get current usage count
        $usageCount = $keyData['usage_count'] ?? 0;
        return max(0, $this->rateLimit - $usageCount);
    }

    public function getRateLimitReset($apiKey) {
        if (!isset($this->apiKeys[$apiKey])) {
            return time() + $this->rateLimitWindow;
        }

        $keyData = $this->apiKeys[$apiKey];
        $lastUsed = strtotime($keyData['last_used'] ?? '2000-01-01 00:00:00');
        return $lastUsed + $this->rateLimitWindow;
    }

    public function updateLastUsed($apiKey) {
        if (!isset($this->apiKeys[$apiKey])) {
            return;
        }

        // Initialize or increment usage count
        if (!isset($this->apiKeys[$apiKey]['usage_count'])) {
            $this->apiKeys[$apiKey]['usage_count'] = 1;
        } else {
            $this->apiKeys[$apiKey]['usage_count']++;
        }

        // Update last used timestamp
        $this->apiKeys[$apiKey]['last_used'] = date('Y-m-d H:i:s');
        
        // Reset usage count if rate limit window has passed
        $lastUsed = strtotime($this->apiKeys[$apiKey]['last_used']);
        if (time() - $lastUsed > $this->rateLimitWindow) {
            $this->apiKeys[$apiKey]['usage_count'] = 1;
        }

        $this->saveApiKeys();
    }
} 