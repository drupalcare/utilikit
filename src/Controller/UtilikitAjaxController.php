<?php

declare(strict_types=1);

namespace Drupal\utilikit\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Render\RendererInterface;
use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\utilikit\Service\UtilikitServiceProvider;
use Drupal\utilikit\Traits\ResponseHelperTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides AJAX endpoints for UtiliKit CSS processing.
 *
 * This controller handles AJAX requests for CSS generation, update button
 * rendering, and mode switching. It implements security measures including
 * rate limiting, CSRF protection, and input validation to ensure safe
 * operation in production environments.
 *
 * @package Drupal\utilikit\Controller
 */
class UtilikitAjaxController extends ControllerBase {

  use ResponseHelperTrait;

  /**
   * Constructs a new UtilikitAjaxController object.
   *
   * @param \Drupal\utilikit\Service\UtilikitServiceProvider $serviceProvider
   *   The UtiliKit service provider for CSS operations.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend service for preventing concurrent operations.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory for handling deferred CSS processing.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service for generating HTML output.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service for recording operations and errors.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service for timestamp operations.
   */
  public function __construct(
    protected readonly UtilikitServiceProvider $serviceProvider,
    protected readonly LockBackendInterface $lock,
    protected readonly QueueFactory $queueFactory,
    protected readonly RendererInterface $renderer,
    protected readonly LoggerInterface $logger,
    protected readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('utilikit.service_provider'),
      $container->get('lock'),
      $container->get('queue'),
      $container->get('renderer'),
      $container->get('logger.channel.utilikit'),
      $container->get('datetime.time'),
    );
  }

  /**
   * Checks rate limiting for the current request IP address.
   *
   * Implements IP-based rate limiting to prevent abuse of AJAX endpoints.
   * Tracks request counts per IP within a time window and enforces limits
   * defined in UtilikitConstants.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current HTTP request containing client IP information.
   *
   * @return bool
   *   TRUE if request is within rate limits, FALSE if limit exceeded.
   *
   * @see \Drupal\utilikit\Service\UtilikitConstants::RATE_LIMIT_REQUESTS_PER_MINUTE
   * @see \Drupal\utilikit\Service\UtilikitConstants::RATE_LIMIT_WINDOW_SECONDS
   */
  private function checkRateLimit(Request $request): bool {
    $ip = $request->getClientIp();
    $lock_name = 'utilikit_rate_limit:' . $ip;

    // Try to acquire lock. Wait up to 500ms for concurrent rate-check
    // from same IP to finish, so we can properly read/increment the counter.
    if (!$this->lock->acquire($lock_name, 0.5)) {
      // Lock still held after waiting — reject the request to prevent
      // rate limit bypass via intentional lock contention.
      $this->logger->warning('Rate limit lock contention for IP @ip - rejecting request', [
        '@ip' => $ip,
      ]);
      return FALSE;
    }

    try {
      $stateManager = $this->serviceProvider->getStateManager();
      $rateLimitData = $stateManager->getRateLimitData($ip);

      // Use Drupal's time service for testability.
      $now = $this->time->getRequestTime();

      // Check if we need to reset the window.
      if ($now > $rateLimitData['reset_time']) {
        $stateManager->setRateLimitData(
          $ip,
          1,
          $now + UtilikitConstants::RATE_LIMIT_WINDOW_SECONDS
        );
        return TRUE;
      }

      // Check if over limit.
      if ($rateLimitData['current'] >= UtilikitConstants::RATE_LIMIT_REQUESTS_PER_MINUTE) {
        $this->logger->warning('Rate limit exceeded for IP @ip: @current requests', [
          '@ip' => $ip,
          '@current' => $rateLimitData['current'],
        ]);
        return FALSE;
      }

      // Increment counter.
      $stateManager->setRateLimitData(
        $ip,
        $rateLimitData['current'] + 1,
        $rateLimitData['reset_time']
      );

      return TRUE;
    }
    finally {
      $this->lock->release($lock_name);
    }
  }

  /**
   * Updates CSS by processing new utility classes via AJAX.
   *
   * This method handles AJAX requests to process new UtiliKit utility classes
   * and regenerate CSS files in static mode, or return success response for
   * inline mode. Rate limiting and security validation are enforced.
   *
   * Security measures implemented:
   * - AJAX-only access validation
   * - Rate limiting per IP address
   * - JSON input validation
   * - Class count limits
   * - Lock-based concurrency control
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object containing utility classes to process.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response containing:
   *   - status: 'success' or 'error'
   *   - message: Status message for user feedback
   *   - mode: Current rendering mode ('static' or 'inline')
   *   - count: Number of classes processed (static mode only)
   *   - css: Generated CSS content (static mode only)
   *   - timestamp: Update timestamp for cache busting
   *   - queued: TRUE if request was queued (when lock unavailable)
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when request is not AJAX or rate limit exceeded.
   *
   * @see \Drupal\utilikit\Service\UtilikitConstants::RATE_LIMIT_REQUESTS_PER_MINUTE
   * @see \Drupal\utilikit\Service\UtilikitConstants::MAX_CLASSES_PER_REQUEST
   * @see \Drupal\utilikit\Service\UtilikitConstants::LOCK_CSS_UPDATE
   */
  public function updateCss(Request $request): JsonResponse {
    try {
      // Validate AJAX request to prevent direct access.
      if (!$request->isXmlHttpRequest()) {
        throw new AccessDeniedHttpException('AJAX requests only.');
      }

      // Enforce rate limiting to prevent abuse.
      if (!$this->checkRateLimit($request)) {
        $this->logger->warning('Rate limit exceeded for IP: @ip', [
          '@ip' => $request->getClientIp(),
        ]);
        return $this->createErrorResponse(
          'Rate limit exceeded. Please wait a moment.',
          429
        );
      }

      // Parse and validate JSON request data.
      try {
        $data = json_decode(
          $request->getContent(),
          TRUE,
          512,
          JSON_THROW_ON_ERROR
        );
      }
      catch (\JsonException $e) {
        $this->logger->warning('Invalid JSON in CSS update request: @error', [
          '@error' => $e->getMessage(),
        ]);
        return $this->createErrorResponse('Invalid JSON data.', 400);
      }

      if (!is_array($data)) {
        return $this->createErrorResponse('Request data must be an object.', 400);
      }

      // Determine rendering mode from request or configuration.
      $config = $this->config('utilikit.settings');
      $mode = $data['mode'] ?? $config->get('rendering_mode') ?? 'inline';

      if (!in_array($mode, ['inline', 'static', 'head'], TRUE)) {
        return $this->createErrorResponse('Invalid rendering mode.', 400);
      }

      // Handle inline mode (no CSS file generation needed).
      if ($mode === 'inline') {
        $this->logger->info('CSS update processed in inline mode.');
        return $this->createSuccessResponse(
          'Inline mode active - styles applied dynamically.',
          [
            'mode' => 'inline',
            'timestamp' => $this->time->getRequestTime(),
          ]
        );
      }

      // Validate classes array for static mode processing.
      if (!isset($data['classes'])) {
        return $this->createErrorResponse('Missing classes array.', 400);
      }

      if (!is_array($data['classes'])) {
        return $this->createErrorResponse('Classes must be an array.', 400);
      }

      // Validate that all class entries are strings to prevent type confusion.
      $data['classes'] = array_values(array_filter($data['classes'], 'is_string'));

      // Enforce class count limits to prevent resource exhaustion.
      $classCount = count($data['classes']);
      if ($classCount > UtilikitConstants::MAX_CLASSES_PER_REQUEST) {
        $this->logger->warning('Too many classes in CSS update request: @count', [
          '@count' => $classCount,
        ]);
        return $this->createErrorResponse(
          'Too many classes provided. Maximum: ' . UtilikitConstants::MAX_CLASSES_PER_REQUEST,
          400
        );
      }

      // Log processing attempt for debugging.
      $this->logger->info('Processing CSS update with @count classes in @mode mode.', [
        '@count' => $classCount,
        '@mode' => $mode,
      ]);

      // Acquire lock to prevent concurrent CSS generation.
      if (!$this->lock->acquire(UtilikitConstants::LOCK_CSS_UPDATE, UtilikitConstants::CSS_UPDATE_LOCK_TIMEOUT)) {
        // If lock unavailable, queue the request for later processing.
        $this->queueCssUpdate($data['classes']);
        $this->logger->info('CSS update queued due to concurrent processing.');
        return $this->createSuccessResponse(
          'CSS update queued for processing.',
          [
            'mode' => 'static',
            'queued' => TRUE,
            'timestamp' => $this->time->getRequestTime(),
          ]
        );
      }

      try {
        // Process based on mode.
        if ($mode === 'head') {
          // Head mode: update state only, no file.
          $validClasses = $this->serviceProvider->getContentScanner()->validateUtilityClasses($data['classes']);

          if (!empty($validClasses)) {
            $allClasses = $this->serviceProvider->getStateManager()->addKnownClasses($validClasses);
            $css = $this->serviceProvider->getCssGenerator()->generateCssFromClasses($allClasses);

            // Apply optimization if enabled.
            if ($config->get('optimize_css')) {
              $css = $this->serviceProvider->getFileManager()->minifyCss($css);
            }

            $this->serviceProvider->getStateManager()->setGeneratedCss($css);
            $this->serviceProvider->getStateManager()->updateCssTimestamp();
            $this->serviceProvider->getCacheManager()->clearCssCaches();
          }

          $this->logger->info('CSS updated in head mode with @count classes.', [
            '@count' => count($validClasses ?? []),
          ]);

          return $this->createSuccessResponse(
            'CSS updated successfully in head mode.',
            [
              'mode' => 'head',
              'count' => count($validClasses ?? []),
              'css' => $css ?? '',
              'timestamp' => $this->time->getRequestTime(),
            ]
          );
        }

        // Add any new classes from the request to known classes (static mode).
        if (!empty($data['classes'])) {
          $stateManager = $this->serviceProvider->getStateManager();
          $beforeCount = count($stateManager->getKnownClasses());
          $stateManager->addKnownClasses($data['classes']);
          $afterCount = count($stateManager->getKnownClasses());

          if ($afterCount > $beforeCount) {
            $this->logger->info('Added @new new classes (total: @total)', [
              '@new' => $afterCount - $beforeCount,
              '@total' => $afterCount,
            ]);
          }
        }

        // Regenerate CSS from all known classes.
        $result = $this->serviceProvider->regenerateStaticCss();

        if ($result) {
          // Get current state for response.
          $stateManager = $this->serviceProvider->getStateManager();
          $knownClasses = $stateManager->getKnownClasses();
          $generatedCss = $stateManager->getGeneratedCss();
          $timestamp = $stateManager->getCssTimestamp();

          $this->logger->info('CSS updated successfully with @count total classes.', [
            '@count' => count($knownClasses),
          ]);

          return $this->createSuccessResponse(
            'CSS updated successfully.',
            [
              'mode' => 'static',
              'count' => count($knownClasses),
              'css' => $generatedCss ?: '',
              'timestamp' => $timestamp,
            ]
          );
        }
        else {
          $this->logger->warning('CSS regeneration failed - no utility classes found.');
          return $this->createErrorResponse(
            'No utility classes found. Add UtiliKit classes to your content first.',
            400
          );
        }
      }
      finally {
        // Always release the lock to prevent deadlocks.
        $this->lock->release(UtilikitConstants::LOCK_CSS_UPDATE);
        $this->processQueuedUpdates();
      }
    }
    catch (AccessDeniedHttpException $e) {
      $this->logger->warning('Access denied for CSS update request from IP: @ip', [
        '@ip' => $request->getClientIp(),
      ]);
      return $this->createErrorResponse('Access denied.', 403);
    }
    catch (\Exception $e) {
      // Log detailed error for debugging while returning generic message.
      $this->logger->error('CSS update failed with exception: @message', [
        '@message' => $e->getMessage(),
        '@trace' => $e->getTraceAsString(),
      ]);

      return $this->createErrorResponse(
        'An error occurred while updating CSS. Please try again.',
        500
      );
    }
  }

  /**
   * Processes queued CSS updates in batches.
   *
   * Handles deferred CSS processing for requests that couldn't acquire
   * the processing lock immediately. Processes items in batches to
   * prevent memory exhaustion and ensure responsive operation.
   *
   * @return void
   *   This method does not return a value but processes queued updates
   *   as a side effect, updating the CSS state and regenerating files.
   */
  private function processQueuedUpdates(): void {
    $queue = $this->queueFactory->get(UtilikitConstants::QUEUE_CSS_PROCESSOR);
    $stateManager = $this->serviceProvider->getStateManager();

    $processed = 0;

    // Process queue items up to batch limit.
    while ($item = $queue->claimItem()) {
      if ($processed >= UtilikitConstants::QUEUE_PROCESSING_LIMIT) {
        $queue->releaseItem($item);
        break;
      }

      try {
        $stateManager->addKnownClasses($item->data['classes']);
        $queue->deleteItem($item);
        $processed++;
      }
      catch (\Exception $e) {
        $queue->releaseItem($item);
        $this->logger->error(
          'Failed to process queued update: @message',
          ['@message' => $e->getMessage()]
        );
        break;
      }
    }

    // Regenerate static CSS if any items were processed.
    if ($processed > 0) {
      $this->serviceProvider->regenerateStaticCss();
    }
  }

  /**
   * Queues CSS update for deferred processing.
   *
   * When the CSS update lock cannot be acquired immediately, this method
   * queues the classes for later processing to ensure eventual consistency.
   *
   * @param array $classes
   *   The utility classes to queue for processing.
   *
   * @return void
   *   This method does not return a value but queues the CSS update
   *   as a side effect for later background processing.
   */
  private function queueCssUpdate(array $classes): void {
    // Validate classes before queuing to prevent invalid data from being
    // processed later when lock context may differ.
    $validClasses = $this->serviceProvider->getContentScanner()->validateUtilityClasses($classes);
    if (empty($validClasses)) {
      return;
    }

    $queue = $this->queueFactory->get(UtilikitConstants::QUEUE_CSS_PROCESSOR);

    $queue->createItem([
      'classes' => $validClasses,
      'timestamp' => $this->time->getRequestTime(),
    ]);
  }

  /**
   * Renders the update button for authorized users.
   *
   * Provides HTML for the UtiliKit update button that allows users with
   * appropriate permissions to trigger CSS regeneration. Only rendered
   * in static mode and for users with 'use utilikit update button' permission.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The rendered button HTML or empty response if not authorized.
   */
  public function renderButton(): Response {
    // Verify user has permission to use update button.
    if (!$this->currentUser()->hasPermission('use utilikit update button')) {
      return new Response('', 403);
    }

    // Only render button in static and head modes.
    $config = $this->config('utilikit.settings');
    $renderingMode = $config->get('rendering_mode');
    if (!in_array($renderingMode, ['static', 'head'], TRUE)) {
      return new Response('', 204);
    }

    $build = [
      '#theme' => 'utilikit_update_button',
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => [UtilikitConstants::CACHE_TAG_CONFIG],
      ],
    ];

    $html = $this->renderer->renderPlain($build);

    return new Response((string) $html, 200, [
      'Content-Type' => 'text/html',
    ]);
  }

}
