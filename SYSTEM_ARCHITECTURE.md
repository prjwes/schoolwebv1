# School Management System - Complete Architecture Analysis

## System Overview
This is a school management portal built with PHP and MySQL. It supports role-based access (Admin, HOI, DHOI, Teachers, Students).

## Database Core Tables

### Users & Identity
- **users**: All system users (id, username, email, password, role, profile_image, is_active, timestamps)
- **role_status**: Tracks if special roles (Admin, HOI, DHOI) are filled
- **students**: Student data linked to users (user_id FK, student_id, grade, stream, parent_info, status)

### Academic
- **exams**: Exam records (exam_name, exam_type, grade, total_marks, exam_date)
- **exam_results**: Student performance (exam_id, student_id, subject, marks_obtained)
- **timetable_sessions**: Time slots (start_time, end_time, break_type)
- **timetable_teachers**: Teacher assignments (teacher_name, grade, subject)
- **timetables**: Generated timetables (timetable_data as LONGTEXT)
- **lesson_hours**: Subject-grade hour requirements
- **teacher_grades**: Which grades each specialized teacher handles

### Fees Management
- **fee_types**: Fee structures (fee_name, amount, grade, is_active)
- **fee_payments**: Payment records (student_id, fee_type_id, amount_paid, payment_date, payment_method, term, receipt_number)

### Content & Communication
- **news_posts**: System announcements (user_id, content, title, media_url, media_type)
- **news_comments**: Comments on announcements

## Key Functions

### Fee Calculation (`functions.php`)
- `calculateFeePercentage()`: Calculates % of fees paid by summing all fee_types for student's grade (or 'All')
- `getStudentsByFeePercentageAndGrade()`: Filters students by payment percentage

### Fee Management (`fees.php`)
- Single student payment: INSERT into fee_payments
- Grade-wide fee assignment: Gets all active students in a grade, adds same payment to each
- Fee type management: Create/Edit/Delete fee_types

### Authentication (`auth.php`)
- Session-based authentication with role checking
- Functions: `getCurrentUser()`, `requireLogin()`, etc.

## Current Fee Flow

1. Admin/Finance Teacher adds fee_type (fee_name, amount, grade or 'All')
2. To assign to students:
   - Option A: Add individual payment with amount_paid > 0
   - Option B: Use "Add to Grade" - gets all students in that grade, inserts with amount_paid value
3. Fee percentage calculated as: SUM(amount_paid) / SUM(fee_types where grade matches) * 100

## Issues Identified

### Current Problem
When assigning fee types to grades/students, the system only creates payment records when admin explicitly adds a payment. There's no automatic creation of fee records with 0 paid amount when a fee type is added.

## Implementation Plan

### Task 2: Fee Type Auto-Assignment
**Change**: When a fee_type is added/updated, automatically create student_fee records with 0 paid

New table needed:
- **student_fees**: Links students to fee types (student_id, fee_type_id, amount_due, amount_paid, created_at)

**Flow**:
1. Add fee_type
2. If grade = 'All': Create student_fee record for EVERY student
3. If grade = specific: Create student_fee record for all active students in that grade
4. Update fee calculation to use student_fees table

### Task 3: SMS API Integration
Libraries: Twilio (or Africastalking, JAJAH)
New table:
- **sms_logs**: Track SMS sent (recipient, message, status, timestamp)
- **sms_templates**: Pre-written messages

### Task 4: Parent Contact Section
Add sidebar link → contact.php
Form to:
- Select grade or individual parent
- View parent contacts (phone, email from students table)
- Send SMS via integrated API

### Task 5: M-Pesa STK Push
Integration with M-Pesa API (using Safaricom sandbox or live)
New table:
- **mpesa_transactions**: Transaction tracking (student_id, amount, mpesa_ref, status)

Flow:
1. On fees page, show "Pay via M-Pesa" button
2. Trigger STK push to 0758955122
3. Listen for callback, update fee_payments on success
