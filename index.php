<?php

// index.php

// --- Helper Function to generate slugs (in case it's missing in JSON) ---
function slugify($text) {
    if (function_exists('iconv')) {
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    }
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    if (empty($text)) {
        return 'n-a-' . rand(100, 999);
    }
    return $text;
}

// --- Base Path (for subdirectory support) ---
define('BASE_PATH', rtrim(str_replace('index.php', '', $_SERVER['SCRIPT_NAME']), '/'));

// --- Load All Product and Category Data ---
$products_file_path = 'products.json';
if (!file_exists($products_file_path)) {
    file_put_contents($products_file_path, '[]');
}
$all_data = json_decode(file_get_contents($products_file_path), true);

// --- Load Coupon Data ---
$coupons_file_path = 'coupons.json';
if (!file_exists($coupons_file_path)) {
    file_put_contents($coupons_file_path, '[]');
}
$all_coupons_data = json_decode(file_get_contents($coupons_file_path), true);

// --- Load Site Config Data ---
$config_file_path = 'config.json';
if (!file_exists($config_file_path)) {
    file_put_contents($config_file_path, '{"hero_banner":[],"favicon":"","contact_info":{"phone":"","whatsapp":"","email":""},"admin_password":"password123", "usd_to_bdt_rate": 110, "site_logo":"", "hero_slider_interval": 5000, "hot_deals_speed": 40, "rupantorpay_api_key": ""}');
}
$site_config = json_decode(file_get_contents($config_file_path), true);

// ** NEW & ROBUST BANNER PATH LOGIC **
$hero_banner_paths_raw = $site_config['hero_banner'] ?? [];
$hero_banner_paths = array_map(function($path) {
    return rtrim(BASE_PATH, '/') . '/' . ltrim($path, '/');
}, $hero_banner_paths_raw);

$favicon_path = $site_config['favicon'] ?? '';
$contact_info = $site_config['contact_info'] ?? ['phone' => '', 'whatsapp' => '', 'email' => ''];
$usd_to_bdt_rate = $site_config['usd_to_bdt_rate'] ?? 110;
$site_logo_path = $site_config['site_logo'] ?? '';
$hero_slider_interval = $site_config['hero_slider_interval'] ?? 5000;
$hot_deals_speed = $site_config['hot_deals_speed'] ?? 40;


// --- Load Hot Deals Data ---
$hotdeals_file_path = 'hotdeals.json';
if (!file_exists($hotdeals_file_path)) {
    file_put_contents($hotdeals_file_path, '[]');
}
$all_hotdeals_data = json_decode(file_get_contents($hotdeals_file_path), true);


// --- Prepare Data ---
$all_categories = [];
$all_products_flat = [];
$products_by_category = [];
$product_slug_map = []; // For URL routing
$category_slug_map = []; // For URL routing
$static_pages = ['cart', 'checkout', 'order-history', 'products', 'about-us', 'privacy-policy', 'terms-and-conditions', 'refund-policy'];


if (is_array($all_data)) {
    foreach ($all_data as $category) {
        $category_slug = $category['slug'] ?? slugify($category['name']);
        $category_slug_map[$category_slug] = $category['name'];

        $all_categories[] = [
            'name' => $category['name'],
            'slug' => $category_slug,
            'icon' => $category['icon']
        ];

        if (isset($category['products']) && is_array($category['products'])) {
            $category_products_temp = [];
            foreach ($category['products'] as $product) {
                $product_slug = $product['slug'] ?? slugify($product['name']);
                $product['category'] = $category['name'];
                $product['category_slug'] = $category_slug;
                $product['slug'] = $product_slug;
                $all_products_flat[] = $product;
                $category_products_temp[] = $product;
                
                $product_slug_map[$category_slug . '/' . $product_slug] = $product['id'];
            }
            
            if (!empty($category_products_temp)) {
                $products_by_category[$category['name']] = $category_products_temp;
            }
        }
    }
}


// --- URL ROUTING LOGIC (PHP) ---
$request_path = trim($_GET['path'] ?? '', '/');
$path_parts = explode('/', $request_path);

// ** URL JUMP FIX: Determine the initial view on the server-side **
$initial_view = 'home'; 
$initial_params = new stdClass();

if (strpos($request_path, '#/') === 0) { // Handle hash-based URLs from payment_verify.php
    $hash_path = substr($request_path, 2);
    if ($hash_path === 'order-history') {
        $initial_view = 'orderHistory';
    }
} elseif ($request_path) {
    $view_map = [
        'order-history' => 'orderHistory', 'about-us' => 'aboutUs',
        'privacy-policy' => 'privacyPolicy', 'terms-and-conditions' => 'termsAndConditions',
        'refund-policy' => 'refundPolicy'
    ];
    $view_key = $path_parts[0];
    
    if (isset($product_slug_map[$request_path])) {
        // This is a product detail page, set view directly
        $initial_view = 'productDetail';
        $initial_params->productId = $product_slug_map[$request_path];
    } elseif ($path_parts[0] === 'products' && isset($path_parts[1], $path_parts[2]) && $path_parts[1] === 'category' && isset($category_slug_map[$path_parts[2]])) {
        // This is a category page
        $initial_view = 'products';
        $initial_params->filterType = 'category';
        $initial_params->filterValue = $category_slug_map[$path_parts[2]];
    } elseif (in_array($view_key, $static_pages) && !isset($path_parts[1])) {
        // This is a static page
        $initial_view = $view_map[$view_key] ?? $view_key;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submonth - Premium Digital Subscriptions</title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="Submonth is your trusted source for premium digital subscriptions and courses in Bangladesh. Get affordable access to tools like Canva Pro, ChatGPT Plus, and more.">
    <meta name="keywords" content="digital subscriptions, premium accounts, online courses, submonth, bangladesh, canva pro, chatgpt plus, affordable price">
    <!-- END SEO Meta Tags -->

    <?php if (!empty($favicon_path) && file_exists($favicon_path)): ?>
        <link rel="icon" type="image/png" href="<?= htmlspecialchars(BASE_PATH . '/' . $favicon_path) ?>">
        <link rel="apple-touch-icon" href="<?= htmlspecialchars(BASE_PATH . '/' . $favicon_path) ?>">
    <?php else: ?>
        <link rel="icon" type="image/png" href="https://i.postimg.cc/ncGxB1jm/IMG-20250919-WA0036.jpg">
        <link rel="apple-touch-icon" href="https://i.postimg.cc/ncGxB1jm/IMG-20250919-WA0036.jpg">
    <?php endif; ?>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=League+Spartan:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    
    <style>
        :root {
            --primary-color: #7C3AED;
            --primary-color-darker: #6D28D9;
            --primary-color-light: #F5F3FF;
            --strong-border-color: #C4B5FD;
        }
        [v-cloak] { display: none !important; }
        html {
            overflow-y: scroll; 
        }
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f9fafb;
        }
        h1, h2, h3, h4, .font-display {
            font-family: 'League Spartan', 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .hero-section { 
            margin: 1rem;
            position: relative;
        }
        
        .preserve-whitespace {
            white-space: pre-wrap;
        }
        
        /* --- CATEGORY CARD STYLES --- */
        .category-icon { 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; 
            cursor: pointer; 
            transition: all 0.2s ease; 
            text-decoration: none; 
            width: 72px; 
            height: 72px;
            padding: 0.5rem; 
            flex-shrink: 0; 
            border: 2px solid var(--strong-border-color);
            border-radius: 0.75rem; 
            background-color: #ffffff; 
        }
        .category-icon:hover { 
             border-color: var(--primary-color); 
        }
        .category-icon i { 
            font-size: 1.75rem;
            color: var(--primary-color); 
            margin-bottom: 0.25rem;
        }
        .category-icon span { 
            font-size: 0.7rem; 
            color: #374151; 
            font-weight: 500; 
            text-align: center; 
            line-height: 1.1; 
        }
        
        .category-scroll-container { 
            display: flex; 
            flex-wrap: nowrap; 
            gap: 1rem; 
            width: max-content;
            padding: 0 1rem;
        }
        .horizontal-scroll { overflow-x: auto; scrollbar-width: none; }
        .horizontal-scroll::-webkit-scrollbar { display: none; }
        .smooth-scroll { scroll-behavior: smooth; }
        
        @media (min-width: 768px) {
            .category-scroll-container { 
                gap: 2rem; /* 32px */
                padding: 0;
            }
            div[ref="categoryScroller"] { padding: 0; }
            .category-icon { 
                width: 90px;
                height: 90px;
            }
            .category-icon i { font-size: 2.5rem; }
            .category-icon span { font-size: 0.875rem; margin-top: 0.5rem; }
        }

        /* --- Other Styles --- */
        .product-card { 
            transition: all 0.2s ease; 
            border-width: 2px;
            border-color: #e5e7eb;
            box-shadow: none;
        }
        .product-card:hover { border-color: #d1d5db; }
        .product-card:active { transform: scale(0.98); filter: brightness(0.98); }
        .product-card { width: 170px; display: flex; flex-direction: column; flex-shrink: 0; border-radius: 0.75rem; overflow: hidden; position: relative; scroll-snap-align: start; cursor: pointer; background-color: white; }
        .product-scroll-container { display: flex; width: max-content; padding-left: 10px; padding-right: 10px; gap: 10px; }
        .product-card-image-container { aspect-ratio: 4 / 3; background-color: #f3f4f6; overflow: hidden; }
        .product-image { width: 100%; height: 100%; object-fit: cover; }
        .line-clamp-1 { overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 1; }
        .line-clamp-2 { overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .hot-deals-container {
            overflow: hidden;
            -webkit-mask-image: linear-gradient(to right, transparent 0%, white 10%, white 90%, transparent 100%);
            mask-image: linear-gradient(to right, transparent 0%, white 10%, white 90%, transparent 100%);
        }
        .hot-deals-scroller {
            display: flex;
            width: max-content;
            animation-name: scroll-anim;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
        }
        .hot-deal-card { width: 100px; margin: 0 8px; flex-shrink: 0; text-align: center; text-decoration: none; color: inherit; }
        .hot-deal-image-container { aspect-ratio: 4 / 3; border-radius: 0.75rem; overflow: hidden; margin-bottom: 0.5rem; border: 2px solid #e5e7eb; }
        .hot-deal-image { width: 100%; height: 100%; object-fit: cover; }
        .hot-deal-title { font-size: 0.75rem; font-weight: 500; color: #374151; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        @keyframes scroll-anim { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }
        @media (min-width: 768px) {
            .hero-section { aspect-ratio: 5 / 2; }
            .product-card { width: 280px; }
            .product-scroll-container { padding-left: 30px; padding-right: 30px; gap: 30px; }
            .hot-deal-card { width: 180px; margin: 0 14px; }
            .hot-deal-title { font-size: 0.875rem; }
            .hot-deals-container { -webkit-mask-image: linear-gradient(to right, transparent, #f9fafb 8%, #f9fafb 92%, transparent); mask-image: linear-gradient(to right, transparent, #f9fafb 8%, #f9fafb 92%, transparent); }
        }
        @media (max-width: 767px) { html { font-size: 80%; } .hero-section { aspect-ratio: 2 / 1; } #related-products-container > div:nth-of-type(n+3) { display: none; } }
        .feature-card { transition: all 0.3s ease; }
        .feature-card:hover { box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1); }
        .product-grid-card { display: flex; flex-direction: column; background-color: white; border-radius: 0.75rem; border-width: 2px; border-color: #e5e7eb; overflow: hidden; transition: all 0.3s ease; position: relative; cursor: pointer; box-shadow: none; }
        .product-grid-card:hover { border-color: #d1d5db; }
        .notification-badge { position: absolute; top: -2px; right: -4px; background-color: #ef4444; color: white; border-radius: 50%; width: 12px; height: 12px; display: flex; align-items: center; justify-content: center; font-size: 6px; font-weight: bold; line-height: 1; }
        .product-detail-title { font-size: 1.75rem; max-width: 25ch; }
        @media (min-width: 768px) {
            .product-detail-title { font-size: 2rem; }
            .product-detail-content { display: flex; flex-direction: row; align-items: flex-start; gap: 2rem;}
            .product-detail-image-container { flex-shrink: 0; position: relative; width: 50%; }
            .product-detail-info-container { flex-grow: 1; }
            .duration-button-selected::after { content: '✓'; position: absolute; top: -8px; right: -8px; font-size: 1rem; color: white; background-color: var(--primary-color); border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        }
        @media (max-width: 767px) {
            .product-detail-image-container { aspect-ratio: 1 / 1; }
            .duration-button-selected::after { content: '✓'; position: absolute; top: -8px; right: -8px; font-size: 0.8rem; color: white; background-color: var(--primary-color); border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        }
        .fab-icon { transition: transform 0.3s ease; }
    </style>
</head>
<body class="bg-gray-50 flex flex-col min-h-screen">
    
    <div id="app" v-cloak>
        <!-- Custom Modal Popup -->
        <div v-show="modal.visible" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4" @click.self="closeModal" style="display: none;">
            <div @click.stop class="bg-white rounded-lg shadow-xl w-full max-w-sm text-center p-6" v-if="modal.visible">
                <div class="mb-4">
                    <i class="fas text-5xl" :class="{ 'fa-check-circle text-green-500': modal.type === 'success', 'fa-exclamation-circle text-red-500': modal.type === 'error', 'fa-info-circle text-blue-500': modal.type === 'info' }"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">{{ modal.title }}</h3>
                <p class="text-gray-600 mb-6">{{ modal.message }}</p>
                <button @click="closeModal" class="w-full bg-[var(--primary-color)] text-white font-semibold py-2 px-4 rounded-lg hover:bg-[var(--primary-color-darker)] transition">
                    OK
                </button>
            </div>
        </div>

        <!-- Side Menu -->
        <div v-show="isSideMenuOpen" class="fixed inset-0 z-50 flex" style="display: none;">
            <div @click="isSideMenuOpen = false" v-show="isSideMenuOpen" class="fixed inset-0 bg-black bg-opacity-50"></div>
            <div v-show="isSideMenuOpen" class="relative w-72 max-w-xs bg-white h-full shadow-xl p-6">
                <button @click="isSideMenuOpen = false" class="absolute top-4 right-4 text-gray-500 hover:text-gray-800 text-2xl"><i class="fas fa-times"></i></button>
                <h2 class="text-2xl font-bold text-[var(--primary-color)] mb-8 font-display tracking-wider">Menu</h2>
                <nav class="flex flex-col space-y-4">
                    <a :href="basePath + '/'" @click.prevent="setView('home'); isSideMenuOpen = false;" class="text-lg text-gray-700 hover:text-[var(--primary-color)]">Home</a>
                    <a :href="basePath + '/about-us'" @click.prevent="setView('aboutUs'); isSideMenuOpen = false;" class="text-lg text-gray-700 hover:text-[var(--primary-color)]">About Us</a>
                    <a :href="basePath + '/privacy-policy'" @click.prevent="setView('privacyPolicy'); isSideMenuOpen = false;" class="text-lg text-gray-700 hover:text-[var(--primary-color)]">Privacy Policy</a>
                    <a :href="basePath + '/terms-and-conditions'" @click.prevent="setView('termsAndConditions'); isSideMenuOpen = false;" class="text-lg text-gray-700 hover:text-[var(--primary-color)]">Terms & Conditions</a>
                    <a :href="basePath + '/refund-policy'" @click.prevent="setView('refundPolicy'); isSideMenuOpen = false;" class="text-lg text-gray-700 hover:text-[var(--primary-color)]">Refund Policy</a>
                </nav>
                <hr class="my-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4 font-display tracking-wider">Categories</h3>
                <nav class="flex flex-col space-y-3">
                     <?php foreach ($all_categories as $category): ?>
                        <a :href="basePath + '/products/category/<?= htmlspecialchars($category['slug']) ?>'" @click.prevent="setView('products', { filterType: 'category', filterValue: '<?= htmlspecialchars($category['name']) ?>' }); isSideMenuOpen = false;" class="text-gray-600 hover:text-[var(--primary-color)]"><?= htmlspecialchars($category['name']) ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
        
        <!-- Header -->
        <header class="header flex justify-between items-center px-4 bg-white shadow-md sticky top-0 z-40 h-16 md:h-20">
            <!-- Mobile Header View -->
            <div class="flex items-center justify-between w-full md:hidden gap-2">
                <a :href="basePath + '/'" @click.prevent="setView('home')" class="logo flex-shrink-0">
                    <?php if (!empty($site_logo_path) && file_exists($site_logo_path)): ?>
                        <img src="<?= htmlspecialchars(BASE_PATH . '/' . $site_logo_path) ?>" alt="Submonth Logo" class="h-8">
                    <?php else: ?>
                        <img src="https://i.postimg.cc/gJRL0cdG/1758261543098.png" alt="Submonth Logo" class="h-8">
                    <?php endif; ?>
                </a>
                <form @submit.prevent="performSearch" class="relative flex-1 min-w-0">
                     <input type="text" v-model.lazy="searchQuery" placeholder="Search..." class="w-full py-2 pl-3 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-gray-400 h-9 text-sm" aria-label="Search mobile">
                    <div class="absolute top-2 bottom-2 right-8 w-px bg-gray-300"></div>
                    <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2" aria-label="Submit search mobile">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"></path>
                        </svg>
                    </button>
                </form>
                <div class="flex items-center gap-3">
                    <button @click="toggleCurrency()" class="icon text-gray-600 hover:text-[var(--primary-color)] cursor-pointer flex items-center gap-1 font-semibold">
                        <i class="fas fa-dollar-sign text-xl"></i>
                        <span class="text-sm">{{ currency }}</span>
                    </button>
                     <a :href="basePath + '/cart'" @click.prevent="setView('cart')" class="icon text-2xl text-gray-600 hover:text-[var(--primary-color)] cursor-pointer relative" aria-label="Shopping Cart">
                        <i class="fas fa-shopping-bag relative -top-0.5"></i>
                        <span v-show="cartCount > 0" class="notification-badge">{{ cartCount }}</span>
                    </a>
                    <button @click="isSideMenuOpen = !isSideMenuOpen" class="icon text-2xl text-gray-600 hover:text-[var(--primary-color)] cursor-pointer" aria-label="Open menu"><i class="fas fa-bars"></i></button>
                </div>
            </div>


            <!-- Desktop Header View -->
            <div class="hidden md:flex items-center w-full gap-5">
                <a :href="basePath + '/'" @click.prevent="setView('home')" class="logo flex-shrink-0 flex items-center text-gray-800 no-underline">
                     <?php if (!empty($site_logo_path) && file_exists($site_logo_path)): ?>
                        <img src="<?= htmlspecialchars(BASE_PATH . '/' . $site_logo_path) ?>" alt="Submonth Logo" class="h-9">
                    <?php else: ?>
                        <img src="https://i.postimg.cc/gJRL0cdG/1758261543098.png" alt="Submonth Logo" class="h-9">
                    <?php endif; ?>
                </a>
                <form @submit.prevent="performSearch" class="relative flex-1">
                    <input type="text" v-model.lazy="searchQuery" placeholder="Search for premium subscriptions, courses, and more..." class="w-full py-2.5 px-4 pr-12 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-gray-500 focus:border-gray-500 transition-colors text-gray-900 placeholder-gray-400" aria-label="Search">
                    <div class="absolute top-2.5 bottom-2.5 right-10 w-px bg-gray-300"></div>
                    <button type="submit" class="absolute right-3 top-1/2 transform -translate-y-1/2" aria-label="Submit search">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                      </svg>
                    </button>
                </form>
                <div class="flex-shrink-0 flex items-center gap-5">
                    <button @click="toggleCurrency()" class="icon text-xl text-gray-600 hover:text-[var(--primary-color)] cursor-pointer flex items-center gap-2 font-semibold">
                        <i class="fas fa-dollar-sign text-2xl pt-px"></i>
                        <span class="pt-px">{{ currency }}</span>
                    </button>
                    <a :href="basePath + '/products'" @click.prevent="setView('products')" class="icon text-2xl text-gray-600 hover:text-[var(--primary-color)] cursor-pointer" aria-label="All Products"><i class="fas fa-box-open"></i></a>
                    <a :href="basePath + '/cart'" @click.prevent="setView('cart')" class="icon text-2xl text-gray-600 hover:text-[var(--primary-color)] cursor-pointer relative" aria-label="Shopping Cart">
                        <i class="fas fa-shopping-bag relative -top-0.5"></i>
                        <span v-show="cartCount > 0" class="notification-badge">{{ cartCount }}</span>
                    </a>
                    <a :href="basePath + '/order-history'" @click.prevent="setView('orderHistory')" class="icon text-2xl text-gray-600 hover:text-[var(--primary-color)] cursor-pointer relative" aria-label="Order History">
                        <i class="fas fa-receipt relative -top-0.5"></i>
                        <span v-show="newNotificationCount > 0" class="notification-badge">{{ newNotificationCount }}</span>
                    </a>
                    <button @click="isSideMenuOpen = !isSideMenuOpen" class="icon text-2xl text-gray-600 hover:text-[var(--primary-color)] cursor-pointer" aria-label="Open menu"><i class="fas fa-bars"></i></button>
                </div>
            </div>
        </header>
        
        <main class="flex-grow">
            <div v-if="currentView === 'home'">
                
                <!-- HERO SECTION -->
                <section class="hero-section aspect-[2/1] md:aspect-[5/2] rounded-lg overflow-hidden" ref="heroSlider" @mouseenter="stopSlider" @mouseleave="startSlider">
                    <div class="relative w-full h-full">
                        <template v-if="heroSlides.hasImages">
                            <template v-for="(slide, index) in heroSlides.slides" :key="index">
                                <div v-show="heroSlides.activeSlide === index" class="absolute inset-0">
                                    <img :src="slide.url" alt="Promotional Banner" class="w-full h-full object-cover">
                                </div>
                            </template>
                        </template>
                        <template v-if="!heroSlides.hasImages">
                            <template v-for="(slide, index) in heroSlides.slides" :key="index">
                                <div v-show="heroSlides.activeSlide === index" class="absolute inset-0 flex items-center justify-center h-full w-full" :class="slide.bgColor">
                                    <span class="text-2xl md:text-4xl font-bold text-white/80 tracking-wider">{{ slide.text }}</span>
                                </div>
                            </template>
                        </template>
                    </div>
                    
                    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex space-x-2 z-10">
                        <template v-for="(slide, index) in heroSlides.slides" :key="index">
                            <button @click="heroSlides.activeSlide = index" :class="{'bg-white': heroSlides.activeSlide === index, 'bg-white/50': heroSlides.activeSlide !== index}" class="w-2.5 h-2.5 rounded-full transition"></button>
                        </template>
                    </div>
                </section>
                
                <section class="relative">
                    <div class="text-center mt-6 mb-6 md:mt-8 md:mb-8">
                        <h2 class="text-2xl font-bold text-gray-800 font-display tracking-wider">Product Categories</h2>
                    </div>
                     <div class="max-w-7xl mx-auto">
                        <div class="relative flex items-center justify-center gap-2 md:px-0">
                            <button @click="scrollCategories(-1)" class="hidden md:flex p-2 flex-shrink-0 z-10 items-center justify-center">
                                <i class="fas fa-chevron-left text-2xl text-gray-500 hover:text-[var(--primary-color)] transition-colors"></i>
                            </button>
                            <div ref="categoryScrollerWrapper" class="overflow-hidden">
                                <div class="horizontal-scroll smooth-scroll" ref="categoryScroller">
                                    <div class="category-scroll-container">
                                        <?php foreach ($all_categories as $category): ?>
                                            <a :href="basePath + '/products/category/<?= htmlspecialchars($category['slug']) ?>'" @click.prevent="setView('products', { filterType: 'category', filterValue: '<?= htmlspecialchars($category['name']) ?>' })" class="category-icon">
                                                <i class="<?= htmlspecialchars($category['icon']) ?>"></i>
                                                <span><?= htmlspecialchars($category['name']) ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <button @click="scrollCategories(1)" class="hidden md:flex p-2 flex-shrink-0 z-10 items-center justify-center">
                                <i class="fas fa-chevron-right text-2xl text-gray-500 hover:text-[var(--primary-color)] transition-colors"></i>
                            </button>
                        </div>
                    </div>
                </section>
                
                <!-- Hot Deals Section -->
                <?php if (!empty($all_hotdeals_data)): ?>
                <section class="py-6 md:py-8">
                    <div class="text-center mb-6 md:mb-8">
                        <h2 class="text-2xl font-bold font-display tracking-wider">Hot Deals</h2>
                    </div>
                    <div class="hot-deals-container">
                        <div class="hot-deals-scroller" style="animation-duration: <?= htmlspecialchars($hot_deals_speed) ?>s;">
                            <?php
                                $product_map_by_id = array_column($all_products_flat, null, 'id');
                                $hot_deals_to_render = [];

                                foreach ($all_hotdeals_data as $deal) {
                                    if (isset($product_map_by_id[$deal['productId']])) {
                                        $product = $product_map_by_id[$deal['productId']];
                                        $hot_deals_to_render[] = [
                                            'href' => BASE_PATH . '/' . ($product['category_slug'] ?? '') . '/' . ($product['slug'] ?? ''),
                                            'click_event' => "setView('productDetail', { productId: " . json_encode($product['id']) . " })",
                                            'image' => BASE_PATH . '/' . ($product['image'] ?: 'https://via.placeholder.com/120x120.png?text=No+Image'),
                                            'name' => !empty($deal['customTitle']) ? $deal['customTitle'] : $product['name']
                                        ];
                                    }
                                }
                            ?>
                            <?php if(!empty($hot_deals_to_render)): 
                                $duplicated_deals = array_merge($hot_deals_to_render, $hot_deals_to_render);
                                foreach ($duplicated_deals as $item): 
                            ?>
                                <a href="<?= htmlspecialchars($item['href']) ?>" @click.prevent="<?= htmlspecialchars($item['click_event']) ?>" class="hot-deal-card">
                                    <div class="hot-deal-image-container">
                                        <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="hot-deal-image">
                                    </div>
                                    <span class="hot-deal-title"><?= htmlspecialchars($item['name']) ?></span>
                                </a>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

                <?php foreach ($products_by_category as $category_name => $products): ?>
                <section class="py-6">
                    <div class="flex justify-between items-center mb-4 px-4 md:px-6">
                        <h2 class="text-2xl font-bold font-display tracking-wider"><?= htmlspecialchars($category_name) ?></h2>
                        <a :href="basePath + '/products/category/<?= htmlspecialchars($products[0]['category_slug'] ?? '') ?>'" @click.prevent="setView('products', { filterType: 'category', filterValue: '<?= htmlspecialchars($category_name) ?>' })" class="text-[var(--primary-color)] font-bold hover:text-[var(--primary-color-darker)] flex items-center text-lg">View all <span class="ml-2 text-2xl font-bold">&raquo;</span></a>
                    </div>
                    <div class="horizontal-scroll smooth-scroll">
                        <div class="product-scroll-container">
                            <?php foreach ($products as $product): ?>
                                <div class="product-card" @click.prevent="setView('productDetail', { productId: <?= htmlspecialchars(json_encode($product['id'])) ?> })">
                                    <a :href="basePath + '/<?= htmlspecialchars($product['category_slug'] ?? '') ?>/<?= htmlspecialchars($product['slug'] ?? '') ?>'" class="contents">
                                        <div class="product-card-image-container relative">
                                            <img src="<?= BASE_PATH ?>/<?= htmlspecialchars($product['image'] ?: 'https://via.placeholder.com/400x300.png?text=No+Image') ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image">
                                            <?php if (!empty($product['stock_out'])): ?>
                                                <div class="absolute top-2 right-2 bg-white text-red-600 text-xs font-bold px-3 py-1 rounded-full shadow-md">Stock Out</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="p-4 flex flex-col flex-grow">
                                            <h3 class="font-bold text-sm md:text-base mb-1 line-clamp-1 font-display tracking-wider"><?= htmlspecialchars($product['name']) ?></h3>
                                            <p class="text-gray-600 text-xs md:text-sm mb-2 line-clamp-2 preserve-whitespace"><?= htmlspecialchars($product['description']) ?></p>
                                            <div class="text-[var(--primary-color)] font-bold text-lg mb-2 mt-auto">{{ formatPrice(<?= $product['pricing'][0]['price'] ?>) }}</div>
                                            <button class="w-full text-[var(--primary-color)] bg-transparent hover:bg-violet-50 font-semibold py-1 px-2 rounded-lg transition md:py-1 md:text-base text-sm flex items-center justify-center gap-2 border-2 border-[var(--primary-color)]">
                                                View Details <i class="fas fa-arrow-up-right-from-square text-xs"></i>
                                            </button>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
                <?php endforeach; ?>

                <section class="why-choose-us px-4 py-6">
                    <h2 class="text-3xl font-bold text-center mb-12 font-display tracking-wider">Why Choose Us</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 max-w-7xl mx-auto">
                        <div class="feature-card bg-white p-4 rounded-xl text-center flex flex-col justify-center md:aspect-square border border-gray-200 shadow-sm">
                            <div class="icon bg-[var(--primary-color-light)] w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2"><i class="fas fa-dollar-sign text-2xl text-[var(--primary-color)]"></i></div>
                            <h3 class="text-lg font-bold mb-1 font-display tracking-wider">Affordable Price</h3>
                            <p class="text-sm text-gray-600 mt-2 md:text-center text-left">Get top-tier content without breaking the bank. Quality education for everyone.</p>
                        </div>
                        <div class="feature-card bg-white p-4 rounded-xl text-center flex flex-col justify-center md:aspect-square border border-gray-200 shadow-sm">
                            <div class="icon bg-[var(--primary-color-light)] w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2"><i class="fas fa-award text-2xl text-[var(--primary-color)]"></i></div>
                            <h3 class="text-lg font-bold mb-1 font-display tracking-wider">Premium Quality</h3>
                            <p class="text-sm text-gray-600 mt-2 md:text-center text-left">Expert-curated content to ensure the best learning experience and outcomes.</p>
                        </div>
                        <div class="feature-card bg-white p-4 rounded-xl text-center flex flex-col justify-center md:aspect-square border border-gray-200 shadow-sm">
                            <div class="icon bg-[var(--primary-color-light)] w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2"><i class="fas fa-shield-alt text-2xl text-[var(--primary-color)]"></i></div>
                            <h3 class="text-lg font-bold mb-1 font-display tracking-wider">Trusted</h3>
                            <p class="text-sm text-gray-600 mt-2 md:text-center text-left">Join thousands of satisfied learners on our platform, building skills and careers.</p>
                        </div>
                        <div class="feature-card bg-white p-4 rounded-xl text-center flex flex-col justify-center md:aspect-square border border-gray-200 shadow-sm">
                            <div class="icon bg-[var(--primary-color-light)] w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2"><i class="fas fa-lock text-2xl text-[var(--primary-color)]"></i></div>
                            <h3 class="text-lg font-bold mb-1 font-display tracking-wider">Secure Payment</h3>
                            <p class="text-sm text-gray-600 mt-2 md:text-center text-left">Your transactions are protected with encrypted payment gateways for peace of mind.</p>
                        </div>
                    </div>
                </section>
            </div>
            
            <div class="pb-16" v-else>
                
                <div v-if="currentView === 'products'" class="bg-white min-h-screen">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-12">
                        <h1 class="text-3xl font-bold text-gray-800 mb-8 font-display tracking-wider">{{ productsTitle }}</h1>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
                            <template v-for="product in filteredProducts" :key="product.id">
                                <div class="product-grid-card" @click.prevent="setView('productDetail', { productId: product.id })">
                                    <a :href="basePath + '/' + product.category_slug + '/' + product.slug" class="contents">
                                        <div class="product-card-image-container relative">
                                            <img :src="basePath + '/' + (product.image || 'https://via.placeholder.com/400x300.png?text=No+Image')" :alt="product.name" class="product-image">
                                            <div v-show="product.stock_out" class="absolute top-2 right-2 bg-white text-red-600 text-xs font-bold px-3 py-1 rounded-full shadow-md">Stock Out</div>
                                        </div>
                                        <div class="p-3 sm:p-4 flex flex-col flex-grow">
                                            <h3 class="text-sm md:text-base font-bold text-gray-800 mb-1 line-clamp-1 font-display tracking-wider">{{ product.name }}</h3>
                                            <p class="text-xs md:text-sm text-gray-600 mb-2 line-clamp-2 preserve-whitespace">{{ product.description }}</p>
                                            <p class="text-lg md:text-xl font-extrabold text-[var(--primary-color)] mt-auto">{{ formatPrice(product.pricing[0].price) }}</p>
                                            <div class="flex flex-row gap-2 mt-2">
                                                <button @click.stop.prevent="addToCart(product.id, 1)" class="w-full border-2 border-[var(--primary-color)] text-[var(--primary-color)] bg-transparent hover:bg-[var(--primary-color)] hover:text-white transition py-1.5 px-2 sm:py-2 rounded-md text-xs sm:text-sm font-semibold">Add to Cart</button>
                                                <button class="w-full bg-gray-200 text-gray-700 py-1.5 px-2 sm:py-2 rounded-md hover:bg-gray-300 transition text-xs sm:text-sm font-semibold">View Details</button>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                
                <div v-if="currentView === 'productDetail'">
                    <template v-if="selectedProduct">
                        <div class="bg-white min-h-screen" :key="selectedProduct.id">
                            <div class="max-w-6xl mx-auto px-6 sm:px-20 lg:px-[110px] pt-6 pb-12">
                                <div class="max-w-5xl mx-auto">
                                    <div class="product-detail-content">
                                        <div ref="imageContainer" class="product-detail-image-container rounded-lg shadow-lg overflow-hidden border">
                                            <div v-show="isImageLoading" class="absolute inset-0 bg-gray-200 rounded-lg animate-pulse"></div>
                                            <img :src="basePath + '/' + (selectedProduct.image || 'https://via.placeholder.com/400x400.png?text=No+Image')" 
                                                 :alt="selectedProduct.name" 
                                                 @load="isImageLoading = false" 
                                                 @error="isImageLoading = false"
                                                 :class="{'opacity-0': isImageLoading}"
                                                 class="w-full h-full object-cover rounded-lg transition-opacity duration-300">
                                        </div>
                                        <div ref="infoContainer" class="product-detail-info-container mt-6 md:mt-0">
                                            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                                <h1 class="product-detail-title font-bold text-gray-800 font-display tracking-wider">{{ selectedProduct.name }}</h1>
                                                <span v-show="!selectedProduct.stock_out" class="text-sm font-semibold text-green-600 whitespace-nowrap">[In Stock]</span>
                                                <span v-show="selectedProduct.stock_out" class="text-sm font-semibold text-red-600 whitespace-nowrap">[Stock Out]</span>
                                            </div>
                                            <p class="mt-2 text-gray-600 preserve-whitespace">{{ selectedProduct.description }}</p>
                                            <div class="mt-6">
                                                <span class="text-3xl font-bold text-[var(--primary-color)]">{{ selectedPriceFormatted }}</span>
                                            </div>
                                            <div class="mt-6" v-show="selectedProduct.pricing.length > 1 || selectedProduct.pricing[0].duration !== 'Default'">
                                                <label class="block text-sm font-medium text-gray-700 mb-3">Select an option</label>
                                                <div class="flex flex-wrap gap-3">
                                                    <template v-for="(p, index) in selectedProduct.pricing" :key="index">
                                                        <button type="button" @click="selectedDurationIndex = index" :class="{ 'border-[var(--primary-color)] text-[var(--primary-color)] font-bold duration-button-selected': selectedDurationIndex === index, 'border-gray-300 text-gray-700': selectedDurationIndex !== index }" class="relative py-2 px-4 border rounded-md text-sm flex items-center justify-center transition duration-button"><span>{{ p.duration }}</span></button>
                                                    </template>
                                                </div>
                                            </div>
                                            <div class="mt-8 flex">
                                                <div class="flex w-full flex-row gap-4">
                                                    <button @click="addToCart(selectedProduct.id, 1)" class="flex-1 whitespace-nowrap rounded-lg border-2 border-[var(--primary-color)] px-4 sm:px-8 py-3 text-base sm:text-lg font-semibold text-[var(--primary-color)] shadow-md transition-colors hover:bg-[var(--primary-color)] hover:text-white">Add to Cart</button>
                                                    <button :disabled="selectedProduct.stock_out" @click="buyNowAndCheckout(selectedProduct.id)" class="flex-1 whitespace-nowrap rounded-lg bg-[var(--primary-color)] px-4 sm:px-8 py-3 text-base sm:text-lg font-semibold text-white shadow-md transition-colors hover:bg-[var(--primary-color-darker)] disabled:cursor-not-allowed disabled:opacity-50">Buy Now</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-12">
                                        <div class="flex border-b justify-center">
                                            <button @click="activeTab = 'description'" :class="{ 'border-[var(--primary-color)] text-[var(--primary-color)]': activeTab === 'description', 'border-transparent text-gray-500': activeTab !== 'description' }" class="py-3 px-6 font-medium border-b-2">Description</button>
                                            <button @click="activeTab = 'reviews'" :class="{ 'border-[var(--primary-color)] text-[var(--primary-color)]': activeTab === 'reviews', 'border-transparent text-gray-500': activeTab !== 'reviews' }" class="py-3 px-6 font-medium border-b-2">Reviews</button>
                                        </div>
                                        <div class="pt-6 tab-content">
                                            <div v-show="activeTab === 'description'" class="w-full max-w-4xl mx-auto">
                                                <div class="text-gray-700 leading-relaxed text-justify preserve-whitespace" :class="{ 'line-clamp-4': !isDescriptionExpanded }" v-html="formattedLongDescription"></div>
                                                <button @click="isDescriptionExpanded = !isDescriptionExpanded" class="text-[var(--primary-color)] font-bold mt-2" v-if="selectedProduct.long_description && selectedProduct.long_description.length > 300">
                                                    <span v-show="!isDescriptionExpanded">See More</span>
                                                    <span v-show="isDescriptionExpanded" style="display: none;">See Less</span>
                                                </button>
                                            </div>
                                            <div v-show="activeTab === 'reviews'" class="w-full max-w-4xl mx-auto">
                                                <div @click="reviewModalOpen = true" class="flex items-center gap-4 p-2 mb-6 cursor-pointer">
                                                    <i class="fas fa-user-circle text-4xl text-gray-400"></i>
                                                    <div class="flex-1 p-3 bg-gray-100 rounded-xl text-gray-500 font-medium hover:bg-gray-200 transition">Write your review...</div>
                                                </div>
                                                <div class="space-y-4">
                                                    <template v-if="!selectedProduct.reviews || selectedProduct.reviews.length === 0">
                                                        <p class="text-center text-gray-500 py-4">No reviews yet. Be the first to write one!</p>
                                                    </template>
                                                    <template v-for="review in selectedProduct.reviews" :key="review.id">
                                                        <div class="flex items-start gap-4 py-4 border-b border-gray-200 last:border-b-0">
                                                            <i class="fas fa-user-circle text-4xl text-gray-400"></i>
                                                            <div class="flex-1">
                                                                <div class="flex items-center justify-between"><h4 class="font-bold text-gray-800 font-display tracking-wider">{{ review.name }}</h4></div>
                                                                <div class="flex items-center my-1"><template v-for="i in 5"><i class="fas fa-star" :class="i <= review.rating ? 'text-yellow-400' : 'text-gray-300'"></i></template></div>
                                                                <p class="text-gray-600">{{ review.comment }}</p>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-16 w-full flex flex-col items-center">
                                        <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-8 text-center font-display tracking-wider">Related Products</h2>
                                        <div id="related-products-container" class="inline-grid grid-cols-2 md:grid-cols-3 gap-4 md:gap-6">
                                            <template v-for="product in relatedProducts" :key="product.id">
                                                 <div @click.prevent="setView('productDetail', { productId: product.id })" class="bg-white rounded-lg border-2 border-gray-200 overflow-hidden transition hover:border-violet-300 flex flex-col cursor-pointer">
                                                    <a :href="basePath + '/' + product.category_slug + '/' + product.slug" class="contents">
                                                        <div class="product-card-image-container relative">
                                                            <img :src="basePath + '/' + (product.image || 'https://via.placeholder.com/400x300.png?text=No+Image')" :alt="product.name" class="product-image">
                                                            <div v-show="product.stock_out" class="absolute top-2 right-2 bg-white text-red-600 text-xs font-bold px-3 py-1 rounded-full shadow-md">Stock Out</div>
                                                        </div>
                                                        <div class="p-4 flex flex-col flex-grow">
                                                            <h3 class="font-bold text-sm mb-1 line-clamp-1 font-display tracking-wider">{{ product.name }}</h3>
                                                            <p class="text-xs text-gray-500 mb-2 line-clamp-2 preserve-whitespace">{{ product.description }}</p>
                                                            <p class="font-bold text-base text-[var(--primary-color)] mt-auto">{{ formatPrice(product.pricing[0].price) }}</p>
                                                        </div>
                                                    </a>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div v-show="reviewModalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.away="reviewModalOpen = false" style="display: none;">
                        <div @click.stop class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
                            <h3 class="text-xl font-bold mb-4 font-display tracking-wider">Write a Review</h3>
                            <div class="mb-4">
                                <label for="reviewerName" class="block text-sm font-medium text-gray-700 mb-1">Your Name</label>
                                <input type="text" id="reviewerName" v-model="newReview.name" placeholder="Enter your name" class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--primary-color)]">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Your Rating</label>
                                <div class="flex items-center gap-1" @mouseleave="hoverRating = 0">
                                    <template v-for="(star, index) in 5" :key="index"><button @click="newReview.rating = index + 1" @mouseenter="hoverRating = index + 1" class="text-2xl cursor-pointer transition"><i class="fas fa-star" :class="{'text-yellow-400': (hoverRating || newReview.rating) > index, 'text-gray-300': (hoverRating || newReview.rating) <= index}"></i></button></template>
                                </div>
                            </div>
                            <div class="mb-6">
                                <label for="reviewText" class="block text-sm font-medium text-gray-700 mb-1">Your Review</label>
                                <textarea id="reviewText" v-model="newReview.comment" placeholder="Share your thoughts..." class="w-full p-2 border border-gray-300 rounded-md" rows="4"></textarea>
                            </div>
                            <div class="flex justify-end gap-3">
                                <button @click="reviewModalOpen = false" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</button>
                                <button @click="submitReview()" class="px-6 py-2 bg-[var(--primary-color)] text-white font-semibold rounded-md hover:bg-[var(--primary-color-darker)]">Submit</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div v-if="currentView === 'cart'" class="bg-white min-h-screen">
                    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-12">
                        <h1 class="text-3xl font-bold text-gray-800 mb-8 font-display tracking-wider">Shopping Cart</h1>
                        <template v-if="cart.length === 0">
                            <div class="py-16 text-center">
                                <i class="fas fa-shopping-bag text-6xl text-gray-300 mb-4"></i>
                                <h3 class="text-2xl font-semibold text-gray-700 mb-2 font-display tracking-wider">Your cart is empty</h3>
                                <p class="text-gray-500 mb-6">Looks like you haven't added anything to your cart yet.</p>
                                <a :href="basePath + '/products'" @click.prevent="setView('products')" class="inline-block px-8 py-3 bg-[var(--primary-color)] text-white font-semibold rounded-lg shadow-md hover:bg-[var(--primary-color-darker)] transition">Browse Products</a>
                            </div>
                        </template>
                        <template v-if="cart.length > 0">
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                                <div class="lg:col-span-2 bg-white rounded-lg border-2 p-4">
                                    <ul class="">
                                        <template v-for="(cartItem, index) in cart" :key="cartItem.productId">
                                            <li class="py-6 flex items-start gap-4" :class="{ 'border-t border-gray-200': index > 0 }">
                                                <div class="flex-shrink-0 w-24 h-24 rounded-md flex items-center justify-center bg-gray-100 border"><img :src="basePath + '/' + (getProductById(cartItem.productId)?.image || '')" class="product-image rounded-md"></div>
                                                <div class="flex-1 flex flex-col">
                                                    <div>
                                                        <div class="flex justify-between">
                                                            <h3 class="text-lg font-semibold text-gray-800 font-display tracking-wider">{{ getProductById(cartItem.productId)?.name || 'Unknown Product' }}</h3>
                                                            <p class="text-lg font-bold text-[var(--primary-color)]">{{ formatPrice(getProductById(cartItem.productId)?.pricing[cartItem.durationIndex].price * cartItem.quantity) }}</p>
                                                        </div>
                                                        <p class="text-sm text-gray-500 mt-1">{{ 'Duration: ' + getProductById(cartItem.productId)?.pricing[cartItem.durationIndex].duration }}</p>
                                                        <p v-show="getProductById(cartItem.productId)?.stock_out" class="text-red-600 text-xs mt-2 font-semibold">This item is out of stock and will be excluded from checkout.</p>
                                                    </div>
                                                    <div class="mt-4 flex items-center justify-between">
                                                        <div class="flex items-center border rounded-md">
                                                            <button @click="updateCartQuantity(cartItem.productId, -1)" :disabled="cartItem.quantity <= 1" class="px-3 py-1 text-gray-600 disabled:opacity-50"><i class="fas fa-minus text-xs"></i></button>
                                                            <span class="px-4 py-1 border-l border-r">{{ cartItem.quantity }}</span>
                                                            <button @click="updateCartQuantity(cartItem.productId, 1)" :disabled="getProductById(cartItem.productId)?.stock_out" class="px-3 py-1 text-gray-600 disabled:opacity-50"><i class="fas fa-plus text-xs"></i></button>
                                                        </div>
                                                        <button @click="removeFromCart(cartItem.productId)" class="font-medium text-red-500 hover:text-red-700 text-sm flex items-center gap-1"><i class="fas fa-trash-alt"></i> Remove</button>
                                                    </div>
                                                </div>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                                <div class="lg:col-span-1">
                                    <div class="bg-white rounded-lg border-2 p-6 sticky top-28">
                                        <h2 class="text-xl font-semibold text-gray-900 mb-4 font-display tracking-wider">Order Summary</h2>
                                        <div class="space-y-3">
                                            <div class="flex justify-between text-gray-600"><span>Subtotal</span><span>{{ formatPrice(cartTotal) }}</span></div>
                                            <div class="flex justify-between text-gray-600"><span>Shipping</span><span>Free</span></div>
                                            <div class="pt-4 border-t flex justify-between text-xl font-bold text-gray-900"><span>Total</span><span>{{ formatPrice(cartTotal) }}</span></div>
                                        </div>
                                        <button @click="proceedToCheckout()" :disabled="!isCartCheckoutable" class="w-full mt-6 px-6 py-3 border border-transparent rounded-lg shadow-sm text-base font-medium text-white bg-[var(--primary-color)] hover:bg-[var(--primary-color-darker)] transition disabled:opacity-50 disabled:cursor-not-allowed">Proceed to Checkout</button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                
                <div v-if="currentView === 'checkout'" class="bg-white min-h-screen">
                    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-12">
                        <h1 class="text-3xl font-bold text-gray-800 mb-8 font-display tracking-wider">Secure Checkout</h1>
                        <!-- *** START: CHECKOUT FORM MODIFICATION *** -->
                        <form @submit.prevent="initiatePayment" id="checkout-form">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-start">
                                <div class="md:col-span-1 order-1 md:order-2">
                                    <div class="bg-white rounded-lg border-2 p-6 md:sticky md:top-28">
                                        <h2 class="text-xl font-semibold text-gray-900 mb-4 font-display tracking-wider">Order Summary</h2>
                                        <ul>
                                            <template v-for="(item, index) in checkoutItems" :key="item.productId">
                                                <li class="py-3 flex items-center gap-4" :class="{ 'border-t border-gray-200': index > 0 }">
                                                    <div class="flex-shrink-0 w-16 h-16 rounded-md flex items-center justify-center bg-gray-100 border"><img :src="basePath + '/' + (getProductById(item.productId)?.image || '')" class="product-image rounded-md"></div>
                                                    <div class="flex-1">
                                                        <p class="font-medium text-gray-800">{{ getProductById(item.productId)?.name }}</p>
                                                        <p class="text-sm text-gray-500">{{ 'Qty: ' + item.quantity }}</p>
                                                    </div>
                                                    <p class="font-semibold text-gray-800">{{ formatPrice(getProductById(item.productId)?.pricing[item.durationIndex].price * item.quantity) }}</p>
                                                </li>
                                            </template>
                                        </ul>
                                        <div class="mt-4 pt-4 border-t">
                                            <div class="flex justify-between text-gray-600 mb-2"><span>Subtotal</span><span>{{ formatPrice(checkoutTotals.subtotal) }}</span></div>
                                            <template v-if="appliedCoupon">
                                                <div class="flex justify-between text-green-600 mb-2 font-semibold">
                                                    <span>Discount ({{ appliedCoupon.code }} - {{ appliedCoupon.discount_percentage }}%)</span>
                                                    <span>{{ '-' + formatPrice(checkoutTotals.discount) }}</span>
                                                </div>
                                            </template>
                                            <div class="flex justify-between text-gray-600 mb-4"><span>Shipping</span><span>Free</span></div>
                                            <div class="flex justify-between text-xl font-bold text-gray-900 mb-6"><span>Total</span><span>{{ formatPrice(checkoutTotals.total) }}</span></div>
                                            <div class="mt-4">
                                                <label for="coupon" class="block text-sm font-medium text-gray-700 mb-1">Coupon Code</label>
                                                <div class="flex gap-2">
                                                    <input type="text" v-model="couponCode" @input="appliedCoupon = null; couponMessage = ''" placeholder="ENTER CODE" class="w-full px-3 py-2 border border-gray-300 rounded-md uppercase">
                                                    <button @click.prevent="applyCoupon" type="button" class="px-4 py-2 bg-gray-200 text-gray-700 font-semibold rounded-md hover:bg-gray-300">Apply</button>
                                                </div>
                                                <p v-show="couponMessage" class="mt-2 text-sm" :class="appliedCoupon ? 'text-green-600' : 'text-red-600'">{{ couponMessage }}</p>
                                            </div>
                                            <button form="checkout-form" type="submit" :disabled="checkoutItems.length === 0" class="w-full mt-6 px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-[var(--primary-color)] hover:bg-[var(--primary-color-darker)] disabled:opacity-50 hidden md:block">Proceed to Payment</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="md:col-span-2 space-y-8 order-2 md:order-1">
                                    <div class="bg-white rounded-lg border-2 p-6">
                                        <h2 class="text-xl font-semibold text-gray-900 mb-4 font-display tracking-wider">Billing Information</h2>
                                        <div class="grid grid-cols-2 gap-4 mb-4">
                                            <div>
                                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                                <input type="text" id="name" v-model="checkoutForm.name" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[var(--primary-color)] focus:border-[var(--primary-color)]">
                                            </div>
                                            <div>
                                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                                <input type="tel" id="phone" v-model="checkoutForm.phone" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[var(--primary-color)] focus:border-[var(--primary-color)]">
                                            </div>
                                        </div>
                                        <div>
                                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                                            <input type="email" id="email" v-model="checkoutForm.email" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[var(--primary-color)] focus:border-[var(--primary-color)]">
                                        </div>
                                    </div>

                                    <!-- *** THIS IS THE NEW PAYMENT SECTION *** -->
                                    <div class="bg-white rounded-lg border-2 p-6">
                                        <h2 class="text-xl font-semibold text-gray-900 mb-4 font-display tracking-wider">Payment Method</h2>
                                        <div class="flex items-center p-4 border rounded-lg bg-gray-50">
                                            <img src="https://rupantorpay.com/public/images/logo.png" alt="RupantorPay" class="h-8 mr-4">
                                            <p class="text-gray-700 font-medium">You will be redirected to RupantorPay to complete your payment securely.</p>
                                        </div>
                                        <div class="mt-6 space-y-3">
                                            <div class="flex items-center gap-2">
                                                <input type="checkbox" id="agree-terms" name="agree-terms" required class="h-4 w-4 rounded border-gray-300 text-[var(--primary-color)] focus:ring-[var(--primary-color)]">
                                                <label for="agree-terms" class="text-sm text-gray-700">I agree to the <a :href="basePath + '/terms-and-conditions'" @click.prevent="setView('termsAndConditions')" class="font-semibold text-[var(--primary-color)] hover:underline">Terms and Conditions</a></label>
                                            </div>
                                        </div>
                                    </div>
                                    <button form="checkout-form" type="submit" :disabled="checkoutItems.length === 0" class="w-full mt-6 px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-[var(--primary-color)] hover:bg-[var(--primary-color-darker)] disabled:opacity-50 block md:hidden">Proceed to Payment</button>
                                </div>
                            </div>
                        </form>
                        <!-- *** END: CHECKOUT FORM MODIFICATION *** -->
                    </div>
                </div>
                
                <div v-if="currentView === 'orderHistory'" class="bg-white min-h-screen">
                    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-12">
                        <h1 class="text-3xl font-bold text-gray-800 mb-8 font-display tracking-wider">Your Order History</h1>
                        
                        <template v-if="orderHistory.length === 0 && !isSearchingOrders">
                            <div class="py-16 text-center">
                                <i class="fas fa-receipt text-6xl text-gray-300 mb-4"></i>
                                <h3 class="text-2xl font-semibold text-gray-700 mb-2 font-display tracking-wider">You have no orders yet</h3>
                                <p class="text-gray-500 mb-6">Looks like you haven't placed any orders. Let's change that!</p>
                                <a :href="basePath + '/products'" @click.prevent="setView('products')" class="inline-block px-8 py-3 bg-[var(--primary-color)] text-white font-semibold rounded-lg shadow-md hover:bg-[var(--primary-color-darker)] transition">Start Shopping</a>
                            </div>
                        </template>
                        <div v-show="isSearchingOrders" class="text-center py-16">
                            <i class="fas fa-spinner animate-spin text-4xl text-[var(--primary-color)]"></i>
                            <p class="mt-4 text-gray-600">Loading your order history...</p>
                        </div>
                        <template v-if="orderHistory.length > 0 && !isSearchingOrders">
                            <div class="space-y-6">
                                <template v-for="(order, index) in orderHistory" :key="order.order_id">
                                    <div class="bg-white rounded-lg shadow-md overflow-hidden border">
                                        <div class="p-4 sm:p-6 bg-gray-50 flex flex-col sm:flex-row justify-between items-start sm:items-center">
                                            <div>
                                                <h3 class="text-lg font-bold text-gray-800 font-display tracking-wider">Order #<span>{{ order.order_id }}</span></h3>
                                                <p class="text-sm text-gray-500 mt-1">{{ 'Placed on ' + new Date(order.order_date).toLocaleDateString() }}</p>
                                            </div>
                                            <div class="mt-4 sm:mt-0 flex items-center gap-4">
                                                <span class="text-sm font-medium px-3 py-1 rounded-full" :class="{'bg-green-100 text-green-800': order.status === 'Confirmed', 'bg-yellow-100 text-yellow-800': order.status === 'Pending', 'bg-red-100 text-red-800': order.status === 'Cancelled', 'bg-blue-100 text-blue-800': order.status === 'Awaiting Payment'}">{{ order.status }}</span>
                                                <p class="text-xl font-bold text-[var(--primary-color)]">{{ formatPrice(order.totals.total) }}</p>
                                            </div>
                                        </div>
                                        <div class="p-4 sm:p-6">
                                            <div class="mb-4">
                                                <template v-for="item in order.items" :key="item.id">
                                                    <div class="flex items-center gap-4 py-2">
                                                        <div class="flex-shrink-0 w-12 h-12 rounded-md flex items-center justify-center bg-gray-100 border"><img :src="basePath + '/' + (getProductById(item.id)?.image || '')" class="product-image rounded-md"></div>
                                                        <p class="font-semibold text-gray-700">{{ item.name }}</p>
                                                        <p class="ml-auto text-gray-500">{{ 'Qty: ' + item.quantity }}</p>
                                                    </div>
                                                </template>
                                            </div>
                                            <button @click="openOrder = (openOrder === order.order_id ? null : order.order_id)" class="text-sm font-semibold text-[var(--primary-color)] hover:underline">
                                                <span v-show="openOrder !== order.order_id">View Details</span>
                                                <span v-show="openOrder === order.order_id" style="display: none;">Hide Details</span>
                                            </button>
                                            <div v-show="openOrder === order.order_id" class="mt-4 pt-4 border-t">
                                                <h4 class="font-semibold text-gray-800 mb-2 font-display tracking-wider">Customer Information</h4>
                                                <p class="text-sm text-gray-600">{{ 'Name: ' + order.customer.name }}</p>
                                                <p class="text-sm text-gray-600">{{ 'Email: ' + order.customer.email }}</p>
                                                <p class="text-sm text-gray-600">{{ 'Phone: ' + order.customer.phone }}</p>
                                                <p class="text-sm text-gray-600" v-if="order.payment.trx_id">{{ 'Transaction ID: ' + order.payment.trx_id }}</p>
                                                <p class="text-sm text-gray-600" v-if="order.payment.method">{{ 'Payment Method: ' + order.payment.method }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
                
                <!-- START: Static Page Views -->
                <div v-if="currentView === 'aboutUs'" class="bg-white min-h-screen">
                    <div class="container mx-auto max-w-4xl p-6 md:p-12">
                        <div class="space-y-8">
                            <h1 class="text-3xl md:text-4xl font-bold text-gray-800 text-center border-b pb-4">আমাদের সম্পর্কে (About Us) – Submonth</h1>
                            <p class="text-lg text-gray-700 leading-relaxed">Submonth হল একটি উদ্ভাবনী ডিজিটাল প্ল্যাটফর্ম, যেখানে আপনি পেতে পারেন শিক্ষনীয় এবং প্রাত্যহিক জীবনের জন্য গুরুত্বপূর্ণ বিভিন্ন ডিজিটাল পণ্য ও পরিষেবা।</p>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-800 mb-4">আমরা কী করি:</h2>
                                <ul class="list-disc list-inside space-y-2 text-gray-700 pl-4">
                                    <li><strong class="font-semibold">লাইভ কোর্স –</strong> স্কিল ডেভেলপমেন্টের জন্য আমাদের এক্সপার্টদের দ্বারা পরিচালিত লাইভ কোর্স।</li>
                                    <li><strong class="font-semibold">সাবস্ক্রিপশন সার্ভিস –</strong> ChatGPT Plus, Canva Pro, Envato Elements-এর মতো প্রিমিয়াম সাবস্ক্রিপশন সহজ ও সাশ্রয়ী দামে।</li>
                                    <li><strong class="font-semibold">সফটওয়্যার –</strong> প্রয়োজনীয় ইউটিলিটি সফটওয়্যার, প্রোডাক্টিভিটি টুলস ইত্যাদি।</li>
                                    <li><strong class="font-semibold">ইবুক –</strong> ব্যাবসা, মার্কেটিং, ফ্রিল্যান্সিং ও স্কিল ডেভেলপমেন্ট ভিত্তিক ইবুক।</li>
                                    <li><strong class="font-semibold">টেমপ্লেট –</strong> ডিজাইন, প্রেজেন্টেশন, মার্কেটিং বা সোশ্যাল মিডিয়ার জন্য কাস্টম টেমপ্লেট।</li>
                                </ul>
                            </div>
                            <div class="pt-2">
                                <h2 class="text-2xl font-bold text-gray-800 mb-3">আমাদের মিশন:</h2>
                                <p class="text-lg text-gray-700">"প্রযুক্তিকে কাজে লাগিয়ে মানুষের জীবন সহজ করা এবং ডিজিটাল বাংলাদেশ গঠনে অবদান রাখা।"</p>
                            </div>
                            <div class="pt-2">
                                <h2 class="text-2xl font-bold text-gray-800 mb-3">আমাদের লক্ষ্য:</h2>
                                <ul class="list-disc list-inside space-y-2 text-gray-700 pl-4">
                                    <li>সাশ্রয়ী দামে মানসম্পন্ন ডিজিটাল পণ্য</li>
                                    <li>নতুনদের জন্য সহজ শেখার সুযোগ</li>
                                    <li>সময়োপযোগী ও নিরাপদ সাপোর্ট সিস্টেম</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div v-if="currentView === 'privacyPolicy'" class="bg-white min-h-screen">
                    <div class="container mx-auto max-w-4xl p-6 md:p-12">
                        <div class="space-y-8">
                            <h1 class="text-3xl md:text-4xl font-bold text-gray-800 text-center border-b pb-4">Privacy Policy</h1>
                            <p class="text-center text-gray-600">Submonth-এ আমরা আপনার গোপনীয়তাকে অত্যন্ত গুরুত্ব দিয়ে থাকি। আমাদের কাছে আপনি যেসব তথ্য প্রদান করেন, তা নিরাপদ রাখার জন্য আমরা প্রতিশ্রুতিবদ্ধ।</p>
                            <div class="space-y-6">
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-800 mb-2">১. আমরা যেসব তথ্য সংগ্রহ করি:</h2>
                                    <p class="text-gray-700">আপনার অর্ডার প্রসেস এবং উন্নত সেবা প্রদানের জন্য আমরা সাধারণত নিম্নলিখিত তথ্য সংগ্রহ করি: নাম, ইমেইল, ফোন নম্বর এবং পেমেন্ট তথ্য।</p>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-800 mb-2">২. তথ্যের ব্যবহার:</h2>
                                     <ul class="list-disc list-inside space-y-2 text-gray-700 pl-4">
                                        <li>আপনার অর্ডার সম্পাদন করা, পণ্যের এক্সেস প্রদান, এবং কাস্টমার সাপোর্টে সহায়তা করার জন্য।</li>
                                        <li>আপনার সম্মতিতে আপনাকে আমাদের নতুন অফার, নতুন পণ্য ও বিভিন্ন আপডেট পাঠানোর জন্য।</li>
                                    </ul>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-800 mb-2">৩. তৃতীয় পক্ষের সাথে তথ্য ভাগাভাগি:</h2>
                                     <p class="text-gray-700">আমরা আপনার কোনো ব্যক্তিগত তথ্য তৃতীয় পক্ষের সাথে বিক্রি বা শেয়ার করি না। তবে, নির্দিষ্ট প্রযুক্তিগত (যেমন পেমেন্ট গেটওয়ে) এবং আইনি পরিস্থিতিতে সীমাবদ্ধ তথ্য শেয়ার করা বাধ্যতামূলক হতে পারে।</p>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-800 mb-2">৪. তথ্য সুরক্ষা:</h2>
                                     <ul class="list-disc list-inside space-y-2 text-gray-700 pl-4">
                                       <li>আপনার তথ্য সুরক্ষিত রাখতে আমরা আধুনিক এনক্রিপশন প্রযুক্তি ও সুরক্ষিত সার্ভার ব্যবহার করি।</li>
                                       <li>আপনি যেকোনো সময় আপনার তথ্য সংশোধন বা আমাদের ডেটাবেস থেকে মুছে ফেলার জন্য অনুরোধ করতে পারেন।</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="currentView === 'termsAndConditions'" class="bg-white min-h-screen">
                     <div class="container mx-auto max-w-4xl p-6 md:p-12">
                        <div class="space-y-8">
                            <h1 class="text-3xl md:text-4xl font-bold text-gray-800 text-center border-b pb-4">Terms and Conditions</h1>
                            <p class="text-center text-gray-600">এই শর্তাবলীতে আপনাকে স্বাগতম। Submonth পরিচালিত ওয়েবসাইট, পণ্য এবং পরিষেবাগুলোর ব্যবহার করার পূর্বে নিচের শর্তগুলো মনোযোগসহকারে পড়ে নিন।</p>
                            <div class="space-y-6">
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-800 mb-2">১. পরিষেবার গ্রহণযোগ্যতা:</h2>
                                    <ul class="list-disc list-inside space-y-2 text-gray-700 pl-4">
                                        <li>আপনি যদি ১৮ বছর বা তদূর্ধ্ব হন বা আপনার অভিভাবকের অনুমতি নিয়ে ওয়েবসাইটটি ব্যবহার করেন, তাহলেই আপনি আমাদের পরিষেবা গ্রহণ করতে পারবেন।</li>
                                        <li>আমাদের কোনো কোর্স, ইবুক, সফটওয়্যার, বা টেমপ্লেট অবৈধ বা অননুমোদিত কাজে ব্যবহার করা যাবে না।</li>
                                    </ul>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-800 mb-2">২. ডিজিটাল পণ্যের মালিকানা:</h2>
                                     <ul class="list-disc list-inside space-y-2 text-gray-700 pl-4">
                                        <li>আপনি Submonth-এর কাছ থেকে কোনো পণ্য ক্রয়ের মাধ্যমে সেটির কপিরাইট বা মালিকানা লাভ করছেন না।</li>
                                        <li>প্রতিটি পণ্য বা কোর্স শুধুমাত্র আপনার ব্যক্তিগত ব্যবহারের জন্য বরাদ্দ, তা অন্য কারো সাথে শেয়ার, বিক্রি বা বিতরণ করা যাবে না।</li>
                                    </ul>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-800 mb-2">৩. পেমেন্ট ও অর্ডার প্রসেস:</h2>
                                     <ul class="list-disc list-inside space-y-2 text-gray-700 pl-4">
                                        <li>আপনি আমাদের ওয়েবসাইটে পেমেন্ট সম্পন্ন করার পর সংশ্লিষ্ট পণ্য বা সেবার লিংক বা এক্সেস তথ্য ইমেইলের মাধ্যমে বা অ্যাকাউন্টে সরবরাহ করা হবে।</li>
                                        <li>কোনো কারণে যদি পণ্য সরবরাহে বিলম্ব হয়, আমরা যথাযথভাবে আপনাকে অবহিত করবো।</li>
                                    </ul>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-800 mb-2">৪. আচরণবিধি:</h2>
                                     <ul class="list-disc list-inside space-y-2 text-gray-700 pl-4">
                                       <li>Submonth-এর কোর্স বা লাইভ সেশনে কোনো প্রকার অপমানজনক ভাষা, হেনস্তা, বা অবাঞ্চিত আচরণ গ্রহণযোগ্য নয়।</li>
                                       <li>যেকোনো সন্দেহজনক বা অসৎ আচরণের জন্য আপনার অ্যাক্সেস সাময়িক বা স্থায়ীভাবে বাতিল করা হতে পারে।</li>
                                    </ul>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-800 mb-2">৫. পরিবর্তন ও আপডেট:</h2>
                                    <p class="text-gray-700">Submonth যে কোনো সময় এই শর্তাবলী পরিবর্তন বা আপডেট করার অধিকার রাখে। নতুন শর্তাবলী আমাদের ওয়েবসাইটে প্রকাশের মাধ্যমে কার্যকর হবে।</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="currentView === 'refundPolicy'" class="bg-white min-h-screen">
                    <div class="container mx-auto max-w-4xl p-6 md:p-12">
                        <div class="space-y-8">
                            <h1 class="text-3xl md:text-4xl font-bold text-gray-800 text-center border-b pb-4">Refund Policy</h1>
                            <p class="text-center text-gray-600">আমরা গ্রাহক সন্তুষ্টিকে সর্বোচ্চ গুরুত্ব দিই। তবে আমাদের পণ্য ডিজিটাল হওয়ায় রিফান্ড নীতির জন্য নির্দিষ্ট নিয়ম রয়েছে:</p>
                            <div class="space-y-6">
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-800 mb-2">১. ফেরতযোগ্য পণ্য:</h2>
                                    <p class="text-gray-700">যদি আপনি ভুল ডিজিটাল পণ্য পান বা লিংকে প্রবেশ করতে না পারেন এবং আমাদের সাপোর্ট টিম আপনার সমস্যার সমাধান ৭২ ঘন্টার মধ্যে করতে ব্যর্থ হয়, তাহলে আপনি সম্পূর্ণ রিফান্ডের জন্য আবেদন করতে পারেন।</p>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-800 mb-2">২. ফেরতযোগ্য নয় এমন পণ্য:</h2>
                                     <ul class="list-disc list-inside space-y-2 text-gray-700 pl-4">
                                        <li>আপনি যদি ইবুক সফলভাবে ডাউনলোড করে ফেলেন, লাইভ কোর্সে অংশগ্রহণ করেন বা কোনো সফটওয়্যার একটিভ করেন, তাহলে সেই পণ্যটি ফেরতযোগ্য বলে গণ্য হবে না।</li>
                                        <li>যেকোনো সাবস্ক্রিপশন চালু হওয়ার পর তা বাতিল করে রিফান্ড চাওয়া যাবে না।</li>
                                    </ul>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-800 mb-2">৩. রিফান্ড প্রসেস:</h2>
                                     <ul class="list-disc list-inside space-y-2 text-gray-700 pl-4">
                                        <li>রিফান্ডের জন্য আপনাকে আমাদের অফিসিয়াল WhatsApp নম্বরে যোগাযোগ করতে হবে: 
                                            <a href="https://wa.me/<?= htmlspecialchars($contact_info['whatsapp']) ?>" target="_blank" class="text-[var(--primary-color)] font-semibold hover:underline">+<?= htmlspecialchars($contact_info['phone']) ?></a>
                                        </li>
                                        <li>আপনার আবেদন রিভিউ করে ২-৩ কার্যদিবসের মধ্যে আপনার টাকা পেমেন্টকৃত মাধ্যমে ফেরত পাঠানো হবে।</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- END: Static Page Views -->
            </div>
        </main>

        <!-- Decorated Review Section - ONLY ON HOME PAGE -->
        <div v-show="currentView === 'home'">
            <section class="py-16 sm:py-24">
                <div class="max-w-2xl mx-auto text-center px-6">
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">
                        Your Opinion Matters
                    </h2>
                    <p class="mt-2 text-base text-slate-600">
                        Share your experience on Trustpilot.
                    </p>
                    <div class="mt-8">
                        <div class="trustpilot-widget inline-block" data-locale="en-US" data-template-id="56278e9abfbbba0bdcd568bc" data-businessunit-id="68cd3e85a5e773033d7242cf" data-style-height="52px" data-style-width="100%" data-token="4607939e-09dd-4f65-8fed-06bda9352f4e">
                          <a href="https://www.trustpilot.com/review/submonth.com" target="_blank" rel="noopener">Trustpilot</a>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- New Footer Section (Visible only on Home Page) -->
        <div v-show="currentView === 'home'">
            <footer class="bg-slate-900">
                <div class="max-w-4xl mx-auto px-6 sm:px-8 py-12">
                    <div class="space-y-8 text-center">
                        <div class="space-y-4">
                            <div>
                                <a :href="basePath + '/'" @click.prevent="setView('home')" class="inline-block text-2xl font-bold text-slate-100">Submonth</a>
                                <p class="text-sm text-slate-400 max-w-sm mx-auto">
                                    The Digital Product Store
                                </p>
                            </div>
                            <div class="pt-2">
                                <form class="flex gap-2 max-w-md mx-auto">
                                    <input type="email" placeholder="Enter your email" class="flex-1 w-full min-w-0 px-3 py-2 bg-slate-800 border border-slate-600 rounded-md text-sm shadow-sm placeholder-slate-400 text-white focus:outline-none focus:border-[var(--primary-color)] focus:ring-1 focus:ring-[var(--primary-color)]" required>
                                    <button type="submit" class="bg-[var(--primary-color)] hover:bg-[var(--primary-color-darker)] text-white font-semibold px-3 sm:px-4 py-2 rounded-md text-sm transition-colors duration-300 flex-shrink-0">Subscribe</button>
                                </form>
                            </div>
                        </div>

                        <nav>
                            <div class="overflow-x-auto no-scrollbar pb-2">
                                <ul class="inline-flex flex-nowrap items-center whitespace-nowrap gap-x-6 sm:gap-x-8 text-sm text-slate-400">
                                    <li><a :href="basePath + '/'" @click.prevent="setView('home')" class="hover:text-violet-400 hover:underline">Home</a></li>
                                    <li><a :href="basePath + '/about-us'" @click.prevent="setView('aboutUs')" class="hover:text-violet-400 hover:underline">About Us</a></li>
                                    <li><a :href="basePath + '/privacy-policy'" @click.prevent="setView('privacyPolicy')" class="hover:text-violet-400 hover:underline">Privacy Policy</a></li>
                                    <li><a :href="basePath + '/terms-and-conditions'" @click.prevent="setView('termsAndConditions')" class="hover:text-violet-400 hover:underline">Terms & Conditions</a></li>
                                    <li><a :href="basePath + '/refund-policy'" @click.prevent="setView('refundPolicy')" class="hover:text-violet-400 hover:underline">Refund Policy</a></li>
                                </ul>
                            </div>
                        </nav>

                        <div class="pt-2">
                            <p class="text-xs text-slate-500">&copy; <span id="current-year-footer"></span> Submonth, Inc. All rights reserved.</p>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
        
        <nav class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200 flex justify-around items-center h-16 shadow-[0_-2px_5px_rgba(0,0,0,0.05)]">
            <a :href="basePath + '/'" @click.prevent="setView('home')" class="flex flex-col items-center justify-center transition w-full" :class="currentView === 'home' ? 'text-[var(--primary-color)]' : 'text-gray-500'"><i class="fas fa-home text-2xl"></i><span class="text-xs mt-1">Home</span></a>
            <a :href="basePath + '/products'" @click.prevent="setView('products')" class="flex flex-col items-center justify-center transition w-full" :class="currentView === 'products' ? 'text-[var(--primary-color)]' : 'text-gray-500'"><i class="fas fa-box-open text-2xl"></i><span class="text-xs mt-1">Products</span></a>
            <a :href="basePath + '/order-history'" @click.prevent="setView('orderHistory')" class="relative flex flex-col items-center justify-center transition w-full" :class="currentView === 'orderHistory' ? 'text-[var(--primary-color)]' : 'text-gray-500'">
                <div class="relative">
                    <i class="fas fa-receipt text-2xl"></i>
                    <span v-show="newNotificationCount > 0" class="notification-badge" style="top: -2px; right: -8px;">{{ newNotificationCount }}</span>
                </div>
                <span class="text-xs mt-1">Orders</span>
            </a>
        </nav>
        
        <div class="fixed bottom-20 md:bottom-6 right-4 z-40">
            <div v-show="fabOpen" class="flex flex-col items-center space-y-3 mb-3" style="display: none;">
                <a href="tel:<?= htmlspecialchars($contact_info['phone']) ?>" class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-lg text-[var(--primary-color)] border"><i class="fas fa-phone-alt text-xl transform -scale-x-100"></i></a>
                <a href="https://wa.me/<?= htmlspecialchars($contact_info['whatsapp']) ?>" target="_blank" class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-lg text-green-500 border"><i class="fab fa-whatsapp text-2xl"></i></a>
                <a href="mailto:<?= htmlspecialchars($contact_info['email']) ?>" class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-lg text-red-500 border"><i class="fas fa-envelope text-xl"></i></a>
            </div>
            <button @click="fabOpen = !fabOpen" class="flex flex-col items-center text-gray-700">
                <div class="w-14 h-14 bg-[var(--primary-color)] text-white rounded-full flex items-center justify-center shadow-lg"><i class="fas fa-headset text-2xl fab-icon" :class="{'rotate-45': fabOpen}"></i></div>
                <span class="text-xs font-semibold mt-2">Need Help?</span>
            </button>
        </div>
    </div>

    <!-- *** START: VUE SCRIPT MODIFICATION *** -->
    <script>
        const { createApp, nextTick } = Vue;

        createApp({
            data() {
                return {
                    // Initial data loaded from PHP
                    basePath: '<?= BASE_PATH ?>',
                    allProducts: <?= json_encode($all_products_flat) ?>,
                    allCoupons: <?= json_encode($all_coupons_data) ?>,
                    usdRate: <?= $usd_to_bdt_rate ?>,
                    
                    // App State
                    currentView: '<?= $initial_view ?>',
                    previousView: 'home',
                    isSideMenuOpen: false,
                    fabOpen: false,
                    selectedProduct: null,
                    isImageLoading: true,
                    cart: [],
                    orderHistory: [],
                    isSearchingOrders: false,
                    newNotificationCount: 0,
                    
                    // Product/Filter State
                    productsTitle: 'All Products',
                    productFilter: { filterType: null, filterValue: null },
                    searchQuery: '',
                    selectedDurationIndex: 0,
                    activeTab: 'description',
                    isDescriptionExpanded: false,
                    
                    // Checkout State
                    checkoutItems: [],
                    checkoutForm: { name: '', phone: '', email: ''},
                    couponCode: '',
                    appliedCoupon: null,
                    couponMessage: '',
                    
                    // Review State
                    reviewModalOpen: false,
                    newReview: { name: '', rating: 0, comment: '' },
                    hoverRating: 0,

                    // Modal State
                    modal: { visible: false, title: '', message: '', type: 'info', onOk: null },

                    // UI State
                    currency: 'BDT',
                    openOrder: null,

                    // Hero Slider State
                    heroSlides: {
                        slides: [],
                        activeSlide: 0,
                        hasImages: false,
                        interval: null,
                        sliderInterval: <?= $hero_slider_interval ?>,
                    },
                    resizeObserver: null
                }
            },
            computed: {
                cartCount() {
                    return this.cart.reduce((total, item) => total + item.quantity, 0);
                },
                cartTotal() {
                    return this.cart.reduce((total, item) => {
                        const product = this.getProductById(item.productId);
                        if (product) {
                            return total + (product.pricing[item.durationIndex].price * item.quantity);
                        }
                        return total;
                    }, 0);
                },
                isCartCheckoutable() {
                    if (this.cart.length === 0) return false;
                    return this.cart.some(cartItem => {
                        const product = this.getProductById(cartItem.productId);
                        return product && !product.stock_out;
                    });
                },
                filteredProducts() {
                    if (this.searchQuery.trim() !== '') {
                        this.productsTitle = `Search Results for "${this.searchQuery.trim()}"`;
                        const query = this.searchQuery.trim().toLowerCase();
                        return this.allProducts.filter(p => p.name.toLowerCase().includes(query) || p.description.toLowerCase().includes(query));
                    }
                    if (this.productFilter.filterType === 'category') {
                        this.productsTitle = `All ${this.productFilter.filterValue}`;
                        return this.allProducts.filter(p => p.category === this.productFilter.filterValue);
                    }
                    this.productsTitle = 'All Products';
                    return this.allProducts;
                },
                relatedProducts() {
                    if (!this.selectedProduct) return [];
                    const limit = window.innerWidth < 768 ? 2 : 3;
                    return this.allProducts
                        .filter(p => p.category === this.selectedProduct.category && p.id !== this.selectedProduct.id)
                        .slice(0, limit);
                },
                selectedPrice() {
                    if (!this.selectedProduct) return 0;
                    return this.selectedProduct.pricing[this.selectedDurationIndex].price;
                },
                selectedPriceFormatted() {
                    return this.formatPrice(this.selectedPrice);
                },
                formattedLongDescription() {
                    if (!this.selectedProduct || !this.selectedProduct.long_description) return '';
                    return this.selectedProduct.long_description
                        .replace(/\*\*(.*?)\*\*/gs, '<strong>$1</strong>')
                        .replace(/\n/g, '<br>');
                },
                checkoutTotals() {
                    const subtotal = this.checkoutItems.reduce((total, item) => {
                        const product = this.getProductById(item.productId);
                        return product ? total + (product.pricing[item.durationIndex].price * item.quantity) : total;
                    }, 0);

                    let discount = 0;
                    if (this.appliedCoupon) {
                        let eligibleSubtotal = 0;
                        const coupon = this.appliedCoupon;

                        if (!coupon.scope || coupon.scope === 'all_products') {
                            eligibleSubtotal = subtotal;
                        } else if (coupon.scope === 'category') {
                            this.checkoutItems.forEach(item => {
                                const product = this.getProductById(item.productId);
                                if (product && product.category === coupon.scope_value) {
                                    eligibleSubtotal += product.pricing[item.durationIndex].price * item.quantity;
                                }
                            });
                        } else if (coupon.scope === 'single_product') {
                            this.checkoutItems.forEach(item => {
                                if (item.productId == coupon.scope_value) {
                                    eligibleSubtotal += this.getProductById(item.productId)?.pricing[item.durationIndex].price * item.quantity;
                                }
                            });
                        }
                        discount = eligibleSubtotal * (coupon.discount_percentage / 100);
                    }
                    return { subtotal, discount, total: Math.max(0, subtotal - discount) };
                }
            },
            watch: {
                searchQuery(newValue) {
                    if (newValue.trim() && this.currentView !== 'products') {
                        this.setView('products');
                    }
                },
                selectedProduct(newProduct) {
                    if (newProduct && this.currentView === 'productDetail') {
                        nextTick(() => { this.setupResizeObserver(); });
                    } else {
                        this.disconnectResizeObserver();
                    }
                }
            },
            methods: {
                // --- UI & Formatting ---
                formatPrice(bdtPrice) {
                    if (this.currency === 'USD') {
                        if (!bdtPrice || !this.usdRate) return '$0.00';
                        const usdPrice = bdtPrice / this.usdRate;
                        return '$' + usdPrice.toFixed(2);
                    }
                    return '৳' + Number(bdtPrice).toFixed(2);
                },
                toggleCurrency() {
                    this.currency = (this.currency === 'BDT') ? 'USD' : 'BDT';
                    localStorage.setItem('submonthCurrency', this.currency);
                },
                showModal(title, message, type = 'info', onOkCallback = null) {
                    this.modal = { visible: true, title, message, type, onOk: onOkCallback };
                },
                closeModal() {
                    if (typeof this.modal.onOk === 'function') {
                        this.modal.onOk();
                    }
                    this.modal.visible = false;
                    this.modal.onOk = null;
                },
                scrollCategories(direction) {
                    const scroller = this.$refs.categoryScroller;
                    if (!scroller) return;
                    const icons = Array.from(scroller.querySelectorAll('.category-icon'));
                    if (icons.length === 0) return;
                    const containerRect = scroller.getBoundingClientRect();
                    let firstVisibleIndex = icons.findIndex(icon => {
                        const iconRect = icon.getBoundingClientRect();
                        return iconRect.right > containerRect.left + 1;
                    });
                    if (firstVisibleIndex === -1) firstVisibleIndex = 0;
                    let targetIndex;
                    if (direction > 0) {
                        targetIndex = Math.min(firstVisibleIndex + 1, icons.length - 1);
                    } else {
                        targetIndex = Math.max(firstVisibleIndex - 1, 0);
                    }
                    icons[targetIndex].scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' });
                },
                setCategoryScrollerWidth() {
                    const wrapper = this.$refs.categoryScrollerWrapper;
                    if (!wrapper) return;
                    if (window.innerWidth < 768) {
                        wrapper.style.maxWidth = ''; // Clear style on mobile
                        return;
                    }
                    const scroller = this.$refs.categoryScroller;
                    const firstIcon = scroller.querySelector('.category-icon');
                    const container = scroller.querySelector('.category-scroll-container');
                    if (firstIcon && container) {
                        const gap = parseFloat(window.getComputedStyle(container).gap);
                        const iconWidth = firstIcon.offsetWidth;
                        const totalWidth = (iconWidth * 6) + (gap * 5);
                        wrapper.style.maxWidth = totalWidth + 'px';
                    }
                },
                adjustImageSize() {
                    const imageContainer = this.$refs.imageContainer;
                    const infoContainer = this.$refs.infoContainer;
                    if (window.innerWidth < 768 || !imageContainer || !infoContainer) {
                        if(imageContainer) { imageContainer.style.height = ''; imageContainer.style.width = ''; }
                        return;
                    }
                    const infoHeight = infoContainer.offsetHeight;
                    const parentContainer = imageContainer.parentNode;
                    const gap = parseInt(window.getComputedStyle(parentContainer).gap, 10) || 32;
                    const maxImageWidth = (parentContainer.clientWidth - infoContainer.offsetWidth - gap);
                    const finalSize = Math.min(infoHeight, maxImageWidth);
                    if (finalSize > 0) {
                         imageContainer.style.height = `${finalSize}px`;
                         imageContainer.style.width = `${finalSize}px`;
                    }
                },
                setupResizeObserver() {
                    this.disconnectResizeObserver(); 
                    if (this.$refs.infoContainer) {
                        this.resizeObserver = new ResizeObserver(() => { this.adjustImageSize(); });
                        this.resizeObserver.observe(this.$refs.infoContainer);
                        this.adjustImageSize();
                    }
                },
                disconnectResizeObserver() {
                    if (this.resizeObserver) { this.resizeObserver.disconnect(); this.resizeObserver = null; }
                },
                // --- Routing & View Management ---
                setView(viewName, params = {}) {
                    let newUrlPath = '';
                    const viewMap = { 'orderHistory': 'order-history', 'aboutUs': 'about-us', 'privacyPolicy': 'privacy-policy', 'termsAndConditions': 'terms-and-conditions', 'refundPolicy': 'refund-policy' };
                    
                    if (viewName === 'home') { newUrlPath = '/'; } 
                    else if (viewName === 'productDetail' && params.productId) {
                        const product = this.getProductById(params.productId);
                        if (product) newUrlPath = `/${product.category_slug}/${product.slug}`;
                    } else if (viewName === 'products' && params.filterType === 'category') {
                        const category = this.allProducts.find(p => p.category === params.filterValue);
                        if (category) newUrlPath = `/products/category/${category.category_slug}`;
                    } else if (Object.keys(viewMap).concat(['products', 'cart', 'checkout']).includes(viewName)) {
                        newUrlPath = `/${viewMap[viewName] || viewName}`;
                    }
                    
                    const newUrl = (this.basePath + (newUrlPath === '/' ? '' : newUrlPath)) || '/';
                    
                    // Prevent pushing state if the URL is already correct (e.g., on initial load).
                    if (window.location.pathname !== newUrl || window.location.hash) {
                         // Use replaceState for hash-based redirects to avoid broken back button behavior
                        if (window.location.hash.includes('order-history')) {
                            history.replaceState(params, '', newUrl);
                        } else {
                            history.pushState(params, '', newUrl);
                        }
                    }
                    
                    this.changeView(viewName, params);
                },
                changeView(viewName, params = {}, isInitialLoad = false) {
                    if (!isInitialLoad) { this.previousView = this.currentView; }
                    this.currentView = viewName;
                    if (viewName === 'productDetail') {
                        this.selectedProduct = this.getProductById(params.productId);
                        if (this.selectedProduct) { this.isImageLoading = true; this.resetProductDetailState(); }
                    } else { this.selectedProduct = null; }
                    if (viewName === 'orderHistory') { this.clearNotifications(); }
                    if (viewName === 'products') {
                        if (params.filterType === 'category') { this.searchQuery = ''; this.productFilter = { filterType: 'category', filterValue: params.filterValue }; } 
                        else if (!this.searchQuery.trim()) { this.productFilter = { filterType: null, filterValue: null }; }
                    }
                    if (viewName === 'checkout') { this.checkoutItems = params.items || this.checkoutItems; }
                    if (!isInitialLoad) { window.scrollTo({ top: 0, behavior: 'smooth' }); }
                },
                handleRouting() {
                    const path = window.location.pathname.replace(this.basePath, '').substring(1);
                    if (path.endsWith('.php')) return;
                    const pathParts = path.split('/');
                    const productSlugMap = <?php echo json_encode($product_slug_map); ?>;
                    const categorySlugMap = <?php echo json_encode($category_slug_map); ?>;
                    const staticPages = <?php echo json_encode($static_pages); ?>;
                    const viewMap = { 'order-history': 'orderHistory', 'about-us': 'aboutUs', 'privacy-policy': 'privacyPolicy', 'terms-and-conditions': 'termsAndConditions', 'refund-policy': 'refundPolicy' };
                    if (productSlugMap[path]) { this.changeView('productDetail', { productId: productSlugMap[path] }); } 
                    else if (pathParts[0] === 'products' && pathParts.length > 2 && pathParts[1] === 'category' && categorySlugMap[pathParts[2]]) { this.changeView('products', { filterType: 'category', filterValue: categorySlugMap[pathParts[2]] }); } 
                    else if (staticPages.includes(pathParts[0]) && pathParts.length === 1) { this.changeView(viewMap[pathParts[0]] || pathParts[0]); } 
                    else if (path === '') { this.changeView('home'); }
                },
                // --- Data & State Helpers ---
                getProductById(id) { return this.allProducts.find(p => p.id == id); },
                performSearch() { if (this.searchQuery.trim()) { this.setView('products'); } },
                resetProductDetailState() { this.activeTab = 'description'; this.isDescriptionExpanded = false; this.selectedDurationIndex = 0; },
                resetCheckoutState() { this.appliedCoupon = null; this.couponCode = ''; this.couponMessage = ''; },
                // --- Cart & Checkout ---
                addToCart(productId, quantity = 1) {
                    const existingItemIndex = this.cart.findIndex(item => item.productId === productId && item.durationIndex === this.selectedDurationIndex);
                    if (existingItemIndex > -1) { this.cart[existingItemIndex].quantity += quantity; } 
                    else { this.cart.push({ productId, quantity, durationIndex: this.selectedDurationIndex }); }
                    const product = this.getProductById(productId);
                    if (product) { this.showModal('Added to Cart', `'${product.name}' has been added to your cart.`, 'success'); }
                    this.saveCart();
                },
                buyNowAndCheckout(productId) { this.checkoutItems = [{ productId, quantity: 1, durationIndex: this.selectedDurationIndex }]; this.resetCheckoutState(); this.setView('checkout'); },
                proceedToCheckout() {
                    const inStockItems = this.cart.filter(item => !this.getProductById(item.productId)?.stock_out);
                    if (inStockItems.length === 0) { this.showModal("Cart Error", "All items in your cart are out of stock.", "error"); return; }
                    const outOfStockCount = this.cart.length - inStockItems.length;
                    if (outOfStockCount > 0) { this.showModal("Stock Alert", `${outOfStockCount} item(s) are out of stock and were excluded from this order.`, "info"); }
                    this.checkoutItems = inStockItems; this.resetCheckoutState(); this.setView('checkout');
                },
                removeFromCart(productId) { this.cart = this.cart.filter(item => item.productId !== productId); this.saveCart(); },
                updateCartQuantity(productId, change) {
                    const item = this.cart.find(item => item.productId === productId);
                    if (item) { item.quantity += change; if (item.quantity <= 0) this.removeFromCart(productId); else this.saveCart(); }
                },
                saveCart() { localStorage.setItem('submonthCart', JSON.stringify(this.cart)); },
                applyCoupon() {
                    this.couponMessage = ''; this.appliedCoupon = null;
                    if (!this.couponCode.trim()) { this.couponMessage = 'Please enter a coupon code.'; return; }
                    const codeToApply = this.couponCode.toUpperCase(); const foundCoupon = this.allCoupons.find(c => c.code === codeToApply);
                    if (foundCoupon && foundCoupon.is_active) {
                        let isApplicable = false;
                        if (!foundCoupon.scope || foundCoupon.scope === 'all_products') { isApplicable = this.checkoutItems.length > 0; }
                        else if (foundCoupon.scope === 'category') { isApplicable = this.checkoutItems.some(item => this.getProductById(item.productId)?.category === foundCoupon.scope_value); }
                        else if (foundCoupon.scope === 'single_product') { isApplicable = this.checkoutItems.some(item => item.productId == foundCoupon.scope_value); }
                        if (isApplicable) { this.appliedCoupon = foundCoupon; this.couponMessage = `Coupon "${foundCoupon.code}" applied successfully!`; this.showModal('Success', this.couponMessage, 'success'); } 
                        else { this.couponMessage = `Coupon is not valid for the items in your cart.`; this.showModal('Invalid Coupon', this.couponMessage, 'error'); }
                    } else { this.couponMessage = 'The coupon code is invalid or has expired.'; this.showModal('Invalid Coupon', this.couponMessage, 'error'); }
                },
                // --- API Calls ---
                
                // *** NEW: INITIATE RUPANTORPAY PAYMENT ***
                async initiatePayment() {
                    if (this.checkoutItems.length === 0) {
                        this.showModal("Error", "Your checkout is empty!", "error");
                        return;
                    }
                    if (!this.checkoutForm.name || !this.checkoutForm.phone || !this.checkoutForm.email) {
                        this.showModal("Error", "Please fill in all billing information.", "error");
                        return;
                    }
                    
                    this.showModal("Processing...", "Please wait while we prepare your payment.", "info");

                    const paymentPayload = {
                        action: 'create_rupantorpay_payment',
                        customerInfo: this.checkoutForm,
                        items: this.checkoutItems.map(item => ({
                            id: item.productId,
                            name: this.getProductById(item.productId).name,
                            quantity: item.quantity,
                            pricing: this.getProductById(item.productId).pricing[item.durationIndex]
                        })),
                        coupon: this.appliedCoupon || {}
                    };

                    try {
                        const response = await fetch(`${this.basePath}/api.php`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(paymentPayload)
                        });
                        const result = await response.json();

                        if (result.status === 1 && result.payment_url) {
                            // Redirect to the payment URL
                            window.location.href = result.payment_url;
                        } else {
                            this.showModal("Payment Failed", result.message || "Could not initiate payment. Please try again.", "error");
                        }
                    } catch (error) {
                        this.showModal("Connection Error", "An error occurred. Please check your connection and try again.", "error");
                    }
                },
                async submitReview() {
                    if (!this.newReview.name.trim() || this.newReview.rating === 0 || !this.newReview.comment.trim()) { this.showModal('Review Error', 'Please fill all fields and provide a rating.', 'error'); return; }
                    const reviewPayload = { action: 'add_review', review: { ...this.newReview, productId: this.selectedProduct.id } };
                    try {
                        const response = await fetch(`${this.basePath}/api.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(reviewPayload) });
                        const result = await response.json();
                        if(result.success) {
                            const product = this.getProductById(this.selectedProduct.id);
                            if (product) { if(!product.reviews) product.reviews = []; product.reviews.unshift({ ...this.newReview, id: Date.now() }); }
                            this.newReview = { name: '', rating: 0, comment: '' }; this.hoverRating = 0; this.reviewModalOpen = false; this.showModal('Success', 'Thank you for your review!', 'success');
                        } else { this.showModal('Error', 'Failed to submit review. ' + (result.message || ''), 'error'); }
                    } catch(error) { this.showModal('Error', "An error occurred while submitting your review.", "error"); }
                },
                async findOrdersByIds(ids) {
                    if (!ids || ids.length === 0) { this.isSearchingOrders = false; return; }
                    this.isSearchingOrders = true;
                    try {
                        const response = await fetch(`${this.basePath}/api.php?action=get_orders_by_ids&ids=${JSON.stringify(ids)}`);
                        const orders = await response.json();
                        if (orders.length > 0) {
                            this.orderHistory = orders.sort((a, b) => b.order_id - a.order_id);
                            this.calculateNotifications();
                        }
                    } catch (error) { console.error('Error fetching orders:', error); } finally { this.isSearchingOrders = false; }
                },
                // --- Notifications ---
                calculateNotifications() {
                    const seenOrders = JSON.parse(localStorage.getItem('submonthSeenOrders') || '{}');
                    let count = 0; this.orderHistory.forEach(order => { if (!seenOrders[order.order_id] || seenOrders[order.order_id] !== order.status) count++; });
                    this.newNotificationCount = count;
                },
                clearNotifications() {
                    this.newNotificationCount = 0;
                    const seenOrders = {}; this.orderHistory.forEach(order => { seenOrders[order.order_id] = order.status; });
                    localStorage.setItem('submonthSeenOrders', JSON.stringify(seenOrders));
                },
                // --- Hero Slider Methods ---
                initSlider() {
                    const images = <?= json_encode($hero_banner_paths) ?>;
                    if (images.length > 0) {
                        this.heroSlides.hasImages = true; this.heroSlides.slides = images.map(url => ({ url: url }));
                    } else {
                        this.heroSlides.hasImages = false; const placeholders = [];
                        const bgColors = ['bg-violet-500', 'bg-indigo-500', 'bg-sky-500', 'bg-teal-500'];
                        for (let i = 0; i < 4; i++) { placeholders.push({ text: `Banner ${i + 1}`, bgColor: bgColors[i % bgColors.length] }); }
                        this.heroSlides.slides = placeholders;
                    }
                    this.startSlider();
                },
                startSlider() {
                    if (this.heroSlides.slides.length <= 1) return;
                    this.heroSlides.interval = setInterval(() => { this.heroSlides.activeSlide = this.heroSlides.activeSlide === this.heroSlides.slides.length - 1 ? 0 : this.heroSlides.activeSlide + 1; }, this.heroSlides.sliderInterval);
                },
                stopSlider() { clearInterval(this.heroSlides.interval); }
            },
            mounted() {
                this.currency = localStorage.getItem('submonthCurrency') || 'BDT';
                this.cart = JSON.parse(localStorage.getItem('submonthCart') || '[]');
                const savedOrderIds = JSON.parse(localStorage.getItem('submonthOrderIds') || '[]');
                if (savedOrderIds.length > 0) { this.findOrdersByIds(savedOrderIds); }

                // Check for payment status from URL
                const urlParams = new URLSearchParams(window.location.search);
                if(urlParams.get('payment') === 'failed') {
                    this.showModal('Payment Failed', 'Your payment was not completed. Please try again.', 'error');
                } else if(urlParams.get('payment') === 'verification_failed') {
                    this.showModal('Verification Failed', 'We could not verify your payment. Please contact support.', 'error');
                }
                
                const initialParams = <?= json_encode($initial_params) ?>;
                this.changeView(this.currentView, initialParams, true);

                window.addEventListener('popstate', this.handleRouting);
                
                const yearSpan = document.getElementById('current-year-footer');
                if(yearSpan) { yearSpan.textContent = new Date().getFullYear(); }
                this.initSlider();
                nextTick(() => {
                    this.setCategoryScrollerWidth();
                    window.addEventListener('resize', this.setCategoryScrollerWidth);
                });
            },
            beforeUnmount() {
                window.removeEventListener('popstate', this.handleRouting);
                this.disconnectResizeObserver();
                window.removeEventListener('resize', this.setCategoryScrollerWidth);
            }
        }).mount('#app');
    </script>
    <!-- *** END: VUE SCRIPT MODIFICATION *** -->

    <!-- TrustBox script -->
    <script>
        (function(w,d,s,r,n){w.TrustpilotObject=n;w[n]=w[n]||function(){(w[n].q=w[n].q||[]).push(arguments)};
            a=d.createElement(s);a.async=1;a.src=r;a.type='text/java'+s;f=d.getElementsByTagName(s)[0];
            f.parentNode.insertBefore(a,f)})(window,document,'script', 'https://invitejs.trustpilot.com/tp.min.js', 'tp');
            tp('register', 'Fe0Kg2WfsOnYav1E');
    </script>
    <script type="text/javascript" src="//widget.trustpilot.com/bootstrap/v5/tp.widget.bootstrap.min.js" async></script>
    <!-- End TrustBox script -->
</body>
</html>