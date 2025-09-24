<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_complex_serializer;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityInterface;

/**
 * Base class for field_type plugins.
 */
abstract class FieldTypePluginBase extends PluginBase implements FieldTypeInterface {

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritDoc}
   */
  public function import(EntityInterface $entity, string $field_name, mixed $values, ?int $delta = NULL): bool|EntityInterface {
    if (!method_exists($entity, 'set') || !method_exists($entity, 'get')) {
      return FALSE;
    }

    unset($values['_field_type']);
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
    if (!method_exists($entity, 'get')) {
      return FALSE;
    }

    $values = $entity->get($field_name)->getValue();
    if ($delta) {
      return $values[$delta] ?? NULL;
    }

    return $values;
  }

}
