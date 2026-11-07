<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

session_name('aling_mameng_staff');
ini_set('session.gc_maxlifetime', '1800');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

final class ApiError extends RuntimeException
{
    public function __construct(public readonly int $httpStatus, string $message)
    {
        parent::__construct($message);
    }
}

function respond(array $body, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function fail(int $status, string $message): never
{
    throw new ApiError($status, $message);
}

function body(): array
{
    $json = file_get_contents('php://input');
    if (!is_string($json) || $json === '') {
        fail(400, 'A JSON request body is required.');
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        fail(400, 'The request body must be valid JSON.');
    }
    return $data;
}

function text(array $data, string $key, int $maxLength, bool $required = false): string
{
    $value = trim((string)($data[$key] ?? ''));
    if ($required && $value === '') {
        fail(422, 'Please complete all required fields.');
    }
    if (mb_strlen($value) > $maxLength) {
        fail(422, 'One or more fields are too long.');
    }
    return $value;
}

function positiveInt(mixed $value, string $label): int
{
    $validated = filter_var($value, FILTER_VALIDATE_INT);
    if ($validated === false || $validated < 1) {
        fail(422, $label . ' is invalid.');
    }
    return $validated;
}

function dateValue(mixed $value, string $label): string
{
    $value = (string)$value;
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$parsed || $parsed->format('Y-m-d') !== $value) {
        fail(422, $label . ' must be a valid date.');
    }
    return $value;
}

function datetimeValue(mixed $value, string $label): string
{
    $value = trim((string)$value);
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value, new DateTimeZone(APP_TIMEZONE));
    if (!$parsed || $parsed->format('Y-m-d\TH:i') !== $value) {
        fail(422, $label . ' must include a valid date and time.');
    }
    return $parsed->format('Y-m-d H:i:s');
}

function positiveMoney(mixed $value, string $label, bool $allowZero = false): string
{
    if (!is_numeric($value)) {
        fail(422, $label . ' must be a valid amount.');
    }
    $amount = round((float)$value, 2);
    if (!is_finite($amount) || $amount < 0 || (!$allowZero && $amount <= 0)) {
        fail(422, $label . ' must be ' . ($allowZero ? 'zero or more.' : 'greater than zero.'));
    }
    return number_format($amount, 2, '.', '');
}

function currentUser(): array
{
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        fail(401, 'Please sign in to continue.');
    }
    if (isset($_SESSION['last_activity']) && time() - (int)$_SESSION['last_activity'] > 1800) {
        $_SESSION = [];
        session_destroy();
        fail(401, 'Your session expired. Please sign in again.');
    }
    $account = execute(
        'SELECT id, name, email, role, active FROM users WHERE id=:id',
        ['id' => $_SESSION['user']['id']]
    )->fetch();
    if (!$account || !(bool)$account['active']) {
        $_SESSION = [];
        session_destroy();
        fail(401, 'This staff account is inactive. Please contact an administrator.');
    }
    $_SESSION['user'] = [
        'id' => (int)$account['id'],
        'name' => $account['name'],
        'email' => $account['email'],
        'role' => $account['role'],
    ];
    $_SESSION['last_activity'] = time();
    return $_SESSION['user'];
}

function requireAdmin(): array
{
    $user = currentUser();
    if ($user['role'] !== 'admin') {
        fail(403, 'Administrator access is required for this action.');
    }
    return $user;
}

function requireCsrf(): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!is_string($provided) || !is_string($expected) || $expected === '' || !hash_equals($expected, $provided)) {
        fail(419, 'Your session token expired. Refresh the page and try again.');
    }
}

function logActivity(?int $userId, string $action, string $entity, ?int $entityId, array $details = []): void
{
    $statement = db()->prepare(
        'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address)
         VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip_address)'
    );
    $statement->execute([
        'user_id' => $userId,
        'action' => $action,
        'entity_type' => $entity,
        'entity_id' => $entityId,
        'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function execute(string $sql, array $parameters = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    return $statement;
}

function requireFields(array $data, array $keys): void
{
    foreach ($keys as $key) {
        if (!isset($data[$key]) || trim((string)$data[$key]) === '') {
            fail(422, 'Please complete all required fields.');
        }
    }
}

function countRows(string $sql): int
{
    return (int)db()->query($sql)->fetchColumn();
}

function dashboardData(): array
{
    $rooms = db()->query("SELECT status, COUNT(*) AS total FROM rooms WHERE deleted_at IS NULL GROUP BY status")->fetchAll();
    $roomCounts = ['Available' => 0, 'Occupied' => 0, 'Under Maintenance' => 0, 'Cleaning' => 0];
    foreach ($rooms as $row) {
        $roomCounts[$row['status']] = (int)$row['total'];
    }
    $today = date('Y-m-d');
    $checkIns = (int)execute(
        "SELECT COUNT(*) FROM check_ins WHERE DATE(checked_in_at) = :today",
        ['today' => $today]
    )->fetchColumn();
    $checkOuts = (int)execute('SELECT COUNT(*) FROM check_outs WHERE DATE(checked_out_at) = :today', ['today' => $today])->fetchColumn();
    $guestCount = countRows('SELECT COUNT(*) FROM guests WHERE deleted_at IS NULL');
    $income = (float)execute(
        "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'Paid' AND DATE(paid_at) = :today",
        ['today' => $today]
    )->fetchColumn();
    $revenueByDay = db()->query(
        "SELECT DATE(paid_at) AS day, SUM(amount) AS total
         FROM payments WHERE status = 'Paid' AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(paid_at) ORDER BY day"
    )->fetchAll();

    return [
        'rooms' => $roomCounts,
        'totalRooms' => array_sum($roomCounts),
        'totalGuests' => $guestCount,
        'checkInsToday' => $checkIns,
        'checkOutsToday' => $checkOuts,
        'revenueToday' => $income,
        'weeklyRevenue' => $revenueByDay,
    ];
}

function applicationData(): array
{
    $roomRows = db()->query(
        'SELECT r.id, r.room_number AS number, r.room_type_id AS roomTypeId, t.name AS type,
                r.nightly_rate AS rate, r.status, r.created_at AS createdAt
         FROM rooms r JOIN room_types t ON t.id = r.room_type_id
         WHERE r.deleted_at IS NULL ORDER BY r.room_number'
    )->fetchAll();

    $guests = db()->query(
        'SELECT id, guest_code AS guestCode, full_name AS fullName, phone, email, address,
                id_type AS idType, id_number AS idNumber, created_at AS createdAt
         FROM guests WHERE deleted_at IS NULL ORDER BY full_name'
    )->fetchAll();

    $stays = db()->query(
        "SELECT ci.id, ci.checkin_code AS checkinCode, ci.guest_id AS guestId,
                g.guest_code AS guestCode, g.full_name AS guestName, g.phone, g.email,
                ci.room_id AS roomId, r.room_number AS roomNumber, rt.name AS roomType,
                ci.checked_in_at AS checkedInAt, ci.expected_checkout AS expectedCheckout,
                ci.actual_checkout_at AS actualCheckoutAt, ci.guest_count AS guestCount,
                ci.nightly_rate AS nightlyRate, ci.expected_room_charge AS expectedRoomCharge,
                ci.status, ci.notes,
                COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.checkin_id = ci.id AND p.status = 'Paid'), 0) AS paidAmount,
                co.total_charge AS finalCharge, co.additional_charges AS additionalCharges
         FROM check_ins ci JOIN guests g ON g.id = ci.guest_id
         JOIN rooms r ON r.id = ci.room_id JOIN room_types rt ON rt.id = r.room_type_id
         LEFT JOIN check_outs co ON co.checkin_id = ci.id
         ORDER BY ci.checked_in_at DESC"
    )->fetchAll();

    $employees = db()->query(
        'SELECT e.id, e.employee_code AS employeeCode, e.full_name AS fullName, e.email,
                e.phone, e.position, e.hire_date AS hireDate, e.active,
                u.id AS userId, u.role AS role
         FROM employees e LEFT JOIN users u ON u.id = e.user_id
         WHERE e.deleted_at IS NULL ORDER BY e.full_name'
    )->fetchAll();

    $payments = db()->query(
        'SELECT p.id, p.receipt_code AS receiptCode, p.checkin_id AS checkinId,
                p.amount, p.method, p.status, p.notes, p.paid_at AS paidAt,
                g.full_name AS guestName, ci.checkin_code AS checkinCode, u.name AS receivedBy
         FROM payments p JOIN check_ins ci ON ci.id = p.checkin_id
         JOIN guests g ON g.id = ci.guest_id JOIN users u ON u.id = p.received_by
         ORDER BY p.paid_at DESC LIMIT 300'
    )->fetchAll();

    $expenses = db()->query(
        'SELECT e.id, e.category, e.description, e.amount, e.expense_date AS expenseDate,
                e.receipt_reference AS receiptReference, u.name AS createdBy
         FROM expenses e JOIN users u ON u.id = e.created_by
         WHERE e.deleted_at IS NULL ORDER BY e.expense_date DESC, e.id DESC LIMIT 300'
    )->fetchAll();

    $activity = db()->query(
        'SELECT a.id, a.action, a.entity_type AS entityType, a.entity_id AS entityId,
                a.details, a.created_at AS createdAt, u.name AS userName
         FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id
         ORDER BY a.created_at DESC LIMIT 30'
    )->fetchAll();
    foreach ($activity as &$entry) {
        $entry['details'] = $entry['details'] ? json_decode($entry['details'], true) : [];
    }
    unset($entry);

    $typeRows = db()->query('SELECT id, name, description FROM room_types ORDER BY name')->fetchAll();
    $employeesMayManage = ($_SESSION['user']['role'] ?? '') === 'admin';

    return [
        'user' => $_SESSION['user'],
        'csrfToken' => $_SESSION['csrf_token'],
        'dashboard' => dashboardData(),
        'rooms' => $roomRows,
        'roomTypes' => $typeRows,
        'guests' => $guests,
        'stays' => $stays,
        'employees' => $employeesMayManage ? $employees : [],
        'payments' => $payments,
        'expenses' => $expenses,
        'activity' => $employeesMayManage ? $activity : [],
        'permissions' => ['manageEmployees' => $employeesMayManage],
    ];
}

function validRole(mixed $value): string
{
    if (!in_array($value, ['admin', 'staff'], true)) {
        fail(422, 'Choose a valid staff role.');
    }
    return $value;
}

function validRoomStatus(mixed $value): string
{
    $allowed = ['Available', 'Under Maintenance', 'Cleaning'];
    if (!in_array($value, $allowed, true)) {
        fail(422, 'Choose Available, Under Maintenance, or Cleaning. Occupied rooms are set by check-in.');
    }
    return $value;
}

function validPaymentMethod(mixed $value): string
{
    if (!in_array($value, ['Cash', 'GCash', 'Card'], true)) {
        fail(422, 'Choose Cash, GCash, or Card.');
    }
    return $value;
}

function preventLastAdminChange(?int $targetUserId, string $nextRole, bool $nextActive, int $actorId): void
{
    if ($targetUserId === null) return;
    if ($targetUserId === $actorId && !$nextActive) {
        fail(409, 'You cannot deactivate your own account while signed in.');
    }
    $current = execute('SELECT role, active FROM users WHERE id=:id FOR UPDATE', ['id' => $targetUserId])->fetch();
    if (!$current || $current['role'] !== 'admin' || !(bool)$current['active']) return;
    if ($nextRole === 'admin' && $nextActive) return;
    if ((int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin' AND active=1")->fetchColumn() <= 1) {
        fail(409, 'The last active administrator cannot be removed or deactivated.');
    }
}

function generateCode(string $prefix): string
{
    return $prefix . '-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function beginTransaction(callable $callback): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $callback($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function checkAvailability(PDO $pdo, int $roomId, string $checkIn, string $checkOut, ?int $excludeId = null): void
{
    $sql = "SELECT COUNT(*) FROM check_ins
            WHERE room_id = :room_id AND status = 'Checked-in'
            AND DATE(checked_in_at) < :check_out AND expected_checkout > :check_in";
    $params = ['room_id' => $roomId, 'check_in' => $checkIn, 'check_out' => $checkOut];
    if ($excludeId !== null) {
        $sql .= ' AND id <> :exclude_id';
        $params['exclude_id'] = $excludeId;
    }
    if ((int)execute($sql, $params)->fetchColumn() > 0) {
        fail(409, 'The selected room overlaps an active stay.');
    }
}

function reportData(array $query): array
{
    $from = dateValue($query['from'] ?? date('Y-m-01'), 'Start date');
    $to = dateValue($query['to'] ?? date('Y-m-d'), 'End date');
    if ($from > $to) {
        fail(422, 'The report start date must be on or before its end date.');
    }
    $type = (string)($query['type'] ?? 'revenue');
    if ($type === 'revenue') {
        $rows = execute(
            "SELECT DATE(paid_at) AS report_date, method,
                    SUM(amount) AS total, COUNT(*) AS transaction_count
             FROM payments WHERE status = 'Paid' AND DATE(paid_at) BETWEEN :from_date AND :to_date
             GROUP BY DATE(paid_at), method ORDER BY report_date",
            ['from_date' => $from, 'to_date' => $to]
        )->fetchAll();
    } elseif ($type === 'occupancy') {
        $rows = execute(
            "SELECT DATE(ci.checked_in_at) AS report_date, r.room_number,
                    rt.name AS room_type, g.full_name AS guest_name,
                    ci.expected_checkout, ci.status
             FROM check_ins ci JOIN rooms r ON r.id = ci.room_id
             JOIN room_types rt ON rt.id = r.room_type_id JOIN guests g ON g.id = ci.guest_id
             WHERE DATE(ci.checked_in_at) BETWEEN :from_date AND :to_date
             ORDER BY ci.checked_in_at DESC",
            ['from_date' => $from, 'to_date' => $to]
        )->fetchAll();
    } elseif ($type === 'guests') {
        $rows = execute(
            "SELECT g.guest_code, g.full_name, g.phone, g.email,
                    COUNT(ci.id) AS stays, MAX(ci.checked_in_at) AS last_checkin
             FROM guests g LEFT JOIN check_ins ci ON ci.guest_id = g.id
             WHERE g.deleted_at IS NULL AND (ci.id IS NULL OR DATE(ci.checked_in_at) BETWEEN :from_date AND :to_date)
             GROUP BY g.id, g.guest_code, g.full_name, g.phone, g.email ORDER BY g.full_name",
            ['from_date' => $from, 'to_date' => $to]
        )->fetchAll();
    } elseif ($type === 'stays') {
        $rows = execute(
            "SELECT ci.checkin_code, g.full_name AS guest_name, r.room_number,
                    ci.checked_in_at, ci.expected_checkout, ci.actual_checkout_at, ci.status
             FROM check_ins ci JOIN guests g ON g.id = ci.guest_id JOIN rooms r ON r.id = ci.room_id
             WHERE DATE(ci.checked_in_at) BETWEEN :from_date AND :to_date
             ORDER BY ci.checked_in_at DESC",
            ['from_date' => $from, 'to_date' => $to]
        )->fetchAll();
    } elseif ($type === 'payments') {
        $rows = execute(
            "SELECT p.receipt_code, p.paid_at, g.full_name AS guest_name,
                    p.method, p.status, p.amount, u.name AS received_by
             FROM payments p JOIN check_ins ci ON ci.id = p.checkin_id
             JOIN guests g ON g.id = ci.guest_id JOIN users u ON u.id = p.received_by
             WHERE DATE(p.paid_at) BETWEEN :from_date AND :to_date
             ORDER BY p.paid_at DESC",
            ['from_date' => $from, 'to_date' => $to]
        )->fetchAll();
    } elseif ($type === 'expenses') {
        $rows = execute(
            'SELECT expense_date, category, description, amount, receipt_reference
             FROM expenses WHERE deleted_at IS NULL AND expense_date BETWEEN :from_date AND :to_date
             ORDER BY expense_date DESC, id DESC',
            ['from_date' => $from, 'to_date' => $to]
        )->fetchAll();
    } elseif ($type === 'activity') {
        requireAdmin();
        $rows = execute(
            "SELECT a.action, a.entity_type, a.entity_id, a.details, a.created_at, u.name AS user_name
             FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id
             WHERE DATE(a.created_at) BETWEEN :from_date AND :to_date
             ORDER BY a.created_at DESC",
            ['from_date' => $from, 'to_date' => $to]
        )->fetchAll();
    } else {
        fail(422, 'Choose a valid report.');
    }
    return ['type' => $type, 'from' => $from, 'to' => $to, 'rows' => $rows];
}

try {
    $action = (string)($_GET['action'] ?? '');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrfFreeActions = ['bootstrap', 'setup', 'login'];
    if ($method !== 'GET' && !in_array($action, $csrfFreeActions, true)) {
        requireCsrf();
    }

    if ($action === 'bootstrap' && $method === 'GET') {
        $userCount = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $result = ['setupRequired' => $userCount === 0, 'csrfToken' => $_SESSION['csrf_token']];
        if (isset($_SESSION['user'])) {
            currentUser();
            $result['data'] = applicationData();
        }
        respond($result);
    }

    if ($action === 'setup' && $method === 'POST') {
        $input = body();
        requireFields($input, ['name', 'email', 'password']);
        $name = text($input, 'name', 120, true);
        $email = strtolower(text($input, 'email', 190, true));
        $password = (string)$input['password'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            fail(422, 'Enter a valid email address and a password of at least 12 characters.');
        }
        $pdo = db();
        if ((int)$pdo->query("SELECT GET_LOCK('aling_mameng_initial_admin', 5)")->fetchColumn() !== 1) {
            fail(503, 'Administrator setup is busy. Please try again.');
        }
        try {
            $user = beginTransaction(function (PDO $pdo) use ($name, $email, $password): array {
                if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) {
                    fail(409, 'Initial administrator setup has already been completed.');
                }
                execute(
                    "INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, 'admin')",
                    ['name' => $name, 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]
                );
                $id = (int)$pdo->lastInsertId();
                logActivity($id, 'Initial administrator created', 'user', $id);
                return ['id' => $id, 'name' => $name, 'email' => $email, 'role' => 'admin'];
            });
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('aling_mameng_initial_admin')");
        }
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        respond(['user' => $user, 'csrfToken' => $_SESSION['csrf_token']], 201);
    }

    if ($action === 'login' && $method === 'POST') {
        $input = body();
        $email = strtolower(text($input, 'email', 190, true));
        $password = (string)($input['password'] ?? '');
        $identifier = hash('sha256', $email . "\0" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $loginResult = beginTransaction(function (PDO $pdo) use ($email, $password, $identifier): array {
            execute(
                'INSERT IGNORE INTO login_attempts (identifier, attempts, window_started) VALUES (:identifier, 0, NOW())',
                ['identifier' => $identifier]
            );
            $attempt = execute('SELECT attempts, window_started, locked_until FROM login_attempts WHERE identifier=:identifier FOR UPDATE', ['identifier' => $identifier])->fetch();
            if ($attempt['locked_until'] && strtotime($attempt['locked_until']) > time()) {
                return ['locked' => true];
            }
            $account = execute(
                'SELECT id, name, email, password_hash, role, active FROM users WHERE email = :email LIMIT 1',
                ['email' => $email]
            )->fetch();
            $passwordValid = password_verify(
                $password,
                $account['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi.'
            );
            if (!$account || !(bool)$account['active'] || !$passwordValid) {
                $windowExpired = strtotime($attempt['window_started']) < time() - 900;
                $attempts = $windowExpired ? 1 : (int)$attempt['attempts'] + 1;
                $lockedUntil = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
                execute(
                    'UPDATE login_attempts SET attempts=:attempts, window_started=:window_started, locked_until=:locked_until WHERE identifier=:identifier',
                    [
                        'attempts' => $attempts,
                        'window_started' => $windowExpired ? date('Y-m-d H:i:s') : $attempt['window_started'],
                        'locked_until' => $lockedUntil,
                        'identifier' => $identifier,
                    ]
                );
                return ['invalid' => true, 'locked' => $attempts >= 5];
            }
            execute('DELETE FROM login_attempts WHERE identifier=:identifier', ['identifier' => $identifier]);
            return ['account' => $account];
        });
        if (!empty($loginResult['locked'])) fail(429, 'Too many sign-in attempts. Wait 15 minutes and try again.');
        $account = $loginResult['account'] ?? null;
        if (!$account) {
            fail(401, 'Email or password is incorrect, or this account is inactive.');
        }
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int)$account['id'],
            'name' => $account['name'],
            'email' => $account['email'],
            'role' => $account['role'],
        ];
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        logActivity((int)$account['id'], 'Signed in', 'user', (int)$account['id']);
        respond(['user' => $_SESSION['user'], 'csrfToken' => $_SESSION['csrf_token']]);
    }

    if ($action === 'logout' && $method === 'POST') {
        $user = currentUser();
        logActivity((int)$user['id'], 'Signed out', 'user', (int)$user['id']);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], '', $params['secure'], $params['httponly']);
        }
        session_destroy();
        respond(['ok' => true]);
    }

    if ($action === 'data' && $method === 'GET') {
        currentUser();
        respond(['data' => applicationData()]);
    }

    if ($action === 'reports' && $method === 'GET') {
        currentUser();
        respond(['report' => reportData($_GET)]);
    }

    if ($action === 'room_types' && $method === 'GET') {
        currentUser();
        respond(['roomTypes' => db()->query('SELECT id, name, description FROM room_types ORDER BY name')->fetchAll()]);
    }

    if ($action === 'room_save' && $method === 'POST') {
        $user = currentUser();
        $input = body();
        requireFields($input, ['number', 'typeId', 'rate', 'status']);
        $id = isset($input['id']) && $input['id'] !== '' ? positiveInt($input['id'], 'Room') : null;
        $number = text($input, 'number', 20, true);
        $typeId = positiveInt($input['typeId'], 'Room type');
        $rate = positiveMoney($input['rate'], 'Nightly rate', true);
        $status = validRoomStatus($input['status']);
        if (!execute('SELECT id FROM room_types WHERE id = :id', ['id' => $typeId])->fetchColumn()) {
            fail(422, 'Select a valid room type.');
        }
        if ($id) {
            $current = execute('SELECT status FROM rooms WHERE id = :id AND deleted_at IS NULL', ['id' => $id])->fetch();
            if (!$current) fail(404, 'Room not found.');
            if ($current['status'] === 'Occupied') fail(409, 'An occupied room cannot be edited until check-out.');
            if ($current['status'] === 'Cleaning' && $status === 'Available') {
                fail(409, 'Use the room Ready action to complete cleaning before setting this room to Available.');
            }
            execute(
                'UPDATE rooms SET room_number=:number, room_type_id=:type_id, nightly_rate=:rate, status=:status WHERE id=:id',
                ['number' => $number, 'type_id' => $typeId, 'rate' => $rate, 'status' => $status, 'id' => $id]
            );
            $roomId = $id;
            $logAction = 'Updated room';
        } else {
            execute(
                'INSERT INTO rooms (room_number, room_type_id, nightly_rate, status) VALUES (:number, :type_id, :rate, :status)',
                ['number' => $number, 'type_id' => $typeId, 'rate' => $rate, 'status' => $status]
            );
            $roomId = (int)db()->lastInsertId();
            $logAction = 'Added room';
        }
        logActivity((int)$user['id'], $logAction, 'room', $roomId, ['number' => $number]);
        respond(['id' => $roomId, 'message' => 'Room saved.']);
    }

    if ($action === 'room_delete' && $method === 'POST') {
        $user = currentUser();
        $input = body();
        $id = positiveInt($input['id'] ?? null, 'Room');
        beginTransaction(function () use ($id, $user): void {
            $room = execute('SELECT room_number, status FROM rooms WHERE id=:id AND deleted_at IS NULL FOR UPDATE', ['id' => $id])->fetch();
            if (!$room) fail(404, 'Room not found.');
            if ($room['status'] === 'Occupied') fail(409, 'Occupied rooms cannot be deleted.');
            execute('UPDATE rooms SET deleted_at=NOW() WHERE id=:id', ['id' => $id]);
            logActivity((int)$user['id'], 'Archived room', 'room', $id, ['number' => $room['room_number']]);
        });
        respond(['ok' => true]);
    }

    if ($action === 'room_clean' && $method === 'POST') {
        $user = currentUser();
        $input = body();
        $id = positiveInt($input['id'] ?? null, 'Room');
        $changed = execute("UPDATE rooms SET status='Available' WHERE id=:id AND status='Cleaning' AND deleted_at IS NULL", ['id' => $id])->rowCount();
        if (!$changed) fail(409, 'Only rooms marked Cleaning can be set to Available.');
        logActivity((int)$user['id'], 'Marked room ready', 'room', $id);
        respond(['ok' => true]);
    }

    if ($action === 'guest_save' && $method === 'POST') {
        $user = currentUser();
        $input = body();
        requireFields($input, ['fullName', 'phone']);
        $id = isset($input['id']) && $input['id'] !== '' ? positiveInt($input['id'], 'Guest') : null;
        $fields = [
            'full_name' => text($input, 'fullName', 120, true),
            'phone' => text($input, 'phone', 30, true),
            'email' => text($input, 'email', 190),
            'address' => text($input, 'address', 500),
            'id_type' => text($input, 'idType', 60),
            'id_number' => text($input, 'idNumber', 100),
        ];
        if ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) fail(422, 'Enter a valid guest email.');
        if ($id) {
            $exists = execute('SELECT id FROM guests WHERE id=:id AND deleted_at IS NULL', ['id' => $id])->fetchColumn();
            if (!$exists) fail(404, 'Guest not found.');
            execute(
                'UPDATE guests SET full_name=:full_name, phone=:phone, email=:email, address=:address, id_type=:id_type, id_number=:id_number WHERE id=:id',
                [...$fields, 'id' => $id]
            );
            $guestId = $id;
            $actionName = 'Updated guest';
        } else {
            $guestCode = generateCode('GST');
            execute(
                'INSERT INTO guests (guest_code, full_name, phone, email, address, id_type, id_number)
                 VALUES (:guest_code, :full_name, :phone, :email, :address, :id_type, :id_number)',
                ['guest_code' => $guestCode, ...$fields]
            );
            $guestId = (int)db()->lastInsertId();
            $actionName = 'Registered guest';
        }
        logActivity((int)$user['id'], $actionName, 'guest', $guestId, ['name' => $fields['full_name']]);
        respond(['id' => $guestId, 'message' => 'Guest saved.']);
    }

    if ($action === 'guest_delete' && $method === 'POST') {
        $user = currentUser();
        $input = body();
        $id = positiveInt($input['id'] ?? null, 'Guest');
        beginTransaction(function () use ($id, $user): void {
            $guest = execute('SELECT full_name FROM guests WHERE id=:id AND deleted_at IS NULL FOR UPDATE', ['id' => $id])->fetch();
            if (!$guest) fail(404, 'Guest not found.');
            $activeStay = execute("SELECT id FROM check_ins WHERE guest_id=:id AND status='Checked-in' LIMIT 1", ['id' => $id])->fetchColumn();
            if ($activeStay) fail(409, 'A guest with an active stay cannot be archived.');
            execute('UPDATE guests SET deleted_at=NOW() WHERE id=:id', ['id' => $id]);
            logActivity((int)$user['id'], 'Archived guest', 'guest', $id);
        });
        respond(['ok' => true]);
    }

    if ($action === 'checkin' && $method === 'POST') {
        $user = currentUser();
        $input = body();
        requireFields($input, ['roomId', 'checkedInAt', 'expectedCheckout', 'guestCount']);
        $roomId = positiveInt($input['roomId'], 'Room');
        $checkedInAt = datetimeValue($input['checkedInAt'], 'Check-in time');
        $expectedCheckout = dateValue($input['expectedCheckout'], 'Expected check-out');
        $guestCount = positiveInt($input['guestCount'], 'Guest count');
        $checkInDate = substr($checkedInAt, 0, 10);
        if ($expectedCheckout < $checkInDate) fail(422, 'Expected check-out cannot be before check-in.');
        if ($checkedInAt > date('Y-m-d H:i:s')) fail(422, 'Check-in time cannot be in the future.');
        $notes = text($input, 'notes', 2000);

        $result = beginTransaction(function (PDO $pdo) use ($input, $user, $roomId, $checkedInAt, $expectedCheckout, $checkInDate, $guestCount, $notes): array {
            $room = execute(
                "SELECT id, nightly_rate, status FROM rooms WHERE id=:id AND deleted_at IS NULL FOR UPDATE",
                ['id' => $roomId]
            )->fetch();
            if (!$room || $room['status'] !== 'Available') fail(409, 'Choose an available room. Occupied or unavailable rooms cannot be assigned.');
            checkAvailability($pdo, $roomId, $checkInDate, $expectedCheckout);

            if (!empty($input['guestId'])) {
                $guestId = positiveInt($input['guestId'], 'Guest');
                if (!execute('SELECT id FROM guests WHERE id=:id AND deleted_at IS NULL', ['id' => $guestId])->fetchColumn()) {
                    fail(404, 'Guest not found.');
                }
            } else {
                requireFields($input, ['guestName', 'guestPhone']);
                $email = text($input, 'guestEmail', 190);
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422, 'Enter a valid guest email.');
                execute(
                    'INSERT INTO guests (guest_code, full_name, phone, email, address, id_type, id_number)
                     VALUES (:code, :name, :phone, :email, :address, :id_type, :id_number)',
                    [
                        'code' => generateCode('GST'),
                        'name' => text($input, 'guestName', 120, true),
                        'phone' => text($input, 'guestPhone', 30, true),
                        'email' => $email,
                        'address' => text($input, 'guestAddress', 500),
                        'id_type' => text($input, 'guestIdType', 60),
                        'id_number' => text($input, 'guestIdNumber', 100),
                    ]
                );
                $guestId = (int)$pdo->lastInsertId();
            }

            $nights = max(1, (int)date_diff(new DateTimeImmutable($checkInDate), new DateTimeImmutable($expectedCheckout))->days);
            $rate = (float)$room['nightly_rate'];
            execute(
                'INSERT INTO check_ins
                 (checkin_code, guest_id, room_id, checked_in_at, expected_checkout, guest_count,
                  nightly_rate, expected_room_charge, status, notes, created_by)
                 VALUES (:code, :guest_id, :room_id, :checked_in_at, :expected_checkout, :guest_count,
                         :nightly_rate, :charge, "Checked-in", :notes, :created_by)',
                [
                    'code' => generateCode('CHK'),
                    'guest_id' => $guestId,
                    'room_id' => $roomId,
                    'checked_in_at' => $checkedInAt,
                    'expected_checkout' => $expectedCheckout,
                    'guest_count' => $guestCount,
                    'nightly_rate' => number_format($rate, 2, '.', ''),
                    'charge' => number_format($rate * $nights, 2, '.', ''),
                    'notes' => $notes,
                    'created_by' => $user['id'],
                ]
            );
            $checkinId = (int)$pdo->lastInsertId();
            execute("UPDATE rooms SET status='Occupied' WHERE id=:id", ['id' => $roomId]);
            logActivity((int)$user['id'], 'Checked in guest', 'check_in', $checkinId);
            return ['id' => $checkinId, 'message' => 'Guest checked in and room marked Occupied.'];
        });
        respond($result, 201);
    }

    if ($action === 'checkout' && $method === 'POST') {
        $user = currentUser();
        $input = body();
        $checkinId = positiveInt($input['checkinId'] ?? null, 'Check-in');
        $additional = positiveMoney($input['additionalCharges'] ?? 0, 'Additional charges', true);
        $paymentAmount = positiveMoney($input['paymentAmount'] ?? 0, 'Payment amount', true);
        $methodName = (string)($input['paymentMethod'] ?? '');
        if ((float)$paymentAmount > 0) $methodName = validPaymentMethod($methodName);

        $result = beginTransaction(function (PDO $pdo) use ($user, $checkinId, $additional, $paymentAmount, $methodName, $input): array {
            $stay = execute(
                "SELECT ci.*, r.status AS room_status FROM check_ins ci
                 JOIN rooms r ON r.id=ci.room_id WHERE ci.id=:id FOR UPDATE",
                ['id' => $checkinId]
            )->fetch();
            if (!$stay || $stay['status'] !== 'Checked-in') fail(409, 'This guest is not currently checked in.');
            $room = execute('SELECT id, room_number FROM rooms WHERE id=:id FOR UPDATE', ['id' => $stay['room_id']])->fetch();
            $checkoutDate = date('Y-m-d');
            $nights = max(1, (int)date_diff(new DateTimeImmutable(substr($stay['checked_in_at'], 0, 10)), new DateTimeImmutable(max($checkoutDate, substr($stay['checked_in_at'], 0, 10))))->days);
            $roomCharge = round((float)$stay['nightly_rate'] * $nights, 2);
            $total = round($roomCharge + (float)$additional, 2);
            $alreadyPaid = (float)execute(
                "SELECT COALESCE(SUM(amount),0) FROM payments WHERE checkin_id=:id AND status='Paid'",
                ['id' => $checkinId]
            )->fetchColumn();
            if ($alreadyPaid > $total + 0.001) {
                fail(409, 'Payments already exceed the final stay charge. Resolve the overpayment before checkout.');
            }
            $remaining = round($total - $alreadyPaid, 2);
            if ((float)$paymentAmount > $remaining + 0.001) fail(422, 'Payment cannot exceed the remaining balance.');
            $checkedOutAt = date('Y-m-d H:i:s');
            $notes = text($input, 'notes', 2000);
            execute(
                'INSERT INTO check_outs (checkin_id, checked_out_at, nights_charged, room_charge, additional_charges, total_charge, notes, processed_by)
                 VALUES (:checkin_id, :checked_out_at, :nights, :room_charge, :additional, :total, :notes, :user_id)',
                [
                    'checkin_id' => $checkinId,
                    'checked_out_at' => $checkedOutAt,
                    'nights' => $nights,
                    'room_charge' => number_format($roomCharge, 2, '.', ''),
                    'additional' => $additional,
                    'total' => number_format($total, 2, '.', ''),
                    'notes' => $notes,
                    'user_id' => $user['id'],
                ]
            );
            execute("UPDATE check_ins SET status='Checked-out', actual_checkout_at=:date WHERE id=:id", ['date' => $checkedOutAt, 'id' => $checkinId]);
            execute("UPDATE rooms SET status='Cleaning' WHERE id=:id", ['id' => $room['id']]);
            if ((float)$paymentAmount > 0) {
                execute(
                    'INSERT INTO payments (receipt_code, checkin_id, amount, method, status, notes, received_by)
                     VALUES (:code, :checkin_id, :amount, :method, "Paid", :notes, :user_id)',
                    [
                        'code' => generateCode('RCP'),
                        'checkin_id' => $checkinId,
                        'amount' => $paymentAmount,
                        'method' => $methodName,
                        'notes' => 'Checkout payment',
                        'user_id' => $user['id'],
                    ]
                );
            }
            logActivity((int)$user['id'], 'Checked out guest', 'check_out', $checkinId, ['total' => $total, 'room' => $room['room_number']]);
            return [
                'message' => 'Check-out completed. Room marked Cleaning.',
                'nights' => $nights,
                'roomCharge' => $roomCharge,
                'additionalCharges' => (float)$additional,
                'total' => $total,
                'paid' => $alreadyPaid + (float)$paymentAmount,
                'balance' => round($remaining - (float)$paymentAmount, 2),
            ];
        });
        respond($result);
    }

    if ($action === 'payment' && $method === 'POST') {
        $user = currentUser();
        $input = body();
        requireFields($input, ['checkinId', 'amount', 'method']);
        $checkinId = positiveInt($input['checkinId'], 'Check-in');
        $amount = positiveMoney($input['amount'], 'Payment');
        $paymentMethod = validPaymentMethod($input['method']);
        $receipt = beginTransaction(function (PDO $pdo) use ($user, $checkinId, $amount, $paymentMethod, $input): string {
            $stay = execute(
                'SELECT ci.expected_room_charge, co.total_charge
                 FROM check_ins ci LEFT JOIN check_outs co ON co.checkin_id=ci.id
                 WHERE ci.id=:id FOR UPDATE',
                ['id' => $checkinId]
            )->fetch();
            if (!$stay) fail(404, 'Check-in record not found.');
            $paid = (float)execute(
                "SELECT COALESCE(SUM(amount),0) FROM payments WHERE checkin_id=:id AND status='Paid'",
                ['id' => $checkinId]
            )->fetchColumn();
            $charge = $stay['total_charge'] !== null ? (float)$stay['total_charge'] : (float)$stay['expected_room_charge'];
            if ($paid + (float)$amount > $charge + 0.001) fail(422, 'Payment exceeds the current room-charge balance.');
            $receiptCode = generateCode('RCP');
            execute(
                'INSERT INTO payments (receipt_code, checkin_id, amount, method, status, notes, received_by)
                 VALUES (:code, :checkin_id, :amount, :method, "Paid", :notes, :user_id)',
                [
                    'code' => $receiptCode, 'checkin_id' => $checkinId, 'amount' => $amount,
                    'method' => $paymentMethod, 'notes' => text($input, 'notes', 500), 'user_id' => $user['id'],
                ]
            );
            logActivity((int)$user['id'], 'Recorded payment', 'payment', (int)$pdo->lastInsertId(), ['receipt' => $receiptCode]);
            return $receiptCode;
        });
        respond(['receiptCode' => $receipt, 'message' => 'Payment recorded.'], 201);
    }

    if ($action === 'refund' && $method === 'POST') {
        $user = requireAdmin();
        $input = body();
        $id = positiveInt($input['id'] ?? null, 'Payment');
        $updated = execute("UPDATE payments SET status='Refunded' WHERE id=:id AND status='Paid'", ['id' => $id])->rowCount();
        if (!$updated) fail(409, 'Only paid transactions can be refunded.');
        logActivity((int)$user['id'], 'Refunded payment', 'payment', $id);
        respond(['ok' => true]);
    }

    if ($action === 'expense_save' && $method === 'POST') {
        $user = currentUser();
        $input = body();
        requireFields($input, ['category', 'description', 'amount', 'expenseDate']);
        $id = isset($input['id']) && $input['id'] !== '' ? positiveInt($input['id'], 'Expense') : null;
        $record = [
            'category' => text($input, 'category', 100, true),
            'description' => text($input, 'description', 500, true),
            'amount' => positiveMoney($input['amount'], 'Expense amount'),
            'expense_date' => dateValue($input['expenseDate'], 'Expense date'),
            'receipt_reference' => text($input, 'receiptReference', 100),
        ];
        if ($id) {
            execute(
                'UPDATE expenses SET category=:category, description=:description, amount=:amount,
                 expense_date=:expense_date, receipt_reference=:receipt_reference
                 WHERE id=:id AND deleted_at IS NULL',
                [...$record, 'id' => $id]
            );
            $expenseId = $id;
            $logAction = 'Updated expense';
        } else {
            execute(
                'INSERT INTO expenses (category, description, amount, expense_date, receipt_reference, created_by)
                 VALUES (:category, :description, :amount, :expense_date, :receipt_reference, :created_by)',
                [...$record, 'created_by' => $user['id']]
            );
            $expenseId = (int)db()->lastInsertId();
            $logAction = 'Added expense';
        }
        logActivity((int)$user['id'], $logAction, 'expense', $expenseId);
        respond(['id' => $expenseId, 'message' => 'Expense saved.']);
    }

    if ($action === 'expense_delete' && $method === 'POST') {
        $user = currentUser();
        $input = body();
        $id = positiveInt($input['id'] ?? null, 'Expense');
        $updated = execute('UPDATE expenses SET deleted_at=NOW() WHERE id=:id AND deleted_at IS NULL', ['id' => $id])->rowCount();
        if (!$updated) fail(404, 'Expense not found.');
        logActivity((int)$user['id'], 'Archived expense', 'expense', $id);
        respond(['ok' => true]);
    }

    if ($action === 'employee_save' && $method === 'POST') {
        $user = requireAdmin();
        $input = body();
        requireFields($input, ['fullName', 'position', 'role', 'active']);
        $id = isset($input['id']) && $input['id'] !== '' ? positiveInt($input['id'], 'Employee') : null;
        $fullName = text($input, 'fullName', 120, true);
        $position = text($input, 'position', 100, true);
        $email = strtolower(text($input, 'email', 190));
        $phone = text($input, 'phone', 30);
        $role = validRole($input['role']);
        $active = filter_var($input['active'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($active === null) fail(422, 'Choose whether the employee account is active.');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422, 'Enter a valid employee email.');
        $password = (string)($input['password'] ?? '');
        if ($email !== '' && !$id && strlen($password) < 12) fail(422, 'New staff accounts need a password of at least 12 characters.');
        if ($password !== '' && strlen($password) < 12) fail(422, 'Passwords must be at least 12 characters.');

        $employeeId = beginTransaction(function (PDO $pdo) use ($user, $id, $fullName, $position, $email, $phone, $role, $active, $password): int {
            if ($id) {
                $employee = execute('SELECT id, user_id, employee_code FROM employees WHERE id=:id AND deleted_at IS NULL FOR UPDATE', ['id' => $id])->fetch();
                if (!$employee) fail(404, 'Employee not found.');
                $userId = $employee['user_id'] ? (int)$employee['user_id'] : null;
                preventLastAdminChange(
                    $userId,
                    $email !== '' ? $role : 'staff',
                    $email !== '' && $active,
                    (int)$user['id']
                );
                if ($email !== '') {
                    if ($userId) {
                        if ($password !== '') {
                            execute(
                                'UPDATE users SET name=:name, email=:email, password_hash=:password_hash, role=:role, active=:active WHERE id=:id',
                                ['name' => $fullName, 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $role, 'active' => (int)$active, 'id' => $userId]
                            );
                        } else {
                            execute(
                                'UPDATE users SET name=:name, email=:email, role=:role, active=:active WHERE id=:id',
                                ['name' => $fullName, 'email' => $email, 'role' => $role, 'active' => (int)$active, 'id' => $userId]
                            );
                        }
                    } else {
                        execute(
                            'INSERT INTO users (name, email, password_hash, role, active) VALUES (:name, :email, :password_hash, :role, :active)',
                            ['name' => $fullName, 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $role, 'active' => (int)$active]
                        );
                        $userId = (int)$pdo->lastInsertId();
                    }
                } elseif ($userId) {
                    execute('UPDATE users SET active=0 WHERE id=:id', ['id' => $userId]);
                    $userId = null;
                }
                execute(
                    'UPDATE employees SET full_name=:name, email=:email, phone=:phone, position=:position,
                     active=:active, user_id=:user_id WHERE id=:id',
                    ['name' => $fullName, 'email' => $email ?: null, 'phone' => $phone ?: null, 'position' => $position, 'active' => (int)$active, 'user_id' => $userId, 'id' => $id]
                );
                $employeeId = $id;
                $logAction = 'Updated employee';
            } else {
                $userId = null;
                if ($email !== '') {
                    execute(
                        'INSERT INTO users (name, email, password_hash, role, active) VALUES (:name, :email, :password_hash, :role, :active)',
                        ['name' => $fullName, 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $role, 'active' => (int)$active]
                    );
                    $userId = (int)$pdo->lastInsertId();
                }
                execute(
                    'INSERT INTO employees (user_id, employee_code, full_name, email, phone, position, active)
                     VALUES (:user_id, :code, :name, :email, :phone, :position, :active)',
                    [
                        'user_id' => $userId, 'code' => generateCode('EMP'), 'name' => $fullName,
                        'email' => $email ?: null, 'phone' => $phone ?: null, 'position' => $position, 'active' => (int)$active,
                    ]
                );
                $employeeId = (int)$pdo->lastInsertId();
                $logAction = 'Added employee';
            }
            logActivity((int)$user['id'], $logAction, 'employee', $employeeId);
            return $employeeId;
        });
        respond(['id' => $employeeId, 'message' => 'Employee saved.']);
    }

    if ($action === 'employee_delete' && $method === 'POST') {
        $user = requireAdmin();
        $input = body();
        $id = positiveInt($input['id'] ?? null, 'Employee');
        beginTransaction(function () use ($id, $user): void {
            $employee = execute('SELECT full_name, user_id FROM employees WHERE id=:id AND deleted_at IS NULL FOR UPDATE', ['id' => $id])->fetch();
            if (!$employee) fail(404, 'Employee not found.');
            if ($employee['user_id']) {
                preventLastAdminChange((int)$employee['user_id'], 'staff', false, (int)$user['id']);
                execute('UPDATE users SET active=0 WHERE id=:id', ['id' => $employee['user_id']]);
            }
            execute('UPDATE employees SET active=0, deleted_at=NOW() WHERE id=:id', ['id' => $id]);
            logActivity((int)$user['id'], 'Deactivated employee', 'employee', $id, ['name' => $employee['full_name']]);
        });
        respond(['ok' => true]);
    }

    respond(['error' => 'Unknown API action.'], 404);
} catch (ApiError $error) {
    respond(['error' => $error->getMessage()], $error->httpStatus);
} catch (PDOException $error) {
    error_log('Hotel management database error: ' . $error->getMessage());
    $status = str_starts_with((string)$error->getCode(), '23') ? 409 : 500;
    $message = $status === 409
        ? 'This record conflicts with existing data. Check for duplicate values or linked records.'
        : 'A database error occurred. Verify the database setup and try again.';
    respond(['error' => $message], $status);
} catch (Throwable $error) {
    error_log('Hotel management API error: ' . $error->getMessage());
    respond(['error' => 'The request could not be completed. Check the server logs for details.'], 500);
}
