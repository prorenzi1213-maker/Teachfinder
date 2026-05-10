<?php
session_start();
require_once __DIR__ . '/connections.php';
require_once __DIR__ . '/paymongo_config.php';

$session_id = $_GET['session_id'] ?? '';

if (empty($session_id)) {
    header("Location: wallet.php?error=1");
    exit();
}

$ch = curl_init(PAYMONGO_API_URL . '/checkout_sessions/' . $session_id);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':')
    ]
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    header("Location: wallet.php?error=2");
    exit();
}

$data = json_decode($response, true);
$attrs = $data['data']['attributes'] ?? [];
$status = $attrs['status'] ?? '';
$amount = ($attrs['line_items'][0]['amount'] ?? 0) / 100;
$metadata = $attrs['metadata'] ?? [];
$paid_at = $attrs['paid_at'] ?? null;

if ($status !== 'paid' || !$paid_at) {
    header("Location: wallet.php?error=3");
    exit();
}

$user_id = $metadata['user_id'] ?? 0;
if (!$user_id) {
    header("Location: login.php");
    exit();
}

try {
    $pdo->beginTransaction();

    $check = $pdo->prepare("SELECT id FROM wallets WHERE user_id = ?");
    $check->execute([$user_id]);
    if (!$check->fetch()) {
        $pdo->prepare("INSERT INTO wallets (user_id, balance, currency) VALUES (?, 0, 'PHP')")->execute([$user_id]);
    }

    $pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?")->execute([$amount, $user_id]);

    $wallet = $pdo->prepare("SELECT id FROM wallets WHERE user_id = ?");
    $wallet->execute([$user_id]);
    $w = $wallet->fetch();

    $pdo->prepare("INSERT INTO transactions (wallet_id, sender_id, amount, transaction_type, description)
                   VALUES (?, ?, ?, 'deposit', 'PayMongo: ' . ?)")
       ->execute([$w['id'], $user_id, $amount, $session_id]);

    $pdo->commit();
    header("Location: wallet.php?success=1");
} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: wallet.php?error=4");
}
