
<?php
require_once __DIR__.'/../config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['payment_status'] ?? '';
    $method = $_POST['payment_method'] ?? '';

    if (
        in_array($status, ['unpaid', 'pending', 'paid'], true) &&
        in_array($method, ['cash', 'gcash', 'bank_transfer'], true)
    ) {

        $s = $pdo->prepare("
            UPDATE payments
            SET payment_status = ?,
                payment_method = ?,
                paid_at = CASE
                    WHEN ? = 'paid' THEN COALESCE(paid_at, NOW())
                    ELSE NULL
                END
            WHERE id = ?
        ");

        $s->execute([$status, $method, $status, $id]);
    }

    header('Location: payments.php');
    exit;
}

$rows = $pdo->query("
    SELECT p.*, b.booking_code, u.full_name
    FROM payments p
    JOIN bookings b ON b.id = p.booking_id
    JOIN users u ON u.id = b.user_id
    ORDER BY p.created_at DESC
")->fetchAll();

$adminTitle = 'Payments';
require '_layout_top.php';
?>

<div class="content-card">

    <div class="mb-3">
        <h5 class="mb-1">Guest Payments</h5>
        <p class="text-secondary small mb-0">
            Manage guest payments and confirm their payment status.
        </p>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">

            <thead>
                <tr>
                    <th>Booking</th>
                    <th>Guest</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Admin Action</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($rows as $p): ?>

                <tr>

                    <td>
                        <?= e($p['booking_code']) ?>
                    </td>

                    <td>
                        <?= e($p['full_name']) ?>
                    </td>

                    <td>
                        <?= money($p['amount']) ?>
                    </td>

                    <td>
                        <?= e(str_replace('_', ' ', ucfirst($p['payment_method']))) ?>
                    </td>

                    <td>
                        <?php if ($p['payment_status'] === 'paid'): ?>

                            <span class="badge text-bg-success">Paid</span>

                        <?php elseif ($p['payment_status'] === 'pending'): ?>

                            <span class="badge text-bg-warning">Pending</span>

                        <?php else: ?>

                            <span class="badge text-bg-danger">Unpaid</span>

                        <?php endif; ?>
                    </td>

                    <td>
                        <form method="post" class="d-grid gap-1">

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int)$p['id'] ?>"
                            >

                            <select
                                class="form-select form-select-sm"
                                name="payment_status"
                            >
                                <?php foreach (['unpaid', 'pending', 'paid'] as $v): ?>

                                    <option
                                        value="<?= $v ?>"
                                        <?= $v === $p['payment_status'] ? 'selected' : '' ?>
                                    >
                                        <?= ucfirst($v) ?>
                                    </option>

                                <?php endforeach; ?>
                            </select>

                            <select
                                class="form-select form-select-sm"
                                name="payment_method"
                            >
                                <?php foreach (['cash', 'gcash', 'bank_transfer'] as $v): ?>

                                    <option
                                        value="<?= $v ?>"
                                        <?= $v === $p['payment_method'] ? 'selected' : '' ?>
                                    >
                                        <?= e(str_replace('_', ' ', ucfirst($v))) ?>
                                    </option>

                                <?php endforeach; ?>
                            </select>

                            <button type="submit" class="btn btn-sm btn-dark">
                                Save Status
                            </button>

                        </form>
                    </td>

                </tr>

                <?php endforeach; ?>

                <?php if (!$rows): ?>

                    <tr>
                        <td colspan="6" class="text-center text-secondary py-4">
                            No payments recorded yet.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>
    </div>

</div>

<?php require '_layout_bottom.php'; ?>
