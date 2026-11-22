
<?php
require_once __DIR__.'/../config.php';

require_staff();

$adminTitle = $adminTitle ?? 'Dashboard';

$isAdmin = is_admin();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <title><?= e($adminTitle) ?> | Aling Mameng Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <link href="../assets/css/style.css" rel="stylesheet">

</head>

<body class="admin-body">

<div class="admin-shell">

    <aside class="admin-sidebar">

        <a class="admin-brand" href="dashboard.php">
            <span class="brand-mark">AM</span>

            <span>
                Aling Mameng
                <small>
                    <?= $isAdmin ? 'ADMIN PANEL' : 'EMPLOYEE PANEL' ?>
                </small>
            </span>
        </a>

        <nav>

            <a class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>"
               href="dashboard.php">

                <i class="fa-solid fa-gauge-high"></i>
                Dashboard

            </a>

            <?php if ($isAdmin): ?>

                <?php
                $links = [
                    'reservations.php' => 'calendar-check',
                    'rooms.php' => 'bed',
                    'guests.php' => 'users',
                    'facilities.php' => 'umbrella-beach',
                    'payments.php' => 'wallet',
                    'employees.php' => 'user-tie',
                    'reports.php' => 'chart-line'
                ];
                ?>

                <?php foreach ($links as $file => $ico): ?>

                    <a class="<?= basename($_SERVER['PHP_SELF']) === $file ? 'active' : '' ?>"
                       href="<?= $file ?>">

                        <i class="fa-solid fa-<?= $ico ?>"></i>

                        <?= ucfirst(str_replace('.php', '', $file)) ?>

                    </a>

                <?php endforeach; ?>

            <?php endif; ?>

        </nav>

        <a class="sidebar-logout" href="../logout.php">
            <i class="fa-solid fa-right-from-bracket"></i>
            Logout
        </a>

    </aside>

    <div class="admin-main">

        <header class="admin-topbar">

            <div>
                <span class="text-secondary small">
                    ALING MAMENG RESORT
                </span>

                <h5 class="mb-0">
                    <?= e($adminTitle) ?>
                </h5>
            </div>

            <div class="small text-end">

                <div>
                    <i class="fa-regular fa-circle-user me-2"></i>

                    <strong>
                        <?= e($_SESSION['name'] ?? 'User') ?>
                    </strong>
                </div>

                <span class="text-secondary">
                    <?= $isAdmin ? 'Administrator' : 'Employee' ?>
                </span>

            </div>

        </header>

        <div class="admin-content">
