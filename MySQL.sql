SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS portal_db;
USE portal_db;

-- 1. Create the 'users' Table
CREATE TABLE users (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role ENUM('patient', 'doctor') NOT NULL, 
    fullname VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL, 
    specialty VARCHAR(100) NULL,
    degrees VARCHAR(255) NULL,
    designation VARCHAR(100) NULL,
    workplace VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Create the 'password_resets' Table
CREATE TABLE password_resets (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(64) NOT NULL UNIQUE, 
    expires BIGINT(20) NOT NULL, 
    FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE
);

-- 3. Create the 'appointment_slots' Table (Corrected Name)
-- This table was previously named 'available_slots' but is renamed here
-- to match the table name expected by patient_dashboard.php.
CREATE TABLE appointment_slots (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT(11) UNSIGNED NOT NULL,
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('available', 'booked', 'deleted') NOT NULL DEFAULT 'available',
    UNIQUE KEY unique_slot (doctor_id, appointment_date, start_time), 
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 4. Create the 'appointments' Table
-- Note: The foreign key now references the new 'appointment_slots' table.
CREATE TABLE appointments (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slot_id INT(11) UNSIGNED NOT NULL,
    patient_id INT(11) UNSIGNED NOT NULL,
    request_status ENUM('pending', 'confirmed', 'canceled_by_patient', 'canceled_by_doctor') NOT NULL DEFAULT 'pending',
    UNIQUE KEY unique_appointment_per_slot (slot_id),
    
    -- Updated Foreign Key
    FOREIGN KEY (slot_id) REFERENCES appointment_slots(id) ON DELETE CASCADE,
    
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

SET FOREIGN_KEY_CHECKS = 1;
