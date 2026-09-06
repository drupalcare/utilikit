<?php

declare(strict_types=1);

namespace Drupal\utilikit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Psr\Log\LoggerInterface;

/**
 * Scans entity content for UtiliKit utility classes.
 *
 * This service provides comprehensive scanning capabilities for finding
 * UtiliKit utility classes within entity field data. It validates classes
 * against defined rules, extracts classes from HTML content, and supports
 * batch processing for performance optimization.
 */
class UtilikitContentScanner {

  /**
   * Constructs a new UtilikitContentScanner object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service for recording scanning operations.
   * @param \Drupal\utilikit\Service\UtilikitRules $rulesService
   *   The UtiliKit rules service for validation.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly LoggerInterface $logger,
    protected readonly UtilikitRules $rulesService,
  ) {}

  /**
   * Scans all fieldable entities for UtiliKit utility classes.
   *
   * Iterates through all fieldable entity types and their entities to find
   * UtiliKit utility classes. Processing is done in batches to manage memory
   * usage and prevent timeouts during large content scans.
   *
   * @param int|null $batchSize
   *   Optional batch size for entity processing. Defaults to
   *   UtilikitConstants::BATCH_SIZE_DEFAULT if not specified.
   * @param int|null $maxExecutionTime
   *   Optional maximum execution time in seconds. Defaults to 30 seconds
   *   if not specified. Used to prevent timeouts during large scans.
   *
   * @return array<string, mixed>
   *   Results array containing:
   *   - classes: Array of unique utility classes found
   *   - scanned_count: Total number of entities scanned
   *   - completed: Boolean indicating if scan completed or timed out
   *   - execution_time: Total execution time in seconds
   */
  public function scanAllContent(?int $batchSize = NULL, ?int $maxExecutionTime = NULL): array {
    $batchSize = $batchSize ?? UtilikitConstants::BATCH_SIZE_DEFAULT;
    $maxExecutionTime = $maxExecutionTime ?? 30;
    $startTime = time();

    $allClasses = [];
    $totalScanned = 0;
    $completed = TRUE;

    // Get scanning configuration.
    $config = $this->configFactory->get('utilikit.settings');
    $scanning_entity_types = $config->get('scanning_entity_types') ?? ['node', 'block_content', 'paragraph'];

    // Filter entity types based on configuration.
    $entityTypes = array_filter(
      $this->entityTypeManager->getDefinitions(),
      function ($entityType) use ($scanning_entity_types) {
        if (!$entityType->entityClassImplements(FieldableEntityInterface::class)) {
          return FALSE;
        }
        return in_array($entityType->id(), $scanning_entity_types, TRUE);
      }
    );

    foreach ($entityTypes as $entityTypeId => $entityType) {
      $storage = $this->entityTypeManager->getStorage($entityTypeId);
      $idKey = $entityType->getKey('id');
      $lastId = 0;
      $entityTypeCount = 0;

      while (TRUE) {
        // Check timeout.
        if ((time() - $startTime) > $maxExecutionTime) {
          $this->logger->warning('Scan timeout reached after @time seconds. Scanned @count entities.', [
            '@time' => $maxExecutionTime,
            '@count' => $totalScanned,
          ]);
          $completed = FALSE;
          break 2;
        }

        $query = $storage->getQuery()
          ->accessCheck(FALSE)
          ->condition($idKey, $lastId, '>')
          ->sort($idKey, 'ASC')
          ->range(0, $batchSize);

        $entityIds = $query->execute();

        if (empty($entityIds)) {
          break;
        }

        $entities = $storage->loadMultiple($entityIds);

        foreach ($entities as $entity) {
          $classes = $this->scanEntity($entity);
          if (!empty($classes)) {
            array_push($allClasses, ...$classes);
          }
          $totalScanned++;
          $entityTypeCount++;
        }

        // Progress logging every 10 batches.
        if ($entityTypeCount % ($batchSize * 10) === 0) {
          $this->logger->info('Scanned @count @type entities', [
            '@count' => $entityTypeCount,
            '@type' => $entityTypeId,
          ]);
        }

        $entityIdsArray = array_values($entityIds);
        $lastId = end($entityIdsArray);
        unset($entities, $entityIds, $entityIdsArray);
      }

      if ($entityTypeCount > 0) {
        $this->logger->info('Completed @type: @count entities scanned', [
          '@type' => $entityTypeId,
          '@count' => $entityTypeCount,
        ]);
      }
    }

    return [
      'classes' => array_unique($allClasses),
      'scanned_count' => $totalScanned,
      'completed' => $completed,
      'execution_time' => time() - $startTime,
    ];
  }

  /**
   * Scans a single entity for UtiliKit utility classes.
   *
   * Examines all fields and properties of a fieldable entity to extract
   * UtiliKit utility classes from string values that may contain HTML
   * with class attributes.
   *
   * @param mixed $entity
   *   The entity to scan. Must implement FieldableEntityInterface.
   *
   * @return list<string> mixed>
   *   Array of unique utility classes found in the entity.
   */
  public function scanEntity($entity): array {
    if (!$entity instanceof FieldableEntityInterface) {
      return [];
    }

    $classes = [];

    foreach ($entity->getFields() as $field) {
      if ($field instanceof FieldItemListInterface) {
        foreach ($field as $item) {
          foreach ($item->getProperties() as $property) {
            $value = $property->getValue();

            if (is_string($value) && !empty($value)) {
              $extractedClasses = $this->extractClassesFromHtml($value);
              $classes = array_merge($classes, $extractedClasses);
            }
          }
        }
      }
    }

    return array_unique($classes);
  }

  /**
   * Extract UtiliKit classes from HTML content following strict validation.
   *
   * Requirements:
   * 1. Must have class attribute
   * 2. Must have "utilikit" marker class
   * 3. Must have at least one uk- class
   * 4. uk- classes must use valid abbreviations
   * 5. uk- classes must have exactly two dashes: uk-{abbrev}--{value}
   *
   * @param string $html
   *   HTML content to scan for utility classes.
   *
   * @return list<string> mixed>
   *   Array of unique utility classes found in the HTML.
   */
  public function extractClassesFromHtml(string $html): array {
    if (empty($html)) {
      return [];
    }

    // Quick check - must contain both "utilikit" and "uk-".
    if (!str_contains($html, 'utilikit') || !str_contains($html, 'uk-')) {
      return [];
    }

    $classes = [];
    $validAbbreviations = $this->getValidAbbreviations();

    // Use DOMDocument for reliable HTML parsing instead of regex.
    $dom = new \DOMDocument();
    // Suppress warnings from malformed HTML and use UTF-8 encoding.
    $previousUseErrors = libxml_use_internal_errors(TRUE);
    $dom->loadHTML('<meta charset="utf-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previousUseErrors);

    $xpath = new \DOMXPath($dom);
    // Find all elements with a class attribute containing "utilikit".
    $nodes = $xpath->query('//*[contains(@class, "utilikit")]');

    if ($nodes === FALSE) {
      return [];
    }

    foreach ($nodes as $node) {
      // getAttribute() is declared on DOMElement, not on DOMNode. An XPath
      // query returns DOMNode|DOMNameSpaceNode, so an attribute or namespace
      // node reaching this loop would be a fatal. Narrow rather than assume.
      if (!$node instanceof \DOMElement) {
        continue;
      }

      $classAttr = $node->getAttribute('class');
      $elementClasses = preg_split('/\s+/', trim($classAttr));

      // Verify this element has the utilikit marker.
      if (!in_array('utilikit', $elementClasses, TRUE)) {
        continue;
      }

      // Check each class in this element.
      foreach ($elementClasses as $class) {
        if ($this->isValidUtilityClass($class, $validAbbreviations)) {
          $classes[] = $class;
        }
      }
    }

    return array_unique($classes);
  }

  /**
   * Get valid abbreviations from the rules service.
   *
   * Retrieves the list of valid utility class abbreviations that can be
   * used in UtiliKit classes from the rules service.
   *
   * @return list<string> mixed>
   *   Array of valid abbreviation strings.
   */
  protected function getValidAbbreviations(): array {
    $rules = $this->rulesService->getRules();
    return array_keys($rules);
  }

  /**
   * Validate a utility class against all requirements.
   *
   * Performs comprehensive validation of a utility class including format,
   * length, abbreviation validity, breakpoint validity, and value format
   * checking.
   *
   * @param string $class
   *   The utility class name to validate.
   * @param list<string> $validAbbreviations
   *   Optional array of valid abbreviations. If not provided, will be
   *   retrieved from the rules service.
   *
   * @return bool
   *   TRUE if the class is valid, FALSE otherwise.
   */
  public function isValidUtilityClass(string $class, ?array $validAbbreviations = NULL): bool {
    // Must start with uk-.
    if (!str_starts_with($class, 'uk-')) {
      return FALSE;
    }

    // Check length limit.
    if (strlen($class) > UtilikitConstants::MAX_CLASS_NAME_LENGTH) {
      return FALSE;
    }

    // Get valid abbreviations if not provided.
    if ($validAbbreviations === NULL) {
      $validAbbreviations = $this->getValidAbbreviations();
    }

    // Parse the class structure: uk-[breakpoint-]abbreviation--value.
    $pattern = '/^uk-(?:(sm|md|lg|xl|xxl)-)?([a-z]{2,4})--(.+)$/';
    if (!preg_match($pattern, $class, $matches)) {
      return FALSE;
    }

    $breakpoint = $matches[1] ?: NULL;
    $abbreviation = $matches[2];
    $value = $matches[3];

    // Validate abbreviation exists in rules.
    if (!in_array($abbreviation, $validAbbreviations, TRUE)) {
      return FALSE;
    }

    // Validate breakpoint if present.
    if ($breakpoint && !in_array($breakpoint, ['sm', 'md', 'lg', 'xl', 'xxl'])) {
      return FALSE;
    }

    // Validate value is not empty.
    if (empty($value)) {
      return FALSE;
    }

    // Additional validation for value format with 'd' notation support
    // Allow alphanumeric, hyphens, underscores, percentages, and 'd'
    // for decimals.
    if (!preg_match('/^[a-zA-Z0-9\-_%d]+$/', $value)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Validates an array of utility classes.
   *
   * Filters an array of utility class names to return only those that pass
   * validation according to UtiliKit rules and format requirements.
   *
   * @param list<string> $classes
   *   Array of utility class names to validate.
   *
   * @return list<string> mixed>
   *   Array containing only the valid utility classes.
   */
  public function validateUtilityClasses(array $classes): array {
    $validAbbreviations = $this->getValidAbbreviations();
    return array_filter($classes, function ($class) use ($validAbbreviations) {
      return $this->isValidUtilityClass($class, $validAbbreviations);
    });
  }

  /**
   * Extracts abbreviations from an array of utility classes.
   *
   * Parses utility class names to extract their abbreviation components,
   * useful for analyzing which types of utility classes are being used.
   *
   * @param list<string> $classes
   *   Array of utility class names to parse.
   *
   * @return list<string> mixed>
   *   Array of unique abbreviations found in the classes.
   */
  public function getAbbreviationsFromClasses(array $classes): array {
    $abbreviations = [];

    foreach ($classes as $class) {
      if (preg_match('/^uk-(?:(?:sm|md|lg|xl|xxl)-)?([a-z]{2,4})--/', $class, $matches)) {
        $abbreviations[] = $matches[1];
      }
    }

    return array_unique($abbreviations);
  }

  /**
   * Scans a batch of entities for utility classes.
   *
   * Processes multiple entities in a single operation for efficient batch
   * scanning when entities are already loaded.
   *
   * @param array<string, mixed> $entities
   *   Array of entities to scan for utility classes.
   *
   * @return list<mixed> mixed>
   *   Array of unique utility classes found across all entities.
   */
  public function scanEntityBatch(array $entities): array {
    $allClasses = [];

    foreach ($entities as $entity) {
      foreach ($this->scanEntity($entity) as $class) {
        $allClasses[] = $class;
      }
    }

    return array_unique($allClasses);
  }

  /**
   * Scan for new classes not in the known classes list.
   *
   * Performs a complete content scan and compares results against a list
   * of known classes to identify newly added utility classes.
   *
   * @param array<string, mixed> $knownClasses
   *   Array of already known utility classes to compare against.
   *
   * @return array<string, mixed>
   *   Results array containing:
   *   - new_classes: Array of newly found utility classes
   *   - total_scanned: Total number of entities scanned
   *   - new_count: Count of new classes found
   *   - total_found: Total count of all classes found
   */
  public function scanForNewClasses(array $knownClasses): array {
    $scanResult = $this->scanAllContent();
    $newClasses = array_diff($scanResult['classes'], $knownClasses);

    return [
      'new_classes' => $newClasses,
      'total_scanned' => $scanResult['scanned_count'],
      'new_count' => count($newClasses),
      'total_found' => count($scanResult['classes']),
    ];
  }

}
