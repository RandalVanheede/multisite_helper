<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_complex_serializer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Interface for entity_type plugins.
 */
interface EntityTypeInterface extends ContainerFactoryPluginInterface {

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Imports the field value to the given entity.
   */
  public function import(array $data): bool|EntityInterface;

  /**
   * Exports the field value from the given entity.
   */
  public function export(EntityInterface $entity): mixed;

  /**
   * Retrieves the base data that is necessary to import this entity.
   */
  public function getBaseData(EntityInterface $entity): array;

}
