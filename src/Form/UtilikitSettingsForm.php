<?php

declare(strict_types=1);

namespace Drupal\utilikit\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorableConfigBase;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
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
      $container->get('file_system')
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
   */
  protected function getEditableConfigNames(): array {
    return ['utilikit.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Attach the CSS library.
    $form['#attached']['library'][] = 'utilikit/utilikit-settings';
    // Vary form cache by user so developer tools render per permission.
    $form['#cache']['contexts'][] = 'user';
    $config = $this->config('utilikit.settings');

    $form = array_merge($form, $this->buildRenderingModeSection($config));
    $form = array_merge($form, $this->buildScanningScopeSection($config));
    $form = array_merge($form, $this->buildPerformanceSection($config));
    $form = array_merge($form, $this->buildScopeSection($config));
    $form = array_merge($form, $this->buildTriggersSection($config));
    $form = array_merge($form, $this->buildCacheSection($config));
    if ($this->currentUser()->hasPermission('administer utilikit')) {
      $form = array_merge($form, $this->buildDeveloperSection($config));
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Builds the rendering mode configuration section.
   *
   * @param \Drupal\Core\Config\StorableConfigBase $config
   *   The current UtiliKit configuration.
   *
   * @return array
   *   Form elements for rendering mode selection.
   */
  private function buildRenderingModeSection(StorableConfigBase $config): array {
    return [
      'rendering_mode' => [
        '#type' => 'radios',
        '#title' => $this->t('Rendering Mode'),
        '#options' => [
          'inline' => $this->t('<strong>Inline Mode</strong> - JavaScript processes all utility classes dynamically on page load'),
          'static' => $this->t('<strong>Static Mode</strong> - Pre-generated CSS file that must be updated either automatically (on save) or manually (update button) when adding new classes'),
          'head' => $this->t('<strong>Head Mode</strong> - Server-generated CSS injected into page head on each request (no file system required)'),
        ],
        '#default_value' => $config->get('rendering_mode') ?? 'inline',
      ],
    ];
  }

  /**
   * Builds the performance configuration section.
   *
   * @param \Drupal\Core\Config\StorableConfigBase $config
   *   The current UtiliKit configuration.
   *
   * @return array
   *   Form elements for performance and optimization settings.
   */
  private function buildPerformanceSection(StorableConfigBase $config): array {
    return [
      'performance' => [
        '#type' => 'details',
        '#title' => $this->t('Performance & Optimization'),
        '#open' => TRUE,

        'active_breakpoints' => $this->createCheckboxesField(
          $this->t('Active breakpoints'),
          $this->t('Select which responsive breakpoints to enable. Disabling unused breakpoints can improve performance.'),
          $this->getBreakpointOptions(),
          $config->get('active_breakpoints') ?? UtilikitConstants::DEFAULT_BREAKPOINTS
        ),

        'optimize_css' => $this->createCheckboxField(
          $this->t('Optimize CSS output'),
          $this->t('Minify and optimize the generated CSS to reduce size.'),
          (bool) ($config->get('optimize_css') ?? TRUE),
          [
            'states' => [
              'visible' => [
                [':input[name="rendering_mode"]' => ['value' => 'static']],
                [':input[name="rendering_mode"]' => ['value' => 'head']],
              ],
            ],
          ]
        ),

        'use_important' => $this->createCheckboxField(
          $this->t('Use !important in CSS rules'),
          $this->t('Add !important to all utility class rules to ensure they override inline styles or css variables. Recommended for utility-first workflows. <strong>Note:</strong> Changing this setting will regenerate all site CSS.'),
          (bool) ($config->get('use_important') ?? TRUE),
          [
            'states' => [
              'visible' => [
                [':input[name="rendering_mode"]' => ['value' => 'static']],
                [':input[name="rendering_mode"]' => ['value' => 'head']],
              ],
            ],
          ]
        ),
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
            'attributes' => ['style' => 'width: 100px'],
          ]
        ),
      ],
    ];
  }

  /**
   * Builds the scope configuration section.
   *
   * @param \Drupal\Core\Config\StorableConfigBase $config
   *   The current UtiliKit configuration.
   *
   * @return array
   *   Form elements for scope control settings.
   */
  private function buildScopeSection(StorableConfigBase $config): array {
    return [
      'scope' => [
        '#type' => 'details',
        '#title' => $this->t('Scope & Visibility'),
        '#open' => TRUE,
        'scope_global' => $this->createCheckboxField(
          $this->t('Enable globally on all pages'),
          $this->t('Load UtiliKit across your entire site.'),
          (bool) ($config->get('scope_global') ?? TRUE)
        ),
        'disable_admin' => $this->createCheckboxField(
          $this->t('Disable on admin pages'),
          $this->t('Prevent UtiliKit from loading on administrative routes. This does not affect UtiliKit development tools (Playground, Examples, Test Suite) which always run independently.'),
          (bool) ($config->get('disable_admin') ?? FALSE),
          ['states' => ['visible' => [':input[name="scope_global"]' => ['checked' => TRUE]]]]
        ),
        'scope_content_types' => $this->createCheckboxField(
          $this->t('Limit to specific content types'),
          $this->t('Only load UtiliKit on selected content types.'),
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
   * @return array
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
   * @return array
   *   Form elements for automatic update triggers in static mode.
   */
  private function buildTriggersSection(StorableConfigBase $config): array {
    $description = $this->t('<div class="messages messages--info"><strong>How automatic updates work in Static & Head Modes:</strong><br>@enabled_text<br>@disabled_text<br><br><em>@note_text</em></div>', [
      '@enabled_text' => '• Enabled: When you save content with new utility classes, they are automatically added to the generated CSS.',
      '@disabled_text' => '• Disabled: When you save content, new classes are NOT added to the generated CSS. You must click the "Update UtiliKit" button to scan for and add them.',
      '@note_text' => 'Note: This setting only applies to Static & Head Modes. In Inline Mode, all classes are processed dynamically without needing updates.',
    ]);

    return [
      'triggers' => [
        '#type' => 'details',
        '#title' => $this->t('Automatic CSS Updates (Static & Head Modes)'),
        '#description' => $description,
        '#open' => FALSE,
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
   * @return array
   *   Form elements for cache management and test CSS generation.
   */
  private function buildCacheSection(StorableConfigBase $config): array {
    $knownClasses = $this->serviceProvider->getStateManager()->getKnownClasses();

    $cacheSection = [
      'cache' => [
        '#type' => 'details',
        '#title' => $this->t('Cache Management'),
        '#open' => FALSE,
        'info' => [
          '#markup' => '<p>' . $this->t('Currently tracking <strong>@count</strong> utility classes.', [
            '@count' => count($knownClasses),
          ]) . '</p>',
        ],

        'clear_css_cache' => $this->createSubmitButton(
          $this->t('Clear All CSS & Reset'),
          ['::clearCssCache'],
          ['button--danger'],
          ['onclick' => $this->t('return confirm("This will remove all tracked CSS classes. Are you sure?");')]
        ),

      ],
    ];

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
   * @param array $form
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
   * @param array $form
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
   */
  private function buildScanningScopeSection(StorableConfigBase $config): array {
    $entity_types = $this->entityTypeManager->getDefinitions();
    $options = [];
    $counts = [];

    foreach ($entity_types as $entity_type_id => $entity_type) {
      if ($entity_type->entityClassImplements(FieldableEntityInterface::class)) {
        $count = $this->getEntityCount($entity_type_id);
        $options[$entity_type_id] = $entity_type->getLabel() . ' (' . number_format($count) . ' entities)';
        $counts[$entity_type_id] = $count;
      }
    }

    asort($options);

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
        '#title' => $this->t('Content Scanning Scope'),
        '#description' => $this->t('Select which entity types to scan for UtiliKit utility classes. Scanning only relevant types improves performance during CSS generation.'),
        '#open' => TRUE,

        'scanning_entity_types' => [
          '#type' => 'checkboxes',
          '#title' => $this->t('Entity types to scan'),
          '#options' => $options,
          '#default_value' => $selected_types,
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
   * @return array
   *   Form elements for developer and debugging options.
   */
  private function buildDeveloperSection(StorableConfigBase $config): array {
    $developerSection = [
      'developer' => [
        '#type' => 'details',
        '#title' => $this->t('Developer Tools'),
        '#open' => FALSE,
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
          $this->t('Show validation errors as Drupal messages (requires developer mode). <strong>Note:</strong> Only works in inline mode - static mode uses pre-generated CSS and does not validate classes in real-time.'),
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
          $config->get('log_level') ?? 'warnings',
          ['states' => ['visible' => [':input[name="dev_mode"]' => ['checked' => TRUE]]]]
        ),
      ],
    ];

    // Only show the Generated CSS fieldset for static and head modes.
    // Using server-side check instead of #states because the content
    // is already rendered server-side per mode — #states would only
    // toggle visibility while leaving mismatched content in the DOM.
    $rendering_mode = $config->get('rendering_mode') ?? 'inline';

    if ($rendering_mode === 'static' || $rendering_mode === 'head') {
      $known_classes = $this->serviceProvider->getStateManager()->getKnownClasses();
      $class_count = count($known_classes);
      $css_timestamp = $this->serviceProvider->getStateManager()->getCssTimestamp();
      $file_time = $css_timestamp
        ? $this->dateFormatter->format($css_timestamp, 'custom', 'j M Y - g:i A T')
        : $this->t('Unknown');

      $developerSection['developer']['static_css_info'] = [
        '#type' => 'fieldset',
        '#title' => $rendering_mode === 'head'
          ? $this->t('Generated CSS (Head Mode)')
          : $this->t('Generated CSS (Static Mode)'),
      ];

      if ($rendering_mode === 'head') {
        $css = $this->serviceProvider->getStateManager()->getGeneratedCss();
        $css_size = !empty($css) ? round(strlen($css) / 1024, 1) . ' KB' : '0 KB';

        $developerSection['developer']['static_css_info']['file_details'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['messages', 'messages--status']],
          'title' => [
            '#markup' => '<strong>' . $this->t('CSS Generated (Head Mode)') . '</strong>',
          ],
          'description' => [
            '#markup' => '<br>' . $this->t('CSS is injected in page head on each request'),
          ],
          'stats' => [
            '#markup' => '<br><small>' . $this->t('Classes: @count | Size: @size | Last Updated: @time', [
              '@count' => $class_count,
              '@size' => $css_size,
              '@time' => $file_time,
            ]) . '</small>',
          ],
        ];
      }
      else {
        $fileManager = $this->serviceProvider->getFileManager();
        $css_url = $fileManager->getStaticCssUrl();

        // Check if static CSS file exists.
        $css_file_uri = UtilikitConstants::CSS_DIRECTORY . '/' . UtilikitConstants::CSS_FILENAME;
        $css_real_path = $this->fileSystem->realpath($css_file_uri);

        if ($css_real_path && file_exists($css_real_path)) {
          $file_size = round(filesize($css_real_path) / 1024, 1) . ' KB';
          $css_url = $fileManager->getStaticCssUrl();

          $developerSection['developer']['static_css_info']['file_details'] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['messages', 'messages--status']],
            'title' => [
              '#markup' => '<strong>' . $this->t('CSS File Generated') . '</strong>',
            ],
            'link' => [
              '#markup' => '<br><a href="' . htmlspecialchars($css_url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . $this->t('View Generated CSS File') . '</a>',
            ],
            'stats' => [
              '#markup' => '<br><small>' . $this->t('Classes: @count | Size: @size | Last Updated: @time', [
                '@count' => $class_count,
                '@size' => $file_size,
                '@time' => $file_time,
              ]) . '</small>',
            ],
          ];
        }
        else {
          $developerSection['developer']['static_css_info']['no_file'] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['messages', 'messages--warning']],
            'message' => [
              '#markup' => $this->t('No CSS file found. Save this form to generate the CSS file, or add UtiliKit classes to your content.'),
            ],
          ];
        }
      }

      $developerSection['developer']['static_css_info']['regenerate_note'] = [
        '#markup' => '<p><small>' .
        $this->t('To regenerate the CSS file, use the "Clear All CSS & Reset" button in the Cache Management section above, then save this form.') .
        '</small></p>',
      ];
    }

    return $developerSection;
  }

  /**
   * Gets available breakpoint options for configuration.
   *
   * @return array
   *   Array of breakpoint options with labels.
   */
  private function getBreakpointOptions(): array {
    return [
      'sm' => $this->t('Small (≥576px)'),
      'md' => $this->t('Medium (≥768px)'),
      'lg' => $this->t('Large (≥992px)'),
      'xl' => $this->t('Extra Large (≥1200px)'),
      'xxl' => $this->t('Extra Extra Large (≥1400px)'),
    ];
  }

  /**
   * {@inheritdoc}
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
   * @param array $form
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
      ->set('disable_admin', TRUE)
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
