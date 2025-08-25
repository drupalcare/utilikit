<?php

namespace Drupal\utilikit\Traits;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Trait providing standardized JSON response creation methods.
 *
 * This trait offers a collection of helper methods for creating consistent
 * JSON responses across UtiliKit controllers and services. It standardizes
 * response structure with common status indicators, messages, and HTTP
 * status codes for success, error, and locked states.
 */
trait ResponseHelperTrait {

  /**
   * Creates a standardized JSON response with custom status and data.
   *
   * This is the base method for creating all JSON responses with a consistent
   * structure including status, message, and optional additional data.
   *
   * @param string $status
   *   The response status indicator (e.g., 'success', 'error', 'locked').
   * @param string $message
   *   Human-readable message describing the response.
   * @param array $data
   *   Optional additional data to include in the response (e.g., counts,
   *   URLs, timestamps).
   * @param int $statusCode
   *   HTTP status code for the response (default: 200).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with standardized structure.
   */
  protected function createJsonResponse(string $status, string $message, array $data = [], int $statusCode = 200): JsonResponse {
    $response = [
      'status' => $status,
      'message' => $message,
    ] + $data;

    return new JsonResponse($response, $statusCode);
  }

  /**
   * Creates a success JSON response.
   *
   * Convenient method for creating successful operation responses with
   * HTTP 200 status code and 'success' status indicator.
   *
   * @param string $message
   *   Success message to display to the user.
   * @param array $data
   *   Optional additional data to include in the response (e.g., operation
   *   results, counts, generated URLs).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response indicating successful operation.
   */
  protected function createSuccessResponse(string $message, array $data = []): JsonResponse {
    return $this->createJsonResponse('success', $message, $data);
  }

  /**
   * Creates an error JSON response.
   *
   * Convenient method for creating error responses with appropriate HTTP
   * status codes and 'error' status indicator.
   *
   * @param string $message
   *   Error message to display to the user.
   * @param int $statusCode
   *   HTTP status code for the error (default: 500 Internal Server Error).
   * @param array $data
   *   Optional additional error data (e.g., error codes, debug information).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response indicating an error occurred.
   */
  protected function createErrorResponse(string $message, int $statusCode = 500, array $data = []): JsonResponse {
    return $this->createJsonResponse('error', $message, $data, $statusCode);
  }

  /**
   * Creates a locked resource JSON response.
   *
   * Convenient method for creating responses when a resource is locked or
   * temporarily unavailable, typically used during concurrent operations
   * that require exclusive access.
   *
   * @param string $message
   *   Message explaining why the resource is locked.
   * @param int $retryAfter
   *   Suggested number of seconds to wait before retrying the operation
   *   (default: 2 seconds).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response indicating resource is locked with retry suggestion.
   */
  protected function createLockedResponse(string $message, int $retryAfter = 2): JsonResponse {
    return $this->createJsonResponse('locked', $message, [
      'retry_after' => $retryAfter,
    ]);
  }

}
