
<?php

require_once __DIR__ . '/../config.php';

require_admin();

// ==========================================
// 1. PROCESS FACILITY ACTIONS
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    // ======================================
    // 2. ADD NEW FACILITY
    // ======================================

    if ($action === 'add') {

        $sql = "
            INSERT INTO facilities (
                facility_name,
                description,
                opening_hours
            )
            VALUES (?, ?, ?)
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            trim($_POST['name'] ?? ''),
            trim($_POST['description'] ?? ''),
            trim($_POST['hours'] ?? '')
        ]);

    }

    // ======================================
    // 3. UPDATE FACILITY STATUS
    // ======================================

    elseif ($action === 'status') {

        $facilityId = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';

        // Validate facility status
        $allowedStatuses = [
            'available',
            'maintenance',
            'inactive'
        ];

        if (in_array($status, $allowedStatuses, true)) {

            $sql = "
                UPDATE facilities
                SET status = ?
                WHERE id = ?
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $status,
                $facilityId
            ]);

        }
    }

    // Redirect after processing
    header('Location: facilities.php');
    exit;
}

// ==========================================
// 4. FETCH FACILITY RECORDS
// ==========================================

$sql = "
    SELECT *
    FROM facilities
    ORDER BY facility_name ASC
";

$stmt = $pdo->query($sql);

$facilities = $stmt->fetchAll();

// ==========================================
// 5. PAGE SETTINGS
// ==========================================

$adminTitle = 'Facilities';

require '_layout_top.php';

?>

<!-- ==========================================
     6. ADD FACILITY FORM
========================================== -->

<div class="content-card mb-4">

    <h5>Add Facility</h5>

    <form method="POST" class="row g-2">

        <input
            type="hidden"
            name="action"
            value="add"
        >

        <!-- Facility Name -->
        <div class="col-md-3">

            <input
                type="text"
                class="form-control"
                name="name"
                placeholder="Facility name"
                required
            >

        </div>

        <!-- Description -->
        <div class="col-md-5">

            <input
                type="text"
                class="form-control"
                name="description"
                placeholder="Description"
            >

        </div>

        <!-- Opening Hours -->
        <div class="col-md-2">

            <input
                type="text"
                class="form-control"
                name="hours"
                placeholder="Opening hours"
            >

        </div>

        <!-- Add Button -->
        <div class="col-md-2">

            <button
                type="submit"
                class="btn btn-dark w-100"
            >
                Add Facility
            </button>

        </div>

    </form>

</div>

<!-- ==========================================
     7. FACILITY TABLE
========================================== -->

<div class="content-card">

    <h5>Facility List</h5>

    <div class="table-responsive">

        <table class="table align-middle">

            <thead>

                <tr>
                    <th>Facility</th>
                    <th>Description</th>
                    <th>Opening Hours</th>
                    <th>Status</th>
                    <th>Update</th>
                </tr>

            </thead>

            <tbody>

                <?php if (!empty($facilities)): ?>

                    <?php foreach ($facilities as $facility): ?>

                        <tr>

                            <!-- Facility Name -->
                            <td>
                                <?= e($facility['facility_name']) ?>
                            </td>

                            <!-- Description -->
                            <td>
                                <?= e($facility['description']) ?>
                            </td>

                            <!-- Opening Hours -->
                            <td>
                                <?= e($facility['opening_hours']) ?>
                            </td>

                            <!-- Facility Status -->
                            <td>

                                <?php if ($facility['status'] === 'available'): ?>

                                    <span class="badge text-bg-success">
                                        Available
                                    </span>

                                <?php elseif ($facility['status'] === 'maintenance'): ?>

                                    <span class="badge text-bg-warning">
                                        Maintenance
                                    </span>

                                <?php else: ?>

                                    <span class="badge text-bg-secondary">
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </td>

                            <!-- Update Status -->
                            <td>

                                <form
                                    method="POST"
                                    class="d-flex gap-1"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="status"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $facility['id'] ?>"
                                    >

                                    <select
                                        class="form-select form-select-sm"
                                        name="status"
                                    >

                                        <option
                                            value="available"
                                            <?= $facility['status'] === 'available' ? 'selected' : '' ?>
                                        >
                                            Available
                                        </option>

                                        <option
                                            value="maintenance"
                                            <?= $facility['status'] === 'maintenance' ? 'selected' : '' ?>
                                        >
                                            Maintenance
                                        </option>

                                        <option
                                            value="inactive"
                                            <?= $facility['status'] === 'inactive' ? 'selected' : '' ?>
                                        >
                                            Inactive
                                        </option>

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
                            colspan="5"
                            class="text-center text-secondary py-4"
                        >
                            No facilities found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require '_layout_bottom.php'; ?>
