<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit_ai\Kernel;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\utilikit_ai\Traits\UtilikitAiOptionalModulesTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The shipped agent, and whether it points at tools that exist.
 *
 * WHY THIS FILE EXISTS. A FunctionCall plugin reaches an assistant only through
 * ai_agents — MEASURED on sts.local 2026-09-05, where three sibling AI modules
 * had shipped tools and no agent, so nothing on the site could call any of
 * them. The agent config is what makes the module usable, which makes it worth
 * testing rather than trusting.
 *
 * A tool id in that file that no longer matches a plugin id is the exact kind
 * of drift that produces an agent which loads, looks configured, and silently
 * cannot do the thing it advertises. Nothing else would catch it: the YAML is
 * valid, the agent installs, and the failure only appears when a person asks.
 *
 * @group utilikit
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('utilikit')]
#[RunTestsInSeparateProcesses]
final class AgentConfigTest extends KernelTestBase {

  use UtilikitAiOptionalModulesTrait;

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    // Node is REQUIRED here, and finding that out is why this test earns its
    // place. apply_classes declares module_dependencies: ['utilikit', 'node'],
    // so without node installed the plugin manager does not offer it at all
    // and the agent's tool list silently loses its only writer. That is
    // correct behaviour — the tool edits nodes — but a test environment that
    // omits node reports it as drift.
    'node',
    'key',
    'ai',
    'utilikit',
    'utilikit_ai',
  ];

  /**
   * The tool ids the agent switches on.
   *
   * @var list<string>
   */
  private array $toolIds = [];

  /**
   * The tool ids that have a settings block.
   *
   * @var list<string>
   */
  private array $settingsKeys = [];

  /**
   * The agent's system prompt.
   */
  private string $prompt = '';

  /**
   * The modules the agent config is enforced-dependent on.
   *
   * @var list<string>
   */
  private array $enforcedModules = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // Same reason as ToolDiscoveryTest: without drupal/ai there is no
    // function-call plugin manager, so "does this tool exist" has no answer
    // here. D12 has no drupal/ai release yet, and erroring there would report
    // a missing dependency as a broken agent.
    $this->skipUnlessModuleAvailable(
      'ai',
      'The function-call plugin manager does not exist without it, so the agent\'s '
      . 'tool list cannot be checked here. Run this where drupal/ai is present.',
    );

    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'filter', 'node', 'utilikit']);

    $path = sprintf(
      '%s/config/optional/ai_agents.ai_agent.utilikit_agent.yml',
      \Drupal::service('extension.list.module')->getPath('utilikit_ai'),
    );

    self::assertFileExists($path, 'The module ships a default agent, or its tools reach nobody.');
    $parsed = Yaml::decode((string) file_get_contents($path));
    self::assertIsArray($parsed);

    $this->toolIds = self::enabledKeys($parsed['tools'] ?? NULL);
    $this->settingsKeys = self::stringKeys($parsed['tool_settings'] ?? NULL);

    $prompt = $parsed['system_prompt'] ?? '';
    $this->prompt = is_string($prompt) ? $prompt : '';

    $dependencies = $parsed['dependencies'] ?? NULL;
    $enforced = is_array($dependencies) ? ($dependencies['enforced'] ?? NULL) : NULL;
    $this->enforcedModules = self::stringList(is_array($enforced) ? ($enforced['module'] ?? NULL) : NULL);
  }

  /**
   * Every tool the agent claims is a plugin that actually exists.
   */
  public function testEveryToolTheAgentListsExists(): void {
    $available = array_keys($this->toolManager()->getDefinitions());

    self::assertNotEmpty($this->toolIds, 'An agent with no tools is an agent that does nothing.');

    foreach ($this->toolIds as $id) {
      self::assertContains($id, $available, sprintf('The agent lists "%s", which is not a plugin.', $id));
    }
  }

  /**
   * It ships every tool the module has, not a subset that drifted.
   */
  public function testItShipsEveryToolTheModuleHas(): void {
    $ours = [];

    foreach (array_keys($this->toolManager()->getDefinitions()) as $id) {
      if (is_string($id) && str_starts_with($id, 'utilikit:')) {
        $ours[] = $id;
      }
    }

    $listed = $this->toolIds;
    sort($ours);
    sort($listed);
    self::assertSame($ours, $listed, 'A tool the module ships but the agent omits is unreachable.');
  }

  /**
   * Every listed tool has settings, because ai_agents reads them per tool.
   */
  public function testEveryToolHasItsSettings(): void {
    foreach ($this->toolIds as $id) {
      self::assertContains($id, $this->settingsKeys, sprintf('No settings for "%s".', $id));
    }
  }

  /**
   * The prompt carries the one fact without which nothing it emits works.
   */
  public function testThePromptCarriesTheMarkerRule(): void {
    $prompt = $this->prompt;

    self::assertStringContainsString('utilikit', $prompt);
    self::assertMatchesRegularExpression(
      '/only styles elements/i',
      $prompt,
      'The marker rule is the one thing the model cannot work out for itself.',
    );
  }

  /**
   * It is enforced-dependent on this module, so it is removed with it.
   */
  public function testItIsTiedToThisModule(): void {
    self::assertSame(
      ['utilikit_ai'],
      $this->enforcedModules,
      'Without an enforced dependency the agent outlives the module that defines its tools.',
    );
  }

  /**
   * The plugin manager, narrowed through a core interface.
   *
   * \Drupal::service() returns object, and drupal/ai's own manager class
   * cannot be named here — it is absent whenever the project is built from its
   * own composer.json, which is what drupal.org CI does. PluginManagerInterface
   * is core, is always resolvable, and declares getDefinitions() through
   * DiscoveryInterface, so this narrows the type honestly instead of hiding the
   * problem behind an exclusion.
   *
   * @return \Drupal\Component\Plugin\PluginManagerInterface
   *   The function-call plugin manager.
   */
  private function toolManager(): PluginManagerInterface {
    $manager = \Drupal::service('plugin.manager.ai.function_calls');

    if (!$manager instanceof PluginManagerInterface) {
      self::fail('The function-call plugin manager is not a plugin manager.');
    }

    return $manager;
  }

  /**
   * The ids of every tool switched on, as strings.
   *
   * Decoded YAML is mixed all the way down. Walking it with real checks costs
   * a few lines and means nothing downstream has to be asserted about.
   *
   * @param mixed $value
   *   The decoded `tools` mapping, or anything else.
   *
   * @return list<string>
   *   The enabled ids.
   */
  private static function enabledKeys(mixed $value): array {
    $out = [];

    if (is_array($value)) {
      foreach ($value as $key => $enabled) {
        if (is_string($key) && $enabled) {
          $out[] = $key;
        }
      }
    }

    return $out;
  }

  /**
   * Every key of a decoded mapping, as strings.
   *
   * @param mixed $value
   *   The decoded mapping, or anything else.
   *
   * @return list<string>
   *   The keys.
   */
  private static function stringKeys(mixed $value): array {
    $out = [];

    if (is_array($value)) {
      foreach (array_keys($value) as $key) {
        if (is_string($key)) {
          $out[] = $key;
        }
      }
    }

    return $out;
  }

  /**
   * A decoded list, as strings.
   *
   * @param mixed $value
   *   The decoded list, or anything else.
   *
   * @return list<string>
   *   The strings in it.
   */
  private static function stringList(mixed $value): array {
    $out = [];

    if (is_array($value)) {
      foreach ($value as $item) {
        if (is_string($item)) {
          $out[] = $item;
        }
      }
    }

    return $out;
  }

}
