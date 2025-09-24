<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_complex_serializer\Plugin\EntityType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper_complex_serializer\Attribute\EntityType as EntityTypeAttribute;
use Drupal\multisite_helper_complex_serializer\EntityTypePluginBase;
use Drupal\multisite_helper_complex_serializer\Enum\EntityType as EntityTypeEnum;

/**
 * Plugin implementation of the entity_type.
 */
#[EntityTypeAttribute(
  id: EntityTypeEnum::FIELDABLE->value,
  label: new TranslatableMarkup('Fieldable entity'),
  description: new TranslatableMarkup('Processor for fieldable entities.'),
)]
final class Fieldable extends EntityTypePluginBase {

  /**
   * {@inheritDoc}
   */
  public function import(array $data): bool|EntityInterface {
    $entity = $this->getEntity($data, stub: FALSE);

    foreach ($data['fields'] as $field_name => $field_values) {
      if (isset($field_values['_field_type']) && ($field_type = $field_values['_field_type'])) {
        unset($field_values['_field_type']);
        $serializer = $this->fieldTypePluginManager->getSerializerForFieldType($field_type);
        $serializer->import($entity, $field_name, $field_values);
      }
    }

    $entity->save();

    return $entity;
  }

  /**
   * {@inheritDoc}
   */
  public function export(EntityInterface $entity): array {
    $values = ['fields' => []];

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    foreach ($entity->getFieldDefinitions() as $field_name => $field_definition) {
      $field_type = $field_definition->getType();
      $serializer = $this->fieldTypePluginManager->getSerializerForFieldType($field_type);
      $field_values = $serializer->export($entity, $field_name);
      if (!$field_values) {
        continue;
      }
      $values['fields'][$field_name] = [
        '_field_type' => $field_type,
      ] + $serializer->export($entity, $field_name);
    }

    return $values;
  }

}
