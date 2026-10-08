<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitRules;
use PHPUnit\Framework\Attributes\Group;

/**
 * The rule definitions exist in exactly one place.
 *
 * WHY THIS TEST EXISTS, measured on 2026-09-05. The same fifty rules were
 * written out three times — `UtilikitRules::getRules()`, `js/utilikit.rules.js`
 * and the playground's autocomplete — and they had already drifted. Four sizing
 * rules carried `allowAuto` in JavaScript and not in PHP, so `uk-wd--auto`
 * rendered in inline mode and produced NO CSS in static and head mode. The
 * playground file's own comment said what it should have been: "Should ideally
 * be passed from Drupal settings for consistency."
 *
 * Nobody could have seen that by reading either file alone, and no existing
 * test could fail because of it. An unenforced rule decays into a suggestion,
 * so the rule is enforced here.
 *
 * @group utilikit
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('utilikit')]
final class UtilikitRulesSingleSourceTest extends UnitTestCase {

  /**
   * Matches a rule declared as a JavaScript object literal.
   *
   * Deliberately anchored on `css:` rather than on the two-letter key alone.
   * A key like `fd:` appears in ordinary code; `fd: { css: 'flexDirection'` is
   * unmistakably a rule definition and nothing else.
   */
  private const JS_RULE_LITERAL = '/\b[a-z]{2,4}\s*:\s*\{\s*css\s*:\s*[\'"]/';

  /**
   * The module root.
   *
   * @return string
   *   Absolute path to the utilikit directory.
   */
  private function moduleRoot(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * Every JavaScript file in the project, excluding build output.
   *
   * @return list<string>
   *   Absolute file paths.
   */
  private function javascriptFiles(): array {
    $root = $this->moduleRoot();
    $found = [];

    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
      if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'js') {
        continue;
      }

      $path = $file->getPathname();

      // node_modules is third-party; tests/js mocks describe rules on purpose.
      if (str_contains($path, '/node_modules/') || str_contains($path, '/tests/js/')) {
        continue;
      }

      $found[] = $path;
    }

    sort($found);

    return $found;
  }

  /**
   * No JavaScript file declares a rule of its own.
   *
   * This is the whole point: PHP declares the rules, and
   * utilikit_js_settings_alter() publishes them. A second declaration anywhere
   * is the defect, whether or not it currently agrees.
   */
  public function testNoJavaScriptFileDeclaresRules(): void {
    $files = $this->javascriptFiles();

    // Guard the guard. A scan that finds no files would pass this test while
    // checking nothing at all — the failure mode the test is written against.
    self::assertGreaterThan(10, count($files), 'The JavaScript scan found almost nothing, so it proved nothing.');

    $offenders = [];

    foreach ($files as $path) {
      $source = (string) file_get_contents($path);

      if (preg_match_all(self::JS_RULE_LITERAL, $source, $matches)) {
        $offenders[] = sprintf(
          '%s (%d rule literal(s))',
          str_replace($this->moduleRoot() . '/', '', $path),
          count($matches[0]),
        );
      }
    }

    self::assertSame([], $offenders, implode("\n", array_merge(
      ['A JavaScript file declares utility rules. They are declared once, in'],
      ['UtilikitRules::getRules(), and reach JavaScript through'],
      ['utilikit_js_settings_alter(). Offending files:'],
      $offenders,
    )));
  }

  /**
   * No JavaScript file declares its own keyword list either.
   *
   * MEASURED 2026-09-05, the second instance of one rule written twice. The
   * allowed keyword values lived only in js/utilikit.rules.apply.js; PHP
   * validated nothing and its generator emitted any word it was handed, so
   * `uk-dp--banana` produced `display: banana;` in static and head mode while
   * the engine rejected the identical class in inline mode.
   */
  public function testNoJavaScriptFileDeclaresKeywordLists(): void {
    $offenders = [];

    foreach ($this->javascriptFiles() as $path) {
      $source = (string) file_get_contents($path);

      // A literal list assigned to a name containing "keyword" is the shape
      // being outlawed. Reading one from settings is fine and does not match.
      if (preg_match('/\b\w*keywords?\w*\s*=\s*\{\s*\n\s*\w+:\s*\[/i', $source)) {
        $offenders[] = str_replace($this->moduleRoot() . '/', '', $path);
      }
    }

    self::assertSame([], $offenders, implode("\n", array_merge(
      ['A JavaScript file declares its own keyword list. They are declared once,'],
      ['in UtilikitRules::getAllowedKeywords(). Offending files:'],
      $offenders,
    )));
  }

  /**
   * Every keyword rule has a list, and every list is usable.
   *
   * A keyword rule with no list is unvalidated: any word at all would be
   * written into the stylesheet.
   */
  public function testEveryKeywordRuleHasAnAllowedList(): void {
    $rules = UtilikitRules::getRules();
    $keywords = UtilikitRules::getAllowedKeywords();

    self::assertNotEmpty($keywords, 'The keyword catalogue is empty.');

    $missing = [];

    foreach ($rules as $prefix => $rule) {
      if (empty($rule['isKeyword'])) {
        continue;
      }

      $css = (string) ($rule['css'] ?? '');

      if (!isset($keywords[$css]) || $keywords[$css] === []) {
        $missing[] = sprintf('%s (%s)', $prefix, $css);
        continue;
      }

      foreach ($keywords[$css] as $value) {
        self::assertIsString($value, $css . ' has a non-string keyword.');
        self::assertNotSame('', $value, $css . ' has an empty keyword.');
      }
    }

    self::assertSame([], $missing, 'Keyword rules with no allowed list: ' . implode(', ', $missing));
  }

  /**
   * The one source is populated and structurally complete.
   *
   * A single source that is empty or half-filled is worse than three copies,
   * because everything now depends on it.
   */
  public function testTheSingleSourceIsComplete(): void {
    $rules = UtilikitRules::getRules();

    self::assertGreaterThan(40, count($rules), 'The rule registry is implausibly small.');

    foreach ($rules as $prefix => $rule) {
      self::assertIsString($prefix);
      self::assertMatchesRegularExpression('/^[a-z]{2,4}$/', (string) $prefix, 'Prefix is not a short lowercase code: ' . $prefix);
      self::assertIsArray($rule, $prefix . ' is not a rule definition.');
      self::assertArrayHasKey('css', $rule, $prefix . ' has no css property.');
      self::assertNotSame('', $rule['css'], $prefix . ' has an empty css property.');
      self::assertArrayHasKey('group', $rule, $prefix . ' has no group, so it cannot be listed or explained.');
    }
  }

  /**
   * Every rule carries a plain-words label, and no two share one.
   *
   * The CKEditor panel lists utilities by these labels — "Max width (xw)", not
   * "xw — maxInlineSize". They live on the rule rather than in the editor so a
   * rule cannot be added without one and no consumer keeps its own list of
   * names. Two rules with one label would be indistinguishable in that list.
   */
  public function testEveryRuleHasUniquePlainLabel(): void {
    $labels = [];

    foreach (UtilikitRules::getRules() as $prefix => $rule) {
      self::assertIsArray($rule);
      self::assertArrayHasKey('label', $rule, $prefix . ' has no label, so the editor can only show its code.');
      self::assertIsString($rule['label']);
      self::assertNotSame('', trim($rule['label']), $prefix . ' has an empty label.');
      self::assertDoesNotMatchRegularExpression(
        '/[a-z][A-Z]/',
        $rule['label'],
        $prefix . ' label looks like a camelCase property name, not plain words.',
      );
      $labels[$prefix] = $rule['label'];
    }

    $duplicates = array_keys(array_filter(array_count_values($labels), static fn (int $n): bool => $n > 1));
    self::assertSame([], $duplicates, 'Labels used by more than one rule: ' . implode(', ', $duplicates));
  }

  /**
   * The auto keyword is allowed exactly where CSS allows it.
   *
   * MEASURED against the browser's own CSS.supports() on 2026-09-05, not
   * recalled: `inline-size`, `block-size` and `min-inline-size` accept `auto`;
   * `max-inline-size` and `max-block-size` do not — they take `none`. Before
   * this was reconciled, PHP allowed it on none of them and JavaScript allowed
   * it on `xw`, where it is invalid CSS the browser silently discards.
   */
  public function testAutoIsAllowedExactlyWhereCssAllowsIt(): void {
    $rules = UtilikitRules::getRules();

    foreach (['wd', 'ht', 'nw', 'mg'] as $prefix) {
      self::assertTrue(
        (bool) ($rules[$prefix]['allowAuto'] ?? FALSE),
        sprintf('%s (%s) accepts auto in CSS and must allow it.', $prefix, $rules[$prefix]['css'] ?? '?'),
      );
    }

    foreach (['xw', 'xh'] as $prefix) {
      self::assertFalse(
        (bool) ($rules[$prefix]['allowAuto'] ?? FALSE),
        sprintf('%s (%s) does not accept auto in CSS; it takes none.', $prefix, $rules[$prefix]['css'] ?? '?'),
      );
    }
  }

}
