// Jest configuration for the WebAuthn / passkey JS helpers shared between
// resources/js/webauthn.js and the inline <script> blocks in passkey-related
// Blade views. jsdom provides the document/window globals used by the cookie
// and base64url helpers.
module.exports = {
    testEnvironment: 'jsdom',
    rootDir: '.',
    testMatch: ['<rootDir>/tests/js/**/*.test.js'],
    setupFiles: ['<rootDir>/tests/js/setup.cjs'],
    transform: {
        '^.+\\.js$': 'babel-jest',
    },
    collectCoverage: true,
    coverageDirectory: 'build/coverage/js',
    collectCoverageFrom: ['resources/js/**/*.js'],
    coverageReporters: ['html', 'clover', 'text-summary'],
};
