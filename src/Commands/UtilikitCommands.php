<?php

declare(strict_types=1);

namespace Drupal\utilikit\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\utilikit\Service\UtilikitServiceProvider;
use Drupal\utilikit\Service\UtilikitConstants;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for UtiliKit module.
 *
 * Provides command-line interface for managing UtiliKit CSS generation,
 * content scanning, mode switching, and maintenance operations.
 */
final class UtilikitCommands extends DrushCommands {

  /**
   * Constructs a UtilikitCommands object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\utilikit\Service\UtilikitServiceProvider $serviceProvider
   *   The UtiliKit service provider.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly UtilikitServiceProvider $serviceProvider,
    protected readonly StateInterface $state,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('config.factory'),
      $container->get('utilikit.service_provider'),
      $container->get('state')
    );
  }

  /**
   * Formats bytes into human-readable size.
   *
   * @param int $bytes
   *   Number of bytes.
   *
   * @return string
   *   Formatted size string.
   */
  private function formatBytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);

    return round($bytes, 2) . ' ' . $units[$pow];
  }

  /**
   * Generate or regenerate UtiliKit static CSS file.
   *
   * @command utilikit:generate
   * @aliases uk-gen,utilikit-generate
   * @usage utilikit:generate
   *   Generate CSS from all known utility classes
   * @usage drush uk-gen
   *   Short alias for generating CSS
   */
  #[CLI\Command(name: 'utilikit:generate', aliases: ['uk-gen', 'utilikit-generate'])]
  #[CLI\Usage(name: 'utilikit:generate', description: 'Generate CSS from all known utility classes')]
  #[CLI\Usage(name: 'drush uk-gen', description: 'Short alias for generating CSS')]
  public function generate(): void {
    $config = $this->configFactory->get('utilikit.settings');
    $mode = $config->get('rendering_mode') ?? 'inline';

    $this->io()->title('UtiliKit CSS Generation');

    // Check mode first.
    if ($mode !== 'static') {
      $this->io()->warning([
        'UtiliKit is currently in ' . strtoupper($mode) . ' mode.',
        'CSS file generation is only available in STATIC mode.',
        '',
        'In ' . $mode . ' mode, CSS is generated ' . ($mode === 'inline' ? 'dynamically by JavaScript' : 'in the page head') . ' on each page load.',
        'No static CSS file is needed or created.',
      ]);
      $this->io()->note([
        'To switch to static mode and enable CSS file generation:',
        '  drush utilikit:mode static',
      ]);
      return;
    }

    $stateManager = $this->serviceProvider->getStateManager();
    $knownClasses = $stateManager->getKnownClasses();

    if (empty($knownClasses)) {
      $this->io()->error([
        'No utility classes found in the system.',
        '',
        'UtiliKit needs to scan your content to discover utility classes before generating CSS.',
      ]);
      $this->io()->note([
        'Run a content scan to find utility classes:',
        '  drush utilikit:scan',
        '',
        'Or check your scanning configuration:',
        '  drush utilikit:status',
      ]);
      return;
    }

    $this->io()->section('Generating Static CSS File');
    $this->io()->text(sprintf('Processing %d utility classes...', count($knownClasses)));

    $result = $this->serviceProvider->regenerateStaticCss();

    if ($result) {
      $css = $stateManager->getGeneratedCss();
      $fileManager = $this->serviceProvider->getFileManager();
      $cssUrl = $fileManager->getStaticCssUrl();
      $timestamp = $stateManager->getCssTimestamp();

      $this->io()->success('CSS file generated successfully');

      $this->io()->definitionList(
        ['Utility Classes' => count($knownClasses)],
        ['CSS File Size' => $this->formatBytes(strlen($css))],
        ['File Location' => $cssUrl ?? 'Error: Unable to determine file URL'],
        ['Generated At' => date('Y-m-d H:i:s', $timestamp)],
      );
    }
    else {
      $this->io()->error([
        'CSS generation failed.',
        '',
        'The CSS generator was unable to create a valid CSS file from the known classes.',
        'This usually indicates a configuration or permission issue.',
      ]);
      $this->io()->note([
        'Check the following:',
        '  1. Ensure the CSS directory is writable',
        '  2. Verify utility classes exist: drush utilikit:classes',
        '  3. Check logs for detailed error messages',
      ]);
    }
  }

  /**
   * Scan content for UtiliKit utility classes.
   *
   * @param array $options
   *   Command options.
   *
   * @command utilikit:scan
   * @aliases uk-scan,utilikit-scan
   * @option entity-types Comma-separated list of entity types to scan (e.g., node,block_content)
   * @option batch-size Number of entities to process per batch (default: 50)
   * @option timeout Maximum execution time in seconds (default: 300)
   * @usage utilikit:scan
   *   Scan all configured entity types
   * @usage utilikit:scan --entity-types=node,paragraph
   *   Scan only nodes and paragraphs
   * @usage drush uk-scan --batch-size=100
   *   Scan with larger batch size
   */
  #[CLI\Command(name: 'utilikit:scan', aliases: ['uk-scan', 'utilikit-scan'])]
  #[CLI\Option(name: 'entity-types', description: 'Comma-separated list of entity types to scan')]
  #[CLI\Option(name: 'batch-size', description: 'Number of entities to process per batch')]
  #[CLI\Option(name: 'timeout', description: 'Maximum execution time in seconds')]
  #[CLI\Usage(name: 'utilikit:scan', description: 'Scan all configured entity types')]
  #[CLI\Usage(name: 'utilikit:scan --entity-types=node,paragraph', description: 'Scan only nodes and paragraphs')]
  public function scan(
    array $options = [
      'entity-types' => NULL,
      'batch-size' => 50,
      'timeout' => 300,
    ],
  ): void {
    $config = $this->configFactory->get('utilikit.settings');
    $mode = $config->get('rendering_mode') ?? 'inline';

    $this->io()->title('UtiliKit Content Scanner');

    // Show current mode.
    if ($mode === 'inline') {
      $this->io()->note([
        'Current mode: INLINE',
        'Scanning will identify utility classes, but they will be processed dynamically by JavaScript.',
        'No static CSS file will be generated.',
      ]);
    }
    else {
      $this->io()->note([
        'Current mode: STATIC',
        'After scanning, a static CSS file will be automatically generated.',
      ]);
    }

    // Override config temporarily if entity-types specified.
    $originalTypes = $config->get('scanning_entity_types');

    if ($options['entity-types']) {
      $entityTypes = array_map('trim', explode(',', $options['entity-types']));
      $editableConfig = $this->configFactory->getEditable('utilikit.settings');
      $editableConfig->set('scanning_entity_types', $entityTypes)->save();
      $this->io()->text(sprintf('Scanning entity types: %s', implode(', ', $entityTypes)));
    }
    else {
      $entityTypes = $originalTypes ?? ['node', 'block_content', 'paragraph'];
      $this->io()->text(sprintf('Scanning configured entity types: %s', implode(', ', $entityTypes)));
    }

    $this->io()->section('Scanning Content');

    $scanner = $this->serviceProvider->getContentScanner();
    $result = $scanner->scanAllContent(
      (int) $options['batch-size'],
      (int) $options['timeout']
    );

    // CRITICAL: Save classes to state immediately after scanning.
    $stateManager = $this->serviceProvider->getStateManager();
    if (!empty($result['classes'])) {
      $stateManager->addKnownClasses($result['classes']);
    }

    // Restore original config if it was changed.
    if ($options['entity-types']) {
      $editableConfig = $this->configFactory->getEditable('utilikit.settings');
      $editableConfig->set('scanning_entity_types', $originalTypes)->save();
    }

    // Check for timeout.
    if (!$result['completed']) {
      $this->io()->warning([
        'Content scan timed out after ' . $result['execution_time'] . ' seconds.',
        '',
        'Some content may not have been scanned.',
        'Partial results have been saved.',
      ]);
    }

    // Display scan results.
    $this->io()->success('Content scan completed');

    $this->io()->definitionList(
      ['Entities Scanned' => number_format($result['scanned_count'])],
      ['Utility Classes Found' => count($result['classes'])],
      ['Execution Time' => $result['execution_time'] . ' seconds'],
      ['Status' => $result['completed'] ? 'Complete' : 'Timed out (partial)'],
    );

    // Show found classes if any.
    if (!empty($result['classes'])) {
      $this->io()->section('Sample Classes Found');
      $sampleClasses = array_slice($result['classes'], 0, 10);
      $this->io()->listing($sampleClasses);

      if (count($result['classes']) > 10) {
        $this->io()->text(sprintf('... and %d more classes', count($result['classes']) - 10));
      }

      // Auto-generate CSS in static mode.
      if ($mode === 'static') {
        $this->io()->section('Generating Static CSS File');
        $this->io()->text('Automatically generating CSS file from scanned classes...');
        $this->generate();
      }
      else {
        $this->io()->note([
          'Classes have been saved and will be processed dynamically in inline mode.',
          'To generate a static CSS file instead:',
          '  1. Switch to static mode: drush utilikit:mode static',
          '  2. Generate CSS: drush utilikit:generate',
        ]);
      }
    }
    else {
      $this->io()->warning([
        'No UtiliKit utility classes found in scanned content.',
        'This could mean:',
        '  • The scanned entity types have no rich-text field to scan.',
        '    A content type with no body field has nothing for the scanner to read,',
        '    and entities of that type are still counted as scanned.',
        '  • The text format strips class attributes on save.',
        '    Basic HTML and Restricted HTML both remove them; Full HTML keeps them.',
        '  • No content is using UtiliKit utility classes yet',
        '  • UtiliKit classes are in entity types not included in the scan',
      ]);

      $this->io()->note([
        'Next steps:',
        '  1. Check that the entity types you are scanning have a rich-text field',
        '     (Structure > Content types > Manage fields).',
        '  2. Check the field\'s text format allows class attributes — use Full HTML.',
        '  3. Add UtiliKit classes to your content, e.g. uk-pd--20 uk-bg--f0f9ff,',
        '     remembering the required "utilikit" base class on the same element.',
        '  4. Verify scanning configuration: drush utilikit:status',
        '  5. Try scanning different entity types: drush utilikit:scan --entity-types=node,block_content',
      ]);
    }
  }

  /**
   * Clear all UtiliKit CSS and reset known classes.
   *
   * @command utilikit:clear
   * @aliases uk-clear,utilikit-clear
   * @usage utilikit:clear
   *   Clear all CSS and reset UtiliKit state
   * @usage drush uk-clear
   *   Short alias for clearing
   */
  #[CLI\Command(name: 'utilikit:clear', aliases: ['uk-clear', 'utilikit-clear'])]
  #[CLI\Usage(name: 'utilikit:clear', description: 'Clear all CSS and reset UtiliKit state')]
  public function clear(): void {
    $this->io()->title('Clearing UtiliKit Data');

    $stateManager = $this->serviceProvider->getStateManager();
    $classCount = count($stateManager->getKnownClasses());

    // Clear state using State API.
    $this->state->deleteMultiple([
      UtilikitConstants::STATE_GENERATED_CSS,
      UtilikitConstants::STATE_KNOWN_CLASSES,
      UtilikitConstants::STATE_CSS_TIMESTAMP,
      UtilikitConstants::STATE_LAST_CLEANUP,
    ]);

    // Clear caches.
    $this->serviceProvider->getCacheManager()->clearAllCaches();

    // Clean up files.
    $fileManager = $this->serviceProvider->getFileManager();
    $fileManager->cleanupStaticFiles();

    $this->io()->success([
      sprintf('Cleared %d utility classes', $classCount),
      'Cleared all caches',
      'Removed static CSS files',
    ]);
  }

  /**
   * Switch UtiliKit rendering mode.
   *
   * @param string $mode
   *   The rendering mode (inline or static).
   *
   * @command utilikit:mode
   * @aliases uk-mode,utilikit-mode
   * @argument mode The rendering mode: inline or static
   * @usage utilikit:mode static
   *   Switch to static mode (pre-generated CSS)
   * @usage utilikit:mode inline
   *   Switch to inline mode (dynamic CSS)
   * @usage drush uk-mode static
   *   Short alias for mode switching
   */
  #[CLI\Command(name: 'utilikit:mode', aliases: ['uk-mode', 'utilikit-mode'])]
  #[CLI\Argument(name: 'mode', description: 'The rendering mode: inline or static')]
  #[CLI\Usage(name: 'utilikit:mode static', description: 'Switch to static mode')]
  #[CLI\Usage(name: 'utilikit:mode inline', description: 'Switch to inline mode')]
  public function switchMode(string $mode): void {
    // Validate mode.
    if (!in_array($mode, ['inline', 'static', 'head'], TRUE)) {
      $this->io()->error('Mode must be "inline", "static", or "head"');
      return;
    }

    $config = $this->configFactory->getEditable('utilikit.settings');
    $oldMode = $config->get('rendering_mode') ?? 'inline';

    if ($oldMode === $mode) {
      $this->io()->note(sprintf('Already in %s mode', $mode));
      return;
    }

    $this->io()->title(sprintf('Switching from %s to %s mode', $oldMode, $mode));

    // Save new mode.
    $config->set('rendering_mode', $mode)->save();

    // Clean up old mode artifacts.
    if ($mode === 'inline') {
      // Switching to inline - clean up static files.
      $fileManager = $this->serviceProvider->getFileManager();
      $fileManager->cleanupStaticFiles();
      $this->io()->text('Cleaned up static CSS files');
    }
    elseif ($mode === 'head') {
      // Switching to head - clean up static files.
      $fileManager = $this->serviceProvider->getFileManager();
      $fileManager->cleanupStaticFiles();
      $this->io()->text('Cleaned up static CSS files');

      $stateManager = $this->serviceProvider->getStateManager();
      $knownClasses = $stateManager->getKnownClasses();

      if (empty($knownClasses)) {
        $this->io()->text('Scanning content for utility classes...');
        $scanResult = $this->serviceProvider->getContentScanner()->scanAllContent();
        if (!empty($scanResult['classes'])) {
          $stateManager->setKnownClasses($scanResult['classes']);
          $this->io()->text(sprintf('Found %d utility classes', count($scanResult['classes'])));
        }
      }
    }
    else {
      // Switching to static - generate CSS if classes exist.
      $stateManager = $this->serviceProvider->getStateManager();
      $knownClasses = $stateManager->getKnownClasses();

      if (!empty($knownClasses)) {
        $this->io()->text(sprintf('Generating CSS for %d classes...', count($knownClasses)));
        $this->serviceProvider->regenerateStaticCss();
      }
      else {
        $this->io()->warning('No utility classes found. Run "drush utilikit:scan" to scan content.');
      }
    }

    // Clear caches.
    $this->serviceProvider->getCacheManager()->clearAllCaches();

    $this->io()->success(sprintf('Switched to %s mode', $mode));
  }

  /**
   * Show UtiliKit status and configuration.
   *
   * @command utilikit:status
   * @aliases uk-status,utilikit-status
   * @usage utilikit:status
   *   Display UtiliKit configuration and statistics
   * @usage drush uk-status
   *   Short alias for status
   */
  #[CLI\Command(name: 'utilikit:status', aliases: ['uk-status', 'utilikit-status'])]
  #[CLI\Usage(name: 'utilikit:status', description: 'Display UtiliKit configuration and statistics')]
  public function status(): void {
    $config = $this->configFactory->get('utilikit.settings');
    $stateManager = $this->serviceProvider->getStateManager();

    $mode = $config->get('rendering_mode') ?? 'inline';
    $knownClasses = $stateManager->getKnownClasses();
    $scanningTypes = $config->get('scanning_entity_types') ?? [];
    $css = $stateManager->getGeneratedCss();

    $rows = [
      ['Rendering Mode', $mode],
      ['Utility Classes', count($knownClasses)],
      ['Scanning Entity Types', implode(', ', $scanningTypes)],
      ['Scope', $config->get('scope_global') ? 'Global' : 'Content Types'],
      ['Dev Mode', $config->get('dev_mode') ? 'Enabled' : 'Disabled'],
    ];

    if ($mode === 'static') {
      $fileManager = $this->serviceProvider->getFileManager();
      $cssUrl = $fileManager->getStaticCssUrl();

      $rows[] = ['CSS File', $cssUrl ?? 'Not generated'];
      $rows[] = ['CSS Size', $css ? $this->formatBytes(strlen($css)) : 'N/A'];
      $rows[] = [
        'Last Updated',
        $stateManager->getCssTimestamp() ? date('Y-m-d H:i:s', $stateManager->getCssTimestamp()) : 'Never',
      ];
    }

    $this->io()->title('UtiliKit Status');
    $this->io()->table(['Setting', 'Value'], $rows);
  }

  /**
   * List all tracked utility classes.
   *
   * @param array $options
   *   Command options.
   *
   * @command utilikit:classes
   * @aliases uk-classes,utilikit-classes
   * @option limit Maximum number of classes to display (default: 50, 0 = all)
   * @option filter Filter classes by prefix (e.g., uk-pd for padding)
   * @usage utilikit:classes
   *   List first 50 utility classes
   * @usage utilikit:classes --limit=0
   *   List all utility classes
   * @usage utilikit:classes --filter=uk-pd
   *   List only padding classes
   * @usage drush uk-classes --limit=20
   *   Short alias showing 20 classes
   */
  #[CLI\Command(name: 'utilikit:classes', aliases: ['uk-classes', 'utilikit-classes'])]
  #[CLI\Option(name: 'limit', description: 'Maximum number of classes to display')]
  #[CLI\Option(name: 'filter', description: 'Filter classes by prefix')]
  #[CLI\Usage(name: 'utilikit:classes', description: 'List first 50 utility classes')]
  #[CLI\Usage(name: 'utilikit:classes --limit=0', description: 'List all utility classes')]
  public function listClasses(
    array $options = [
      'limit' => 50,
      'filter' => NULL,
    ],
  ): void {
    $stateManager = $this->serviceProvider->getStateManager();
    $classes = $stateManager->getKnownClasses();

    if (empty($classes)) {
      $this->io()->warning('No utility classes found. Run "drush utilikit:scan" first.');
      return;
    }

    // Filter if requested.
    if ($options['filter']) {
      $classes = array_filter($classes, function ($class) use ($options) {
        return str_starts_with($class, $options['filter']);
      });
    }

    sort($classes);

    $total = count($classes);
    $limit = (int) $options['limit'];

    if ($limit > 0 && $total > $limit) {
      $classes = array_slice($classes, 0, $limit);
      $this->io()->title(sprintf('Utility Classes (showing %d of %d)', $limit, $total));
    }
    else {
      $this->io()->title(sprintf('Utility Classes (%d total)', $total));
    }

    $this->io()->listing($classes);

    if ($limit > 0 && $total > $limit) {
      $this->io()->note(sprintf('Showing first %d classes. Use --limit=0 to see all.', $limit));
    }
  }

  /**
   * Validate UtiliKit CSS generation.
   *
   * @command utilikit:validate
   * @aliases uk-validate,utilikit-validate
   * @usage utilikit:validate
   *   Check for CSS generation errors
   * @usage drush uk-validate
   *   Short alias for validation
   */
  #[CLI\Command(name: 'utilikit:validate', aliases: ['uk-validate', 'utilikit-validate'])]
  #[CLI\Usage(name: 'utilikit:validate', description: 'Check for CSS generation errors')]
  public function validate(): void {
    $this->io()->title('Validating UtiliKit Configuration');

    $config = $this->configFactory->get('utilikit.settings');
    $stateManager = $this->serviceProvider->getStateManager();
    $mode = $config->get('rendering_mode') ?? 'inline';

    $issues = [];
    $warnings = [];

    // Check for known classes.
    $knownClasses = $stateManager->getKnownClasses();
    if (empty($knownClasses)) {
      $warnings[] = 'No utility classes found. Run "drush utilikit:scan" to scan content.';
    }
    else {
      $this->io()->text(sprintf('✓ Found %d utility classes', count($knownClasses)));
    }

    // Check static mode requirements.
    if ($mode === 'static') {
      $fileManager = $this->serviceProvider->getFileManager();
      $cssUrl = $fileManager->getStaticCssUrl();

      if (!$cssUrl) {
        $issues[] = 'Static CSS file not found. Run "drush utilikit:generate" to create it.';
      }
      else {
        $this->io()->text(sprintf('✓ Static CSS file exists: %s', $cssUrl));
      }

      $css = $stateManager->getGeneratedCss();
      if (empty($css)) {
        $warnings[] = 'Generated CSS is empty. Ensure utility classes exist in content.';
      }
      else {
        $this->io()->text(sprintf('✓ Generated CSS size: %s', $this->formatBytes(strlen($css))));
      }
    }

    // Check scanning configuration.
    $scanningTypes = $config->get('scanning_entity_types') ?? [];
    if (empty($scanningTypes)) {
      $warnings[] = 'No entity types configured for scanning.';
    }
    else {
      $this->io()->text(sprintf('✓ Scanning entity types: %s', implode(', ', $scanningTypes)));
    }

    // Output results.
    if (!empty($issues)) {
      $this->io()->error('Validation failed:');
      foreach ($issues as $issue) {
        $this->io()->text('  ✗ ' . $issue);
      }
    }

    if (!empty($warnings)) {
      $this->io()->warning('Warnings:');
      foreach ($warnings as $warning) {
        $this->io()->text('  ⚠ ' . $warning);
      }
    }

    if (empty($issues) && empty($warnings)) {
      $this->io()->success('UtiliKit configuration is valid');
    }
    elseif (empty($issues)) {
      $this->io()->note('Validation passed with warnings');
    }
  }

  /**
   * Clear UtiliKit-specific caches.
   *
   * @command utilikit:cache-clear
   * @aliases uk-cc,utilikit-cache-clear
   * @usage utilikit:cache-clear
   *   Clear UtiliKit caches
   * @usage drush uk-cc
   *   Short alias for cache clear
   */
  #[CLI\Command(name: 'utilikit:cache-clear', aliases: ['uk-cc', 'utilikit-cache-clear'])]
  #[CLI\Usage(name: 'utilikit:cache-clear', description: 'Clear UtiliKit caches')]
  public function cacheClear(): void {
    $this->io()->title('Clearing UtiliKit Caches');

    $this->serviceProvider->getCacheManager()->clearAllCaches();

    $this->io()->success('UtiliKit caches cleared');
  }

}
