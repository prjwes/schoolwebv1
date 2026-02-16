<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user = getCurrentUser();
$conn = getDBConnection();

// Static KNEC links that are always available
$static_websites = [
    [
        'website_name' => 'CBA Portal',
        'website_url' => 'https://cba.knec.ac.ke/',
        'description' => 'Competency Based Assessment Portal for CBC curriculum',
        'icon' => '📝'
    ],
    [
        'website_name' => 'Placement Portal',
        'website_url' => 'https://placement.education.go.ke/',
        'description' => 'Student Placement and Course Allocation Portal',
        'icon' => '📊'
    ],
    [
        'website_name' => 'TSC Portal',
        'website_url' => 'https://portal.tsc.go.ke/',
        'description' => 'Teachers Service Commission Official Portal',
        'icon' => '🏛️'
    ],
    [
        'website_name' => 'PaySlip Portal',
        'website_url' => 'https://payslips.tsc.go.ke/',
        'description' => 'Teachers Payroll and Salary Management System',
        'icon' => '💰'
    ],
    [
        'website_name' => 'TPAD Portal',
        'website_url' => 'https://tpad.tsc.go.ke/',
        'description' => 'Teacher Performance Appraisal and Development Portal',
        'icon' => '📈'
    ],
    [
        'website_name' => 'Ministry of Education',
        'website_url' => 'https://www.education.go.ke/',
        'description' => 'Official Ministry of Education and Research Portal',
        'icon' => '🎓'
    ]
];

$dynamic_websites = [];
if ($conn && function_exists('tableExists') && tableExists('knec_websites')) {
    $stmt = $conn->prepare("UPDATE knec_notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE");
    if ($stmt) {
        $stmt->bind_param("i", $user['id']);
        @$stmt->execute();
        $stmt->close();
    }

    // Get all KNEC websites
    $result = @$conn->query("SELECT * FROM knec_websites ORDER BY website_name");
    if ($result) {
        $dynamic_websites = $result->fetch_all(MYSQLI_ASSOC);
    }
}

$websites = !empty($dynamic_websites) ? $dynamic_websites : $static_websites;

// Get unread notifications for user
$unread_count = 0;
if ($conn && function_exists('tableExists') && tableExists('knec_notifications')) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM knec_notifications WHERE user_id = ? AND is_read = FALSE");
    if ($stmt) {
        $stmt->bind_param("i", $user['id']);
        @$stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $unread_count = $row['count'] ?? 0;
        $stmt->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KNEC Portal - Schoolweb</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .knec-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }
        
        .knec-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 32px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .knec-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            border-color: var(--primary-color);
        }
        
        .knec-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }
        
        .knec-icon {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }
        
        .knec-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-primary);
        }
        
        .knec-description {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 24px;
            min-height: 40px;
        }
        
        .knec-button {
            display: inline-block;
            padding: 12px 32px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .knec-button:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }
        
        .update-badge {
            position: absolute;
            top: 16px;
            right: 16px;
            background: #ef4444;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .info-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        
        .info-banner h2 {
            margin: 0 0 8px 0;
            font-size: 20px;
        }
        
        .info-banner p {
            margin: 0;
            opacity: 0.9;
        }
        
        .no-portals {
            text-align: center;
            padding: 48px 24px;
            color: var(--text-secondary);
        }
        
        .no-portals h3 {
            margin-bottom: 12px;
            color: var(--text-primary);
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>KNEC Portals</h1>
                <p>Quick access to external education and aportals</p>
            </div>

            <div class="info-banner">
                <h2>📢 Stay Updated</h2>
                <p>This portal provides quick access to important KNEC and education portals.</p>
            </div>

            <?php if (empty($websites)): ?>
                <div class="no-portals">
                    <h3>No Portals Available</h3>
                    <p>Portals will be added by administrators. Check back later for available external portals.</p>
                </div>
            <?php else: ?>
                <div class="knec-grid">
                    <?php foreach ($websites as $website): ?>
                        <div class="knec-card">
                            <?php if (!empty($website['has_updates']) && $website['has_updates']): ?>
                                <span class="update-badge">NEW UPDATE</span>
                            <?php endif; ?>
                            
                            <span class="knec-icon">
                                <?php echo $website['icon'] ?? '🌐'; ?>
                            </span>
                            
                            <h3 class="knec-title"><?php echo htmlspecialchars($website['website_name'] ?? 'Portal'); ?></h3>
                            
                            <p class="knec-description">
                                <?php echo htmlspecialchars($website['description'] ?? 'Access external portal and resources'); ?>
                            </p>
                            
                            <a href="<?php echo htmlspecialchars($website['website_url'] ?? '#'); ?>" 
                               target="_blank" 
                               rel="noopener noreferrer" 
                               class="knec-button"
                               title="Opens in new window">
                                Access Portal →
                            </a>
                            
                            <?php if (!empty($website['last_checked'])): ?>
                                <div style="margin-top: 16px; font-size: 12px; color: var(--text-tertiary);">
                                    Last checked: <?php echo htmlspecialchars(date('M d, Y', strtotime($website['last_checked']))); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="table-container" style="margin-top: 32px;">
                <div class="table-header">
                    <h3>Portal Information</h3>
                </div>
                <div style="padding: 24px;">
                    <h4 style="margin-bottom: 16px;">Important Notes:</h4>
                    <ul style="line-height: 2;">
                        <li>All portals open in a new window for your convenience</li>
                        <li>Updates are checked periodically - you'll see notifications when changes are detected</li>
                        <li>Make sure you have valid credentials for each portal</li>
                        <li>Keep your login information secure and never share with unauthorized persons</li>
                        <li>Report any access issues to the system administrator</li>
                    </ul>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/theme.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
