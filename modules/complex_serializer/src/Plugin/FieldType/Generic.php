<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_complex_serializer\Plugin\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper_complex_serializer\Attribute\FieldType as FieldTypeAttribute;
use Drupal\multisite_helper_complex_serializer\Enum\FieldType as FieldTypeEnum;
use Drupal\multisite_helper_complex_serializer\FieldTypePluginBase;

/**
 * Plugin implementation of the field_type.
 */
#[FieldTypeAttribute(
  id: FieldTypeEnum::GENERIC->value,
  label: new TranslatableMarkup('Generic field'),
  description: new TranslatableMarkup('Processor for generic field types.'),
)]
final class Generic extends FieldTypePluginBase {

}
