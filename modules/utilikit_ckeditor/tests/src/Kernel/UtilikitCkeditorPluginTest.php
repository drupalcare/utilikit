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

  /**
   * Uninstalling takes the toolbar item with it.
   *
   * WHY THIS IS THE MODULE'S JOB AND NOT CORE'S, measured on Drupal 11.4.
   * `Editor::calculateDependencies()` records a dependency on the EDITOR
   * plugin's provider only — `ckeditor5` — and core's ckeditor5 module adds
   * no dependency calculation of its own; core carries a `@todo` in
   * Editor.php saying plugin configuration and its provider dependency are
   * not managed. Saving the editor through the normal entity path with this
   * module INSTALLED still produced `module: [ckeditor5]` and nothing else,
   * so there is no config dependency for the uninstall machinery to act on.
   *
   * Without hook_uninstall(), the format keeps a toolbar item pointing at a
   * plugin that no longer exists — a broken button and nothing saying why.
   */
  public function testUninstallRemovesTheToolbarItem(): void {
    // Uninstalling ANY module makes user_modules_uninstalled() clear that
    // module's rows from users_data, so the table has to exist first.
    $this->installSchema('user', ['users_data']);

    $storage = \Drupal::entityTypeManager()->getStorage('editor');

    self::assertContains(
      'utilikit',
      $storage->load('utilikit_test')->getSettings()['toolbar']['items'],
      'The fixture starts with the toolbar item in place.',
    );

    $this->container->get('module_installer')->uninstall(['utilikit_ckeditor']);
    $storage->resetCache(['utilikit_test']);

    self::assertNotContains(
      'utilikit',
      $storage->load('utilikit_test')->getSettings()['toolbar']['items'],
      'Uninstalling removed the toolbar item it had added.',
    );
  }

}
