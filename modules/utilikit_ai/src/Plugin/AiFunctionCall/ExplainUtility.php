<?php

declare(strict_types=1);

namespace Drupal\utilikit_ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\utilikit_ai\Service\RuleCatalogueInterface;
use Drupal\utilikit_ai\Tool\SafeContextTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Says what a UtiliKit utility does and what values it will accept.
 *
 * THE ANSWER A MODEL CANNOT GUESS. UtiliKit uses fifty two-letter prefixes and
 * eleven value grammars, and which grammar applies is not predictable from the
 * CSS property. `fw` (font-weight) takes a NUMBER here — `uk-fw--700` is right
 * and `uk-fw--bold` is not — while `ta` (text-align) takes a keyword and
 * nothing else. Asked for a colour a model writes `#3b82f6`, hash and all.
 * Every one of those looks right, validates as markup, and produces nothing.
 *
 * An earlier version of this very docblock asserted that `fw` was a keyword
 * rule. It is not, and the mistake survived until the tool was run against a
 * real site. That is the argument for the tool in one sentence: the rules are
 * not guessable, including by whoever writes the documentation.
 *
 * Reads only.
 *
 * @package Drupal\utilikit_ai\Plugin\AiFunctionCall
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[FunctionCall(
  id: 'utilikit:explain_utility',
  function_name: 'utilikit_explain_utility',
  name: 'Explain a UtiliKit utility',
  description: 'Says what a UtiliKit utility prefix does and exactly which values it accepts on this site, read from the module rather than guessed. Call this before writing any uk- class you are not certain of: the prefixes are two letters and the accepted values differ by rule, so a plausible-looking class often produces no CSS at all. Omit the prefix to list every utility grouped by purpose. Reads only.',
  group: 'utilikit',
  module_dependencies: ['utilikit'],
  context_definitions: [
    'prefix' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('The utility prefix'),
      description: 'A two-to-four letter prefix such as pd, mg, dp or bg. Omit to list every utility this site has, grouped.',
      required: FALSE,
    ),
  ],
)]
final class ExplainUtility extends FunctionCallBase implements ExecutableFunctionCallInterface {

  use SafeContextTrait;

  /**
   * The utilities this site has.
   */
  protected RuleCatalogueInterface $catalogue;

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
      $container->get('plugin.manager.ai_data_type_converter'),
    );
    $instance->catalogue = $container->get(RuleCatalogueInterface::class);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $value = $this->contextValue('prefix');
    $prefix = is_string($value) ? strtolower(trim($value)) : '';

    if ($prefix === '') {
      $this->setOutput($this->listAll());

      return;
    }

    // People write the whole class as often as the prefix.
    if (preg_match('/^uk-(?:(?:sm|md|lg|xl|xxl)-)?([a-z]{2,4})(?:--.*)?$/', $prefix, $m)) {
      $prefix = $m[1];
    }

    $utility = $this->catalogue->get($prefix);

    if ($utility === NULL) {
      $this->setOutput(implode("\n", [
        sprintf('There is no "%s" utility on this site.', $prefix),
        '',
        'These exist: ' . implode(', ', $this->catalogue->prefixes()) . '.',
        'Call this tool with no prefix to see them grouped, with what each accepts.',
      ]));

      return;
    }

    $this->setOutput(implode("\n", array_filter([
      sprintf('uk-%s — %s (%s)', $utility->prefix, $utility->css, $utility->group),
      '',
      'Accepts: ' . $utility->acceptedValues(),
      $utility->hasSides()
        ? 'Directions: also takes t, r, b or l — uk-' . $utility->prefix . '--t-16px sets only the top.'
        : NULL,
      $utility->shorthand !== []
        ? 'Shorthand: ' . implode(', ', array_map(
            static fn(string $k, string $v): string => $k . ' means ' . $v,
            array_keys($utility->shorthand),
            $utility->shorthand,
          )) . '.'
        : NULL,
      '',
      'Example: ' . $utility->example(),
      '',
      // The single most important thing, and the easiest to leave out.
      'The "utilikit" class in that example is required. UtiliKit only styles '
        . 'elements carrying it, so uk- classes on their own do nothing at all.',
      'Responsive: put a breakpoint after uk- — uk-md-' . $utility->prefix . '--… applies from medium up.',
    ])));
  }

  /**
   * Every utility, grouped, with what each accepts.
   *
   * @return string
   *   The listing.
   */
  private function listAll(): string {
    $lines = [sprintf('This site has %d UtiliKit utilities.', count($this->catalogue->all())), ''];

    foreach ($this->catalogue->byGroup() as $group => $utilities) {
      $lines[] = $group;

      foreach ($utilities as $utility) {
        $lines[] = sprintf('  uk-%-4s %-18s %s', $utility->prefix, $utility->css, $utility->acceptedValues());
      }

      $lines[] = '';
    }

    $lines[] = 'Every element also needs the "utilikit" class itself, or none of these apply.';
    $lines[] = 'Ask about one prefix for its full detail and an example.';

    return implode("\n", $lines);
  }

}
