/**
 * @file
 * Tests for utilikit.rules.js
 */

describe('UtiliKit Rules', () => {
  beforeEach(() => {
    // Set up Drupal global with complete rules and breakpoints
    global.Drupal = {
      utilikit: {
        // Mock the complete rules object based on your actual rules
        rules: {
          // Box Model
          pd: { css: 'padding', sides: true, isNumericFlexible: true },
          mg: { css: 'margin', sides: true, isNumericFlexible: true },
          bw: { css: 'borderWidth', sides: true, isNumericFlexible: true },
          br: { css: 'borderRadius', sides: true, isNumericFlexible: true },

          // Sizing
          wd: { css: 'width', isNumericFlexible: true },
          ht: { css: 'height', isNumericFlexible: true },

          // Typography
          fs: { css: 'fontSize', isNumericFlexible: true },
          fw: { css: 'fontWeight', isInteger: true },

          // Colors
          bg: { css: 'backgroundColor', isColor: true },
          tc: { css: 'color', isColor: true },

          // Layout
          dp: { css: 'display', isKeyword: true },
          ps: { css: 'position', isKeyword: true },

          // Transform
          rt: { css: 'transform', isTransform: 'rotate' },
          sc: { css: 'transform', isTransform: 'scale' }
        },

        // Mock breakpoints based on standard values
        breakpoints: {
          sm: 576,
          md: 768,
          lg: 992,
          xl: 1200,
          xxl: 1400
        }
      }
    };
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  test('should have all required rule categories', () => {
    const rules = Drupal.utilikit.rules;

    // Box Model
    expect(rules.pd).toBeDefined();
    expect(rules.mg).toBeDefined();
    expect(rules.bw).toBeDefined();
    expect(rules.br).toBeDefined();

    // Sizing
    expect(rules.wd).toBeDefined();
    expect(rules.ht).toBeDefined();

    // Typography
    expect(rules.fs).toBeDefined();
    expect(rules.fw).toBeDefined();

    // Colors
    expect(rules.bg).toBeDefined();
    expect(rules.tc).toBeDefined();

    // Layout
    expect(rules.dp).toBeDefined();
    expect(rules.ps).toBeDefined();
  });

  test('should have correct rule properties', () => {
    const paddingRule = Drupal.utilikit.rules.pd;

    expect(paddingRule).toEqual({
      css: 'padding',
      sides: true,
      isNumericFlexible: true
    });
  });

  test('should have correct margin rule with allowAuto', () => {
    const marginRule = Drupal.utilikit.rules.mg;

    expect(marginRule.css).toBe('margin');
    expect(marginRule.sides).toBe(true);
    expect(marginRule.isNumericFlexible).toBe(true);
  });

  test('should define transform rules correctly', () => {
    const rotateRule = Drupal.utilikit.rules.rt;
    const scaleRule = Drupal.utilikit.rules.sc;

    expect(rotateRule.isTransform).toBe('rotate');
    expect(scaleRule.isTransform).toBe('scale');
    expect(rotateRule.css).toBe('transform');
    expect(scaleRule.css).toBe('transform');
  });

  test('should define keyword rules correctly', () => {
    const displayRule = Drupal.utilikit.rules.dp;
    const positionRule = Drupal.utilikit.rules.ps;

    expect(displayRule.isKeyword).toBe(true);
    expect(positionRule.isKeyword).toBe(true);
    expect(displayRule.css).toBe('display');
    expect(positionRule.css).toBe('position');
  });

  test('should define color rules correctly', () => {
    const backgroundRule = Drupal.utilikit.rules.bg;
    const colorRule = Drupal.utilikit.rules.tc;

    expect(backgroundRule.isColor).toBe(true);
    expect(colorRule.isColor).toBe(true);
    expect(backgroundRule.css).toBe('backgroundColor');
    expect(colorRule.css).toBe('color');
  });

  test('should define sizing rules correctly', () => {
    const widthRule = Drupal.utilikit.rules.wd;
    const heightRule = Drupal.utilikit.rules.ht;

    expect(widthRule.isNumericFlexible).toBe(true);
    expect(heightRule.isNumericFlexible).toBe(true);
    expect(widthRule.css).toBe('width');
    expect(heightRule.css).toBe('height');
  });

  test('should define typography rules correctly', () => {
    const fontSizeRule = Drupal.utilikit.rules.fs;
    const fontWeightRule = Drupal.utilikit.rules.fw;

    expect(fontSizeRule.isNumericFlexible).toBe(true);
    expect(fontWeightRule.isInteger).toBe(true);
    expect(fontSizeRule.css).toBe('fontSize');
    expect(fontWeightRule.css).toBe('fontWeight');
  });

  test('should define border rules correctly', () => {
    const borderWidthRule = Drupal.utilikit.rules.bw;
    const borderRadiusRule = Drupal.utilikit.rules.br;

    expect(borderWidthRule.sides).toBe(true);
    expect(borderRadiusRule.sides).toBe(true);
    expect(borderWidthRule.isNumericFlexible).toBe(true);
    expect(borderRadiusRule.isNumericFlexible).toBe(true);
  });

  test('should have all breakpoints defined', () => {
    const breakpoints = Drupal.utilikit.breakpoints;

    expect(breakpoints).toEqual({
      sm: 576,
      md: 768,
      lg: 992,
      xl: 1200,
      xxl: 1400
    });
  });

  test('should have valid breakpoint values', () => {
    const breakpoints = Drupal.utilikit.breakpoints;

    // All breakpoints should be numbers
    Object.values(breakpoints).forEach(value => {
      expect(typeof value).toBe('number');
      expect(value).toBeGreaterThan(0);
    });

    // Breakpoints should be in ascending order
    expect(breakpoints.sm).toBeLessThan(breakpoints.md);
    expect(breakpoints.md).toBeLessThan(breakpoints.lg);
    expect(breakpoints.lg).toBeLessThan(breakpoints.xl);
    expect(breakpoints.xl).toBeLessThan(breakpoints.xxl);
  });

  test('should distinguish between different rule types', () => {
    const rules = Drupal.utilikit.rules;

    // Numeric flexible rules
    const numericRules = Object.keys(rules).filter(key => rules[key].isNumericFlexible);
    expect(numericRules).toContain('pd');
    expect(numericRules).toContain('mg');
    expect(numericRules).toContain('wd');
    expect(numericRules).toContain('ht');

    // Color rules
    const colorRules = Object.keys(rules).filter(key => rules[key].isColor);
    expect(colorRules).toContain('bg');
    expect(colorRules).toContain('tc');

    // Keyword rules
    const keywordRules = Object.keys(rules).filter(key => rules[key].isKeyword);
    expect(keywordRules).toContain('dp');
    expect(keywordRules).toContain('ps');

    // Transform rules
    const transformRules = Object.keys(rules).filter(key => rules[key].isTransform);
    expect(transformRules).toContain('rt');
    expect(transformRules).toContain('sc');
  });

  test('should validate rule structure', () => {
    const rules = Drupal.utilikit.rules;

    Object.keys(rules).forEach(prefix => {
      const rule = rules[prefix];

      // Each rule should have a css property
      expect(rule).toHaveProperty('css');
      expect(typeof rule.css).toBe('string');

      // Each rule should have at least one type flag
      const typeFlags = ['isNumericFlexible', 'isColor', 'isKeyword', 'isTransform', 'isInteger'];
      const hasTypeFlag = typeFlags.some(flag => rule[flag]);
      expect(hasTypeFlag).toBe(true);
    });
  });

  test('should properly identify sides-based rules', () => {
    const rules = Drupal.utilikit.rules;
    const sidesRules = Object.keys(rules).filter(key => rules[key].sides);

    expect(sidesRules).toContain('pd'); // padding
    expect(sidesRules).toContain('mg'); // margin
    expect(sidesRules).toContain('bw'); // border-width
    expect(sidesRules).toContain('br'); // border-radius

    // Non-sides rules shouldn't have sides property
    expect(rules.wd.sides).toBeUndefined();
    expect(rules.ht.sides).toBeUndefined();
    expect(rules.dp.sides).toBeUndefined();
  });
});
