/**
 * @file
 * Jest setup file for UtiliKit tests
 */

// Mock DOM APIs
if (typeof document !== 'undefined') {
  document.body.innerHTML = '';
}

// Mock window object
Object.defineProperty(global, 'innerWidth', {
  writable: true,
  configurable: true,
  value: 1024
});

// Ensure window exists
if (typeof window !== 'undefined') {
  Object.defineProperty(window, 'innerWidth', {
    writable: true,
    configurable: true,
    value: 1024
  });
}

// Mock console methods to reduce noise during tests
global.console = {
  ...console,
  log: jest.fn(),
  warn: jest.fn(),
  error: jest.fn()
};

// Mock requestAnimationFrame
global.requestAnimationFrame = (cb) => setTimeout(cb, 0);
global.cancelAnimationFrame = (id) => clearTimeout(id);

// Initialize global Drupal and drupalSettings objects
global.Drupal = global.Drupal || {};
global.drupalSettings = global.drupalSettings || {};
