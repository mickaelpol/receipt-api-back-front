<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for date parsing and conversion functions
 */
class DateParsingTest extends TestCase
{
    /**
     * Test parse_date_ymd with various date formats
     */
    public function testParseDateYMD(): void
    {
        // YYYY-MM-DD format
        $result = parse_date_ymd('2024-01-15');
        $this->assertEquals([2024, 1, 15], $result);

        // DD/MM/YYYY format
        $result = parse_date_ymd('15/01/2024');
        $this->assertEquals([2024, 1, 15], $result);

        // DD-MM-YYYY format
        $result = parse_date_ymd('15-01-2024');
        $this->assertEquals([2024, 1, 15], $result);

        // DD.MM.YYYY format
        $result = parse_date_ymd('15.01.2024');
        $this->assertEquals([2024, 1, 15], $result);

        // ISO 8601 with time
        $result = parse_date_ymd('2024-01-15T10:30:00');
        $this->assertEquals([2024, 1, 15], $result);

        // ISO 8601 with time and timezone
        $result = parse_date_ymd('2024-01-15 10:30:00+01:00');
        $this->assertEquals([2024, 1, 15], $result);
    }

    /**
     * Test parse_date_ymd with single digit days/months
     */
    public function testParseDateYMDWithSingleDigits(): void
    {
        $result = parse_date_ymd('2024-1-5');
        $this->assertEquals([2024, 1, 5], $result);

        $result = parse_date_ymd('5/1/2024');
        $this->assertEquals([2024, 1, 5], $result);
    }

    /**
     * Test parse_date_ymd with invalid formats
     */
    public function testParseDateYMDInvalid(): void
    {
        $this->assertNull(parse_date_ymd('invalid'));
        $this->assertNull(parse_date_ymd(''));
        $this->assertNull(parse_date_ymd('2024'));
        $this->assertNull(parse_date_ymd('15/15/2024')); // Invalid month
    }

    /**
     * Test sheets_date_serial conversion
     */
    public function testSheetsDateSerial(): void
    {
        // Excel serial date for 2024-01-01 should be around 45292
        $serial = sheets_date_serial(2024, 1, 1);
        $this->assertGreaterThan(45000, $serial);
        $this->assertLessThan(46000, $serial);

        // Test specific known dates
        // 1970-01-01 should be 25569 (Unix epoch)
        $serial = sheets_date_serial(1970, 1, 1);
        $this->assertEquals(25569, $serial);
    }

    /**
     * Test sheets_date_serial with different dates
     */
    public function testSheetsDateSerialVariousDates(): void
    {
        $serial1 = sheets_date_serial(2024, 1, 1);
        $serial2 = sheets_date_serial(2024, 1, 2);

        // Next day should be +1
        $this->assertEquals($serial1 + 1, $serial2);

        // Test leap year
        $serial_feb28 = sheets_date_serial(2024, 2, 28);
        $serial_feb29 = sheets_date_serial(2024, 2, 29);
        $serial_mar01 = sheets_date_serial(2024, 3, 1);

        $this->assertEquals($serial_feb28 + 1, $serial_feb29);
        $this->assertEquals($serial_feb29 + 1, $serial_mar01);
    }

    /**
     * Test date parsing edge cases
     */
    public function testParseDateYMDEdgeCases(): void
    {
        // Whitespace
        $result = parse_date_ymd('  2024-01-15  ');
        $this->assertEquals([2024, 1, 15], $result);

        // Different separators
        $result = parse_date_ymd('2024/01/15');
        $this->assertEquals([2024, 1, 15], $result);
    }
}
