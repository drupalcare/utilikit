<?php

declare(strict_types=1);

namespace Drupal\utilikit_test\Service;

use Drupal\utilikit\Service\UtilikitRules;

/**
 * Generates comprehensive test cases for UtiliKit utility class validation.
 *
 * This service provides systematic test case generation for all UtiliKit
 * utility rules, ensuring comprehensive coverage of value types, responsive
 * breakpoints, and edge cases. The generator creates both positive test
 * cases (expected to work) and boundary condition tests to validate the
 * complete UtiliKit system behavior.
 *
 * Test Case Generation Strategy:
 * - Covers all rule types defined in UtilikitRules
 * - Generates responsive variants for all applicable utilities
 * - Tests boundary values and edge cases
 * - Includes both basic and complex value patterns
 * - Validates CSS property mapping accuracy
 * - Ensures consistent behavior across breakpoints
 *
 * Generated test cases include metadata for automated testing frameworks
 * and can be used for CSS validation, performance testing, and regression
 * testing during development.
 */
class TestGenerator {

  /**
   * Predefined test values organized by category and use case.
   *
   * This array contains carefully selected test values that cover common
   * use cases, edge cases, and boundary conditions for different value
   * types used throughout the UtiliKit system.
   *
   * @var array
   */
  private array $testValues = [
    'numeric' => [0, 4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 100],
    'percentages' => ['25pr', '50pr', '75pr', '100pr'],
    'viewport' => ['50vh', '100vh', '50vw', '100vw'],
    'em' => ['0.5em', '1em', '1.5em', '2em'],
    'rem' => ['0.5rem', '1rem', '1.5rem', '2rem'],
    'colors' => ['000000', 'ffffff', '3b82f6', 'ef4444', '10b981', 'f59e0b'],
    'alphas' => ['50', '75', '90'],
    'transforms' => [
      'rotate' => [0, 45, 90, 180, 270, 360],
      'scale' => [50, 75, 100, 110, 125, 150, 200, 250],
    ],
    'breakpoints' => ['', 'sm', 'md', 'lg', 'xl', 'xxl'],
    'directions' => ['t', 'r', 'b', 'l'],
  ];

  /**
   * CSS keyword values mapped by property type.
   *
   * Contains comprehensive lists of valid CSS keyword values for each
   * property type that accepts keyword values in the UtiliKit system.
   * These mappings ensure test coverage for all supported keyword
   * combinations and help validate keyword-based utility classes.
   *
   * @var array
   */
  private array $keywordMap = [
    'display' => ['none', 'block', 'inline', 'inline-block', 'flex', 'inline-flex', 'grid', 'inline-grid'],
    'position' => ['static', 'relative', 'absolute', 'fixed', 'sticky'],
    'textAlign' => ['left', 'center', 'right', 'justify'],
    'overflow' => ['visible', 'hidden', 'auto', 'scroll'],
    'cursor' => ['auto', 'pointer', 'move', 'text', 'wait', 'help', 'not-allowed'],
    'float' => ['left', 'right', 'none'],
    'clear' => ['left', 'right', 'both', 'none'],
    'userSelect' => ['none', 'auto', 'text', 'all'],
    'borderStyle' => ['none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset'],
    'backgroundSize' => ['auto', 'cover', 'contain'],
    'flexDirection' => ['row', 'row-reverse', 'column', 'column-reverse'],
    'justifyContent' => ['flex-start', 'flex-end', 'center', 'space-between', 'space-around', 'space-evenly'],
    'alignItems' => ['flex-start', 'flex-end', 'center', 'baseline', 'stretch'],
    'alignContent' => ['flex-start', 'flex-end', 'center', 'space-between', 'space-around', 'stretch'],
    'flexWrap' => ['nowrap', 'wrap', 'wrap-reverse'],
  ];

  /**
   * Generates comprehensive test cases for all UtiliKit rules.
   *
   * Creates a complete test suite covering every rule defined in the
   * UtiliKit system, including all value types, responsive variants,
   * and edge cases. The generated test cases include metadata for
   * automated testing and validation frameworks.
   *
   * @return array
   *   Array of test case groups, each containing:
   *   - prefix: The UtiliKit prefix being tested
   *   - rule: Complete rule definition from UtilikitRules
   *   - tests: Array of individual test cases
   *   - group: Rule category for organization
   *
   * @see UtilikitRules::getRules()
   */
  public function generateAllTests(): array {
    $rules = UtilikitRules::getRules();
    $allTests = [];

    foreach ($rules as $prefix => $rule) {
      $testCases = $this->generateTestCasesForRule($prefix, $rule);
      if (!empty($testCases)) {
        $allTests[] = [
          'prefix' => $prefix,
          'rule' => $rule,
          'tests' => $testCases,
          'group' => $rule['group'] ?? 'Other',
        ];
      }
    }

    return $allTests;
  }

  /**
   * Generates test cases for a specific UtiliKit rule.
   *
   * Analyzes the rule definition to determine appropriate test case
   * generation strategies and creates comprehensive test coverage
   * based on the rule's value types and characteristics.
   *
   * @param string $prefix
   *   The UtiliKit prefix identifier (e.g., 'pd', 'mg', 'bg').
   * @param array $rule
   *   The complete rule definition array containing CSS property
   *   and behavior flags.
   *
   * @return array
   *   Array of test case objects for the specified rule.
   */
  private function generateTestCasesForRule(string $prefix, array $rule): array {
    $testCases = [];

    // Numeric flexible properties.
    if (!empty($rule['isNumericFlexible'])) {
      $testCases = array_merge($testCases, $this->generateNumericTests($prefix, $rule));
    }

    // Keyword properties.
    if (!empty($rule['isKeyword'])) {
      $testCases = array_merge($testCases, $this->generateKeywordTests($prefix, $rule));
    }

    // Color properties.
    if (!empty($rule['isColor'])) {
      $testCases = array_merge($testCases, $this->generateColorTests($prefix, $rule));
    }

    // Transform properties.
    if (!empty($rule['isTransform'])) {
      $testCases = array_merge($testCases, $this->generateTransformTests($prefix, $rule));
    }

    // Grid template properties.
    if (!empty($rule['isGridTrackList'])) {
      $testCases = array_merge($testCases, $this->generateGridTests($prefix, $rule));
    }

    // Integer properties (for fw, zi, or, etc)
    if (!empty($rule['isInteger']) && empty($rule['isOpacity'])) {
      $testCases = array_merge($testCases, $this->generateIntegerTests($prefix, $rule));
    }

    // Decimal fixed properties (for fg, fk)
    if (!empty($rule['isDecimalFixed'])) {
      $testCases = array_merge($testCases, $this->generateDecimalTests($prefix, $rule));
    }

    // Opacity special case.
    if (!empty($rule['isOpacity'])) {
      $testCases = array_merge($testCases, $this->generateOpacityTests($prefix));
    }

    // Range properties (grid column/row span)
    if (!empty($rule['isRange'])) {
      $testCases = array_merge($testCases, $this->generateRangeTests($prefix, $rule));
    }

    return $testCases;
  }

  /**
   * Generates test cases for numeric flexible properties.
   *
   * Creates comprehensive test coverage for properties that accept
   * numeric values with flexible units. Includes basic values,
   * responsive variants, directional properties, shorthand notation,
   * and special handling for specific properties like line-height.
   *
   * @param string $prefix
   *   The UtiliKit prefix being tested.
   * @param array $rule
   *   The rule definition containing CSS property and behavior flags.
   *
   * @return array
   *   Array of test cases covering numeric value scenarios.
   */
  private function generateNumericTests(string $prefix, array $rule): array {
    $tests = [];
    $cssProperty = $rule['css'];
    $hasSides = !empty($rule['sides']);
    $allowAuto = !empty($rule['allowAuto']);

    // Basic numeric values.
    foreach ($this->testValues['numeric'] as $value) {
      // Skip small numeric values for line-height as they'll be treated as
      // multipliers.
      if ($prefix === 'lh' && $value <= 10) {
        // Skip these - we'll handle them separately.
        continue;
      }

      // For unitless properties, don't add 'px'.
      $expectedValue = $value;
      if (!in_array($prefix, ['zi', 'fg', 'fk', 'or', 'fw'])) {
        $expectedValue = "{$value}px";
      }

      // Simple value.
      $tests[] = $this->createTestCase(
        "uk-$prefix--$value",
        $cssProperty,
        $expectedValue,
        'basic',
        "Basic value"
      );

      // Responsive variants.
      foreach (['sm', 'md', 'lg', 'xl', 'xxl'] as $bp) {
        // Skip small numeric values for line-height.
        if ($prefix === 'lh' && $value <= 10) {
          continue;
        }

        // For unitless properties, don't add 'px'.
        $responsiveExpectedValue = $value;
        if (!in_array($prefix, ['op', 'zi', 'fg', 'fk', 'or', 'fw'])) {
          $responsiveExpectedValue = "{$value}px";
        }

        $tests[] = $this->createTestCase(
          "uk-$bp-$prefix--$value",
          $cssProperty,
          $responsiveExpectedValue,
          'responsive',
          "Responsive at $bp breakpoint",
          $bp
        );
      }
    }

    // Auto value.
    if ($allowAuto) {
      $tests[] = $this->createTestCase(
        "uk-$prefix--auto",
        $cssProperty,
        "auto",
        'auto',
        "Auto value"
      );
    }

    // Directional tests (sides)
    if ($hasSides) {
      foreach ($this->testValues['directions'] as $dir) {
        $dirName = ['t' => 'BlockStart', 'r' => 'InlineEnd', 'b' => 'BlockEnd', 'l' => 'InlineStart'][$dir];
        $dirLabel = ['t' => 'Block-start', 'r' => 'Inline-end', 'b' => 'Block-end', 'l' => 'Inline-start'][$dir];

        // For border properties, we need the specific directional property.
        if ($prefix === 'bw') {
          $cssProp = 'border' . $dirName . 'Width';
        }
        elseif ($prefix === 'br') {
          // Border radius uses logical corner names.
          $radiusPropMap = [
            't' => ['borderStartStartRadius', 'borderStartEndRadius'],
            'r' => ['borderStartEndRadius', 'borderEndEndRadius'],
            'b' => ['borderEndEndRadius', 'borderEndStartRadius'],
            'l' => ['borderStartStartRadius', 'borderEndStartRadius'],
          ];
          // For testing, we'll check just the first property.
          $cssProp = $radiusPropMap[$dir][0];
        }
        else {
          $cssProp = $cssProperty . $dirName;
        }

        // Numeric directional.
        $tests[] = $this->createTestCase(
          "uk-$prefix--$dir-16",
          $cssProp,
          "16px",
          'directional',
          "Directional $dirLabel"
        );

        // Auto directional.
        if ($allowAuto) {
          $tests[] = $this->createTestCase(
            "uk-$prefix--$dir-auto",
            $cssProp,
            "auto",
            'directional-auto',
            "Directional $dirLabel auto"
          );
        }
      }

      // Shorthand tests - use property: shorthand format.
      $tests[] = $this->createTestCase(
        "uk-$prefix--16-32",
        $cssProperty,
        "16px 32px",
        'shorthand-2',
        "Two-value shorthand (vertical horizontal)"
      );

      $tests[] = $this->createTestCase(
        "uk-$prefix--16-32-24",
        $cssProperty,
        "16px 32px 24px",
        'shorthand-3',
        "Three-value shorthand (top horizontal bottom)"
      );

      $tests[] = $this->createTestCase(
        "uk-$prefix--16-32-24-48",
        $cssProperty,
        "16px 32px 24px 48px",
        'shorthand-4',
        "Four-value shorthand (top right bottom left)"
      );
    }

    // Viewport units (for specific properties)
    if (in_array($prefix, ['wd', 'ht', 'xw', 'xh', 'nw', 'nh', 'fs'])) {
      foreach ($this->testValues['viewport'] as $value) {
        $tests[] = $this->createTestCase(
          "uk-$prefix--$value",
          $cssProperty,
          $value,
          'viewport',
          "Viewport unit"
        );
      }
    }

    // Special case for gap (can have two values)
    if ($prefix === 'gp') {
      // Gap only accepts integers in JS engine.
      $tests[] = $this->createTestCase(
        "uk-gp--16-32",
        'gap',
        "16px 32px",
        'gap-2',
        "Two-value gap (row column)"
      );
    }

    // Special handling for line-height.
    if ($prefix === 'lh') {
      // Test unitless multipliers - but expect pixel values.
      // This is what your test environment uses.
      $defaultFontSize = 12;

      foreach ([1, 1.2, 1.5, 1.75, 2, 2.5, 3] as $multiplier) {
        $expectedPx = $multiplier * $defaultFontSize;
        // Convert decimal to 'd' format for class name.
        $classValue = str_replace('.', 'd', (string) $multiplier);
        $tests[] = $this->createTestCase(
          "uk-lh--" . $classValue,
          'lineHeight',
          // Expect the computed pixel value.
          $expectedPx . 'px',
          'line-height-unitless',
          "Line height unitless multiplier {$multiplier}"
        );
      }

      // Test with explicit px units.
      foreach ([12, 16, 20, 24, 28, 32] as $value) {
        $tests[] = $this->createTestCase(
          "uk-lh--{$value}px",
          'lineHeight',
          "{$value}px",
          'line-height-px',
          "Line height {$value}px"
        );
      }

      // Test with rem units - expect pixel values.
      // Default root font size.
      $rootFontSize = 16;
      foreach ([1, 1.25, 1.5, 1.75, 2] as $remValue) {
        $expectedPx = $remValue * $rootFontSize;
        // Convert decimal to 'd' format for class name.
        $classValue = str_replace('.', 'd', (string) $remValue);
        $tests[] = $this->createTestCase(
          "uk-lh--{$classValue}rem",
          'lineHeight',
          // Expect the computed pixel value.
          $expectedPx . 'px',
          'line-height-rem',
          "Line height {$remValue}rem"
        );
      }
    }

    return $tests;
  }

  /**
   * Generates test cases for integer-only properties.
   *
   * Creates test coverage for properties that accept only integer values,
   * such as z-index, font-weight, and flex order. Includes appropriate
   * value ranges for each property type.
   *
   * @param string $prefix
   *   The UtiliKit prefix being tested.
   * @param array $rule
   *   The rule definition containing CSS property information.
   *
   * @return array
   *   Array of test cases for integer value scenarios.
   */
  private function generateIntegerTests(string $prefix, array $rule): array {
    $tests = [];
    $values = [1, 100, 200, 300, 400, 500, 600, 700, 800, 900];

    if ($prefix === 'zi') {
      $values = [-1, 0, 1, 10, 100, 999, 9999];
    }
    elseif ($prefix === 'fw') {
      $values = [100, 200, 300, 400, 500, 600, 700, 800, 900];
    }
    elseif ($prefix === 'or') {
      $values = [-1, 0, 1, 2, 3];
    }

    foreach ($values as $value) {
      $tests[] = $this->createTestCase(
        "uk-$prefix--$value",
        $rule['css'],
        (string) $value,
        'integer',
        "Integer value $value"
      );
    }

    return $tests;
  }

  /**
   * Generates test cases for decimal fixed-precision properties.
   *
   * Creates test coverage for properties that accept decimal values
   * with fixed precision, such as flex-grow and flex-shrink. Uses
   * UtiliKit's 'd' notation for decimal points in class names.
   *
   * @param string $prefix
   *   The UtiliKit prefix being tested.
   * @param array $rule
   *   The rule definition containing CSS property information.
   *
   * @return array
   *   Array of test cases for decimal value scenarios.
   */
  private function generateDecimalTests(string $prefix, array $rule): array {
    $tests = [];
    // JS engine requires exactly 2 decimal places, but browser normalizes.
    $values = [
      '0' => '0',
    // Changed from '0.50'.
      '0d50' => '0.5',
      '1' => '1',
    // Changed from '1.50'.
      '1d50' => '1.5',
      '2' => '2',
    // Changed from '2.25'.
      '2d25' => '2.25',
    ];

    foreach ($values as $input => $expected) {
      $tests[] = $this->createTestCase(
        "uk-$prefix--$input",
        $rule['css'],
        $expected,
        'decimal',
        "Decimal value $input"
      );
    }

    return $tests;
  }

  /**
   * Generates test cases for keyword-based properties.
   *
   * Creates comprehensive test coverage for properties that accept
   * CSS keyword values. Includes both basic keyword tests and
   * responsive variants across all breakpoints.
   *
   * @param string $prefix
   *   The UtiliKit prefix being tested.
   * @param array $rule
   *   The rule definition containing CSS property information.
   *
   * @return array
   *   Array of test cases for keyword value scenarios.
   */
  private function generateKeywordTests(string $prefix, array $rule): array {
    $tests = [];
    $cssProperty = $rule['css'];
    $keywords = $this->keywordMap[$cssProperty] ?? [];

    foreach ($keywords as $keyword) {
      // Basic keyword.
      $tests[] = $this->createTestCase(
        "uk-$prefix--$keyword",
        $cssProperty,
        $keyword,
        'keyword',
        "Keyword: $keyword"
      );

      // Responsive keyword.
      foreach (['md', 'lg'] as $bp) {
        $tests[] = $this->createTestCase(
          "uk-$bp-$prefix--$keyword",
          $cssProperty,
          $keyword,
          'responsive-keyword',
          "Responsive keyword at $bp",
          $bp
        );
      }
    }

    // Side-specific keyword tests (e.g., uk-bs--b-dashed).
    if (!empty($rule['sides'])) {
      $sideMap = ['t' => 'Top', 'r' => 'Right', 'b' => 'Bottom', 'l' => 'Left'];
      // Test first two keywords per side to keep test count manageable.
      $sideKeywords = array_slice($keywords, 0, 2);
      foreach ($sideKeywords as $keyword) {
        foreach ($sideMap as $dir => $dirName) {
          // e.g. borderStyle + Top → borderTopStyle.
          $sidedProp = preg_replace('/^(border)(.+)$/', '$1' . $dirName . '$2', $cssProperty);
          $tests[] = $this->createTestCase(
            "uk-$prefix--$dir-$keyword",
            $sidedProp,
            $keyword,
            'directional-keyword',
            "Directional keyword $dirName: $keyword"
          );
        }
      }
    }

    return $tests;
  }

  /**
   * Generates test cases for color properties.
   *
   * Creates comprehensive test coverage for color values including
   * hex notation, alpha transparency, and various color formats
   * supported by the UtiliKit color system.
   *
   * @param string $prefix
   *   The UtiliKit prefix being tested.
   * @param array $rule
   *   The rule definition containing CSS property information.
   *
   * @return array
   *   Array of test cases for color value scenarios.
   */
  private function generateColorTests(string $prefix, array $rule): array {
    $tests = [];
    $cssProperty = $rule['css'];

    foreach ($this->testValues['colors'] as $color) {
      // Basic color.
      $tests[] = $this->createTestCase(
        "uk-$prefix--$color",
        $cssProperty,
        "#$color",
        'color',
        "Hex color #$color"
      );

      // Color with alpha.
      foreach ($this->testValues['alphas'] as $alpha) {
        $alphaDecimal = $alpha / 100;
        $r = hexdec(substr($color, 0, 2));
        $g = hexdec(substr($color, 2, 2));
        $b = hexdec(substr($color, 4, 2));

        $tests[] = $this->createTestCase(
          "uk-$prefix--$color-$alpha",
          $cssProperty,
          "rgba($r, $g, $b, $alphaDecimal)",
          'color-alpha',
          "Color with $alpha% alpha"
        );
      }
    }

    // 3-digit hex test
    $tests[] = $this->createTestCase(
      "uk-$prefix--f00",
      $cssProperty,
      "#ff0000",
      'color-short',
      "3-digit hex color"
    );

    // Pseudo-state suffix tests (bg and tc only).
    // In inline mode the suffix is stripped and the base color applies.
    // In static/head mode the CSS selector gets :hover/:focus/:active.
    if (in_array($prefix, ['bg', 'tc'], TRUE)) {
      $stateLabels = ['h' => 'hover', 'f' => 'focus', 'a' => 'active'];
      $testColor = '007bff';

      foreach ($stateLabels as $suffix => $label) {
        // Color with pseudo-state.
        $tests[] = $this->createTestCase(
          "uk-$prefix--$testColor-$suffix",
          $cssProperty,
          "#$testColor",
          'color',
          "Color with $label state"
        );
      }

      // Alpha + pseudo-state combo.
      $r = hexdec(substr($testColor, 0, 2));
      $g = hexdec(substr($testColor, 2, 2));
      $b = hexdec(substr($testColor, 4, 2));
      $tests[] = $this->createTestCase(
        "uk-$prefix--$testColor-50-h",
        $cssProperty,
        "rgba($r, $g, $b, 0.5)",
        'color-alpha',
        "Color with 50% alpha + hover state"
      );
    }

    return $tests;
  }

  /**
   * Generates test cases for CSS transform properties.
   *
   * Creates test coverage for transform functions including rotation
   * and scale transforms with support for decimal values using
   * UtiliKit's 'd' notation system.
   *
   * @param string $prefix
   *   The UtiliKit prefix being tested.
   * @param array $rule
   *   The rule definition containing transform type information.
   *
   * @return array
   *   Array of test cases for transform scenarios.
   */
  private function generateTransformTests(string $prefix, array $rule): array {
    $tests = [];
    $transformType = $rule['isTransform'];

    if ($transformType === 'rotate') {
      foreach ([45, 90, 180, 270, 360] as $value) {
        $tests[] = $this->createTestCase(
          "uk-$prefix--$value",
          'transform',
          "rotate({$value}deg)",
          'transform',
          "Rotate {$value} degrees"
        );
      }
    }
    elseif ($transformType === 'scale') {
      // Use 'd' format for decimal scale values.
      $scaleValues = [
      // 50% = 0.5x
        '50' => 0.5,
      // 75% = 0.75x
        '75' => 0.75,
      // 100% = 1x (normal)
        '100' => 1,
      // 110% = 1.1x
        '110' => 1.1,
      // 125% = 1.25x
        '125' => 1.25,
      // 150% = 1.5x
        '150' => 1.5,
      // 200% = 2x
        '200' => 2.0,
      // 250% = 2.5x
        '250' => 2.5,
      ];

      foreach ($scaleValues as $classValue => $scaleValue) {
        $tests[] = $this->createTestCase(
          "uk-$prefix--$classValue",
          'transform',
          "scale($scaleValue)",
          'transform',
          "Scale " . ($scaleValue * 100) . "%"
        );
      }
    }

    return $tests;
  }

  /**
   * Generates test cases for CSS Grid template properties.
   *
   * Creates comprehensive test coverage for complex grid template
   * definitions including repeat(), minmax(), and other CSS Grid
   * functions. Includes both simple and complex grid patterns.
   *
   * @param string $prefix
   *   The UtiliKit prefix being tested (gc for columns, gr for rows).
   * @param array $rule
   *   The rule definition containing CSS property information.
   *
   * @return array
   *   Array of test cases for grid template scenarios.
   */
  private function generateGridTests(string $prefix, array $rule): array {
    $tests = [];
    $cssProperty = $rule['css'];

    // For grid templates, we need to handle them differently.
    if ($prefix === 'gc' || $prefix === 'gr') {
      // Grid patterns that work in all browsers.
      $workingPatterns = [
        // ==========================================
        // LEVEL 1: BASIC VALUES (Simplest)
        // ==========================================
        // Single values and basic combinations
        'auto-auto' => 'auto auto',
        '200px-1fr' => '200px 1fr',
        '1fr-auto-2fr' => '1fr auto 2fr',
        '100px-1fr-100px' => '100px 1fr 100px',
        '1fr-2fr-0.5fr' => '1fr 2fr 0.5fr',

        // ==========================================
        // LEVEL 2: SIMPLE FUNCTIONS
        // ==========================================
        // Single function calls with basic parameters
        // 2A: Simple repeat() functions
        'repeat-3-1fr' => 'repeat(3, 1fr)',
        'repeat-2-200px' => 'repeat(2, 200px)',
        'repeat-4-1fr' => 'repeat(4, 1fr)',

        // 2B: Simple minmax() functions
        'minmax-100px-1fr' => 'minmax(100px, 1fr)',
        'minmax-200px-1fr' => 'minmax(200px, 1fr)',
        'minmax-50pr-2fr' => 'minmax(50%, 2fr)',
        'minmax-10rem-1fr' => 'minmax(10rem, 1fr)',

        // 2C: Simple fit-content() functions
        'fitc-150px' => 'fit-content(150px)',
        'fitc-250px' => 'fit-content(250px)',

        // ==========================================
        // LEVEL 3: NESTED FUNCTIONS
        // ==========================================
        // Functions within functions (higher complexity)
        // 3A: repeat() with minmax() (fixed count)
        'repeat-3-minmax-200px-1fr' => 'repeat(3, minmax(200px, 1fr))',
        'repeat-2-minmax-100px-1fr' => 'repeat(2, minmax(100px, 1fr))',

        // ==========================================
        // LEVEL 4: AUTO-RESPONSIVE PATTERNS
        // ==========================================
        // Auto-fit and auto-fill (highest parsing complexity)
        // 4A: auto-fit with minmax (most complex parsing)
        'repeat-autofit-minmax-250px-1fr' => 'repeat(auto-fit, minmax(250px, 1fr))',
        'repeat-autofit-minmax-280px-1fr' => 'repeat(auto-fit, minmax(280px, 1fr))',
        'repeat-autofit-minmax-200px-1fr' => 'repeat(auto-fit, minmax(200px, 1fr))',
        'repeat-autofit-minmax-300px-1fr' => 'repeat(auto-fit, minmax(300px, 1fr))',
        // 4B: auto-fill with minmax
        'repeat-autofill-minmax-200px-1fr' => 'repeat(auto-fill, minmax(200px, 1fr))',
        'repeat-autofill-minmax-150px-1fr' => 'repeat(auto-fill, minmax(150px, 1fr))',
        'repeat-autofill-minmax-250px-1fr' => 'repeat(auto-fill, minmax(250px, 1fr))',

        // 4C: auto-fit/fill with fixed sizes (simpler than with minmax)
        'repeat-autofit-250px' => 'repeat(auto-fit, 250px)',
        'repeat-autofill-200px' => 'repeat(auto-fill, 200px)',

        // ==========================================
        // LEVEL 5: MIXED PATTERNS
        // ==========================================
        // Functions combined with basic values
        // 5A: fit-content() with other values
        'fitc-300px-1fr' => 'fit-content(300px) 1fr',
        '1fr-fitc-250px' => '1fr fit-content(250px)',

        // 5B: Complex combinations (existing patterns)
        'fitc-150px-1fr-100px' => 'fit-content(150px) 1fr 100px',
        'repeat-2-1fr-100px' => 'repeat(2, 1fr) 100px',
        'repeat-4-minc' => 'repeat(4, min-content)',
        'repeat-2-auto-1fr' => 'repeat(2, auto) 1fr',
        '1fr-minc' => '1fr min-content',
        'minc-1fr-auto-maxc' => 'min-content 1fr auto max-content',
        '100px-minc-maxc' => '100px min-content max-content',
        'minc-maxc' => 'min-content max-content',

        // 5C: Advanced combinations (existing)
        'minmax-100px-1fr-repeat-2-200px' => 'minmax(100px, 1fr) repeat(2, 200px)',
      ];
      foreach ($workingPatterns as $pattern => $expected) {
        $tests[] = $this->createTestCase(
          "uk-$prefix--$pattern",
          $cssProperty,
          $expected,
          'grid-template',
          "Grid template: $expected"
        );
      }

      // Add responsive variants.
      $tests[] = $this->createTestCase(
        "uk-md-$prefix--repeat-2-1fr-100px",
        $cssProperty,
        'repeat(2, 1fr) 100px',
        'grid-template-responsive',
        "Grid template at md: repeat(2, 1fr) 100px",
        'md'
      );

      $tests[] = $this->createTestCase(
        "uk-lg-$prefix--minmax-200px-1fr",
        $cssProperty,
        'minmax(200px, 1fr)',
        'grid-template-responsive',
        "Grid template at lg: minmax(200px, 1fr)",
        'lg'
      );

      // Add these additional responsive test cases.
      $tests[] = $this->createTestCase(
        "uk-sm-$prefix--minmax-200px-1fr",
        $cssProperty,
        'minmax(200px, 1fr)',
        'grid-template-responsive',
        "Grid template at sm: minmax(200px, 1fr)",
        'sm'
      );

      $tests[] = $this->createTestCase(
        "uk-md-$prefix--repeat-2-1fr",
        $cssProperty,
        'repeat(2, 1fr)',
        'grid-template-responsive',
        "Grid template at md: repeat(2, 1fr)",
        'md'
      );

      $tests[] = $this->createTestCase(
        "uk-lg-$prefix--repeat-auto-fit-minmax-250px-1fr",
        $cssProperty,
        'repeat(auto-fit, minmax(250px, 1fr))',
        'grid-template-responsive',
        "Grid template at lg: repeat(auto-fit, minmax(250px, 1fr))",
        'lg'
      );

      $tests[] = $this->createTestCase(
        "uk-xl-$prefix--repeat-auto-fill-200px",
        $cssProperty,
        'repeat(auto-fill, 200px)',
        'grid-template-responsive',
        "Grid template at xl: repeat(auto-fill, 200px)",
        'xl'
      );

      return $tests;
    }

    // Original fractional tests for non-grid properties.
    foreach ([1, 2, 3] as $value) {
      $tests[] = $this->createTestCase(
        "uk-$prefix--{$value}fr",
        $cssProperty,
        "{$value}fr",
        'fractional',
        "Fractional unit {$value}fr"
      );
    }

    return $tests;
  }

  /**
   * Generates test cases for range-based properties.
   *
   * Creates test coverage for properties that use range notation,
   * including CSS Grid positioning and aspect ratios. Supports
   * the "start-end" format used by UtiliKit.
   *
   * @param string $prefix
   *   The UtiliKit prefix being tested.
   * @param array $rule
   *   The rule definition containing CSS property information.
   *
   * @return array
   *   Array of test cases for range value scenarios.
   */
  private function generateRangeTests(string $prefix, array $rule): array {
    $tests = [];
    $cssProperty = $rule['css'];

    $ranges = [
      '1-2' => '1 / 2',
      '1-3' => '1 / 3',
      '2-4' => '2 / 4',
      '1-5' => '1 / 5',
    ];

    foreach ($ranges as $range => $expected) {
      $tests[] = $this->createTestCase(
        "uk-$prefix--$range",
        $cssProperty,
        $expected,
        'range',
        "Grid range $expected"
      );
    }

    // Test aspect ratio specifically.
    if ($cssProperty === 'aspectRatio') {
      $ratios = [
        '16-9' => '16 / 9',
        '4-3' => '4 / 3',
        '1-1' => '1 / 1',
        '21-9' => '21 / 9',
      ];

      foreach ($ratios as $ratio => $expected) {
        $tests[] = $this->createTestCase(
          "uk-$prefix--$ratio",
          $cssProperty,
          $expected,
          'aspect-ratio',
          "Aspect ratio $expected"
        );
      }
    }

    return $tests;
  }

  /**
   * Creates a standardized test case object.
   *
   * Generates a consistent test case structure with all necessary
   * metadata for automated testing frameworks and validation systems.
   *
   * @param string $className
   *   The complete UtiliKit class name to test.
   * @param string|null $cssProperty
   *   The expected CSS property name that should be set.
   * @param mixed $expectedValue
   *   The expected CSS value that should be applied.
   * @param string $testType
   *   The category/type of test for organization purposes.
   * @param string $description
   *   Human-readable description of what the test validates.
   * @param string|null $breakpoint
   *   The responsive breakpoint if applicable.
   *
   * @return array
   *   Structured test case array with all metadata.
   */
  private function createTestCase(
    string $className,
    ?string $cssProperty,
    $expectedValue,
    string $testType,
    string $description,
    ?string $breakpoint = NULL,
  ): array {
    return [
      'className' => $className,
      'cssProperty' => $cssProperty,
      'expectedValue' => $expectedValue,
      'testType' => $testType,
      'description' => $description,
      'breakpoint' => $breakpoint,
    ];
  }

  /**
   * Generates test cases specifically for opacity properties.
   *
   * Creates comprehensive test coverage for opacity values including
   * the conversion from 0-100 integer values to 0-1 decimal values
   * as required by CSS. Includes responsive variants.
   *
   * @param string $prefix
   *   The UtiliKit prefix being tested (should be 'op').
   *
   * @return array
   *   Array of test cases for opacity scenarios.
   */
  private function generateOpacityTests(string $prefix): array {
    $tests = [];

    // Standard opacity values.
    foreach ([0, 25, 50, 75, 100] as $value) {
      // Opacity is always a decimal between 0 and 1.
      $cssValue = $value === 0 ? '0' : (string) ($value / 100);

      // Base test.
      $tests[] = $this->createTestCase(
        "uk-op--$value",
        'opacity',
        $cssValue,
        'opacity',
        "Opacity $value%"
      );

      // Responsive variants.
      foreach (['sm', 'md', 'lg', 'xl', 'xxl'] as $bp) {
        $tests[] = $this->createTestCase(
          "uk-$bp-op--$value",
          'opacity',
          $cssValue,
          'opacity-responsive',
          "Opacity $value% at $bp",
          $bp
        );
      }
    }

    return $tests;
  }

  /**
   * Calculates comprehensive test statistics for analysis and reporting.
   *
   * Analyzes the complete test suite to provide detailed statistics
   * about test coverage, distribution across categories, and breakpoint
   * coverage for quality assurance and reporting purposes.
   *
   * @return array
   *   Comprehensive statistics including:
   *   - totalTests: Total number of test cases generated
   *   - byGroup: Test distribution by rule group/category
   *   - byType: Test distribution by test type
   *   - byBreakpoint: Test distribution by responsive breakpoint
   */
  public function getTestStatistics(): array {
    $allTests = $this->generateAllTests();
    $stats = [
      'totalTests' => 0,
      'byGroup' => [],
      'byType' => [],
      'byBreakpoint' => ['base' => 0],
    ];

    foreach ($allTests as $ruleTests) {
      $group = $ruleTests['group'];
      $stats['byGroup'][$group] = ($stats['byGroup'][$group] ?? 0) + count($ruleTests['tests']);

      foreach ($ruleTests['tests'] as $test) {
        $stats['totalTests']++;
        $type = $test['testType'];
        $stats['byType'][$type] = ($stats['byType'][$type] ?? 0) + 1;

        $bp = $test['breakpoint'] ?? 'base';
        $stats['byBreakpoint'][$bp] = ($stats['byBreakpoint'][$bp] ?? 0) + 1;
      }
    }

    return $stats;
  }

  /**
   * Generates a flat array of all possible UtiliKit class names.
   *
   * Extracts all generated class names into a simple array format
   * suitable for CSS generation, validation systems, and bulk
   * processing operations. Removes duplicates and filters empty values.
   *
   * @return array
   *   Array of unique, valid UtiliKit class names ready for CSS generation
   *   or validation processing.
   */
  public function getAllPossibleClasses(): array {
    $allTests = $this->generateAllTests();
    $classes = [];

    foreach ($allTests as $ruleTest) {
      if (!isset($ruleTest['tests']) || !is_array($ruleTest['tests'])) {
        continue;
      }

      foreach ($ruleTest['tests'] as $test) {
        // Use 'className' instead of 'class'.
        if (isset($test['className']) && !empty($test['className']) && is_string($test['className'])) {
          $classes[] = $test['className'];
        }
      }
    }

    // Remove duplicates and filter out any empty values.
    $classes = array_unique(array_filter($classes));

    return $classes;
  }

}
