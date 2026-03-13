<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\utilikit\Traits\UtilikitTestCleanupTrait;
use GuzzleHttp\Cookie\CookieJar;
use Psr\Http\Message\ResponseInterface;

/**
 * Base class for UtiliKit functional tests.
 *
 * Provides automatic cleanup following Drupal best practices.
 */
abstract class UtilikitFunctionalTestBase extends BrowserTestBase {
  use UtilikitTestCleanupTrait;

  /**
   * The last JSON POST response object.
   *
   * @var \Psr\Http\Message\ResponseInterface|null
   */
  protected ?ResponseInterface $lastJsonResponse = NULL;

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Drupal's BrowserTestBase already provides test isolation,
    // but we clean up our specific artifacts.
    $this->cleanupUtilikitArtifacts();

    parent::tearDown();
  }

  /**
   * Performs a POST request with JSON data and returns the response body.
   *
   * Uses the Mink session cookies so requests are authenticated.
   *
   * @param string $path
   *   The URL path to post to.
   * @param array $data
   *   The data to encode as JSON and send.
   * @param array $headers
   *   Optional additional headers.
   *
   * @return string|null
   *   The response body content, or NULL on failure.
   */
  protected function drupalPostJson(string $path, array $data, array $headers = []): ?string {
    $url = $this->buildUrl($path);
    $requestHeaders = array_merge([
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
    ], $headers);

    // Build a cookie jar from the Mink session cookies so the Guzzle request
    // is authenticated with the same session as drupalLogin().
    $cookieJar = $this->getSessionCookieJar();

    $client = $this->getHttpClient();
    try {
      $this->lastJsonResponse = $client->post($url, [
        'json' => $data,
        'headers' => $requestHeaders,
        'cookies' => $cookieJar,
        'http_errors' => FALSE,
      ]);

      return $this->lastJsonResponse->getBody()->getContents();
    }
    catch (\Exception $e) {
      $this->lastJsonResponse = NULL;
      return NULL;
    }
  }

  /**
   * Gets the HTTP status code from the last drupalPostJson() call.
   *
   * @return int
   *   The HTTP status code, or 0 if no response is available.
   */
  protected function getLastJsonResponseStatusCode(): int {
    return $this->lastJsonResponse ? $this->lastJsonResponse->getStatusCode() : 0;
  }

  /**
   * Creates a Guzzle CookieJar from the current Mink session cookies.
   *
   * @return \GuzzleHttp\Cookie\CookieJar
   *   A cookie jar containing the browser session cookies.
   */
  protected function getSessionCookieJar(): CookieJar {
    $cookies = $this->getSession()->getDriver()->getClient()->getCookieJar()->all();
    $cookieArray = [];
    $domain = parse_url($this->baseUrl, PHP_URL_HOST) ?: 'localhost';

    foreach ($cookies as $cookie) {
      $cookieArray[$cookie->getName()] = $cookie->getValue();
    }

    return CookieJar::fromArray($cookieArray, $domain);
  }

}
