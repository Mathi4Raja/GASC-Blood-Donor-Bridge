<?php
/**
 * Environment Configuration Loader
 * Loads environment variables from .env file for sensitive configuration
 */

class EnvLoader {
    private static $loaded = false;
    private static $env = [];

    /**
     * Load environment variables from .env file
     */
    public static function load($envPath = null) {
        if (self::$loaded) {
            return;
        }

        if ($envPath === null) {
            $envPath = __DIR__ . '/../.env';
        }

        if (!file_exists($envPath)) {
            // Try .env.example as fallback for development
            $examplePath = __DIR__ . '/../.env.example';
            if (file_exists($examplePath)) {
                error_log("Warning: .env file not found, using .env.example. Create .env file for production!");
                $envPath = $examplePath;
            } else {
                error_log("Warning: No .env or .env.example file found. Using default values.");
                self::$loaded = true;
                return;
            }
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (preg_match('/^"(.*)"$/', $value, $matches)) {
                    $value = $matches[1];
                } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                    $value = $matches[1];
                }
                
                self::$env[$key] = $value;
                
                // Also set as environment variable if not already set
                if (!getenv($key)) {
                    putenv("$key=$value");
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Get environment variable with optional default
     */
    public static function get($key, $default = null) {
        self::load();
        
        // Check environment first, then our loaded values, then default
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        return self::$env[$key] ?? $default;
    }

    /**
     * Check if environment variable exists
     */
    public static function has($key) {
        self::load();
        return isset(self::$env[$key]) || getenv($key) !== false;
    }
}

// Auto-load environment variables when this file is included
EnvLoader::load();
?>
