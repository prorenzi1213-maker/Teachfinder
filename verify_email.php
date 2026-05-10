<?php
session_start();
require_once 'config.php';
require_once 'mailer.php';

// If no pending registration in session, redirect back
if (!isset($_SESSION['pending_user'])) {
    header("Location: signup.php");
    exit();
}

$message = '';
$account_created = false;
$redirect_url = '';
$pending = $_SESSION['pending_user'];
$email   = $pending['email'];

// Handle resend OTP
if (isset($_GET['resend'])) {

    $new_otp = rand(100000, 999999);
    $_SESSION['pending_user']['otp']        = $new_otp;
    $_SESSION['pending_user']['otp_expiry'] = time() + 600;

    if (sendOTPEmail($email, $pending['username'], $new_otp)) {
        $message = "<div class='alert alert-success text-center'>A new code has been sent to <strong>" . htmlspecialchars($email) . "</strong>.</div>";
    } else {
        $message = "<div class='alert alert-danger text-center'>Failed to resend code. Please try again.</div>";
    }
    // Refresh pending after update
    $pending = $_SESSION['pending_user'];
}

// Handle OTP submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_otp = trim($_POST['otp'] ?? '');
    $stored_otp  = $_SESSION['pending_user']['otp'];
    $expiry      = $_SESSION['pending_user']['otp_expiry'];

    if (time() > $expiry) {
        $message = "<div class='alert alert-danger text-center'>Your verification code has expired. <a href='verify_email.php?resend=1' class='alert-link'>Resend code</a>.</div>";
    } elseif ($entered_otp != $stored_otp) {
        $message = "<div class='alert alert-danger text-center'>Incorrect verification code. Please try again.</div>";
    } else {
        // OTP is correct — create the account
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, 'approved')");
            $stmt->execute([
                $pending['username'],
                $pending['email'],
                $pending['password'],
                $pending['role']
            ]);

            $user_id = $pdo->lastInsertId();

            if ($pending['role'] === 'teacher') {
                $stmt_teacher = $pdo->prepare("INSERT INTO teachers (user_id, fullname, subject, status) VALUES (?, ?, 'Not Set', 'available')");
                $stmt_teacher->execute([$user_id, $pending['username']]);
            }

            // Set session and clear pending
            $_SESSION['user_id']  = $user_id;
            $_SESSION['username'] = $pending['username'];
            $_SESSION['role']     = $pending['role'];
            unset($_SESSION['pending_user']);

            $account_created = true;
            $redirect_url = ($pending['role'] === 'teacher') ? 'teacher_dashboard.php' : 'student_dashboard.php';

            // Send welcome email in background
            register_shutdown_function(function() use ($pending) {
                sendWelcomeEmail($pending['email'], $pending['username']);
            });

        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger text-center'>Account creation failed. Email may already be registered.</div>";
        }
    }
}

// Mask email for display: e.g. jo***@gmail.com
$parts      = explode('@', $email);
$masked     = substr($parts[0], 0, 2) . str_repeat('*', max(1, strlen($parts[0]) - 2)) . '@' . $parts[1];
$expiry_min = isset($_SESSION['pending_user']['otp_expiry'])
    ? max(0, ceil(($_SESSION['pending_user']['otp_expiry'] - time()) / 60))
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email | TeachFinder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .verify-card { width: 100%; max-width: 420px; border-radius: 16px; border: none; }
        .otp-input {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: 12px;
            text-align: center;
            border: 2px solid #dee2e6;
            border-radius: 12px;
            padding: 14px;
            width: 100%;
            transition: border-color 0.2s;
        }
        .otp-input:focus { border-color: #198754; outline: none; box-shadow: 0 0 0 3px rgba(25,135,84,0.15); }
        .timer { font-size: 0.85rem; }
        .timer.expired { color: #dc3545; }
        .timer.active  { color: #198754; }
    </style>
</head>
<body>
<?php if ($account_created): ?>
<div class="card verify-card shadow-sm p-4 text-center">
    <div style="font-size: 4rem;" class="mb-3">&#9989;</div>
    <h4 class="fw-bold text-success">Account Created!</h4>
    <p class="text-muted">Welcome to TeachFinder, <strong><?= htmlspecialchars($_SESSION['username'] ?? '') ?></strong>!</p>
    <p class="text-muted small">Redirecting you to your dashboard...</p>
    <div class="spinner-border text-success mt-2" role="status"></div>
</div>
<script>
    setTimeout(function() {
        window.location.href = '<?= $redirect_url ?>';
    }, 2000);
</script>
<?php else: ?>
<div class="card verify-card shadow-sm p-4">
    <div class="text-center mb-4">
        <div style="font-size: 3rem;">&#128231;</div>
        <h4 class="fw-bold text-success mt-2">Check Your Email</h4>
        <p class="text-muted small mb-0">We sent a 6-digit code to</p>
        <p class="fw-bold"><?= htmlspecialchars($masked) ?></p>
    </div>

    <?= $message ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label small fw-bold text-center w-100">Enter Verification Code</label>
            <input type="text" name="otp" class="otp-input" maxlength="6" placeholder="------"
                   inputmode="numeric" pattern="\d{6}" autocomplete="one-time-code" required autofocus>
        </div>

        <div class="text-center mb-3">
            <span class="timer <?= $expiry_min > 0 ? 'active' : 'expired' ?>" id="timer">
                <?= $expiry_min > 0 ? "Code expires in <span id='countdown'></span>" : "Code has expired." ?>
            </span>
        </div>

        <button type="submit" class="btn btn-success w-100 py-2 fw-bold">
            <i class="fas fa-check-circle me-2"></i>Verify & Create Account
        </button>
    </form>

    <div class="text-center mt-3">
        <span class="text-muted small">Didn't receive it? </span>
        <a href="verify_email.php?resend=1" class="text-success small fw-bold text-decoration-none">Resend Code</a>
    </div>
    <div class="text-center mt-2">
        <a href="signup.php" class="text-secondary small text-decoration-none">&larr; Back to Sign Up</a>
    </div>
</div>

<script>
    // Countdown timer
    const expiry = <?= $_SESSION['pending_user']['otp_expiry'] ?? 0 ?>;
    const countdownEl = document.getElementById('countdown');
    const timerEl = document.getElementById('timer');

    function updateTimer() {
        const now = Math.floor(Date.now() / 1000);
        const remaining = expiry - now;

        if (!countdownEl) return;

        if (remaining <= 0) {
            timerEl.className = 'timer expired';
            timerEl.textContent = 'Code has expired. Please resend.';
            return;
        }

        const mins = Math.floor(remaining / 60);
        const secs = remaining % 60;
        countdownEl.textContent = mins + ':' + String(secs).padStart(2, '0');
        setTimeout(updateTimer, 1000);
    }

    updateTimer();

    // Auto-submit when 6 digits entered
    document.querySelector('.otp-input').addEventListener('input', function () {
        if (this.value.length === 6) {
            this.closest('form').submit();
        }
    });
</script>
<?php endif; ?>
</body>
</html>
