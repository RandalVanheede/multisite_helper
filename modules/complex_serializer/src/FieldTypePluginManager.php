<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_complex_serializer;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\multisite_helper_complex_serializer\Attribute\FieldType as FieldTypeAttribute;
use Drupal\multisite_helper_complex_serializer\Enum\FieldType as FieldTypeEnum;

/**
 * FieldType plugin manager.
 */
class FieldTypePluginManager extends DefaultPluginManager {

  private static array $serializers = [];

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/FieldType', $namespaces, $module_handler, FieldTypeInterface::class, FieldTypeAttribute::class);
    $this->alterInfo('field_type_info');
    $this->setCacheBackend($cache_backend, 'field_type_plugins');

    // Prepare generic types ahead of time.
    foreach (FieldTypeEnum::cases() as $case) {
      static::$serializers[$case->value] = $this->createInstance($case->value);
    }
  }

  /**
   * Retrieves a serializer for the given field type.
   */
  public function getSerializerForFieldType(string $field_type): FieldTypeInterface {
    if (!isset(static::$serializers[$field_type])) {
      if (!$this->hasDefinition($field_type)) {
        return static::$serializers[FieldTypeEnum::GENERIC->value];
      }

      static::$serializers[$field_type] = $this->createInstance($field_type);
    }

    return static::$serializers[$field_type];
  }

}
