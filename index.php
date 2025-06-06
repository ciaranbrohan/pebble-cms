<?php
/**
 * Pebble CMS - A lightweight flat-file CMS
 */

// Define base paths
define('ROOT_DIR', __DIR__);
define('CORE_DIR', ROOT_DIR . '/core');
define('PAGES_DIR', ROOT_DIR . '/pages');
define('THEMES_DIR', ROOT_DIR . '/themes');
define('CONFIG_DIR', ROOT_DIR . '/config');

// Environment detection
$isProduction = getenv('APP_ENV') === 'production';
$isCli = (php_sapi_name() === 'cli');

// Error reporting based on environment
if ($isProduction) {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', ROOT_DIR . '/logs/error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Initialize API Authentication
require_once CORE_DIR . '/ApiAuth.php';
require_once CORE_DIR . '/Content.php';
$apiAuth = new ApiAuth();

// Get URI - handle both CLI and web contexts
$uri = '';
if (!$isCli) {
    // Web context
    if (isset($_SERVER['REQUEST_URI'])) {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = trim($uri, '/');
    }

    // Set security headers BEFORE any output
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';");
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    if ($isProduction) {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }

    // Get API key from request
    $apiKey = null;
    if (isset($_SERVER['HTTP_X_API_KEY'])) {
        $apiKey = $_SERVER['HTTP_X_API_KEY'];
    } elseif (isset($_GET['api_key'])) {
        $apiKey = $_GET['api_key'];
    }

    // Public routes that don't require API key
    $publicRoutes = ['/', '/health', '/docs'];

    // Check if the current route is public
    $isPublicRoute = in_array('/' . $uri, $publicRoutes);

    // Temporarily disabled API key validation
    /*
    // Validate API key for non-public routes
    if (!$isPublicRoute) {
        if (!$apiKey || !$apiAuth->validateApiKey($apiKey)) {
            header('HTTP/1.0 401 Unauthorized');
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Invalid or missing API key',
                'status' => 'error'
            ]);
            exit;
        }
        
        // Update last used timestamp for the API key
        $apiAuth->updateLastUsed($apiKey);
        
        // Check permissions if needed
        $permissions = $apiAuth->getApiKeyPermissions($apiKey);
        if (!in_array('read', $permissions)) {
            header('HTTP/1.0 403 Forbidden');
            echo json_encode([
                'error' => 'Insufficient permissions',
                'status' => 'error'
            ]);
            exit;
        }
    }
    */
}

// If we're at the root URL, return list of sites and pages
if (empty($uri)) {
    // Set content type BEFORE any output
    if (!$isCli) {
        header('Content-Type: application/json');
    }
    
    $sites = [];
    $siteDirs = glob(PAGES_DIR . '/*', GLOB_ONLYDIR);
    
    foreach ($siteDirs as $siteDir) {
        $siteName = basename($siteDir);
        $pages = [];
        
        // Recursively find all .md files
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($siteDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        // Store pages with their numeric prefixes for sorting
        $pagesWithOrder = [];
        
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'md') {
                // Get relative path from site directory
                $relativePath = str_replace($siteDir . '/', '', $file->getPathname());
                // Remove 'default.md' from the end
                $pagePath = str_replace('/default.md', '', $relativePath);
                
                if (!empty($pagePath)) {
                    // No need to extract numeric prefix anymore
                    $pagesWithOrder[] = [
                        'order' => 9999, // Default order for sorting
                        'path' => $pagePath,
                        'original_path' => $pagePath
                    ];
                }
            }
        }
        
        // Sort pages by their numeric order
        usort($pagesWithOrder, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        // Extract just the clean paths for the final output
        $pages = array_map(function($page) {
            return $page['path'];
        }, $pagesWithOrder);
        
        $sites[$siteName] = [
            'pages' => $pages,
            'url' => '/' . $siteName
        ];
    }
    
    $response = [
        'sites' => $sites,
        'total_sites' => count($sites)
    ];
    
    if ($isCli) {
        // In CLI, just print the sites
        foreach ($sites as $siteName => $siteInfo) {
            echo "Site: $siteName\n";
            echo "URL: {$siteInfo['url']}\n";
            echo "Pages:\n";
            foreach ($siteInfo['pages'] as $page) {
                echo "  - $page\n";
            }
            echo "\n";
        }
    } else {
        // In web context, output JSON
        echo json_encode($response, JSON_PRETTY_PRINT);
    }
    exit;
}

// Split the URI to get potential site prefix and remaining path
$parts = explode('/', $uri, 2);
$potentialSite = $parts[0]; // First part could be a site name
$remainingPath = isset($parts[1]) ? $parts[1] : ''; // Empty string for root path

// First check if the potential site is actually a numeric-prefixed directory
$numericPrefixedDir = PAGES_DIR . '/' . $potentialSite;
if (is_dir($numericPrefixedDir) && preg_match('/^\d{1,4}\./', $potentialSite)) {
    // It's a numeric-prefixed directory, use it as the page path
    $site = 'default';
    $uri = $potentialSite . ($remainingPath ? '/' . $remainingPath : '');
} else {
    // Check if the first part matches a site directory
    $siteDir = PAGES_DIR . '/' . $potentialSite;
    if (is_dir($siteDir)) {
        // It's a site-specific path
        $site = $potentialSite;
        $uri = $remainingPath;
    } else {
        // It's a regular path, use default site
        $site = 'default';
        $uri = $uri; // Use the full original URI
    }
}

// If we're accessing a directory path, list its contents
$dirPath = PAGES_DIR . '/' . $site . '/' . $uri;
if (is_dir($dirPath)) {
    header('Content-Type: application/json');
    
    $pages = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    // Store pages with their numeric prefixes for sorting
    $pagesWithOrder = [];
    
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'md') {
            // Get relative path from current directory
            $relativePath = str_replace($dirPath . '/', '', $file->getPathname());
            // Remove 'default.md' from the end
            $pagePath = str_replace('/default.md', '', $relativePath);
            
            if (!empty($pagePath)) {
                // No need to extract numeric prefix anymore
                $pagesWithOrder[] = [
                    'order' => 9999, // Default order for sorting
                    'path' => $pagePath,
                    'original_path' => $pagePath
                ];
            }
        }
    }
    
    // Sort pages by their numeric order
    usort($pagesWithOrder, function($a, $b) {
        return $a['order'] - $b['order'];
    });
    
    // Extract just the clean paths for the final output
    $pages = array_map(function($page) {
        return $page['path'];
    }, $pagesWithOrder);
    
    echo json_encode([
        'site' => $site,
        'path' => $uri,
        'pages' => $pages,
        'total_pages' => count($pages)
    ], JSON_PRETTY_PRINT);
    exit;
}

// Function to strip numeric prefixes from a path
function stripNumericPrefix($path) {
    return preg_replace('/^\d{1,4}\./', '', $path);
}

// Function to find the actual file path by handling numeric prefixes at all levels
function findActualFilePath($basePath, $remainingPath) {
    if (empty($remainingPath)) {
        return $basePath . '/default.md';
    }

    $parts = explode('/', $remainingPath, 2);
    $currentDir = $parts[0];
    $nextPath = isset($parts[1]) ? $parts[1] : '';

    // Scan the current directory
    if (is_dir($basePath)) {
        $dirContents = scandir($basePath);
        foreach ($dirContents as $item) {
            // Look for directories that match the pattern: numbers followed by dot
            if (is_dir($basePath . '/' . $item) && preg_match('/^\d{1,4}\./', $item)) {
                // Remove the numeric prefix and compare with the requested path
                $cleanName = stripNumericPrefix($item);
                if ($cleanName === $currentDir) {
                    // Found matching directory, recurse into it
                    return findActualFilePath($basePath . '/' . $item, $nextPath);
                }
            }
        }
    }

    // If no numeric prefix match found, try the original path
    $nextBasePath = $basePath . '/' . $currentDir;
    if (is_dir($nextBasePath)) {
        return findActualFilePath($nextBasePath, $nextPath);
    }

    return null;
}

// Replace the existing page loading logic (around line 280) with:
$cleanUri = stripNumericPrefix($uri);
$pageFile = findActualFilePath(PAGES_DIR . '/' . $site, $cleanUri);

error_log("Looking for page file: $pageFile");

if (!$pageFile || !file_exists($pageFile)) {
    // Fallback to default site if page not found in specific site
    $pageFile = findActualFilePath(PAGES_DIR . '/default', $cleanUri);
    error_log("Fallback to default site, checking: $pageFile");
    if (!$pageFile || !file_exists($pageFile)) {
        header("HTTP/1.0 404 Not Found");
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Page not found',
            'status' => 'error'
        ]);
        exit;
    }
}

// Parse the content
$content = new Content($pageFile);

// Set JSON content type header
header('Content-Type: application/json');

// Create response array
$response = [
    'title' => $content->get('title', 'Untitled'),
    'template' => $content->get('template', 'default'),
    'frontmatter' => $content->getFrontmatter()
];

// Handle modular pages differently
if ($content->isModular()) {
    $response['type'] = 'modular';
    $response['modules'] = array_map(function($module) {
        return [
            'title' => $module->get('title', 'Untitled'),
            'content' => $module->getContent(),
            'frontmatter' => $module->getFrontmatter(),
            'template' => $module->get('template', 'default')
        ];
    }, $content->getModules());
} else {
    $response['type'] = 'standard';
    $response['content'] = $content->getContent();
}

// Modify the JSON response to include rate limit information
$response['rate_limit'] = [
    'remaining' => $apiAuth->getRemainingRequests($apiKey),
    'reset' => $apiAuth->getRateLimitReset($apiKey)
];

// Add rate limit headers
header('X-RateLimit-Limit: ' . $apiAuth->getRateLimit());
header('X-RateLimit-Remaining: ' . $apiAuth->getRemainingRequests($apiKey));
header('X-RateLimit-Reset: ' . $apiAuth->getRateLimitReset($apiKey));

// Secure JSON output
function secureJsonEncode($data) {
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
}

// Use secure JSON encoding
echo secureJsonEncode($response);






///// PEBBLE CMS /////
///// PEBBLE CMS /////