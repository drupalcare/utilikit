<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Functional;

/**
 * Tests the UtiliKit AJAX endpoint access control.
 *
 * POST-based AJAX tests with CSRF tokens are better handled in kernel tests
 * where the controller can be called directly. Functional tests verify access
 * control and permission enforcement.
 *
 * @group utilikit
 */
class UtilikitAjaxTest extends UtilikitFunctionalTestBase {

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
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test user with permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $testUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article']);

    $this->testUser = $this->drupalCreateUser([
      'access content',
      'create article content',
      'use utilikit update button',
    ]);

    $this->drupalLogin($this->testUser);
  }

  /**
   * Tests access control for the update CSS endpoint.
   */
  public function testAccessControl() {
    // Test with anonymous user (should be denied).
    $this->drupalLogout();
    $this->drupalGet('/utilikit/update-css');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests render button is accessible to authorized users in static mode.
   */
  public function testRenderButtonStaticMode() {
    $this->config('utilikit.settings')
      ->set('rendering_mode', 'static')
      ->save();

    $this->drupalGet('/utilikit/render-button');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests render button returns 204 in inline mode.
   */
  public function testRenderButtonInlineMode() {
    $this->config('utilikit.settings')
      ->set('rendering_mode', 'inline')
      ->save();

    $this->drupalGet('/utilikit/render-button');
    $this->assertSession()->statusCodeEquals(204);
  }

  /**
   * Tests render button in head mode.
   */
  public function testRenderButtonHeadMode() {
    $this->config('utilikit.settings')
      ->set('rendering_mode', 'head')
      ->save();

    $this->drupalGet('/utilikit/render-button');
    $this->assertSession()->statusCodeEquals(200);
  }

}
