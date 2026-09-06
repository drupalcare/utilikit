<?php

declare(strict_types=1);

namespace Drupal\utilikit\Service;

use Psr\Log\NullLogger;
use Drupal\Core\Asset\AssetCollectionOptimizerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Theme\Registry;
use Psr\Log\LoggerInterface;

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
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected readonly LoggerInterface $logger;

  /**
   * Constructs a new UtilikitCacheManager object.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $renderCache
   *   The render cache backend service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   The cache tags invalidator service.
   * @param \Drupal\utilikit\Service\UtilikitStateManager $stateManager
   *   The UtiliKit state manager for Utilikit-specific state data.
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
   * @param \Psr\Log\LoggerInterface|null $logger
   *   The logger service for recording cache operations.
   */
  public function __construct(
    protected readonly CacheBackendInterface $renderCache,
    protected readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    protected readonly UtilikitStateManager $stateManager,
    protected readonly Registry $themeRegistry,
    protected readonly AssetCollectionOptimizerInterface $cssOptimizer,
    protected readonly AssetCollectionOptimizerInterface $jsOptimizer,
    protected readonly ?CacheBackendInterface $pageCache = NULL,
    protected readonly ?CacheBackendInterface $dynamicPageCache = NULL,
    ?LoggerInterface $logger = NULL,
  ) {
    $this->logger = $logger ?? new NullLogger();
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
    $this->stateManager->clearUtilikitData();
  }

  /**
   * Clears caches using a specific strategy.
   *
   * Provides different cache clearing strategies based on the use case:
   * - conservative: Minimal cache clearing for minor updates
   * - aggressive: Full cache clearing with optional data preservation
   * - standard: Default CSS-focused cache clearing.
   *
   * @param array<string, mixed> $options
   *   Options array with the following keys:
   *   - strategy: Cache clearing strategy ('conservative', 'aggressive',
   *     'standard')
   *   - preserve_static_css: Whether to preserve static CSS data in
   *     aggressive mode (default: FALSE)
   */
  public function clearCachesWithStrategy(array $options = []): void {
    $strategy = $options['strategy'] ?? 'standard';

    if ($strategy === 'conservative') {
      $this->clearRenderCache();
      $this->updateCssTimestamp();
    }
    elseif ($strategy === 'aggressive') {
      $this->clearAllCaches();
      if (!($options['preserve_static_css'] ?? FALSE)) {
        $this->clearTrackedData();
      }
    }
    else {
      $this->clearCssCaches();
    }
  }

  /**
   * Invalidates Utilikit-specific cache tags.
   *
   * Invalidates cache entries tagged with Utilikit-specific tags to ensure
   * that cached content using Utilikit classes is regenerated with the
   * latest CSS rules and configurations.
   *
   * @param list<string> $tags
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
      UtilikitConstants::CACHE_TAG_THEME,
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
    $this->stateManager->updateCssTimestamp();
  }

  /**
   * Gets cache statistics for monitoring and debugging.
   *
   * Provides information about the current state of Utilikit caches
   * including counts, sizes, and timestamps for monitoring purposes.
   *
   * @return array<string, mixed>
   *   Statistics array containing:
   *   - known_classes_count: Number of tracked utility classes
   *   - generated_css_size: Size of generated CSS in bytes
   *   - last_cleanup: Timestamp of last cleanup operation
   *   - css_timestamp: Timestamp of last CSS update
   */
  public function getCacheStatistics(): array {
    return $this->stateManager->getStateStatistics();
  }

  /**
   * Gets the availability status of cache services.
   *
   * Reports which cache services are available and properly configured
   * for debugging and monitoring purposes.
   *
   * @return array<string, mixed>
   *   Service status array containing boolean values for:
   *   - render_cache: Render cache service availability
   *   - page_cache: Page cache service availability
   *   - dynamic_page_cache: Dynamic page cache service availability
   *   - css_optimizer: CSS optimizer service availability
   *   - js_optimizer: JS optimizer service availability
   */
  public function getCacheServiceStatus(): array {
    // Three of these are required, non-nullable constructor arguments, so the
    // container cannot build this service without them and their `!== NULL`
    // checks could only ever answer TRUE — reporting an availability the
    // method was in no position to determine. Those three are stated as
    // constants. `pageCache` and `dynamicPageCache` are optional arguments
    // defaulting to NULL, so their checks are meaningful and remain.
    return [
      'render_cache' => TRUE,
      // These two ARE optional constructor arguments and genuinely can be
      // NULL, so their checks are real and stay.
      'page_cache' => $this->pageCache !== NULL,
      'dynamic_page_cache' => $this->dynamicPageCache !== NULL,
      'css_optimizer' => TRUE,
      'js_optimizer' => TRUE,
    ];
  }

  /**
   * Clears the render cache.
   *
   * Deletes all render cache entries. Exceptions are caught and handled
   * silently to prevent cache clearing failures from breaking the system.
   */
  private function clearRenderCache(): void {
    try {
      // deleteAll(), not invalidateAll(). `CacheBackendInterface` dropped
      // invalidateAll() in Drupal 12 — verified by diffing the interface
      // against 11.4.5, where it is still present — so the old call is a
      // fatal on a core version this module declares support for.
      // deleteAll() exists in 10, 11 and 12 alike, and it is the closer match
      // to what this method documents: clearing the cache, not marking it
      // stale.
      $this->renderCache->deleteAll();
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to clear render cache: @message', [
        '@message' => $e->getMessage(),
      ]);
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
      $this->logger->warning('Failed to clear asset caches: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

}
