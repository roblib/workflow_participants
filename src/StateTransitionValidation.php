<?php

namespace Drupal\workflow_participants;

use Drupal\content_moderation\Entity\ModerationStateTransition;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\ModerationStateInterface;
use Drupal\content_moderation\StateTransitionValidation as ContentModerationBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflows\TransitionInterface;

/**
 * Decorated state transition validation service.
 *
 * This overrides the base class to allow for access to transitions for
 * workflow participants.
 */
class StateTransitionValidation extends ContentModerationBase {

  /**
   * Workflow participant storage.
   *
   * @var \Drupal\workflow_participants\WorkflowParticipantsStorageInterface
   */
  protected $participantStorage;

  /**
   * Constructs the state transition validator.
   *
   * Since this is extending the decorated service, no inner service is needed.
   *
   * @todo Once ported to 8.3.x version of content moderation/workflow, a
   * better solution should be found so this can be less complicated and copy
   * less code.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_information
   *   The moderation information service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(ModerationInformationInterface $moderation_information, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($moderation_information);
    $this->participantStorage = $entity_type_manager->getStorage('workflow_participants');
  }

  /**
   * {@inheritdoc}
   */
  public function getValidTransitions(ContentEntityInterface $entity, AccountInterface $user) {
    $transitions = parent::getValidTransitions($entity, $user);

    // In addition to those granted by content moderation, check for transitions
    // that allow editor/reviewer transitions.
    return $transitions + $this->getParticipantTransitions($entity, $user);
  }

  /**
   * Get valid transitions for editors and reviewers.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The moderated entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current authenticated user.
   *
   * @return \Drupal\content_moderation\ModerationStateTransitionInterface[]
   *   The allowed state transitions.
   */
  protected function getParticipantTransitions(ContentEntityInterface $entity, AccountInterface $account) {
    if (!$entity->id()) {
      return [];
    }

    // This logic is copied from parent::getValidTransitions.
    $workflow = $this->moderationInfo->getWorkflowForEntity($entity);
    $current_state = $entity->moderation_state->value ? $workflow->getState($entity->moderation_state->value) : $workflow->getInitialState();

    // Legal transitions include those that are possible from the current state,
    // filtered by those whose target is legal on this bundle and that the
    // user has access to execute.
    $participants = $this->participantStorage->loadForModeratedEntity($entity);
    $transitions = array_filter($current_state->getTransitions(), function (TransitionInterface $transition) use ($workflow, $participants, $account) {
      $participants->userMayTransition($workflow, $transition, $account);
    });

    return $transitions;
  }

}
