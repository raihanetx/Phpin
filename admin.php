<?php
session_start();

// --- Security Check ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// --- Load All Data ---
$products_file_path = 'products.json';
if (!file_exists($products_file_path)) file_put_contents($products_file_path, '[]');
$all_products_data = json_decode(file_get_contents($products_file_path), true);

$coupons_file_path = 'coupons.json';
if (!file_exists($coupons_file_path)) file_put_contents($coupons_file_path, '[]');
$all_coupons_data = json_decode(file_get_contents($coupons_file_path), true);

$orders_file_path = 'orders.json';
if (!file_exists($orders_file_path)) file_put_contents($orders_file_path, '[]');
$all_orders_data_raw = json_decode(file_get_contents($orders_file_path), true);
if(!empty($all_orders_data_raw)) {
    usort($all_orders_data_raw, fn($a, $b) => $b['order_id'] <=> $a['order_id']);
}

// New: Load Site Config Data
$config_file_path = 'config.json';
if (!file_exists($config_file_path)) file_put_contents($config_file_path, '{"hero_banner":[],"favicon":"","contact_info":{"phone":"","whatsapp":"","email":""},"admin_password":"password123", "usd_to_bdt_rate": 110, "site_logo":"", "hero_slider_interval": 5000, "hot_deals_speed": 40}');
$site_config = json_decode(file_get_contents($config_file_path), true);

// New: Load Hot Deals Data
$hotdeals_file_path = 'hotdeals.json';
if (!file_exists($hotdeals_file_path)) file_put_contents($hotdeals_file_path, '[]');
$all_hotdeals_data = json_decode(file_get_contents($hotdeals_file_path), true);


// --- Helper function to calculate stats for a given period ---
function calculate_stats($orders, $days = null) {
    $filtered_orders = $orders;
    if ($days !== null) {
        $cutoff_date = new DateTime();
        if ($days == 0) { // Special case for "Today"
             $cutoff_date->setTime(0, 0, 0);
        } else {
             $cutoff_date->modify("-{$days} days");
        }
        $filtered_orders = array_filter($orders, function ($order) use ($cutoff_date) {
            $order_date = new DateTime($order['order_date']);
            return $order_date >= $cutoff_date;
        });
    }

    $stats = [
        'total_revenue' => 0,
        'total_orders' => count($filtered_orders),
        'pending_orders' => 0,
        'confirmed_orders' => 0,
        'cancelled_orders' => 0,
    ];

    foreach ($filtered_orders as $order) {
        if ($order['status'] === 'Confirmed') {
            $stats['total_revenue'] += $order['totals']['total'];
            $stats['confirmed_orders']++;
        } elseif ($order['status'] === 'Pending') {
            $stats['pending_orders']++;
        } elseif ($order['status'] === 'Cancelled') {
            $stats['cancelled_orders']++;
        }
    }
    return $stats;
}

// Pre-calculate stats for different periods
$stats_today = calculate_stats($all_orders_data_raw, 0);
$stats_7_days = calculate_stats($all_orders_data_raw, 7);
$stats_30_days = calculate_stats($all_orders_data_raw, 30);
$stats_6_months = calculate_stats($all_orders_data_raw, 180);
$stats_all_time = calculate_stats($all_orders_data_raw);

// Calculate pending orders for notification badge
$pending_orders_count = count(array_filter($all_orders_data_raw, fn($o) => $o['status'] === 'Pending'));


// --- Logic for displaying specific views ---
$category_to_manage = null;
if (isset($_GET['category'])) {
    foreach ($all_products_data as $category) {
        if ($category['name'] === $_GET['category']) {
            $category_to_manage = $category;
            break;
        }
    }
}

// Consolidate all reviews & products for JS
$all_reviews = [];
$all_products_for_js = [];
if (!empty($all_products_data)) {
    foreach ($all_products_data as $category) {
        if (isset($category['products']) && is_array($category['products'])) {
            foreach ($category['products'] as $product) {
                 // For JS dropdowns
                $all_products_for_js[] = ['id' => $product['id'], 'name' => $product['name'], 'category' => $category['name']];
                // For review management
                if (isset($product['reviews']) && is_array($product['reviews'])) {
                    foreach ($product['reviews'] as $review) {
                        $review['product_id'] = $product['id'];
                        $review['product_name'] = $product['name'];
                        $all_reviews[] = $review;
                    }
                }
            }
        }
    }
}
if (!empty($all_reviews)) {
    usort($all_reviews, fn($a, $b) => strcmp($b['id'], $a['id']));
}


$current_view = $_GET['view'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root { --primary-color: #6D28D9; --primary-color-darker: #5B21B6; }
        body { font-family: 'Inter', sans-serif; }
        .form-input, .form-select, .form-textarea { width: 100%; border-radius: 0.5rem; border: 1px solid #d1d5db; padding: 0.6rem 0.8rem; transition: all 0.2s ease-in-out; background-color: #F9FAFB; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary-color); box-shadow: 0 0 0 2px #E9D5FF; outline: none; background-color: white; }
        .btn { padding: 0.6rem 1.2rem; border-radius: 0.5rem; font-weight: 600; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .btn-primary { background-color: var(--primary-color); color: white; } .btn-primary:hover { background-color: var(--primary-color-darker); }
        .btn-secondary { background-color: #f3f4f6; color: #374151; border: 1px solid #d1d5db; } .btn-secondary:hover { background-color: #e5e7eb; }
        .btn-danger { background-color: #fee2e2; color: #b91c1c; } .btn-danger:hover { background-color: #fecaca; color: #991b1b; }
        .btn-success { background-color: #dcfce7; color: #166534; } .btn-success:hover { background-color: #bbf7d0; }
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.875rem; }
        .tab { padding: 0.75rem 1rem; font-weight: 600; color: #4b5563; border-bottom: 3px solid transparent; }
        .tab-active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
        .stats-filter-btn { padding: 0.5rem 1rem; border-radius: 9999px; font-weight: 500; transition: all 0.2s; border: 1px solid transparent; }
        .stats-filter-btn.active { background-color: var(--primary-color); color: white; }
        .stats-filter-btn:not(.active) { background-color: #f3f4f6; color: #374151; }
        .stats-filter-btn:not(.active):hover { background-color: #e5e7eb; }
        .card { background-color: white; border-radius: 0.75rem; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05), 0 1px 2px -1px rgb(0 0 0 / 0.05); border: 1px solid #e5e7eb; }
        .hidden { display: none; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto p-4 md:p-6">
        
        <?php if ($category_to_manage !== null): ?>
            <!-- Product Management View -->
            <a href="admin.php?view=categories" class="inline-flex items-center gap-2 mb-6 text-gray-600 font-semibold hover:text-[var(--primary-color)] transition-colors">
                <i class="fa-solid fa-arrow-left"></i> Back to Categories
            </a>
            <h1 class="text-3xl font-bold text-gray-800 mb-6">Manage Products: <span class="text-[var(--primary-color)]"><?= htmlspecialchars($category_to_manage['name']) ?></span></h1>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                <div class="lg:col-span-1 card p-6">
                    <h2 class="text-xl font-bold text-gray-700 mb-4">Add New Product</h2>
                    <form action="api.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="action" value="add_product">
                        <input type="hidden" name="category_name" value="<?= htmlspecialchars($category_to_manage['name']) ?>">
                        <div><label class="block mb-1.5 font-medium text-gray-700 text-sm">Product Name</label><input type="text" name="name" class="form-input" required></div>
                        <div><label class="block mb-1.5 font-medium text-gray-700 text-sm">Short Description</label><textarea name="description" class="form-textarea" rows="3" required></textarea></div>
                        <div><label class="block mb-1.5 font-medium text-gray-700 text-sm">Long Description</label><textarea name="long_description" class="form-textarea" rows="5"></textarea><p class="text-xs text-gray-500 mt-1">Use **text** to make text bold.</p></div>
                        <div><label class="block mb-1.5 font-medium text-gray-700 text-sm">Pricing Type</label><select id="pricing-type" class="form-select"><option value="single">Single Price</option><option value="multiple">Multiple Durations</option></select></div>
                        <div id="single-price-container"><label class="block mb-1.5 font-medium text-gray-700 text-sm">Price (৳)</label><input type="number" name="price" step="0.01" class="form-input" value="0.00"></div>
                        <div id="multiple-pricing-container" class="space-y-3 hidden"><label class="block font-medium text-gray-700 text-sm">Durations & Prices</label><div id="duration-fields"></div><button type="button" id="add-duration-btn" class="btn btn-secondary btn-sm"><i class="fa-solid fa-plus"></i> Add Duration</button></div>
                        <div><label class="block mb-1.5 font-medium text-gray-700 text-sm">Product Image</label><input type="file" name="image" class="form-input" accept="image/*"></div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block mb-1.5 font-medium text-gray-700 text-sm">Stock Status</label><select name="stock_out" class="form-select"><option value="false">In Stock</option><option value="true">Out of Stock</option></select></div>
                            <div class="pt-7"><label class="flex items-center gap-2"><input type="checkbox" name="featured" id="featured" value="true" class="h-4 w-4 rounded border-gray-300 text-[var(--primary-color)] focus:ring-[var(--primary-color)]"> Featured?</label></div>
                        </div>
                        <button type="submit" class="btn btn-primary w-full mt-2"><i class="fa-solid fa-circle-plus"></i>Add Product</button>
                    </form>
                </div>

                <div class="lg:col-span-2 card p-6">
                    <h2 class="text-xl font-bold text-gray-700 mb-4">Existing Products</h2>
                    <div class="space-y-3">
                        <?php if (empty($category_to_manage['products'])): ?>
                            <p class="text-gray-500 text-center py-10">No products found in this category.</p>
                        <?php else: ?>
                            <?php foreach ($category_to_manage['products'] as $product): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border flex-wrap gap-4">
                                <div class="flex items-center gap-4 flex-grow">
                                    <img src="<?= htmlspecialchars($product['image'] ? $product['image'] : 'https://via.placeholder.com/64/E9D5FF/5B21B6?text=N/A') ?>" class="w-16 h-16 object-cover rounded-md bg-gray-200">
                                    <div>
                                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($product['name']) ?></p>
                                        <p class="text-sm text-gray-600 font-semibold text-[var(--primary-color)]">৳<?= number_format($product['pricing'][0]['price'], 2) ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <a href="edit_product.php?category=<?= urlencode($category_to_manage['name']) ?>&id=<?= $product['id'] ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pencil"></i> Edit</a>
                                    <form action="api.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="category_name" value="<?= htmlspecialchars($category_to_manage['name']) ?>">
                                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash-can"></i></button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Main Dashboard View -->
            <header class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Admin Dashboard</h1>
                <a href="logout.php" class="btn btn-secondary"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </header>
            <div class="card">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex gap-4 px-6 overflow-x-auto">
                        <a href="admin.php?view=dashboard" class="tab flex-shrink-0 <?= $current_view === 'dashboard' ? 'tab-active' : '' ?>"><i class="fa-solid fa-table-columns mr-2"></i>Dashboard</a>
                        <a href="admin.php?view=categories" class="tab flex-shrink-0 <?= $current_view === 'categories' ? 'tab-active' : '' ?>"><i class="fa-solid fa-list mr-2"></i>Categories</a>
                        <a href="admin.php?view=hotdeals" class="tab flex-shrink-0 <?= $current_view === 'hotdeals' ? 'tab-active' : '' ?>"><i class="fa-solid fa-fire mr-2"></i>Hot Deals</a>
                        <a href="admin.php?view=orders" class="tab flex-shrink-0 <?= $current_view === 'orders' ? 'tab-active' : '' ?>"><i class="fa-solid fa-bag-shopping mr-2"></i>Orders <?php if ($pending_orders_count > 0): ?><span class="ml-2 bg-yellow-100 text-yellow-800 text-xs font-bold rounded-full px-2 py-0.5"><?= $pending_orders_count ?></span><?php endif; ?></a>
                        <a href="admin.php?view=reviews" class="tab flex-shrink-0 <?= $current_view === 'reviews' ? 'tab-active' : '' ?>"><i class="fa-solid fa-star mr-2"></i>Reviews <span class="ml-2 bg-purple-100 text-purple-700 text-xs font-bold rounded-full px-2 py-0.5"><?= count($all_reviews) ?></span></a>
                        <a href="admin.php?view=settings" class="tab flex-shrink-0 <?= $current_view === 'settings' ? 'tab-active' : '' ?>"><i class="fa-solid fa-gear mr-2"></i>Settings</a>
                    </nav>
                </div>
                <!-- Dashboard (Stats & Coupon) View -->
                <div id="view-dashboard" style="<?= $current_view === 'dashboard' ? '' : 'display:none;' ?>" class="p-6 space-y-8">
                    <div>
                        <div class="flex flex-wrap gap-2 mb-4" id="stats-filter-container">
                            <button class="stats-filter-btn" data-period="today">Today</button><button class="stats-filter-btn" data-period="7days">7 Days</button><button class="stats-filter-btn" data-period="30days">30 Days</button><button class="stats-filter-btn" data-period="6months">6 Months</button><button class="stats-filter-btn active" data-period="all">All Time</button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4" id="stats-display-container"></div>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-700 mb-4">Manage Coupons</h2>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                            <div class="bg-gray-50 p-6 rounded-lg border">
                                <h3 class="text-lg font-semibold mb-4">Add New Coupon</h3>
                                <form action="api.php" method="POST" class="space-y-4"><input type="hidden" name="action" value="add_coupon"><div><label class="block mb-1.5 text-sm font-medium text-gray-700">Coupon Code</label><input type="text" name="code" class="form-input uppercase" placeholder="e.g., SALE20" required></div><div><label class="block mb-1.5 text-sm font-medium text-gray-700">Discount (%)</label><input type="number" name="discount_percentage" class="form-input" placeholder="e.g., 20" required min="1" max="100"></div><div><label class="block mb-1.5 text-sm font-medium text-gray-700">Coupon Scope</label><select name="scope" id="coupon_scope" class="form-select"><option value="all_products">All Products</option><option value="category">Specific Category</option><option value="single_product">Single Product</option></select></div><div id="scope_category_container" class="hidden"><label class="block mb-1.5 text-sm font-medium text-gray-700">Select Category</label><select name="scope_value_category" class="form-select"><?php foreach($all_products_data as $cat): ?><option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option><?php endforeach; ?></select></div><div id="scope_product_container" class="hidden"><label class="block mb-1.5 text-sm font-medium text-gray-700">Select Product</label><select name="scope_value_product" class="form-select"><?php foreach($all_products_for_js as $prod): ?><option value="<?= htmlspecialchars($prod['id']) ?>"><?= htmlspecialchars($prod['name']) ?></option><?php endforeach; ?></select></div><div class="flex items-center gap-2 pt-2"><input type="checkbox" name="is_active" id="is_active" value="true" checked class="h-4 w-4 rounded border-gray-300 text-[var(--primary-color)] focus:ring-[var(--primary-color)]"><label for="is_active" class="text-sm font-medium text-gray-700">Activate Coupon</label></div><div><button type="submit" class="btn btn-primary"><i class="fa-solid fa-circle-plus"></i>Add Coupon</button></div></form>
                            </div>
                            <div class="bg-gray-50 p-6 rounded-lg border">
                                <h3 class="text-lg font-semibold mb-4">Existing Coupons</h3>
                                <div class="space-y-3 max-h-[25rem] overflow-y-auto pr-2"><?php if (empty($all_coupons_data)): ?><p class="text-gray-500 text-center py-4">No coupons found.</p><?php else: ?><?php foreach ($all_coupons_data as $coupon): ?><div class="flex items-center justify-between p-3 bg-white rounded-lg border flex-wrap"><div><p class="font-bold text-base uppercase"><?= htmlspecialchars($coupon['code']) ?> <span class="ml-2 text-sm font-normal text-gray-500"><?= htmlspecialchars($coupon['discount_percentage']) ?>% Off</span></p><?php $scope_display_value = $coupon['scope_value'] ?? ''; if (($coupon['scope'] ?? 'all_products') === 'single_product' && !empty($scope_display_value)) { foreach ($all_products_for_js as $p) { if ($p['id'] == $scope_display_value) { $scope_display_value = $p['name']; break; } } } ?><p class="text-xs text-blue-600 font-semibold mt-1">Scope: <?= htmlspecialchars(ucwords(str_replace('_', ' ', $coupon['scope'] ?? 'all_products'))) ?><?php if(!empty($coupon['scope_value'])): ?> (<?= htmlspecialchars($scope_display_value) ?>)<?php endif; ?></p></div><div class="flex items-center gap-3"><span class="text-sm font-bold py-0.5 px-2 rounded-full <?= $coupon['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>"><?= $coupon['is_active'] ? 'Active' : 'Inactive' ?></span><form action="api.php" method="POST" onsubmit="return confirm('Are you sure?');"><input type="hidden" name="action" value="delete_coupon"><input type="hidden" name="coupon_id" value="<?= $coupon['id'] ?>"><button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash-can"></i></button></form></div></div><?php endforeach; ?><?php endif; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Category Management View -->
                <div id="view-categories" style="<?= $current_view === 'categories' ? '' : 'display:none;' ?>" class="p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                        <div class="bg-gray-50 p-6 rounded-lg border">
                            <h3 class="text-lg font-semibold mb-4">Add New Category</h3>
                            <form action="api.php" method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="add_category">
                                <div><label class="block mb-1.5 font-medium text-gray-700 text-sm">Category Name</label><input type="text" name="name" class="form-input" required></div>
                                <div><label class="block mb-1.5 font-medium text-gray-700 text-sm">Font Awesome Icon Class</label><input type="text" name="icon" class="form-input" placeholder="e.g., fa-solid fa-book-open" required></div>
                                <div><button type="submit" class="btn btn-primary"><i class="fa-solid fa-circle-plus"></i> Add Category</button></div>
                            </form>
                        </div>
                        <div class="bg-gray-50 p-6 rounded-lg border">
                            <h3 class="text-lg font-semibold mb-4">Existing Categories</h3>
                            <div class="space-y-3 max-h-96 overflow-y-auto pr-2">
                                <?php if(!empty($all_products_data)): foreach ($all_products_data as $category): ?>
                                <div class="flex items-center justify-between p-3 bg-white rounded-lg border flex-wrap gap-2">
                                    <div class="flex items-center gap-4">
                                        <i class="<?= htmlspecialchars($category['icon']) ?> text-xl w-8 text-center text-[var(--primary-color)]"></i>
                                        <span class="font-semibold text-gray-800"><?= htmlspecialchars($category['name']) ?></span>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <a href="admin.php?category=<?= urlencode($category['name']) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pencil"></i> Manage (<?= count($category['products'] ?? []) ?>)</a>
                                        <a href="edit_category.php?name=<?= urlencode($category['name']) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pencil"></i></a>
                                        <form action="api.php" method="POST" onsubmit="return confirm('Delete this category and all its products?');">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="name" value="<?= htmlspecialchars($category['name']) ?>">
                                            <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash-can"></i></button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Hot Deals Management View -->
                <div id="view-hotdeals" style="<?= $current_view === 'hotdeals' ? '' : 'display:none;' ?>" class="p-6">
                    <form action="api.php" method="POST">
                        <input type="hidden" name="action" value="update_hot_deals">
                        <div class="bg-white p-6 rounded-lg border mb-6">
                             <h3 class="text-lg font-semibold mb-4 text-gray-800">Hot Deals Settings</h3>
                             <div>
                                <label for="hot_deals_speed" class="block mb-1.5 font-medium text-gray-700 text-sm">Scroll Speed (in seconds)</label>
                                <input type="number" id="hot_deals_speed" name="hot_deals_speed" class="form-input max-w-xs" value="<?= htmlspecialchars($site_config['hot_deals_speed'] ?? 40) ?>" placeholder="e.g., 40">
                                <p class="text-xs text-gray-500 mt-1">A higher number means a slower scroll.</p>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-lg border">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800">Select & Customize Hot Deals Products</h3>
                             <div class="space-y-4 max-h-[40rem] overflow-y-auto p-2 border rounded-md">
                            <?php
                                $selected_deals_map = array_column($all_hotdeals_data, null, 'productId');
                                foreach ($all_products_for_js as $product):
                                    $product_id = $product['id'];
                                    $is_selected = isset($selected_deals_map[$product_id]);
                                    $custom_title = $is_selected ? $selected_deals_map[$product_id]['customTitle'] : '';
                            ?>
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 transition-all" x-data="{ selected: <?= $is_selected ? 'true' : 'false' ?> }">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox" :checked="selected" @change="selected = !selected" name="selected_deals[]" value="<?= htmlspecialchars($product_id) ?>" class="h-5 w-5 rounded border-gray-300 text-[var(--primary-color)] focus:ring-[var(--primary-color)] flex-shrink-0">
                                        <label class="font-semibold text-gray-800"><?= htmlspecialchars($product['name']) ?></label>
                                    </div>
                                    <div x-show="selected" x-cloak class="mt-4 pl-8 space-y-3 border-l-2 border-purple-200 ml-2">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-600 mb-1">Custom Title (Optional)</label>
                                            <input type="text" name="custom_titles[<?= htmlspecialchars($product_id) ?>]" value="<?= htmlspecialchars($custom_title) ?>" class="form-input" placeholder="Overrides product name">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mt-6">
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Hot Deals Configuration</button>
                        </div>
                    </form>
                </div>


                <!-- Order Management View -->
                <div id="view-orders" style="<?= $current_view === 'orders' ? '' : 'display:none;' ?>" class="p-6" x-data="ordersManager()">
                     <div class="mb-4"><input type="text" x-model.debounce.300ms="searchQuery" class="form-input" placeholder="Search by Order ID, Customer Name, Phone, Email, or Product Name..."></div>
                     <template x-if="paginatedOrders.length === 0">
                        <p class="text-gray-500 text-center py-10" x-text="searchQuery ? 'No orders match your search.' : 'No orders have been placed yet.'"></p>
                     </template>
                     <template x-if="paginatedOrders.length > 0">
                        <div class="space-y-4">
                            <template x-for="order in paginatedOrders" :key="order.order_id">
                                <div class="bg-white border rounded-lg">
                                    <div class="p-4 border-b flex justify-between items-center flex-wrap gap-4">
                                        <div>
                                            <h3 class="font-bold text-gray-800">Order #<span x-text="order.order_id"></span></h3>
                                            <p class="text-sm text-gray-500" x-text="new Date(order.order_date).toLocaleString()"></p>
                                        </div>
                                        <div>
                                            <span class="font-bold py-1 px-3 rounded-full text-sm" x-text="order.status" :class="{ 'bg-green-100 text-green-800': order.status === 'Confirmed', 'bg-red-100 text-red-800': order.status === 'Cancelled', 'bg-yellow-100 text-yellow-800': order.status === 'Pending' }"></span>
                                        </div>
                                    </div>
                                    <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                                        <div>
                                            <h4 class="font-semibold mb-2 text-gray-500 uppercase text-xs tracking-wider">Customer & Payment</h4>
                                            <p><strong>Name:</strong> <span x-text="order.customer.name"></span></p>
                                            <p><strong>Phone:</strong> <span x-text="order.customer.phone"></span></p>
                                            <p><strong>Email:</strong> <span x-text="order.customer.email"></span></p>
                                            <hr class="my-2">
                                            <p><strong>Method:</strong> <span x-text="order.payment.method"></span></p>
                                            <p><strong>TrxID:</strong> <span x-text="order.payment.trx_id"></span></p>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold mb-2 text-gray-500 uppercase text-xs tracking-wider">Items Ordered</h4>
                                            <template x-for="item in order.items" :key="item.id + item.pricing.duration">
                                                <div class="mb-1"><span x-text="item.quantity"></span>x <span x-text="item.name"></span> (<span x-text="item.pricing.duration"></span>)</div>
                                            </template>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold mb-2 text-gray-500 uppercase text-xs tracking-wider">Summary & Actions</h4>
                                            <p><strong>Subtotal:</strong> <span x-text="'৳' + order.totals.subtotal.toFixed(2)"></span></p>
                                            <template x-if="order.totals.discount > 0">
                                                <p class="text-green-600"><strong>Discount (<span x-text="order.coupon.code || 'N/A'"></span>):</strong> <span x-text="'-৳' + order.totals.discount.toFixed(2)"></span></p>
                                            </template>
                                            <p class="font-bold text-base mt-1"><strong>Total:</strong> <span x-text="'৳' + order.totals.total.toFixed(2)"></span></p>
                                            <template x-if="order.status === 'Pending'">
                                                <div class="mt-4 flex gap-2">
                                                    <form action="api.php" method="POST"><input type="hidden" name="action" value="update_order_status"><input type="hidden" name="order_id" :value="order.order_id"><input type="hidden" name="new_status" value="Confirmed"><button type="submit" class="btn btn-success btn-sm">Confirm</button></form>
                                                    <form action="api.php" method="POST"><input type="hidden" name="action" value="update_order_status"><input type="hidden" name="order_id" :value="order.order_id"><input type="hidden" name="new_status" value="Cancelled"><button type="submit" class="btn btn-danger btn-sm">Cancel</button></form>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                     </template>
                     <div class="mt-6 flex justify-between items-center" x-show="totalPages > 1">
                        <button @click="prevPage" :disabled="currentPage === 1" class="btn btn-secondary" :class="{'opacity-50 cursor-not-allowed': currentPage === 1}"><i class="fa-solid fa-chevron-left"></i> Previous</button>
                        <span class="text-sm font-medium text-gray-700">Page <span x-text="currentPage"></span> of <span x-text="totalPages"></span></span>
                        <button @click="nextPage" :disabled="currentPage === totalPages" class="btn btn-secondary" :class="{'opacity-50 cursor-not-allowed': currentPage === totalPages}">Next <i class="fa-solid fa-chevron-right"></i></button>
                    </div>
                </div>
                <!-- Review Management View -->
                <div id="view-reviews" style="<?= $current_view === 'reviews' ? '' : 'display:none;' ?>" class="p-6"><h2 class="text-xl font-bold text-gray-700 mb-4">Manage All Reviews</h2><?php if(empty($all_reviews)): ?><p class="text-gray-500 text-center py-10">There are no reviews on the website yet.</p><?php else: ?><div class="space-y-4"><?php foreach($all_reviews as $review): ?><div class="bg-gray-50 border rounded-lg p-4 flex flex-col md:flex-row gap-4 justify-between items-start"><div class="flex-grow"><p class="font-semibold text-gray-800"><?= htmlspecialchars($review['name']) ?> <span class="text-yellow-500 ml-2"><?= str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']) ?></span></p><p class="text-sm text-gray-500">For: <strong><?= htmlspecialchars($review['product_name']) ?></strong></p><p class="mt-2 text-gray-700">"<?= nl2br(htmlspecialchars($review['comment'])) ?>"</p></div><div class="flex-shrink-0 flex items-center gap-2 mt-2 md:mt-0"><form action="api.php" method="POST" onsubmit="return confirm('Are you sure?');"><input type="hidden" name="action" value="update_review_status"><input type="hidden" name="product_id" value="<?= $review['product_id'] ?>"><input type="hidden" name="review_id" value="<?= $review['id'] ?>"><input type="hidden" name="new_status" value="deleted"><button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash-can"></i> Delete</button></form></div></div><?php endforeach; ?></div><?php endif; ?></div>
                <!-- Settings View -->
                <div id="view-settings" style="<?= $current_view === 'settings' ? '' : 'display:none;' ?>" class="p-6 space-y-8 max-w-5xl mx-auto">
                    <!-- Site Identity -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <form action="api.php" method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded-lg border">
                            <input type="hidden" name="action" value="update_site_logo">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800">Site Logo</h3>
                            <?php if (!empty($site_config['site_logo']) && file_exists($site_config['site_logo'])): ?>
                                <div class="mb-4">
                                    <p class="text-sm font-medium text-gray-600 mb-2">Current Logo:</p>
                                    <img src="<?= htmlspecialchars($site_config['site_logo']) ?>" class="h-10 bg-gray-200 p-1 rounded-md border shadow-sm">
                                    <div class="flex items-center gap-2 mt-3">
                                        <input type="checkbox" name="delete_site_logo" id="delete_site_logo" value="true" class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                        <label for="delete_site_logo" class="text-sm text-red-600 font-medium">Delete current logo</label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div>
                                <label class="block mb-1.5 font-medium text-gray-700 text-sm">Upload New Logo</label>
                                <input type="file" name="site_logo" class="form-input" accept="image/*">
                            </div>
                            <button type="submit" class="btn btn-primary mt-4"><i class="fa-solid fa-floppy-disk"></i> Save Logo</button>
                        </form>
                        <form action="api.php" method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded-lg border">
                            <input type="hidden" name="action" value="update_favicon">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800">Site Favicon</h3>
                             <?php if (!empty($site_config['favicon']) && file_exists($site_config['favicon'])): ?>
                                <div class="mb-4">
                                    <p class="text-sm font-medium text-gray-600 mb-2">Current Favicon:</p>
                                    <img src="<?= htmlspecialchars($site_config['favicon']) ?>" class="h-10 w-10 rounded-md border shadow-sm">
                                    <div class="flex items-center gap-2 mt-3">
                                        <input type="checkbox" name="delete_favicon" id="delete_favicon" value="true" class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                        <label for="delete_favicon" class="text-sm text-red-600 font-medium">Delete current favicon</label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div>
                                <label class="block mb-1.5 font-medium text-gray-700 text-sm">Upload New Favicon (.png, .ico)</label>
                                <input type="file" name="favicon" class="form-input" accept="image/png, image/x-icon">
                            </div>
                            <button type="submit" class="btn btn-primary mt-4"><i class="fa-solid fa-floppy-disk"></i> Save Favicon</button>
                        </form>
                    </div>

                    <!-- Hero Banner Section -->
                    <div class="bg-white p-6 rounded-lg border">
                        <form action="api.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_hero_banner">
                            <h3 class="text-lg font-semibold mb-2 text-gray-800">Hero Section Banners (Slider)</h3>
                            <p class="text-sm text-gray-600 mb-4">You can upload up to 10 images for the homepage slider.</p>
                             <div class="mb-6">
                                <label for="hero_slider_interval" class="block mb-1.5 font-medium text-gray-700 text-sm">Slider Interval (in seconds)</label>
                                <input type="number" id="hero_slider_interval" name="hero_slider_interval" class="form-input max-w-xs" value="<?= htmlspecialchars(($site_config['hero_slider_interval'] ?? 5000) / 1000) ?>" placeholder="e.g., 5">
                            </div>
                            <div class="space-y-6">
                                <?php
                                $current_banners = $site_config['hero_banner'] ?? [];
                                for ($i = 0; $i < 10; $i++):
                                    $banner_path = $current_banners[$i] ?? null;
                                ?>
                                <div class="p-4 border rounded-md bg-gray-50">
                                    <label class="block font-medium text-gray-700 text-sm mb-2">Slider Image #<?= $i + 1 ?></label>
                                    <?php if ($banner_path && file_exists($banner_path)): ?>
                                        <div class="mb-2">
                                            <img src="<?= htmlspecialchars($banner_path) ?>" class="max-h-24 rounded border">
                                            <div class="flex items-center gap-2 mt-2">
                                                <input type="checkbox" name="delete_hero_banners[<?= $i ?>]" id="delete_banner_<?= $i ?>" value="true" class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                                <label for="delete_banner_<?= $i ?>" class="text-sm text-red-600 font-medium">Delete this image</label>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" name="hero_banners[<?= $i ?>]" class="form-input text-sm" accept="image/*">
                                    <p class="text-xs text-gray-500 mt-1">Uploading an image here will replace the existing one for this slot.</p>
                                </div>
                                <?php endfor; ?>
                            </div>
                            <button type="submit" class="btn btn-primary mt-6"><i class="fa-solid fa-floppy-disk"></i> Save Banner Settings</button>
                        </form>
                    </div>
                    
                    <!-- Other Settings -->
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <form action="api.php" method="POST" class="bg-white p-6 rounded-lg border">
                            <input type="hidden" name="action" value="update_currency_rate">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800">Currency Settings</h3>
                            <div>
                                <label class="block mb-1.5 font-medium text-gray-700 text-sm">1 USD = ? BDT</label>
                                <input type="number" step="0.01" name="usd_to_bdt_rate" class="form-input" value="<?= htmlspecialchars($site_config['usd_to_bdt_rate'] ?? '110') ?>" placeholder="e.g., 110.50">
                            </div>
                            <button type="submit" class="btn btn-primary mt-4"><i class="fa-solid fa-floppy-disk"></i> Save Rate</button>
                        </form>

                        <form action="api.php" method="POST" class="bg-white p-6 rounded-lg border">
                            <input type="hidden" name="action" value="update_contact_info">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800">Help Center Contacts</h3>
                            <div class="space-y-4">
                                <div><label class="block mb-1.5 font-medium text-gray-700 text-sm">Phone Number</label><input type="text" name="phone_number" class="form-input" value="<?= htmlspecialchars($site_config['contact_info']['phone'] ?? '') ?>" placeholder="+8801234567890"></div>
                                <div><label class="block mb-1.5 font-medium text-gray-700 text-sm">WhatsApp Number</label><input type="text" name="whatsapp_number" class="form-input" value="<?= htmlspecialchars($site_config['contact_info']['whatsapp'] ?? '') ?>" placeholder="8801234567890 (without +)"></div>
                                <div><label class="block mb-1.5 font-medium text-gray-700 text-sm">Email Address</label><input type="email" name="email_address" class="form-input" value="<?= htmlspecialchars($site_config['contact_info']['email'] ?? '') ?>" placeholder="contact@example.com"></div>
                            </div>
                             <button type="submit" class="btn btn-primary mt-4"><i class="fa-solid fa-floppy-disk"></i> Save Contacts</button>
                        </form>
                    </div>

                    <!-- Password Section -->
                    <form action="api.php" method="POST" class="bg-white p-6 rounded-lg border">
                        <input type="hidden" name="action" value="update_admin_password">
                        <h3 class="text-lg font-semibold mb-4 text-gray-800">Change Admin Password</h3>
                        <div>
                            <label for="new_password_field" class="block mb-1.5 font-medium text-gray-700 text-sm">New Password</label>
                            <div class="relative">
                                <input type="password" id="new_password_field" name="new_password" class="form-input pr-16" placeholder="Leave blank to keep current password">
                                <button type="button" id="toggle_password_btn" class="absolute top-1/2 right-2 -translate-y-1/2 text-xs font-semibold text-gray-600 bg-gray-200 hover:bg-gray-300 px-2 py-1 rounded-md transition-colors">Show</button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-4"><i class="fa-solid fa-floppy-disk"></i> Save Password</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

<script>
const allStats = { today: <?= json_encode($stats_today) ?>, '7days': <?= json_encode($stats_7_days) ?>, '30days': <?= json_encode($stats_30_days) ?>, '6months': <?= json_encode($stats_6_months) ?>, 'all': <?= json_encode($stats_all_time) ?> };
function updateStatsDisplay(period) { const stats = allStats[period]; const container = document.getElementById('stats-display-container'); container.innerHTML = `<div class="bg-gray-50 p-4 rounded-lg flex items-center gap-4"><div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center"><i class="fa-solid fa-dollar-sign text-2xl text-blue-600"></i></div><div><p class="text-gray-500 text-sm font-medium">Revenue</p><p class="text-xl font-bold text-gray-800">৳${stats.total_revenue.toFixed(2)}</p></div></div><div class="bg-gray-50 p-4 rounded-lg flex items-center gap-4"><div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center"><i class="fa-solid fa-box-archive text-2xl text-purple-600"></i></div><div><p class="text-gray-500 text-sm font-medium">Orders</p><p class="text-xl font-bold text-gray-800">${stats.total_orders}</p></div></div><div class="bg-gray-50 p-4 rounded-lg flex items-center gap-4"><div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center"><i class="fa-solid fa-circle-check text-2xl text-green-600"></i></div><div><p class="text-gray-500 text-sm font-medium">Confirmed</p><p class="text-xl font-bold text-gray-800">${stats.confirmed_orders}</p></div></div><div class="bg-gray-50 p-4 rounded-lg flex items-center gap-4"><div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center"><i class="fa-solid fa-clock-rotate-left text-2xl text-yellow-600"></i></div><div><p class="text-gray-500 text-sm font-medium">Pending</p><p class="text-xl font-bold text-gray-800">${stats.pending_orders}</p></div></div><div class="bg-gray-50 p-4 rounded-lg flex items-center gap-4"><div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center"><i class="fa-solid fa-ban text-2xl text-red-600"></i></div><div><p class="text-gray-500 text-sm font-medium">Cancelled</p><p class="text-xl font-bold text-gray-800">${stats.cancelled_orders}</p></div></div>`; }
document.addEventListener('DOMContentLoaded', function() {
    const pricingType = document.getElementById('pricing-type'); if (pricingType) { const singlePriceContainer = document.getElementById('single-price-container'); const multiplePricingContainer = document.getElementById('multiple-pricing-container'); const addDurationBtn = document.getElementById('add-duration-btn'); const durationFields = document.getElementById('duration-fields'); pricingType.addEventListener('change', function() { if (this.value === 'single') { singlePriceContainer.classList.remove('hidden'); multiplePricingContainer.classList.add('hidden'); } else { singlePriceContainer.classList.add('hidden'); multiplePricingContainer.classList.remove('hidden'); if (durationFields.children.length === 0) addDurationField(); } }); addDurationBtn.addEventListener('click', addDurationField); function addDurationField() { const fieldGroup = document.createElement('div'); fieldGroup.className = 'flex items-center gap-2 mb-2'; fieldGroup.innerHTML = `<input type="text" name="durations[]" class="form-input" placeholder="Duration (e.g., 1 Year)" required><input type="number" name="duration_prices[]" step="0.01" class="form-input" placeholder="Price" required><button type="button" class="btn btn-danger btn-sm remove-duration-btn"><i class="fa-solid fa-trash-can"></i></button>`; durationFields.appendChild(fieldGroup); } durationFields.addEventListener('click', function(e) { if (e.target && e.target.closest('.remove-duration-btn')) { e.target.closest('.flex').remove(); } }); }

    const statsFilterContainer = document.getElementById('stats-filter-container'); if (statsFilterContainer) { updateStatsDisplay('all'); statsFilterContainer.addEventListener('click', function(e) { if (e.target.matches('.stats-filter-btn')) { this.querySelectorAll('.stats-filter-btn').forEach(btn => btn.classList.remove('active')); e.target.classList.add('active'); const period = e.target.dataset.period; updateStatsDisplay(period); } }); }
    const couponScope = document.getElementById('coupon_scope'); if (couponScope) { const categoryContainer = document.getElementById('scope_category_container'); const productContainer = document.getElementById('scope_product_container'); couponScope.addEventListener('change', function() { categoryContainer.classList.add('hidden'); productContainer.classList.add('hidden'); if (this.value === 'category') categoryContainer.classList.remove('hidden'); if (this.value === 'single_product') productContainer.classList.remove('hidden'); }); }

    const toggleBtn = document.getElementById('toggle_password_btn');
    const passwordField = document.getElementById('new_password_field');

    if (toggleBtn && passwordField) {
        toggleBtn.addEventListener('click', function() {
            const isPassword = passwordField.type === 'password';
            passwordField.type = isPassword ? 'text' : 'password';
            this.textContent = isPassword ? 'Hide' : 'Show';
        });
    }
});

function ordersManager() {
    return {
        allOrders: <?php echo json_encode($all_orders_data_raw); ?>,
        searchQuery: '',
        currentPage: 1,
        ordersPerPage: 20,
        
        get filteredOrders() {
            if (this.searchQuery.trim() === '') {
                return this.allOrders;
            }
            const query = this.searchQuery.toLowerCase().trim();
            return this.allOrders.filter(order => {
                const productNames = (order.items || []).map(item => item.name).join(' ').toLowerCase();
                const searchableText = `${order.order_id} ${order.customer.name} ${order.customer.phone} ${order.customer.email} ${productNames}`.toLowerCase();
                return searchableText.includes(query);
            });
        },
        
        get totalPages() {
            return Math.ceil(this.filteredOrders.length / this.ordersPerPage);
        },
        
        get paginatedOrders() {
            const start = (this.currentPage - 1) * this.ordersPerPage;
            const end = start + this.ordersPerPage;
            return this.filteredOrders.slice(start, end);
        },
        
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
        },
        
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },

        init() {
            this.$watch('searchQuery', () => {
                this.currentPage = 1;
            });
        }
    }
}

function hotDealsManager() {
    return {
        modal: {
            isOpen: false,
            type: 'product' // 'product' or 'custom'
        }
    }
}
</script>
</body>
</html>