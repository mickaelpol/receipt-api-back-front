#!/bin/bash

# Pre-commit hook for Receipt API
# Ensures code quality before commits

set -e

echo "🔍 Pre-commit quality checks..."

# Check for debug statements
echo "📝 Checking for debug statements..."
if grep -r "console\.log\|var_dump\|print_r\|var_export" frontend/ backend/ --exclude-dir=vendor --exclude-dir=node_modules; then
    echo "❌ Debug statements found! Please remove them before committing."
    exit 1
fi
echo "✅ No debug statements found"

# Check for sensitive data exposure
echo "🔒 Checking for sensitive data exposure..."
if grep -r "Bearer\|token\|password\|secret" backend/ --exclude-dir=vendor --exclude-dir=keys; then
    echo "⚠️  Potential sensitive data found. Please review and mask if necessary."
fi

# Run linting
echo "🔧 Running code linting..."
make lint

echo "✅ All pre-commit checks passed!"
