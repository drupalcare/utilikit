<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\utilikit\Service\UtilikitContentScanner;
use Drupal\utilikit\Service\UtilikitCssGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;

/**
 * Tests for the UtilikitCssGenerator service.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Service\UtilikitCssGenerator
 *
 * Aligned with the current UtilikitCssGenerator implementation.
 */
#[CoversClass(\Drupal\utilikit\Service\UtilikitCssGenerator::class)]
#[Group('utilikit')]
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
   * @var \Drupal\Core\Config\ImmutableConfig|\PHPUnit\Framework\MockObject\Stub
   */
  protected ImmutableConfig&Stub $settingsConfig;

  /**
   * The cache backend mock.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface&\PHPUnit\Framework\MockObject\Stub
   */
  protected CacheBackendInterface&Stub $cache;

  /**
   * The content scanner mock.
   *
   * @var \Drupal\utilikit\Service\UtilikitContentScanner|\PHPUnit\Framework\MockObject\Stub
   */
  protected UtilikitContentScanner&Stub $contentScanner;

  /**
   * The logger mock.
   *
   * @var \Psr\Log\LoggerInterface&\PHPUnit\Framework\MockObject\Stub
   */
  protected LoggerInterface&Stub $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->settingsConfig = $this->createStub(ImmutableConfig::class);
    $this->cache = $this->createStub(CacheBackendInterface::class);
    $this->contentScanner = $this->createStub(UtilikitContentScanner::class);
    $this->logger = $this->createStub(LoggerInterface::class);

    // UtilikitCssGenerator expects to read utilikit.settings for active
    // breakpoints.
    $this->configFactory
      ->method('get')
      ->with('utilikit.settings')
      ->willReturn($this->settingsConfig);

    $this->settingsConfig
      ->method('get')
      ->willReturnCallback(static function (string $key) {
        return match ($key) {
          'active_breakpoints' => [
            'sm' => TRUE,
            'md' => TRUE,
            'lg' => TRUE,
            'xl' => TRUE,
            'xxl' => TRUE,
          ],
          'use_important' => TRUE,
          'rendering_mode' => 'inline',
          default => NULL,
        };
      });

    $this->buildSubject();
  }

  /**
   * Builds the subject under test from the current collaborators.
   *
   * Collaborators are stubs by default; a test that asserts calls on one
   * replaces it with a mock and calls this again.
   */
  private function buildSubject(): void {
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
    $this->cache = $this->createMock(CacheBackendInterface::class);
    $this->buildSubject();

    $classes = [
      'uk-pd--10',
      '',
      NULL,
      'not-utilikit',
      'uk-pd--10',
    ];

    $this->contentScanner
      ->method('isValidUtilityClass')
      ->willReturnCallback(static function (string $class): bool {
        return str_starts_with($class, 'uk-');
      });

    $this->cache
      ->method('get')
      ->willReturn(FALSE);

    $this->cache
      ->expects($this->once())
      ->method('set')
      ->with(
        $this->callback(static fn ($value): bool => is_string($value)),
        $this->callback(static fn ($value): bool => is_string($value)),
        $this->anything(),
        $this->callback(static function (array $tags): bool {
          return in_array(UtilikitConstants::CACHE_TAG_CSS, $tags, TRUE);
        })
      );

    $css = $this->cssGenerator->generateCssFromClasses($classes);

    $this->assertIsString($css);
    $this->assertNotSame('', $css);
    // Valid utility class should appear as a selector.
    $this->assertStringContainsString('.uk-pd--10', $css);
    // Non-utilikit class should not become a selector.
    $this->assertStringNotContainsString('not-utilikit', $css);
  }

  /**
   * Tests generateCssFromClasses uses cache when available.
   *
   * @covers ::generateCssFromClasses
   */
  public function testGenerateCssFromClassesUsesCacheWhenAvailable(): void {
    $this->cache = $this->createMock(CacheBackendInterface::class);
    $this->buildSubject();

    $classes = ['uk-pd--10'];

    $cacheHit = (object) ['data' => '/* cached css */'];

    $this->contentScanner
      ->method('isValidUtilityClass')
      ->willReturn(TRUE);

    $this->cache
      ->expects($this->exactly(2))
      ->method('get')
      ->with($this->callback(static function (string $cid): bool {
        return str_starts_with($cid, 'utilikit:css:');
      }))
      ->willReturnOnConsecutiveCalls(FALSE, $cacheHit);

    $this->cache
      ->expects($this->once())
      ->method('set')
      ->with(
        $this->callback(static function (string $cid): bool {
          return str_starts_with($cid, 'utilikit:css:');
        }),
        $this->callback(static fn ($value): bool => is_string($value)),
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
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->buildSubject();

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
