<?php

declare(strict_types=1);

namespace Drupal\utilikit\Service;

use Drupal\Core\Asset\AssetOptimizerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Psr\Log\LoggerInterface;

/**
 * Manages CSS file operations for UtiliKit static mode.
 *
 * This service handles the creation, optimization, cleanup, and URL generation
 * for UtiliKit CSS files when operating in static mode. It provides file
 * system operations with proper error handling, CSS optimization capabilities,
 * and cache-busting URL generation.
 */
class UtilikitFileManager {

  /**
   * Constructs a new UtilikitFileManager object.
   *
   * @param \Drupal\utilikit\Service\UtilikitStateManager $stateManager
   *   The state manager service for accessing CSS data.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service for file operations.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file URL generator service for creating file URLs.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   * @param \Drupal\Core\Asset\AssetOptimizerInterface $cssOptimizer
   *   The CSS optimizer service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service for recording file operations.
   */
  public function __construct(
    protected readonly UtilikitStateManager $stateManager,
    protected readonly FileSystemInterface $fileSystem,
    protected readonly FileUrlGeneratorInterface $fileUrlGenerator,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly AssetOptimizerInterface $cssOptimizer,
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * Ensures the static CSS file exists and is up to date.
   *
   * Creates or updates the static CSS file with the current generated CSS
   * content from the state manager. Handles directory preparation, file
   * writing, and optional CSS optimization based on configuration.
   *
   * @return bool
   *   TRUE if the CSS file was successfully created/updated, FALSE otherwise.
   */
  public function ensureStaticCssFile(): bool {
    $config = $this->configFactory->get('utilikit.settings');
    if (!$config->get('rendering_mode')) {
      return FALSE;
    }

    try {
      $static_css = $this->stateManager->getGeneratedCss();

      if (empty($static_css)) {
        $this->logger->warning('No CSS to write to static file.');
        return FALSE;
      }

      $css_file_uri = UtilikitConstants::CSS_DIRECTORY . '/' . UtilikitConstants::CSS_FILENAME;
      $css_directory = $this->fileSystem->dirname($css_file_uri);

      if (!$this->fileSystem->prepareDirectory($css_directory, FileSystemInterface::CREATE_DIRECTORY)) {
        $this->logger->error('Failed to prepare directory for static CSS: @dir', [
          '@dir' => $css_directory,
        ]);
        return FALSE;
      }

      $result = $this->fileSystem->saveData($static_css, $css_file_uri, FileExists::Replace);
      if ($result === FALSE) {
        $this->logger->error('Failed to save static CSS file to: @uri', [
          '@uri' => $css_file_uri,
        ]);
        return FALSE;
      }

      if ($config->get('optimize_css')) {
        $this->optimizeCssFile($css_file_uri);
      }

      $this->logger->info('Successfully saved static CSS file to: @uri', [
        '@uri' => $css_file_uri,
      ]);

      return TRUE;

    }
    catch (\Exception $e) {
      $this->logger->error('Exception while saving static CSS: @message', [
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Optimizes a CSS file using Drupal's CSS optimizer or fallback minifier.
   *
   * Attempts to optimize the CSS file using Drupal's built-in CSS optimizer
   * service. If that fails, falls back to a simple minification process.
   * Logs the optimization results including file size reduction.
   *
   * @param string $css_file_uri
   *   The URI of the CSS file to optimize.
   *
   * @return bool
   *   TRUE if optimization succeeded, FALSE otherwise.
   */
  private function optimizeCssFile(string $css_file_uri): bool {
    try {
      // Read via stream wrapper URI (e.g. public://) for Drupal compatibility.
      $css_content = file_get_contents($css_file_uri);
      if ($css_content === FALSE || $css_content === '') {
        $this->logger->error('Failed to read CSS file for optimization: @uri', [
          '@uri' => $css_file_uri,
        ]);
        return FALSE;
      }

      $optimized_css = '';

      try {
        // Drupal's CssOptimizer::optimize() expects a real file path in
        // 'data' and returns the optimized CSS as a string.
        $real_path = $this->fileSystem->realpath($css_file_uri);
        if ($real_path && file_exists($real_path)) {
          $css_asset = [
            'type' => 'file',
            'data' => $real_path,
            'weight' => 0,
            'group' => 0,
            'every_page' => FALSE,
            'media' => 'all',
            'preprocess' => TRUE,
            'browsers' => ['IE' => TRUE, '!IE' => TRUE],
          ];

          $result = $this->cssOptimizer->optimize($css_asset);

          // optimize() returns a string of optimized CSS.
          if (is_string($result) && $result !== '') {
            $optimized_css = $result;
          }
        }
      }
      catch (\Exception $e) {
        // Drupal optimizer failed, fall through to minifyCss().
      }

      // Fallback to built-in minifier.
      if ($optimized_css === '') {
        $optimized_css = $this->minifyCss($css_content);
      }

      if ($optimized_css !== '' && $optimized_css !== $css_content) {
        $this->fileSystem->saveData($optimized_css, $css_file_uri, FileExists::Replace);

        $original_size = strlen($css_content);
        $optimized_size = strlen($optimized_css);
        $reduction = ($original_size > 0)
          ? round((1 - $optimized_size / $original_size) * 100, 1)
          : 0;

        $this->logger->info('CSS optimized: @original KB → @optimized KB (@reduction% reduction)', [
          '@original' => round($original_size / 1024, 1),
          '@optimized' => round($optimized_size / 1024, 1),
          '@reduction' => $reduction,
        ]);
      }

      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->warning('CSS optimization failed: @message. Using unoptimized CSS.', [
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Minifies CSS content by removing comments and whitespace.
   *
   * Provides a fallback CSS minification when Drupal's CSS optimizer
   * is not available or fails. Removes comments, normalizes whitespace,
   * and optimizes selector and property formatting.
   *
   * @param string $css
   *   The CSS content to minify.
   *
   * @return string
   *   The minified CSS content.
   */
  public function minifyCss(string $css): string {
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    $css = strtr($css, [
      "\r\n" => '',
      "\r" => '',
      "\n" => '',
      "\t" => '',
    ]);
    $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);
    $css = str_replace(';}', '}', $css);
    $css = preg_replace('/\s*>\s*/', '>', $css);
    $css = preg_replace('/\s*\+\s*/', '+', $css);
    $css = preg_replace('/\s*~\s*/', '~', $css);
    $css = preg_replace('/;(?=\s*})/', '', $css);
    $css = preg_replace('/\s+/', ' ', $css);
    $css = preg_replace('/@media\s+/', '@media ', $css);
    return trim($css);
  }

  /**
   * Cleans up UtiliKit static CSS files and directories.
   *
   * Removes the static CSS file and attempts to clean up empty directories
   * in the UtiliKit file structure. Safely handles missing files and
   * directories without throwing exceptions.
   */
  public function cleanupStaticFiles(): void {
    $css_file_uri = UtilikitConstants::CSS_DIRECTORY . '/' . UtilikitConstants::CSS_FILENAME;
    $css_directory = $this->fileSystem->dirname($css_file_uri);

    $css_real_path = $this->fileSystem->realpath($css_file_uri);
    if ($css_real_path && file_exists($css_real_path)) {
      $this->fileSystem->delete($css_file_uri);
    }

    $dir_real_path = $this->fileSystem->realpath($css_directory);
    if ($dir_real_path && is_dir($dir_real_path)) {
      $files = scandir($dir_real_path);
      if ($files !== FALSE && count($files) <= 2) {
        if (!rmdir($dir_real_path)) {
          $this->logger->debug('Could not remove empty directory: @path', ['@path' => $dir_real_path]);
        }

        $parent_dir = $this->fileSystem->dirname($css_directory);
        $parent_real_path = $this->fileSystem->realpath($parent_dir);
        if ($parent_real_path && is_dir($parent_real_path)) {
          $parent_files = scandir($parent_real_path);
          if ($parent_files !== FALSE && count($parent_files) <= 2) {
            if (!rmdir($parent_real_path)) {
              $this->logger->debug('Could not remove empty parent directory: @path', ['@path' => $parent_real_path]);
            }
          }
        }
      }
    }
  }

  /**
   * Gets the URL for the static CSS file with cache busting parameters.
   *
   * Generates a relative URL for the static CSS file with a cache-busting
   * query string based on the CSS content hash. Uses 6 characters to match
   * Drupal's standard cache busting format.
   *
   * @return string|null
   *   The cache-busted URL to the static CSS file, or NULL if file doesn't
   *   exist.
   */
  public function getStaticCssUrl(): ?string {
    $css_file_uri = UtilikitConstants::CSS_DIRECTORY . '/' . UtilikitConstants::CSS_FILENAME;
    $css_real_path = $this->fileSystem->realpath($css_file_uri);

    if (!$css_real_path || !file_exists($css_real_path)) {
      return NULL;
    }

    // Read via stream wrapper URI (e.g. public://) for Drupal compatibility.
    $css_content = file_get_contents($css_file_uri);
    if ($css_content === FALSE) {
      return NULL;
    }
    // Use 6 chars to match Drupal's cache busting format.
    $css_hash = substr(md5($css_content), 0, 6);

    // Use generateString() for relative URL (not generateAbsoluteString).
    return $this->fileUrlGenerator->generateString($css_file_uri) . '?' . $css_hash;
  }

}
