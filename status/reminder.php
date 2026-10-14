<?php

declare(strict_types=1);

require_once __DIR__ . '/lib.php';
AppLocale::boot();

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    $expected = (string)cfg('reminder.web_token', '');
    $given    = (string)($_GET['token'] ?? '');
    if ($expected === '' || !hash_equals($expected, $given)) {
        http_response_code(404);
        exit('Not found.');
    }
    header('Content-Type: text/plain; charset=UTF-8');
}

 
function out(string $publicMsg, ?string $logDetail = null): never {
    if ($logDetail !== null) {
        error_log('reminder.php: ' . $logDetail);
    }
    echo $publicMsg, PHP_EOL;
    exit;
}

$smtpPass = (string)cfg('reminder.smtp_pass', '');
if ($smtpPass === '') {
    out('Reminder not sent: SMTP is not configured.',
        'SMTP_PASSWORD is empty — set it in the environment.');
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    out('Reminder not sent: mailer library is not installed.',
        'vendor/autoload.php missing — run "composer require phpmailer/phpmailer" in ' . __DIR__);
}
require $autoload;

if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
    out('Reminder not sent: mailer library is not installed.', 'PHPMailer class not found after autoload.');
}

$everyDays = max(1, (int)cfg('reminder.every_days', 13));
$lockFile  = DATA_DIR . DIRECTORY_SEPARATOR . 'reminder_lock.json';
$lock      = safeReadJson($lockFile);
if (isset($lock['last_sent']) && (time() - (int)$lock['last_sent']) < ($everyDays * 86400)) {
    out('Reminder skipped: already sent within the last ' . $everyDays . ' days.');
}

$company  = (string)cfg('brand.company', 'The portal');
$adminUrl = appUrl('admin.php');
$toEmail  = (string)cfg('reminder.to_email', '');
$toName   = (string)cfg('reminder.to_name', 'Administrator');

if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    out('Reminder not sent: recipient address is not configured.', 'ADMIN_EMAIL invalid: ' . $toEmail);
}

$mail = new \PHPMailer\PHPMailer\PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = (string)cfg('reminder.smtp_host', '');
    $mail->SMTPAuth   = true;
    $mail->Username   = (string)cfg('reminder.smtp_user', '');
    $mail->Password   = $smtpPass;
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int)cfg('reminder.smtp_port', 587);
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom((string)cfg('reminder.smtp_user', ''), $company . ' Alerts');
    $mail->addAddress($toEmail, $toName);
    $mail->isHTML(true);
    $mail->Subject = 'ACTION REQUIRED: Update Construction Progress Portal';

    $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
    $safeCo   = htmlspecialchars($company, ENT_QUOTES, 'UTF-8');
    $safeUrl  = htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8');

    $mail->Body = <<<HTML
    <div style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px;">
        <h2 style="color: #910048; border-bottom: 2px solid #C8A265; padding-bottom: 10px;">Portal Update Reminder</h2>
        <p>Dear {$safeName},</p>
        <p>This is an automated system reminder to update the <strong>Construction Progress Portal</strong>.</p>
        <p>Keeping our stakeholders informed is a priority. Please log in to the dashboard to upload the latest tower-wise status and site photographs.</p>
        <div style="margin: 30px 0; text-align: center;">
            <a href="{$safeUrl}" style="background: #910048; color: #fff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;">
               Open Admin Dashboard
            </a>
        </div>
        <p style="color: #888; font-size: 12px; border-top: 1px solid #eee; padding-top: 15px; margin-bottom: 0;">
            Best Regards,<br>{$safeCo} Automation System<br>
            <em>This is an automated message. Do not reply to this email.</em>
        </p>
    </div>
    HTML;

    $mail->AltBody = "Dear {$toName},\n\nThis is a reminder to update the Construction Progress Portal.\n\nLogin: {$adminUrl}\n\nBest Regards,\n{$company} Automation System";

    $mail->send();

    atomicWriteJson($lockFile, ['last_sent' => time(), 'sent_to' => $toEmail]);
    out('Reminder sent successfully.');

} catch (Throwable $e) {
     
    out('Reminder not sent: the mail server rejected the message.',
        'SMTP error: ' . ($mail->ErrorInfo ?: $e->getMessage()));
}
