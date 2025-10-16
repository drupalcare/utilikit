<?php

namespace Drupal\utilikit\Service;

/**
 * Defines constants and configuration values for UtiliKit operations.
 *
 * This class centralizes all constant values used throughout the UtiliKit
 * module including rate limits, processing thresholds, cache tags, file
 * paths, validation patterns, and default configuration values. Constants
 * are organized by category for easy maintenance and reference.
 */
class UtilikitConstants {

  // =======================================================================
  // RATE LIMITING AND PERFORMANCE
  // ======================================================================== */
  /**
   * Maximum number of requests allowed per minute for rate limiting.
   */
  public const RATE_LIMIT_REQUESTS_PER_MINUTE = 60;

  /**
   * Time window in seconds for rate limiting calculations.
   */
  public const RATE_LIMIT_WINDOW_SECONDS = 60;

  /**
   * Maximum number of utility classes that can be processed per request.
   */
  public const MAX_CLASSES_PER_REQUEST = 5000;

  /**
   * Threshold for logging warnings about excessive utility class counts.
   */
  public const MAX_CLASSES_WARNING_THRESHOLD = 50000;

  /**
   * Default batch size for processing utility classes in chunks.
   */
  public const BATCH_SIZE_DEFAULT = 50;

  /**
   * Maximum number of queue items to process per cron run.
   */
  public const QUEUE_PROCESSING_LIMIT = 5;

  // ======================================================================= */
  // LOCKING AND CONCURRENCY
  // ======================================================================= */
  /**
   * Timeout in seconds for CSS update lock acquisition.
   */
  public const CSS_UPDATE_LOCK_TIMEOUT = 5;

  /**
   * Wait time in seconds between CSS update lock attempts.
   */
  public const CSS_UPDATE_LOCK_WAIT = 2;

  // ======================================================================= */
  // VALIDATION AND LIMITS
  // ======================================================================= */
  /**
   * Maximum allowed length for utility class names in characters.
   */
  public const MAX_CLASS_NAME_LENGTH = 200;

  // ======================================================================= */
  // CACHE TAGS AND CONTEXTS
  // ======================================================================= */
  /**
   * Cache tag for UtiliKit configuration changes.
   */
  public const CACHE_TAG_CONFIG = 'config:utilikit.settings';

  /**
   * Cache tag for CSS-related cache entries.
   */
  public const CACHE_TAG_CSS = 'utilikit:css';

  /**
   * Cache tag for inline mode specific cache entries.
   */
  public const CACHE_TAG_INLINE_MODE = 'utilikit:mode:inline';

  /**
   * Cache tag for static mode specific cache entries.
   */
  public const CACHE_TAG_STATIC_MODE = 'utilikit:mode:static';

  /**
   * Cache tag for timestamp-based cache invalidation.
   */
  public const CACHE_TAG_TIMESTAMP = 'utilikit:timestamp';

  /**
   * Cache tag for rendered output cache entries.
   */
  public const CACHE_TAG_RENDERED = 'rendered';

  /**
   * Cache tag for library information cache entries.
   */
  public const CACHE_TAG_LIBRARY_INFO = 'library_info';

  // ======================================================================= */
  // RESPONSIVE BREAKPOINTS
  // ======================================================================= */
  /**
   * Default active breakpoints for responsive utility classes.
   */
  public const DEFAULT_BREAKPOINTS = [
    'sm' => 'sm',
    'md' => 'md',
    'lg' => 'lg',
    'xl' => 'xl',
    'xxl' => 'xxl',
  ];

  /**
   * Pixel values for responsive breakpoints (min-width values).
   */
  public const BREAKPOINT_VALUES = [
    'sm' => 576,
    'md' => 768,
    'lg' => 992,
    'xl' => 1200,
    'xxl' => 1400,
  ];

  // ======================================================================= */
  // ENTITY AUTO-UPDATE CONFIGURATION
  // ======================================================================= */
  /**
   * Entity types and their corresponding configuration keys for auto-update.
   */
  public const array AUTO_UPDATE_ENTITY_TYPES = [
    'node' => 'update_on_node_save',
    'block_content' => 'update_on_block_save',
    'paragraph' => 'update_on_paragraph_save',
  ];

  // ======================================================================= */
  // QUEUE AND LOCK IDENTIFIERS
  // ======================================================================= */
  /**
   * Queue name for CSS processing queue worker.
   */
  public const string QUEUE_CSS_PROCESSOR = 'utilikit_css_processor';

  /**
   * Lock identifier for CSS update operations.
   */
  public const string LOCK_CSS_UPDATE = 'utilikit_css_update';

  // ======================================================================= */
  // STATE API KEYS
  // ======================================================================= */
  /**
   * State key for storing known utility classes array.
   */
  public const string STATE_KNOWN_CLASSES = 'utilikit.known_classes';

  /**
   * State key for storing generated CSS content.
   */
  public const string STATE_GENERATED_CSS = 'utilikit.generated_css';

  /**
   * State key for storing CSS generation timestamp.
   */
  public const STATE_CSS_TIMESTAMP = 'utilikit.css_timestamp';

  /**
   * State key for storing last cleanup operation timestamp.
   */
  public const string STATE_LAST_CLEANUP = 'utilikit.last_cleanup';

  // ======================================================================= */
  // VALIDATION PATTERNS
  // ======================================================================= */
  /**
   * Regular expression pattern for validating utility class names.
   */
  public const CLASS_VALIDATION_PATTERN = '/^uk-(?:(?:sm|md|lg|xl|xxl)-)?[a-z]{2,4}--[a-zA-Z0-9\-_.%]+$/';

  // ======================================================================= */
  // HTML EXTRACTION PATTERNS
  // ======================================================================= */
  /**
   * Pattern for extracting HTML elements with UtiliKit classes.
   */
  public const HTML_CLASS_EXTRACTION_PATTERN = '/<[^>]+class=["\']([^"\']*utilikit[^"\']*)["\'][^>]*>/i';

  /**
   * Pattern for extracting HTML elements with uk- prefixed classes.
   */
  public const HTML_UK_CLASS_PATTERN = '/<[^>]+class=["\']([^"\']*uk-[^"\']*)["\'][^>]*>/i';

  /**
   * Pattern for directly matching uk- prefixed utility classes.
   */
  public const DIRECT_UK_CLASS_PATTERN = '/uk-[a-zA-Z0-9\-_]+/';

  // ======================================================================= */
  // FILE SYSTEM PATHS
  // ======================================================================= */
  /**
   * Directory path for storing UtiliKit CSS files.
   */
  public const string CSS_DIRECTORY = 'public://css/utilikit';

  /**
   * Filename for the main generated CSS file.
   */
  public const string CSS_FILENAME = 'generated.css';

  /**
   * Filename for the complete test CSS file.
   */
  public const TEST_CSS_FILENAME = 'utilikit-test-complete.css';

  // ======================================================================= */
  // CSS AND TIMING CONFIGURATION
  // ======================================================================= */
  /**
   * Cache TTL in seconds for CSS-related cache entries.
   */
  public const CSS_CACHE_TTL = 3600;

  /**
   * Length of CSS cache hash for cache busting.
   */
  public const CSS_HASH_LENGTH = 8;

  /**
   * Default debounce delay in milliseconds for resize events.
   */
  public const DEBOUNCE_DEFAULT = 50;

  /**
   * Minimum allowed debounce delay in milliseconds.
   */
  public const DEBOUNCE_MIN = 0;

  /**
   * Maximum allowed debounce delay in milliseconds.
   */
  public const DEBOUNCE_MAX = 500;

  /**
   * Step increment for debounce delay configuration.
   */
  public const DEBOUNCE_STEP = 10;

}
