<?php

declare(strict_types=1);

namespace Drupal\multisite_helper_complex_serializer\Plugin\EntityType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multisite_helper_complex_serializer\Attribute\EntityType as EntityTypeAttribute;
use Drupal\multisite_helper_complex_serializer\EntityTypePluginBase;
use Drupal\multisite_helper_complex_serializer\Enum\EntityType as EntityTypeEnum;

/**
 * Plugin implementation of the entity_type.
 */
#[EntityTypeAttribute(
  id: EntityTypeEnum::GENERIC->value,
  label: new TranslatableMarkup('Generic entity'),
  description: new TranslatableMarkup('Processor for generic entities.'),
)]
final class Generic extends EntityTypePluginBase {

}
