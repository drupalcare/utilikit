<?php

namespace Drupal\Tests\utilikit\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtiliKitCacheManager;
use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Theme\Registry;
use Drupal\Core\Asset\AssetCollectionOptimizerInterface;

/**
 * Tests the UtiliKitCacheManager service.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Service\UtiliKitCacheManager
 */
class UtiliKitCacheManagerTest extends UnitTestCase {

  /**
   * The cache manager service under test.
   *
   * @var \Drupal\utilikit\Service\UtiliKitCacheManager
   */
  protected UtiliKitCacheManager $cacheManager;

  /**
   * Mock render cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected CacheBackendInterface|MockObject $renderCache;

  /**
   * Mock cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $cacheTagsInvalidator;

  /**
   * Mock state service.
   *
   * @var \Drupal\Core\State\StateInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $state;

  /**
   * Mock theme registry.
   *
   * @var \Drupal\Core\Theme\Registry|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $themeRegistry;

  /**
   * Mock CSS optimizer.
   *
   * @var \Drupal\Core\Asset\AssetCollectionOptimizerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $cssOptimizer;

  /**
   * Mock JS optimizer.
   *
   * @var \Drupal\Core\Asset\AssetCollectionOptimizerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $jsOptimizer;

  /**
   * Mock page cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $pageCache;

  /**
   * Mock dynamic page cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $dynamicPageCache;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create all mocks.
    $this->renderCache = $this->createMock(CacheBackendInterface::class);
    $this->cacheTagsInvalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $this->state = $this->createMock(StateInterface::class);
    $this->themeRegistry = $this->createMock(Registry::class);
    $this->cssOptimizer = $this->createMock(AssetCollectionOptimizerInterface::class);
    $this->jsOptimizer = $this->createMock(AssetCollectionOptimizerInterface::class);
    $this->pageCache = $this->createMock(CacheBackendInterface::class);
    $this->dynamicPageCache = $this->createMock(CacheBackendInterface::class);

    // Mock drupal_flush_all_caches function.
    $this->mockDrupalFunctions();

    // Create cache manager.
    $this->cacheManager = new UtiliKitCacheManager(
      $this->renderCache,
      $this->cacheTagsInvalidator,
      $this->state,
      $this->themeRegistry,
      $this->cssOptimizer,
      $this->jsOptimizer,
      $this->pageCache,
      $this->dynamicPageCache
    );
  }

  /**
   * Tests clearing all caches.
   *
   * @covers ::clearAllCaches
   */
  public function testClearAllCaches() {
    // Expect render cache to be invalidated.
    $this->renderCache->expects($this->once())
      ->method('invalidateAll');

    // Expect page caches to be deleted.
    $this->dynamicPageCache->expects($this->once())
      ->method('deleteAll');

    $this->pageCache->expects($this->once())
      ->method('deleteAll');

    // Expect asset caches to be deleted.
    $this->cssOptimizer->expects($this->once())
      ->method('deleteAll');

    $this->jsOptimizer->expects($this->once())
      ->method('deleteAll');

    // Expect timestamp update.
    $this->state->expects($this->once())
      ->method('set')
      ->with(UtilikitConstants::STATE_CSS_TIMESTAMP, $this->isType('int'));

    // Expect cache tags invalidation.
    $this->cacheTagsInvalidator->expects($this->once())
      ->method('invalidateTags')
      ->with($this->arrayHasKey(0));

    // Expect theme registry reset.
    $this->themeRegistry->expects($this->once())
      ->method('reset');

    // Execute.
    $this->cacheManager->clearAllCaches();
  }

  /**
   * Tests clearing CSS-specific caches.
   *
   * @covers ::clearCssCaches
   */
  public function testClearCssCaches() {
    // Expect render cache to be invalidated.
    $this->renderCache->expects($this->once())
      ->method('invalidateAll');

    // Expect asset caches to be deleted.
    $this->cssOptimizer->expects($this->once())
      ->method('deleteAll');

    $this->jsOptimizer->expects($this->once())
      ->method('deleteAll');

    // Page caches should NOT be cleared.
    $this->dynamicPageCache->expects($this->never())
      ->method('deleteAll');

    $this->pageCache->expects($this->never())
      ->method('deleteAll');

    // Expect timestamp update.
    $this->state->expects($this->once())
      ->method('set')
      ->with(UtilikitConstants::STATE_CSS_TIMESTAMP, $this->isType('int'));

    // Expect specific CSS cache tags invalidation.
    $this->cacheTagsInvalidator->expects($this->once())
      ->method('invalidateTags')
      ->with($this->callback(function ($tags) {
        return in_array(UtilikitConstants::CACHE_TAG_CSS, $tags) &&
               in_array(UtilikitConstants::CACHE_TAG_INLINE_MODE, $tags) &&
               in_array(UtilikitConstants::CACHE_TAG_STATIC_MODE, $tags);
      }));

    // Theme registry should NOT be reset.
    $this->themeRegistry->expects($this->never())
      ->method('reset');

    // Execute.
    $this->cacheManager->clearCssCaches();
  }

  /**
   * Tests clearing tracked data.
   *
   * @covers ::clearTrackedData
   */
  public function testClearTrackedData() {
    $this->state->expects($this->once())
      ->method('deleteMultiple')
      ->with([
        UtilikitConstants::STATE_GENERATED_CSS,
        UtilikitConstants::STATE_KNOWN_CLASSES,
      ]);

    $this->cacheManager->clearTrackedData();
  }

  /**
   * Tests cache clearing with conservative strategy.
   *
   * @covers ::clearCachesWithStrategy
   */
  public function testClearCachesConservativeStrategy() {
    // Conservative strategy only clears render cache and updates timestamp.
    $this->renderCache->expects($this->once())
      ->method('invalidateAll');

    $this->state->expects($this->once())
      ->method('set')
      ->with(UtilikitConstants::STATE_CSS_TIMESTAMP, $this->isType('int'));

    // Should NOT clear other caches.
    $this->pageCache->expects($this->never())
      ->method('deleteAll');

    $this->cssOptimizer->expects($this->never())
      ->method('deleteAll');

    $this->cacheTagsInvalidator->expects($this->never())
      ->method('invalidateTags');

    $this->cacheManager->clearCachesWithStrategy(['strategy' => 'conservative']);
  }

  /**
   * Tests cache clearing with aggressive strategy.
   *
   * @covers ::clearCachesWithStrategy
   */
  public function testClearCachesAggressiveStrategy() {
    // Aggressive strategy clears everything.
    $this->renderCache->expects($this->once())
      ->method('invalidateAll');

    $this->pageCache->expects($this->once())
      ->method('deleteAll');

    $this->dynamicPageCache->expects($this->once())
      ->method('deleteAll');

    $this->cssOptimizer->expects($this->once())
      ->method('deleteAll');

    $this->jsOptimizer->expects($this->once())
      ->method('deleteAll');

    $this->cacheTagsInvalidator->expects($this->once())
      ->method('invalidateTags');

    $this->themeRegistry->expects($this->once())
      ->method('reset');

    // Should also clear tracked data by default.
    $this->state->expects($this->once())
      ->method('deleteMultiple')
      ->with([
        UtilikitConstants::STATE_GENERATED_CSS,
        UtilikitConstants::STATE_KNOWN_CLASSES,
      ]);

    $this->cacheManager->clearCachesWithStrategy(['strategy' => 'aggressive']);
  }

  /**
   * Tests aggressive strategy with preserve option.
   *
   * @covers ::clearCachesWithStrategy
   */
  public function testClearCachesAggressivePreserveStatic() {
    // Should clear all caches but NOT tracked data.
    $this->renderCache->expects($this->once())
      ->method('invalidateAll');

    $this->state->expects($this->never())
      ->method('deleteMultiple');

    $this->cacheManager->clearCachesWithStrategy([
      'strategy' => 'aggressive',
      'preserve_static_css' => TRUE,
    ]);
  }

  /**
   * Tests cache clearing with standard strategy.
   *
   * @covers ::clearCachesWithStrategy
   */
  public function testClearCachesStandardStrategy() {
    // Standard strategy calls clearCssCaches.
    $this->renderCache->expects($this->once())
      ->method('invalidateAll');

    $this->cssOptimizer->expects($this->once())
      ->method('deleteAll');

    $this->jsOptimizer->expects($this->once())
      ->method('deleteAll');

    $this->state->expects($this->once())
      ->method('set')
      ->with(UtilikitConstants::STATE_CSS_TIMESTAMP, $this->isType('int'));

    $this->cacheTagsInvalidator->expects($this->once())
      ->method('invalidateTags');

    // Default strategy.
    $this->cacheManager->clearCachesWithStrategy();
  }

  /**
   * Tests invalidating UtiliKit cache tags.
   *
   * @covers ::invalidateUtiliKitTags
   */
  public function testInvalidateUtiliKitTags() {
    $additionalTags = ['custom_tag_1', 'custom_tag_2'];

    $this->cacheTagsInvalidator->expects($this->once())
      ->method('invalidateTags')
      ->with($this->callback(function ($tags) {
        // Should include default tags.
        $hasDefaults = in_array(UtilikitConstants::CACHE_TAG_CONFIG, $tags) &&
                      in_array(UtilikitConstants::CACHE_TAG_CSS, $tags) &&
                      in_array(UtilikitConstants::CACHE_TAG_INLINE_MODE, $tags) &&
                      in_array(UtilikitConstants::CACHE_TAG_STATIC_MODE, $tags) &&
                      in_array(UtilikitConstants::CACHE_TAG_RENDERED, $tags);

        // Should include additional tags.
        $hasAdditional = in_array('custom_tag_1', $tags) &&
                        in_array('custom_tag_2', $tags);

        return $hasDefaults && $hasAdditional;
      }));

    $this->cacheManager->invalidateUtiliKitTags($additionalTags);
  }

  /**
   * Tests updating CSS timestamp.
   *
   * @covers ::updateCssTimestamp
   */
  public function testUpdateCssTimestamp() {
    $this->freezeTime(1234567890);

    $this->state->expects($this->once())
      ->method('set')
      ->with(UtilikitConstants::STATE_CSS_TIMESTAMP, 1234567890);

    $this->cacheManager->updateCssTimestamp();
  }

  /**
   * Tests getting cache statistics.
   *
   * @covers ::getCacheStatistics
   */
  public function testGetCacheStatistics() {
    $knownClasses = ['uk-pd--20', 'uk-mg--10', 'uk-bg--ff0000'];
    $generatedCss = '.uk-pd--20 { padding: 20px; }';

    $this->state->expects($this->exactly(4))
      ->method('get')
      ->willReturnMap([
        [UtilikitConstants::STATE_KNOWN_CLASSES, [], $knownClasses],
        [UtilikitConstants::STATE_GENERATED_CSS, '', $generatedCss],
        [UtilikitConstants::STATE_LAST_CLEANUP, 0, 1234567000],
        [UtilikitConstants::STATE_CSS_TIMESTAMP, 0, 1234567890],
      ]);

    $stats = $this->cacheManager->getCacheStatistics();

    $expected = [
      'known_classes_count' => 3,
      'generated_css_size' => strlen($generatedCss),
      'last_cleanup' => 1234567000,
      'css_timestamp' => 1234567890,
    ];

    $this->assertEquals($expected, $stats);
  }

  /**
   * Tests getting cache service status.
   *
   * @covers ::getCacheServiceStatus
   */
  public function testGetCacheServiceStatus() {
    $status = $this->cacheManager->getCacheServiceStatus();

    $expected = [
      'render_cache' => TRUE,
      'page_cache' => TRUE,
      'dynamic_page_cache' => TRUE,
      'css_optimizer' => TRUE,
      'js_optimizer' => TRUE,
    ];

    $this->assertEquals($expected, $status);
  }

  /**
   * Tests cache service status with missing services.
   *
   * @covers ::getCacheServiceStatus
   */
  public function testGetCacheServiceStatusMissing() {
    // Create cache manager with NULL services.
    $cacheManager = new UtiliKitCacheManager(
      $this->renderCache,
      $this->cacheTagsInvalidator,
      $this->state,
      $this->themeRegistry,
      $this->cssOptimizer,
      $this->jsOptimizer,
    // No page cache.
      NULL,
    // No dynamic page cache.
      NULL
    );

    $status = $cacheManager->getCacheServiceStatus();

    $expected = [
      'render_cache' => TRUE,
      'page_cache' => FALSE,
      'dynamic_page_cache' => FALSE,
      'css_optimizer' => TRUE,
      'js_optimizer' => TRUE,
    ];

    $this->assertEquals($expected, $status);
  }

  /**
   * Tests exception handling in cache operations.
   *
   * @covers ::clearAllCaches
   */
  public function testExceptionHandling() {
    // Make render cache throw exception.
    $this->renderCache->method('invalidateAll')
      ->willThrowException(new \Exception('Cache error'));

    // Other operations should still proceed.
    $this->pageCache->expects($this->once())
      ->method('deleteAll');

    $this->cssOptimizer->expects($this->once())
      ->method('deleteAll');

    // Should not throw exception.
    $this->cacheManager->clearAllCaches();
  }

  /**
   * Tests clearing caches with NULL services.
   *
   * @covers ::clearAllCaches
   */
  public function testClearCachesWithNullServices() {
    // Create cache manager with some NULL services.
    $cacheManager = new UtiliKitCacheManager(
      $this->renderCache,
      $this->cacheTagsInvalidator,
      $this->state,
      $this->themeRegistry,
      $this->cssOptimizer,
      $this->jsOptimizer,
      NULL,
      NULL
    );

    // Should not throw errors.
    $this->renderCache->expects($this->once())
      ->method('invalidateAll');

    $this->cssOptimizer->expects($this->once())
      ->method('deleteAll');

    $cacheManager->clearAllCaches();
  }

  /**
   * Tests multiple cache tag invalidations.
   *
   * @covers ::invalidateUtiliKitTags
   */
  public function testMultipleTagInvalidations() {
    // Test with no additional tags.
    $this->cacheTagsInvalidator->expects($this->once())
      ->method('invalidateTags')
      ->with($this->callback(function ($tags) {
        // Only default tags.
        return count($tags) === 5;
      }));

    $this->cacheManager->invalidateUtiliKitTags();
  }

  /**
   * Tests timestamp precision.
   *
   * @covers ::updateCssTimestamp
   */
  public function testTimestampPrecision() {
    // Test that timestamp is current time.
    $beforeTime = time();

    $this->state->expects($this->once())
      ->method('set')
      ->with(
        UtilikitConstants::STATE_CSS_TIMESTAMP,
        $this->callback(function ($timestamp) use ($beforeTime) {
          $afterTime = time();
          return $timestamp >= $beforeTime && $timestamp <= $afterTime;
        })
      );

    $this->cacheManager->updateCssTimestamp();
  }

  /**
   * Helper to freeze time for testing.
   */
  protected function freezeTime($timestamp) {
    // In a real test environment, use a time mocking library
    // For this test, we'll use a simple approach.
  }

  /**
   * Mock Drupal global functions.
   */
  protected function mockDrupalFunctions() {
    // In a real test environment, you'd mock drupal_flush_all_caches
    // For unit tests, we can use namespacing trick or test doubles.
  }

}

// Mock global functions in the service namespace.
namespace Drupal\utilikit\Service;

/**
 * Mock implementation of drupal_flush_all_caches for testing.
 */
function drupal_flush_all_caches() {
  // Mock implementation for testing.
  return TRUE;
}

/**
 * Mock implementation of time function for testing.
 */
function time() {
  global $_utilikit_frozenTime;
  return $_utilikit_frozenTime ?? \time();
}
