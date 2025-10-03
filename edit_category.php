<?php
session_start();

// --- Security Check ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// --- Load Data ---
$data_file_path = 'products.json';
$all_data = json_decode(file_get_contents($data_file_path), true);
$category_to_edit = null;
$category_name = $_GET['name'] ?? '';

if ($category_name) {
    foreach ($all_data as $category) {
        if ($category['name'] === $category_name) {
            $category_to_edit = $category;
            break;
        }
    }
}

if (!$category_to_edit) {
    die("Category not found or invalid name!");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #6D28D9; --primary-color-darker: #5B21B6; }
        body { font-family: 'Inter', sans-serif; }
        .form-input { width: 100%; border-radius: 0.5rem; border: 1px solid #d1d5db; padding: 0.6rem 0.8rem; transition: all 0.2s ease-in-out; background-color: #F9FAFB; }
        .form-input:focus { border-color: var(--primary-color); box-shadow: 0 0 0 2px #E9D5FF; outline: none; background-color: white; }
        .btn { padding: 0.6rem 1.2rem; border-radius: 0.5rem; font-weight: 600; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .btn-primary { background-color: var(--primary-color); color: white; } .btn-primary:hover { background-color: var(--primary-color-darker); }
        .btn-secondary { background-color: #f3f4f6; color: #374151; border: 1px solid #d1d5db; } .btn-secondary:hover { background-color: #e5e7eb; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="container mx-auto p-4 max-w-lg">
        <div class="bg-white p-6 md:p-8 rounded-lg shadow-md border">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">Edit Category</h1>
            <form action="api.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="original_name" value="<?= htmlspecialchars($category_to_edit['name']) ?>">
                
                <div>
                    <label class="block mb-1.5 font-medium text-gray-700 text-sm">Category Name</label>
                    <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($category_to_edit['name']) ?>" required>
                </div>
                <div>
                    <label class="block mb-1.5 font-medium text-gray-700 text-sm">Font Awesome Icon Class</label>
                    <input type="text" name="icon" class="form-input" value="<?= htmlspecialchars($category_to_edit['icon']) ?>" placeholder="e.g., fa-solid fa-book-open" required>
                </div>

                <div class="flex justify-between items-center mt-6 pt-4 border-t">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                     <a href="admin.php?view=categories" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>