<?php
require_once '../config/database.php';

// Check if user is logged in as admin or moderator
requireRole(['admin', 'moderator']);

$db = new Database();

// Date range for reports
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today
$reportType = $_GET['report_type'] ?? 'overview';

// Validate dates
$startDateTime = DateTime::createFromFormat('Y-m-d', $startDate);
$endDateTime = DateTime::createFromFormat('Y-m-d', $endDate);

if (!$startDateTime || !$endDateTime) {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-d');
}

// Generate reports based on type
$reportData = [];

if ($reportType === 'overview') {
    // Overall statistics
    $reportData['total_donors'] = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'donor'")->fetch_assoc()['count'];
    $reportData['verified_donors'] = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'donor' AND is_verified = TRUE")->fetch_assoc()['count'];
    $reportData['active_donors'] = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'donor' AND is_active = TRUE AND is_verified = TRUE")->fetch_assoc()['count'];
    
    // Requests in date range
    $requestStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_requests,
            SUM(CASE WHEN status = 'Fulfilled' THEN 1 ELSE 0 END) as fulfilled_requests,
            SUM(CASE WHEN status = 'Expired' THEN 1 ELSE 0 END) as expired_requests,
            SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_requests
        FROM blood_requests 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $requestStmt->bind_param('ss', $startDate, $endDate);
    $requestStmt->execute();
    $requestStats = $requestStmt->get_result()->fetch_assoc();
    $reportData = array_merge($reportData, $requestStats);
    
    // Blood group distribution
    $reportData['blood_group_stats'] = $db->query("SELECT * FROM blood_group_stats ORDER BY blood_group")->fetch_all(MYSQLI_ASSOC);
    
    // Daily requests trend
    $trendStmt = $db->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as requests_count,
            SUM(CASE WHEN status = 'Fulfilled' THEN 1 ELSE 0 END) as fulfilled_count
        FROM blood_requests 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date DESC
        LIMIT 30
    ");
    $trendStmt->bind_param('ss', $startDate, $endDate);
    $trendStmt->execute();
    $reportData['daily_trend'] = $trendStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} elseif ($reportType === 'donors') {
    // Donor registration trend
    $donorTrendStmt = $db->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as new_donors
        FROM users 
        WHERE user_type = 'donor' AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $donorTrendStmt->bind_param('ss', $startDate, $endDate);
    $donorTrendStmt->execute();
    $reportData['donor_trend'] = $donorTrendStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // City-wise distribution
    $reportData['city_distribution'] = $db->query("
        SELECT 
            city,
            COUNT(*) as total_donors,
            SUM(CASE WHEN is_available = TRUE THEN 1 ELSE 0 END) as available_donors,
            SUM(CASE WHEN is_verified = TRUE THEN 1 ELSE 0 END) as verified_donors
        FROM users 
        WHERE user_type = 'donor' AND city IS NOT NULL
        GROUP BY city
        ORDER BY total_donors DESC
    ")->fetch_all(MYSQLI_ASSOC);
    
    // Gender distribution
    $reportData['gender_distribution'] = $db->query("
        SELECT 
            gender,
            COUNT(*) as count
        FROM users 
        WHERE user_type = 'donor' AND gender IS NOT NULL
        GROUP BY gender
    ")->fetch_all(MYSQLI_ASSOC);
    
    // Recent donors
    $recentDonorsStmt = $db->prepare("
        SELECT name, email, blood_group, city, created_at
        FROM users 
        WHERE user_type = 'donor' AND DATE(created_at) BETWEEN ? AND ?
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $recentDonorsStmt->bind_param('ss', $startDate, $endDate);
    $recentDonorsStmt->execute();
    $reportData['recent_donors'] = $recentDonorsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} elseif ($reportType === 'requests') {
    // Request statistics by urgency
    $urgencyStmt = $db->prepare("
        SELECT 
            urgency,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Fulfilled' THEN 1 ELSE 0 END) as fulfilled,
            AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(updated_at, NOW()))) as avg_resolution_hours
        FROM blood_requests 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY urgency
    ");
    $urgencyStmt->bind_param('ss', $startDate, $endDate);
    $urgencyStmt->execute();
    $reportData['urgency_stats'] = $urgencyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Blood group demand
    $demandStmt = $db->prepare("
        SELECT 
            blood_group,
            COUNT(*) as total_requests,
            SUM(units_needed) as total_units_requested,
            SUM(CASE WHEN status = 'Fulfilled' THEN units_needed ELSE 0 END) as units_fulfilled
        FROM blood_requests 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY blood_group
        ORDER BY total_requests DESC
    ");
    $demandStmt->bind_param('ss', $startDate, $endDate);
    $demandStmt->execute();
    $reportData['demand_stats'] = $demandStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Recent requests
    $recentRequestsStmt = $db->prepare("
        SELECT requester_name, blood_group, city, urgency, status, created_at, units_needed
        FROM blood_requests 
        WHERE DATE(created_at) BETWEEN ? AND ?
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $recentRequestsStmt->bind_param('ss', $startDate, $endDate);
    $recentRequestsStmt->execute();
    $reportData['recent_requests'] = $recentRequestsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} elseif ($reportType === 'activity') {
    // Most active users
    $activeUsersStmt = $db->prepare("
        SELECT 
            u.name,
            u.user_type,
            COUNT(al.id) as activity_count
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        WHERE DATE(al.created_at) BETWEEN ? AND ?
        GROUP BY al.user_id
        ORDER BY activity_count DESC
        LIMIT 20
    ");
    $activeUsersStmt->bind_param('ss', $startDate, $endDate);
    $activeUsersStmt->execute();
    $reportData['active_users'] = $activeUsersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Activity by action type
    $actionStatsStmt = $db->prepare("
        SELECT 
            action,
            COUNT(*) as count
        FROM activity_logs
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY action
        ORDER BY count DESC
    ");
    $actionStatsStmt->bind_param('ss', $startDate, $endDate);
    $actionStatsStmt->execute();
    $reportData['action_stats'] = $actionStatsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Daily activity trend
    $activityTrendStmt = $db->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as activity_count
        FROM activity_logs
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date DESC
        LIMIT 30
    ");
    $activityTrendStmt->bind_param('ss', $startDate, $endDate);
    $activityTrendStmt->execute();
    $reportData['activity_trend'] = $activityTrendStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="blood_bridge_report_' . $reportType . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if ($reportType === 'overview') {
        fputcsv($output, ['GASC Blood Bridge - Overview Report']);
        fputcsv($output, ['Generated on', date('Y-m-d H:i:s')]);
        fputcsv($output, ['Date Range', $startDate . ' to ' . $endDate]);
        fputcsv($output, []);
        
        fputcsv($output, ['Metric', 'Value']);
        fputcsv($output, ['Total Donors', $reportData['total_donors']]);
        fputcsv($output, ['Verified Donors', $reportData['verified_donors']]);
        fputcsv($output, ['Active Donors', $reportData['active_donors']]);
        fputcsv($output, ['Total Requests', $reportData['total_requests']]);
        fputcsv($output, ['Active Requests', $reportData['active_requests']]);
        fputcsv($output, ['Fulfilled Requests', $reportData['fulfilled_requests']]);
        
    } elseif ($reportType === 'donors') {
        fputcsv($output, ['GASC Blood Bridge - Donor Report']);
        fputcsv($output, ['Generated on', date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        
        fputcsv($output, ['Recent Donors']);
        fputcsv($output, ['Name', 'Email', 'Blood Group', 'City', 'Registration Date']);
        foreach ($reportData['recent_donors'] as $donor) {
            fputcsv($output, [
                $donor['name'],
                $donor['email'],
                $donor['blood_group'],
                $donor['city'],
                $donor['created_at']
            ]);
        }
    }
    
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-card {
            border: 1px solid #e9ecef;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stats-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: none;
            border-radius: 15px;
            text-align: center;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        
        .report-nav {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .report-nav .nav-link {
            border-radius: 8px;
            margin: 0 5px;
        }
        
        .report-nav .nav-link.active {
            background: #dc2626;
            color: white;
        }
        
        @media (max-width: 768px) {
            .report-card {
                padding: 15px;
            }
            
            .chart-container {
                height: 250px;
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
                        <a class="nav-link" href="donors.php">
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
                        <a class="nav-link active" href="reports.php">
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
                        <i class="fas fa-chart-bar text-danger me-2"></i>Reports & Analytics
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print me-1"></i>Print
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" onclick="exportReport()">
                                <i class="fas fa-download me-1"></i>Export CSV
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Report Navigation -->
                <div class="report-nav">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $reportType === 'overview' ? 'active' : ''; ?>" 
                               href="?report_type=overview&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>">
                                <i class="fas fa-chart-pie me-1"></i>Overview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $reportType === 'donors' ? 'active' : ''; ?>" 
                               href="?report_type=donors&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>">
                                <i class="fas fa-users me-1"></i>Donors
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $reportType === 'requests' ? 'active' : ''; ?>" 
                               href="?report_type=requests&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>">
                                <i class="fas fa-hand-holding-heart me-1"></i>Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $reportType === 'activity' ? 'active' : ''; ?>" 
                               href="?report_type=activity&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>">
                                <i class="fas fa-activity me-1"></i>Activity
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- Date Range Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <input type="hidden" name="report_type" value="<?php echo $reportType; ?>">
                            <div class="col-md-4">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-1"></i>Generate Report
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Report Content -->
                <?php if ($reportType === 'overview'): ?>
                    <!-- Overview Report -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card">
                                <div class="h3 text-primary"><?php echo $reportData['total_donors']; ?></div>
                                <div class="text-muted">Total Donors</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card">
                                <div class="h3 text-success"><?php echo $reportData['verified_donors']; ?></div>
                                <div class="text-muted">Verified Donors</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card">
                                <div class="h3 text-warning"><?php echo $reportData['total_requests']; ?></div>
                                <div class="text-muted">Total Requests</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card">
                                <div class="h3 text-danger"><?php echo $reportData['fulfilled_requests']; ?></div>
                                <div class="text-muted">Fulfilled Requests</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="report-card">
                                <h5>Blood Group Distribution</h5>
                                <div class="chart-container">
                                    <canvas id="bloodGroupChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="report-card">
                                <h5>Request Status Distribution</h5>
                                <div class="chart-container">
                                    <canvas id="requestStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($reportData['daily_trend'])): ?>
                        <div class="report-card">
                            <h5>Daily Requests Trend</h5>
                            <div class="chart-container">
                                <canvas id="dailyTrendChart"></canvas>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php elseif ($reportType === 'donors'): ?>
                    <!-- Donors Report -->
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="report-card">
                                <h5>City-wise Distribution</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>City</th>
                                                <th>Total</th>
                                                <th>Available</th>
                                                <th>Verified</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData['city_distribution'] as $city): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($city['city']); ?></td>
                                                    <td><?php echo $city['total_donors']; ?></td>
                                                    <td><?php echo $city['available_donors']; ?></td>
                                                    <td><?php echo $city['verified_donors']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="report-card">
                                <h5>Gender Distribution</h5>
                                <div class="chart-container">
                                    <canvas id="genderChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($reportData['recent_donors'])): ?>
                        <div class="report-card">
                            <h5>Recent Donor Registrations</h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Blood Group</th>
                                            <th>City</th>
                                            <th>Registration Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportData['recent_donors'] as $donor): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($donor['name']); ?></td>
                                                <td><?php echo htmlspecialchars($donor['email']); ?></td>
                                                <td><span class="badge bg-danger"><?php echo $donor['blood_group']; ?></span></td>
                                                <td><?php echo htmlspecialchars($donor['city']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($donor['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php elseif ($reportType === 'requests'): ?>
                    <!-- Requests Report -->
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="report-card">
                                <h5>Urgency Statistics</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Urgency</th>
                                                <th>Total</th>
                                                <th>Fulfilled</th>
                                                <th>Avg. Resolution (hrs)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData['urgency_stats'] as $urgency): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge <?php 
                                                            echo $urgency['urgency'] === 'Critical' ? 'bg-danger' : 
                                                                ($urgency['urgency'] === 'Urgent' ? 'bg-warning' : 'bg-info'); 
                                                        ?>">
                                                            <?php echo $urgency['urgency']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $urgency['total']; ?></td>
                                                    <td><?php echo $urgency['fulfilled']; ?></td>
                                                    <td><?php echo round($urgency['avg_resolution_hours'], 1); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="report-card">
                                <h5>Blood Group Demand</h5>
                                <div class="chart-container">
                                    <canvas id="demandChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($reportType === 'activity'): ?>
                    <!-- Activity Report -->
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="report-card">
                                <h5>Most Active Users</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Type</th>
                                                <th>Activities</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData['active_users'] as $user): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                                    <td><span class="badge bg-secondary"><?php echo ucfirst($user['user_type']); ?></span></td>
                                                    <td><?php echo $user['activity_count']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="report-card">
                                <h5>Activity by Action Type</h5>
                                <div class="chart-container">
                                    <canvas id="activityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportReport() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'reports.php?' + params.toString();
        }
        
        // Chart.js configurations
        <?php if ($reportType === 'overview' && !empty($reportData['blood_group_stats'])): ?>
            // Blood Group Chart
            const bloodGroupCtx = document.getElementById('bloodGroupChart').getContext('2d');
            new Chart(bloodGroupCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($reportData['blood_group_stats'], 'blood_group')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($reportData['blood_group_stats'], 'total_donors')); ?>,
                        backgroundColor: [
                            '#dc2626', '#ef4444', '#f59e0b', '#10b981',
                            '#3b82f6', '#8b5cf6', '#ec4899', '#6b7280'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Request Status Chart
            const requestStatusCtx = document.getElementById('requestStatusChart').getContext('2d');
            new Chart(requestStatusCtx, {
                type: 'pie',
                data: {
                    labels: ['Active', 'Fulfilled', 'Expired', 'Cancelled'],
                    datasets: [{
                        data: [
                            <?php echo $reportData['active_requests']; ?>,
                            <?php echo $reportData['fulfilled_requests']; ?>,
                            <?php echo $reportData['expired_requests']; ?>,
                            <?php echo $reportData['cancelled_requests']; ?>
                        ],
                        backgroundColor: ['#f59e0b', '#10b981', '#6b7280', '#ef4444']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        <?php endif; ?>
        
        <?php if ($reportType === 'donors' && !empty($reportData['gender_distribution'])): ?>
            // Gender Chart
            const genderCtx = document.getElementById('genderChart').getContext('2d');
            new Chart(genderCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($reportData['gender_distribution'], 'gender')); ?>,
                    datasets: [{
                        label: 'Number of Donors',
                        data: <?php echo json_encode(array_column($reportData['gender_distribution'], 'count')); ?>,
                        backgroundColor: ['#3b82f6', '#ec4899', '#10b981']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        <?php endif; ?>
        
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
    </script>
</body>
</html>
