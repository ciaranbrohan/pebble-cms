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
require_once __DIR__ . '/../includes/header.php';

// Add this after the require statements at the top of the file
function getPagesFromDirectory($baseDir) {
    $pages = [];
    $dirs = ['main', 'iom', 'default'];
    
    foreach ($dirs as $dir) {
        $path = $baseDir . '/' . $dir;
        if (!is_dir($path)) continue;
        
        // Get all subdirectories (which represent page groups)
        $pageGroups = array_filter(glob($path . '/*'), 'is_dir');
        foreach ($pageGroups as $group) {
            // Get all .md files in the group directory
            $mdFiles = glob($group . '/*.md');
            foreach ($mdFiles as $file) {
                $content = file_get_contents($file);
                // Parse YAML frontmatter
                if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)/s', $content, $matches)) {
                    $frontmatter = YamlParser::parse($matches[1]);
                    $pages[] = [
                        'title' => $frontmatter['title'] ?? basename($file, '.md'),
                        'path' => str_replace($baseDir . '/', '', $file),
                        'template' => $frontmatter['template'] ?? 'default',
                        'group' => $dir,
                        'slug' => basename(dirname($file))
                    ];
                }
            }
        }
    }
    return $pages;
}

// Get all pages
$pages = getPagesFromDirectory(ROOT_DIR . '/pages');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_page':
                // TODO: Add page creation logic
                $_SESSION['flash_message'] = 'Page created successfully';
                $_SESSION['flash_type'] = 'success';
                break;
            case 'add_site':
                // TODO: Add site creation logic
                $_SESSION['flash_message'] = 'Site created successfully';
                $_SESSION['flash_type'] = 'success';
                break;
        }
        header('Location: pages.php');
        exit;
    }
}
?>

<div class="bg-white shadow rounded-lg p-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Manage Pages & Sites</h1>
    
    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8">
            
            <?php
            // Get directories from pages folder
            $pagesDir = ROOT_DIR . '/pages';

            $dirs = array_filter(glob($pagesDir . '/*'), 'is_dir');

            $dirs = array_map(function($dir) {
                return basename($dir);
            }, $dirs);
            sort($dirs); // Sort directories alphabetically
            
            foreach ($dirs as $index => $dir) {
                $isActive = $index === 0 ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300';
                echo '<button class="' . $isActive . ' whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" id="' . $dir . '-tab">';
                echo ucfirst($dir);
                echo '</button>';
            }
            ?>
        </nav>
    </div>

    <!-- Content Sections -->
    <?php foreach ($dirs as $index => $dir): ?>
    <div id="<?php echo $dir; ?>-section" class="space-y-6 <?php echo $index === 0 ? '' : 'hidden'; ?>">
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-900"><?php echo ucfirst($dir); ?> Pages</h2>
            <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700" onclick="showAddPageModal('<?php echo $dir; ?>')">
                <i class="fas fa-plus mr-2"></i> Add New <?php echo ucfirst($dir); ?> Page
            </button>
        </div>

        <!-- Pages List -->
        <div class="bg-white shadow overflow-hidden sm:rounded-md">
            <ul class="divide-y divide-gray-200">
                <?php 
                $dirPages = array_filter($pages, function($page) use ($dir) {
                    return $page['group'] === $dir;
                });
                if (empty($dirPages)): 
                ?>
                    <li class="px-6 py-4">
                        <div class="text-sm text-gray-500">No pages found in <?php echo ucfirst($dir); ?></div>
                    </li>
                <?php else: ?>
                    <?php foreach ($dirPages as $page): ?>
                        <li class="px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($page['title']); ?></div>
                                        <div class="text-sm text-gray-500">
                                            /<?php echo htmlspecialchars($page['slug']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <a href="edit_page.php?path=<?php echo urlencode($page['path']); ?>" class="text-indigo-600 hover:text-indigo-900">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="deletePage('<?php echo htmlspecialchars($page['path']); ?>')" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Sites Section -->
    <div id="sites-section" class="hidden space-y-6">
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-900">Sites</h2>
            <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700" onclick="showAddSiteModal()">
                <i class="fas fa-plus mr-2"></i> Add New Site
            </button>
        </div>

        <!-- Sites List -->
        <div class="bg-white shadow overflow-hidden sm:rounded-md">
            <ul class="divide-y divide-gray-200">
                <!-- TODO: Add dynamic site list -->
                <li class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900">Example Site</div>
                                <div class="text-sm text-gray-500">example.com</div>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button class="text-indigo-600 hover:text-indigo-900">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="text-red-600 hover:text-red-900">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- Add Page Modal -->
<div id="add-page-modal" class="hidden fixed z-10 inset-0 overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form action="pages.php" method="POST">
                <input type="hidden" name="action" value="add_page">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Page</h3>
                    <div class="space-y-4">
                        <div>
                            <label for="page-title" class="block text-sm font-medium text-gray-700">Page Title</label>
                            <input type="text" name="title" id="page-title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                        </div>
                        <div>
                            <label for="page-slug" class="block text-sm font-medium text-gray-700">URL Slug</label>
                            <input type="text" name="slug" id="page-slug" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                        </div>
                        <div>
                            <label for="page-content" class="block text-sm font-medium text-gray-700">Content</label>
                            <textarea name="content" id="page-content" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Create Page
                    </button>
                    <button type="button" onclick="hideAddPageModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Site Modal -->
<div id="add-site-modal" class="hidden fixed z-10 inset-0 overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form action="pages.php" method="POST">
                <input type="hidden" name="action" value="add_site">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Site</h3>
                    <div class="space-y-4">
                        <div>
                            <label for="site-name" class="block text-sm font-medium text-gray-700">Site Name</label>
                            <input type="text" name="name" id="site-name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                        </div>
                        <div>
                            <label for="site-domain" class="block text-sm font-medium text-gray-700">Domain</label>
                            <input type="text" name="domain" id="site-domain" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Create Site
                    </button>
                    <button type="button" onclick="hideAddSiteModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Tab switching
<?php foreach ($dirs as $dir): ?>
document.getElementById('<?php echo $dir; ?>-tab').addEventListener('click', () => {
    <?php foreach ($dirs as $otherDir): ?>
    document.getElementById('<?php echo $otherDir; ?>-section').classList.add('hidden');
    document.getElementById('<?php echo $otherDir; ?>-tab').classList.remove('border-indigo-500', 'text-indigo-600');
    document.getElementById('<?php echo $otherDir; ?>-tab').classList.add('border-transparent', 'text-gray-500');
    <?php endforeach; ?>
    
    document.getElementById('<?php echo $dir; ?>-section').classList.remove('hidden');
    document.getElementById('<?php echo $dir; ?>-tab').classList.add('border-indigo-500', 'text-indigo-600');
    document.getElementById('<?php echo $dir; ?>-tab').classList.remove('border-transparent', 'text-gray-500');
});
<?php endforeach; ?>

// Modal functions
function showAddPageModal(dir) {
    document.getElementById('add-page-modal').classList.remove('hidden');
    // Add a hidden input to store the directory
    let dirInput = document.createElement('input');
    dirInput.type = 'hidden';
    dirInput.name = 'directory';
    dirInput.value = dir;
    document.querySelector('#add-page-modal form').appendChild(dirInput);
}

function hideAddPageModal() {
    document.getElementById('add-page-modal').classList.add('hidden');
}

function showAddSiteModal() {
    document.getElementById('add-site-modal').classList.remove('hidden');
}

function hideAddSiteModal() {
    document.getElementById('add-site-modal').classList.add('hidden');
}

function deletePage(path) {
    if (confirm('Are you sure you want to delete this page?')) {
        fetch('delete_page.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'path=' + encodeURIComponent(path)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error deleting page: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the page');
        });
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?> 