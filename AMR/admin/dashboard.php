
<?php
require_once __DIR__.'/../config.php';
require_staff();
// Allow admin and employee accounts
if (!logged_in() || !in_array($_SESSION['role'], ['admin', 'employee'], true)) {
    header('Location: ../login.php');
    exit;
}

$employeeName = $_SESSION['name'] ?? 'Employee';

$stats = [
    'Rooms' => $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn(),

    'Bookings' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status NOT IN ('cancelled')")->fetchColumn(),

    'Guests' => $pdo->query("SELECT COUNT(*) FROM users WHERE role='guest'")->fetchColumn(),

    'Revenue' => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_status='paid'")->fetchColumn()
];

$recent = $pdo->query("
    SELECT b.*, u.full_name, r.room_name
    FROM bookings b
    JOIN users u ON u.id=b.user_id
    JOIN rooms r ON r.id=b.room_id
    ORDER BY b.created_at DESC
    LIMIT 8
")->fetchAll();

$adminTitle = 'Dashboard';
require '_layout_top.php';
?>

<div class="mb-4">
    <h4>Welcome, <?= e($employeeName) ?>!</h4>
    <p class="text-secondary">
        Welcome to Aling Mameng Resort Management System.
    </p>
</div>

<div class="row g-3 mb-4">

    <?php foreach($stats as $label => $value): ?>

        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <span><?= e($label) ?></span>

                <h3>
                    <?= $label === 'Revenue'
                        ? money($value)
                        : number_format((int)$value) ?>
                </h3>

                <small>Current records</small>
            </div>
        </div>

    <?php endforeach; ?>

</div>

<div class="content-card">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Recent Reservations</h5>
        <a href="reservations.php" class="small">View all</a>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">

            <thead>
                <tr>
                    <th>Booking</th>
                    <th>Guest</th>
                    <th>Room</th>
                    <th>Check-in</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach($recent as $b): ?>

                    <tr>
                        <td><?= e($b['booking_code']) ?></td>
                        <td><?= e($b['full_name']) ?></td>
                        <td><?= e($b['room_name']) ?></td>
                        <td><?= e($b['check_in']) ?></td>
                        <td><?= money($b['total_amount']) ?></td>
                        <td>
                            <span class="badge text-bg-light">
                                <?= e($b['status']) ?>
                            </span>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <?php if(!$recent): ?>
                    <tr>
                        <td colspan="6" class="text-center text-secondary">
                            No reservations yet.
                        </td>
                    </tr>
                <?php endif; ?>

            </tbody>

        </table>
    </div>

</div>

<?php require '_layout_bottom.php'; ?>
