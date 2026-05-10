<?php
session_start();
require_once __DIR__ . '/connections.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$student_id = $_SESSION['user_id'];
$booking_id = (int)($_POST['booking_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);

if ($booking_id <= 0 || $rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

try {
    $pdo->beginTransaction();

    $booking = $pdo->prepare("SELECT b.id, b.teacher_id, b.user_id, b.status
                              FROM bookings b
                              WHERE b.id = ? AND b.user_id = ? AND b.status = 'completed'");
    $booking->execute([$booking_id, $student_id]);
    $booking = $booking->fetch();

    if (!$booking) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Booking not found or not completed']);
        exit();
    }

    $existing = $pdo->prepare("SELECT id FROM ratings WHERE booking_id = ?");
    $existing->execute([$booking_id]);
    if ($existing->fetch()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Already rated']);
        exit();
    }

    $insert = $pdo->prepare("INSERT INTO ratings (booking_id, student_id, teacher_id, rating) VALUES (?, ?, ?, ?)");
    $insert->execute([$booking_id, $student_id, $booking['teacher_id'], $rating]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Rating submitted successfully']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
