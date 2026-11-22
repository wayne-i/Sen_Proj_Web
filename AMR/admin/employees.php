
<?php

require_once __DIR__ . '/../config.php';

require_admin();

// ==========================================
// 1. PROCESS EMPLOYEE ACTIONS
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    // ======================================
    // 2. ADD NEW EMPLOYEE
    // ======================================

    if ($action === 'add') {

        $sql = "
            INSERT INTO employees (
                full_name,
                position,
                phone,
                email
            )
            VALUES (?, ?, ?, ?)
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            trim($_POST['name'] ?? ''),
            trim($_POST['position'] ?? ''),
            trim($_POST['phone'] ?? ''),
            trim($_POST['email'] ?? '')
        ]);

    }

    // ======================================
    // 3. UPDATE EMPLOYEE STATUS
    // ======================================

    elseif ($action === 'status') {

        $status = $_POST['status'] ?? '';
        $employeeId = (int) ($_POST['id'] ?? 0);

        // Validate employment status
        if (in_array($status, ['active', 'inactive'], true)) {

            $sql = "
                UPDATE employees
                SET employment_status = ?
                WHERE id = ?
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $status,
                $employeeId
            ]);

        }
    }

    // Redirect after processing
    header('Location: employees.php');
    exit;
}

// ==========================================
// 4. FETCH EMPLOYEE RECORDS
// ==========================================

$sql = "
    SELECT *
    FROM employees
    ORDER BY full_name ASC
";

$stmt = $pdo->query($sql);

$employees = $stmt->fetchAll();

// ==========================================
// 5. PAGE SETTINGS
// ==========================================

$adminTitle = 'Employees';

require '_layout_top.php';

?>

<!-- ==========================================
     6. ADD EMPLOYEE FORM
========================================== -->

<div class="content-card mb-4">

    <h5>Add Employee</h5>

    <form method="POST" class="row g-2">

        <input
            type="hidden"
            name="action"
            value="add"
        >

        <!-- Employee Name -->
        <div class="col-md-3">

            <input
                type="text"
                class="form-control"
                name="name"
                placeholder="Full name"
                required
            >

        </div>

        <!-- Position -->
        <div class="col-md-3">

            <input
                type="text"
                class="form-control"
                name="position"
                placeholder="Position"
                required
            >

        </div>

        <!-- Phone -->
        <div class="col-md-2">

            <input
                type="text"
                class="form-control"
                name="phone"
                placeholder="Phone"
            >

        </div>

        <!-- Email -->
        <div class="col-md-3">

            <input
                type="email"
                class="form-control"
                name="email"
                placeholder="Email"
            >

        </div>

        <!-- Add Button -->
        <div class="col-md-1">

            <button type="submit" class="btn btn-dark w-100">
                Add
            </button>

        </div>

    </form>

</div>

<!-- ==========================================
     7. EMPLOYEE TABLE
========================================== -->

<div class="content-card">

    <h5>Employee List</h5>

    <div class="table-responsive">

        <table class="table align-middle">

            <thead>

                <tr>
                    <th>Name</th>
                    <th>Position</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Update</th>
                </tr>

            </thead>

            <tbody>

                <?php if (!empty($employees)): ?>

                    <?php foreach ($employees as $employee): ?>

                        <tr>

                            <!-- Employee Name -->
                            <td>
                                <?= e($employee['full_name']) ?>
                            </td>

                            <!-- Position -->
                            <td>
                                <?= e($employee['position']) ?>
                            </td>

                            <!-- Phone -->
                            <td>
                                <?= e($employee['phone']) ?>
                            </td>

                            <!-- Email -->
                            <td>
                                <?= e($employee['email']) ?>
                            </td>

                            <!-- Employment Status -->
                            <td>

                                <?php if ($employee['employment_status'] === 'active'): ?>

                                    <span class="badge text-bg-success">
                                        Active
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
                                        value="<?= (int) $employee['id'] ?>"
                                    >

                                    <select
                                        class="form-select form-select-sm"
                                        name="status"
                                    >

                                        <option
                                            value="active"
                                            <?= $employee['employment_status'] === 'active' ? 'selected' : '' ?>
                                        >
                                            Active
                                        </option>

                                        <option
                                            value="inactive"
                                            <?= $employee['employment_status'] === 'inactive' ? 'selected' : '' ?>
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

                        <td colspan="6" class="text-center text-secondary py-4">
                            No employees found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require '_layout_bottom.php'; ?>
