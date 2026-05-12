<?php
session_start();
require_once 'config.php';

// Wrap mailer include so a missing/broken vendor doesn't crash the whole page
try {
    require_once 'mailer.php';
} catch (Throwable $e) {
    error_log("mailer.php load error: " . $e->getMessage());
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Credentials correct — generate OTP and send email
            $otp = rand(100000, 999999);

            $_SESSION['login_otp'] = [
                'user_id'    => $user['id'],
                'username'   => $user['username'],
                'role'       => $user['role'],
                'email'      => $user['email'],
                'otp'        => $otp,
                'otp_expiry' => time() + 600 // 10 minutes
            ];

            if (function_exists('sendOTPEmail') && sendOTPEmail($user['email'], $user['username'], $otp)) {
                header("Location: login_otp.php");
                exit();
            } else {
                $error_detail = $_SESSION['mailer_error'] ?? 'Unknown error';
                unset($_SESSION['mailer_error'], $_SESSION['login_otp']);
                $message = "<div class='alert alert-danger small'>Failed to send verification code.<br><small>" . htmlspecialchars($error_detail) . "</small></div>";
            }

        } else {
            $message = "<div class='alert alert-danger small'>Invalid email or password.</div>";
        }
    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger small'>Database error. Please try again.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | TeachFinder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { width: 100%; max-width: 400px; border-radius: 15px; border: none; }
    </style>
</head>
<body>
    <div class="card login-card shadow-sm p-4">
        <a href="frontpage.php" class="text-secondary text-decoration-none small mb-3 d-inline-block">
            &larr; Back to Home
        </a>

        <h3 class="text-center fw-bold text-success mb-4">TeachFinder Login</h3>

        <?= $message ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="Enter your email" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold">Password</label>
                <div class="input-group">
                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required>
                    <button type="button" class="btn btn-outline-secondary" id="togglePassword" tabindex="-1">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-success w-100 py-2 fw-bold">
                <i class="fas fa-paper-plane me-2"></i>Send Verification Code
            </button>
            <p class="text-center mt-3 small">New here? <a href="signup.php" class="text-success text-decoration-none">Create Account</a></p>
        </form>
    </div>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function () {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            const type = pwd.getAttribute('type') === 'password' ? 'text' : 'password';
            pwd.setAttribute('type', type);
            icon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        });
    </script>
</body>
</html>
