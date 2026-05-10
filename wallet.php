<?php
session_start();
require_once __DIR__ . '/connections.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

if (!isset($pdo)) {
    die("Database connection variable \$pdo not found.");
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ?");
$stmt->execute([$user_id]);
$wallet = $stmt->fetch();
$balance = $wallet ? $wallet['balance'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Wallet | TeachFinder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', sans-serif; }
        .card { border-radius: 20px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    </style>
</head>
<body class="p-4">
    <div class="container" style="max-width: 600px;">

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success text-center">Payment successful! Wallet topped up.</div>
        <?php elseif (isset($_GET['cancel'])): ?>
            <div class="alert alert-warning text-center">Payment was cancelled.</div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="alert alert-danger text-center">Payment verification failed. Please contact support.</div>
        <?php endif; ?>

        <div class="card p-4 mb-4 text-center">
            <h6 class="text-muted text-uppercase small fw-bold">Current Balance</h6>
            <h1 class="fw-bold text-primary mb-0">₱<?= number_format($balance, 2) ?></h1>
        </div>

        <div class="card p-4">
            <h5 class="fw-bold mb-4"><i class="fas fa-wallet me-2 text-primary"></i>Top Up Wallet</h5>
            <div class="mb-4">
                <label class="form-label text-muted small fw-bold text-uppercase">Amount (PHP)</label>
                <div class="input-group">
                    <span class="input-group-text bg-white">₱</span>
                    <input type="number" id="payAmount" class="form-control" placeholder="0.00" min="10" step="0.01" value="100">
                </div>
                <small class="text-muted">Minimum ₱10</small>
            </div>

            <div class="mb-4">
                <label class="form-label text-muted small fw-bold text-uppercase">Pay with</label>
                <div class="list-group">
                    <label class="list-group-item d-flex align-items-center py-3">
                        <i class="fas fa-mobile-alt text-success me-3 fa-lg"></i>
                        <div>
                            <strong>GCash / Maya / Card</strong><br>
                            <small class="text-muted">Powered by PayMongo</small>
                        </div>
                    </label>
                </div>
            </div>

            <div id="payloader" class="text-center d-none py-3">
                <div class="spinner-border text-primary mb-2"></div>
                <p class="small text-muted">Creating payment link...</p>
            </div>

            <button type="button" id="payNowBtn" class="btn btn-primary w-100 py-3 fw-bold">
                <i class="fas fa-credit-card me-2"></i>Pay Now
            </button>
        </div>

        <div class="text-center mt-4">
            <a href="student_dashboard.php" class="text-muted text-decoration-none small">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('payNowBtn').addEventListener('click', function() {
    const amount = document.getElementById('payAmount').value;

    if (!amount || amount < 10) {
        alert('Please enter a valid amount (minimum ₱10)');
        return;
    }

    document.getElementById('payloader').classList.remove('d-none');
    this.disabled = true;

    fetch('paymongo_checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'amount=' + encodeURIComponent(amount)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = data.checkout_url;
        } else {
            alert('Error: ' + data.message);
            document.getElementById('payloader').classList.add('d-none');
            document.getElementById('payNowBtn').disabled = false;
        }
    })
    .catch(() => {
        alert('Connection error. Please try again.');
        document.getElementById('payloader').classList.add('d-none');
        document.getElementById('payNowBtn').disabled = false;
    });
});
</script>
</body>
</html>
