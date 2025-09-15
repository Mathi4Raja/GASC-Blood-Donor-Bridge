<?php
// Add cache-busting headers first
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Initialize secure session BEFORE any database connections
require_once '../config/session.php';

// Now safely include database and other configs
require_once '../config/database.php';
require_once '../config/email.php';

// Include configuration files
require_once '../config/database.php';
require_once '../config/email.php';
error_log("Session initialized - ID: " . session_id());
error_log("CSRF token in session: " . ($_SESSION['csrf_token'] ?? 'NONE'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF protection
        $submittedToken = $_POST['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        
        // Verify CSRF token
        if (empty($submittedToken) || empty($sessionToken) || !hash_equals($sessionToken, $submittedToken)) {
            throw new Exception('Invalid security token. Please try again.');
        }
        
        // Validate input
        $requesterName = sanitizeInput($_POST['requester_name'] ?? '');
        $requesterEmail = sanitizeInput($_POST['requester_email'] ?? '');
        $requesterPhone = sanitizeInput($_POST['requester_phone'] ?? '');
        $bloodGroup = sanitizeInput($_POST['blood_group'] ?? '');
        $urgency = sanitizeInput($_POST['urgency'] ?? '');
        $details = sanitizeInput($_POST['details'] ?? '');
        $city = sanitizeInput($_POST['city'] ?? '');
        $unitsNeeded = intval($_POST['units_needed'] ?? 1);
        // Hospital fields removed from frontend
        
        // Validation
        if (empty($requesterName) || empty($requesterEmail) || empty($requesterPhone) || 
            empty($bloodGroup) || empty($urgency) || empty($details) || empty($city)) {
            throw new Exception('All required fields must be filled.');
        }
        
        if (!isValidEmail($requesterEmail)) {
            throw new Exception('Please enter a valid email address.');
        }
        
        if (!isValidPhone($requesterPhone)) {
            throw new Exception('Please enter a valid 10-digit phone number.');
        }
        
        if (!isValidBloodGroup($bloodGroup)) {
            throw new Exception('Please select a valid blood group.');
        }
        
        if (!in_array($urgency, ['Critical', 'Urgent', 'Normal'])) {
            throw new Exception('Please select a valid urgency level.');
        }
        
        if ($unitsNeeded < 1 || $unitsNeeded > 10) {
            throw new Exception('Units needed must be between 1 and 10.');
        }
        
        // Check request limits per user per day
        require_once '../config/system-settings.php';
        $maxRequestsPerDay = SystemSettings::getMaxRequestsPerUser();
        
        // Initialize database connection
        $db = new Database();
        
        // Count requests from this email today
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        $requestCountQuery = "SELECT COUNT(*) as count FROM blood_requests 
                             WHERE requester_email = ? 
                             AND created_at BETWEEN ? AND ?";
        $requestCountResult = $db->query($requestCountQuery, [$requesterEmail, $todayStart, $todayEnd]);
        $requestCount = $requestCountResult->fetch_assoc()['count'];
        
        if ($requestCount >= $maxRequestsPerDay) {
            throw new Exception("Request limit exceeded. You can only submit {$maxRequestsPerDay} request(s) per day.");
        }
        
        // Calculate expiry date based on urgency
        $expiryDays = [
            'Critical' => 1,
            'Urgent' => 3,
            'Normal' => 7
        ];
        
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays[$urgency]} days"));
        
        // Insert blood request
        $sql = "INSERT INTO blood_requests (requester_name, requester_email, requester_phone, blood_group, urgency, details, city, units_needed, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param('sssssssis', 
            $requesterName, $requesterEmail, $requesterPhone, $bloodGroup, 
            $urgency, $details, $city, $unitsNeeded, $expiresAt
        );
        
        if ($stmt->execute()) {
            $requestId = $db->lastInsertId();
            
            // Get available donors count
            $donorsQuery = "SELECT COUNT(*) as donor_count FROM users 
                           WHERE blood_group = ? 
                           AND is_available = TRUE AND is_verified = TRUE AND is_active = TRUE AND user_type = 'donor'";
            $donorsResult = $db->query($donorsQuery, [$bloodGroup]);
            $donorCount = $donorsResult->fetch_assoc()['donor_count'];
            
            // Send confirmation email to requester
            $emailSubject = "GASC Blood Bridge - Blood Request Received";
            $emailBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: linear-gradient(135deg, #dc2626, #991b1b); padding: 20px; text-align: center;'>
                    <h1 style='color: white; margin: 0;'>GASC Blood Bridge</h1>
                </div>
                <div style='padding: 30px; background: #f8f9fa;'>
                    <h2>Blood Request Received</h2>
                    <p>Dear $requesterName,</p>
                    <p>Your blood request has been successfully submitted. Here are the details:</p>
                    <div style='background: white; border-left: 4px solid #dc2626; padding: 20px; margin: 20px 0;'>
                        <p><strong>Request ID:</strong> #$requestId</p>
                        <p><strong>Blood Group:</strong> $bloodGroup</p>
                        <p><strong>Urgency:</strong> $urgency</p>
                        <p><strong>Units Needed:</strong> $unitsNeeded</p>
                        <p><strong>City:</strong> $city</p>
                        <p><strong>Available Donors:</strong> $donorCount</p>
                    </div>
                    <p>Our team will contact available donors and get back to you soon.</p>
                    <p>You can track your request status by visiting our website with your request ID.</p>
                    <hr style='margin: 30px 0; border: 1px solid #dee2e6;'>
                    <p style='color: #6c757d; font-size: 14px;'>
                        Best regards,<br>
                        GASC Blood Bridge Team<br>
                        Emergency Helpline: +91-9999999999
                    </p>
                </div>
            </div>
            ";
            
            sendEmail($requesterEmail, $emailSubject, $emailBody);
            
            // Send notifications to eligible donors
            require_once '../config/notifications.php';
            $notificationResult = notifyDonorsForBloodRequest($requestId);
            
            if ($notificationResult && is_array($notificationResult)) {
                logActivity(null, 'blood_request_notifications', 
                    "Request #$requestId notifications: {$notificationResult['donors_notified']} donors, " .
                    "{$notificationResult['emails_sent']} emails sent");
            }
            
            // Log activity
            logActivity(null, 'blood_request_created', "New blood request: $bloodGroup in $city (Request ID: $requestId)");
            
            // Redirect to success page with request details
            $_SESSION['request_success'] = [
                'request_id' => $requestId,
                'blood_group' => $bloodGroup,
                'city' => $city,
                'donor_count' => $donorCount,
                'urgency' => $urgency
            ];
            
            header('Location: request-success.php');
            exit;
            
        } else {
            throw new Exception('Failed to submit blood request. Please try again.');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        logActivity(null, 'blood_request_failed', $error . " - Email: " . ($requesterEmail ?? 'unknown'));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Blood - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .request-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        
        .request-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            margin: 0 auto;
        }
        
        body {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
        }
        
        .request-header {
            background: linear-gradient(135deg, #fee2e2, #ffffff);
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .urgency-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            margin-top: 0.75rem;
        }
        
        .urgency-card {
            position: relative;
        }
        
        .urgency-card input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .urgency-card label {
            display: block;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 100%;
            font-size: 0.9rem;
        }
        
        .urgency-card input[type="radio"]:checked + label {
            border-color: #dc2626;
            background: #fee2e2;
            font-weight: 600;
        }
        
        .urgency-critical label {
            border-color: #dc2626;
        }
        
        /* Mobile responsive urgency cards */
        @media (max-width: 768px) {
            .request-container {
                padding: 1rem;
                align-items: flex-start;
                min-height: auto;
                justify-content: center;
            }
            
            .request-card {
                margin: 0;
                max-width: 100%;
            }
            
            .request-header {
                padding: 1.25rem 1rem;
            }
            
            .request-header h2 {
                font-size: 1.5rem;
            }
            
            .urgency-cards {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .urgency-card label {
                padding: 0.875rem;
                font-size: 0.85rem;
            }
            
            .blood-group-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 576px) {
            .request-container {
                padding: 0.75rem 0.5rem;
            }
            
            .request-card {
                border-radius: 8px;
            }
            
            .request-header {
                padding: 1rem 0.75rem;
            }
            
            .request-header h2 {
                font-size: 1.25rem;
            }
            
            .urgency-card label {
                padding: 0.75rem;
                font-size: 0.8rem;
            }
            
            .p-4 {
                padding: 1rem !important;
            }
        }
        
        @media (max-width: 480px) {
            .request-container {
                padding: 0.5rem 0.25rem;
            }
            
            .request-card {
                border-radius: 6px;
            }
            
            .p-4 {
                padding: 0.75rem !important;
            }
        }
        
        .urgency-urgent label {
            border-color: #f59e0b;
        }
        
        .urgency-normal label {
            border-color: #10b981;
        }
        
        .urgency-card input[type="radio"]:checked + label.urgency-critical {
            background: #dc2626;
            color: white;
        }
        
        .urgency-card input[type="radio"]:checked + label.urgency-urgent {
            background: #f59e0b;
            color: white;
        }
        
        .urgency-card input[type="radio"]:checked + label.urgency-normal {
            background: #10b981;
            color: white;
        }
        
        .blood-group-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        
        .blood-group-option {
            position: relative;
        }
        
        .blood-group-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .blood-group-option label {
            display: block;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .blood-group-option input[type="radio"]:checked + label {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }
        
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b3d7ff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .urgency-cards {
                grid-template-columns: 1fr;
            }
            
            .blood-group-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .request-card {
                margin: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="request-container">
        <div class="request-card">
            <div style="position: relative;">
                <a href="../index.php" class="back-home-btn-card position-absolute top-0 start-0 mt-3 ms-3 text-decoration-none" style="z-index:1050;">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>
        <style>
            .back-home-btn-card {
                background: #fff;
                color: #dc2626 !important;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                box-shadow: 0 2px 10px rgba(220,38,38,0.10);
                border: 1px solid #f3f4f6;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 1.2rem;
                transition: background 0.2s, color 0.2s, box-shadow 0.2s;
            }
            .back-home-btn-card:hover {
                background: #fee2e2;
                color: #991b1b !important;
                box-shadow: 0 4px 16px rgba(220,38,38,0.18);
                text-decoration: none;
            }
            @media (max-width: 576px) {
                .back-home-btn-card {
                    width: 36px;
                    height: 36px;
                    font-size: 1rem;
                    margin-top: 10px !important;
                    margin-left: 10px !important;
                }
            }
        </style>
                <div class="request-header">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <h2 class="text-danger fw-bold mb-0">GASC Blood Bridge</h2>
                    </div>
                    <h3 class="text-dark mb-2">Request Blood</h3>
                    <p class="text-muted mb-0">Help us connect you with life-saving donors</p>
                </div>
                
                <div class="p-4">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="info-box">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            <strong>Important Information:</strong>
                        </div>
                        <ul class="mt-2 mb-0">
                            <li>All fields marked with * are mandatory</li>
                            <li>Requests expire based on urgency (Critical: 1 day, Urgent: 3 days, Normal: 7 days)</li>
                            <li>You will receive email updates about your request status</li>
                            <li>Emergency cases will be prioritized</li>
                        </ul>
                    </div>
                    
                    <form method="POST" action="" id="requestForm" novalidate>
                        <?php 
                        // Ensure we have a CSRF token
                        if (!isset($_SESSION['csrf_token'])) {
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        }
                        $csrfToken = $_SESSION['csrf_token'];
                        ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="requester_name" class="form-label">
                                    <i class="fas fa-user text-danger me-1"></i>Your Name *
                                </label>
                                <input type="text" class="form-control" id="requester_name" name="requester_name" 
                                       value="<?php echo $requesterName ?? ''; ?>" required
                                       placeholder="Enter your full name">
                                <div class="invalid-feedback">Please provide your name.</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="requester_email" class="form-label">
                                    <i class="fas fa-envelope text-danger me-1"></i>Email Address *
                                </label>
                                <input type="email" class="form-control" id="requester_email" name="requester_email" 
                                       value="<?php echo $requesterEmail ?? ''; ?>" required
                                       placeholder="your.email@example.com">
                                <div class="invalid-feedback">Please provide a valid email address.</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="requester_phone" class="form-label">
                                    <i class="fas fa-phone text-danger me-1"></i>Phone Number *
                                </label>
                                <input type="tel" class="form-control" id="requester_phone" name="requester_phone" 
                                       value="<?php echo $requesterPhone ?? ''; ?>" required
                                       placeholder="10-digit mobile number">
                                <div class="invalid-feedback">Please provide a valid 10-digit phone number.</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">
                                    <i class="fas fa-map-marker-alt text-danger me-1"></i>City *
                                </label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       value="<?php echo $city ?? ''; ?>" required
                                       placeholder="City where blood is needed">
                                <div class="invalid-feedback">Please provide the city.</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="blood_group" class="form-label">
                                    <i class="fas fa-tint text-danger me-1"></i>Blood Group Required *
                                </label>
                                <input type="text" class="form-control" id="blood_group" name="blood_group" 
                                       value="<?php echo $bloodGroup ?? ''; ?>" required
                                       placeholder="e.g., A+, O-, B+">
                                <div class="invalid-feedback">Please provide your blood group (e.g., A+, O-, B+).</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="units_needed" class="form-label">
                                    <i class="fas fa-vial text-danger me-1"></i>Units Needed *
                                </label>
                                <select class="form-select" id="units_needed" name="units_needed" required>
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <option value="<?php echo $i; ?>" 
                                                <?php echo (($unitsNeeded ?? 1) == $i) ? 'selected' : ''; ?>>
                                            <?php echo $i; ?> Unit<?php echo $i > 1 ? 's' : ''; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <div class="invalid-feedback">Please select units needed.</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-exclamation-triangle text-danger me-1"></i>Urgency Level *
                            </label>
                            <div class="urgency-cards">
                                <div class="urgency-card">
                                    <input type="radio" id="critical" name="urgency" value="Critical" 
                                           <?php echo (($urgency ?? '') === 'Critical') ? 'checked' : ''; ?> required>
                                    <label for="critical" class="urgency-critical">
                                        <i class="fas fa-bolt text-danger d-block mb-2" style="font-size: 24px;"></i>
                                        <strong>Critical</strong>
                                        <small class="d-block text-muted">Emergency (1 day)</small>
                                    </label>
                                </div>
                                <div class="urgency-card">
                                    <input type="radio" id="urgent" name="urgency" value="Urgent" 
                                           <?php echo (($urgency ?? '') === 'Urgent') ? 'checked' : ''; ?> required>
                                    <label for="urgent" class="urgency-urgent">
                                        <i class="fas fa-clock text-warning d-block mb-2" style="font-size: 24px;"></i>
                                        <strong>Urgent</strong>
                                        <small class="d-block text-muted">ASAP (3 days)</small>
                                    </label>
                                </div>
                                <div class="urgency-card">
                                    <input type="radio" id="normal" name="urgency" value="Normal" 
                                           <?php echo (($urgency ?? '') === 'Normal') ? 'checked' : ''; ?> required>
                                    <label for="normal" class="urgency-normal">
                                        <i class="fas fa-calendar text-success d-block mb-2" style="font-size: 24px;"></i>
                                        <strong>Normal</strong>
                                        <small class="d-block text-muted">Planned (7 days)</small>
                                    </label>
                                </div>
                            </div>
                            <div class="invalid-feedback">Please select urgency level.</div>
                        </div>
                        
                        <div class="row">
                            <!-- Hospital/Clinic Name and Hospital Address fields removed as per privacy-focused design -->
                        </div>
                        
                        <div class="mb-3">
                            <label for="details" class="form-label">
                                <i class="fas fa-file-alt text-danger me-1"></i>Additional Details *
                            </label>
                            <textarea class="form-control" id="details" name="details" rows="4" required
                                      placeholder="Please provide details about the Hospital location, patient condition, any specific requirements, etc."><?php echo $details ?? ''; ?></textarea>
                            <div class="invalid-feedback">Please provide additional details.</div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="consent" required>
                                <label class="form-check-label" for="consent">
                                    I understand that this is a request for blood donation and not a guarantee. 
                                    I consent to share my contact information with potential donors. *
                                </label>
                                <div class="invalid-feedback">You must provide consent.</div>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-danger btn-lg" id="submitBtn">
                                <span class="btn-text">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Blood Request
                                </span>
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4 pt-3 border-top">
                        <p class="text-muted mb-2">Already submitted a request?</p>
                        <a href="../requestor/login.php" class="btn btn-outline-danger">
                            <i class="fas fa-search me-2"></i>Track Your Requests
                        </a>
                    </div>
                    
                    <div class="text-center mt-3">
                        <p class="text-muted mb-2">
                            <i class="fas fa-phone me-1"></i>Emergency Helpline: 
                            <a href="tel:+919999999999" class="text-danger text-decoration-none">+91-9999999999</a>
                        </p>
                        <!-- Back to Home button moved to top-left inside card -->
                    </div>
                </div>
            </div>
        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Enhanced Page Loader -->
    <div class="loader-overlay" id="pageLoader">
        <div class="loader-content">
            <div class="loader-blood"></div>
            <div class="loader-brand">GASC Blood Bridge</div>
            <div class="loader-text">Processing Request...</div>
            <div class="progress-loader"></div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('requestForm');
            
            // Phone number validation
            document.getElementById('requester_phone').addEventListener('input', function() {
                const phone = this.value.replace(/\D/g, '');
                if (phone.length === 10 && phone.match(/^[6-9]/)) {
                    this.setCustomValidity('');
                } else {
                    this.setCustomValidity('Please enter a valid 10-digit phone number starting with 6-9');
                }
            });
            
            // Form validation
            form.addEventListener('submit', function(e) {
                // Check form validity
                const isValid = form.checkValidity();
                
                if (!isValid) {
                    e.preventDefault();
                    e.stopPropagation();
                    form.classList.add('was-validated');
                    return;
                }
                
                form.classList.add('was-validated');
            });
            
            // Auto-resize textarea
            const textarea = document.getElementById('details');
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });
    </script>
</body>
</html>
