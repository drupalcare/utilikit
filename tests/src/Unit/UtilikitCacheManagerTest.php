<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitCacheManager;
use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\utilikit\Service\UtilikitStateManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Theme\Registry;
use Drupal\Core\Asset\AssetCollectionOptimizerInterface;
use PHPUnit\Framework\MockObject\Stub;

/**
 * Tests the UtilikitCacheManager service.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Service\UtilikitCacheManager
 */
#[CoversClass(\Drupal\utilikit\Service\UtilikitCacheManager::class)]
#[Group('utilikit')]
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
   * @var \Drupal\Core\Cache\CacheBackendInterface&\PHPUnit\Framework\MockObject\Stub
   */
  protected CacheBackendInterface&Stub $renderCache;

  /**
   * Mock cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface&\PHPUnit\Framework\MockObject\Stub
   */
  protected CacheTagsInvalidatorInterface&Stub $cacheTagsInvalidator;

  /**
   * Mock state manager.
   *
   * @var \Drupal\utilikit\Service\UtilikitStateManager&\PHPUnit\Framework\MockObject\Stub
   */
  protected UtilikitStateManager&Stub $stateManager;

  /**
   * Mock theme registry.
   *
   * @var \Drupal\Core\Theme\Registry&\PHPUnit\Framework\MockObject\Stub
   */
  protected Registry&Stub $themeRegistry;

  /**
   * Mock CSS optimizer.
   *
   * @var \Drupal\Core\Asset\AssetCollectionOptimizerInterface&\PHPUnit\Framework\MockObject\Stub
   */
  protected AssetCollectionOptimizerInterface&Stub $cssOptimizer;

  /**
   * Mock JS optimizer.
   *
   * @var \Drupal\Core\Asset\AssetCollectionOptimizerInterface&\PHPUnit\Framework\MockObject\Stub
   */
  protected AssetCollectionOptimizerInterface&Stub $jsOptimizer;

  /**
   * Mock page cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface&\PHPUnit\Framework\MockObject\Stub
   */
  protected CacheBackendInterface&Stub $pageCache;

  /**
   * Mock dynamic page cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface&\PHPUnit\Framework\MockObject\Stub
   */
  protected CacheBackendInterface&Stub $dynamicPageCache;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create all mocks.
    $this->renderCache = $this->createMock(CacheBackendInterface::class);
    $this->cacheTagsInvalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $this->stateManager = $this->createStub(UtilikitStateManager::class);
    $this->themeRegistry = $this->createStub(Registry::class);
    $this->cssOptimizer = $this->createStub(AssetCollectionOptimizerInterface::class);
    $this->jsOptimizer = $this->createStub(AssetCollectionOptimizerInterface::class);
    $this->pageCache = $this->createStub(CacheBackendInterface::class);
    $this->dynamicPageCache = $this->createStub(CacheBackendInterface::class);

    // Create cache manager with UtilikitStateManager.
    $this->buildSubject();
  }

  /**
   * Builds the subject under test from the current collaborators.
   *
   * Collaborators are stubs by default; a test that asserts calls on one
   * replaces it with a mock and calls this again.
   */
  private function buildSubject(): void {
    $this->cacheManager = new UtilikitCacheManager(
      $this->renderCache,
      $this->cacheTagsInvalidator,
      $this->stateManager,
      $this->themeRegistry,
      $this->cssOptimizer,
      $this->jsOptimizer,
      $this->pageCache,
      $this->dynamicPageCache
    );
  }

  /**
   * Tests clearCssCaches method.
   *
   * @covers ::clearCssCaches
   */
  public function testClearCssCaches(): void {
    $defaultTags = [
      UtilikitConstants::CACHE_TAG_CONFIG,
      UtilikitConstants::CACHE_TAG_CSS,
      UtilikitConstants::CACHE_TAG_INLINE_MODE,
      UtilikitConstants::CACHE_TAG_STATIC_MODE,
      UtilikitConstants::CACHE_TAG_HEAD_MODE,
      UtilikitConstants::CACHE_TAG_RENDERED,
      UtilikitConstants::CACHE_TAG_THEME,
    ];
    $extraTags = [
      UtilikitConstants::CACHE_TAG_CSS,
      UtilikitConstants::CACHE_TAG_INLINE_MODE,
      UtilikitConstants::CACHE_TAG_STATIC_MODE,
      UtilikitConstants::CACHE_TAG_HEAD_MODE,
    ];
    $expectedTags = array_merge($defaultTags, $extraTags);

    $this->cacheTagsInvalidator->expects($this->once())
      ->method('invalidateTags')
      ->with($expectedTags);

    $this->renderCache->expects($this->once())
      ->method('deleteAll');

    $this->cacheManager->clearCssCaches();
  }

  /**
   * Tests clearAllCaches method.
   *
   * @covers ::clearAllCaches
   */
  public function testClearAllCaches(): void {
    $this->cssOptimizer = $this->createMock(AssetCollectionOptimizerInterface::class);
    $this->dynamicPageCache = $this->createMock(CacheBackendInterface::class);
    $this->jsOptimizer = $this->createMock(AssetCollectionOptimizerInterface::class);
    $this->pageCache = $this->createMock(CacheBackendInterface::class);
    $this->themeRegistry = $this->createMock(Registry::class);
    $this->buildSubject();

    $expectedTags = [
      UtilikitConstants::CACHE_TAG_CONFIG,
      UtilikitConstants::CACHE_TAG_CSS,
      UtilikitConstants::CACHE_TAG_INLINE_MODE,
      UtilikitConstants::CACHE_TAG_STATIC_MODE,
      UtilikitConstants::CACHE_TAG_HEAD_MODE,
      UtilikitConstants::CACHE_TAG_RENDERED,
      UtilikitConstants::CACHE_TAG_THEME,
    ];

    $this->cacheTagsInvalidator->expects($this->once())
      ->method('invalidateTags')
      ->with($expectedTags);

    $this->renderCache->expects($this->once())
      ->method('deleteAll');

    $this->pageCache->expects($this->once())
      ->method('deleteAll');

    $this->dynamicPageCache->expects($this->once())
      ->method('deleteAll');

    $this->themeRegistry->expects($this->once())
      ->method('reset');

    $this->cssOptimizer->expects($this->once())
      ->method('deleteAll');

    $this->jsOptimizer->expects($this->once())
      ->method('deleteAll');

    $this->cacheManager->clearAllCaches();
  }

}
