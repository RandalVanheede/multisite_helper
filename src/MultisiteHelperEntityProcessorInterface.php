<?php

declare(strict_types=1);

namespace Drupal\multisite_helper;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Interface for multisite_helper_entity_processor plugins.
 */
interface MultisiteHelperEntityProcessorInterface extends ContainerFactoryPluginInterface {

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Returns the translated plugin description.
   */
  public function description(): string;

  /**
   * This method returns the entity type and uuid of the entity.
   */
  public function getEntityBaseInformation(array $data): array;

  /**
   * Import an entity by the exported entity data.
   */
  public function importEntity(array $data): bool;

  /**
   * Export an entity to array data.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to export.
   * @param array $extra_data
   *   Optional extra data to merge into the export.
   *
   * @return array
   *   The exported entity data.
   */
  public function exportEntity(ContentEntityInterface $entity, array $extra_data = []): array;

  /**
   * Remove an entity that has been imported before by its exported data.
   */
  public function deleteEntity(array $data): bool;

}
