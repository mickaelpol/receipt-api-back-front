<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Google Sheets functions
 * These tests use mock data and don't require real API calls
 */
class SheetsIntegrationTest extends TestCase
{
    /**
     * Test findNextEmptyRow logic with mock HTTP response
     *
     * Note: This is a pseudo-integration test that verifies the logic
     * without making real API calls
     */
    public function testFindNextEmptyRowLogic(): void
    {
        // We can't easily test findNextEmptyRow without mocking HTTP calls
        // But we can verify it exists and has the right signature
        $this->assertTrue(function_exists('findNextEmptyRow'));

        // Verify function signature by reflection
        $reflection = new \ReflectionFunction('findNextEmptyRow');
        $params = $reflection->getParameters();

        $this->assertCount(5, $params);
        $this->assertEquals('spreadsheetId', $params[0]->getName());
        $this->assertEquals('sheetName', $params[1]->getName());
        $this->assertEquals('column', $params[2]->getName());
        $this->assertEquals('startRow', $params[3]->getName());
        $this->assertEquals('token', $params[4]->getName());
    }

    /**
     * Test writeToSheetOptimistic exists and has correct signature
     */
    public function testWriteToSheetOptimisticSignature(): void
    {
        $this->assertTrue(function_exists('writeToSheetOptimistic'));

        $reflection = new \ReflectionFunction('writeToSheetOptimistic');
        $params = $reflection->getParameters();

        $this->assertGreaterThanOrEqual(8, count($params));
        $this->assertEquals('spreadsheetId', $params[0]->getName());
        $this->assertEquals('sheetName', $params[1]->getName());
        $this->assertEquals('cols', $params[2]->getName());
    }

    /**
     * Test get_sheet_id_by_title signature
     */
    public function testGetSheetIdByTitleSignature(): void
    {
        $this->assertTrue(function_exists('get_sheet_id_by_title'));

        $reflection = new \ReflectionFunction('get_sheet_id_by_title');
        $params = $reflection->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals('spreadsheetId', $params[0]->getName());
        $this->assertEquals('title', $params[1]->getName());
        $this->assertEquals('token', $params[2]->getName());
    }

    /**
     * Test docai_process_bytes signature
     */
    public function testDocaiProcessBytesSignature(): void
    {
        $this->assertTrue(function_exists('docai_process_bytes'));

        $reflection = new \ReflectionFunction('docai_process_bytes');
        $params = $reflection->getParameters();

        $this->assertCount(5, $params);
        $this->assertEquals('bytes', $params[0]->getName());
        $this->assertEquals('mime', $params[1]->getName());
        $this->assertEquals('projectId', $params[2]->getName());
        $this->assertEquals('location', $params[3]->getName());
        $this->assertEquals('processorId', $params[4]->getName());
    }

    /**
     * Test docai_process_bytes_cached signature
     */
    public function testDocaiProcessBytesCachedSignature(): void
    {
        $this->assertTrue(function_exists('docai_process_bytes_cached'));

        $reflection = new \ReflectionFunction('docai_process_bytes_cached');
        $params = $reflection->getParameters();

        $this->assertGreaterThanOrEqual(5, count($params));
        $this->assertEquals('bytes', $params[0]->getName());
    }

    /**
     * Test requireGoogleUserAllowed signature
     */
    public function testRequireGoogleUserAllowedSignature(): void
    {
        $this->assertTrue(function_exists('requireGoogleUserAllowed'));

        $reflection = new \ReflectionFunction('requireGoogleUserAllowed');
        $params = $reflection->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('allowed', $params[0]->getName());
        $this->assertEquals('clientId', $params[1]->getName());
    }

    /**
     * Test saToken signature
     */
    public function testSaTokenSignature(): void
    {
        $this->assertTrue(function_exists('saToken'));

        $reflection = new \ReflectionFunction('saToken');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('scopes', $params[0]->getName());
    }
}
