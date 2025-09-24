<?php

namespace Drupal\multisite_helper_complex_serializer;

use Drupal\Core\Entity\EntityInterface;

interface SerializerInterface {

  /**
   * Serializes entities into a data array.
   */
  public function serialize(EntityInterface $entity, bool $initial_entity = TRUE): array;

  /**
   * Deserializes entity data back into an entity object.
   */
  public function deserialize(array $data): ?EntityInterface;

}
