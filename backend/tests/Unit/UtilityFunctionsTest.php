<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for utility functions in app.php
 */
class UtilityFunctionsTest extends TestCase
{
    /**
     * Test validate_who_columns with valid JSON
     */
    public function testValidateWhoColumnsValid(): void
    {
        $validJson = '{"Sabrina":["K","L","M"],"Mickael":["O","P","Q"]}';
        $this->assertTrue(validate_who_columns($validJson));
    }

    /**
     * Test validate_who_columns with invalid JSON
     */
    public function testValidateWhoColumnsInvalidJson(): void
    {
        $this->assertFalse(validate_who_columns('invalid json'));
        $this->assertFalse(validate_who_columns(''));
        $this->assertFalse(validate_who_columns('[]'));
    }

    /**
     * Test validate_who_columns with invalid structure
     */
    public function testValidateWhoColumnsInvalidStructure(): void
    {
        // Empty name
        $this->assertFalse(validate_who_columns('{"":["A","B","C"]}'));

        // Wrong number of columns (2 instead of 3)
        $this->assertFalse(validate_who_columns('{"Test":["A","B"]}'));

        // Wrong number of columns (4 instead of 3)
        $this->assertFalse(validate_who_columns('{"Test":["A","B","C","D"]}'));

        // Non-letter column
        $this->assertFalse(validate_who_columns('{"Test":["A","B","1"]}'));

        // Multi-character column
        $this->assertFalse(validate_who_columns('{"Test":["A","BB","C"]}'));

        // Columns not an array
        $this->assertFalse(validate_who_columns('{"Test":"ABC"}'));
    }

    /**
     * Test validate_who_columns with empty object
     */
    public function testValidateWhoColumnsEmpty(): void
    {
        $this->assertFalse(validate_who_columns('{}'));
    }

    /**
     * Test parse_who_columns with valid JSON format
     */
    public function testParseWhoColumnsJson(): void
    {
        putenv('WHO_COLUMNS={"Sabrina":["K","L","M"],"Mickael":["o","p","q"]}');

        $result = parse_who_columns();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('Sabrina', $result);
        $this->assertArrayHasKey('Mickael', $result);
        $this->assertEquals(['K', 'L', 'M'], $result['Sabrina']);
        $this->assertEquals(['O', 'P', 'Q'], $result['Mickael']); // Should be uppercased

        putenv('WHO_COLUMNS'); // Clear
    }

    /**
     * Test parse_who_columns with legacy format
     */
    public function testParseWhoColumnsLegacy(): void
    {
        putenv('WHO_COLUMNS=Sabrina:K,L,M;Mickael:O,P,Q');

        $result = parse_who_columns();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('Sabrina', $result);
        $this->assertArrayHasKey('Mickael', $result);
        $this->assertEquals(['K', 'L', 'M'], $result['Sabrina']);
        $this->assertEquals(['O', 'P', 'Q'], $result['Mickael']);

        putenv('WHO_COLUMNS'); // Clear
    }

    /**
     * Test parse_who_columns with WHO_COLUMNS_JSON
     */
    public function testParseWhoColumnsJsonEnv(): void
    {
        putenv('WHO_COLUMNS_JSON={"Test":["A","B","C"]}');

        $result = parse_who_columns();

        $this->assertArrayHasKey('Test', $result);
        $this->assertEquals(['A', 'B', 'C'], $result['Test']);

        putenv('WHO_COLUMNS_JSON'); // Clear
    }

    /**
     * Test parse_who_columns returns empty array when not set
     */
    public function testParseWhoColumnsEmpty(): void
    {
        putenv('WHO_COLUMNS');
        putenv('WHO_COLUMNS_JSON');

        $result = parse_who_columns();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test parse_who_columns filters invalid entries
     */
    public function testParseWhoColumnsFilterInvalid(): void
    {
        putenv('WHO_COLUMNS=ValidUser:A,B,C;InvalidUser:X,Y;AnotherValid:D,E,F');

        $result = parse_who_columns();

        $this->assertArrayHasKey('ValidUser', $result);
        $this->assertArrayNotHasKey('InvalidUser', $result); // Only 2 columns
        $this->assertArrayHasKey('AnotherValid', $result);

        putenv('WHO_COLUMNS'); // Clear
    }

    /**
     * Test col_letter_to_index with single letters
     */
    public function testColLetterToIndexSingle(): void
    {
        $this->assertEquals(0, col_letter_to_index('A'));
        $this->assertEquals(1, col_letter_to_index('B'));
        $this->assertEquals(25, col_letter_to_index('Z'));
    }

    /**
     * Test col_letter_to_index with double letters
     */
    public function testColLetterToIndexDouble(): void
    {
        $this->assertEquals(26, col_letter_to_index('AA'));
        $this->assertEquals(27, col_letter_to_index('AB'));
        $this->assertEquals(51, col_letter_to_index('AZ'));
        $this->assertEquals(52, col_letter_to_index('BA'));
    }

    /**
     * Test col_letter_to_index with lowercase
     */
    public function testColLetterToIndexLowercase(): void
    {
        $this->assertEquals(0, col_letter_to_index('a'));
        $this->assertEquals(1, col_letter_to_index('b'));
        $this->assertEquals(26, col_letter_to_index('aa'));
    }

    /**
     * Test col_letter_to_index with whitespace
     */
    public function testColLetterToIndexWhitespace(): void
    {
        $this->assertEquals(0, col_letter_to_index(' A '));
        $this->assertEquals(1, col_letter_to_index('  B  '));
    }

    /**
     * Test generateTransactionId returns unique values
     */
    public function testGenerateTransactionIdUnique(): void
    {
        $id1 = generateTransactionId();
        $id2 = generateTransactionId();

        $this->assertIsString($id1);
        $this->assertIsString($id2);
        $this->assertNotEquals($id1, $id2);
        $this->assertEquals(32, strlen($id1)); // 16 bytes = 32 hex chars
        $this->assertEquals(32, strlen($id2));
    }

    /**
     * Test generateTransactionId format
     */
    public function testGenerateTransactionIdFormat(): void
    {
        $id = generateTransactionId();

        // Should be 32 hex characters
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $id);
    }

    /**
     * Test save_base64_to_tmp with valid base64
     */
    public function testSaveBase64ToTmpValid(): void
    {
        // Create a simple PNG in base64
        $base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $path = save_base64_to_tmp($base64, '.png');

        $this->assertFileExists($path);
        $this->assertStringContainsString('docai_', basename($path));
        $this->assertStringEndsWith('.png', $path);

        // Cleanup
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * Test save_base64_to_tmp with data URI
     */
    public function testSaveBase64ToTmpWithDataUri(): void
    {
        $dataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $path = save_base64_to_tmp($dataUri, '.png');

        $this->assertFileExists($path);

        // Cleanup
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * Test save_base64_to_tmp with invalid base64
     */
    public function testSaveBase64ToTmpInvalid(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Base64 invalide');

        save_base64_to_tmp('not-valid-base64!!!', '.jpg');
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        // Clean up environment variables
        putenv('WHO_COLUMNS');
        putenv('WHO_COLUMNS_JSON');

        parent::tearDown();
    }
}
