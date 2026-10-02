<?php

declare(strict_types=1);

namespace Drupal\utilikit_ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\utilikit_ai\Service\ClassApplierInterface;
use Drupal\utilikit_ai\Tool\SafeContextTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Applies UtiliKit classes to elements in a page's formatted text.
 *
 * THE ONLY TOOL HERE THAT WRITES. It adds a class attribute and nothing else:
 * no text is changed, no element removed, no field replaced.
 *
 * FIVE RULES IT CANNOT BE CONFIGURED OUT OF:
 *
 * 1. A NEW REVISION every time. Anything a machine changes in someone's
 *    content has to be reversible by someone who did not watch it happen.
 * 2. IT REFUSES WHERE THE TEXT FORMAT WOULD STRIP THE CLASS — measured on a
 *    real site, Basic HTML and Restricted HTML both do. Writing something
 *    silently discarded is worse than refusing, because it looks like it
 *    worked.
 * 3. ONE BAD CLASS REFUSES THE WHOLE REQUEST. A partial application leaves
 *    content in a state nobody asked for and nobody can see.
 * 4. THE `utilikit` MARKER IS ADDED AUTOMATICALLY. Without it the engine never
 *    looks at the element and every class on it is inert.
 * 5. EVERY PRECONDITION IS RE-CHECKED HERE, through the same validator and
 *    doctor the read-only tools use, because a model can skip those.
 *
 * @package Drupal\utilikit_ai\Plugin\AiFunctionCall
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[FunctionCall(
  id: 'utilikit:apply_classes',
  function_name: 'utilikit_apply_classes',
  name: 'Apply UtiliKit classes to a page',
  description: 'Adds UtiliKit utility classes to every element of one HTML tag inside a page\'s formatted text — for example, padding on every paragraph. It only ever adds a class attribute, always saves a new revision, adds the required "utilikit" marker for you, and refuses outright if any class is invalid or if the text format would strip the classes on save. Check with utilikit_check_classes first if you are unsure of a class.',
  group: 'utilikit',
  module_dependencies: ['utilikit', 'node'],
  context_definitions: [
    'nid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('The page'),
      description: 'The node id of the page to change.',
      required: FALSE,
    ),
    'tag' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('The element'),
      description: 'The HTML tag whose elements get the classes — p, div, h2, li, blockquote and so on. Every element with that tag in the field is changed.',
      required: FALSE,
    ),
    'classes' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('The classes'),
      description: 'The UtiliKit classes to add, space separated, for example "uk-pd--16px uk-bg--3b82f6". Do not include "utilikit" — it is added for you.',
      required: FALSE,
    ),
    'field' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('The field'),
      description: 'The formatted-text field to change. Omit to use the page\'s first one.',
      required: FALSE,
    ),
  ],
)]
final class ApplyClasses extends FunctionCallBase implements ExecutableFunctionCallInterface {

  use SafeContextTrait;

  /**
   * The only service here that writes.
   */
  protected ClassApplierInterface $applier;

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
    $instance->applier = $container->get(ClassApplierInterface::class);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $nid = $this->contextValue('nid');
    $result = $this->applier->apply(
      is_numeric($nid) ? (int) $nid : NULL,
      $this->string('field'),
      $this->string('tag'),
      $this->string('classes'),
    );

    if (($result['applied'] ?? FALSE) !== TRUE) {
      $this->setOutput(implode("\n", array_merge(
        ['Nothing was changed. These have to be settled first:', ''],
        array_map(static fn(string $r): string => '  - ' . $r, $result['refusals']),
        [
          '',
          'The page was not touched. Ask the person about all of the above together, then call this again.',
        ],
      )));

      return;
    }

    $this->setOutput(implode("\n", array_merge(
      [
        sprintf(
          'Updated "%s" — %d <%s> element(s) in the %s field.',
          $result['label'],
          $result['elements'],
          $this->string('tag'),
          $result['field'],
        ),
        '',
      ],
      array_map(static fn(string $n): string => '  - ' . $n, $result['notes']),
      [
        '',
        sprintf('See it at /node/%d, and the previous version under Revisions.', $result['nid']),
      ],
    )));
  }

  /**
   * A context value as a trimmed string.
   *
   * @param string $name
   *   The context name.
   *
   * @return string
   *   The value, or '' when the model omitted it.
   */
  private function string(string $name): string {
    $value = $this->contextValue($name);

    return is_string($value) ? trim($value) : '';
  }

}
