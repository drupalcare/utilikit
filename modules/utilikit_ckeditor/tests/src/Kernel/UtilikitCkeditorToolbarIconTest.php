<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit_ckeditor\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Every UtiliKit toolbar item has an icon, and it is one SVG in both places.
 *
 * WHY THIS TEST EXISTS. Core's text format admin UI draws each toolbar button
 * as an EMPTY span, `.ckeditor5-toolbar-button-<item>`, and leaves the icon to
 * the plugin's admin library as a CSS background image. Until 1.0.7 this
 * module's admin library existed but set no background image, so "Available
 * buttons" showed a blank grey box. Nothing went red, because nothing looked.
 *
 * The toolbar item names are read from the plugin definition, not typed here,
 * so a second button added later without an icon fails this test too.
 *
 * @group utilikit
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('utilikit')]
#[RunTestsInSeparateProcesses]
final class UtilikitCkeditorToolbarIconTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'system',
    'user',
    'filter',
    'editor',
    'ckeditor5',
    'utilikit',
    'utilikit_ckeditor',
  ];

  /**
   * The two committed editor plugin builds: DLL first, then externals.
   */
  private const BUILDS = [
    'js/build/utilikit.js',
    'js/build/utilikit.externals.js',
  ];

  /**
   * The module's path, relative to the Drupal root.
   */
  private string $modulePath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // ckeditor5 resolves plugin libraries through the active theme.
    $this->container->get('theme_installer')->install(['stark']);
    $this->modulePath = \Drupal::service('extension.list.module')
      ->getPath('utilikit_ckeditor');
  }

  /**
   * Each toolbar item gets a non-empty background image from the admin library.
   */
  public function testEveryToolbarItemHasAnAdminIcon(): void {
    $definition = \Drupal::service('plugin.manager.ckeditor5.plugin')
      ->getDefinition('utilikit_ckeditor_classes');

    $adminLibrary = $definition->getAdminLibrary();
    self::assertNotEmpty(
      $adminLibrary,
      'Without an admin library core has nothing to draw the button with.',
    );

    [$extension, $name] = explode('/', (string) $adminLibrary, 2);
    $library = \Drupal::service('library.discovery')
      ->getLibraryByName($extension, $name);
    self::assertIsArray($library, "The admin library $adminLibrary is defined.");

    $css = '';
    foreach ($library['css'] ?? [] as $asset) {
      self::assertFileExists($asset['data']);
      $css .= file_get_contents($asset['data']);
    }

    $items = array_keys($definition->getToolbarItems());
    self::assertNotEmpty($items, 'The plugin declares at least one toolbar item.');

    foreach ($items as $item) {
      $pattern = '/\.ckeditor5-toolbar-button-' . preg_quote($item, '/')
        . '\s*\{[^}]*background-image:\s*url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/';
      self::assertMatchesRegularExpression(
        $pattern,
        $css,
        "Toolbar item '$item' has a background image in $adminLibrary, or "
        . '"Available buttons" shows an empty box.',
      );

      preg_match($pattern, $css, $match);
      $svg = $this->modulePath . '/css/' . $match[1];
      self::assertFileExists($svg, 'The icon the admin CSS points at is shipped.');
      self::assertStringContainsString('viewBox=', (string) file_get_contents($svg));
    }
  }

  /**
   * The editor button and the admin list use the same SVG.
   *
   * The built bundle inlines the SVG at webpack time. If someone edits the
   * SVG and forgets to rebuild, or points the JavaScript at a different file,
   * the two icons drift. This pins them to one source.
   */
  public function testTheEditorButtonUsesTheSameSvg(): void {
    $path = $this->modulePath . '/icons/handyman.svg';
    self::assertFileExists($path, 'The shared icon is shipped.');
    $svg = (string) file_get_contents($path);
    self::assertSame(
      1,
      preg_match('/<path d="([^"]+)"/', $svg, $match),
      'The icon has path data.',
    );

    foreach (self::BUILDS as $build) {
      $bundle = (string) file_get_contents($this->modulePath . '/' . $build);
      self::assertStringContainsString(
        $match[1],
        $bundle,
        "$build embeds the same SVG the admin CSS uses — "
        . 'rebuild js/build after changing icons/handyman.svg.',
      );
    }
  }

  /**
   * Both committed builds come from the current source and the right config.
   *
   * Webpack.config.js stamps each build with `utilikit-src-sha256:<hash>`,
   * a sha256 over js/ckeditor5_plugins/utilikit/src/*.js in sorted order,
   * then icons/handyman.svg, then webpack.config.js itself. This recomputes
   * it, so editing the source or the build config and committing without
   * `npm run build` fails here — CI never rebuilds. The recipe is defined
   * twice, here and in webpack.config.js sourceHash(); if they drift this
   * test goes red, never silently green.
   */
  public function testBothBuildsMatchTheCurrentSource(): void {
    $srcDir = $this->modulePath . '/js/ckeditor5_plugins/utilikit/src';
    $files = glob($srcDir . '/*.js') ?: [];
    self::assertNotEmpty($files, 'The plugin source is shipped.');
    sort($files, SORT_STRING);
    $files[] = $this->modulePath . '/icons/handyman.svg';
    $files[] = $this->modulePath . '/webpack.config.js';

    $context = hash_init('sha256');
    foreach ($files as $file) {
      hash_update($context, (string) file_get_contents($file));
    }
    $expected = 'utilikit-src-sha256:' . hash_final($context);

    foreach (self::BUILDS as $build) {
      $bundle = (string) file_get_contents($this->modulePath . '/' . $build);
      self::assertSame(
        1,
        preg_match('/utilikit-src-sha256:[0-9a-f]{64}/', $bundle, $stamp),
        "$build carries a source stamp — it was built by webpack.config.js.",
      );
      self::assertSame(
        $expected,
        $stamp[0],
        "$build is stale: run `npm run build` in modules/utilikit_ckeditor.",
      );
    }

    $dll = (string) file_get_contents($this->modulePath . '/' . self::BUILDS[0]);
    $externals = (string) file_get_contents($this->modulePath . '/' . self::BUILDS[1]);
    self::assertStringContainsString('CKEditor5.dll', $dll, 'The DLL build links to the Drupal 11 runtime.');
    self::assertStringNotContainsString(
      'CKEditor5.dll',
      $externals,
      'The externals build must not touch the DLL — on Drupal 12 that is the deprecated shim.',
    );
    self::assertStringContainsString('CKEDITOR', $externals, 'The externals build uses the UMD global.');
  }

}
