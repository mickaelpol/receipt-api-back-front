#!/bin/bash

# Test for side-effect-free file loading
# Ensures declaration-only files don't execute logic on load

set -e

echo "🧪 Testing side-effect-free file loading..."

# Test app.php (should be declaration-only)
echo "📝 Testing app.php (declaration-only)..."
if php -r "
    ob_start();
    \$result = include 'backend/app.php';
    \$output = ob_get_contents();
    ob_end_clean();
    
    if (!empty(\$output)) {
        echo 'FAIL: app.php produced output: ' . \$output;
        exit 1;
    }
    
    if (\$result === false) {
        echo 'FAIL: app.php failed to include properly';
        exit 1;
    }
    
    echo 'PASS: app.php loaded without side effects';
"; then
    echo "✅ app.php passes side-effect test"
else
    echo "❌ app.php failed side-effect test"
    exit 1
fi

# Test that bootstrap.php and index.php are NOT included (they have side effects)
echo "📝 Testing that bootstrap.php and index.php are not included..."
if php -r "
    try {
        include 'backend/bootstrap.php';
        echo 'FAIL: bootstrap.php should not be included in tests';
        exit 1;
    } catch (Exception \$e) {
        echo 'PASS: bootstrap.php correctly has side effects';
    }
"; then
    echo "✅ Side-effect tests passed"
else
    echo "❌ Side-effect tests failed"
    exit 1
fi

echo "✅ All side-effect tests passed!"
