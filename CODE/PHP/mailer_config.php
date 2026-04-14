<?php
// /NexGen/CODE/PHP/mailer_config.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer manually
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

function createMailer(): PHPMailer
{
    $mail = new PHPMailer(true);

    // SMTP Settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'mattraileyvaldevia@gmail.com';
    $mail->Password   = 'twhw dhvp emfm uhwf';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Email format
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(false);

    // Sender
    $mail->setFrom('mattraileyvaldevia@gmail.com', 'NextGen Micro-Enterprise');

    return $mail;
}
?>