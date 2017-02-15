<?php

namespace Drupal\workflow_participants\Routing;

use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Route subscriber to alter access for content moderation routes.
 */
class RouteSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[RoutingEvents::ALTER] = ['alterRoutes', 50];
    return $events;
  }

  /**
   * Alter content moderation route permissions callbacks.
   *
   * @param \Drupal\Core\Routing\RouteBuildEvent $event
   *   The route build event.
   */
  public function alterRoutes(RouteBuildEvent $event) {
    // @todo This is hard-coded for nodes.
    $collection = $event->getRouteCollection();
    if ($route = $collection->get('entity.node.latest_version')) {
      $route->setRequirement('_workflow_participants_latest_version', 'TRUE');

      // Unset the permission check. These permissions are checked in the new
      // requirement added above.
      $requirements = $route->getRequirements();
      unset($requirements['_permission']);
      $route->setRequirements($requirements);
    }
  }

}
