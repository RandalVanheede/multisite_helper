<?php

namespace Drupal\multisite_helper_complex_serializer\Cache;

use Drupal\Core\Entity\EntityInterface;

class StaticEntityCache {

  /**
   * @var array|\Drupal\Core\Entity\EntityInterface[]
   */
  private static array $entities = [];

  public static function getEntities(): array {
    return static::$entities;
  }

  public static function getEntity(string $entity_type, string $uuid): null|array|EntityInterface {
    return static::$entities[$entity_type][$uuid] ?? NULL;
  }

  public static function hasEntity(string $entity_type, string $uuid): bool {
    return isset(static::$entities[$entity_type][$uuid]);
  }

  public static function cacheEntity(string $entity_type, string $uuid, array|EntityInterface $entity, bool $overwrite = TRUE): void {
    if (!$overwrite && isset(static::$entities[$entity_type][$uuid])) {
      return;
    }
    static::$entities[$entity_type][$uuid] = $entity;
  }

}
