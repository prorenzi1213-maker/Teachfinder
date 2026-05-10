<?php
session_start();
require_once __DIR__ . '/connections.php';
require_once __DIR__ . '/paymongo_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$amount = floatval($_POST['amount'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($amount < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Minimum amount is ₱10']);
    exit();
}

$base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['SCRIPT_NAME']);
$success_url = $base_url . '/paymongo_success.php?session_id={CHECKOUT_SESSION_ID}';
$cancel_url = $base_url . '/wallet.php?cancel=1';

$payload = [
    'data' => [
        'attributes' => [
            'billing' => [
                'name' => $_SESSION['username'] ?? 'Student',
                'email' => ''
            ],
            'send_email_receipt' => false,
            'show_description' => true,
            'description' => 'TeachFinder Wallet Top-Up: ₱' . number_format($amount, 2),
            'line_items' => [
                [
                    'currency' => 'PHP',
                    'amount' => intval($amount * 100),
                    'description' => 'Wallet Deposit',
                    'name' => 'Wallet Top-Up',
                    'quantity' => 1
                ]
            ],
            'payment_method_types' => ['gcash', 'card'],
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'metadata' => [
                'user_id' => (string)$user_id,
                'amount' => (string)$amount
            ]
        ]
    ]
];

$ch = curl_init(PAYMONGO_API_URL . '/checkout_sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':')
    ]
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($http_code === 200 && isset($data['data']['attributes']['checkout_url'])) {
    echo json_encode([
        'success' => true,
        'checkout_url' => $data['data']['attributes']['checkout_url']
    ]);
} else {
    $error = $data['errors'][0]['detail'] ?? json_encode($data);
    echo json_encode(['success' => false, 'message' => $error]);
}
