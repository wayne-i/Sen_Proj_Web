
<?php

require_once __DIR__ . '/../config.php';

require_admin();

// ==========================================
// 1. PROCESS RESERVATION STATUS UPDATE
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $bookingId = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';

    // Allowed reservation statuses
    $allowedStatuses = [
        'pending',
        'confirmed',
        'checked_in',
        'checked_out',
        'cancelled'
    ];

    // Validate status
    if (
        $bookingId > 0 &&
        in_array($status, $allowedStatuses, true)
    ) {

        $sql = "
            UPDATE bookings
            SET status = ?
            WHERE id = ?
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $status,
            $bookingId
        ]);

    }

    // Redirect after updating
    header('Location: reservations.php');
    exit;
}

// ==========================================
// 2. FETCH RESERVATION RECORDS
// ==========================================

$sql = "
    SELECT
        b.*,
        u.full_name,
        u.email,
        r.room_name
    FROM bookings b
    INNER JOIN users u ON u.id = b.user_id
    INNER JOIN rooms r ON r.id = b.room_id
    ORDER BY b.created_at DESC
";

$stmt = $pdo->query($sql);

$reservations = $stmt->fetchAll();

// ==========================================
// 3. PAGE SETTINGS
// ==========================================

$adminTitle = 'Reservations';

require '_layout_top.php';

?>

<!-- ==========================================
     4. RESERVATION MANAGEMENT TABLE
========================================== -->

<div class="content-card">

    <h5>Reservation List</h5>

    <p class="text-secondary small">
        Manage guest reservations and update their booking status.
    </p>

    <div class="table-responsive">

        <table class="table align-middle">

            <thead>

                <tr>
                    <th>Booking Code</th>
                    <th>Guest</th>
                    <th>Room</th>
                    <th>Stay</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Update</th>
                </tr>

            </thead>

            <tbody>

                <?php if (!empty($reservations)): ?>

                    <?php foreach ($reservations as $reservation): ?>

                        <tr>

                            <!-- Booking Code -->
                            <td>

                                <strong>
                                    <?= e($reservation['booking_code']) ?>
                                </strong>

                            </td>

                            <!-- Guest Information -->
                            <td>

                                <strong>
                                    <?= e($reservation['full_name']) ?>
                                </strong>

                                <div class="small text-secondary">
                                    <?= e($reservation['email']) ?>
                                </div>

                            </td>

                            <!-- Room -->
                            <td>
                                <?= e($reservation['room_name']) ?>
                            </td>

                            <!-- Check-in and Check-out -->
                            <td>

                                <?= e($reservation['check_in']) ?>

                                <span class="text-secondary">–</span>

                                <?= e($reservation['check_out']) ?>

                            </td>

                            <!-- Total Amount -->
                            <td>

                                <strong>
                                    <?= money($reservation['total_amount']) ?>
                                </strong>

                            </td>

                            <!-- Reservation Status -->
                            <td>

                                <?php

                                $status = $reservation['status'];

                                $statusBadges = [
                                    'pending'     => 'text-bg-warning',
                                    'confirmed'   => 'text-bg-success',
                                    'checked_in'  => 'text-bg-primary',
                                    'checked_out' => 'text-bg-secondary',
                                    'cancelled'   => 'text-bg-danger'
                                ];

                                $badgeClass = $statusBadges[$status]
                                    ?? 'text-bg-secondary';

                                $statusLabel = ucwords(
                                    str_replace('_', ' ', $status)
                                );

                                ?>

                                <span class="badge <?= e($badgeClass) ?>">
                                    <?= e($statusLabel) ?>
                                </span>

                            </td>

                            <!-- Update Reservation Status -->
                            <td>

                                <form
                                    method="POST"
                                    class="d-flex gap-1"
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $reservation['id'] ?>"
                                    >

                                    <select
                                        class="form-select form-select-sm"
                                        name="status"
                                    >

                                        <?php

                                        $allowedStatuses = [
                                            'pending',
                                            'confirmed',
                                            'checked_in',
                                            'checked_out',
                                            'cancelled'
                                        ];

                                        foreach ($allowedStatuses as $statusOption):

                                        ?>

                                            <option
                                                value="<?= e($statusOption) ?>"
                                                <?= $statusOption === $reservation['status'] ? 'selected' : '' ?>
                                            >

                                                <?= e(
                                                    ucwords(
                                                        str_replace(
                                                            '_',
                                                            ' ',
                                                            $statusOption
                                                        )
                                                    )
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-dark"
                                    >
                                        Save
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="7"
                            class="text-center text-secondary py-4"
                        >
                            No reservations found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require '_layout_bottom.php'; ?>
