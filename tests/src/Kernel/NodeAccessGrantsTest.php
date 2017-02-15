<?php

namespace Drupal\Tests\workflow_participants\Kernel;

use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simpletest\ContentTypeCreationTrait;
use Drupal\simpletest\NodeCreationTrait;
use Drupal\simpletest\UserCreationTrait;

/**
 * Test to ensure node access grants are working for participants.
 *
 * @group workflow_participants
 */
class NodeAccessGrantsTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;
  use UserCreationTrait;

  /**
   * Workflow participant storage.
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
    'field',
    'node',
    'system',
    'user',
    'workflow_participants',
    // These seem to be implicitly required by content creation trait.
    'filter',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('workflow_participants');
    $this->installEntitySchema('user');

    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', ['sequences']);

    $this->installConfig(['filter', 'node']);

    $this->participantStorage = $this->container->get('entity_type.manager')
      ->getStorage('workflow_participants');

    // Create a dummy user so other users are not uid 1.
    $this->createUser();
  }

  /**
   * Verify grants are written when participants change.
   */
  public function testNodeAccessRecordWrites() {
    $type = $this->createContentType();
    $node = $this->createNode(['type' => $type->id()]);
    $editor = $this->createUser(['can be workflow participant']);
    $reviewer = $this->createUser(['can be workflow participant']);

    $participants = $this->participantStorage->loadForModeratedEntity($node);
    $participants->editors[0] = $editor;
    $participants->reviewers[0] = $reviewer;
    $participants->save();

    $grants = Database::getConnection()
      ->select('node_access', 'na')
      ->fields('na', [
        'gid',
        'realm',
        'grant_view',
        'grant_update',
        'grant_delete',
      ])
      ->execute()
      ->fetchAllAssoc('gid');

    $this->assertEquals(2, count($grants));

    $editor_grants = $grants[$editor->id()];
    $this->assertTrue($editor_grants->grant_view);
    $this->assertTrue($editor_grants->grant_update);
    $this->assertFalse($editor_grants->grant_delete);
    $this->assertEquals('workflow_participants_editors', $editor_grants->realm);

    $reviewer_grants = $grants[$reviewer->id()];
    $this->assertTrue($reviewer_grants->grant_view);
    $this->assertFalse($reviewer_grants->grant_update);
    $this->assertFalse($reviewer_grants->grant_delete);
    $this->assertEquals('workflow_participants_reviewers', $reviewer_grants->realm);

    // Remove participants, grants should be open.
    $participants->editors = [];
    $participants->reviewers = [];
    $participants->save();

    $grants = Database::getConnection()
      ->select('node_access', 'na')
      ->fields('na', [
        'gid',
        'realm',
        'grant_view',
        'grant_update',
        'grant_delete',
      ])
      ->execute()
      ->fetchAllAssoc('gid');

    $this->assertEquals(1, count($grants));
    $this->assertEquals(0, $grants[0]->gid);
    $this->assertEquals('all', $grants[0]->realm);
  }

  /**
   * Verify hook_node_grants for participants.
   */
  public function testNodeGrants() {
    // Non-participant.
    $account = $this->createUser();
    $this->assertEquals([], workflow_participants_node_grants($account, 'view'));
    $this->assertEquals([], workflow_participants_node_grants($account, 'update'));
    $this->assertEquals([], workflow_participants_node_grants($account, 'delete'));

    // Editor/reviewer.
    $account = $this->createUser(['can be workflow participant']);

    // Update op.
    $grants = [
      'workflow_participants_editors' => [$account->id()],
    ];
    $this->assertEquals($grants, workflow_participants_node_grants($account, 'update'));

    // View op.
    $grants = [
      'workflow_participants_editors' => [$account->id()],
      'workflow_participants_reviewers' => [$account->id()],
    ];
    $this->assertEquals($grants, workflow_participants_node_grants($account, 'view'));

    // Delete op.
    $this->assertEquals([], workflow_participants_node_grants($account, 'delete'));
  }

}
