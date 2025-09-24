<?php

namespace Drupal\multisite_helper_complex_serializer\Enum;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\multisite_helper_complex_serializer\Enum\EntityType as EntityTypeEnum;

enum EntityType: string {

  /**
   * Start with underscores as to not collide with actual entity types.
   */
  case GENERIC = '_generic';
  case FIELDABLE = '_fieldable';

  public static function forEntityType(string $entity_type_id): EntityType {
    // Check if entity is fieldable.
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    if (in_array(FieldableEntityInterface::class, class_implements($entity_type->getClass()))) {
      return self::FIELDABLE;
    }
    return self::GENERIC;
  }

}
