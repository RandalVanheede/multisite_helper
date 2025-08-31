<?php

namespace Drupal\multisite_helper_content\Hook;


use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\multisite_helper\MultisiteHelper;

class EntityBaseFieldInfo {

  #[Hook('entity_base_field_info')]
  public function hook(\Drupal\Core\Entity\EntityTypeInterface $entity_type): array {
    if ($entity_type->id() !== 'node') {
      return [];
    }

    return static::getSyncFields();
  }

  /**
   * Builds the field definitions for the Multisite Helper sync fields.
   */
  public static function getSyncFields(): array {
    $fields['mh_sync'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Sync to other sites'))
      ->setDescription(t('Whether this item should be synced to other subsites.'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['mh_sites'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Sites'))
      ->setDescription(t('The subsites to deploy this content item to.'))
      ->setSetting('allowed_values_function', [MultisiteHelper::class, 'getOtherSitesAsOptions'])
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['mh_source'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Source website'))
      ->setDescription(t('Which website this content originates from.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    return $fields;
  }

}
