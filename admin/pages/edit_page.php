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
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save') {
        $newContent = $_POST['content'];
        $newFrontmatter = [];
        
        // Process frontmatter fields
        foreach ($_POST['frontmatter'] as $key => $value) {
            if (!empty($value)) {
                $newFrontmatter[$key] = $value;
            }
        }
        
        // Construct the new file content
        $fileContent = "---\n";
        $fileContent .= YamlParser::dump($newFrontmatter);
        $fileContent .= "---\n\n";
        $fileContent .= $newContent;
        
        // Save the file
        if (file_put_contents($fullPath, $fileContent)) {
            $_SESSION['flash_message'] = 'Page saved successfully';
            $_SESSION['flash_type'] = 'success';
            header('Location: edit_page.php?path=' . urlencode($pagePath));
            exit;
        } else {
            $_SESSION['flash_message'] = 'Error saving page';
            $_SESSION['flash_type'] = 'error';
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

    <form method="POST" class="space-y-6">
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
                    <label for="frontmatter[template]" class="block text-sm font-medium text-gray-700">Template</label>
                    <input type="text" name="frontmatter[template]" id="frontmatter[template]" 
                           value="<?php echo htmlspecialchars($frontmatter['template'] ?? 'default'); ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="frontmatter[menu]" class="block text-sm font-medium text-gray-700">Menu Label</label>
                    <input type="text" name="frontmatter[menu]" id="frontmatter[menu]" 
                           value="<?php echo htmlspecialchars($frontmatter['menu'] ?? ''); ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="frontmatter[order]" class="block text-sm font-medium text-gray-700">Order</label>
                    <input type="number" name="frontmatter[order]" id="frontmatter[order]" 
                           value="<?php echo htmlspecialchars($frontmatter['order'] ?? '9999'); ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
            </div>
        </div>

        <!-- Markdown Editor -->
        <div>
            <label for="content" class="block text-sm font-medium text-gray-700 mb-2">Content</label>
            <textarea name="content" id="content" class="hidden"><?php echo htmlspecialchars($markdownContent); ?></textarea>
        </div>

        <div class="flex justify-end space-x-2">
            <button type="button" onclick="window.location.href='index.php'" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Cancel
            </button>
            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                <i class="fas fa-save mr-2"></i> Save Changes
            </button>
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
// Initialize EasyMDE
const easyMDE = new EasyMDE({
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
    autofocus: true,
    autosave: {
        enabled: true,
        uniqueId: '<?php echo md5($pagePath); ?>',
        delay: 1000,
    }
});

// Preview functionality
function previewPage() {
    const content = easyMDE.value();
    const previewContent = document.getElementById('preview-content');
    previewContent.innerHTML = marked.parse(content);
    document.getElementById('preview-modal').classList.remove('hidden');
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?> 