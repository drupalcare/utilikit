<?php

namespace Drupal\utilikit\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Asset\AssetOptimizerInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\File\FileExists;

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
   * The state manager service.
   *
   * @var \Drupal\utilikit\Service\UtilikitStateManager
   */
  protected UtilikitStateManager $stateManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * The configuration factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The CSS optimizer service.
   *
   * @var \Drupal\Core\Asset\AssetOptimizerInterface
   */
  protected AssetOptimizerInterface $cssOptimizer;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

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
    UtilikitStateManager $stateManager,
    FileSystemInterface $fileSystem,
    FileUrlGeneratorInterface $fileUrlGenerator,
    ConfigFactoryInterface $configFactory,
    AssetOptimizerInterface $cssOptimizer,
    LoggerInterface $logger,
  ) {
    $this->stateManager = $stateManager;
    $this->fileSystem = $fileSystem;
    $this->fileUrlGenerator = $fileUrlGenerator;
    $this->configFactory = $configFactory;
    $this->cssOptimizer = $cssOptimizer;
    $this->logger = $logger;
  }

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
   */
  private function optimizeCssFile(string $css_file_uri): void {
    try {
      $css_content = file_get_contents($css_file_uri);
      if ($css_content === FALSE) {
        $this->logger->error('Failed to read CSS file for optimization: @uri', [
          '@uri' => $css_file_uri,
        ]);
        return;
      }

      try {
        $css_asset = [
          'type' => 'file',
          'data' => $css_file_uri,
          'weight' => 0,
          'group' => 0,
          'every_page' => FALSE,
          'media' => 'all',
          'preprocess' => TRUE,
          'browsers' => ['IE' => TRUE, '!IE' => TRUE],
        ];

        $optimized_assets = $this->cssOptimizer->optimize($css_asset);

        if (!empty($optimized_assets) && isset($optimized_assets['data'])) {
          $optimized_css = file_get_contents($optimized_assets['data']);
        }
        else {
          $optimized_css = $this->minifyCss($css_content);
        }
      }
      catch (\Exception $e) {
        $optimized_css = $this->minifyCss($css_content);
      }

      if (!empty($optimized_css) && $optimized_css !== $css_content) {
        $this->fileSystem->saveData($optimized_css, $css_file_uri, FileExists::Replace);

        $original_size = strlen($css_content);
        $optimized_size = strlen($optimized_css);
        $reduction = round((1 - $optimized_size / $original_size) * 100, 1);

        $this->logger->info('CSS optimized: @original KB → @optimized KB (@reduction% reduction)', [
          '@original' => round($original_size / 1024, 1),
          '@optimized' => round($optimized_size / 1024, 1),
          '@reduction' => $reduction,
        ]);
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('CSS optimization failed: @message. Using unoptimized CSS.', [
        '@message' => $e->getMessage(),
      ]);
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
        @rmdir($dir_real_path);

        $parent_dir = $this->fileSystem->dirname($css_directory);
        $parent_real_path = $this->fileSystem->realpath($parent_dir);
        if ($parent_real_path && is_dir($parent_real_path)) {
          $parent_files = scandir($parent_real_path);
          if ($parent_files !== FALSE && count($parent_files) <= 2) {
            @rmdir($parent_real_path);
          }
        }
      }
    }
  }

  /**
   * Gets the URL for the static CSS file with cache busting parameters.
   *
   * Generates a public URL for the static CSS file with cache-busting
   * parameters based on CSS content hash and timestamp. Returns NULL
   * if the file doesn't exist.
   *
   * @return string|null
   *   The cache-busted URL to the static CSS file, or NULL if file doesn't
   *   exist.
   */
  public function getStaticCssUrl(): ?string {
    $css_file_uri = UtilikitConstants::CSS_DIRECTORY . '/' . UtilikitConstants::CSS_FILENAME;
    $css_real_path = $this->fileSystem->realpath($css_file_uri);

    if ($css_real_path && file_exists($css_real_path)) {
      $static_css = $this->stateManager->getGeneratedCss();
      $css_hash = substr(md5($static_css), 0, 8);

      $timestamp = $this->stateManager->getCssTimestamp();

      $base_url = $this->fileUrlGenerator->generateAbsoluteString($css_file_uri);
      return $base_url . '?v=' . $css_hash . '&t=' . $timestamp;
    }

    return NULL;
  }

}
