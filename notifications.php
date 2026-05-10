<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Correctly include your database connection
require_once 'connections.php';

// 2. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'student';
$dashboard_link = ($role === 'teacher') ? 'teacher_dashboard.php' : 'student_dashboard.php';

try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$user_id]);
    
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications | TeachFinder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Your Notifications</h5>
            <a href="<?= $dashboard_link ?>" class="btn btn-outline-primary btn-sm rounded-pill"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
        </div>
        <div class="card-body">
            
            <?php 
            // 5. FIX FOR LINE 31: Use count() on the array instead of ->num_rows
            if (count($notifications) > 0): 
            ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $row): ?>
                        <?php
                            $msg = $row['message'];
                            $link = '';
                            if (preg_match('/(teacher_profile\.php\?id=\d+)/', $msg, $m)) {
                                $link = $m[1];
                                $msg = trim(str_replace($m[0], '', $msg));
                            }
                        ?>
                        <div class="list-group-item">
                            <?php if ($link): ?>
                                <a href="<?= $link ?>" class="text-decoration-none text-dark d-block">
                                    <p class="mb-1"><?= htmlspecialchars($msg) ?> <span class="badge bg-warning text-dark">Rate Now</span></p>
                                </a>
                            <?php else: ?>
                                <p class="mb-1"><?= htmlspecialchars($msg) ?></p>
                            <?php endif; ?>
                            <small class="text-muted"><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-muted">You have no notifications yet.</p>
                    <a href="<?= $dashboard_link ?>" class="btn btn-primary btn-sm">Back to Dashboard</a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

</body>
</html>