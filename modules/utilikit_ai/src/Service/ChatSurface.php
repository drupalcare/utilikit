<?php

declare(strict_types=1);

namespace Drupal\utilikit_ai\Service;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\utilikit_ai\Model\SurfaceDecision;

/**
 * Decides whether the node-form chat bar appears, and builds it.
 *
 * WHY THIS IS A SERVICE AND NOT A HOOK BODY. Six preconditions gate the bar,
 * none of them visible from the node form, and a hook that returned early six
 * times would be untestable without building a whole form. Here each refusal
 * is one assertion.
 *
 * WHY NO ASSISTANT IS SHIPPED. It would be the obvious convenience, and it
 * cannot work: an `ai_assistant` entity requires an `llm_provider` and an
 * `llm_model`, both naming something specific to one site. A shipped default
 * would install an assistant pointing at a provider that may not exist — a
 * thing that appears configured and fails on first use, which is worse than an
 * empty setting that says what to do.
 *
 * IT NEVER WRITES CONFIGURATION. Placing this bar does not alter the node form
 * display, the block layout, the text formats, or the assistant. The only
 * configuration it reads is its own setting, and the only thing that writes
 * that setting is a person on the settings form.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
final class ChatSurface implements ChatSurfaceInterface {

  /**
   * The chat block shipped by ai_chatbot.
   */
  private const string BLOCK_ID = 'ai_deepchat_block';

  /**
   * The chat processor that routes a conversation to an assistant.
   *
   * Supplied by ai_assistant_api, whose presence is checked before this is
   * used. The block will not render without one.
   */
  private const string PROCESSOR_ID = 'ai_assistant_api_processor';

  /**
   * Constructs the surface.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Reads which assistant the site chose.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Checks for ai_chatbot, ai_assistant_api and node, none of which is a
   *   dependency of this module.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Confirms the chosen assistant still exists, and labels content types.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Finds the content types that have somewhere to write.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Checked for the permission this module adds.
   * @param \Drupal\Core\Block\BlockManagerInterface $blockManager
   *   Builds the chat block.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly AccountInterface $currentUser,
    private readonly BlockManagerInterface $blockManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function decide(?string $contentType = NULL): SurfaceDecision {
    // Permission first. Someone without it does not need to hear about the
    // site's AI configuration, and checking it last would leak that anyway
    // through the reason text.
    if (!$this->currentUser->hasPermission(self::PERMISSION)) {
      return SurfaceDecision::hide(
        'You do not have the "use the UtiliKit assistant" permission.',
      );
    }

    if (!$this->moduleHandler->moduleExists('ai_chatbot')) {
      return SurfaceDecision::hide(
        'The AI Chatbot module (ai_chatbot, part of drupal/ai) is not enabled, so there is no chat bar to show.',
      );
    }

    if (!$this->moduleHandler->moduleExists('ai_assistant_api')) {
      return SurfaceDecision::hide(
        'The AI Assistant API module (ai_assistant_api, part of drupal/ai) is not enabled, so there is no assistant to talk to.',
      );
    }

    // Config values are mixed. Casting one is an assumption; this is a check.
    $setting = $this->configFactory
      ->get('utilikit_ai.settings')
      ->get('assistant');
    $chosen = is_string($setting) ? trim($setting) : '';

    if ($chosen === '') {
      return SurfaceDecision::hide(
        'No assistant has been chosen yet. Create one at Configuration › AI › AI Assistants, then choose it on this page.',
      );
    }

    // An assistant that was chosen and later deleted is the case that would
    // otherwise fail as a broken block rather than as a hidden one.
    //
    // AND LOADING IT CAN THROW, which is not defensive theatre — it has been
    // measured TWICE in this project, on two different properties, which is
    // why the catch is broad rather than aimed at one field:
    //
    //   - 2026-09-04: an assistant whose `description` was null could not be
    //     constructed at all.
    //   - 2026-09-04: an assistant saved without `llm_configuration` stored
    //     null, and every later load threw `Cannot assign null to property
    //     AiAssistant::$llm_configuration of type array`. The entity became
    //     permanently unloadable through the entity API and had to be repaired
    //     through the config factory.
    //
    // The contrib entity types these properties as non-nullable, so
    // EntityBase's constructor raises a TypeError before the storage can
    // return NULL. Config written by an older version, a partial import, a
    // programmatic save that omits a key, or another module's half-finished
    // save all produce it — and the second case shows how ordinary that is: it
    // happened while simply creating an assistant.
    //
    // Unhandled, that TypeError does not merely hide this bar — it breaks
    // EVERY node add and edit form on the site, because this runs from
    // form_alter. A surface whose entire promise is "absent unless wanted"
    // must not be able to take the node form down with it. So the throw is
    // caught HERE, around this one statement, and turned into the same visible
    // refusal as every other missing precondition. It is never swallowed: the
    // message goes into the reason the settings page prints.
    try {
      $exists = $this->entityTypeManager
        ->getStorage('ai_assistant')
        ->load($chosen);
    }
    catch (\Throwable $error) {
      return SurfaceDecision::hide(sprintf(
        'The chosen assistant "%s" is stored but cannot be loaded (%s). Its configuration is damaged — re-save it, or choose another one.',
        $chosen,
        $error->getMessage(),
      ));
    }

    if ($exists === NULL) {
      return SurfaceDecision::hide(sprintf(
        'The chosen assistant "%s" no longer exists. Choose another one.',
        $chosen,
      ));
    }

    // A content type with no formatted-text field has nowhere for the writer
    // to put anything. Offering the assistant there invites a conversation
    // whose only possible outcome is a refusal — the tools would work
    // correctly and the person would still have wasted their time. Checked
    // last because it is the only precondition that depends on WHICH form is
    // being built.
    if ($contentType !== NULL && $contentType !== '') {
      $usable = $this->writableContentTypes();

      if (!isset($usable[$contentType])) {
        return SurfaceDecision::hide(sprintf(
          'The content type "%s" has no formatted-text field, so there is nowhere here for the assistant to add classes. It appears on: %s.',
          $contentType,
          $usable === [] ? 'no content type on this site' : implode(', ', array_keys($usable)),
        ));
      }
    }

    return SurfaceDecision::show($chosen);
  }

  /**
   * {@inheritdoc}
   */
  public function writableContentTypes(): array {
    // The node module is not a dependency of this one — the tools declare it
    // as a per-plugin module_dependency instead, so this module installs on a
    // site without it and simply offers nothing. Asking the field manager
    // about an entity type that does not exist throws, so it is not asked.
    if (!$this->moduleHandler->moduleExists('node')) {
      return [];
    }

    $out = [];

    foreach ($this->entityTypeManager->getStorage('node_type')->loadMultiple() as $id => $type) {
      $bundle = (string) $id;

      foreach ($this->entityFieldManager->getFieldDefinitions('node', $bundle) as $definition) {
        if (in_array($definition->getType(), ClassApplierInterface::RICH_TEXT_FIELD_TYPES, TRUE)) {
          $out[$bundle] = (string) $type->label();
          break;
        }
      }
    }

    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function build(?string $contentType = NULL): array {
    $decision = $this->decide($contentType);

    if (!$decision->show) {
      return [];
    }

    // FOUR THINGS HERE ARE NOT OBVIOUS, all four measured on sts.local rather
    // than reasoned about, and every one of them failed silently.
    //
    // 1. The assistant is chosen through the chat PROCESSOR's `assistant_id`,
    //    not through a top-level key on the block. The block's own
    //    `ai_assistant` key exists but only feeds an ajax callback on its
    //    configuration form; setting it alone selects nothing.
    // 2. `chat_processor_plugin` has to be named explicitly. The block's
    //    defaultConfiguration() leaves it an empty string and its build()
    //    passes that straight to createInstance(), so the block's own defaults
    //    throw PluginNotFoundException on render.
    // 3. `placement` defaults to 'toolbar', which styles the widget for
    //    Drupal's toolbar and rendered 679px tall inline. 'bottom-right'
    //    floats it instead.
    // 4. `toggle_state` is set here for intent, and on this version of
    //    ai_chatbot it does NOTHING at this placement — measured 2026-09-04,
    //    not assumed. DeepChatFormBlock::build() does attach it to
    //    drupalSettings.ai_deepchat.toggle_state, but the only JS that reads a
    //    toggle_state is form-stream.js, which reads the OTHER block's
    //    drupalSettings.ai_chatbot. The sticky widget used for every
    //    non-toolbar placement initialises purely from
    //    localStorage.deepChatState_<chatId>, defaulting to closed when unset.
    //    So a first-time visitor gets a collapsed bubble, which is what is
    //    wanted; 'close' is kept because it states the intent and will take
    //    effect if contrib wires it up. Do not read it as a working control.
    //
    //    AND THE KEY IS NOT NAMESPACED: it is `deepChatState_bot1` for every
    //    deepchat block on the site, so opening a sibling module's bar leaves
    //    this one open too, and vice versa. That is a contrib limitation, not
    //    something this module can fix from here — worth knowing before
    //    treating an open bar as a bug in this code.
    $block = $this->blockManager->createInstance(self::BLOCK_ID, [
      'chat_processor_plugin' => self::PROCESSOR_ID,
      'plugin_configuration' => [
        'assistant_id' => $decision->assistant,
        'stream_output' => FALSE,
        'verbose_mode' => FALSE,
      ],
      'bot_name' => 'UtiliKit',
      'toggle_state' => 'close',
      'placement' => 'bottom-right',
      'collapse_minimal' => TRUE,
      'first_message' => 'Say what you want styled and how — the page, the elements, and the look. For example: "on node 12, give every paragraph 16px padding".',
    ]);

    // The manager's return is only `object` to a static analyser, and a block
    // plugin that is not one would fail at ->build() rather than here.
    if (!$block instanceof BlockPluginInterface) {
      return [];
    }

    return [
      '#type' => 'container',
      '#weight' => 100,
      '#attributes' => ['class' => ['utilikit-ai-chat']],
      'chat' => $block->build(),
      // The bar depends on a setting, an entity, the site's fields and the
      // user's permissions. Without these a cached form shows one person's
      // answer to another.
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => [
          'config:utilikit_ai.settings',
          'config:ai_assistant_api.ai_assistant.' . $decision->assistant,
          'entity_field_info',
        ],
      ],
    ];
  }

}
