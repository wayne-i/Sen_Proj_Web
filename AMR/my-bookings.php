
<?php
// =====================================================
// 1. DATABASE CONNECTION AND LOGIN REQUIREMENT
// =====================================================

require_once 'config.php';

require_login();


// =====================================================
// 2. FETCH CUSTOMER RESERVATIONS
// =====================================================

$bookingStmt = $pdo->prepare("
    SELECT
        b.*,
        r.room_name,
        p.payment_status

    FROM bookings b

    INNER JOIN rooms r
        ON r.id = b.room_id

    LEFT JOIN payments p
        ON p.booking_id = b.id

    WHERE b.user_id = ?

    ORDER BY b.created_at DESC
");

$bookingStmt->execute([
    $_SESSION['user_id']
]);

$bookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);


// =====================================================
// 3. PAGE SETTINGS
// =====================================================

$pageTitle = 'My Bookings';


// =====================================================
// 4. PAGE HEADER
// =====================================================

require_once 'includes/header.php';

?>

<!-- =====================================================
     5. MY RESERVATIONS SECTION
====================================================== -->

<div class="container section-space">

    <!-- Page Heading -->

    <div class="section-heading">

        <div>

            <span class="eyebrow">
                GUEST ACCOUNT
            </span>

            <h2>
                My Reservations
            </h2>

        </div>


        <a href="index.php#rooms" class="btn btn-dark">
            <i class="fa-solid fa-plus me-2"></i>
            Book a Room
        </a>

    </div>


    <!-- =================================================
         6. BOOKING SUCCESS MESSAGE
    ================================================== -->

    <?php if (isset($_GET['created']) && $_GET['created'] === '1'): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <i class="fa-solid fa-circle-check me-2"></i>

            Your reservation request has been submitted successfully.

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"
            ></button>

        </div>

    <?php endif; ?>


    <!-- =================================================
         7. RESERVATIONS TABLE
    ================================================== -->

    <div class="table-responsive bg-white rounded-4 border">

        <table class="table align-middle mb-0">

            <!-- Table Header -->

            <thead>

                <tr>
                    <th>Booking Code</th>
                    <th>Room</th>
                    <th>Dates</th>
                    <th>Total Rent</th>
                    <th>Booking Status</th>
                    <th>Payment Status</th>
                    <th>Action</th>
                </tr>

            </thead>


            <!-- Table Body -->

            <tbody>

                <?php if (!empty($bookings)): ?>

                    <?php foreach ($bookings as $booking): ?>

                        <?php
                            // Get payment status
                            $paymentStatus = $booking['payment_status'] ?? 'unpaid';

                            // Format booking status
                            $bookingStatus = ucwords(
                                str_replace('_', ' ', $booking['status'])
                            );

                            // Determine payment badge
                            $paymentBadge = 'danger';

                            if ($paymentStatus === 'paid') {
                                $paymentBadge = 'success';
                            } elseif ($paymentStatus === 'pending') {
                                $paymentBadge = 'warning';
                            }
                        ?>

                        <tr>

                            <!-- Booking Code -->

                            <td>
                                <strong>
                                    <?= e($booking['booking_code']) ?>
                                </strong>
                            </td>


                            <!-- Room Name -->

                            <td>
                                <?= e($booking['room_name']) ?>
                            </td>


                            <!-- Check-in and Check-out -->

                            <td>

                                <div class="small">

                                    <?= e($booking['check_in']) ?>

                                    <span class="text-secondary">
                                        to
                                    </span>

                                    <?= e($booking['check_out']) ?>

                                </div>

                            </td>


                            <!-- Total Rent -->

                            <td>

                                <strong>
                                    <?= money($booking['total_amount']) ?>
                                </strong>

                            </td>


                            <!-- Booking Status -->

                            <td>

                                <span class="badge text-bg-light">
                                    <?= e($bookingStatus) ?>
                                </span>

                            </td>


                            <!-- Payment Status -->

                            <td>

                                <span class="badge text-bg-<?= $paymentBadge ?>">

                                    <?= e(ucfirst($paymentStatus)) ?>

                                </span>

                            </td>


                            <!-- Payment Action -->

                            <td>

                                <?php if ($paymentStatus !== 'paid'): ?>

                                    <a
                                        href="payment.php?booking=<?= (int) $booking['id'] ?>"
                                        class="btn btn-sm btn-dark"
                                    >

                                        <?php if ($paymentStatus === 'pending'): ?>

                                            <i class="fa-solid fa-eye me-1"></i>
                                            View Payment

                                        <?php else: ?>

                                            <i class="fa-solid fa-credit-card me-1"></i>
                                            Pay Rent

                                        <?php endif; ?>

                                    </a>

                                <?php else: ?>

                                    <span class="small text-success">

                                        <i class="fa-solid fa-circle-check me-1"></i>
                                        Completed

                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <!-- Empty Reservations Message -->

                    <tr>

                        <td colspan="7" class="text-center py-5 text-secondary">

                            <i class="fa-solid fa-calendar-xmark fa-2x mb-3 d-block"></i>

                            <h5>
                                No Reservations Found
                            </h5>

                            <p class="mb-3">
                                You don't have any reservations yet.
                            </p>

                            <a href="index.php#rooms" class="btn btn-dark btn-sm">
                                Explore Available Rooms
                            </a>

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- =====================================================
     8. PAGE FOOTER
====================================================== -->

<?php require_once 'includes/footer.php'; ?>
