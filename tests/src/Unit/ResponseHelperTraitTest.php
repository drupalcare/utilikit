<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Traits\ResponseHelperTrait;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Tests the ResponseHelperTrait.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Traits\ResponseHelperTrait
 */
class ResponseHelperTraitTest extends UnitTestCase {

  /**
   * Mock class using the trait.
   *
   * @var object
   */
  protected $traitObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create anonymous class using the trait.
    $this->traitObject = new class() {
      use ResponseHelperTrait;
    };
  }

  /**
   * Tests createJsonResponse method.
   *
   * @covers ::createJsonResponse
   * @dataProvider providerJsonResponse
   */
  public function testCreateJsonResponse($status, $message, $data, $statusCode, $expected) {
    $response = $this->traitObject->createJsonResponse($status, $message, $data, $statusCode);

    // Verify it's a JsonResponse.
    $this->assertInstanceOf(JsonResponse::class, $response);

    // Verify status code.
    $this->assertEquals($statusCode, $response->getStatusCode());

    // Verify content.
    $content = json_decode($response->getContent(), TRUE);
    $this->assertEquals($expected, $content);

    // Verify headers.
    $this->assertEquals('application/json', $response->headers->get('Content-Type'));
  }

  /**
   * Data provider for createJsonResponse tests.
   */
  public static function providerJsonResponse(): array {
    return [
      'basic success' => [
        'success',
        'Operation completed',
        [],
        200,
        [
          'status' => 'success',
          'message' => 'Operation completed',
        ],
      ],
      'success with data' => [
        'success',
        'Data retrieved',
        ['count' => 5, 'items' => ['a', 'b', 'c']],
        200,
        [
          'status' => 'success',
          'message' => 'Data retrieved',
          'count' => 5,
          'items' => ['a', 'b', 'c'],
        ],
      ],
      'error response' => [
        'error',
        'Something went wrong',
        ['code' => 'ERR_001'],
        500,
        [
          'status' => 'error',
          'message' => 'Something went wrong',
          'code' => 'ERR_001',
        ],
      ],
      'custom status code' => [
        'partial',
        'Partially completed',
        ['processed' => 50, 'total' => 100],
        206,
        [
          'status' => 'partial',
          'message' => 'Partially completed',
          'processed' => 50,
          'total' => 100,
        ],
      ],
    ];
  }

  /**
   * Tests createSuccessResponse method.
   *
   * @covers ::createSuccessResponse
   */
  public function testCreateSuccessResponse() {
    // Test without data.
    $response = $this->traitObject->createSuccessResponse('All good!');

    $this->assertInstanceOf(JsonResponse::class, $response);
    $this->assertEquals(200, $response->getStatusCode());

    $content = json_decode($response->getContent(), TRUE);
    $this->assertEquals([
      'status' => 'success',
      'message' => 'All good!',
    ], $content);

    // Test with data.
    $response = $this->traitObject->createSuccessResponse('Data saved', [
      'id' => 123,
      'created' => '2025-01-01',
    ]);

    $content = json_decode($response->getContent(), TRUE);
    $this->assertEquals([
      'status' => 'success',
      'message' => 'Data saved',
      'id' => 123,
      'created' => '2025-01-01',
    ], $content);
  }

  /**
   * Tests createErrorResponse method.
   *
   * @covers ::createErrorResponse
   */
  public function testCreateErrorResponse() {
    // Test with default status code.
    $response = $this->traitObject->createErrorResponse('Failed to process');

    $this->assertInstanceOf(JsonResponse::class, $response);
    $this->assertEquals(500, $response->getStatusCode());

    $content = json_decode($response->getContent(), TRUE);
    $this->assertEquals([
      'status' => 'error',
      'message' => 'Failed to process',
    ], $content);

    // Test with custom status code.
    $response = $this->traitObject->createErrorResponse('Not found', 404);
    $this->assertEquals(404, $response->getStatusCode());

    // Test with additional data.
    $response = $this->traitObject->createErrorResponse('Validation failed', 400, [
      'errors' => [
        'field1' => 'Required',
        'field2' => 'Invalid format',
      ],
    ]);

    $content = json_decode($response->getContent(), TRUE);
    $this->assertEquals([
      'status' => 'error',
      'message' => 'Validation failed',
      'errors' => [
        'field1' => 'Required',
        'field2' => 'Invalid format',
      ],
    ], $content);
  }

  /**
   * Tests createLockedResponse method.
   *
   * @covers ::createLockedResponse
   */
  public function testCreateLockedResponse() {
    // Test with default retry time.
    $response = $this->traitObject->createLockedResponse('Resource is locked');

    $this->assertInstanceOf(JsonResponse::class, $response);
    // Default is 200.
    $this->assertEquals(200, $response->getStatusCode());

    $content = json_decode($response->getContent(), TRUE);
    $this->assertEquals([
      'status' => 'locked',
      'message' => 'Resource is locked',
      'retry_after' => 2,
    ], $content);

    // Test with custom retry time.
    $response = $this->traitObject->createLockedResponse('Please wait', 5);

    $content = json_decode($response->getContent(), TRUE);
    $this->assertEquals([
      'status' => 'locked',
      'message' => 'Please wait',
      'retry_after' => 5,
    ], $content);
  }

  /**
   * Tests response encoding.
   *
   * @covers ::createJsonResponse
   */
  public function testResponseEncoding() {
    // Test with special characters.
    $response = $this->traitObject->createSuccessResponse('UTF-8 test: café, 日本語');

    $content = $response->getContent();
    $this->assertStringContainsString('café', $content);
    $this->assertStringContainsString('日本語', $content);

    // Verify proper JSON encoding.
    $decoded = json_decode($content, TRUE);
    $this->assertEquals('UTF-8 test: café, 日本語', $decoded['message']);
  }

  /**
   * Tests response with complex data structures.
   *
   * @covers ::createJsonResponse
   */
  public function testComplexDataStructures() {
    $complexData = [
      'nested' => [
        'level1' => [
          'level2' => [
            'value' => 'deep',
          ],
        ],
      ],
      'array' => [1, 2, 3, 4, 5],
      'mixed' => [
        'string' => 'text',
        'number' => 123.45,
        'boolean' => TRUE,
        'null' => NULL,
      ],
    ];

    $response = $this->traitObject->createSuccessResponse('Complex data', $complexData);

    $content = json_decode($response->getContent(), TRUE);
    $this->assertEquals('deep', $content['nested']['level1']['level2']['value']);
    $this->assertEquals([1, 2, 3, 4, 5], $content['array']);
    $this->assertTrue($content['mixed']['boolean']);
    $this->assertNull($content['mixed']['null']);
  }

  /**
   * Tests HTTP status codes.
   *
   * @covers ::createJsonResponse
   * @dataProvider providerStatusCodes
   */
  public function testHttpStatusCodes($statusCode) {
    $response = $this->traitObject->createJsonResponse('test', 'Testing status', [], $statusCode);
    $this->assertEquals($statusCode, $response->getStatusCode());
  }

  /**
   * Data provider for status code tests.
   */
  public static function providerStatusCodes(): array {
    return [
      'OK' => [200],
      'Created' => [201],
      'Accepted' => [202],
      'No Content' => [204],
      'Bad Request' => [400],
      'Unauthorized' => [401],
      'Forbidden' => [403],
      'Not Found' => [404],
      'Method Not Allowed' => [405],
      'Conflict' => [409],
      'Unprocessable Entity' => [422],
      'Too Many Requests' => [429],
      'Internal Server Error' => [500],
      'Bad Gateway' => [502],
      'Service Unavailable' => [503],
    ];
  }

  /**
   * Tests empty data handling.
   *
   * @covers ::createJsonResponse
   */
  public function testEmptyDataHandling() {
    // Empty array.
    $response = $this->traitObject->createSuccessResponse('Empty data', []);
    $content = json_decode($response->getContent(), TRUE);
    // No numeric keys.
    $this->assertArrayNotHasKey('0', $content);

    // Null values in data.
    $response = $this->traitObject->createSuccessResponse('With nulls', [
      'value1' => NULL,
      'value2' => '',
      'value3' => 0,
      'value4' => FALSE,
    ]);

    $content = json_decode($response->getContent(), TRUE);
    $this->assertNull($content['value1']);
    $this->assertEquals('', $content['value2']);
    $this->assertEquals(0, $content['value3']);
    $this->assertFalse($content['value4']);
  }

  /**
   * Tests response headers.
   *
   * @covers ::createJsonResponse
   */
  public function testResponseHeaders() {
    $response = $this->traitObject->createSuccessResponse('Test');

    // Check Content-Type header.
    $this->assertEquals('application/json', $response->headers->get('Content-Type'));

    // Check that response is not cached by default.
    $this->assertNull($response->headers->get('Cache-Control'));
  }

  /**
   * Tests method chaining and reusability.
   */
  public function testMethodReusability() {
    // Create multiple responses to ensure no state pollution.
    $response1 = $this->traitObject->createSuccessResponse('First');
    $response2 = $this->traitObject->createErrorResponse('Second', 400);
    $response3 = $this->traitObject->createLockedResponse('Third', 10);

    // Verify each response is independent.
    $content1 = json_decode($response1->getContent(), TRUE);
    $content2 = json_decode($response2->getContent(), TRUE);
    $content3 = json_decode($response3->getContent(), TRUE);

    $this->assertEquals('success', $content1['status']);
    $this->assertEquals('error', $content2['status']);
    $this->assertEquals('locked', $content3['status']);

    $this->assertEquals(200, $response1->getStatusCode());
    $this->assertEquals(400, $response2->getStatusCode());
    $this->assertEquals(10, $content3['retry_after']);
  }

  /**
   * Tests edge cases.
   */
  public function testEdgeCases() {
    // Very long message.
    $longMessage = str_repeat('This is a very long message. ', 1000);
    $response = $this->traitObject->createSuccessResponse($longMessage);
    $content = json_decode($response->getContent(), TRUE);
    $this->assertEquals($longMessage, $content['message']);

    // Empty message.
    $response = $this->traitObject->createSuccessResponse('');
    $content = json_decode($response->getContent(), TRUE);
    $this->assertEquals('', $content['message']);

    // Zero retry time.
    $response = $this->traitObject->createLockedResponse('Locked', 0);
    $content = json_decode($response->getContent(), TRUE);
    $this->assertEquals(0, $content['retry_after']);
  }

  /**
   * Tests JSON encoding options.
   */
  public function testJsonEncodingOptions() {
    // Test with data that could be affected by JSON encoding options.
    $data = [
      'unicode' => '♥️ 🚀 Unicode',
      'slashes' => 'path/to/resource',
      'html' => '<p>HTML content</p>',
      'quotes' => 'He said "Hello"',
    ];

    $response = $this->traitObject->createSuccessResponse('Encoding test', $data);
    $content = $response->getContent();

    // Symfony's JsonResponse uses specific encoding options
    // Verify the content is properly encoded.
    $decoded = json_decode($content, TRUE);
    $this->assertEquals($data['unicode'], $decoded['unicode']);
    $this->assertEquals($data['slashes'], $decoded['slashes']);
    $this->assertEquals($data['html'], $decoded['html']);
    $this->assertEquals($data['quotes'], $decoded['quotes']);
  }

}
