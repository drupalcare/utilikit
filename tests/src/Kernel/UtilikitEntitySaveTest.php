<?php

namespace Drupal\Tests\utilikit\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\utilikit\Service\UtilikitConstants;

/**
 * Tests entity save hooks and CSS auto-update functionality.
 *
 * @group utilikit
 */
class UtilikitEntitySaveTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'block_content',
    'paragraphs',
    'entity_reference_revisions',
    'file',
    'utilikit',
  ];

  /**
   * The UtiliKit service provider.
   *
   * @var \Drupal\utilikit\Service\UtilikitServiceProvider
   */
  protected $serviceProvider;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The lock service.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install schemas.
    $this->installEntitySchema('node');
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'filter', 'utilikit']);

    // Get services.
    $this->serviceProvider = $this->container->get('utilikit.service_provider');
    $this->state = $this->container->get('state');
    $this->configFactory = $this->container->get('config.factory');
    $this->lock = $this->container->get('lock');
    $this->queueFactory = $this->container->get('queue');

    // Create content types.
    $this->createContentTypes();

    // Set up default configuration.
    $config = $this->configFactory->getEditable('utilikit.settings');
    $config->set('rendering_mode', 'static');
    $config->save();
  }

  /**
   * Creates necessary content types and fields.
   */
  protected function createContentTypes() {
    // Create node type.
    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    // Add body field to node.
    $this->createBodyField('node', 'article');

    // Create block content type.
    BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic block',
    ])->save();

    // Add body field to block.
    $this->createBodyField('block_content', 'basic');

    // Create paragraph type.
    ParagraphsType::create([
      'id' => 'text',
      'label' => 'Text paragraph',
    ])->save();

    // Add text field to paragraph.
    $this->createBodyField('paragraph', 'text', 'field_text');
  }

  /**
   * Creates a body/text field for an entity type.
   */
  protected function createBodyField($entity_type, $bundle, $field_name = 'body') {
    // Create field storage if it doesn't exist.
    if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'type' => 'text_with_summary',
      ])->save();
    }

    // Create field instance.
    if (!FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'label' => 'Body',
      ])->save();
    }
  }

  /**
   * Tests node save with auto-update enabled.
   */
  public function testNodeSaveWithAutoUpdate() {
    // Enable auto-update for nodes.
    $config = $this->configFactory->getEditable('utilikit.settings');
    $config->set('update_on_node_save', TRUE);
    $config->save();

    // Clear any existing classes.
    $this->state->delete(UtilikitConstants::STATE_KNOWN_CLASSES);

    // Create node with UtiliKit classes.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test article',
      'body' => [
        'value' => '<div class="utilikit uk-pd--20 uk-mg--10">Test content</div>',
        'format' => 'full_html',
      ],
    ]);

    // Save the node.
    $node->save();

    // Verify classes were added.
    $knownClasses = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    $this->assertContains('uk-pd--20', $knownClasses);
    $this->assertContains('uk-mg--10', $knownClasses);

    // Verify CSS was generated.
    $generatedCss = $this->state->get(UtilikitConstants::STATE_GENERATED_CSS);
    $this->assertNotEmpty($generatedCss);
    $this->assertStringContainsString('.uk-pd--20', $generatedCss);
    $this->assertStringContainsString('.uk-mg--10', $generatedCss);
  }

  /**
   * Tests node save with auto-update disabled.
   */
  public function testNodeSaveWithoutAutoUpdate() {
    // Disable auto-update for nodes.
    $config = $this->configFactory->getEditable('utilikit.settings');
    $config->set('update_on_node_save', FALSE);
    $config->save();

    // Clear any existing classes.
    $this->state->delete(UtilikitConstants::STATE_KNOWN_CLASSES);

    // Create node with UtiliKit classes.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test article',
      'body' => [
        'value' => '<div class="utilikit uk-pd--30">Test content</div>',
        'format' => 'full_html',
      ],
    ]);

    // Save the node.
    $node->save();

    // Verify classes were NOT added.
    $knownClasses = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    $this->assertEmpty($knownClasses);
  }

  /**
   * Tests block content save with auto-update.
   */
  public function testBlockContentSave() {
    // Enable auto-update for blocks.
    $config = $this->configFactory->getEditable('utilikit.settings');
    $config->set('update_on_block_save', TRUE);
    $config->save();

    // Clear any existing classes.
    $this->state->delete(UtilikitConstants::STATE_KNOWN_CLASSES);

    // Create block with UtiliKit classes.
    $block = BlockContent::create([
      'type' => 'basic',
      'info' => 'Test block',
      'body' => [
        'value' => '<div class="utilikit uk-bg--ff0000 uk-tc--ffffff">Block content</div>',
        'format' => 'full_html',
      ],
    ]);

    // Save the block.
    $block->save();

    // Verify classes were added.
    $knownClasses = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    $this->assertContains('uk-bg--ff0000', $knownClasses);
    $this->assertContains('uk-tc--ffffff', $knownClasses);
  }

  /**
   * Tests paragraph save with auto-update.
   */
  public function testParagraphSave() {
    // Enable auto-update for paragraphs.
    $config = $this->configFactory->getEditable('utilikit.settings');
    $config->set('update_on_paragraph_save', TRUE);
    $config->save();

    // Clear any existing classes.
    $this->state->delete(UtilikitConstants::STATE_KNOWN_CLASSES);

    // Create paragraph with UtiliKit classes.
    $paragraph = Paragraph::create([
      'type' => 'text',
      'field_text' => [
        'value' => '<div class="utilikit uk-dp--flex uk-jc--center">Paragraph content</div>',
        'format' => 'full_html',
      ],
    ]);

    // Save the paragraph.
    $paragraph->save();

    // Verify classes were added.
    $knownClasses = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    $this->assertContains('uk-dp--flex', $knownClasses);
    $this->assertContains('uk-jc--center', $knownClasses);
  }

  /**
   * Tests entity save in inline mode.
   */
  public function testEntitySaveInlineMode() {
    // Switch to inline mode.
    $config = $this->configFactory->getEditable('utilikit.settings');
    $config->set('rendering_mode', 'inline');
    $config->set('update_on_node_save', TRUE);
    $config->save();

    // Clear any existing classes.
    $this->state->delete(UtilikitConstants::STATE_KNOWN_CLASSES);

    // Create node with UtiliKit classes.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test article',
      'body' => [
        'value' => '<div class="utilikit uk-pd--40">Test content</div>',
        'format' => 'full_html',
      ],
    ]);

    // Save the node.
    $node->save();

    // In inline mode, classes should still be tracked.
    $knownClasses = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    $this->assertContains('uk-pd--40', $knownClasses);
  }

  /**
   * Tests entity save with CSS update lock.
   */
  public function testEntitySaveWithLock() {
    // Enable auto-update.
    $config = $this->configFactory->getEditable('utilikit.settings');
    $config->set('update_on_node_save', TRUE);
    $config->save();

    // Acquire the lock to simulate another process updating CSS.
    $this->lock->acquire(UtilikitConstants::LOCK_CSS_UPDATE, 5);

    // Create node with UtiliKit classes.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test article',
      'body' => [
        'value' => '<div class="utilikit uk-pd--50">Test content</div>',
        'format' => 'full_html',
      ],
    ]);

    // Save the node.
    $node->save();

    // Verify item was queued.
    $queue = $this->queueFactory->get(UtilikitConstants::QUEUE_CSS_PROCESSOR);
    $this->assertGreaterThan(0, $queue->numberOfItems());

    // Get the queued item.
    $item = $queue->claimItem();
    $this->assertNotFalse($item);
    $this->assertEquals(['uk-pd--50'], $item->data['classes']);
    $this->assertEquals('node', $item->data['entity_type']);

    // Release lock.
    $this->lock->release(UtilikitConstants::LOCK_CSS_UPDATE);

    // Clean up.
    $queue->deleteItem($item);
  }

  /**
   * Tests multiple entities saving simultaneously.
   */
  public function testMultipleEntitySaves() {
    // Enable auto-update for all entity types.
    $config = $this->configFactory->getEditable('utilikit.settings');
    $config->set('update_on_node_save', TRUE);
    $config->set('update_on_block_save', TRUE);
    $config->save();

    // Clear any existing classes.
    $this->state->delete(UtilikitConstants::STATE_KNOWN_CLASSES);

    // Create and save multiple entities.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test article',
      'body' => [
        'value' => '<div class="utilikit uk-pd--60">Node content</div>',
        'format' => 'full_html',
      ],
    ]);
    $node->save();

    $block = BlockContent::create([
      'type' => 'basic',
      'info' => 'Test block',
      'body' => [
        'value' => '<div class="utilikit uk-mg--60">Block content</div>',
        'format' => 'full_html',
      ],
    ]);
    $block->save();

    // Verify all classes were added.
    $knownClasses = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    $this->assertContains('uk-pd--60', $knownClasses);
    $this->assertContains('uk-mg--60', $knownClasses);
  }

  /**
   * Tests entity save with invalid classes.
   */
  public function testEntitySaveInvalidClasses() {
    // Enable auto-update.
    $config = $this->configFactory->getEditable('utilikit.settings');
    $config->set('update_on_node_save', TRUE);
    $config->save();

    // Clear any existing classes.
    $this->state->delete(UtilikitConstants::STATE_KNOWN_CLASSES);

    // Create node with mixed valid and invalid classes.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test article',
      'body' => [
        'value' => '<div class="utilikit uk-pd--70 uk-invalid--class not-utility">Test content</div>',
        'format' => 'full_html',
      ],
    ]);

    // Save the node.
    $node->save();

    // Verify only valid classes were added.
    $knownClasses = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    $this->assertContains('uk-pd--70', $knownClasses);
    $this->assertNotContains('uk-invalid--class', $knownClasses);
    $this->assertNotContains('not-utility', $knownClasses);
  }

  /**
   * Tests entity save with no UtiliKit classes.
   */
  public function testEntitySaveNoClasses() {
    // Enable auto-update.
    $config = $this->configFactory->getEditable('utilikit.settings');
    $config->set('update_on_node_save', TRUE);
    $config->save();

    // Clear any existing classes.
    $this->state->delete(UtilikitConstants::STATE_KNOWN_CLASSES);

    // Create node without UtiliKit classes.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test article',
      'body' => [
        'value' => '<div class="regular-class">No utility classes here</div>',
        'format' => 'full_html',
      ],
    ]);

    // Save the node.
    $node->save();

    // Verify no classes were added.
    $knownClasses = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    $this->assertEmpty($knownClasses);
  }

  /**
   * Tests entity save with non-fieldable entity.
   */
  public function testNonFieldableEntitySave() {
    // Enable auto-update.
    $config = $this->configFactory->getEditable('utilikit.settings');
    $config->set('update_on_node_save', TRUE);
    $config->save();

    // Create a user (non-fieldable in our test setup)
    $user = $this->createUser();

    // This should not trigger any errors.
    $user->save();

    // Verify no errors occurred (test passes if no exceptions)
    $this->assertTrue(TRUE);
  }

  /**
   * Tests entity update vs create.
   */
  public function testEntityUpdate() {
    // Enable auto-update.
    $config = $this->configFactory->getEditable('utilikit.settings');
    $config->set('update_on_node_save', TRUE);
    $config->save();

    // Clear any existing classes.
    $this->state->delete(UtilikitConstants::STATE_KNOWN_CLASSES);

    // Create node with initial classes.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test article',
      'body' => [
        'value' => '<div class="utilikit uk-pd--80">Initial content</div>',
        'format' => 'full_html',
      ],
    ]);
    $node->save();

    // Verify initial classes.
    $knownClasses = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    $this->assertContains('uk-pd--80', $knownClasses);

    // Update node with new classes.
    $node->body->value = '<div class="utilikit uk-pd--80 uk-mg--80">Updated content</div>';
    $node->save();

    // Verify new classes were added.
    $knownClasses = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    $this->assertContains('uk-pd--80', $knownClasses);
    $this->assertContains('uk-mg--80', $knownClasses);
  }

  /**
   * Tests entity save with complex HTML structure.
   */
  public function testComplexHtmlStructure() {
    // Enable auto-update.
    $config = $this->configFactory->getEditable('utilikit.settings');
    $config->set('update_on_node_save', TRUE);
    $config->save();

    // Clear any existing classes.
    $this->state->delete(UtilikitConstants::STATE_KNOWN_CLASSES);

    // Create node with complex nested HTML.
    $html = '
      <div class="wrapper">
        <div class="utilikit uk-pd--20 uk-mg--10">
          <h2 class="utilikit uk-fs--24 uk-fw--700">Heading</h2>
          <div class="utilikit uk-dp--flex uk-jc--center">
            <span class="utilikit uk-tc--ff0000">Red text</span>
            <span class="utilikit uk-tc--00ff00">Green text</span>
          </div>
        </div>
      </div>
    ';

    $node = Node::create([
      'type' => 'article',
      'title' => 'Complex HTML',
      'body' => [
        'value' => $html,
        'format' => 'full_html',
      ],
    ]);
    $node->save();

    // Verify all unique classes were extracted.
    $knownClasses = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    $expectedClasses = [
      'uk-pd--20', 'uk-mg--10', 'uk-fs--24', 'uk-fw--700',
      'uk-dp--flex', 'uk-jc--center', 'uk-tc--ff0000', 'uk-tc--00ff00',
    ];

    foreach ($expectedClasses as $class) {
      $this->assertContains($class, $knownClasses);
    }
  }

  /**
   * Tests performance with large content.
   */
  public function testLargeContentPerformance() {
    // Enable auto-update.
    $config = $this->configFactory->getEditable('utilikit.settings');
    $config->set('update_on_node_save', TRUE);
    $config->save();

    // Generate large HTML with many classes.
    $html = '';
    for ($i = 0; $i < 100; $i++) {
      $html .= sprintf(
        '<div class="utilikit uk-pd--%d uk-mg--%d">Content %d</div>',
        $i % 50,
        $i % 30,
        $i
      );
    }

    $startTime = microtime(TRUE);

    $node = Node::create([
      'type' => 'article',
      'title' => 'Large content',
      'body' => [
        'value' => $html,
        'format' => 'full_html',
      ],
    ]);
    $node->save();

    $duration = microtime(TRUE) - $startTime;

    // Should complete in reasonable time.
    $this->assertLessThan(5.0, $duration, 'Large content should be processed within 5 seconds');

    // Verify classes were processed.
    $knownClasses = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    $this->assertNotEmpty($knownClasses);
  }

}
