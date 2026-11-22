
<?php

require_once __DIR__ . '/../config.php';

require_admin();

// ==========================================
// 1. FETCH GUEST RECORDS
// ==========================================

$sql = "
    SELECT
        u.id,
        u.full_name,
        u.email,
        u.phone,
        u.created_at,
        COUNT(b.id) AS booking_count
    FROM users u
    LEFT JOIN bookings b ON b.user_id = u.id
    WHERE u.role = 'guest'
    GROUP BY
        u.id,
        u.full_name,
        u.email,
        u.phone,
        u.created_at
    ORDER BY u.created_at DESC
";

$stmt = $pdo->query($sql);

$guests = $stmt->fetchAll();

// ==========================================
// 2. PAGE SETTINGS
// ==========================================

$adminTitle = 'Guest Management';

require '_layout_top.php';

?>

<!-- ==========================================
     3. GUEST MANAGEMENT TABLE
========================================== -->

<div class="content-card">

    <h5>Guest List</h5>

    <p class="text-secondary small">
        View registered guests and their reservation records.
    </p>

    <div class="table-responsive">

        <table class="table align-middle">

            <thead>

                <tr>
                    <th>Guest</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Reservations</th>
                    <th>Registered</th>
                </tr>

            </thead>

            <tbody>

                <?php if (!empty($guests)): ?>

                    <?php foreach ($guests as $guest): ?>

                        <tr>

                            <!-- Guest Name -->
                            <td>

                                <strong>
                                    <?= e($guest['full_name']) ?>
                                </strong>

                            </td>

                            <!-- Email Address -->
                            <td>
                                <?= e($guest['email']) ?>
                            </td>

                            <!-- Phone Number -->
                            <td>
                                <?= e($guest['phone']) ?>
                            </td>

                            <!-- Reservation Count -->
                            <td>

                                <span class="badge text-bg-primary">
                                    <?= (int) $guest['booking_count'] ?>
                                </span>

                            </td>

                            <!-- Registration Date -->
                            <td>
                                <?= e($guest['created_at']) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="5"
                            class="text-center text-secondary py-4"
                        >
                            No registered guests found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require '_layout_bottom.php'; ?>
