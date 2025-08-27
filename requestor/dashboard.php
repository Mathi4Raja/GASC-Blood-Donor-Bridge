<?php
session_start();

// Simple session-based authentication for requestors
if (!isset($_SESSION['requestor_email'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';

$db = new Database();
$requestorEmail = $_SESSION['requestor_email'];

// Get requestor's name from their blood requests
$nameQuery = "SELECT DISTINCT requester_name FROM blood_requests WHERE requester_email = ? LIMIT 1";
$nameResult = $db->query($nameQuery, [$requestorEmail]);
$requestorName = $nameResult->num_rows > 0 ? $nameResult->fetch_assoc()['requester_name'] : $requestorEmail;

// Get requestor's blood requests with filters
$statusFilter = $_GET['status'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';

$whereClause = "WHERE requester_email = ?";
$params = [$requestorEmail];

if ($statusFilter !== 'all') {
    $whereClause .= " AND status = ?";
    $params[] = $statusFilter;
}

$sql = "SELECT * FROM blood_requests $whereClause ORDER BY $sortBy $sortOrder";
$result = $db->query($sql, $params);
$requests = $result->fetch_all(MYSQLI_ASSOC);

// Get summary statistics
$statsQuery = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_requests,
    SUM(CASE WHEN status = 'Fulfilled' THEN 1 ELSE 0 END) as fulfilled_requests,
    SUM(CASE WHEN status = 'Expired' THEN 1 ELSE 0 END) as expired_requests
    FROM blood_requests WHERE requester_email = ?";
$stats = $db->query($statsQuery, [$requestorEmail])->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Blood Requests - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .requestor-header {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: white;
            padding: 2rem 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #dc2626;
        }
        
        .request-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid;
        }
        
        .status-active { border-left-color: #28a745; }
        .status-fulfilled { border-left-color: #17a2b8; }
        .status-expired { border-left-color: #6c757d; }
        .status-cancelled { border-left-color: #dc3545; }
        
        .urgency-critical { color: #dc3545; }
        .urgency-urgent { color: #fd7e14; }
        .urgency-normal { color: #28a745; }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="requestor-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-0">My Blood Requests</h1>
                    <p class="mb-0 opacity-75">Welcome back, <?php echo htmlspecialchars($requestorName); ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <button type="button" class="btn btn-light me-2" data-bs-toggle="modal" data-bs-target="#newRequestModal">
                        <i class="fas fa-plus me-1"></i>New Request
                    </button>
                    <a href="logout.php" class="btn btn-outline-light">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-4">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_requests']; ?></div>
                    <div class="text-muted">Total Requests</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-number text-success"><?php echo $stats['active_requests']; ?></div>
                    <div class="text-muted">Active</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-number text-info"><?php echo $stats['fulfilled_requests']; ?></div>
                    <div class="text-muted">Fulfilled</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-number text-secondary"><?php echo $stats['expired_requests']; ?></div>
                    <div class="text-muted">Expired</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Filter by Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Active" <?php echo $statusFilter === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Fulfilled" <?php echo $statusFilter === 'Fulfilled' ? 'selected' : ''; ?>>Fulfilled</option>
                            <option value="Expired" <?php echo $statusFilter === 'Expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sort by</label>
                        <select name="sort" class="form-select">
                            <option value="created_at" <?php echo $sortBy === 'created_at' ? 'selected' : ''; ?>>Date Created</option>
                            <option value="urgency" <?php echo $sortBy === 'urgency' ? 'selected' : ''; ?>>Urgency</option>
                            <option value="blood_group" <?php echo $sortBy === 'blood_group' ? 'selected' : ''; ?>>Blood Group</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Order</label>
                        <select name="order" class="form-select">
                            <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Oldest First</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-danger d-block">
                            <i class="fas fa-filter me-1"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Requests List -->
        <div class="row">
            <?php if (empty($requests)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No blood requests found</h4>
                        <p class="text-muted">You haven't made any blood requests yet.</p>
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#newRequestModal">
                            <i class="fas fa-plus me-1"></i>Create Your First Request
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="request-card status-<?php echo strtolower($request['status']); ?>">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-danger">Request #<?php echo $request['id']; ?></span>
                                <span class="badge bg-<?php echo $request['status'] === 'Active' ? 'success' : ($request['status'] === 'Fulfilled' ? 'info' : 'secondary'); ?>">
                                    <?php echo $request['status']; ?>
                                </span>
                            </div>
                            
                            <h6 class="mb-2">
                                <i class="fas fa-tint text-danger me-1"></i>
                                <?php echo $request['blood_group']; ?> Blood
                            </h6>
                            
                            <div class="mb-2">
                                <small class="text-muted">
                                    <i class="fas fa-exclamation-circle me-1"></i>
                                    <span class="urgency-<?php echo strtolower($request['urgency']); ?>">
                                        <?php echo $request['urgency']; ?>
                                    </span>
                                </small>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted">
                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo $request['city']; ?>
                                </small>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted">
                                    <i class="fas fa-vials me-1"></i><?php echo $request['units_needed']; ?> unit(s) needed
                                </small>
                            </div>
                            
                            <p class="text-muted small mb-2">
                                <?php echo htmlspecialchars(substr($request['details'], 0, 100)); ?>
                                <?php if (strlen($request['details']) > 100): ?>...<?php endif; ?>
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                </small>
                                <div>
                                    <button class="btn btn-sm btn-outline-danger" onclick="viewRequest(<?php echo $request['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($request['status'] === 'Active'): ?>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="cancelRequest(<?php echo $request['id']; ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($request['status'] === 'Active'): ?>
                                <div class="mt-2 pt-2 border-top">
                                    <small class="text-warning">
                                        <i class="fas fa-clock me-1"></i>
                                        Expires: <?php echo date('M d, Y H:i', strtotime($request['expires_at'])); ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Request Details Modal -->
    <div class="modal fade" id="requestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="requestModalContent">
                    <!-- Content loaded via JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- New Blood Request Modal -->
    <div class="modal fade" id="newRequestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-tint me-2"></i>New Blood Request
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Important:</strong> All fields are mandatory. Request expires based on urgency level.
                    </div>
                    
                    <form id="newRequestForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="modal_requester_name" class="form-label">
                                    <i class="fas fa-user text-danger me-1"></i>Your Name *
                                </label>
                                <input type="text" class="form-control" id="modal_requester_name" name="requester_name" 
                                       value="<?php echo htmlspecialchars($requestorName); ?>" required>
                                <div class="invalid-feedback">Please provide your name.</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="modal_requester_email" class="form-label">
                                    <i class="fas fa-envelope text-danger me-1"></i>Email Address *
                                </label>
                                <input type="email" class="form-control" id="modal_requester_email" name="requester_email" 
                                       value="<?php echo htmlspecialchars($requestorEmail); ?>" required>
                                <div class="invalid-feedback">Please provide a valid email address.</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="modal_requester_phone" class="form-label">
                                    <i class="fas fa-phone text-danger me-1"></i>Phone Number *
                                </label>
                                <input type="tel" class="form-control" id="modal_requester_phone" name="requester_phone" 
                                       required placeholder="10-digit mobile number">
                                <div class="invalid-feedback">Please provide a valid 10-digit phone number.</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="modal_city" class="form-label">
                                    <i class="fas fa-map-marker-alt text-danger me-1"></i>City *
                                </label>
                                <input type="text" class="form-control" id="modal_city" name="city" 
                                       required placeholder="City where blood is needed">
                                <div class="invalid-feedback">Please provide the city.</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="modal_blood_group" class="form-label">
                                    <i class="fas fa-tint text-danger me-1"></i>Blood Group Required *
                                </label>
                                <select class="form-select" id="modal_blood_group" name="blood_group" required>
                                    <option value="">Select Blood Group</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                                <div class="invalid-feedback">Please select a blood group.</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="modal_units_needed" class="form-label">
                                    <i class="fas fa-vial text-danger me-1"></i>Units Needed *
                                </label>
                                <select class="form-select" id="modal_units_needed" name="units_needed" required>
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?> Unit<?php echo $i > 1 ? 's' : ''; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <div class="invalid-feedback">Please select units needed.</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-exclamation-triangle text-danger me-1"></i>Urgency Level *
                            </label>
                            <div class="row">
                                <div class="col-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="urgency" id="modal_critical" value="Critical" required>
                                        <label class="form-check-label text-danger fw-bold" for="modal_critical">
                                            <i class="fas fa-bolt"></i> Critical (1 day)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="urgency" id="modal_urgent" value="Urgent" required>
                                        <label class="form-check-label text-warning fw-bold" for="modal_urgent">
                                            <i class="fas fa-clock"></i> Urgent (3 days)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="urgency" id="modal_normal" value="Normal" required>
                                        <label class="form-check-label text-success fw-bold" for="modal_normal">
                                            <i class="fas fa-calendar"></i> Normal (7 days)
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="invalid-feedback">Please select urgency level.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modal_details" class="form-label">
                                <i class="fas fa-file-alt text-danger me-1"></i>Additional Details *
                            </label>
                            <textarea class="form-control" id="modal_details" name="details" rows="4" required
                                      placeholder="Please provide details about the Hospital location, patient condition, any specific requirements, etc."></textarea>
                            <div class="invalid-feedback">Please provide additional details.</div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="modal_consent" required>
                                <label class="form-check-label" for="modal_consent">
                                    I understand that this is a request for blood donation and not a guarantee. 
                                    I consent to share my contact information with potential donors. *
                                </label>
                                <div class="invalid-feedback">You must provide consent.</div>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Emergency Helpline:</strong> <a href="tel:+919999999999">+91-9999999999</a>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="newRequestForm" class="btn btn-danger">
                        <i class="fas fa-paper-plane me-2"></i>Submit Request
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewRequest(requestId) {
            // Load request details via AJAX
            fetch(`get-request-details.php?id=${requestId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('requestModalContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('requestModal')).show();
                    } else {
                        alert('Error loading request details: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error loading request details');
                });
        }

        function cancelRequest(requestId) {
            if (confirm('Are you sure you want to cancel this blood request?')) {
                fetch('cancel-request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        request_id: requestId,
                        csrf_token: '<?php echo generateCSRFToken(); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Request cancelled successfully');
                        window.location.reload();
                    } else {
                        alert('Error cancelling request: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error cancelling request');
                });
            }
        }

        // Handle new request form submission
        document.getElementById('newRequestForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!this.checkValidity()) {
                e.stopPropagation();
                this.classList.add('was-validated');
                return;
            }

            const formData = new FormData(this);
            const submitBtn = document.querySelector('#newRequestModal .btn-danger');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            submitBtn.disabled = true;

            fetch('submit-request.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success - close modal and reload page
                    bootstrap.Modal.getInstance(document.getElementById('newRequestModal')).hide();
                    alert('Blood request submitted successfully! You will receive a confirmation email shortly.');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error submitting request. Please try again.');
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        // Phone number validation for modal form
        document.getElementById('modal_requester_phone').addEventListener('input', function() {
            const phone = this.value.replace(/\D/g, '');
            if (phone.length === 10 && phone.match(/^[6-9]/)) {
                this.setCustomValidity('');
            } else {
                this.setCustomValidity('Please enter a valid 10-digit phone number starting with 6-9');
            }
        });

        // Reset form when modal is closed
        document.getElementById('newRequestModal').addEventListener('hidden.bs.modal', function() {
            const form = document.getElementById('newRequestForm');
            form.reset();
            form.classList.remove('was-validated');
        });
    </script>
</body>
</html>
