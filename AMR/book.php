
<?php
// =====================================================
// 1. DATABASE CONNECTION AND LOGIN REQUIREMENT
// =====================================================

require_once 'config.php';

require_login();


// =====================================================
// 2. GET AND VALIDATE ROOM INFORMATION
// =====================================================

$roomId = (int) ($_GET['room'] ?? $_POST['room_id'] ?? 0);

$roomStmt = $pdo->prepare("
    SELECT *
    FROM rooms
    WHERE id = ?
      AND status = 'available'
");

$roomStmt->execute([$roomId]);

$room = $roomStmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    http_response_code(404);
    exit('Room not found or unavailable.');
}


// =====================================================
// 3. INITIALIZE MESSAGES
// =====================================================

$error = '';


// =====================================================
// 4. PROCESS RESERVATION FORM
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get submitted form values
    $checkIn = trim($_POST['check_in'] ?? '');
    $checkOut = trim($_POST['check_out'] ?? '');

    $guests = (int) ($_POST['guests'] ?? 1);

    $specialRequest = trim($_POST['special_request'] ?? '');


    // -------------------------------------------------
    // 4.1 VALIDATE DATES
    // -------------------------------------------------

    $checkInDate = DateTime::createFromFormat('!Y-m-d', $checkIn);
    $checkOutDate = DateTime::createFromFormat('!Y-m-d', $checkOut);

    $validCheckIn = $checkInDate &&
        $checkInDate->format('Y-m-d') === $checkIn;

    $validCheckOut = $checkOutDate &&
        $checkOutDate->format('Y-m-d') === $checkOut;

    $today = new DateTime('today');

    if (
        !$validCheckIn ||
        !$validCheckOut ||
        $checkInDate < $today ||
        $checkOutDate <= $checkInDate
    ) {

        $error = 'Please select valid check-in and check-out dates.';

    }

    // -------------------------------------------------
    // 4.2 VALIDATE NUMBER OF GUESTS
    // -------------------------------------------------

    elseif (
        $guests < 1 ||
        $guests > (int) $room['capacity']
    ) {

        $error = 'The selected room does not have enough capacity.';

    }

    // -------------------------------------------------
    // 4.3 CHECK ROOM AVAILABILITY
    // -------------------------------------------------

    else {

        // Calculate number of nights
        $nights = (int) $checkInDate->diff($checkOutDate)->days;

        // Check overlapping reservations
        $availabilityStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM bookings
            WHERE room_id = ?
              AND status IN ('pending', 'confirmed', 'checked_in')
              AND check_in < ?
              AND check_out > ?
        ");

        $availabilityStmt->execute([
            $roomId,
            $checkOut,
            $checkIn
        ]);

        $existingBookings = (int) $availabilityStmt->fetchColumn();


        if ($existingBookings > 0) {

            $error = 'This room is already reserved for some or all of those dates.';

        }

        // -------------------------------------------------
        // 4.4 CREATE BOOKING AND PAYMENT RECORD
        // -------------------------------------------------

        else {

            try {

                // Begin database transaction
                $pdo->beginTransaction();

                // Generate unique booking code
                $bookingCode = 'AMR-' .
                    date('ymd') . '-' .
                    strtoupper(bin2hex(random_bytes(3)));

                // Calculate total reservation amount
                $totalAmount = $nights *
                    (float) $room['price_per_night'];


                // Insert booking information
                $bookingStmt = $pdo->prepare("
                    INSERT INTO bookings (
                        booking_code,
                        user_id,
                        room_id,
                        check_in,
                        check_out,
                        guests,
                        nights,
                        total_amount,
                        special_request
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $bookingStmt->execute([
                    $bookingCode,
                    $_SESSION['user_id'],
                    $roomId,
                    $checkIn,
                    $checkOut,
                    $guests,
                    $nights,
                    $totalAmount,
                    $specialRequest
                ]);


                // Get newly created booking ID
                $bookingId = (int) $pdo->lastInsertId();


                // Create initial payment record
                $paymentStmt = $pdo->prepare("
                    INSERT INTO payments (
                        booking_id,
                        amount,
                        payment_method,
                        payment_status
                    )
                    VALUES (?, ?, 'cash', 'unpaid')
                ");

                $paymentStmt->execute([
                    $bookingId,
                    $totalAmount
                ]);


                // Commit database transaction
                $pdo->commit();


                // Redirect after successful booking
                header('Location: my-bookings.php?created=1');
                exit;

            } catch (Throwable $e) {

                // Roll back if an error occurs
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Booking Error: ' . $e->getMessage());

                $error = 'Unable to complete your reservation. Please try again.';
            }
        }
    }
}


// =====================================================
// 5. PAGE HEADER
// =====================================================

$pageTitle = 'Book a Room';

require_once 'includes/header.php';

?>

<!-- =====================================================
     6. ROOM BOOKING FORM
====================================================== -->

<div class="container section-space">

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="auth-card">

                <!-- Room Information -->

                <span class="eyebrow">
                    RESERVATION REQUEST
                </span>

                <h2>
                    <?= e($room['room_name']) ?>
                </h2>

                <p class="text-secondary">
                    <?= money($room['price_per_night']) ?> per night
                    · Maximum <?= (int) $room['capacity'] ?> guests
                </p>


                <!-- Error Message -->

                <?php if (!empty($error)): ?>

                    <div class="alert alert-danger">
                        <?= e($error) ?>
                    </div>

                <?php endif; ?>


                <!-- Reservation Form -->

                <form method="POST" class="row g-3">

                    <input
                        type="hidden"
                        name="room_id"
                        value="<?= $roomId ?>"
                    >


                    <!-- Check-in Date -->

                    <div class="col-md-6">

                        <label class="form-label">
                            Check-in
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            name="check_in"
                            min="<?= date('Y-m-d') ?>"
                            value="<?= e($_POST['check_in'] ?? '') ?>"
                            required
                        >

                    </div>


                    <!-- Check-out Date -->

                    <div class="col-md-6">

                        <label class="form-label">
                            Check-out
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            name="check_out"
                            min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                            value="<?= e($_POST['check_out'] ?? '') ?>"
                            required
                        >

                    </div>


                    <!-- Number of Guests -->

                    <div class="col-md-6">

                        <label class="form-label">
                            Number of Guests
                        </label>

                        <input
                            type="number"
                            class="form-control"
                            name="guests"
                            min="1"
                            max="<?= (int) $room['capacity'] ?>"
                            value="<?= (int) ($_POST['guests'] ?? 2) ?>"
                            required
                        >

                    </div>


                    <!-- Special Request -->

                    <div class="col-12">

                        <label class="form-label">
                            Special Request (Optional)
                        </label>

                        <textarea
                            class="form-control"
                            name="special_request"
                            rows="3"
                            placeholder="Enter any additional requests..."
                        ><?= e($_POST['special_request'] ?? '') ?></textarea>

                    </div>


                    <!-- Submit Button -->

                    <div class="col-12">

                        <button
                            type="submit"
                            class="btn btn-dark w-100"
                        >
                            Submit Reservation Request
                        </button>

                    </div>

                </form>


                <!-- Booking Notice -->

                <p class="small text-secondary mt-3 mb-0">
                    Your booking will be marked as pending
                    until confirmed by the resort.
                </p>

            </div>

        </div>

    </div>

</div>


<!-- =====================================================
     7. PAGE FOOTER
====================================================== -->

<?php require_once 'includes/footer.php'; ?>
