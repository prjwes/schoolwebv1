<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user = getCurrentUser();
$conn = getDBConnection();

// Handle add book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_book'])) {
    $book_name = sanitize($_POST['book_name']);
    $isbn = sanitize($_POST['isbn'] ?? '');
    $subject = sanitize($_POST['subject']);
    $author = sanitize($_POST['author'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 1);

    $stmt = $conn->prepare("INSERT INTO library_books (book_name, isbn, subject, author, quantity_total, quantity_available) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssii", $book_name, $isbn, $subject, $author, $quantity, $quantity);
    $stmt->execute();
    $stmt->close();

    header('Location: library_books.php?success=Book added');
    exit();
}

// Handle assign book to student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_book'])) {
    $student_id = intval($_POST['student_id']);
    $book_id = intval($_POST['book_id']);
    $assigned_date = sanitize($_POST['assigned_date']);

    $stmt = $conn->prepare("INSERT INTO student_library_records (student_id, book_id, assigned_date) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $student_id, $book_id, $assigned_date);
    if ($stmt->execute()) {
        // Update quantity available
        $conn->query("UPDATE library_books SET quantity_available = quantity_available - 1 WHERE id = $book_id");
        header('Location: library_books.php?success=Book assigned to student');
    }
    $stmt->close();
    exit();
}

// Handle return book
if (isset($_GET['return_record'])) {
    $record_id = intval($_GET['return_record']);
    $return_date = date('Y-m-d');

    $stmt = $conn->prepare("SELECT book_id FROM student_library_records WHERE id = ?");
    $stmt->bind_param("i", $record_id);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($record) {
        $stmt = $conn->prepare("UPDATE student_library_records SET is_returned = TRUE, return_date = ? WHERE id = ?");
        $stmt->bind_param("si", $return_date, $record_id);
        $stmt->execute();
        $stmt->close();

        // Update quantity available
        $conn->query("UPDATE library_books SET quantity_available = quantity_available + 1 WHERE id = " . $record['book_id']);
    }

    header('Location: library_books.php?success=Book returned and record cleared');
    exit();
}

// Get all books
$books = [];
$result = $conn->query("SELECT * FROM library_books ORDER BY subject, book_name");
if ($result) {
    $books = $result->fetch_all(MYSQLI_ASSOC);
}

// Get all active records
$active_records = [];
$result = $conn->query("SELECT sr.*, s.id as student_id, u.full_name, lb.book_name, lb.subject FROM student_library_records sr 
    JOIN students s ON sr.student_id = s.id 
    JOIN users u ON s.user_id = u.id 
    JOIN library_books lb ON sr.book_id = lb.id 
    WHERE sr.is_returned = FALSE 
    ORDER BY sr.assigned_date DESC");
if ($result) {
    $active_records = $result->fetch_all(MYSQLI_ASSOC);
}

// Get all students
$students = [];
$result = $conn->query("SELECT s.id, u.full_name FROM students s JOIN users u ON s.user_id = u.id WHERE s.status = 'Active' ORDER BY u.full_name");
if ($result) {
    $students = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Books Management - School Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Library Book Management</h1>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
            <?php endif; ?>

            <!-- Add Book Section -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Add New Book</h3>
                </div>
                <form method="POST" style="padding: 24px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label for="book_name">Book Name *</label>
                            <input type="text" id="book_name" name="book_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="isbn">ISBN (Optional)</label>
                            <input type="text" id="isbn" name="isbn">
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject *</label>
                            <select id="subject" name="subject" required>
                                <option value="">Select Subject</option>
                                <option value="Mathematics">Mathematics</option>
                                <option value="English">English</option>
                                <option value="Kiswahili">Kiswahili</option>
                                <option value="Science">Science</option>
                                <option value="Social Studies">Social Studies</option>
                                <option value="Religious Studies">Religious Studies</option>
                                <option value="Computer">Computer Studies</option>
                                <option value="Agriculture">Agriculture</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="author">Author</label>
                            <input type="text" id="author" name="author">
                        </div>

                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input type="number" id="quantity" name="quantity" value="1" min="1">
                        </div>
                    </div>
                    
                    <button type="submit" name="add_book" class="btn btn-primary">Add Book</button>
                </form>
            </div>

            <!-- Books Inventory -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Books Inventory (<?php echo count($books); ?> books)</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Book Name</th>
                                <th>ISBN</th>
                                <th>Subject</th>
                                <th>Author</th>
                                <th>Total Qty</th>
                                <th>Available</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($books as $book): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($book['book_name']); ?></td>
                                    <td><?php echo htmlspecialchars($book['isbn'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($book['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($book['author'] ?? '-'); ?></td>
                                    <td><?php echo $book['quantity_total']; ?></td>
                                    <td><strong><?php echo $book['quantity_available']; ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Assign Book to Student -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Assign Book to Student</h3>
                </div>
                <form method="POST" style="padding: 24px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label for="student_id">Student *</label>
                            <select id="student_id" name="student_id" required>
                                <option value="">Select Student</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="book_id">Book *</label>
                            <select id="book_id" name="book_id" required>
                                <option value="">Select Book</option>
                                <?php foreach ($books as $book): ?>
                                    <?php if ($book['quantity_available'] > 0): ?>
                                        <option value="<?php echo $book['id']; ?>"><?php echo htmlspecialchars($book['book_name'] . ' (' . $book['quantity_available'] . ' available)'); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="assigned_date">Assignment Date *</label>
                            <input type="date" id="assigned_date" name="assigned_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="assign_book" class="btn btn-primary">Assign Book</button>
                </form>
            </div>

            <!-- Active Records -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Active Book Records (<?php echo count($active_records); ?>)</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Book Name</th>
                                <th>Subject</th>
                                <th>Assigned Date</th>
                                <th>Days Out</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_records as $record): 
                                $assigned = new DateTime($record['assigned_date']);
                                $now = new DateTime();
                                $days_out = $now->diff($assigned)->days;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['book_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['subject']); ?></td>
                                    <td><?php echo formatDate($record['assigned_date']); ?></td>
                                    <td><?php echo $days_out; ?> days</td>
                                    <td>
                                        <a href="?return_record=<?php echo $record['id']; ?>" class="btn btn-sm" style="background-color: #28a745;" onclick="return confirm('Mark this book as returned?')">Mark Returned</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($active_records)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">No active book records</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/theme.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
