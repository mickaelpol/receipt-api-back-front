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

// Include the main application logic
require __DIR__ . '/app.php';
