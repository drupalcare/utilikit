<?php

namespace Drupal\Tests\utilikit\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitConstants;

/**
 * Tests UtilikitConstants service class.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Service\UtilikitConstants
 */
class UtilikitConstantsTest extends UnitTestCase {

  /**
   * Tests that all expected constants are defined.
   *
   * @covers ::__construct
   */
  public function testConstantsAreDefined() {
    // Rate limiting constants.
    $this->assertTrue(defined('Drupal\utilikit\Service\UtilikitConstants::RATE_LIMIT_REQUESTS_PER_MINUTE'));
    $this->assertTrue(defined('Drupal\utilikit\Service\UtilikitConstants::RATE_LIMIT_WINDOW_SECONDS'));
    $this->assertTrue(defined('Drupal\utilikit\Service\UtilikitConstants::MAX_CLASSES_PER_REQUEST'));

    // Batch processing constants.
    $this->assertTrue(defined('Drupal\utilikit\Service\UtilikitConstants::BATCH_SIZE_DEFAULT'));
    $this->assertTrue(defined('Drupal\utilikit\Service\UtilikitConstants::QUEUE_PROCESSING_LIMIT'));

    // Cache tags.
    $this->assertTrue(defined('Drupal\utilikit\Service\UtilikitConstants::CACHE_TAG_CONFIG'));
    $this->assertTrue(defined('Drupal\utilikit\Service\UtilikitConstants::CACHE_TAG_CSS'));
    $this->assertTrue(defined('Drupal\utilikit\Service\UtilikitConstants::CACHE_TAG_INLINE_MODE'));
    $this->assertTrue(defined('Drupal\utilikit\Service\UtilikitConstants::CACHE_TAG_STATIC_MODE'));

    // State keys.
    $this->assertTrue(defined('Drupal\utilikit\Service\UtilikitConstants::STATE_KNOWN_CLASSES'));
    $this->assertTrue(defined('Drupal\utilikit\Service\UtilikitConstants::STATE_GENERATED_CSS'));
    $this->assertTrue(defined('Drupal\utilikit\Service\UtilikitConstants::STATE_CSS_TIMESTAMP'));
    $this->assertTrue(defined('Drupal\utilikit\Service\UtilikitConstants::STATE_LAST_CLEANUP'));

    // File paths.
    $this->assertTrue(defined('Drupal\utilikit\Service\UtilikitConstants::CSS_DIRECTORY'));
    $this->assertTrue(defined('Drupal\utilikit\Service\UtilikitConstants::CSS_FILENAME'));
  }

  /**
   * Tests rate limiting constants have reasonable values.
   */
  public function testRateLimitingConstants() {
    $this->assertEquals(60, UtilikitConstants::RATE_LIMIT_REQUESTS_PER_MINUTE);
    $this->assertEquals(60, UtilikitConstants::RATE_LIMIT_WINDOW_SECONDS);
    $this->assertEquals(5000, UtilikitConstants::MAX_CLASSES_PER_REQUEST);
    $this->assertGreaterThan(0, UtilikitConstants::RATE_LIMIT_REQUESTS_PER_MINUTE);
    $this->assertGreaterThan(0, UtilikitConstants::RATE_LIMIT_WINDOW_SECONDS);
  }

  /**
   * Tests batch processing constants have reasonable values.
   */
  public function testBatchProcessingConstants() {
    $this->assertEquals(50, UtilikitConstants::BATCH_SIZE_DEFAULT);
    $this->assertEquals(5, UtilikitConstants::QUEUE_PROCESSING_LIMIT);
    $this->assertGreaterThan(0, UtilikitConstants::BATCH_SIZE_DEFAULT);
    $this->assertGreaterThan(0, UtilikitConstants::QUEUE_PROCESSING_LIMIT);
  }

  /**
   * Tests cache tag constants are properly formatted.
   */
  public function testCacheTagConstants() {
    $this->assertEquals('config:utilikit.settings', UtilikitConstants::CACHE_TAG_CONFIG);
    $this->assertEquals('utilikit:css', UtilikitConstants::CACHE_TAG_CSS);
    $this->assertEquals('utilikit:mode:inline', UtilikitConstants::CACHE_TAG_INLINE_MODE);
    $this->assertEquals('utilikit:mode:static', UtilikitConstants::CACHE_TAG_STATIC_MODE);

    // Verify cache tags follow expected pattern.
    $this->assertStringStartsWith('config:', UtilikitConstants::CACHE_TAG_CONFIG);
    $this->assertStringStartsWith('utilikit:', UtilikitConstants::CACHE_TAG_CSS);
    $this->assertStringStartsWith('utilikit:', UtilikitConstants::CACHE_TAG_INLINE_MODE);
    $this->assertStringStartsWith('utilikit:', UtilikitConstants::CACHE_TAG_STATIC_MODE);
  }

  /**
   * Tests state key constants are properly formatted.
   */
  public function testStateKeyConstants() {
    $this->assertEquals('utilikit.known_classes', UtilikitConstants::STATE_KNOWN_CLASSES);
    $this->assertEquals('utilikit.generated_css', UtilikitConstants::STATE_GENERATED_CSS);
    $this->assertEquals('utilikit.css_timestamp', UtilikitConstants::STATE_CSS_TIMESTAMP);
    $this->assertEquals('utilikit.last_cleanup', UtilikitConstants::STATE_LAST_CLEANUP);

    // Verify state keys follow expected pattern.
    $this->assertStringStartsWith('utilikit.', UtilikitConstants::STATE_KNOWN_CLASSES);
    $this->assertStringStartsWith('utilikit.', UtilikitConstants::STATE_GENERATED_CSS);
    $this->assertStringStartsWith('utilikit.', UtilikitConstants::STATE_CSS_TIMESTAMP);
    $this->assertStringStartsWith('utilikit.', UtilikitConstants::STATE_LAST_CLEANUP);
  }

  /**
   * Tests file system path constants.
   */
  public function testFileSystemConstants() {
    $this->assertEquals('public://css/utilikit', UtilikitConstants::CSS_DIRECTORY);
    $this->assertEquals('generated.css', UtilikitConstants::CSS_FILENAME);
    $this->assertStringStartsWith('public://', UtilikitConstants::CSS_DIRECTORY);
    $this->assertStringEndsWith('.css', UtilikitConstants::CSS_FILENAME);
  }

  /**
   * Tests breakpoint configuration constants.
   */
  public function testBreakpointConstants() {
    $breakpoints = UtilikitConstants::DEFAULT_BREAKPOINTS;
    $this->assertIsArray($breakpoints);
    $this->assertArrayHasKey('sm', $breakpoints);
    $this->assertArrayHasKey('md', $breakpoints);
    $this->assertArrayHasKey('lg', $breakpoints);
    $this->assertArrayHasKey('xl', $breakpoints);
    $this->assertArrayHasKey('xxl', $breakpoints);

    $values = UtilikitConstants::BREAKPOINT_VALUES;
    $this->assertIsArray($values);
    $this->assertEquals(576, $values['sm']);
    $this->assertEquals(768, $values['md']);
    $this->assertEquals(992, $values['lg']);
    $this->assertEquals(1200, $values['xl']);
    $this->assertEquals(1400, $values['xxl']);
  }

  /**
   * Tests entity auto-update configuration constants.
   */
  public function testEntityAutoUpdateConstants() {
    $entities = UtilikitConstants::AUTO_UPDATE_ENTITY_TYPES;
    $this->assertIsArray($entities);
    $this->assertArrayHasKey('node', $entities);
    $this->assertArrayHasKey('block_content', $entities);
    $this->assertArrayHasKey('paragraph', $entities);

    $this->assertEquals('update_on_node_save', $entities['node']);
    $this->assertEquals('update_on_block_save', $entities['block_content']);
    $this->assertEquals('update_on_paragraph_save', $entities['paragraph']);
  }

  /**
   * Tests queue and lock identifier constants.
   */
  public function testQueueAndLockConstants() {
    $this->assertEquals('utilikit_css_processor', UtilikitConstants::QUEUE_CSS_PROCESSOR);
    $this->assertEquals('utilikit_css_update', UtilikitConstants::LOCK_CSS_UPDATE);
    $this->assertIsString(UtilikitConstants::QUEUE_CSS_PROCESSOR);
    $this->assertIsString(UtilikitConstants::LOCK_CSS_UPDATE);
  }

  /**
   * Tests validation pattern constants.
   */
  public function testValidationPatterns() {
    $pattern = UtilikitConstants::CLASS_VALIDATION_PATTERN;
    $this->assertIsString($pattern);
    $this->assertStringStartsWith('/', $pattern);
    $this->assertStringEndsWith('/', $pattern);

    // Test valid class names match the pattern.
    $this->assertMatchesRegularExpression($pattern, 'uk-pd--20');
    $this->assertMatchesRegularExpression($pattern, 'uk-sm-mg--auto');
    $this->assertMatchesRegularExpression($pattern, 'uk-lg-bg--ff0000');

    // Test invalid class names don't match.
    $this->assertDoesNotMatchRegularExpression($pattern, 'invalid-class');
    $this->assertDoesNotMatchRegularExpression($pattern, 'uk-pd');
    $this->assertDoesNotMatchRegularExpression($pattern, 'pd--20');
  }

  /**
   * Tests timing and CSS configuration constants.
   */
  public function testTimingConstants() {
    $this->assertEquals(3600, UtilikitConstants::CSS_CACHE_TTL);
    $this->assertEquals(8, UtilikitConstants::CSS_HASH_LENGTH);
    $this->assertEquals(50, UtilikitConstants::DEBOUNCE_DEFAULT);
    $this->assertEquals(0, UtilikitConstants::DEBOUNCE_MIN);
    $this->assertEquals(500, UtilikitConstants::DEBOUNCE_MAX);
    $this->assertEquals(10, UtilikitConstants::DEBOUNCE_STEP);

    $this->assertGreaterThan(0, UtilikitConstants::CSS_CACHE_TTL);
    $this->assertGreaterThan(0, UtilikitConstants::CSS_HASH_LENGTH);
    $this->assertGreaterThanOrEqual(UtilikitConstants::DEBOUNCE_MIN, UtilikitConstants::DEBOUNCE_DEFAULT);
    $this->assertLessThanOrEqual(UtilikitConstants::DEBOUNCE_MAX, UtilikitConstants::DEBOUNCE_DEFAULT);
  }

}
