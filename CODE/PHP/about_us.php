<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

$displayName = $_SESSION['username'] ?? 'Client';
$fullName = $_SESSION['full_name'] ?? 'Client';
$profileImage = !empty($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'uploads/default.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - NexGen</title>
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/about_us.css">
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/header.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

<div class="about-page">

    <?php include 'header.php'; ?>

    <section class="about-sections">

        <div class="about-card animate-card animate-up">
            <div class="about-card-image">
                <img src="/NexGen/IMAGES/boutusupper.png" alt="About NexGen">
            </div>

            <div class="about-card-text">
                <span class="section-label">WHO WE ARE</span>
                <h2>About NexGen</h2>
                <p>
                    NexGen is a web-based management system designed to help micro-enterprises manage
                    inventory, sales transactions, and analytic reporting in a more efficient and intelligent way.
                </p>
                <p>
                    Our platform centralizes business data into one secure system, reducing manual work
                    and improving data accuracy. It simplifies daily operations by integrating inventory monitoring,
                    sales analytics, and a chatbot assistant that allows users to access information quickly
                    through conversational commands.
                </p>
                <p>
                    NexGen supports small businesses in transitioning from traditional methods to a smarter,
                    faster, and more reliable digital management system.
                </p>
            </div>
        </div>

        <div class="about-card reverse animate-card animate-left hover-fade-card">
            <div class="about-card-image">
                <img src="/NexGen/IMAGES/vission.jpg" alt="NexGen Vision">
            </div>

            <div class="about-card-text">
                <span class="section-label">OUR FUTURE</span>
                <h2>Vision</h2>
                <p>
                    Our vision is to empower small businesses to grow and succeed by transforming traditional
                    management methods into smart, reliable, and technology-driven solutions.
                </p>
            </div>
        </div>

        <div class="about-card animate-card animate-right hover-fade-card">
            <div class="about-card-image">
                <img src="/NexGen/IMAGES/mission.jpg" alt="NexGen Mission">
            </div>

            <div class="about-card-text">
                <span class="section-label">OUR GOAL</span>
                <h2>Mission</h2>
                <p>
                    NexGen aims to provide micro-enterprise owners with an efficient web-based management
                    system that simplifies inventory tracking, sales monitoring, and business analytics.
                </p>
                <p>
                    Through intelligent tools and a chatbot assistant, the system helps businesses make faster
                    decisions, improve operational efficiency, and enhance overall productivity.
                </p>
            </div>
        </div>

    </section>

    <footer class="footer-section" id="footer-section">
        <div class="footer-top-line"></div>
        <p>
            Copyright © 2026 NexGen Micro-Enterprise |
            <a href="/NexGen/CODE/PHP/privacy_policy.php" style="color:#f7d98b; text-decoration:none; font-weight:700;">
                Privacy Policy
            </a>
            |
            <a href="/NexGen/CODE/PHP/privacy_policy.php#cookie-notice" style="color:#f7d98b; text-decoration:none; font-weight:700;">
                Cookie Notice
            </a>
        </p>
    </footer>

    <?php include 'chatbot.php'; ?>

<script src="/NexGen/CODE/JS/about_us.js"></script>
</body>
</html>