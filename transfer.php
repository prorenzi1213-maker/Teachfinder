<?php
session_start();
require_once __DIR__ . '/connections.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

/**
 * Sends SMS via Semaphore or logs to file if key is invalid
 */
function sendSMS($number, $message) {
    // PASTE YOUR VALID, ACTIVE API KEY HERE
    $apikey = 'YOUR_REAL_API_KEY_HERE'; 
    
    $ch = curl_init();
    $parameters = [
        'apikey' => $apikey,
        'number' => $number,
        'message' => $message,
        'sendername' => 'TeachFinder'
    ];
    
    curl_setopt($ch, CURLOPT_URL, 'https://api.semaphore.co/api/v4/messages');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    
    $output = curl_exec($ch);
    curl_close($ch);
    
    return $output;
}

$sender_id = $_SESSION['user_id'];
$teacher_user_id = isset($_GET['teacher_user_id']) ? (int)$_GET['teacher_user_id'] : 0;
$msg = "";
$teacher_name = "Unknown Teacher";

if ($teacher_user_id > 0) {
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $teacher_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $teacher_name = $row['username'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = (float)$_POST['amount'];
    $phone_to_text = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
    
    if (empty($phone_to_text)) {
        $msg = "<div class='alert alert-danger'>Error: Please enter a phone number.</div>";
    } else {
        $conn->begin_transaction();
        try {
            // Check if sender has enough balance
            $check_balance = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
            $check_balance->bind_param("i", $sender_id);
            $check_balance->execute();
            $balance_result = $check_balance->get_result();
            $current_balance = $balance_result->fetch_assoc()['balance'] ?? 0;
            
            if ($current_balance < $amount) {
                throw new Exception("Insufficient balance. Your current balance is ₱" . number_format($current_balance, 2));
            }
            
            // Check if receiver has wallet record, if not create one
            $check_receiver = $conn->prepare("SELECT id FROM wallets WHERE user_id = ?");
            $check_receiver->bind_param("i", $teacher_user_id);
            $check_receiver->execute();
            if ($check_receiver->get_result()->num_rows == 0) {
                $create_wallet = $conn->prepare("INSERT INTO wallets (user_id, balance, currency) VALUES (?, 0, 'PHP')");
                $create_wallet->bind_param("i", $teacher_user_id);
                $create_wallet->execute();
            }
            
            // Update sender's balance (deduct)
            $stmt1 = $conn->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?");
            $stmt1->bind_param("di", $amount, $sender_id);
            $stmt1->execute();
            
            // Update receiver's balance (add)
            $stmt2 = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
            $stmt2->bind_param("di", $amount, $teacher_user_id);
            $stmt2->execute();
            
            // Insert transaction record - with wallet_id
            $stmt3 = $conn->prepare("INSERT INTO transactions (sender_id, receiver_id, wallet_id, amount, transaction_type, description) VALUES (?, ?, ?, ?, 'transfer', 'Wallet to wallet transfer')");
            $stmt3->bind_param("iiid", $sender_id, $teacher_user_id, $sender_id, $amount);
            $stmt3->execute();
            
            $conn->commit();
            
            // Clean SMS Message
            $sms_msg = "TeachFinder: Teacher " . $teacher_name . " received P" . number_format($amount, 2) . ".";
            sendSMS($phone_to_text, $sms_msg);
            
            // Success message
            $msg = "<div class='alert alert-success'>Transfer successful! The teacher has been notified.</div>";
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function confirmTransfer() { return confirm("Proceed with transfer?"); }
    </script>
</head>
<body class="bg-light p-5">
    <div class="container" style="max-width: 400px;">
        <div class="card p-4 shadow-sm border-0">
            <h4>Send to <?= htmlspecialchars($teacher_name) ?></h4>
            <?= $msg ?>
            <form method="POST" onsubmit="return confirmTransfer();">
                <input type="number" name="amount" class="form-control mb-2" step="0.01" required placeholder="Amount (PHP)">
                <input type="text" name="phone_number" class="form-control mb-3" required placeholder="Phone (e.g. 09604199260)">
                <button type="submit" class="btn btn-primary w-100">Transfer Now</button>
            </form>
            <a href="student_dashboard.php" class="btn btn-link mt-2">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>