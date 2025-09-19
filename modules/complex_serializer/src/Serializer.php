<?php

namespace Drupal\multisite_helper_complex_serializer;

use Drupal\Core\Entity\EntityInterface;

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
  public function serialize(EntityInterface $entity): array {
    $entity_type_processor = $this->pluginManager->getSerializerForEntityType($entity->getEntityTypeId());
    return $entity_type_processor->getBaseData($entity)
      + $entity_type_processor->export($entity);
  }

  /**
   * {@inheritDoc}
   */
  public function deserialize(array $data): ?EntityInterface {
    $entity_type_processor = $this->pluginManager->getSerializerForEntityType($data['entity_type']);
    return $entity_type_processor->import($data);
  }

}
