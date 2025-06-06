<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . '/../includes/security.php';
secure_file();

// Define required constants
define('ROOT_DIR', dirname(dirname(__DIR__)));
define('CONFIG_DIR', ROOT_DIR . '/config');

require_once __DIR__ . '/../../core/ApiAuth.php';
require_once __DIR__ . '/../../core/YamlParser.php';
require_once __DIR__ . '/../../core/Content.php';
require_once __DIR__ . '/../includes/header.php';

// Validate path parameter
if (!isset($_GET['path'])) {
    $_SESSION['flash_message'] = 'No page specified';
    $_SESSION['flash_type'] = 'error';
    header('Location: index.php');
    exit;
}

$pagePath = $_GET['path'];
$fullPath = ROOT_DIR . '/pages/' . $pagePath;

// Add this code to determine the site
$pathParts = explode('/', $pagePath);
$site = $pathParts[0]; // The first part of the path is the site name

// Validate file exists and is readable
if (!file_exists($fullPath) || !is_readable($fullPath)) {
    $_SESSION['flash_message'] = 'Page not found or not accessible';
    $_SESSION['flash_type'] = 'error';
    header('Location: index.php');
    exit;
}

// Load page content
$content = new Content($fullPath);
$frontmatter = $content->getFrontmatter();
$markdownContent = $content->getContent();

$isModule = isset($frontmatter['type']) && $frontmatter['type'] === 'module';

// After loading the page content, add this code to load sibling modules
$siblingModules = [];
$pageDir = dirname($fullPath);
$moduleDirs = glob($pageDir . '/_*', GLOB_ONLYDIR);

foreach ($moduleDirs as $moduleDir) {
    $moduleFile = $moduleDir . '/default.md';
    if (file_exists($moduleFile)) {
        $moduleContent = new Content($moduleFile);
        $moduleFrontmatter = $moduleContent->getFrontmatter();
        $siblingModules[] = [
            'title' => $moduleFrontmatter['title'] ?? basename($moduleDir),
            'content' => $moduleContent->getContent(),
            'template' => $moduleFrontmatter['template'] ?? 'default',
            'order' => $moduleFrontmatter['order'] ?? 0,
            'path' => str_replace(ROOT_DIR . '/pages/', '', $moduleFile),
            'directory' => basename($moduleDir),
            'frontmatter' => $moduleFrontmatter
        ];
    }
}

// Sort modules by order
usort($siblingModules, function($a, $b) {
    return $a['order'] - $b['order'];
});

// var_dump($siblingModules);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('POST data: ' . print_r($_POST, true));
    if (isset($_POST['action']) && $_POST['action'] === 'save') {
        $newContent = $_POST['content'];
        $newFrontmatter = [];
        
        // Validate required fields
        if (empty($_POST['frontmatter']['title'])) {
            $_SESSION['flash_message'] = 'Title is required';
            $_SESSION['flash_type'] = 'error';
            header('Location: edit_page.php?path=' . urlencode($pagePath));
            exit;
        }
        
        if (!isset($_POST['frontmatter']['order'])) {
            $_SESSION['flash_message'] = 'Order is required';
            $_SESSION['flash_type'] = 'error';
            header('Location: edit_page.php?path=' . urlencode($pagePath));
            exit;
        }
        
        // Handle path change if provided
        if (!empty($_POST['path'])) {
            // Clean and validate new path
            $newPath = trim($_POST['path'], '/');
            $pathParts = explode('/', $newPath);
            $pathParts = array_map(function($part) {
                return preg_replace('/[^a-z0-9-]/', '-', strtolower($part));
            }, $pathParts);
            
            // Add site name back to the path
            $cleanPath = $site . '/' . implode('/', $pathParts);
            
            // Add default.md if it's not already there
            if (strpos($cleanPath, '/default.md') === false) {
                $cleanPath .= '/default.md';
            }
            
            $newFullPath = ROOT_DIR . '/pages/' . $cleanPath;
            
            // If the new path is different from the current path
            if ($newFullPath !== $fullPath) {
                // Create new directory if it doesn't exist
                $newDir = dirname($newFullPath);
                if (!is_dir($newDir)) {
                    if (!mkdir($newDir, 0755, true)) {
                        $_SESSION['flash_message'] = 'Error creating new directory';
                        $_SESSION['flash_type'] = 'error';
                        header('Location: edit_page.php?path=' . urlencode($pagePath));
                        exit;
                    }
                }
                
                // If the target file exists, we'll update it instead of moving
                if (file_exists($newFullPath)) {
                    // Update the paths to point to the existing file
                    $fullPath = $newFullPath;
                    $pagePath = $cleanPath;
                } else {
                    // Move the file if it doesn't exist
                    if (!rename($fullPath, $newFullPath)) {
                        $_SESSION['flash_message'] = 'Error moving page to new location';
                        $_SESSION['flash_type'] = 'error';
                        header('Location: edit_page.php?path=' . urlencode($pagePath));
                        exit;
                    }
                    
                    // Update paths for modules if they exist
                    $oldModuleDir = dirname($fullPath);
                    $newModuleDir = dirname($newFullPath);
                    $moduleDirs = glob($oldModuleDir . '/_*', GLOB_ONLYDIR);
                    
                    foreach ($moduleDirs as $moduleDir) {
                        $moduleName = basename($moduleDir);
                        $newModulePath = $newModuleDir . '/' . $moduleName;
                        
                        if (!is_dir($newModulePath)) {
                            mkdir($newModulePath, 0755, true);
                        }
                        
                        $moduleFile = $moduleDir . '/default.md';
                        $newModuleFile = $newModulePath . '/default.md';
                        
                        if (file_exists($moduleFile)) {
                            rename($moduleFile, $newModuleFile);
                        }
                    }
                    
                    // Clean up old directory if it's empty
                    if (is_dir($oldModuleDir)) {
                        $files = glob($oldModuleDir . '/*');
                        if (empty($files)) {
                            rmdir($oldModuleDir);
                        }
                    }
                    
                    // Update the paths
                    $fullPath = $newFullPath;
                    $pagePath = $cleanPath;
                }
            }
        }
        
        // Process frontmatter fields
        foreach ($_POST['frontmatter'] as $key => $value) {
            if (is_array($value) && isset($value['key']) && isset($value['value'])) {
                // This is a custom field
                if (!empty($value['key']) && !empty($value['value'])) {
                    $newFrontmatter[$value['key']] = $value['value'];
                }
            } else {
                // This is a standard field
                if (!empty($value)) {
                    $newFrontmatter[$key] = $value;
                }
            }
        }
        
        // Handle sibling modules if they exist
        if (isset($_POST['modules']) && is_array($_POST['modules'])) {
            $pageDir = dirname($fullPath);

            
            
            foreach ($_POST['modules'] as $moduleDir => $moduleData) {
                // Skip if this is an inline module (numeric index)
                if (is_numeric($moduleDir)) {
                    continue;
                }
                
                // Process sibling module
                if (!empty($moduleData['content'])) {
                    $modulePath = $pageDir . '/' . $moduleDir . '/default.md';
                    $moduleDirPath = dirname($modulePath);
                    
                    // Create module directory if it doesn't exist
                    if (!is_dir($moduleDirPath)) {
                        mkdir($moduleDirPath, 0755, true);
                    }
                    
                    // Prepare module frontmatter
                    $moduleFrontmatter = [
                        'title' => $moduleData['title'] ?? basename($moduleDir),
                        'template' => $moduleData['template'] ?? 'default',
                        'type' => 'module',
                        'order' => $moduleData['order'] ?? 0
                    ];
                    
                    // Add custom fields for modules if they exist
                    if (isset($moduleData['frontmatter']) && is_array($moduleData['frontmatter'])) {
                        foreach ($moduleData['frontmatter'] as $fieldKey => $fieldData) {
                            if (isset($fieldData['key']) && isset($fieldData['value']) && !empty($fieldData['key'])) {
                                $moduleFrontmatter[$fieldData['key']] = $fieldData['value'];
                            }
                        }
                    }
                    
                    // Construct the module file content
                    $moduleContent = "---\n";
                    $moduleContent .= YamlParser::dump($moduleFrontmatter);
                    $moduleContent .= "---\n\n";
                    $moduleContent .= $moduleData['content'];
                    
                    // Save the module file
                    file_put_contents($modulePath, $moduleContent);
                }
            }
        }
        
        // Construct the new file content
        $fileContent = "---\n";
        $fileContent .= YamlParser::dump($newFrontmatter);
        $fileContent .= "---\n\n";
        $fileContent .= $newContent;
        

        // Now try to save the file
        if (file_put_contents($fullPath, $fileContent)) {
            $_SESSION['flash_message'] = 'Page saved successfully';
            $_SESSION['flash_type'] = 'success';
            header('Location: edit_page.php?path=' . urlencode($pagePath));
            exit;
        } else {
            $_SESSION['flash_message'] = 'Error saving page: ' . error_get_last()['message'];
            $_SESSION['flash_type'] = 'error';
            error_log("Error saving file: " . error_get_last()['message']);
        }
    }
}
?>

<div class="bg-white shadow rounded-lg p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Edit Page</h1>
        <div class="flex space-x-2">
            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i> Back to Pages
            </a>
            <button type="button" onclick="previewPage()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                <i class="fas fa-eye mr-2"></i> Preview
            </button>
            <button type="submit" form="edit-form" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                <i class="fas fa-save mr-2"></i> Save
            </button>
        </div>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $_SESSION['flash_type'] === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'; ?>">
            <?php 
            echo $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_type']);
            ?>
        </div>
    <?php endif; ?>

    <form id="edit-form" method="POST" class="space-y-6">
        <input type="hidden" name="action" value="save">
        
        <!-- Frontmatter Fields -->
        <div class="bg-gray-50 p-4 rounded-lg">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Page Settings</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="frontmatter[title]" class="block text-sm font-medium text-gray-700">Title</label>
                    <input type="text" name="frontmatter[title]" id="frontmatter[title]" 
                           value="<?php echo htmlspecialchars($frontmatter['title'] ?? ''); ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>

                <div>
                    <label for="frontmatter[order]" class="block text-sm font-medium text-gray-700">Order</label>
                    <input type="number" name="frontmatter[order]" id="frontmatter[order]" 
                           value="<?php echo htmlspecialchars($frontmatter['order'] ?? '0'); ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
            
                <div class="sm:col-span-2">
                    <label for="path" class="block text-sm font-medium text-gray-700">Page Path</label>
                    <div class="mt-1 flex rounded-md shadow-sm">
                        <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
                            <?php echo htmlspecialchars($site); ?>/
                        </span>
                        <input type="text" name="path" id="path" 
                               value="<?php 
                                    // Strip site name and default.md from the path
                                    $displayPath = $pagePath;
                                    if (strpos($displayPath, $site . '/') === 0) {
                                        $displayPath = substr($displayPath, strlen($site . '/'));
                                    }
                                    if (strpos($displayPath, '/default.md') !== false) {
                                        $displayPath = str_replace('/default.md', '', $displayPath);
                                    }
                                    echo htmlspecialchars($displayPath);
                               ?>"
                               class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-r-md border border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom Fields -->
        <div class="bg-gray-50 p-4 rounded-lg">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-medium text-gray-900">Custom Fields</h2>
                <button type="button" onclick="addCustomField()" class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    <i class="fas fa-plus mr-1"></i> Add Field
                </button>
            </div>
            <div id="custom-fields-container" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <?php
                // Get all frontmatter fields except the standard ones
                $standardFields = ['title', 'order', 'description', 'keywords', 'author', 'date', 'status', 'featured', 'modules'];
                $customFields = array_diff_key($frontmatter ?? [], array_flip($standardFields));
                
                foreach ($customFields as $key => $value): 
                ?>
                    <div class="custom-field-item">
                        <div class="flex items-start space-x-2">
                            <div class="flex-grow">
                                <label class="block text-sm font-medium text-gray-700">Field Name</label>
                                <input type="text" name="frontmatter[<?php echo htmlspecialchars($key); ?>][key]" 
                                       value="<?php echo htmlspecialchars($key); ?>"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div class="flex-grow">
                                <label class="block text-sm font-medium text-gray-700">Value</label>
                                <input type="text" name="frontmatter[<?php echo htmlspecialchars($key); ?>][value]" 
                                       value="<?php echo htmlspecialchars($value); ?>"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <button type="button" onclick="removeCustomField(this)" class="mt-6 text-red-600 hover:text-red-900">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="mb-6">
            <label for="main-content" class="block text-sm font-medium text-gray-700">Content</label>
            <textarea name="content" id="main-content" rows="10" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"><?php echo htmlspecialchars($markdownContent); ?></textarea>
        </div>

        <!-- Modules Section -->
        <div id="modules-section" class="mt-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-medium text-gray-900">Modules</h2>
                <div class="space-x-2">
                    <button type="button" onclick="addModule()" class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <i class="fas fa-plus mr-1"></i> Add Inline Module
                    </button>
                </div>
            </div>
            
            <!-- Sibling Modules -->
            <?php if (!empty($siblingModules)): ?>
            <div class="mb-6">
                <h3 class="text-md font-medium text-gray-900 mb-4">Sibling Modules</h3>
                <div class="space-y-4 w-full">
                    <?php foreach ($siblingModules as $module): ?>
                    <div class="module-item bg-gray-50 p-4 rounded-lg border border-gray-200 w-full">
                        <div class="flex justify-between items-start mb-4">
                            <div class="w-full">
                                <h4 class="text-md font-medium text-gray-900"><?php echo htmlspecialchars($module['title']); ?></h4>

                                <!-- Add title input -->
                                <input type="hidden" name="modules[<?php echo htmlspecialchars($module['directory']); ?>][title]" 
                                       value="<?php echo htmlspecialchars($module['title']); ?>">
                                
                                <!-- Add template input -->
                                <input type="hidden" name="modules[<?php echo htmlspecialchars($module['directory']); ?>][template]" 
                                       value="<?php echo htmlspecialchars($module['template']); ?>">
                                
                                <!-- Add order input -->
                                <input type="hidden" name="modules[<?php echo htmlspecialchars($module['directory']); ?>][order]" 
                                       value="<?php echo htmlspecialchars($module['order']); ?>">

                                <!-- Main Content -->
                                <div class="w-full">
                                    <label for="module-content-<?php echo htmlspecialchars($module['directory']); ?>" class="block text-sm font-medium text-gray-700">Content</label>
                                    <textarea name="modules[<?php echo htmlspecialchars($module['directory']); ?>][content]" 
                                              id="module-content-<?php echo htmlspecialchars($module['directory']); ?>" 
                                              rows="10" 
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"><?php echo htmlspecialchars($module['content']); ?></textarea>
                                </div>

                                <!-- Custom Fields Section -->
                                <div class="w-full mt-4">
                                    <div class="flex justify-between items-center mb-2">
                                        <h4 class="text-sm font-medium text-gray-700">Custom Fields</h4>
                                        <button type="button" onclick="addSiblingModuleCustomField(this)" class="inline-flex items-center px-2 py-1 border border-transparent text-xs font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                            <i class="fas fa-plus mr-1"></i> Add Field
                                        </button>
                                    </div>
                                    <div class="module-custom-fields-container space-y-2">
                                        <?php
                                        // Get all module frontmatter fields except the standard ones
                                        $moduleStandardFields = ['title', 'template', 'type', 'order', 'content', 'path', 'directory'];
                                        $moduleCustomFields = array_diff_key($module['frontmatter'] ?? [], array_flip($moduleStandardFields));
                                        
                                        foreach ($moduleCustomFields as $key => $value): 
                                        ?>
                                            <div class="module-custom-field-item bg-white p-2 rounded-lg border border-gray-200">
                                                <div class="flex items-start space-x-2">
                                                    <div class="flex-grow">
                                                        <label class="block text-xs font-medium text-gray-700">Field Name</label>
                                                        <input type="text" name="modules[<?php echo htmlspecialchars($module['directory']); ?>][frontmatter][<?php echo htmlspecialchars($key); ?>][key]" 
                                                               value="<?php echo htmlspecialchars($key); ?>"
                                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs">
                                                    </div>
                                                    <div class="flex-grow">
                                                        <label class="block text-xs font-medium text-gray-700">Value</label>
                                                        <input type="text" name="modules[<?php echo htmlspecialchars($module['directory']); ?>][frontmatter][<?php echo htmlspecialchars($key); ?>][value]" 
                                                               value="<?php echo htmlspecialchars($value); ?>"
                                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs">
                                                    </div>
                                                    <button type="button" onclick="removeModuleCustomField(this)" class="mt-6 text-red-600 hover:text-red-900">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <p class="text-sm text-gray-500">Directory: _<?php echo htmlspecialchars($module['directory']); ?></p>
                            </div>
                            <div class="flex space-x-2">
                                <a href="edit_page.php?path=<?php echo urlencode($module['path']); ?>" 
                                   class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-indigo-700 bg-indigo-100 hover:bg-indigo-200">
                                    <i class="fas fa-edit mr-1"></i> Edit
                                </a>
                                <button type="button" onclick="deleteModule('<?php echo htmlspecialchars($module['directory']); ?>')" 
                                        class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-red-700 bg-red-100 hover:bg-red-200">
                                    <i class="fas fa-trash mr-1"></i> Delete
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-sm w-full">
                            <div>
                                <span class="font-medium">Template:</span> <?php echo htmlspecialchars($module['template']); ?>
                            </div>
                            <div>
                                <span class="font-medium">Order:</span> <?php echo htmlspecialchars($module['order']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Inline Modules -->
            <div id="modules-container" class="space-y-4 w-full">
                <?php 
                if (isset($frontmatter['modules']) && is_array($frontmatter['modules'])) {
                    foreach ($frontmatter['modules'] as $index => $module): 
                ?>
                    <div class="module-item bg-gray-50 p-4 rounded-lg w-full">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-md font-medium text-gray-900">Module <?php echo $index + 1; ?></h3>
                            <button type="button" onclick="removeModule(this)" class="text-red-600 hover:text-red-900">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div class="grid grid-cols-1 gap-4 w-full">
                            <div class="w-full">
                                <label class="block text-sm font-medium text-gray-700">Title</label>
                                <input type="text" name="modules[<?php echo $index; ?>][title]" 
                                       value="<?php echo htmlspecialchars($module['title']); ?>"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div class="w-full">
                                <label class="block text-sm font-medium text-gray-700">Template</label>
                                <input type="text" name="modules[<?php echo $index; ?>][template]" 
                                       value="<?php echo htmlspecialchars($module['template'] ?? 'default'); ?>"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div class="w-full">
                                <label class="block text-sm font-medium text-gray-700">Order</label>
                                <input type="number" name="modules[<?php echo $index; ?>][order]" 
                                       value="<?php echo htmlspecialchars($module['order'] ?? '0'); ?>"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div class="w-full">
                                <label class="block text-sm font-medium text-gray-700">Description</label>
                                <textarea name="modules[<?php echo $index; ?>][description]" 
                                          rows="2"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"><?php echo htmlspecialchars($module['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="w-full">
                                <label class="block text-sm font-medium text-gray-700">Status</label>
                                <select name="modules[<?php echo $index; ?>][status]" 
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="active" <?php echo ($module['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($module['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="w-full">
                                <label for="inline-module-content-<?php echo $index; ?>" class="block text-sm font-medium text-gray-700">Content</label>
                                <textarea name="modules[<?php echo $index; ?>][content]" 
                                          id="inline-module-content-<?php echo $index; ?>" 
                                          rows="4"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"><?php echo htmlspecialchars($module['content']); ?></textarea>
                            </div>
                            <div class="w-full">
                                <div class="flex justify-between items-center mb-2">
                                    <h4 class="text-sm font-medium text-gray-700">Custom Fields</h4>
                                    <button type="button" onclick="addModuleCustomField(this)" class="inline-flex items-center px-2 py-1 border border-transparent text-xs font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                        <i class="fas fa-plus mr-1"></i> Add Field
                                    </button>
                                </div>
                                <div class="module-custom-fields-container space-y-2">
                                    <?php
                                    // Get all module frontmatter fields except the standard ones
                                    $moduleStandardFields = ['title', 'template', 'type', 'order', 'content', 'path', 'directory'];
                                    $moduleCustomFields = array_diff_key($module['frontmatter'] ?? [], array_flip($moduleStandardFields));
                                    
                                    foreach ($moduleCustomFields as $key => $value): 
                                    ?>
                                        <div class="module-custom-field-item bg-white p-2 rounded-lg border border-gray-200">
                                            <div class="flex items-start space-x-2">
                                                <div class="flex-grow">
                                                    <label class="block text-xs font-medium text-gray-700">Field Name</label>
                                                    <input type="text" name="modules[<?php echo $index; ?>][frontmatter][<?php echo htmlspecialchars($key); ?>][key]" 
                                                           value="<?php echo htmlspecialchars($key); ?>"
                                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs">
                                                </div>
                                                <div class="flex-grow">
                                                    <label class="block text-xs font-medium text-gray-700">Value</label>
                                                    <input type="text" name="modules[<?php echo $index; ?>][frontmatter][<?php echo htmlspecialchars($key); ?>][value]" 
                                                           value="<?php echo htmlspecialchars($value); ?>"
                                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs">
                                                </div>
                                                <button type="button" onclick="removeModuleCustomField(this)" class="mt-6 text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php 
                    endforeach;
                }
                ?>
            </div>
        </div>
    </form>
</div>

<!-- Preview Modal -->
<div id="preview-modal" class="hidden fixed z-10 inset-0 overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Page Preview</h3>
                    <button type="button" onclick="hidePreviewModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="preview-content" class="prose max-w-none"></div>
            </div>
        </div>
    </div>
</div>

<!-- Include EasyMDE -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
<script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<script>
// First declare the editors object
let editors = {};

// Then define the cleanup function
function cleanupEditors() {
    Object.values(editors).forEach(editor => {
        if (editor) {
            editor.toTextArea();
        }
    });
    editors = {};
}

// Then the initialization function
function initializeEditors() {
    cleanupEditors();
    document.querySelectorAll('textarea[name*="content"]').forEach(textarea => {
        // Get the current page path from the URL
        const urlParams = new URLSearchParams(window.location.search);
        const pagePath = urlParams.get('path');
        
        const editor = new EasyMDE({
            element: textarea,
            spellChecker: false,
            status: ['lines', 'words', 'cursor'],
            toolbar: [
                'bold', 'italic', 'heading', '|',
                'quote', 'unordered-list', 'ordered-list', '|',
                'link', 'image', 'table', '|',
                'preview', 'side-by-side', 'fullscreen', '|',
                'guide'
            ],
            autofocus: false,
            autosave: {
                enabled: true,
                // Make the uniqueId include the page path
                uniqueId: `smde_${pagePath}_${textarea.id}`,
                delay: 1000,
            },
            initialValue: textarea.value
        });
        editors[textarea.id] = editor;
    });
}

// Call initializeEditors when the page loads
document.addEventListener('DOMContentLoaded', initializeEditors);

// Update the preview functionality
function previewPage() {
    const mainEditor = editors['main-content'];
    if (mainEditor) {
        const content = mainEditor.value();
        const previewContent = document.getElementById('preview-content');
        previewContent.innerHTML = marked.parse(content);
        document.getElementById('preview-modal').classList.remove('hidden');
    }
}

function hidePreviewModal() {
    document.getElementById('preview-modal').classList.add('hidden');
}

// Handle keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + S to save
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.querySelector('form').submit();
    }
    // Esc to close preview
    if (e.key === 'Escape' && !document.getElementById('preview-modal').classList.contains('hidden')) {
        hidePreviewModal();
    }
});

// Module handling functions
function toggleModuleFields(type) {
    const modulesSection = document.getElementById('modules-section');
    modulesSection.classList.toggle('hidden', type !== 'module');
}

function addModule() {
    const container = document.getElementById('modules-container');
    const moduleCount = container.children.length;
    const moduleId = `module-${moduleCount}`;
    
    const moduleHtml = `        <div class="module-item bg-gray-50 p-4 rounded-lg">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-md font-medium text-gray-900">Module ${moduleCount + 1}</h3>
                <button type="button" onclick="removeModule(this)" class="text-red-600 hover:text-red-900">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Title</label>
                    <input type="text" name="modules[${moduleCount}][title]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Template</label>
                    <input type="text" name="modules[${moduleCount}][template]" 
                           value="default"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Order</label>
                    <input type="number" name="modules[${moduleCount}][order]" 
                           value="0"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="modules[${moduleCount}][description]" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="modules[${moduleCount}][status]" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div>
                    <label for="inline-module-content-${moduleCount}" class="block text-sm font-medium text-gray-700">Content</label>
                    <textarea id="${moduleId}" name="modules[${moduleCount}][content]" rows="4"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                </div>
                <div class="w-full">
                    <div class="flex justify-between items-center mb-2">
                        <h4 class="text-sm font-medium text-gray-700">Custom Fields</h4>
                        <button type="button" onclick="addModuleCustomField(this)" class="inline-flex items-center px-2 py-1 border border-transparent text-xs font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            <i class="fas fa-plus mr-1"></i> Add Field
                        </button>
                    </div>
                    <div class="module-custom-fields-container space-y-2">
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', moduleHtml);
    
    // Initialize EasyMDE for the new module's textarea
    const newTextarea = document.getElementById(moduleId);
    if (newTextarea) {
        editors[moduleId] = new EasyMDE({
            element: newTextarea,
            spellChecker: false,
            status: ['lines', 'words', 'cursor'],
            toolbar: [
                'bold', 'italic', 'heading', '|',
                'quote', 'unordered-list', 'ordered-list', '|',
                'link', 'image', 'table', '|',
                'preview', 'side-by-side', 'fullscreen', '|',
                'guide'
            ],
            autofocus: false
        });
    }
}

function removeModule(button) {
    if (confirm('Are you sure you want to remove this module?')) {
        const moduleItem = button.closest('.module-item');
        const textarea = moduleItem.querySelector('textarea');
        if (textarea && editors[textarea.id]) {
            editors[textarea.id].toTextArea();
            delete editors[textarea.id];
        }
        moduleItem.remove();
        // Renumber remaining modules
        const modules = document.querySelectorAll('.module-item');
        modules.forEach((module, index) => {
            module.querySelector('h3').textContent = `Module ${index + 1}`;
        });
    }
}

function addSiblingModuleCustomField(button) {
    let container = button.closest('.module-custom-fields-container');
    
    // If container doesn't exist, create it
    if (!container) {
        const moduleItem = button.closest('.module-item');
        if (!moduleItem) {
            console.error('Could not find module-item');
            return;
        }
        
        // Create the container
        container = document.createElement('div');
        container.className = 'module-custom-fields-container space-y-2';
        
        // Insert it after the button's parent div
        const buttonParent = button.parentElement;
        buttonParent.insertAdjacentElement('afterend', container);
    }

    const moduleItem = container.closest('.module-item');
    if (!moduleItem) {
        console.error('Could not find module-item');
        return;
    }

    // Find the module directory from the hidden input that contains the directory name
    const directoryText = moduleItem.querySelector('p.text-sm.text-gray-500');
    let moduleDirectory = 'new_field';
    
    if (directoryText) {
        // Extract directory name from text like "Directory: _module_name"
        const match = directoryText.textContent.match(/Directory: _([^\s]+)/);
        if (match && match[1]) {
            moduleDirectory = match[1];
        }
    }

    // Generate a unique field name
    const fieldCount = container.children.length;
    const uniqueFieldName = `custom_field_${fieldCount}`;

    const fieldHtml = `
        <div class="module-custom-field-item bg-white p-2 rounded-lg border border-gray-200">
            <div class="flex items-start space-x-2">
                <div class="flex-grow">
                    <label class="block text-xs font-medium text-gray-700">Field Name</label>
                    <input type="text" name="modules[${moduleDirectory}][frontmatter][${uniqueFieldName}][key]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs">
                </div>
                <div class="flex-grow">
                    <label class="block text-xs font-medium text-gray-700">Value</label>
                    <input type="text" name="modules[${moduleDirectory}][frontmatter][${uniqueFieldName}][value]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs">
                </div>
                <button type="button" onclick="removeModuleCustomField(this)" class="mt-6 text-red-600 hover:text-red-900">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', fieldHtml);
}

function addCustomField() {
    const container = document.getElementById('custom-fields-container');
    const fieldCount = container.children.length;
    
    const fieldHtml = `
        <div class="custom-field-item">
            <div class="flex items-start space-x-2">
                <div class="flex-grow">
                    <label class="block text-sm font-medium text-gray-700">Field Name</label>
                    <input type="text" name="frontmatter[new_field_${fieldCount}][key]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div class="flex-grow">
                    <label class="block text-sm font-medium text-gray-700">Value</label>
                    <input type="text" name="frontmatter[new_field_${fieldCount}][value]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <button type="button" onclick="removeCustomField(this)" class="mt-6 text-red-600 hover:text-red-900">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', fieldHtml);
}

function removeCustomField(button) {
    const fieldItem = button.closest('.custom-field-item');
    fieldItem.remove();
}
</script>
</body>
</html>
