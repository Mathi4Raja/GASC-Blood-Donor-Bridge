<?php
/**
 * Simple autoloader for PHPMailer
 */

// Manual autoloader for PHPMailer if Composer is not available
spl_autoload_register(function ($className) {
    // Only handle PHPMailer classes
    if (strpos($className, 'PHPMailer\\PHPMailer\\') === 0) {
        $classFile = str_replace('PHPMailer\\PHPMailer\\', '', $className);
        $filePath = __DIR__ . '/phpmailer/phpmailer/src/' . $classFile . '.php';
        
        if (file_exists($filePath)) {
            require_once $filePath;
        }
    }
});
?>
