<?php
session_start();
require_once __DIR__ . '/connections.php';

if (!isset($_SESSION['user_id'])) {
    exit("Please login first.");
}

$student_id = $_SESSION['user_id'];
$search = isset($_POST['search']) ? '%' . $_POST['search'] . '%' : '%';

// Get all teachers with their booking status
$query = "
    SELECT u.id, u.username, 
           (SELECT status FROM bookings WHERE teacher_id = u.id AND status IN ('pending', 'confirmed') LIMIT 1) as booking_status
    FROM users u
    WHERE u.role = 'teacher' 
    AND (u.username LIKE ? OR ? = '%%')
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $search, $search);
$stmt->execute();
$teachers = $stmt->get_result();

if ($teachers->num_rows > 0) {
    while ($teacher = $teachers->fetch_assoc()) {
        $isBooked = ($teacher['booking_status'] == 'pending' || $teacher['booking_status'] == 'confirmed');
        ?>
        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1"><?= htmlspecialchars($teacher['username']) ?></h5>
                    <small class="text-muted">Subject: Math, Science, English</small>
                </div>
                <div>
                    <?php if ($isBooked): ?>
                        <button class="btn btn-secondary" disabled>Booked</button>
                    <?php else: ?>
                        <button class="btn btn-primary book-now" data-teacher-id="<?= $teacher['id'] ?>" data-name="<?= htmlspecialchars($teacher['username']) ?>">Book Now</button>
                    <?php endif; ?>
                    <button class="btn btn-outline-primary message-btn" data-id="<?= $teacher['id'] ?>">Message</button>
                    <a href="transfer.php?teacher_user_id=<?= $teacher['id'] ?>" class="btn btn-outline-success">Send Money</a>
                </div>
            </div>
        </div>
        <?php
    }
} else {
    echo '<div class="alert alert-info">No teachers found.</div>';
}
?>

<script>
    // Book Now functionality
    $('.book-now').click(function() {
        let teacherId = $(this).data('teacher-id');
        let teacherName = $(this).data('name');
        
        let subject = prompt("Enter subject for booking with " + teacherName + ":");
        if (subject) {
            let date = prompt("Enter date (YYYY-MM-DD):");
            let time = prompt("Enter time (HH:MM):");
            
            $.ajax({
                url: 'book_now.php',
                method: 'POST',
                data: {
                    teacher_id: teacherId,
                    subject: subject,
                    date: date,
                    time: time
                },
                success: function(response) {
                    alert(response);
                    location.reload();
                }
            });
        }
    });
</script>