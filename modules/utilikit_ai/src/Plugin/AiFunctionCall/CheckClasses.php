<?php

declare(strict_types=1);

namespace Drupal\utilikit_ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\utilikit_ai\Service\ClassValidatorInterface;
use Drupal\utilikit_ai\Tool\SafeContextTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Checks a class string before anyone puts it in a page.
 *
 * WHY THIS EXISTS SEPARATELY FROM `explain_utility`. Explaining is what a model
 * does before writing; this is what it must do after. A model that has been
 * told the rules still produces `uk-fw--700` and `uk-bg--#3b82f6`, because CSS
 * habits are stronger than one paragraph of instruction. The only reliable
 * check is to run the candidate past the module itself.
 *
 * IT ALWAYS REPORTS THE MARKER. Measured in a browser 2026-09-05: UtiliKit
 * styles only elements carrying the `utilikit` class, so a perfectly valid
 * `uk-pd--16px` on its own renders nothing. That is the single most common way
 * to produce markup which looks right and does nothing, and it is invisible in
 * the class string unless something says so.
 *
 * Reads only; it changes no content and no configuration.
 *
 * @package Drupal\utilikit_ai\Plugin\AiFunctionCall
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[FunctionCall(
  id: 'utilikit:check_classes',
  function_name: 'utilikit_check_classes',
  name: 'Check UtiliKit classes before using them',
  description: 'Checks a UtiliKit class string against what this site actually supports, and says which classes work, which do not and why. Call this on any uk- classes you are about to give someone or put in content — a class with an unknown prefix or a wrong value kind renders nothing at all and reports no error. It also tells you whether the required "utilikit" marker class is present. Reads only.',
  group: 'utilikit',
  module_dependencies: ['utilikit'],
  context_definitions: [
    'classes' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('The classes'),
      description: 'The class string to check, space separated — for example "utilikit uk-pd--16px uk-bg--3b82f6".',
      required: FALSE,
    ),
  ],
)]
final class CheckClasses extends FunctionCallBase implements ExecutableFunctionCallInterface {

  use SafeContextTrait;

  /**
   * Decides whether each class works here.
   */
  protected ClassValidatorInterface $validator;

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
    $instance->validator = $container->get(ClassValidatorInterface::class);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $value = $this->contextValue('classes');
    $classes = is_string($value) ? trim($value) : '';

    if ($classes === '') {
      $this->setOutput(implode("\n", [
        'No classes were given, so there is nothing to check.',
        'Pass the class string you intend to use, for example "utilikit uk-pd--16px".',
      ]));

      return;
    }

    $verdicts = $this->validator->checkAll($classes);
    $bad = array_values(array_filter($verdicts, static fn($v): bool => !$v->valid));
    $lines = ['Checked ' . count($verdicts) . ' class(es):', ''];

    foreach ($verdicts as $verdict) {
      $lines[] = $verdict->line();
    }

    $lines[] = '';

    // The marker gets its own line whatever else happened, because it is the
    // failure people do not think to look for.
    if (!$this->validator->hasMarker($classes)) {
      $lines[] = 'MISSING the "utilikit" class. UtiliKit only styles elements that carry it, '
        . 'so none of the above will apply as written. Use: utilikit ' . $classes;
    }
    else {
      $lines[] = 'The "utilikit" marker class is present, so these will be processed.';
    }

    $lines[] = '';
    $unsure = array_values(array_filter($verdicts, static fn($v): bool => $v->valid && $v->caveat !== ''));

    if ($bad !== []) {
      $lines[] = sprintf('%d class(es) will produce no CSS. Fix those before using this string.', count($bad));
    }
    elseif ($unsure !== []) {
      // Not "every class is valid". Saying that of a class whose value was
      // never checked is the false pass this tool exists to prevent.
      $lines[] = sprintf(
        'No class was rejected, but %d could not be checked in full — see UNSURE above.',
        count($unsure),
      );
    }
    else {
      $lines[] = 'Every class is valid on this site.';
    }

    $this->setOutput(implode("\n", $lines));
  }

}
