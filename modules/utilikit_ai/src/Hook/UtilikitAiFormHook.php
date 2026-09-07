<?php

declare(strict_types=1);

namespace Drupal\utilikit_ai\Hook;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Drupal\utilikit_ai\Service\ChatSurfaceInterface;

/**
 * Puts the chat bar on node add and edit forms.
 *
 * WHY THE NODE FORM. `apply_classes` writes into a node's formatted text and
 * takes a node id, so the node form is the one screen where the thing the
 * assistant can act on is already in front of the person. The playground was
 * considered and rejected in the brief for the opposite reason: it is entirely
 * client-side, so there is nothing there for a server-side tool to write into.
 *
 * WHY THIS ASKS ABOUT THE ENTITY AND NOT THE FORM CLASS. Testing
 * `instanceof \Drupal\node\NodeForm` is the obvious version and it is wrong —
 * that class does not exist; the real one is `\Drupal\node\Form\NodeForm`. PHP
 * does not complain about `instanceof` against an unknown class, it answers
 * FALSE, so the hook runs on every node form and adds nothing, with no error
 * anywhere. Measured in the sibling module 2026-09-04; the bar was simply
 * absent. Testing the ENTITY removes the whole class of mistake, and survives
 * a site substituting its own node form class.
 *
 * Matching form ids would be no better: `node_*_form` also catches
 * `node_type_edit_form` and anything else named that way.
 *
 * The hook adds a render element and nothing else. It does not touch the form
 * display, reorder fields, or change any setting — those are the site
 * builder's, and this is a guest on their form.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
final class UtilikitAiFormHook {

  /**
   * Constructs the hook.
   *
   * @param \Drupal\utilikit_ai\Service\ChatSurfaceInterface $surface
   *   Decides whether the bar appears, and builds it.
   */
  public function __construct(
    private readonly ChatSurfaceInterface $surface,
  ) {}

  /**
   * Appends the chat bar to node add and edit forms.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   * @param string $formId
   *   The form id.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $formState, string $formId): void {
    $object = $formState->getFormObject();

    if (!$object instanceof EntityFormInterface) {
      return;
    }

    if (!$object->getEntity() instanceof NodeInterface) {
      return;
    }

    // Add and edit only. Delete, and any other one-off operation a module
    // registers on nodes, get nothing: a confirmation form is not a place to
    // start a conversation about styling.
    if (!in_array($object->getOperation(), ['default', 'edit', 'add'], TRUE)) {
      return;
    }

    // The bundle decides whether the bar is any use here: a content type with
    // no formatted-text field can only ever produce a refusal.
    $entity = $object->getEntity();
    $bar = $this->surface->build($entity->bundle());

    if ($bar === []) {
      return;
    }

    $form['utilikit_ai_chat'] = $bar;
  }

}
