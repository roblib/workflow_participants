<?php

namespace Drupal\workflow_participants\Entity;

use Drupal\content_moderation\ModerationStateTransitionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides an interface for defining Workflow participants entities.
 *
 * @ingroup workflow_participants
 */
interface WorkflowParticipantsInterface extends ContentEntityInterface {

  /**
   * Gets the entity being moderated.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The moderated entity.
   */
  public function getModeratedEntity();

  /**
   * Get the editor IDs.
   *
   * @return int[]
   *   An array of editor IDs.
   */
  public function getEditorIds();

  /**
   * Get the editors for the item being moderated.
   *
   * @return \Drupal\user\UserInterface[]
   *   The editors.
   */
  public function getEditors();

  /**
   * Get the reviewer IDs.
   *
   * @return int[]
   *   An array of reviewer IDs.
   */
  public function getReviewerIds();

  /**
   * Get the reviewers for the item being moderated.
   *
   * @return \Drupal\user\UserInterface[]
   *   The reviewers.
   */
  public function getReviewers();

  /**
   * Determine if a user has access to the transition.
   *
   * @param \Drupal\content_moderation\ModerationStateTransitionInterface $transition
   *   The state transition object.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   *
   * @return bool
   *   Returns TRUE if the user can make the transition.
   */
  public function userMayTransition(ModerationStateTransitionInterface $transition, AccountInterface $account);

  /**
   * Determine if the user is an editor.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current authenticated user.
   *
   * @return bool
   *   Returns TRUE if the user is an editor.
   */
  public function isEditor(AccountInterface $account);

  /**
   * Determine if the user is a reviewer.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current authenticated user.
   *
   * @return bool
   *   Returns TRUE if the user is a reviewer.
   */
  public function isReviewer(AccountInterface $account);

}
