<?php
/**
 * Pebble CMS - A lightweight flat-file CMS
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define base paths
define('ROOT_DIR', __DIR__);
define('CORE_DIR', ROOT_DIR . '/core');
define('PAGES_DIR', ROOT_DIR . '/pages');
define('THEMES_DIR', ROOT_DIR . '/themes');
define('CONFIG_DIR', ROOT_DIR . '/config');

// Add site configuration
$site = 'default'; // Default site if none specified
if (isset($_SERVER['HTTP_HOST'])) {
    // You can customize this logic to determine the site based on domain
    $host = $_SERVER['HTTP_HOST'];
    // Example: if domain is site1.example.com, use 'site1' as the site name
    $site = explode('.', $host)[0];
}

// Simple autoloader for core classes
spl_autoload_register(function ($class) {
    $file = CORE_DIR . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Load configuration
$config = [];
if (file_exists(CONFIG_DIR . '/site.yaml')) {
    $config = YamlParser::parse(file_get_contents(CONFIG_DIR . '/site.yaml'));
}

// Basic routing
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');

// If we're at the root URL, return list of sites and pages
if (empty($uri)) {
    header('Content-Type: application/json');
    
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
                    // Extract the numeric prefix (up to 4 digits)
                    if (preg_match('/^(\d{1,4})\./', $pagePath, $matches)) {
                        $order = (int)$matches[1];
                        // Remove the numeric prefix for the URL
                        $cleanPath = preg_replace('/^\d{1,4}\./', '', $pagePath);
                        $pagesWithOrder[] = [
                            'order' => $order,
                            'path' => $cleanPath,
                            'original_path' => $pagePath
                        ];
                    } else {
                        // If no numeric prefix, add with high order number
                        $pagesWithOrder[] = [
                            'order' => 9999,
                            'path' => $pagePath,
                            'original_path' => $pagePath
                        ];
                    }
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
    
    echo json_encode([
        'sites' => $sites,
        'total_sites' => count($sites)
    ], JSON_PRETTY_PRINT);
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
                // Extract the numeric prefix (up to 4 digits)
                if (preg_match('/^(\d{1,4})\./', $pagePath, $matches)) {
                    $order = (int)$matches[1];
                    // Remove the numeric prefix for the URL
                    $cleanPath = preg_replace('/^\d{1,4}\./', '', $pagePath);
                    $pagesWithOrder[] = [
                        'order' => $order,
                        'path' => $cleanPath,
                        'original_path' => $pagePath
                    ];
                } else {
                    // If no numeric prefix, add with high order number
                    $pagesWithOrder[] = [
                        'order' => 9999,
                        'path' => $pagePath,
                        'original_path' => $pagePath
                    ];
                }
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

// Modify the page loading logic to handle numeric prefixes
if (preg_match('/^\d{1,4}\./', $uri)) {
    // If the URI starts with a numeric prefix, look directly in the pages directory
    $pageFile = PAGES_DIR . '/' . $uri . '/default.md';
    error_log("Looking for page file: $pageFile");
} else {
    // Otherwise use the site-based path
    $pageFile = PAGES_DIR . '/' . $site . '/' . ($uri ? $uri . '/' : '') . 'default.md';
    error_log("Looking for page file: $pageFile");
}

// If the page doesn't exist directly, try to find it by scanning the directory
if (!file_exists($pageFile)) {
    $dirPath = preg_match('/^\d{1,4}\./', $uri) 
        ? PAGES_DIR . '/' . dirname($uri)  // For numeric prefixes, look in pages root
        : PAGES_DIR . '/' . $site . '/' . ($uri ? dirname($uri) : ''); // For site-based paths
    if (is_dir($dirPath)) {
        $dirContents = scandir($dirPath);
        foreach ($dirContents as $item) {
            // Look for directories that match the pattern: numbers followed by dot
            if (is_dir($dirPath . '/' . $item) && preg_match('/^\d{1,4}\./', $item)) {
                // Remove the numeric prefix and compare with the requested URI
                $cleanName = preg_replace('/^\d{1,4}\./', '', $item);
                if ($cleanName === basename($uri)) {
                    $pageFile = $dirPath . '/' . $item . '/default.md';
                    break;
                }
            }
        }
    }
}

error_log("Final page file checked: $pageFile");

if (!file_exists($pageFile)) {
    // Fallback to default site if page not found in specific site
    $pageFile = PAGES_DIR . '/default/' . $uri . '/default.md';
    error_log("Fallback to default site, checking: $pageFile");
    if (!file_exists($pageFile)) {
        header("HTTP/1.0 404 Not Found");
        echo "Page not found";
        exit;
    }
}

// Parse the content
$content = new Content($pageFile);
// TODO: Implement template rendering

// Set JSON content type header
header('Content-Type: application/json');

// Create response array
$response = [
    'title' => $content->get('title', 'Untitled'),
    'content' => $content->getContent(),
    'frontmatter' => $content->getFrontmatter()
];

// Output JSON
echo json_encode($response, JSON_PRETTY_PRINT);






///// PEBBLE CMS /////