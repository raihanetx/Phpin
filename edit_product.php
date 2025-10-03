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
$product_to_edit = null;
$category_name = $_GET['category'] ?? '';
$product_id = $_GET['id'] ?? null;

if ($category_name && $product_id) {
    foreach ($all_data as $category) {
        if ($category['name'] === $category_name) {
            foreach ($category['products'] as $product) {
                if ($product['id'] == $product_id) {
                    $product_to_edit = $product;
                    break 2; // Exit both loops
                }
            }
        }
    }
}

if (!$product_to_edit) {
    die("Product not found or invalid ID!");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #6D28D9; --primary-color-darker: #5B21B6; }
        body { font-family: 'Inter', sans-serif; }
        .form-input, .form-select, .form-textarea { width: 100%; border-radius: 0.5rem; border: 1px solid #d1d5db; padding: 0.6rem 0.8rem; transition: all 0.2s ease-in-out; background-color: #F9FAFB; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary-color); box-shadow: 0 0 0 2px #E9D5FF; outline: none; background-color: white; }
        .btn { padding: 0.6rem 1.2rem; border-radius: 0.5rem; font-weight: 600; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .btn-primary { background-color: var(--primary-color); color: white; } .btn-primary:hover { background-color: var(--primary-color-darker); }
        .btn-danger { background-color: #fee2e2; color: #b91c1c; } .btn-danger:hover { background-color: #fecaca; color: #991b1b; }
        .btn-secondary { background-color: #f3f4f6; color: #374151; border: 1px solid #d1d5db; } .btn-secondary:hover { background-color: #e5e7eb; }
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.875rem; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center py-8">
    <div class="container mx-auto p-4 max-w-3xl">
        <div class="bg-white p-6 rounded-lg shadow-md border">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">Edit Product: <span class="text-[var(--primary-color)]"><?= htmlspecialchars($product_to_edit['name']) ?></span></h1>
            <form action="api.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="edit_product">
                <input type="hidden" name="category_name" value="<?= htmlspecialchars($category_name) ?>">
                <input type="hidden" name="product_id" value="<?= htmlspecialchars($product_id) ?>">
                
                <div>
                    <label class="block mb-1.5 font-medium text-gray-700 text-sm">Product Name</label>
                    <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($product_to_edit['name']) ?>" required>
                </div>
                <div>
                    <label class="block mb-1.5 font-medium text-gray-700 text-sm">Short Description</label>
                    <textarea name="description" class="form-textarea" rows="3" required><?= htmlspecialchars($product_to_edit['description']) ?></textarea>
                </div>
                <div>
                    <label class="block mb-1.5 font-medium text-gray-700 text-sm">Long Description</label>
                    <textarea name="long_description" class="form-textarea" rows="4"><?= htmlspecialchars($product_to_edit['long_description'] ?? '') ?></textarea>
                     <p class="text-xs text-gray-500 mt-1">Use **text** to make text bold.</p>
                </div>

                <?php $is_single_price = count($product_to_edit['pricing']) <= 1 && ($product_to_edit['pricing'][0]['duration'] === 'Default' || count($product_to_edit['pricing']) === 0); ?>
                <div>
                    <label class="block mb-1.5 font-medium text-gray-700 text-sm">Pricing Type</label>
                    <select id="pricing-type" class="form-select">
                        <option value="single" <?= $is_single_price ? 'selected' : '' ?>>Single Price</option>
                        <option value="multiple" <?= !$is_single_price ? 'selected' : '' ?>>Multiple Durations</option>
                    </select>
                </div>
                
                <div id="single-price-container" class="<?= !$is_single_price ? 'hidden' : '' ?>">
                    <label class="block mb-1.5 font-medium text-gray-700 text-sm">Price (৳)</label>
                    <input type="number" name="price" step="0.01" class="form-input" value="<?= htmlspecialchars($product_to_edit['pricing'][0]['price'] ?? '0.00') ?>">
                </div>

                <div id="multiple-pricing-container" class="space-y-3 <?= $is_single_price ? 'hidden' : '' ?>">
                    <label class="block font-medium text-gray-700 text-sm">Durations & Prices</label>
                    <div id="duration-fields">
                        <?php if (!$is_single_price): foreach ($product_to_edit['pricing'] as $pricing): ?>
                        <div class="flex items-center gap-2 mb-2">
                            <input type="text" name="durations[]" class="form-input" placeholder="Duration" value="<?= htmlspecialchars($pricing['duration']) ?>" required>
                            <input type="number" name="duration_prices[]" step="0.01" class="form-input" placeholder="Price" value="<?= htmlspecialchars($pricing['price']) ?>" required>
                            <button type="button" class="btn btn-danger btn-sm remove-duration-btn"><i class="fa-solid fa-trash-can"></i></button>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <button type="button" id="add-duration-btn" class="btn btn-secondary btn-sm"><i class="fa-solid fa-plus"></i> Add Duration</button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t mt-4">
                    <div>
                        <label class="block mb-1.5 font-medium text-gray-700 text-sm">Stock Status</label>
                        <select name="stock_out" class="form-select">
                            <option value="false" <?= !$product_to_edit['stock_out'] ? 'selected' : '' ?>>In Stock</option>
                            <option value="true" <?= $product_to_edit['stock_out'] ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                    </div>
                    <div class="pt-7">
                       <label class="flex items-center gap-2"><input type="checkbox" name="featured" id="featured" value="true" <?= !empty($product_to_edit['featured']) ? 'checked' : '' ?> class="h-4 w-4 rounded border-gray-300 text-[var(--primary-color)] focus:ring-[var(--primary-color)]"> Featured?</label>
                   </div>
                </div>

                <div>
                    <label class="block mb-1.5 font-medium text-gray-700 text-sm">Product Image</label>
                    <?php if (!empty($product_to_edit['image'])): ?>
                        <div class="my-2">
                            <img src="<?= htmlspecialchars($product_to_edit['image']) ?>" class="w-24 h-24 object-cover rounded-md border">
                            <div class="flex items-center gap-2 mt-2">
                                <input type="checkbox" name="delete_image" id="delete_image" value="true" class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                <label for="delete_image" class="text-sm text-red-600 font-medium">Delete current image</label>
                            </div>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" class="form-input" accept="image/png, image/jpeg, image/gif, image/webp">
                    <p class="text-xs text-gray-500 mt-1">Upload a new image to replace the current one.</p>
                </div>
                
                <div class="flex justify-between items-center mt-6 pt-4 border-t">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                    <a href="admin.php?category=<?= urlencode($category_name) ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const pricingType = document.getElementById('pricing-type');
    const singlePriceContainer = document.getElementById('single-price-container');
    const multiplePricingContainer = document.getElementById('multiple-pricing-container');
    const addDurationBtn = document.getElementById('add-duration-btn');
    const durationFields = document.getElementById('duration-fields');

    pricingType.addEventListener('change', function() {
        if (this.value === 'single') {
            singlePriceContainer.classList.remove('hidden');
            multiplePricingContainer.classList.add('hidden');
        } else {
            singlePriceContainer.classList.add('hidden');
            multiplePricingContainer.classList.remove('hidden');
            if (durationFields.children.length === 0) {
                 addDurationField();
            }
        }
    });

    addDurationBtn.addEventListener('click', addDurationField);
    
    function addDurationField() {
        const fieldGroup = document.createElement('div');
        fieldGroup.className = 'flex items-center gap-2 mb-2';
        fieldGroup.innerHTML = `<input type="text" name="durations[]" class="form-input" placeholder="Duration (e.g., 1 Year)" required><input type="number" name="duration_prices[]" step="0.01" class="form-input" placeholder="Price" required><button type="button" class="btn btn-danger btn-sm remove-duration-btn"><i class="fa-solid fa-trash-can"></i></button>`;
        durationFields.appendChild(fieldGroup);
    }
    
    durationFields.addEventListener('click', function(e) {
        if (e.target && e.target.closest('.remove-duration-btn')) {
            e.target.closest('.flex').remove();
        }
    });
});
</script>

</body>
</html>