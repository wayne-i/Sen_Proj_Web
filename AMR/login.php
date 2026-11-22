
<?php

require 'config.php';

// ==========================================
// 1. REDIRECT IF ALREADY LOGGED IN
// ==========================================

if (logged_in()) {

    if (is_admin()) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: my-bookings.php');
    }

    exit;
}

$error = '';

// ==========================================
// 2. PROCESS LOGIN
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Find user by email
    $sql = "
        SELECT *
        FROM users
        WHERE email = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);

    $user = $stmt->fetch();

    // ======================================
    // 3. VERIFY LOGIN CREDENTIALS
    // ======================================

    if (
        $user &&
        password_verify($password, $user['password_hash'])
    ) {

        // Regenerate session ID
        session_regenerate_id(true);

        // Store user information
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];

        // ==================================
        // 4. REDIRECT BASED ON USER ROLE
        // ==================================

        if (in_array($user['role'], ['admin', 'employee'], true)) {

            header('Location: admin/dashboard.php');

        } else {

            header('Location: my-bookings.php');

        }

        exit;

    } else {

        $error = 'Incorrect email or password.';

    }
}

// ==========================================
// 5. PAGE SETTINGS
// ==========================================

$pageTitle = 'Login';

require 'includes/header.php';

?>

<!-- ==========================================
     6. LOGIN PAGE DESIGN
========================================== -->

<div class="container auth-wrap">

    <div class="auth-card">

        <span class="eyebrow">
            ACCOUNT ACCESS
        </span>

        <h2>Welcome back</h2>

        <p class="text-secondary">
            Login to continue to your account.
        </p>

        <!-- Error Message -->
        <?php if ($error): ?>

            <div class="alert alert-danger">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" class="vstack gap-3">

            <!-- Email Address -->
            <div>

                <label class="form-label">
                    Email address
                </label>

                <input
                    type="email"
                    class="form-control"
                    name="email"
                    placeholder="Enter your email"
                    autocomplete="email"
                    required
                >

            </div>

            <!-- Password -->
            <div>

                <label class="form-label">
                    Password
                </label>

                <input
                    type="password"
                    class="form-control"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >

            </div>

            <!-- Login Button -->
            <button type="submit" class="btn btn-dark w-100">
                Login
            </button>

        </form>

        <!-- Registration Link -->
        <p class="small mt-3 mb-0">

            No account yet?

            <a href="register.php">
                Register
            </a>.

        </p>

    </div>

</div>

<?php require 'includes/footer.php'; ?>
