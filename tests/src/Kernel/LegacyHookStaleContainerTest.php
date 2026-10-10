<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\utilikit\Hook\UtilikitHooks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder as SymfonyContainerBuilder;

/**
 * The #[LegacyHook] wrappers work when the Hook class is not a service.
 *
 * WHY THIS EXISTS. Issue #3628961 (reported against solo_utilities, same
 * defect): "You have requested a non-existent service ...Hooks". UtiliKit
 * first declared Drupal\utilikit\Hook\UtilikitHooks as a service in 1.0.5. A
 * site updated from 1.0.4 runs the new .module against the container compiled
 * for 1.0.4 until a cache rebuild, and every wrapper called
 * \Drupal::service(UtilikitHooks::class). hook_page_attachments() fires on
 * every page, so every page could answer HTTP 500 until `drush cr` or
 * `drush updb`.
 *
 * This test builds exactly that container: the Hook class definition is
 * removed after every other compiler pass, including core's
 * HookCollectorPass, which re-registers Hook classes on Drupal 11.1+.
 *
 * @group utilikit
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('utilikit')]
#[RunTestsInSeparateProcesses]
class LegacyHookStaleContainerTest extends KernelTestBase implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'utilikit',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->addCompilerPass($this, PassConfig::TYPE_AFTER_REMOVING, -1000);
  }

  /**
   * Removes the Hook class service, as a pre-1.0.5 container lacks it.
   */
  public function process(SymfonyContainerBuilder $container): void {
    $container->removeDefinition(UtilikitHooks::class);
  }

  /**
   * The wrappers resolve the Hook class without the container service.
   */
  public function testWrappersRunWithoutTheHookService(): void {
    // Control: without this, a green result would prove nothing.
    $this->assertFalse(
      $this->container->has(UtilikitHooks::class),
      'The Hook class must be absent from the container, as on a site updated from 1.0.4 before a cache rebuild.'
    );

    // The theme registry answer proves the Hook class really was
    // instantiated and its method ran, not merely that nothing threw.
    $theme = utilikit_theme();
    $this->assertIsArray($theme);
    $this->assertArrayHasKey('utilikit_update_button', $theme);

    $route_match = $this->container->get('current_route_match');
    $help = utilikit_help('help.page.utilikit', $route_match);
    $this->assertIsString($help);
    $this->assertStringContainsString('UtiliKit', $help);
    $this->assertNull(utilikit_help('some.other.route', $route_match));

    // The wrapper that runs on every page. utilikit.settings is not
    // installed here, so the hook returns before attaching anything; what is
    // under test is that the wrapper resolves the Hook class at all.
    $attachments = [];
    utilikit_page_attachments($attachments);
    $this->assertSame([], $attachments);
  }

}
