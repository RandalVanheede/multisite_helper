<?php

namespace Drupal\multisite_helper_content\Hook;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\multisite_helper\MhSubsiteInterface;
use Drupal\multisite_helper\MultisiteHelperInterface;
use Drupal\multisite_helper\MultisiteHelperPluginManager;
use Drupal\node\NodeInterface;

class EntityHooks {

  public function __construct(
    private readonly MultisiteHelperPluginManager $pluginManager,
    private readonly MultisiteHelperInterface $helper,
  ) {}

  #[Hook('node_insert')]
  #[Hook('node_update')]
  public function save(NodeInterface $node): void {
    /** @var \Drupal\multisite_helper_content\Plugin\MultisiteHelperPlugin\ContentSync $plugin */
    $plugin = $this->pluginManager->getPlugin('content_sync');
    $plugin_config = $plugin->getConfiguration();
    if (empty($plugin_config['enabled'])) {
      return;
    }

    // Check if this website is the source website of the item, or if the sync
    // is turned off for this item.
    if (!$node->get('mh_source')->isEmpty()) {
      return;
    }

    $original = $node->getOriginal();
    if (!$node->get('mh_sync')->value) {
      if ($original && $original->get('mh_sync')->value) {
        $this->delete($original);
      }
      return;
    }

    // Calculate the sites and deleted sites lists.
    $sites = array_map(function (MhSubsiteInterface $subsite) {
      return $subsite->get('url');
    }, $node->get('mh_sites')->referencedEntities());
    $deleted_sites = [];
    if ($original) {
      $original_sites = array_map(function (MhSubsiteInterface $subsite) {
        return $subsite->get('url');
      }, $original->get('mh_sites')->referencedEntities());
      $deleted_sites = array_diff($original_sites, $sites);
    }

    // Grab the node values and add the custom mh_sync values.
    $extra_data['mh_sync'] = [['value' => 1]];
    $extra_data['mh_source'] = [
      [
        'value' => $this->helper->getCurrentSiteId(),
        'target_id' => $this->helper->getCurrentSiteId(),
        'type' => 'config',
      ],
    ];
    $extra_data['mh_sync_menu_link'] = [['value' => $node->get('mh_sync_menu_link')->value]];
    $node_values = $this->helper->exportEntity($node, $extra_data);
    if (empty($extra_data['mh_sync_menu_link'][0]['value'])) {
      unset($node_values['menu_link']);
      unset($node_values['base_fields']['menu_link']);
      unset($node_values['fields']['menu_link']);
    }
    $plugin->send($node_values, $sites);
    if ($deleted_sites) {
      $plugin->remove($node_values, $deleted_sites);
    }
  }

  #[Hook('node_delete')]
  public function delete(NodeInterface $node): void {
    /** @var \Drupal\multisite_helper_content\Plugin\MultisiteHelperPlugin\ContentSync $plugin */
    $plugin = $this->pluginManager->getPlugin('content_sync');
    $plugin_config = $plugin->getConfiguration();
    if (empty($plugin_config['enabled'])) {
      return;
    }

    if (!$node->get('mh_sync')->value) {
      return;
    }

    // Calculate the sites list.
    $sites = array_map(function (MhSubsiteInterface $subsite) {
      return $subsite->get('url');
    }, $node->get('mh_sites')->referencedEntities());

    // Grab the node values and add the custom mh_sync values.
    $extra_data['mh_sync'] = [['value' => 1]];
    $extra_data['mh_source'] = [
      [
        'value' => $this->helper->getCurrentSiteId(),
        'target_id' => $this->helper->getCurrentSiteId(),
        'type' => 'config',
      ],
    ];
    $extra_data['mh_sync_menu_link'] = [['value' => $node->get('mh_sync_menu_link')->value]];
    $node_values = $this->helper->exportEntity($node, $extra_data);
    if (empty($form_values['mh_sync_menu_link']['value'])) {
      unset($node_values['menu_link']);
      unset($node_values['base_fields']['menu_link']);
    }
    $plugin->remove($node_values, $sites);
  }

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

    $fields['mh_sites'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Subsites'))
      ->setDescription(t('The subsites to deploy this content item to.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'mh_subsite')
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['mh_source'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Source website'))
      ->setDescription(t('Which website this content originates from.'))
      ->setCardinality(1)
      ->setSetting('target_type', 'mh_subsite')
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['mh_sync_menu_link'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Sync menu link'))
      ->setDescription(t("Whether this item's menu link should be synced to other subsites."))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    return $fields;
  }

}
