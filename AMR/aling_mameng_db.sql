-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 22, 2026 at 02:38 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `aling_mameng_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `booking_code` varchar(30) NOT NULL,
  `user_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `guests` int(11) NOT NULL DEFAULT 1,
  `nights` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','checked_in','checked_out','cancelled') DEFAULT 'pending',
  `special_request` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `booking_code`, `user_id`, `room_id`, `check_in`, `check_out`, `guests`, `nights`, `total_amount`, `status`, `special_request`, `created_at`) VALUES
(1, 'AMR-261122-B6A7ED', 1, 2, '2026-11-25', '2026-11-26', 2, 1, 3500.00, 'checked_out', '', '2026-11-22 08:04:39');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `position` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(160) DEFAULT NULL,
  `employment_status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `full_name`, `position`, `phone`, `email`, `employment_status`, `created_at`) VALUES
(1, 'Arnold Domingo', 'Employee', '09455060876', 'arnolddomingo@gmail.com', 'active', '2026-11-22 12:36:44');

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `id` int(11) NOT NULL,
  `facility_name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `opening_hours` varchar(100) DEFAULT NULL,
  `status` enum('available','maintenance','inactive') DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`id`, `facility_name`, `description`, `opening_hours`, `status`) VALUES
(1, 'Swimming Pool', 'Resort swimming pool for registered guests.', '7:00 AM - 9:00 PM', 'available'),
(2, 'Beach Access', 'Guest access to the resort beach area.', '6:00 AM - 6:00 PM', 'available'),
(3, 'Restaurant', 'On-site dining and refreshments.', '7:00 AM - 10:00 PM', 'available'),
(4, 'Function Area', 'Venue for gatherings and special occasions.', 'By reservation', 'available');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','gcash','bank_transfer','card') NOT NULL DEFAULT 'cash',
  `payment_status` enum('unpaid','partial','paid','refunded') DEFAULT 'unpaid',
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `amount`, `payment_method`, `payment_status`, `paid_at`, `created_at`) VALUES
(1, 1, 3500.00, 'cash', 'paid', '2026-11-22 20:18:07', '2026-11-22 08:04:39');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `room_name` varchar(120) NOT NULL,
  `room_type` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `capacity` int(11) NOT NULL DEFAULT 2,
  `price_per_night` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT 'assets/images/room-placeholder.jpg',
  `status` enum('available','maintenance','inactive') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_name`, `room_type`, `description`, `capacity`, `price_per_night`, `image`, `status`, `created_at`) VALUES
(1, 'Garden Standard Room', 'Standard', 'A comfortable room with a peaceful garden view.', 2, 1800.00, 'assets/images/room-placeholder.jpg', 'available', '2026-11-21 02:08:25'),
(2, 'Family Room', 'Family', 'A spacious room suitable for family stays.', 5, 3500.00, 'assets/images/room-placeholder.jpg', 'available', '2026-11-21 02:08:25'),
(3, 'Ocean View Suite', 'Suite', 'A relaxing suite with a beautiful sea view.', 3, 4200.00, 'assets/images/room-placeholder.jpg', 'available', '2026-11-21 02:08:25'),
(4, 'Barkada Cottage', 'Cottage', 'A group cottage for friends and larger groups.', 8, 5000.00, 'assets/images/room-placeholder.jpg', 'available', '2026-11-21 02:08:25');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','guest') NOT NULL DEFAULT 'guest',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `password_hash`, `role`, `created_at`) VALUES
(1, 'Charlie Magyaya', 'magyayacharlie20@gmail.com', '09455060876', '$2y$10$iT/KF1tqsk8VwdIqUzS5oODqLXhv8iZAlZOkQIMNrouy7JPlqSym6', 'guest', '2026-11-21 02:10:15'),
(2, 'Resort Administrator', 'arnolddomingo@gmail.com', NULL, '$2y$10$2EIDDy1hflFRlYUoMm5An.rPm4KHcZ5YUv5wdbTFnbAtDinN.Jw9W', 'admin', '2026-11-21 02:12:33'),
(3, 'Resort Admin', 'alingmamengresort@gmail.com', NULL, '$2y$10$ZU.lI/9zo/pvV5GL2HGjPeV.IuDVyvSxLVPJcAdJRP.n2bW5VeleK', 'admin', '2026-11-22 08:03:33'),
(4, 'Arnold Dante', 'arnolddante@gmail.com', '09123123121', '$2y$10$vT41bH/tlwRdN0S8yPy2eeNlGAivVuS4ueQhXPsRZ/3vz/NxRZ9qW', '', '2026-11-22 13:29:54');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_code` (`booking_code`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
