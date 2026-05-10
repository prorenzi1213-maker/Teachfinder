<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connections.php';

// Check if user is logged in as a student
if (!isset($_SESSION['user_id'])) {
    die("Error: You must be logged in to book a lesson.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_SESSION['user_id'];
    $teacher_id = $_POST['teacher_id']; // ID from teachers table
    $start_time = $_POST['booking_date'] . ' ' . $_POST['booking_time'];

    try {
        // Start a transaction to ensure both tasks happen together
        $pdo->beginTransaction();

        // 1. Insert into bookings table
        $sql = "INSERT INTO bookings (user_id, teacher_id, start_time, status) 
                VALUES (?, ?, ?, 'pending')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$student_id, $teacher_id, $start_time]);

        // 2. Fetch the teacher's User ID (required for the notification)
        $stmt = $pdo->prepare("SELECT user_id FROM teachers WHERE id = ?");
        $stmt->execute([$teacher_id]);
        $teacher_user_id = $stmt->fetchColumn();

        // 3. Insert into notifications table
        $notif_msg = "You have a new booking request for " . date('M d, Y @ h:i A', strtotime($start_time));
        $notif_sql = "INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)";
        $pdo->prepare($notif_sql)->execute([$teacher_user_id, $notif_msg]);

        // Commit everything to the database
        $pdo->commit();

        header("Location: student_dashboard.php?msg=Booking successful!");
        exit();

    } catch (Exception $e) {
        // If anything goes wrong, cancel everything
        $pdo->rollBack();
        die("Booking failed: " . $e->getMessage());
    }
}