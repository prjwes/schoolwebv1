# School Management System - Deployment Guide

## Pre-Deployment Checklist

Before deploying the updated code, ensure:

- [ ] Database backup created
- [ ] Server accessible and running
- [ ] PHP 7.4+ installed
- [ ] Write permissions on `/uploads/` directory
- [ ] All files backed up

---

## Files to Deploy

### Modified Files (MUST UPDATE)

1. **dashboard.php** - Dashboard student view fix
   - Location: Root directory
   - Size: Updated
   - Purpose: Fix 500 error on student dashboard

2. **fees.php** - Fees management with auto-assignment
   - Location: Root directory
   - Size: Expanded (added ~100 lines)
   - Purpose: Auto-assign fees, add filtering

3. **exams.php** - Exam display improvements
   - Location: Root directory
   - Size: Expanded (added ~100 lines)
   - Purpose: Card-based layout with modal details

4. **student_details.php** - Student profile enhancements
   - Location: Root directory
   - Size: Expanded (added ~150 lines)
   - Purpose: Photo upload, fees display, print results

### Documentation Files (Reference Only)

- IMPLEMENTATION_COMPLETE.md
- QUICK_REFERENCE.md
- DEPLOYMENT_GUIDE.md
- UPDATES_SUMMARY.md

---

## Deployment Steps

### Step 1: Backup Current Code
```bash
cp dashboard.php dashboard.php.backup
cp fees.php fees.php.backup
cp exams.php exams.php.backup
cp student_details.php student_details.php.backup
```

### Step 2: Prepare Upload Directory
```bash
# Ensure uploads directory exists
mkdir -p uploads/student_photos
chmod 755 uploads
chmod 755 uploads/student_photos
```

### Step 3: Upload Updated Files
Upload the following files to your server's root directory:
- dashboard.php
- fees.php
- exams.php
- student_details.php

### Step 4: Verify PHP Syntax
```bash
php -l dashboard.php
php -l fees.php
php -l exams.php
php -l student_details.php
```

All should return: `No syntax errors detected`

### Step 5: Test Dashboard
1. Login as Student account
2. Dashboard should load without errors
3. Check all stats display correctly

### Step 6: Test Fee Management
1. Login as Admin/HOI/Finance Teacher
2. Go to Fees page
3. Add new fee type
4. Verify success message shows assignment count
5. Go to student details - verify fee appears

### Step 7: Test Exam Display
1. Go to Exams page
2. Verify exams show as cards in grid
3. Click on exam card
4. Modal should open with details
5. Test View Results, Export, Delete buttons

### Step 8: Test Photo Upload
1. Go to Student Details page (any student)
2. Click Edit button
3. Upload a valid image file
4. Success message should appear
5. Verify photo persists

### Step 9: Test Payment Filtering
1. Go to Fees page
2. Click "Filter by Type"
3. Select fee type
4. Payment history should filter
5. Test all filter buttons

### Step 10: Production Verification
```bash
# Check error logs
tail -f /var/log/php-errors.log

# Monitor database connections
# Ensure no connection errors

# Test from different browsers
# Verify responsive design works
```

---

## Rollback Procedure

If issues occur, rollback is simple:

### Rollback Steps
```bash
# Stop web server (optional, for safety)
# sudo service apache2 stop

# Restore backup files
mv dashboard.php.backup dashboard.php
mv fees.php.backup fees.php
mv exams.php.backup exams.php
mv student_details.php.backup student_details.php

# Restart web server
# sudo service apache2 start

# Test application
```

---

## Common Deployment Issues

### Issue 1: "Permission Denied" on uploads directory
**Solution:**
```bash
chmod 755 uploads
chmod 755 uploads/student_photos
chown www-data:www-data uploads
chown www-data:www-data uploads/student_photos
```

### Issue 2: "Class 'PDO' not found"
**Solution:** Ensure PDO PHP extension is enabled
```bash
php -m | grep pdo
```

### Issue 3: "Cannot write to database"
**Solution:** Check database user permissions and connection parameters in `config/database.php`

### Issue 4: Dashboard still shows 500 error
**Solution:** 
- Check PHP error logs
- Verify `getStudentByUserId()` function exists
- Ensure student records exist in database
- Test database connection

### Issue 5: Photo upload fails
**Solution:**
- Verify uploads directory exists and is writable
- Check file size limit in PHP ini (post_max_size, upload_max_filesize)
- Ensure MIME type is valid (JPEG, PNG, GIF)

---

## Performance Notes

### File Upload Impact
- Photos are stored in `/uploads/student_photos/`
- Recommend implementing cleanup for old/unused photos
- Monitor disk space usage

### Database Query Impact
- Auto-assignment query runs on fee creation only
- Payment filtering queries are indexed
- No significant performance impact

### Frontend Impact
- Modal overlay may use additional memory on older browsers
- Grid layout responsive - tested on all screen sizes
- No external dependencies added

---

## Security Verification

After deployment, verify:

- [ ] File uploads only accept valid image types
- [ ] File size limits enforced (5MB max)
- [ ] MIME type validation working
- [ ] No SQL injection vulnerabilities
- [ ] All user inputs sanitized
- [ ] Error messages don't expose system paths

---

## Post-Deployment Testing

Run these tests in order:

### 1. Basic Functionality
- [ ] Login works for all user types
- [ ] Dashboard loads for all roles
- [ ] Navigation menu works
- [ ] All pages load without errors

### 2. Student Features
- [ ] Dashboard shows stats
- [ ] Student can view own details
- [ ] Student can view own fees
- [ ] Student can view own exam results
- [ ] Student can see clubs

### 3. Admin Features
- [ ] Can add new fee types
- [ ] Fees auto-assign to students
- [ ] Can view student details
- [ ] Can upload student photos
- [ ] Can filter payments
- [ ] Can view exams in card format
- [ ] Can click exam cards for details
- [ ] Can print exam results

### 4. Data Integrity
- [ ] No data loss
- [ ] All student records intact
- [ ] All fee records intact
- [ ] All exam records intact
- [ ] Payment history complete

### 5. Error Handling
- [ ] Try invalid file upload
- [ ] Try oversized file
- [ ] Try loading with slow connection
- [ ] Test with JavaScript disabled (basic functionality)

---

## Maintenance Notes

### Regular Checks
- Monitor `/uploads/student_photos/` disk usage
- Review PHP error logs weekly
- Check database for orphaned records
- Verify backup strategy is working

### Recommended Backups
- Daily database backups
- Weekly file backups
- Monthly full system backups

---

## Support Contact

For issues or questions:
1. Check error logs in PHP error file
2. Review QUICK_REFERENCE.md for user guide
3. Check IMPLEMENTATION_COMPLETE.md for technical details
4. Contact system administrator

---

## Deployment Confirmation

When deployment is complete and tested, confirm:

- ✅ All 4 PHP files updated successfully
- ✅ No errors in PHP syntax
- ✅ Dashboard works for students
- ✅ Fees auto-assign correctly
- ✅ Exams display in cards
- ✅ Photos upload successfully
- ✅ Payments filter by type
- ✅ No errors in application logs
- ✅ Database connections stable
- ✅ Backups created

---

## Version Information

**Version:** 1.0
**Release Date:** 2024
**Status:** Production Ready
**Compatibility:** PHP 7.4+, MySQL 5.7+

---

## Sign-Off

Deployment completed by: ________________
Date: ________________
Verified by: ________________
