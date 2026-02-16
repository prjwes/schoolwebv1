# School Management System - User Guide

## For Finance Staff

### How to Assign Fees to Students

#### Step 1: Access Fees Management
1. Log in as Admin, HOI, DHOI, or Finance_Teacher
2. Click "Fees" in the sidebar
3. You'll see the Fee Management page

#### Step 2: Create a Fee Type (First Time Only)
1. Click "Add Fee Type" button
2. Fill in:
   - **Fee Name**: e.g., "Tuition Fees Term 1"
   - **Fee Amount**: e.g., 5000
   - **Grade**: Select which grade this fee applies to
3. Click "Add Fee Type"

#### Step 3: Assign Fee to Students
1. Click "Assign Fee" button
2. **Select Fee Type**: Choose the fee you want to assign
3. **Choose Recipients**:
   - **All Students**: Assigns to all active students
   - **Specific Grade**: Select a grade to assign to that grade only
4. **Set Due Date**: When the fee should be paid by
5. Click "Assign Fee"

#### Result
- Each student gets the fee with 0 paid amount initially
- Students will see it on their "My Fees" page
- Status shows as "pending" until payment is made

### How to Record Manual Payments

1. Click "Add Payment" button
2. Fill in:
   - **Student**: Select the student
   - **Fee Type**: Select which fee they're paying
   - **Amount Paid**: How much they paid
   - **Payment Date**: When they paid
   - **Payment Method**: Cash/M-Pesa/Bank Transfer/Card
   - **Term**: Which term (1, 2, 3, or Full Year)
   - **Remarks**: Any notes (optional)
3. Click "Record Payment"

### How to Update Student Fees
- Use the fee assignment feature to add outstanding balances
- Record manual payments as students pay
- System automatically calculates "pending", "partial", or "paid" status

---

## For Finance Staff: Send SMS to Parents

### Access Contact Parents Page
1. Log in as Admin, HOI, DHOI, or Finance_Teacher
2. Click "Contact Parents" in the sidebar
3. You'll see the SMS dashboard

### Send Message to All Parents
1. Select "Send to All Parents"
2. Type your message (max 160 characters)
3. Click "Send SMS"
4. See the confirmation of how many were sent

### Send to Specific Grade
1. Select "Send to Grade"
2. Click the grade button (Grade 5, Grade 7, etc.)
3. Type your message
4. Click "Send SMS"

### Send to Individual Parent
1. Select "Send to Individual Parent"
2. Choose the parent from dropdown
3. Type your message
4. Click "Send SMS"

### Example Messages
- "Dear parent, please settle outstanding school fees by end of term"
- "School closed today due to weather. Will resume tomorrow"
- "Your child's report card is ready. Please collect from office"
- "Fee payment reminder: Term 1 fees are due on 31st March"

### Track Messages
- All sent messages appear in "Recent Messages" table
- Shows date, phone, message preview, status, and who sent it
- Status shows "Sent" or "Failed"

---

## For Students: View Your Fees

### Access My Fees Page
1. Log in as a student
2. Click "My Fees" in the sidebar
3. You'll see:
   - Fee percentage paid (e.g., 45%)
   - Total number of payments made
   - All assigned fees

### What You'll See
- **Fee Type**: Name of the fee (e.g., "Tuition Fees")
- **Amount Due**: Total amount for this fee
- **Amount Paid**: How much you've already paid
- **Status**: Pending (unpaid) / Partial (partially paid) / Paid
- **Due Date**: When the fee should be paid by

### Example Fee Display
```
Fee Type          Amount Due    Amount Paid    Status      Due Date
Tuition Fees      5000.00       2000.00        Partial     2024-03-31
Sports Fund       500.00        0.00           Pending     2024-03-31
```

---

## For Students: Pay via M-Pesa

### How to Make Payment

#### Step 1: Open My Fees Page
1. Log in as a student
2. Click "My Fees"
3. Scroll to "Pay via M-Pesa" section

#### Step 2: Send STK Prompt
1. Enter your phone number in format: **254712345678**
   - Must start with 254
   - Example: 254712345678, 254722123456
2. Click "Send STK Prompt"
3. You'll see message: "STK prompt sent to your phone"

#### Step 3: Complete Payment
1. Look at your phone
2. You'll see M-Pesa popup asking for PIN
3. Enter your M-Pesa PIN
4. Wait for confirmation
5. Check your account - fees will update automatically

#### Payment Details
- **Paybill Number**: 0758955122
- **School Code**: EBUSHIBO
- **Amount**: Enter the amount you want to pay

#### Example
1. Phone prompt appears asking for PIN
2. You enter your 4-digit M-Pesa PIN
3. Money deducted from your account
4. You get M-Pesa receipt
5. Finance staff see payment in system automatically

### Troubleshooting

**"Phone number must start with 254"**
- Make sure you entered format correctly
- Example: 254712345678 ✅
- Wrong: 0712345678 ❌
- Wrong: +254712345678 ❌

**"STK prompt didn't appear"**
- Check your phone connection
- Check you have M-Pesa service active
- Try again after a few seconds

**"Payment didn't update in system"**
- Wait a few minutes for system to sync
- Refresh the page
- Contact finance office if still not updated

---

## For Parents: What You'll Receive

### Fee Reminder SMS
Example: "Your child needs to pay 5000 KES school fees by 31st March. Please make arrangements"

### Payment Confirmation SMS
Example: "Thank you for paying school fees. Your child's account has been updated. Thank you"

### General Announcements
Example: "School will close on 15th April for holiday. Reopen on 20th April"

### How to Pay
1. Get SMS with payment details
2. Go to **Paybill 0758955122**
3. Enter school code if required
4. Send amount required
5. Payment processes immediately

---

## Dashboard & Reports

### For Finance Staff: Fee Payment Report
1. Click "Filter & Print" on Fees page
2. Set fee percentage range (0-100%)
3. Optionally filter by grade
4. Click "Print List"
5. See students and their current fee payment status

### View Payment History
1. Scroll to "Payment History" section
2. See all recorded payments:
   - Student name and ID
   - Which fee they paid
   - Amount paid
   - Date paid
   - Payment method
   - Receipt number
3. Can edit or delete payments if needed

---

## Best Practices

### For Finance Staff
1. **Create fee types at start of term**: Makes it easier to assign
2. **Use clear fee names**: "Tuition Term 1" instead of "Fee1"
3. **Send payment reminders early**: Don't wait until due date
4. **Update payments regularly**: Keep records current
5. **Check SMS logs**: Ensure messages are being sent successfully

### For Students
1. **Check My Fees regularly**: Know what you owe
2. **Pay early**: Don't wait until deadline
3. **Keep M-Pesa receipt**: For proof of payment
4. **Report issues**: Tell office if payment doesn't update

### For Parents
1. **Respond to SMS reminders**: Don't ignore payment notices
2. **Pay before due date**: Avoid late fees
3. **Contact school if issues**: Ask questions about amounts
4. **Update phone number**: Ensure you receive messages

---

## Common Questions

**Q: Why do students get 0 paid amount initially?**
A: This allows tracking of partial payments. As students pay portions of fees, the amount updates to show exactly what they've paid and what's remaining.

**Q: Can I delete an assigned fee?**
A: Not directly. Instead, mark it as not applicable in records or contact your system administrator for assistance.

**Q: What if student pays partially?**
A: Status shows "Partial" and remaining amount is displayed. They can pay the rest later, and system tracks both payments.

**Q: Does M-Pesa payment show immediately?**
A: Yes, within a few minutes. If not, refresh the page or contact finance office.

**Q: What if SMS fails?**
A: Check SMS logs page to see status. Failures are recorded with error details. Usually due to invalid phone number or network issues.

**Q: Can I send SMS outside school hours?**
A: Yes, SMS can be sent 24/7. But consider sending during reasonable hours for parents.

**Q: What if I pay more than the amount due?**
A: The extra amount can be used for future fees or refunded based on school policy.

---

## Getting Help

**For Finance Staff Questions**:
- Check SETUP_GUIDE.md for technical setup
- Check SYSTEM_ARCHITECTURE.md for how system works
- Ask your system administrator

**For SMS Issues**:
- Check phone numbers are in correct format (254XXXXXXXXX)
- Check SMS provider credits
- Check API credentials are set correctly

**For M-Pesa Issues**:
- Ensure phone number format is 254XXXXXXXXX
- Check M-Pesa account has balance
- Verify school paybill number: 0758955122

**For Database Issues**:
- Run initialize script: /setup/initialize_fee_system.php
- Check all tables are created
- Contact hosting provider if tables won't create

---

## Summary

**Fee Management**: Assign fees once, students pay in portions, track automatically
**SMS Notifications**: Send bulk messages to parents about fees and school updates  
**M-Pesa Payments**: Students pay directly from phone, system updates instantly

All features are designed to be simple, fast, and reliable!
