<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for Google credentials validation functions
 */
class GoogleCredentialsTest extends TestCase
{
    private string $testCredentialsPath = '';

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Clear environment variables
        putenv('K_SERVICE');
        putenv('GOOGLE_APPLICATION_CREDENTIALS');
    }

    /**
     * Test validateGoogleCredentials on Cloud Run
     */
    public function testValidateGoogleCredentialsCloudRun(): void
    {
        putenv('K_SERVICE=test-service');

        $result = validateGoogleCredentials();

        $this->assertTrue($result['valid']);
        $this->assertEquals('CLOUD_RUN_DEFAULT', $result['code']);
        $this->assertArrayHasKey('message', $result);

        putenv('K_SERVICE'); // Clear
    }

    /**
     * Test validateGoogleCredentials missing env variable
     */
    public function testValidateGoogleCredentialsMissingEnv(): void
    {
        putenv('K_SERVICE'); // Ensure not on Cloud Run
        putenv('GOOGLE_APPLICATION_CREDENTIALS'); // Clear

        $result = validateGoogleCredentials();

        $this->assertFalse($result['valid']);
        $this->assertEquals('MISSING_ENV', $result['code']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test validateGoogleCredentials file not found
     */
    public function testValidateGoogleCredentialsFileNotFound(): void
    {
        putenv('GOOGLE_APPLICATION_CREDENTIALS=/nonexistent/path/to/credentials.json');

        $result = validateGoogleCredentials();

        $this->assertFalse($result['valid']);
        $this->assertEquals('FILE_NOT_FOUND', $result['code']);
        $this->assertStringContainsString('does not exist', $result['error']);

        putenv('GOOGLE_APPLICATION_CREDENTIALS'); // Clear
    }

    /**
     * Test validateGoogleCredentials with valid credentials file
     */
    public function testValidateGoogleCredentialsValidFile(): void
    {
        // Create temporary valid credentials file
        $tempFile = sys_get_temp_dir() . '/test_credentials_' . uniqid() . '.json';
        $credentials = [
            'type' => 'service_account',
            'project_id' => 'test-project',
            'private_key' => '-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----\n',
            'client_email' => 'test@test-project.iam.gserviceaccount.com'
        ];

        file_put_contents($tempFile, json_encode($credentials));
        putenv("GOOGLE_APPLICATION_CREDENTIALS=$tempFile");

        $result = validateGoogleCredentials();

        $this->assertTrue($result['valid']);
        $this->assertEquals('test-project', $result['project_id']);
        $this->assertEquals('test@test-project.iam.gserviceaccount.com', $result['client_email']);
        $this->assertEquals($tempFile, $result['path']);

        // Cleanup
        unlink($tempFile);
        putenv('GOOGLE_APPLICATION_CREDENTIALS');
    }

    /**
     * Test validateGoogleCredentials with invalid JSON
     */
    public function testValidateGoogleCredentialsInvalidJson(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_credentials_invalid_' . uniqid() . '.json';
        file_put_contents($tempFile, 'not valid json {');
        putenv("GOOGLE_APPLICATION_CREDENTIALS=$tempFile");

        $result = validateGoogleCredentials();

        $this->assertFalse($result['valid']);
        $this->assertEquals('INVALID_JSON', $result['code']);
        $this->assertStringContainsString('Invalid JSON', $result['error']);

        // Cleanup
        unlink($tempFile);
        putenv('GOOGLE_APPLICATION_CREDENTIALS');
    }

    /**
     * Test validateGoogleCredentials with missing required field
     */
    public function testValidateGoogleCredentialsMissingField(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_credentials_missing_' . uniqid() . '.json';
        $credentials = [
            'type' => 'service_account',
            'project_id' => 'test-project',
            // Missing private_key and client_email
        ];

        file_put_contents($tempFile, json_encode($credentials));
        putenv("GOOGLE_APPLICATION_CREDENTIALS=$tempFile");

        $result = validateGoogleCredentials();

        $this->assertFalse($result['valid']);
        $this->assertEquals('MISSING_FIELD', $result['code']);
        $this->assertStringContainsString('Missing required field', $result['error']);

        // Cleanup
        unlink($tempFile);
        putenv('GOOGLE_APPLICATION_CREDENTIALS');
    }

    /**
     * Test validateGoogleCredentials with wrong type
     */
    public function testValidateGoogleCredentialsWrongType(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_credentials_wrong_type_' . uniqid() . '.json';
        $credentials = [
            'type' => 'authorized_user', // Not service_account
            'project_id' => 'test-project',
            'private_key' => 'test-key',
            'client_email' => 'test@example.com'
        ];

        file_put_contents($tempFile, json_encode($credentials));
        putenv("GOOGLE_APPLICATION_CREDENTIALS=$tempFile");

        $result = validateGoogleCredentials();

        $this->assertFalse($result['valid']);
        $this->assertEquals('INVALID_TYPE', $result['code']);
        $this->assertStringContainsString('expected service_account', $result['error']);

        // Cleanup
        unlink($tempFile);
        putenv('GOOGLE_APPLICATION_CREDENTIALS');
    }

    /**
     * Test validateGoogleCredentials with empty field
     */
    public function testValidateGoogleCredentialsEmptyField(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_credentials_empty_' . uniqid() . '.json';
        $credentials = [
            'type' => 'service_account',
            'project_id' => '', // Empty
            'private_key' => 'test-key',
            'client_email' => 'test@example.com'
        ];

        file_put_contents($tempFile, json_encode($credentials));
        putenv("GOOGLE_APPLICATION_CREDENTIALS=$tempFile");

        $result = validateGoogleCredentials();

        $this->assertFalse($result['valid']);
        $this->assertEquals('MISSING_FIELD', $result['code']);

        // Cleanup
        unlink($tempFile);
        putenv('GOOGLE_APPLICATION_CREDENTIALS');
    }

    /**
     * Test validateGoogleCredentials file not readable
     */
    public function testValidateGoogleCredentialsNotReadable(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_credentials_not_readable_' . uniqid() . '.json';
        file_put_contents($tempFile, '{}');
        chmod($tempFile, 0000); // Make unreadable

        putenv("GOOGLE_APPLICATION_CREDENTIALS=$tempFile");

        $result = validateGoogleCredentials();

        $this->assertFalse($result['valid']);
        // In Docker/root environment, file might still be readable, so it could return MISSING_FIELD
        // because the JSON is empty {}
        $this->assertContains($result['code'], ['FILE_NOT_READABLE', 'FILE_READ_ERROR', 'MISSING_FIELD']);

        // Cleanup
        chmod($tempFile, 0644);
        unlink($tempFile);
        putenv('GOOGLE_APPLICATION_CREDENTIALS');
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        putenv('K_SERVICE');
        putenv('GOOGLE_APPLICATION_CREDENTIALS');

        parent::tearDown();
    }
}
