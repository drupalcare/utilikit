<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit_ckeditor\Kernel;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\utilikit_ckeditor\Hook\UtilikitCkeditorHooks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The editor plugin build a site receives matches the CKEditor 5 core ships.
 *
 * WHY THIS TEST EXISTS. Drupal 11 runs CKEditor 5 as a DLL; Drupal 12 runs a
 * UMD bundle and reaches DLL-built plugins only through a deprecated shim.
 * Serving the wrong build is not a PHP error: on Drupal 11 the externals build
 * never registers (there is no `CKEDITOR` global) and the button silently
 * does not appear; on Drupal 12 the DLL build works but raises the
 * deprecation on every editor page. Both happen in JavaScript, after every
 * PHP test has passed — so this pins the choice where PHP makes it.
 *
 * @group utilikit
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('utilikit')]
#[RunTestsInSeparateProcesses]
final class UtilikitCkeditorBuildSelectionTest extends KernelTestBase {

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
   * On the running core, exactly one build is attached: the matching one.
   */
  public function testTheRunningCoreGetsTheMatchingBuild(): void {
    $discovery = $this->container->get('library.discovery');

    $core = $discovery->getLibraryByName('core', 'ckeditor5');
    self::assertIsArray($core, 'core/ckeditor5 is defined.');
    $coreFiles = array_column($core['js'], 'data');
    $umd = array_filter($coreFiles, static fn (string $file): bool => str_ends_with($file, '/ckeditor5.umd.js'));
    $dll = array_filter($coreFiles, static fn (string $file): bool => str_ends_with($file, '/ckeditor5-dll.js'));
    self::assertCount(
      1,
      $umd + $dll,
      'Core loads exactly one CKEditor 5 runtime this test recognises: ' . implode(', ', $coreFiles),
    );

    $library = $discovery->getLibraryByName('utilikit_ckeditor', 'utilikit_ckeditor');
    self::assertIsArray($library);
    $pluginFiles = array_values(array_filter(
      array_column($library['js'], 'data'),
      static fn (string $file): bool => str_contains($file, '/js/build/'),
    ));

    self::assertCount(1, $pluginFiles, 'Exactly one plugin build is attached: ' . implode(', ', $pluginFiles));
    $expected = $umd !== [] ? UtilikitCkeditorHooks::EXTERNALS_BUILD : UtilikitCkeditorHooks::DLL_BUILD;
    self::assertStringEndsWith(
      '/' . $expected,
      $pluginFiles[0],
      $umd !== []
        ? 'Core ships the UMD runtime, so the plugin must be the externals build.'
        : 'Core ships the DLL runtime, so the plugin must be the DLL build.',
    );
    self::assertFileExists($pluginFiles[0], 'The selected build is shipped.');

    $options = $library['js'][array_search($pluginFiles[0], array_column($library['js'], 'data'), TRUE)];
    self::assertFalse($options['preprocess'], 'The build keeps preprocess: false, so it loads after core.');
    self::assertTrue($options['minified']);
  }

  /**
   * Both branches of the decision, on whichever core runs the suite.
   *
   * The test above proves only the branch the running core takes. This one
   * feeds the hook each runtime, so a Drupal 11 job also notices if the UMD
   * branch breaks, and the other way round.
   */
  public function testTheDecisionFollowsCoresRuntimeFile(): void {
    $cases = [
      'UMD runtime' => [['core/assets/vendor/ckeditor5/ckeditor5.umd.js'], UtilikitCkeditorHooks::EXTERNALS_BUILD],
      'DLL runtime' => [
        ['core/assets/vendor/ckeditor5/ckeditor5-dll/ckeditor5-dll.js'],
        UtilikitCkeditorHooks::DLL_BUILD,
      ],
      'no core library' => [NULL, UtilikitCkeditorHooks::DLL_BUILD],
    ];

    foreach ($cases as $label => [$coreFiles, $expected]) {
      $discovery = $this->createStub(LibraryDiscoveryInterface::class);
      $discovery->method('getLibraryByName')->willReturnCallback(
        static fn (string $extension, string $name): array|false => $extension === 'core' && $name === 'ckeditor5' && $coreFiles !== NULL
          ? ['js' => array_map(static fn (string $file): array => ['data' => $file], $coreFiles)]
          : FALSE,
      );

      $libraries = [
        'utilikit_ckeditor' => [
          'js' => [UtilikitCkeditorHooks::DLL_BUILD => ['preprocess' => FALSE, 'minified' => TRUE]],
        ],
      ];
      (new UtilikitCkeditorHooks($discovery))->libraryInfoAlter($libraries, 'utilikit_ckeditor');

      self::assertSame(
        [$expected => ['preprocess' => FALSE, 'minified' => TRUE]],
        $libraries['utilikit_ckeditor']['js'],
        "$label: one build, the right one, options unchanged.",
      );
    }

    // Another extension's libraries are never touched, and core is not read.
    $discovery = $this->createMock(LibraryDiscoveryInterface::class);
    $discovery->expects(self::never())->method('getLibraryByName');
    $other = ['x' => ['js' => [UtilikitCkeditorHooks::DLL_BUILD => []]]];
    $before = $other;
    (new UtilikitCkeditorHooks($discovery))->libraryInfoAlter($other, 'core');
    self::assertSame($before, $other);
  }

}
