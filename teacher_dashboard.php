<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/connections.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

$teacher_row = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_row->execute([$user_id]);
$teacher_table_id = $teacher_row->fetchColumn();

if (!$teacher_table_id) {
    die("Teacher profile not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_availability'])) {
    $days = $_POST['days'] ?? [];
    $start_times = $_POST['start_time'] ?? [];
    $end_times = $_POST['end_time'] ?? [];

    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM teacher_availability WHERE teacher_id = ?")->execute([$teacher_table_id]);

        $insert = $pdo->prepare("INSERT INTO teacher_availability (teacher_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)");
        foreach ($days as $i => $day) {
            if (isset($start_times[$i], $end_times[$i]) && !empty($start_times[$i]) && !empty($end_times[$i])) {
                $insert->execute([$teacher_table_id, (int)$day, $start_times[$i], $end_times[$i]]);
            }
        }
        $pdo->commit();
        $message = "<div class='alert alert-success shadow-sm'>Availability updated!</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger shadow-sm'>Error saving availability.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $rate = (float)($_POST['rate'] ?? 0);
    $bio = trim($_POST['bio'] ?? '');

    try {
        $pdo->beginTransaction();

        $stmt1 = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
        $stmt1->execute([$full_name, $email, $user_id]);

        $stmt2 = $pdo->prepare("UPDATE teachers SET subject = ?, hourly_rate = ?, bio = ? WHERE user_id = ?");
        $stmt2->execute([$subject, $rate, $bio, $user_id]);

        if (!empty($_FILES['profile_image']['name'])) {
            $target_dir = __DIR__ . '/uploads/';
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($file_ext, $allowed)) {
                $new_filename = 'teacher_' . $user_id . '_' . time() . '.' . $file_ext;
                $target_file = $target_dir . $new_filename;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                    $old_pic = $_POST['old_pic'] ?? '';
                    if ($old_pic && $old_pic !== 'default.jpg' && file_exists($target_dir . $old_pic)) {
                        unlink($target_dir . $old_pic);
                    }
                    $stmt3 = $pdo->prepare("UPDATE teachers SET profile_pic = ? WHERE user_id = ?");
                    $stmt3->execute([$new_filename, $user_id]);
                }
            }
        }

        $pdo->commit();
        $message = "<div class='alert alert-success shadow-sm'>Profile updated successfully!</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger shadow-sm'>Update failed: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

try {
    $profile_stmt = $pdo->prepare("SELECT t.*, u.username, u.email FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.user_id = ?");
    $profile_stmt->execute([$user_id]);
    $profile = $profile_stmt->fetch();

    if (!$profile) {
        throw new PDOException("No teacher profile found for this account. Please contact support.");
    }

    $notif_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $notif_stmt->execute([$user_id]);
    $unread_notif_count = $notif_stmt->fetchColumn();

    $notif_list = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
    $notif_list->execute([$user_id]);
    $recent_notifs = $notif_list->fetchAll();

    $msg_stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $msg_stmt->execute([$user_id]);
    $msg_count = $msg_stmt->fetchColumn();

    $unread_count = $unread_notif_count + $msg_count;

    $unread_msgs = $pdo->prepare("SELECT m.*, u.username as sender_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.receiver_id = ? AND m.is_read = 0 ORDER BY m.sent_at DESC LIMIT 5");
    $unread_msgs->execute([$user_id]);
    $recent_msgs = $unread_msgs->fetchAll();

    $bookings_stmt = $pdo->prepare("SELECT b.*, u.username as student_name 
                                    FROM bookings b 
                                    JOIN users u ON b.user_id = u.id 
                                    WHERE b.teacher_id = ? 
                                    ORDER BY b.start_time DESC");
    $bookings_stmt->execute([$teacher_table_id]);
    $lesson_requests = $bookings_stmt->fetchAll();

    $avail_stmt = $pdo->prepare("SELECT * FROM teacher_availability WHERE teacher_id = ? ORDER BY day_of_week, start_time");
    $avail_stmt->execute([$teacher_table_id]);
    $availability = $avail_stmt->fetchAll();

} catch (PDOException $e) {
    $lesson_requests = [];
    $recent_notifs = [];
    $message = "<div class='alert alert-danger shadow-sm'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | TeachFinder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; font-family: 'Inter', sans-serif; }
        .card { border-radius: 12px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .profile-img { width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .stat-card { background: #fff; padding: 1.5rem; border-radius: 12px; text-align: center; border-bottom: 4px solid #0d6efd; }
        .nav-link { color: #6c757d; transition: 0.3s; }
        .nav-link:hover { color: #0d6efd; }
        .notif-badge { font-size: 0.65rem; min-width: 18px; height: 18px; line-height: 18px; padding: 0 4px; }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.15); } 100% { transform: scale(1); } }
        .notif-pulse { animation: pulse 1.5s infinite; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="#"><i class="fas fa-graduation-cap me-2"></i>TeachFinder</a>
        <div class="ms-auto d-flex align-items-center">
            
            <!-- Messages Icon -->
            <a href="message.php" class="nav-link px-3 position-relative">
                <i class="fas fa-envelope fa-lg"></i>
                <?php if ($msg_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notif-badge notif-pulse" style="margin-top: -4px; margin-left: -4px;">
                        <?= $msg_count > 9 ? '9+' : $msg_count ?>
                    </span>
                <?php endif; ?>
            </a>

            <!-- Notification Button -->
            <div class="dropdown me-2">
                <a href="#" class="nav-link px-2 position-relative" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-bell fa-lg"></i>
                    <?php if (isset($unread_notif_count) && $unread_notif_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notif-badge notif-pulse" style="margin-top: -4px; margin-left: -4px;">
                            <?= $unread_notif_count > 9 ? '9+' : $unread_notif_count ?>
                        </span>
                    <?php endif; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 p-0" aria-labelledby="notifDropdown" style="width: 320px; border-radius: 10px; overflow: hidden;">
                    <li class="px-3 py-2 border-bottom bg-light"><span class="fw-bold small">Notifications <?= $unread_notif_count > 0 ? "<span class='badge bg-danger ms-1'>$unread_notif_count new</span>" : '' ?></span></li>
                    <?php if (!empty($recent_notifs)): ?>
                        <?php foreach ($recent_notifs as $n): ?>
                            <li class="border-bottom">
                                <a class="dropdown-item px-3 py-2 small" href="notifications.php" style="white-space: normal;">
                                    <span class="d-block text-dark"><?= htmlspecialchars($n['message']) ?></span>
                                    <small class="text-muted"><?= date('M d, h:i A', strtotime($n['created_at'])) ?></small>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><span class="dropdown-item small text-muted text-center py-3">No new notifications</span></li>
                    <?php endif; ?>
                    <li><a class="dropdown-item small text-center py-2 text-primary fw-bold border-top" href="notifications.php">View All Activity</a></li>
                </ul>
            </div>

            <!-- Logout Button -->
            <a href="logout.php" class="btn btn-sm btn-outline-danger rounded-pill px-3">Logout</a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <?= $message ?>

    <?php if (isset($unread_notif_count) && $unread_notif_count > 0): ?>
        <div class="alert alert-danger py-2 d-flex justify-content-between align-items-center shadow-sm mb-3" style="border-radius: 10px;">
            <span><i class="fas fa-bell me-2"></i> You have <strong><?= $unread_notif_count ?></strong> unread notification<?= $unread_notif_count > 1 ? 's' : '' ?></span>
            <a href="notifications.php" class="btn btn-sm btn-outline-danger">View</a>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card shadow-sm">
                <div class="text-muted small">Active Bookings</div>
                <h3 class="fw-bold mb-0"><?= count($lesson_requests) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card shadow-sm" style="border-bottom-color: #198754;">
                <div class="text-muted small">Hourly Rate</div>
                <h3 class="fw-bold mb-0">₱<?= number_format($profile['hourly_rate'] ?? 0, 0) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card shadow-sm" style="border-bottom-color: #ffc107;">
                <div class="text-muted small">Messages</div>
                <h3 class="fw-bold mb-0"><?= $msg_count ?></h3>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Profile Column -->
        <div class="col-lg-4">
            <div class="card p-4">
                <form method="POST" enctype="multipart/form-data">
                    <div class="text-center mb-4">
                        <img src="uploads/<?= !empty($profile['profile_pic']) ? $profile['profile_pic'] : 'default.jpg' ?>" class="profile-img mb-3">
                        <input type="file" name="profile_image" class="form-control form-control-sm">
                        <input type="hidden" name="old_pic" value="<?= $profile['profile_pic'] ?? '' ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($profile['username'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($profile['email'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Subject</label>
                        <input type="text" name="subject" class="form-control" value="<?= htmlspecialchars($profile['subject'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Rate (PHP)</label>
                        <input type="number" name="rate" class="form-control" value="<?= $profile['hourly_rate'] ?? 0 ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Bio</label>
                        <textarea name="bio" class="form-control" rows="3"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" name="update_profile" class="btn btn-primary w-100 fw-bold">Update Profile</button>
                </form>
            </div>

            <!-- Availability Section -->
            <div class="card p-4 mt-4">
                <h6 class="fw-bold mb-3"><i class="fas fa-clock me-2 text-primary"></i>Available Schedule</h6>
                <form method="POST">
                    <div id="avail-slots">
                        <?php if (!empty($availability)): ?>
                            <?php foreach ($availability as $slot): ?>
                            <div class="row g-2 mb-2 avail-row">
                                <div class="col-4">
                                    <select name="days[]" class="form-select form-select-sm">
                                        <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d => $label): ?>
                                            <option value="<?= $d ?>" <?= $slot['day_of_week'] == $d ? 'selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-3">
                                    <input type="time" name="start_time[]" class="form-control form-control-sm" value="<?= substr($slot['start_time'], 0, 5) ?>">
                                </div>
                                <div class="col-3">
                                    <input type="time" name="end_time[]" class="form-control form-control-sm" value="<?= substr($slot['end_time'], 0, 5) ?>">
                                </div>
                                <div class="col-2">
                                    <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-avail"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="row g-2 mb-2 avail-row">
                                <div class="col-4">
                                    <select name="days[]" class="form-select form-select-sm">
                                        <option value="1">Mon</option><option value="2">Tue</option><option value="3">Wed</option><option value="4">Thu</option><option value="5">Fri</option><option value="6">Sat</option><option value="0">Sun</option>
                                    </select>
                                </div>
                                <div class="col-3">
                                    <input type="time" name="start_time[]" class="form-control form-control-sm">
                                </div>
                                <div class="col-3">
                                    <input type="time" name="end_time[]" class="form-control form-control-sm">
                                </div>
                                <div class="col-2">
                                    <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-avail"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="add-avail" class="btn btn-outline-primary btn-sm w-100 mb-2"><i class="fas fa-plus me-1"></i>Add Slot</button>
                    <button type="submit" name="save_availability" class="btn btn-primary w-100 fw-bold">Save Schedule</button>
                </form>
            </div>
        </div>

        <!-- Bookings Column -->
        <div class="col-lg-8">
            <div class="card p-0 overflow-hidden">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">Recent Bookings</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr class="small text-uppercase">
                                <th class="ps-4">Student</th>
                                <th>Schedule</th>
                                <th>Status</th>
                                <th class="text-center">Done Tutoring</th>
                                <th class="text-center">Accept/Decline</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lesson_requests as $b): ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?= htmlspecialchars($b['student_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($b['start_time'])) ?></td>
                                <td>
                                    <?php 
                                        $colors = ['pending' => 'info', 'confirmed' => 'success', 'cancelled' => 'danger', 'completed' => 'secondary'];
                                        $status_color = $colors[$b['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $status_color ?>"><?= ucfirst($b['status']) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($b['status'] == 'confirmed'): ?>
                                        <form method="POST" action="complete_booking.php" onsubmit="return confirm('Mark this session as completed?');">
                                            <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-warning fw-bold">
                                                <i class="fas fa-check-double me-1"></i>Done Tutoring
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($b['status'] == 'pending'): ?>
                                        <form method="POST" action="update_booking.php" class="d-inline">
                                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                            <button type="submit" name="status" value="confirmed" class="btn btn-sm btn-success me-1 fw-bold"><i class="fas fa-check me-1"></i>Accept</button>
                                            <button type="button" class="btn btn-sm btn-outline-danger fw-bold reject-btn" data-booking-id="<?= $b['id'] ?>" data-student="<?= htmlspecialchars($b['student_name']) ?>"><i class="fas fa-times me-1"></i>Decline</button>
                                        </form>
                                    <?php elseif ($b['status'] == 'cancelled' && !empty($b['rejection_reason'])): ?>
                                        <span class="text-muted small" tabindex="0" data-bs-toggle="tooltip" title="Reason: <?= htmlspecialchars($b['rejection_reason']) ?>">
                                            Declined <i class="fas fa-info-circle ms-1"></i>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($b['status'] == 'pending'): ?>
                                        <span class="text-muted small">Awaiting</span>
                                    <?php elseif ($b['status'] == 'confirmed'): ?>
                                        <span class="text-success small fw-bold">Ongoing</span>
                                    <?php elseif ($b['status'] == 'completed'): ?>
                                        <span class="text-success small fw-bold">Done</span>
                                    <?php elseif ($b['status'] == 'cancelled'): ?>
                                        <span class="text-danger small">Cancelled</span>
                                    <?php else: ?>
                                        <span class="text-muted small"><?= ucfirst($b['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($lesson_requests)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No bookings yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px;">
            <form method="POST" action="update_booking.php">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger"><i class="fas fa-times-circle me-2"></i>Decline Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Provide a reason for declining <strong id="rejectStudentName"></strong>:</p>
                    <input type="hidden" name="id" id="rejectBookingId">
                    <input type="hidden" name="status" value="cancelled">
                    <textarea name="rejection_reason" class="form-control" rows="3" placeholder="e.g., I'm unavailable on that date..." required></textarea>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">Decline</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));

    document.querySelectorAll('.reject-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('rejectBookingId').value = this.dataset.bookingId;
            document.getElementById('rejectStudentName').textContent = this.dataset.student;
            rejectModal.show();
        });
    });

    document.getElementById('add-avail').addEventListener('click', function() {
        const row = document.querySelector('.avail-row').cloneNode(true);
        row.querySelectorAll('select, input').forEach(el => el.value = '');
        row.querySelector('.remove-avail').addEventListener('click', function() {
            this.closest('.avail-row').remove();
        });
        document.getElementById('avail-slots').appendChild(row);
    });

    document.querySelectorAll('.remove-avail').forEach(btn => {
        btn.addEventListener('click', function() {
            if (document.querySelectorAll('.avail-row').length > 1) {
                this.closest('.avail-row').remove();
            }
        });
    });

    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });
});
</script>
</body>
</html>