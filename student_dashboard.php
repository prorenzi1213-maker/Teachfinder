<?php
session_start();
require_once __DIR__ . '/connections.php';

// 1. Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// SAFETY CHECK: Ensure the database connection exists
if (!isset($pdo)) {
    die("Database connection variable \$pdo not found. Check connections.php.");
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Student';

// Helper function for status
function getOfflineTime($last_activity) {
    if (!$last_activity) return 'Offline';
    $last_time = strtotime($last_activity);
    $diff = time() - $last_time;
    if ($diff < 300) return 'Online';
    $minutes = round($diff / 60);
    if ($minutes < 60) return $minutes . "m ago";
    return round($minutes / 60) . "h ago";
}

// 2. Fetch Messages Count (Updated to PDO syntax)
try {
    $unread_stmt = $pdo->prepare("SELECT count(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $unread_stmt->execute([$user_id]);
    $msg_count = $unread_stmt->fetchColumn();
} catch (PDOException $e) {
    $msg_count = 0;
}

// 3. Fetch Teachers (Including Bio and Rate - Updated to PDO syntax)
$search = $_GET['search'] ?? '';
$search_query = "%$search%";

try {
    $teachers_stmt = $pdo->prepare("SELECT t.id, t.user_id, t.subject, t.bio, t.hourly_rate, t.profile_pic, u.username, u.last_activity,
                                    (SELECT COUNT(*) FROM bookings WHERE teacher_id = t.id AND status = 'confirmed') as confirmed_bookings,
                                    (SELECT COUNT(*) FROM bookings WHERE teacher_id = t.id AND user_id = ? AND status = 'pending') as my_pending_bookings,
                                    (SELECT ROUND(AVG(r.rating), 1) FROM ratings r WHERE r.teacher_id = t.id) as avg_rating,
                                    (SELECT COUNT(*) FROM ratings r WHERE r.teacher_id = t.id) as rating_count
                                     FROM teachers t 
                                     JOIN users u ON t.user_id = u.id 
                                     WHERE (u.username LIKE ? OR t.subject LIKE ?)");
    $teachers_stmt->execute([$user_id, $search_query, $search_query]);
    $all_teachers = $teachers_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_teachers = [];
}

// 4. Fetch all teacher availability
$days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
$avail_by_teacher = [];
try {
    $avail_all = $pdo->query("SELECT * FROM teacher_availability ORDER BY day_of_week, start_time")->fetchAll();
    foreach ($avail_all as $a) {
        $avail_by_teacher[$a['teacher_id']][] = $a;
    }
} catch (PDOException $e) {}

// 5. Fetch notifications
try {
    $notif_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $notif_count_stmt->execute([$user_id]);
    $unread_notif_count = $notif_count_stmt->fetchColumn();

    $notif_list = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
    $notif_list->execute([$user_id]);
    $recent_notifs = $notif_list->fetchAll();
} catch (PDOException $e) {
    $unread_notif_count = 0;
    $recent_notifs = [];
}

// 6. Fetch completed bookings that haven't been rated yet
try {
    $unrated_stmt = $pdo->prepare("SELECT b.id, b.teacher_id, u.username as teacher_name, t.subject, b.start_time
                                   FROM bookings b
                                   JOIN teachers t ON b.teacher_id = t.id
                                   JOIN users u ON t.user_id = u.id
                                   LEFT JOIN ratings r ON r.booking_id = b.id
                                   WHERE b.user_id = ? AND b.status = 'completed' AND r.id IS NULL
                                   ORDER BY b.start_time DESC");
    $unrated_stmt->execute([$user_id]);
    $unrated_bookings = $unrated_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $unrated_bookings = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | TeachFinder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; }
        .navbar { background: white; border-bottom: 1px solid #e2e8f0; }
        .teacher-card { 
            border-radius: 20px; 
            border: none; 
            background: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            height: 100%;
        }
        .incomplete-card { opacity: 0.75; background: #fafafa; }
        .profile-img { width: 85px; height: 85px; object-fit: cover; border: 3px solid #f1f5f9; }
        .bio-text { font-size: 0.85rem; height: 4.5em; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; }
        .text-not-reg { color: #dc3545; font-weight: bold; font-size: 0.8rem; }
        .notif-badge { font-size: 0.65rem; min-width: 18px; height: 18px; line-height: 18px; padding: 0 4px; }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.15); } 100% { transform: scale(1); } }
        .notif-pulse { animation: pulse 1.5s infinite; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg py-3 sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary"><i class="fas fa-user-graduate me-2"></i>TeachFinder</a>
        <div class="d-flex align-items-center gap-3">
            <a href="message.php" class="text-secondary position-relative">
                <i class="fas fa-envelope fa-lg"></i>
                <?php if($msg_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;"><?= $msg_count ?></span>
                <?php endif; ?>
            </a>
            <div class="dropdown">
                <a href="#" class="text-secondary position-relative" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-bell fa-lg"></i>
                    <?php if ($unread_notif_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notif-badge"><?= $unread_notif_count > 9 ? '9+' : $unread_notif_count ?></span>
                    <?php endif; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 p-0" aria-labelledby="notifDropdown" style="width: 320px; border-radius: 10px; overflow: hidden;">
                    <li class="px-3 py-2 border-bottom bg-light"><span class="fw-bold small">Notifications <?= $unread_notif_count > 0 ? "<span class='badge bg-danger ms-1'>$unread_notif_count new</span>" : '' ?></span></li>
                    <?php if (!empty($recent_notifs)): ?>
                        <?php foreach ($recent_notifs as $n): ?>
                            <?php
                                $msg = $n['message'];
                                $link = '';
                                if (preg_match('/(teacher_profile\.php\?id=\d+)/', $msg, $m)) {
                                    $link = $m[1];
                                    $msg = trim(str_replace($m[0], '', $msg));
                                }
                            ?>
                            <li class="border-bottom">
                                <?php if ($link): ?>
                                    <a class="dropdown-item px-3 py-2 small" href="<?= $link ?>" style="white-space: normal;">
                                        <span class="d-block text-dark"><?= htmlspecialchars($msg) ?></span>
                                        <small class="text-muted"><?= date('M d, h:i A', strtotime($n['created_at'])) ?></small>
                                    </a>
                                <?php else: ?>
                                    <span class="dropdown-item px-3 py-2 small text-dark" style="white-space: normal;">
                                        <span class="d-block"><?= htmlspecialchars($n['message']) ?></span>
                                        <small class="text-muted"><?= date('M d, h:i A', strtotime($n['created_at'])) ?></small>
                                    </span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><span class="dropdown-item small text-muted text-center py-3">No new notifications</span></li>
                    <?php endif; ?>
                    <li><a class="dropdown-item small text-center py-2 text-primary fw-bold border-top" href="notifications.php">View All Activity</a></li>
                </ul>
            </div>
            <a href="wallet.php" class="btn btn-outline-secondary btn-sm rounded-pill">Wallet</a>
            <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill">Logout</a>
        </div>
    </div>
</nav>

<div class="container py-5">

    <?php if (!empty($unrated_bookings)): ?>
    <div class="mb-4">
        <div class="d-flex align-items-center mb-3">
            <i class="fas fa-star text-warning me-2"></i>
            <h5 class="fw-bold mb-0">Rate Your Tutor</h5>
        </div>
        <div class="row g-3">
            <?php foreach ($unrated_bookings as $ub): ?>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                    <div class="card-body text-center p-3">
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($ub['teacher_name']) ?></h6>
                        <small class="text-primary d-block mb-2"><?= htmlspecialchars($ub['subject']) ?></small>
                        <small class="text-muted d-block mb-3"><?= date('M d, Y', strtotime($ub['start_time'])) ?></small>
                        <button class="btn btn-warning btn-sm rounded-pill fw-bold px-4 rate-btn"
                                data-booking-id="<?= $ub['id'] ?>"
                                data-teacher="<?= htmlspecialchars($ub['teacher_name']) ?>">
                            <i class="fas fa-star me-1"></i>Rate
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <hr class="my-4">
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">Available Teachers</h2>
        <form action="" method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm rounded-pill" placeholder="Search subject..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3">Search</button>
        </form>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
        <?php foreach ($all_teachers as $t): 
            $status_text = getOfflineTime($t['last_activity']);
            $is_fully_registered = (!empty($t['bio']) && $t['hourly_rate'] > 0);
            $is_booked = ($t['confirmed_bookings'] > 0);
            $has_pending = ($t['my_pending_bookings'] > 0);
        ?>
        <div class="col">
            <div class="teacher-card p-4 text-center d-flex flex-column <?= !$is_fully_registered ? 'incomplete-card' : '' ?>">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <?php if ($has_pending): ?>
                        <span class="badge rounded-pill bg-info text-dark fw-bold">
                            <i class="fas fa-clock me-1"></i>Requesting
                        </span>
                    <?php elseif ($is_booked): ?>
                        <span class="badge rounded-pill bg-warning text-dark fw-bold">
                            <i class="fas fa-book me-1"></i>Booked
                        </span>
                    <?php else: ?>
                        <span class="badge rounded-pill <?= $status_text === 'Online' ? 'bg-success' : 'bg-light text-muted' ?>">
                            <?= $status_text ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if($t['hourly_rate'] > 0): ?>
                        <span class="fw-bold text-success">₱<?= number_format($t['hourly_rate'], 0) ?>/hr</span>
                    <?php else: ?>
                        <span class="text-not-reg">Rate: N/A</span>
                    <?php endif; ?>
                </div>

                <img src="uploads/<?= !empty($t['profile_pic']) ? $t['profile_pic'] : 'default.jpg' ?>" class="rounded-circle mx-auto mb-2 profile-img border">
                <a href="teacher_profile.php?id=<?= $t['id'] ?>" class="text-decoration-none">
                    <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($t['username']) ?></h6>
                </a>
                <small class="text-primary fw-bold mb-2 d-block"><?= htmlspecialchars($t['subject'] ?? 'General') ?></small>
                <div class="mb-2">
                    <?php if ($t['avg_rating']): ?>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fa<?= $i <= round($t['avg_rating']) ? 's' : 'r' ?> fa-star text-warning" style="font-size: 0.85rem;"></i>
                        <?php endfor; ?>
                        <small class="text-muted ms-1">(<?= $t['avg_rating'] ?>)</small>
                    <?php else: ?>
                        <small class="text-muted">No ratings yet</small>
                    <?php endif; ?>
                </div>

                <?php if (isset($avail_by_teacher[$t['id']])): ?>
                    <div class="mb-2 d-flex flex-wrap justify-content-center gap-1">
                        <?php foreach ($avail_by_teacher[$t['id']] as $a): ?>
                            <span class="badge bg-primary bg-opacity-10 text-primary" style="font-size: 0.65rem;">
                                <?= $days[$a['day_of_week']] ?> <?= substr($a['start_time'], 0, 5) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="bio-text mb-3 text-start">
                    <?php if(!empty($t['bio'])): ?>
                        <p class="text-muted mb-0 small"><?= htmlspecialchars($t['bio']) ?></p>
                    <?php else: ?>
                        <p class="text-not-reg mb-0 small"><i class="fas fa-exclamation-triangle me-1"></i>Profile incomplete</p>
                    <?php endif; ?>
                </div>

                <div class="mt-auto pt-3 border-top">
                    <?php if($is_fully_registered && !$is_booked && !$has_pending): ?>
                        <div class="d-flex gap-2 mb-2">
                            <a href="message.php?receiver_id=<?= $t['user_id'] ?>" class="btn btn-sm btn-outline-primary w-50 rounded-pill">Message</a>
                            <a href="transfer.php?teacher_user_id=<?= $t['user_id'] ?>" class="btn btn-sm btn-outline-success w-50 rounded-pill">Pay</a>
                        </div>
                        <button class="btn btn-primary w-100 rounded-pill fw-bold book-now-btn"
                                data-teacher-id="<?= $t['id'] ?>"
                                data-teacher-name="<?= htmlspecialchars($t['username']) ?>"
                                data-availability='<?= isset($avail_by_teacher[$t['id']]) ? htmlspecialchars(json_encode($avail_by_teacher[$t['id']])) : '[]' ?>'>Book Now</button>
                    <?php elseif($has_pending): ?>
                        <div class="d-flex gap-2 mb-2">
                            <a href="message.php?receiver_id=<?= $t['user_id'] ?>" class="btn btn-sm btn-outline-primary w-50 rounded-pill">Message</a>
                            <a href="transfer.php?teacher_user_id=<?= $t['user_id'] ?>" class="btn btn-sm btn-outline-success w-50 rounded-pill">Pay</a>
                        </div>
                        <button class="btn btn-info w-100 rounded-pill fw-bold text-dark" disabled><i class="fas fa-clock me-1"></i>Requesting</button>
                    <?php elseif($is_booked): ?>
                        <div class="d-flex gap-2 mb-2">
                            <a href="message.php?receiver_id=<?= $t['user_id'] ?>" class="btn btn-sm btn-outline-primary w-50 rounded-pill">Message</a>
                            <a href="transfer.php?teacher_user_id=<?= $t['user_id'] ?>" class="btn btn-sm btn-outline-success w-50 rounded-pill">Pay</a>
                        </div>
                        <button class="btn btn-secondary w-100 rounded-pill fw-bold" disabled><i class="fas fa-check-circle me-1"></i>Already Booked</button>
                    <?php else: ?>
                        <div class="text-muted small mb-2">Unavailable</div>
                        <button class="btn btn-secondary w-100 rounded-pill disabled" disabled>Incomplete Profile</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Booking Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-calendar-check me-2 text-primary"></i>Book a Lesson</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Scheduling with <strong id="bookingTeacherName"></strong></p>
                <div id="bookingAvail" class="mb-3">
                    <div class="p-2 bg-light rounded">
                        <span class="small fw-bold text-success"><i class="fas fa-calendar-alt me-1"></i>Click a time slot to auto-fill</span>
                        <div class="d-flex flex-wrap gap-1 mt-2" id="availSlots"></div>
                    </div>
                </div>
                <form id="bookingForm">
                    <input type="hidden" name="teacher_id" id="bookingTeacherId">
                    <div class="row g-3 mb-3">
                        <div class="col-12"><span class="small fw-bold text-primary">Start Date & Time</span></div>
                        <div class="col-7"><input type="date" name="start_date" class="form-control" id="bStartDate" required></div>
                        <div class="col-5"><input type="time" name="start_time" class="form-control" id="bStartTime" required></div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-12"><span class="small fw-bold text-primary">End Date & Time</span></div>
                        <div class="col-7"><input type="date" name="end_date" class="form-control" id="bEndDate" required></div>
                        <div class="col-5"><input type="time" name="end_time" class="form-control" id="bEndTime" required></div>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold text-primary">Learning Goals / Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="I'd like to focus on..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" id="submitBooking"><i class="fas fa-paper-plane me-1"></i>Send Request</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ratingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Rate Your Tutor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p class="text-muted mb-3">How was your session with <strong id="modalTeacherName"></strong>?</p>
                <div class="star-rating mb-3">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="far fa-star fa-2x text-warning star-select" data-value="<?= $i ?>" style="cursor: pointer; transition: 0.2s;"></i>
                    <?php endfor; ?>
                </div>
                <p class="small text-muted" id="ratingLabel">Click a star to rate</p>
                <input type="hidden" id="selectedRating" value="0">
                <input type="hidden" id="modalBookingId" value="">
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning rounded-pill px-4 fw-bold" id="submitRating" disabled>Submit Rating</button>
            </div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="ratingToast" class="toast align-items-center text-bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-bold" id="toastMessage"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.star-select');
    const ratingInput = document.getElementById('selectedRating');
    const submitBtn = document.getElementById('submitRating');
    const ratingLabel = document.getElementById('ratingLabel');
    const modal = new bootstrap.Modal(document.getElementById('ratingModal'));

    document.querySelectorAll('.rate-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('modalBookingId').value = this.dataset.bookingId;
            document.getElementById('modalTeacherName').textContent = this.dataset.teacher;
            ratingInput.value = '0';
            submitBtn.disabled = true;
            ratingLabel.textContent = 'Click a star to rate';
            stars.forEach(s => s.className = 'far fa-star fa-2x text-warning star-select');
            modal.show();
        });
    });

    stars.forEach(star => {
        star.addEventListener('mouseenter', function() {
            const val = parseInt(this.dataset.value);
            stars.forEach(s => {
                const sv = parseInt(s.dataset.value);
                s.className = (sv <= val ? 'fas' : 'far') + ' fa-star fa-2x text-warning star-select';
            });
        });

        star.addEventListener('mouseleave', function() {
            const selected = parseInt(ratingInput.value);
            stars.forEach(s => {
                const sv = parseInt(s.dataset.value);
                s.className = (sv <= selected ? 'fas' : 'far') + ' fa-star fa-2x text-warning star-select';
            });
        });

        star.addEventListener('click', function() {
            const val = parseInt(this.dataset.value);
            ratingInput.value = val;
            submitBtn.disabled = false;
            const labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
            ratingLabel.textContent = labels[val] + ' (' + val + '/5)';
            stars.forEach(s => {
                const sv = parseInt(s.dataset.value);
                s.className = (sv <= val ? 'fas' : 'far') + ' fa-star fa-2x text-warning star-select';
            });
        });
    });

    document.getElementById('submitRating').addEventListener('click', function() {
        const bookingId = document.getElementById('modalBookingId').value;
        const rating = document.getElementById('selectedRating').value;

        fetch('rate_teacher.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'booking_id=' + bookingId + '&rating=' + rating
        })
        .then(res => res.json())
        .then(data => {
            modal.hide();
            const toast = new bootstrap.Toast(document.getElementById('ratingToast'));
            document.getElementById('toastMessage').textContent = data.message;
            document.getElementById('ratingToast').className = 'toast align-items-center border-0 ' +
                (data.success ? 'text-bg-success' : 'text-bg-danger');
            toast.show();
            if (data.success) {
                setTimeout(() => location.reload(), 1500);
            }
        });
    });

    // Booking Modal
    const dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    const bookingModal = new bootstrap.Modal(document.getElementById('bookingModal'));

    function getNextDate(dayOfWeek) {
        const today = new Date();
        const todayDay = today.getDay();
        let diff = dayOfWeek - todayDay;
        if (diff < 0 || (diff === 0 && today.getHours() >= 17)) diff += 7;
        if (diff === 0) diff = 7;
        const next = new Date(today);
        next.setDate(today.getDate() + diff);
        return next.toISOString().split('T')[0];
    }

    document.querySelectorAll('.book-now-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('bookingTeacherId').value = this.dataset.teacherId;
            document.getElementById('bookingTeacherName').textContent = this.dataset.teacherName;
            document.getElementById('bookingForm').reset();

            const avail = JSON.parse(this.dataset.availability || '[]');
            const slotsDiv = document.getElementById('availSlots');
            slotsDiv.innerHTML = '';

            if (avail.length > 0) {
                avail.forEach(a => {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-success bg-opacity-10 text-success px-3 py-2';
                    badge.style.cssText = 'cursor:pointer;font-size:0.75rem;transition:0.2s;';
                    badge.textContent = dayNames[a.day_of_week] + ' ' + a.start_time.substring(0,5) + '-' + a.end_time.substring(0,5);
                    badge.addEventListener('click', function() {
                        const date = getNextDate(a.day_of_week);
                        document.getElementById('bStartDate').value = date;
                        document.getElementById('bStartTime').value = a.start_time.substring(0,5);
                        document.getElementById('bEndDate').value = date;
                        document.getElementById('bEndTime').value = a.end_time.substring(0,5);
                    });
                    badge.addEventListener('mouseenter', function() {
                        this.style.transform = 'scale(1.05)';
                    });
                    badge.addEventListener('mouseleave', function() {
                        this.style.transform = 'scale(1)';
                    });
                    slotsDiv.appendChild(badge);
                });
            } else {
                slotsDiv.innerHTML = '<span class="text-muted small">No schedule set</span>';
            }

            bookingModal.show();
        });
    });

    document.getElementById('submitBooking').addEventListener('click', function() {
        const form = document.getElementById('bookingForm');
        const data = new URLSearchParams(new FormData(form));

        fetch('book_lesson.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: data
        })
        .then(res => res.json())
        .then(result => {
            bookingModal.hide();
            form.reset();
            const toast = new bootstrap.Toast(document.getElementById('ratingToast'));
            document.getElementById('toastMessage').textContent = result.message;
            document.getElementById('ratingToast').className = 'toast align-items-center border-0 ' +
                (result.success ? 'text-bg-success' : 'text-bg-danger');
            toast.show();
            if (result.success) {
                setTimeout(() => location.reload(), 1500);
            }
        });
    });
});
</script>
</body>
</html>