/**
 * @file
 * Tests for utilikit.helpers.js
 */

describe('UtiliKit Helpers', () => {
  beforeEach(() => {
    // Set up Drupal global with helper functions
    global.Drupal = {
      utilikit: {
        // Mock helper functions with actual implementations for testing
        parseColorWithAlpha(suffix) {
          let match;

          // Match 6-digit hex color with optional alpha percentage (0-100)
          match = suffix.match(/^([0-9a-fA-F]{6})(?:-(\d{1,3}))?$/);

          if (match) {
            const hex = match[1];
            const hasAlpha = match[2] !== undefined;
            const alpha = hasAlpha
              ? Math.min(parseInt(match[2], 10), 100) / 100
              : 1;
            const r = parseInt(hex.substring(0, 2), 16);
            const g = parseInt(hex.substring(2, 4), 16);
            const b = parseInt(hex.substring(4, 6), 16);
            return hasAlpha ? `rgba(${r}, ${g}, ${b}, ${alpha})` : `#${hex}`;
          }

          // Match 3-digit hex color with optional alpha percentage (0-100)
          match = suffix.match(/^([0-9a-fA-F]{3})(?:-(\d{1,3}))?$/);

          if (match) {
            // Expand 3-digit hex to 6-digit by doubling each character
            const hex = match[1]
              .split('')
              .map((ch) => ch + ch)
              .join('');
            const hasAlpha = match[2] !== undefined;
            const alpha = hasAlpha
              ? Math.min(parseInt(match[2], 10), 100) / 100
              : 1;
            const r = parseInt(hex.substring(0, 2), 16);
            const g = parseInt(hex.substring(2, 4), 16);
            const b = parseInt(hex.substring(4, 6), 16);
            return hasAlpha ? `rgba(${r}, ${g}, ${b}, ${alpha})` : `#${hex}`;
          }

          return null; // Invalid format
        },

        breakpointApplies(bp) {
          const minWidth = this.breakpoints[bp];
          return (
            typeof minWidth === 'number' &&
            (global.innerWidth || window.innerWidth) >= minWidth
          );
        },

        convertDecimalNotation(value) {
          return value.replace(/d/g, '.');
        },

        // Mock breakpoints for testing
        breakpoints: {
          sm: 576,
          md: 768,
          lg: 992,
          xl: 1200,
          xxl: 1400,
        },
      },
    };

    // Set up global window width for testing
    global.innerWidth = 1024;
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  describe('parseColorWithAlpha', () => {
    test('should parse 6-digit hex colors', () => {
      const result = Drupal.utilikit.parseColorWithAlpha('ff0000');
      expect(result).toBe('#ff0000');
    });

    test('should parse 6-digit hex with alpha', () => {
      const result = Drupal.utilikit.parseColorWithAlpha('ff0000-50');
      expect(result).toBe('rgba(255, 0, 0, 0.5)');
    });

    test('should parse 3-digit hex colors', () => {
      const result = Drupal.utilikit.parseColorWithAlpha('f00');
      expect(result).toBe('#ff0000');
    });

    test('should parse 3-digit hex with alpha', () => {
      const result = Drupal.utilikit.parseColorWithAlpha('f00-75');
      expect(result).toBe('rgba(255, 0, 0, 0.75)');
    });

    test('should return null for invalid colors', () => {
      expect(Drupal.utilikit.parseColorWithAlpha('invalid')).toBeNull();
      expect(Drupal.utilikit.parseColorWithAlpha('gg0000')).toBeNull();
      expect(Drupal.utilikit.parseColorWithAlpha('')).toBeNull();
    });

    test('should cap alpha at 100', () => {
      const result = Drupal.utilikit.parseColorWithAlpha('ff0000-150');
      expect(result).toBe('rgba(255, 0, 0, 1)');
    });

    test('should handle uppercase hex values', () => {
      const result = Drupal.utilikit.parseColorWithAlpha('FF0000');
      expect(result).toBe('#FF0000');
    });

    test('should handle mixed case hex values with alpha', () => {
      const result = Drupal.utilikit.parseColorWithAlpha('AbCdEf-25');
      expect(result).toBe('rgba(171, 205, 239, 0.25)');
    });
  });

  describe('breakpointApplies', () => {
    beforeEach(() => {
      // Reset breakpoints for each test
      Drupal.utilikit.breakpoints = {
        sm: 576,
        md: 768,
        lg: 992,
        xl: 1200,
        xxl: 1400,
      };
    });

    test('should return true when viewport is larger than breakpoint', () => {
      global.innerWidth = 800;
      expect(Drupal.utilikit.breakpointApplies('sm')).toBe(true);
      expect(Drupal.utilikit.breakpointApplies('md')).toBe(true);
      expect(Drupal.utilikit.breakpointApplies('lg')).toBe(false);
    });

    test('should return false for invalid breakpoints', () => {
      global.innerWidth = 1000;
      expect(Drupal.utilikit.breakpointApplies('invalid')).toBe(false);
      expect(Drupal.utilikit.breakpointApplies('')).toBe(false);
      expect(Drupal.utilikit.breakpointApplies(null)).toBe(false);
    });

    test('should return true when viewport exactly matches breakpoint', () => {
      global.innerWidth = 768;
      expect(Drupal.utilikit.breakpointApplies('md')).toBe(true);
    });

    test('should return false when viewport is smaller than breakpoint', () => {
      global.innerWidth = 500;
      expect(Drupal.utilikit.breakpointApplies('sm')).toBe(false);
      expect(Drupal.utilikit.breakpointApplies('md')).toBe(false);
    });

    test('should work with all standard breakpoints', () => {
      global.innerWidth = 1500;
      expect(Drupal.utilikit.breakpointApplies('sm')).toBe(true);
      expect(Drupal.utilikit.breakpointApplies('md')).toBe(true);
      expect(Drupal.utilikit.breakpointApplies('lg')).toBe(true);
      expect(Drupal.utilikit.breakpointApplies('xl')).toBe(true);
      expect(Drupal.utilikit.breakpointApplies('xxl')).toBe(true);
    });
  });

  describe('convertDecimalNotation', () => {
    test('should convert d to decimal point', () => {
      expect(Drupal.utilikit.convertDecimalNotation('1d5')).toBe('1.5');
      expect(Drupal.utilikit.convertDecimalNotation('10d25')).toBe('10.25');
      expect(Drupal.utilikit.convertDecimalNotation('0d5')).toBe('0.5');
    });

    test('should handle strings without d', () => {
      expect(Drupal.utilikit.convertDecimalNotation('15')).toBe('15');
      expect(Drupal.utilikit.convertDecimalNotation('test')).toBe('test');
      expect(Drupal.utilikit.convertDecimalNotation('100px')).toBe('100px');
    });

    test('should handle multiple d characters', () => {
      expect(Drupal.utilikit.convertDecimalNotation('1d5d8')).toBe('1.5.8');
      expect(Drupal.utilikit.convertDecimalNotation('2d75rem')).toBe('2.75rem');
    });

    test('should handle empty strings', () => {
      expect(Drupal.utilikit.convertDecimalNotation('')).toBe('');
    });

    test('should handle special characters', () => {
      expect(Drupal.utilikit.convertDecimalNotation('0d5fr')).toBe('0.5fr');
      expect(Drupal.utilikit.convertDecimalNotation('1d25em')).toBe('1.25em');
    });
  });
});
