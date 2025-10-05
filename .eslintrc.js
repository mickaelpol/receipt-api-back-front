module.exports = {
    languageOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
        globals: {
            // Browser globals
            window: 'readonly',
            document: 'readonly',
            console: 'readonly',
            fetch: 'readonly',
            URL: 'readonly',
            localStorage: 'readonly',
            sessionStorage: 'readonly',
            location: 'readonly',
            requestAnimationFrame: 'readonly',
            setTimeout: 'readonly',
            clearTimeout: 'readonly',
            FileReader: 'readonly',
            Image: 'readonly',
            ResizeObserver: 'readonly',
            // Google APIs
            google: 'readonly',
            gapi: 'readonly',
        },
    },
    rules: {
        // Disable debug statements
        'no-console': 'error',
        'no-debugger': 'error',

        // Code quality
        'no-unused-vars': 'warn',
        'no-undef': 'error',
        'no-var': 'error',
        'prefer-const': 'error',

        // Style
        'indent': ['error', 4],
        'quotes': ['error', 'single'],
        'semi': ['error', 'always'],
        'comma-dangle': ['error', 'never'],
        'object-curly-spacing': ['error', 'always'],
        'array-bracket-spacing': ['error', 'never'],
    },
};
