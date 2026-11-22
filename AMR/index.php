
<?php
// =====================================================
// 1. DATABASE CONNECTION
// =====================================================

require_once 'config.php';


// =====================================================
// 2. PAGE SETTINGS
// =====================================================

$pageTitle = 'Welcome';


// =====================================================
// 3. FETCH AVAILABLE ROOMS
// =====================================================

$roomStmt = $pdo->query("
    SELECT *
    FROM rooms
    WHERE status = 'available'
    ORDER BY price_per_night ASC
");

$rooms = $roomStmt->fetchAll(PDO::FETCH_ASSOC);


// =====================================================
// 4. FETCH AVAILABLE FACILITIES
// =====================================================

$facilityStmt = $pdo->query("
    SELECT *
    FROM facilities
    WHERE status = 'available'
    ORDER BY facility_name ASC
");

$facilities = $facilityStmt->fetchAll(PDO::FETCH_ASSOC);


// =====================================================
// 5. PAGE HEADER
// =====================================================

require_once 'includes/header.php';

?>

<!-- =====================================================
     6. HERO SECTION
====================================================== -->

<section class="hero">

    <div class="container hero-content">

        <span class="eyebrow">
            YOUR PEACEFUL ESCAPE
        </span>

        <h1>
            Find your moment
            <br>
            of <span>calm.</span>
        </h1>

        <p>
            Welcome to Aling Mameng Resort — a relaxing destination
            for family trips, friendly getaways, and memorable stays.
        </p>

        <a href="#rooms" class="btn btn-dark btn-lg px-4">
            Explore Rooms
            <i class="fa-solid fa-arrow-right ms-2"></i>
        </a>

    </div>

</section>


<!-- =====================================================
     7. ROOMS AND ACCOMMODATIONS SECTION
====================================================== -->

<section class="container section-space" id="rooms">

    <div class="section-heading">

        <div>

            <span class="eyebrow">
                STAY WITH US
            </span>

            <h2>
                Rooms & Accommodations
            </h2>

        </div>

        <p>
            Comfortable spaces for every kind of getaway.
        </p>

    </div>


    <div class="row g-4">

        <?php if (!empty($rooms)): ?>

            <?php foreach ($rooms as $room): ?>

                <div class="col-md-6 col-lg-3">

                    <div class="room-card h-100">

                        <!-- Room Image Placeholder -->

                        <div class="room-image">

                            <i class="fa-solid fa-bed"></i>

                        </div>


                        <!-- Room Details -->

                        <div class="p-3">

                            <div class="small text-secondary">

                                <?= e($room['room_type']) ?>

                                · Up to <?= (int) $room['capacity'] ?> guests

                            </div>


                            <h5 class="mt-2">
                                <?= e($room['room_name']) ?>
                            </h5>


                            <p class="small text-secondary">
                                <?= e($room['description']) ?>
                            </p>


                            <!-- Price and Booking Button -->

                            <div class="d-flex justify-content-between align-items-center">

                                <strong>

                                    <?= money($room['price_per_night']) ?>

                                    <small class="text-secondary fw-normal">
                                        / night
                                    </small>

                                </strong>


                                <a
                                    href="book.php?room=<?= (int) $room['id'] ?>"
                                    class="btn btn-outline-dark btn-sm"
                                >
                                    Book Now
                                </a>

                            </div>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <!-- No Available Rooms -->

            <div class="col-12">

                <div class="alert alert-light text-center">

                    <i class="fa-solid fa-bed me-2"></i>

                    No rooms are currently available.
                    Please check again later.

                </div>

            </div>

        <?php endif; ?>

    </div>

</section>


<!-- =====================================================
     8. RESORT FACILITIES SECTION
====================================================== -->

<section class="soft-section section-space" id="facilities">

    <div class="container">

        <div class="section-heading">

            <div>

                <span class="eyebrow">
                    ENJOY YOUR STAY
                </span>

                <h2>
                    Resort Facilities
                </h2>

            </div>

            <p>
                Everything you need for a refreshing break.
            </p>

        </div>


        <div class="row g-3">

            <?php if (!empty($facilities)): ?>

                <?php foreach ($facilities as $facility): ?>

                    <div class="col-md-6 col-lg-3">

                        <div class="facility-card">

                            <i class="fa-solid fa-sun"></i>


                            <h5>
                                <?= e($facility['facility_name']) ?>
                            </h5>


                            <p>
                                <?= e($facility['description']) ?>
                            </p>


                            <small>
                                <?= e($facility['opening_hours']) ?>
                            </small>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <!-- No Available Facilities -->

                <div class="col-12">

                    <div class="alert alert-light text-center">

                        No facilities are currently available.

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </div>

</section>


<!-- =====================================================
     9. CONTACT AND REGISTRATION SECTION
====================================================== -->

<section class="container section-space" id="contact">

    <div class="contact-panel">

        <div>

            <span class="eyebrow">
                PLAN YOUR GETAWAY
            </span>

            <h2>
                Make time for a better stay.
            </h2>

            <p class="mb-0 text-secondary">
                Create an account to reserve your preferred accommodation.
            </p>

        </div>


        <a href="register.php" class="btn btn-dark px-4">
            Get Started
        </a>

    </div>

</section>


<!-- =====================================================
     10. PAGE FOOTER
====================================================== -->

<?php require_once 'includes/footer.php'; ?>
