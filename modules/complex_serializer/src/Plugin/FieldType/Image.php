<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_complex_serializer\Plugin\FieldType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\Entity\File as FileEntity;
use Drupal\multisite_helper_complex_serializer\Attribute\FieldType as FieldTypeAttribute;
use Drupal\multisite_helper_complex_serializer\FieldTypePluginBase;

/**
 * Plugin implementation of the field_type.
 */
#[FieldTypeAttribute(
  id: 'image',
  label: new TranslatableMarkup('Image field'),
  description: new TranslatableMarkup('Processor for image field types.'),
)]
class Image extends File {

}
