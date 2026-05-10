<?php
session_start();
require_once 'config.php';
require_once 'mailer.php';

define('RECAPTCHA_SITE_KEY',   '6LfT3uIsAAAAABMRDa_ql6T4E1N6w2WpFeCZHZAR');
define('RECAPTCHA_SECRET_KEY', '6LfT3uIsAAAAAJZ-mNDpXAxU285RQZtussoj_s3q');

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username         = trim($_POST['username']);
    $email            = trim($_POST['email']);
    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role             = $_POST['role'];

    // --- reCAPTCHA Verification ---
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    $recaptcha_verify   = file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-type: application/x-www-form-urlencoded',
            'content' => http_build_query([
                'secret'   => RECAPTCHA_SECRET_KEY,
                'response' => $recaptcha_response,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ])
        ]
    ]));
    $recaptcha_data = json_decode($recaptcha_verify);

    if (!$recaptcha_data->success) {
        $message = "<div class='alert alert-danger text-center'>Please complete the reCAPTCHA verification.</div>";

    } elseif (empty($username) || strlen($username) < 3 || !preg_match('/^\w+$/', $username)) {
        $message = "<div class='alert alert-danger text-center'>Username must be at least 3 characters (letters, numbers, underscores only).</div>";

    } elseif ($password !== $confirm_password) {
        $message = "<div class='alert alert-danger text-center'>Passwords do not match.</div>";

    } elseif (strlen($password) < 8 || !preg_match('/[0-9]/', $password)) {
        $message = "<div class='alert alert-danger text-center'>Password must be at least 8 characters and contain at least one number.</div>";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='alert alert-danger text-center'>Invalid email format. Please enter a valid email address.</div>";

    } else {
        // Block disposable domains
        $blocked_domains = ['mailinator.com','tempmail.com','guerrillamail.com','throwam.com','yopmail.com','sharklasers.com','trashmail.com','fakeinbox.com','maildrop.cc','dispostable.com'];
        $email_domain    = strtolower(substr(strrchr($email, "@"), 1));

        if (in_array($email_domain, $blocked_domains)) {
            $message = "<div class='alert alert-danger text-center'>Disposable email addresses are not allowed.</div>";

        } elseif (!checkdnsrr($email_domain, "MX") && !checkdnsrr($email_domain, "A")) {
            $message = "<div class='alert alert-danger text-center'>This email domain does not exist. Please use a valid email address.</div>";

        } else {
            // Check if email already exists in DB
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $message = "<div class='alert alert-danger text-center'>This email is already registered. <a href='login.php' class='alert-link'>Login instead?</a></div>";
            } else {
                // Generate OTP and store pending registration in session
                $otp = rand(100000, 999999);

                $_SESSION['pending_user'] = [
                    'username'   => $username,
                    'email'      => $email,
                    'password'   => password_hash($password, PASSWORD_DEFAULT),
                    'role'       => $role,
                    'otp'        => $otp,
                    'otp_expiry' => time() + 600 // 10 minutes
                ];

                // Send OTP email
                if (sendOTPEmail($email, $username, $otp)) {
                    header("Location: verify_email.php");
                    exit();
                } else {
                    $error_detail = $_SESSION['mailer_error'] ?? 'Unknown error';
                    unset($_SESSION['mailer_error']);
                    $message = "<div class='alert alert-danger text-center'>
                        Failed to send verification email.<br>
                        <small class='text-muted'>Error: " . htmlspecialchars($error_detail) . "</small>
                    </div>";
                    unset($_SESSION['pending_user']);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Join TeachFinder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        function onRecaptchaLoad() {
            grecaptcha.render('recaptcha-widget', {
                'sitekey': '<?= RECAPTCHA_SITE_KEY ?>'
            });
            grecaptcha.reset();
        }
    </script>
    <script src="https://www.google.com/recaptcha/api.js?onload=onRecaptchaLoad&render=explicit" async defer></script>
    <style>
        body { background: #f0f2f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .register-card { width: 100%; max-width: 420px; border-radius: 15px; }
    </style>
</head>
<body>
<div class="card register-card shadow-sm p-4">
    <a href="frontpage.php" class="text-secondary text-decoration-none small mb-3 d-inline-block">
        &larr; Back to Home
    </a>

    <h3 class="text-center fw-bold text-success mb-4">Create Account</h3>

    <?= $message ?>

    <form method="POST" id="signupForm" novalidate>
        <div class="mb-3">
            <label class="form-label small fw-bold">Username</label>
            <input type="text" name="username" id="username" class="form-control" required minlength="3" pattern="^\w+$"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            <div class="invalid-feedback">At least 3 characters. Letters, numbers, underscores only.</div>
            <div class="valid-feedback">Looks good!</div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-bold">Email</label>
            <input type="email" name="email" id="email" class="form-control" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            <div class="invalid-feedback" id="email-feedback">Please enter a valid email address.</div>
            <div class="valid-feedback">Looks good!</div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-bold">Password</label>
            <div class="input-group">
                <input type="password" name="password" id="password" class="form-control" required minlength="8">
                <button type="button" class="btn btn-outline-secondary" id="togglePassword" tabindex="-1">
                    <i class="fas fa-eye" id="eyeIcon"></i>
                </button>
            </div>
            <div class="invalid-feedback">Minimum 8 characters with at least one number.</div>
            <div id="password-strength" class="form-text mt-1"></div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-bold">Confirm Password</label>
            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
            <div class="invalid-feedback" id="confirm-feedback">Passwords do not match.</div>
            <div class="valid-feedback">Passwords match!</div>
        </div>

        <div class="mb-4">
            <label class="form-label small fw-bold">I am a...</label>
            <select name="role" class="form-select" required>
                <option value="student" <?= (($_POST['role'] ?? '') === 'student') ? 'selected' : '' ?>>Student (I want to learn)</option>
                <option value="teacher" <?= (($_POST['role'] ?? '') === 'teacher') ? 'selected' : '' ?>>Teacher (I want to teach)</option>
            </select>
        </div>

        <div class="mb-3 d-flex justify-content-center">
            <div id="recaptcha-widget"></div>
        </div>

        <button type="submit" class="btn btn-success w-100 py-2 fw-bold">
            <i class="fas fa-paper-plane me-2"></i>Send Verification Code
        </button>
        <p class="text-center mt-3 small">Already have an account? <a href="login.php" class="text-success text-decoration-none">Login</a></p>
    </form>
</div>

<script>
    // --- Email Validation ---
    const emailInput = document.getElementById('email');
    emailInput.addEventListener('input', function () {
        const val = this.value;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
        const blocked = ['mailinator.com','tempmail.com','guerrillamail.com','yopmail.com','trashmail.com','fakeinbox.com'];
        const domain = val.split('@')[1]?.toLowerCase();

        if (!emailRegex.test(val)) {
            this.classList.add('is-invalid'); this.classList.remove('is-valid');
            document.getElementById('email-feedback').textContent = 'Please enter a valid email address.';
        } else if (blocked.includes(domain)) {
            this.classList.add('is-invalid'); this.classList.remove('is-valid');
            document.getElementById('email-feedback').textContent = 'Disposable emails are not allowed.';
        } else {
            this.classList.remove('is-invalid'); this.classList.add('is-valid');
        }
    });

    // --- Password Strength ---
    const passwordInput = document.getElementById('password');
    passwordInput.addEventListener('input', function () {
        const val = this.value;
        const strengthEl = document.getElementById('password-strength');
        let strength = 0;
        if (val.length >= 8)            strength++;
        if (/[0-9]/.test(val))          strength++;
        if (/[A-Z]/.test(val))          strength++;
        if (/[^a-zA-Z0-9]/.test(val))   strength++;

        const levels = ['', 'text-danger', 'text-warning', 'text-info', 'text-success'];
        const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];

        if (val.length === 0) {
            strengthEl.textContent = '';
            this.classList.remove('is-invalid', 'is-valid');
        } else if (val.length < 8 || !/[0-9]/.test(val)) {
            this.classList.add('is-invalid'); this.classList.remove('is-valid');
            strengthEl.innerHTML = `<span class="${levels[strength] || 'text-danger'}">Strength: ${labels[strength] || 'Too weak'}</span>`;
        } else {
            this.classList.remove('is-invalid'); this.classList.add('is-valid');
            strengthEl.innerHTML = `<span class="${levels[strength]}">Strength: ${labels[strength]}</span>`;
        }
    });

    // --- Confirm Password ---
    const confirmInput = document.getElementById('confirm_password');
    confirmInput.addEventListener('input', function () {
        if (this.value.length === 0) {
            this.classList.remove('is-invalid', 'is-valid');
        } else if (this.value !== passwordInput.value) {
            this.classList.add('is-invalid'); this.classList.remove('is-valid');
        } else {
            this.classList.remove('is-invalid'); this.classList.add('is-valid');
        }
    });

    // --- Username ---
    const usernameInput = document.getElementById('username');
    usernameInput.addEventListener('input', function () {
        if (this.value.length < 3 || !/^\w+$/.test(this.value)) {
            this.classList.add('is-invalid'); this.classList.remove('is-valid');
        } else {
            this.classList.remove('is-invalid'); this.classList.add('is-valid');
        }
    });

    // --- Show/Hide Password ---
    document.getElementById('togglePassword').addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        document.getElementById('eyeIcon').className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
    });
</script>
</body>
</html>
