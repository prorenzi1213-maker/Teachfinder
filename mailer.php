<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

function getAppUrl(): string {
    $env = getenv('APP_URL');
    if ($env) return rtrim($env, '/');
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function createMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = getenv('MAIL_USERNAME') ?: 'prorenzi1213@gmail.com';
    $mail->Password   = getenv('MAIL_PASSWORD') ?: 'ywnc clfi vabh uszi';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->Timeout    = 10; // 10 second timeout — prevents hanging
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]
    ];
    $mail->setFrom(
        getenv('MAIL_USERNAME') ?: 'prorenzi1213@gmail.com',
        'TeachFinder'
    );
    return $mail;
}

function sendOTPEmail(string $to_email, string $to_name, $otp): bool {
    try {
        $mail = createMailer();
        $mail->addAddress($to_email, $to_name);

        $app_url    = getAppUrl();
        $verify_url = $app_url . '/login_otp.php';

        $mail->isHTML(true);
        $mail->Subject = 'TeachFinder - Login Verification Code';
        $mail->Body    = "
            <div style='font-family:Arial,sans-serif;max-width:500px;margin:auto;padding:30px;border:1px solid #e2e8f0;border-radius:12px;'>
                <h2 style='color:#198754;text-align:center;'>&#127891; TeachFinder</h2>
                <h3 style='color:#1a202c;'>Login Verification Code</h3>
                <p>Hi <strong>" . htmlspecialchars($to_name) . "</strong>,</p>
                <p>Your login code is below. Expires in <strong>10 minutes</strong>.</p>
                <div style='text-align:center;margin:30px 0;'>
                    <span style='font-size:2.5rem;font-weight:800;letter-spacing:10px;color:#198754;background:#f0fdf4;padding:15px 30px;border-radius:10px;border:2px dashed #198754;display:inline-block;'>
                        {$otp}
                    </span>
                </div>
                <div style='text-align:center;margin:20px 0;'>
                    <a href='{$verify_url}' style='background-color:#198754;color:#fff;padding:14px 36px;border-radius:8px;text-decoration:none;font-size:1rem;font-weight:700;display:inline-block;'>
                        &#9989; Verify &amp; Login
                    </a>
                </div>
                <p style='color:#718096;font-size:0.85rem;'>If you did not attempt to log in, please ignore this email.</p>
                <p style='color:#a0aec0;font-size:0.75rem;text-align:center;'>&copy; " . date('Y') . " TeachFinder.</p>
            </div>
        ";
        $mail->AltBody = "Your TeachFinder login code: {$otp}. Expires in 10 minutes. Visit: {$verify_url}";

        $mail->send();
        return true;
    } catch (Throwable $e) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['mailer_error'] = $e->getMessage();
        }
        error_log("sendOTPEmail error: " . $e->getMessage());
        return false;
    }
}

function sendWelcomeEmail(string $to_email, string $to_name): bool {
    try {
        $mail = createMailer();
        $mail->addAddress($to_email, $to_name);

        $login_url = getAppUrl() . '/login.php';

        $mail->isHTML(true);
        $mail->Subject = 'Welcome to TeachFinder!';
        $mail->Body    = "
            <div style='font-family:Arial,sans-serif;max-width:500px;margin:auto;padding:30px;border:1px solid #e2e8f0;border-radius:12px;'>
                <h2 style='color:#198754;text-align:center;'>&#127891; TeachFinder</h2>
                <h3>Welcome, " . htmlspecialchars($to_name) . "! &#127881;</h3>
                <p>Your account has been successfully created.</p>
                <div style='text-align:center;margin:25px 0;'>
                    <a href='{$login_url}' style='background-color:#198754;color:#fff;padding:14px 36px;border-radius:8px;text-decoration:none;font-size:1rem;font-weight:700;display:inline-block;'>
                        &#128274; Login to Your Account
                    </a>
                </div>
                <p style='color:#a0aec0;font-size:0.75rem;text-align:center;'>&copy; " . date('Y') . " TeachFinder.</p>
            </div>
        ";
        $mail->AltBody = "Welcome to TeachFinder, {$to_name}! Login at {$login_url}";

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log("sendWelcomeEmail error: " . $e->getMessage());
        return false;
    }
}
