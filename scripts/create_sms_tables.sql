-- SMS Configuration Table
CREATE TABLE IF NOT EXISTS sms_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sms_number VARCHAR(20) NOT NULL DEFAULT '0737615143',
    sms_provider VARCHAR(50) DEFAULT 'generic',
    api_key VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- SMS Logs Table
CREATE TABLE IF NOT EXISTS sms_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parent_id INT,
    student_id INT,
    message TEXT NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    response TEXT,
    sent_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default SMS configuration if not exists
INSERT INTO sms_config (sms_number, sms_provider) 
SELECT '0737615143', 'generic' 
FROM DUAL 
WHERE NOT EXISTS (SELECT 1 FROM sms_config);
