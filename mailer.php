<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

function sendOTPEmail($to_email, $to_name, $otp) {
    $mail = new PHPMailer(true);

    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'prorenzi1213@gmail.com';
        $mail->Password   = 'ywnc clfi vabh uszi';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender & Recipient
        $mail->setFrom('prorenzi1213@gmail.com', 'TeachFinder');
        $mail->addAddress($to_email, $to_name);

        // Email Content
        $mail->isHTML(true);
        $mail->Subject = 'TeachFinder - Login Verification Code';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 500px; margin: auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 12px;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h2 style='color: #198754;'>&#127891; TeachFinder</h2>
                </div>
                <h3 style='color: #1a202c;'>Login Verification Code</h3>
                <p style='color: #4a5568;'>Hi <strong>" . htmlspecialchars($to_name) . "</strong>,</p>
                <p style='color: #4a5568;'>Someone is trying to log in to your TeachFinder account. Use the code below to complete your login. This code expires in <strong>10 minutes</strong>.</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <span style='font-size: 2.5rem; font-weight: 800; letter-spacing: 10px; color: #198754; background: #f0fdf4; padding: 15px 30px; border-radius: 10px; border: 2px dashed #198754; display: inline-block;'>
                        {$otp}
                    </span>
                </div>
                <p style='color: #4a5568; text-align: center;'>Or click the button below to go to the verification page:</p>
                <div style='text-align: center; margin: 20px 0;'>
                    <a href='http://localhost/teachfinder/login_otp.php'
                       style='background-color: #198754; color: #ffffff; padding: 14px 36px; border-radius: 8px; text-decoration: none; font-size: 1rem; font-weight: 700; display: inline-block;'>
                        &#9989; Verify & Login
                    </a>
                </div>
                <p style='color: #718096; font-size: 0.85rem; text-align: center;'>If the button does not work, copy and paste this link:<br>
                    <a href='http://localhost/teachfinder/login_otp.php' style='color: #198754;'>http://localhost/teachfinder/login_otp.php</a>
                </p>
                <p style='color: #718096; font-size: 0.85rem;'>If you did not attempt to log in, please change your password immediately.</p>
                <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                <p style='color: #a0aec0; font-size: 0.75rem; text-align: center;'>&copy; 2026 TeachFinder. All rights reserved.</p>
            </div>
        ";
        $mail->AltBody = "Your TeachFinder login code is: {$otp}. It expires in 10 minutes.";

        $mail->send();
        return true;

    } catch (Exception $e) {
        $_SESSION['mailer_error'] = $mail->ErrorInfo;
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

function sendWelcomeEmail($to_email, $to_name) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'prorenzi1213@gmail.com';
        $mail->Password   = 'ywnc clfi vabh uszi';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('prorenzi1213@gmail.com', 'TeachFinder');
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = 'Welcome to TeachFinder!';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 500px; margin: auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 12px;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h2 style='color: #198754;'>&#127891; TeachFinder</h2>
                </div>
                <h3 style='color: #1a202c;'>Welcome, " . htmlspecialchars($to_name) . "! &#127881;</h3>
                <p style='color: #4a5568;'>Your account has been successfully created and verified.</p>
                <p style='color: #4a5568;'>You can now log in and start using TeachFinder.</p>
                <div style='text-align: center; margin: 25px 0;'>
                    <a href='http://localhost/teachfinder/login.php'
                       style='background-color: #198754; color: #ffffff; padding: 14px 36px; border-radius: 8px; text-decoration: none; font-size: 1rem; font-weight: 700; display: inline-block;'>
                        &#128274; Login to Your Account
                    </a>
                </div>
                <p style='color: #718096; font-size: 0.85rem;'>If you did not create this account, please ignore this email.</p>
                <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                <p style='color: #a0aec0; font-size: 0.75rem; text-align: center;'>&copy; 2026 TeachFinder. All rights reserved.</p>
            </div>
        ";
        $mail->AltBody = "Welcome to TeachFinder, {$to_name}! Your account has been created. Login at http://localhost/teachfinder/login.php";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Welcome Email Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>
