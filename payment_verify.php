<?php
// payment_verify.php

// Helper ফাংশন এবং ফাইল পাথগুলো api.php থেকে কপি করে আনুন
$config_file_path = 'config.json';
$orders_file_path = 'orders.json';

function get_data($file_path) {
    if (!file_exists($file_path)) return [];
    return json_decode(file_get_contents($file_path), true);
}

function save_data($file_path, $data) {
    file_put_contents($file_path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

// ট্রানজেকশন আইডি URL থেকে নিন
$rupantor_transaction_id = $_GET['transactionId'] ?? null;
$status = $_GET['status'] ?? null;

$base_url = rtrim(dirname((isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME']), '/');

// যদি ট্রানজেকশন আইডি না থাকে বা স্ট্যাটাস সফল না হয়
if (!$rupantor_transaction_id || $status !== 'COMPLETED') {
    // গ্রাহককে একটি ব্যর্থতার মেসেজ সহ হোমপেজে পাঠান
    header('Location: ' . $base_url . '/index.php?payment=failed');
    exit;
}

// API Key config.json থেকে লোড করুন
$config_data = get_data($config_file_path);
$api_key = $config_data['rupantorpay_api_key'] ?? '';

if (empty($api_key)) {
    die('Payment verification failed: API key not configured.');
}

// রূপান্তর পে-এর কাছে ভেরিফিকেশন রিকোয়েস্ট পাঠান
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://payment.rupantorpay.com/api/payment/verify-payment',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => json_encode(['transaction_id' => $rupantor_transaction_id]),
  CURLOPT_HTTPHEADER => array(
    'X-API-KEY: ' . $api_key,
    'Content-Type: application/json'
  ),
));

$response = curl_exec($curl);
curl_close($curl);
$verification_data = json_decode($response, true);

// ভেরিফিকেশন সফল হলে
if (isset($verification_data['status']) && $verification_data['status'] === 'COMPLETED') {
    
    $internal_order_id = $verification_data['metadata']['internal_order_id'] ?? null;
    $real_trx_id = $verification_data['trx_id'] ?? null;

    if ($internal_order_id) {
        $all_orders = get_data($orders_file_path);
        $order_updated = false;
        $should_redirect_success = false;

        foreach ($all_orders as &$order) {
            if ($order['order_id'] == $internal_order_id) {
                // If order is awaiting payment, update it. This is the first successful verification.
                if ($order['status'] === 'Awaiting Payment') {
                    $order['status'] = 'Pending'; // Set to Pending for admin confirmation
                    $order['payment']['trx_id'] = $real_trx_id; // Save the real transaction ID
                    $order['payment']['rupantor_tid'] = $rupantor_transaction_id; // Save RupantorPay's transaction ID
                    $order_updated = true;
                    $should_redirect_success = true;
                    break;
                }
                // If order is already processed, this is a page refresh. Redirect to success without updating.
                elseif (in_array($order['status'], ['Pending', 'Confirmed'])) {
                    $should_redirect_success = true;
                    break;
                }
            }
        }
        unset($order); // Unset reference

        // Only save if we actually made a change
        if ($order_updated) {
            save_data($orders_file_path, $all_orders);
        }

        // Redirect to success page if the order was found and was in a valid state
        if ($should_redirect_success) {
            // Redirect to order history page after saving the order ID in localStorage
            echo <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <title>Processing Payment...</title>
                <script>
                    try {
                        const orderId = "{$internal_order_id}";
                        const savedOrderIds = JSON.parse(localStorage.getItem('submonthOrderIds') || '[]');
                        if (!savedOrderIds.includes(orderId)) {
                            savedOrderIds.push(orderId);
                            localStorage.setItem('submonthOrderIds', JSON.stringify(savedOrderIds));
                        }
                    } catch (e) {
                        console.error("Could not save order ID to localStorage", e);
                    }
                    // Redirect to order history page
                    window.location.href = '{$base_url}/order-history'; 
                </script>
            </head>
            <body>
                <p>Payment successful! Redirecting you to your order history...</p>
            </body>
            </html>
HTML;
            exit;
        }
    }
}

// ভেরিফিকেশন ব্যর্থ হলে
header('Location: ' . $base_url . '/index.php?payment=verification_failed');
exit;

?>