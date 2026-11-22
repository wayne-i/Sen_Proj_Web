ALING MAMENG RESORT MANAGEMENT SYSTEM
=====================================

REQUIREMENTS
- XAMPP (Apache + MySQL)
- PHP 8.0 or newer
- MySQL / MariaDB
- Modern web browser
- Internet connection for Bootstrap, Font Awesome, Google Fonts, and Chart.js CDNs

INSTALLATION
1. Extract the aling_mameng_resort folder into:
   C:\xampp\htdocs\
2. Open XAMPP Control Panel and start Apache and MySQL.
3. Open http://localhost/phpmyadmin
4. For a NEW installation, import database.sql.
   If you already installed the previous version, import update_payment_schema.sql ONCE.
5. Check config.php. Default XAMPP settings are:
   host=localhost, database=aling_mameng_db, user=root, password blank.
   Change these if your MySQL setup uses a different password.
6. Open:
   http://localhost/aling_mameng_resort/create_admin.php
7. The initial admin login is:
   Email: alingmamengresort@gmail.com
   Password: admin123
8. Log in at:
   http://localhost/aling_mameng_resort/login.php
9. IMPORTANT: Delete create_admin.php immediately after creating the admin.
10. Change the temporary admin password before using the system.

GUEST WEBSITE
http://localhost/aling_mameng_resort/

ADMIN FEATURES
- Dashboard statistics
- Reservation status management
- Room creation and availability status
- Guest list
- Facility management
- Payment status and method updates
- Employee records
- Monthly paid revenue and booking status reports

GUEST FEATURES
- Guest registration and login
- View available rooms and facilities
- Submit room reservation requests
- View own booking history and status

IMPORTANT NOTES
- This is a functional starter project, not a production-certified booking platform.
- Payments are recorded by an administrator; no live payment gateway is integrated.
- Email/SMS notifications, password reset, CSRF protection, audit logs, room image uploads,
  and a full edit/delete workflow should be added before public deployment.
- Configure HTTPS, strong database credentials, backups, and server security before launch.
- Booking overlap checks are included, but production deployment should use database transactions
  and stronger concurrency controls to prevent simultaneous double-booking edge cases.
- The hero image uses an external Unsplash URL; replace it with resort-owned imagery as desired.

GUEST PAYMENT WORKFLOW
1. Guest opens My Bookings and selects Pay Rent.
2. Guest selects GCash, bank transfer, or cash at resort.
3. For electronic transfers, guest enters the transaction reference and may upload proof.
4. The payment becomes PENDING for admin verification.
5. Admin checks the details under Admin > Payments and marks it PAID or UNPAID.
6. The guest sees the updated status in My Bookings.

PAYMENT LIMITATION
This is manual payment recording and verification, not a live payment gateway.
It does not automatically charge GCash, bank, or cards. The guest must use official
payment instructions supplied by the resort. Admin must verify funds received before
marking a transaction PAID.
