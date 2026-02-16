# School Management System - Setup Guide

## Overview
This guide helps you set up the new features added to the school management system:

1. **Fee Type Auto-Assignment** - Assign fees to students with 0 paid amount initially
2. **SMS Mass Messaging** - Send SMS to parents via Afrikaas or Twilio
3. **M-Pesa STK Push** - Enable fee payments via M-Pesa

---

## Task 1: Setup Database Tables

The database migration script creates the necessary tables. However, since the v0 system cannot execute .sql files directly on your external database, you need to:

### Option A: Using phpMyAdmin
1. Go to your hosting control panel (InfinityFree, Hostinger, etc.)
2. Open phpMyAdmin
3. Select your database (`if0_40418447_schooldb`)
4. Go to SQL tab
5. Copy and paste the SQL from `/scripts/migrate_student_fees.sql`
6. Click Execute

### Option B: Using a Setup Script
Run this PHP file in your browser once:
```
https://yourschool.com/setup/initialize_fee_system.php
```

This will create the tables automatically if they don't exist.

---

## Task 2: Fee Type Auto-Assignment System

### How It Works
1. Admin creates a fee type (e.g., "Tuition Fees" - 5000 KES)
2. Admin clicks "Assign Fee" button
3. Select:
   - Fee Type to assign
   - Recipients (All Students / Specific Grade)
   - Due Date
4. System creates `student_fees` records with:
   - `amount_due`: The full fee amount
   - `amount_paid`: 0.00 (initially)
   - `status`: pending

### Student View
Students can see:
- All fees assigned to them on the Fees page
- Outstanding balance
- M-Pesa payment option

### Finance Staff View
Finance staff can:
- View assigned fees in the admin section
- Update payment amounts as students pay
- Track which fees are pending/partial/paid

### Database Schema
```sql
CREATE TABLE student_fees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    fee_type_id INT NOT NULL,
    amount_due DECIMAL(10, 2) NOT NULL,
    amount_paid DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('pending', 'partial', 'paid'),
    due_date DATE,
    last_payment_date DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
);
```

---

## Task 3: SMS Mass Messaging System

### Setup Instructions

#### Step 1: Choose SMS Provider

**Option A: Afrikaas (Recommended for Kenya)**
1. Visit https://afrikaas.co.ke
2. Create an account
3. Get your API Key
4. Get your Sender ID

**Option B: Twilio (Works Globally)**
1. Visit https://www.twilio.com
2. Create an account
3. Get your Account SID and Auth Token
4. Verify phone numbers

#### Step 2: Add Environment Variables
Contact your hosting provider to add these environment variables:

For **Afrikaas**:
```
SMS_API_KEY=your_afrikaas_api_key
SMS_API_URL=https://api.afrikaas.co.ke/api/send
SMS_SENDER_ID=EBUSHIBO
```

For **Twilio**:
```
SMS_API_KEY=not_used_for_twilio
TWILIO_ACCOUNT_SID=your_account_sid
TWILIO_AUTH_TOKEN=your_auth_token
TWILIO_PHONE_NUMBER=+1234567890
```

#### Step 3: Update Parent Phone Numbers
Parents must have phone numbers in the system:
1. Go to Students page
2. Edit each student
3. Add parent phone number (254712345678 format)
4. Save

#### Step 4: Use Contact Parents Feature
1. Click "Contact Parents" in sidebar
2. Choose recipients:
   - All parents
   - Parents of a specific grade
   - Individual parent
3. Type your message (max 160 characters)
4. Click "Send SMS"

### Features
- **SMS Logs**: All messages are logged with status (sent/failed)
- **Character Count**: Real-time character counter (160 char limit)
- **Bulk Messaging**: Send to all parents or specific grades
- **Message History**: View last 30 messages sent

---

## Task 4: M-Pesa STK Push Integration

### Setup Instructions

#### Step 1: Register with Safaricom
1. Go to Safaricom API Portal (developer.safaricom.co.ke)
2. Create an account
3. Register your application
4. Get Consumer Key and Consumer Secret
5. Generate M-Pesa API credentials

#### Step 2: Test Environment Setup
For **Sandbox/Testing**:
- Use Business Shortcode: **174379**
- Use test phone numbers provided by Safaricom

For **Production**:
- Get your actual Business Shortcode from Safaricom
- Register callback URL

#### Step 3: Add Environment Variables
Ask your hosting provider to add:

```
MPESA_BUSINESS_SHORTCODE=174379
MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret
MPESA_PASSKEY=your_passkey
MPESA_ENVIRONMENT=sandbox
MPESA_CALLBACK_URL=https://yourschool.com/api/mpesa_callback.php
```

Change to `production` when ready and update SHORTCODE.

#### Step 4: Student Flow
1. Student goes to "My Fees" page
2. Sees M-Pesa section with:
   - School Paybill: 0758955122
   - Phone number input field
3. Enters their phone number (254712345678)
4. Clicks "Send STK Prompt"
5. A prompt appears on their phone
6. They enter M-Pesa PIN to complete payment
7. Payment automatically updates in the system

### Database Schema for M-Pesa
```sql
CREATE TABLE mpesa_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    phone_number VARCHAR(20),
    amount DECIMAL(10, 2),
    mpesa_request_id VARCHAR(100),
    mpesa_response_code VARCHAR(50),
    mpesa_response_message TEXT,
    status ENUM('initiated', 'success', 'failed', 'timeout'),
    initiated_at DATETIME,
    completed_at DATETIME
);
```

---

## API Endpoints Reference

### Fee Management
```
POST /api/manage_fees.php?action=assign_single
POST /api/manage_fees.php?action=assign_grade
POST /api/manage_fees.php?action=assign_all
POST /api/manage_fees.php?action=update_payment
GET /api/manage_fees.php?action=get_student_fees&student_id=1
```

### SMS Sending
```
POST /api/send_sms.php?action=send_single
POST /api/send_sms.php?action=send_grade
POST /api/send_sms.php?action=send_all
GET /api/send_sms.php?action=logs
```

### M-Pesa Payments
```
POST /api/mpesa_stk.php?action=initiate
GET /api/mpesa_stk.php?action=query_status
POST /api/mpesa_stk.php?action=callback
GET /api/mpesa_stk.php?action=history
```

---

## Troubleshooting

### SMS Not Sending
1. Check phone numbers are in 254XXXXXXXXX format
2. Verify API key is correct
3. Check SMS logs for error messages
4. Ensure you have SMS credits with provider

### M-Pesa Not Working
1. Verify Consumer Key and Secret are correct
2. Check callback URL is accessible
3. Ensure phone number is in correct format
4. Test with sandbox credentials first
5. Check M-Pesa transaction logs

### Database Tables Not Created
1. Run `/setup/initialize_fee_system.php` in browser
2. Or manually run the SQL in phpMyAdmin
3. Check "SMS Logs" table - if it exists, others should too

---

## Support

For more information:
- Afrikaas SMS: https://afrikaas.co.ke/docs
- Twilio SMS: https://www.twilio.com/docs
- M-Pesa API: https://developer.safaricom.co.ke/docs

Contact your hosting provider for help with environment variables.
