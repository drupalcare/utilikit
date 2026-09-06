<?php

declare(strict_types=1);

namespace Drupal\utilikit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;

/**
 * Provides validated access to UtiliKit configuration settings.
 *
 * This service acts as a configuration accessor that provides typed methods
 * for retrieving UtiliKit settings with proper validation, defaults, and
 * type conversion. It centralizes configuration access and ensures
 * consistency across the module.
 */
class UtilikitService {

  /**
   * The configuration factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a new UtilikitService object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * Gets the UtiliKit settings configuration object.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The UtiliKit settings configuration object.
   */
  public function getSettings(): ImmutableConfig {
    return $this->configFactory->get('utilikit.settings');
  }

  /**
   * Gets a validated configuration value with optional allowed values check.
   *
   * Retrieves a configuration value with fallback to default and optional
   * validation against a list of allowed values for security and consistency.
   *
   * @param string $key
   *   The configuration key to retrieve.
   * @param mixed $default
   *   The default value to return if the key is not set.
   * @param list<string> $allowedValues
   *   Optional array of allowed values for validation.
   *
   * @return mixed
   *   The validated configuration value or default if invalid/missing.
   */
  private function getValidatedConfig(string $key, $default, array $allowedValues = []) {
    $value = $this->getSettings()->get($key) ?? $default;

    if (!empty($allowedValues) && !in_array($value, $allowedValues, TRUE)) {
      return $default;
    }

    return $value;
  }

  /**
   * Gets the rendering mode with validation.
   *
   * @return string
   *   The rendering mode ('inline' or 'static'), defaults to 'inline'.
   */
  public function getRenderingMode(): string {
    return $this->getValidatedConfig('rendering_mode', 'inline', ['inline', 'static', 'head']);
  }

  /**
   * Gets the scope setting with validation.
   *
   * @return string
   *   The scope setting ('global' or 'content'), defaults to 'global'.
   */
  public function getScope(): string {
    return $this->getValidatedConfig('scope', 'global', ['global', 'content']);
  }

  /**
   * Checks if UtiliKit is in static rendering mode.
   *
   * @return bool
   *   TRUE if in static mode, FALSE if in inline mode.
   */
  public function isStaticMode(): bool {
    return $this->getRenderingMode() === 'static';
  }

  /**
   * Checks if developer mode is enabled.
   *
   * @return bool
   *   TRUE if developer mode is enabled, FALSE otherwise.
   */
  public function isDevMode(): bool {
    return (bool) $this->getSettings()->get('dev_mode');
  }

  /**
   * Gets the list of active responsive breakpoints.
   *
   * @return list<int|string> mixed>
   *   Array of active breakpoint keys (e.g., ['sm', 'md', 'lg']).
   */
  public function getActiveBreakpoints(): array {
    $breakpoints = $this->getSettings()->get('active_breakpoints') ?? UtilikitConstants::DEFAULT_BREAKPOINTS;
    return array_keys(array_filter($breakpoints));
  }

  /**
   * Checks if auto-update is enabled for a specific entity type.
   *
   * @param string $entity_type
   *   The entity type to check (e.g., 'node', 'block_content').
   *
   * @return bool
   *   TRUE if auto-update is enabled for the entity type, FALSE otherwise.
   */
  public function shouldUpdateOnEntitySave(string $entity_type): bool {
    $setting_key = UtilikitConstants::AUTO_UPDATE_ENTITY_TYPES[$entity_type] ?? NULL;
    return $setting_key && (bool) $this->getSettings()->get($setting_key);
  }

  /**
   * Gets the pixel values for responsive breakpoints.
   *
   * @return array<string, mixed>
   *   Array mapping breakpoint keys to their pixel values.
   */
  public function getBreakpointValues(): array {
    return UtilikitConstants::BREAKPOINT_VALUES;
  }

  /**
   * Gets the debounce delay in milliseconds.
   *
   * @return int
   *   The debounce delay in milliseconds, defaults to 50.
   */
  public function getDebounceMs(): int {
    return (int) ($this->getSettings()->get('debounce') ?? 50);
  }

  /**
   * Gets the console logging level.
   *
   * @return string
   *   The logging level ('off', 'warnings', 'detailed'), defaults to
   *   'warnings'.
   */
  public function getLogLevel(): string {
    return $this->getSettings()->get('log_level') ?? 'warnings';
  }

  /**
   * Checks if page error display is enabled.
   *
   * @return bool
   *   TRUE if page errors should be displayed, FALSE otherwise.
   */
  public function shouldShowPageErrors(): bool {
    return (bool) $this->getSettings()->get('show_page_errors');
  }

  /**
   * Checks if CSS transitions are enabled.
   *
   * @return bool
   *   TRUE if transitions should be enabled, FALSE otherwise.
   */
  public function shouldEnableTransitions(): bool {
    return (bool) $this->getSettings()->get('enable_transitions');
  }

  /**
   * Checks if CSS optimization is enabled.
   *
   * @return bool
   *   TRUE if CSS should be optimized, FALSE otherwise.
   */
  public function shouldOptimizeCss(): bool {
    return (bool) $this->getSettings()->get('optimize_css');
  }

  /**
   * Checks if admin preview mode is enabled.
   *
   * @return bool
   *   TRUE if admin preview is enabled, FALSE otherwise.
   */
  public function isAdminPreviewEnabled(): bool {
    return (bool) $this->getSettings()->get('admin_preview');
  }

  /**
   * Checks if global scope is enabled.
   *
   * @return bool
   *   TRUE if UtiliKit should apply globally, FALSE otherwise.
   */
  public function isScopeGlobal(): bool {
    return (bool) $this->getSettings()->get('scope_global');
  }

  /**
   * Checks if admin pages should be disabled.
   *
   * @return bool
   *   TRUE if UtiliKit should be disabled on admin pages, FALSE otherwise.
   */
  public function shouldDisableAdmin(): bool {
    return (bool) $this->getSettings()->get('disable_admin');
  }

  /**
   * Checks if content type scoping is enabled.
   *
   * @return bool
   *   TRUE if UtiliKit should be limited to specific content types,
   *   FALSE otherwise.
   */
  public function isScopeContentTypes(): bool {
    return (bool) $this->getSettings()->get('scope_content_types');
  }

  /**
   * Gets the list of enabled content types.
   *
   * @return array<string, mixed>
   *   Array of content type machine names that have UtiliKit enabled.
   */
  public function getEnabledContentTypes(): array {
    return $this->getSettings()->get('enabled_content_types') ?? [];
  }

}
