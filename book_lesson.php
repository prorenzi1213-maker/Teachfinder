<?php
session_start();
require_once __DIR__ . '/connections.php'; // Using your verified connection file

// 1. Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : (isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0);

// Fetch Teacher Details
$stmt = $pdo->prepare("SELECT t.*, u.username as fullname, u.id as teacher_user_id 
                       FROM teachers t 
                       JOIN users u ON t.user_id = u.id 
                       WHERE t.id = ?");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch();

if (!$teacher) die("Teacher not found.");

$avail_stmt = $pdo->prepare("SELECT * FROM teacher_availability WHERE teacher_id = ? ORDER BY day_of_week, start_time");
$avail_stmt->execute([$teacher_id]);
$availability = $avail_stmt->fetchAll();

$days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

$status_msg = '';

$ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $start = $_POST['start_date'] . ' ' . $_POST['start_time'];
    $end = $_POST['end_date'] . ' ' . $_POST['end_time'];
    $notes = $_POST['notes'] ?? '';

    try {
        $pdo->beginTransaction();

        $sql = "INSERT INTO bookings (user_id, teacher_id, start_time, end_time, status, notes) 
                VALUES (?, ?, ?, ?, 'pending', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $teacher_id, $start, $end, $notes]);
        
        $new_booking_id = $pdo->lastInsertId();

        $msg = "New lesson request from " . $_SESSION['username'];
        
        $notif = $pdo->prepare("INSERT INTO notifications (user_id, message, booking_id, is_read) 
                                VALUES (?, ?, ?, 0)");
        $notif->execute([$teacher['teacher_user_id'], $msg, $new_booking_id]);

        $pdo->commit();

        if ($ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Booking request sent successfully!']);
            exit();
        }
        $status_msg = "<div class='alert alert-success border-0 shadow-sm'>Request sent successfully!</div>";
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Booking failed: ' . $e->getMessage()]);
            exit();
        }
        $status_msg = "<div class='alert alert-danger'>Booking Failed: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Lesson | TeachFinder</title>
    <!-- Modern Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { 
            background: #f0f2f5; 
            font-family: 'Inter', sans-serif;
            color: #1c1e21;
        }
        .booking-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            overflow: hidden;
            background: #ffffff;
        }
        .booking-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .form-section {
            padding: 2rem;
        }
        .input-group-text {
            background-color: #f8f9fa;
            border-right: none;
            color: #6c757d;
        }
        .form-control {
            border-left: none;
            padding: 0.75rem;
            background-color: #f8f9fa;
        }
        .form-control:focus {
            background-color: #fff;
            box-shadow: none;
            border-color: #dee2e6;
        }
        .label-style {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            display: block;
            color: #6c757d;
        }
        .btn-submit {
            background: #0d6efd;
            border: none;
            border-radius: 12px;
            padding: 1rem;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            background: #0b5ed7;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }
        .back-link {
            text-decoration: none;
            color: #6c757d;
            font-size: 0.9rem;
            transition: color 0.2s;
        }
        .back-link:hover { color: #0d6efd; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            
            <div class="mb-4 d-flex justify-content-between align-items-center">
                <a href="student_dashboard.php" class="back-link">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>

            <div class="booking-card">
                <div class="booking-header">
                    <i class="fas fa-calendar-check fa-3x mb-3"></i>
                    <h3 class="fw-bold mb-1">Book a Lesson</h3>
                    <p class="mb-0 opacity-75">Scheduling with <strong><?= htmlspecialchars($teacher['fullname']) ?></strong></p>
                </div>

                <div class="form-section">
                    <?= $status_msg ?>

                    <?php if (!empty($availability)): ?>
                    <div class="mb-4 p-3 bg-light rounded">
                        <span class="label-style fw-bold text-success"><i class="fas fa-calendar-alt me-1"></i>Teacher's Available Schedule</span>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <?php foreach ($availability as $a): ?>
                                <span class="badge bg-success bg-opacity-10 text-success px-3 py-2">
                                    <?= $days[$a['day_of_week']] ?>: <?= substr($a['start_time'], 0, 5) ?>-<?= substr($a['end_time'], 0, 5) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="teacher_id" value="<?= $teacher_id ?>">
                        <!-- Start Schedule -->
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <span class="label-style fw-bold text-primary">Start Date & Time</span>
                            </div>
                            <div class="col-md-7">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                    <input type="date" name="start_date" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                    <input type="time" name="start_time" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <!-- End Schedule -->
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <span class="label-style fw-bold text-primary">End Date & Time</span>
                            </div>
                            <div class="col-md-7">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-day"></i></span>
                                    <input type="date" name="end_date" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-hourglass-end"></i></span>
                                    <input type="time" name="end_time" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="mb-4">
                            <label class="label-style fw-bold text-primary">Learning Goals / Notes</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-pen"></i></span>
                                <textarea name="notes" class="form-control" rows="3" placeholder="I'd like to focus on..."></textarea>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-submit w-100 fw-bold shadow-sm">
                            <i class="fas fa-paper-plane me-2"></i>Send Booking Request
                        </button>
                    </form>
                </div>
            </div>

            <p class="text-center mt-4 text-muted small">
                The teacher will be notified immediately of your request.
            </p>
        </div>
    </div>
</div>

</body>
</html>