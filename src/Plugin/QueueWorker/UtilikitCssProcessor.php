<?php

declare(strict_types=1);

namespace Drupal\utilikit\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\utilikit\Service\UtilikitServiceProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes queued CSS updates for UtiliKit utility classes.
 *
 * This queue worker handles asynchronous processing of CSS updates when new
 * utility classes are discovered. It validates classes, checks for new
 * additions, and updates the CSS file during cron runs to prevent blocking
 * the main request flow.
 *
 * @QueueWorker(
 *   id = "utilikit_css_processor",
 *   title = @Translation("UtiliKit CSS Processor"),
 *   cron = {"time" = 30}
 * )
 *
 * @phpstan-consistent-constructor
 *   Drupal's create() factory returns `new static()`. PHPStan treats that as
 *   unsafe on a non-final class because a subclass could declare a different
 *   constructor. This class's constructor is the contract subclasses inherit,
 *   which is what the annotation asserts.
 */
class UtilikitCssProcessor extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new UtilikitCssProcessor object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service for recording processing events.
   * @param \Drupal\utilikit\Service\UtilikitServiceProvider $serviceProvider
   *   The UtiliKit service provider for CSS processing operations.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    // NOT READONLY, and that is forced rather than stylistic.
    // DependencySerializationTrait re-injects these from the container on wake,
    // and __wakeup() lives in the PARENT class — PHP before 8.4 forbids writing
    // a child's readonly property from there, so the object comes back broken.
    // Reported by PHPStan on drupal.org as
    // dependencySerializationTraitProperty.unsupportedReadOnlyProperty, and
    // invisible on this machine twice over: the local PHPStan is 1.x, which
    // does not report it, and PHP here is 8.5, where the restriction is lifted.
    // The bug is real on every site running 8.3.
    //
    // Third instance of this in the project — the same fix landed in
    // paragraphs_bundles and vvj_core on 2026-09-05.
    protected LoggerInterface $logger,
    protected UtilikitServiceProvider $serviceProvider,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.utilikit'),
      $container->get('utilikit.service_provider')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Processes a single queue item containing utility classes for CSS update.
   *
   * This method validates the incoming utility classes, identifies new classes
   * that aren't already in the known classes collection, and updates the CSS
   * file if new classes are found. The processing is done asynchronously
   * during cron runs to avoid blocking the main request flow.
   *
   * @param mixed $data
   *   Queue item data expected to contain a 'classes' array with utility
   *   class names to be processed.
   *
   * @throws \Exception
   *   When CSS processing fails, the exception is re-thrown after logging
   *   to ensure the queue item is marked as failed and can be retried.
   */
  public function processItem($data): void {
    if (!isset($data['classes']) || !is_array($data['classes'])) {
      $this->logger->error('Invalid queue item data');
      return;
    }

    try {
      $validClasses = $this->serviceProvider->getContentScanner()->validateUtilityClasses($data['classes']);

      if (empty($validClasses)) {
        $this->logger->warning('No valid utility classes found in queue item');
        return;
      }

      $stateManager = $this->serviceProvider->getStateManager();
      $knownClasses = $stateManager->getKnownClasses();
      $newClasses = array_diff($validClasses, $knownClasses);

      if (!empty($newClasses)) {
        $result = $this->serviceProvider->updateCssAndFile($validClasses);

        if ($result) {
          // Explicitly invalidate CSS caches after successful queue processing
          // to ensure pages reflect the newly generated CSS.
          $this->serviceProvider->getCacheManager()->clearCssCaches();

          $this->logger->info('Processed queued CSS update with @count new classes', [
            '@count' => count($newClasses),
          ]);
        }
        else {
          $this->logger->error('Failed to update CSS file during queue processing');
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to process CSS update: @message', [
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

}
