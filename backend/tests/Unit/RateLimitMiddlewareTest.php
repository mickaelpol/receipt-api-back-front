<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for rate limiting middleware functions
 */
class RateLimitMiddlewareTest extends TestCase
{
    /**
     * Load rate_limit_middleware.php before tests
     */
    public static function setUpBeforeClass(): void
    {
        // Load the middleware file
        if (!function_exists('getRateLimitIdentifier')) {
            require_once __DIR__ . '/../../rate_limit_middleware.php';
        }
    }

    /**
     * Test getRateLimitIdentifier with IP only
     */
    public function testGetRateLimitIdentifierWithIpOnly(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $identifier = getRateLimitIdentifier();

        $this->assertEquals('192.168.1.100', $identifier);
    }

    /**
     * Test getRateLimitIdentifier without IP
     */
    public function testGetRateLimitIdentifierWithoutIp(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $identifier = getRateLimitIdentifier();

        $this->assertEquals('unknown', $identifier);
    }

    /**
     * Test getRateLimitIdentifier with bearer token
     */
    public function testGetRateLimitIdentifierWithBearerToken(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        // Create a simple JWT-like token (3 parts separated by dots)
        // Payload: {"email":"test@example.com"}
        $payload = base64_encode(json_encode(['email' => 'test@example.com']));
        $token = "header.$payload.signature";
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer $token";

        $identifier = getRateLimitIdentifier();

        $this->assertEquals('192.168.1.100:test@example.com', $identifier);
    }

    /**
     * Test getRateLimitIdentifier with invalid token
     */
    public function testGetRateLimitIdentifierWithInvalidToken(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid-token';

        $identifier = getRateLimitIdentifier();

        // Should fallback to IP only
        $this->assertEquals('192.168.1.100', $identifier);
    }

    /**
     * Test getRateLimitIdentifier with token without email
     */
    public function testGetRateLimitIdentifierWithTokenWithoutEmail(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        // Token without email field
        $payload = base64_encode(json_encode(['sub' => '12345']));
        $token = "header.$payload.signature";
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer $token";

        $identifier = getRateLimitIdentifier();

        // Should use IP only
        $this->assertEquals('192.168.1.100', $identifier);
    }

    /**
     * Test getRateLimitIdentifier with malformed authorization header
     */
    public function testGetRateLimitIdentifierWithMalformedAuth(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $_SERVER['HTTP_AUTHORIZATION'] = 'InvalidFormat';

        $identifier = getRateLimitIdentifier();

        $this->assertEquals('192.168.1.100', $identifier);
    }

    /**
     * Test getRateLimitIdentifier with token having only 2 parts
     */
    public function testGetRateLimitIdentifierWithTwoPartToken(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer header.payload';

        $identifier = getRateLimitIdentifier();

        // Should fallback to IP only (token must have 3 parts)
        $this->assertEquals('192.168.1.100', $identifier);
    }

    /**
     * Test applyRateLimit allows requests within limit
     */
    public function testApplyRateLimitAllowsWithinLimit(): void
    {
        $this->markTestSkipped('applyRateLimit calls header() and potentially exit() - requires output buffering or refactoring');

        // This would need output buffering and exception handling
        // ob_start();
        // applyRateLimit('test-user', '/api/scan');
        // $output = ob_get_clean();
    }

    /**
     * Test cleanupRateLimits function exists and is callable
     */
    public function testCleanupRateLimitsExists(): void
    {
        $this->assertTrue(function_exists('cleanupRateLimits'));
        $this->assertTrue(is_callable('cleanupRateLimits'));
    }

    /**
     * Test cleanupRateLimits can be called (basic smoke test)
     */
    public function testCleanupRateLimitsCanBeCalled(): void
    {
        // This might log but shouldn't throw errors
        $this->expectNotToPerformAssertions();

        try {
            cleanupRateLimits();
        } catch (\Throwable $e) {
            // If RateLimiter class isn't available, that's expected
            if (strpos($e->getMessage(), 'Class') !== false) {
                $this->markTestSkipped('RateLimiter class not available');
            }
            throw $e;
        }
    }

    /**
     * Test RATE_LIMITS constant is defined
     */
    public function testRateLimitsConstantIsDefined(): void
    {
        $this->assertTrue(defined('RATE_LIMITS'));
        $this->assertIsArray(RATE_LIMITS);
    }

    /**
     * Test RATE_LIMITS has expected endpoints
     */
    public function testRateLimitsHasExpectedEndpoints(): void
    {
        $this->assertArrayHasKey('/api/scan', RATE_LIMITS);
        $this->assertArrayHasKey('/api/scan/batch', RATE_LIMITS);
        $this->assertArrayHasKey('/api/sheets/write', RATE_LIMITS);
        $this->assertArrayHasKey('/api/auth/me', RATE_LIMITS);
        $this->assertArrayHasKey('default', RATE_LIMITS);
    }

    /**
     * Test RATE_LIMITS values are reasonable
     */
    public function testRateLimitsValuesAreReasonable(): void
    {
        foreach (RATE_LIMITS as $endpoint => $limit) {
            $this->assertIsInt($limit);
            $this->assertGreaterThan(0, $limit, "Rate limit for $endpoint should be positive");
            $this->assertLessThanOrEqual(1000, $limit, "Rate limit for $endpoint seems too high");
        }
    }

    /**
     * Test rate limit for batch endpoint is lower than single scan
     */
    public function testBatchRateLimitIsLowerThanSingle(): void
    {
        $this->assertLessThan(
            RATE_LIMITS['/api/scan'],
            RATE_LIMITS['/api/scan/batch'],
            'Batch scan should have stricter rate limit than single scan'
        );
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_AUTHORIZATION']);

        parent::tearDown();
    }
}
