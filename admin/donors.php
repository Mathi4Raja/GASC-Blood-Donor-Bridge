<?php
require_once '../config/database.php';
require_once '../config/email.php';

// Check if user is logged in as admin or moderator
requireRole(['admin', 'moderator']);

$db = new Database();
$success = '';
$error = '';

// Handle AJAX request for donation history
if (isset($_GET['action']) && $_GET['action'] === 'get_donation_history') {
    $donorId = intval($_GET['donor_id'] ?? 0);
    
    if ($donorId > 0) {
        $donations = $db->query("
            SELECT dah.*, u.name as verified_by_name 
            FROM donor_availability_history dah 
            LEFT JOIN users u ON dah.verified_by = u.id 
            WHERE dah.donor_id = ? 
            ORDER BY dah.donation_date DESC
        ", [$donorId])->fetch_all(MYSQLI_ASSOC);
        
        if (empty($donations)) {
            echo '<div class="alert alert-info"><i class="fas fa-info-circle"></i> No donation records found for this donor.</div>';
        } else {
            echo '<div class="table-responsive">';
            echo '<table class="table table-striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Date</th>';
            echo '<th>Location</th>';
            echo '<th>Units</th>';
            echo '<th>Blood Bank</th>';
            echo '<th>Status</th>';
            echo '<th>Verified By</th>';
            echo '<th>Actions</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($donations as $donation) {
                $statusBadge = $donation['is_verified'] ? 
                    '<span class="badge bg-success"><i class="fas fa-check"></i> Verified</span>' : 
                    '<span class="badge bg-warning"><i class="fas fa-clock"></i> Pending</span>';
                
                $verifiedBy = $donation['verified_by_name'] ? 
                    htmlspecialchars($donation['verified_by_name']) : 
                    '<span class="text-muted">Not verified</span>';
                
                echo '<tr>';
                echo '<td>' . date('M j, Y', strtotime($donation['donation_date'])) . '</td>';
                echo '<td>' . htmlspecialchars($donation['location']) . '</td>';
                echo '<td>' . $donation['units_donated'] . '</td>';
                echo '<td>' . htmlspecialchars($donation['blood_bank_name'] ?? 'N/A') . '</td>';
                echo '<td>' . $statusBadge . '</td>';
                echo '<td>' . $verifiedBy . '</td>';
                echo '<td class="donation-actions">';
                
                if (!$donation['is_verified']) {
                    echo '<button class="btn btn-success btn-sm me-1" onclick="updateDonationStatus(' . $donation['id'] . ', \'verify\')">';
                    echo '<i class="fas fa-check"></i> Verify</button>';
                }
                
                echo '<button class="btn btn-warning btn-sm me-1" onclick="updateDonationStatus(' . $donation['id'] . ', \'reject\')">';
                echo '<i class="fas fa-times"></i> Reject</button>';
                
                echo '<button class="btn btn-danger btn-sm" onclick="updateDonationStatus(' . $donation['id'] . ', \'delete\')">';
                echo '<i class="fas fa-trash"></i> Delete</button>';
                
                echo '</td>';
                echo '</tr>';
                
                if (!empty($donation['notes'])) {
                    echo '<tr><td colspan="7"><small class="text-muted"><strong>Notes:</strong> ' . htmlspecialchars($donation['notes']) . '</small></td></tr>';
                }
            }
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }
    } else {
        echo '<div class="alert alert-danger">Invalid donor ID.</div>';
    }
    exit; // Important: exit here to prevent the rest of the page from loading
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_donor') {
            // Handle add new donor
            $roll_no = sanitizeInput($_POST['roll_no'] ?? '');
            $name = sanitizeInput($_POST['name'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $blood_group = sanitizeInput($_POST['blood_group'] ?? '');
            $gender = sanitizeInput($_POST['gender'] ?? '');
            $date_of_birth = $_POST['date_of_birth'] ?? '';
            $class = sanitizeInput($_POST['class'] ?? '');
            $city = sanitizeInput($_POST['city'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validation
            if (empty($roll_no) || empty($name) || empty($email) || empty($phone) || empty($blood_group) || 
                empty($gender) || empty($date_of_birth) || empty($class) || empty($city) || 
                empty($password)) {
                throw new Exception('All fields are required.');
            }
            
            if (!isValidEmail($email)) {
                throw new Exception('Please enter a valid email address.');
            }
            
            if (strlen($password) < 8) {
                throw new Exception('Password must be at least 8 characters long.');
            }
            
            if ($password !== $confirm_password) {
                throw new Exception('Passwords do not match.');
            }
            
            // Validate age (18-65 years)
            $birthDate = new DateTime($date_of_birth);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
            
            if ($age < 18) {
                throw new Exception('Donor must be at least 18 years old.');
            }
            
            if ($age > 65) {
                throw new Exception('Donor must be under 65 years old for blood donation.');
            }
            
            // Check if email already exists
            $existingUser = $db->query("SELECT id FROM users WHERE email = ?", [$email]);
            if ($existingUser->num_rows > 0) {
                throw new Exception('An account with this email already exists.');
            }
            
            // Check if roll number already exists
            $existingRollNo = $db->query("SELECT id FROM users WHERE roll_no = ?", [$roll_no]);
            if ($existingRollNo->num_rows > 0) {
                throw new Exception('An account with this roll number already exists.');
            }
            
            // Create new donor account
            $hashedPassword = hashPassword($password);
            $verificationToken = generateSecureToken(32);
            
            $insertSQL = "INSERT INTO users (roll_no, name, email, phone, password_hash, blood_group, gender, 
                         date_of_birth, class, city, user_type, is_active, is_verified, email_verified,
                         email_verification_token, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'donor', 1, 1, 1, ?, NOW(), NOW())";
            
            $db->query($insertSQL, [
                $roll_no, $name, $email, $phone, $hashedPassword, $blood_group, $gender,
                $date_of_birth, $class, $city, $verificationToken
            ]);
            
            $newDonorId = $db->getConnection()->insert_id;
            logActivity($_SESSION['user_id'], 'donor_created_by_admin', "New donor created: $email (ID: $newDonorId)");
            
            $success = "New donor account created successfully for $name!";
        }
        
        if ($action === 'edit_donor') {
            // Handle edit existing donor
            $donorId = intval($_POST['donor_id'] ?? 0);
            $roll_no = sanitizeInput($_POST['roll_no'] ?? '');
            $name = sanitizeInput($_POST['name'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $blood_group = sanitizeInput($_POST['blood_group'] ?? '');
            $gender = sanitizeInput($_POST['gender'] ?? '');
            $date_of_birth = $_POST['date_of_birth'] ?? '';
            $class = sanitizeInput($_POST['class'] ?? '');
            $city = sanitizeInput($_POST['city'] ?? '');
            
            // Validation
            if ($donorId <= 0) {
                throw new Exception('Invalid donor ID.');
            }
            
            if (empty($roll_no) || empty($name) || empty($email) || empty($phone) || empty($blood_group) || 
                empty($gender) || empty($date_of_birth) || empty($class) || empty($city)) {
                throw new Exception('All fields are required.');
            }
            
            if (!isValidEmail($email)) {
                throw new Exception('Please enter a valid email address.');
            }
            
            // Validate age (18-65 years)
            $birthDate = new DateTime($date_of_birth);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
            
            if ($age < 18) {
                throw new Exception('Donor must be at least 18 years old.');
            }
            
            if ($age > 65) {
                throw new Exception('Donor must be under 65 years old for blood donation.');
            }
            
            // Check if email already exists for another user
            $existingUser = $db->query("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $donorId]);
            if ($existingUser->num_rows > 0) {
                throw new Exception('An account with this email already exists.');
            }
            
            // Check if roll number already exists for another user
            $existingRollNo = $db->query("SELECT id FROM users WHERE roll_no = ? AND id != ?", [$roll_no, $donorId]);
            if ($existingRollNo->num_rows > 0) {
                throw new Exception('An account with this roll number already exists.');
            }
            
            // Verify donor exists and is actually a donor
            $donorCheck = $db->query("SELECT name FROM users WHERE id = ? AND user_type = 'donor'", [$donorId]);
            if ($donorCheck->num_rows === 0) {
                throw new Exception('Donor not found.');
            }
            $originalDonor = $donorCheck->fetch_assoc();
            
            // Update donor details
            $updateSQL = "UPDATE users SET 
                         roll_no = ?, name = ?, email = ?, phone = ?, blood_group = ?, 
                         gender = ?, date_of_birth = ?, class = ?, city = ?, updated_at = NOW()
                         WHERE id = ? AND user_type = 'donor'";
            
            $stmt = $db->prepare($updateSQL);
            $stmt->bind_param('sssssssssi', 
                $roll_no, $name, $email, $phone, $blood_group, 
                $gender, $date_of_birth, $class, $city, $donorId
            );
            
            if ($stmt->execute()) {
                logActivity($_SESSION['user_id'], 'donor_details_updated', 
                    "Updated donor details for: {$originalDonor['name']} (ID: $donorId)");
                $success = "Donor details updated successfully for $name!";
            } else {
                throw new Exception('Failed to update donor details.');
            }
        }
        
        if ($action === 'toggle_status') {
            $donorId = intval($_POST['donor_id']);
            $currentStatus = $_POST['current_status'] === 'true' ? 1 : 0;
            $newStatus = $currentStatus ? 0 : 1;
            
            $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ? AND user_type = 'donor'");
            $stmt->bind_param('ii', $newStatus, $donorId);
            
            if ($stmt->execute()) {
                $statusText = $newStatus ? 'activated' : 'deactivated';
                $success = "Donor account has been {$statusText} successfully.";
                logActivity($_SESSION['user_id'], 'donor_status_toggle', "Donor ID {$donorId} {$statusText}");
            } else {
                $error = "Failed to update donor status.";
            }
        }
        
        if ($action === 'toggle_availability') {
            $donorId = intval($_POST['donor_id']);
            $currentAvailability = $_POST['current_availability'] === 'true' ? 1 : 0;
            $newAvailability = $currentAvailability ? 0 : 1;
            
            $stmt = $db->prepare("UPDATE users SET is_available = ? WHERE id = ? AND user_type = 'donor'");
            $stmt->bind_param('ii', $newAvailability, $donorId);
            
            if ($stmt->execute()) {
                $availabilityText = $newAvailability ? 'available' : 'unavailable';
                $success = "Donor availability has been set to {$availabilityText}.";
                logActivity($_SESSION['user_id'], 'donor_availability_toggle', "Donor ID {$donorId} set to {$availabilityText}");
            } else {
                $error = "Failed to update donor availability.";
            }
        }
        
        if ($action === 'verify_donor') {
            $donorId = intval($_POST['donor_id']);
            
            $stmt = $db->prepare("UPDATE users SET is_verified = TRUE WHERE id = ? AND user_type = 'donor'");
            $stmt->bind_param('i', $donorId);
            
            if ($stmt->execute()) {
                $success = "Donor has been verified successfully.";
                logActivity($_SESSION['user_id'], 'donor_verified', "Donor ID {$donorId} verified");
                
                // Send verification email to donor
                $donorQuery = $db->prepare("SELECT name, email FROM users WHERE id = ?");
                $donorQuery->bind_param('i', $donorId);
                $donorQuery->execute();
                $donor = $donorQuery->get_result()->fetch_assoc();
                
                if ($donor) {
                    $emailSubject = "Account Verified - GASC Blood Bridge";
                    $emailBody = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <div style='background: linear-gradient(135deg, #dc2626, #991b1b); padding: 20px; text-align: center;'>
                            <h1 style='color: white; margin: 0;'>GASC Blood Bridge</h1>
                        </div>
                        <div style='padding: 30px; background: #f8f9fa;'>
                            <h2>Account Verified!</h2>
                            <p>Dear {$donor['name']},</p>
                            <p>Great news! Your donor account has been verified by our admin team.</p>
                            <p>You can now:</p>
                            <ul>
                                <li>Receive blood donation requests</li>
                                <li>Update your availability status</li>
                                <li>Access your full donor dashboard</li>
                            </ul>
                            <p>Thank you for joining our life-saving mission!</p>
                        </div>
                    </div>
                    ";
                    sendEmail($donor['email'], $emailSubject, $emailBody);
                }
            } else {
                $error = "Failed to verify donor.";
            }
        }
        
        if ($action === 'delete_donor') {
            $donorId = intval($_POST['donor_id']);
            
            // First check if donor has any blood requests associated
            $checkQuery = $db->prepare("SELECT COUNT(*) as count FROM blood_requests WHERE requester_email = (SELECT email FROM users WHERE id = ?)");
            $checkQuery->bind_param('i', $donorId);
            $checkQuery->execute();
            $hasRequests = $checkQuery->get_result()->fetch_assoc()['count'] > 0;
            
            if ($hasRequests) {
                $error = "Cannot delete donor who has associated blood requests. Please archive instead.";
            } else {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND user_type = 'donor'");
                $stmt->bind_param('i', $donorId);
                
                if ($stmt->execute()) {
                    $success = "Donor has been deleted successfully.";
                    logActivity($_SESSION['user_id'], 'donor_deleted', "Donor ID {$donorId} deleted");
                } else {
                    $error = "Failed to delete donor.";
                }
            }
        }
        
        if ($action === 'update_last_donation') {
            $donorId = intval($_POST['donor_id']);
            $lastDonationDate = $_POST['last_donation_date'];
            
            if (empty($lastDonationDate)) {
                $lastDonationDate = null;
            }
            
            $stmt = $db->prepare("UPDATE users SET last_donation_date = ? WHERE id = ? AND user_type = 'donor'");
            $stmt->bind_param('si', $lastDonationDate, $donorId);
            
            if ($stmt->execute()) {
                $success = "Last donation date updated successfully.";
                logActivity($_SESSION['user_id'], 'donor_last_donation_updated', "Donor ID {$donorId} last donation date updated");
            } else {
                $error = "Failed to update last donation date.";
            }
        }
        
        if ($action === 'verify_donation') {
            $donationId = intval($_POST['donation_id']);
            
            $stmt = $db->prepare("UPDATE donor_availability_history SET is_verified = TRUE, verified_by = ? WHERE id = ?");
            $stmt->bind_param('ii', $_SESSION['user_id'], $donationId);
            
            if ($stmt->execute()) {
                $success = "Donation has been verified successfully.";
                logActivity($_SESSION['user_id'], 'donation_verified', "Donation ID {$donationId} verified");
            } else {
                $error = "Failed to verify donation.";
            }
        }
        
        if ($action === 'reject_donation') {
            $donationId = intval($_POST['donation_id']);
            
            $stmt = $db->prepare("UPDATE donor_availability_history SET is_verified = FALSE, verified_by = ? WHERE id = ?");
            $stmt->bind_param('ii', $_SESSION['user_id'], $donationId);
            
            if ($stmt->execute()) {
                $success = "Donation has been rejected.";
                logActivity($_SESSION['user_id'], 'donation_rejected', "Donation ID {$donationId} rejected");
            } else {
                $error = "Failed to reject donation.";
            }
        }
        
        if ($action === 'delete_donation') {
            $donationId = intval($_POST['donation_id']);
            
            $stmt = $db->prepare("DELETE FROM donor_availability_history WHERE id = ?");
            $stmt->bind_param('i', $donationId);
            
            if ($stmt->execute()) {
                $success = "Donation record has been deleted successfully.";
                logActivity($_SESSION['user_id'], 'donation_deleted', "Donation ID {$donationId} deleted");
            } else {
                $error = "Failed to delete donation record.";
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        logActivity($_SESSION['user_id'], 'donor_management_error', $error);
    }
}

// Get filters
$statusFilter = $_GET['status'] ?? 'all';
$verificationFilter = $_GET['verification'] ?? 'all';
$availabilityFilter = $_GET['availability'] ?? 'all';
$bloodGroupFilter = $_GET['blood_group'] ?? 'all';
$cityFilter = $_GET['city'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$whereConditions = ["user_type = 'donor'"];
$params = [];
$types = '';

if ($statusFilter !== 'all') {
    $whereConditions[] = 'is_active = ?';
    $params[] = $statusFilter === 'active' ? 1 : 0;
    $types .= 'i';
}

if ($verificationFilter !== 'all') {
    $whereConditions[] = 'is_verified = ?';
    $params[] = $verificationFilter === 'verified' ? 1 : 0;
    $types .= 'i';
}

if ($availabilityFilter !== 'all') {
    $whereConditions[] = 'is_available = ?';
    $params[] = $availabilityFilter === 'available' ? 1 : 0;
    $types .= 'i';
}

if ($bloodGroupFilter !== 'all') {
    $whereConditions[] = 'blood_group = ?';
    $params[] = $bloodGroupFilter;
    $types .= 's';
}

if (!empty($cityFilter)) {
    $whereConditions[] = 'city LIKE ?';
    $params[] = "%{$cityFilter}%";
    $types .= 's';
}

if (!empty($search)) {
    $whereConditions[] = '(name LIKE ? OR email LIKE ? OR roll_no LIKE ? OR phone LIKE ?)';
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    $types .= 'ssss';
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM users {$whereClause}";
$countStmt = $db->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// Get donors
$donorsQuery = "SELECT id, roll_no, name, email, phone, gender, class, blood_group, city, date_of_birth,
                last_donation_date, is_available, is_verified, is_active, email_verified, created_at,
                CASE 
                    WHEN last_donation_date IS NULL THEN TRUE
                    WHEN gender = 'Female' AND DATEDIFF(CURDATE(), last_donation_date) >= 120 THEN TRUE
                    WHEN gender != 'Female' AND DATEDIFF(CURDATE(), last_donation_date) >= 90 THEN TRUE
                    ELSE FALSE
                END AS can_donate
                FROM users {$whereClause} 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";

$donorsStmt = $db->prepare($donorsQuery);
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';
$donorsStmt->bind_param($types, ...$params);
$donorsStmt->execute();
$donors = $donorsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'donors_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    $headers = [
        'ID', 'Roll Number', 'Name', 'Email', 'Phone', 'Gender', 'Class', 
        'Blood Group', 'City', 'Date of Birth', 'Last Donation Date', 
        'Available', 'Verified', 'Active', 'Email Verified', 'Can Donate Now', 
        'Registration Date'
    ];
    fputcsv($output, $headers);
    
    // Get all donors for export (without pagination)
    $exportQuery = "SELECT id, roll_no, name, email, phone, gender, class, blood_group, city, 
                    date_of_birth, last_donation_date, is_available, is_verified, is_active, 
                    email_verified, created_at,
                    CASE 
                        WHEN last_donation_date IS NULL THEN TRUE
                        WHEN gender = 'Female' AND DATEDIFF(CURDATE(), last_donation_date) >= 120 THEN TRUE
                        WHEN gender != 'Female' AND DATEDIFF(CURDATE(), last_donation_date) >= 90 THEN TRUE
                        ELSE FALSE
                    END AS can_donate
                    FROM users {$whereClause} 
                    ORDER BY created_at DESC";
    
    $exportStmt = $db->prepare($exportQuery);
    if (!empty($params)) {
        // Remove the limit and offset parameters for export
        $exportParams = array_slice($params, 0, -2);
        $exportTypes = substr($types, 0, -2);
        
        // Only bind parameters if we have both types and parameters
        if (!empty($exportTypes) && !empty($exportParams)) {
            $exportStmt->bind_param($exportTypes, ...$exportParams);
        }
    }
    $exportStmt->execute();
    $exportDonors = $exportStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Write data rows
    foreach ($exportDonors as $donor) {
        $row = [
            $donor['id'],
            $donor['roll_no'],
            $donor['name'],
            $donor['email'],
            $donor['phone'],
            $donor['gender'],
            $donor['class'],
            $donor['blood_group'],
            $donor['city'],
            $donor['date_of_birth'] ?? '',
            $donor['last_donation_date'] ?? '',
            $donor['is_available'] ? 'Yes' : 'No',
            $donor['is_verified'] ? 'Yes' : 'No',
            $donor['is_active'] ? 'Yes' : 'No',
            $donor['email_verified'] ? 'Yes' : 'No',
            $donor['can_donate'] ? 'Yes' : 'No',
            $donor['created_at']
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
    
    // Log the export activity
    logActivity($_SESSION['user_id'], 'donors_exported', "Exported " . count($exportDonors) . " donors to CSV");
    
    exit;
}

// Get blood groups for filter
$bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

// Get donor statistics
$stats = [];
$stats['total'] = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'donor'")->fetch_assoc()['count'];
$stats['verified'] = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'donor' AND is_verified = TRUE")->fetch_assoc()['count'];
$stats['available'] = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'donor' AND is_available = TRUE AND is_verified = TRUE AND is_active = TRUE")->fetch_assoc()['count'];
$stats['can_donate'] = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'donor' AND is_available = TRUE AND is_verified = TRUE AND is_active = TRUE AND (
    last_donation_date IS NULL 
    OR (gender = 'Female' AND DATEDIFF(CURDATE(), last_donation_date) >= 120)
    OR (gender != 'Female' AND DATEDIFF(CURDATE(), last_donation_date) >= 90)
)")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Donors - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .donor-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden; /* Prevent content overflow */
            word-wrap: break-word;
        }
        
        .donor-card .small.text-muted {
            word-break: break-word;
            overflow-wrap: break-word;
            hyphens: auto;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        
        .blood-group-badge {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: white;
            border-radius: 15px;
            padding: 4px 12px;
            font-weight: bold;
            font-size: 0.8rem;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: none;
            border-radius: 15px;
            text-align: center;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .action-btn {
            margin: 2px;
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        .can-donate {
            color: #10b981;
            font-weight: bold;
        }
        
        .cannot-donate {
            color: #ef4444;
            font-weight: bold;
        }
        
        .donation-actions .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .modal-lg {
            max-width: 800px;
        }
        
        .rounded-pill {
            border-radius: 50rem !important;
            transition: all 0.3s ease;
        }
        
        .rounded-pill:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        @media (max-width: 768px) {
            .donor-card {
                padding: 15px;
            }
            
            .donor-card .small.text-muted {
                word-break: break-word;
                overflow-wrap: break-word;
                white-space: normal;
            }
            
            .donor-card h6 {
                word-break: break-word;
                overflow-wrap: break-word;
            }
            
            .action-btn {
                width: 100%;
                margin-bottom: 5px;
                font-size: 0.8rem;
                padding: 6px 12px;
            }
            
            .donation-actions .btn {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .d-flex.gap-2 {
                flex-direction: column;
                gap: 0.5rem !important;
            }
            
            .rounded-pill {
                width: 100%;
            }
            
            /* Fix for long email addresses and other text */
            .donor-card .text-muted {
                word-wrap: break-word;
                overflow-wrap: break-word;
                hyphens: auto;
            }
            
            /* Ensure badges and pills don't overflow */
            .badge, .blood-group-badge {
                word-break: break-word;
                white-space: normal;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Navigation Toggle -->
    <button class="mobile-nav-toggle" type="button" id="mobileNavToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar bg-light vh-100 sticky-top" id="adminSidebar">
                <!-- Close button for mobile -->
                <button class="sidebar-close d-md-none" type="button" id="sidebarClose">
                    <i class="fas fa-times"></i>
                </button>
                
                <div class="p-3">
                    <div class="d-flex align-items-center mb-4">
                        <i class="fas fa-tint text-danger me-2 fs-4"></i>
                        <h5 class="mb-0 text-danger fw-bold">GASC Blood Bridge</h5>
                    </div>
                    
                    <!-- Navigation -->
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a class="nav-link active" href="donors.php">
                            <i class="fas fa-users me-2"></i>Manage Donors
                        </a>
                        <a class="nav-link" href="requests.php">
                            <i class="fas fa-hand-holding-heart me-2"></i>Blood Requests
                        </a>
                        <a class="nav-link" href="inventory.php">
                            <i class="fas fa-warehouse me-2"></i>Blood Inventory
                        </a>
                        <?php if ($_SESSION['user_type'] === 'admin'): ?>
                        <a class="nav-link" href="moderators.php">
                            <i class="fas fa-user-cog me-2"></i>Manage Moderators
                        </a>
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog me-2"></i>System Settings
                        </a>
                        <?php endif; ?>
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                        <a class="nav-link" href="logs.php">
                            <i class="fas fa-clipboard-list me-2"></i>Activity Logs
                        </a>
                        <hr>
                        <a class="nav-link text-danger" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ms-sm-auto px-md-4 admin-main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-users text-danger me-2"></i>Manage Donors
                    </h1>
                    <div class="d-flex gap-2 mb-2 mb-md-0">
                        <?php if (isset($_GET['action']) && $_GET['action'] === 'add'): ?>
                            <a href="donors.php" class="btn btn-sm btn-secondary rounded-pill px-3">
                                <i class="fas fa-arrow-left me-1"></i>Back to List
                            </a>
                        <?php else: ?>
                            <a href="donors.php?action=add" class="btn btn-sm btn-danger rounded-pill px-3">
                                <i class="fas fa-user-plus me-1"></i>Add New Donor
                            </a>
                            <button type="button" class="btn btn-sm btn-success rounded-pill px-3" onclick="exportData()">
                                <i class="fas fa-download me-1"></i>Export CSV
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success d-flex align-items-center">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['action']) && $_GET['action'] === 'add'): ?>
                <!-- Add New Donor Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Add New Donor</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="donors.php">
                            <input type="hidden" name="action" value="add_donor">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="roll_no" class="form-label">Roll Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="roll_no" name="roll_no" required placeholder="e.g., CS2021001">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="class" class="form-label">Class/Course <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="class" name="class" required placeholder="e.g., B.Tech CSE, M.Tech, MBA">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" id="phone" name="phone" required pattern="^[0-9]{10}$" maxlength="10" inputmode="numeric" title="Enter a 10-digit phone number" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="blood_group" class="form-label">Blood Group <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="blood_group" name="blood_group" required placeholder="e.g., A+, O-, B+" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase();">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                        <select class="form-select" id="gender" name="gender" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="date_of_birth" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required
                                               max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>"
                                               min="<?php echo date('Y-m-d', strtotime('-65 years')); ?>">
                                        <div class="form-text">Must be between 18-65 years old for blood donation</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="city" name="city" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                <i class="fas fa-eye" id="passwordIcon"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Minimum 8 characters</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                                <i class="fas fa-eye" id="confirmPasswordIcon"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end gap-2">
                                <a href="donors.php" class="btn btn-secondary d-flex align-items-center">
                                    <i class="fas fa-arrow-left me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-danger d-flex align-items-center">
                                    <i class="fas fa-user-plus me-2"></i>Create Donor Account
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <!-- Regular donor list view -->
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="h3 text-primary"><?php echo $stats['total']; ?></div>
                            <div class="text-muted">Total Donors</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="h3 text-success"><?php echo $stats['verified']; ?></div>
                            <div class="text-muted">Verified Donors</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="h3 text-warning"><?php echo $stats['available']; ?></div>
                            <div class="text-muted">Available Donors</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="h3 text-danger"><?php echo $stats['can_donate']; ?></div>
                            <div class="text-muted">Can Donate Now</div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filter-card">
                    <form method="GET" action="">
                        <div class="row">
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Verification</label>
                                <select name="verification" class="form-select form-select-sm">
                                    <option value="all" <?php echo $verificationFilter === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="verified" <?php echo $verificationFilter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                    <option value="unverified" <?php echo $verificationFilter === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Availability</label>
                                <select name="availability" class="form-select form-select-sm">
                                    <option value="all" <?php echo $availabilityFilter === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="available" <?php echo $availabilityFilter === 'available' ? 'selected' : ''; ?>>Available</option>
                                    <option value="unavailable" <?php echo $availabilityFilter === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Blood Group</label>
                                <select name="blood_group" class="form-select form-select-sm">
                                    <option value="all">All Groups</option>
                                    <?php foreach ($bloodGroups as $group): ?>
                                        <option value="<?php echo $group; ?>" <?php echo $bloodGroupFilter === $group ? 'selected' : ''; ?>>
                                            <?php echo $group; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">City</label>
                                <input type="text" name="city" class="form-control form-control-sm" 
                                       placeholder="City" value="<?php echo htmlspecialchars($cityFilter); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Search</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Name, email, roll no..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-filter me-1"></i>Apply Filters
                                </button>
                                <a href="donors.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Results -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted">
                        Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalRecords); ?> 
                        of <?php echo $totalRecords; ?> donors
                    </div>
                    <div>
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Donor pagination">
                                <ul class="pagination pagination-sm">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Donors List -->
                <div class="row">
                    <?php if (empty($donors)): ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No donors found</h5>
                                <p class="text-muted">Try adjusting your filters or search criteria.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($donors as $donor): ?>
                            <div class="col-lg-6 col-xl-4">
                                <div class="donor-card">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($donor['name']); ?></h6>
                                            <?php if ($donor['roll_no']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($donor['roll_no']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <span class="blood-group-badge"><?php echo htmlspecialchars($donor['blood_group'] ?? 'N/A'); ?></span>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="small text-muted mb-1">
                                            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($donor['email']); ?>
                                        </div>
                                        <div class="small text-muted mb-1">
                                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($donor['phone']); ?>
                                        </div>
                                        <?php if ($donor['gender']): ?>
                                            <div class="small text-muted mb-1">
                                                <i class="fas <?php echo $donor['gender'] === 'Male' ? 'fa-mars text-primary' : 'fa-venus text-danger'; ?> me-1"></i><?php echo htmlspecialchars($donor['gender']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($donor['city']): ?>
                                            <div class="small text-muted mb-1">
                                                <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($donor['city']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($donor['class']): ?>
                                            <div class="small text-muted mb-1">
                                                <i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars($donor['class']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex flex-wrap gap-1">
                                            <span class="badge status-badge <?php echo $donor['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $donor['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                            <span class="badge status-badge <?php echo $donor['is_verified'] ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo $donor['is_verified'] ? 'Verified' : 'Unverified'; ?>
                                            </span>
                                            <span class="badge status-badge <?php echo $donor['is_available'] ? 'bg-info' : 'bg-secondary'; ?>">
                                                <?php echo $donor['is_available'] ? 'Available' : 'Unavailable'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <?php if ($donor['last_donation_date']): ?>
                                            <small class="text-muted">
                                                Last Donation: <?php echo date('M j, Y', strtotime($donor['last_donation_date'])); ?>
                                            </small><br>
                                        <?php endif; ?>
                                        <small class="<?php echo $donor['can_donate'] ? 'can-donate' : 'cannot-donate'; ?>">
                                            <?php echo $donor['can_donate'] ? 'Can donate now' : 'Cannot donate yet'; ?>
                                        </small>
                                    </div>
                                    
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php if (!$donor['is_verified']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="verify_donor">
                                                <input type="hidden" name="donor_id" value="<?php echo $donor['id']; ?>">
                                                <button type="submit" class="btn btn-success action-btn" 
                                                        onclick="return confirm('Verify this donor?')">
                                                    <i class="fas fa-check-circle"></i> Verify
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn btn-warning action-btn" 
                                                onclick="showEditDonorModal({
                                                    id: <?php echo $donor['id']; ?>,
                                                    name: '<?php echo addslashes($donor['name']); ?>',
                                                    email: '<?php echo addslashes($donor['email']); ?>',
                                                    phone: '<?php echo addslashes($donor['phone']); ?>',
                                                    blood_group: '<?php echo addslashes($donor['blood_group']); ?>',
                                                    gender: '<?php echo addslashes($donor['gender']); ?>',
                                                    date_of_birth: '<?php echo $donor['date_of_birth'] ?? ''; ?>',
                                                    class: '<?php echo addslashes($donor['class']); ?>',
                                                    city: '<?php echo addslashes($donor['city']); ?>',
                                                    roll_no: '<?php echo addslashes($donor['roll_no']); ?>'
                                                })">
                                            <i class="fas fa-edit"></i> Edit Details
                                        </button>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="donor_id" value="<?php echo $donor['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $donor['is_active'] ? 'true' : 'false'; ?>">
                                            <button type="submit" class="btn btn-<?php echo $donor['is_active'] ? 'warning' : 'success'; ?> action-btn"
                                                    onclick="return confirm('<?php echo $donor['is_active'] ? 'Deactivate' : 'Activate'; ?> this donor?')">
                                                <i class="fas fa-power-off"></i> 
                                                <?php echo $donor['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_availability">
                                            <input type="hidden" name="donor_id" value="<?php echo $donor['id']; ?>">
                                            <input type="hidden" name="current_availability" value="<?php echo $donor['is_available'] ? 'true' : 'false'; ?>">
                                            <button type="submit" class="btn btn-info action-btn"
                                                    onclick="return confirm('Toggle availability for this donor?')">
                                                <i class="fas fa-toggle-<?php echo $donor['is_available'] ? 'on' : 'off'; ?>"></i>
                                                <?php echo $donor['is_available'] ? 'Set Unavailable' : 'Set Available'; ?>
                                            </button>
                                        </form>
                                        
                                        <button type="button" class="btn btn-primary action-btn" 
                                                onclick="showUpdateDonationModal(<?php echo $donor['id']; ?>, '<?php echo $donor['last_donation_date']; ?>', '<?php echo htmlspecialchars($donor['name']); ?>')">
                                            <i class="fas fa-calendar"></i> Update Donation
                                        </button>
                                        
                                        <button type="button" class="btn btn-info action-btn" 
                                                onclick="showDonationHistoryModal(<?php echo $donor['id']; ?>, '<?php echo htmlspecialchars($donor['name']); ?>')">
                                            <i class="fas fa-history"></i> View Donations
                                        </button>
                                        
                                        <?php if ($_SESSION['user_type'] === 'admin'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_donor">
                                                <input type="hidden" name="donor_id" value="<?php echo $donor['id']; ?>">
                                                <button type="submit" class="btn btn-danger action-btn"
                                                        onclick="return confirm('Are you sure you want to delete this donor? This action cannot be undone.')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            Registered: <?php echo date('M j, Y', strtotime($donor['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Update Last Donation Modal -->
    <div class="modal fade" id="updateDonationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Last Donation Date</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="updateDonationForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_last_donation">
                        <input type="hidden" name="donor_id" id="updateDonorId">
                        
                        <div class="mb-3">
                            <label for="donorName" class="form-label">Donor Name</label>
                            <input type="text" class="form-control" id="donorName" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="lastDonationDate" class="form-label">Last Donation Date</label>
                            <input type="date" class="form-control" name="last_donation_date" id="lastDonationDate">
                            <div class="form-text">Leave empty if donor has never donated before.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Donation History Modal -->
    <div class="modal fade" id="donationHistoryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Donation History - <span id="donationHistoryDonorName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="donationHistoryContent">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Donor Modal -->
    <div class="modal fade" id="editDonorModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit me-2"></i>Edit Donor Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editDonorForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_donor">
                        <input type="hidden" name="donor_id" id="editDonorId">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editRollNo" class="form-label">Roll Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="editRollNo" name="roll_no" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editName" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="editName" name="name" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editEmail" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="editEmail" name="email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editPhone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="editPhone" name="phone" required 
                                           pattern="^[0-9]{10}$" maxlength="10" inputmode="numeric" 
                                           title="Enter a 10-digit phone number" 
                                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editBloodGroup" class="form-label">Blood Group <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="editBloodGroup" name="blood_group" required 
                                           placeholder="e.g., A+, O-, B+" style="text-transform: uppercase;" 
                                           oninput="this.value = this.value.toUpperCase();">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editGender" class="form-label">Gender <span class="text-danger">*</span></label>
                                    <select class="form-select" id="editGender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editDateOfBirth" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="editDateOfBirth" name="date_of_birth" required
                                           max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>"
                                           min="<?php echo date('Y-m-d', strtotime('-65 years')); ?>">
                                    <div class="form-text">Must be between 18-65 years old</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editClass" class="form-label">Class/Course <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="editClass" name="class" required 
                                           placeholder="e.g., B.Tech CSE, M.Tech, MBA">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editCity" class="form-label">City <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="editCity" name="city" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Details
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/loading-manager.js"></script>
    <script>
        function showUpdateDonationModal(donorId, lastDonationDate, donorName) {
            document.getElementById('updateDonorId').value = donorId;
            document.getElementById('donorName').value = donorName;
            document.getElementById('lastDonationDate').value = lastDonationDate || '';
            
            new bootstrap.Modal(document.getElementById('updateDonationModal')).show();
        }
        
        function showDonationHistoryModal(donorId, donorName) {
            document.getElementById('donationHistoryDonorName').textContent = donorName;
            
            // Store donor ID in modal for later use
            document.getElementById('donationHistoryModal').setAttribute('data-current-donor-id', donorId);
            
            // Show modal first
            const modal = new bootstrap.Modal(document.getElementById('donationHistoryModal'));
            modal.show();
            
            // Load donation history via AJAX
            fetch('donors.php?action=get_donation_history&donor_id=' + donorId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('donationHistoryContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('donationHistoryContent').innerHTML = 
                        '<div class="alert alert-danger">Error loading donation history: ' + error.message + '</div>';
                });
        }
        
        function showEditDonorModal(donorData) {
            // Populate form fields with current donor data
            document.getElementById('editDonorId').value = donorData.id;
            document.getElementById('editName').value = donorData.name;
            document.getElementById('editEmail').value = donorData.email;
            document.getElementById('editPhone').value = donorData.phone;
            document.getElementById('editBloodGroup').value = donorData.blood_group;
            document.getElementById('editGender').value = donorData.gender;
            document.getElementById('editDateOfBirth').value = donorData.date_of_birth;
            document.getElementById('editClass').value = donorData.class;
            document.getElementById('editCity').value = donorData.city;
            document.getElementById('editRollNo').value = donorData.roll_no;
            
            // Show modal
            new bootstrap.Modal(document.getElementById('editDonorModal')).show();
        }
        
        function updateDonationStatus(donationId, action) {
            if (!confirm('Are you sure you want to ' + action + ' this donation?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', action + '_donation');
            formData.append('donation_id', donationId);
            
            fetch('donors.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Get donor info from modal
                const modal = document.getElementById('donationHistoryModal');
                const donorId = modal.getAttribute('data-current-donor-id');
                const donorName = document.getElementById('donationHistoryDonorName').textContent;
                
                // Reload the donation history only if modal is still open
                if (donorId && modal.classList.contains('show')) {
                    showDonationHistoryModal(donorId, donorName);
                }
                
                // Show success message
                if (data.includes('successfully')) {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show mt-2';
                    alertDiv.innerHTML = 'Donation ' + action + 'd successfully! <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                    document.getElementById('donationHistoryContent').insertBefore(alertDiv, document.getElementById('donationHistoryContent').firstChild);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }
        
        function exportData() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'donors.php?' + params.toString();
        }
        
        // Auto-submit form on filter change
        document.querySelectorAll('select[name="status"], select[name="verification"], select[name="availability"], select[name="blood_group"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
        
        // Reset modal content when closed
        document.getElementById('donationHistoryModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('donationHistoryContent').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            this.removeAttribute('data-current-donor-id');
        });
        
        // Mobile Navigation Toggle
        const mobileNavToggle = document.getElementById('mobileNavToggle');
        const sidebarClose = document.getElementById('sidebarClose');
        const sidebar = document.getElementById('adminSidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
            function showSidebar() {
                sidebar.classList.add('show');
                sidebarOverlay.classList.add('show');
                mobileNavToggle.classList.add('hidden');
                document.body.style.overflow = 'hidden';
            }
            
            function hideSidebar() {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
                mobileNavToggle.classList.remove('hidden');
                document.body.style.overflow = '';
            }        mobileNavToggle.addEventListener('click', showSidebar);
        sidebarClose.addEventListener('click', hideSidebar);
        sidebarOverlay.addEventListener('click', hideSidebar);
        
        // Close sidebar when clicking on navigation links on mobile
        const navLinks = sidebar.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 768) {
                    hideSidebar();
                }
            });
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                hideSidebar();
            }
        });
        
        // Add Donor Form Validation
        const passwordField = document.getElementById('password');
        const confirmPasswordField = document.getElementById('confirm_password');
        
        if (passwordField && confirmPasswordField) {
            function validatePasswords() {
                if (passwordField.value !== confirmPasswordField.value) {
                    confirmPasswordField.setCustomValidity('Passwords do not match');
                } else {
                    confirmPasswordField.setCustomValidity('');
                }
            }
            
            passwordField.addEventListener('input', validatePasswords);
            confirmPasswordField.addEventListener('input', validatePasswords);
            
            // Password visibility toggle
            const togglePassword = document.getElementById('togglePassword');
            const passwordIcon = document.getElementById('passwordIcon');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const confirmPasswordIcon = document.getElementById('confirmPasswordIcon');
            
            if (togglePassword) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordField.setAttribute('type', type);
                    
                    // Toggle the eye icon
                    if (type === 'password') {
                        passwordIcon.classList.remove('fa-eye-slash');
                        passwordIcon.classList.add('fa-eye');
                    } else {
                        passwordIcon.classList.remove('fa-eye');
                        passwordIcon.classList.add('fa-eye-slash');
                    }
                });
            }
            
            if (toggleConfirmPassword) {
                toggleConfirmPassword.addEventListener('click', function() {
                    const type = confirmPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
                    confirmPasswordField.setAttribute('type', type);
                    
                    // Toggle the eye icon
                    if (type === 'password') {
                        confirmPasswordIcon.classList.remove('fa-eye-slash');
                        confirmPasswordIcon.classList.add('fa-eye');
                    } else {
                        confirmPasswordIcon.classList.remove('fa-eye');
                        confirmPasswordIcon.classList.add('fa-eye-slash');
                    }
                });
            }
            
            // Age validation for date of birth (Add donor form)
            const dobField = document.getElementById('date_of_birth');
            if (dobField) {
                addDateValidation(dobField);
            }
        }
        
        // Edit Donor Form Validation
        const editDobField = document.getElementById('editDateOfBirth');
        if (editDobField) {
            addDateValidation(editDobField);
        }
        
        // Shared date validation function
        function addDateValidation(dobField) {
            // Calculate and set dynamic min/max dates
            const today = new Date();
            const minDate = new Date(today.getFullYear() - 65, today.getMonth(), today.getDate());
            const maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
            
            dobField.setAttribute('min', minDate.toISOString().split('T')[0]);
            dobField.setAttribute('max', maxDate.toISOString().split('T')[0]);
            
            dobField.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const age = Math.floor((today - selectedDate) / (365.25 * 24 * 60 * 60 * 1000));
                
                if (age < 18) {
                    this.setCustomValidity('Donor must be at least 18 years old');
                    this.classList.add('is-invalid');
                } else if (age > 65) {
                    this.setCustomValidity('Donor must be under 65 years old');
                    this.classList.add('is-invalid');
                } else {
                    this.setCustomValidity('');
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
            
            // Real-time validation as user types or changes date
            dobField.addEventListener('input', function() {
                if (this.value) {
                    const selectedDate = new Date(this.value);
                    const age = Math.floor((today - selectedDate) / (365.25 * 24 * 60 * 60 * 1000));
                    
                    if (age < 18 || age > 65) {
                        this.classList.add('is-invalid');
                        this.classList.remove('is-valid');
                    } else {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    }
                }
            });
        }
        
        // Edit form submission confirmation
        const editDonorForm = document.getElementById('editDonorForm');
        if (editDonorForm) {
            editDonorForm.addEventListener('submit', function(e) {
                if (!confirm('Are you sure you want to update this donor\'s details?')) {
                    e.preventDefault();
                    return false;
                }
            });
        }
    </script>
</body>
</html>
