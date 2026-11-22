
<?php

require_once __DIR__ . '/../config.php';

require_admin();

// ==========================================
// 1. FETCH MONTHLY PAID REVENUE
// ==========================================

$sql = "
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        SUM(
            CASE
                WHEN payment_status = 'paid' THEN amount
                ELSE 0
            END
        ) AS revenue
    FROM payments
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
";

$stmt = $pdo->query($sql);

$monthlyRevenue = array_reverse($stmt->fetchAll());

// ==========================================
// 2. FETCH RESERVATION COUNTS BY STATUS
// ==========================================

$sql = "
    SELECT
        status,
        COUNT(*) AS total
    FROM bookings
    GROUP BY status
    ORDER BY status ASC
";

$stmt = $pdo->query($sql);

$reservationCounts = $stmt->fetchAll();

// ==========================================
// 3. PAGE SETTINGS
// ==========================================

$adminTitle = 'Reports';

require '_layout_top.php';

?>

<!-- ==========================================
     4. REPORTS DASHBOARD
========================================== -->

<div class="row g-3">

    <!-- ======================================
         MONTHLY REVENUE CHART
    ====================================== -->

    <div class="col-lg-7">

        <div class="content-card">

            <h5>Monthly Paid Revenue</h5>

            <p class="text-secondary small">
                Overview of monthly confirmed payments.
            </p>

            <canvas
                id="revenueChart"
                height="120"
            ></canvas>

            <!-- Revenue Data for Chart.js -->
            <script
                type="application/json"
                id="revenueData"
            ><?= json_encode(
                $monthlyRevenue,
                JSON_HEX_TAG |
                JSON_HEX_APOS |
                JSON_HEX_AMP |
                JSON_HEX_QUOT |
                JSON_THROW_ON_ERROR
            ) ?></script>

        </div>

    </div>

    <!-- ======================================
         RESERVATION STATUS REPORT
    ====================================== -->

    <div class="col-lg-5">

        <div class="content-card">

            <h5>Reservations by Status</h5>

            <p class="text-secondary small">
                Total reservations grouped by status.
            </p>

            <?php if (!empty($reservationCounts)): ?>

                <?php foreach ($reservationCounts as $reservation): ?>

                    <div class="d-flex justify-content-between border-bottom py-3">

                        <!-- Reservation Status -->
                        <span>
                            <?= e(
                                ucwords(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $reservation['status']
                                    )
                                )
                            ) ?>
                        </span>

                        <!-- Total Reservations -->
                        <strong>
                            <?= (int) $reservation['total'] ?>
                        </strong>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p class="text-secondary text-center py-4">
                    No reservation records found.
                </p>

            <?php endif; ?>

        </div>

    </div>

</div>

<!-- ==========================================
     5. CHART.JS LIBRARY
========================================== -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- ==========================================
     6. REVENUE CHART CONFIGURATION
========================================== -->

<script>

    // Retrieve monthly revenue data from PHP
    const revenueData = JSON.parse(
        document.getElementById('revenueData').textContent
    );

    // Prepare chart labels
    const months = revenueData.map(item => item.month);

    // Prepare revenue values
    const revenues = revenueData.map(
        item => Number(item.revenue)
    );

    // Create monthly revenue chart
    new Chart(
        document.getElementById('revenueChart'),
        {
            type: 'bar',

            data: {
                labels: months,

                datasets: [
                    {
                        label: 'Paid Revenue (PHP)',
                        data: revenues,
                        borderWidth: 1
                    }
                ]
            },

            options: {
                responsive: true,

                maintainAspectRatio: true,

                plugins: {
                    legend: {
                        display: false
                    },

                    tooltip: {
                        callbacks: {
                            label: function(context) {

                                return '₱' +
                                    Number(context.raw).toLocaleString(
                                        'en-PH',
                                        {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        }
                                    );

                            }
                        }
                    }
                },

                scales: {
                    y: {
                        beginAtZero: true,

                        ticks: {
                            callback: function(value) {
                                return '₱' +
                                    Number(value).toLocaleString('en-PH');
                            }
                        }
                    }
                }
            }
        }
    );

</script>

<?php require '_layout_bottom.php'; ?>
