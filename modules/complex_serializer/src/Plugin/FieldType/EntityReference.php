<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_complex_serializer\Plugin\FieldType;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper_complex_serializer\Attribute\FieldType as FieldTypeAttribute;
use Drupal\multisite_helper_complex_serializer\Cache\StaticEntityCache;
use Drupal\multisite_helper_complex_serializer\Traits\EntityTypeManagerTrait;

/**
 * Plugin implementation of the field_type.
 */
#[FieldTypeAttribute(
  id: 'entity_reference',
  label: new TranslatableMarkup('Entity reference field'),
  description: new TranslatableMarkup('Processor for entity reference field types.'),
)]
class EntityReference extends Generic {

  use EntityTypeManagerTrait;

  /**
   * {@inheritDoc}
   */
  public function import(EntityInterface $entity, string $field_name, mixed $values, ?int $delta = NULL): bool|EntityInterface {
    if (!method_exists($entity, 'set') || !method_exists($entity, 'get')) {
      return FALSE;
    }

    if ($delta) {
      if (!isset($values['entity_type']) || !isset($values['uuid'])) {
        $entity->get($field_name)->set($delta, NULL);
        return $entity;
      }
      $entity->get($field_name)->set($delta, $values);
    }
    else {
      $refactored_values = [];
      foreach ($values as $key => $value) {
        if (!isset($value['entity_type']) || !isset($value['uuid'])) {
          $refactored_values[$key] = NULL;
          continue;
        }

        if (!StaticEntityCache::hasEntity($value['entity_type'], $value['uuid'])) {
          $refactored_values[$key] = NULL;
          continue;
        }

        $referenced_entity = StaticEntityCache::getEntity($value['entity_type'], $value['uuid']);
        if (!$referenced_entity instanceof EntityInterface) {
          $refactored_values[$key] = NULL;
          continue;
        }
        $refactored_values[$key] = ['target_id' => $referenced_entity->id()];
      }

      $entity->set($field_name, $refactored_values);
    }

    return $entity;
  }

  /**
   * {@inheritDoc}
   */
  public function export(EntityInterface $entity, string $field_name, ?int $delta = NULL): mixed {
    if (!$values = parent::export($entity, $field_name, $delta)) {
      return FALSE;
    }

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $field_definition = $entity->getFieldDefinition($field_name);
    $entity_storage = $this->entityTypeManager()->getStorage($field_definition->getSetting('target_type'));

    // Config entities should exist on all subsites.
    if ($entity_storage instanceof ConfigEntityStorageInterface) {
      return $values;
    }

    $refactored_values = [];
    foreach ($values as $delta => $value) {
      if (!$entity = $entity_storage->load($value['target_id'])) {
        continue;
      }

      if (!StaticEntityCache::hasEntity($entity->getEntityTypeId(), $entity->uuid())) {
        \Drupal::service('multisite_helper.serializer')->serialize($entity, FALSE);
      }

      $refactored_values[$delta] = $value;
      $refactored_values[$delta]['entity_type'] = $entity->getEntityTypeId();
      $refactored_values[$delta]['uuid'] = $entity->uuid();
      unset($refactored_values[$delta]['target_id']);
    }

    return $refactored_values;
  }

}
