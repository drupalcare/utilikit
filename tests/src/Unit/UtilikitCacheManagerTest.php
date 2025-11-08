<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitCacheManager;
use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Theme\Registry;
use Drupal\Core\Asset\AssetCollectionOptimizerInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the UtilikitCacheManager service.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Service\UtilikitCacheManager
 */
class UtilikitCacheManagerTest extends UnitTestCase {

  /**
   * The cache manager service under test.
   *
   * @var \Drupal\utilikit\Service\UtilikitCacheManager
   */
  protected UtilikitCacheManager $cacheManager;

  /**
   * Mock render cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected CacheBackendInterface&MockObject $renderCache;

  /**
   * Mock cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected CacheTagsInvalidatorInterface&MockObject $cacheTagsInvalidator;

  /**
   * Mock state service.
   *
   * @var \Drupal\Core\State\StateInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected StateInterface&MockObject $state;

  /**
   * Mock theme registry.
   *
   * @var \Drupal\Core\Theme\Registry&\PHPUnit\Framework\MockObject\MockObject
   */
  protected Registry&MockObject $themeRegistry;

  /**
   * Mock CSS optimizer.
   *
   * @var \Drupal\Core\Asset\AssetCollectionOptimizerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected AssetCollectionOptimizerInterface&MockObject $cssOptimizer;

  /**
   * Mock JS optimizer.
   *
   * @var \Drupal\Core\Asset\AssetCollectionOptimizerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected AssetCollectionOptimizerInterface&MockObject $jsOptimizer;

  /**
   * Mock page cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected CacheBackendInterface&MockObject $pageCache;

  /**
   * Mock dynamic page cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected CacheBackendInterface&MockObject $dynamicPageCache;

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

    // Set up global function mock flag.
    $GLOBALS['_utilikit_test_drupal_flush_all_caches_called'] = FALSE;

    // Create cache manager.
    $this->cacheManager = new UtilikitCacheManager(
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
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Clean up global test flags.
    unset($GLOBALS['_utilikit_test_drupal_flush_all_caches_called']);

    parent::tearDown();
  }

  /**
   * Tests clearCssCaches method.
   *
   * @covers ::clearCssCaches
   */
  public function testClearCssCaches(): void {
    // Expect cache tag invalidation.
    $this->cacheTagsInvalidator->expects($this->once())
      ->method('invalidateTags')
      ->with([
        UtilikitConstants::CACHE_TAG_CSS,
        UtilikitConstants::CACHE_TAG_CONFIG,
      ]);

    // Expect render cache clear.
    $this->renderCache->expects($this->once())
      ->method('deleteAll');

    $this->cacheManager->clearCssCaches();
  }

  /**
   * Tests clearRenderingModeCaches for inline mode.
   *
   * @covers ::clearRenderingModeCaches
   */
  public function testClearRenderingModeCachesInline(): void {
    $this->cacheTagsInvalidator->expects($this->once())
      ->method('invalidateTags')
      ->with([UtilikitConstants::CACHE_TAG_INLINE_MODE]);

    $this->cacheManager->clearRenderingModeCaches('inline');
  }

  /**
   * Tests clearRenderingModeCaches for static mode.
   *
   * @covers ::clearRenderingModeCaches
   */
  public function testClearRenderingModeCachesStatic(): void {
    $this->cacheTagsInvalidator->expects($this->once())
      ->method('invalidateTags')
      ->with([UtilikitConstants::CACHE_TAG_STATIC_MODE]);

    $this->cacheManager->clearRenderingModeCaches('static');
  }

  /**
   * Tests clearAllCaches method.
   *
   * @covers ::clearAllCaches
   */
  public function testClearAllCaches(): void {
    // Expect all cache invalidations.
    $this->cacheTagsInvalidator->expects($this->once())
      ->method('invalidateTags')
      ->with([
        UtilikitConstants::CACHE_TAG_CSS,
        UtilikitConstants::CACHE_TAG_CONFIG,
        UtilikitConstants::CACHE_TAG_INLINE_MODE,
        UtilikitConstants::CACHE_TAG_STATIC_MODE,
      ]);

    // Expect all cache backends to be cleared.
    $this->renderCache->expects($this->once())
      ->method('deleteAll');

    $this->pageCache->expects($this->once())
      ->method('deleteAll');

    $this->dynamicPageCache->expects($this->once())
      ->method('deleteAll');

    // Expect theme registry rebuild.
    $this->themeRegistry->expects($this->once())
      ->method('reset');

    // Expect asset optimization clear.
    $this->cssOptimizer->expects($this->once())
      ->method('deleteAll');

    $this->jsOptimizer->expects($this->once())
      ->method('deleteAll');

    $this->cacheManager->clearAllCaches();

    // Verify drupal_flush_all_caches was called.
    $this->assertTrue($GLOBALS['_utilikit_test_drupal_flush_all_caches_called']);
  }

  /**
   * Tests getCacheTags method.
   *
   * @covers ::getCacheTags
   */
  public function testGetCacheTags(): void {
    $tags = $this->cacheManager->getCacheTags();

    $this->assertIsArray($tags);
    $this->assertContains(UtilikitConstants::CACHE_TAG_CSS, $tags);
    $this->assertContains(UtilikitConstants::CACHE_TAG_CONFIG, $tags);
  }

  /**
   * Tests getRenderCacheTags method for inline mode.
   *
   * @covers ::getRenderCacheTags
   */
  public function testGetRenderCacheTagsInline(): void {
    $tags = $this->cacheManager->getRenderCacheTags('inline');

    $this->assertIsArray($tags);
    $this->assertContains(UtilikitConstants::CACHE_TAG_CSS, $tags);
    $this->assertContains(UtilikitConstants::CACHE_TAG_CONFIG, $tags);
    $this->assertContains(UtilikitConstants::CACHE_TAG_INLINE_MODE, $tags);
    $this->assertNotContains(UtilikitConstants::CACHE_TAG_STATIC_MODE, $tags);
  }

  /**
   * Tests getRenderCacheTags method for static mode.
   *
   * @covers ::getRenderCacheTags
   */
  public function testGetRenderCacheTagsStatic(): void {
    $tags = $this->cacheManager->getRenderCacheTags('static');

    $this->assertIsArray($tags);
    $this->assertContains(UtilikitConstants::CACHE_TAG_CSS, $tags);
    $this->assertContains(UtilikitConstants::CACHE_TAG_CONFIG, $tags);
    $this->assertContains(UtilikitConstants::CACHE_TAG_STATIC_MODE, $tags);
    $this->assertNotContains(UtilikitConstants::CACHE_TAG_INLINE_MODE, $tags);
  }

  /**
   * Tests invalidateEntityCaches method.
   *
   * @covers ::invalidateEntityCaches
   */
  public function testInvalidateEntityCaches(): void {
    $entityType = 'node';
    $entityId = '123';

    $expectedTags = [
      'node:123',
      'node_list',
      UtilikitConstants::CACHE_TAG_CSS,
    ];

    $this->cacheTagsInvalidator->expects($this->once())
      ->method('invalidateTags')
      ->with($expectedTags);

    $this->cacheManager->invalidateEntityCaches($entityType, $entityId);
  }

  /**
   * Tests clearAssetCache method.
   *
   * @covers ::clearAssetCache
   */
  public function testClearAssetCache(): void {
    // Expect both optimizers to be cleared.
    $this->cssOptimizer->expects($this->once())
      ->method('deleteAll');

    $this->jsOptimizer->expects($this->once())
      ->method('deleteAll');

    // Expect state update.
    $this->state->expects($this->once())
      ->method('set')
      ->with('system.css_js_query_string', $this->matchesRegularExpression('/^[a-zA-Z0-9]{6}$/'));

    $this->cacheManager->clearAssetCache();
  }

  /**
   * Tests warmCache method.
   *
   * @covers ::warmCache
   */
  public function testWarmCache(): void {
    $testData = ['test' => 'data'];
    $cacheId = 'utilikit:test';
    $tags = ['test_tag'];

    $this->renderCache->expects($this->once())
      ->method('set')
      ->with(
        $cacheId,
        $testData,
        $this->anything(),
        $tags
      );

    $this->cacheManager->warmCache($cacheId, $testData, $tags);
  }

  /**
   * Tests getCache method when cache exists.
   *
   * @covers ::getCache
   */
  public function testGetCacheHit(): void {
    $cacheId = 'utilikit:test';
    $expectedData = ['test' => 'data'];

    $cacheObject = new \stdClass();
    $cacheObject->data = $expectedData;

    $this->renderCache->expects($this->once())
      ->method('get')
      ->with($cacheId)
      ->willReturn($cacheObject);

    $result = $this->cacheManager->getCache($cacheId);
    $this->assertEquals($expectedData, $result);
  }

  /**
   * Tests getCache method when cache misses.
   *
   * @covers ::getCache
   */
  public function testGetCacheMiss(): void {
    $cacheId = 'utilikit:test';

    $this->renderCache->expects($this->once())
      ->method('get')
      ->with($cacheId)
      ->willReturn(FALSE);

    $result = $this->cacheManager->getCache($cacheId);
    $this->assertNull($result);
  }

}
