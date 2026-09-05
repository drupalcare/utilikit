<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit_ckeditor\Kernel;

use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\utilikit\Service\UtilikitRules;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The editor plugin, and the contract it has with the built JavaScript.
 *
 * WHAT IS WORTH TESTING HERE. The JavaScript needs a browser, and CI has one;
 * what a kernel test can pin is the half that decides whether the JavaScript
 * has anything to work with — that the plugin is discoverable, that the rules
 * reaching the editor are THIS SITE'S rules rather than a copy, and that the
 * marker class is among them. Every one of those failing produces a toolbar
 * button that opens and then does nothing, which is the defect class this
 * project keeps hitting.
 *
 * @group utilikit
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('utilikit')]
#[RunTestsInSeparateProcesses]
final class UtilikitCkeditorPluginTest extends KernelTestBase {

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
   * The plugin's configuration as the editor receives it.
   *
   * @var array<string, mixed>
   */
  private array $config;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // ckeditor5 resolves plugin libraries through the active theme, so one has
    // to exist before the plugin manager is asked for anything.
    $this->container->get('theme_installer')->install(['stark']);
    $this->installConfig(['system', 'filter', 'utilikit']);

    FilterFormat::create([
      'format' => 'utilikit_test',
      'name' => 'UtiliKit test',
      'filters' => [],
    ])->save();

    $editor = Editor::create([
      'format' => 'utilikit_test',
      'editor' => 'ckeditor5',
      'settings' => [
        'toolbar' => ['items' => ['utilikit']],
        'plugins' => [],
      ],
      'image_upload' => [],
    ]);
    $editor->save();

    $manager = \Drupal::service('plugin.manager.ckeditor5.plugin');
    $definitions = $manager->getDefinitions();
    self::assertArrayHasKey(
      'utilikit_ckeditor_classes',
      $definitions,
      'The plugin is discoverable, or the toolbar item never appears.',
    );

    $instance = $manager->getPlugin('utilikit_ckeditor_classes', $editor);
    $this->config = $instance->getDynamicPluginConfig([], $editor)['utilikit'] ?? [];
  }

  /**
   * The editor is given this site's utilities, not an empty list.
   */
  public function testTheEditorReceivesThisSitesUtilities(): void {
    self::assertNotEmpty(
      $this->config['utilities'] ?? [],
      'With no utilities the toolbar button opens an empty panel.',
    );

    $prefixes = array_column($this->config['utilities'], 'prefix');
    $expected = array_keys(UtilikitRules::getRules());

    sort($prefixes);
    sort($expected);
    self::assertSame(
      $expected,
      $prefixes,
      'The editor sees exactly the rules UtilikitRules declares — no fewer, and '
      . 'no second copy that could drift.',
    );
  }

  /**
   * The marker class travels with the rules.
   */
  public function testTheMarkerClassIsSent(): void {
    self::assertSame(
      UtilikitConstants::MARKER_CLASS,
      $this->config['marker'] ?? NULL,
      'Without the marker every class the editor applies is inert.',
    );
  }

  /**
   * Keyword rules carry their accepted values, numeric rules do not.
   */
  public function testKeywordRulesCarryTheirAcceptedValues(): void {
    $byPrefix = [];

    foreach ($this->config['utilities'] as $utility) {
      $byPrefix[$utility['prefix']] = $utility;
    }

    self::assertArrayHasKey('dp', $byPrefix, 'display is a keyword rule.');
    self::assertContains(
      'flex',
      $byPrefix['dp']['keywords'],
      'The editor cannot offer values it was not told about.',
    );

    self::assertArrayHasKey('pd', $byPrefix);
    self::assertSame(
      [],
      $byPrefix['pd']['keywords'],
      'padding takes a length, so it has no keyword list to offer.',
    );
  }

  /**
   * The breakpoints offered are the ones UtiliKit actually generates.
   */
  public function testBreakpointsMatchTheModule(): void {
    self::assertSame(
      array_keys(UtilikitConstants::DEFAULT_BREAKPOINTS),
      $this->config['breakpoints'] ?? [],
    );
  }

  /**
   * The built JavaScript the library points at exists.
   *
   * A library entry naming a file that is not there produces a toolbar button
   * that silently never loads. js/build is committed precisely so this holds
   * for anyone installing from the dev branch.
   */
  public function testTheBuiltPluginFileIsShipped(): void {
    $path = \Drupal::service('extension.list.module')->getPath('utilikit_ckeditor');

    self::assertFileExists(
      $path . '/js/build/utilikit.js',
      'The built plugin is committed, not left for the installer to compile.',
    );
  }

}
