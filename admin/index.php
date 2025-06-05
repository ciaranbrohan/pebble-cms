<?php
require_once __DIR__ . '/includes/security.php';
secure_file();

// Define required constants
define('ROOT_DIR', dirname(__DIR__));
define('CONFIG_DIR', ROOT_DIR . '/config');

require_once __DIR__ . '/../core/ApiAuth.php';
require_once __DIR__ . '/includes/header.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$apiAuth = new ApiAuth();
$apiKeys = $apiAuth->listApiKeys();
$totalApiKeys = count($apiKeys);
$activeApiKeys = count(array_filter($apiKeys, function($key) {
    return $key['active'] === true;
}));
?>

<div class="bg-white shadow rounded-lg p-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Dashboard</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <!-- API Keys Stats -->
         
        <div class="bg-indigo-50 rounded-lg p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                    <i class="fas fa-key text-xl"></i>
                </div>
                <div class="ml-4">
                    <h2 class="text-lg font-semibold text-gray-900">API Keys</h2>
                    <p class="text-sm text-gray-600"><?php echo $activeApiKeys; ?> active of <?php echo $totalApiKeys; ?> total</p>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="bg-green-50 rounded-lg p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600">
                    <i class="fas fa-server text-xl"></i>
                </div>
                <div class="ml-4">
                    <h2 class="text-lg font-semibold text-gray-900">System Status</h2>
                    <p class="text-sm text-gray-600">All systems operational</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-blue-50 rounded-lg p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                    <i class="fas fa-bolt text-xl"></i>
                </div>
                <div class="ml-4">
                    <h2 class="text-lg font-semibold text-gray-900">Quick Actions</h2>
                    <div class="mt-2 space-x-2">
                        <a href="api-keys.php" class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200">
                            Manage API Keys
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="mt-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Recent API Key Activity</h2>
        <div class="bg-white shadow overflow-hidden sm:rounded-md">
            <ul class="divide-y divide-gray-200">
                <?php foreach ($apiKeys as $key => $data): ?>
                    <?php if (isset($data['last_used'])): ?>
                    <li class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-key text-gray-400"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($data['name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        Last used: <?php echo date('M j, Y g:i A', strtotime($data['last_used'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="text-sm text-gray-500">
                                <?php echo $data['active'] ? 
                                    '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>' : 
                                    '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Inactive</span>'; ?>
                            </div>
                        </div>
                    </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?> 