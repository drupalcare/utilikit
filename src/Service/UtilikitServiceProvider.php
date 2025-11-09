<?php

declare(strict_types=1);

namespace Drupal\utilikit\Service;

use Psr\Log\LoggerInterface;

/**
 * Main service provider for Utilikit module operations.
 *
 * This class acts as a facade that coordinates between various Utilikit
 * services to provide high-level functionality such as CSS generation,
 * file management, content scanning, and switching between rendering modes.
 *
 * The service provider ensures proper coordination between:
 * - CSS generation from utility classes
 * - File system operations for static CSS files
 * - Content scanning for utility class discovery
 * - State management for persistent data
 * - Cache management for performance optimization
 */
class UtilikitServiceProvider {

  /**
   * The CSS generator service.
   *
   * @var \Drupal\utilikit\Service\UtilikitCssGenerator
   */
  protected UtilikitCssGenerator $cssGenerator;

  /**
   * The file manager service.
   *
   * @var \Drupal\utilikit\Service\UtilikitFileManager
   */
  protected UtilikitFileManager $fileManager;

  /**
   * The content scanner service.
   *
   * @var \Drupal\utilikit\Service\UtilikitContentScanner
   */
  protected UtilikitContentScanner $contentScanner;

  /**
   * The state manager service.
   *
   * @var \Drupal\utilikit\Service\UtilikitStateManager
   */
  protected UtilikitStateManager $stateManager;

  /**
   * The cache manager service.
   *
   * @var \Drupal\utilikit\Service\UtilikitCacheManagerInterface
   */
  protected UtilikitCacheManagerInterface $cacheManager;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The test CSS generator service.
   *
   * @var \Drupal\utilikit\Service\UtilikitTestCssGenerator|null
   */
  protected ?UtilikitTestCssGenerator $testCssGenerator;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected \Drupal\Core\Config\ConfigFactoryInterface $configFactory;

  /**
   * Constructs a new UtilikitServiceProvider.
   *
   * @param \Drupal\utilikit\Service\UtilikitCssGenerator $cssGenerator
   *   The CSS generator service for creating CSS from utility classes.
   * @param \Drupal\utilikit\Service\UtilikitFileManager $fileManager
   *   The file manager service for handling static CSS file operations.
   * @param \Drupal\utilikit\Service\UtilikitContentScanner $contentScanner
   *   The content scanner service for discovering utility classes.
   * @param \Drupal\utilikit\Service\UtilikitStateManager $stateManager
   *   The state manager service for persistent data operations.
   * @param \Drupal\utilikit\Service\UtilikitCacheManagerInterface $cacheManager
   *   The cache manager service for cache operations and invalidation.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service for recording operations and errors.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   * @param \Drupal\utilikit\Service\UtilikitTestCssGenerator|null $testCssGenerator
   *   The test generator service for creating all possible CSS classes.
   */
  public function __construct(
    UtilikitCssGenerator $cssGenerator,
    UtilikitFileManager $fileManager,
    UtilikitContentScanner $contentScanner,
    UtilikitStateManager $stateManager,
    UtilikitCacheManagerInterface $cacheManager,
    LoggerInterface $logger,
    \Drupal\Core\Config\ConfigFactoryInterface $configFactory,
    ?UtilikitTestCssGenerator $testCssGenerator = NULL,
  ) {
    $this->cssGenerator = $cssGenerator;
    $this->fileManager = $fileManager;
    $this->contentScanner = $contentScanner;
    $this->stateManager = $stateManager;
    $this->cacheManager = $cacheManager;
    $this->logger = $logger;
    $this->configFactory = $configFactory;
    $this->testCssGenerator = $testCssGenerator;
  }

  /**
   * Gets the CSS generator service.
   *
   * @return \Drupal\utilikit\Service\UtilikitCssGenerator
   *   The CSS generator service instance.
   */
  public function getCssGenerator(): UtilikitCssGenerator {
    return $this->cssGenerator;
  }

  /**
   * Gets the file manager service.
   *
   * @return \Drupal\utilikit\Service\UtilikitFileManager
   *   The file manager service instance.
   */
  public function getFileManager(): UtilikitFileManager {
    return $this->fileManager;
  }

  /**
   * Gets the content scanner service.
   *
   * @return \Drupal\utilikit\Service\UtilikitContentScanner
   *   The content scanner service instance.
   */
  public function getContentScanner(): UtilikitContentScanner {
    return $this->contentScanner;
  }

  /**
   * Gets the state manager service.
   *
   * @return \Drupal\utilikit\Service\UtilikitStateManager
   *   The state manager service instance.
   */
  public function getStateManager(): UtilikitStateManager {
    return $this->stateManager;
  }

  /**
   * Gets the cache manager service.
   *
   * @return \Drupal\utilikit\Service\UtilikitCacheManagerInterface
   *   The cache manager service instance.
   */
  public function getCacheManager(): UtilikitCacheManagerInterface {
    return $this->cacheManager;
  }

  /**
   * Gets the current rendering mode from configuration.
   *
   * @return string
   *   The rendering mode: 'inline', 'static', or 'head'. Defaults to 'inline'
   *   if not configured.
   */
  public function getRenderingMode(): string {
    $config = $this->configFactory->get('utilikit.settings');
    return $config->get('rendering_mode') ?? 'inline';
  }

  /**
   * Updates CSS and static file with new utility classes.
   *
   * This method validates the provided classes, updates the known classes
   * state, generates new CSS, and ensures the static CSS file is current.
   * It also invalidates relevant caches to ensure changes are visible.
   *
   * @param array $classes
   *   An array of utility class names to process and add to the CSS.
   *
   * @return bool
   *   TRUE if the CSS and file were successfully updated, FALSE otherwise.
   *   Returns FALSE if no classes provided or no valid classes found.
   */
  public function updateCssAndFile(array $classes): bool {
    if (empty($classes)) {
      return FALSE;
    }

    $validClasses = $this->contentScanner->validateUtilityClasses($classes);
    if (empty($validClasses)) {
      return FALSE;
    }

    $allClasses = $this->stateManager->addKnownClasses($validClasses);
    $css = $this->cssGenerator->generateCssFromClasses($allClasses);
    $this->stateManager->setGeneratedCss($css);

    $result = $this->fileManager->ensureStaticCssFile();
    if ($result) {
      $this->stateManager->updateCssTimestamp();
      $this->cacheManager->clearCssCaches();
    }

    return $result;
  }

  /**
   * Regenerates the static CSS file from existing known classes.
   *
   * This method uses the currently stored known classes to regenerate
   * the CSS and update the static file. Useful for rebuilding CSS
   * without scanning content again. Uses conservative cache clearing
   * to avoid deleting the newly created CSS file.
   *
   * @return bool
   *   TRUE if the CSS was successfully regenerated, FALSE if no known
   *   classes exist or file operations failed.
   *
   * @throws \Exception
   *   Thrown if CSS generation or file operations encounter critical errors.
   */
  public function regenerateStaticCss(): bool {
    try {
      $knownClasses = $this->stateManager->getKnownClasses();

      if (empty($knownClasses)) {
        $this->logger->info('No known classes found for CSS regeneration.');
        return FALSE;
      }

      $classCount = count($knownClasses);
      $this->logger->info('Regenerating CSS with @count known classes.', [
        '@count' => $classCount,
      ]);

      // Generate CSS from all known classes.
      $css = $this->cssGenerator->generateCssFromClasses($knownClasses);

      if (empty($css)) {
        $this->logger->warning('CSS generation produced empty output.');
        return FALSE;
      }

      // Store the generated CSS in state.
      $this->stateManager->setGeneratedCss($css);

      // Ensure the static CSS file is created/updated.
      $fileResult = $this->fileManager->ensureStaticCssFile();

      if (!$fileResult) {
        $this->logger->error('Failed to create static CSS file.');
        return FALSE;
      }

      // Update timestamp and clear caches after successful file creation.
      // Use conservative strategy to avoid deleting the file we just created.
      $this->stateManager->updateCssTimestamp();
      $this->cacheManager->clearCachesWithStrategy(['strategy' => 'conservative']);

      $this->logger->info('Static CSS regenerated successfully. Size: @size bytes.', [
        '@size' => strlen($css),
      ]);

      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('CSS regeneration failed: @message', [
        '@message' => $e->getMessage(),
        '@trace' => $e->getTraceAsString(),
      ]);

      // Re-throw for proper error handling upstream.
      throw $e;
    }
  }

  /**
   * Switches Utilikit to static rendering mode.
   *
   * This method performs a complete transition to static mode by:
   * - Clearing existing Utilikit data
   * - Cleaning up old static files
   * - Scanning all content for utility classes
   * - Generating and saving CSS to a static file
   * - Clearing all caches.
   *
   * @return array
   *   An associative array containing:
   *   - 'classes_count': Number of unique utility classes found
   *   - 'scanned_count': Total number of entities scanned
   */
  public function switchToStaticMode(): array {
    $this->stateManager->clearUtilikitData();
    $this->fileManager->cleanupStaticFiles();

    $scanResult = $this->contentScanner->scanAllContent();
    $knownClasses = $scanResult['classes'];
    $totalScanned = $scanResult['scanned_count'];

    if (!empty($knownClasses)) {
      $this->stateManager->setKnownClasses($knownClasses);
      $css = $this->cssGenerator->generateCssFromClasses($knownClasses);
      $this->stateManager->setGeneratedCss($css);
      $this->fileManager->ensureStaticCssFile();
    }
    else {
      $this->stateManager->setGeneratedCss('/* Utilikit Static CSS - Add utility classes to your content */');
      $this->fileManager->ensureStaticCssFile();
    }

    $this->stateManager->updateCssTimestamp();
    $this->cacheManager->clearAllCaches();

    return [
      'classes_count' => count($knownClasses),
      'scanned_count' => $totalScanned,
    ];
  }

  /**
   * Gets the test CSS generator service.
   *
   * @return \Drupal\utilikit\Service\UtilikitTestCssGenerator|null
   *   The test CSS generator service, or NULL if not available.
   */
  public function getTestCssGenerator(): ?UtilikitTestCssGenerator {
    return $this->testCssGenerator;
  }

  /**
   * Switches Utilikit to inline rendering mode.
   *
   * This method transitions from static to inline mode by:
   * - Cleaning up static CSS files
   * - Updating the CSS timestamp for cache invalidation
   * - Clearing all caches.
   *
   * The known classes are preserved for potential future use.
   *
   * @return int
   *   The number of known utility classes that will continue to be
   *   available in inline mode.
   */
  public function switchToInlineMode(): int {
    $this->fileManager->cleanupStaticFiles();

    $this->stateManager->updateCssTimestamp();
    $this->cacheManager->clearAllCaches();

    return count($this->stateManager->getKnownClasses());
  }

}
