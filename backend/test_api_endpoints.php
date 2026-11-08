<?php
require __DIR__ . '/vendor/autoload.php';

function apiRequest($method, $url, $token = null, $body = null) {
    $ch = curl_init("http://127.0.0.1:8000$url");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json'
    ];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $res];
}

// 1. Login
$login = apiRequest('POST', '/api/v1/auth/login', null, [
    'email' => 'juan2@example.com',
    'password' => 'password123'
]);

echo "Login status: " . $login['code'] . "\n";
$loginData = json_decode($login['body'], true);
$token = $loginData['data']['token'] ?? null;
echo "Token: " . substr($token ?? 'NONE', 0, 15) . "...\n\n";

// 2. Test /products without token and with token
echo "--- 1. Testing GET /api/v1/products without token ---\n";
$pNoAuth = apiRequest('GET', '/api/v1/products');
echo "Status: " . $pNoAuth['code'] . " Response: " . $pNoAuth['body'] . "\n";

echo "\n--- 1b. Testing GET /api/v1/products WITH token ---\n";
$pAuth = apiRequest('GET', '/api/v1/products', $token);
echo "Status: " . $pAuth['code'] . " Response: " . substr($pAuth['body'], 0, 200) . "\n";

// 3. Test /payments and /payments/summary
echo "\n--- 2. Testing GET /api/v1/customer/payments ---\n";
$pay = apiRequest('GET', '/api/v1/customer/payments', $token);
echo "Status: " . $pay['code'] . " Response: " . $pay['body'] . "\n";

echo "\n--- 2b. Testing GET /api/v1/customer/payments/summary ---\n";
$paySum = apiRequest('GET', '/api/v1/customer/payments/summary', $token);
echo "Status: " . $paySum['code'] . " Response: " . $paySum['body'] . "\n";

// 4. Test /orders
echo "\n--- 3. Testing GET /api/v1/customer/orders ---\n";
$orders = apiRequest('GET', '/api/v1/customer/orders', $token);
echo "Status: " . $orders['code'] . " Response: " . substr($orders['body'], 0, 300) . "\n";

$ordersData = json_decode($orders['body'], true);
$firstOrderId = null;
if (isset($ordersData['data']) && is_array($ordersData['data']) && count($ordersData['data']) > 0) {
    $firstOrderId = $ordersData['data'][0]['id'] ?? null;
}
echo "\nFirst order ID from list: $firstOrderId\n";
if ($firstOrderId) {
    echo "--- 3b. Testing GET /api/v1/customer/orders/$firstOrderId ---\n";
    $ord = apiRequest('GET', "/api/v1/customer/orders/$firstOrderId", $token);
    echo "Status: " . $ord['code'] . " Response: " . $ord['body'] . "\n";
}
