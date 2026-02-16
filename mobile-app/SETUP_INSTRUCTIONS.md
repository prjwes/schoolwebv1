# School Management System - Mobile App Setup

## Installation Requirements

- Node.js and npm installed
- Expo CLI installed globally: `npm install -g expo-cli`
- Android Studio with Android SDK (for APK generation)
- EAS CLI for building: `npm install -g eas-cli`

## Quick Start

1. **Navigate to mobile app directory:**
   \`\`\`bash
   cd mobile-app
   \`\`\`

2. **Install dependencies:**
   \`\`\`bash
   npm install
   \`\`\`

3. **Update API URL:**
   - Open `src/screens/LoginScreen.tsx`
   - Change `API_URL` to match your server: `https://schoolweb.ct.ws`
   - Repeat for other screen files that use `API_URL`

4. **Start development server:**
   \`\`\`bash
   npm start
   \`\`\`

5. **Run on Android device/emulator:**
   \`\`\`bash
   npm run android
   \`\`\`

## Building APK for Distribution

### Method 1: Using EAS (Recommended)

1. **Login to Expo:**
   \`\`\`bash
   eas login
   \`\`\`

2. **Build APK:**
   \`\`\`bash
   eas build --platform android --local
   \`\`\`

3. Download the APK from the provided link and install on your Android phone.

### Method 2: Local Build

1. **Install Android build tools**
2. **Generate APK:**
   \`\`\`bash
   eas build --platform android --local
   \`\`\`

## Backend API Requirements

Ensure your PHP backend has these API endpoints:

### Authentication
- `POST /api/login.php` - User login

### Student API
- `GET /api/student/dashboard.php` - Dashboard data
- `GET /api/student/exam-results.php` - Exam results
- `GET /api/student/timetable.php` - Student timetable
- `GET /api/student/fees.php` - Fees information
- `GET /api/student/clubs.php` - Student clubs

### Teacher API
- `GET /api/teacher/dashboard.php` - Dashboard data
- `GET /api/teacher/timetable.php` - Teacher timetable
- `GET /api/teacher/students.php` - Students list
- `GET /api/teacher/exams.php` - Exams list

### Admin API
- `GET /api/admin/dashboard.php` - Dashboard data
- `GET /api/admin/students.php` - Students list
- `GET /api/admin/teachers.php` - Teachers list
- `GET /api/admin/exams.php` - Exams list

All responses should return:
\`\`\`json
{
  "success": true/false,
  "message": "Optional message",
  "data": {...}
}
\`\`\`

## Login Credentials

- **Students:** Username = Full Name, Password = student.grade.year (e.g., student.9.2025)
- **Teachers:** Username/Email, Password = assigned by admin
- **Admin:** Username/Email, Password = assigned during setup

## Troubleshooting

1. **White screen on startup:** Check API URL configuration
2. **API connection errors:** Ensure backend server is running
3. **Build errors:** Delete `node_modules` and `package-lock.json`, then run `npm install` again
4. **Android SDK not found:** Configure Android SDK path in environment variables

## Features by User Type

### Students
- View dashboard with statistics
- Check exam results organized by exam
- View personal timetable
- Check fees status and payment history
- View clubs they're members of
- Manage profile

### Teachers
- Dashboard with class statistics
- View personal timetable across all grades
- Search and view students
- View exams they teach
- Manage profile

### Admin
- Complete dashboard overview
- Manage all students
- Manage all teachers
- View all exams
- System settings and preferences
- Manage profile

## Next Steps

1. Create corresponding API endpoints in your PHP backend
2. Test each endpoint with appropriate data
3. Build and test the APK on real Android devices
4. Configure Firebase for push notifications (optional)
5. Deploy to Google Play Store (requires signing and account setup)
