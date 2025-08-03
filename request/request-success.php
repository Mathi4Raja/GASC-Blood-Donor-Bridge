<?php
require_once '../config/database.php';

$requestSuccess = $_SESSION['request_success'] ?? null;
unset($_SESSION['request_success']);

if (!$requestSuccess) {
    header('Location: blood-request.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Submitted - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .success-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            display: flex;
            align-items: center;
            padding: 20px 0;
        }
        
        .success-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 600px;
            margin: 0 auto;
            text-align: center;
        }
        
        .success-header {
            background: linear-gradient(135deg, #dcfce7, #white);
            padding: 3rem 2rem 2rem;
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #10b981;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 3rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .request-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .donor-count-badge {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: white;
            border-radius: 50px;
            padding: 15px 25px;
            font-size: 1.2rem;
            font-weight: bold;
            display: inline-block;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="container">
            <div class="success-card">
                <div class="success-header">
                    <div class="success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <!-- GASC Logo removed as per privacy-focused design -->
                        <h2 class="text-success fw-bold mb-0">GASC Blood Bridge</h2>
                    </div>
                    <h3 class="text-dark mb-2">Request Submitted Successfully!</h3>
                    <p class="text-muted">Your blood request has been received and is now active</p>
                </div>
                
                <div class="p-4">
                    <div class="request-details">
                        <h5 class="text-dark mb-3">Request Details</h5>
                        <div class="row text-start">
                            <div class="col-sm-6 mb-2">
                                <strong>Request ID:</strong> #<?php echo $requestSuccess['request_id']; ?>
                            </div>
                            <div class="col-sm-6 mb-2">
                                <strong>Blood Group:</strong> 
                                <span class="badge bg-danger"><?php echo $requestSuccess['blood_group']; ?></span>
                            </div>
                            <div class="col-sm-6 mb-2">
                                <strong>City:</strong> <?php echo htmlspecialchars($requestSuccess['city']); ?>
                            </div>
                            <div class="col-sm-6 mb-2">
                                <strong>Urgency:</strong> 
                                <span class="badge bg-<?php echo strtolower($requestSuccess['urgency']) === 'critical' ? 'danger' : (strtolower($requestSuccess['urgency']) === 'urgent' ? 'warning' : 'success'); ?>">
                                    <?php echo $requestSuccess['urgency']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="donor-count-badge">
                        <i class="fas fa-users me-2"></i>
                        <?php echo $requestSuccess['donor_count']; ?> Available Donors Found
                    </div>
                    
                    <div class="alert alert-info">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-info-circle me-2"></i>
                            <div class="text-start">
                                <strong>What happens next?</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Our team will contact available donors in your area</li>
                                    <li>You'll receive email updates about your request status</li>
                                    <li>Willing donors will contact you directly</li>
                                    <li>Keep your phone accessible for urgent communications</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="../requestor/login.php" class="btn btn-danger btn-lg">
                            <i class="fas fa-search me-2"></i>Track This Request
                        </a>
                        <a href="blood-request.php" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Submit Another Request
                        </a>
                        <a href="../index.php" class="btn btn-outline-success">
                            <i class="fas fa-home me-2"></i>Back to Home
                        </a>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top">
                        <p class="text-muted small mb-2">
                            <i class="fas fa-phone me-1"></i>Emergency Helpline: 
                            <a href="tel:+919999999999" class="text-success">+91-9999999999</a>
                        </p>
                        <p class="text-muted small mb-0">
                            <i class="fas fa-envelope me-1"></i>Support Email: 
                            <a href="mailto:support@gasc.edu" class="text-success">support@gasc.edu</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-redirect after 30 seconds
        setTimeout(function() {
            window.location.href = '../index.php';
        }, 30000);
        
        // Show countdown
        let timeLeft = 30;
        const countdownElement = document.createElement('p');
        countdownElement.className = 'text-muted small mt-3';
        countdownElement.innerHTML = 'Redirecting to home in <span id="countdown">30</span> seconds...';
        document.querySelector('.success-card .p-4').appendChild(countdownElement);
        
        const countdown = setInterval(function() {
            timeLeft--;
            document.getElementById('countdown').textContent = timeLeft;
            
            if (timeLeft <= 0) {
                clearInterval(countdown);
            }
        }, 1000);
    </script>
</body>
</html>
