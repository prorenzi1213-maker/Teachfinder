<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Ensure booking_id is captured from the URL
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

try {
    $stmt = $pdo->prepare("SELECT b.*, u.username as student_name, u.email FROM bookings b 
                           JOIN users u ON b.user_id = u.id WHERE b.id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

if (!$booking) {
    die("Booking not found! Looking for ID: " . $booking_id);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking Details | TeachFinder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Plus Jakarta Sans', sans-serif; }
        .card { border-radius: 24px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
    </style>
</head>
<body class="p-4">
    <div class="container" style="max-width: 600px;">
        <div class="card p-4">
            <a href="teacher_dashboard.php" class="text-decoration-none text-muted mb-3"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
            
            <h4 class="fw-bold mb-3">Lesson Request Details</h4>
            <hr>
            
            <p><strong>Student:</strong> <?= htmlspecialchars($booking['student_name']) ?></p>
            
            <p><strong>Time:</strong> 
                <?php 
                if (!empty($booking['start_time'])) {
                    echo date('M d, Y', strtotime($booking['start_time'])) . " (" . date('H:i', strtotime($booking['start_time'])) . " - " . date('H:i', strtotime($booking['end_time'])) . ")";
                } else {
                    echo "Date/Time not set.";
                }
                ?>
            </p>
            
            <div class="bg-light p-3 rounded-3 mb-4">
                <strong>Student's Message:</strong><br>
                <?= nl2br(htmlspecialchars($booking['notes'] ?: 'No notes provided.')) ?>
            </div>

            <?php if ($booking['status'] === 'pending'): ?>
                <form method="POST" action="update_booking.php">
                    <input type="hidden" name="id" value="<?= $booking_id ?>">
                    <div class="d-flex gap-2">
                        <button name="status" value="confirmed" class="btn btn-success flex-grow-1"><i class="fas fa-check me-2"></i>Confirm</button>
                        <button name="status" value="cancelled" class="btn btn-danger flex-grow-1"><i class="fas fa-times me-2"></i>Decline</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-info text-center">Status: <strong><?= strtoupper(htmlspecialchars($booking['status'])) ?></strong></div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>