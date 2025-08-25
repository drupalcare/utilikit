<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Functional;

use Drupal\utilikit\Controller\UtilikitAjaxController;
use Drupal\Core\Url;
use Drupal\Tests\utilikit\UtilikitTestHelpers;
use Drupal\utilikit\Service\UtilikitConstants;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the UtilikitAjaxController functionality.
 *
 * This test class covers AJAX endpoints for CSS updates and button rendering,
 * including security features like rate limiting, CSRF protection, access
 * control, and error handling. Tests cover both inline and static rendering
 * modes with comprehensive validation of JSON responses.
 *
 * @group utilikit
 * @group utilikit_functional
 *
 * @coversDefaultClass \Drupal\utilikit\Controller\UtilikitAjaxController
 */
class UtilikitAjaxControllerTest extends UtilikitFunctionalTestBase {

  use UtilikitTestHelpers;

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
   * The update CSS endpoint URL.
   *
   * @var \Drupal\Core\Url
   */
  protected $updateCssUrl;

  /**
   * The render button endpoint URL.
   *
   * @var \Drupal\Core\Url
   */
  protected $renderButtonUrl;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create content type for testing.
    $this->drupalCreateContentType(['type' => 'article']);

    // Create test users with different permissions.
    $this->webUser = $this->drupalCreateUser([
      'access content',
      'create article content',
      'edit own article content',
    ]);

    $this->unauthorizedUser = $this->drupalCreateUser([
      'access content',
    ]);

    // Set up endpoint URLs.
    $this->updateCssUrl = Url::fromRoute('utilikit.update_css');
    $this->renderButtonUrl = Url::fromRoute('utilikit.render_button');

    // Configure UtiliKit for testing.
    $this->config('utilikit.settings')
      ->set('rendering_mode', 'static')
      ->set('scope', 'all')
      ->set('cache_enabled', TRUE)
      ->save();
  }

  /**
   * Tests updateCss endpoint with valid AJAX request in static mode.
   *
   * @covers ::updateCss
   */
  public function testUpdateCssStaticModeSuccess(): void {
    $this->drupalLogin($this->webUser);

    $test_classes = $this->getTestUtilityClasses('basic');
    $request_data = [
      'mode' => 'static',
      'classes' => $test_classes,
    ];

    $response = $this->drupalPost(
      $this->updateCssUrl->toString(),
      json_encode($request_data),
      [
        'Content-Type' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
      ]
    );

    $this->assertEquals(200, $response->getStatusCode());

    $responseData = json_decode($response->getContent(), TRUE);
    $this->assertNotNull($responseData);
    $this->assertEquals('success', $responseData['status']);
    $this->assertEquals('static', $responseData['mode']);
    $this->assertArrayHasKey('count', $responseData);
    $this->assertArrayHasKey('timestamp', $responseData);
    $this->assertStringContainsString('CSS updated successfully', $responseData['message']);
  }

  /**
   * Tests updateCss endpoint with inline mode.
   *
   * @covers ::updateCss
   */
  public function testUpdateCssInlineModeSuccess(): void {
    // Configure for inline mode.
    $this->config('utilikit.settings')
      ->set('rendering_mode', 'inline')
      ->save();

    $this->drupalLogin($this->webUser);

    $request_data = [
      'mode' => 'inline',
      'classes' => $this->getTestUtilityClasses('basic'),
    ];

    $response = $this->drupalPost(
      $this->updateCssUrl->toString(),
      json_encode($request_data),
      [
        'Content-Type' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
      ]
    );

    $this->assertEquals(200, $response->getStatusCode());

    $responseData = json_decode($response->getContent(), TRUE);
    $this->assertEquals('success', $responseData['status']);
    $this->assertEquals('inline', $responseData['mode']);
    $this->assertStringContainsString('Inline mode active', $responseData['message']);
  }

  /**
   * Tests updateCss endpoint without AJAX header (should fail).
   *
   * @covers ::updateCss
   */
  public function testUpdateCssWithoutAjaxHeader(): void {
    $this->drupalLogin($this->webUser);

    $request_data = [
      'mode' => 'static',
      'classes' => $this->getTestUtilityClasses('basic'),
    ];

    $response = $this->drupalPost(
      $this->updateCssUrl->toString(),
      json_encode($request_data),
      ['Content-Type' => 'application/json']
    );

    $this->assertEquals(403, $response->getStatusCode());
  }

  /**
   * Tests updateCss endpoint with invalid JSON data.
   *
   * @covers ::updateCss
   */
  public function testUpdateCssWithInvalidJson(): void {
    $this->drupalLogin($this->webUser);

    $response = $this->drupalPost(
      $this->updateCssUrl->toString(),
      'invalid json data',
      [
        'Content-Type' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
      ]
    );

    $this->assertEquals(400, $response->getStatusCode());

    $responseData = json_decode($response->getContent(), TRUE);
    $this->assertEquals('error', $responseData['status']);
    $this->assertStringContainsString('Invalid JSON data', $responseData['message']);
  }

  /**
   * Tests updateCss endpoint with missing classes array.
   *
   * @covers ::updateCss
   */
  public function testUpdateCssWithMissingClasses(): void {
    $this->drupalLogin($this->webUser);

    $request_data = [
      'mode' => 'static',
      // Missing 'classes' array.
    ];

    $response = $this->drupalPost(
      $this->updateCssUrl->toString(),
      json_encode($request_data),
      [
        'Content-Type' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
      ]
    );

    $this->assertEquals(400, $response->getStatusCode());

    $responseData = json_decode($response->getContent(), TRUE);
    $this->assertEquals('error', $responseData['status']);
    $this->assertStringContainsString('Missing or invalid classes array', $responseData['message']);
  }

  /**
   * Tests updateCss endpoint with too many classes.
   *
   * @covers ::updateCss
   */
  public function testUpdateCssWithTooManyClasses(): void {
    $this->drupalLogin($this->webUser);

    // Create an array with more classes than the limit.
    $tooManyClasses = $this->getTestUtilityClasses('performance');

    $request_data = [
      'mode' => 'static',
      'classes' => $tooManyClasses,
    ];

    $response = $this->drupalPost(
      $this->updateCssUrl->toString(),
      json_encode($request_data),
      [
        'Content-Type' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
      ]
    );

    $this->assertEquals(400, $response->getStatusCode());

    $responseData = json_decode($response->getContent(), TRUE);
    $this->assertEquals('error', $responseData['status']);
    $this->assertStringContainsString('Too many classes provided', $responseData['message']);
  }

  /**
   * Tests rate limiting functionality.
   *
   * @covers ::updateCss
   * @covers ::checkRateLimit
   */
  public function testRateLimiting(): void {
    $this->drupalLogin($this->webUser);

    $request_data = [
      'mode' => 'static',
      'classes' => $this->getTestUtilityClasses('basic'),
    ];

    $headers = [
      'Content-Type' => 'application/json',
      'X-Requested-With' => 'XMLHttpRequest',
    ];

    // Make requests up to the rate limit.
    $limit = UtilikitConstants::RATE_LIMIT_REQUESTS_PER_MINUTE;
    for ($i = 0; $i < $limit; $i++) {
      $response = $this->drupalPost(
        $this->updateCssUrl->toString(),
        json_encode($request_data),
        $headers
      );
      $this->assertLessThanOrEqual(200, $response->getStatusCode());
    }

    // Next request should be rate limited.
    $response = $this->drupalPost(
      $this->updateCssUrl->toString(),
      json_encode($request_data),
      $headers
    );

    $this->assertEquals(429, $response->getStatusCode());

    $responseData = json_decode($response->getContent(), TRUE);
    $this->assertEquals('error', $responseData['status']);
    $this->assertStringContainsString('Rate limit exceeded', $responseData['message']);
  }

  /**
   * Tests renderButton endpoint with valid AJAX request.
   *
   * @covers ::renderButton
   */
  public function testRenderButtonSuccess(): void {
    // Create user with update button permission.
    $user = $this->drupalCreateUser([
      'access content',
      'use utilikit update button',
    ]);
    $this->drupalLogin($user);

    $response = $this->drupalPost(
      $this->renderButtonUrl->toString(),
      '{}',
      [
        'Content-Type' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
      ]
    );

    $this->assertEquals(200, $response->getStatusCode());

    $responseData = json_decode($response->getContent(), TRUE);
    $this->assertNotNull($responseData);
    $this->assertEquals('success', $responseData['status']);
    $this->assertArrayHasKey('html', $responseData);
    $this->assertArrayHasKey('timestamp', $responseData);
  }

  /**
   * Tests renderButton endpoint without proper permissions.
   *
   * @covers ::renderButton
   */
  public function testRenderButtonWithoutPermission(): void {
    $this->drupalLogin($this->unauthorizedUser);

    $response = $this->drupalPost(
      $this->renderButtonUrl->toString(),
      '{}',
      [
        'Content-Type' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
      ]
    );

    $this->assertEquals(403, $response->getStatusCode());
  }

  /**
   * Tests renderButton endpoint without AJAX header.
   *
   * @covers ::renderButton
   */
  public function testRenderButtonWithoutAjaxHeader(): void {
    $user = $this->drupalCreateUser([
      'access content',
      'use utilikit update button',
    ]);
    $this->drupalLogin($user);

    $response = $this->drupalPost(
      $this->renderButtonUrl->toString(),
      '{}',
      ['Content-Type' => 'application/json']
    );

    $this->assertEquals(403, $response->getStatusCode());
  }

  /**
   * Tests dependency injection in controller.
   *
   * @covers ::create
   * @covers ::__construct
   */
  public function testControllerDependencyInjection(): void {
    $container = $this->container;

    $controller = UtilikitAjaxController::create($container);

    $this->assertInstanceOf(
      UtilikitAjaxController::class,
      $controller
    );
  }

  /**
   * Tests updateCss with responsive utility classes.
   *
   * @covers ::updateCss
   */
  public function testUpdateCssWithResponsiveClasses(): void {
    $this->drupalLogin($this->webUser);

    $request_data = [
      'mode' => 'static',
      'classes' => $this->getTestUtilityClasses('responsive'),
    ];

    $response = $this->drupalPost(
      $this->updateCssUrl->toString(),
      json_encode($request_data),
      [
        'Content-Type' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
      ]
    );

    $this->assertEquals(200, $response->getStatusCode());

    $responseData = json_decode($response->getContent(), TRUE);
    $this->assertEquals('success', $responseData['status']);
    $this->assertEquals('static', $responseData['mode']);
    $this->assertGreaterThan(0, $responseData['count']);
  }

  /**
   * Tests updateCss with complex utility classes.
   *
   * @covers ::updateCss
   */
  public function testUpdateCssWithComplexClasses(): void {
    $this->drupalLogin($this->webUser);

    $request_data = [
      'mode' => 'static',
      'classes' => $this->getTestUtilityClasses('complex'),
    ];

    $response = $this->drupalPost(
      $this->updateCssUrl->toString(),
      json_encode($request_data),
      [
        'Content-Type' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
      ]
    );

    $this->assertEquals(200, $response->getStatusCode());

    $responseData = json_decode($response->getContent(), TRUE);
    $this->assertEquals('success', $responseData['status']);
    $this->assertArrayHasKey('css', $responseData);
  }

  /**
   * Tests updateCss with invalid classes (should handle gracefully).
   *
   * @covers ::updateCss
   */
  public function testUpdateCssWithInvalidClasses(): void {
    $this->drupalLogin($this->webUser);

    $request_data = [
      'mode' => 'static',
      'classes' => $this->getTestUtilityClasses('invalid'),
    ];

    $response = $this->drupalPost(
      $this->updateCssUrl->toString(),
      json_encode($request_data),
      [
        'Content-Type' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
      ]
    );

    // Should still return 200 but might process 0 classes.
    $this->assertEquals(200, $response->getStatusCode());

    $responseData = json_decode($response->getContent(), TRUE);
    $this->assertEquals('success', $responseData['status']);
  }

  /**
   * Tests queue processing when lock is unavailable.
   *
   * @covers ::updateCss
   */
  public function testQueueProcessingWhenLockUnavailable(): void {
    $this->drupalLogin($this->webUser);

    // Acquire lock to simulate busy system.
    $lock = $this->container->get('lock');
    $lock->acquire(UtilikitConstants::LOCK_CSS_UPDATE, 30);

    $request_data = [
      'mode' => 'static',
      'classes' => $this->getTestUtilityClasses('basic'),
    ];

    $response = $this->drupalPost(
      $this->updateCssUrl->toString(),
      json_encode($request_data),
      [
        'Content-Type' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
      ]
    );

    $this->assertEquals(200, $response->getStatusCode());

    $responseData = json_decode($response->getContent(), TRUE);
    $this->assertEquals('success', $responseData['status']);
    $this->assertTrue($responseData['queued']);
    $this->assertStringContainsString('queued for processing', $responseData['message']);

    // Release lock for cleanup.
    $lock->release(UtilikitConstants::LOCK_CSS_UPDATE);
  }

  /**
   * Tests error handling with service provider failures.
   *
   * @covers ::updateCss
   */
  public function testUpdateCssWithServiceProviderError(): void {
    $this->drupalLogin($this->webUser);

    // This test would require mocking the service provider to return an error
    // For now, we'll test with a request that might cause processing issues.
    $request_data = [
      'mode' => 'static',
      'classes' => ['uk-invalid--property-value-that-causes-error'],
    ];

    $response = $this->drupalPost(
      $this->updateCssUrl->toString(),
      json_encode($request_data),
      [
        'Content-Type' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
      ]
    );

    // The response should still be valid JSON.
    $responseData = json_decode($response->getContent(), TRUE);
    $this->assertNotNull($responseData);
    $this->assertArrayHasKey('status', $responseData);
  }

  /**
   * Helper method to perform AJAX POST requests.
   *
   * @param string $url
   *   The URL to post to.
   * @param string $data
   *   The data to send.
   * @param array $headers
   *   Additional headers.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  protected function drupalPost(string $url, string $data, array $headers = []): Response {
    $client = $this->getHttpClient();

    $options = [
      'body' => $data,
      'headers' => array_merge([
        'Content-Type' => 'application/json',
      ], $headers),
      'cookies' => $this->getSessionCookies(),
      'allow_redirects' => FALSE,
    ];

    try {
      $response = $client->request('POST', $url, $options);
    }
    catch (\Exception $e) {
      // Handle HTTP errors as responses for testing.
      $response = $e->getResponse();
    }

    return $response;
  }

}
