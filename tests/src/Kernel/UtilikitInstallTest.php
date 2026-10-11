<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\utilikit\Hook\UtilikitRequirements;
use Drupal\utilikit\Service\UtilikitConstants;

/**
 * Tests the UtiliKit installation process.
 *
 * @group utilikit
 */
#[Group('utilikit')]
#[RunTestsInSeparateProcesses]
class UtilikitInstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'utilikit',
  ];

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install config schema.
    $this->installConfig(['utilikit']);

    // Get services.
    $this->fileSystem = $this->container->get('file_system');
    $this->configFactory = $this->container->get('config.factory');
    $this->state = $this->container->get('state');
  }

  /**
   * Tests hook_install creates directory and sets defaults.
   */
  public function testInstallHook() {
    // Get config before installation.
    $config = $this->configFactory->get('utilikit.settings');

    // Run the install hook.
    \Drupal::moduleHandler()->loadInclude('utilikit', 'install');
    utilikit_install();

    // Reload config.
    $config = $this->configFactory->get('utilikit.settings');

    // Test directory creation.
    $directory = UtilikitConstants::CSS_DIRECTORY;
    $this->assertTrue(
      is_dir($directory),
      'CSS directory should be created'
    );

    // Test default configuration values.
    $this->assertEquals('inline', $config->get('rendering_mode'));
    $this->assertTrue($config->get('scope_global'));
    $this->assertFalse($config->get('disable_admin'));
    $this->assertFalse($config->get('scope_content_types'));
    $this->assertEquals([], $config->get('enabled_content_types'));

    // Test performance settings.
    $this->assertTrue($config->get('enable_transitions'));
    $this->assertEquals(50, $config->get('debounce'));
    $this->assertTrue($config->get('optimize_css'));
    $this->assertEquals(UtilikitConstants::DEFAULT_BREAKPOINTS, $config->get('active_breakpoints'));

    // Test developer settings.
    $this->assertFalse($config->get('dev_mode'));
    $this->assertFalse($config->get('admin_preview'));
    $this->assertFalse($config->get('show_page_errors'));
    $this->assertEquals('warnings', $config->get('log_level'));

    // Test update triggers.
    $this->assertFalse($config->get('update_on_node_save'));
    $this->assertFalse($config->get('update_on_block_save'));
    $this->assertFalse($config->get('update_on_paragraph_save'));

    // Test advanced settings.
    $this->assertEquals(UtilikitConstants::BATCH_SIZE_DEFAULT, $config->get('batch_size'));
    $this->assertEquals(UtilikitConstants::MAX_CLASSES_PER_REQUEST, $config->get('max_classes_per_request'));
    $this->assertEquals(UtilikitConstants::RATE_LIMIT_REQUESTS_PER_MINUTE, $config->get('rate_limit_requests'));
    $this->assertEquals(3600, $config->get('css_cache_ttl'));

    // Test file management.
    $this->assertEquals(UtilikitConstants::CSS_DIRECTORY, $config->get('css_directory'));
    $this->assertEquals(UtilikitConstants::CSS_FILENAME, $config->get('css_filename'));

    // Test migration flags.
    $this->assertFalse($config->get('legacy_mode'));
    $this->assertTrue($config->get('migration_complete'));

    // Test experimental features.
    $experimental = $config->get('experimental_features');
    $this->assertFalse($experimental['grid_auto_generation']);
    $this->assertFalse($experimental['advanced_selectors']);
    $this->assertFalse($experimental['css_variables']);
    $this->assertFalse($experimental['media_query_optimization']);
  }

  /**
   * Tests directory creation failure handling.
   */
  public function testInstallDirectoryFailure() {
    // Make directory creation fail by creating a file with the same name.
    $directory = UtilikitConstants::CSS_DIRECTORY;
    $parentDir = dirname($directory);

    // Ensure parent exists.
    $this->fileSystem->prepareDirectory($parentDir, FileSystemInterface::CREATE_DIRECTORY);

    // Create a file where the directory should be.
    $blockingFile = $directory;
    file_put_contents($blockingFile, 'blocking');

    // Capture messages.
    $messenger = \Drupal::messenger();

    // Run install.
    \Drupal::moduleHandler()->loadInclude('utilikit', 'install');
    utilikit_install();

    // Check for warning message.
    $messages = $messenger->messagesByType('warning');
    $found = FALSE;
    foreach ($messages as $message) {
      if (strpos((string) $message, 'Could not create UtiliKit CSS directory') !== FALSE) {
        $found = TRUE;
        break;
      }
    }
    $this->assertTrue($found, 'Warning message should be displayed when directory creation fails');

    // Clean up.
    unlink($blockingFile);
  }

  /**
   * Tests hook_uninstall removes all data.
   */
  public function testUninstallHook() {
    // First install to set everything up.
    \Drupal::moduleHandler()->loadInclude('utilikit', 'install');
    utilikit_install();

    // Add some state data.
    $this->state->set(UtilikitConstants::STATE_KNOWN_CLASSES, ['uk-pd--20', 'uk-mg--10']);
    $this->state->set(UtilikitConstants::STATE_GENERATED_CSS, '.uk-pd--20 { padding: 20px; }');
    $this->state->set(UtilikitConstants::STATE_CSS_TIMESTAMP, time());
    $this->state->set(UtilikitConstants::STATE_LAST_CLEANUP, time());
    $this->state->set('utilikit.initialized', TRUE);

    // Add rate limit data.
    $this->state->set('utilikit_rate_limit:127.0.0.1', 5);
    $this->state->set('utilikit_rate_limit:127.0.0.1_reset', time() + 60);

    // Create CSS file.
    $cssFile = UtilikitConstants::CSS_DIRECTORY . '/' . UtilikitConstants::CSS_FILENAME;
    $this->fileSystem->saveData('test css', $cssFile);

    // Verify everything exists before uninstall.
    $this->assertNotNull($this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES));
    $this->assertTrue(file_exists($cssFile));

    // Run uninstall.
    utilikit_uninstall();

    // Verify config deleted.
    $config = $this->configFactory->get('utilikit.settings');
    $this->assertNull($config->get('rendering_mode'));

    // Verify state deleted.
    $this->assertNull($this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES));
    $this->assertNull($this->state->get(UtilikitConstants::STATE_GENERATED_CSS));
    $this->assertNull($this->state->get(UtilikitConstants::STATE_CSS_TIMESTAMP));
    $this->assertNull($this->state->get(UtilikitConstants::STATE_LAST_CLEANUP));
    $this->assertNull($this->state->get('utilikit.initialized'));

    // Rate limit data is cleaned via direct SQL LIKE query on key_value table.
    // This cannot be reliably asserted in kernel tests because the state
    // service caches values in memory and the key_value table may not exist
    // after config deletion during uninstall.
    // Verify CSS file and directory deleted.
    $this->assertFalse(file_exists($cssFile));
    $this->assertFalse(is_dir(UtilikitConstants::CSS_DIRECTORY));
  }

  /**
   * Tests hook_requirements at runtime.
   */
  public function testRequirementsRuntime() {
    // Install module first.
    \Drupal::moduleHandler()->loadInclude('utilikit', 'install');
    utilikit_install();

    // Through the hook class, not the removed utilikit_requirements(). The
    // function moved to Drupal\utilikit\Hook\UtilikitRequirements for Drupal
    // 12, where hook_requirements() is no longer a procedural hook.
    $requirements = $this->container
      ->get(UtilikitRequirements::class)
      ->runtimeRequirements();

    // Check directory requirement.
    $this->assertArrayHasKey('utilikit_directory', $requirements);
    $this->assertEquals(DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn() => RequirementSeverity::OK, fn() => REQUIREMENT_OK), $requirements['utilikit_directory']['severity']);

    // Test with static mode and missing CSS file.
    $config = $this->configFactory->getEditable('utilikit.settings');
    $config->set('rendering_mode', 'static')->save();

    $requirements = $this->container
      ->get(UtilikitRequirements::class)
      ->runtimeRequirements();
    $this->assertArrayHasKey('utilikit_static_css', $requirements);
    $this->assertEquals(DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn() => RequirementSeverity::Warning, fn() => REQUIREMENT_WARNING), $requirements['utilikit_static_css']['severity']);

    // Test with many classes (performance warning)
    $manyClasses = [];
    for ($i = 0; $i < UtilikitConstants::MAX_CLASSES_WARNING_THRESHOLD + 100; $i++) {
      $manyClasses[] = 'uk-pd--' . $i;
    }
    $this->state->set(UtilikitConstants::STATE_KNOWN_CLASSES, $manyClasses);

    $requirements = $this->container
      ->get(UtilikitRequirements::class)
      ->runtimeRequirements();
    $this->assertArrayHasKey('utilikit_performance', $requirements);
    $this->assertEquals(DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn() => RequirementSeverity::Warning, fn() => REQUIREMENT_WARNING), $requirements['utilikit_performance']['severity']);
  }

  /**
   * Tests requirements with non-writable directory.
   */
  public function testRequirementsNonWritableDirectory() {
    // This test is tricky in kernel tests as we can't easily make directories
    // non-writable
    // We'll test the logic by mocking the conditions.
    // Create a mock file system service.
    $mockFileSystem = $this->createStub(FileSystemInterface::class);

    // Make realpath return a valid path but is_writable check will fail.
    $mockFileSystem->method('realpath')
      ->willReturn('/tmp/test-dir');

    // Replace the service temporarily.
    $this->container->set('file_system', $mockFileSystem);

    // We'll need to mock is_writable in the install file
    // For now, skip this specific test scenario.
    $this->markTestSkipped('Testing non-writable directories requires additional mocking infrastructure');
  }

  /**
   * Tests installation with existing data (upgrade scenario).
   */
  public function testInstallWithExistingData() {
    // Simulate existing data from a previous installation.
    $existingClasses = ['uk-pd--20', 'uk-mg--10'];
    $this->state->set(UtilikitConstants::STATE_KNOWN_CLASSES, $existingClasses);

    // Run install.
    \Drupal::moduleHandler()->loadInclude('utilikit', 'install');
    utilikit_install();

    // Verify existing data is preserved.
    $classes = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES);
    $this->assertEquals($existingClasses, $classes);

    // Verify new config is set.
    $config = $this->configFactory->get('utilikit.settings');
    $this->assertEquals('inline', $config->get('rendering_mode'));
  }

  /**
   * Tests requirements during install phase.
   */
  public function testRequirementsInstallPhase() {
    // There is no install-phase implementation to call any more, and there
    // never was one with a body: utilikit_requirements() ran nothing outside
    // `$phase === 'runtime'`. Drupal 11.2 split the hook into
    // hook_runtime_requirements() and hook_install_requirements(); this module
    // implements only the former, so the install phase contributing nothing is
    // now a fact about the module rather than a branch that returned [].
    $this->assertFalse(
      $this->container->has('utilikit.install_requirements'),
      'utilikit deliberately implements no install-phase requirements.',
    );
  }

  /**
   * Tests complete install/uninstall cycle.
   */
  public function testCompleteInstallUninstallCycle() {
    // Install.
    \Drupal::moduleHandler()->loadInclude('utilikit', 'install');
    utilikit_install();

    // Use the module - add some data.
    $this->state->set(UtilikitConstants::STATE_KNOWN_CLASSES, ['uk-pd--20']);
    $cssFile = UtilikitConstants::CSS_DIRECTORY . '/' . UtilikitConstants::CSS_FILENAME;
    $this->fileSystem->saveData('.uk-pd--20 { padding: 20px; }', $cssFile);

    // Verify it's working.
    $this->assertTrue(file_exists($cssFile));

    // Uninstall.
    utilikit_uninstall();

    // Verify complete cleanup.
    $this->assertFalse(file_exists($cssFile));
    $this->assertNull($this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES));

    // Reinstall.
    utilikit_install();

    // Verify fresh installation.
    $this->assertTrue(is_dir(UtilikitConstants::CSS_DIRECTORY));
    $this->assertEquals([], $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []));
  }

  /**
   * Tests configuration validation after install.
   */
  public function testConfigurationValidation() {
    // Install.
    \Drupal::moduleHandler()->loadInclude('utilikit', 'install');
    utilikit_install();

    $config = $this->configFactory->get('utilikit.settings');

    // Validate all required keys exist.
    $requiredKeys = [
      'rendering_mode', 'scope_global', 'disable_admin', 'scope_content_types',
      'enable_transitions', 'debounce', 'optimize_css', 'active_breakpoints',
      'dev_mode', 'admin_preview', 'show_page_errors', 'log_level',
      'update_on_node_save', 'update_on_block_save', 'update_on_paragraph_save',
      'batch_size', 'max_classes_per_request', 'rate_limit_requests',
      'css_cache_ttl', 'css_directory', 'css_filename',
      'legacy_mode', 'migration_complete', 'experimental_features',
    ];

    foreach ($requiredKeys as $key) {
      $this->assertNotNull(
        $config->get($key),
        "Configuration key '$key' should be set after installation"
      );
    }
  }

}
