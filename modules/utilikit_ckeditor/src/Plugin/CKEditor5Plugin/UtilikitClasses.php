<?php

declare(strict_types=1);

namespace Drupal\utilikit_ckeditor\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\editor\EditorInterface;
use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\utilikit\Service\UtilikitRules;

/**
 * Feeds the editor the utilities THIS SITE accepts.
 *
 * WHY THIS CLASS EXISTS AT ALL. Core's Style plugin can already put a class on
 * an element, so the obvious question is why UtiliKit needs its own. Style
 * works from a list someone typed into the text format settings: every class it
 * can ever apply has to be enumerated in advance. UtiliKit's classes are
 * composed — `uk-pd--16px`, `uk-pd--t-40px`, `uk-md-bg--3b82f6` — so the useful
 * set is effectively unbounded and enumerating it is not possible.
 *
 * So the editor is given the RULES instead of a list of classes, and composes
 * against them: which prefixes exist here, what each accepts, and the keyword
 * values where a rule takes keywords.
 *
 * AND THE RULES COME FROM ONE PLACE. UtilikitRules is the single declaration
 * the whole module reads — the CSS generator, the JavaScript engine, and now
 * the editor. That was worth a drift test in utilikit itself; this class must
 * not become a fourth copy.
 *
 * @internal
 *   Plugin classes are internal.
 */
class UtilikitClasses extends CKEditor5PluginDefault {

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $static_plugin_config
   *   The plugin's static configuration.
   * @param \Drupal\editor\EditorInterface $editor
   *   The text editor this configuration is for.
   *
   * @return array<string, mixed>
   *   The plugin configuration, with this site's utilities added.
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $rules = UtilikitRules::getRules();
    $keywords = UtilikitRules::getAllowedKeywords();
    $shorthand = UtilikitRules::getKeywordShorthand();

    $utilities = [];

    foreach ($rules as $prefix => $rule) {
      $css = is_array($rule) && isset($rule['css']) ? (string) $rule['css'] : '';

      $utilities[] = [
        'prefix' => (string) $prefix,
        'css' => $css,
        'group' => is_array($rule) && isset($rule['group']) ? (string) $rule['group'] : 'Other',
        // The editor needs to know WHAT to ask for, and these flags are what
        // distinguish "type a length" from "pick one of these words".
        'keywords' => array_values($keywords[$css] ?? []),
        'shorthand' => $shorthand[$css] ?? [],
        'sides' => (bool) ($rule['sides'] ?? FALSE),
        'allowAuto' => (bool) ($rule['allowAuto'] ?? FALSE),
        'isColor' => (bool) ($rule['isColor'] ?? FALSE),
        'isInteger' => (bool) ($rule['isInteger'] ?? FALSE),
        'isNumericFlexible' => (bool) ($rule['isNumericFlexible'] ?? FALSE),
      ];
    }

    return [
      'utilikit' => [
        'utilities' => $utilities,
        'breakpoints' => array_keys(UtilikitConstants::DEFAULT_BREAKPOINTS),
        // WITHOUT THIS CLASS NOTHING THE EDITOR APPLIES DOES ANYTHING. The
        // engine only styles elements carrying the marker, so the plugin adds
        // it alongside whatever the person picked, exactly as
        // utilikit_ai's writer does.
        'marker' => UtilikitConstants::MARKER_CLASS,
      ],
    ] + $static_plugin_config;
  }

}
