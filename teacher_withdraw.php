<?php
session_start();
require_once 'config.php';

// Security check: Only teachers
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$message = "";

// Fetch current earnings balance
$stmt_bal = $pdo->prepare("SELECT balance FROM teachers WHERE id = ?");
$stmt_bal->execute([$teacher_id]);
$balance = $stmt_bal->fetchColumn() ?: 0.00;

// Handle Withdrawal Request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = (float)$_POST['amount'];
    $method = $_POST['method'];
    $details = $_POST['details'];

    if ($amount > $balance) {
        $message = "<div class='alert alert-danger'>Insufficient balance!</div>";
    } elseif ($amount < 10) {
        $message = "<div class='alert alert-warning'>Minimum withdrawal is $10.00</div>";
    } else {
        // 1. Deduct from teacher balance immediately
        $update = $pdo->prepare("UPDATE teachers SET balance = balance - ? WHERE id = ?");
        $update->execute([$amount, $teacher_id]);

        // 2. Record the request
        $ins = $pdo->prepare("INSERT INTO withdrawals (teacher_id, amount, payout_method, payout_details) VALUES (?, ?, ?, ?)");
        $ins->execute([$teacher_id, $amount, $method, $details]);

        $message = "<div class='alert alert-success'>Request submitted! Admin will process it soon.</div>";
        // Refresh balance after deduction
        $balance -= $amount;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Withdraw Earnings | TeachFinder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Inter', sans-serif; }
        .withdraw-card { max-width: 500px; margin: 50px auto; background: white; border-radius: 15px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .balance-box { background: #4a90e2; color: white; border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 25px; }
    </style>
</head>
<body>

<div class="container">
    <div class="withdraw-card">
        <a href="teacher_dashboard.php" class="text-decoration-none small mb-3 d-block">← Back to Dashboard</a>
        <h4 class="fw-bold mb-4">Withdraw Funds</h4>
        
        <?= $message ?>

        <div class="balance-box">
            <small class="opacity-75">Your Earnings</small>
            <h2 class="fw-bold mb-0">$<?= number_format($balance, 2) ?></h2>
        </div>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold">Amount to Withdraw</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" name="amount" step="0.01" class="form-control" placeholder="0.00" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Payout Method</label>
                <select name="method" class="form-select" required>
                    <option value="PayPal">PayPal</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-bold">Payment Details (Email or IBAN)</label>
                <input type="text" name="details" class="form-control" placeholder="e.g. email@example.com" required>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-pill">Submit Request</button>
        </form>
    </div>
</div>

</body>
</html>