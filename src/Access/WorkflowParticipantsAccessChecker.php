<?php

namespace Drupal\workflow_participants\Access;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Access checker for workflow participants manager form.
 */
class WorkflowParticipantsAccessChecker implements AccessInterface {

  /**
   * The workflow participant storage.
   *
   * @var \Drupal\workflow_participants\WorkflowParticipantsStorageInterface
   */
  protected $participantStorage;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * Construct the access checker.
   */
  public function __construct(ModerationInformationInterface $moderation_information, EntityTypeManagerInterface $entity_type_manager) {
    $this->moderationInfo = $moderation_information;
    $this->participantStorage = $entity_type_manager->getStorage('workflow_participants');
  }

  /**
   * Verify access for the workflow participants manager form.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param \Drupal\node\NodeInterface $node
   *   The corresponding entity to be moderated.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access.
   *
   * @todo Don't hard-code for node.
   */
  public function access(AccountInterface $account, NodeInterface $node) {
    // If this entity cannot be moderated, deny access.
    if (!$this->moderationInfo->isModeratedEntity($node)) {
      return AccessResultForbidden::forbidden()->addCacheableDependency($node);
    }

    if ($account->hasPermission('manage workflow participants')) {
      return AccessResultAllowed::allowed()->addCacheableDependency($node);
    }

    if ($node instanceof EntityOwnerInterface) {
      // Allowed if user is a participant on the current entity. Further access
      // for editors and reviewers is controlled at the form level.
      $participants = $this->participantStorage->loadForModeratedEntity($node);
      if ($participants->isEditor($account) || $participants->isReviewer($account)) {
        return AccessResult::allowed()->addCacheableDependency($node)->addCacheableDependency($participants);
      }

      // Allowed if user is the author and has appropriate permission.
      return AccessResult::allowedIfHasPermission($account, 'manage own workflow participants')->andIf(AccessResult::allowedIf($node->getOwnerId() == $account->id()))->addCacheableDependency($node);
    }

    $access = AccessResult::forbidden()->addCacheableDependency($node);
    if (isset($participants)) {
      $access->addCacheableDependency($participants);
    }
    return $access;
  }

  /**
   * Verify entity access for participants.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check.
   * @param string $operation
   *   The entity operation.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The logged in account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function hasEntityAccess(ContentEntityInterface $entity, $operation, AccountInterface $account) {
    // Hard-coded for nodes now.
    if (!$entity instanceof NodeInterface) {
      return AccessResult::neutral();
    }

    $participants = $this->participantStorage->loadForModeratedEntity($entity);
    if (!$participants->id() || (empty($participants->getEditorIds()) && empty($participants->getReviewerIds()))) {
      // No participants.
      $access = AccessResult::neutral();
      if ($participants->id()) {
        $access->addCacheableDependency($participants);
      }
      return $access;
    }

    if ($operation === 'view' && !$entity->isPublished()) {
      // Read operation, editors and reviewers can view.
      return AccessResult::allowedIf($participants->isReviewer($account) || $participants->isEditor($account))->addCacheableDependency($participants);
    }

    if ($operation === 'update') {
      // Only editors can update.
      return AccessResult::allowedIf($participants->isEditor($account))->addCacheableDependency($participants);
    }

    // Default to neutral.
    return AccessResult::neutral()->addCacheableDependency($participants);
  }

}
