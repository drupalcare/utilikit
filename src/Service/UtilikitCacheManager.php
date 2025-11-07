<?php

namespace Drupal\utilikit\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Theme\Registry;
use Drupal\Core\Asset\AssetCollectionOptimizerInterface;

/**
 * Manages cache operations for Utilikit utility classes and CSS.
 *
 * This service provides comprehensive cache management including render cache,
 * page cache, asset optimization caches, and Utilikit-specific cache tags.
 * It supports different clearing strategies and provides cache statistics
 * for monitoring and debugging purposes.
 */
class UtilikitCacheManager implements UtilikitCacheManagerInterface {

  /**
   * The render cache backend service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $renderCache;

  /**
   * The cache tags invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected CacheTagsInvalidatorInterface $cacheTagsInvalidator;

  /**
   * The state service for persistent data storage.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The theme registry service.
   *
   * @var \Drupal\Core\Theme\Registry
   */
  protected Registry $themeRegistry;

  /**
   * The CSS asset collection optimizer service.
   *
   * @var \Drupal\Core\Asset\AssetCollectionOptimizerInterface
   */
  protected AssetCollectionOptimizerInterface $cssOptimizer;

  /**
   * The JS asset collection optimizer service.
   *
   * @var \Drupal\Core\Asset\AssetCollectionOptimizerInterface
   */
  protected AssetCollectionOptimizerInterface $jsOptimizer;

  /**
   * The page cache backend service (optional).
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|null
   */
  protected ?CacheBackendInterface $pageCache;

  /**
   * The dynamic page cache backend service (optional).
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|null
   */
  protected ?CacheBackendInterface $dynamicPageCache;

  /**
   * Constructs a new UtilikitCacheManager object.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $renderCache
   *   The render cache backend service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   The cache tags invalidator service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service for persistent data storage.
   * @param \Drupal\Core\Theme\Registry $themeRegistry
   *   The theme registry service.
   * @param \Drupal\Core\Asset\AssetCollectionOptimizerInterface $cssOptimizer
   *   The CSS asset collection optimizer service.
   * @param \Drupal\Core\Asset\AssetCollectionOptimizerInterface $jsOptimizer
   *   The JS asset collection optimizer service.
   * @param \Drupal\Core\Cache\CacheBackendInterface|null $pageCache
   *   The page cache backend service (optional).
   * @param \Drupal\Core\Cache\CacheBackendInterface|null $dynamicPageCache
   *   The dynamic page cache backend service (optional).
   */
  public function __construct(
    CacheBackendInterface $renderCache,
    CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    StateInterface $state,
    Registry $themeRegistry,
    AssetCollectionOptimizerInterface $cssOptimizer,
    AssetCollectionOptimizerInterface $jsOptimizer,
    ?CacheBackendInterface $pageCache = NULL,
    ?CacheBackendInterface $dynamicPageCache = NULL,
  ) {
    $this->renderCache = $renderCache;
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
    $this->state = $state;
    $this->themeRegistry = $themeRegistry;
    $this->cssOptimizer = $cssOptimizer;
    $this->jsOptimizer = $jsOptimizer;
    $this->pageCache = $pageCache;
    $this->dynamicPageCache = $dynamicPageCache;
  }

  /**
   * Clears all caches and resets the Utilikit system.
   *
   * Performs a comprehensive cache clear including render cache, page caches,
   * asset optimization caches, theme registry, and all Utilikit-specific
   * cache tags. Also triggers a full Drupal cache flush.
   */
  public function clearAllCaches(): void {
    $this->clearRenderCache();
    $this->clearPageCaches();
    $this->clearAssetCaches();
    $this->updateCssTimestamp();
    $this->invalidateUtilikitTags();
    $this->themeRegistry->reset();
    drupal_flush_all_caches();
  }

  /**
   * Clears CSS-related caches without full system flush.
   *
   * Targets caches specifically related to CSS processing and Utilikit
   * functionality while preserving other system caches for better
   * performance during CSS updates.
   */
  public function clearCssCaches(): void {
    $this->clearRenderCache();
    $this->clearAssetCaches();
    $this->updateCssTimestamp();
    $this->invalidateUtilikitTags([
      UtilikitConstants::CACHE_TAG_CSS,
      UtilikitConstants::CACHE_TAG_INLINE_MODE,
      UtilikitConstants::CACHE_TAG_STATIC_MODE,
      UtilikitConstants::CACHE_TAG_HEAD_MODE,
    ]);
  }

  /**
   * Clears tracked Utilikit data from state storage.
   *
   * Removes generated CSS content and known classes from the state API,
   * effectively resetting Utilikit to a clean state without affecting
   * other cache layers.
   */
  public function clearTrackedData(): void {
    $this->state->deleteMultiple([
      UtilikitConstants::STATE_GENERATED_CSS,
      UtilikitConstants::STATE_KNOWN_CLASSES,
    ]);
  }

  /**
   * Clears caches using a specific strategy.
   *
   * Provides different cache clearing strategies based on the use case:
   * - conservative: Minimal cache clearing for minor updates
   * - aggressive: Full cache clearing with optional data preservation
   * - standard: Default CSS-focused cache clearing.
   *
   * @param array $options
   *   Options array with the following keys:
   *   - strategy: Cache clearing strategy ('conservative', 'aggressive',
   *     'standard')
   *   - preserve_static_css: Whether to preserve static CSS data in
   *     aggressive mode (default: FALSE)
   */
  public function clearCachesWithStrategy(array $options = []): void {
    $strategy = $options['strategy'] ?? 'standard';

    switch ($strategy) {
      case 'conservative':
        $this->clearRenderCache();
        $this->updateCssTimestamp();
        break;

      case 'aggressive':
        $this->clearAllCaches();
        if (!($options['preserve_static_css'] ?? FALSE)) {
          $this->clearTrackedData();
        }
        break;

      default:
        $this->clearCssCaches();
        break;
    }
  }

  /**
   * Invalidates Utilikit-specific cache tags.
   *
   * Invalidates cache entries tagged with Utilikit-specific tags to ensure
   * that cached content using Utilikit classes is regenerated with the
   * latest CSS rules and configurations.
   *
   * @param array $tags
   *   Additional cache tags to invalidate beyond the default Utilikit tags.
   */
  public function invalidateUtilikitTags(array $tags = []): void {
    $defaultTags = [
      UtilikitConstants::CACHE_TAG_CONFIG,
      UtilikitConstants::CACHE_TAG_CSS,
      UtilikitConstants::CACHE_TAG_INLINE_MODE,
      UtilikitConstants::CACHE_TAG_STATIC_MODE,
      UtilikitConstants::CACHE_TAG_HEAD_MODE,
      UtilikitConstants::CACHE_TAG_RENDERED,
    ];

    $allTags = array_merge($defaultTags, $tags);
    $this->cacheTagsInvalidator->invalidateTags($allTags);
  }

  /**
   * Updates the CSS timestamp to current time.
   *
   * Records the current timestamp for CSS updates, used for cache busting
   * and tracking when CSS was last modified.
   */
  public function updateCssTimestamp(): void {
    $this->state->set(UtilikitConstants::STATE_CSS_TIMESTAMP, time());
  }

  /**
   * Gets cache statistics for monitoring and debugging.
   *
   * Provides information about the current state of Utilikit caches
   * including counts, sizes, and timestamps for monitoring purposes.
   *
   * @return array
   *   Statistics array containing:
   *   - known_classes_count: Number of tracked utility classes
   *   - generated_css_size: Size of generated CSS in bytes
   *   - last_cleanup: Timestamp of last cleanup operation
   *   - css_timestamp: Timestamp of last CSS update
   */
  public function getCacheStatistics(): array {
    $knownClasses = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    $generatedCss = $this->state->get(UtilikitConstants::STATE_GENERATED_CSS, '');
    $lastCleanup = $this->state->get(UtilikitConstants::STATE_LAST_CLEANUP, 0);

    return [
      'known_classes_count' => count($knownClasses),
      'generated_css_size' => strlen($generatedCss),
      'last_cleanup' => $lastCleanup,
      'css_timestamp' => $this->state->get(UtilikitConstants::STATE_CSS_TIMESTAMP, 0),
    ];
  }

  /**
   * Gets the availability status of cache services.
   *
   * Reports which cache services are available and properly configured
   * for debugging and monitoring purposes.
   *
   * @return array
   *   Service status array containing boolean values for:
   *   - render_cache: Render cache service availability
   *   - page_cache: Page cache service availability
   *   - dynamic_page_cache: Dynamic page cache service availability
   *   - css_optimizer: CSS optimizer service availability
   *   - js_optimizer: JS optimizer service availability
   */
  public function getCacheServiceStatus(): array {
    return [
      'render_cache' => $this->renderCache !== NULL,
      'page_cache' => $this->pageCache !== NULL,
      'dynamic_page_cache' => $this->dynamicPageCache !== NULL,
      'css_optimizer' => $this->cssOptimizer !== NULL,
      'js_optimizer' => $this->jsOptimizer !== NULL,
    ];
  }

  /**
   * Clears the render cache.
   *
   * Invalidates all render cache entries. Exceptions are caught and handled
   * silently to prevent cache clearing failures from breaking the system.
   */
  private function clearRenderCache(): void {
    try {
      $this->renderCache->invalidateAll();
    }
    catch (\Exception $e) {
      // Continue silently.
    }
  }

  /**
   * Clears page caches (both dynamic and static).
   *
   * Clears both dynamic page cache and static page cache if available.
   * Exceptions are caught and handled silently to prevent cache clearing
   * failures from breaking the system.
   */
  private function clearPageCaches(): void {
    try {
      if ($this->dynamicPageCache) {
        $this->dynamicPageCache->deleteAll();
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to clear dynamic page cache: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    try {
      if ($this->pageCache) {
        $this->pageCache->deleteAll();
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to clear page cache: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Clears asset optimization caches.
   *
   * Deletes optimized CSS and JS asset caches to ensure fresh asset
   * generation. Exceptions are caught and handled silently to prevent
   * cache clearing failures from breaking the system.
   */
  private function clearAssetCaches(): void {
    try {
      $this->cssOptimizer->deleteAll();
      $this->jsOptimizer->deleteAll();
    }
    catch (\Exception $e) {
      // Continue silently.
    }
  }

}
