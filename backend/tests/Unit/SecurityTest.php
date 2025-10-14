<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for security-related functions
 */
class SecurityTest extends TestCase
{
    /**
     * Test maskSensitiveData masks Bearer tokens
     */
    public function testMaskSensitiveDataBearerToken(): void
    {
        $data = 'Authorization: Bearer abc123xyz456';
        $masked = maskSensitiveData($data);

        $this->assertStringContainsString('Bearer ***', $masked);
        $this->assertStringNotContainsString('abc123xyz456', $masked);
    }

    /**
     * Test maskSensitiveData masks tokens in JSON
     */
    public function testMaskSensitiveDataTokenInJSON(): void
    {
        $data = '{"token": "secret123", "access_token": "xyz456"}';
        $masked = maskSensitiveData($data);

        $this->assertStringContainsString('token="***"', $masked);
        $this->assertStringNotContainsString('secret123', $masked);
    }

    /**
     * Test maskSensitiveData masks client IDs
     */
    public function testMaskSensitiveDataClientId(): void
    {
        $data = 'client_id=12345-abcdef.apps.googleusercontent.com';
        $masked = maskSensitiveData($data);

        $this->assertStringContainsString('client_id="***"', $masked);
        $this->assertStringNotContainsString('12345-abcdef', $masked);
    }

    /**
     * Test maskSensitiveData masks email addresses
     */
    public function testMaskSensitiveDataEmail(): void
    {
        $data = 'User email: user@example.com';
        $masked = maskSensitiveData($data);

        $this->assertStringContainsString('***@***.***', $masked);
        $this->assertStringNotContainsString('user@example.com', $masked);
    }

    /**
     * Test maskSensitiveData with multiple sensitive values
     */
    public function testMaskSensitiveDataMultiple(): void
    {
        $data = 'Bearer abc123 from user@example.com with token="secret"';
        $masked = maskSensitiveData($data);

        $this->assertStringContainsString('Bearer ***', $masked);
        $this->assertStringContainsString('***@***.***', $masked);
        $this->assertStringContainsString('token="***"', $masked);
    }

    /**
     * Test maskSensitiveData with non-sensitive data
     */
    public function testMaskSensitiveDataNoSensitiveData(): void
    {
        $data = 'This is a normal message without secrets';
        $masked = maskSensitiveData($data);

        $this->assertEquals($data, $masked);
    }

    /**
     * Test maskSensitiveData with non-string input
     */
    public function testMaskSensitiveDataNonString(): void
    {
        $data = ['array', 'of', 'values'];
        $result = maskSensitiveData($data);

        $this->assertEquals($data, $result);
    }

    /**
     * Test bearer token extraction
     */
    public function testBearerExtraction(): void
    {
        // Test with Authorization header (mocked via $_SERVER)
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test-token-123';
        $token = bearer();
        $this->assertEquals('test-token-123', $token);

        // Cleanup
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    /**
     * Test bearer token extraction with different header formats
     */
    public function testBearerExtractionVariousFormats(): void
    {
        // Case insensitive
        $_SERVER['HTTP_AUTHORIZATION'] = 'bearer lowercase-token';
        $token = bearer();
        $this->assertEquals('lowercase-token', $token);

        // With extra whitespace
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer  spaced-token  ';
        $token = bearer();
        $this->assertEquals('spaced-token  ', $token); // trim only Bearer prefix

        // Cleanup
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    /**
     * Test bearer token extraction from query string
     */
    public function testBearerExtractionFromQuery(): void
    {
        $_GET['access_token'] = 'query-token-123';
        $token = bearer();
        $this->assertEquals('query-token-123', $token);

        // Cleanup
        unset($_GET['access_token']);
    }

    /**
     * Test bearer returns null when no token
     */
    public function testBearerNoToken(): void
    {
        // Ensure no token sources exist
        unset($_SERVER['HTTP_AUTHORIZATION']);
        unset($_GET['access_token']);

        $token = bearer();
        $this->assertNull($token);
    }

    /**
     * Test detectProtocol with HTTPS
     */
    public function testDetectProtocolHTTPS(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $protocol = detectProtocol();
        $this->assertEquals('https', $protocol);

        unset($_SERVER['HTTPS']);
    }

    /**
     * Test detectProtocol with X-Forwarded-Proto
     */
    public function testDetectProtocolForwardedProto(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $protocol = detectProtocol();
        $this->assertEquals('https', $protocol);

        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
    }

    /**
     * Test detectProtocol with Cloud Run
     */
    public function testDetectProtocolCloudRun(): void
    {
        $_SERVER['HTTP_HOST'] = 'myapp-abc123.a.run.app';
        $protocol = detectProtocol();
        $this->assertEquals('https', $protocol);

        unset($_SERVER['HTTP_HOST']);
    }

    /**
     * Test detectProtocol defaults to http
     */
    public function testDetectProtocolDefaultHTTP(): void
    {
        // Clear all relevant server variables
        unset($_SERVER['HTTPS']);
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
        unset($_SERVER['HTTP_HOST']);

        $protocol = detectProtocol();
        $this->assertEquals('http', $protocol);
    }

    /**
     * Clean up after each test to prevent state leakage
     */
    protected function tearDown(): void
    {
        // Clean up all potential bearer token sources
        unset($_SERVER['HTTP_AUTHORIZATION']);
        unset($_SERVER['Authorization']);
        unset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        unset($_GET['access_token']);

        // Clean up protocol detection variables
        unset($_SERVER['HTTPS']);
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
        unset($_SERVER['HTTP_HOST']);

        parent::tearDown();
    }
}
