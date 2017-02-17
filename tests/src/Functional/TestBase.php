<?php

namespace Drupal\Tests\workflow_participants\Functional;

use Drupal\simpletest\NodeCreationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Base class for functional workflow participant tests.
 */
abstract class TestBase extends BrowserTestBase {

  use NodeCreationTrait;

  /**
   * User with workflow participant permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Users that can be reviewers or editors.
   *
   * @var \Drupal\user\UserInterface[]
   */
  protected $participants;

  /**
   * Workflow participant storage.
   *
   * @var \Drupal\workflow_participants\WorkflowParticipantsStorageInterface
   */
  protected $participantStorage;

  /**
   * A node to test with.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * Moderation information.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'node',
    'system',
    'user',
    'workflow_participants',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->placeBlock('local_tasks_block');

    // Add a node type and enable content moderation.
    $node_type = $this->createContentType(['type' => 'article']);
    $node_type->setThirdPartySetting('content_moderation', 'enabled', TRUE);
    $node_type->setThirdPartySetting('content_moderation', 'allowed_moderation_states', ['draft', 'published']);
    $node_type->setThirdPartySetting('content_moderation', 'default_moderation_state', 'draft');
    $node_type->save();

    $this->node = $this->createNode([
      'type' => 'article',
    ]);

    // Setup a role that can be participants.
    $role = $this->createRole([
      'can be workflow participant',
      'access user profiles',
      'access content',
    ]);

    // Dummy admin user to avoid uid 1 super perms.
    $this->createUser();
    // Real admin user.
    $this->adminUser = $this->createUser([
      'manage workflow participants',
      'administer nodes',
      'edit any article content',
      'view any unpublished content',
    ]);
    $this->drupalLogin($this->adminUser);

    // Create 10 participants.
    foreach (range(1, 10) as $i) {
      $account = $this->createUser();
      if (in_array($i, [1, 2, 3, 4])) {
        // Users 1 through 4 can be participants.
        $account->addRole($role);
      }
      $account->save();
      $this->participants[$i] = $account;
    }

    $this->moderationInfo = $this->container->get('content_moderation.moderation_information');
    $this->participantStorage = $this->container->get('entity_type.manager')->getStorage('workflow_participants');
  }

}
