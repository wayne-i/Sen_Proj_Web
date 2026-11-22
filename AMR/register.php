
<?php
// =====================================================
// 1. DATABASE CONNECTION
// =====================================================

require_once 'config.php';


// =====================================================
// 2. REDIRECT IF ALREADY LOGGED IN
// =====================================================

if (logged_in()) {
    header('Location: index.php');
    exit;
}


// =====================================================
// 3. INITIALIZE ERROR MESSAGE
// =====================================================

$error = '';


// =====================================================
// 4. PROCESS REGISTRATION FORM
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get submitted form values
    $name = trim($_POST['full_name'] ?? '');

    $emailInput = trim($_POST['email'] ?? '');
    $email = filter_var($emailInput, FILTER_VALIDATE_EMAIL);

    $phone = trim($_POST['phone'] ?? '');

    $password = $_POST['password'] ?? '';


    // -------------------------------------------------
    // 4.1 VALIDATE INPUTS
    // -------------------------------------------------

    if (
        $name === '' ||
        !$email ||
        strlen($password) < 8
    ) {

        $error = 'Enter a name, valid email, and password with at least 8 characters.';

    } else {

        // -------------------------------------------------
        // 4.2 CREATE NEW GUEST ACCOUNT
        // -------------------------------------------------

        try {

            // Hash password before saving
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            // Insert guest information
            $registerStmt = $pdo->prepare("
                INSERT INTO users (
                    full_name,
                    email,
                    phone,
                    password_hash,
                    role
                )
                VALUES (?, ?, ?, ?, 'guest')
            ");

            $registerStmt->execute([
                $name,
                $email,
                $phone,
                $hashedPassword
            ]);


            // -------------------------------------------------
            // 4.3 AUTOMATICALLY LOG IN NEW GUEST
            // -------------------------------------------------

            // Regenerate session ID for security
            session_regenerate_id(true);

            // Store guest session information
            $_SESSION['user_id'] = (int) $pdo->lastInsertId();

            $_SESSION['name'] = $name;

            $_SESSION['role'] = 'guest';


            // Redirect to reservations page
            header('Location: my-bookings.php');
            exit;


        } catch (PDOException $ex) {

            // Log database error
            error_log('Registration Error: ' . $ex->getMessage());

            // Display registration error
            if ($ex->getCode() === '23000') {

                $error = 'That email address is already registered.';

            } else {

                $error = 'Unable to create your account. Please try again.';

            }
        }
    }
}


// =====================================================
// 5. PAGE SETTINGS
// =====================================================

$pageTitle = 'Create Account';


// =====================================================
// 6. PAGE HEADER
// =====================================================

require_once 'includes/header.php';

?>

<!-- =====================================================
     7. REGISTRATION FORM
====================================================== -->

<div class="container auth-wrap">

    <div class="auth-card">

        <!-- Registration Heading -->

        <span class="eyebrow">
            WELCOME
        </span>

        <h2>
            Create Your Account
        </h2>

        <p class="text-secondary">
            Register to book your stay at Aling Mameng Resort.
        </p>


        <!-- Error Message -->

        <?php if (!empty($error)): ?>

            <div class="alert alert-danger">

                <i class="fa-solid fa-circle-exclamation me-2"></i>

                <?= e($error) ?>

            </div>

        <?php endif; ?>


        <!-- Registration Form -->

        <form method="POST" class="vstack gap-3">

            <!-- Full Name -->

            <div>

                <label class="form-label">
                    Full Name
                </label>

                <input
                    type="text"
                    class="form-control"
                    name="full_name"
                    value="<?= e($_POST['full_name'] ?? '') ?>"
                    placeholder="Enter your full name"
                    maxlength="150"
                    required
                >

            </div>


            <!-- Email Address -->

            <div>

                <label class="form-label">
                    Email Address
                </label>

                <input
                    type="email"
                    class="form-control"
                    name="email"
                    value="<?= e($_POST['email'] ?? '') ?>"
                    placeholder="Enter your email address"
                    required
                >

            </div>


            <!-- Phone Number -->

            <div>

                <label class="form-label">
                    Phone Number
                </label>

                <input
                    type="tel"
                    class="form-control"
                    name="phone"
                    value="<?= e($_POST['phone'] ?? '') ?>"
                    placeholder="Enter your phone number"
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
                    minlength="8"
                    placeholder="Enter your password"
                    required
                >

                <small class="text-secondary">
                    Password must contain at least 8 characters.
                </small>

            </div>


            <!-- Submit Button -->

            <button
                type="submit"
                class="btn btn-dark w-100"
            >

                <i class="fa-solid fa-user-plus me-2"></i>

                Create Account

            </button>

        </form>


        <!-- Login Link -->

        <p class="small mt-3 mb-0">

            Already registered?

            <a href="login.php">
                Login here
            </a>.

        </p>

    </div>

</div>


<!-- =====================================================
     8. PAGE FOOTER
====================================================== -->

<?php require_once 'includes/footer.php'; ?>
