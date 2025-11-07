<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Functional;

use GuzzleHttp\Promise\Utils;
use Drupal\Core\Url;
use Drupal\Tests\utilikit\Traits\UtilikitTestHelpers;
use Drupal\utilikit\Service\UtilikitConstants;

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
   */
  public function testUpdateCssValidRequestStaticMode(): void {
    $this->drupalLogin($this->webUser);

    // Create test classes.
    $testClasses = [
      'uk-pd--20',
      'uk-mg--10',
      'uk-bg--ff0000',
    ];

    // Get CSRF token.
    $csrfToken = $this->getSession()->getPage()->find('css', 'meta[name="csrf-token"]');

    // Make AJAX request.
    $response = $this->drupalPostJson($this->updateCssUrl->toString(), [
      'classes' => $testClasses,
    ], [
      'X-CSRF-Token' => $csrfToken ? $csrfToken->getAttribute('content') : '',
    ]);

    $this->assertNotNull($response);
    $data = json_decode($response, TRUE);

    // Verify response structure.
    $this->assertArrayHasKey('status', $data);
    $this->assertArrayHasKey('message', $data);
    $this->assertArrayHasKey('mode', $data);
    $this->assertArrayHasKey('classes_processed', $data);

    // Verify response data.
    $this->assertEquals('success', $data['status']);
    $this->assertEquals('static', $data['mode']);
    $this->assertEquals(3, $data['classes_processed']);
    $this->assertStringContainsString('processed', $data['message']);

    // Verify CSS file URL is provided in static mode.
    $this->assertArrayHasKey('css_url', $data);
    $this->assertNotEmpty($data['css_url']);
  }

  /**
   * Tests updateCss endpoint with valid AJAX request in inline mode.
   */
  public function testUpdateCssValidRequestInlineMode(): void {
    // Switch to inline mode.
    $this->config('utilikit.settings')
      ->set('rendering_mode', 'inline')
      ->save();

    $this->drupalLogin($this->webUser);

    $testClasses = ['uk-pd--30', 'uk-mg--20'];

    // Get CSRF token.
    $csrfToken = $this->getSession()->getPage()->find('css', 'meta[name="csrf-token"]');

    $response = $this->drupalPostJson($this->updateCssUrl->toString(), [
      'classes' => $testClasses,
    ], [
      'X-CSRF-Token' => $csrfToken ? $csrfToken->getAttribute('content') : '',
    ]);

    $data = json_decode($response, TRUE);

    $this->assertEquals('success', $data['status']);
    $this->assertEquals('inline', $data['mode']);
    $this->assertEquals(2, $data['classes_processed']);

    // In inline mode, CSS URL should not be provided.
    $this->assertArrayNotHasKey('css_url', $data);
  }

  /**
   * Tests updateCss endpoint with empty classes array.
   */
  public function testUpdateCssEmptyClasses(): void {
    $this->drupalLogin($this->webUser);

    $response = $this->drupalPostJson($this->updateCssUrl->toString(), [
      'classes' => [],
    ]);

    $data = json_decode($response, TRUE);

    $this->assertEquals('error', $data['status']);
    $this->assertStringContainsString('No classes provided', $data['message']);
  }

  /**
   * Tests updateCss endpoint with invalid classes.
   */
  public function testUpdateCssInvalidClasses(): void {
    $this->drupalLogin($this->webUser);

    $invalidClasses = [
      'not-a-utility-class',
      'uk-invalid',
      'random-class',
    ];

    $response = $this->drupalPostJson($this->updateCssUrl->toString(), [
      'classes' => $invalidClasses,
    ]);

    $data = json_decode($response, TRUE);

    // Should still succeed but process 0 valid classes.
    $this->assertEquals('success', $data['status']);
    $this->assertEquals(0, $data['classes_processed']);
  }

  /**
   * Tests updateCss endpoint rate limiting.
   */
  public function testUpdateCssRateLimit(): void {
    $this->drupalLogin($this->webUser);

    $testClasses = ['uk-pd--20'];
    $maxRequests = UtilikitConstants::RATE_LIMIT_REQUESTS_PER_MINUTE;

    // Make requests up to the limit.
    for ($i = 0; $i < $maxRequests; $i++) {
      $response = $this->drupalPostJson($this->updateCssUrl->toString(), [
        'classes' => $testClasses,
      ]);

      $data = json_decode($response, TRUE);
      $this->assertEquals('success', $data['status']);
    }

    // Next request should be rate limited.
    $response = $this->drupalPostJson($this->updateCssUrl->toString(), [
      'classes' => $testClasses,
    ]);

    $data = json_decode($response, TRUE);
    $this->assertEquals('locked', $data['status']);
    $this->assertArrayHasKey('retry_after', $data);
    $this->assertStringContainsString('Rate limit', $data['message']);
  }

  /**
   * Tests updateCss endpoint with too many classes.
   */
  public function testUpdateCssTooManyClasses(): void {
    $this->drupalLogin($this->webUser);

    // Create array exceeding max classes limit.
    $tooManyClasses = [];
    for ($i = 0; $i <= UtilikitConstants::MAX_CLASSES_PER_REQUEST; $i++) {
      $tooManyClasses[] = 'uk-pd--' . $i;
    }

    $response = $this->drupalPostJson($this->updateCssUrl->toString(), [
      'classes' => $tooManyClasses,
    ]);

    $data = json_decode($response, TRUE);
    $this->assertEquals('error', $data['status']);
    $this->assertStringContainsString('Too many classes', $data['message']);
  }

  /**
   * Tests updateCss endpoint without CSRF token.
   */
  public function testUpdateCssNoCsrfToken(): void {
    $this->drupalLogin($this->webUser);

    // Make request without CSRF token header.
    $client = $this->getHttpClient();
    $response = $client->post($this->updateCssUrl->toString(), [
      'json' => ['classes' => ['uk-pd--20']],
      'http_errors' => FALSE,
    ]);

    $this->assertEquals(403, $response->getStatusCode());
  }

  /**
   * Tests updateCss endpoint with unauthorized user.
   */
  public function testUpdateCssUnauthorized(): void {
    $this->drupalLogin($this->unauthorizedUser);

    $response = $this->drupalPostJson($this->updateCssUrl->toString(), [
      'classes' => ['uk-pd--20'],
    ]);

    // Should get 403 Forbidden.
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests updateCss endpoint as anonymous user.
   */
  public function testUpdateCssAnonymous(): void {
    $this->drupalLogout();

    $client = $this->getHttpClient();
    $response = $client->post($this->updateCssUrl->toString(), [
      'json' => ['classes' => ['uk-pd--20']],
      'http_errors' => FALSE,
    ]);

    $this->assertEquals(403, $response->getStatusCode());
  }

  /**
   * Tests renderButton endpoint success.
   */
  public function testRenderButtonSuccess(): void {
    $this->drupalLogin($this->webUser);

    $response = $this->drupalGet($this->renderButtonUrl->toString(), [
      'query' => ['ajax_form' => 1],
    ]);

    $data = json_decode($response, TRUE);

    $this->assertArrayHasKey('status', $data);
    $this->assertArrayHasKey('html', $data);
    $this->assertEquals('success', $data['status']);
    $this->assertStringContainsString('utilikit-update-button', $data['html']);
    $this->assertStringContainsString('button', $data['html']);
  }

  /**
   * Tests renderButton endpoint caching.
   */
  public function testRenderButtonCaching(): void {
    $this->drupalLogin($this->webUser);

    // First request.
    $response1 = $this->drupalGet($this->renderButtonUrl->toString(), [
      'query' => ['ajax_form' => 1],
    ]);
    $data1 = json_decode($response1, TRUE);

    // Second request should be cached.
    $response2 = $this->drupalGet($this->renderButtonUrl->toString(), [
      'query' => ['ajax_form' => 1],
    ]);
    $data2 = json_decode($response2, TRUE);

    // HTML should be identical.
    $this->assertEquals($data1['html'], $data2['html']);

    // Check cache headers.
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache', 'HIT');
  }

  /**
   * Tests AJAX response handling for malformed requests.
   */
  public function testMalformedRequest(): void {
    $this->drupalLogin($this->webUser);

    // Send malformed JSON.
    $client = $this->getHttpClient();
    $response = $client->post($this->updateCssUrl->toString(), [
      'body' => 'not-json-data',
      'headers' => [
        'Content-Type' => 'application/json',
        'X-CSRF-Token' => $this->getCsrfToken(),
      ],
      'http_errors' => FALSE,
    ]);

    $this->assertEquals(400, $response->getStatusCode());
  }

  /**
   * Tests concurrent request handling.
   */
  public function testConcurrentRequests(): void {
    $this->drupalLogin($this->webUser);

    // Simulate multiple concurrent requests.
    $promises = [];
    $client = $this->getHttpClient();

    for ($i = 0; $i < 5; $i++) {
      $promises[] = $client->postAsync($this->updateCssUrl->toString(), [
        'json' => ['classes' => ['uk-pd--' . $i]],
        'headers' => ['X-CSRF-Token' => $this->getCsrfToken()],
      ]);
    }

    // Wait for all requests to complete.
    $responses = Utils::settle($promises)->wait();

    // Verify all succeeded.
    foreach ($responses as $response) {
      $this->assertEquals('fulfilled', $response['state']);
      $data = json_decode($response['value']->getBody()->getContents(), TRUE);
      $this->assertEquals('success', $data['status']);
    }
  }

  /**
   * Tests error logging on CSS generation failure.
   */
  public function testCssGenerationFailure(): void {
    $this->drupalLogin($this->webUser);

    // Mock a service to throw exception.
    $this->container->set('utilikit.service_provider', new class() {

      /**
       *
       */
      public function processNewClasses($classes) {
        throw new \Exception('Test exception');
      }

    });

    $response = $this->drupalPostJson($this->updateCssUrl->toString(), [
      'classes' => ['uk-pd--20'],
    ]);

    $data = json_decode($response, TRUE);
    $this->assertEquals('error', $data['status']);
    $this->assertStringContainsString('Failed to process', $data['message']);
  }

  /**
   * Helper to get CSRF token.
   *
   * @return string
   *   The CSRF token.
   */
  protected function getCsrfToken(): string {
    $token = \Drupal::csrfToken()->get('utilikit-update-css');
    return $token;
  }

}
