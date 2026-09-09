<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;

/**
 * Tests UtilikitService class.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Service\UtilikitService
 */
#[Group('utilikit')]
class UtilikitServiceTest extends UnitTestCase {

  /**
   * The UtilikitService instance under test.
   *
   * @var \Drupal\utilikit\Service\UtilikitService
   */
  protected $utilikitService;

  /**
   * Mock config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * Mock config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config = $this->createMock(ImmutableConfig::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('utilikit.settings')
      ->willReturn($this->config);

    $this->utilikitService = new UtilikitService($this->configFactory);
  }

  /**
   * Tests service construction.
   *
   * @covers ::__construct
   */
  public function testConstruct() {
    $service = new UtilikitService($this->configFactory);
    $this->assertInstanceOf(UtilikitService::class, $service);
  }

  /**
   * Tests getSettings returns config object.
   *
   * @covers ::getSettings
   */
  public function testGetSettings() {
    $settings = $this->utilikitService->getSettings();
    $this->assertSame($this->config, $settings);
  }

  /**
   * Tests getRenderingMode with valid values.
   *
   * @covers ::getRenderingMode
   */
  #[DataProvider('renderingModeProvider')]
  public function testGetRenderingModeValid($configValue, $expectedResult) {
    $this->config->expects($this->once())
      ->method('get')
      ->with('rendering_mode')
      ->willReturn($configValue);

    $result = $this->utilikitService->getRenderingMode();
    $this->assertEquals($expectedResult, $result);
  }

  /**
   * Data provider for rendering mode tests.
   *
   * @return array
   *   Test cases for rendering mode values.
   */
  public static function renderingModeProvider(): array {
    return [
      'inline mode' => ['inline', 'inline'],
      'static mode' => ['static', 'static'],
      'null defaults to inline' => [NULL, 'inline'],
      'empty string defaults to inline' => ['', 'inline'],
    ];
  }

  /**
   * Tests getRenderingMode with invalid values returns default.
   *
   * @covers ::getRenderingMode
   */
  public function testGetRenderingModeInvalid() {
    $this->config->expects($this->once())
      ->method('get')
      ->with('rendering_mode')
      ->willReturn('invalid_mode');

    $result = $this->utilikitService->getRenderingMode();
    $this->assertEquals('inline', $result);
  }

  /**
   * Tests getRenderingMode with null returns default.
   *
   * @covers ::getRenderingMode
   */
  public function testGetRenderingModeNull() {
    $this->config->expects($this->once())
      ->method('get')
      ->with('rendering_mode')
      ->willReturn(NULL);

    $result = $this->utilikitService->getRenderingMode();
    $this->assertEquals('inline', $result);
  }

  /**
   * Tests getScope with valid values.
   *
   * @covers ::getScope
   */
  #[DataProvider('scopeProvider')]
  public function testGetScopeValid($configValue, $expectedResult) {
    $this->config->expects($this->once())
      ->method('get')
      ->with('scope')
      ->willReturn($configValue);

    $result = $this->utilikitService->getScope();
    $this->assertEquals($expectedResult, $result);
  }

  /**
   * Data provider for scope tests.
   *
   * @return array
   *   Test cases for scope values.
   */
  public static function scopeProvider(): array {
    return [
      'global scope' => ['global', 'global'],
      'content scope' => ['content', 'content'],
      'null defaults to global' => [NULL, 'global'],
      'empty string defaults to global' => ['', 'global'],
    ];
  }

  /**
   * Tests getScope with invalid values returns default.
   *
   * @covers ::getScope
   */
  public function testGetScopeInvalid() {
    $this->config->expects($this->once())
      ->method('get')
      ->with('scope')
      ->willReturn('invalid_scope');

    $result = $this->utilikitService->getScope();
    $this->assertEquals('global', $result);
  }

  /**
   * Tests isStaticMode method.
   *
   * @covers ::isStaticMode
   */
  #[DataProvider('staticModeProvider')]
  public function testIsStaticMode($configValue, $expectedResult) {
    $this->config->expects($this->once())
      ->method('get')
      ->with('rendering_mode')
      ->willReturn($configValue);

    $result = $this->utilikitService->isStaticMode();
    $this->assertEquals($expectedResult, $result);
  }

  /**
   * Data provider for static mode tests.
   *
   * @return array
   *   Test cases for static mode detection.
   */
  public static function staticModeProvider(): array {
    return [
      'static mode returns true' => ['static', TRUE],
      'inline mode returns false' => ['inline', FALSE],
      'null defaults to false' => [NULL, FALSE],
      'invalid defaults to false' => ['invalid', FALSE],
    ];
  }

  /**
   * Tests isDevMode method.
   *
   * @covers ::isDevMode
   */
  #[DataProvider('devModeProvider')]
  public function testIsDevMode($configValue, $expectedResult) {
    $this->config->expects($this->once())
      ->method('get')
      ->with('dev_mode')
      ->willReturn($configValue);

    $result = $this->utilikitService->isDevMode();
    $this->assertEquals($expectedResult, $result);
  }

  /**
   * Data provider for dev mode tests.
   *
   * @return array
   *   Test cases for dev mode detection.
   */
  public static function devModeProvider(): array {
    return [
      'true value' => [TRUE, TRUE],
      'false value' => [FALSE, FALSE],
      'string "1"' => ['1', TRUE],
      'string "0"' => ['0', FALSE],
      'null value' => [NULL, FALSE],
    ];
  }

  /**
   * Tests getActiveBreakpoints method.
   *
   * @covers ::getActiveBreakpoints
   */
  public function testGetActiveBreakpoints() {
    $testBreakpoints = [
      'sm' => 'sm',
      'md' => 'md',
      'lg' => 'lg',
      'xl' => 'xl',
      'xxl' => 'xxl',
    ];

    $this->config->expects($this->once())
      ->method('get')
      ->with('active_breakpoints')
      ->willReturn($testBreakpoints);

    $result = $this->utilikitService->getActiveBreakpoints();
    $this->assertEquals(['sm', 'md', 'lg', 'xl', 'xxl'], $result);
  }

  /**
   * Tests getActiveBreakpoints with filtered breakpoints.
   *
   * @covers ::getActiveBreakpoints
   */
  public function testGetActiveBreakpointsFiltered() {
    $testBreakpoints = [
    // Disabled.
      'sm' => '',
    // Enabled.
      'md' => 'md',
    // Enabled.
      'lg' => 'lg',
    // Disabled.
      'xl' => FALSE,
    // Enabled.
      'xxl' => 'xxl',
    ];

    $this->config->expects($this->once())
      ->method('get')
      ->with('active_breakpoints')
      ->willReturn($testBreakpoints);

    $result = $this->utilikitService->getActiveBreakpoints();
    $this->assertEquals(['md', 'lg', 'xxl'], $result);
  }

  /**
   * Tests shouldUpdateOnEntitySave method.
   *
   * @covers ::shouldUpdateOnEntitySave
   */
  #[DataProvider('entityUpdateProvider')]
  public function testShouldUpdateOnEntitySave($entityType, $configKey, $configValue, $expectedResult) {
    if ($configKey) {
      $this->config->expects($this->once())
        ->method('get')
        ->with($configKey)
        ->willReturn($configValue);
    }

    $result = $this->utilikitService->shouldUpdateOnEntitySave($entityType);
    $this->assertEquals($expectedResult, $result);
  }

  /**
   * Data provider for entity update tests.
   *
   * @return array
   *   Test cases for entity update settings.
   */
  public static function entityUpdateProvider(): array {
    return [
      'node enabled' => ['node', 'update_on_node_save', TRUE, TRUE],
      'node disabled' => ['node', 'update_on_node_save', FALSE, FALSE],
      'block_content enabled' => ['block_content', 'update_on_block_save', TRUE, TRUE],
      'block_content disabled' => ['block_content', 'update_on_block_save', FALSE, FALSE],
      'paragraph enabled' => ['paragraph', 'update_on_paragraph_save', TRUE, TRUE],
      'paragraph disabled' => ['paragraph', 'update_on_paragraph_save', FALSE, FALSE],
      'unknown entity type' => ['unknown_type', NULL, NULL, FALSE],
    ];
  }

  /**
   * Tests getBreakpointValues method.
   *
   * @covers ::getBreakpointValues
   */
  public function testGetBreakpointValues() {
    $result = $this->utilikitService->getBreakpointValues();

    // Should return the constant values.
    $expected = [
      'sm' => 576,
      'md' => 768,
      'lg' => 992,
      'xl' => 1200,
      'xxl' => 1400,
    ];

    $this->assertEquals($expected, $result);
  }

  /**
   * Tests getDebounceMs method.
   *
   * @covers ::getDebounceMs
   */
  #[DataProvider('debounceProvider')]
  public function testGetDebounceMs($configValue, $expectedResult) {
    $this->config->expects($this->once())
      ->method('get')
      ->with('debounce')
      ->willReturn($configValue);

    $result = $this->utilikitService->getDebounceMs();
    $this->assertEquals($expectedResult, $result);
  }

  /**
   * Data provider for debounce tests.
   *
   * @return array
   *   Test cases for debounce values.
   */
  public static function debounceProvider(): array {
    return [
      'default value' => [NULL, 50],
      'custom value' => [100, 100],
      'zero value' => [0, 0],
      'string number' => ['75', 75],
    ];
  }

  /**
   * Tests getLogLevel method.
   *
   * @covers ::getLogLevel
   */
  public function testGetLogLevel() {
    $this->config->expects($this->once())
      ->method('get')
      ->with('log_level')
      ->willReturn('detailed');

    $result = $this->utilikitService->getLogLevel();
    $this->assertEquals('detailed', $result);
  }

  /**
   * Tests getLogLevel with default value.
   *
   * @covers ::getLogLevel
   */
  public function testGetLogLevelDefault() {
    $this->config->expects($this->once())
      ->method('get')
      ->with('log_level')
      ->willReturn(NULL);

    $result = $this->utilikitService->getLogLevel();
    $this->assertEquals('warnings', $result);
  }

  /**
   * Tests boolean configuration methods.
   *
   * @covers ::shouldShowPageErrors
   * @covers ::shouldEnableTransitions
   */
  #[DataProvider('booleanConfigProvider')]
  public function testBooleanConfigMethods($method, $configKey, $configValue, $expectedResult) {
    $this->config->expects($this->once())
      ->method('get')
      ->with($configKey)
      ->willReturn($configValue);

    $result = $this->utilikitService->$method();
    $this->assertEquals($expectedResult, $result);
  }

  /**
   * Data provider for boolean configuration tests.
   *
   * @return array
   *   Test cases for boolean configuration methods.
   */
  public static function booleanConfigProvider(): array {
    return [
      'show page errors true' => ['shouldShowPageErrors', 'show_page_errors', TRUE, TRUE],
      'show page errors false' => ['shouldShowPageErrors', 'show_page_errors', FALSE, FALSE],
      'enable transitions true' => ['shouldEnableTransitions', 'enable_transitions', TRUE, TRUE],
      'enable transitions false' => ['shouldEnableTransitions', 'enable_transitions', FALSE, FALSE],
    ];
  }

}
