<?php

namespace Drupal\Tests\workflow_participants\Kernel;

use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simpletest\NodeCreationTrait;
use Drupal\workflows\Entity\Workflow;

/**
 * Test entity deletion.
 *
 * @group workflow_participants
 */
class EntityDeleteTest extends KernelTestBase {

  use NodeCreationTrait;

  /**
   * Participant storage.
   *
   * @var \Drupal\workflow_participants\WorkflowParticipantsStorageInterface
   */
  protected $participantStorage;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'content_moderation',
    'dynamic_entity_reference',
    'entity_test',
    'node',
    'system',
    'user',
    'workflows',
    'workflow_participants',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test_rev');
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('workflow_participants');
    $this->installConfig('content_moderation');

    $this->enableModeration();

    $this->participantStorage = $this->container->get('entity_type.manager')->getStorage('workflow_participants');
  }

  /**
   * Tests that corresponding participants are removed when entity is deleted.
   */
  public function testEntityDeletion() {
    $entity = EntityTestRev::create([
      'moderation_state' => 'draft',
    ]);
    $entity->save();

    // Add participants.
    $participants = $this->participantStorage->loadForModeratedEntity($entity);
    $participants->save();

    // Delete the node.
    $entity->delete();
    $this->participantStorage->resetCache();
    $this->assertNull($this->participantStorage->load($participants->id()));
  }

  /**
   * Creates a page node type to test with, ensuring that it's moderated.
   */
  protected function enableModeration() {
    $workflow = Workflow::load('editorial');
    $workflow->getTypePlugin()->addEntityTypeAndBundle('entity_test_rev', 'entity_test_rev');
    $workflow->save();
  }

}
