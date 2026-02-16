<?php
/**
 * Fee Management API
 * Handles fee type assignments to students
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

requireLogin();
$user = getCurrentUser();
$conn = getDBConnection();

// Check authorization
if (!in_array($user['role'], ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$action = isset($_GET['action']) ? sanitize($_GET['action']) : '';

// Assign fee type to single student with initial 0 paid
if ($action === 'assign_single') {
    $student_id = intval($_POST['student_id'] ?? 0);
    $fee_type_id = intval($_POST['fee_type_id'] ?? 0);
    $amount_due = floatval($_POST['amount_due'] ?? 0);
    $due_date = sanitize($_POST['due_date'] ?? date('Y-m-d'));
    
    if (!$student_id || !$fee_type_id || !$amount_due) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit();
    }
    
    // Check if assignment already exists
    $check = $conn->prepare("SELECT id FROM student_fees WHERE student_id = ? AND fee_type_id = ?");
    $check->bind_param("ii", $student_id, $fee_type_id);
    $check->execute();
    $existing = $check->get_result();
    $check->close();
    
    if ($existing->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Fee already assigned to this student']);
        exit();
    }
    
    // Assign fee with 0 paid amount
    $stmt = $conn->prepare("INSERT INTO student_fees (student_id, fee_type_id, amount_due, amount_paid, status, due_date) VALUES (?, ?, ?, 0.00, 'pending', ?)");
    $stmt->bind_param("iids", $student_id, $fee_type_id, $amount_due, $due_date);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Fee assigned successfully',
            'fee_id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
}

// Assign fee type to all students in a grade
elseif ($action === 'assign_grade') {
    $grade = sanitize($_POST['grade'] ?? '');
    $fee_type_id = intval($_POST['fee_type_id'] ?? 0);
    $amount_due = floatval($_POST['amount_due'] ?? 0);
    $due_date = sanitize($_POST['due_date'] ?? date('Y-m-d'));
    
    if (!$grade || !$fee_type_id || !$amount_due) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit();
    }
    
    // Get all active students in grade
    $stmt = $conn->prepare("SELECT id FROM students WHERE grade = ? AND status = 'Active'");
    $stmt->bind_param("s", $grade);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $assigned_count = 0;
    $skipped_count = 0;
    
    foreach ($students as $student) {
        // Check if already assigned
        $check = $conn->prepare("SELECT id FROM student_fees WHERE student_id = ? AND fee_type_id = ?");
        $check->bind_param("ii", $student['id'], $fee_type_id);
        $check->execute();
        $existing = $check->get_result();
        $check->close();
        
        if ($existing->num_rows > 0) {
            $skipped_count++;
            continue;
        }
        
        // Assign fee with 0 paid
        $insert = $conn->prepare("INSERT INTO student_fees (student_id, fee_type_id, amount_due, amount_paid, status, due_date) VALUES (?, ?, ?, 0.00, 'pending', ?)");
        $insert->bind_param("iids", $student['id'], $fee_type_id, $amount_due, $due_date);
        
        if ($insert->execute()) {
            $assigned_count++;
        }
        $insert->close();
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Fees assigned to $assigned_count students",
        'assigned' => $assigned_count,
        'skipped' => $skipped_count
    ]);
}

// Assign fee type to all students
elseif ($action === 'assign_all') {
    $fee_type_id = intval($_POST['fee_type_id'] ?? 0);
    $amount_due = floatval($_POST['amount_due'] ?? 0);
    $due_date = sanitize($_POST['due_date'] ?? date('Y-m-d'));
    
    if (!$fee_type_id || !$amount_due) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit();
    }
    
    // Get all active students
    $stmt = $conn->prepare("SELECT id FROM students WHERE status = 'Active'");
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $assigned_count = 0;
    $skipped_count = 0;
    
    foreach ($students as $student) {
        // Check if already assigned
        $check = $conn->prepare("SELECT id FROM student_fees WHERE student_id = ? AND fee_type_id = ?");
        $check->bind_param("ii", $student['id'], $fee_type_id);
        $check->execute();
        $existing = $check->get_result();
        $check->close();
        
        if ($existing->num_rows > 0) {
            $skipped_count++;
            continue;
        }
        
        // Assign fee with 0 paid
        $insert = $conn->prepare("INSERT INTO student_fees (student_id, fee_type_id, amount_due, amount_paid, status, due_date) VALUES (?, ?, ?, 0.00, 'pending', ?)");
        $insert->bind_param("iids", $student['id'], $fee_type_id, $amount_due, $due_date);
        
        if ($insert->execute()) {
            $assigned_count++;
        }
        $insert->close();
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Fees assigned to $assigned_count students",
        'assigned' => $assigned_count,
        'skipped' => $skipped_count
    ]);
}

// Get student's fees
elseif ($action === 'get_student_fees') {
    $student_id = intval($_GET['student_id'] ?? 0);
    
    $stmt = $conn->prepare("SELECT sf.*, ft.fee_name FROM student_fees sf JOIN fee_types ft ON sf.fee_type_id = ft.id WHERE sf.student_id = ? ORDER BY sf.due_date");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $fees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode(['success' => true, 'fees' => $fees]);
}

// Update payment amount
elseif ($action === 'update_payment') {
    $student_fee_id = intval($_POST['student_fee_id'] ?? 0);
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    
    if (!$student_fee_id || $amount_paid < 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit();
    }
    
    // Get the fee details
    $get = $conn->prepare("SELECT amount_due FROM student_fees WHERE id = ?");
    $get->bind_param("i", $student_fee_id);
    $get->execute();
    $fee = $get->get_result()->fetch_assoc();
    $get->close();
    
    if (!$fee) {
        echo json_encode(['success' => false, 'error' => 'Fee not found']);
        exit();
    }
    
    // Calculate status
    $status = 'pending';
    if ($amount_paid > 0 && $amount_paid < $fee['amount_due']) {
        $status = 'partial';
    } elseif ($amount_paid >= $fee['amount_due']) {
        $status = 'paid';
        $amount_paid = $fee['amount_due'];
    }
    
    // Update
    $stmt = $conn->prepare("UPDATE student_fees SET amount_paid = ?, status = ?, last_payment_date = NOW() WHERE id = ?");
    $stmt->bind_param("dsi", $amount_paid, $status, $student_fee_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Payment updated',
            'status' => $status,
            'amount_paid' => $amount_paid
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
}

else {
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
?>
