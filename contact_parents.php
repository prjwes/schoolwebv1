<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user = getCurrentUser();
$role = $user['role'];
$conn = getDBConnection();

// Only finance staff can access
if (!in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])) {
    header('Location: dashboard.php');
    exit();
}

// Get list of grades
$grades = $conn->query("SELECT DISTINCT grade FROM students WHERE status = 'Active' ORDER BY grade")->fetch_all(MYSQLI_ASSOC);

// Get all parents with phone numbers
$all_parents = $conn->query("
    SELECT DISTINCT u.id, u.full_name, u.parent_phone, s.student_id
    FROM users u
    JOIN students s ON s.user_id = u.id
    WHERE u.parent_phone IS NOT NULL AND u.parent_phone != '' AND s.status = 'Active'
    ORDER BY u.full_name
")->fetch_all(MYSQLI_ASSOC);

// Get SMS logs for history
$sms_logs = $conn->query("
    SELECT sl.*, u.full_name as sent_by_name
    FROM sms_logs sl
    LEFT JOIN users u ON sl.sent_by = u.id
    ORDER BY sl.created_at DESC
    LIMIT 30
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Parents - EBUSHIBO J.S PORTAL</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .contact-section {
            background: white;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #333;
        }
        
        .option-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .option-card:hover {
            border-color: #007bff;
            background: #f8f9ff;
        }
        
        .option-card.selected {
            border-color: #007bff;
            background: #f0f4ff;
        }
        
        .message-area {
            width: 100%;
            min-height: 120px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: Arial, sans-serif;
            resize: vertical;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-box {
            background: #f5f5f5;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #007bff;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 8px;
        }
        
        .log-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        
        .log-table th, .log-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .log-table th {
            background: #f5f5f5;
            font-weight: 600;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-sent {
            background: #d4edda;
            color: #155724;
        }
        
        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .grade-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .grade-btn {
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .grade-btn:hover {
            border-color: #007bff;
            color: #007bff;
        }
        
        .grade-btn.selected {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Contact Parents</h1>
                <p>Send SMS messages to parents and track communication history</p>
            </div>

            <!-- Stats -->
            <div class="stats">
                <div class="stat-box">
                    <div class="stat-number"><?php echo count($all_parents); ?></div>
                    <div class="stat-label">Total Parents</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo count($grades); ?></div>
                    <div class="stat-label">Grades</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number" id="sent-count">0</div>
                    <div class="stat-label">Messages Sent</div>
                </div>
            </div>

            <!-- Send SMS Section -->
            <div class="contact-section">
                <h2 class="section-title">Send SMS Message</h2>
                
                <form id="smsForm" method="POST">
                    <div class="form-group">
                        <label>Select Recipients</label>
                        <div style="margin-bottom: 16px;">
                            <label style="display: flex; align-items: center; margin-bottom: 12px; cursor: pointer;">
                                <input type="radio" name="recipient_type" value="all" checked style="margin-right: 8px;">
                                <span>Send to All Parents</span>
                            </label>
                            <label style="display: flex; align-items: center; margin-bottom: 12px; cursor: pointer;">
                                <input type="radio" name="recipient_type" value="grade" style="margin-right: 8px;">
                                <span>Send to Grade</span>
                            </label>
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="radio" name="recipient_type" value="single" style="margin-right: 8px;">
                                <span>Send to Individual Parent</span>
                            </label>
                        </div>
                    </div>

                    <!-- Grade Selection -->
                    <div id="gradeSection" style="display: none; margin-bottom: 16px;">
                        <label>Select Grade</label>
                        <div class="grade-list" id="gradeList">
                            <?php foreach ($grades as $grade): ?>
                                <button type="button" class="grade-btn" data-grade="<?php echo htmlspecialchars($grade['grade']); ?>">
                                    <?php echo htmlspecialchars($grade['grade']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="selected_grade" id="selectedGrade">
                    </div>

                    <!-- Individual Parent Selection -->
                    <div id="parentSection" style="display: none; margin-bottom: 16px;">
                        <label>Select Parent</label>
                        <select name="selected_parent" id="selectedParent" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                            <option value="">Choose a parent...</option>
                            <?php foreach ($all_parents as $parent): ?>
                                <option value="<?php echo $parent['id']; ?>">
                                    <?php echo htmlspecialchars($parent['full_name']); ?> (<?php echo $parent['parent_phone']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" class="message-area" placeholder="Type your message here... (Max 160 characters for SMS)" maxlength="160"></textarea>
                        <small style="color: #666; display: block; margin-top: 4px;">
                            Characters: <span id="charCount">0</span>/160
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                        Send SMS
                    </button>
                </form>
            </div>

            <!-- SMS History -->
            <div class="contact-section">
                <h2 class="section-title">Recent Messages (Last 30)</h2>
                
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Phone</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Sent By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sms_logs as $log): ?>
                            <tr>
                                <td><?php echo date('d M Y H:i', strtotime($log['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($log['phone_number']); ?></td>
                                <td><?php echo htmlspecialchars(substr($log['message'], 0, 50)); ?>...</td>
                                <td>
                                    <span class="status-badge status-<?php echo $log['status']; ?>">
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['sent_by_name'] ?? 'System'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <script>
        // Handle recipient type selection
        document.querySelectorAll('input[name="recipient_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('gradeSection').style.display = this.value === 'grade' ? 'block' : 'none';
                document.getElementById('parentSection').style.display = this.value === 'single' ? 'block' : 'none';
            });
        });

        // Handle grade selection
        document.querySelectorAll('.grade-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.grade-btn').forEach(b => b.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('selectedGrade').value = this.dataset.grade;
            });
        });

        // Character counter
        document.querySelector('textarea[name="message"]').addEventListener('input', function() {
            document.getElementById('charCount').textContent = this.value.length;
        });

        // Form submission
        document.getElementById('smsForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const recipientType = document.querySelector('input[name="recipient_type"]:checked').value;
            const message = document.querySelector('textarea[name="message"]').value;

            if (!message.trim()) {
                alert('Please enter a message');
                return;
            }

            let apiAction = '';
            let formData = new FormData();
            formData.append('message', message);

            if (recipientType === 'all') {
                apiAction = 'send_all';
            } else if (recipientType === 'grade') {
                const grade = document.getElementById('selectedGrade').value;
                if (!grade) {
                    alert('Please select a grade');
                    return;
                }
                apiAction = 'send_grade';
                formData.append('grade', grade);
            } else if (recipientType === 'single') {
                const parentId = document.getElementById('selectedParent').value;
                if (!parentId) {
                    alert('Please select a parent');
                    return;
                }
                apiAction = 'send_single';
                formData.append('parent_id', parentId);
            }

            try {
                const response = await fetch(`api/send_sms.php?action=${apiAction}`, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert(result.message || 'SMS sent successfully!');
                    document.getElementById('smsForm').reset();
                    document.getElementById('charCount').textContent = '0';
                    // Reload page to show new logs
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert('Error: ' + (result.error || 'Failed to send SMS'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while sending SMS');
            }
        });
    </script>
</body>
</html>
