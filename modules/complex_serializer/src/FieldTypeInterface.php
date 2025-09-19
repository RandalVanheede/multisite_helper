<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_complex_serializer;

use Drupal\Core\Entity\EntityInterface;

/**
 * Interface for field_type plugins.
 */
interface FieldTypeInterface {

  const GENERIC = '_generic';

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Imports the field value to the given entity.
   */
  public function import(EntityInterface $entity, string $field_name, mixed $values, ?int $delta = NULL): bool|EntityInterface;

  /**
   * Exports the field value from the given entity.
   */
  public function export(EntityInterface $entity, string $field_name, ?int $delta = NULL): mixed;

}
