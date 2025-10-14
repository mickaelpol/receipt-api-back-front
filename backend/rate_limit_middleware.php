<?php

/**
 * Rate Limiting Middleware
 *
 * Apply rate limiting to API endpoints
 */

require_once __DIR__ . '/RateLimiter.php';

use App\RateLimiter;

/**
 * Rate limits per endpoint (requests per minute)
 */
const RATE_LIMITS = [
    '/api/scan' => 20,
    '/api/scan/batch' => 5,
    '/api/sheets/write' => 30,
    '/api/auth/me' => 100,
    'default' => 60
];

/**
 * Get rate limit identifier from request
 * Uses IP address + user email (if authenticated)
 */
function getRateLimitIdentifier(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // If user is authenticated, include email in identifier
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($authHeader && preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
        try {
            // Try to decode token to get email
            $token = $matches[1];
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode($parts[1]), true);
                if (isset($payload['email'])) {
                    return $ip . ':' . $payload['email'];
                }
            }
        } catch (Throwable $e) {
            // If decoding fails, just use IP
        }
    }

    return $ip;
}

/**
 * Apply rate limiting to a request
 *
 * @param string $identifier Rate limit identifier
 * @param string $endpoint Current endpoint path
 * @throws Exception if rate limit exceeded (sends 429 response and exits)
 */
function applyRateLimit(string $identifier, string $endpoint): void
{
    // Get rate limit for this endpoint
    $limit = RATE_LIMITS[$endpoint] ?? RATE_LIMITS['default'];

    // Create rate limiter
    $rateLimiter = new RateLimiter($limit, 60);

    // Check rate limit
    $result = $rateLimiter->check($identifier);

    // Add rate limit headers to response
    header("X-RateLimit-Limit: {$result['limit']}");
    header("X-RateLimit-Remaining: {$result['remaining']}");
    header("X-RateLimit-Reset: {$result['reset']}");

    // If rate limit exceeded, return 429
    if (!$result['allowed']) {
        $retryAfter = $result['reset'] - time();
        header("Retry-After: {$retryAfter}");
        http_response_code(429);

        // Log rate limit violation
        logMessage('warning', 'Rate limit exceeded', [
            'identifier' => $identifier,
            'endpoint' => $endpoint,
            'limit' => $limit,
            'reset' => $result['reset']
        ]);

        echo json_encode([
            'error' => 'Rate limit exceeded',
            'message' => "Too many requests. Please retry after {$retryAfter} seconds.",
            'limit' => $result['limit'],
            'reset' => $result['reset']
        ]);
        exit;
    }
}

/**
 * Cleanup old rate limit files (call periodically)
 */
function cleanupRateLimits(): void
{
    $rateLimiter = new RateLimiter(1); // Dummy limiter for cleanup
    $cleaned = $rateLimiter->cleanup();

    if ($cleaned > 0) {
        logMessage('info', 'Rate limit cleanup', ['files_cleaned' => $cleaned]);
    }
}
