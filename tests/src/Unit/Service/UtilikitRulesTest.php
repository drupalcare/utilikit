<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitRules;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Tests the UtilikitRules service.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Service\UtilikitRules
 */
class UtilikitRulesTest extends UnitTestCase {

  /**
   * The rules service under test.
   *
   * @var \Drupal\utilikit\Service\UtilikitRules
   */
  protected UtilikitRules $rules;

  /**
   * Mock config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected ConfigFactoryInterface&MockObject $configFactory;

  /**
   * Mock logger.
   *
   * @var \Psr\Log\LoggerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected LoggerInterface&MockObject $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    // Setup default config.
    $config = $this->createMock(ImmutableConfig::class);
    $config->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['rules_enabled', TRUE],
        ['custom_rules', []],
        ['rule_priority', 'custom_first'],
        ['rule_cache_enabled', TRUE],
      ]);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('utilikit.settings')
      ->willReturn($config);

    $this->rules = new UtilikitRules(
      $this->configFactory,
      $this->logger
    );
  }

  /**
   * Tests getRule for valid utility classes.
   *
   * @covers ::getRule
   * @dataProvider providerValidUtilityClasses
   */
  public function testGetRuleValid(string $property, string $value, array $expected): void {
    $rule = $this->rules->getRule($property, $value);

    $this->assertIsArray($rule);
    $this->assertArrayHasKey('property', $rule);
    $this->assertArrayHasKey('value', $rule);
    $this->assertEquals($expected['property'], $rule['property']);
    $this->assertEquals($expected['value'], $rule['value']);
  }

  /**
   * Data provider for valid utility classes.
   *
   * @return array <int, mixed>
   *   The data sets used for testing providerValidUtilityClasses().
   */
  public function providerValidUtilityClasses(): array {
    return [
      ['pd', '20', ['property' => 'padding', 'value' => '20px']],
      ['pd', '10-20', ['property' => 'padding', 'value' => '10px 20px']],
      ['pd', '10-20-30', ['property' => 'padding', 'value' => '10px 20px 30px']],
      ['pd', '10-20-30-40', ['property' => 'padding', 'value' => '10px 20px 30px 40px']],
      ['mg', '20', ['property' => 'margin', 'value' => '20px']],
      ['mg', 'auto', ['property' => 'margin', 'value' => 'auto']],
      ['text', 'center', ['property' => 'text-align', 'value' => 'center']],
      ['bg', 'primary', ['property' => 'background-color', 'value' => 'var(--color-primary)']],
      ['flex', 'row', ['property' => 'flex-direction', 'value' => 'row']],
      ['display', 'block', ['property' => 'display', 'value' => 'block']],
      ['w', '100', ['property' => 'width', 'value' => '100px']],
      ['w', '50p', ['property' => 'width', 'value' => '50%']],
      ['h', 'auto', ['property' => 'height', 'value' => 'auto']],
      ['z', '10', ['property' => 'z-index', 'value' => '10']],
    ];
  }

  /**
   * Tests getRule for invalid properties.
   *
   * @covers ::getRule
   */
  public function testGetRuleInvalid(): void {
    $rule = $this->rules->getRule('invalid', '20');
    $this->assertNull($rule);

    $rule = $this->rules->getRule('', '20');
    $this->assertNull($rule);
  }

  /**
   * Tests parseValue method.
   *
   * @covers ::parseValue
   * @dataProvider providerParseValue
   */
  public function testParseValue(string $value, string $type, string $expected): void {
    $parsed = $this->rules->parseValue($value, $type);
    $this->assertEquals($expected, $parsed);
  }

  /**
   * Data provider for parseValue tests.
   *
   * @return array<int, string>
   *   The data sets used for testing parseValue().
   */
  public function providerParseValue(): array {
    return [
      // Numeric values.
      ['20', 'size', '20px'],
      ['0', 'size', '0'],
      ['-10', 'size', '-10px'],
      ['100', 'size', '100px'],

      // Percentage values.
      ['50p', 'size', '50%'],
      ['100p', 'size', '100%'],
      ['33p', 'size', '33%'],

      // Auto values.
      ['auto', 'size', 'auto'],
      ['AUTO', 'size', 'auto'],

      // Color values.
      ['primary', 'color', 'var(--color-primary)'],
      ['secondary', 'color', 'var(--color-secondary)'],
      ['success', 'color', 'var(--color-success)'],
      ['danger', 'color', 'var(--color-danger)'],
      ['warning', 'color', 'var(--color-warning)'],
      ['info', 'color', 'var(--color-info)'],

      // Text values.
      ['center', 'text', 'center'],
      ['left', 'text', 'left'],
      ['right', 'text', 'right'],

      // Multiple values.
      ['10-20', 'spacing', '10px 20px'],
      ['10-20-30', 'spacing', '10px 20px 30px'],
      ['10-20-30-40', 'spacing', '10px 20px 30px 40px'],
      ['0-auto', 'spacing', '0 auto'],
      ['auto-0', 'spacing', 'auto 0'],
    ];
  }

  /**
   * Tests custom rules functionality.
   *
   * @covers ::getRule
   * @covers ::applyCustomRules
   */
  public function testCustomRules(): void {
    // Setup config with custom rules.
    $config = $this->createMock(ImmutableConfig::class);
    $config->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['rules_enabled', TRUE],
        ['custom_rules', [
          'custom' => [
            'property' => 'custom-property',
            'value_template' => 'custom-{value}',
          ],
          'override-pd' => [
            'property' => 'padding',
            'value_template' => '{value}rem',
          ],
        ],
        ],
        ['rule_priority', 'custom_first'],
        ['rule_cache_enabled', FALSE],
      ]);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('utilikit.settings')
      ->willReturn($config);

    // Recreate rules with custom config.
    $rules = new UtilikitRules(
      $this->configFactory,
      $this->logger
    );

    // Test custom rule.
    $rule = $rules->getRule('custom', 'test');
    $this->assertNotNull($rule);
    $this->assertEquals('custom-property', $rule['property']);
    $this->assertEquals('custom-test', $rule['value']);

    // Test override rule (custom_first priority).
    $rule = $rules->getRule('override-pd', '2');
    $this->assertNotNull($rule);
    $this->assertEquals('padding', $rule['property']);
    $this->assertEquals('2rem', $rule['value']);
  }

  /**
   * Tests rule priority settings.
   *
   * @covers ::getRule
   */
  public function testRulePriority(): void {
    // Test with default_first priority.
    $config = $this->createMock(ImmutableConfig::class);
    $config->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['rules_enabled', TRUE],
        ['custom_rules', [
          'pd' => [
            'property' => 'padding',
            'value_template' => '{value}rem',
          ],
        ],
        ],
        ['rule_priority', 'default_first'],
        ['rule_cache_enabled', FALSE],
      ]);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('utilikit.settings')
      ->willReturn($config);

    $rules = new UtilikitRules(
      $this->configFactory,
      $this->logger
    );

    // Default rule should win.
    $rule = $rules->getRule('pd', '20');
    $this->assertNotNull($rule);
    $this->assertEquals('padding', $rule['property']);
    // Default rule uses px.
    $this->assertEquals('20px', $rule['value']);
  }

  /**
   * Tests getAllRules method.
   *
   * @covers ::getAllRules
   */
  public function testGetAllRules(): void {
    $allRules = $this->rules->getAllRules();

    $this->assertIsArray($allRules);
    $this->assertArrayHasKey('pd', $allRules);
    $this->assertArrayHasKey('mg', $allRules);
    $this->assertArrayHasKey('text', $allRules);
    $this->assertArrayHasKey('bg', $allRules);
    $this->assertArrayHasKey('flex', $allRules);
  }

  /**
   * Tests validateRule method.
   *
   * @covers ::validateRule
   */
  public function testValidateRule(): void {
    // Valid rules.
    $this->assertTrue($this->rules->validateRule('pd', '20'));
    $this->assertTrue($this->rules->validateRule('mg', '10-20'));
    $this->assertTrue($this->rules->validateRule('text', 'center'));
    $this->assertTrue($this->rules->validateRule('bg', 'primary'));

    // Invalid rules.
    $this->assertFalse($this->rules->validateRule('invalid', '20'));
    $this->assertFalse($this->rules->validateRule('pd', ''));
    $this->assertFalse($this->rules->validateRule('', 'value'));
  }

  /**
   * Tests special value handling.
   *
   * @covers ::getRule
   */
  public function testSpecialValues(): void {
    // Test inherit value.
    $rule = $this->rules->getRule('pd', 'inherit');
    $this->assertNotNull($rule);
    $this->assertEquals('inherit', $rule['value']);

    // Test initial value.
    $rule = $this->rules->getRule('mg', 'initial');
    $this->assertNotNull($rule);
    $this->assertEquals('initial', $rule['value']);

    // Test unset value.
    $rule = $this->rules->getRule('text', 'unset');
    $this->assertNotNull($rule);
    $this->assertEquals('unset', $rule['value']);

    // Test var() custom property.
    $rule = $this->rules->getRule('bg', 'var(--custom-color)');
    $this->assertNotNull($rule);
    $this->assertEquals('var(--custom-color)', $rule['value']);
  }

  /**
   * Tests complex spacing values.
   *
   * @covers ::parseValue
   */
  public function testComplexSpacingValues(): void {
    // Test negative values.
    $parsed = $this->rules->parseValue('-10', 'size');
    $this->assertEquals('-10px', $parsed);

    // Test mixed values.
    $parsed = $this->rules->parseValue('10-auto-20-auto', 'spacing');
    $this->assertEquals('10px auto 20px auto', $parsed);

    // Test percentage in spacing.
    $parsed = $this->rules->parseValue('50p-auto', 'spacing');
    $this->assertEquals('50% auto', $parsed);
  }

  /**
   * Tests rule caching.
   *
   * @covers ::getRule
   */
  public function testRuleCaching(): void {
    // First call should process the rule.
    $rule1 = $this->rules->getRule('pd', '20');
    $this->assertNotNull($rule1);

    // Second call should return cached result.
    $rule2 = $this->rules->getRule('pd', '20');
    $this->assertNotNull($rule2);
    $this->assertEquals($rule1, $rule2);
  }

  /**
   * Tests error handling.
   *
   * @covers ::getRule
   */
  public function testErrorHandling(): void {
    // Test with malformed custom rules.
    $config = $this->createMock(ImmutableConfig::class);
    $config->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['rules_enabled', TRUE],
        ['custom_rules', [
          'broken' => [
            // Missing required fields.
          ],
        ],
        ],
        ['rule_priority', 'custom_first'],
        ['rule_cache_enabled', FALSE],
      ]);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('utilikit.settings')
      ->willReturn($config);

    $this->logger->expects($this->once())
      ->method('error')
      ->with($this->stringContains('Invalid custom rule'));

    $rules = new UtilikitRules(
      $this->configFactory,
      $this->logger
    );

    $rule = $rules->getRule('broken', 'test');
    $this->assertNull($rule);
  }

  /**
   * Tests rules disabled configuration.
   *
   * @covers ::getRule
   */
  public function testRulesDisabled(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['rules_enabled', FALSE],
        ['custom_rules', []],
        ['rule_priority', 'custom_first'],
        ['rule_cache_enabled', TRUE],
      ]);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('utilikit.settings')
      ->willReturn($config);

    $rules = new UtilikitRules(
      $this->configFactory,
      $this->logger
    );

    $rule = $rules->getRule('pd', '20');
    $this->assertNull($rule);
  }

}
