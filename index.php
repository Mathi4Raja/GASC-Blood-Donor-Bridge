<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="GASC Blood Donor Bridge - Connecting donors with those in need. Save lives through blood donation.">
    <meta name="keywords" content="blood donation, donors, blood bank, save lives, GASC college">
    <title>GASC Blood Donor Bridge - Save Lives Through Donation</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#home">
                <img src="assets/images/gobi-arts-science-logo.png" alt="GASC Logo" class="me-2">
                <span class="fw-bold text-danger">GASC Blood Bridge</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#benefits">Benefits</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#rules">Rules</a>
                    </li>
                    <li class="nav-item">
                        <button class="btn btn-outline-danger ms-2" data-bs-toggle="modal" data-bs-target="#loginModal">
                            <i class="fas fa-sign-in-alt me-1"></i>Admin Login
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <h1 class="display-4 fw-bold text-white mb-4">
                            Save Lives Through <span class="text-warning">Blood Donation</span>
                        </h1>
                        <p class="lead text-white-50 mb-4">
                            Join GASC Blood Donor Bridge - where compassion meets technology. 
                            Connect with those in need and become a hero in someone's story.
                        </p>
                        <div class="d-grid d-md-flex gap-3">
                            <a href="donor/register.php" class="btn btn-danger btn-lg px-4">
                                <i class="fas fa-heart me-2"></i>Become A Donor
                            </a>
                            <a href="request/blood-request.php" class="btn btn-outline-light btn-lg px-4">
                                <i class="fas fa-plus-circle me-2"></i>Request For Blood
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image text-center">
                        <img src="assets/images/hero-blood-donation.png" alt="Blood Donation Medical Equipment" class="hero-logo">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="section-title">About GASC Blood Donor Bridge</h2>
                    <p class="section-subtitle">Connecting Hearts, Saving Lives</p>
                </div>
            </div>
            <div class="row mt-5">
                <div class="col-lg-6">
                    <div class="about-content">
                        <h3 class="text-danger mb-3">Our History</h3>
                        <p>
                            Founded in 2020, GASC Blood Donor Bridge emerged from a simple yet powerful vision: 
                            to create a seamless connection between blood donors and those in desperate need. 
                            What started as a college initiative has grown into a comprehensive platform serving 
                            our entire community.
                        </p>
                        <p>
                            Our journey began when a group of GASC students witnessed the urgent need for blood 
                            during a medical emergency on campus. Recognizing the gap between willing donors and 
                            those requiring immediate assistance, we developed this digital bridge to save precious time 
                            and lives.
                        </p>
                        <div class="stats-row mt-4">
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="stat-item">
                                        <h3 class="text-danger fw-bold">500+</h3>
                                        <p class="small">Registered Donors</p>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stat-item">
                                        <h3 class="text-danger fw-bold">200+</h3>
                                        <p class="small">Lives Saved</p>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stat-item">
                                        <h3 class="text-danger fw-bold">50+</h3>
                                        <p class="small">Cities Covered</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="about-image">
                        <img src="assets/images/about-us.png" alt="About Us" class="img-fluid rounded shadow">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section id="benefits" class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="section-title">Benefits of Blood Donation</h2>
                    <p class="section-subtitle">Giving blood is giving life - and it benefits you too</p>
                </div>
            </div>
            <div class="row mt-5">
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="benefit-card">
                        <div class="benefit-icon">
                            <i class="fas fa-heart text-danger"></i>
                        </div>
                        <h4>Health Benefits</h4>
                        <p>Regular blood donation helps maintain healthy iron levels and may reduce the risk of heart disease and cancer.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="benefit-card">
                        <div class="benefit-icon">
                            <i class="fas fa-user-md text-danger"></i>
                        </div>
                        <h4>Free Health Check</h4>
                        <p>Every donation includes a mini health screening including blood pressure, pulse, temperature, and hemoglobin check.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="benefit-card">
                        <div class="benefit-icon">
                            <i class="fas fa-hands-helping text-danger"></i>
                        </div>
                        <h4>Save Lives</h4>
                        <p>One blood donation can save up to three lives. Your single act of kindness can make an enormous difference.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="benefit-card">
                        <div class="benefit-icon">
                            <i class="fas fa-smile text-danger"></i>
                        </div>
                        <h4>Feel Good Factor</h4>
                        <p>Experience the psychological benefits of helping others and being part of something bigger than yourself.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="benefit-card">
                        <div class="benefit-icon">
                            <i class="fas fa-weight text-danger"></i>
                        </div>
                        <h4>Calorie Burn</h4>
                        <p>Donating blood burns approximately 650 calories, helping with weight management while saving lives.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="benefit-card">
                        <div class="benefit-icon">
                            <i class="fas fa-certificate text-danger"></i>
                        </div>
                        <h4>Recognition</h4>
                        <p>Receive certificates and badges for your donations, building a record of your life-saving contributions.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Rules Section -->
    <section id="rules" class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="section-title">Donation Rules & Guidelines</h2>
                    <p class="section-subtitle">Important guidelines to ensure safe donation</p>
                </div>
            </div>
            <div class="row mt-5">
                <div class="col-lg-8 mx-auto">
                    <div class="rules-container">
                        <div class="rule-item">
                            <div class="rule-icon">
                                <i class="fas fa-birthday-cake text-danger"></i>
                            </div>
                            <div class="rule-content">
                                <h5>Age Requirement</h5>
                                <p>Must be between 18-65 years old. First-time donors over 60 need medical clearance.</p>
                            </div>
                        </div>
                        <div class="rule-item">
                            <div class="rule-icon">
                                <i class="fas fa-weight-hanging text-danger"></i>
                            </div>
                            <div class="rule-content">
                                <h5>Weight Requirement</h5>
                                <p>Minimum weight of 50kg (110 lbs) required for safe donation.</p>
                            </div>
                        </div>
                        <div class="rule-item">
                            <div class="rule-icon">
                                <i class="fas fa-clock text-danger"></i>
                            </div>
                            <div class="rule-content">
                                <h5>Donation Frequency</h5>
                                <p>Males: Every 3 months (4 times per year)<br>Females: Every 4 months (3 times per year)</p>
                            </div>
                        </div>
                        <div class="rule-item">
                            <div class="rule-icon">
                                <i class="fas fa-shield-alt text-danger"></i>
                            </div>
                            <div class="rule-content">
                                <h5>Health Requirements</h5>
                                <p>Must be in good health, not taking antibiotics, and free from cold/fever for at least 7 days.</p>
                            </div>
                        </div>
                        <div class="rule-item">
                            <div class="rule-icon">
                                <i class="fas fa-utensils text-danger"></i>
                            </div>
                            <div class="rule-content">
                                <h5>Pre-Donation Guidelines</h5>
                                <p>Eat a healthy meal 2-3 hours before donation. Avoid fatty foods. Stay well hydrated.</p>
                            </div>
                        </div>
                        <div class="rule-item">
                            <div class="rule-icon">
                                <i class="fas fa-ban text-danger"></i>
                            </div>
                            <div class="rule-content">
                                <h5>Exclusions</h5>
                                <p>Cannot donate if pregnant, breastfeeding, have certain medical conditions, or recent tattoos/piercings.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-light py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5 class="fw-bold text-danger mb-3">GASC Blood Donor Bridge</h5>
                    <p class="text-light mb-3">A student-led initiative connecting hearts and saving lives through technology and compassion.</p>
                    <div class="social-links">
                        <a href="#" class="text-light me-3" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-light me-3" title="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-light me-3" title="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-light" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5 class="fw-bold mb-3 text-light">About GASC</h5>
                    <p class="text-light mb-2">
                        <i class="fas fa-university text-danger me-2"></i>
                        Gobi Arts & Science College
                    </p>
                    <p class="text-light mb-2">
                        <i class="fas fa-map-marker-alt text-danger me-2"></i>
                        Gobichettipalayam, Tamil Nadu
                    </p>
                    <p class="text-light mb-2">
                        <i class="fas fa-envelope text-danger me-2"></i>
                        info@gasccollege.edu.in
                    </p>
                    <p class="text-light">
                        <i class="fas fa-phone text-danger me-2"></i>
                        +91 4285 222 xxx
                    </p>
                </div>
                <div class="col-lg-4 col-md-12">
                    <h5 class="fw-bold mb-3 text-light">Developer Credits</h5>
                    <div class="dev-credits">
                        <p class="text-light mb-2">
                            <i class="fas fa-code text-danger me-2"></i>
                            Developed by GASC Students
                        </p>
                        <p class="text-light mb-2">
                            <i class="fas fa-graduation-cap text-danger me-2"></i>
                            Computer Science Department
                        </p>
                        <p class="text-light mb-2">
                            <i class="fas fa-calendar text-danger me-2"></i>
                            Academic Year 2024-25
                        </p>
                        <p class="text-light small">
                            Built with ❤️ using PHP, MySQL & Bootstrap
                        </p>
                    </div>
                </div>
            </div>
            <hr class="border-secondary my-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-light mb-0">© 2025 GASC Blood Donor Bridge. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-light mb-0 small">
                        Made with <i class="fas fa-heart text-danger"></i> for humanity
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger fw-bold">
                        <i class="fas fa-sign-in-alt me-2"></i>Admin/Moderator Login
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="loginForm" action="admin/login.php" method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="user_type" class="form-label">Login As</label>
                            <select class="form-select" id="user_type" name="user_type" required>
                                <option value="">Select Role</option>
                                <option value="admin">Admin</option>
                                <option value="moderator">Moderator</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
</body>
</html>
