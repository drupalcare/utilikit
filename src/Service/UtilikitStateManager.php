<?php

namespace Drupal\utilikit\Service;

use Drupal\Core\State\StateInterface;

/**
 * Manages persistent state data for UtiliKit module operations.
 *
 * This service provides a centralized interface for storing and retrieving
 * UtiliKit's persistent data using Drupal's State API. It manages:
 * - Known utility classes discovered from content scanning
 * - Generated CSS content for static mode
 * - Timestamps for cache invalidation and cleanup operations
 * - Rate limiting data for performance control
 * - Statistical information about module usage.
 *
 * The State API is used instead of configuration because this data changes
 * frequently during normal operation and doesn't need to be exported with
 * configuration management.
 */
class UtilikitStateManager {

  /**
   * The state service for persistent data storage.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * Constructs a new UtilikitStateManager.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service for storing persistent data across requests.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * Gets the list of known utility classes.
   *
   * @return array
   *   An array of utility class names that have been discovered and
   *   validated. Returns an empty array if no classes are known.
   */
  public function getKnownClasses(): array {
    return $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
  }

  /**
   * Sets the list of known utility classes.
   *
   * @param array $classes
   *   An array of utility class names to store. Duplicates will be removed
   *   automatically.
   */
  public function setKnownClasses(array $classes): void {
    $this->state->set(UtilikitConstants::STATE_KNOWN_CLASSES, array_unique($classes));
  }

  /**
   * Adds new utility classes to the existing known classes.
   *
   * This method merges new classes with existing ones, removes duplicates,
   * and updates the stored list.
   *
   * @param array $newClasses
   *   An array of new utility class names to add to the known classes.
   *
   * @return array
   *   The complete merged array of known classes after adding the new ones.
   */
  public function addKnownClasses(array $newClasses): array {
    $knownClasses = $this->getKnownClasses();
    $mergedClasses = array_unique(array_merge($knownClasses, $newClasses));
    $this->setKnownClasses($mergedClasses);
    return $mergedClasses;
  }

  /**
   * Gets the generated CSS content.
   *
   * @return string
   *   The complete generated CSS string for all known utility classes.
   *   Returns an empty string if no CSS has been generated.
   */
  public function getGeneratedCss(): string {
    return $this->state->get(UtilikitConstants::STATE_GENERATED_CSS, '');
  }

  /**
   * Sets the generated CSS content.
   *
   * @param string $css
   *   The complete CSS string to store. This typically contains all CSS
   *   rules for the currently known utility classes.
   */
  public function setGeneratedCss(string $css): void {
    $this->state->set(UtilikitConstants::STATE_GENERATED_CSS, $css);
  }

  /**
   * Gets the timestamp when CSS was last updated.
   *
   * @return int
   *   Unix timestamp of when the CSS was last generated or updated.
   *   Returns 0 if no timestamp has been set.
   */
  public function getCssTimestamp(): int {
    return $this->state->get(UtilikitConstants::STATE_CSS_TIMESTAMP, 0);
  }

  /**
   * Updates the CSS timestamp to the current time.
   *
   * This is used for cache invalidation and determining if CSS files
   * need to be regenerated.
   */
  public function updateCssTimestamp(): void {
    $this->state->set(UtilikitConstants::STATE_CSS_TIMESTAMP, time());
  }

  /**
   * Clears all UtiliKit-specific state data.
   *
   * This removes the generated CSS and known classes from state storage.
   * Used during module resets, mode switches, and uninstallation.
   */
  public function clearUtiliKitData(): void {
    $this->state->deleteMultiple([
      UtilikitConstants::STATE_GENERATED_CSS,
      UtilikitConstants::STATE_KNOWN_CLASSES,
    ]);
  }

  /**
   * Gets the timestamp of the last cleanup operation.
   *
   * @return int
   *   Unix timestamp of when cleanup was last performed. Returns 0 if
   *   no cleanup has been recorded.
   */
  public function getLastCleanup(): int {
    return $this->state->get(UtilikitConstants::STATE_LAST_CLEANUP, 0);
  }

  /**
   * Sets the timestamp for the last cleanup operation.
   *
   * @param int|null $timestamp
   *   Unix timestamp to record. If NULL, the current time will be used.
   */
  public function setLastCleanup(?int $timestamp = NULL): void {
    $this->state->set(UtilikitConstants::STATE_LAST_CLEANUP, $timestamp ?? time());
  }

  /**
   * Gets rate limiting data for a specific operation.
   *
   * @param string $key
   *   The rate limit key identifier (e.g., 'ajax_update', 'css_generation').
   *
   * @return array
   *   An associative array containing:
   *   - 'current': Current usage count for the rate limit period
   *   - 'reset_time': Unix timestamp when the rate limit period resets
   */
  public function getRateLimitData(string $key): array {
    return [
      'current' => $this->state->get("utilikit_rate_limit:$key", 0),
      'reset_time' => $this->state->get("utilikit_rate_limit:{$key}_reset", 0),
    ];
  }

  /**
   * Sets rate limiting data for a specific operation.
   *
   * @param string $key
   *   The rate limit key identifier.
   * @param int $current
   *   The current usage count for the rate limit period.
   * @param int $resetTime
   *   Unix timestamp when the rate limit period should reset.
   */
  public function setRateLimitData(string $key, int $current, int $resetTime): void {
    $this->state->set("utilikit_rate_limit:$key", $current);
    $this->state->set("utilikit_rate_limit:{$key}_reset", $resetTime);
  }

  /**
   * Clears rate limiting data for a specific operation.
   *
   * @param string $key
   *   The rate limit key identifier to clear.
   */
  public function clearRateLimitData(string $key): void {
    $this->state->deleteMultiple([
      "utilikit_rate_limit:$key",
      "utilikit_rate_limit:{$key}_reset",
    ]);
  }

  /**
   * Gets all state keys used by UtiliKit.
   *
   * This is useful for cleanup operations and debugging.
   *
   * @return array
   *   An array of state key names used by UtiliKit for core operations.
   *   Does not include rate limiting keys as they are dynamic.
   */
  public function getAllUtiliKitStateKeys(): array {
    return [
      UtilikitConstants::STATE_KNOWN_CLASSES,
      UtilikitConstants::STATE_GENERATED_CSS,
      UtilikitConstants::STATE_CSS_TIMESTAMP,
      UtilikitConstants::STATE_LAST_CLEANUP,
    ];
  }

  /**
   * Gets statistical information about current state data.
   *
   * Provides a summary of UtiliKit's current state for debugging,
   * monitoring, and administrative display purposes.
   *
   * @return array
   *   An associative array containing:
   *   - 'known_classes_count': Number of utility classes stored
   *   - 'generated_css_size': Size of generated CSS in bytes
   *   - 'css_timestamp': Unix timestamp of last CSS generation
   *   - 'last_cleanup': Unix timestamp of last cleanup operation
   */
  public function getStateStatistics(): array {
    $knownClasses = $this->getKnownClasses();
    $generatedCss = $this->getGeneratedCss();

    return [
      'known_classes_count' => count($knownClasses),
      'generated_css_size' => strlen($generatedCss),
      'css_timestamp' => $this->getCssTimestamp(),
      'last_cleanup' => $this->getLastCleanup(),
    ];
  }

}
