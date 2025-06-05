<?php
require_once __DIR__ . '/includes/security.php';
// Login page should be accessible directly, but we'll still set security headers
if (function_exists('secure_file')) {
    // Only set security headers, skip the direct access check
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; font-src \'self\' https://cdnjs.cloudflare.com;');
}

session_start();

// Load admin configuration
$config_file = __DIR__ . '/config/admin.json';
if (!file_exists($config_file)) {
    die('Admin configuration file not found');
}

$admin_config = json_decode(file_get_contents($config_file), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die('Invalid admin configuration file');
}

// Initialize login attempt tracking if not exists
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user is locked out
    if ($_SESSION['login_attempts'] >= $admin_config['max_login_attempts']) {
        $time_passed = time() - $_SESSION['last_attempt_time'];
        if ($time_passed < $admin_config['lockout_time']) {
            $_SESSION['flash_message'] = 'Too many login attempts. Please try again in ' . 
                ceil(($admin_config['lockout_time'] - $time_passed) / 60) . ' minutes.';
            $_SESSION['flash_type'] = 'error';
        } else {
            // Reset attempts after lockout period
            $_SESSION['login_attempts'] = 0;
        }
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if ($username === $admin_config['username'] && 
            password_verify($password, $admin_config['password_hash'])) {
            // Successful login
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['login_attempts'] = 0;
            header('Location: index.php');
            exit;
        } else {
            // Failed login
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt_time'] = time();
            $_SESSION['flash_message'] = 'Invalid username or password';
            $_SESSION['flash_type'] = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CMS Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8 p-8 bg-white rounded-lg shadow-lg">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                CMS Admin Login
            </h2>
        </div>
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="rounded-md bg-red-100 p-4">
                <div class="text-sm text-red-700">
                    <?php 
                    echo $_SESSION['flash_message'];
                    unset($_SESSION['flash_message']);
                    unset($_SESSION['flash_type']);
                    ?>
                </div>
            </div>
        <?php endif; ?>
        <form class="mt-8 space-y-6" method="POST">
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="username" class="sr-only">Username</label>
                    <input id="username" name="username" type="text" required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" 
                           placeholder="Username">
                </div>
                <div>
                    <label for="password" class="sr-only">Password</label>
                    <input id="password" name="password" type="password" required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" 
                           placeholder="Password">
                </div>
            </div>

            <div>
                <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Sign in
                </button>
            </div>
        </form>
    </div>
</body>
</html> 