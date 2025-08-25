<?php

namespace Drupal\Tests\utilikit\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitRules;

/**
 * Tests UtilikitRules service class.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Service\UtilikitRules
 */
class UtilikitRulesTest extends UnitTestCase {

  /**
   * The UtilikitRules service instance.
   *
   * @var \Drupal\utilikit\Service\UtilikitRules
   */
  protected $rulesService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->rulesService = new UtilikitRules();
  }

  /**
   * Tests that getRules returns a non-empty array.
   *
   * @covers ::getRules
   */
  public function testGetRulesReturnsArray() {
    $rules = UtilikitRules::getRules();
    $this->assertIsArray($rules);
    $this->assertNotEmpty($rules);
  }

  /**
   * Tests that all rules have required structure.
   *
   * @covers ::getRules
   */
  public function testRuleStructure() {
    $rules = UtilikitRules::getRules();

    foreach ($rules as $prefix => $rule) {
      $this->assertIsString($prefix, "Rule prefix should be a string");
      $this->assertIsArray($rule, "Rule definition should be an array");
      $this->assertArrayHasKey('css', $rule, "Rule should have 'css' property");
      $this->assertIsString($rule['css'], "CSS property should be a string");

      // Optional properties should be boolean if present.
      $booleanProperties = [
        'sides', 'isNumericFlexible', 'isKeyword', 'isColor',
        'isInteger', 'isDecimalFixed', 'isOpacity', 'isTransform',
        'isGridTrackList', 'isRange', 'allowAuto',
      ];

      foreach ($booleanProperties as $prop) {
        if (isset($rule[$prop])) {
          $this->assertIsBool($rule[$prop], "Property '{$prop}' should be boolean if present");
        }
      }

      // Group should be string if present.
      if (isset($rule['group'])) {
        $this->assertIsString($rule['group'], "Group should be a string if present");
      }
    }
  }

  /**
   * Tests that CSS property map contains expected mappings.
   *
   * @covers ::CSS_PROPERTY_MAP
   */
  public function testCssPropertyMap() {
    $map = UtilikitRules::CSS_PROPERTY_MAP;
    $this->assertIsArray($map);

    // Test specific known mappings.
    $expectedMappings = [
      'borderWidth' => 'border-width',
      'borderRadius' => 'border-radius',
      'backgroundColor' => 'background-color',
      'fontSize' => 'font-size',
      'maxWidth' => 'max-width',
      'minWidth' => 'min-width',
      'maxHeight' => 'max-height',
      'minHeight' => 'min-height',
      'textAlign' => 'text-align',
      'justifyContent' => 'justify-content',
      'alignItems' => 'align-items',
      'flexDirection' => 'flex-direction',
      'gridTemplateColumns' => 'grid-template-columns',
      'gridTemplateRows' => 'grid-template-rows',
    ];

    foreach ($expectedMappings as $camelCase => $kebabCase) {
      $this->assertArrayHasKey($camelCase, $map, "CSS property map should contain '{$camelCase}'");
      $this->assertEquals($kebabCase, $map[$camelCase], "'{$camelCase}' should map to '{$kebabCase}'");
    }

    // Test that all values are kebab-case.
    foreach ($map as $camelCase => $kebabCase) {
      $this->assertMatchesRegularExpression('/^[a-z]+(-[a-z]+)*$/', $kebabCase,
        "CSS property '{$kebabCase}' should be in kebab-case format");
    }
  }

  /**
   * Tests that rules are properly grouped.
   *
   * @covers ::getRules
   */
  public function testRuleGroups() {
    $rules = UtilikitRules::getRules();
    $groups = [];

    // Collect all groups.
    foreach ($rules as $rule) {
      if (isset($rule['group'])) {
        $groups[] = $rule['group'];
      }
    }

    $groups = array_unique($groups);

    // Test expected groups exist.
    $expectedGroups = ['Box Model', 'Colors', 'Sizing', 'Positioning', 'Layout', 'Typography'];
    foreach ($expectedGroups as $expectedGroup) {
      $this->assertContains($expectedGroup, $groups,
        "Expected group '{$expectedGroup}' should exist in rules");
    }
  }

  /**
   * Tests rule prefixes are properly formatted.
   *
   * @covers ::getRules
   */
  public function testRulePrefixes() {
    $rules = UtilikitRules::getRules();

    foreach (array_keys($rules) as $prefix) {
      $this->assertIsString($prefix);
      $this->assertMatchesRegularExpression('/^[a-z]{2,4}$/', $prefix,
        "Rule prefix '{$prefix}' should be 2-4 lowercase letters");
    }
  }

  /**
   * Tests that rules with sides property have appropriate CSS properties.
   *
   * @covers ::getRules
   */
  public function testSidesRules() {
    $rules = UtilikitRules::getRules();

    $sidesExpectedProperties = ['padding', 'margin', 'borderWidth', 'borderRadius'];

    foreach ($rules as $rule) {
      if (isset($rule['sides']) && $rule['sides'] === TRUE) {
        $this->assertContains($rule['css'], $sidesExpectedProperties,
          "Rule with sides=true should have appropriate CSS property");
      }
    }
  }

  /**
   * Tests that color rules are properly configured.
   *
   * @covers ::getRules
   */
  public function testColorRules() {
    $rules = UtilikitRules::getRules();

    $colorRules = array_filter($rules, function ($rule) {
      return isset($rule['isColor']) && $rule['isColor'] === TRUE;
    });

    $this->assertNotEmpty($colorRules, "Should have color rules defined");

    foreach ($colorRules as $prefix => $rule) {
      // Color rules should typically be in Colors group.
      if (isset($rule['group'])) {
        $this->assertEquals('Colors', $rule['group'],
          "Color rule '{$prefix}' should be in Colors group");
      }
    }
  }

  /**
   * Tests that numeric flexible rules are properly configured.
   *
   * @covers ::getRules
   */
  public function testNumericFlexibleRules() {
    $rules = UtilikitRules::getRules();

    $numericRules = array_filter($rules, function ($rule) {
      return isset($rule['isNumericFlexible']) && $rule['isNumericFlexible'] === TRUE;
    });

    $this->assertNotEmpty($numericRules, "Should have numeric flexible rules defined");

    // These rules should typically be in Box Model or Sizing groups.
    $expectedGroups = ['Box Model', 'Sizing', 'Positioning'];

    foreach ($numericRules as $prefix => $rule) {
      if (isset($rule['group'])) {
        $this->assertContains($rule['group'], $expectedGroups,
          "Numeric rule '{$prefix}' should be in appropriate group");
      }
    }
  }

  /**
   * Tests keyword rules are properly configured.
   *
   * @covers ::getRules
   */
  public function testKeywordRules() {
    $rules = UtilikitRules::getRules();

    $keywordRules = array_filter($rules, function ($rule) {
      return isset($rule['isKeyword']) && $rule['isKeyword'] === TRUE;
    });

    $this->assertNotEmpty($keywordRules, "Should have keyword rules defined");

    // Test some expected keyword rules.
    $expectedKeywordPrefixes = ['dp', 'ps', 'ta', 'fw'];
    foreach ($expectedKeywordPrefixes as $prefix) {
      if (isset($rules[$prefix])) {
        $this->assertTrue($rules[$prefix]['isKeyword'] ?? FALSE,
          "Rule '{$prefix}' should be a keyword rule");
      }
    }
  }

  /**
   * Tests transform rules are properly configured.
   *
   * @covers ::getRules
   */
  public function testTransformRules() {
    $rules = UtilikitRules::getRules();

    $transformRules = array_filter($rules, function ($rule) {
      return isset($rule['isTransform']) && $rule['isTransform'] === TRUE;
    });

    if (!empty($transformRules)) {
      foreach ($transformRules as $prefix => $rule) {
        // Transform rules should be in Layout or Transform group.
        if (isset($rule['group'])) {
          $this->assertContains($rule['group'], ['Layout', 'Transform', 'Effects'],
            "Transform rule '{$prefix}' should be in appropriate group");
        }
      }
    }
  }

  /**
   * Tests grid track list rules are properly configured.
   *
   * @covers ::getRules
   */
  public function testGridTrackListRules() {
    $rules = UtilikitRules::getRules();

    $gridRules = array_filter($rules, function ($rule) {
      return isset($rule['isGridTrackList']) && $rule['isGridTrackList'] === TRUE;
    });

    if (!empty($gridRules)) {
      foreach ($gridRules as $prefix => $rule) {
        // Grid rules should be in Layout group.
        if (isset($rule['group'])) {
          $this->assertEquals('Layout', $rule['group'],
            "Grid rule '{$prefix}' should be in Layout group");
        }
      }
    }
  }

  /**
   * Tests that the service instance can be created.
   */
  public function testServiceInstantiation() {
    $service = new UtilikitRules();
    $this->assertInstanceOf(UtilikitRules::class, $service);
  }

  /**
   * Tests consistency between CSS property map and rule CSS properties.
   *
   * @covers ::getRules
   * @covers ::CSS_PROPERTY_MAP
   */
  public function testCssPropertyConsistency() {
    $rules = UtilikitRules::getRules();
    $map = UtilikitRules::CSS_PROPERTY_MAP;

    foreach ($rules as $prefix => $rule) {
      $cssProperty = $rule['css'];

      // If the CSS property is in camelCase and exists in the map.
      if (isset($map[$cssProperty])) {
        $kebabCase = $map[$cssProperty];

        // The kebab-case version should be properly formatted.
        $this->assertMatchesRegularExpression('/^[a-z]+(-[a-z]+)*$/', $kebabCase,
          "Mapped CSS property '{$kebabCase}' for rule '{$prefix}' should be valid kebab-case");
      }
    }
  }

  /**
   * Tests that all rule definitions are complete.
   *
   * @covers ::getRules
   */
  public function testRuleCompleteness() {
    $rules = UtilikitRules::getRules();

    foreach ($rules as $prefix => $rule) {
      // Every rule must have a CSS property.
      $this->assertArrayHasKey('css', $rule, "Rule '{$prefix}' must have a CSS property");
      $this->assertNotEmpty($rule['css'], "Rule '{$prefix}' CSS property cannot be empty");

      // If a rule claims to support sides, it should have appropriate
      // properties.
      if (isset($rule['sides']) && $rule['sides']) {
        $this->assertTrue($rule['isNumericFlexible'] ?? FALSE,
          "Rule '{$prefix}' with sides support should typically be numeric flexible");
      }

      // Color rules shouldn't also be numeric flexible (usually)
      if (isset($rule['isColor']) && $rule['isColor']) {
        $this->assertFalse($rule['isNumericFlexible'] ?? FALSE,
          "Rule '{$prefix}' should not be both color and numeric flexible");
      }
    }
  }

}
