<?php
session_start();
require_once 'config.php';

// 1. Access Control: Ensure only students can see this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/**
 * 2. THE FIX: 
 * - We join with the 'teachers' table (and 'users' table via teacher_id) 
 *   so the student can see WHO they booked.
 * - We filter by 'b.student_id' because that's the current user.
 */
$bookings_sql = "SELECT b.*, u.username as teacher_name, t.subject 
                 FROM bookings b 
                 JOIN teachers t ON b.teacher_id = t.id
                 JOIN users u ON t.user_id = u.id 
                 WHERE b.student_id = ? 
                 ORDER BY b.start_time DESC";

try {
    $stmt = $pdo->prepare($bookings_sql); // Fixed: Use $bookings_sql, not $query
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching bookings: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings | TeachFinder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --tf-blue: #4a90e2; --tf-bg: #eef2f7; }
        body { background-color: var(--tf-bg); font-family: 'Inter', sans-serif; }
        .tf-nav { background: var(--tf-blue); color: white; padding: 15px; border-radius: 0 0 20px 20px; }
        
        .booking-card { 
            background: white; 
            border-radius: 15px; 
            border: none; 
            padding: 15px; 
            margin-bottom: 15px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
        }
        .status-badge { font-size: 0.7rem; padding: 5px 12px; border-radius: 50px; font-weight: 600; text-transform: uppercase; }
        .status-pending { background: #fff4e5; color: #ff9800; }
        .status-confirmed { background: #e6fcf5; color: #0ca678; }
        .status-declined { background: #fff5f5; color: #fa5252; }
        
        .teacher-avatar { width: 45px; height: 45px; border-radius: 12px; object-fit: cover; }
        .nav-link-custom { color: white; text-decoration: none; transition: 0.2s; }
        .nav-link-custom:hover { opacity: 0.8; }
    </style>
</head>
<body>

<div class="tf-nav shadow-sm mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <a href="student_dashboard.php" class="nav-link-custom me-3">
                <i class="fas fa-arrow-left fa-lg"></i>
            </a>
            <span class="fw-bold">My Lesson Requests</span>
        </div>
        <a href="student_dashboard.php" class="nav-link-custom">
            <i class="fas fa-home fa-lg"></i>
        </a>
    </div>
</div>

<div class="container">
    <?php if (empty($bookings)): ?>
        <div class="text-center mt-5">
            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
            <p class="text-muted">You haven't booked any lessons yet.</p>
            <a href="student_dashboard.php" class="btn btn-primary rounded-pill px-4" style="background: var(--tf-blue); border:none;">Find a Tutor</a>
        </div>
    <?php else: ?>
        <?php foreach ($bookings as $booking): ?>
            <div class="booking-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="d-flex align-items-center">
                        <?php 
                            $img_src = !empty($booking['profile_pic']) && $booking['profile_pic'] !== 'default.png' 
                                       ? 'uploads/' . $booking['profile_pic'] 
                                       : 'https://ui-avatars.com/api/?name=' . urlencode($booking['teacher_name']) . '&background=4a90e2&color=fff';
                        ?>
                        <img src="<?= $img_src ?>" class="teacher-avatar me-3" alt="Teacher">
                        <div>
                            <h6 class="mb-0 fw-bold"><?= htmlspecialchars($booking['teacher_name']) ?></h6>
                            <small class="text-muted"><?= htmlspecialchars($booking['subject']) ?></small>
                        </div>
                    </div>
                    <span class="status-badge status-<?= strtolower($booking['status']) ?>">
                        <?= htmlspecialchars($booking['status']) ?>
                    </span>
                </div>

                <hr class="my-3 opacity-50">

                <div class="row text-center">
                    <div class="col-6 border-end">
                        <small class="text-muted d-block">DATE</small>
                        <span class="small fw-bold"><?= date('D, M j', strtotime($booking['lesson_date'])) ?></span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">TIME</small>
                        <span class="small fw-bold"><?= date('h:i A', strtotime($booking['lesson_time'])) ?></span>
                    </div>
                </div>

                <?php if (!empty($booking['notes'])): ?>
                    <div class="mt-3 p-2 bg-light rounded" style="font-size: 0.8rem;">
                        <i class="fas fa-sticky-note me-1 text-muted"></i> 
                        <?= htmlspecialchars($booking['notes']) ?>
                    </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <a href="message.php?receiver_id=<?= $booking['teacher_id'] ?>" class="btn btn-sm w-100 btn-outline-primary border-0" style="font-size: 0.75rem;">
                        <i class="fas fa-comment-dots me-1"></i> Message Teacher
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>