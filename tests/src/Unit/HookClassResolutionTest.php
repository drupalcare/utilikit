<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Procedural code must never fetch UtiliKit's own classes as services.
 *
 * WHY THIS EXISTS. Issue #3628961 (reported against solo_utilities, same
 * defect): "You have requested a non-existent service ...Hooks". UtiliKit
 * 1.0.5 converted its hooks to src/Hook classes and declared each Hook class
 * as a service; 1.0.4 had none of them. After updating the files, a site
 * keeps its 1.0.4 container until a cache rebuild, and every #[LegacyHook]
 * wrapper called \Drupal::service(<X>Hooks::class). hook_page_attachments()
 * fires on every page, so every page could answer HTTP 500 until `drush cr`
 * or `drush updb`.
 *
 * The wrappers now use \Drupal::classResolver(), which returns the service
 * when the container has it and instantiates the class itself when it does
 * not. The same fix shipped in the Solo theme 1.0.36.
 *
 * WHAT THIS ENFORCES. No \Drupal::service() call in a *.module, *.inc or
 * *.install file of UtiliKit or its sub-modules names a class in a
 * Drupal\utilikit or Drupal\utilikit_* namespace. Core and third-party
 * classes are not flagged: `use` statements are resolved, so the check is
 * about the namespace, not the name. It covers all nineteen wrappers,
 * including those the kernel test does not invoke.
 *
 * Red-proofed against the 1.0.7 code: 19 calls flagged. Reads files as text,
 * so it runs on every core version.
 *
 * @group utilikit
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('utilikit')]
class HookClassResolutionTest extends TestCase {

  /**
   * The number of #[LegacyHook] wrappers that resolve a Hook class.
   *
   * 7 in utilikit.module, 3 each in the examples, help, playground and test
   * sub-modules.
   */
  private const EXPECTED_WRAPPERS = 19;

  /**
   * Procedural files of the module and its sub-modules.
   *
   * @return list<string>
   *   Absolute paths.
   */
  private function proceduralFiles(): array {
    $root = dirname(__DIR__, 3);
    $files = [];
    foreach ([$root, ...(glob($root . '/modules/*', GLOB_ONLYDIR) ?: [])] as $dir) {
      foreach (['module', 'inc', 'install'] as $extension) {
        $files = array_merge($files, glob($dir . '/*.' . $extension) ?: []);
      }
    }
    return $files;
  }

  /**
   * Maps each imported short class name to its fully qualified name.
   *
   * @return array<string, string>
   *   FQCN keyed by the short name (or alias) used in the file.
   */
  private function imports(string $code): array {
    $map = [];
    preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $code, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      $fqcn = ltrim($match[1], '\\');
      $short = $match[2] ?? '';
      if ($short === '') {
        $parts = explode('\\', $fqcn);
        $short = end($parts);
      }
      $map[$short] = $fqcn;
    }
    return $map;
  }

  /**
   * No wrapper asks the container for a class UtiliKit itself defines.
   */
  public function testNoContainerLookupOfModuleOwnClasses(): void {
    $offenders = [];
    $resolved = 0;
    $root = dirname(__DIR__, 3);
    foreach ($this->proceduralFiles() as $file) {
      $code = (string) file_get_contents($file);
      $imports = $this->imports($code);
      preg_match_all('/\\\\Drupal::(service|classResolver)\(\s*\\\\?([\w\\\\]+)::class\s*\)/', $code, $calls, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
      foreach ($calls as $call) {
        $name = $call[2][0];
        $fqcn = str_contains($name, '\\') ? ltrim($name, '\\') : ($imports[$name] ?? $name);
        if (!preg_match('/^Drupal\\\\utilikit(_\w+)?\\\\/', $fqcn)) {
          continue;
        }
        if ($call[1][0] === 'classResolver') {
          $resolved++;
          continue;
        }
        $line = substr_count(substr($code, 0, $call[0][1]), "\n") + 1;
        $offenders[] = sprintf('%s:%d \\Drupal::service(%s::class)', substr($file, strlen($root) + 1), $line, $name);
      }
    }

    $this->assertSame([], $offenders, sprintf(
      "A service declared in a new release does not exist in the container of a site updated from an older one until a cache rebuild; use \\Drupal::classResolver() instead. %d offending call(s):\n%s",
      count($offenders),
      implode("\n", $offenders)
    ));
    // A scan that matches nothing proves nothing: a moved file or a broken
    // pattern must go red rather than pass on an empty result.
    $this->assertSame(self::EXPECTED_WRAPPERS, $resolved, sprintf('Found %d classResolver() wrappers; expected %d.', $resolved, self::EXPECTED_WRAPPERS));
  }

}
