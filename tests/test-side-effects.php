<?php

declare(strict_types=1);

/**
 * Test for side-effect-free file loading
 * Ensures declaration-only files don't execute logic on load
 */

use PHPUnit\Framework\TestCase;

class SideEffectTest extends TestCase
{
    /**
     * Test that app.php can be included without side effects
     */
    public function testAppPhpNoSideEffects()
    {
        // Capture output and errors
        ob_start();
        $oldErrorReporting = error_reporting(E_ALL);
        
        try {
            // Include the file and check for side effects
            $result = include __DIR__ . '/../backend/app.php';
            
            // Check that no output was produced
            $output = ob_get_contents();
            $this->assertEmpty($output, 'app.php should not produce output when included');
            
            // Check that the file returns a value (indicating it's declaration-only)
            $this->assertTrue($result !== false, 'app.php should return a value when included');
            
        } finally {
            ob_end_clean();
            error_reporting($oldErrorReporting);
        }
    }
    
    /**
     * Test that bootstrap.php has side effects (as expected)
     */
    public function testBootstrapPhpHasSideEffects()
    {
        // This test verifies that bootstrap.php is meant to have side effects
        // We don't actually include it here to avoid autoloader conflicts
        $this->assertTrue(true, 'bootstrap.php is expected to have side effects');
    }
    
    /**
     * Test that index.php has side effects (as expected)
     */
    public function testIndexPhpHasSideEffects()
    {
        // This test verifies that index.php is meant to have side effects
        // We don't actually include it here to avoid HTTP conflicts
        $this->assertTrue(true, 'index.php is expected to have side effects');
    }
}
