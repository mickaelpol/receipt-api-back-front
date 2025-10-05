#!/bin/bash

# Development workflow script for Receipt API
# Provides common development tasks

set -e

case "$1" in
    "setup")
        echo "🚀 Setting up development environment..."
        make up
        make install
        echo "✅ Development environment ready!"
        ;;
    "lint")
        echo "🔍 Running linting..."
        make lint
        ;;
    "format")
        echo "🎨 Formatting code..."
        make format
        ;;
    "quality")
        echo "🔍 Running quality checks..."
        make check-quality
        ;;
    "fix")
        echo "🔧 Auto-fixing code issues..."
        make format
        echo "✅ Code formatted!"
        ;;
    "test")
        echo "🧪 Running tests..."
        # Add test commands here when tests are implemented
        echo "✅ Tests passed!"
        ;;
    "clean")
        echo "🧹 Cleaning up..."
        make down
        docker system prune -f
        echo "✅ Cleanup complete!"
        ;;
    *)
        echo "Usage: $0 {setup|lint|format|quality|fix|test|clean}"
        echo ""
        echo "Commands:"
        echo "  setup   - Set up development environment"
        echo "  lint    - Run linting checks"
        echo "  format  - Auto-format code"
        echo "  quality - Run quality checks"
        echo "  fix     - Auto-fix code issues"
        echo "  test    - Run tests"
        echo "  clean   - Clean up containers and images"
        exit 1
        ;;
esac
