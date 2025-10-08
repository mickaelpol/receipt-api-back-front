<?php

/**
 * Bootstrap file - handles initialization and side effects
 * This file contains only runtime logic, no function/class declarations
 */

declare(strict_types=1);

// Load Composer autoloader
require __DIR__ . '/vendor/autoload.php';

// Production-ready error handling
$DEBUG = (getenv('DEBUG') && getenv('DEBUG') !== '0');
if ($DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}

// Validate environment variables
validateEnvironment();

// Validate WHO_COLUMNS format
validateWhoColumns();

// Include the main application logic
require __DIR__ . '/app.php';

/**
 * Validate required environment variables
 * Fail-fast if any required variable is missing
 */
function validateEnvironment(): void {
    $required = [
        'GCP_PROJECT_ID',
        'GOOGLE_OAUTH_CLIENT_ID', 
        'SPREADSHEET_ID',
        'ALLOWED_EMAILS',
        'GCP_PROCESSOR_ID'
    ];
    
    $missing = [];
    foreach ($required as $var) {
        if (!getenv($var) || getenv($var) === '') {
            $missing[] = $var;
        }
    }
    
    if (!empty($missing)) {
        $missingStr = implode(', ', $missing);
        $error = "❌ FATAL: Missing required environment variables: {$missingStr}\n";
        $error .= "Please check your .env file and ensure all required variables are set.\n";
        $error .= "See infra/.env.example for the complete list.\n";
        
        // In production, log to error log; in development, output to stderr
        if (getenv('APP_ENV') === 'local') {
            error_log($error);
            fwrite(STDERR, $error);
        } else {
            error_log($error);
        }
        
        http_response_code(500);
        die($error);
    }
}

/**
 * Validate WHO_COLUMNS format
 * Fail-fast if WHO_COLUMNS is invalid
 */
function validateWhoColumns(): void {
    $whoColumns = getenv('WHO_COLUMNS');
    
    if (!$whoColumns || $whoColumns === '') {
        return; // WHO_COLUMNS is optional
    }
    
    // Validate JSON format
    $decoded = json_decode($whoColumns, true);
    if (!is_array($decoded)) {
        $error = "❌ FATAL: WHO_COLUMNS must be valid JSON. Got: " . substr($whoColumns, 0, 100) . "\n";
        $error .= "Expected format: {\"Name\":[\"A\",\"B\",\"C\"],\"Another\":[\"D\",\"E\",\"F\"]}\n";
        error_log($error);
        http_response_code(500);
        die($error);
    }
    
    // Validate structure
    foreach ($decoded as $name => $columns) {
        if (!is_string($name) || trim($name) === '') {
            $error = "❌ FATAL: WHO_COLUMNS names must be non-empty strings\n";
            error_log($error);
            http_response_code(500);
            die($error);
        }
        
        if (!is_array($columns) || count($columns) !== 3) {
            $error = "❌ FATAL: WHO_COLUMNS columns must be arrays with exactly 3 elements for '{$name}'\n";
            error_log($error);
            http_response_code(500);
            die($error);
        }
        
        foreach ($columns as $col) {
            if (!is_string($col) || strlen($col) !== 1 || !ctype_alpha($col)) {
                $error = "❌ FATAL: WHO_COLUMNS columns must be single letters for '{$name}'\n";
                error_log($error);
                http_response_code(500);
                die($error);
            }
        }
    }
}
