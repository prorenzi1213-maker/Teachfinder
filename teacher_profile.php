<?php
session_start();
require_once __DIR__ . '/connections.php';

$teacher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_student = isset($_SESSION['user_id']) && $_SESSION['role'] === 'student';
$student_id = $_SESSION['user_id'] ?? 0;

$can_rate = false;
$unrated_booking_id = 0;
if ($is_student) {
    $check = $pdo->prepare("SELECT b.id FROM bookings b
                            LEFT JOIN ratings r ON r.booking_id = b.id
                            WHERE b.user_id = ? AND b.teacher_id = ? AND b.status = 'completed' AND r.id IS NULL
                            LIMIT 1");
    $check->execute([$student_id, $teacher_id]);
    $row = $check->fetch();
    if ($row) {
        $can_rate = true;
        $unrated_booking_id = $row['id'];
    }
}

$stmt = $pdo->prepare("SELECT t.*, u.username, u.last_activity
                       FROM teachers t
                       JOIN users u ON t.user_id = u.id
                       WHERE t.id = ?");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch();

if (!$teacher) {
    die("Teacher not found.");
}

$avail_stmt = $pdo->prepare("SELECT * FROM teacher_availability WHERE teacher_id = ? ORDER BY day_of_week, start_time");
$avail_stmt->execute([$teacher_id]);
$availability = $avail_stmt->fetchAll();

$days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

$rating_stmt = $pdo->prepare("SELECT r.rating, r.created_at, u.username as student_name
                              FROM ratings r
                              JOIN users u ON r.student_id = u.id
                              WHERE r.teacher_id = ?
                              ORDER BY r.created_at DESC");
$rating_stmt->execute([$teacher_id]);
$ratings = $rating_stmt->fetchAll();

$avg_stmt = $pdo->prepare("SELECT ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as total
                           FROM ratings WHERE teacher_id = ?");
$avg_stmt->execute([$teacher_id]);
$avg = $avg_stmt->fetch();

function getOfflineTime($last_activity) {
    if (!$last_activity) return 'Offline';
    $last_time = strtotime($last_activity);
    $diff = time() - $last_time;
    if ($diff < 300) return 'Online';
    $minutes = round($diff / 60);
    if ($minutes < 60) return $minutes . "m ago";
    return round($minutes / 60) . "h ago";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($teacher['username']) ?> | TeachFinder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; }
        .profile-header { background: white; border-radius: 20px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .profile-img { width: 120px; height: 120px; object-fit: cover; border: 4px solid #f1f5f9; }
        .rating-star { font-size: 1.1rem; }
    </style>
</head>
<body>

<div class="container py-5">
    <a href="javascript:history.back()" class="text-secondary text-decoration-none small mb-3 d-inline-block"><i class="fas fa-arrow-left me-1"></i>Back</a>

    <div class="profile-header p-4 mb-4">
        <div class="row align-items-center">
            <div class="col-auto">
                <img src="uploads/<?= !empty($teacher['profile_pic']) ? $teacher['profile_pic'] : 'default.jpg' ?>" class="rounded-circle profile-img">
            </div>
            <div class="col">
                <h3 class="fw-bold mb-1"><?= htmlspecialchars($teacher['username']) ?></h3>
                <p class="text-primary fw-bold mb-1"><?= htmlspecialchars($teacher['subject'] ?? 'General') ?></p>
                <span class="badge rounded-pill <?= getOfflineTime($teacher['last_activity']) === 'Online' ? 'bg-success' : 'bg-light text-muted' ?>">
                    <?= getOfflineTime($teacher['last_activity']) ?>
                </span>

                <?php if ($avg['avg_rating']): ?>
                    <div class="mt-2">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fa<?= $i <= round($avg['avg_rating']) ? 's' : 'r' ?> fa-star text-warning rating-star"></i>
                        <?php endfor; ?>
                        <span class="fw-bold ms-1"><?= $avg['avg_rating'] ?></span>
                        <span class="text-muted">(<?= $avg['total'] ?> review<?= $avg['total'] > 1 ? 's' : '' ?>)</span>
                    </div>
                <?php else: ?>
                    <div class="mt-2 text-muted small">No ratings yet</div>
                <?php endif; ?>
            </div>
            <div class="col-auto text-end">
                <?php if ($teacher['hourly_rate'] > 0): ?>
                    <h4 class="fw-bold text-success mb-1">₱<?= number_format($teacher['hourly_rate'], 0) ?>/hr</h4>
                <?php endif; ?>
                <?php if (!empty($teacher['bio'])): ?>
                    <a href="book_lesson.php?teacher_id=<?= $teacher_id ?>" class="btn btn-primary rounded-pill px-4 fw-bold mt-2">Book Now</a>
                <?php endif; ?>
                <?php if ($can_rate): ?>
                    <button class="btn btn-warning rounded-pill px-4 fw-bold mt-2 rate-btn"
                            data-booking-id="<?= $unrated_booking_id ?>"
                            data-teacher="<?= htmlspecialchars($teacher['username']) ?>">
                        <i class="fas fa-star me-1"></i>Rate
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($teacher['bio'])): ?>
            <hr class="my-3">
            <p class="text-muted mb-0"><?= htmlspecialchars($teacher['bio']) ?></p>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="fas fa-clock text-primary me-2"></i>Available Schedule</h6>
                    <?php if (!empty($availability)): ?>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($availability as $a): ?>
                                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                                    <span class="fw-bold"><?= $days[$a['day_of_week']] ?></span>
                                    <span class="text-muted"><?= substr($a['start_time'], 0, 5) ?> - <?= substr($a['end_time'], 0, 5) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-0">No schedule set yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="fas fa-star text-warning me-2"></i>Ratings & Reviews</h6>
                    <?php if (!empty($ratings)): ?>
                        <?php foreach ($ratings as $r): ?>
                            <div class="d-flex justify-content-between align-items-start py-2 border-bottom">
                                <div>
                                    <span class="fw-bold small"><?= htmlspecialchars($r['student_name']) ?></span>
                                    <div class="mt-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa<?= $i <= $r['rating'] ? 's' : 'r' ?> fa-star text-warning" style="font-size: 0.8rem;"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <small class="text-muted"><?= date('M d, Y', strtotime($r['created_at'])) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted small mb-0">No ratings yet.</p>
                    <?php endif; ?>
                </div>
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
});
</script>
</body>
</html>
