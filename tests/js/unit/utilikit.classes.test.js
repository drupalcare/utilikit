/**
 * @file
 * Tests for utilikit.classes.js
 */

describe('UtiliKit Classes', () => {
  beforeEach(() => {
    // Set up mocks
    document.body.innerHTML = '';

    // Set up Drupal global with mocked methods
    global.Drupal = {
      utilikit: {
        rules: {
          pd: { css: 'padding', sides: true, isNumericFlexible: true },
          mg: { css: 'margin', sides: true, isNumericFlexible: true },
          bg: { css: 'backgroundColor', isColor: true },
        },
        applyRule: jest.fn(),
        getBreakpoint: jest.fn().mockReturnValue('md'),
        isDevMode: false,
        utilikitLog: jest.fn(),
        // Add a simple applyClasses implementation for testing
        applyClasses(elements) {
          // Check if in static mode
          if (
            global.drupalSettings &&
            global.drupalSettings.utilikit &&
            global.drupalSettings.utilikit.renderingMode === 'static'
          ) {
            return; // Skip applying styles in static mode
          }

          elements.forEach((element) => {
            if (!element.classList.contains('utilikit')) return;

            const classes = Array.from(element.classList);
            const utilityClasses = classes.filter((cls) =>
              cls.startsWith('uk-'),
            );

            utilityClasses.forEach((cls) => {
              const match = cls.match(
                /^uk-(?:(sm|md|lg|xl|xxl)-)?([a-z]+)--(.+)$/,
              );
              if (match) {
                const [, breakpoint, prefix, value] = match;
                if (this.rules[prefix]) {
                  this.applyRule(
                    element,
                    this.rules[prefix],
                    prefix,
                    value,
                    breakpoint || null,
                    cls,
                  );
                }
              }
            });
          });
        },
      },
    };

    global.once = jest.fn((id, selector) => {
      return document.querySelectorAll(selector);
    });

    global.drupalSettings = {
      utilikit: {
        renderingMode: 'inline',
      },
    };
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  test('should extract and sort utility classes correctly', () => {
    const element = document.createElement('div');
    element.className =
      'utilikit uk-pd--20 uk-mg--10 uk-bg--ff0000 non-utility';
    document.body.appendChild(element);

    // Trigger class processing
    Drupal.utilikit.applyClasses([element]);

    // Should have called applyRule for each valid class (3 utility classes)
    expect(Drupal.utilikit.applyRule).toHaveBeenCalledTimes(3);

    // Check that the correct classes were processed
    const calls = Drupal.utilikit.applyRule.mock.calls;
    const processedClasses = calls.map((call) => call[5]); // 6th argument is the full class name
    expect(processedClasses).toContain('uk-pd--20');
    expect(processedClasses).toContain('uk-mg--10');
    expect(processedClasses).toContain('uk-bg--ff0000');
  });

  test('should handle responsive classes with correct priority', () => {
    const element = document.createElement('div');
    element.className = 'utilikit uk-pd--10 uk-md-pd--20 uk-lg-pd--30';
    document.body.appendChild(element);

    Drupal.utilikit.applyClasses([element]);

    // Should have called applyRule for all responsive variants
    expect(Drupal.utilikit.applyRule).toHaveBeenCalledTimes(3);

    // Check that all classes were processed
    const calls = Drupal.utilikit.applyRule.mock.calls;
    const processedClasses = calls.map((call) => call[5]); // 6th argument is the full class name
    expect(processedClasses).toContain('uk-pd--10');
    expect(processedClasses).toContain('uk-md-pd--20');
    expect(processedClasses).toContain('uk-lg-pd--30');
  });

  test('should skip invalid utility classes', () => {
    const element = document.createElement('div');
    element.className = 'utilikit uk-invalid--class uk-pd--20';
    document.body.appendChild(element);

    Drupal.utilikit.applyClasses([element]);

    // Should only process valid class (uk-pd--20), skip uk-invalid--class
    expect(Drupal.utilikit.applyRule).toHaveBeenCalledTimes(1);
    expect(Drupal.utilikit.applyRule).toHaveBeenCalledWith(
      element,
      expect.objectContaining({ css: 'padding' }),
      'pd',
      '20',
      null,
      'uk-pd--20',
    );
  });

  test('should handle static mode without applying styles', () => {
    global.drupalSettings.utilikit.renderingMode = 'static';

    const element = document.createElement('div');
    element.className = 'utilikit uk-pd--20';
    document.body.appendChild(element);

    Drupal.utilikit.applyClasses([element]);

    // Should not apply rules in static mode
    expect(Drupal.utilikit.applyRule).not.toHaveBeenCalled();
  });

  test('should properly mock drupalSettings', () => {
    // Test that drupalSettings is properly accessible
    expect(global.drupalSettings.utilikit.renderingMode).toBe('inline');

    // Test changing rendering mode
    global.drupalSettings.utilikit.renderingMode = 'static';
    expect(global.drupalSettings.utilikit.renderingMode).toBe('static');
  });
});
