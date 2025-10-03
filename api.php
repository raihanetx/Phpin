<?php
// api.php - Final Corrected Version
session_start();

// --- File Paths ---
$products_file_path = 'products.json';
$coupons_file_path = 'coupons.json';
$orders_file_path = 'orders.json';
$config_file_path = 'config.json';
$hotdeals_file_path = 'hotdeals.json';
$upload_dir = 'uploads/';

// --- Helper Functions ---
function get_data($file_path) {
    if (!file_exists($file_path)) file_put_contents($file_path, '[]');
    return json_decode(file_get_contents($file_path), true);
}
function save_data($file_path, $data) {
    file_put_contents($file_path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    if (function_exists('iconv')) {
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    }
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    if (empty($text)) {
        return 'n-a-' . rand(100, 999);
    }
    return $text;
}
function handle_image_upload($file_input, $upload_dir, $prefix = '') {
    if (isset($file_input) && $file_input['error'] === UPLOAD_ERR_OK) {
        $original_filename = basename($file_input['name']);
        $safe_filename = preg_replace("/[^a-zA-Z0-9-_\.]/", "", $original_filename);
        $destination = $upload_dir . $prefix . time() . '-' . uniqid() . '-' . $safe_filename;
        if (move_uploaded_file($file_input['tmp_name'], $destination)) {
            return $destination;
        }
    }
    return null;
}
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// --- GET Requests ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'get_orders_by_ids' && isset($_GET['ids'])) {
        $order_ids_to_find = json_decode($_GET['ids'], true);
        if (is_array($order_ids_to_find)) {
            $all_orders = get_data($orders_file_path);
            $found_orders = array_filter($all_orders, fn($order) => in_array($order['order_id'], $order_ids_to_find));
            header('Content-Type: application/json');
            echo json_encode(array_values($found_orders));
        } else {
            header('Content-Type: application/json', true, 400);
            echo json_encode([]);
        }
        exit;
    }
}

// --- POST Requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $json_data = null;
    
    if (!$action) {
        $json_data = json_decode(file_get_contents('php://input'), true);
        $action = $json_data['action'] ?? null;
    }
    
    if (!$action) { http_response_code(400); echo "Action not specified."; exit; }

    $admin_actions = [
        'add_category', 'delete_category', 'edit_category', 'add_product', 'delete_product', 'edit_product', 
        'add_coupon', 'delete_coupon', 'update_review_status', 'update_order_status',
        'update_hero_banner', 'update_favicon', 'update_currency_rate', 
        'update_contact_info', 'update_admin_password', 'update_site_logo', 'update_hot_deals'
    ];

    if (in_array($action, $admin_actions)) {
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            http_response_code(403);
            echo "Forbidden: You must be logged in to perform this action.";
            exit;
        }
    }

    $redirect_url = null;
    
    // --- RUPANTORPAY PAYMENT ACTION ---
    if ($action === 'create_rupantorpay_payment') {
        $config_data = get_data($config_file_path);
        $api_key = $config_data['rupantorpay_api_key'] ?? '';

        if (empty($api_key)) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['status' => false, 'message' => 'API Key is not configured.']);
            exit;
        }

        $order_data = $json_data;

        // --- Calculate totals ---
        $subtotal = 0;
        $all_products_data = get_data($products_file_path);
        $all_products_flat = [];
        foreach($all_products_data as $cat) { if(isset($cat['products'])) { foreach($cat['products'] as $p) { $p['category'] = $cat['name']; $all_products_flat[$p['id']] = $p; } } }
        foreach($order_data['items'] as $item) { $subtotal += $item['pricing']['price'] * $item['quantity']; }
        $discount = 0;
        if (!empty($order_data['coupon']) && isset($order_data['coupon']['discount_percentage'])) {
            $coupon = $order_data['coupon'];
            $eligible_subtotal = 0;
            if (!isset($coupon['scope']) || $coupon['scope'] === 'all_products') { $eligible_subtotal = $subtotal; } else {
                foreach($order_data['items'] as $item) {
                    $product_id = $item['id'];
                    if (isset($all_products_flat[$product_id])) {
                        $product_details = $all_products_flat[$product_id];
                        if ($coupon['scope'] === 'category' && $product_details['category'] === $coupon['scope_value']) {
                            $eligible_subtotal += $item['pricing']['price'] * $item['quantity'];
                        } elseif ($coupon['scope'] === 'single_product' && $product_id == $coupon['scope_value']) {
                            $eligible_subtotal += $item['pricing']['price'] * $item['quantity'];
                        }
                    }
                }
            }
            $discount = $eligible_subtotal * ($coupon['discount_percentage'] / 100);
        }
        $total = $subtotal - $discount;
        // --- End of total calculation ---

        // Create a new order in our system
        $new_order_id = time();
        $new_order = [
            'order_id' => $new_order_id,
            'order_date' => date('Y-m-d H:i:s'),
            'customer' => $order_data['customerInfo'],
            'payment' => ['method' => 'RupantorPay', 'trx_id' => null],
            'items' => $order_data['items'],
            'coupon' => $order_data['coupon'] ?? [],
            'totals' => ['subtotal' => $subtotal, 'discount' => $discount, 'total' => $total],
            'status' => 'Awaiting Payment',
        ];
        $all_orders = get_data($orders_file_path);
        $all_orders[] = $new_order;
        save_data($orders_file_path, $all_orders);

        $domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
        $base_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        
        // *** THIS IS THE FINAL, CORRECT PAYLOAD STRUCTURE BASED ON YOUR LATEST DOCUMENTATION ***
        $payload = [
            'fullname' => $order_data['customerInfo']['name'],
            'email' => $order_data['customerInfo']['email'],
            'amount' => (string)$total, // Amount MUST be a string
            'success_url' => $domain . $base_dir . '/payment_verify.php',
            'cancel_url' => $domain . $base_dir . '/index.php',
            'webhook_url' => '', // Add your webhook URL here if you have one in the future
            'metadata' => [
                'internal_order_id' => (string)$new_order_id,
                'customer_phone' => $order_data['customerInfo']['phone']
            ]
        ];

        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://payment.rupantorpay.com/api/payment/checkout',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_CONNECTTIMEOUT => 30, // Increased connection timeout to 30 seconds
          CURLOPT_TIMEOUT => 60,      // Increased total timeout to 60 seconds
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => json_encode($payload),
          CURLOPT_HTTPHEADER => array(
            'X-API-KEY: ' . $api_key,
            'Content-Type: application/json',
            'X-CLIENT: ' . $_SERVER['HTTP_HOST']
          ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        
        if ($err) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['status' => false, 'message' => 'cURL Error: ' . $err]);
            exit;
        }
        
        header('Content-Type: application/json');
        echo $response;
        exit;
    }

    // --- All OTHER Form-based (non-JSON) Admin actions ---
    if ($action === 'update_hero_banner') {
        $config = get_data($config_file_path);
        if (isset($_POST['hero_slider_interval'])) { $config['hero_slider_interval'] = (int)$_POST['hero_slider_interval'] * 1000; }
        $current_banners = $config['hero_banner'] ?? [];
        if (isset($_POST['delete_hero_banners'])) { foreach ($_POST['delete_hero_banners'] as $index => $value) { if ($value === 'true' && isset($current_banners[$index]) && file_exists($current_banners[$index])) { unlink($current_banners[$index]); $current_banners[$index] = null; } } }
        for ($i = 0; $i < 10; $i++) { if (isset($_FILES['hero_banners']['tmp_name'][$i]) && is_uploaded_file($_FILES['hero_banners']['tmp_name'][$i])) { if (isset($current_banners[$i]) && file_exists($current_banners[$i])) { unlink($current_banners[$i]); } $destination = handle_image_upload($_FILES['hero_banners'][$i], $upload_dir, 'hero-'); if ($destination) { $current_banners[$i] = $destination; } } }
        $config['hero_banner'] = array_values(array_filter($current_banners));
        save_data($config_file_path, $config);
        $redirect_url = 'admin.php?view=settings';
    }
    if ($action === 'update_site_logo') {
        $config = get_data($config_file_path);
        if (isset($_POST['delete_site_logo']) && !empty($config['site_logo']) && file_exists($config['site_logo'])) { unlink($config['site_logo']); $config['site_logo'] = ''; }
        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) { if (!empty($config['site_logo']) && file_exists($config['site_logo'])) { unlink($config['site_logo']); } $destination = handle_image_upload($_FILES['site_logo'], $upload_dir, 'logo-'); if($destination) $config['site_logo'] = $destination; }
        save_data($config_file_path, $config);
        $redirect_url = 'admin.php?view=settings';
    }
    if ($action === 'update_favicon') {
        $config = get_data($config_file_path);
        if (isset($_POST['delete_favicon']) && !empty($config['favicon']) && file_exists($config['favicon'])) { unlink($config['favicon']); $config['favicon'] = ''; }
        if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) { if (!empty($config['favicon']) && file_exists($config['favicon'])) { unlink($config['favicon']); } $destination = handle_image_upload($_FILES['favicon'], $upload_dir, 'favicon-'); if($destination) $config['favicon'] = $destination; }
        save_data($config_file_path, $config);
        $redirect_url = 'admin.php?view=settings';
    }
    if ($action === 'update_currency_rate') {
        $config = get_data($config_file_path);
        if (isset($_POST['usd_to_bdt_rate'])) { $config['usd_to_bdt_rate'] = (float)$_POST['usd_to_bdt_rate']; }
        save_data($config_file_path, $config);
        $redirect_url = 'admin.php?view=settings';
    }
    if ($action === 'update_contact_info') {
        $config = get_data($config_file_path);
        $config['contact_info']['phone'] = htmlspecialchars(trim($_POST['phone_number']));
        $config['contact_info']['whatsapp'] = htmlspecialchars(trim($_POST['whatsapp_number']));
        $config['contact_info']['email'] = htmlspecialchars(trim($_POST['email_address']));
        save_data($config_file_path, $config);
        $redirect_url = 'admin.php?view=settings';
    }
    if ($action === 'update_admin_password') {
        $config = get_data($config_file_path);
        if (!empty(trim($_POST['new_password']))) { $config['admin_password'] = trim($_POST['new_password']); }
        save_data($config_file_path, $config);
        $redirect_url = 'admin.php?view=settings';
    }
    if (in_array($action, ['add_category', 'delete_category', 'edit_category', 'add_product', 'delete_product', 'edit_product'])) {
        $all_data = get_data($products_file_path);
        if ($action === 'add_category') { $name = htmlspecialchars(trim($_POST['name'])); $all_data[] = ['name' => $name, 'slug' => slugify($name), 'icon' => htmlspecialchars(trim($_POST['icon'])), 'products' => []]; $redirect_url = 'admin.php?view=categories'; }
        if ($action === 'delete_category') { $all_data = array_values(array_filter($all_data, fn($cat) => $cat['name'] !== $_POST['name'])); $redirect_url = 'admin.php?view=categories'; }
        if ($action === 'edit_category') { $old_name = $_POST['original_name']; $new_name = htmlspecialchars(trim($_POST['name'])); $new_icon = htmlspecialchars(trim($_POST['icon'])); $new_slug = slugify($new_name); foreach ($all_data as &$category) { if ($category['name'] === $old_name) { $category['name'] = $new_name; $category['slug'] = $new_slug; $category['icon'] = $new_icon; break; } } unset($category); $all_coupons = get_data($coupons_file_path); foreach ($all_coupons as &$coupon) { if (($coupon['scope'] ?? '') === 'category' && $coupon['scope_value'] === $old_name) { $coupon['scope_value'] = $new_name; } } unset($coupon); save_data($coupons_file_path, $all_coupons); $redirect_url = 'admin.php?view=categories'; }
        if ($action === 'add_product' || $action === 'edit_product') { function parse_pricing_data() { $p = []; if (!empty($_POST['durations'])) { for ($i = 0; $i < count($_POST['durations']); $i++) { $p[] = ['duration' => htmlspecialchars(trim($_POST['durations'][$i])), 'price' => (float)$_POST['duration_prices'][$i]]; } } else { $p[] = ['duration' => 'Default', 'price' => (float)$_POST['price']]; } return $p; } function sanitize_description($desc) { return str_replace(['<', '>'], ['&lt;', '&gt;'], $desc); } if ($action === 'add_product') { $image_path = handle_image_upload($_FILES['image'] ?? null, $upload_dir, 'product-'); $name = htmlspecialchars(trim($_POST['name'])); $long_description_safe = sanitize_description(trim($_POST['long_description'] ?? '')); $new_product = [ 'id' => time() . rand(100, 999), 'name' => $name, 'slug' => slugify($name), 'description' => htmlspecialchars(trim($_POST['description'])), 'long_description' => $long_description_safe, 'image' => $image_path ?? '', 'pricing' => parse_pricing_data(), 'stock_out' => ($_POST['stock_out'] ?? 'false') === 'true', 'featured' => isset($_POST['featured']), 'reviews' => [] ]; foreach ($all_data as &$category) { if ($category['name'] === $_POST['category_name']) { if (!isset($category['products'])) { $category['products'] = []; } $category['products'][] = $new_product; break; } } unset($category); $redirect_url = 'admin.php?category=' . urlencode($_POST['category_name']); } if ($action === 'edit_product') { for ($i = 0; $i < count($all_data); $i++) { if ($all_data[$i]['name'] === $_POST['category_name']) { for ($j = 0; $j < count($all_data[$i]['products']); $j++) { if ($all_data[$i]['products'][$j]['id'] == $_POST['product_id']) { $cp = &$all_data[$i]['products'][$j]; if (isset($_POST['delete_image']) && !empty($cp['image']) && file_exists($cp['image'])) { unlink($cp['image']); $cp['image'] = ''; } $nip = handle_image_upload($_FILES['image'] ?? null, 'uploads/', 'product-'); if ($nip) { if (!empty($cp['image']) && file_exists($cp['image'])) { unlink($cp['image']); } $cp['image'] = $nip; } $name = htmlspecialchars(trim($_POST['name'])); $cp['name'] = $name; $cp['slug'] = slugify($name); $cp['description'] = htmlspecialchars(trim($_POST['description'])); $cp['long_description'] = sanitize_description(trim($_POST['long_description'] ?? '')); $cp['pricing'] = parse_pricing_data(); $cp['stock_out'] = $_POST['stock_out'] === 'true'; $cp['featured'] = isset($_POST['featured']); break 2; } } } } $redirect_url = 'admin.php?category=' . urlencode($_POST['category_name']); } }
        if ($action === 'delete_product') { for ($i = 0; $i < count($all_data); $i++) { if ($all_data[$i]['name'] === $_POST['category_name']) { foreach($all_data[$i]['products'] as $p) { if ($p['id'] == $_POST['product_id'] && !empty($p['image']) && file_exists($p['image'])) { unlink($p['image']); break; } } $all_data[$i]['products'] = array_values(array_filter($all_data[$i]['products'], fn($prod) => $prod['id'] != $_POST['product_id'])); break; } } $redirect_url = 'admin.php?category=' . urlencode($_POST['category_name']); }
        save_data($products_file_path, $all_data);
    }
    if (in_array($action, ['add_coupon', 'delete_coupon'])) {
        $all_coupons = get_data($coupons_file_path);
        if ($action === 'add_coupon') { $scope = $_POST['scope'] ?? 'all_products'; $scope_value = null; if ($scope === 'category') { $scope_value = $_POST['scope_value_category'] ?? null; } elseif ($scope === 'single_product') { $scope_value = $_POST['scope_value_product'] ?? null; } $all_coupons[] = [ 'id' => time() . rand(100, 999), 'code' => strtoupper(htmlspecialchars(trim($_POST['code']))), 'discount_percentage' => (int)$_POST['discount_percentage'], 'is_active' => isset($_POST['is_active']), 'scope' => $scope, 'scope_value' => $scope_value ]; }
        if ($action === 'delete_coupon') { $all_coupons = array_values(array_filter($all_coupons, fn($c) => $c['id'] != $_POST['coupon_id'])); }
        save_data($coupons_file_path, $all_coupons);
        $redirect_url = 'admin.php?view=dashboard';
    }
    if ($action === 'update_hot_deals') {
        $config = get_data($config_file_path);
        if (isset($_POST['hot_deals_speed'])) { $config['hot_deals_speed'] = (int)$_POST['hot_deals_speed']; }
        save_data($config_file_path, $config);
        $new_deals_data = [];
        $selected_product_ids = $_POST['selected_deals'] ?? [];
        foreach($selected_product_ids as $productId) { $custom_title = htmlspecialchars(trim($_POST['custom_titles'][$productId] ?? '')); $new_deals_data[] = [ 'productId' => $productId, 'customTitle' => $custom_title ]; }
        save_data($hotdeals_file_path, $new_deals_data);
        $redirect_url = 'admin.php?view=hotdeals';
    }
    if ($action === 'update_review_status') {
        $product_id = $_POST['product_id']; $review_id = $_POST['review_id']; $new_status = $_POST['new_status']; $all_products = get_data($products_file_path);
        if ($new_status === 'deleted') { for ($i = 0; $i < count($all_products); $i++) { if (empty($all_products[$i]['products'])) continue; for ($j = 0; $j < count($all_products[$i]['products']); $j++) { if ($all_products[$i]['products'][$j]['id'] == $product_id) { $all_products[$i]['products'][$j]['reviews'] = array_values( array_filter( $all_products[$i]['products'][$j]['reviews'] ?? [], fn($review) => $review['id'] !== $review_id ) ); break 2; } } } save_data($products_file_path, $all_products); }
        $redirect_url = 'admin.php?view=reviews';
    }
    if ($action === 'update_order_status') {
        $order_id = $_POST['order_id']; $new_status = $_POST['new_status']; $all_orders = get_data($orders_file_path);
        foreach ($all_orders as &$order) { if ($order['order_id'] == $order_id) { $order['status'] = $new_status; break; } }
        save_data($orders_file_path, $all_orders); $redirect_url = 'admin.php?view=orders';
    }
    if ($redirect_url) {
        header('Location: ' . $redirect_url);
        exit;
    }
}

// Fallback for any other unhandled POST requests
http_response_code(403);
echo "Invalid Access.";
?>
