<?php

namespace Drupal\workflow_participants\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Allow access to the latest version tab for editors and reviewers.
 */
class LatestVersionCheck implements AccessInterface {

  /**
   * The workflow participant storage.
   *
   * @var \Drupal\workflow_participants\WorkflowParticipantsStorageInterface
   */
  protected $participantStorage;

  /**
   * Constructs the latest version access checker.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->participantStorage = $entity_type_manager->getStorage('workflow_participants');
  }

  /**
   * Grant access to the latest revision for participants.
   */
  public function access(AccountInterface $account, NodeInterface $node) {
    // Need to re-check for permissions here since those are removed from the
    // route when this access checker is added.
    $permissions = ['view latest version', 'view any unpublished content'];
    $participants = $this->participantStorage->loadForModeratedEntity($node);
    return AccessResult::allowedIfHasPermissions($account, $permissions)
      ->orIf(AccessResult::allowedIf($participants->isEditor($account) || $participants->isReviewer($account))->addCacheableDependency($participants))
      ->addCacheableDependency($participants);
  }

}
