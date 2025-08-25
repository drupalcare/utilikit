<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitCssGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Tests the UtilikitCssGenerator service.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Service\UtilikitCssGenerator
 */
class UtilikitCssGeneratorTest extends UnitTestCase {

  /**
   * The CSS generator service under test.
   *
   * @var \Drupal\utilikit\Service\UtilikitCssGenerator
   */
  protected UtilikitCssGenerator $cssGenerator;

  /**
   * Mock config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected ConfigFactoryInterface|MockObject $configFactory;

  /**
   * Mock configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig|\PHPUnit\Framework\MockObject\MockObject
   */
  protected ImmutableConfig|MockObject $config;

  /**
   * Mock logger.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected LoggerInterface|MockObject $logger;

  /**
   * Test utility classes for generation.
   *
   * @var array
   */
  protected array $testClasses = [
    'uk-pd--20',
    'uk-mg--t-10',
    'uk-bg--ff0000',
    'uk-tc--333333',
    'uk-dp--flex',
    'uk-md-pd--30',
    'uk-lg-mg--40',
  ];

  /**
   * Expected breakpoint definitions.
   *
   * @var array
   */
  protected array $testBreakpoints = [
    'sm' => 576,
    'md' => 768,
    'lg' => 992,
    'xl' => 1200,
    'xxl' => 1400,
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create config and config factory mocks.
    $this->config = $this->createMock(ImmutableConfig::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    // Set up config factory to return our config mock.
    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('utilikit.settings')
      ->willReturn($this->config);

    // Set up config mock with default values.
    $this->config->method('get')->willReturnCallback(function ($key) {
      $defaults = [
        'active_breakpoints' => $this->testBreakpoints,
        'optimize_css' => FALSE,
        'css_cache_ttl' => 3600,
      ];
      return $defaults[$key] ?? NULL;
    });

    // Create the CSS generator with ConfigFactoryInterface.
    $this->cssGenerator = new UtilikitCssGenerator(
      $this->configFactory,
      $this->logger
    );
  }

  /**
   * Tests basic CSS generation from utility classes.
   *
   * @covers ::generateCss
   */
  public function testGenerateCss() {
    $css = $this->cssGenerator->generateCss($this->testClasses);

    // Verify CSS is generated.
    $this->assertNotEmpty($css);

    // Check for specific class rules.
    $this->assertStringContainsString('.uk-pd--20', $css);
    $this->assertStringContainsString('padding: 20px', $css);

    $this->assertStringContainsString('.uk-mg--t-10', $css);
    $this->assertStringContainsString('margin-top: 10px', $css);

    $this->assertStringContainsString('.uk-bg--ff0000', $css);
    $this->assertStringContainsString('background-color: #ff0000', $css);

    $this->assertStringContainsString('.uk-dp--flex', $css);
    $this->assertStringContainsString('display: flex', $css);
  }

  /**
   * Tests responsive CSS generation.
   *
   * @covers ::generateCss
   */
  public function testGenerateResponsiveCss() {
    $responsiveClasses = [
      'uk-pd--20',
      'uk-md-pd--30',
      'uk-lg-pd--40',
    ];

    $css = $this->cssGenerator->generateCss($responsiveClasses);

    // Verify base class.
    $this->assertStringContainsString('.uk-pd--20', $css);
    $this->assertStringContainsString('padding: 20px', $css);

    // Verify responsive classes with media queries.
    $this->assertStringContainsString('@media (min-width: 768px)', $css);
    $this->assertStringContainsString('.uk-md-pd--30', $css);
    $this->assertStringContainsString('padding: 30px', $css);

    $this->assertStringContainsString('@media (min-width: 992px)', $css);
    $this->assertStringContainsString('.uk-lg-pd--40', $css);
    $this->assertStringContainsString('padding: 40px', $css);
  }

  /**
   * Tests color value parsing and generation.
   *
   * @covers ::generateCss
   * @dataProvider providerColorValues
   */
  public function testColorValueGeneration($class, $expectedCss) {
    $css = $this->cssGenerator->generateCss([$class]);
    $this->assertStringContainsString($expectedCss, $css);
  }

  /**
   * Data provider for color value tests.
   *
   * @return array
   *   Test cases for color value generation.
   */
  public static function providerColorValues(): array {
    return [
      'hex 6-digit' => ['uk-bg--ff0000', 'background-color: #ff0000'],
      'hex 3-digit' => ['uk-bg--f00', 'background-color: #ff0000'],
      'hex with alpha' => ['uk-bg--ff0000-50', 'background-color: rgba(255, 0, 0, 0.5)'],
      'text color' => ['uk-tc--007bff', 'color: #007bff'],
    ];
  }

  /**
   * Tests side value parsing and generation.
   *
   * @covers ::generateCss
   * @dataProvider providerSideValues
   */
  public function testSideValueGeneration($class, $expectedCss) {
    $css = $this->cssGenerator->generateCss([$class]);
    $this->assertStringContainsString($expectedCss, $css);
  }

  /**
   * Data provider for side value tests.
   *
   * @return array
   *   Test cases for side value generation.
   */
  public static function providerSideValues(): array {
    return [
      'top side' => ['uk-pd--t-20', 'padding-top: 20px'],
      'bottom side' => ['uk-mg--b-15', 'margin-bottom: 15px'],
      'horizontal sides' => ['uk-pd--x-10', 'padding-left: 10px; padding-right: 10px'],
      'vertical sides' => ['uk-mg--y-25', 'margin-top: 25px; margin-bottom: 25px'],
      'four values' => ['uk-pd--10-20-30-40', 'padding: 10px 20px 30px 40px'],
    ];
  }

  /**
   * Tests CSS optimization features.
   *
   * @covers ::generateCss
   */
  public function testCssOptimization() {
    // Enable optimization in config.
    $this->config->method('get')->willReturnCallback(function ($key) {
      $defaults = [
        'active_breakpoints' => $this->testBreakpoints,
        'optimize_css' => TRUE,
        'css_cache_ttl' => 3600,
      ];
      return $defaults[$key] ?? NULL;
    });

    $css = $this->cssGenerator->generateCss($this->testClasses);

    // Optimized CSS should be minified (no extra whitespace)
    $this->assertNotEmpty($css);
    // This is a basic test - actual optimization logic would be tested more
    // thoroughly.
  }

  /**
   * Tests invalid class filtering.
   *
   * @covers ::generateCss
   */
  public function testInvalidClassFiltering() {
    $mixedClasses = [
    // Valid.
      'uk-pd--20',
    // Invalid.
      'invalid-class',
    // Valid.
      'uk-mg--10',
    // Invalid.
      'not-utilikit',
    ];

    $css = $this->cssGenerator->generateCss($mixedClasses);

    // Should only contain valid classes.
    $this->assertStringContainsString('.uk-pd--20', $css);
    $this->assertStringContainsString('.uk-mg--10', $css);

    // Should not contain invalid classes.
    $this->assertStringNotContainsString('invalid-class', $css);
    $this->assertStringNotContainsString('not-utilikit', $css);
  }

  /**
   * Tests empty class array handling.
   *
   * @covers ::generateCss
   */
  public function testEmptyClassArray() {
    $css = $this->cssGenerator->generateCss([]);

    // Should return empty CSS or minimal CSS structure.
    $this->assertIsString($css);
  }

  /**
   * Tests duplicate class deduplication.
   *
   * @covers ::generateCss
   */
  public function testDuplicateClassDeduplication() {
    $duplicateClasses = [
      'uk-pd--20',
    // Duplicate.
      'uk-pd--20',
      'uk-mg--10',
    // Duplicate.
      'uk-mg--10',
    ];

    $css = $this->cssGenerator->generateCss($duplicateClasses);

    // Should only generate each class once.
    $occurrences = substr_count($css, '.uk-pd--20');
    $this->assertEquals(1, $occurrences, 'Duplicate classes should be deduplicated');
  }

  /**
   * Tests CSS ordering and organization.
   *
   * @covers ::generateCss
   */
  public function testCssOrdering() {
    $css = $this->cssGenerator->generateCss($this->testClasses);

    // Basic classes should come before responsive variants.
    $basePadding = strpos($css, '.uk-pd--20');
    $responsivePadding = strpos($css, '.uk-md-pd--30');

    if ($basePadding !== FALSE && $responsivePadding !== FALSE) {
      $this->assertLessThan($responsivePadding, $basePadding, 'Base classes should come before responsive variants');
    }
  }

  /**
   * Tests error handling with invalid input.
   *
   * @covers ::generateCss
   */
  public function testErrorHandling() {
    // Test with null input.
    $css = $this->cssGenerator->generateCss(NULL);
    $this->assertIsString($css);

    // Test with non-array input (if applicable)
    // This depends on how your service handles type checking.
  }

}
