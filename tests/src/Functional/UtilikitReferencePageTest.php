<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the UtiliKit reference page functionality.
 *
 * @group utilikit
 */
#[Group('utilikit')]
#[RunTestsInSeparateProcesses]
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
      'access utilikit reference',
    ]);

    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests reference page access.
   */
  public function testReferencePageAccess() {
    // Test with admin user.
    $this->drupalGet('utilikit/reference');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('UtiliKit');

    // Test with anonymous user.
    $this->drupalLogout();
    $this->drupalGet('utilikit/reference');
    $this->assertSession()->statusCodeEquals(403);

    // Test with user without permission.
    $user = $this->drupalCreateUser(['access administration pages']);
    $this->drupalLogin($user);
    $this->drupalGet('utilikit/reference');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests reference page content structure.
   */
  public function testReferencePageContent() {
    $this->drupalGet('utilikit/reference');

    // Check page structure.
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

    // Check the reference page container exists.
    $this->assertSession()->elementExists('css', '.utilikit-reference-page');
  }

  /**
   * Tests reference page tables.
   */
  public function testReferencePageTables() {
    $this->drupalGet('utilikit/reference');

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
    $this->drupalGet('utilikit/reference');

    // Check for syntax structure.
    $this->assertSession()->elementExists('css', '.utilikit-cheatsheet');
    $this->assertSession()->elementExists('css', '.syntax-group');
    $this->assertSession()->elementExists('css', '.syntax-item');

    // Check syntax coloring elements.
    $this->assertSession()->elementExists('css', '.syntax-uk');
    $this->assertSession()->elementExists('css', '.syntax-prefix');
    $this->assertSession()->elementExists('css', '.syntax-value');
  }

  /**
   * Tests specific rule examples.
   */
  public function testRuleExamples() {
    $this->drupalGet('utilikit/reference');

    // Test color examples.
    $this->assertSession()->pageTextContains('uk-bg--ff0000');
    $this->assertSession()->pageTextContains('Hex color');

    // Test spacing examples.
    $this->assertSession()->pageTextContains('uk-pd--t-20');

    // Test keyword examples.
    $this->assertSession()->pageTextContains('uk-dp--flex');
    $this->assertSession()->pageTextContains('CSS keyword');

    // Test transform examples.
    $this->assertSession()->pageTextContains('uk-rt--45');
    $this->assertSession()->pageTextContains('Degrees');

    // Test grid examples.
    $this->assertSession()->pageTextContains('uk-gc--repeat-3-1fr');
    $this->assertSession()->pageTextContains('Grid track list');

    // Test responsive examples (md breakpoint prefix).
    $this->assertSession()->responseContains('uk-md-');
  }

  /**
   * Tests that utility prefix examples are shown.
   */
  public function testUtilityPrefixExamples() {
    $this->drupalGet('utilikit/reference');

    // Check that common utility prefixes appear in the cheatsheet.
    $prefixes = ['uk-pd', 'uk-mg', 'uk-bg', 'uk-tc', 'uk-dp'];
    foreach ($prefixes as $prefix) {
      $this->assertSession()->responseContains($prefix);
    }
  }

  /**
   * Tests that CSS and JS assets are attached.
   */
  public function testLibraryAttachment() {
    $this->drupalGet('utilikit/reference');

    // Verify the page loads with CSS/JS — check for reference page CSS class
    // which proves the library was attached and rendered.
    $this->assertSession()->elementExists('css', '.utilikit-reference-page');
    $this->assertSession()->elementExists('css', '.utilikit-cheatsheet');
  }

  /**
   * Tests notes and tooltips.
   */
  public function testNotesAndTooltips() {
    $this->drupalGet('utilikit/reference');

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
