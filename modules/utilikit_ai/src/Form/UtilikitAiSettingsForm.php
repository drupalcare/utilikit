<?php

declare(strict_types=1);

namespace Drupal\utilikit_ai\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\utilikit_ai\Service\ChatSurfaceInterface;
use Drupal\utilikit_ai\Service\SetupDoctorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * One setting, and an answer to "why is nothing showing".
 *
 * THE STATUS LINE IS THE REASON THIS PAGE EXISTS. Choosing an assistant is one
 * select; it would not need a page of its own. What needs a page is the six
 * invisible preconditions behind the chat bar, because when the bar does not
 * appear on a node form, the node form is the one place that cannot say which
 * one is missing.
 *
 * AND ONE WARNING THE SIBLING MODULES DO NOT NEED. UtiliKit's brief names the
 * failure that must not be repeated: a text format strips the `class`
 * attribute, so a class is applied, saved, and silently gone. The writer
 * refuses in that case rather than writing something that disappears — but a
 * refusal arrives mid-conversation, to one person, once. The formats are a
 * property of the site, so they are stated here as well, before anybody starts.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
final class UtilikitAiSettingsForm extends ConfigFormBase {

  /**
   * Constructs the form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\utilikit_ai\Service\ChatSurfaceInterface $surface
   *   Supplies the status line.
   * @param \Drupal\utilikit_ai\Service\SetupDoctorInterface $doctor
   *   Names the text formats that would strip the classes.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Lists the assistants to choose from.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Tells whether ai_assistant_api is present at all.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    // PROTECTED AND NOT READONLY. Both halves are forced, and the second was
    // learned the hard way in the sibling module.
    //
    // ConfigFormBase uses DependencySerializationTrait. The form is serialised
    // into the form cache, and on wake the trait re-injects these from the
    // container. That rules out BOTH:
    //
    //   - `private`, which the trait cannot restore at all (drupal.org
    //     #3110266) — the next submit fatals on an unset property;
    //   - `readonly`, which the trait cannot initialise either, because
    //     __wakeup() lives in a PARENT class and PHP before 8.4 forbids
    //     writing a child's readonly property from there.
    //
    // The second only surfaced on drupal.org CI, at PHPStan level 10, as
    // `dependencySerializationTraitProperty.unsupportedReadOnlyProperty`, and
    // it sat there unread because that job is allow_failure and the pipeline
    // showed green. Invisible locally twice over: PHPStan 1.x does not report
    // it, and this machine runs PHP 8.5, where the restriction is lifted — so
    // the bug is real on every site running 8.3 and cannot be reproduced here.
    protected ChatSurfaceInterface $surface,
    protected SetupDoctorInterface $doctor,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get(ChatSurfaceInterface::class),
      $container->get(SetupDoctorInterface::class),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'utilikit_ai_settings';
  }

  /**
   * {@inheritdoc}
   *
   * @return list<string>
   *   The editable config names.
   */
  protected function getEditableConfigNames(): array {
    return ['utilikit_ai.settings'];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The form.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $decision = $this->surface->decide();

    $form['status'] = [
      '#theme' => 'status_messages',
      '#message_list' => $decision->show
        ? ['status' => [$this->t('The chat bar is showing on node add and edit forms, using the assistant below.')]]
        : ['warning' => [$this->t('The chat bar is not showing. @reason', ['@reason' => $decision->reason])]],
    ];

    $options = $this->assistantOptions();

    $form['assistant'] = [
      '#type' => 'select',
      '#title' => $this->t('Assistant'),
      '#description' => $this->t('Which assistant answers on node forms. Assistants are created at Configuration › AI › AI Assistants; this module never creates or edits one, and never changes its settings.'),
      '#options' => ['' => $this->t('- Off -')] + $options,
      '#default_value' => $this->chosenAssistant(),
      '#disabled' => $options === [],
    ];

    if ($options === []) {
      $form['assistant']['#description'] = $this->moduleHandler->moduleExists('ai_assistant_api')
        ? $this->t('There are no assistants to choose from yet. Create one at Configuration › AI › AI Assistants first.')
        : $this->t('The AI Assistant API module is not enabled, so there are no assistants to choose from.');
    }

    $form['tools'] = [
      '#type' => 'item',
      '#title' => $this->t('Reaching the tools'),
      // The step people miss, and it costs an afternoon each time: an
      // assistant built with ai_assistant_api alone builds its tool list from
      // actions_enabled, which is a different plugin type. FunctionCall tools
      // like this module's reach an assistant only through ai_agents.
      //
      // The status line above cannot catch this one. Every precondition it
      // reports is on THIS side of the boundary; whether the chosen assistant
      // has an agent behind it is the assistant's own configuration, and a
      // chat that answers questions but silently changes nothing looks
      // identical to one that is working.
      '#markup' => $this->t('This module ships its tools as AI function calls. An assistant reaches them only through the AI Agents module: create an agent, enable the utilikit tools on it, and point the assistant at that agent. An assistant configured without an agent will answer questions but will never apply a class. This module ships a ready-made agent — utilikit_agent — which installs automatically when AI Agents is enabled before this module.'),
    ];

    $stripping = $this->doctor->formatsStrippingClasses();

    if ($stripping !== []) {
      $form['formats'] = [
        '#type' => 'item',
        '#title' => $this->t('Text formats that would strip the classes'),
        // Stated even though the writer refuses in this case, because the
        // refusal is a sentence one person sees once, mid-conversation. This
        // is a property of the site and belongs where the site is configured.
        '#markup' => $this->t('These formats run a filter that removes the class attribute, so a class applied to content using one of them would be silently discarded on save: @formats. The assistant refuses rather than writing something that disappears. To use it on that content, allow the class attribute on the format at Configuration › Content authoring › Text formats and editors.', [
          '@formats' => implode(', ', $stripping),
        ]),
      ];
    }

    // parent::buildForm() is typed as a bare `array`, so the value has to be
    // narrowed here for the stricter return type above to hold. PHPStan 2.x at
    // level 10 — the level drupal.org CI runs — requires it; level 9 does not.
    /** @var array<string, mixed> $built */
    $built = parent::buildForm($form, $form_state);

    return $built;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $chosen = $form_state->getValue('assistant');

    $this->config('utilikit_ai.settings')
      ->set('assistant', is_string($chosen) ? $chosen : '')
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * The assistant currently chosen, as a string.
   *
   * @return string
   *   The assistant id, or '' when the surface is off. Config values are
   *   mixed, and casting one is an assumption rather than a check.
   */
  private function chosenAssistant(): string {
    $chosen = $this->config('utilikit_ai.settings')->get('assistant');

    return is_string($chosen) ? $chosen : '';
  }

  /**
   * The assistants on this site, as select options.
   *
   * @return array<string, string>
   *   Assistant id => label. Empty when there are none, or when
   *   ai_assistant_api is not installed — the storage does not exist then, and
   *   asking for it would fatal rather than return nothing.
   */
  private function assistantOptions(): array {
    if (!$this->moduleHandler->moduleExists('ai_assistant_api')) {
      return [];
    }

    $out = [];

    foreach ($this->entityTypeManager->getStorage('ai_assistant')->loadMultiple() as $id => $assistant) {
      $out[(string) $id] = (string) $assistant->label();
    }

    return $out;
  }

}
