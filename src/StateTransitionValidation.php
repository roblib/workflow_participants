<?php

namespace Drupal\workflow_participants;

use Drupal\content_moderation\Entity\ModerationStateTransition;
use Drupal\content_moderation\ModerationStateInterface;
use Drupal\content_moderation\StateTransitionValidation as ContentModerationBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Session\AccountInterface;

/**
 * Decorated state transition validation service.
 *
 * This overrides the base class to allow for access to transitions for
 * workflow participants.
 */
class StateTransitionValidation extends ContentModerationBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\Query\QueryFactory $query_factory
   *   The query factory service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, QueryFactory $query_factory) {
    parent::__construct($entity_type_manager, $query_factory);
    $this->participantStorage = $entity_type_manager->getStorage('workflow_participants');
  }

  /**
   * {@inheritdoc}
   */
  public function getValidTransitionTargets(ContentEntityInterface $entity, AccountInterface $account) {
    $transitions = parent::getValidTransitionTargets($entity, $account);
    return $transitions + $this->getParticipantTargetTransitions($entity, $account);
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
   * Get valid target transitions for editors and reviewers.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The moderated entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current authenticated user.
   *
   * @return \Drupal\content_moderation\ModerationStateTransitionInterface[]
   *   The allowed state transitions.
   */
  protected function getParticipantTargetTransitions(ContentEntityInterface $entity, AccountInterface $account) {
    if (!$entity->id()) {
      return [];
    }

    // This logic is copied from parent::getValidTransitionTargets.
    $bundle = $this->loadBundleEntity($entity->getEntityType()->getBundleEntityType(), $entity->bundle());
    $states_for_bundle = $bundle->getThirdPartySetting('content_moderation', 'allowed_moderation_states', []);

    $query = \Drupal::database()->select('content_moderation_state_field_revision');
    $query->addField('content_moderation_state_field_revision', 'moderation_state');
    $query->condition('content_entity_revision_id', $entity->getRevisionId());
    $query->range(0, 1);
    $moderation_id = $query->execute()->fetchField();

    $current_state = $this->entityTypeManager
      ->getStorage('moderation_state')
      ->load($moderation_id);

    $all_transitions = $this->getPossibleTransitions();

    if (isset($all_transitions[$current_state->id()])) {
      $participants = $this->participantStorage->loadForModeratedEntity($entity);
      $destination_ids = array_intersect($states_for_bundle, $all_transitions[$current_state->id()]);
      $destinations = $this->entityTypeManager->getStorage('moderation_state')->loadMultiple($destination_ids);

      return array_filter($destinations, function (ModerationStateInterface $destination_state) use ($current_state, $account, $participants) {
        $transition = $this->getTransitionFromStates($current_state, $destination_state);
        return $participants->userMayTransition($transition, $account);
      });
    }
    else {
      return [];
    }
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
    $bundle = $this->loadBundleEntity($entity->getEntityType()->getBundleEntityType(), $entity->bundle());

    $query = \Drupal::database()->select('content_moderation_state_field_revision');
    $query->addField('content_moderation_state_field_revision', 'moderation_state');
    $query->condition('content_entity_revision_id', $entity->getRevisionId());
    $query->range(0, 1);
    $moderation_id = $query->execute()->fetchField();

    $current_state = $this->entityTypeManager
      ->getStorage('moderation_state')
      ->load($moderation_id);

    $current_state_id = $current_state ? $current_state->id() : $bundle->getThirdPartySetting('content_moderation', 'default_moderation_state');

    // Determine the states that are legal on this bundle.
    $legal_bundle_states = $bundle->getThirdPartySetting('content_moderation', 'allowed_moderation_states', []);
    // Legal transitions include those that are possible from the current state,
    // filtered by those whose target is legal on this bundle and that the
    // user has access to execute.
    $participants = $this->participantStorage->loadForModeratedEntity($entity);
    $transitions = array_filter($this->getTransitionsFrom($current_state_id), function (ModerationStateTransition $transition) use ($legal_bundle_states, $account, $participants) {
      return in_array($transition->getToState(), $legal_bundle_states, TRUE)
        && $participants->userMayTransition($transition, $account);
    });

    return $transitions;
  }

}
