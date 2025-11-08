<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the UtiliKit reference page functionality.
 *
 * @group utilikit
 */
class UtilikitReferencePageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['utilikit', 'user'];

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

    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
      'access administration pages',
    ]);

    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests reference page access.
   */
  public function testReferencePageAccess() {
    // Test with admin user.
    $this->drupalGet('admin/config/development/utilikit/reference');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('UtiliKit Utility Class Reference');

    // Test with anonymous user.
    $this->drupalLogout();
    $this->drupalGet('admin/config/development/utilikit/reference');
    $this->assertSession()->statusCodeEquals(403);

    // Test with user without permission.
    $user = $this->drupalCreateUser(['access administration pages']);
    $this->drupalLogin($user);
    $this->drupalGet('admin/config/development/utilikit/reference');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests reference page content structure.
   */
  public function testReferencePageContent() {
    $this->drupalGet('admin/config/development/utilikit/reference');

    // Check page structure.
    $this->assertSession()->pageTextContains('UtiliKit Utility Class Reference');
    $this->assertSession()->pageTextContains('Quick Reference Guide');

    // Check for cheatsheet sections.
    $this->assertSession()->pageTextContains('Box Model');
    $this->assertSession()->pageTextContains('Sizing');
    $this->assertSession()->pageTextContains('Positioning');
    $this->assertSession()->pageTextContains('Typography');
    $this->assertSession()->pageTextContains('Colors');
    $this->assertSession()->pageTextContains('Layout');
    $this->assertSession()->pageTextContains('Flexbox');
    $this->assertSession()->pageTextContains('Grid');
    $this->assertSession()->pageTextContains('Transform');
    $this->assertSession()->pageTextContains('Effects');

    // Check for interactive example.
    $this->assertSession()->pageTextContains('Try it yourself:');
    $this->assertSession()->fieldExists('utilikit-example-input');
    $this->assertSession()->elementExists('css', '#utilikit-example-preview');
  }

  /**
   * Tests reference page tables.
   */
  public function testReferencePageTables() {
    $this->drupalGet('admin/config/development/utilikit/reference');

    // Check table headers.
    $this->assertSession()->pageTextContains('Property');
    $this->assertSession()->pageTextContains('Prefix');
    $this->assertSession()->pageTextContains('Example');
    $this->assertSession()->pageTextContains('Responsive');
    $this->assertSession()->pageTextContains('Value Type');
    $this->assertSession()->pageTextContains('Notes');

    // Check for specific utility classes.
    $utilityExamples = [
      'uk-pd' => 'padding',
      'uk-mg' => 'margin',
      'uk-bg' => 'backgroundColor',
      'uk-tc' => 'color',
      'uk-dp' => 'display',
      'uk-ps' => 'position',
      'uk-wd' => 'width',
      'uk-ht' => 'height',
    ];

    foreach ($utilityExamples as $prefix => $property) {
      $this->assertSession()->pageTextContains($prefix);
      // Check that property is shown in some form.
      $this->assertSession()->responseContains($property);
    }

    // Check for responsive examples.
    $this->assertSession()->pageTextContains('uk-md-');
  }

  /**
   * Tests cheatsheet functionality.
   */
  public function testCheatsheetContent() {
    $this->drupalGet('admin/config/development/utilikit/reference');

    // Check for syntax structure.
    $this->assertSession()->elementExists('css', '.utilikit-cheatsheet');
    $this->assertSession()->elementExists('css', '.syntax-group');
    $this->assertSession()->elementExists('css', '.syntax-item');

    // Check for copy buttons.
    $this->assertSession()->elementExists('css', '.copy-group-btn');

    // Check syntax coloring elements.
    $this->assertSession()->elementExists('css', '.syntax-uk');
    $this->assertSession()->elementExists('css', '.syntax-prefix');
    $this->assertSession()->elementExists('css', '.syntax-value');
  }

  /**
   * Tests specific rule examples.
   */
  public function testRuleExamples() {
    $this->drupalGet('admin/config/development/utilikit/reference');

    // Test color examples.
    $this->assertSession()->pageTextContains('uk-bg--ff0000');
    $this->assertSession()->pageTextContains('Hex color');

    // Test spacing examples.
    $this->assertSession()->pageTextContains('uk-pd--t-20');
    $this->assertSession()->pageTextContains('uk-pd--10-20');

    // Test keyword examples.
    $this->assertSession()->pageTextContains('uk-dp--flex');
    $this->assertSession()->pageTextContains('CSS keyword');

    // Test transform examples.
    $this->assertSession()->pageTextContains('uk-rt--45');
    $this->assertSession()->pageTextContains('Degrees');

    // Test grid examples.
    $this->assertSession()->pageTextContains('uk-gc--repeat-3-1fr');
    $this->assertSession()->pageTextContains('Grid track list');

    // Test responsive examples.
    $this->assertSession()->pageTextContains('uk-md-pd--20');
  }

  /**
   * Tests common example suggestions.
   */
  public function testCommonExamples() {
    $this->drupalGet('admin/config/development/utilikit/reference');

    // Check for common example patterns.
    $commonExamples = [
      'uk-pd--20 uk-mg--t-auto uk-bg--f8f9fa',
      'uk-dp--flex uk-jc--center uk-ai--center uk-gp--16',
      'uk-fs--24 uk-fw--700 uk-tc--007bff uk-ta--center',
    ];

    foreach ($commonExamples as $example) {
      $this->assertSession()->responseContains($example);
    }
  }

  /**
   * Tests JavaScript library attachment.
   */
  public function testLibraryAttachment() {
    $this->drupalGet('admin/config/development/utilikit/reference');

    // Check that required libraries are attached.
    $this->assertSession()->responseContains('utilikit.reference.css');
    $this->assertSession()->responseContains('utilikit.reference.js');
    $this->assertSession()->responseContains('utilikit.engine');
  }

  /**
   * Tests notes and tooltips.
   */
  public function testNotesAndTooltips() {
    $this->drupalGet('admin/config/development/utilikit/reference');

    // Check for specific notes.
    $notes = [
      'Use 6-digit hex',
      'Supports sides + shorthand',
      'Multiple units',
      'px, %, em, rem',
      '0-100 → 0-1',
      'Grid syntax',
    ];

    foreach ($notes as $note) {
      $this->assertSession()->responseContains($note);
    }
  }

}
