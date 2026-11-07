CREATE DATABASE IF NOT EXISTS aling_mameng_hotel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE aling_mameng_hotel;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
    identifier CHAR(64) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    window_started DATETIME NOT NULL,
    locked_until DATETIME NULL,
    PRIMARY KEY (identifier),
    KEY idx_login_attempts_locked (locked_until)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS employees (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    employee_code VARCHAR(24) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(30) NULL,
    position VARCHAR(100) NOT NULL,
    hire_date DATE NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_employees_code (employee_code),
    UNIQUE KEY uq_employees_user (user_id),
    KEY idx_employees_active (active, deleted_at),
    CONSTRAINT fk_employees_user FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS guests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    guest_code VARCHAR(24) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(190) NULL,
    address VARCHAR(500) NULL,
    id_type VARCHAR(60) NULL,
    id_number VARCHAR(100) NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_guests_code (guest_code),
    KEY idx_guests_name (full_name),
    KEY idx_guests_phone (phone),
    KEY idx_guests_email (email),
    KEY idx_guests_id_number (id_type, id_number),
    KEY idx_guests_deleted (deleted_at)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS room_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(60) NOT NULL,
    description VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_room_types_name (name)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS rooms (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_number VARCHAR(20) NOT NULL,
    room_type_id BIGINT UNSIGNED NOT NULL,
    nightly_rate DECIMAL(12, 2) NOT NULL,
    status ENUM(
        'Available',
        'Occupied',
        'Under Maintenance',
        'Cleaning'
    ) NOT NULL DEFAULT 'Available',
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rooms_number (room_number),
    KEY idx_rooms_status (status, deleted_at),
    CONSTRAINT fk_rooms_type FOREIGN KEY (room_type_id) REFERENCES room_types (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_rooms_rate CHECK (nightly_rate >= 0)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS check_ins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    checkin_code VARCHAR(24) NOT NULL,
    guest_id BIGINT UNSIGNED NOT NULL,
    room_id BIGINT UNSIGNED NOT NULL,
    checked_in_at DATETIME NOT NULL,
    expected_checkout DATE NOT NULL,
    actual_checkout_at DATETIME NULL,
    guest_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
    nightly_rate DECIMAL(12, 2) NOT NULL,
    expected_room_charge DECIMAL(12, 2) NOT NULL DEFAULT 0,
    status ENUM(
        'Checked-in',
        'Checked-out',
        'Cancelled'
    ) NOT NULL DEFAULT 'Checked-in',
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_checkins_code (checkin_code),
    KEY idx_checkins_room_status (room_id, status),
    KEY idx_checkins_guest_date (guest_id, checked_in_at),
    KEY idx_checkins_checkout_date (expected_checkout, status),
    CONSTRAINT fk_checkins_guest FOREIGN KEY (guest_id) REFERENCES guests (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_checkins_room FOREIGN KEY (room_id) REFERENCES rooms (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_checkins_user FOREIGN KEY (created_by) REFERENCES users (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_checkins_guest_count CHECK (guest_count > 0),
    CONSTRAINT chk_checkins_dates CHECK (
        expected_checkout >= DATE(checked_in_at)
    ),
    CONSTRAINT chk_checkins_amounts CHECK (
        nightly_rate >= 0
        AND expected_room_charge >= 0
    )
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS check_outs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    checkin_id BIGINT UNSIGNED NOT NULL,
    checked_out_at DATETIME NOT NULL,
    nights_charged SMALLINT UNSIGNED NOT NULL,
    room_charge DECIMAL(12, 2) NOT NULL,
    additional_charges DECIMAL(12, 2) NOT NULL DEFAULT 0,
    total_charge DECIMAL(12, 2) NOT NULL,
    notes TEXT NULL,
    processed_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_checkouts_checkin (checkin_id),
    CONSTRAINT fk_checkouts_checkin FOREIGN KEY (checkin_id) REFERENCES check_ins (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_checkouts_user FOREIGN KEY (processed_by) REFERENCES users (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_checkouts_amounts CHECK (
        room_charge >= 0
        AND additional_charges >= 0
        AND total_charge >= 0
    )
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    receipt_code VARCHAR(30) NOT NULL,
    checkin_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    method ENUM('Cash', 'GCash', 'Card') NOT NULL,
    status ENUM('Paid', 'Refunded') NOT NULL DEFAULT 'Paid',
    notes VARCHAR(500) NULL,
    received_by BIGINT UNSIGNED NOT NULL,
    paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_receipt (receipt_code),
    KEY idx_payments_paid_at (paid_at),
    KEY idx_payments_checkin (checkin_id),
    CONSTRAINT fk_payments_checkin FOREIGN KEY (checkin_id) REFERENCES check_ins (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_payments_user FOREIGN KEY (received_by) REFERENCES users (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_payments_amount CHECK (amount > 0)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS expenses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category VARCHAR(100) NOT NULL,
    description VARCHAR(500) NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    expense_date DATE NOT NULL,
    receipt_reference VARCHAR(100) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_expenses_date (expense_date, deleted_at),
    CONSTRAINT fk_expenses_user FOREIGN KEY (created_by) REFERENCES users (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_expenses_amount CHECK (amount > 0)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(60) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_activity_created (created_at),
    KEY idx_activity_entity (entity_type, entity_id),
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE = InnoDB;

INSERT INTO
    room_types (name, description)
VALUES (
        'Single',
        'Single occupancy room'
    ),
    (
        'Double',
        'Double occupancy room'
    ),
    ('Deluxe', 'Deluxe room'),
    ('Suite', 'Suite room')
ON DUPLICATE KEY UPDATE
    description = VALUES(description);