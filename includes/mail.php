<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function appBaseUrlFromRequest(): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $scheme = $isHttps ? 'https' : 'http';

    if (!empty($_SERVER['HTTP_HOST'])) {
        return $scheme . '://' . $_SERVER['HTTP_HOST'];
    }

    return rtrim(APP_URL, '/');
}

/**
 * Minimal SMTP sender for development using MailHog (or any SMTP server).
 * Does not require PHPMailer. Returns ['sent'=>bool, 'error'=>string|null]
 */
function smtpSendEmail(string $toEmail, string $subject, string $bodyText, string $bodyHtml, string $toName = ''): array
{
    $host = defined('SMTP_HOST') ? SMTP_HOST : '127.0.0.1';
    $port = defined('SMTP_PORT') ? SMTP_PORT : 25;
    $user = defined('SMTP_USER') ? SMTP_USER : '';
    $pass = defined('SMTP_PASS') ? SMTP_PASS : '';
    $from = defined('SMTP_FROM') ? SMTP_FROM : 'no-reply@local.test';
    $fromName = defined('SMTP_FROM_NAME')
        ? SMTP_FROM_NAME
        : (defined('APP_NAME') ? APP_NAME : 'App');

    $boundary = 'b_' . bin2hex(random_bytes(8));
    $date = date('r');

    $headers = [];
    $headers[] = 'Date: ' . $date;
    $headers[] = 'From: ' . sprintf('%s <%s>', $fromName, $from);
    $headers[] = 'To: ' . sprintf('%s <%s>', $toName ?: $toEmail, $toEmail);
    $headers[] = 'Subject: ' . $subject;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

    $message = [];
    $message[] = '--' . $boundary;
    $message[] = 'Content-Type: text/plain; charset=utf-8';
    $message[] = '';
    $message[] = $bodyText;
    $message[] = '';
    $message[] = '--' . $boundary;
    $message[] = 'Content-Type: text/html; charset=utf-8';
    $message[] = '';
    $message[] = $bodyHtml;
    $message[] = '';
    $message[] = '--' . $boundary . '--';

    $data = implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $message) . "\r\n";

    $err = null;

    $ctx = stream_context_create([]);
    $remote = sprintf('%s:%s', $host, $port);
    $fp = @stream_socket_client($remote, $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return ['sent' => false, 'error' => sprintf('SMTP connect failed: %s (%s)', $errstr, $errno)];
    }

    $read = function () use ($fp) {
        $res = '';
        while (($line = fgets($fp, 515)) !== false) {
            $res .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $res;
    };

    $write = function ($cmd) use ($fp) {
        fwrite($fp, $cmd . "\r\n");
    };

    // Server greeting
    $greet = $read();

    $write('EHLO localhost');
    $ehlo = $read();

    if ($user !== '') {
        // Try AUTH LOGIN
        $write('AUTH LOGIN');
        $read();
        $write(base64_encode($user));
        $read();
        $write(base64_encode($pass));
        $authResp = $read();
        if (strpos($authResp, '235') === false) {
            // auth failed but continue to attempt send
        }
    }

    $write('MAIL FROM:<' . $from . '>');
    $read();

    $write('RCPT TO:<' . $toEmail . '>');
    $rcpt = $read();
    if (strpos($rcpt, '250') === false && strpos($rcpt, '251') === false) {
        fclose($fp);
        return ['sent' => false, 'error' => 'SMTP RCPT TO refused: ' . trim($rcpt)];
    }

    $write('DATA');
    $read();

    fwrite($fp, $data . "\r\n.\r\n");
    $dataResp = $read();

    $write('QUIT');
    fclose($fp);

    if (strpos($dataResp, '250') === false) {
        return ['sent' => false, 'error' => 'SMTP DATA not accepted: ' . trim($dataResp)];
    }

    return ['sent' => true, 'error' => null];
}

function sendResetEmail(string $toEmail, string $resetLink, string $toName = ''): array
{
    $bodyText = 'To reset your password, open this link: ' . $resetLink;
    $bodyHtml = '<p>To reset your password click the link: <a href="' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '</a></p>';

    return smtpSendEmail($toEmail, 'Password reset instructions', $bodyText, $bodyHtml, $toName);
}

function sendVerificationEmail(string $toEmail, string $verificationLink, string $toName = ''): array
{
    $bodyText = 'To verify your email address, open this link: ' . $verificationLink;
    $bodyHtml = '<p>To verify your email address click the link: <a href="' . htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8') . '</a></p>';

    return smtpSendEmail($toEmail, 'Email verification', $bodyText, $bodyHtml, $toName);
}

function sendChangeEmailVerification(string $toEmail, string $verificationLink, string $toName = ''): array
{
    $bodyText = 'To confirm your requested email change, open this link: ' . $verificationLink;
    $bodyHtml = '<p>To confirm your requested email change click the link: <a href="' . htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8') . '</a></p>';

    return smtpSendEmail($toEmail, 'Conferma cambio email', $bodyText, $bodyHtml, $toName);
}

function sendChangeEmailNotice(string $toEmail, string $oldEmail = '', string $toName = ''): array
{
    $bodyText = 'The email address for your account was just changed to: ' . $toEmail . '\nIf you did not request this change, contact support.';
    $bodyHtml = '<p>The email address for your account was just changed to: ' . htmlspecialchars($toEmail, ENT_QUOTES, 'UTF-8') . '</p><p>If you did not request this change, contact support.</p>';

    return smtpSendEmail($oldEmail ?: $toEmail, 'Notifica cambio email', $bodyText, $bodyHtml, $toName);
}
