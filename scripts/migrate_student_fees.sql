-- ============================================
-- Student Fees Auto-Assignment Migration
-- Creates student_fees table to track fee assignments with 0 paid initially
-- ============================================

-- Create student_fees table
CREATE TABLE IF NOT EXISTS student_fees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    fee_type_id INT NOT NULL,
    amount_due DECIMAL(10, 2) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(10, 2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_fee (student_id, fee_type_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_type_id) REFERENCES fee_types(id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_fee_type (fee_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create student_fee_payments table for detailed payment tracking
CREATE TABLE IF NOT EXISTS student_fee_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_fee_id INT NOT NULL,
    amount_paid DECIMAL(10, 2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    term VARCHAR(20) NOT NULL,
    receipt_number VARCHAR(50) UNIQUE,
    remarks TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_fee_id) REFERENCES student_fees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_student_fee (student_fee_id),
    INDEX idx_payment_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create SMS logs table for communication tracking
CREATE TABLE IF NOT EXISTS sms_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    recipient_phone VARCHAR(20) NOT NULL,
    recipient_name VARCHAR(100),
    message_type VARCHAR(50),
    message_content TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    api_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    INDEX idx_recipient_phone (recipient_phone),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create M-Pesa transactions table
CREATE TABLE IF NOT EXISTS mpesa_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    student_fee_id INT,
    amount DECIMAL(10, 2) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    mpesa_request_id VARCHAR(100) UNIQUE,
    mpesa_checkout_request_id VARCHAR(100) UNIQUE,
    status ENUM('initiated', 'pending', 'completed', 'failed', 'cancelled') DEFAULT 'initiated',
    response_code VARCHAR(20),
    response_message TEXT,
    merchant_request_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (student_fee_id) REFERENCES student_fees(id) ON DELETE SET NULL,
    INDEX idx_student (student_id),
    INDEX idx_status (status),
    INDEX idx_mpesa_checkout (mpesa_checkout_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
