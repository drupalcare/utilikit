<?php

namespace Drupal\utilikit\Service;

/**
 * Interface for UtiliKit cache management operations.
 *
 * Defines the contract for cache management services that handle UtiliKit-
 * specific cache operations including CSS cache clearing, tracked data
 * management, cache tag invalidation, and statistics reporting.
 */
interface UtiliKitCacheManagerInterface {

  /**
   * Clear all UtiliKit-related caches.
   *
   * Performs a comprehensive cache clear including render cache, page caches,
   * asset optimization caches, theme registry, and all UtiliKit-specific
   * cache tags. Also triggers a full Drupal cache flush.
   */
  public function clearAllCaches(): void;

  /**
   * Clear only CSS-related caches.
   *
   * Targets caches specifically related to CSS processing and UtiliKit
   * functionality while preserving other system caches for better
   * performance during CSS updates.
   */
  public function clearCssCaches(): void;

  /**
   * Clear tracked data (classes, generated CSS).
   *
   * Removes generated CSS content and known classes from the state API,
   * effectively resetting UtiliKit to a clean state without affecting
   * other cache layers.
   */
  public function clearTrackedData(): void;

  /**
   * Clear caches with specific strategy.
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
  public function clearCachesWithStrategy(array $options = []): void;

  /**
   * Invalidate UtiliKit cache tags.
   *
   * Invalidates cache entries tagged with UtiliKit-specific tags to ensure
   * that cached content using UtiliKit classes is regenerated with the
   * latest CSS rules and configurations.
   *
   * @param array $tags
   *   Additional cache tags to invalidate beyond the default UtiliKit tags.
   */
  public function invalidateUtiliKitTags(array $tags = []): void;

  /**
   * Update CSS timestamp for browser cache busting.
   *
   * Records the current timestamp for CSS updates, used for cache busting
   * and tracking when CSS was last modified.
   */
  public function updateCssTimestamp(): void;

  /**
   * Get cache statistics.
   *
   * Provides information about the current state of UtiliKit caches
   * including counts, sizes, and timestamps for monitoring purposes.
   *
   * @return array
   *   Statistics array containing:
   *   - known_classes_count: Number of tracked utility classes
   *   - generated_css_size: Size of generated CSS in bytes
   *   - last_cleanup: Timestamp of last cleanup operation
   *   - css_timestamp: Timestamp of last CSS update
   */
  public function getCacheStatistics(): array;

  /**
   * Check if cache services are available.
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
  public function getCacheServiceStatus(): array;

}
