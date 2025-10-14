<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for logging functions
 */
class LoggingTest extends TestCase
{
    private string $logFile = '';

    /**
     * Set up test environment before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary log file for capturing error_log() output
        $this->logFile = sys_get_temp_dir() . '/test_log_' . uniqid() . '.log';
        ini_set('error_log', $this->logFile);

        // Clear the log file
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    /**
     * Read and return the last log entry (JSON part only, without timestamp)
     */
    private function getLastLogEntry(): string
    {
        if (!file_exists($this->logFile)) {
            return '';
        }

        $content = file_get_contents($this->logFile);
        $lines = array_filter(explode("\n", $content));
        $lastLine = end($lines) ?: '';

        // Remove timestamp prefix: [12-Oct-2025 23:10:09 UTC] {...json...}
        // Extract only the JSON part
        if (preg_match('/\[.*?\]\s*(.*)/', $lastLine, $matches)) {
            return $matches[1];
        }

        return $lastLine;
    }

    /**
     * Test logMessage outputs structured JSON
     */
    public function testLogMessage(): void
    {
        // Enable debug mode for this test
        $GLOBALS['DEBUG'] = true;

        logMessage('info', 'Test message', ['key' => 'value']);

        // Read log output from file
        $logOutput = $this->getLastLogEntry();

        // Verify JSON structure
        $this->assertNotEmpty($logOutput);
        $decoded = json_decode($logOutput, true);
        $this->assertIsArray($decoded);
        $this->assertEquals('info', $decoded['level']);
        $this->assertEquals('Test message', $decoded['message']);
        $this->assertEquals(['key' => 'value'], $decoded['context']);
        $this->assertArrayHasKey('timestamp', $decoded);
    }

    /**
     * Test logMessage with different levels
     */
    public function testLogMessageLevels(): void
    {
        $GLOBALS['DEBUG'] = true;

        $levels = ['info', 'warn', 'error'];

        foreach ($levels as $level) {
            // Clear log file for each level
            if (file_exists($this->logFile)) {
                file_put_contents($this->logFile, '');
            }

            logMessage($level, "Test $level message");

            $logOutput = $this->getLastLogEntry();
            $decoded = json_decode($logOutput, true);
            $this->assertEquals($level, $decoded['level']);
        }
    }

    /**
     * Test logMessage in production mode (DEBUG=false)
     */
    public function testLogMessageProductionMode(): void
    {
        $GLOBALS['DEBUG'] = false;

        // Clear log file
        if (file_exists($this->logFile)) {
            file_put_contents($this->logFile, '');
        }

        // Info should not log in production
        logMessage('info', 'Info message');
        $logOutput = $this->getLastLogEntry();
        $this->assertEmpty($logOutput);

        // Error should always log
        logMessage('error', 'Error message');
        $logOutput = $this->getLastLogEntry();
        $this->assertNotEmpty($logOutput);
    }

    /**
     * Test logApiRequest
     */
    public function testLogApiRequest(): void
    {
        $GLOBALS['DEBUG'] = true;

        // Clear log file
        if (file_exists($this->logFile)) {
            file_put_contents($this->logFile, '');
        }

        logApiRequest('/api/test', 200, ['user' => 'test@example.com']);

        $logOutput = $this->getLastLogEntry();
        $decoded = json_decode($logOutput, true);
        $this->assertEquals('info', $decoded['level']);
        $this->assertStringContainsString('/api/test', $decoded['message']);
        $this->assertEquals(200, $decoded['context']['status_code']);
    }

    /**
     * Test logApiRequest with error status
     */
    public function testLogApiRequestError(): void
    {
        $GLOBALS['DEBUG'] = true;

        // Clear log file
        if (file_exists($this->logFile)) {
            file_put_contents($this->logFile, '');
        }

        logApiRequest('/api/test', 500);

        $logOutput = $this->getLastLogEntry();
        $decoded = json_decode($logOutput, true);
        $this->assertEquals('error', $decoded['level']);
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        // Clean up log file
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }

        // Reset error_log to default
        ini_restore('error_log');

        unset($GLOBALS['DEBUG']);
        parent::tearDown();
    }
}
