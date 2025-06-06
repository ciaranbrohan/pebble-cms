<?php
require_once __DIR__ . '/../includes/security.php';
secure_file();

ini_set('error_log', '/var/www/cms.t45jiujitsu.ie/html/php_errors.log');
ini_set('log_errors', 1);
error_reporting(E_ALL);

error_log("Test log message - " . date('Y-m-d H:i:s'));

// Define required constants
define('ROOT_DIR', dirname(dirname(__DIR__)));
define('CONFIG_DIR', ROOT_DIR . '/config');

require_once __DIR__ . '/../../core/ApiAuth.php';
require_once __DIR__ . '/../../core/YamlParser.php';
require_once __DIR__ . '/../includes/header.php';

// Add these lines after the header include
?>
<!-- Add EasyMDE CSS and JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
<script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>

<?php
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $title = $_POST['title'] ?? '';
        $path = $_POST['path'] ?? '';
        $type = $_POST['type'] ?? 'page';
        $order = (int)($_POST['order'] ?? 0);
        $directory = $_POST['directory'] ?? 'default';
        
        if (empty($title)) {
            $_SESSION['flash_message'] = 'Title is required';
            $_SESSION['flash_type'] = 'error';
            header('Location: index.php');
            exit;
        }
        
        // Clean and validate path
        $path = trim($path, '/');
        $pathParts = explode('/', $path);
        $pathParts = array_map(function($part) {
            return preg_replace('/[^a-z0-9-]/', '-', strtolower($part));
        }, $pathParts);
        
        // Remove order prefix formatting
        $cleanPath = implode('/', $pathParts);
        
        // Construct the full path
        $fullPath = ROOT_DIR . '/pages/' . $directory;
        if (!empty($cleanPath)) {
            $fullPath .= '/' . $cleanPath;
        }
        $fullPath .= '/default.md';
        
        // Create directory if it doesn't exist
        $dirPath = dirname($fullPath);
        
        // Debug information
        $debug_info = [
            'ROOT_DIR' => ROOT_DIR,
            'Directory from form' => $directory,
            'Clean path' => $cleanPath,
            'Full path' => $fullPath,
            'Directory to create' => $dirPath,
            'Directory exists' => is_dir($dirPath) ? 'yes' : 'no',
            'Parent directory writable' => is_writable(dirname($dirPath)) ? 'yes' : 'no',
            'Parent directory permissions' => substr(sprintf('%o', fileperms(dirname($dirPath))), -4),
            'Web server user' => get_current_user()
        ];
        
        // Temporarily display debug info
        echo "<pre>Debug Information:\n";
        print_r($debug_info);
        echo "</pre>";
        
        if (!is_dir($dirPath)) {
            $mkdirResult = mkdir($dirPath, 0755, true);
            $debug_info['mkdir_result'] = $mkdirResult ? 'success' : 'failed';
            if (!$mkdirResult) {
                $debug_info['mkdir_error'] = error_get_last()['message'] ?? 'Unknown error';
                echo "<pre>Directory Creation Failed:\n";
                print_r($debug_info);
                echo "</pre>";
                $_SESSION['flash_message'] = 'Error creating directory';
                $_SESSION['flash_type'] = 'error';
                header('Location: index.php');
                exit;
            }
        }
        
        // Prepare frontmatter
        $frontmatter = [
            'title' => $title,
            'order' => $order
        ];
        
        // Add custom fields if they exist
        if (isset($_POST['frontmatter']) && is_array($_POST['frontmatter'])) {
            foreach ($_POST['frontmatter'] as $field) {
                if (!empty($field['key']) && isset($field['value'])) {
                    $frontmatter[$field['key']] = $field['value'];
                }
            }
        }
        
        // Add modules if this is a modular page
        if (isset($_POST['modules']) && is_array($_POST['modules'])) {
            $modules = [];
            foreach ($_POST['modules'] as $module) {
                if (!empty($module['title']) && !empty($module['content'])) {
                    // Create a sanitized directory name from the title
                    $moduleDirName = '_' . preg_replace('/[^a-z0-9]+/', '-', strtolower($module['title']));
                    $moduleDir = $dirPath . '/' . $moduleDirName;
                    $moduleFile = $moduleDir . '/default.md';
                    
                    // Create module directory if it doesn't exist
                    if (!is_dir($moduleDir)) {
                        mkdir($moduleDir, 0755, true);
                    }
                    
                    // Prepare module frontmatter
                    $moduleFrontmatter = [
                        'title' => $module['title'],
                        'template' => $module['template'] ?? 'default',
                        'type' => 'module',
                        'order' => $module['order'] ?? 0
                    ];
                    
                    // Add custom fields for modules if they exist
                    if (isset($module['frontmatter']) && is_array($module['frontmatter'])) {
                        foreach ($module['frontmatter'] as $field) {
                            if (!empty($field['key']) && isset($field['value'])) {
                                $moduleFrontmatter[$field['key']] = $field['value'];
                            }
                        }
                    }
                    
                    // Construct the module file content
                    $moduleContent = "---\n";
                    $moduleContent .= YamlParser::dump($moduleFrontmatter);
                    $moduleContent .= "---\n\n";
                    $moduleContent .= $module['content'];
                    
                    // Save the module file
                    if (file_put_contents($moduleFile, $moduleContent)) {
                        $modules[] = [
                            'title' => $module['title'],
                            'content' => $module['content'],
                            'template' => $module['template'] ?? 'default',
                            'order' => $module['order'] ?? 0,
                            'directory' => $moduleDirName
                        ];
                    }
                }
            }
            
            if (!empty($modules)) {
                $frontmatter['modules'] = $modules;
            }
        }
        
        // Construct the file content
        $fileContent = "---\n";
        $fileContent .= YamlParser::dump($frontmatter);
        $fileContent .= "---\n\n";
        $fileContent .= $_POST['content'] ?? '';
        
        // Save the file
        if (file_put_contents($fullPath, $fileContent)) {
            $_SESSION['flash_message'] = 'Page created successfully';
            $_SESSION['flash_type'] = 'success';
            header('Location: edit_page.php?path=' . urlencode(str_replace(ROOT_DIR . '/pages/', '', $fullPath)));
            exit;
        } else {
            $_SESSION['flash_message'] = 'Error creating page';
            $_SESSION['flash_type'] = 'error';
        }
    }
}

// Get available templates
$templates = glob(ROOT_DIR . '/themes/*/templates/*.php');
$templates = array_map(function($template) {
    return basename($template, '.php');
}, $templates);
?>

<div class="bg-white shadow rounded-lg p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Create New Page</h1>
    </div>

    <form method="POST" class="space-y-6">
        <input type="hidden" name="action" value="create">
        
        <!-- Basic Settings -->
        <div class="bg-gray-50 p-4 rounded-lg">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Page Settings</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                    <input type="text" name="title" id="title" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="order" class="block text-sm font-medium text-gray-700">Order</label>
                    <input type="number" name="order" id="order" value="0"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="site" class="block text-sm font-medium text-gray-700">Site</label>
                    <select name="site" id="site" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <?php
                        $dirs = array_filter(glob(ROOT_DIR . '/pages/*'), 'is_dir');
                        foreach ($dirs as $dir) {
                            $dirName = basename($dir);
                            echo '<option value="' . htmlspecialchars($dirName) . '">' . 
                                 htmlspecialchars(ucfirst($dirName)) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label for="path" class="block text-sm font-medium text-gray-700">Path</label>
                    <div class="mt-1 flex rounded-md shadow-sm">
                        <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
                            /
                        </span>
                        <input type="text" name="path" id="path" 
                               class="flex-1 block w-full rounded-none rounded-r-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                               placeholder="path/to/page">
                    </div>
                    <p class="mt-1 text-sm text-gray-500">Leave empty for root level, or enter a path like "about/team"</p>
                </div>
            </div>
        </div>

        <!-- Custom Fields Section -->
        <div class="bg-gray-50 p-4 rounded-lg mt-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-medium text-gray-900">Custom Fields</h2>
                <button type="button" onclick="addCustomField()" class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-plus mr-1"></i> Add Custom Field
                </button>
            </div>
            
            <div id="custom-fields-container" class="space-y-4">
                <!-- Custom fields will be added here dynamically -->
            </div>
        </div>

        <!-- Main Content -->
        <div>
            <label for="content" class="block text-sm font-medium text-gray-700">Content</label>
            <textarea name="content" id="content" rows="10"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
        </div>

        <!-- Modules Section (hidden by default) -->
        <div id="modules-section">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-medium text-gray-900">Modules</h2>
                <button type="button" onclick="addModule()" class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-plus mr-1"></i> Add Module
                </button>
            </div>
            
            <div id="modules-container" class="space-y-4">
                <!-- Modules will be added here dynamically -->
            </div>
        </div>

        <div class="flex justify-end space-x-2">
            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Cancel
            </a>
            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                <i class="fas fa-save mr-2"></i> Create Page
            </button>
        </div>
    </form>
</div>

<script>
// Initialize EasyMDE for the main content
const mainEditor = new EasyMDE({
    element: document.getElementById('content'),
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

// Update the addModule function to initialize EasyMDE for new modules
function addModule() {
    const container = document.getElementById('modules-container');
    const moduleCount = container.children.length;
    const moduleId = `module-content-${moduleCount}`;
    
    const moduleHtml = `
        <div class="module-item bg-gray-50 p-4 rounded-lg">
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
                    <select name="modules[${moduleCount}][template]" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <?php foreach ($templates as $template): ?>
                            <option value="<?php echo htmlspecialchars($template); ?>"
                                    <?php echo $template === 'default' ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($template); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Order</label>
                    <input type="number" name="modules[${moduleCount}][order]" 
                           value="0"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="${moduleId}" class="block text-sm font-medium text-gray-700">Content</label>
                    <textarea id="${moduleId}" name="modules[${moduleCount}][content]" rows="4"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                </div>
                <div class="module-custom-fields">
                    <div class="flex justify-between items-center mb-2">
                        <h4 class="text-sm font-medium text-gray-700">Custom Fields</h4>
                        <button type="button" onclick="addModuleCustomField(this)" class="inline-flex items-center px-2 py-1 border border-transparent text-xs font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            <i class="fas fa-plus mr-1"></i> Add Field
                        </button>
                    </div>
                    <div class="module-custom-fields-container space-y-2">
                        <!-- Custom fields will be added here dynamically -->
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', moduleHtml);
    
    // Initialize EasyMDE for the new module's textarea
    const newTextarea = document.getElementById(moduleId);
    if (newTextarea) {
        new EasyMDE({
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

// Update the removeModule function to clean up EasyMDE instances
function removeModule(button) {
    if (confirm('Are you sure you want to remove this module?')) {
        const moduleItem = button.closest('.module-item');
        const textarea = moduleItem.querySelector('textarea');
        if (textarea && textarea.easymde) {
            textarea.easymde.toTextArea();
        }
        moduleItem.remove();
        // Renumber remaining modules
        const modules = document.querySelectorAll('.module-item');
        modules.forEach((module, index) => {
            module.querySelector('h3').textContent = `Module ${index + 1}`;
            module.querySelectorAll('[name^="modules["]').forEach(input => {
                input.name = input.name.replace(/modules\[\d+\]/, `modules[${index}]`);
            });
        });
    }
}

// Add custom field functionality
function addCustomField() {
    const container = document.getElementById('custom-fields-container');
    const fieldCount = container.children.length;
    
    const fieldHtml = `
        <div class="custom-field-item bg-white p-4 rounded-lg border border-gray-200">
            <div class="flex items-start space-x-2">
                <div class="flex-grow">
                    <label class="block text-sm font-medium text-gray-700">Field Name</label>
                    <input type="text" name="frontmatter[custom_${fieldCount}][key]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Enter field name">
                </div>
                <div class="flex-grow">
                    <label class="block text-sm font-medium text-gray-700">Value</label>
                    <input type="text" name="frontmatter[custom_${fieldCount}][value]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Enter field value">
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
    if (confirm('Are you sure you want to remove this custom field?')) {
        button.closest('.custom-field-item').remove();
    }
}

// Add these new functions at the end of the script section
function addModuleCustomField(button) {
    const container = button.closest('.module-custom-fields').querySelector('.module-custom-fields-container');
    const moduleItem = button.closest('.module-item');
    const moduleIndex = Array.from(document.querySelectorAll('.module-item')).indexOf(moduleItem);
    const fieldCount = container.children.length;
    
    const fieldHtml = `
        <div class="module-custom-field-item bg-white p-2 rounded-lg border border-gray-200">
            <div class="flex items-start space-x-2">
                <div class="flex-grow">
                    <label class="block text-xs font-medium text-gray-700">Field Name</label>
                    <input type="text" name="modules[${moduleIndex}][frontmatter][${fieldCount}][key]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs"
                           placeholder="Enter field name">
                </div>
                <div class="flex-grow">
                    <label class="block text-xs font-medium text-gray-700">Value</label>
                    <input type="text" name="modules[${moduleIndex}][frontmatter][${fieldCount}][value]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs"
                           placeholder="Enter field value">
                </div>
                <button type="button" onclick="removeModuleCustomField(this)" class="mt-6 text-red-600 hover:text-red-900">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', fieldHtml);
}

function removeModuleCustomField(button) {
    if (confirm('Are you sure you want to remove this custom field?')) {
        button.closest('.module-custom-field-item').remove();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?> 