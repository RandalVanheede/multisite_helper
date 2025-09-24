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
   * Imports the entity data.
   */
  public function import(array $data): bool|EntityInterface;

  /**
   * Creates a stub entity for the given entity data.
   */
  public function importStub(array $data): bool|EntityInterface;

  /**
   * Exports entity to array data.
   */
  public function export(EntityInterface $entity): mixed;

  /**
   * Retrieves the base data that is necessary to import this entity.
   */
  public function getBaseData(EntityInterface $entity): array;

}
