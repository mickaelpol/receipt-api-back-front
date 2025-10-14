<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for column parsing and conversion functions
 */
class ColumnParsingTest extends TestCase
{
    /**
     * Test col_letter_to_index conversion
     */
    public function testColLetterToIndex(): void
    {
        // Single letters
        $this->assertEquals(0, col_letter_to_index('A'));
        $this->assertEquals(1, col_letter_to_index('B'));
        $this->assertEquals(25, col_letter_to_index('Z'));

        // Double letters
        $this->assertEquals(26, col_letter_to_index('AA'));
        $this->assertEquals(27, col_letter_to_index('AB'));
        $this->assertEquals(51, col_letter_to_index('AZ'));
        $this->assertEquals(52, col_letter_to_index('BA'));

        // Triple letters
        $this->assertEquals(702, col_letter_to_index('AAA'));
    }

    /**
     * Test col_letter_to_index with lowercase
     */
    public function testColLetterToIndexLowercase(): void
    {
        $this->assertEquals(0, col_letter_to_index('a'));
        $this->assertEquals(1, col_letter_to_index('b'));
        $this->assertEquals(25, col_letter_to_index('z'));
    }

    /**
     * Test col_letter_to_index with whitespace
     */
    public function testColLetterToIndexWithWhitespace(): void
    {
        $this->assertEquals(0, col_letter_to_index(' A '));
        $this->assertEquals(26, col_letter_to_index('  AA  '));
    }

    /**
     * Test validate_who_columns with valid JSON
     */
    public function testValidateWhoColumnsValid(): void
    {
        $validJson = json_encode([
            'Sabrina' => ['K', 'L', 'M'],
            'Mickael' => ['O', 'P', 'Q']
        ]);

        $this->assertTrue(validate_who_columns($validJson));
    }

    /**
     * Test validate_who_columns with invalid formats
     */
    public function testValidateWhoColumnsInvalid(): void
    {
        // Invalid JSON
        $this->assertFalse(validate_who_columns('not json'));

        // Missing columns
        $invalidJson = json_encode(['Sabrina' => ['K', 'L']]);
        $this->assertFalse(validate_who_columns($invalidJson));

        // Too many columns
        $invalidJson = json_encode(['Sabrina' => ['K', 'L', 'M', 'N']]);
        $this->assertFalse(validate_who_columns($invalidJson));

        // Empty name
        $invalidJson = json_encode(['' => ['K', 'L', 'M']]);
        $this->assertFalse(validate_who_columns($invalidJson));

        // Non-letter column
        $invalidJson = json_encode(['Sabrina' => ['K', 'L', '1']]);
        $this->assertFalse(validate_who_columns($invalidJson));

        // Multi-letter column
        $invalidJson = json_encode(['Sabrina' => ['K', 'L', 'MM']]);
        $this->assertFalse(validate_who_columns($invalidJson));

        // Empty array
        $invalidJson = json_encode([]);
        $this->assertFalse(validate_who_columns($invalidJson));
    }

    /**
     * Test parse_who_columns with JSON format
     */
    public function testParseWhoColumnsJSON(): void
    {
        // Set environment variable
        putenv('WHO_COLUMNS={"Sabrina":["K","L","M"],"Mickael":["O","P","Q"]}');

        $result = parse_who_columns();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('Sabrina', $result);
        $this->assertArrayHasKey('Mickael', $result);
        $this->assertEquals(['K', 'L', 'M'], $result['Sabrina']);
        $this->assertEquals(['O', 'P', 'Q'], $result['Mickael']);

        // Cleanup
        putenv('WHO_COLUMNS');
    }

    /**
     * Test parse_who_columns with legacy format
     */
    public function testParseWhoColumnsLegacy(): void
    {
        // Set environment variable in legacy format
        putenv('WHO_COLUMNS=Sabrina:K,L,M;Mickael:O,P,Q');

        $result = parse_who_columns();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('Sabrina', $result);
        $this->assertArrayHasKey('Mickael', $result);
        $this->assertEquals(['K', 'L', 'M'], $result['Sabrina']);
        $this->assertEquals(['O', 'P', 'Q'], $result['Mickael']);

        // Cleanup
        putenv('WHO_COLUMNS');
    }

    /**
     * Test parse_who_columns with lowercase letters
     */
    public function testParseWhoColumnsLowercase(): void
    {
        putenv('WHO_COLUMNS={"Sabrina":["k","l","m"]}');

        $result = parse_who_columns();

        // Should be normalized to uppercase
        $this->assertEquals(['K', 'L', 'M'], $result['Sabrina']);

        // Cleanup
        putenv('WHO_COLUMNS');
    }

    /**
     * Test parse_who_columns with empty/invalid input
     */
    public function testParseWhoColumnsEmpty(): void
    {
        putenv('WHO_COLUMNS=');

        $result = parse_who_columns();

        // Should return empty array for invalid input
        $this->assertIsArray($result);
        $this->assertEmpty($result);

        // Cleanup
        putenv('WHO_COLUMNS');
    }
}
