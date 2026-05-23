<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Send password reset email.
 * Returns ['sent'=>bool, 'debug_link'=>string|null, 'error'=>string|null]
 */
function sendResetEmail(string $toEmail, string $resetLink, string $toName = ''): array
{
    $useSmtp = defined('SMTP_HOST') && SMTP_HOST !== '';

    // Try PHPMailer if composer autoload exists and SMTP is configured
    if ($useSmtp && file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = SMTP_USER !== '';
                if (SMTP_USER !== '') {
                    $mail->Username = SMTP_USER;
                    $mail->Password = SMTP_PASS;
                }
                if (SMTP_SECURE) {
                    $mail->SMTPSecure = SMTP_SECURE;
                }
                $mail->Port = SMTP_PORT;

                $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
                $mail->addAddress($toEmail, $toName);

                $mail->isHTML(true);
                $mail->Subject = 'Password reset instructions';
                $mail->Body = 'To reset your password click the link: <a href="' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '</a>';
                $mail->AltBody = $resetLink;

                $mail->send();
                return ['sent' => true, 'debug_link' => null, 'error' => null];
            } catch (\Throwable $e) {
                error_log('Mail error: ' . $e->getMessage());
                return ['sent' => false, 'debug_link' => null, 'error' => $e->getMessage()];
            }
        }
    }

    // Dev fallback: append the link to storage/reset_emails.log and return debug_link
    $storageDir = __DIR__ . '/../storage';
    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0755, true);
    }

    $logFile = $storageDir . '/reset_emails.log';
    $entry = sprintf("[%s] To: %s\nLink: %s\n\n", date('c'), $toEmail, $resetLink);
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

    return ['sent' => true, 'debug_link' => $resetLink, 'error' => null];
}
