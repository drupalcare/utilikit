<?php

declare(strict_types=1);

namespace Drupal\utilikit\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorableConfigBase;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\utilikit\Service\UtilikitServiceProvider;
use Drupal\utilikit\Traits\FormHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for UtiliKit module settings.
 *
 * Provides administrative interface for configuring rendering mode,
 * performance settings, scope controls, cache management, and developer
 * tools for the UtiliKit utility class system.
 *
 * @phpstan-consistent-constructor
 *   Drupal's create() factory returns `new static()`. PHPStan treats that as
 *   unsafe on a non-final class because a subclass could declare a different
 *   constructor. This class's constructor is the contract subclasses inherit,
 *   which is what the annotation asserts.
 */
class UtilikitSettingsForm extends ConfigFormBase {

  use FormHelperTrait;

  /**
   * Constructs a new UtilikitSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   * @param \Drupal\utilikit\Service\UtilikitServiceProvider $serviceProvider
   *   The UtiliKit service provider.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list, used for the header's purpose line.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    // NOT READONLY, and that is forced rather than stylistic.
    // DependencySerializationTrait re-injects these from the container on wake,
    // and __wakeup() lives in the PARENT class — PHP before 8.4 forbids writing
    // a child's readonly property from there, so the object comes back broken.
    // Reported by PHPStan on drupal.org as
    // dependencySerializationTraitProperty.unsupportedReadOnlyProperty, and
    // invisible on this machine twice over: the local PHPStan is 1.x, which
    // does not report it, and PHP here is 8.5, where the restriction is lifted.
    // The bug is real on every site running 8.3.
    //
    // Third instance of this in the project — the same fix landed in
    // paragraphs_bundles and vvj_core on 2026-09-05.
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DateFormatterInterface $dateFormatter,
    protected ModuleHandlerInterface $moduleHandler,
    protected UtilikitServiceProvider $serviceProvider,
    protected FileSystemInterface $fileSystem,
    protected ModuleExtensionList $moduleExtensionList,
  ) {
    parent::__construct($configFactory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('module_handler'),
      $container->get('utilikit.service_provider'),
      $container->get('file_system'),
      $container->get('extension.list.module')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'utilikit_settings_form';
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return list<string>
   */
  protected function getEditableConfigNames(): array {
    return ['utilikit.settings'];
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   *
   * @phpstan-return array<string, mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Attach the CSS library.
    $form['#attached']['library'][] = 'utilikit/utilikit-settings';
    $form['#attributes']['class'][] = 'utilikit-settings';
    // Vary form cache by user so developer tools render per permission.
    $form['#cache']['contexts'][] = 'user';
    $config = $this->config('utilikit.settings');

    // Sections are ordered by weight to follow the job: how CSS is delivered,
    // where it loads, what is scanned, the output, updates, upkeep, tools.
    $form = array_merge($form, $this->buildHeaderSection($config));
    $form = array_merge($form, $this->buildRenderingModeSection($config));
    $form = array_merge($form, $this->buildScopeSection($config));
    $form = array_merge($form, $this->buildScanningScopeSection($config));
    $form = array_merge($form, $this->buildPerformanceSection($config));
    $form = array_merge($form, $this->buildTriggersSection($config));
    $form = array_merge($form, $this->buildCacheSection($config));
    if ($this->currentUser()->hasPermission('administer utilikit')) {
      $form = array_merge($form, $this->buildDeveloperSection($config));
    }
    $weights = [
      'uk_header' => -100,
      'delivery' => 10,
      'scope' => 20,
      'scanning_scope' => 30,
      'performance' => 40,
      'triggers' => 50,
      'cache' => 60,
      'developer' => 70,
    ];
    foreach ($weights as $key => $weight) {
      if (isset($form[$key])) {
        $form[$key]['#weight'] = $weight;
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Builds a details title: a short title plus a one-line hint.
   *
   * @param string|\Stringable $title
   *   The section title.
   * @param string|\Stringable $hint
   *   What the section is for, in a few words.
   * @param string|\Stringable|null $tag
   *   An optional small tag shown after the hint.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   Escaped markup for a details #title.
   */
  private function sectionTitle(string|\Stringable $title, string|\Stringable $hint, string|\Stringable|null $tag = NULL): MarkupInterface {
    $markup = '<span class="utilikit-settings__head"><span class="utilikit-settings__title">@title</span><span class="utilikit-settings__hint">@hint</span>';
    $args = ['@title' => (string) $title, '@hint' => (string) $hint];
    if ($tag !== NULL) {
      $markup .= '<span class="utilikit-settings__tag">@tag</span>';
      $args['@tag'] = (string) $tag;
    }
    $markup .= '</span>';
    return new FormattableMarkup($markup, $args);
  }

  /**
   * Builds the header band: name, purpose and live status.
   *
   * @param \Drupal\Core\Config\StorableConfigBase $config
   *   The current UtiliKit configuration.
   *
   * @return array<string, mixed>
   *   A render-only container; it holds no form value.
   */
  private function buildHeaderSection(StorableConfigBase $config): array {
    $mode = $config->get('rendering_mode') ?? 'inline';
    $mode_labels = [
      'inline' => $this->t('Inline'),
      'static' => $this->t('Static'),
      'head' => $this->t('Head'),
    ];
    $info = $this->moduleExtensionList->getExtensionInfo('utilikit');
    $stateManager = $this->serviceProvider->getStateManager();
    $class_count = count($stateManager->getKnownClasses());

    $chips = [
      'mode' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['utilikit-settings__chip']],
        'label' => ['#markup' => '<span>' . $this->t('Rendering mode') . '</span>'],
        'value' => ['#markup' => '<b>' . ($mode_labels[$mode] ?? $mode) . '</b>'],
      ],
      'classes' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['utilikit-settings__chip']],
        'label' => ['#markup' => '<span>' . $this->t('Tracked classes') . '</span>'],
        'value' => ['#markup' => '<b>' . number_format($class_count) . '</b>'],
      ],
    ];

    // Generated CSS facts, moved here from the Developer tools section. Same
    // server-side mode check as before: only Static and Head generate CSS.
    if ($mode === 'static' || $mode === 'head') {
      $css_timestamp = $stateManager->getCssTimestamp();
      $file_time = $css_timestamp
        ? $this->dateFormatter->format($css_timestamp, 'custom', 'j M Y - g:i A T')
        : $this->t('Unknown');
      $css_size = NULL;
      $css_link = NULL;

      if ($mode === 'head') {
        $css = $stateManager->getGeneratedCss();
        $css_size = !empty($css) ? round(strlen($css) / 1024, 1) . ' KB' : '0 KB';
      }
      else {
        $fileManager = $this->serviceProvider->getFileManager();
        $css_file_uri = UtilikitConstants::CSS_DIRECTORY . '/' . UtilikitConstants::CSS_FILENAME;
        $css_real_path = $this->fileSystem->realpath($css_file_uri);
        if ($css_real_path && file_exists($css_real_path)) {
          $css_size = round(filesize($css_real_path) / 1024, 1) . ' KB';
          $css_link = $fileManager->getStaticCssUrl();
        }
      }

      if ($css_size !== NULL) {
        $chip = [
          '#type' => 'container',
          '#attributes' => ['class' => ['utilikit-settings__chip']],
          'label' => ['#markup' => '<span>' . $this->t('Generated CSS, updated @time', ['@time' => $file_time]) . '</span>'],
          'value' => ['#markup' => '<b>' . $css_size . '</b>'],
        ];
        if ($css_link !== NULL) {
          $chip['link'] = [
            '#type' => 'inline_template',
            '#template' => '<a href="{{ url }}" target="_blank" rel="noopener">{{ text }}</a>',
            '#context' => [
              'url' => $css_link,
              'text' => $this->t('View generated CSS file'),
            ],
          ];
        }
      }
      else {
        $chip = [
          '#type' => 'container',
          '#attributes' => ['class' => ['utilikit-settings__chip']],
          'label' => ['#markup' => '<span>' . $this->t('Generated CSS') . '</span>'],
          'value' => ['#markup' => '<b>' . $this->t('No CSS file found') . '</b>'],
          'hint' => ['#markup' => '<span>' . $this->t('Save this form to generate the CSS file, or add UtiliKit classes to your content.') . '</span>'],
        ];
      }
      $chips['css'] = $chip;
    }

    return [
      'uk_header' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['utilikit-settings__band']],
        'brand' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['utilikit-settings__brand']],
          'name' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $this->t('UtiliKit'),
            '#attributes' => ['class' => ['utilikit-settings__name']],
          ],
          'purpose' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => (string) ($info['description'] ?? ''),
            '#attributes' => ['class' => ['utilikit-settings__purpose']],
          ],
        ],
        'status' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['utilikit-settings__status'],
            'role' => 'group',
            'aria-label' => $this->t('Current status'),
          ],
        ] + $chips,
      ],
    ];
  }

  /**
   * Builds the rendering mode configuration section.
   *
   * @param \Drupal\Core\Config\StorableConfigBase $config
   *   The current UtiliKit configuration.
   *
   * @return array<string, mixed>
   *   Form elements for rendering mode selection.
   */
  private function buildRenderingModeSection(StorableConfigBase $config): array {
    return [
      'delivery' => [
        '#type' => 'details',
        '#title' => $this->sectionTitle($this->t('Rendering mode'), $this->t('How CSS reaches the page')),
        '#open' => TRUE,
        '#attributes' => ['class' => ['uk-step']],
        'rendering_mode' => [
          '#type' => 'radios',
          '#title' => $this->t('Rendering mode'),
          '#title_display' => 'invisible',
          '#options' => [
            'inline' => $this->t('Inline'),
            'static' => $this->t('Static'),
            'head' => $this->t('Head'),
          ],
          '#default_value' => $config->get('rendering_mode') ?? 'inline',
          '#attributes' => ['class' => ['utilikit-settings__modes']],
          '#after_build' => [[static::class, 'afterBuildModes']],
        ],
      ],
    ];
  }

  /**
   * Adds the per-mode description and card classes to the mode radios.
   *
   * @param array<string, mixed> $element
   *   The expanded radios element.
   *
   * @return array<string, mixed>
   *   The element with a description and card class on each option.
   *
   * @phpstan-param array<string, mixed> $element
   * @phpstan-return array<string, mixed>
   */
  public static function afterBuildModes(array $element): array {
    $descriptions = [
      'inline' => t('JavaScript builds the CSS in the browser on each page.'),
      'static' => t('A CSS file on disk, refreshed on save or with the Update button.'),
      'head' => t('The server writes the CSS into the page head; no file needed.'),
    ];
    foreach ($descriptions as $key => $description) {
      if (isset($element[$key])) {
        $element[$key]['#description'] = $description;
        // Core links a description to its input while building the child,
        // which ran before this #after_build; link it here so screen
        // readers announce it.
        $element[$key]['#attributes']['aria-describedby'] = $element[$key]['#id'] . '--description';
        $element[$key]['#wrapper_attributes']['class'][] = 'utilikit-settings__mode';
        $element[$key]['#wrapper_attributes']['class'][] = 'utilikit-settings__mode--' . $key;
      }
    }
    return $element;
  }

  /**
   * Builds the performance configuration section.
   *
   * @param \Drupal\Core\Config\StorableConfigBase $config
   *   The current UtiliKit configuration.
   *
   * @return array<string, mixed>
   *   Form elements for performance and optimization settings.
   */
  private function buildPerformanceSection(StorableConfigBase $config): array {
    $fileStates = [
      'visible' => [
        [':input[name="rendering_mode"]' => ['value' => 'static']],
        [':input[name="rendering_mode"]' => ['value' => 'head']],
      ],
    ];

    return [
      'performance' => [
        '#type' => 'details',
        '#title' => $this->sectionTitle($this->t('CSS output'), $this->t('Breakpoints, file and runtime')),
        '#open' => TRUE,
        '#attributes' => ['class' => ['uk-step']],

        'uk_breakpoints' => [
          '#type' => 'fieldset',
          '#title' => $this->t('Active breakpoints'),
          '#attributes' => ['class' => ['uk-group', 'uk-group--tune']],
          'active_breakpoints' => $this->createCheckboxesField(
            $this->t('Active breakpoints'),
            $this->t('Select which responsive breakpoints to enable. Disabling unused breakpoints can improve performance.'),
            $this->getBreakpointOptions(),
            $config->get('active_breakpoints') ?? UtilikitConstants::DEFAULT_BREAKPOINTS,
            ['attributes' => ['class' => ['utilikit-settings__bps']]]
          ) + ['#title_display' => 'invisible'],
        ],

        'uk_file' => [
          '#type' => 'fieldset',
          '#title' => $this->t('Generated CSS'),
          '#description' => $this->t('Static and Head modes only.'),
          '#attributes' => ['class' => ['uk-group', 'uk-group--code']],
          '#states' => $fileStates,
          'optimize_css' => $this->createCheckboxField(
            $this->t('Optimize CSS output'),
            $this->t('Minify and optimize the generated CSS to reduce size.'),
            (bool) ($config->get('optimize_css') ?? TRUE),
            ['states' => $fileStates]
          ),
          'use_important' => $this->createCheckboxField(
            $this->t('Use <code>!important</code> in CSS rules'),
            $this->t('Add !important to all utility class rules to ensure they override inline styles or css variables. Recommended for utility-first workflows. <strong>Note:</strong> Changing this setting will regenerate all site CSS.'),
            (bool) ($config->get('use_important') ?? TRUE),
            ['states' => $fileStates]
          ),
        ],

        'uk_runtime' => [
          '#type' => 'fieldset',
          '#title' => $this->t('In the browser'),
          '#attributes' => ['class' => ['uk-group', 'uk-group--eye']],
          'enable_transitions' => $this->createCheckboxField(
            $this->t('Enable smooth transitions'),
            $this->t('Apply CSS transitions when styles change dynamically (recommended for better UX).'),
            (bool) ($config->get('enable_transitions') ?? TRUE)
          ),
          'debounce' => $this->createNumberField(
            $this->t('Resize debounce delay'),
            $this->t('Milliseconds to wait before reapplying styles on window resize.'),
            $config->get('debounce') ?? 50,
            [
              'min' => 0,
              'max' => 500,
              'step' => 10,
              'field_suffix' => $this->t('ms'),
            ]
          ),
        ],
      ],
    ];
  }

  /**
   * Builds the scope configuration section.
   *
   * @param \Drupal\Core\Config\StorableConfigBase $config
   *   The current UtiliKit configuration.
   *
   * @return array<string, mixed>
   *   Form elements for scope control settings.
   */
  private function buildScopeSection(StorableConfigBase $config): array {
    return [
      'scope' => [
        '#type' => 'details',
        '#title' => $this->sectionTitle($this->t('Where it loads'), $this->t('Pages and content types')),
        '#open' => TRUE,
        '#attributes' => ['class' => ['uk-step']],
        'scope_global' => $this->createCheckboxField(
          $this->t('Enable globally on all pages'),
          $this->t('Load UtiliKit across your entire site.'),
          (bool) ($config->get('scope_global') ?? TRUE)
        ),
        'disable_admin' => $this->createCheckboxField(
          $this->t('Disable on admin pages'),
          $this->t('Prevent UtiliKit from loading on administrative routes. Playground, Examples and Test Suite always load.'),
          (bool) ($config->get('disable_admin') ?? FALSE),
          ['states' => ['visible' => [':input[name="scope_global"]' => ['checked' => TRUE]]]]
        ),
        'scope_content_types' => $this->createCheckboxField(
          $this->t('Limit to specific content types'),
          $this->t('Only load UtiliKit on selected content types. Available when "Enable globally on all pages" is off.'),
          (bool) ($config->get('scope_content_types') ?? FALSE),
          ['states' => ['disabled' => [':input[name="scope_global"]' => ['checked' => TRUE]]]]
        ),
        'enabled_content_types' => $this->buildContentTypesField($config),
      ],
    ];
  }

  /**
   * Builds the content types field with options.
   *
   * @param \Drupal\Core\Config\StorableConfigBase $config
   *   The current UtiliKit configuration.
   *
   * @return array<string, mixed>
   *   Form elements for content types selection.
   */
  private function buildContentTypesField(StorableConfigBase $config): array {
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $options = [];
    foreach ($node_types as $node_type) {
      $options[$node_type->id()] = $node_type->label();
    }

    return $this->createCheckboxesField(
      $this->t('Enabled content types'),
      '',
      $options,
      $config->get('enabled_content_types') ?? [],
      [
        'states' => [
          'visible' => [
            ':input[name="scope_content_types"]' => ['checked' => TRUE],
            ':input[name="scope_global"]' => ['checked' => FALSE],
          ],
          'required' => [':input[name="scope_content_types"]' => ['checked' => TRUE]],
        ],
        'prefix' => '<div class="form-item-indent">',
        'suffix' => '</div>',
      ]
    );
  }

  /**
   * Builds the triggers configuration section.
   *
   * @param \Drupal\Core\Config\StorableConfigBase $config
   *   The current UtiliKit configuration.
   *
   * @return array<string, mixed>
   *   Form elements for automatic update triggers in static mode.
   */
  private function buildTriggersSection(StorableConfigBase $config): array {
    $description = $this->t('On: saving content adds its new classes to the CSS. Off: use the Update UtiliKit button. Inline mode never needs this.');

    return [
      'triggers' => [
        '#type' => 'details',
        '#title' => $this->sectionTitle($this->t('Automatic updates'), $this->t('Refresh the CSS when content is saved'), $this->t('Static & Head')),
        '#description' => $description,
        '#open' => FALSE,
        '#attributes' => ['class' => ['uk-step']],
        '#states' => [
          'visible' => [
            [':input[name="rendering_mode"]' => ['value' => 'static']],
            [':input[name="rendering_mode"]' => ['value' => 'head']],
          ],
        ],
        'update_on_node_save' => $this->createCheckboxField(
          $this->t('Update when saving nodes'),
          '',
          (bool) ($config->get('update_on_node_save') ?? FALSE)
        ),
        'update_on_block_save' => $this->createCheckboxField(
          $this->t('Update when saving custom blocks'),
          '',
          (bool) ($config->get('update_on_block_save') ?? FALSE)
        ),
        'update_on_paragraph_save' => $this->createCheckboxField(
          $this->t('Update when saving paragraphs'),
          '',
          (bool) ($config->get('update_on_paragraph_save') ?? FALSE)
        ),
      ],
    ];
  }

  /**
   * Builds the cache management section.
   *
   * @param \Drupal\Core\Config\StorableConfigBase $config
   *   The current UtiliKit configuration.
   *
   * @return array<string, mixed>
   *   Form elements for cache management and test CSS generation.
   */
  private function buildCacheSection(StorableConfigBase $config): array {
    $knownClasses = $this->serviceProvider->getStateManager()->getKnownClasses();

    $cacheSection = [
      'cache' => [
        '#type' => 'details',
        '#title' => $this->sectionTitle($this->t('Maintenance'), $this->t('Reset and test CSS')),
        '#open' => FALSE,
        '#attributes' => ['class' => ['uk-step']],
        'uk_danger' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['utilikit-settings__danger']],
          'consequence' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['utilikit-settings__danger-text']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('Reset all CSS'),
            ],
            'text' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#value' => $this->t('Forgets all @count tracked classes, deletes the generated and test CSS files, and sets the mode back to Inline. Global loading, admin-page and transition settings are reset too; other settings are kept.', [
                '@count' => number_format(count($knownClasses)),
              ]),
            ],
          ],
          'clear_css_cache' => $this->createSubmitButton(
            $this->t('Reset all CSS'),
            ['::clearCssCache'],
            ['button--danger'],
            // Asked by js/utilikit.settings-confirm.js; inline handlers are
            // blocked by a strict Content Security Policy.
            ['data-utilikit-confirm' => $this->t('This will remove all tracked CSS classes. Are you sure?')]
          ),
        ],
      ],
    ];

    // Hint for regenerating the CSS file; only Static and Head have one.
    $rendering_mode = $config->get('rendering_mode') ?? 'inline';
    if ($rendering_mode === 'static' || $rendering_mode === 'head') {
      $cacheSection['cache']['regenerate_note'] = [
        '#markup' => '<p class="utilikit-settings__note">' .
        $this->t('To regenerate the CSS file, use the "Reset all CSS" button above, then save this form.') .
        '</p>',
      ];
    }

    // Check if utilikit_test module is enabled.
    if ($this->moduleHandler->moduleExists('utilikit_test')) {
      $testFileInfo = $this->serviceProvider->getTestCssGenerator()->getTestCssFileInfo();

      // Only show file info and delete button if file exists.
      if ($testFileInfo['exists'] && $config->get('dev_mode')) {
        // Add file info and delete button.
        $cacheSection['cache']['test_css_delete'] = [
          '#type' => 'container',
          '#states' => [
            'visible' => [
              ':input[name="dev_mode"]' => ['checked' => TRUE],
            ],
          ],
          'info' => [
            '#markup' => $this->t('<p class="messages messages--warning">Test CSS file exists: <a href="@url" target="_blank">utilikit-test-complete.css</a> (@size)</p>', [
              '@url' => $testFileInfo['url'],
              '@size' => (string) ByteSizeMarkup::create((int) $testFileInfo['size']),
            ]),
          ],
          'button' => $this->createSubmitButton(
            $this->t('Delete Test CSS'),
            ['::deleteTestCss'],
            ['button--danger', 'button--small'],
            ['title' => $this->t('Delete the test CSS file')]
          ),
        ];
      }

      // Generate button (keep existing code)
      $cacheSection['cache']['generate_test_css'] = $this->createSubmitButton(
        $this->t('Generate Complete Test CSS'),
        ['::generateTestCss'],
        ['button--primary'],
        ['title' => $this->t('Generate a CSS file with all possible utility class combinations for testing')]
      );

      // Add state to only show in dev mode.
      $cacheSection['cache']['generate_test_css']['#states'] = [
        'visible' => [
          ':input[name="dev_mode"]' => ['checked' => TRUE],
        ],
      ];
    }
    elseif ($config->get('dev_mode')) {
      // Show hint in dev mode if test module not enabled.
      $cacheSection['cache']['test_module_hint'] = [
        '#markup' => $this->t('<p class="description">Enable the UtiliKit Test module to generate complete test CSS.</p>'),
      ];
    }

    return $cacheSection;
  }

  /**
   * Deletes the test CSS file.
   *
   * @param array<string, mixed> $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function deleteTestCss(array &$form, FormStateInterface $form_state): void {
    try {
      $generator = $this->serviceProvider->getTestCssGenerator();
      $result = $generator->deleteTestCssFile();

      if ($result) {
        $this->messenger()->addStatus($this->t('Test CSS files deleted successfully.'));
        $this->getLogger('utilikit')->notice('Test CSS files deleted by user.');
      }
      else {
        $this->messenger()->addError($this->t('Failed to delete one or more test CSS files.'));
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to delete test CSS files: @error', [
        '@error' => $e->getMessage(),
      ]));
    }

    $form_state->setRebuild(TRUE);
  }

  /**
   * Generates complete test CSS file.
   *
   * @param array<string, mixed> $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function generateTestCss(array &$form, FormStateInterface $form_state): void {
    // Double-check module is enabled.
    if (!$this->moduleHandler->moduleExists('utilikit_test')) {
      $this->messenger()->addError($this->t('UtiliKit Test module must be enabled to generate test CSS.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    try {
      /** @var \Drupal\utilikit\Service\UtilikitTestCssGeneratorInterface|null $generator */
      $generator = $this->serviceProvider->getTestCssGenerator();

      // getTestCssGenerator() is nullable and the module check above is not
      // enough on its own: the module can be enabled while the service is
      // still unavailable — during install, or if the container has not been
      // rebuilt since. Calling a method on NULL here would be a fatal on a
      // button a site builder can press.
      if ($generator === NULL) {
        $this->messenger()->addError($this->t('The test CSS generator is not available. Rebuild caches and try again.'));
        $form_state->setRebuild(TRUE);
        return;
      }

      $result = $generator->generateCompleteTestCss();

      if (!empty($result['success'])) {
        $this->messenger()->addStatus($this->t('Generated complete test CSS with @count classes. File size: @size', [
          '@count' => $result['count'],
          '@size' => (string) ByteSizeMarkup::create((int) $result['size']),
        ]));

        if (!empty($result['file_url'])) {
          $this->messenger()->addStatus($this->t('<a href="@url" target="_blank">View Test CSS File</a>', [
            '@url' => $result['file_url'],
          ]));
        }
      }
      else {
        $this->messenger()->addError($this->t('Failed to generate test CSS: @error', [
          '@error' => $result['error'] ?? 'Unknown error',
        ]));
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error generating test CSS: @error', [
        '@error' => $e->getMessage(),
      ]));
    }

    $form_state->setRebuild(TRUE);
  }

  /**
   * Builds the scanning scope section.
   *
   * @phpstan-return array<string, mixed>
   */
  private function buildScanningScopeSection(StorableConfigBase $config): array {
    $entity_types = $this->entityTypeManager->getDefinitions();
    $options = [];
    $counts = [];

    $labels = [];
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if ($entity_type->entityClassImplements(FieldableEntityInterface::class)) {
        $count = $this->getEntityCount($entity_type_id);
        $labels[$entity_type_id] = $entity_type->getLabel() . ' (' . number_format($count) . ' entities)';
        $counts[$entity_type_id] = $count;
      }
    }

    // Sort on the plain label text, then wrap the count so it can be styled.
    asort($labels);
    foreach ($labels as $entity_type_id => $label) {
      $options[$entity_type_id] = new FormattableMarkup('@name <span class="utilikit-settings__count">(@count entities)</span>', [
        '@name' => (string) $entity_types[$entity_type_id]->getLabel(),
        '@count' => number_format($counts[$entity_type_id]),
      ]);
    }

    $total_selected = 0;
    $selected_types = $config->get('scanning_entity_types') ?? ['node', 'block_content', 'paragraph'];
    foreach ($selected_types as $type) {
      if (isset($counts[$type])) {
        $total_selected += $counts[$type];
      }
    }

    return [
      'scanning_scope' => [
        '#type' => 'details',
        '#title' => $this->sectionTitle($this->t('What is scanned'), $this->t('Entity types searched for classes')),
        '#description' => $this->t('Select which entity types to scan for UtiliKit utility classes. Scanning only relevant types improves performance during CSS generation.'),
        '#open' => TRUE,
        '#attributes' => ['class' => ['uk-step']],

        'scanning_entity_types' => [
          '#type' => 'checkboxes',
          '#title' => $this->t('Entity types to scan'),
          '#options' => $options,
          '#default_value' => $selected_types,
          '#attributes' => ['class' => ['utilikit-settings__entities']],
          '#description' => $this->t('Total selected: @count entities', ['@count' => number_format($total_selected)]),
        ],
      ],
    ];
  }

  /**
   * Gets entity count for an entity type.
   */
  private function getEntityCount(string $entity_type_id): int {
    try {
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $query = $storage->getQuery()->accessCheck(FALSE);
      return (int) $query->count()->execute();
    }
    catch (\Exception $e) {
      return 0;
    }
  }

  /**
   * Builds the developer tools section.
   *
   * @param \Drupal\Core\Config\StorableConfigBase $config
   *   The current UtiliKit configuration.
   *
   * @return array<string, mixed>
   *   Form elements for developer and debugging options.
   */
  private function buildDeveloperSection(StorableConfigBase $config): array {
    $developerSection = [
      'developer' => [
        '#type' => 'details',
        '#title' => $this->sectionTitle($this->t('Developer tools'), $this->t('Debug output for site builders')),
        '#open' => FALSE,
        '#attributes' => ['class' => ['uk-step']],
        'dev_mode' => $this->createCheckboxField(
          $this->t('Enable developer mode'),
          $this->t('Output debug information to browser console.'),
          (bool) ($config->get('dev_mode') ?? FALSE)
        ),
        'admin_preview' => $this->createCheckboxField(
          $this->t('Visual debugging'),
          $this->t('Show outlines around UtiliKit elements for debugging.'),
          (bool) ($config->get('admin_preview') ?? FALSE)
        ),
        'show_page_errors' => $this->createCheckboxField(
          $this->t('Display errors on page'),
          $this->t('Show validation errors as Drupal messages (requires developer mode). Inline mode only.'),
          (bool) ($config->get('show_page_errors') ?? FALSE),
          [
            'states' => [
              'visible' => [':input[name="dev_mode"]' => ['checked' => TRUE]],
              'disabled' => [':input[name="dev_mode"]' => ['checked' => FALSE]],
            ],
          ]
        ),
        'log_level' => $this->createSelectField(
          $this->t('Console logging level'),
          $this->t('Controls browser console output detail. <strong>Note:</strong> Most detailed logs are generated in inline mode during class processing. Static mode shows minimal logging since CSS is pre-generated.'),
          [
            'off' => $this->t('Off'),
            'warnings' => $this->t('Warnings only'),
            'detailed' => $this->t('Detailed (verbose)'),
          ],
          $config->get('log_level') ?? 'off',
          ['states' => ['visible' => [':input[name="dev_mode"]' => ['checked' => TRUE]]]]
        ),
      ],
    ];

    return $developerSection;
  }

  /**
   * Gets available breakpoint options for configuration.
   *
   * @return array<string, mixed>
   *   Array of breakpoint options with labels.
   */
  private function getBreakpointOptions(): array {
    $options = [];
    foreach (['sm' => 576, 'md' => 768, 'lg' => 992, 'xl' => 1200, 'xxl' => 1400] as $key => $min) {
      $options[$key] = new FormattableMarkup('<span class="utilikit-settings__bp-name">@name</span><span class="utilikit-settings__bp-from">@from</span>', [
        '@name' => strtoupper($key),
        '@from' => $this->t('@minpx+', ['@min' => $min]),
      ]);
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    if ($this->currentUser()->hasPermission('administer utilikit')) {
      $this->validateDeveloperModeSettings($form_state);
    }
  }

  /**
   * Validates developer mode related settings.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  private function validateDeveloperModeSettings(FormStateInterface $form_state): void {
    $devMode = $form_state->getValue('dev_mode');
    $showPageErrors = $form_state->getValue('show_page_errors');

    if ($showPageErrors && !$devMode) {
      $this->messenger()->addWarning($this->t('Show page errors requires developer mode to be enabled.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $oldConfig = $this->config('utilikit.settings');
    $oldMode = $oldConfig->get('rendering_mode') ?? 'inline';
    $oldUseImportant = (bool) ($oldConfig->get('use_important') ?? TRUE);
    $oldOptimizeCss = (bool) ($oldConfig->get('optimize_css') ?? TRUE);
    $newMode = $form_state->getValue('rendering_mode');
    $newUseImportant = (bool) $form_state->getValue('use_important');
    $newOptimizeCss = (bool) $form_state->getValue('optimize_css');

    $this->saveAllSettings($form_state);

    // Clear all caches first — this must happen before any file generation
    // because clearAllCaches() -> cssOptimizer->deleteAll() wipes the
    // public://css/ directory and would delete our generated CSS file.
    $this->serviceProvider->getCacheManager()->clearAllCaches();

    $this->handleModeSwitch($oldMode, $newMode);

    // Regenerate CSS if use_important or optimize_css changed (and mode
    // didn't change, since mode switch already handles regeneration).
    if ($oldMode === $newMode && ($oldUseImportant !== $newUseImportant || $oldOptimizeCss !== $newOptimizeCss)) {
      $this->serviceProvider->regenerateStaticCss();
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Saves all form settings to configuration.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  private function saveAllSettings(FormStateInterface $form_state): void {
    $isAdmin = $this->currentUser()->hasPermission('administer utilikit');

    // Developer tool settings require 'administer utilikit' permission.
    $developerSettings = ['dev_mode', 'show_page_errors', 'admin_preview', 'log_level'];
    $generalSettings = [
      'enable_transitions', 'rendering_mode', 'scope_global', 'disable_admin',
      'scope_content_types', 'update_on_node_save', 'update_on_block_save',
      'update_on_paragraph_save', 'optimize_css', 'use_important',
    ];

    $settingsMap = $isAdmin
      ? array_merge($developerSettings, $generalSettings)
      : $generalSettings;

    $config = $this->config('utilikit.settings');
    foreach ($settingsMap as $setting) {
      $config->set($setting, $form_state->getValue($setting));
    }

    // Validate debounce range (0-500ms, matching form UI constraints).
    $debounce = (int) $form_state->getValue('debounce');
    $debounce = max(0, min(500, $debounce));
    $config->set('debounce', $debounce);

    // Validate breakpoints against allowed values.
    $breakpoints = array_filter($form_state->getValue('active_breakpoints'));
    $validBreakpoints = ['sm', 'md', 'lg', 'xl', 'xxl'];
    $breakpoints = array_intersect($breakpoints, $validBreakpoints);
    $config->set('active_breakpoints', $breakpoints);

    $config->set('scanning_entity_types', array_values(array_filter($form_state->getValue('scanning_entity_types'))));
    $config->set('enabled_content_types', array_filter($form_state->getValue('enabled_content_types')));
    $config->save();
  }

  /**
   * Handles rendering mode switches between inline and static modes.
   *
   * @param string $oldMode
   *   The previous rendering mode.
   * @param string $newMode
   *   The new rendering mode.
   */
  private function handleModeSwitch(string $oldMode, string $newMode): void {
    if ($oldMode !== $newMode) {
      if ($newMode === 'static') {
        $this->switchToStaticMode();
      }
      elseif ($newMode === 'head') {
        $this->switchToHeadMode();
      }
      else {
        $this->switchToInlineMode();
      }
    }
    elseif ($newMode === 'static') {
      $this->ensureStaticCssExists();
    }
  }

  /**
   * Switches to static rendering mode and generates CSS.
   */
  private function switchToStaticMode(): void {
    $result = $this->serviceProvider->switchToStaticMode();

    if ($result['classes_count'] > 0) {
      $this->messenger()->addStatus($this->t('Switched to static mode. Generated CSS for @count classes from @scanned entities.', [
        '@count' => $result['classes_count'],
        '@scanned' => $result['scanned_count'],
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Switched to static mode. No existing classes found in @scanned entities - CSS will be generated as you add utility classes.', [
        '@scanned' => $result['scanned_count'],
      ]));
    }

    $this->messenger()->addStatus($this->t('All caches cleared. Static mode is now active.'));
  }

  /**
   * Switches to inline rendering mode and cleans up static files.
   */
  private function switchToInlineMode(): void {
    $classCount = $this->serviceProvider->switchToInlineMode();

    $this->messenger()->addStatus($this->t('Switched to inline mode. Dynamic processing enabled. Tracking @count classes.', [
      '@count' => $classCount,
    ]));

    $this->messenger()->addStatus($this->t('All caches cleared. Inline mode is now active.'));
  }

  /**
   * Switches to head rendering mode and ensures classes are scanned.
   */
  private function switchToHeadMode(): void {
    $this->serviceProvider->getFileManager()->cleanupStaticFiles();

    $stateManager = $this->serviceProvider->getStateManager();
    $knownClasses = $stateManager->getKnownClasses();

    if (empty($knownClasses)) {
      $scanResult = $this->serviceProvider->getContentScanner()->scanAllContent();
      $knownClasses = $scanResult['classes'];

      if (!empty($knownClasses)) {
        $stateManager->setKnownClasses($knownClasses);
        $this->messenger()->addStatus($this->t('Scanned content and found @count utility classes.', [
          '@count' => count($knownClasses),
        ]));
      }
    }

    $this->serviceProvider->getCacheManager()->clearAllCaches();

    $this->messenger()->addStatus($this->t('Switched to head mode. CSS will be generated in page head. Tracking @count classes.', [
      '@count' => count($knownClasses),
    ]));
  }

  /**
   * Ensures static CSS file exists when in static mode.
   */
  private function ensureStaticCssExists(): void {
    $css_url = $this->serviceProvider->getFileManager()->getStaticCssUrl();

    if (!$css_url) {
      $result = $this->serviceProvider->regenerateStaticCss();

      if ($result) {
        $knownClasses = $this->serviceProvider->getStateManager()->getKnownClasses();
        $this->messenger()->addStatus($this->t('Regenerated static CSS file with @count classes.', [
          '@count' => count($knownClasses),
        ]));
      }
    }
  }

  /**
   * Form submission handler for clearing CSS cache and resetting everything.
   *
   * @param array<string, mixed> $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function clearCssCache(array &$form, FormStateInterface $form_state): void {
    // Clear all UtiliKit data.
    $this->serviceProvider->getStateManager()->clearUtilikitData();

    // Clean up all CSS files (static and test).
    $this->serviceProvider->getFileManager()->cleanupStaticFiles();

    // Also clean test CSS if exists.
    $testCssFile = UtilikitConstants::CSS_DIRECTORY . '/' . UtilikitConstants::TEST_CSS_FILENAME;
    $testCssPath = $this->fileSystem->realpath($testCssFile);
    if ($testCssPath && file_exists($testCssPath)) {
      try {
        $this->fileSystem->delete($testCssFile);
      }
      catch (\Exception $e) {
        $this->getLogger('utilikit')->warning('Could not delete test CSS file: @error', [
          '@error' => $e->getMessage(),
        ]);
      }
    }

    // Reset CSS-related config to safe defaults; preserve other settings.
    $config = $this->configFactory()->getEditable('utilikit.settings');
    $config
      ->set('rendering_mode', 'inline')
      ->set('scope_global', TRUE)
      // Same values as config/install/utilikit.settings.yml.
      ->set('disable_admin', FALSE)
      ->set('enable_transitions', TRUE)
      ->save();

    // Clear all relevant Drupal caches.
    $this->serviceProvider->getCacheManager()->clearAllCaches();

    // Add success message.
    $this->messenger()->addStatus($this->t('All UtiliKit settings, caches, and files have been reset to defaults. Starting completely fresh.'));

    // Force redirect with query parameter to ensure fresh page load.
    $form_state->setRedirect('utilikit.settings', [], ['query' => ['reset' => time()]]);
  }

}
