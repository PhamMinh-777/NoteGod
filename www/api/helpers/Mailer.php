<?php
/**
 * Mailer Helper using PHPMailer
 * FinalNote Project
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/PHPMailer/Exception.php';
require_once __DIR__ . '/../../vendor/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../../vendor/PHPMailer/SMTP.php';

class Mailer {
    /**
     * Send email using configured driver (SMTP or local log fallback)
     */
    public static function send(string $toEmail, string $subject, string $htmlBody, string $textBody = ''): bool {
        $config = require __DIR__ . '/../../config/mail.php';

        // In 'log' mode, save mail to file for local testing & inspection
        if (($config['driver'] ?? 'log') === 'log' || empty($config['username'])) {
            return self::logEmail($toEmail, $subject, $htmlBody);
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['username'];
            $mail->Password   = $config['password'];
            $mail->SMTPSecure = $config['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $config['port'];
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($config['from']['address'], $config['from']['name']);
            $mail->addAddress($toEmail);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: " . $mail->ErrorInfo);
            // Fallback to local log so the system keeps functioning seamlessly
            self::logEmail($toEmail, $subject, $htmlBody, "SMTP Failed: " . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Send account activation link email
     */
    public static function sendActivationEmail(string $toEmail, string $displayName, string $token): bool {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $link = "{$scheme}://{$host}/auth/verify.php?token=" . urlencode($token);

        $subject = "Activate your FinalNote account";
        $html = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                <h2 style='color: #4f46e5;'>Welcome to FinalNote, {$displayName}!</h2>
                <p>Thank you for signing up. Please click the button below to verify your email address and activate your account:</p>
                <div style='margin: 25px 0;'>
                    <a href='{$link}' style='background-color: #4f46e5; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Activate Account</a>
                </div>
                <p style='color: #64748b; font-size: 13px;'>If the button doesn't work, copy and paste this link into your browser:<br><a href='{$link}'>{$link}</a></p>
            </div>
        ";
        return self::send($toEmail, $subject, $html);
    }

    /**
     * Send password reset OTP email
     */
    public static function sendResetOtp(string $toEmail, string $displayName, string $otp): bool {
        $subject = "Your FinalNote Password Reset Code: {$otp}";
        $html = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                <h2 style='color: #4f46e5;'>Password Reset Request</h2>
                <p>Hello {$displayName},</p>
                <p>We received a request to reset your FinalNote password. Use the verification code below:</p>
                <div style='background-color: #f1f5f9; padding: 16px; border-radius: 8px; text-align: center; margin: 20px 0;'>
                    <span style='font-size: 32px; font-weight: bold; letter-spacing: 6px; color: #1e293b;'>{$otp}</span>
                </div>
                <p style='color: #64748b; font-size: 13px;'>This OTP code will expire in 15 minutes. If you did not request this, please ignore this email.</p>
            </div>
        ";
        return self::send($toEmail, $subject, $html);
    }

    private static function logEmail(string $toEmail, string $subject, string $body, string $note = ''): bool {
        $logDir = __DIR__ . '/../../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/mail.log';
        $logContent = "========================================\n"
                    . "TIME:    " . date('Y-m-d H:i:s') . "\n"
                    . "TO:      {$toEmail}\n"
                    . "SUBJECT: {$subject}\n"
                    . ($note ? "NOTE:    {$note}\n" : "")
                    . "BODY:\n{$body}\n"
                    . "========================================\n\n";
        @file_put_contents($logFile, $logContent, FILE_APPEND);
        return true;
    }
}
