<?php

declare(strict_types=1);

namespace Drupal\utilikit_ckeditor\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\editor\EditorInterface;
use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\utilikit\Service\UtilikitRules;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
class UtilikitClasses extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  /**
   * The pages the panel's footer may link to, in the order shown.
   *
   * Each lives in a different module, two of them optional submodules, so a
   * link is sent only when its route exists here AND the current user may
   * open it — never a link to a 404 or a 403.
   */
  private const HELP_LINKS = [
    'utilikit_playground.page',
    'utilikit_help.documentation',
    'utilikit.reference',
  ];

  /**
   * Constructs the plugin.
   *
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Routing\RouteProviderInterface $routeProvider
   *   The route provider, to ask whether an optional page exists.
   * @param \Drupal\Core\Access\AccessManagerInterface $accessManager
   *   The access manager, to ask whether the user may open it.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The user the editor is being built for.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    CKEditor5PluginDefinition $plugin_definition,
    private readonly RouteProviderInterface $routeProvider,
    private readonly AccessManagerInterface $accessManager,
    private readonly AccountInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    // Core's plugin convention; a subclass overriding the constructor owns
    // create() too.
    // @phpstan-ignore new.static
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('router.route_provider'),
      $container->get('access_manager'),
      $container->get('current_user'),
    );
  }

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
      if (!is_array($rule)) {
        continue;
      }
      $css = isset($rule['css']) ? (string) $rule['css'] : '';

      $utilities[] = [
        'prefix' => (string) $prefix,
        'css' => $css,
        // The plain-words name and the group both come from the rule itself,
        // so the panel's list is this site's rule list, in its own order,
        // with nothing typed a second time here.
        'label' => isset($rule['label']) ? (string) $rule['label'] : (string) $prefix,
        'group' => isset($rule['group']) ? (string) $rule['group'] : 'Other',
        // The editor needs to know WHAT to ask for, and these flags are what
        // distinguish "type a length" from "pick one of these words".
        // `isKeyword` decides that, not a non-empty keyword list: `order` has
        // a keyword list in getAllowedKeywords() and is an integer rule.
        'isKeyword' => (bool) ($rule['isKeyword'] ?? FALSE),
        'keywords' => array_values($keywords[$css] ?? []),
        'shorthand' => $shorthand[$css] ?? [],
        'sides' => (bool) ($rule['sides'] ?? FALSE),
        'allowAuto' => (bool) ($rule['allowAuto'] ?? FALSE),
        'isColor' => (bool) ($rule['isColor'] ?? FALSE),
        'isInteger' => (bool) ($rule['isInteger'] ?? FALSE),
        'isOpacity' => (bool) ($rule['isOpacity'] ?? FALSE),
        'isDecimalFixed' => (bool) ($rule['isDecimalFixed'] ?? FALSE),
        'isNumericFlexible' => (bool) ($rule['isNumericFlexible'] ?? FALSE),
        'isRange' => (bool) ($rule['isRange'] ?? FALSE),
        'isGridTrackList' => (bool) ($rule['isGridTrackList'] ?? FALSE),
        'isTransform' => isset($rule['isTransform']) ? (string) $rule['isTransform'] : '',
      ];
    }

    $breakpoints = array_keys(UtilikitConstants::DEFAULT_BREAKPOINTS);
    $widths = [];
    foreach ($breakpoints as $breakpoint) {
      $widths[$breakpoint] = UtilikitConstants::BREAKPOINT_VALUES[$breakpoint] ?? NULL;
    }

    return [
      'utilikit' => [
        'utilities' => $utilities,
        'breakpoints' => $breakpoints,
        // Minimum widths in px, so the panel can say "768px and wider"
        // instead of asking an editor what `md` means.
        'breakpointWidths' => $widths,
        // The engine's own test for a well-formed class. The panel refuses to
        // write anything that fails it — a value with a space in it would
        // otherwise smuggle a second class onto the element.
        'classPattern' => UtilikitConstants::CLASS_VALIDATION_PATTERN,
        // WITHOUT THIS CLASS NOTHING THE EDITOR APPLIES DOES ANYTHING. The
        // engine only styles elements carrying the marker, so the plugin adds
        // it alongside whatever the person picked, exactly as
        // utilikit_ai's writer does.
        'marker' => UtilikitConstants::MARKER_CLASS,
        // Small links under the panel. The panel opens them in a new tab so
        // unsaved editor content is never lost.
        'links' => $this->helpLinks(),
      ],
    ] + $static_plugin_config;
  }

  /**
   * The footer links this user can actually open on this site.
   *
   * CACHEABILITY. The result depends on the user's permissions, and the
   * editor's settings travel inside the page's drupalSettings, so a cached
   * page shared across users would carry the wrong links. Measured 2026-10-08
   * on Drupal 11.4 (node edit form, admin): Dynamic Page Cache reports it
   * UNCACHEABLE, max-age 0, with `user.permissions` among its contexts — the
   * settings are built per request. Either way the access check only ever
   * REMOVES a link; it never grants a page.
   *
   * @return list<array{label: string, url: string}>
   *   One entry per link, in display order.
   */
  private function helpLinks(): array {
    // Literal t() calls, so the labels reach the translation extractor.
    $labels = [
      'utilikit_playground.page' => $this->t('Playground'),
      'utilikit_help.documentation' => $this->t('Help'),
      'utilikit.reference' => $this->t('Reference'),
    ];
    // getRoutesByNames() returns only the routes that exist, and does not
    // throw for the others — a disabled submodule simply has none.
    $existing = $this->routeProvider->getRoutesByNames(self::HELP_LINKS);

    $links = [];
    foreach (self::HELP_LINKS as $name) {
      if (!isset($existing[$name])) {
        continue;
      }
      if (!$this->accessManager->checkNamedRoute($name, [], $this->currentUser)) {
        continue;
      }
      $links[] = [
        'label' => (string) $labels[$name],
        'url' => Url::fromRoute($name)->toString(TRUE)->getGeneratedUrl(),
      ];
    }
    return $links;
  }

}
