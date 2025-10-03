<?php
session_start();

// --- Configuration ---
$config_file_path = 'config.json';
if (!file_exists($config_file_path)) {
    // Create a default config if it doesn't exist
    $default_config = [
        "hero_banner" => "",
        "favicon" => "",
        "contact_info" => ["phone" => "", "whatsapp" => "", "email" => ""],
        "admin_password" => "password123"
    ];
    file_put_contents($config_file_path, json_encode($default_config, JSON_PRETTY_PRINT));
}
$config = json_decode(file_get_contents($config_file_path), true);
$ADMIN_PASSWORD = $config['admin_password'] ?? 'password123';

$error_message = '';

// If already logged in, redirect to the admin panel
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: admin.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['password']) && $_POST['password'] === $ADMIN_PASSWORD) {
        // Password is correct, set session variable
        $_SESSION['loggedin'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $error_message = 'Invalid password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #6D28D9; --primary-color-darker: #5B21B6; }
        body { font-family: 'Inter', sans-serif; }
        .form-input:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px #E9D5FF; outline: none; }
        .btn-primary { background-color: var(--primary-color); }
        .btn-primary:hover { background-color: var(--primary-color-darker); }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-xl shadow-lg">
        <div class="text-center">
            <h1 class="text-3xl font-bold text-gray-900">Admin Panel</h1>
            <p class="mt-2 text-gray-600">Please enter your password to access the dashboard.</p>
        </div>
        <form method="POST" action="login.php" class="space-y-6">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                    <i class="fa-solid fa-lock text-gray-400"></i>
                </span>
                <input id="password" name="password" type="password" required
                       class="w-full pl-10 pr-4 py-3 text-gray-800 bg-gray-50 border border-gray-300 rounded-lg shadow-sm form-input focus:outline-none transition"
                       placeholder="Password">
            </div>

            <?php if ($error_message): ?>
                <p class="text-sm text-center text-red-600 font-medium"><?= htmlspecialchars($error_message) ?></p>
            <?php endif; ?>

            <div>
                <button type="submit"
                        class="w-full px-4 py-3 font-semibold text-white transition-colors duration-200 transform rounded-lg btn-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                    Login
                </button>
            </div>
        </form>
    </div>
</body>
</html>