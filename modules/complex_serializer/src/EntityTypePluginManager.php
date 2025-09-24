<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_complex_serializer;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\multisite_helper_complex_serializer\Attribute\EntityType as EntityTypeAttribute;
use Drupal\multisite_helper_complex_serializer\Enum\EntityType as EntityTypeEnum;
use Drupal\multisite_helper_complex_serializer\Traits\EntityTypeManagerTrait;

/**
 * EntityType plugin manager.
 */
class EntityTypePluginManager extends DefaultPluginManager {

  use EntityTypeManagerTrait;

  private static array $serializers = [];

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/EntityType', $namespaces, $module_handler, EntityTypeInterface::class, EntityTypeAttribute::class);
    $this->alterInfo('entity_type_info');
    $this->setCacheBackend($cache_backend, 'entity_type_plugins');

    // Prepare generic types ahead of time.
    foreach (EntityTypeEnum::cases() as $case) {
      static::$serializers[$case->value] = $this->createInstance($case->value);
    }
  }

  /**
   * Retrieves a serializer for the given entity type.
   */
  public function getSerializerForEntityType(string $entity_type_id): EntityTypeInterface {
    if (!isset(static::$serializers[$entity_type_id])) {
      if (!$this->hasDefinition($entity_type_id)) {
        return static::$serializers[EntityTypeEnum::forEntityType($entity_type_id)->value];
      }

      static::$serializers[$entity_type_id] = $this->createInstance($entity_type_id);
    }

    return static::$serializers[$entity_type_id];
  }

}
