<?php
// /NexGen/CODE/PHP/mailer_config.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

const NEXGEN_MAIL_USERNAME = 'nexgeneration2026@gmail.com';
const NEXGEN_MAIL_APP_PASSWORD = 'rbvwpabkiitlwhri';

function createMailer(): PHPMailer
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;

    $mail->Username = NEXGEN_MAIL_USERNAME;
    $mail->Password = NEXGEN_MAIL_APP_PASSWORD;

    // Gmail STARTTLS configuration
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    // Prevent very long hangs when SMTP is unreachable.
    $mail->Timeout = 15;

    $mail->CharSet = 'UTF-8';
    $mail->isHTML(false);

    $mail->setFrom(NEXGEN_MAIL_USERNAME, 'NexGen');

    return $mail;
}