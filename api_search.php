<?php
header('Content-Type: application/json');
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isset($_GET['q']) || !isset($_GET['type'])) {
    echo json_encode(['results' => []]);
    exit();
}

$query = sanitize($_GET['q']);
$type = sanitize($_GET['type']);
$conn = getDBConnection();
$results = [];

if (strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit();
}

if ($type === 'students') {
    $search_term = '%' . $query . '%';
    $stmt = $conn->prepare("SELECT s.id, s.student_id, u.full_name, s.grade FROM students s JOIN users u ON s.user_id = u.id WHERE u.full_name LIKE ? OR s.student_id LIKE ? OR s.admission_number LIKE ? LIMIT 10");
    if ($stmt) {
        $stmt->bind_param("sss", $search_term, $search_term, $search_term);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $results[] = [
                'id' => $row['id'],
                'title' => $row['full_name'] . ' (' . $row['student_id'] . ')',
                'subtitle' => 'Grade ' . $row['grade'],
                'link' => 'student_details.php?id=' . $row['id']
            ];
        }
        $stmt->close();
    }
} elseif ($type === 'exams') {
    $search_term = '%' . $query . '%';
    $stmt = $conn->prepare("SELECT id, exam_name, exam_type, grade FROM exams WHERE exam_name LIKE ? OR exam_type LIKE ? LIMIT 10");
    if ($stmt) {
        $stmt->bind_param("ss", $search_term, $search_term);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $results[] = [
                'id' => $row['id'],
                'title' => $row['exam_name'],
                'subtitle' => $row['exam_type'] . ' - Grade ' . $row['grade'],
                'link' => 'exam_results.php?id=' . $row['id']
            ];
        }
        $stmt->close();
    }
} elseif ($type === 'users') {
    $search_term = '%' . $query . '%';
    $stmt = $conn->prepare("SELECT id, full_name, email, role FROM users WHERE full_name LIKE ? OR email LIKE ? LIMIT 10");
    if ($stmt) {
        $stmt->bind_param("ss", $search_term, $search_term);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $results[] = [
                'id' => $row['id'],
                'title' => $row['full_name'],
                'subtitle' => $row['email'] . ' (' . $row['role'] . ')',
                'link' => 'settings.php'
            ];
        }
        $stmt->close();
    }
}

echo json_encode(['results' => $results]);
?>
