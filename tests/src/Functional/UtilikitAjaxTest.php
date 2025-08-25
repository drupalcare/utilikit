<?php

namespace Drupal\Tests\utilikit\Functional;

use Drupal\utilikit\Service\UtilikitConstants;

/**
 * Tests the UtiliKit AJAX controller functionality.
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

    // Create article content type.
    $this->drupalCreateContentType(['type' => 'article']);

    // Create test user with permissions.
    $this->testUser = $this->drupalCreateUser([
      'access content',
      'create article content',
      'use utilikit update button',
    ]);

    $this->drupalLogin($this->testUser);
  }

  /**
   * Tests successful CSS update via AJAX in static mode.
   */
  public function testAjaxCssUpdateSuccess() {
    // Enable static mode.
    $this->config('utilikit.settings')
      ->set('rendering_mode', 'static')
      ->save();

    // Prepare test data.
    $classes = ['uk-pd--20', 'uk-mg--t-10', 'uk-bg--ff0000'];
    $csrfToken = \Drupal::csrfToken()->get('utilikit-update-css');

    // Make AJAX request.
    $response = $this->drupalPostJson('/utilikit/update-css', [
      'classes' => $classes,
      'mode' => 'static',
    ], [
      'X-CSRF-Token' => $csrfToken,
      'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $this->assertSession()->statusCodeEquals(200);

    $data = json_decode($response, TRUE);
    $this->assertEquals('success', $data['status']);
    $this->assertStringContainsString('CSS updated successfully', $data['message']);
    $this->assertArrayHasKey('count', $data);
    $this->assertArrayHasKey('total', $data);
    $this->assertArrayHasKey('css', $data);
    $this->assertArrayHasKey('timestamp', $data);

    // Verify CSS contains the classes.
    $this->assertStringContainsString('.uk-pd--20', $data['css']);
    $this->assertStringContainsString('.uk-mg--t-10', $data['css']);
    $this->assertStringContainsString('.uk-bg--ff0000', $data['css']);
  }

  /**
   * Tests AJAX update in inline mode.
   */
  public function testAjaxInlineMode() {
    // Enable inline mode.
    $this->config('utilikit.settings')
      ->set('rendering_mode', 'inline')
      ->save();

    $csrfToken = \Drupal::csrfToken()->get('utilikit-update-css');

    $response = $this->drupalPostJson('/utilikit/update-css', [
      'classes' => ['uk-pd--20'],
      'mode' => 'inline',
    ], [
      'X-CSRF-Token' => $csrfToken,
      'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $this->assertSession()->statusCodeEquals(200);

    $data = json_decode($response, TRUE);
    $this->assertEquals('success', $data['status']);
    $this->assertStringContainsString('Inline mode active', $data['message']);
    $this->assertEquals('inline', $data['mode']);
  }

  /**
   * Tests rate limiting.
   */
  public function testRateLimit() {
    $this->config('utilikit.settings')
      ->set('rendering_mode', 'static')
      ->save();

    $csrfToken = \Drupal::csrfToken()->get('utilikit-update-css');
    $headers = [
      'X-CSRF-Token' => $csrfToken,
      'X-Requested-With' => 'XMLHttpRequest',
    ];

    // Make requests up to the rate limit.
    for ($i = 0; $i < UtilikitConstants::RATE_LIMIT_REQUESTS_PER_MINUTE; $i++) {
      $response = $this->drupalPostJson('/utilikit/update-css', [
        'classes' => ['uk-pd--' . $i],
        'mode' => 'static',
      ], $headers);

      $this->assertSession()->statusCodeEquals(200);
    }

    // Next request should be rate limited.
    $response = $this->drupalPostJson('/utilikit/update-css', [
      'classes' => ['uk-pd--999'],
      'mode' => 'static',
    ], $headers);

    $this->assertSession()->statusCodeEquals(429);

    $data = json_decode($response, TRUE);
    $this->assertEquals('error', $data['status']);
    $this->assertStringContainsString('Rate limit exceeded', $data['message']);
  }

  /**
   * Tests access control.
   */
  public function testAccessControl() {
    // Test without XMLHttpRequest header.
    $this->drupalPost('/utilikit/update-css', [
      'classes' => ['uk-pd--20'],
    ]);
    $this->assertSession()->statusCodeEquals(403);

    // Test with anonymous user.
    $this->drupalLogout();
    $csrfToken = \Drupal::csrfToken()->get('utilikit-update-css');
    $this->drupalPostJson('/utilikit/update-css', [
      'classes' => ['uk-pd--20'],
    ], [
      'X-CSRF-Token' => $csrfToken,
      'X-Requested-With' => 'XMLHttpRequest',
    ]);
    // Should work for anonymous users with 'access content'.
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests invalid request data.
   */
  public function testInvalidRequestData() {
    $csrfToken = \Drupal::csrfToken()->get('utilikit-update-css');
    $headers = [
      'X-CSRF-Token' => $csrfToken,
      'X-Requested-With' => 'XMLHttpRequest',
    ];

    // Test with invalid JSON.
    $client = $this->getSession()->getDriver()->getClient();
    $client->request('POST', $this->buildUrl('/utilikit/update-css'), [], [], $headers, 'invalid json');
    $response = $client->getResponse();

    $this->assertEquals(400, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertEquals('error', $data['status']);
    $this->assertStringContainsString('Invalid JSON data', $data['message']);

    // Test with missing classes array.
    $response = $this->drupalPostJson('/utilikit/update-css', [
      'mode' => 'static',
    ], $headers);

    $this->assertSession()->statusCodeEquals(400);
    $data = json_decode($response, TRUE);
    $this->assertEquals('error', $data['status']);
    $this->assertStringContainsString('Missing or invalid classes array', $data['message']);

    // Test with too many classes.
    $tooManyClasses = [];
    for ($i = 0; $i <= UtilikitConstants::MAX_CLASSES_PER_REQUEST; $i++) {
      $tooManyClasses[] = 'uk-pd--' . $i;
    }

    $response = $this->drupalPostJson('/utilikit/update-css', [
      'classes' => $tooManyClasses,
      'mode' => 'static',
    ], $headers);

    $this->assertSession()->statusCodeEquals(400);
    $data = json_decode($response, TRUE);
    $this->assertEquals('error', $data['status']);
    $this->assertStringContainsString('Too many classes', $data['message']);
  }

  /**
   * Tests lock handling and queueing.
   */
  public function testLockHandling() {
    $this->config('utilikit.settings')
      ->set('rendering_mode', 'static')
      ->save();

    $csrfToken = \Drupal::csrfToken()->get('utilikit-update-css');
    $headers = [
      'X-CSRF-Token' => $csrfToken,
      'X-Requested-With' => 'XMLHttpRequest',
    ];

    // Acquire lock manually.
    $lock = \Drupal::lock();
    $lock->acquire(UtilikitConstants::LOCK_CSS_UPDATE);

    try {
      // Make request while locked.
      $response = $this->drupalPostJson('/utilikit/update-css', [
        'classes' => ['uk-pd--20'],
        'mode' => 'static',
      ], $headers);

      $this->assertSession()->statusCodeEquals(200);

      $data = json_decode($response, TRUE);
      $this->assertEquals('locked', $data['status']);
      $this->assertStringContainsString('Another update is in progress', $data['message']);
      $this->assertArrayHasKey('retry_after', $data);

      // Verify item was queued.
      $queue = \Drupal::queue(UtilikitConstants::QUEUE_CSS_PROCESSOR);
      $this->assertGreaterThan(0, $queue->numberOfItems());

    } finally {
      $lock->release(UtilikitConstants::LOCK_CSS_UPDATE);
    }
  }

  /**
   * Tests class validation.
   */
  public function testClassValidation() {
    $this->config('utilikit.settings')
      ->set('rendering_mode', 'static')
      ->save();

    $csrfToken = \Drupal::csrfToken()->get('utilikit-update-css');

    // Mix of valid and invalid classes.
    $response = $this->drupalPostJson('/utilikit/update-css', [
      'classes' => [
    // Valid.
        'uk-pd--20',
    // Invalid - no uk- prefix.
        'invalid-class',
    // Invalid - unknown prefix.
        'uk-xx--20',
    // Valid.
        'uk-mg--t-auto',
    // Invalid - incomplete.
        'uk-',
      ],
      'mode' => 'static',
    ], [
      'X-CSRF-Token' => $csrfToken,
      'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $this->assertSession()->statusCodeEquals(200);

    $data = json_decode($response, TRUE);
    $this->assertEquals('success', $data['status']);

    // Should only process valid classes.
    $this->assertStringContainsString('.uk-pd--20', $data['css']);
    $this->assertStringContainsString('.uk-mg--t-auto', $data['css']);
    $this->assertStringNotContainsString('invalid-class', $data['css']);
    $this->assertStringNotContainsString('.uk-xx--20', $data['css']);
  }

}
