<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_complex_serializer\Plugin\FieldType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\Entity\File as FileEntity;
use Drupal\multisite_helper_complex_serializer\Attribute\FieldType as FieldTypeAttribute;
use Drupal\multisite_helper_complex_serializer\Cache\StaticEntityCache;

/**
 * Plugin implementation of the field_type.
 */
#[FieldTypeAttribute(
  id: 'file',
  label: new TranslatableMarkup('File field'),
  description: new TranslatableMarkup('Processor for file field types.'),
)]
class File extends EntityReference {

  /**
   * {@inheritDoc}
   */
  public function import(EntityInterface $entity, string $field_name, mixed $values, ?int $delta = NULL): bool|EntityInterface {
    if (!method_exists($entity, 'set') || !method_exists($entity, 'get')) {
      return FALSE;
    }

    // @todo

    if ($delta) {
      $entity->get($field_name)->set($delta, $values);
    }
    else {
      $entity->set($field_name, $values);
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
    return $values;

//    $refactored_values = [];
//    foreach ($values as $delta => $value) {
//      dd($values);
//      if (!$entity = FileEntity::load($value['target_id'])) {
//        continue;
//      }
//
//      if (!StaticEntityCache::hasEntity($entity->getEntityTypeId(), $entity->uuid())) {
//        \Drupal::service('multisite_helper.serializer')->serialize($entity, FALSE);
//      }
//
//      $refactored_values[$delta] = $value;
//      $refactored_values[$delta]['entity_type'] = $entity->getEntityTypeId();
//      $refactored_values[$delta]['uuid'] = $entity->uuid();
//      unset($refactored_values[$delta]['target_id']);
//    }
//
//    return $refactored_values;
  }

}
