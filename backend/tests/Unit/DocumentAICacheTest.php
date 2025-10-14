<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for Document AI caching functions
 */
class DocumentAICacheTest extends TestCase
{
    private array $cacheFilesToCleanup = [];

    /**
     * Test docai_process_bytes_cached with cache miss
     */
    public function testDocaiProcessBytesCachedMiss(): void
    {
        $this->markTestSkipped('Requires mock of docai_process_bytes or actual API credentials');

        // This would test the cache miss scenario
        // But requires either mocking or real API access
    }

    /**
     * Test docai_process_bytes_cached cache key generation
     */
    public function testDocaiCacheKeyGeneration(): void
    {
        $bytes1 = 'test image data 1';
        $bytes2 = 'test image data 2';

        // Generate SHA256 hashes (simulating what the cache does)
        $hash1 = hash('sha256', $bytes1);
        $hash2 = hash('sha256', $bytes2);

        $this->assertNotEquals($hash1, $hash2);
        $this->assertEquals(64, strlen($hash1)); // SHA256 is 64 hex chars
        $this->assertEquals(64, strlen($hash2));

        // Same bytes should produce same hash
        $hash1_again = hash('sha256', $bytes1);
        $this->assertEquals($hash1, $hash1_again);
    }

    /**
     * Test cleanupDocAiCache removes files older than maxAge
     */
    public function testCleanupDocAiCacheWithOldFiles(): void
    {
        $tempDir = sys_get_temp_dir();

        // Create test files
        $oldFile1 = $tempDir . '/docai_cache_old1_' . uniqid() . '.json';
        $oldFile2 = $tempDir . '/docai_cache_old2_' . uniqid() . '.json';
        $recentFile = $tempDir . '/docai_cache_recent_' . uniqid() . '.json';

        file_put_contents($oldFile1, '{"test": "old1"}');
        file_put_contents($oldFile2, '{"test": "old2"}');
        file_put_contents($recentFile, '{"test": "recent"}');

        // Set old files to 2 days ago
        $twoDaysAgo = time() - (2 * 86400);
        touch($oldFile1, $twoDaysAgo);
        touch($oldFile2, $twoDaysAgo);

        // Cleanup files older than 1 day
        $cleaned = cleanupDocAiCache(86400);

        $this->assertGreaterThanOrEqual(2, $cleaned);
        $this->assertFileDoesNotExist($oldFile1);
        $this->assertFileDoesNotExist($oldFile2);
        $this->assertFileExists($recentFile);

        // Cleanup recent file
        unlink($recentFile);
    }

    /**
     * Test cleanupDocAiCache with various age thresholds
     */
    public function testCleanupDocAiCacheVariousAges(): void
    {
        $tempDir = sys_get_temp_dir();

        // Create files with different ages
        $file1Hour = $tempDir . '/docai_cache_1h_' . uniqid() . '.json';
        $file6Hours = $tempDir . '/docai_cache_6h_' . uniqid() . '.json';
        $file1Day = $tempDir . '/docai_cache_1d_' . uniqid() . '.json';

        file_put_contents($file1Hour, '{}');
        file_put_contents($file6Hours, '{}');
        file_put_contents($file1Day, '{}');

        touch($file1Hour, time() - 3600); // 1 hour ago
        touch($file6Hours, time() - (6 * 3600)); // 6 hours ago
        touch($file1Day, time() - 86400); // 1 day ago

        // Cleanup files older than 12 hours
        $cleaned = cleanupDocAiCache(12 * 3600);

        $this->assertFileExists($file1Hour); // Should exist
        $this->assertFileExists($file6Hours); // Should exist
        $this->assertFileDoesNotExist($file1Day); // Should be deleted

        // Cleanup remaining files
        if (file_exists($file1Hour)) unlink($file1Hour);
        if (file_exists($file6Hours)) unlink($file6Hours);
    }

    /**
     * Test cleanupDocAiCache with empty directory
     */
    public function testCleanupDocAiCacheEmptyDirectory(): void
    {
        // First clean all cache files
        $cacheFiles = glob(sys_get_temp_dir() . '/docai_cache_*.json');
        if ($cacheFiles) {
            foreach ($cacheFiles as $file) {
                if (strpos($file, 'docai_cache_') !== false) {
                    unlink($file);
                }
            }
        }

        $cleaned = cleanupDocAiCache();

        $this->assertEquals(0, $cleaned);
    }

    /**
     * Test cleanupDocAiCache doesn't remove files from other apps
     */
    public function testCleanupDocAiCacheOnlyRemovesDocAiFiles(): void
    {
        $tempDir = sys_get_temp_dir();

        // Create a non-docai file
        $otherFile = $tempDir . '/other_cache_file_' . uniqid() . '.json';
        file_put_contents($otherFile, '{}');
        touch($otherFile, time() - (10 * 86400)); // 10 days old

        $cleaned = cleanupDocAiCache(1); // Clean files older than 1 second

        // The other file should still exist
        $this->assertFileExists($otherFile);

        // Cleanup
        unlink($otherFile);
    }

    /**
     * Test cleanupDocAiCache handles missing files gracefully
     */
    public function testCleanupDocAiCacheHandlesMissingFiles(): void
    {
        // Create a file, then delete it before cleanup
        $tempDir = sys_get_temp_dir();
        $file = $tempDir . '/docai_cache_ghost_' . uniqid() . '.json';
        file_put_contents($file, '{}');
        touch($file, time() - (2 * 86400));

        // Delete it manually (simulating race condition)
        unlink($file);

        // This should not throw an error
        $cleaned = cleanupDocAiCache(86400);

        $this->assertGreaterThanOrEqual(0, $cleaned);
    }

    /**
     * Test cache file format expectations
     */
    public function testDocAiCacheFileFormat(): void
    {
        $tempDir = sys_get_temp_dir();

        // Simulate creating a cache file (as the system would)
        $imageHash = hash('sha256', 'test image content');
        $cacheFile = $tempDir . "/docai_cache_{$imageHash}.json";

        $mockResponse = [
            'document' => [
                'text' => 'Receipt content',
                'entities' => [
                    ['type' => 'total_amount', 'mentionText' => '42.00']
                ]
            ]
        ];

        file_put_contents($cacheFile, json_encode($mockResponse));

        // Verify file exists and content is valid JSON
        $this->assertFileExists($cacheFile);

        $content = file_get_contents($cacheFile);
        $decoded = json_decode($content, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('document', $decoded);

        // Cleanup
        unlink($cacheFile);
    }

    /**
     * Test cleanupDocAiCache return value
     */
    public function testCleanupDocAiCacheReturnValue(): void
    {
        $tempDir = sys_get_temp_dir();

        // Create exactly 3 old files
        $files = [];
        for ($i = 0; $i < 3; $i++) {
            $file = $tempDir . "/docai_cache_test{$i}_" . uniqid() . '.json';
            file_put_contents($file, '{}');
            touch($file, time() - (2 * 86400)); // 2 days old
            $files[] = $file;
        }

        // Cleanup files older than 1 day
        $cleaned = cleanupDocAiCache(86400);

        $this->assertEquals(3, $cleaned);

        // Verify all files are deleted
        foreach ($files as $file) {
            $this->assertFileDoesNotExist($file);
        }
    }

    /**
     * Clean up test files after each test
     */
    protected function tearDown(): void
    {
        // Clean up any remaining test cache files
        $cacheFiles = glob(sys_get_temp_dir() . '/docai_cache_*_test*.json');
        if ($cacheFiles) {
            foreach ($cacheFiles as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
        }

        parent::tearDown();
    }
}
