<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// Load RateLimiter class if not already loaded
if (!class_exists('RateLimiter')) {
    require_once __DIR__ . '/../../RateLimiter.php';
}

use RateLimiter;

/**
 * Tests for RateLimiter class
 */
class RateLimiterTest extends TestCase
{
    private string $testStorageDir;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create a unique test storage directory
        $this->testStorageDir = sys_get_temp_dir() . '/rate_limits_test_' . uniqid();
    }

    /**
     * Test RateLimiter constructor creates storage directory
     */
    public function testConstructorCreatesStorageDirectory(): void
    {
        $limiter = new RateLimiter(10, 60);

        $this->assertTrue(is_dir(sys_get_temp_dir() . '/rate_limits'));
    }

    /**
     * Test first request is allowed
     */
    public function testFirstRequestIsAllowed(): void
    {
        $limiter = new RateLimiter(5, 60);
        $result = $limiter->check('user123');

        $this->assertTrue($result['allowed']);
        $this->assertEquals(5, $result['limit']);
        $this->assertEquals(4, $result['remaining']);
        $this->assertGreaterThan(time(), $result['reset']);
    }

    /**
     * Test multiple requests within limit
     */
    public function testMultipleRequestsWithinLimit(): void
    {
        $limiter = new RateLimiter(5, 60);

        for ($i = 0; $i < 5; $i++) {
            $result = $limiter->check('user_test');
            $this->assertTrue($result['allowed'], "Request $i should be allowed");
        }
    }

    /**
     * Test request exceeding limit is blocked
     */
    public function testRequestExceedingLimitIsBlocked(): void
    {
        $limiter = new RateLimiter(3, 60);

        // Make 3 requests (all allowed)
        for ($i = 0; $i < 3; $i++) {
            $result = $limiter->check('user_limited');
            $this->assertTrue($result['allowed'], "Request $i should be allowed");
        }

        // 4th request should be blocked
        $result = $limiter->check('user_limited');
        $this->assertFalse($result['allowed']);
        $this->assertEquals(0, $result['remaining']);
    }

    /**
     * Test remaining counter decrements correctly
     */
    public function testRemainingCounterDecrementsCorrectly(): void
    {
        $limiter = new RateLimiter(10, 60);

        for ($i = 0; $i < 5; $i++) {
            $result = $limiter->check('user_counter');
            $expectedRemaining = 10 - $i - 1;
            $this->assertEquals($expectedRemaining, $result['remaining']);
        }
    }

    /**
     * Test different identifiers have separate limits
     */
    public function testDifferentIdentifiersHaveSeparateLimits(): void
    {
        $limiter = new RateLimiter(2, 60);

        // User1 makes 2 requests (exhausts limit)
        $limiter->check('user1');
        $result1 = $limiter->check('user1');
        $this->assertTrue($result1['allowed']);

        $result1_blocked = $limiter->check('user1');
        $this->assertFalse($result1_blocked['allowed']);

        // User2 should still be allowed
        $result2 = $limiter->check('user2');
        $this->assertTrue($result2['allowed']);
    }

    /**
     * Test reset time is consistent within window
     */
    public function testResetTimeIsConsistent(): void
    {
        $limiter = new RateLimiter(5, 60);

        $result1 = $limiter->check('user_reset');
        $reset1 = $result1['reset'];

        sleep(1); // Wait 1 second

        $result2 = $limiter->check('user_reset');
        $reset2 = $result2['reset'];

        // Reset time should be the same (within same window)
        $this->assertEquals($reset1, $reset2);
    }

    /**
     * Test cleanup removes old files
     */
    public function testCleanupRemovesOldFiles(): void
    {
        $limiter = new RateLimiter(10, 2); // 2 second window

        // Create some requests
        $limiter->check('user_cleanup1');
        $limiter->check('user_cleanup2');

        // Wait for files to be old enough (2 * window = 4 seconds)
        sleep(5);

        $cleaned = $limiter->cleanup();

        $this->assertGreaterThanOrEqual(2, $cleaned);
    }

    /**
     * Test cleanup doesn't remove recent files
     */
    public function testCleanupDoesntRemoveRecentFiles(): void
    {
        $limiter = new RateLimiter(10, 60);

        // Create a recent request
        $limiter->check('user_recent');

        // Cleanup immediately
        $cleaned = $limiter->cleanup();

        // Subsequent request should still work (file not deleted)
        $result = $limiter->check('user_recent');
        $this->assertEquals(8, $result['remaining']); // Should be 2nd request
    }

    /**
     * Test cleanup with no files
     */
    public function testCleanupWithNoFiles(): void
    {
        // Create a new limiter with unique storage
        $limiter = new RateLimiter(10, 60);

        // Don't make any requests, just cleanup
        $cleaned = $limiter->cleanup();

        $this->assertGreaterThanOrEqual(0, $cleaned);
    }

    /**
     * Test rate limiter with very small window
     */
    public function testRateLimiterWithSmallWindow(): void
    {
        $limiter = new RateLimiter(3, 1); // 1 second window

        // Exhaust limit
        $limiter->check('user_window');
        $limiter->check('user_window');
        $limiter->check('user_window');

        // Should be blocked
        $result = $limiter->check('user_window');
        $this->assertFalse($result['allowed']);

        // Wait for window to reset
        sleep(2);

        // Should be allowed again
        $result = $limiter->check('user_window');
        $this->assertTrue($result['allowed']);
        $this->assertEquals(2, $result['remaining']);
    }

    /**
     * Test blocked request doesn't increment counter
     */
    public function testBlockedRequestDoesntIncrementCounter(): void
    {
        $limiter = new RateLimiter(2, 60);

        $limiter->check('user_blocked');
        $limiter->check('user_blocked');

        // These should be blocked and not increment
        $result1 = $limiter->check('user_blocked');
        $result2 = $limiter->check('user_blocked');
        $result3 = $limiter->check('user_blocked');

        $this->assertFalse($result1['allowed']);
        $this->assertFalse($result2['allowed']);
        $this->assertFalse($result3['allowed']);

        // All should have 0 remaining
        $this->assertEquals(0, $result1['remaining']);
        $this->assertEquals(0, $result2['remaining']);
        $this->assertEquals(0, $result3['remaining']);
    }

    /**
     * Test rate limiter with special characters in identifier
     */
    public function testRateLimiterWithSpecialCharacters(): void
    {
        $limiter = new RateLimiter(5, 60);

        $identifier = 'user@example.com:192.168.1.1';
        $result = $limiter->check($identifier);

        $this->assertTrue($result['allowed']);
        $this->assertEquals(4, $result['remaining']);
    }

    /**
     * Test rate limiter handles empty identifier
     */
    public function testRateLimiterHandlesEmptyIdentifier(): void
    {
        $limiter = new RateLimiter(5, 60);

        $result = $limiter->check('');

        $this->assertTrue($result['allowed']);
        $this->assertEquals(5, $result['limit']);
    }

    /**
     * Test concurrent access simulation
     */
    public function testConcurrentAccessSimulation(): void
    {
        $limiter = new RateLimiter(10, 60);
        $identifier = 'user_concurrent';

        // Simulate multiple quick requests
        $results = [];
        for ($i = 0; $i < 15; $i++) {
            $results[] = $limiter->check($identifier);
        }

        // First 10 should be allowed
        $allowedCount = 0;
        $blockedCount = 0;

        foreach ($results as $result) {
            if ($result['allowed']) {
                $allowedCount++;
            } else {
                $blockedCount++;
            }
        }

        $this->assertEquals(10, $allowedCount);
        $this->assertEquals(5, $blockedCount);
    }

    /**
     * Test cleanup return value
     */
    public function testCleanupReturnValue(): void
    {
        $limiter = new RateLimiter(10, 1); // 1 second window

        // Create 3 users
        $limiter->check('cleanup_user1');
        $limiter->check('cleanup_user2');
        $limiter->check('cleanup_user3');

        // Wait for files to be old (2 * window = 2 seconds)
        sleep(3);

        $cleaned = $limiter->cleanup();

        // Should have cleaned at least 3 files
        $this->assertGreaterThanOrEqual(3, $cleaned);
    }

    /**
     * Clean up test files after each test
     */
    protected function tearDown(): void
    {
        // Clean up rate limit files created during tests
        $storageDir = sys_get_temp_dir() . '/rate_limits';
        if (is_dir($storageDir)) {
            $files = glob($storageDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        parent::tearDown();
    }
}
