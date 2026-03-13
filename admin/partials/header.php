<?php
/**
 * Admin layout header
 * @var string $page_title
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title ?? 'Admin') ?> – PhpMailerBulk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="bi bi-envelope-at-fill me-2"></i>PhpMailerBulk
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="leads.php"><i class="bi bi-people-fill"></i> Leads</a></li>
                <li class="nav-item"><a class="nav-link" href="smtp.php"><i class="bi bi-server"></i> SMTP Accounts</a></li>
                <li class="nav-item"><a class="nav-link" href="templates.php"><i class="bi bi-file-richtext-fill"></i> Templates</a></li>
                <li class="nav-item"><a class="nav-link" href="campaigns.php"><i class="bi bi-megaphone-fill"></i> Campaigns</a></li>
                <li class="nav-item"><a class="nav-link" href="stats.php"><i class="bi bi-bar-chart-fill"></i> Stats</a></li>
                <li class="nav-item"><a class="nav-link" href="antispam.php"><i class="bi bi-shield-check"></i> Anti-Spam</a></li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="nav-link text-warning">
                        <i class="bi bi-person-circle"></i> <?= h($_SESSION['admin_user'] ?? '') ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid py-4">
    <?php render_flash(); ?>
