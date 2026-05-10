<?php
session_start();
require_once 'connections.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'], $_POST['status'])) {
    $booking_id = (int)$_POST['id'];
    $new_status = $_POST['status'];
    $teacher_user_id = $_SESSION['user_id'];
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');

    try {
        $pdo->beginTransaction();

        if ($new_status === 'cancelled' && !empty($rejection_reason)) {
            $stmt = $pdo->prepare("UPDATE bookings SET status = ?, rejection_reason = ? WHERE id = ?");
            $stmt->execute([$new_status, $rejection_reason, $booking_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $booking_id]);
        }

        $booking = $pdo->prepare("SELECT user_id FROM bookings WHERE id = ?");
        $booking->execute([$booking_id]);
        $book = $booking->fetch();

        if ($book) {
            if ($new_status === 'confirmed') {
                $msg = "Your booking request has been accepted!";
            } elseif ($new_status === 'cancelled') {
                $msg = "Your booking request was declined.";
                if (!empty($rejection_reason)) {
                    $msg .= " Reason: " . $rejection_reason;
                }
            } else {
                $msg = "Your booking request has been " . $new_status;
            }

            $notif = $pdo->prepare("INSERT INTO notifications (user_id, message, booking_id, is_read) VALUES (?, ?, ?, 0)");
            $notif->execute([$book['user_id'], $msg, $booking_id]);

            $teacher_msg = "You " . ($new_status === 'confirmed' ? 'accepted' : 'declined') . " a booking request";
            $notif->execute([$teacher_user_id, $teacher_msg, $booking_id]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
    }

    header("Location: teacher_dashboard.php");
    exit();
}

header("Location: teacher_dashboard.php");
exit();
