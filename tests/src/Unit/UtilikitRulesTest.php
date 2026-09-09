<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitRules;

/**
 * Tests the UtilikitRules service.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Service\UtilikitRules
 */
#[Group('utilikit')]
class UtilikitRulesTest extends UnitTestCase {

  /**
   * Tests getRules returns array keyed by prefix with expected structure.
   *
   * @covers ::getRules
   */
  public function testGetRulesReturnsArray(): void {
    $rules = UtilikitRules::getRules();

    $this->assertIsArray($rules);
    $this->assertNotEmpty($rules);
  }

  /**
   * Tests getRules contains expected core prefixes.
   *
   * @covers ::getRules
   */
  public function testGetRulesContainsCorePrefixes(): void {
    $rules = UtilikitRules::getRules();

    $this->assertArrayHasKey('pd', $rules);
    $this->assertArrayHasKey('mg', $rules);
    $this->assertArrayHasKey('bg', $rules);
    $this->assertArrayHasKey('fx', $rules);
    $this->assertArrayHasKey('dp', $rules);
  }

  /**
   * Tests each rule has required 'css' key.
   *
   * @covers ::getRules
   */
  public function testGetRulesStructure(): void {
    $rules = UtilikitRules::getRules();

    foreach ($rules as $prefix => $rule) {
      $this->assertIsArray($rule, "Rule for prefix '$prefix' should be an array");
      $this->assertArrayHasKey('css', $rule, "Rule for prefix '$prefix' should have 'css' key");
      $this->assertNotEmpty($rule['css'], "Rule for prefix '$prefix' should have non-empty css");
    }
  }

  /**
   * Tests getCssPropertyName maps camelCase to kebab-case.
   *
   * @covers ::getCssPropertyName
   */
  public function testGetCssPropertyName(): void {
    $this->assertSame('border-width', UtilikitRules::getCssPropertyName('borderWidth'));
    $this->assertSame('border-radius', UtilikitRules::getCssPropertyName('borderRadius'));
    $this->assertSame('background-color', UtilikitRules::getCssPropertyName('backgroundColor'));
    $this->assertSame('flex-direction', UtilikitRules::getCssPropertyName('flexDirection'));
    $this->assertSame('z-index', UtilikitRules::getCssPropertyName('zIndex'));
  }

  /**
   * Tests getCssPropertyName returns original when no mapping exists.
   *
   * @covers ::getCssPropertyName
   */
  public function testGetCssPropertyNameUnknown(): void {
    $this->assertSame('padding', UtilikitRules::getCssPropertyName('padding'));
    $this->assertSame('margin', UtilikitRules::getCssPropertyName('margin'));
    $this->assertSame('unknown-property', UtilikitRules::getCssPropertyName('unknown-property'));
  }

}
