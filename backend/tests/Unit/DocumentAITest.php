<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for Document AI functions
 */
class DocumentAITest extends TestCase
{
    /**
     * Test docai_extract_triplet with complete receipt data
     */
    public function testDocaiExtractTripletComplete(): void
    {
        $mockResponse = [
            'document' => [
                'entities' => [
                    [
                        'type' => 'supplier_name',
                        'mentionText' => 'Carrefour',
                        'confidence' => 0.95
                    ],
                    [
                        'type' => 'receipt_date',
                        'mentionText' => '15/01/2024',
                        'normalizedValue' => [
                            'dateValue' => [
                                'year' => 2024,
                                'month' => 1,
                                'day' => 15
                            ]
                        ],
                        'confidence' => 0.98
                    ],
                    [
                        'type' => 'total_amount',
                        'mentionText' => '25.50',
                        'normalizedValue' => [
                            'moneyValue' => [
                                'units' => 25,
                                'nanos' => 500000000,
                                'currencyCode' => 'EUR'
                            ]
                        ],
                        'confidence' => 0.99
                    ]
                ]
            ]
        ];

        $result = docai_extract_triplet($mockResponse);

        $this->assertEquals('Carrefour', $result['supplier_name']);
        $this->assertEquals('2024-01-15', $result['receipt_date']);
        $this->assertEquals(25.5, $result['total_amount']);
    }

    /**
     * Test docai_extract_triplet with alternative entity types
     */
    public function testDocaiExtractTripletAlternativeTypes(): void
    {
        $mockResponse = [
            'document' => [
                'entities' => [
                    [
                        'type' => 'merchant_name',
                        'mentionText' => 'Auchan',
                        'confidence' => 0.90
                    ],
                    [
                        'type' => 'transaction_date',
                        'mentionText' => '2024-02-20',
                        'confidence' => 0.95
                    ],
                    [
                        'type' => 'grand_total',
                        'mentionText' => '42.30',
                        'normalizedValue' => [
                            'moneyValue' => [
                                'units' => 42,
                                'nanos' => 300000000
                            ]
                        ],
                        'confidence' => 0.97
                    ]
                ]
            ]
        ];

        $result = docai_extract_triplet($mockResponse);

        $this->assertEquals('Auchan', $result['supplier_name']);
        $this->assertEquals('2024-02-20', $result['receipt_date']);
        $this->assertEquals(42.3, $result['total_amount']);
    }

    /**
     * Test docai_extract_triplet with subtotal and tax
     */
    public function testDocaiExtractTripletSubtotalPlusTax(): void
    {
        $mockResponse = [
            'document' => [
                'entities' => [
                    [
                        'type' => 'supplier_name',
                        'mentionText' => 'Leclerc'
                    ],
                    [
                        'type' => 'subtotal',
                        'normalizedValue' => [
                            'moneyValue' => [
                                'units' => 100,
                                'nanos' => 0
                            ]
                        ],
                        'confidence' => 0.9
                    ],
                    [
                        'type' => 'total_tax_amount',
                        'normalizedValue' => [
                            'moneyValue' => [
                                'units' => 20,
                                'nanos' => 0
                            ]
                        ],
                        'confidence' => 0.9
                    ]
                ]
            ]
        ];

        $result = docai_extract_triplet($mockResponse);

        $this->assertEquals('Leclerc', $result['supplier_name']);
        $this->assertEquals(120.0, $result['total_amount']); // subtotal + tax
    }

    /**
     * Test docai_extract_triplet with nested properties
     */
    public function testDocaiExtractTripletNestedProperties(): void
    {
        $mockResponse = [
            'document' => [
                'entities' => [
                    [
                        'type' => 'receipt',
                        'properties' => [
                            [
                                'type' => 'supplier_name',
                                'mentionText' => 'Intermarché'
                            ],
                            [
                                'type' => 'total_amount',
                                'normalizedValue' => [
                                    'moneyValue' => [
                                        'units' => 15,
                                        'nanos' => 750000000
                                    ]
                                ],
                                'confidence' => 0.95
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = docai_extract_triplet($mockResponse);

        $this->assertEquals('Intermarché', $result['supplier_name']);
        $this->assertEquals(15.75, $result['total_amount']);
    }

    /**
     * Test docai_extract_triplet with text parsing for amount
     */
    public function testDocaiExtractTripletTextParsing(): void
    {
        $mockResponse = [
            'document' => [
                'entities' => [
                    [
                        'type' => 'total_amount',
                        'mentionText' => 'EUR 33,45',
                        'confidence' => 0.85
                    ]
                ]
            ]
        ];

        $result = docai_extract_triplet($mockResponse);

        $this->assertEquals(33.45, $result['total_amount']);
    }

    /**
     * Test docai_extract_triplet with date regex parsing
     */
    public function testDocaiExtractTripletDateRegex(): void
    {
        $mockResponse = [
            'document' => [
                'entities' => [
                    [
                        'type' => 'receipt_date',
                        'mentionText' => '2024-03-15'
                    ]
                ]
            ]
        ];

        $result = docai_extract_triplet($mockResponse);

        $this->assertEquals('2024-03-15', $result['receipt_date']);
    }

    /**
     * Test docai_extract_triplet with DD/MM/YYYY format
     */
    public function testDocaiExtractTripletDateDDMMYYYY(): void
    {
        $mockResponse = [
            'document' => [
                'entities' => [
                    [
                        'type' => 'receipt_date',
                        'mentionText' => '25/12/2023'
                    ]
                ]
            ]
        ];

        $result = docai_extract_triplet($mockResponse);

        $this->assertEquals('2023-12-25', $result['receipt_date']);
    }

    /**
     * Test docai_extract_triplet with empty response
     */
    public function testDocaiExtractTripletEmpty(): void
    {
        $mockResponse = [
            'document' => [
                'entities' => []
            ]
        ];

        $result = docai_extract_triplet($mockResponse);

        $this->assertNull($result['supplier_name']);
        $this->assertNull($result['receipt_date']);
        $this->assertNull($result['total_amount']);
    }

    /**
     * Test docai_extract_triplet with missing document key
     */
    public function testDocaiExtractTripletMissingDocument(): void
    {
        $mockResponse = [];

        $result = docai_extract_triplet($mockResponse);

        $this->assertNull($result['supplier_name']);
        $this->assertNull($result['receipt_date']);
        $this->assertNull($result['total_amount']);
    }

    /**
     * Test docai_extract_triplet prefers total_amount over grand_total
     */
    public function testDocaiExtractTripletPrecedence(): void
    {
        $mockResponse = [
            'document' => [
                'entities' => [
                    [
                        'type' => 'grand_total',
                        'normalizedValue' => [
                            'moneyValue' => [
                                'units' => 50,
                                'nanos' => 0
                            ]
                        ],
                        'confidence' => 0.9
                    ],
                    [
                        'type' => 'total_amount',
                        'normalizedValue' => [
                            'moneyValue' => [
                                'units' => 60,
                                'nanos' => 0
                            ]
                        ],
                        'confidence' => 0.95
                    ]
                ]
            ]
        ];

        $result = docai_extract_triplet($mockResponse);

        // total_amount should win over grand_total
        $this->assertEquals(60.0, $result['total_amount']);
    }

    /**
     * Test cleanupDocAiCache removes old files
     */
    public function testCleanupDocAiCacheRemovesOldFiles(): void
    {
        $tempDir = sys_get_temp_dir();

        // Create test cache files
        $oldFile = $tempDir . '/docai_cache_test_old.json';
        $recentFile = $tempDir . '/docai_cache_test_recent.json';

        file_put_contents($oldFile, '{"test": "old"}');
        file_put_contents($recentFile, '{"test": "recent"}');

        // Set old file modification time to 2 days ago
        touch($oldFile, time() - (2 * 86400));

        // Cleanup files older than 1 day
        $cleaned = cleanupDocAiCache(86400);

        $this->assertGreaterThanOrEqual(1, $cleaned);
        $this->assertFileDoesNotExist($oldFile);
        $this->assertFileExists($recentFile);

        // Cleanup
        if (file_exists($recentFile)) {
            unlink($recentFile);
        }
    }

    /**
     * Test cleanupDocAiCache with no files
     */
    public function testCleanupDocAiCacheNoFiles(): void
    {
        // Clean up any existing cache files first
        $cacheFiles = glob(sys_get_temp_dir() . '/docai_cache_*.json');
        if ($cacheFiles) {
            foreach ($cacheFiles as $file) {
                unlink($file);
            }
        }

        $cleaned = cleanupDocAiCache();

        $this->assertEquals(0, $cleaned);
    }

    /**
     * Test cleanupDocAiCache keeps recent files
     */
    public function testCleanupDocAiCacheKeepsRecentFiles(): void
    {
        $tempDir = sys_get_temp_dir();
        $recentFile = $tempDir . '/docai_cache_test_recent2.json';

        file_put_contents($recentFile, '{"test": "recent"}');
        // File is just created, so it's recent

        $cleaned = cleanupDocAiCache(86400); // 1 day

        $this->assertEquals(0, $cleaned); // Should not clean recent files
        $this->assertFileExists($recentFile);

        // Cleanup
        unlink($recentFile);
    }
}
