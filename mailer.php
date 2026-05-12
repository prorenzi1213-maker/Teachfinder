<?php
/**
 * mailer.php — Sends email via Resend API (HTTP, no SMTP port needed).
 * Resend free tier: 3,000 emails/month.
 * Sign up at https://resend.com and add RESEND_API_KEY to Railway Variables.
 */

function getAppUrl(): string {
    $env = getenv('APP_URL');
    if ($env) return rtrim($env, '/');
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/**
 * Send email via Resend API using cURL (no SMTP, works on Railway).
 */
function sendEmail(string $to_email, string $to_name, string $subject, string $html, string $text): bool {
    $api_key = getenv('RESEND_API_KEY');

    if (empty($api_key)) {
        error_log("RESEND_API_KEY not set — cannot send email.");
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['mailer_error'] = "RESEND_API_KEY not configured on server.";
        }
        return false;
    }

    $from = getenv('MAIL_FROM') ?: ('TeachFinder <' . (getenv('RESEND_TEST_EMAIL') ?: 'onboarding@resend.dev') . '>');

    // Resend free tier: can only send to verified email (your own)
    // Set RESEND_TEST_EMAIL env var to override recipient for testing
    $test_email = getenv('RESEND_TEST_EMAIL');
    $actual_to  = $test_email ?: $to_email;
    $actual_name = $test_email ? 'Test' : $to_name;

    $payload = json_encode([
        'from'    => $from,
        'to'      => ["{$actual_name} <{$actual_to}>"],
        'subject' => $subject,
        'html'    => $html,
        'text'    => $text,
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        error_log("Resend curl error: " . $curl_err);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['mailer_error'] = "curl: " . $curl_err;
        }
        return false;
    }

    if ($http_code !== 200 && $http_code !== 201) {
        $msg = "Resend HTTP $http_code: $response";
        error_log($msg);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $data = json_decode($response, true);
            $_SESSION['mailer_error'] = $data['message'] ?? $msg;
        }
        return false;
    }

    // Check if response has an id (success)
    $result = json_decode($response, true);
    if (empty($result['id'])) {
        $msg = "Resend no id: $response";
        error_log($msg);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['mailer_error'] = $msg;
        }
        return false;
    }

    return true;
}

function sendOTPEmail(string $to_email, string $to_name, $otp): bool {
    $app_url    = getAppUrl();
    $verify_url = $app_url . '/login_otp.php';
    $otp_str    = (string)$otp;

    $html = "
        <div style='font-family:Arial,sans-serif;max-width:500px;margin:auto;padding:30px;border:1px solid #e2e8f0;border-radius:12px;'>
            <h2 style='color:#198754;text-align:center;'>&#127891; TeachFinder</h2>
            <h3>Login Verification Code</h3>
            <p>Hi <strong>" . htmlspecialchars($to_name) . "</strong>,</p>
            <p>Your login code is below. Expires in <strong>10 minutes</strong>.</p>
            <div style='text-align:center;margin:30px 0;'>
                <span style='font-size:2.5rem;font-weight:800;letter-spacing:10px;color:#198754;background:#f0fdf4;padding:15px 30px;border-radius:10px;border:2px dashed #198754;display:inline-block;'>
                    {$otp_str}
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
    $text = "Your TeachFinder login code: {$otp_str}. Expires in 10 minutes. Visit: {$verify_url}";

    return sendEmail($to_email, $to_name, 'TeachFinder - Login Verification Code', $html, $text);
}

function sendWelcomeEmail(string $to_email, string $to_name): bool {
    $login_url = getAppUrl() . '/login.php';

    $html = "
        <div style='font-family:Arial,sans-serif;max-width:500px;margin:auto;padding:30px;border:1px solid #e2e8f0;border-radius:12px;'>
            <h2 style='color:#198754;text-align:center;'>&#127891; TeachFinder</h2>
            <h3>Welcome, " . htmlspecialchars($to_name) . "! &#127881;</h3>
            <p>Your account has been successfully created and verified.</p>
            <div style='text-align:center;margin:25px 0;'>
                <a href='{$login_url}' style='background-color:#198754;color:#fff;padding:14px 36px;border-radius:8px;text-decoration:none;font-size:1rem;font-weight:700;display:inline-block;'>
                    &#128274; Login to Your Account
                </a>
            </div>
            <p style='color:#a0aec0;font-size:0.75rem;text-align:center;'>&copy; " . date('Y') . " TeachFinder.</p>
        </div>
    ";
    $text = "Welcome to TeachFinder, {$to_name}! Login at {$login_url}";

    return sendEmail($to_email, $to_name, 'Welcome to TeachFinder!', $html, $text);
}
