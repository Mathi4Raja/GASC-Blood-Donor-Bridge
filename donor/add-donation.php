<?php
require_once '../config/database.php';
require_once 'includes/sidebar-utils.php';

// Check if user is logged in as donor
requireRole(['donor']);

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        try {
            $db = new Database();
            
            // Validate required fields
            $donationDate = $_POST['donation_date'] ?? '';
            $location = $_POST['location'] ?? '';
            $unitsDonateds = intval($_POST['units_donated'] ?? 1);
            $bloodBankName = $_POST['blood_bank_name'] ?? '';
            $notes = $_POST['notes'] ?? '';
            
            if (empty($donationDate) || empty($location)) {
                throw new Exception('Donation date and location are required.');
            }
            
            // Validate date (not in future)
            if (strtotime($donationDate) > time()) {
                throw new Exception('Donation date cannot be in the future.');
            }
            
            // Insert donation record
            $sql = "INSERT INTO donor_availability_history 
                    (donor_id, donation_date, location, units_donated, blood_bank_name, notes, is_verified) 
                    VALUES (?, ?, ?, ?, ?, ?, 0)";
                    
            $db->query($sql, [
                $_SESSION['user_id'], 
                $donationDate, 
                $location, 
                $unitsDonateds, 
                $bloodBankName, 
                $notes
            ]);
            
            // Update user's last donation date
            $updateUserSQL = "UPDATE users SET last_donation_date = ? WHERE id = ?";
            $db->query($updateUserSQL, [$donationDate, $_SESSION['user_id']]);
            
            // Clear sidebar cache since last donation date affects eligibility status
            clearSidebarCache();
            
            logActivity($_SESSION['user_id'], 'donation_added', "Added donation record for $donationDate");
            
            $success = "Donation record added successfully! It will be verified by administrators.";
            
        } catch (Exception $e) {
            $error = $e->getMessage();
            logActivity($_SESSION['user_id'], 'donation_add_error', $error);
        }
    } else {
        $error = "Invalid security token.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Donation Record - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="includes/sidebar.css" rel="stylesheet">
    <style>
        .form-control:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 0.2rem rgba(220, 38, 38, 0.25);
        }
        
        .btn-primary {
            background-color: #dc2626;
            border-color: #dc2626;
        }
        
        .btn-primary:hover {
            background-color: #991b1b;
            border-color: #991b1b;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid p-4">
            <!-- Page Header -->
            <div class="page-header">
                <h2><i class="fas fa-plus-circle me-2"></i>Add Donation Record</h2>
                <p class="text-muted mb-0">Record your blood donation for tracking and verification</p>
            </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                                <div class="mt-2">
                                    <a href="dashboard.php" class="btn btn-sm btn-success">
                                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="donation_date" class="form-label">Donation Date *</label>
                                    <input type="date" class="form-control" id="donation_date" name="donation_date" 
                                           max="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="units_donated" class="form-label">Units Donated</label>
                                    <select class="form-select" id="units_donated" name="units_donated">
                                        <option value="1">1 Unit</option>
                                        <option value="2">2 Units</option>
                                        <option value="3">3 Units</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="location" class="form-label">Donation Location *</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       placeholder="e.g., City Hospital, Mobile Blood Drive, etc." required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="blood_bank_name" class="form-label">Blood Bank/Organization Name</label>
                                <input type="text" class="form-control" id="blood_bank_name" name="blood_bank_name" 
                                       placeholder="e.g., Red Cross Blood Bank">
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Additional Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" 
                                          placeholder="Any additional information about the donation..."></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> Your donation record will be pending verification by administrators. 
                                Please ensure all information is accurate.
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-save me-2"></i>Add Donation Record
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="includes/sidebar.js"></script>
</body>
</html>
