<?php

declare(strict_types=1);

namespace Drupal\utilikit\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\utilikit\Service\UtilikitConstants;

/**
 * Runtime requirements for the status report.
 *
 * WHY THIS MOVED OUT OF utilikit.install. `hook_requirements($phase)` was
 * split in Drupal 11.2 into `hook_runtime_requirements()` and
 * `hook_install_requirements()`. On Drupal 12 the old name is no longer a
 * procedural hook: the hook system tries to resolve it as a hook CLASS and
 * throws `Class "utilikit_requirements" does not exist`.
 *
 * That is not a cosmetic deprecation. Measured on d12a.local 2026-09-04,
 * `drush en utilikit_help utilikit_examples utilikit_playground utilikit_test`
 * failed outright with that exception — on Drupal 12 the parent could be
 * enabled but not one of its submodules could be installed.
 *
 * Only the runtime phase was ever implemented, so the `$phase` check
 * disappears with the rename: `hook_runtime_requirements()` IS the runtime
 * phase.
 */
final class UtilikitRequirements {

  use StringTranslationTrait;

  /**
   * Constructs the requirements reporter.
   *
   * Injected rather than fetched. The body of this method came from a
   * procedural .install file where `\Drupal::` is the normal idiom; inside a
   * class it is not, and utilikit's own PHPStan config says so.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   Resolves the CSS directory's real path.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Reads utilikit.settings.
   * @param \Drupal\Core\State\StateInterface $state
   *   Reads the tracked class list.
   */
  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly StateInterface $state,
  ) {}

  /**
   * Reports UtiliKit's runtime status.
   *
   * @return array<string, array<string, mixed>>
   *   Requirements keyed by machine name, for the status report.
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $requirements = [];

    $file_system = $this->fileSystem;
    $config = $this->configFactory->get('utilikit.settings');
    $rendering_mode = $config->get('rendering_mode') ?? 'inline';
    $directory = UtilikitConstants::CSS_DIRECTORY;
    $real_path = $file_system->realpath($directory);
    $scanning_types = $config->get('scanning_entity_types') ?? [];

    $requirements['utilikit_scanning'] = [
      'title' => $this->t('UtiliKit scanning scope'),
      'value' => $this->t('@count entity types', ['@count' => count($scanning_types)]),
      'severity' => RequirementSeverity::Info,
      'description' => $this->t('Scanning: @types', ['@types' => implode(', ', $scanning_types)]),
    ];

    // Check CSS directory - severity depends on rendering mode.
    if (!$real_path || !is_dir($real_path)) {
      // Directory doesn't exist.
      if ($rendering_mode === 'static') {
        // WARNING in static mode - directory will be created automatically
        // on the next page load or form save.
        $requirements['utilikit_directory'] = [
          'title' => $this->t('UtiliKit CSS directory'),
          'value' => $this->t('Directory does not exist'),
          'severity' => RequirementSeverity::Warning,
          'description' => $this->t('The UtiliKit CSS directory at %path does not exist. It will be created automatically on the next page load. If the issue persists, check file permissions.', [
            '%path' => $directory,
          ]),
        ];
      }
      else {
        // WARNING in inline/head mode - directory not needed but should exist.
        $requirements['utilikit_directory'] = [
          'title' => $this->t('UtiliKit CSS directory'),
          'value' => $this->t('Directory does not exist'),
          'severity' => RequirementSeverity::Warning,
          'description' => $this->t('The UtiliKit CSS directory at %path does not exist. This is normal for inline/head mode. The directory will be created automatically if you switch to static mode.', [
            '%path' => $directory,
          ]),
        ];
      }
    }
    elseif (!is_writable($real_path)) {
      // Directory exists but not writable.
      if ($rendering_mode === 'static') {
        // ERROR in static mode - must be writable.
        $requirements['utilikit_directory'] = [
          'title' => $this->t('UtiliKit CSS directory'),
          'value' => $this->t('Not writable'),
          'severity' => RequirementSeverity::Error,
          'description' => $this->t('The UtiliKit CSS directory at %path is not writable. This is required for static mode.', [
            '%path' => $directory,
          ]),
        ];
      }
      else {
        // WARNING in inline/head mode.
        $requirements['utilikit_directory'] = [
          'title' => $this->t('UtiliKit CSS directory'),
          'value' => $this->t('Not writable'),
          'severity' => RequirementSeverity::Warning,
          'description' => $this->t('The UtiliKit CSS directory at %path exists but is not writable. This will cause issues if you switch to static mode.', [
            '%path' => $directory,
          ]),
        ];
      }
    }
    else {
      // Directory exists and is writable - all good.
      $requirements['utilikit_directory'] = [
        'title' => $this->t('UtiliKit CSS directory'),
        'value' => $this->t('Exists and writable'),
        'severity' => RequirementSeverity::OK,
      ];
    }

    // Check if static mode is enabled and validate CSS file existence.
    if ($rendering_mode === 'static') {
      $css_file = UtilikitConstants::CSS_DIRECTORY . '/' . UtilikitConstants::CSS_FILENAME;
      $css_real_path = $file_system->realpath($css_file);

      if (!$css_real_path || !file_exists($css_real_path)) {
        $requirements['utilikit_static_css'] = [
          'title' => $this->t('UtiliKit static CSS'),
          'value' => $this->t('File missing'),
          'severity' => RequirementSeverity::Warning,
          'description' => $this->t('Static mode is enabled but the CSS file does not exist. Visit the <a href="@url">settings page</a> to generate it.', [
            '@url' => Url::fromRoute('utilikit.settings')->toString(),
          ]),
        ];
      }
      else {
        // Show file info if it exists.
        $file_size = filesize($css_real_path);
        $known_classes = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
        $requirements['utilikit_static_css'] = [
          'title' => $this->t('UtiliKit static CSS'),
          'value' => $this->t('File exists (@size, @count classes)', [
            '@size' => (string) ByteSizeMarkup::create((int) $file_size),
            '@count' => count($known_classes),
          ]),
          'severity' => RequirementSeverity::OK,
        ];
      }
    }

    // Validate overall configuration status.
    $mode_labels = [
      'static' => $this->t('Static'),
      'inline' => $this->t('Inline'),
      'head' => $this->t('Head'),
    ];
    $requirements['utilikit_config'] = [
      'title' => $this->t('UtiliKit configuration'),
      'value' => $this->t('Mode: @mode', [
        '@mode' => $mode_labels[$rendering_mode] ?? $this->t('Unknown'),
      ]),
      'severity' => RequirementSeverity::OK,
      'description' => $this->t('UtiliKit is running in @mode mode.', [
        '@mode' => $mode_labels[$rendering_mode] ?? $this->t('unknown'),
      ]),
    ];

    // Monitor performance and provide optimization recommendations.
    $known_classes = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    if (count($known_classes) > UtilikitConstants::MAX_CLASSES_WARNING_THRESHOLD) {
      $requirements['utilikit_performance'] = [
        'title' => $this->t('UtiliKit performance'),
        'value' => $this->t('Large number of classes detected'),
        'severity' => RequirementSeverity::Warning,
        'description' => $this->t('You have @count utility classes tracked. Consider cleaning up unused classes for optimal performance.', [
          '@count' => count($known_classes),
        ]),
      ];
    }

    return $requirements;
  }

}
