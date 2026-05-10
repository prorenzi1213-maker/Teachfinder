<?php
session_start();
require_once __DIR__ . '/connections.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = $_POST['fullname'];
    $phone = $_POST['phone_number'];
    $address = $_POST['address'];
    
    $stmt = $conn->prepare("UPDATE users SET fullname = ?, phone_number = ?, address = ? WHERE id = ?");
    $stmt->bind_param("sssi", $fullname, $phone, $address, $user_id);
    
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>Profile updated successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error updating profile.</div>";
    }
}

// Fetch existing data
$stmt = $conn->prepare("SELECT fullname, phone_number, address FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Edit Profile | TeachFinder</title>
</head>
<body class="bg-light p-4">
    <div class="container" style="max-width: 500px;">
        <div class="card p-4 shadow-sm border-0 rounded-4">
            <h4 class="fw-bold mb-4">Edit Profile</h4>
            <?= $message ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone_number" class="form-control" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>" placeholder="09xxxxxxxxx" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2">Save Changes</button>
            </form>
            <a href="javascript:history.back()" class="btn btn-link mt-3 text-secondary text-decoration-none">Go Back</a>
        </div>
    </div>
</body>
</html>