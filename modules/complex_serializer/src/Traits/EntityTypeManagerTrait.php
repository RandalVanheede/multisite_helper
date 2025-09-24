<?php

namespace Drupal\multisite_helper_complex_serializer\Traits;

use Drupal\Core\Entity\EntityTypeManagerInterface;

trait EntityTypeManagerTrait {

  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Retrieves the entity type manager service.
   */
  protected function entityTypeManager(): EntityTypeManagerInterface {
    if (!isset($this->entityTypeManager)) {
      $this->entityTypeManager = \Drupal::entityTypeManager();
    }
    return $this->entityTypeManager;
  }

}
