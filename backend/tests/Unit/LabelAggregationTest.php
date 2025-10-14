<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for label aggregation functions (US1-US9)
 */
class LabelAggregationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Include app.php to get all function definitions
        require_once __DIR__ . '/../../bootstrap.php';
    }

    /**
     * US2: Test label normalization
     */
    public function testNormalizeLabelBasic()
    {
        $this->assertEquals('lidl', normalizeLabel('Lidl'));
        $this->assertEquals('lidl', normalizeLabel('LIDL'));
        $this->assertEquals('lidl', normalizeLabel('lidl'));
    }

    public function testNormalizeLabelWithAccents()
    {
        $this->assertEquals('cafe', normalizeLabel('Café'));
        $this->assertEquals('ete', normalizeLabel('Été'));
        $this->assertEquals('hopital', normalizeLabel('Hôpital'));
    }

    public function testNormalizeLabelWithPunctuation()
    {
        $this->assertEquals('lidl', normalizeLabel('Lidl!'));
        $this->assertEquals('lidl', normalizeLabel('Lidl.'));
        $this->assertEquals('lidl', normalizeLabel('Lidl,'));
        $this->assertEquals('lidl', normalizeLabel('Lidl?'));
        $this->assertEquals('superu', normalizeLabel('Super-U')); // Hyphen removed, no space
    }

    public function testNormalizeLabelWithMultipleSpaces()
    {
        $this->assertEquals('super marche', normalizeLabel('Super  Marché'));
        $this->assertEquals('super marche', normalizeLabel('Super   Marché'));
        $this->assertEquals('super marche', normalizeLabel('  Super  Marché  '));
    }

    public function testNormalizeLabelComplex()
    {
        $this->assertEquals('carrefour', normalizeLabel('  Carrefour!!!  '));
        $this->assertEquals('eleclerc', normalizeLabel('E.Leclerc')); // Period removed, no space
        $this->assertEquals('auchan', normalizeLabel('AUCHAN...'));
    }

    /**
     * US7: Test amount validation and formatting
     */
    public function testValidateAndFormatAmountValid()
    {
        $this->assertEquals('12,50', validateAndFormatAmount(12.5));
        $this->assertEquals('0,00', validateAndFormatAmount(0.0));
        $this->assertEquals('100,00', validateAndFormatAmount(100.0));
        $this->assertEquals('54,60', validateAndFormatAmount(54.6));
        $this->assertEquals('9,99', validateAndFormatAmount(9.99));
    }

    public function testValidateAndFormatAmountWithRounding()
    {
        $this->assertEquals('12,35', validateAndFormatAmount(12.345));
        $this->assertEquals('12,35', validateAndFormatAmount(12.346));
    }

    public function testValidateAndFormatAmountNegative()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Amount must be non-negative');
        validateAndFormatAmount(-10.0);
    }

    /**
     * Test that normalizeLabel handles edge cases
     */
    public function testNormalizeLabelEdgeCases()
    {
        $this->assertEquals('', normalizeLabel(''));
        $this->assertEquals('', normalizeLabel('   '));
        $this->assertEquals('', normalizeLabel('!!!'));
        $this->assertEquals('123', normalizeLabel('123'));
        $this->assertEquals('abc123', normalizeLabel('abc123'));
    }

    /**
     * Test that validateAndFormatAmount handles edge cases
     */
    public function testValidateAndFormatAmountEdgeCases()
    {
        $this->assertEquals('0,00', validateAndFormatAmount(0.0));
        $this->assertEquals('0,01', validateAndFormatAmount(0.01));
        $this->assertEquals('0,10', validateAndFormatAmount(0.1));
        $this->assertEquals('1000,00', validateAndFormatAmount(1000.0));
        $this->assertEquals('1234,56', validateAndFormatAmount(1234.56));
    }

    /**
     * Test label normalization with international characters
     */
    public function testNormalizeLabelInternational()
    {
        $this->assertEquals('naif', normalizeLabel('Naïf'));
        $this->assertEquals('angstrom', normalizeLabel('Ångström'));
        $this->assertEquals('senor', normalizeLabel('Señor'));
    }

    /**
     * Test that normalizeLabel is idempotent
     */
    public function testNormalizeLabelIdempotent()
    {
        $label = 'Lidl';
        $normalized1 = normalizeLabel($label);
        $normalized2 = normalizeLabel($normalized1);
        $this->assertEquals($normalized1, $normalized2);
    }

    /**
     * Test amount formatting with various decimal values
     */
    public function testValidateAndFormatAmountVariousDecimals()
    {
        $this->assertEquals('12,00', validateAndFormatAmount(12.0));
        $this->assertEquals('12,10', validateAndFormatAmount(12.1));
        $this->assertEquals('12,12', validateAndFormatAmount(12.12));
        $this->assertEquals('12,99', validateAndFormatAmount(12.99));
    }
}
