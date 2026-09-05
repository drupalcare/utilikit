<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit_ai\Kernel;

use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\utilikit_ai\Traits\UtilikitAiOptionalModulesTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Every tool is discoverable, and answers when called with nothing.
 *
 * WHY THIS EXISTS. In a sibling module a service shipped with a full passing
 * test suite and NO plugin — it worked perfectly and no model could reach it.
 * Service tests cannot catch that, because the gap is between the service and
 * the plugin manager.
 *
 * The second half is the SafeContextTrait guarantee.
 * `Context::getContextValue()` throws when a required context has no value, and
 * `FunctionCallBase::populateValues()` only sets arguments the model actually
 * returned — so an omitted argument makes the first read throw from inside
 * execute(), bypassing the refusal the tool was written to give. A model
 * omitting an argument is one of the most ordinary things it does.
 *
 * @group utilikit
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('utilikit')]
final class ToolDiscoveryTest extends KernelTestBase {

  use UtilikitAiOptionalModulesTrait;

  /**
   * Every tool this module ships, by function name.
   *
   * Written out rather than derived from the plugin manager: a test that asks
   * the manager what exists and then checks that it exists passes on an empty
   * set, which is the failure it is supposed to catch.
   */
  private const TOOLS = [
    'utilikit_explain_utility' => 'utilikit:explain_utility',
    'utilikit_check_classes' => 'utilikit:check_classes',
    'utilikit_diagnose_setup' => 'utilikit:diagnose_setup',
  ];

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = ['system', 'user', 'key', 'ai', 'utilikit', 'utilikit_ai'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->skipUnlessModuleAvailable(
      'ai',
      'The function-call plugin manager does not exist without it, so tool discovery '
      . 'cannot be checked here. Run this where drupal/ai is present.',
    );

    parent::setUp();
    $this->installConfig(['utilikit']);
  }

  /**
   * Every tool is registered with the id and function name it claims.
   */
  public function testEveryToolIsDiscoverable(): void {
    $definitions = \Drupal::service('plugin.manager.ai.function_calls')->getDefinitions();

    foreach (self::TOOLS as $functionName => $pluginId) {
      self::assertArrayHasKey($pluginId, $definitions, sprintf(
        'The %s tool is not discoverable, so no model can call it.',
        $pluginId,
      ));

      $definition = $definitions[$pluginId];
      self::assertSame($functionName, $this->definitionValue($definition, 'function_name'));
      self::assertSame('utilikit', $this->definitionValue($definition, 'group'));
    }
  }

  /**
   * A tool called with no arguments at all answers in words.
   */
  public function testEveryToolAnswersWhenCalledWithNothing(): void {
    foreach (self::TOOLS as $pluginId) {
      $tool = $this->tool($pluginId);
      $tool->execute();
      $output = (string) $tool->getReadableOutput();

      self::assertNotSame('', trim($output), $pluginId . ' returned nothing.');
      self::assertGreaterThan(40, strlen($output), $pluginId . ' answered too tersely to act on.');
    }
  }

  /**
   * Explaining with no prefix lists every utility, grouped.
   */
  public function testExplainingWithNoPrefixListsEverything(): void {
    $tool = $this->tool('utilikit:explain_utility');
    $tool->execute();
    $output = (string) $tool->getReadableOutput();

    self::assertStringContainsString('Box Model', $output);
    self::assertStringContainsString('uk-pd', $output);
    self::assertStringContainsString('utilikit', $output, 'The listing must mention the marker class.');
  }

  /**
   * Explaining one prefix gives its values, an example and the marker.
   */
  public function testExplainingOnePrefixGivesValuesAndTheMarker(): void {
    $tool = $this->tool('utilikit:explain_utility');
    $tool->setContextValue('prefix', 'dp');
    $tool->execute();
    $output = (string) $tool->getReadableOutput();

    self::assertStringContainsString('display', $output);
    self::assertStringContainsString('flex', $output, 'It must list the accepted keywords.');
    self::assertStringContainsString('utilikit uk-dp--', $output, 'The example must carry the marker.');
  }

  /**
   * A whole class is accepted where a prefix was asked for.
   *
   * People write `uk-pd--16px` at least as often as `pd`, and refusing that
   * would be a refusal on a technicality.
   */
  public function testWholeClassWorksWherePrefixIsExpected(): void {
    $tool = $this->tool('utilikit:explain_utility');
    $tool->setContextValue('prefix', 'uk-md-pd--16px');
    $tool->execute();

    self::assertStringContainsString('padding', (string) $tool->getReadableOutput());
  }

  /**
   * Checking classes names the bad ones and flags a missing marker.
   */
  public function testCheckingNamesTheBadClassesAndTheMissingMarker(): void {
    $tool = $this->tool('utilikit:check_classes');
    $tool->setContextValue('classes', 'uk-pd--16px uk-dp--banana');
    $tool->execute();
    $output = (string) $tool->getReadableOutput();

    self::assertStringContainsString('REJECTED uk-dp--banana', $output);
    self::assertStringContainsString('MISSING the "utilikit" class', $output);
    self::assertStringContainsString('OK       uk-pd--16px', $output);
  }

  /**
   * With the marker present, the check says so rather than staying silent.
   */
  public function testPresentMarkerIsConfirmed(): void {
    $tool = $this->tool('utilikit:check_classes');
    $tool->setContextValue('classes', 'utilikit uk-pd--16px');
    $tool->execute();
    $output = (string) $tool->getReadableOutput();

    self::assertStringContainsString('marker class is present', $output);
    self::assertStringContainsString('Every class is valid', $output);
  }

  /**
   * The setup diagnosis names the mode and reaches a verdict.
   */
  public function testTheSetupDiagnosisNamesTheModeAndConcludes(): void {
    $tool = $this->tool('utilikit:diagnose_setup');
    $tool->execute();
    $output = (string) $tool->getReadableOutput();

    self::assertStringContainsString('UtiliKit is in', $output);
    self::assertStringContainsString('utilikit', $output, 'It must mention the marker whatever else is true.');
    self::assertMatchesRegularExpression(
      '/(Nothing in this site|will stop a valid class)/',
      $output,
      'A list of findings with no verdict invites hunting for a problem that is not there.',
    );
  }

  /**
   * A tool plugin that can be run.
   *
   * `createInstance()` returns `object`; asserting the interface narrows that
   * and states something the test cares about — a discovered plugin that
   * cannot be executed is not a usable tool.
   *
   * @param string $pluginId
   *   The plugin id.
   *
   * @return \Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface
   *   The tool.
   */
  private function tool(string $pluginId): ExecutableFunctionCallInterface {
    $tool = \Drupal::service('plugin.manager.ai.function_calls')->createInstance($pluginId);

    self::assertInstanceOf(ExecutableFunctionCallInterface::class, $tool, $pluginId . ' cannot be executed.');

    return $tool;
  }

  /**
   * One value from a plugin definition, however the manager returns it.
   *
   * @param mixed $definition
   *   The plugin definition.
   * @param string $key
   *   The value to read.
   *
   * @return mixed
   *   The value, or NULL when absent.
   */
  private function definitionValue(mixed $definition, string $key): mixed {
    if (is_array($definition)) {
      return $definition[$key] ?? NULL;
    }

    return is_object($definition) ? ($definition->{$key} ?? NULL) : NULL;
  }

}
