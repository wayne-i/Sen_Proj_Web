
<?php

require 'config.php';

require_login();

// ==========================================
// 1. GET BOOKING INFORMATION
// ==========================================

$bookingId = (int) ($_GET['booking'] ?? $_POST['booking_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];

/**
 * Retrieve booking and payment information.
 */
function getBooking(PDO $pdo, int $bookingId, int $userId): array|false
{
    $sql = "
        SELECT
            b.*,
            r.room_name,
            p.id AS payment_id,
            p.payment_status,
            p.payment_method,
            p.reference_no,
            p.proof_image,
            p.paid_at
        FROM bookings b
        INNER JOIN rooms r ON r.id = b.room_id
        LEFT JOIN payments p ON p.booking_id = b.id
        WHERE b.id = ?
          AND b.user_id = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$bookingId, $userId]);

    return $stmt->fetch();
}

// Retrieve booking
$booking = getBooking($pdo, $bookingId, $userId);

if (!$booking) {
    http_response_code(404);
    exit('Reservation not found.');
}

// ==========================================
// 2. CREATE PAYMENT RECORD IF NOT EXISTING
// ==========================================

if (!$booking['payment_id']) {

    $sql = "
        INSERT INTO payments (
            booking_id,
            amount,
            payment_method,
            payment_status
        )
        VALUES (?, ?, 'cash', 'unpaid')
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $bookingId,
        $booking['total_amount']
    ]);

    header("Location: payment.php?booking=$bookingId");
    exit;
}

// ==========================================
// 3. PROCESS PAYMENT SUBMISSION
// ==========================================

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $method = $_POST['payment_method'] ?? '';
    $reference = trim($_POST['reference_no'] ?? '');

    // Check if already paid
    if ($booking['payment_status'] === 'paid') {

        $error = 'This reservation is already paid.';

    }

    // Validate payment method
    elseif (!in_array($method, ['gcash', 'bank_transfer', 'cash'], true)) {

        $error = 'Select a valid payment method.';

    }

    // Validate reference number
    elseif ($method !== 'cash' && $reference === '') {

        $error = 'Enter the transfer reference number.';

    }

    else {

        $proof = $booking['proof_image'];

        // ==================================
        // 4. UPLOAD PAYMENT PROOF
        // ==================================

        if (
            isset($_FILES['proof_image']) &&
            $_FILES['proof_image']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            $file = $_FILES['proof_image'];

            if ($file['error'] !== UPLOAD_ERR_OK) {

                $error = 'Proof upload failed.';

            } elseif ($file['size'] > 3 * 1024 * 1024) {

                $error = 'Image must be 3 MB or smaller.';

            } else {

                $mime = (new finfo(FILEINFO_MIME_TYPE))
                    ->file($file['tmp_name']);

                $allowedTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];

                $extension = $allowedTypes[$mime] ?? null;

                if (!$extension) {

                    $error = 'Only JPG, PNG, or WEBP images are accepted.';

                } else {

                    $uploadDirectory = __DIR__ . '/uploads/payment-proofs';

                    if (
                        !is_dir($uploadDirectory) &&
                        !mkdir($uploadDirectory, 0755, true)
                    ) {

                        $error = 'Could not create proof upload folder.';

                    } else {

                        $filename = 'payment_' .
                            $bookingId . '_' .
                            bin2hex(random_bytes(8)) .
                            '.' . $extension;

                        $destination = $uploadDirectory . '/' . $filename;

                        if (move_uploaded_file($file['tmp_name'], $destination)) {

                            $proof = 'uploads/payment-proofs/' . $filename;

                        } else {

                            $error = 'Could not save proof image.';

                        }
                    }
                }
            }
        }

        // ==================================
        // 5. UPDATE PAYMENT INFORMATION
        // ==================================

        if ($error === '') {

            $sql = "
                UPDATE payments
                SET
                    amount = ?,
                    payment_method = ?,
                    reference_no = ?,
                    proof_image = ?,
                    payment_status = 'pending',
                    paid_at = NULL
                WHERE id = ?
                  AND booking_id = ?
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $booking['total_amount'],
                $method,
                $reference ?: null,
                $proof,
                $booking['payment_id'],
                $bookingId
            ]);

            header("Location: payment.php?booking=$bookingId&submitted=1");
            exit;
        }
    }
}

// Refresh booking information
$booking = getBooking($pdo, $bookingId, $userId);

$pageTitle = 'Payment';

require 'includes/header.php';

?>

<!-- ==========================================
     6. PAYMENT PAGE DESIGN
========================================== -->

<div class="container section-space">

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="auth-card">

                <span class="eyebrow">RESERVATION PAYMENT</span>

                <h2>Complete your payment</h2>

                <p class="text-secondary">
                    Submit your payment details for resort verification.
                </p>

                <!-- Submission Success -->
                <?php if (isset($_GET['submitted'])): ?>

                    <div class="alert alert-success">
                        Payment details submitted.
                        Your status is now <strong>Pending</strong>
                        while the resort verifies your payment.
                    </div>

                <?php endif; ?>

                <!-- Error Message -->
                <?php if ($error): ?>

                    <div class="alert alert-danger">
                        <?= e($error) ?>
                    </div>

                <?php endif; ?>

                <!-- ==================================
                     PAYMENT SUMMARY
                ================================== -->

                <div class="payment-summary mb-4">

                    <div class="d-flex justify-content-between">
                        <span>Booking code</span>
                        <strong><?= e($booking['booking_code']) ?></strong>
                    </div>

                    <div class="d-flex justify-content-between mt-2">
                        <span>Room</span>
                        <strong><?= e($booking['room_name']) ?></strong>
                    </div>

                    <div class="d-flex justify-content-between mt-2">
                        <span>Stay</span>
                        <strong>
                            <?= e($booking['check_in']) ?>
                            to
                            <?= e($booking['check_out']) ?>
                        </strong>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between">
                        <strong>Total rent</strong>

                        <strong class="fs-4">
                            <?= money($booking['total_amount']) ?>
                        </strong>
                    </div>

                    <!-- Payment Status -->
                    <div class="mt-3">

                        Payment status:

                        <?php if ($booking['payment_status'] === 'paid'): ?>

                            <span class="badge text-bg-success">
                                Paid
                            </span>

                        <?php elseif ($booking['payment_status'] === 'pending'): ?>

                            <span class="badge text-bg-warning">
                                Pending
                            </span>

                        <?php else: ?>

                            <span class="badge text-bg-danger">
                                Unpaid
                            </span>

                        <?php endif; ?>

                    </div>

                </div>

                <!-- ==================================
                     PAYMENT FORM
                ================================== -->

                <?php if ($booking['payment_status'] !== 'paid'): ?>

                    <div class="alert alert-info small">

                        <strong>Important:</strong>

                        This starter website does not process GCash
                        or bank payments automatically.

                        Use only official payment details given directly
                        by the resort.

                        Submit your transaction reference and proof.
                        An administrator must verify the actual payment
                        before marking it Paid.

                    </div>

                    <form
                        method="POST"
                        enctype="multipart/form-data"
                        class="vstack gap-3"
                    >

                        <input
                            type="hidden"
                            name="booking_id"
                            value="<?= (int) $bookingId ?>"
                        >

                        <!-- Payment Method -->
                        <div>

                            <label class="form-label">
                                Payment method
                            </label>

                            <select
                                class="form-select"
                                name="payment_method"
                                required
                            >

                                <option value="">
                                    Choose payment method
                                </option>

                                <option
                                    value="gcash"
                                    <?= ($booking['payment_method'] ?? '') === 'gcash' ? 'selected' : '' ?>
                                >
                                    GCash
                                </option>

                                <option
                                    value="bank_transfer"
                                    <?= ($booking['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : '' ?>
                                >
                                    Bank transfer
                                </option>

                                <option
                                    value="cash"
                                    <?= ($booking['payment_method'] ?? '') === 'cash' ? 'selected' : '' ?>
                                >
                                    Pay at resort (cash)
                                </option>

                            </select>

                        </div>

                        <!-- Reference Number -->
                        <div>

                            <label class="form-label">
                                Transaction reference number
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="reference_no"
                                value="<?= e($booking['reference_no'] ?? '') ?>"
                                placeholder="Required for GCash or bank transfer"
                            >

                        </div>

                        <!-- Payment Proof -->
                        <div>

                            <label class="form-label">
                                Proof of payment
                            </label>

                            <input
                                type="file"
                                class="form-control"
                                name="proof_image"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            >

                            <div class="form-text">
                                Optional for cash. JPG, PNG, or WEBP.
                                Maximum 3 MB.
                            </div>

                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-dark w-100">
                            Submit payment details
                        </button>

                    </form>

                <?php else: ?>

                    <div class="alert alert-success">
                        This booking is fully paid. Thank you!
                    </div>

                <?php endif; ?>

                <!-- Uploaded Proof -->
                <?php if (!empty($booking['proof_image'])): ?>

                    <p class="small mt-3">

                        Submitted proof:

                        <a
                            href="<?= e($booking['proof_image']) ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            View image
                        </a>

                    </p>

                <?php endif; ?>

                <!-- Back Button -->
                <a
                    href="my-bookings.php"
                    class="btn btn-link px-0 mt-2"
                >
                    Back to my bookings
                </a>

            </div>

        </div>

    </div>

</div>

<?php require 'includes/footer.php'; ?>
