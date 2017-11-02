<?php

namespace Drupal\Tests\workflow_participants\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\workflows\Entity\Workflow;

/**
 * Base kernel test class for workflow participants.
 */
abstract class WorkflowParticipantsTestBase extends KernelTestBase {

  use UserCreationTrait;

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
    'filter',
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
    $this->installConfig([
      'filter',
      'content_moderation',
      'workflow_participants',
      'system',
    ]);

    $this->enableModeration();

    $this->participantStorage = $this->container->get('entity_type.manager')->getStorage('workflow_participants');
  }

  /**
   * Creates a page node type to test with, ensuring that it's moderated.
   *
   * @param string $entity_type
   *   The entity type ID to enable workflow for.
   * @param string $bundle
   *   The bundle ID.
   */
  protected function enableModeration($entity_type = 'entity_test_rev', $bundle = 'entity_test_rev') {
    $workflow = Workflow::load('editorial');
    $workflow->getTypePlugin()->addEntityTypeAndBundle($entity_type, $bundle);
    $workflow->save();
  }

}
