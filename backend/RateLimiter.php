<?php

namespace App;

/**
 * File-based Rate Limiter
 *
 * Simple rate limiting implementation using file system for state management.
 * Compatible with Cloud Run multi-instance deployments.
 */
class RateLimiter
{
    private string $storageDir;
    private int $window; // Time window in seconds
    private int $maxRequests; // Max requests per window

    /**
     * @param int $maxRequests Maximum requests allowed per window
     * @param int $window Time window in seconds (default: 60)
     */
    public function __construct(int $maxRequests, int $window = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->window = $window;
        $this->storageDir = sys_get_temp_dir() . '/rate_limits';

        // Create storage directory if it doesn't exist
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0777, true);
        }
    }

    /**
     * Check if request is allowed and increment counter
     *
     * @param string $identifier Unique identifier (IP, user ID, etc.)
     * @return array ['allowed' => bool, 'limit' => int, 'remaining' => int, 'reset' => int]
     */
    public function check(string $identifier): array
    {
        $key = md5($identifier);
        $filePath = $this->storageDir . '/' . $key . '.json';
        $now = time();

        // Lock file for atomic read-modify-write
        $lockFile = $filePath . '.lock';
        $lockFp = fopen($lockFile, 'c');
        if (!$lockFp || !flock($lockFp, LOCK_EX)) {
            // If we can't get lock, allow request but log error
            error_log("Rate limiter: Could not acquire lock for {$identifier}");
            return [
                'allowed' => true,
                'limit' => $this->maxRequests,
                'remaining' => $this->maxRequests - 1,
                'reset' => $now + $this->window
            ];
        }

        try {
            // Read current state
            $state = $this->readState($filePath, $now);

            // Check if allowed
            $allowed = $state['count'] < $this->maxRequests;

            if ($allowed) {
                // Increment counter
                $state['count']++;
                $this->writeState($filePath, $state);
            }

            $remaining = max(0, $this->maxRequests - $state['count']);

            return [
                'allowed' => $allowed,
                'limit' => $this->maxRequests,
                'remaining' => $remaining,
                'reset' => $state['reset']
            ];
        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }

    /**
     * Read state from file or create new window
     */
    private function readState(string $filePath, int $now): array
    {
        if (file_exists($filePath)) {
            $data = file_get_contents($filePath);
            if ($data !== false) {
                $state = json_decode($data, true);
                if ($state && isset($state['reset']) && $state['reset'] > $now) {
                    // Window is still valid
                    return $state;
                }
            }
        }

        // Create new window
        return [
            'count' => 0,
            'reset' => $now + $this->window
        ];
    }

    /**
     * Write state to file
     */
    private function writeState(string $filePath, array $state): void
    {
        file_put_contents($filePath, json_encode($state), LOCK_EX);
    }

    /**
     * Clean up old rate limit files
     * Call this periodically (e.g., from a cron job)
     */
    public function cleanup(): int
    {
        $cleaned = 0;
        $now = time();
        $files = glob($this->storageDir . '/*.json');

        foreach ($files as $file) {
            if (file_exists($file) && ($now - filemtime($file)) > $this->window * 2) {
                unlink($file);
                $cleaned++;
            }
        }

        return $cleaned;
    }
}
