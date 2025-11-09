<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\utilikit\Service\UtilikitContentScanner;
use Drupal\utilikit\Service\UtilikitCssGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Tests for the UtilikitCssGenerator service.
 *
 * @coversDefaultClass \Drupal\utilikit\Service\UtilikitCssGenerator
 *
 * Aligned with the current UtilikitCssGenerator implementation.
 */
final class UtilikitCssGeneratorTest extends UnitTestCase {

  /**
   * The CSS generator under test.
   *
   * @var \Drupal\utilikit\Service\UtilikitCssGenerator
   */
  protected UtilikitCssGenerator $cssGenerator;

  /**
   * The config factory mock.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected ConfigFactoryInterface&MockObject $configFactory;

  /**
   * The settings config mock.
   *
   * @var \Drupal\Core\Config\ImmutableConfig|\PHPUnit\Framework\MockObject\MockObject
   */
  protected ImmutableConfig&MockObject $settingsConfig;

  /**
   * The cache backend mock.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected CacheBackendInterface&MockObject $cache;

  /**
   * The content scanner mock.
   *
   * @var \Drupal\utilikit\Service\UtilikitContentScanner|\PHPUnit\Framework\MockObject\MockObject
   */
  protected UtilikitContentScanner&MockObject $contentScanner;

  /**
   * The logger mock.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected LoggerInterface&MockObject $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->settingsConfig = $this->createMock(ImmutableConfig::class);
    $this->cache = $this->createMock(CacheBackendInterface::class);
    $this->contentScanner = $this->createMock(UtilikitContentScanner::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    // UtilikitCssGenerator expects to read utilikit.settings for active
    // breakpoints.
    $this->configFactory
      ->method('get')
      ->with('utilikit.settings')
      ->willReturn($this->settingsConfig);

    $this->settingsConfig
      ->method('get')
      ->with('active_breakpoints')
      ->willReturn([
        'sm' => TRUE,
        'md' => TRUE,
        'lg' => TRUE,
        'xl' => TRUE,
        'xxl' => TRUE,
      ]);

    $this->cssGenerator = new UtilikitCssGenerator(
      $this->configFactory,
      $this->cache,
      $this->contentScanner,
      $this->logger,
    );
  }

  /**
   * Tests generateCssFromClasses filters invalid and duplicate values.
   *
   * @covers ::generateCssFromClasses
   */
  public function testGenerateCssFromClassesFiltersInvalidAndDuplicateValues(): void {
    $classes = [
      'uk-m--10',
      '',
      NULL,
      'not-utilikit',
      'uk-m--10',
    ];

    $this->contentScanner
      ->method('isValidUtilityClass')
      ->willReturnCallback(static function (string $class): bool {
        return substr($class, 0, 3) === 'uk-';
      });

    $this->cache
      ->method('get')
      ->willReturn(FALSE);

    $this->cache
      ->expects($this->once())
      ->method('set')
      ->with(
        $this->isType('string'),
        $this->isType('string'),
        $this->anything(),
        $this->callback(static function (array $tags): bool {
          return in_array(UtilikitConstants::CACHE_TAG_CSS, $tags, TRUE);
        })
      );

    $css = $this->cssGenerator->generateCssFromClasses($classes);

    $this->assertIsString($css);
    $this->assertNotSame('', $css);
    // Valid utility class should appear as a selector.
    $this->assertStringContainsString('.uk-m--10', $css);
    // Non-utilikit class should not become a selector.
    $this->assertStringNotContainsString('not-utilikit', $css);
  }

  /**
   * Tests generateCssFromClasses uses cache when available.
   *
   * @covers ::generateCssFromClasses
   */
  public function testGenerateCssFromClassesUsesCacheWhenAvailable(): void {
    $classes = ['uk-m--10'];
    sort($classes);
    $cid = 'utilikit:css:' . md5(serialize($classes));

    $cacheHit = (object) ['data' => '/* cached css */'];

    $this->contentScanner
      ->method('isValidUtilityClass')
      ->willReturn(TRUE);

    $this->cache
      ->expects($this->exactly(2))
      ->method('get')
      ->with($cid)
      ->willReturnOnConsecutiveCalls(FALSE, $cacheHit);

    $this->cache
      ->expects($this->once())
      ->method('set')
      ->with(
        $cid,
        $this->isType('string'),
        $this->anything(),
        $this->callback(static function (array $tags): bool {
          return in_array(UtilikitConstants::CACHE_TAG_CSS, $tags, TRUE);
        })
      );

    $first = $this->cssGenerator->generateCssFromClasses($classes);
    $this->assertIsString($first);
    $this->assertNotSame('', $first);

    $second = $this->cssGenerator->generateCssFromClasses($classes);
    $this->assertSame('/* cached css */', $second);
  }

  /**
   * Tests generateCssFromClasses throws when class count exceeds limit.
   *
   * @covers ::generateCssFromClasses
   */
  public function testGenerateCssFromClassesThrowsWhenClassCountExceedsLimit(): void {
    $classes = [];
    $limit = UtilikitConstants::MAX_CLASSES_WARNING_THRESHOLD + 1;

    for ($i = 0; $i < $limit; $i++) {
      $classes[] = 'uk-m--' . $i;
    }

    $this->contentScanner
      ->method('isValidUtilityClass')
      ->willReturn(TRUE);

    $this->logger
      ->expects($this->once())
      ->method('error')
      ->with(
        $this->stringContains('Class count exceeds hard limit'),
        $this->arrayHasKey('@count')
      );

    $this->expectException(\RuntimeException::class);

    $this->cssGenerator->generateCssFromClasses($classes);
  }

}
