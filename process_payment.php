<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/connections.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
    $user_id = $_SESSION['user_id'];

    if ($amount > 0) {
        // Check if user has wallet record
        $check = $conn->prepare("SELECT id FROM wallets WHERE user_id = ?");
        $check->bind_param("i", $user_id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows == 0) {
            // Create wallet record if wala pa
            $insert = $conn->prepare("INSERT INTO wallets (user_id, balance, currency) VALUES (?, 0, 'PHP')");
            $insert->bind_param("i", $user_id);
            $insert->execute();
        }
        
        // Update wallet balance
        $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
        $stmt->bind_param("di", $amount, $user_id);
        $stmt->execute();
        
        // Display success message
        echo "<!DOCTYPE html>";
        echo "<html><head><title>Payment Processing</title>";
        echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>";
        echo "</head><body class='d-flex justify-content-center align-items-center vh-100'>";
        echo "<div class='text-center'>";
        echo "<div class='alert alert-success p-4 rounded'>";
        echo "<i class='fas fa-check-circle fa-3x text-success mb-3'></i>";
        echo "<h1>Payment Successful!</h1>";
        echo "<p>Amount added: <strong>₱" . number_format($amount, 2) . "</strong></p>";
        echo "<p>User ID: " . $user_id . "</p>";
        echo "<p>Redirecting to wallet in <span id='countdown'>3</span> seconds...</p>";
        echo "</div></div>";
        echo "<script>";
        echo "let seconds = 3;";
        echo "setInterval(() => {";
        echo "  seconds--;";
        echo "  document.getElementById('countdown').innerText = seconds;";
        echo "  if(seconds === 0) window.location.href = 'wallet.php?success=1';";
        echo "}, 1000);";
        echo "</script>";
        echo "</body></html>";
        exit();
    } else {
        echo "Invalid amount. <a href='wallet.php'>Go back</a>";
    }
} else {
    echo "No form submitted. <a href='wallet.php'>Go back</a>";
}
?>