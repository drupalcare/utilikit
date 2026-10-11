<?php

declare(strict_types=1);

namespace Drupal\utilikit_ckeditor\Hook;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for utilikit_ckeditor.
 *
 * WHY THE PLUGIN SHIPS TWO BUILDS. Drupal 11 loads CKEditor 5 as a DLL
 * (`CKEditor5.dll`) and can only run a plugin built against it. Drupal 12
 * loads a UMD bundle with a flat `CKEDITOR` global and keeps `CKEditor5.dll()`
 * only as a deprecated shim (https://www.drupal.org/node/3581531). One build
 * cannot serve both without that deprecation, so webpack.config.js produces
 * one of each and this class picks the one the running core can load.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
final class UtilikitCkeditorHooks {

  /**
   * The DLL build, the library's default — what Drupal 11 loads.
   */
  public const DLL_BUILD = 'js/build/utilikit.js';

  /**
   * The externals build — what a core shipping the UMD runtime loads.
   */
  public const EXTERNALS_BUILD = 'js/build/utilikit.externals.js';

  /**
   * The file core's `core/ckeditor5` library loads when it is the UMD build.
   */
  private const CORE_UMD_FILE = '/ckeditor5.umd.js';

  public function __construct(
    private readonly LibraryDiscoveryInterface $libraryDiscovery,
  ) {}

  /**
   * Implements hook_library_info_alter().
   *
   * Serves the externals build when core's own CKEditor 5 library loads the
   * UMD runtime, and the DLL build otherwise.
   *
   * THE DECISION READS CORE'S LIBRARY, NOT ITS VERSION NUMBER. Which runtime
   * a core ships is a fact owned by core.libraries.yml; a version comparison
   * would be a proxy for it, wrong the day the switch is backported or a site
   * overrides the library. Reading `core` from inside this alter is safe: it
   * builds a different extension's definitions, which re-enters this method
   * with $extension === 'core' and returns at the first line.
   *
   * The result lives in the library cache, so after a core upgrade it changes
   * on the cache rebuild every core update performs.
   *
   * @param array<string, mixed> $libraries
   *   The libraries of $extension, before normalisation.
   * @param string $extension
   *   The extension the libraries belong to.
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $extension): void {
    if ($extension !== 'utilikit_ckeditor') {
      return;
    }
    $js = $libraries['utilikit_ckeditor']['js'] ?? NULL;
    if (!is_array($js) || !array_key_exists(self::DLL_BUILD, $js)) {
      return;
    }
    if (!$this->coreShipsUmdRuntime()) {
      return;
    }

    // Swap the one key in place, keeping its options and its position.
    $swapped = [];
    foreach ($js as $file => $options) {
      $swapped[$file === self::DLL_BUILD ? self::EXTERNALS_BUILD : $file] = $options;
    }
    $libraries['utilikit_ckeditor']['js'] = $swapped;
  }

  /**
   * Whether core's `core/ckeditor5` library loads the UMD runtime.
   */
  private function coreShipsUmdRuntime(): bool {
    $library = $this->libraryDiscovery->getLibraryByName('core', 'ckeditor5');
    if (!is_array($library)) {
      return FALSE;
    }
    foreach ($library['js'] ?? [] as $asset) {
      if (is_string($asset['data'] ?? NULL) && str_ends_with($asset['data'], self::CORE_UMD_FILE)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
