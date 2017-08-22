<?php

namespace Drupal\Tests\workflow_participants\Kernel;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\simpletest\UserCreationTrait;
use Drupal\Tests\content_moderation_notifications\Kernel\ContentModerationNotificationCreateTrait;

/**
 * Verify that notifications are sent via content moderation notifications.
 *
 * @group workflow_participants
 *
 * @requires module content_moderation_notifications
 */
class NotificationsTest extends WorkflowParticipantsTestBase {

  use ContentModerationNotificationCreateTrait;
  use AssertMailTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'content_moderation_notifications',
    'filter',
    'filter_test',
  ];

  /**
   * Participants.
   *
   * @var \Drupal\user\UserInterface[]
   */
  protected $participants;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig(['filter_test']);
    $this->installSchema('system', ['sequences']);

    // Dummy UID 1.
    $this->createUser();

    $participant_role = $this->createRole(['can be workflow participant']);
    foreach (range(1, 10) as $i) {
      $account = $this->createUser();
      $account->addRole($participant_role);
      $account->save();
      $this->participants[$i] = $account;
    }
  }

  /**
   * Verify notifications are sent as configured.
   */
  public function testNotifications() {
    // Create a notification.
    $notification = $this->createNotification([
      'transitions' => ['archive' => 'archive'],
    ]);

    // Enable just reviewer notifications.
    $notification->setThirdPartySetting('workflow_participants', 'reviewers', TRUE);
    $notification->save();

    // Add an entity and some participants.
    $entity = EntityTestRev::create();
    $entity->save();

    /** @var \Drupal\workflow_participants\Entity\WorkflowParticipantsInterface $participants */
    $participants = \Drupal::entityTypeManager()->getStorage('workflow_participants')->loadForModeratedEntity($entity);
    $expected = [];
    foreach ([3, 5, 7] as $uid) {
      $participants->reviewers[] = $this->participants[$uid]->id();
      $expected[] = $this->participants[$uid]->getEmail();
    }
    $participants->save();

    $entity = EntityTestRev::load($entity->id());
    $entity->moderation_state = 'published';
    $entity->save();
    $this->assertEmpty($this->getMails());

    $entity = EntityTestRev::load($entity->id());
    $entity->moderation_state = 'archived';
    $entity->save();
    $this->assertCount(1, $this->getMails());
    $this->assertMail('to', implode(',', $expected));
  }

}
