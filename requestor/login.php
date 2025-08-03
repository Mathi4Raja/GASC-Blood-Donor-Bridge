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
        
        .back-home-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .back-home-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <a href="../index.php" class="back-home-btn">
            <i class="fas fa-arrow-left"></i>
        </a>
        
        <div class="container">
            <div class="login-card">
                <div class="login-header">
                    <i class="fas fa-hand-holding-heart fa-3x text-danger mb-3"></i>
                    <h3 class="text-danger mb-1">Track Your Requests</h3>
                    <p class="text-muted mb-0">Access your blood request dashboard</p>
                </div>
                
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
                        <p class="text-muted mb-2">Don't have any requests yet?</p>
                        <a href="../request/blood-request.php" class="text-danger text-decoration-none">
                            <i class="fas fa-plus me-1"></i>Make Your First Blood Request
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
