module.exports = {
  // Set the test environment to jsdom for DOM testing
  testEnvironment: 'jsdom',

  // Root directory for tests
  rootDir: '../..',

  // Test file patterns
  testMatch: ['<rootDir>/tests/js/**/*.test.js'],

  // Setup files to run before tests
  setupFilesAfterEnv: ['<rootDir>/tests/js/setup.js'],

  // Module name mapping for mocks
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/js/$1',
  },

  // Coverage configuration
  collectCoverageFrom: [
    'js/**/*.js',
    '!js/**/*.min.js',
    '!**/node_modules/**',
    '!**/tests/**',
  ],

  // Coverage output directory
  coverageDirectory: '<rootDir>/tests/coverage',

  // Coverage reporters
  coverageReporters: ['html', 'text', 'lcov'],

  // Verbose output
  verbose: true,

  // Transform configuration (if needed for ES6+)
  transform: {
    '^.+\\.js$': 'babel-jest',
  },

  // Module directories
  moduleDirectories: ['node_modules', '<rootDir>'],

  // Clear mocks between tests
  clearMocks: true,

  // Restore mocks after each test
  restoreMocks: true,
};
