<?php

namespace Drupal\multisite_helper_complex_serializer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\multisite_helper_complex_serializer\Cache\StaticEntityCache;

readonly class Serializer implements SerializerInterface {

  /**
   * Prepare the serializers.
   */
  public function __construct(
    private EntityTypePluginManager $pluginManager,
  ) {}

  /**
   * {@inheritDoc}
   */
  public function serialize(EntityInterface $entity, bool $initial_entity = TRUE): array {
    [$entity_type_id, $uuid] = [$entity->getEntityTypeId(), $entity->uuid()];

    if (!$cached_entity_values = StaticEntityCache::getEntity($entity_type_id, $uuid)) {
      if (!$initial_entity) {
        StaticEntityCache::cacheEntity($entity_type_id, $uuid, []);
      }
      $entity_type_processor = $this->pluginManager->getSerializerForEntityType($entity->getEntityTypeId());
      $cached_entity_values = $entity_type_processor->getBaseData($entity) + $entity_type_processor->export($entity);
      if (!$initial_entity && $cached_entity_values) {
        StaticEntityCache::cacheEntity($entity_type_id, $uuid, $cached_entity_values);
      }
    }

    // Add the extra entities to the initial entity.
    if ($initial_entity) {
      $cached_entity_values['extra_entities'] = StaticEntityCache::getEntities();
    }

    return $cached_entity_values;
  }

  /**
   * {@inheritDoc}
   */
  public function deserialize(array $data): ?EntityInterface {
    if (isset($data['extra_entities'])) {
      // First, create stubs for all the extra entities.
      foreach ($data['extra_entities'] as $entity_type => $extra_entities) {
        $entity_type_processor = $this->pluginManager->getSerializerForEntityType($entity_type);
        foreach ($extra_entities as $uuid => $extra_entity) {
          $stub = $entity_type_processor->importStub($extra_entity);
          StaticEntityCache::cacheEntity($entity_type, $uuid, $stub);
        }
      }

      // Secondly, import the data into the previously created stubs.
      foreach ($data['extra_entities'] as $entity_type => $extra_entities) {
        $entity_type_processor = $this->pluginManager->getSerializerForEntityType($entity_type);
        foreach ($extra_entities as $extra_entity) {
          $entity_type_processor->import($extra_entity);
        }
      }
    }

    $entity_type_processor = $this->pluginManager->getSerializerForEntityType($data['entity_type']);
    return $entity_type_processor->import($data);
  }

}
