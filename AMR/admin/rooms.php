
<?php

require_once __DIR__ . '/../config.php';

require_admin();

// ==========================================
// 1. PROCESS ROOM ACTIONS
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    // ======================================
    // 2. ADD NEW ROOM
    // ======================================

    if ($action === 'add') {

        $roomName = trim($_POST['room_name'] ?? '');
        $roomType = trim($_POST['room_type'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $capacity = (int) ($_POST['capacity'] ?? 1);
        $price = (float) ($_POST['price'] ?? 0);

        // Validate room information
        if (
            $roomName !== '' &&
            $roomType !== '' &&
            $capacity >= 1 &&
            $price > 0
        ) {

            $sql = "
                INSERT INTO rooms (
                    room_name,
                    room_type,
                    description,
                    capacity,
                    price_per_night
                )
                VALUES (?, ?, ?, ?, ?)
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $roomName,
                $roomType,
                $description,
                $capacity,
                $price
            ]);

        }
    }

    // ======================================
    // 3. UPDATE ROOM STATUS
    // ======================================

    elseif ($action === 'status') {

        $roomId = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';

        // Allowed room statuses
        $allowedStatuses = [
            'available',
            'maintenance',
            'inactive'
        ];

        if (
            $roomId > 0 &&
            in_array($status, $allowedStatuses, true)
        ) {

            $sql = "
                UPDATE rooms
                SET status = ?
                WHERE id = ?
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $status,
                $roomId
            ]);

        }
    }

    // Redirect after processing
    header('Location: rooms.php');
    exit;
}

// ==========================================
// 4. FETCH ROOM RECORDS
// ==========================================

$sql = "
    SELECT *
    FROM rooms
    ORDER BY id DESC
";

$stmt = $pdo->query($sql);

$rooms = $stmt->fetchAll();

// ==========================================
// 5. PAGE SETTINGS
// ==========================================

$adminTitle = 'Room Management';

require '_layout_top.php';

?>

<!-- ==========================================
     6. ADD ROOM FORM
========================================== -->

<div class="content-card mb-4">

    <h5>Add a Room</h5>

    <p class="text-secondary small">
        Add new rooms and configure their rates and capacity.
    </p>

    <form method="POST" class="row g-2">

        <input
            type="hidden"
            name="action"
            value="add"
        >

        <!-- Room Name -->
        <div class="col-md-3">

            <input
                type="text"
                class="form-control"
                name="room_name"
                placeholder="Room name"
                required
            >

        </div>

        <!-- Room Type -->
        <div class="col-md-2">

            <input
                type="text"
                class="form-control"
                name="room_type"
                placeholder="Room type"
                required
            >

        </div>

        <!-- Capacity -->
        <div class="col-md-1">

            <input
                type="number"
                class="form-control"
                name="capacity"
                min="1"
                placeholder="Guests"
                required
            >

        </div>

        <!-- Price Per Night -->
        <div class="col-md-2">

            <input
                type="number"
                class="form-control"
                name="price"
                step="0.01"
                min="0.01"
                placeholder="Price/night"
                required
            >

        </div>

        <!-- Description -->
        <div class="col-md-3">

            <input
                type="text"
                class="form-control"
                name="description"
                placeholder="Description"
            >

        </div>

        <!-- Add Button -->
        <div class="col-md-1">

            <button
                type="submit"
                class="btn btn-dark w-100"
            >
                Add
            </button>

        </div>

    </form>

</div>

<!-- ==========================================
     7. ROOM MANAGEMENT TABLE
========================================== -->

<div class="content-card">

    <h5>Room List</h5>

    <p class="text-secondary small">
        View room information, nightly rates, and availability.
    </p>

    <div class="table-responsive">

        <table class="table align-middle">

            <thead>

                <tr>
                    <th>Room</th>
                    <th>Type</th>
                    <th>Capacity</th>
                    <th>Rate / Night</th>
                    <th>Status</th>
                    <th>Update</th>
                </tr>

            </thead>

            <tbody>

                <?php if (!empty($rooms)): ?>

                    <?php foreach ($rooms as $room): ?>

                        <tr>

                            <!-- Room Name -->
                            <td>

                                <strong>
                                    <?= e($room['room_name']) ?>
                                </strong>

                            </td>

                            <!-- Room Type -->
                            <td>
                                <?= e($room['room_type']) ?>
                            </td>

                            <!-- Room Capacity -->
                            <td>

                                <?= (int) $room['capacity'] ?>

                                <span class="text-secondary small">
                                    guests
                                </span>

                            </td>

                            <!-- Room Rate -->
                            <td>

                                <strong>
                                    <?= money($room['price_per_night']) ?>
                                </strong>

                            </td>

                            <!-- Room Status -->
                            <td>

                                <?php if ($room['status'] === 'available'): ?>

                                    <span class="badge text-bg-success">
                                        Available
                                    </span>

                                <?php elseif ($room['status'] === 'maintenance'): ?>

                                    <span class="badge text-bg-warning">
                                        Maintenance
                                    </span>

                                <?php else: ?>

                                    <span class="badge text-bg-secondary">
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </td>

                            <!-- Update Room Status -->
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
                                        value="<?= (int) $room['id'] ?>"
                                    >

                                    <select
                                        class="form-select form-select-sm"
                                        name="status"
                                    >

                                        <option
                                            value="available"
                                            <?= $room['status'] === 'available' ? 'selected' : '' ?>
                                        >
                                            Available
                                        </option>

                                        <option
                                            value="maintenance"
                                            <?= $room['status'] === 'maintenance' ? 'selected' : '' ?>
                                        >
                                            Maintenance
                                        </option>

                                        <option
                                            value="inactive"
                                            <?= $room['status'] === 'inactive' ? 'selected' : '' ?>
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
                            colspan="6"
                            class="text-center text-secondary py-4"
                        >
                            No rooms found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require '_layout_bottom.php'; ?>
