<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the UtilikitAjaxController access control and GET endpoints.
 *
 * POST-based AJAX endpoint tests are in kernel tests where CSRF tokens
 * can be properly managed. Functional tests focus on access control
 * and render button behavior.
 *
 * @group utilikit
 * @group utilikit_functional
 *
 * @coversDefaultClass \Drupal\utilikit\Controller\UtilikitAjaxController
 */
#[Group('utilikit')]
#[Group('utilikit_functional')]
#[RunTestsInSeparateProcesses]
class UtilikitAjaxControllerTest extends UtilikitFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'utilikit',
    'node',
    'field',
    'text',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test user with appropriate permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * Test user without required permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $unauthorizedUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article']);

    $this->webUser = $this->drupalCreateUser([
      'access content',
      'create article content',
      'use utilikit update button',
    ]);

    $this->unauthorizedUser = $this->drupalCreateUser([
      'access content',
    ]);

    $this->config('utilikit.settings')
      ->set('rendering_mode', 'static')
      ->save();
  }

  /**
   * Tests updateCss endpoint denies access to unauthorized users.
   */
  public function testUpdateCssUnauthorized(): void {
    $this->drupalLogin($this->unauthorizedUser);
    $this->drupalGet('/utilikit/update-css');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests updateCss endpoint denies access to anonymous users.
   */
  public function testUpdateCssAnonymous(): void {
    $this->drupalGet('/utilikit/update-css');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests renderButton endpoint returns content for authorized users.
   */
  public function testRenderButtonSuccess(): void {
    $this->drupalLogin($this->webUser);
    $this->drupalGet('/utilikit/render-button');
    // In static mode, the button should render with 200 status.
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests renderButton returns 204 in inline mode (no button needed).
   */
  public function testRenderButtonInlineMode(): void {
    $this->config('utilikit.settings')
      ->set('rendering_mode', 'inline')
      ->save();

    $this->drupalLogin($this->webUser);
    $this->drupalGet('/utilikit/render-button');
    $this->assertSession()->statusCodeEquals(204);
  }

  /**
   * Tests renderButton endpoint denies access to unauthorized users.
   */
  public function testRenderButtonUnauthorized(): void {
    $this->drupalLogin($this->unauthorizedUser);
    $this->drupalGet('/utilikit/render-button');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests renderButton endpoint denies access to anonymous users.
   */
  public function testRenderButtonAnonymous(): void {
    $this->drupalGet('/utilikit/render-button');
    $this->assertSession()->statusCodeEquals(403);
  }

}
