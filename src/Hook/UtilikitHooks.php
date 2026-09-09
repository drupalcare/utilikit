<?php

namespace Drupal\utilikit\Hook;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for utilikit.
 */
class UtilikitHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_help().
   *
   * Provides help information for the UtiliKit module by rendering the
   * README.md file when accessed through the help system.
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ($route_name === 'help.page.utilikit') {
      return _utilikit_helper_render_readme();
    }
    return NULL;
  }

  /**
   * Implements hook_page_attachments().
   *
   * Attaches UtiliKit libraries, settings, and CSS to pages based on the
   * current configuration and scope settings. Handles both static and inline
   * rendering modes, attaches appropriate JavaScript libraries, and sets up
   * drupalSettings for client-side functionality.
   *
   * The function performs several key operations:
   * - Validates scope and applies UtiliKit only where configured
   * - Handles static CSS file generation and linking
   * - Configures JavaScript settings for client-side utilities
   * - Attaches development and debugging libraries when enabled
   * - Sets up cache contexts and tags for proper invalidation
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    if (!\Drupal::config('utilikit.settings')->get('rendering_mode')) {
      return;
    }
    $config = \Drupal::config('utilikit.settings');
    $settings = [
      'scope_global' => (bool) $config->get('scope_global'),
      'disable_admin' => (bool) $config->get('disable_admin'),
      'scope_content_types' => (bool) $config->get('scope_content_types'),
      'enabled_content_types' => $config->get('enabled_content_types') ?? [],
    ];
    if (!utilikit_should_apply($settings)) {
      return;
    }
    $activeBreakpoints = array_keys(array_filter($config->get('active_breakpoints') ?? UtilikitConstants::DEFAULT_BREAKPOINTS));
    $rendering_mode = $config->get('rendering_mode') ?? 'inline';
    if ($rendering_mode === 'static') {
      $fileManager = \Drupal::service('utilikit.file_manager');
      $fileManager->ensureStaticCssFile();
      $css_url = $fileManager->getStaticCssUrl();
      if (!$css_url) {
        \Drupal::logger('utilikit')->warning('Static CSS file not found. Falling back to inline mode.');
        $rendering_mode = 'inline';
      }
    }
    elseif ($rendering_mode === 'head') {
      $stateManager = \Drupal::service('utilikit.state_manager');
      $knownClasses = $stateManager->getKnownClasses();
      if (!empty($knownClasses)) {
        $css = $stateManager->getGeneratedCss();
        if (empty($css)) {
          $cssGenerator = \Drupal::service('utilikit.css_generator');
          $css = $cssGenerator->generateCssFromClasses($knownClasses);
          $stateManager->setGeneratedCss($css);
        }
        if (!empty($css)) {
          // Apply optimization if enabled (same as static mode)
          if ($config->get('optimize_css')) {
            $fileManager = \Drupal::service('utilikit.file_manager');
            $css = $fileManager->minifyCss($css);
          }
          $attachments['#attached']['html_head'][] = [
            [
              '#type' => 'html_tag',
              '#tag' => 'style',
              '#value' => $css,
              '#attributes' => [
                'id' => 'utilikit-head-mode',
              ],
              '#weight' => 900,
            ],
            'utilikit_head_css',
          ];
        }
      }
    }
    $stateManager = \Drupal::service('utilikit.state_manager');
    // Developer tools (devMode, adminPreview, showPageErrors, logLevel) are
    // restricted to the admin user (uid 1) only.
    $isAdmin = (int) \Drupal::currentUser()->id() === 1;
    $attachments['#attached']['drupalSettings']['utilikit'] = [
      'devMode' => $isAdmin && (bool) $config->get('dev_mode'),
      'showPageErrors' => $isAdmin && (bool) $config->get('show_page_errors'),
      'enableTransitions' => (bool) $config->get('enable_transitions'),
      'debounce' => (int) ($config->get('debounce') ?? 50),
      'logLevel' => $isAdmin ? $config->get('log_level') ?? 'warnings' : 'off',
      'adminPreview' => $isAdmin && (bool) $config->get('admin_preview'),
      'activeBreakpoints' => $activeBreakpoints,
      'renderingMode' => $rendering_mode,
      'csrfToken' => \Drupal::csrfToken()->get('utilikit/update-css'),
      'cssTimestamp' => $stateManager->getCssTimestamp(),
      'breakpoints' => UtilikitConstants::BREAKPOINT_VALUES,
    ];
    if ($rendering_mode === 'static') {
      // Ensure the static CSS file exists.
      $fileManager = \Drupal::service('utilikit.file_manager');
      $fileManager->ensureStaticCssFile();
      // Attach the static mode JS library.
      $attachments['#attached']['library'][] = 'utilikit/utilikit.static';
      // Attach the generated CSS via library (weight 999 ensures it loads last).
      // The actual file URL is injected via hook_library_info_alter().
      $attachments['#attached']['library'][] = 'utilikit/utilikit.static.css';
    }
    elseif ($rendering_mode === 'head') {
      $attachments['#attached']['library'][] = 'utilikit/utilikit.static';
    }
    else {
      $attachments['#attached']['library'][] = 'utilikit/utilikit.engine';
    }
    if ($isAdmin && $config->get('show_page_errors')) {
      $attachments['#attached']['library'][] = 'utilikit/utilikit.engine.message';
    }
    if ($isAdmin && $config->get('dev_mode')) {
      $attachments['#attached']['library'][] = 'utilikit/utilikit.debug';
    }
    if (\Drupal::currentUser()->hasPermission('use utilikit update button') && in_array($rendering_mode, [
      'static',
      'head',
    ], TRUE)) {
      $attachments['#attached']['library'][] = 'utilikit/utilikit.update.button';
      $attachments['#attached']['drupalSettings']['utilikit']['updateButton'] = [
        'enabled' => TRUE,
        'autoUpdate' => [
          'node' => (bool) $config->get('update_on_node_save'),
          'block' => (bool) $config->get('update_on_block_save'),
          'paragraph' => (bool) $config->get('update_on_paragraph_save'),
        ],
      ];
    }
    // CSP nonce only needed for inline mode (dynamic JS injection)
    if ($rendering_mode === 'inline') {
      $nonce = Crypt::randomBytesBase64(16);
      $attachments['#attached']['drupalSettings']['utilikit']['cspNonce'] = $nonce;
    }
    // Add cache metadata based on rendering mode.
    $attachments['#cache']['tags'][] = UtilikitConstants::CACHE_TAG_CONFIG;
    $attachments['#cache']['tags'][] = UtilikitConstants::CACHE_TAG_CSS;
    $attachments['#cache']['contexts'][] = 'user.permissions';
    $attachments['#cache']['contexts'][] = 'route';
    $attachments['#cache']['tags'][] = 'utilikit:mode:' . $rendering_mode;
    // Inline mode: must not leak into page cache since output varies dynamically.
    // Set max-age to 0 to prevent caching of user-specific/dynamic content.
    if ($rendering_mode === 'inline') {
      $attachments['#cache']['max-age'] = 0;
    }
    else {
      // Static/head: theme tag so CSS varies by theme.
      $attachments['#cache']['tags'][] = UtilikitConstants::CACHE_TAG_THEME;
    }
  }

  /**
   * Implements hook_entity_presave().
   *
   * Automatically scans entities for utility classes and updates CSS when
   * auto-update is enabled for the entity type. Handles both static and
   * inline rendering modes with appropriate locking for static mode to
   * prevent concurrent CSS file operations.
   *
   * For static mode, uses a lock-and-queue system to handle concurrent
   * updates gracefully. If a lock cannot be acquired, the update is queued
   * for later processing to avoid blocking entity saves.
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    $config = \Drupal::config('utilikit.settings');
    $entity_type = $entity->getEntityTypeId();
    if (!isset(UtilikitConstants::AUTO_UPDATE_ENTITY_TYPES[$entity_type])) {
      return;
    }
    $setting_key = UtilikitConstants::AUTO_UPDATE_ENTITY_TYPES[$entity_type];
    $auto_update_enabled = (bool) $config->get($setting_key);
    if (!$auto_update_enabled) {
      return;
    }
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }
    $serviceProvider = \Drupal::service('utilikit.service_provider');
    $classes = $serviceProvider->getContentScanner()->scanEntity($entity);
    if (empty($classes)) {
      return;
    }
    $mode = $config->get('rendering_mode') ?? 'inline';
    if (in_array($mode, [
      'static',
      'head',
    ], TRUE)) {
      $lock = \Drupal::lock();
      $locked = $lock->acquire(UtilikitConstants::LOCK_CSS_UPDATE, UtilikitConstants::CSS_UPDATE_LOCK_WAIT);
      if (!$locked) {
        $queue = \Drupal::queue(UtilikitConstants::QUEUE_CSS_PROCESSOR);
        $queue->createItem([
          'classes' => $classes,
          'entity_type' => $entity->getEntityTypeId(),
          'entity_id' => $entity->id(),
          'timestamp' => time(),
        ]);
        \Drupal::messenger()->addWarning($this->t('UtiliKit CSS update is in progress. Your content was saved, but styles will be updated shortly.'));
        return;
      }
      try {
        utilikit_update_classes($classes, TRUE);
      }
      finally {
        $lock->release(UtilikitConstants::LOCK_CSS_UPDATE);
      }
    }
    else {
      utilikit_update_classes($classes, TRUE);
    }
  }

  /**
   * Implements hook_theme().
   *
   * Defines theme templates provided by the UtiliKit module.
   */
  #[Hook('theme')]
  public function theme() {
    return [
      'utilikit_update_button' => [
        'variables' => [],
        'template' => 'utilikit-update-button',
      ],
    ];
  }

  /**
   * Implements hook_utilikit_cleanup_entity_types_alter().
   *
   * Allows other modules to alter the list of entity types that should be
   * processed during cleanup operations. This is primarily used for
   * excluding specific entity types from automatic scanning and processing.
   *
   *   Array of entity type machine names to be processed during cleanup.
   *   Modules can add or remove entity types by modifying this array.
   */
  #[Hook('utilikit_cleanup_entity_types_alter')]
  public function utilikitCleanupEntityTypesAlter(&$entity_types) {
  }

  /**
   * Implements hook_library_info_alter().
   *
   * Replaces the placeholder CSS file in the static-css library with the
   * actual generated CSS file URL. This enables dynamic CSS file attachment
   * while maintaining proper Drupal library integration and cache handling.
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $extension): void {
    if ($extension !== 'utilikit') {
      return;
    }
    // Only alter for static mode.
    $config = \Drupal::config('utilikit.settings');
    $rendering_mode = $config->get('rendering_mode') ?? 'inline';
    if ($rendering_mode !== 'static') {
      return;
    }
    // Get the generated CSS file URL.
    $fileManager = \Drupal::service('utilikit.file_manager');
    $css_url = $fileManager->getStaticCssUrl();
    if (!$css_url) {
      return;
    }
    // Replace placeholder with actual generated file.
    if (isset($libraries['utilikit.static.css']['css']['theme'])) {
      unset($libraries['utilikit.static.css']['css']['theme']['css/utilikit-static-placeholder.css']);
      $libraries['utilikit.static.css']['css']['theme'][$css_url] = [
        'weight' => 999,
        'preprocess' => TRUE,
      ];
    }
  }

  /**
   * Implements hook_css_alter().
   *
   * Ensures the UtiliKit static CSS file has proper weight and group settings
   * so it loads last and overrides any other styles on the page.
   */
  #[Hook('css_alter')]
  public function cssAlter(array &$css): void {
    // Only alter for static mode.
    $config = \Drupal::config('utilikit.settings');
    $rendering_mode = $config->get('rendering_mode') ?? 'inline';
    if ($rendering_mode !== 'static') {
      return;
    }
    // Find and update UtiliKit static CSS entry.
    foreach ($css as $key => $item) {
      if (isset($item['data']) && str_contains($item['data'], 'utilikit') && str_contains($item['data'], 'utilikit-static-style.css')) {
        $css[$key]['group'] = CSS_AGGREGATE_THEME;
        $css[$key]['weight'] = 999;
        break;
      }
    }
  }

  /**
   * Implements hook_cache_flush().
   *
   * Regenerates the static CSS file during cache rebuilds to ensure
   * the CSS is always current after cache operations.
   */
  #[Hook('cache_flush')]
  public function cacheFlush(): void {
    $config = \Drupal::config('utilikit.settings');
    $rendering_mode = $config->get('rendering_mode') ?? 'inline';
    // Only regenerate for static and head modes.
    if (!in_array($rendering_mode, [
      'static',
      'head',
    ], TRUE)) {
      return;
    }
    if (\Drupal::hasService('utilikit.service_provider')) {
      \Drupal::service('utilikit.service_provider')->regenerateStaticCss();
    }
  }

}
