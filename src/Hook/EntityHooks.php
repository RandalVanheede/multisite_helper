<?php

namespace Drupal\multisite_helper\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\multisite_helper\MultisiteHelperPluginManager;
use Drupal\single_content_sync\ContentExporterInterface;

class EntityHooks {

  public function __construct(
    private readonly MultisiteHelperPluginManager $pluginManager,
    private readonly ContentExporterInterface $contentExporter,
  ) {}

  #[Hook('entity_delete')]
  public function delete(EntityInterface $entity): void {
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    /** @var \Drupal\multisite_helper\Plugin\MultisiteHelperPlugin\ContentEntitySync $plugin */
    $plugin = $this->pluginManager->getPlugin('content_entity_sync');
    $plugin_config = $plugin->getConfiguration();
    if (empty($plugin_config['enabled'])) {
      return;
    }

    if (!in_array($entity->getEntityTypeId(), $plugin_config['entity_types'])) {
      return;
    }

    $entity_values = $this->contentExporter->doExportToArray($entity);
    $plugin->remove($entity_values);
  }

}
