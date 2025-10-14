<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for Document AI related functions
 */
class DocAITest extends TestCase
{
    /**
     * Test docai_extract_triplet with valid data
     */
    public function testDocAIExtractTripletBasic(): void
    {
        $mockDoc = [
            'document' => [
                'entities' => [
                    [
                        'type' => 'supplier_name',
                        'mentionText' => 'CARREFOUR',
                        'confidence' => 0.95
                    ],
                    [
                        'type' => 'receipt_date',
                        'mentionText' => '2024-01-15',
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
                        'mentionText' => '42.50',
                        'normalizedValue' => [
                            'moneyValue' => [
                                'units' => 42,
                                'nanos' => 500000000,
                                'currencyCode' => 'EUR'
                            ]
                        ],
                        'confidence' => 0.99
                    ]
                ]
            ]
        ];

        $result = docai_extract_triplet($mockDoc);

        $this->assertEquals('CARREFOUR', $result['supplier_name']);
        $this->assertEquals('2024-01-15', $result['receipt_date']);
        $this->assertEquals(42.5, $result['total_amount']);
    }

    /**
     * Test docai_extract_triplet with fallback to text parsing
     */
    public function testDocAIExtractTripletTextFallback(): void
    {
        $mockDoc = [
            'document' => [
                'entities' => [
                    [
                        'type' => 'total_amount',
                        'mentionText' => '€ 42,50',
                        'confidence' => 0.85
                    ]
                ]
            ]
        ];

        $result = docai_extract_triplet($mockDoc);

        // Should parse text amount
        $this->assertEquals(42.5, $result['total_amount']);
    }

    /**
     * Test docai_extract_triplet with subtotal + tax
     */
    public function testDocAIExtractTripletSubtotalTax(): void
    {
        $mockDoc = [
            'document' => [
                'entities' => [
                    [
                        'type' => 'subtotal',
                        'mentionText' => '40.00',
                        'normalizedValue' => [
                            'moneyValue' => [
                                'units' => 40,
                                'nanos' => 0
                            ]
                        ],
                        'confidence' => 0.95
                    ],
                    [
                        'type' => 'total_tax_amount',
                        'mentionText' => '2.50',
                        'normalizedValue' => [
                            'moneyValue' => [
                                'units' => 2,
                                'nanos' => 500000000
                            ]
                        ],
                        'confidence' => 0.90
                    ]
                ]
            ]
        ];

        $result = docai_extract_triplet($mockDoc);

        // Should sum subtotal + tax
        $this->assertEquals(42.5, $result['total_amount']);
    }

    /**
     * Test docai_extract_triplet with missing entities
     */
    public function testDocAIExtractTripletMissingEntities(): void
    {
        $mockDoc = [
            'document' => [
                'entities' => []
            ]
        ];

        $result = docai_extract_triplet($mockDoc);

        $this->assertNull($result['supplier_name']);
        $this->assertNull($result['receipt_date']);
        $this->assertNull($result['total_amount']);
    }

    /**
     * Test docai_extract_triplet with nested properties
     */
    public function testDocAIExtractTripletNestedProperties(): void
    {
        $mockDoc = [
            'document' => [
                'entities' => [
                    [
                        'type' => 'line_item',
                        'properties' => [
                            [
                                'type' => 'total_amount',
                                'mentionText' => '42.50',
                                'normalizedValue' => [
                                    'moneyValue' => [
                                        'units' => 42,
                                        'nanos' => 500000000
                                    ]
                                ],
                                'confidence' => 0.95
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = docai_extract_triplet($mockDoc);

        // Should extract from nested properties
        $this->assertEquals(42.5, $result['total_amount']);
    }

    /**
     * Test docai_extract_triplet prefers total_amount over grand_total
     */
    public function testDocAIExtractTripletPrefersTotalAmount(): void
    {
        $mockDoc = [
            'document' => [
                'entities' => [
                    [
                        'type' => 'total_amount',
                        'normalizedValue' => [
                            'moneyValue' => [
                                'units' => 42,
                                'nanos' => 0
                            ]
                        ],
                        'confidence' => 0.95
                    ],
                    [
                        'type' => 'grand_total',
                        'normalizedValue' => [
                            'moneyValue' => [
                                'units' => 40,
                                'nanos' => 0
                            ]
                        ],
                        'confidence' => 0.99 // Higher confidence but lower priority
                    ]
                ]
            ]
        ];

        $result = docai_extract_triplet($mockDoc);

        // Should prefer total_amount even with lower confidence
        $this->assertEquals(42.0, $result['total_amount']);
    }

    /**
     * Test date parsing from text when normalizedValue is missing
     */
    public function testDocAIExtractTripletDateTextParsing(): void
    {
        $mockDoc = [
            'document' => [
                'entities' => [
                    [
                        'type' => 'receipt_date',
                        'mentionText' => '15/01/2024',
                        'confidence' => 0.90
                    ]
                ]
            ]
        ];

        $result = docai_extract_triplet($mockDoc);

        $this->assertEquals('2024-01-15', $result['receipt_date']);
    }
}
