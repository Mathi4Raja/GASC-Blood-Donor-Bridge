<?php
// Database Setup Script for GASC Blood Donor Bridge
// Run this script once to create the database and tables

require_once '../config/database.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Setup - GASC Blood Bridge</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { background: #f8f9fa; font-family: monospace; }
        .setup-container { max-width: 800px; margin: 50px auto; }
        .log-output { background: #000; color: #00ff00; padding: 20px; border-radius: 8px; height: 400px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class='container setup-container'>
        <div class='card'>
            <div class='card-header bg-danger text-white'>
                <h3 class='mb-0'><i class='fas fa-database'></i> GASC Blood Bridge - Database Setup</h3>
            </div>
            <div class='card-body'>
                <div class='log-output' id='output'>";

function logMessage($message, $type = 'info') {
    $timestamp = date('Y-m-d H:i:s');
    $color = $type === 'error' ? '#ff0000' : ($type === 'success' ? '#00ff00' : '#ffffff');
    echo "<div style='color: $color;'>[$timestamp] $message</div>";
    flush();
    ob_flush();
}

try {
    logMessage("Starting database setup for GASC Blood Donor Bridge...");
    logMessage("⚠️  Please ensure the database 'gasc_blood_bridge' exists before running this setup.");
    
    // Connect to the specific database (assumes it already exists)
    $db = new Database();
    $connection = $db->getConnection();
    
    logMessage("✓ Connected to database successfully");
    
    // Set charset
    $connection->set_charset("utf8mb4");
    logMessage("✓ Character set configured to utf8mb4");
    
    // Read SQL file
    $sqlFile = __DIR__ . '/schema-phpmyadmin.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL schema file not found at: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new Exception("Failed to read SQL schema file");
    }
    
    logMessage("✓ SQL schema file loaded successfully");
    
    // Remove problematic statements for web execution
    $sql = preg_replace('/CREATE DATABASE.*?;/i', '', $sql);
    $sql = preg_replace('/USE .*?;/i', '', $sql);
    $sql = preg_replace('/DELIMITER.*?$/im', '', $sql);
    $sql = str_replace('//', '', $sql);
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0 || stripos($statement, 'DELIMITER') !== false) {
            continue;
        }
        
        try {
            if ($connection->query($statement) === TRUE) {
                $successCount++;
                // Log specific operations
                if (stripos($statement, 'CREATE TABLE') !== false) {
                    preg_match('/CREATE TABLE\s+(\w+)/i', $statement, $matches);
                    $tableName = $matches[1] ?? 'unknown';
                    logMessage("✓ Table '$tableName' created successfully", 'success');
                } elseif (stripos($statement, 'INSERT INTO') !== false) {
                    preg_match('/INSERT INTO\s+(\w+)/i', $statement, $matches);
                    $tableName = $matches[1] ?? 'unknown';
                    logMessage("✓ Sample data inserted into '$tableName'", 'success');
                } elseif (stripos($statement, 'CREATE VIEW') !== false) {
                    preg_match('/CREATE VIEW\s+(\w+)/i', $statement, $matches);
                    $viewName = $matches[1] ?? 'unknown';
                    logMessage("✓ View '$viewName' created successfully", 'success');
                } elseif (stripos($statement, 'CREATE INDEX') !== false) {
                    preg_match('/CREATE INDEX\s+(\w+)/i', $statement, $matches);
                    $indexName = $matches[1] ?? 'unknown';
                    logMessage("✓ Index '$indexName' created successfully", 'success');
                }
            } else {
                throw new Exception($connection->error);
            }
        } catch (Exception $e) {
            $errorCount++;
            logMessage("Error executing statement: " . $e->getMessage(), 'error');
            logMessage("Statement: " . substr($statement, 0, 100) . "...", 'error');
        }
    }
    
    $connection->close();
    
    logMessage("");
    logMessage("=== DATABASE SETUP COMPLETED ===", 'success');
    logMessage("Successful operations: $successCount", 'success');
    logMessage("Failed operations: $errorCount", $errorCount > 0 ? 'error' : 'success');
    
    if ($errorCount === 0) {
        logMessage("");
        logMessage("🎉 All done! Your GASC Blood Bridge database is ready to use.", 'success');
        logMessage("", 'success');
        logMessage("Default Admin Credentials:", 'success');
        logMessage("Email: admin@gasc.edu", 'success');
        logMessage("Password: admin123", 'success');
        logMessage("", 'success');
        logMessage("Default Moderator Credentials:", 'success');
        logMessage("Email: moderator@gasc.edu", 'success');
        logMessage("Password: moderator123", 'success');
        logMessage("", 'success');
        logMessage("⚠️  Please change these default passwords after first login!", 'error');
    } else {
        logMessage("⚠️  Setup completed with some errors. Please check the logs above.", 'error');
    }
    
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage(), 'error');
    logMessage("Setup failed. Please check your database configuration.", 'error');
}

echo "</div>
            <div class='mt-3'>
                <a href='../index.php' class='btn btn-danger'>Go to Home Page</a>
                <a href='../admin/login.php' class='btn btn-outline-danger'>Admin Login</a>
                <button onclick='location.reload()' class='btn btn-secondary'>Retry Setup</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Auto-scroll to bottom
    const output = document.getElementById('output');
    output.scrollTop = output.scrollHeight;
    
    // Scroll to bottom on new content
    const observer = new MutationObserver(() => {
        output.scrollTop = output.scrollHeight;
    });
    observer.observe(output, { childList: true });
</script>
</body>
</html>";
?>
