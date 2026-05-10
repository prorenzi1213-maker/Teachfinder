<?php
session_start();
require_once __DIR__ . '/connections.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    $teacher_user_id = $_SESSION['user_id'];

    // Verify the booking belongs to this teacher and get details
    $stmt = $pdo->prepare("SELECT b.id, b.user_id as student_id, b.teacher_id, t.user_id as teacher_user_id
                           FROM bookings b
                           JOIN teachers t ON b.teacher_id = t.id
                           WHERE b.id = ? AND t.user_id = ?");
    $stmt->execute([$booking_id, $teacher_user_id]);
    $booking = $stmt->fetch();

    if ($booking) {
        $update = $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
        $update->execute([$booking_id]);

        // Get teacher id from the booking's teacher_id
        $t_stmt = $pdo->prepare("SELECT id FROM teachers WHERE id = ?");
        $t_stmt->execute([$booking['teacher_id']]);
        $teacher_row_id = $t_stmt->fetchColumn();

        // Notify the student clickable message
        $notif = $pdo->prepare("INSERT INTO notifications (user_id, message, booking_id, is_read) VALUES (?, ?, ?, 0)");
        $notif->execute([$booking['student_id'], 'Your tutoring session is complete! Rate your tutor: teacher_profile.php?id=' . $teacher_row_id, $booking_id]);

        // Notify the teacher
        $notif->execute([$teacher_user_id, 'You marked a booking as completed.', $booking_id]);
    }

    header("Location: teacher_dashboard.php");
    exit();
}

header("Location: teacher_dashboard.php");
exit();
