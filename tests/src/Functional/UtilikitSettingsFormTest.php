<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the UtiliKit settings form functionality.
 *
 * @group utilikit
 */
class UtilikitSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'utilikit',
    'node',
    'field',
    'text',
    'filter',
    'user',
    'block_content',
    'paragraphs',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create content types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);

    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
      'access administration pages',
    ]);

    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests settings form access.
   */
  public function testSettingsFormAccess() {
    // Test with admin user.
    $this->drupalGet('admin/config/utilikit/settings');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('UtiliKit Settings');

    // Test with anonymous user.
    $this->drupalLogout();
    $this->drupalGet('admin/config/utilikit/settings');
    $this->assertSession()->statusCodeEquals(403);

    // Test with user without permission.
    $user = $this->drupalCreateUser(['access administration pages']);
    $this->drupalLogin($user);
    $this->drupalGet('admin/config/utilikit/settings');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests rendering mode settings.
   */
  public function testRenderingModeSettings() {
    $this->drupalGet('admin/config/utilikit/settings');

    // Check rendering mode options.
    $this->assertSession()->fieldExists('rendering_mode');
    $this->assertSession()->pageTextContains('Inline Mode');
    $this->assertSession()->pageTextContains('Static Mode');

    // Test switching to static mode.
    $edit = ['rendering_mode' => 'static'];
    $this->submitForm($edit, 'Save configuration');

    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->pageTextContains('Switched to static mode');

    // Verify setting was saved.
    $config = $this->config('utilikit.settings');
    $this->assertEquals('static', $config->get('rendering_mode'));

    // Test switching back to inline mode.
    $edit = ['rendering_mode' => 'inline'];
    $this->submitForm($edit, 'Save configuration');

    $this->assertSession()->pageTextContains('Switched to inline mode');
    $this->assertEquals('inline', $this->config('utilikit.settings')->get('rendering_mode'));
  }

  /**
   * Tests performance settings.
   */
  public function testPerformanceSettings() {
    $this->drupalGet('admin/config/utilikit/settings');

    // Check performance fields.
    $this->assertSession()->fieldExists('enable_transitions');
    $this->assertSession()->fieldExists('debounce');
    $this->assertSession()->fieldExists('optimize_css');

    // Check breakpoint options.
    $breakpoints = ['sm', 'md', 'lg', 'xl', 'xxl'];
    foreach ($breakpoints as $bp) {
      $this->assertSession()->fieldExists("active_breakpoints[$bp]");
    }

    // Test saving performance settings.
    $edit = [
      'enable_transitions' => TRUE,
      'debounce' => 100,
      'active_breakpoints[sm]' => FALSE,
      'active_breakpoints[md]' => TRUE,
      'active_breakpoints[lg]' => TRUE,
      'active_breakpoints[xl]' => FALSE,
      'active_breakpoints[xxl]' => FALSE,
      'optimize_css' => TRUE,
    ];
    $this->submitForm($edit, 'Save configuration');

    $config = $this->config('utilikit.settings');
    $this->assertTrue($config->get('enable_transitions'));
    $this->assertEquals(100, $config->get('debounce'));
    $this->assertTrue($config->get('optimize_css'));

    $activeBreakpoints = $config->get('active_breakpoints');
    $this->assertFalse(isset($activeBreakpoints['sm']));
    $this->assertEquals('md', $activeBreakpoints['md']);
    $this->assertEquals('lg', $activeBreakpoints['lg']);
  }

  /**
   * Tests scope settings.
   */
  public function testScopeSettings() {
    $this->drupalGet('admin/config/utilikit/settings');

    // Check scope fields.
    $this->assertSession()->fieldExists('scope_global');
    $this->assertSession()->fieldExists('disable_admin');
    $this->assertSession()->fieldExists('scope_content_types');
    $this->assertSession()->fieldExists('enabled_content_types[article]');
    $this->assertSession()->fieldExists('enabled_content_types[page]');

    // Test global scope.
    $edit = [
      'scope_global' => TRUE,
      'disable_admin' => TRUE,
    ];
    $this->submitForm($edit, 'Save configuration');

    $config = $this->config('utilikit.settings');
    $this->assertTrue($config->get('scope_global'));
    $this->assertTrue($config->get('disable_admin'));

    // Test content type scope.
    $this->drupalGet('admin/config/utilikit/settings');
    $edit = [
      'scope_global' => FALSE,
      'scope_content_types' => TRUE,
      'enabled_content_types[article]' => TRUE,
      'enabled_content_types[page]' => FALSE,
    ];
    $this->submitForm($edit, 'Save configuration');

    $config = $this->config('utilikit.settings');
    $this->assertFalse($config->get('scope_global'));
    $this->assertTrue($config->get('scope_content_types'));
    $this->assertContains('article', $config->get('enabled_content_types'));
    $this->assertNotContains('page', $config->get('enabled_content_types'));
  }

  /**
   * Tests automatic update triggers.
   */
  public function testUpdateTriggers() {
    $this->drupalGet('admin/config/utilikit/settings');

    // First set static mode.
    $edit = ['rendering_mode' => 'static'];
    $this->submitForm($edit, 'Save configuration');

    $this->drupalGet('admin/config/utilikit/settings');

    // Check trigger fields are visible in static mode.
    $this->assertSession()->fieldExists('update_on_node_save');
    $this->assertSession()->fieldExists('update_on_block_save');
    $this->assertSession()->fieldExists('update_on_paragraph_save');

    // Test saving triggers.
    $edit = [
      'update_on_node_save' => TRUE,
      'update_on_block_save' => FALSE,
      'update_on_paragraph_save' => TRUE,
    ];
    $this->submitForm($edit, 'Save configuration');

    $config = $this->config('utilikit.settings');
    $this->assertTrue($config->get('update_on_node_save'));
    $this->assertFalse($config->get('update_on_block_save'));
    $this->assertTrue($config->get('update_on_paragraph_save'));
  }

  /**
   * Tests developer settings.
   */
  public function testDeveloperSettings() {
    $this->drupalGet('admin/config/utilikit/settings');

    // Check developer fields.
    $this->assertSession()->fieldExists('dev_mode');
    $this->assertSession()->fieldExists('admin_preview');
    $this->assertSession()->fieldExists('show_page_errors');
    $this->assertSession()->fieldExists('log_level');

    // Test saving developer settings.
    $edit = [
      'dev_mode' => TRUE,
      'admin_preview' => TRUE,
      'show_page_errors' => TRUE,
      'log_level' => 'detailed',
    ];
    $this->submitForm($edit, 'Save configuration');

    $config = $this->config('utilikit.settings');
    $this->assertTrue($config->get('dev_mode'));
    $this->assertTrue($config->get('admin_preview'));
    $this->assertTrue($config->get('show_page_errors'));
    $this->assertEquals('detailed', $config->get('log_level'));

    // Test validation - show_page_errors requires dev_mode.
    $this->drupalGet('admin/config/utilikit/settings');
    $edit = [
      'dev_mode' => FALSE,
      'show_page_errors' => TRUE,
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('Show page errors requires developer mode to be enabled');
  }

  /**
   * Tests cache management.
   */
  public function testCacheManagement() {
    // First add some tracked classes.
    $stateManager = \Drupal::service('utilikit.state_manager');
    $stateManager->setKnownClasses(['uk-pd--20', 'uk-mg--10']);
    $stateManager->setGeneratedCss('.uk-pd--20 { padding: 20px; }');

    $this->drupalGet('admin/config/utilikit/settings');

    // Check cache info is displayed.
    $this->assertSession()->pageTextContains('Currently tracking');
    // Number of classes.
    $this->assertSession()->pageTextContains('2');

    // Test clear cache button.
    $this->submitForm([], 'Clear All CSS & Reset');

    $this->assertSession()->pageTextContains('CSS cleared');

    // Verify data was cleared.
    $this->assertEquals([], $stateManager->getKnownClasses());
    $this->assertEquals('', $stateManager->getGeneratedCss());
  }

  /**
   * Tests static mode CSS file info display.
   */
  public function testStaticCssFileInfo() {
    // Enable static mode and generate CSS.
    $this->config('utilikit.settings')
      ->set('rendering_mode', 'static')
      ->save();

    $serviceProvider = \Drupal::service('utilikit.service_provider');
    $serviceProvider->updateCssAndFile(['uk-pd--20', 'uk-mg--10']);

    // Enable dev mode to see CSS info.
    $this->drupalGet('admin/config/utilikit/settings');
    $edit = ['dev_mode' => TRUE];
    $this->submitForm($edit, 'Save configuration');

    $this->drupalGet('admin/config/utilikit/settings');

    // Check CSS file info is displayed.
    $this->assertSession()->pageTextContains('Generated CSS File (Static Mode)');
    $this->assertSession()->pageTextContains('CSS File Generated');
    $this->assertSession()->linkExists('View Generated CSS File');
    $this->assertSession()->pageTextContains('Classes: 2');
    $this->assertSession()->pageTextContains('Last Updated:');
  }

  /**
   * Tests form state handling.
   */
  public function testFormStateHandling() {
    $this->drupalGet('admin/config/utilikit/settings');

    // Test conditional field visibility
    // When dev_mode is off, log_level should be hidden.
    $page = $this->getSession()->getPage();

    // Initially dev_mode might be off.
    if (!$page->findField('dev_mode')->isChecked()) {
      $this->assertFalse($page->findField('log_level')->isVisible());
    }

    // Check dev_mode to reveal log_level.
    $page->checkField('dev_mode');
    $this->assertTrue($page->findField('log_level')->isVisible());

    // Test scope_content_types conditional.
    if ($page->findField('scope_global')->isChecked()) {
      $this->assertFalse($page->findField('scope_content_types')->isEnabled());
    }

    // Uncheck scope_global to enable scope_content_types.
    $page->uncheckField('scope_global');
    $this->assertTrue($page->findField('scope_content_types')->isEnabled());
  }

  /**
   * Tests complete workflow from inline to static mode.
   */
  public function testCompleteWorkflow() {
    // Start in inline mode (default)
    $config = $this->config('utilikit.settings');
    $this->assertEquals('inline', $config->get('rendering_mode'));

    // Switch to static mode.
    $this->drupalGet('admin/config/utilikit/settings');
    $edit = [
      'rendering_mode' => 'static',
      'update_on_node_save' => TRUE,
      'dev_mode' => TRUE,
    ];
    $this->submitForm($edit, 'Save configuration');

    // Verify switch messages.
    $this->assertSession()->pageTextContains('Switched to static mode');
    $this->assertSession()->pageTextContains('Generated CSS for');

    // Check that CSS file info appears.
    $this->drupalGet('admin/config/utilikit/settings');
    $this->assertSession()->linkExists('View Generated CSS File');

    // Verify the CSS contains our classes.
    $stateManager = \Drupal::service('utilikit.state_manager');
    $generatedCss = $stateManager->getGeneratedCss();
    $this->assertStringContainsString('.uk-pd--20', $generatedCss);
    $this->assertStringContainsString('.uk-bg--007bff', $generatedCss);
  }

}
