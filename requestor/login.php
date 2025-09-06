<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requestor Login - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .login-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #dc2626, #991b1b);
            display: flex;
            align-items: center;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 400px;
            margin: 0 auto;
        }
        
        .login-header {
            background: linear-gradient(135deg, #fee2e2, white);
            padding: 2rem;
            text-align: center;
        }
        
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
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="login-card position-relative">
                <a href="../index.php" class="back-home-btn-card position-absolute top-0 start-0 mt-3 ms-3 text-decoration-none" style="z-index:1050;">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="login-header">
                    <i class="fas fa-hand-holding-heart fa-3x text-danger mb-3"></i>
                    <h3 class="text-danger mb-1">Track Your Requests</h3>
                    <p class="text-muted mb-0">Access your blood request dashboard</p>
                </div>
                
                <!-- Session Timeout Alert -->
                <?php if (isset($_GET['timeout']) && $_GET['timeout'] == '1'): ?>
                    <div class="alert alert-warning alert-dismissible fade show m-3">
                        <i class="fas fa-clock me-2"></i>
                        <strong>Session Expired:</strong> 
                        <?php echo isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Your session has expired due to inactivity. Please log in again.'; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="p-4">
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($_GET['error']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($_GET['success']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="authenticate.php">
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope text-danger me-1"></i>Email Address
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   required placeholder="Enter the email used for blood requests">
                            <div class="form-text">
                                Use the same email address you provided when making blood requests
                            </div>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-sign-in-alt me-2"></i>Access Dashboard
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center">
                        <div class="alert alert-info d-flex align-items-center mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <div class="text-start">
                                <strong>Track Your Blood Requests</strong><br>
                                <small>Monitor status, updates, and responses from donors in real-time</small>
                            </div>
                        </div>
                        <p class="text-muted mb-2">Don't have any requests yet?</p>
                        <a href="../request/blood-request.php" class="btn btn-outline-danger">
                            <i class="fas fa-plus me-1"></i>Make Your First Blood Request
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/loading-manager.js"></script>
</body>
</html>
