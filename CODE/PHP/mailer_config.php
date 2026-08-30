<?php
// /NexGen/CODE/PHP/mailer_config.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

function createMailer(): PHPMailer
{
    $mailUsername = trim((string)(getenv('NEXGEN_MAIL_USERNAME') ?: ''));
    $mailAppPassword = (string)(getenv('NEXGEN_MAIL_APP_PASSWORD') ?: '');

    if ($mailUsername === '' || $mailAppPassword === '') {
        throw new RuntimeException('NexGen mail delivery is not configured.');
    }

    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;

    $mail->Username = 'nexgeneration2026@gmail.com';
    $mail->Password = 'mqwe ucds cqiq dplw';

    // Gmail STARTTLS configuration
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    // Prevent very long hangs when SMTP is unreachable.
    $mail->Timeout = 15;

    $mail->CharSet = 'UTF-8';
    $mail->isHTML(false);

    $mail->setFrom($mailUsername, 'NexGen');

    return $mail;
}
