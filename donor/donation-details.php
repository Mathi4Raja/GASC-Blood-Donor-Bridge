<?php
require_once '../config/database.php';

// Check if user is logged in as donor
requireRole(['donor']);

$error = null;
$donation = null;
$donationId = intval($_GET['id'] ?? 0);

try {
    $db = new Database();
    
    // Get donation details - ensure it belongs to the current user
    $sql = "SELECT dah.*, u.name as donor_name, u.blood_group, 
                   CASE WHEN dah.verified_by IS NOT NULL THEN 
                        (SELECT name FROM users WHERE id = dah.verified_by) 
                   ELSE NULL END as verified_by_name
            FROM donor_availability_history dah
            JOIN users u ON dah.donor_id = u.id
            WHERE dah.id = ? AND dah.donor_id = ?";
            
    $result = $db->query($sql, [$donationId, $_SESSION['user_id']]);
    $donation = $result->fetch_assoc();
    
    if (!$donation) {
        throw new Exception('Donation record not found or access denied.');
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
    logActivity($_SESSION['user_id'] ?? null, 'donation_view_error', $error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation Details - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .donation-header {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: white;
            padding: 2rem 0;
        }
        
        .detail-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
        }
        
        .detail-card:hover {
            transform: translateY(-2px);
        }
        
        .status-verified {
            color: #28a745;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
        }
        
        .status-pending {
            color: #856404;
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
        }
        
        .info-item {
            border-bottom: 1px solid #e9ecef;
            padding: 1rem 0;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .blood-group-badge {
            background: #dc2626;
            color: white;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        /* Mobile Navigation Styles */
        .mobile-nav-toggle {
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1050;
            background: #dc2626;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            font-size: 18px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        .mobile-nav-toggle:hover {
            background: #991b1b;
            color: white;
            transform: scale(1.05);
        }
        
        @media (max-width: 767.98px) {
            .container.mt-5 {
                margin-top: 80px !important;
                padding-top: 20px;
            }
            
            .container.mt-4 {
                margin-top: 1rem !important;
                padding-top: 20px;
            }
        }
    </style>
</head>
<body class="bg-light">
    <!-- Mobile Navigation Toggle -->
    <button class="mobile-nav-toggle d-lg-none" onclick="window.location.href='dashboard.php'">
        <i class="fas fa-arrow-left"></i>
    </button>
    
    <?php if ($error): ?>
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-exclamation-triangle text-danger" style="font-size: 48px;"></i>
                            <h4 class="text-danger mt-3">Error</h4>
                            <p class="text-muted"><?php echo htmlspecialchars($error); ?></p>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="donation-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2><i class="fas fa-heart me-2"></i>Donation Details</h2>
                        <p class="mb-0">Complete information about your blood donation</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="dashboard.php#history" class="btn btn-light">
                            <i class="fas fa-arrow-left me-2"></i>Back to History
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="container mt-4">
            <div class="row">
                <!-- Main Details -->
                <div class="col-lg-8 mb-4">
                    <div class="card detail-card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle text-danger me-2"></i>
                                Donation Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="info-item">
                                <div class="row">
                                    <div class="col-sm-4"><strong>Donation Date:</strong></div>
                                    <div class="col-sm-8">
                                        <i class="fas fa-calendar-alt text-primary me-2"></i>
                                        <?php echo date('l, F d, Y', strtotime($donation['donation_date'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="row">
                                    <div class="col-sm-4"><strong>Location:</strong></div>
                                    <div class="col-sm-8">
                                        <i class="fas fa-map-marker-alt text-danger me-2"></i>
                                        <?php echo htmlspecialchars($donation['location'] ?? 'Not specified'); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="row">
                                    <div class="col-sm-4"><strong>Units Donated:</strong></div>
                                    <div class="col-sm-8">
                                        <span class="badge bg-danger fs-6">
                                            <i class="fas fa-tint me-1"></i>
                                            <?php echo $donation['units_donated'] ?? 1; ?> unit<?php echo ($donation['units_donated'] ?? 1) > 1 ? 's' : ''; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($donation['blood_bank_name']): ?>
                            <div class="info-item">
                                <div class="row">
                                    <div class="col-sm-4"><strong>Blood Bank:</strong></div>
                                    <div class="col-sm-8">
                                        <i class="fas fa-hospital text-info me-2"></i>
                                        <?php echo htmlspecialchars($donation['blood_bank_name']); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($donation['notes']): ?>
                            <div class="info-item">
                                <div class="row">
                                    <div class="col-sm-4"><strong>Notes:</strong></div>
                                    <div class="col-sm-8">
                                        <i class="fas fa-sticky-note text-secondary me-2"></i>
                                        <?php echo nl2br(htmlspecialchars($donation['notes'])); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Status & Summary -->
                <div class="col-lg-4 mb-4">
                    <!-- Status Card -->
                    <div class="card detail-card mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">
                                <i class="fas fa-check-circle me-2"></i>
                                Verification Status
                            </h6>
                        </div>
                        <div class="card-body text-center">
                            <?php if ($donation['is_verified']): ?>
                                <div class="status-verified">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Verified</strong>
                                </div>
                                <?php if ($donation['verified_by_name']): ?>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            Verified by: <strong><?php echo htmlspecialchars($donation['verified_by_name']); ?></strong>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="status-pending">
                                    <i class="fas fa-clock me-2"></i>
                                    <strong>Pending Verification</strong>
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        Your donation is awaiting verification by administrators.
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Donor Summary -->
                    <div class="card detail-card">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">
                                <i class="fas fa-user me-2"></i>
                                Donor Summary
                            </h6>
                        </div>
                        <div class="card-body text-center">
                            <div class="blood-group-badge mx-auto mb-3">
                                <?php echo $donation['blood_group']; ?>
                            </div>
                            <h6><?php echo htmlspecialchars($donation['donor_name']); ?></h6>
                            
                            <div class="mt-3">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Recorded</small>
                                        <strong><?php echo date('M d, Y', strtotime($donation['created_at'])); ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Updated</small>
                                        <strong><?php echo date('M d, Y', strtotime($donation['updated_at'])); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="row mt-4">
                <div class="col-12 text-center">
                    <div class="btn-group" role="group">
                        <a href="dashboard.php#history" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-2"></i>View All Donations
                        </a>
                        <a href="add-donation.php" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Add New Donation
                        </a>
                        <button class="btn btn-primary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Details
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Print styling
        window.addEventListener('beforeprint', function() {
            document.body.classList.add('printing');
        });
        
        window.addEventListener('afterprint', function() {
            document.body.classList.remove('printing');
        });
    </script>
    
    <style>
        @media print {
            .btn, .donation-header {
                display: none !important;
            }
            
            .card {
                border: 1px solid #000 !important;
                box-shadow: none !important;
            }
            
            .container {
                max-width: none !important;
            }
        }
    </style>
</body>
</html>
